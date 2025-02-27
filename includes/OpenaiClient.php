<?php
/**
 * OpenAI Client Class
 *
 * @package WP_Natural_Language_Commands
 */

namespace WPNaturalLanguageCommands\Includes;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * OpenAI Client class.
 *
 * This class handles communication with the OpenAI API for function calling.
 */
class OpenaiClient {

    /**
     * The OpenAI API key.
     *
     * @var string
     */
    private $api_key;

    /**
     * The OpenAI API endpoint.
     *
     * @var string
     */
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

    /**
     * The OpenAI model to use.
     *
     * @var string
     */
    private $model;
    
    /**
     * Debug mode flag.
     *
     * @var bool
     */
    private $debug_mode;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api_key = get_option( 'wp_nlc_openai_api_key', '' );
        $this->model = get_option( 'wp_nlc_openai_model', 'gpt-4-turbo' );
        $this->debug_mode = get_option( 'wp_nlc_debug_mode', false );
    }

    /**
     * Process a command using the OpenAI API.
     *
     * @param string $command The command to process.
     * @param array $tools The tools to make available to the API.
     * @return array|\WP_Error The result of processing the command.
     */
    public function process_command( $command, $tools ) {
        if ( empty( $this->api_key ) ) {
            return new \WP_Error(
                'missing_api_key',
                'OpenAI API key is not configured. Please set it in the plugin settings.'
            );
        }

        $messages = array(
            array(
                'role' => 'system',
                'content' => $this->get_system_prompt(),
            ),
            array(
                'role' => 'user',
                'content' => $command,
            ),
        );

        $response = $this->send_request( $messages, $tools );

        if ( $response instanceof \WP_Error ) {
            return $response;
        }

        return $this->process_response( $response );
    }
    
    /**
     * Process a command with conversation history using the OpenAI API.
     *
     * @param array $messages The conversation history messages.
     * @param array $tools The tools to make available to the API.
     * @return array|\WP_Error The result of processing the command.
     */
    public function process_command_with_history( $messages, $tools ) {
        if ( empty( $this->api_key ) ) {
            return new \WP_Error(
                'missing_api_key',
                'OpenAI API key is not configured. Please set it in the plugin settings.'
            );
        }
        
        // Ensure the system prompt is included as the first message
        if ( empty( $messages ) || $messages[0]['role'] !== 'system' ) {
            array_unshift( $messages, array(
                'role' => 'system',
                'content' => $this->get_system_prompt(),
            ) );
        }

        $response = $this->send_request( $messages, $tools );

        if ( $response instanceof \WP_Error ) {
            return $response;
        }

        return $this->process_response( $response );
    }

    /**
     * Get the system prompt for the OpenAI API.
     *
     * @return string The system prompt.
     */
    private function get_system_prompt() {
        return 'You are a helpful assistant that can perform actions in WordPress. ' .
               'You have access to various tools that allow you to create and edit content. ' .
               'When a user asks you to do something, use the appropriate tool to accomplish the task. ' .
               'Always respond in a helpful and informative manner. ' .
               'If you are unable to perform a requested action, explain why and suggest alternatives.';
    }

    /**
     * Send a request to the OpenAI API.
     *
     * @param array $messages The messages to send.
     * @param array $tools The tools to make available to the API.
     * @return array|\WP_Error The API response, or \WP_Error on failure.
     */
    private function send_request( $messages, $tools ) {
        // Debug: Log the request payload if debug mode is enabled
        if ($this->debug_mode) {
            error_log('OpenAI API Request: ' . wp_json_encode(array(
                'model' => $this->model,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
            ), JSON_PRETTY_PRINT));
        }
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => $this->model,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
            ) ),
            'timeout' => 30,
        );

        $response = wp_remote_post( $this->api_endpoint, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $body = wp_remote_retrieve_body( $response );
            $error = json_decode( $body, true );
            $error_message = isset( $error['error']['message'] ) ? $error['error']['message'] : 'Unknown error';
            
            return new \WP_Error(
                'openai_api_error',
                sprintf( 'OpenAI API error (%d): %s', $response_code, $error_message )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        return json_decode( $body, true );
    }

    /**
     * Process the response from the OpenAI API.
     *
     * @param array $response The API response.
     * @return array The processed response.
     */
    private function process_response( $response ) {
        // Debug: Log the API response if debug mode is enabled
        if ($this->debug_mode) {
            error_log('OpenAI API Response: ' . wp_json_encode($response, JSON_PRETTY_PRINT));
        }
        
        $message = $response['choices'][0]['message'];
        $content = $message['content'] ?? '';
        $tool_calls = $message['tool_calls'] ?? array();

        $result = array(
            'content' => $content,
            'tool_calls' => array(),
        );

        foreach ( $tool_calls as $tool_call ) {
            $function = $tool_call['function'];
            $name = $function['name'];
            $arguments = json_decode( $function['arguments'], true );

            $result['tool_calls'][] = array(
                'name' => $name,
                'arguments' => $arguments,
                'id' => $tool_call['id']
            );
        }

        return $result;
    }
}
