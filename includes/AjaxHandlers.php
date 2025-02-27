<?php
/**
 * AJAX Handlers Class
 *
 * @package WP_Natural_Language_Commands
 */

namespace WPNaturalLanguageCommands\Includes;

use WPNaturalLanguageCommands\Includes\Services\ConversationService;

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
}
