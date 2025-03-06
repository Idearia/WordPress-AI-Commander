<?php
/**
 * AJAX Handlers Class
 *
 * @package WPNL
 */

namespace WPNL\Includes;

use WPNL\Includes\Services\ConversationService;
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
        add_action( 'wp_ajax_wpnl_create_conversation', array( $this, 'create_conversation' ) );
        add_action( 'wp_ajax_wpnl_get_conversation', array( $this, 'get_conversation' ) );
        add_action( 'wp_ajax_wpnl_process_command', array( $this, 'process_command' ) );
        add_action( 'wp_ajax_wpnl_execute_tool', array( $this, 'execute_tool' ) );
        add_action( 'wp_ajax_wpnl_transcribe_audio', array( $this, 'transcribe_audio' ) );
    }

    /**
     * AJAX handler for creating a new conversation.
     */
    public function create_conversation() {
        // Check nonce for security
        check_ajax_referer( 'wpnl_nonce', 'nonce' );
        
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
        check_ajax_referer( 'wpnl_nonce', 'nonce' );
        
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
        check_ajax_referer( 'wpnl_nonce', 'nonce' );
        
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
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        
        // Return the result
        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for executing tools.
     */
    public function execute_tool() {
        // Check nonce for security
        check_ajax_referer( 'wpnl_nonce', 'nonce' );
        
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
        check_ajax_referer( 'wpnl_nonce', 'nonce' );
        
        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }
        
        // Check if speech-to-text is enabled
        $enable_speech = get_option( 'wpnl_enable_speech_to_text', true );
        if ( ! $enable_speech ) {
            wp_send_json_error( array( 'message' => 'Speech-to-text is disabled in settings' ) );
        }
        
        // Check if file was uploaded
        if ( empty( $_FILES['audio'] ) ) {
            wp_send_json_error( array( 'message' => 'No audio file provided' ) );
        }
        
        // Get the language setting
        $language = get_option( 'wpnl_speech_language', '' );
        
        // Handle the file upload using the ConversationService
        $upload_result = $this->conversation_service->handle_audio_upload( $_FILES['audio'] );
        
        if ( is_wp_error( $upload_result ) ) {
            wp_send_json_error( array( 'message' => $upload_result->get_error_message() ) );
        }
        
        $file_path = $upload_result['file_path'];
        
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
}
