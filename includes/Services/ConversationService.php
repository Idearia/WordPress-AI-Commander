<?php
/**
 * Methods used by AJAX handlers and REST API endpoints,
 * grouped here to avoid code duplication.
 *
 * @package AICommander
 */

namespace AICommander\Includes\Services;

use AICommander\Includes\ConversationManager;
use AICommander\Includes\CommandProcessor;
use AICommander\Includes\OpenaiClient;
use AICommander\Includes\Services\PromptService;
use Exception;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Conversation Service class.
 *
 * This class provides a service layer for conversation-related operations,
 * which can be used by both AJAX handlers and REST API endpoints.
 */
class ConversationService {
    /**
     * The conversation manager.
     *
     * @var ConversationManager
     */
    private $conversation_manager;

    /**
     * The command processor.
     *
     * @var CommandProcessor
     */
    private $command_processor;

    /**
     * The OpenAI client.
     *
     * @var OpenaiClient
     */
    private $openai_client;

    /**
     * The Prompt service.
     *
     * @var PromptService
     */
    private $prompt_service;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->conversation_manager = new ConversationManager();
        $this->command_processor = new CommandProcessor();
        $this->openai_client = new OpenaiClient();
        $this->prompt_service = new PromptService();
    }

    /**
     * Create a new conversation.
     *
     * @param int $user_id The WordPress user ID.
     * @return array The conversation data.
     */
    public function create_conversation( $user_id ) {
        $conversation_uuid = $this->conversation_manager->create_conversation( $user_id );

        $messages = $this->conversation_manager->format_for_frontend( $conversation_uuid, true );

        return array(
            'conversation_uuid' => $conversation_uuid,
            'messages' => $messages
        );
    }

    /**
     * Get a conversation.
     *
     * The result will contain a $messages array formatted for OpenAI's
     * chat completion API, and a $messages_for_frontend array containing
     * extra info (e.g. a summary for each tool call).
     *
     * @param string $conversation_uuid The conversation UUID.
     * @param int $user_id The WordPress user ID.
     * @return array|false The conversation data, or false if not found or not authorized.
     */
    public function get_conversation( $conversation_uuid, $user_id ) {
        $conversation = $this->conversation_manager->get_conversation( $conversation_uuid );

        if ( ! $conversation ) {
            return false;
        }

        if ( $conversation->user_id != $user_id ) {
            return false;
        }

        $messages = $this->conversation_manager->format_for_openai( $conversation_uuid );

        $messages_for_frontend = $this->conversation_manager->format_for_frontend( $conversation_uuid, true );

        return array(
            'conversation_uuid' => $conversation_uuid,
            'messages' => $messages,
            'messages_for_frontend' => $messages_for_frontend
        );
    }

    /**
     * Get all conversations for a user.
     *
     * @param int $user_id The WordPress user ID.
     * @return array|\WP_Error The array of conversation data, or an error if the conversations could not be retrieved.
     */
    public function get_user_conversations( $user_id ) {
        $conversations = $this->conversation_manager->get_user_conversations( $user_id );
        $formatted_conversations = array();

        foreach ( $conversations as $conversation ) {
            // Get the first user message to use as a preview
            $messages = $this->conversation_manager->get_messages( $conversation->conversation_uuid );
            if ( is_wp_error( $messages ) ) {
                return $messages;
            }

            $preview = '';

            foreach ( $messages as $message ) {
                if ( $message->role === 'user' ) {
                    $preview = $message->content;
                    break;
                }
            }

            // Truncate preview if it's too long
            if ( strlen( $preview ) > 100 ) {
                $preview = substr( $preview, 0, 97 ) . '...';
            }

            $formatted_conversations[] = array(
                'conversation_uuid' => $conversation->conversation_uuid,
                'created_at' => $conversation->created_at,
                'updated_at' => $conversation->updated_at,
                'preview' => $preview
            );
        }

        return $formatted_conversations;
    }

    /**
     * Process a command.
     *
     * @param string $command The command to process.
     * @param string|null $conversation_uuid The conversation UUID. If null, a new conversation will be created.
     * @param int|null $user_id The WordPress user ID.
     * @return array|\WP_Error The result of processing the command, or an error if something went wrong (e.g. conversation not found or user not authorized).
     */
    public function process_command( $command, $conversation_uuid = null, $user_id = null ) {
        // If a conversation UUID is provided, verify ownership
        if ( ! empty( $conversation_uuid ) ) {
            $conversation = $this->conversation_manager->get_conversation( $conversation_uuid );

            if ( ! $conversation ) {
                return new \WP_Error(
                    'conversation_not_found',
                    'Conversation not found'
                );
            }

            if ( $user_id !== null && $conversation->user_id != $user_id ) {
                return new \WP_Error(
                    'conversation_not_authorized',
                    'Conversation not authorized'
                );
            }
        }

        return $this->command_processor->process(
            $command,
            get_option( 'ai_commander_openai_chat_model', 'gpt-4o' ),
            $conversation_uuid
        );
    }

    /**
     * Handle audio file upload.
     *
     * @param array $file The uploaded file data from $_FILES.
     * @return array|\WP_Error Array with file_path on success, \WP_Error on failure.
     */
    public function handle_audio_upload( $file ) {
        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            $error_message = $this->get_upload_error_message( $file['error'] );
            return new \WP_Error(
                'upload_error',
                $error_message
            );
        }

        // Move the file to the upload directory
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }
        add_filter('upload_dir', [ static::class, 'get_audio_upload_dir' ]);
        $movefile = \wp_handle_upload($file, array( 'test_form' => false ) );
        remove_filter('upload_dir', [ static::class, 'get_audio_upload_dir' ]);

        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $file_info = pathinfo( $movefile['file'] );
            $extension = isset( $file_info['extension'] ) ? strtolower( $file_info['extension'] ) : 'tmp';
            return array(
                'file_path' => $movefile['file'],
                'extension' => $extension
            );
        } else {
            return new \WP_Error(
                'file_save_error',
                isset( $movefile['error'] ) ? $movefile['error'] : 'Failed to save audio file'
            );
        }
    }

    /**
     * Get a human-readable error message for file upload errors.
     *
     * @param int $error_code The error code from $_FILES['file']['error'].
     * @return string The error message.
     */
    private function get_upload_error_message( $error_code ) {
        switch ( $error_code ) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Filter callback to modify the upload directory for audio files.
     *
     * @param array $uploads The array of upload directory data.
     * @return array The modified array of upload directory data.
     */
    public static function get_audio_upload_dir( $uploads ) {
        $uploads['subdir'] = '/ai-commander/audio';
        $uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
        $uploads['url'] = $uploads['baseurl'] . $uploads['subdir'];

        // Create directory if it doesn't exist
        if ( ! file_exists( $uploads['path'] ) ) {
            wp_mkdir_p( $uploads['path'] );
            file_put_contents( $uploads['path'] . '/index.php', '<?php // Silence is golden' );
        }

        return $uploads;
    }

    /**
     * Transcribe audio file using OpenAI TranscriptionAPI.
     *
     * @param string $audio_file_path The path to the audio file.
     * @return string|\WP_Error The transcribed text or an error.
     */
    public function transcribe_audio( $audio_file_path ) {
        // Get parameter
        $model = get_option( 'ai_commander_openai_transcription_model', 'gpt-4o-transcribe' );
        $language = get_option( 'ai_commander_chatbot_speech_language', '' );

        // Validate the file
        if ( ! file_exists( $audio_file_path ) ) {
            return new \WP_Error(
                'file_not_found',
                'Audio file not found'
            );
        }

        try {
            // Transcribe the audio using the OpenAI client
            $transcription = $this->openai_client->transcribe_audio( $audio_file_path, $model, $language );

            if ( is_wp_error( $transcription ) ) {
                return $transcription;
            }

            return $transcription;
        } catch ( Exception $e ) {
            return new \WP_Error(
                'transcription_error',
                $e->getMessage()
            );
        }
    }

    /**
     * Process a voice command by transcribing audio and then processing the transcribed text.
     *
     * @param string $audio_file_path The path to the audio file.
     * @param string|null $conversation_uuid The conversation UUID. If null, a new conversation will be created.
     * @param int|null $user_id The WordPress user ID.
     * @return array The result containing transcription and command processing results.
     */
    public function process_voice_command( $audio_file_path, $conversation_uuid = null, $user_id = null ) {
        // Transcribe the audio
        $transcription = $this->transcribe_audio( $audio_file_path );

        if ( is_wp_error( $transcription ) ) {
            return array(
                'success' => false,
                'message' => $transcription->get_error_message(),
            );
        }

        // Process the transcribed text as a command
        $result = $this->process_command( $transcription, $conversation_uuid, $user_id );

        if ( ! $result ) {
            return array(
                'success' => false,
                'message' => 'Conversation not found or you do not have permission to access it',
                'transcription' => $transcription,
            );
        }

        // Add the transcription to the result
        $result['transcription'] = $transcription;

        return $result;
    }

    /**
     * Convert text to speech using OpenAI TTS API.
     *
     * @param string $text The text to convert to speech.
     * @return array|\WP_Error Audio data and mime type or error.
     */
    public function read_text($text) {
        if (empty($text)) {
            return new \WP_Error(
                'empty_text',
                'Text to convert to speech cannot be empty.'
            );
        }

        $voice = get_option( 'ai_commander_realtime_voice', 'verse' );
        $model = get_option( 'ai_commander_openai_speech_model', 'gpt-4o-mini-tts' );
        $instructions = $this->prompt_service->get_tts_instructions() ?: null;  // Ignored for tts-1 models
        $speed = 1; // Ignored for gpt-4o-mini-tts

        // Convert text to speech using the OpenAI client with binary data return
        return $this->openai_client->read_text(
            $text, $voice, $model, $instructions, $speed, true
        );
    }

    /**
     * Create a realtime session with OpenAI.
     *
     * @return array|\WP_Error The session data or an error.
     */
    public function create_realtime_session() {
        // In custom TTS mode, we do not need the model to output audio
        $custom_tts_enabled = get_option('ai_commander_use_custom_tts', false);
        $modalities = ['text'];
        if (!$custom_tts_enabled) {
            $modalities[] = 'audio';
        }

        // Get the noise reduction type from the settings
        $noise_reduction_type = get_option('ai_commander_realtime_input_audio_noise_reduction', 'far_field');
        $input_audio_noise_reduction = null;
        if ($noise_reduction_type !== 'none') {
            $input_audio_noise_reduction = [
                'type' => $noise_reduction_type,
            ];
        }

        // Get tool registry instance
        $tool_registry = \AICommander\Includes\ToolRegistry::get_instance();

        // Create session parameters
        $session_params = [
            'model' => get_option('ai_commander_openai_realtime_model', 'gpt-4o-realtime-preview-2025-06-03'),
            'voice' => get_option('ai_commander_realtime_voice', 'verse'),
            'instructions' => $this->prompt_service->get_realtime_system_prompt(),
            'tools' => $tool_registry->get_tool_definitions('realtime'),
            'tool_choice' => 'auto',
            'modalities' => $modalities,
            'input_audio_noise_reduction' => $input_audio_noise_reduction,
            // 'max_response_output_tokens' => 4096, // TODO: make this configurable
            // 'speed' => 1.5, // TODO: make this configurable
            // 'temperature' => 0.8,  // TODO: make this configurable
        ];

        // Add input audio transcription only if enabled in settings
        $input_transcription_enabled = get_option('ai_commander_realtime_input_transcription', false);
        if ($input_transcription_enabled) {
            $session_params['input_audio_transcription'] = [
                "language" => get_option('ai_commander_chatbot_speech_language', ''),
                "model" => get_option('ai_commander_openai_transcription_model', 'gpt-4o-transcribe')
                // "prompt" => "expect words related to technology" TODO: make this configurable (only for gpt-4o-transcribe)
            ];
        }

        // Create the Realtime session
        return $this->openai_client->create_realtime_session($session_params);
    }

    /**
     * Execute a realtime tool.
     *
     * @param string $tool_name The name of the tool to execute.
     * @param array $params The parameters for the tool.
     * @return array|\WP_Error The result of the tool execution or an error.
     */
    public function execute_realtime_tool($tool_name, $params) {
        if (empty($tool_name)) {
            return new \WP_Error(
                'missing_tool_name',
                'No tool name specified.'
            );
        }

        if (!is_array($params)) {
            return new \WP_Error(
                'invalid_params',
                'Tool parameters must be an array.'
            );
        }

        // Get tool registry instance
        $tool_registry = \AICommander\Includes\ToolRegistry::get_instance();

        // Check if the tool exists
        if (!$tool_registry->has_tool($tool_name)) {
            return new \WP_Error(
                'tool_not_found',
                'Tool not found: ' . $tool_name
            );
        }

        // Execute the tool - This method includes the capability check based on the tool's requirement
        return $tool_registry->execute_tool($tool_name, $params);
    }
}
