<?php
/**
 * REST API Class
 *
 * @package AICommander
 */

namespace AICommander\Includes;

use AICommander\Includes\Services\ConversationService;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * REST API class.
 *
 * This class registers and handles REST API endpoints for the plugin.
 */
class RestApi {

    /**
     * The conversation service.
     *
     * @var ConversationService
     */
    private $conversation_service;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->conversation_service = new ConversationService();
        
        // Register REST API routes
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Register route for processing commands (creating a new conversation or adding to an existing one)
        register_rest_route( 'ai-commander/v1', '/command', array(
            'methods' => 'POST',
            'callback' => array( $this, 'process_command' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args' => array(
                'command' => array(
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_string( $param ) && ! empty( $param );
                    },
                ),
                'conversation_uuid' => array(
                    'required' => false,
                    'validate_callback' => function( $param ) {
                        return is_string( $param ) && ! empty( $param );
                    },
                ),
            ),
        ) );
        
        // Register route for transcribing audio
        register_rest_route( 'ai-commander/v1', '/transcribe', array(
            'methods' => 'POST',
            'callback' => array( $this, 'transcribe_audio' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
        
        // Register route for processing voice commands (transcribe + process in one request)
        register_rest_route( 'ai-commander/v1', '/voice-command', array(
            'methods' => 'POST',
            'callback' => array( $this, 'process_voice_command' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args' => array(
                'conversation_uuid' => array(
                    'required' => false,
                    'validate_callback' => function( $param ) {
                        return is_string( $param ) && ! empty( $param );
                    },
                ),
            ),
        ) );

        // Register route for getting all conversations for the current user
        register_rest_route( 'ai-commander/v1', '/conversations', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_all_conversations' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
        
        // Register route for getting a conversation
        register_rest_route( 'ai-commander/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_conversation' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args' => array(
                'uuid' => array(
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_string( $param ) && ! empty( $param );
                    },
                ),
            ),
        ) );
        
    }

    /**
     * Check if the user has permission to use the API.
     *
     * @param \WP_REST_Request $request The request object.
     * @return bool|\WP_Error True if the user has permission, \WP_Error otherwise.
     */
    public function check_permission( $request ) {
        // Check if the user is logged in and has the required capability
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to use this API.', 'ai-commander' ),
                array( 'status' => 403 )
            );
        }
        
        return true;
    }

    /**
     * Get all conversations for the current user.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response The response object.
     */
    public function get_all_conversations( $request ) {
        // Get the current user ID
        $user_id = get_current_user_id();
        
        // Get all conversations for the user
        $result = $this->conversation_service->get_user_conversations( $user_id );

        // Return the response
        return rest_ensure_response( $result );
    }

    /**
     * Get a conversation.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function get_conversation( $request ) {
        // Get the conversation UUID from the request
        $conversation_uuid = $request->get_param( 'uuid' );
        
        // Get the current user ID
        $user_id = get_current_user_id();
        
        // Get the conversation
        $result = $this->conversation_service->get_conversation( $conversation_uuid, $user_id );
        
        if ( ! $result ) {
            return new \WP_Error(
                'rest_not_found',
                __( 'Conversation not found or you do not have permission to access it.', 'ai-commander' ),
                array( 'status' => 404 )
            );
        }
        
        // Return the response
        return rest_ensure_response( $result );
    }

    /**
     * Process a command, either creating a new conversation or adding to an existing one.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function process_command( $request ) {
        // Get the command and optional conversation UUID
        $command = $request->get_param( 'command' );
        $conversation_uuid = $request->get_param( 'conversation_uuid' );
        
        // Get the current user ID
        $user_id = get_current_user_id();
        
        // Process the command (if conversation_uuid is null, a new one will be created)
        $result = $this->conversation_service->process_command( $command, $conversation_uuid, $user_id );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        // Return the response
        return rest_ensure_response( $result );
    }
    
    /**
     * Transcribe audio using the OpenAI Whisper API.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function transcribe_audio( $request ) {        
        // Check if file was uploaded
        $files = $request->get_file_params();
        if ( empty( $files['audio'] ) ) {
            return new \WP_Error(
                'missing_audio',
                __( 'No audio file provided.', 'ai-commander' ),
                array( 'status' => 400 )
            );
        }
        
        // Handle the file upload using the ConversationService
        $upload_result = $this->conversation_service->handle_audio_upload( $files['audio'] );
        
        if ( is_wp_error( $upload_result ) ) {
            return new \WP_Error(
                $upload_result->get_error_code(),
                $upload_result->get_error_message(),
                array( 'status' => 400 )
            );
        }
        
        $file_path = $upload_result['file_path'];
        
        try {
            // Transcribe the audio
            $transcription = $this->conversation_service->transcribe_audio( $file_path );
            
            // Delete the audio file after transcription
            wp_delete_file( $file_path );
            
            if ( is_wp_error( $transcription ) ) {
                return new \WP_Error(
                    'transcription_error',
                    $transcription->get_error_message(),
                    array( 'status' => 500 )
                );
            }
            
            // Return the transcription
            return rest_ensure_response( array(
                'transcription' => $transcription
            ) );
        } catch ( \Exception $e ) {
            // Delete the audio file if there was an error
            wp_delete_file( $file_path );
            
            return new \WP_Error(
                'transcription_error',
                $e->getMessage(),
                array( 'status' => 500 )
            );
        }
    }
    
    /**
     * Process a voice command by transcribing audio and then processing the transcribed text.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function process_voice_command( $request ) {
        // Get the conversation UUID if provided
        $conversation_uuid = $request->get_param( 'conversation_uuid' );
        
        // Check if file was uploaded
        $files = $request->get_file_params();
        if ( empty( $files['audio'] ) ) {
            return new \WP_Error(
                'missing_audio',
                __( 'No audio file provided.', 'ai-commander' ),
                array( 'status' => 400 )
            );
        }
        
        // Handle the file upload using the ConversationService
        $upload_result = $this->conversation_service->handle_audio_upload( $files['audio'] );
        
        if ( is_wp_error( $upload_result ) ) {
            return new \WP_Error(
                $upload_result->get_error_code(),
                $upload_result->get_error_message(),
                array( 'status' => 400 )
            );
        }
        
        $file_path = $upload_result['file_path'];
        
        try {
            // Get the current user ID
            $user_id = get_current_user_id();
            
            // Process the voice command
            $result = $this->conversation_service->process_voice_command( $file_path, $conversation_uuid, $user_id );
            
            // Delete the audio file after processing
            wp_delete_file( $file_path );
            
            if ( ! $result || isset( $result['success'] ) && $result['success'] === false ) {
                $message = isset( $result['message'] ) ? $result['message'] : 'Unknown error';
                return new \WP_Error(
                    'voice_command_error',
                    $message,
                    array( 'status' => 500 )
                );
            }
            
            // Return the result
            return rest_ensure_response( $result );
        } catch ( \Exception $e ) {
            // Delete the audio file if there was an error
            wp_delete_file( $file_path );
            
            return new \WP_Error(
                'voice_command_error',
                $e->getMessage(),
                array( 'status' => 500 )
            );
        }
    }
}
