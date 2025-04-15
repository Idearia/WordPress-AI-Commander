<?php
/**
 * AJAX Handlers Class
 *
 * @package AICommander
 */

namespace AICommander\Includes;

use AICommander\Includes\Services\ConversationService;
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
        add_action( 'wp_ajax_ai_commander_create_conversation', array( $this, 'create_conversation' ) );
        add_action( 'wp_ajax_ai_commander_get_conversation', array( $this, 'get_conversation' ) );
        add_action( 'wp_ajax_ai_commander_process_command', array( $this, 'process_command' ) );
        add_action( 'wp_ajax_ai_commander_transcribe_audio', array( $this, 'transcribe_audio' ) );
        
        // Realtime AJAX handlers
        add_action( 'wp_ajax_ai_commander_get_realtime_token', array( $this, 'get_realtime_token' ) );
        add_action( 'wp_ajax_ai_commander_execute_realtime_tool', array( $this, 'execute_realtime_tool' ) );
        add_action( 'wp_ajax_ai_commander_get_realtime_tool_definitions', array( $this, 'get_realtime_tool_definitions' ) );
    }

    /**
     * AJAX handler for creating a new conversation.
     */
    public function create_conversation() {
        // Check nonce for security
        check_ajax_referer( 'ai_commander_nonce', 'nonce' );
        
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
        check_ajax_referer( 'ai_commander_nonce', 'nonce' );
        
        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }
        
        // Get the conversation UUID from the request
        $conversation_uuid = sanitize_text_field( $_POST['conversation_uuid'] ?? '' );
        
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
        check_ajax_referer( 'ai_commander_nonce', 'nonce' );
        
        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }
        
        // Get the command from the request
        $command = sanitize_text_field( $_POST['command'] ?? '' );
        $conversation_uuid = sanitize_text_field( $_POST['conversation_uuid'] ?? null );
        
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
     * AJAX handler for transcribing audio using OpenAI Whisper API.
     */
    public function transcribe_audio() {
        // Check nonce for security
        check_ajax_referer( 'ai_commander_nonce', 'nonce' );
        
        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
        }
        
        // Check if speech-to-text is enabled
        $enable_speech = get_option( 'ai_commander_enable_speech_to_text', true );
        if ( ! $enable_speech ) {
            wp_send_json_error( array( 'message' => 'Speech-to-text is disabled in settings' ) );
        }
        
        // Check if file was uploaded
        if ( empty( $_FILES['audio'] ) ) {
            wp_send_json_error( array( 'message' => 'No audio file provided' ) );
        }
        
        // Get the language setting
        $language = get_option( 'ai_commander_speech_language', '' );
        
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
            wp_delete_file( $file_path );
            
            if ( is_wp_error( $transcription ) ) {
                wp_send_json_error( array( 'message' => $transcription->get_error_message() ) );
            }
            
            // Return the transcription
            wp_send_json_success( array( 'transcription' => $transcription ) );
        } catch ( Exception $e ) {
            // Delete the audio file if there was an error
            wp_delete_file( $file_path );
            
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }

    // --- Realtime AJAX Handlers ---

    /**
     * AJAX handler for getting an ephemeral token for a new Realtime session.
     */
    public function get_realtime_token() {
        // Check nonce for security - use a specific nonce for realtime operations
        check_ajax_referer( 'ai_commander_realtime_nonce', 'nonce' );

        // Check user capabilities - Ensure the user can interact with the chatbot features
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions to start a Realtime session.' ), 403 );
        }

        // Instantiate the OpenAI client
        $openai_client = new OpenaiClient();

        // Create the Realtime session
        $session_data = $openai_client->create_realtime_session();

        // Check for errors during session creation
        if ( is_wp_error( $session_data ) ) {
            wp_send_json_error(
                array(
                    'message' => 'Failed to create Realtime session: ' . $session_data->get_error_message(),
                    'code' => $session_data->get_error_code(),
                ),
                500 // Internal Server Error or specific error code if available
            );
        }

        // Return the full session data, including the ephemeral token (client_secret)
        wp_send_json_success( $session_data );
    }

    /**
     * AJAX handler for executing a tool requested by the Realtime API.
     *
     * This is different than the process_command() handler, which is used for
     * chatbot commands entered directly by the user, and returns also summaries
     * and action buttons.
     *
     * Here, we simply execute the tool as requested by the OpenAI Realtime API.
     *
     * Please note that the tool definition must be provided by the browser
     * beforehand, in the session.update event.
     */
    public function execute_realtime_tool() {
        // Check nonce for security
        check_ajax_referer( 'ai_commander_realtime_nonce', 'nonce' );

        // Initial capability check - more specific check happens in execute_tool
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions to execute tools.' ), 403 );
        }

        // Get tool name and arguments from the request
        $tool_name = isset( $_POST['tool_name'] ) ? sanitize_text_field( $_POST['tool_name'] ) : '';
        // Important: Arguments from OpenAI are expected to be a JSON string
        $arguments_json = isset( $_POST['arguments'] ) ? wp_unslash( $_POST['arguments'] ) : ''; 
        
        if ( empty( $tool_name ) ) {
            wp_send_json_error( array( 'message' => 'No tool name specified.' ), 400 );
        }

        if ( empty( $arguments_json ) ) {
            wp_send_json_error( array( 'message' => 'No tool arguments specified.' ), 400 );
        }

        // Decode the JSON arguments into a PHP array
        $params = json_decode( $arguments_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
             wp_send_json_error( array( 'message' => 'Invalid tool arguments JSON: ' . json_last_error_msg() ), 400 );
        }
        
        // Ensure $params is an array after decoding
        if (!is_array($params)) {
            $params = array(); // Default to empty array if decoding results in non-array
        }

        // Check if the tool exists using the already instantiated ToolRegistry
        if ( ! $this->tool_registry->has_tool( $tool_name ) ) {
            wp_send_json_error( array( 'message' => 'Tool not found: ' . $tool_name ), 404 );
        }

        // Execute the tool - This method includes the capability check based on the tool's requirement
        $result = $this->tool_registry->execute_tool( $tool_name, $params );

        // The Realtime API expects the function result (even errors) back.
        // We send the raw result. If it's a WP_Error, we format it into a structured error.
        if ( is_wp_error( $result ) ) {
            // Send back a structured error message that the frontend can pass to OpenAI
            wp_send_json_success( array(
                'error' => true,
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
                'data' => $result->get_error_data(),
            ) );
        } else {
            // Send the successful result directly
            // Ensure the result is serializable (likely already is)
            wp_send_json_success( $result );
        }
    }

    /**
     * AJAX handler for getting tool definitions formatted for OpenAI
     * Realtime API.
     */
    public function get_realtime_tool_definitions() {
        // Use the same nonce as realtime token retrieval for consistency
        check_ajax_referer( 'ai_commander_realtime_nonce', 'nonce' );

        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions to get tool definitions.' ), 403 );
        }

        // Get tool definitions from the registry
        $definitions = $this->tool_registry->get_tool_definitions( 'realtime' );

        // Return the definitions
        wp_send_json_success( array( 'tool_definitions' => $definitions ) );
    }
}
