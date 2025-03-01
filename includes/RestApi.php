<?php
/**
 * REST API Class
 *
 * @package WPNL
 */

namespace WPNL\Includes;

use WPNL\Includes\Services\ConversationService;

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
        register_rest_route( 'wpnl/v1', '/command', array(
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
        register_rest_route( 'wpnl/v1', '/transcribe', array(
            'methods' => 'POST',
            'callback' => array( $this, 'transcribe_audio' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
        
        // Register route for processing voice commands (transcribe + process in one request)
        register_rest_route( 'wpnl/v1', '/voice-command', array(
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
        register_rest_route( 'wpnl/v1', '/conversations', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_all_conversations' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
        
        // Register route for getting a conversation
        register_rest_route( 'wpnl/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)', array(
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
                __( 'You do not have permission to use this API.', 'wpnl' ),
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
                __( 'Conversation not found or you do not have permission to access it.', 'wpnl' ),
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
        
        // If conversation_uuid was provided but result is false, the conversation wasn't found
        if ( $conversation_uuid && ! $result ) {
            return new \WP_Error(
                'rest_not_found',
                __( 'Conversation not found or you do not have permission to access it.', 'wpnl' ),
                array( 'status' => 404 )
            );
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
        // Check if speech-to-text is enabled
        $enable_speech = get_option( 'wpnl_enable_speech_to_text', true );
        if ( ! $enable_speech ) {
            return new \WP_Error(
                'speech_disabled',
                __( 'Speech-to-text is disabled in settings.', 'wpnl' ),
                array( 'status' => 400 )
            );
        }
        
        // Get the language parameter if provided
        $language = $request->get_param( 'language' );
        
        // Check if file was uploaded
        $files = $request->get_file_params();
        if ( empty( $files['audio'] ) ) {
            return new \WP_Error(
                'missing_audio',
                __( 'No audio file provided.', 'wpnl' ),
                array( 'status' => 400 )
            );
        }
        
        $file = $files['audio'];
        
        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            $error_message = $this->get_upload_error_message( $file['error'] );
            return new \WP_Error(
                'upload_error',
                $error_message,
                array( 'status' => 400 )
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
        
        // Generate a unique filename
        $filename = 'audio-' . uniqid() . '.webm';
        $file_path = $audio_dir . '/' . $filename;
        
        // Move the uploaded file to our directory
        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            return new \WP_Error(
                'file_save_error',
                __( 'Failed to save audio file.', 'wpnl' ),
                array( 'status' => 500 )
            );
        }
        
        try {
            // Transcribe the audio
            $transcription = $this->conversation_service->transcribe_audio( $file_path, $language );
            
            // Delete the audio file after transcription
            @unlink( $file_path );
            
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
            @unlink( $file_path );
            
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
        // Check if speech-to-text is enabled
        $enable_speech = get_option( 'wpnl_enable_speech_to_text', true );
        if ( ! $enable_speech ) {
            return new \WP_Error(
                'speech_disabled',
                __( 'Speech-to-text is disabled in settings.', 'wpnl' ),
                array( 'status' => 400 )
            );
        }
        
        // Get the conversation UUID if provided
        $conversation_uuid = $request->get_param( 'conversation_uuid' );
        
        // Get the language parameter if provided
        $language = $request->get_param( 'language' );
        
        // Check if file was uploaded
        $files = $request->get_file_params();
        if ( empty( $files['audio'] ) ) {
            return new \WP_Error(
                'missing_audio',
                __( 'No audio file provided.', 'wpnl' ),
                array( 'status' => 400 )
            );
        }
        
        $file = $files['audio'];
        
        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            $error_message = $this->get_upload_error_message( $file['error'] );
            return new \WP_Error(
                'upload_error',
                $error_message,
                array( 'status' => 400 )
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
        
        // Generate a unique filename
        $filename = 'audio-' . uniqid() . '.webm';
        $file_path = $audio_dir . '/' . $filename;
        
        // Move the uploaded file to our directory
        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            return new \WP_Error(
                'file_save_error',
                __( 'Failed to save audio file.', 'wpnl' ),
                array( 'status' => 500 )
            );
        }
        
        try {
            // Get the current user ID
            $user_id = get_current_user_id();
            
            // Process the voice command
            $result = $this->conversation_service->process_voice_command( $file_path, $conversation_uuid, $user_id, $language );
            
            // Delete the audio file after processing
            @unlink( $file_path );
            
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
            @unlink( $file_path );
            
            return new \WP_Error(
                'voice_command_error',
                $e->getMessage(),
                array( 'status' => 500 )
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
                return __( 'The uploaded file exceeds the upload_max_filesize directive in php.ini', 'wpnl' );
            case UPLOAD_ERR_FORM_SIZE:
                return __( 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', 'wpnl' );
            case UPLOAD_ERR_PARTIAL:
                return __( 'The uploaded file was only partially uploaded', 'wpnl' );
            case UPLOAD_ERR_NO_FILE:
                return __( 'No file was uploaded', 'wpnl' );
            case UPLOAD_ERR_NO_TMP_DIR:
                return __( 'Missing a temporary folder', 'wpnl' );
            case UPLOAD_ERR_CANT_WRITE:
                return __( 'Failed to write file to disk', 'wpnl' );
            case UPLOAD_ERR_EXTENSION:
                return __( 'A PHP extension stopped the file upload', 'wpnl' );
            default:
                return __( 'Unknown upload error', 'wpnl' );
        }
    }
}
