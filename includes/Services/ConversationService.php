<?php
/**
 * Conversation Service Class
 *
 * @package WPNL
 */

namespace WPNL\Includes\Services;

use WPNL\Includes\ConversationManager;
use WPNL\Includes\CommandProcessor;
use WPNL\Includes\OpenaiClient;
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
     * Constructor.
     */
    public function __construct() {
        $this->conversation_manager = new ConversationManager();
        $this->command_processor = new CommandProcessor();
        $this->openai_client = new OpenaiClient();
    }

    /**
     * Create a new conversation.
     *
     * @param int $user_id The WordPress user ID.
     * @return array The conversation data.
     */
    public function create_conversation( $user_id ) {
        $conversation_uuid = $this->conversation_manager->create_conversation( $user_id );
        $messages = $this->conversation_manager->format_for_frontend( $conversation_uuid );
        
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
        $messages_for_frontend = $this->conversation_manager->format_for_frontend( $conversation_uuid );
        
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
     * @return array The array of conversation data.
     */
    public function get_user_conversations( $user_id ) {
        $conversations = $this->conversation_manager->get_user_conversations( $user_id );
        $formatted_conversations = array();
        
        foreach ( $conversations as $conversation ) {
            // Get the first user message to use as a preview
            $messages = $this->conversation_manager->get_messages( $conversation->conversation_uuid );
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
     * @return array|false The result of processing the command, or false if either the user or the conversation are not found (or not authorized.
     */
    public function process_command( $command, $conversation_uuid = null, $user_id = null ) {
        // If a conversation UUID is provided, verify ownership
        if ( ! empty( $conversation_uuid ) ) {
            $conversation = $this->conversation_manager->get_conversation( $conversation_uuid );
            
            if ( ! $conversation ) {
                return false;
            }
            
            if ( $user_id !== null && $conversation->user_id != $user_id ) {
                return false;
            }
        }
        
        // Process the command - if conversation_uuid is null, a new one will be created
        // inside the CommandProcessor
        return $this->command_processor->process( $command, $conversation_uuid );
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
        
        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $audio_dir = $upload_dir['basedir'] . '/wpnl-audio';
        
        if ( ! file_exists( $audio_dir ) ) {
            wp_mkdir_p( $audio_dir );
            
            // Create an index.php file to prevent directory listing
            file_put_contents( $audio_dir . '/index.php', '<?php // Silence is golden' );
        }
        
        // Get the original file extension
        $file_info = pathinfo( $file['name'] );
        $extension = isset( $file_info['extension'] ) ? strtolower( $file_info['extension'] ) : 'tmp';
        
        // Generate a unique filename while preserving the original extension
        $filename = 'audio-' . uniqid() . '.' . $extension;
        $file_path = $audio_dir . '/' . $filename;
        
        // Move the uploaded file to our directory
        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            return new \WP_Error(
                'file_save_error',
                'Failed to save audio file'
            );
        }
        
        return array(
            'file_path' => $file_path,
            'extension' => $extension
        );
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
     * Transcribe audio file using OpenAI Whisper API.
     *
     * @param string $audio_file_path The path to the audio file.
     * @param string $language Optional language code to improve transcription accuracy.
     * @return string|\WP_Error The transcribed text or an error.
     */
    public function transcribe_audio( $audio_file_path, $language = null ) {
        // Check if speech-to-text is enabled
        $enable_speech = get_option( 'wpnl_enable_speech_to_text', true );
        if ( ! $enable_speech ) {
            return new \WP_Error(
                'speech_disabled',
                'Speech-to-text is disabled in settings'
            );
        }
        
        // Validate the file
        if ( ! file_exists( $audio_file_path ) ) {
            return new \WP_Error(
                'file_not_found',
                'Audio file not found'
            );
        }
        
        try {
            // Transcribe the audio using the OpenAI client
            $transcription = $this->openai_client->transcribe_audio( $audio_file_path, $language );
            
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
     * @param string|null $language Optional language code to improve transcription accuracy.
     * @return array The result containing transcription and command processing results.
     */
    public function process_voice_command( $audio_file_path, $conversation_uuid = null, $user_id = null, $language = null ) {
        // Transcribe the audio
        $transcription = $this->transcribe_audio( $audio_file_path, $language );
        
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
}
