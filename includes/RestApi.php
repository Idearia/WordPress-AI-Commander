<?php

/**
 * REST API Class.  When possible, use the ConversationService rather
 * than calling the business logic directly.
 *
 * @package AICommander
 */

namespace AICommander\Includes;

use AICommander\Includes\Services\ConversationService;
use AICommander\Includes\Services\MobileTranslations;

if (! defined('WPINC')) {
    die;
}

/**
 * REST API class.
 *
 * This class registers and handles REST API endpoints for the plugin.
 */
class RestApi
{

    /**
     * The conversation service.
     *
     * @var ConversationService
     */
    private $conversation_service;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->conversation_service = new ConversationService();

        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_routes'));

        // Add CORS headers only for our endpoints
        add_filter('rest_pre_serve_request', array($this, 'add_cors_headers'), 10, 4);
    }

    /**
     * Add CORS headers to REST API responses.
     * Only applies to ai-commander/v1 endpoints.
     *
     * @param mixed $served Whether the request has already been served.
     * @param \WP_HTTP_Response $result Result to send to the client.
     * @param \WP_REST_Request $request Request used to generate the response.
     * @param \WP_REST_Server $server Server instance.
     * @return mixed
     */
    public function add_cors_headers($served, $result, $request, $server)
    {
        // Only add CORS headers for our plugin's endpoints
        $route = $request->get_route();
        if (strpos($route, '/ai-commander/v1/') !== 0) {
            return $served;
        }

        // Add CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');

        // Handle preflight requests
        if ('OPTIONS' === $request->get_method()) {
            status_header(200);
            exit();
        }

        return $served;
    }

    /**
     * Register REST API routes.
     */
    public function register_routes()
    {
        // Register route for processing commands (creating a new conversation or adding to an existing one)
        register_rest_route('ai-commander/v1', '/command', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_command'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'command' => array(
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_string($param) && ! empty($param);
                    },
                ),
                'conversation_uuid' => array(
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_string($param) && ! empty($param);
                    },
                ),
            ),
        ));

        // Register route for transcribing audio
        register_rest_route('ai-commander/v1', '/transcribe', array(
            'methods' => 'POST',
            'callback' => array($this, 'transcribe_audio'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Register route for processing voice commands (transcribe + process in one request)
        register_rest_route('ai-commander/v1', '/voice-command', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_voice_command'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'conversation_uuid' => array(
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_string($param) && ! empty($param);
                    },
                ),
            ),
        ));

        // Register route for getting all conversations for the current user
        register_rest_route('ai-commander/v1', '/conversations', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_all_conversations'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Register route for getting a conversation
        register_rest_route('ai-commander/v1', '/conversations/(?P<uuid>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_conversation'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'uuid' => array(
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_string($param) && ! empty($param);
                    },
                ),
            ),
        ));

        // Register route for text-to-speech
        register_rest_route('ai-commander/v1', '/read-text', array(
            'methods' => 'POST',
            'callback' => array($this, 'read_text'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'text' => array(
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_string($param) && ! empty($param);
                    },
                ),
            ),
        ));

        // Register route for getting the assistant greeting.  This is an
        // authenticated endpoint because sometimes the greeting is
        // customized via hooks to include the user's name.
        register_rest_route('ai-commander/v1', '/assistant-greeting', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_assistant_greeting'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Register route for getting translations for mobile app
        // (used in test environment by the PWA app, in production
        // translations are dumped directly on the PWA page itself)
        register_rest_route('ai-commander/v1', '/translations', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_translations'),
            'permission_callback' => '__return_true', // Public endpoint
        ));

        // Register route for getting dynamic manifest for mobile PWA
        register_rest_route('ai-commander/v1', '/manifest', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_manifest'),
            'permission_callback' => '__return_true', // Public endpoint
        ));

        // Register route for getting the whole PWA configuration
        // (currently not used, might use in the future if you need to pass
        // more unauthenticated stuff to the PWA app, right now is just
        // translations so we use the /translations endpoint instead)
        register_rest_route('ai-commander/v1', '/pwa-config', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_pwa_config'),
            'permission_callback' => '__return_true', // Public endpoint
        ));

        // Register route for creating realtime sessions
        register_rest_route('ai-commander/v1', '/realtime/session', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_realtime_session'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Register route for executing realtime tools
        register_rest_route('ai-commander/v1', '/realtime/tool', array(
            'methods' => 'POST',
            'callback' => array($this, 'execute_realtime_tool'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'tool_name' => array(
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_string($param) && ! empty($param);
                    },
                ),
                'arguments' => array(
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_string($param) && ! empty($param);
                    },
                ),
            ),
        ));
    }

    /**
     * Check if the user has permission to use the API.
     *
     * @param \WP_REST_Request $request The request object.
     * @return bool|\WP_Error True if the user has permission, \WP_Error otherwise.
     */
    public function check_permission($request)
    {
        // Check if the user is logged in and has the required capability
        if (! current_user_can('edit_posts')) {
            return new \WP_Error(
                'rest_forbidden',
                __('You do not have permission to use this API.', 'ai-commander'),
                array('status' => 403)
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
    public function get_all_conversations($request)
    {
        // Get the current user ID
        $user_id = get_current_user_id();

        // Get all conversations for the user
        $result = $this->conversation_service->get_user_conversations($user_id);

        // Return the response
        return rest_ensure_response($result);
    }

    /**
     * Get a conversation.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function get_conversation($request)
    {
        // Get the conversation UUID from the request
        $conversation_uuid = $request->get_param('uuid');

        // Get the current user ID
        $user_id = get_current_user_id();

        // Get the conversation
        $result = $this->conversation_service->get_conversation($conversation_uuid, $user_id);

        if (! $result) {
            return new \WP_Error(
                'rest_not_found',
                __('Conversation not found or you do not have permission to access it.', 'ai-commander'),
                array('status' => 404)
            );
        }

        // Return the response
        return rest_ensure_response($result);
    }

    /**
     * Process a command, either creating a new conversation or adding to an existing one.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function process_command($request)
    {
        // Get the command and optional conversation UUID
        $command = $request->get_param('command');
        $conversation_uuid = $request->get_param('conversation_uuid');

        // Get the current user ID
        $user_id = get_current_user_id();

        // Process the command (if conversation_uuid is null, a new one will be created)
        $result = $this->conversation_service->process_command($command, $conversation_uuid, $user_id);

        if (is_wp_error($result)) {
            return $result;
        }

        // Return the response
        return rest_ensure_response($result);
    }

    /**
     * Transcribe audio using the OpenAI Whisper API.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function transcribe_audio($request)
    {
        // Check if file was uploaded
        $files = $request->get_file_params();
        if (empty($files['audio'])) {
            return new \WP_Error(
                'missing_audio',
                __('No audio file provided.', 'ai-commander'),
                array('status' => 400)
            );
        }

        // Handle the file upload using the ConversationService
        $upload_result = $this->conversation_service->handle_audio_upload($files['audio']);

        if (is_wp_error($upload_result)) {
            return new \WP_Error(
                $upload_result->get_error_code(),
                $upload_result->get_error_message(),
                array('status' => 400)
            );
        }

        $file_path = $upload_result['file_path'];

        try {
            // Transcribe the audio
            $transcription = $this->conversation_service->transcribe_audio($file_path);

            // Delete the audio file after transcription
            wp_delete_file($file_path);

            if (is_wp_error($transcription)) {
                return new \WP_Error(
                    'transcription_error',
                    $transcription->get_error_message(),
                    array('status' => 500)
                );
            }

            // Return the transcription
            return rest_ensure_response(array(
                'transcription' => $transcription
            ));
        } catch (\Exception $e) {
            // Delete the audio file if there was an error
            wp_delete_file($file_path);

            return new \WP_Error(
                'transcription_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Process a voice command by transcribing audio and then processing the transcribed text.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function process_voice_command($request)
    {
        // Get the conversation UUID if provided
        $conversation_uuid = $request->get_param('conversation_uuid');

        // Check if file was uploaded
        $files = $request->get_file_params();
        if (empty($files['audio'])) {
            return new \WP_Error(
                'missing_audio',
                __('No audio file provided.', 'ai-commander'),
                array('status' => 400)
            );
        }

        // Handle the file upload using the ConversationService
        $upload_result = $this->conversation_service->handle_audio_upload($files['audio']);

        if (is_wp_error($upload_result)) {
            return new \WP_Error(
                $upload_result->get_error_code(),
                $upload_result->get_error_message(),
                array('status' => 400)
            );
        }

        $file_path = $upload_result['file_path'];

        try {
            // Get the current user ID
            $user_id = get_current_user_id();

            // Process the voice command
            $result = $this->conversation_service->process_voice_command($file_path, $conversation_uuid, $user_id);

            // Delete the audio file after processing
            wp_delete_file($file_path);

            if (! $result || isset($result['success']) && $result['success'] === false) {
                $message = isset($result['message']) ? $result['message'] : 'Unknown error';
                return new \WP_Error(
                    'voice_command_error',
                    $message,
                    array('status' => 500)
                );
            }

            // Return the result
            return rest_ensure_response($result);
        } catch (\Exception $e) {
            // Delete the audio file if there was an error
            wp_delete_file($file_path);

            return new \WP_Error(
                'voice_command_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Convert text to speech using the OpenAI TTS API.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function read_text($request)
    {
        $text = $request->get_param('text');

        $result = $this->conversation_service->read_text($text);

        if (is_wp_error($result)) {
            return new \WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 500)
            );
        }

        // Below we'll bypass normal REST API flow, so we have to manually
        // add CORS headers, because we cannot rely on the rest_pre_serve_request
        // hook.
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');

        // Send binary directly bypassing WP's JSON encoding
        // which corrupts binary data
        header('Content-Type: ' . $result['mime_type']);
        header('Content-Disposition: attachment; filename="audio.mp3"');
        header('Content-Length: ' . strlen($result['audio_data']));

        // This is using WP functions but it's not working
        // $response = new \WP_REST_Response( $result['audio_data'] );
        // $response->set_status( 200 );
        // $response->header( 'Content-Type', $result['mime_type'] );
        // $response->header( 'Content-Disposition', 'attachment; filename="audio.mp3"' );

        // Disable any output buffering to ensure clean binary output
        while (ob_get_level()) {
            ob_end_clean();
        }

        echo $result['audio_data'];
        exit;
    }

    /**
     * Create a realtime session with OpenAI.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function create_realtime_session($request)
    {
        // Create the realtime session using the service
        $result = $this->conversation_service->create_realtime_session();

        if (is_wp_error($result)) {
            return new \WP_Error(
                $result->get_error_code(),
                'Failed to create Realtime session: ' . $result->get_error_message(),
                array('status' => 500)
            );
        }

        // Return the session data
        return rest_ensure_response($result);
    }

    /**
     * Execute a realtime tool.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function execute_realtime_tool($request)
    {
        // Get tool name and arguments from the request
        $tool_name = $request->get_param('tool_name');
        $arguments_json = $request->get_param('arguments');

        // Decode the JSON arguments into a PHP array
        $params = json_decode($arguments_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'invalid_arguments',
                'Invalid tool arguments JSON: ' . json_last_error_msg(),
                array('status' => 400)
            );
        }

        // Ensure $params is an array after decoding
        if (!is_array($params)) {
            $params = array(); // Default to empty array if decoding results in non-array
        }

        // Execute the tool using the service
        $result = $this->conversation_service->execute_realtime_tool($tool_name, $params);

        // The Realtime API expects the function result (even errors) back.
        // We send the raw result. If it's a WP_Error, we format it into a structured error.
        if (is_wp_error($result)) {
            // Send back a structured error message that the frontend can pass to OpenAI
            return rest_ensure_response(array(
                'error' => true,
                'message' => $result->get_error_message(),
                'code' => $result->get_error_code(),
                'data' => $result->get_error_data(),
            ));
        } else {
            return rest_ensure_response($result);
        }
    }

    /**
     * Get the assistant greeting.
     *
     * @return \WP_REST_Response|\WP_Error The response object.
     */
    public function get_assistant_greeting()
    {
        $greeting = ConversationManager::get_assistant_greeting();
        return rest_ensure_response($greeting);
    }

    /**
     * Get translations for mobile app
     *
     * @return \WP_REST_Response The translations response.
     */
    public function get_translations()
    {
        // Get current locale
        $locale = get_locale();

        // Get mobile app translations from dedicated service
        $translations = MobileTranslations::get_translations();

        // Return translations with locale information
        return rest_ensure_response(array(
            'locale' => $locale,
            'translations' => $translations,
        ));
    }

    /**
     * Get dynamic manifest for the mobile PWA.
     *
     * @return \WP_REST_Response The response object.
     */
    public function get_manifest()
    {
        // Get translations
        $translations = MobileTranslations::get_translations();

        // Get site-specific settings
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();

        // Apply filters for PWA customization
        $pwa_name = apply_filters('ai_commander_filter_pwa_name', $translations['mobile.manifest.name']);
        $pwa_short_name = apply_filters('ai_commander_filter_pwa_short_name', $translations['mobile.manifest.short_name']);
        $pwa_description = apply_filters('ai_commander_filter_pwa_description', $translations['mobile.manifest.description']);
        $pwa_theme_color = apply_filters('ai_commander_filter_pwa_theme_color', '#1e3c72');
        $pwa_background_color = apply_filters('ai_commander_filter_pwa_background_color', '#1e3c72');

        // Default icon
        $default_icons = array(
            array(
                'src' => './assets/favicon.png',
                'sizes' => '32x32',
                'type' => 'image/png'
            )
        );
        $pwa_icons = apply_filters('ai_commander_filter_pwa_icons', $default_icons);

        // Build manifest
        $manifest = array(
            'name' => $pwa_name,
            'short_name' => $pwa_short_name,
            'description' => $pwa_description,
            'display' => 'standalone',
            'background_color' => $pwa_background_color,
            'theme_color' => $pwa_theme_color,
            'orientation' => 'portrait',
            'start_url' => '/',
            'scope' => '/',
            'icons' => $pwa_icons
        );

        // Allow filtering the entire manifest
        $manifest = apply_filters('ai_commander_filter_pwa_manifest', $manifest);

        // Set proper content type header
        header('Content-Type: application/manifest+json');

        return rest_ensure_response($manifest);
    }

    /**
     * Get the whole PWA configuration, including translations, manifest,
     * assistant greeting, etc.
     *
     * If you personalized the assistant's greeting to include the user name,
     * don't use this endpoint, use the authenticated /assistant-greeting
     * endpoint instead.
     *
     * @return \WP_REST_Response The response object.
     */
    public function get_pwa_config()
    {
        $config = (new PwaPage())->generate_pwa_config();
        return rest_ensure_response($config);
    }
}
