<?php
/**
 * AJAX Handlers Class
 *
 * @package WP_Natural_Language_Commands
 */

namespace WPNaturalLanguageCommands\Includes;

use WPNaturalLanguageCommands\Includes\Services\ConversationService;
use Exception;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * AJAX Handlers class.
 *
 * This class handles all AJAX requests for the plugin.
 */
class AjaxHandlers {

    /**
     * The conversation service.
     *
     * @var ConversationService
     */
    private $conversation_service;

    /**
     * The tool registry.
     *
     * @var ToolRegistry
     */
    private $tool_registry;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->conversation_service = new ConversationService();
        $this->tool_registry = ToolRegistry::get_instance();
        
        // Register AJAX handlers
        add_action( 'wp_ajax_wp_nlc_create_conversation', array( $this, 'create_conversation' ) );
        add_action( 'wp_ajax_wp_nlc_get_conversation', array( $this, 'get_conversation' ) );
        add_action( 'wp_ajax_wp_nlc_process_command', array( $this, 'process_command' ) );
        add_action( 'wp_ajax_wp_nlc_execute_tool', array( $this, 'execute_tool' ) );
        add_action( 'wp_ajax_wp_nlc_transcribe_audio', array( $this, 'transcribe_audio' ) );
    }

    /**
     * AJAX handler for creating a new conversation.
     */
    public function create_conversation() {
        // Check nonce for security
        check_ajax_referer( 'wp_nlc_nonce', 'nonce' );
        
        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }
        
        // Get current user ID
        $user_id = get_current_user_id();
        
        // Create a new conversation using the service
        $result = $this->conversation_service->create_conversation( $user_id );
        
        // Return the result
        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for getting an existing conversation.
     */
    public function get_conversation() {
        // Check nonce for security
        check_ajax_referer( 'wp_nlc_nonce', 'nonce' );
        
        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }
        
        // Get the conversation UUID from the request
        $conversation_uuid = isset( $_POST['conversation_uuid'] ) ? sanitize_text_field( $_POST['conversation_uuid'] ) : '';
        
        if ( empty( $conversation_uuid ) ) {
            wp_send_json_error( array( 'message' => 'No conversation UUID provided' ) );
        }
        
        // Get the current user ID
        $user_id = get_current_user_id();
        
        // Get the conversation using the service
        $result = $this->conversation_service->get_conversation( $conversation_uuid, $user_id );
        
        if ( ! $result ) {
            wp_send_json_error( array( 'message' => 'Conversation not found or you do not have permission to access it' ) );
        }
        
        // Return the result
        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for processing chatbot commands.
     */
    public function process_command() {
        // Check nonce for security
        check_ajax_referer( 'wp_nlc_nonce', 'nonce' );
        
        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }
        
        // Get the command from the request
        $command = isset( $_POST['command'] ) ? sanitize_text_field( $_POST['command'] ) : '';
        $conversation_uuid = isset( $_POST['conversation_uuid'] ) ? sanitize_text_field( $_POST['conversation_uuid'] ) : null;
        
        if ( empty( $command ) ) {
            wp_send_json_error( array( 'message' => 'No command provided' ) );
        }
        
        // Get the current user ID
        $user_id = get_current_user_id();
        
        // Process the command using the service
        $result = $this->conversation_service->process_command( $command, $conversation_uuid, $user_id );
        
        if ( ! $result ) {
            wp_send_json_error( array( 'message' => 'Conversation not found or you do not have permission to access it' ) );
        }
        
        // Return the result
        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for executing tools.
     */
    public function execute_tool() {
        // Check nonce for security
        check_ajax_referer( 'wp_nlc_nonce', 'nonce' );
        
        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }
        
        // Get the tool and parameters from the request
        $tool = isset( $_POST['tool'] ) ? sanitize_text_field( $_POST['tool'] ) : '';
        $params = isset( $_POST['params'] ) ? $_POST['params'] : array();
        
        if ( empty( $tool ) ) {
            wp_send_json_error( array( 'message' => 'No tool specified' ) );
        }
        
        // Check if the tool exists
        if ( ! $this->tool_registry->has_tool( $tool ) ) {
            wp_send_json_error( array( 'message' => 'Tool not found: ' . $tool ) );
        }
        
        // Execute the tool
        try {
            $result = $this->tool_registry->execute_tool( $tool, $params );
            wp_send_json_success( $result );
        } catch ( \Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }
    
    /**
     * AJAX handler for transcribing audio using OpenAI Whisper API.
     */
    public function transcribe_audio() {
        // Check nonce for security
        check_ajax_referer( 'wp_nlc_nonce', 'nonce' );
        
        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }
        
        // Check if speech-to-text is enabled
        $enable_speech = get_option( 'wp_nlc_enable_speech_to_text', true );
        if ( ! $enable_speech ) {
            wp_send_json_error( array( 'message' => 'Speech-to-text is disabled in settings' ) );
        }
        
        // Check if file was uploaded
        if ( empty( $_FILES['audio'] ) ) {
            wp_send_json_error( array( 'message' => 'No audio file provided' ) );
        }
        
        // Get the language setting
        $language = get_option( 'wp_nlc_speech_language', '' );
        
        // Get the uploaded file
        $file = $_FILES['audio'];
        
        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            $error_message = $this->get_upload_error_message( $file['error'] );
            wp_send_json_error( array( 'message' => $error_message ) );
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $audio_dir = $upload_dir['basedir'] . '/wp-nlc-audio';
        
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
            wp_send_json_error( array( 'message' => 'Failed to save audio file' ) );
        }
        
        try {
            // Use the ConversationService to transcribe the audio
            $transcription = $this->conversation_service->transcribe_audio( $file_path, $language );
            
            // Delete the audio file after transcription
            @unlink( $file_path );
            
            if ( is_wp_error( $transcription ) ) {
                wp_send_json_error( array( 'message' => $transcription->get_error_message() ) );
            }
            
            // Return the transcription
            wp_send_json_success( array( 'transcription' => $transcription ) );
        } catch ( Exception $e ) {
            // Delete the audio file if there was an error
            @unlink( $file_path );
            
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
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
}
