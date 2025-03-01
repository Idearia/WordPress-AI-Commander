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
