<?php
/**
 * REST API Class
 *
 * @package WP_Natural_Language_Commands
 */

namespace WPNaturalLanguageCommands\Includes;

use WPNaturalLanguageCommands\Includes\Services\ConversationService;

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
        // Register route for creating a new conversation with a command
        register_rest_route( 'wp-nlc/v1', '/conversations', array(
            'methods' => 'POST',
            'callback' => array( $this, 'create_conversation' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args' => array(
                'command' => array(
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_string( $param ) && ! empty( $param );
                    },
                ),
            ),
        ) );

        // Register route for getting all conversations for the current user
        register_rest_route( 'wp-nlc/v1', '/conversations', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_all_conversations' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
        
        // Register route for getting a conversation
        register_rest_route( 'wp-nlc/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)', array(
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
        
        // Register route for adding a command to an existing conversation
        register_rest_route( 'wp-nlc/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)/commands', array(
            'methods' => 'POST',
            'callback' => array( $this, 'add_command_to_conversation' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args' => array(
                'uuid' => array(
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_string( $param ) && ! empty( $param );
                    },
                ),
                'command' => array(
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
                __( 'You do not have permission to use this API.', 'wp-natural-language-commands' ),
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
                __( 'Conversation not found or you do not have permission to access it.', 'wp-natural-language-commands' ),
                array( 'status' => 404 )
            );
        }
        
        // Return the response
        return rest_ensure_response( $result );
    }

    /**
     * Add a command to an existing conversation.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function add_command_to_conversation( $request ) {
        // Get the conversation UUID and command from the request
        $conversation_uuid = $request->get_param( 'uuid' );
        $command = $request->get_param( 'command' );

        // Get the current user ID
        $user_id = get_current_user_id();
        
        // Process the command in the existing conversation
        $result = $this->conversation_service->process_command( $command, $conversation_uuid, $user_id );
        
        if ( ! $result ) {
            return new \WP_Error(
                'rest_not_found',
                __( 'Conversation not found or you do not have permission to access it.', 'wp-natural-language-commands' ),
                array( 'status' => 404 )
            );
        }
        
        // Return the response
        return rest_ensure_response( $result );
    }
    
    /**
     * Start a new conversation
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function create_conversation( $request ) {
        // Get the command from the request
        $command = $request->get_param( 'command' );
        
        // Get the current user ID
        $user_id = get_current_user_id();
        
        // Process the command with no conversation UUID (will create a new one)
        $result = $this->conversation_service->process_command( $command, null, $user_id );
        
        // Return the response
        return rest_ensure_response( $result );
    }
}
