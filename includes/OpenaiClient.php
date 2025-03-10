<?php
/**
 * OpenAI Client Class
 *
 * @package AICommander
 */

namespace AICommander\Includes;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * OpenAI Client class.
 *
 * This class handles communication with the OpenAI API for function calling and speech-to-text.
 */
class OpenaiClient {

    /**
     * The OpenAI API key.
     *
     * @var string
     */
    private $api_key;

    /**
     * The OpenAI Chat API endpoint.
     *
     * @var string
     */
    private $chat_api_endpoint = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * The OpenAI Whisper API endpoint.
     *
     * @var string
     */
    private $whisper_api_endpoint = 'https://api.openai.com/v1/audio/transcriptions';

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
        $this->api_key = get_option( 'ai_commander_openai_api_key', '' );
        $this->model = get_option( 'ai_commander_openai_model', 'gpt-4o' );
        $this->debug_mode = get_option( 'ai_commander_debug_mode', false );
    }
    
    /**
     * Transcribe audio using the OpenAI Whisper API.
     *
     * @param string $audio_file_path The path to the audio file.
     * @param string $language Optional language code to improve transcription accuracy.
     * @return string|\WP_Error The transcribed text or an error.
     */
    public function transcribe_audio( $audio_file_path, $language = null ) {
        if ( empty( $this->api_key ) ) {
            return new \WP_Error(
                'missing_api_key',
                'OpenAI API key is not configured. Please set it in the plugin settings.'
            );
        }
        
        if ( ! file_exists( $audio_file_path ) ) {
            return new \WP_Error(
                'file_not_found',
                'Audio file not found.'
            );
        }
        
        // Prepare the request
        $boundary = wp_generate_password( 24, false );
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        );
        
        // Start building the multipart body
        $body = '';
        
        // Add the file part
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename( $audio_file_path ) . '"' . "\r\n";
        
        // Determine the correct Content-Type based on file extension
        $file_extension = strtolower(pathinfo($audio_file_path, PATHINFO_EXTENSION));
        $content_type = 'audio/mpeg'; // Default
        
        // Map file extensions to MIME types
        $mime_types = $this->get_audio_mime_types();
        
        if (isset($mime_types[$file_extension])) {
            $content_type = $mime_types[$file_extension];
        }
        
        $body .= 'Content-Type: ' . $content_type . "\r\n\r\n";
        $body .= file_get_contents( $audio_file_path ) . "\r\n";
        
        // Add the model part
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="model"' . "\r\n\r\n";
        $body .= 'whisper-1' . "\r\n";
        
        // Add the language part if specified
        if ( ! empty( $language ) ) {
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="language"' . "\r\n\r\n";
            $body .= $language . "\r\n";
        }
        
        // Close the multipart body
        $body .= '--' . $boundary . '--';
        
        // Log the request if debug mode is enabled
        if ( $this->debug_mode ) {
            error_log( 'OpenAI Whisper API Request: ' . basename( $audio_file_path ) . ' (language: ' . ( $language ?: 'auto' ) . ')' );
        }
        
        // Send the request
        $response = wp_remote_post( $this->whisper_api_endpoint, array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 60, // Longer timeout for audio processing
        ) );
        
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
                sprintf( 'OpenAI Whisper API error (%d): %s', $response_code, $error_message )
            );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $result = json_decode( $body, true );
        
        // Log the response if debug mode is enabled
        if ( $this->debug_mode ) {
            error_log( 'OpenAI Whisper API Response: ' . wp_json_encode( $result, JSON_PRETTY_PRINT ) );
        }
        
        return isset( $result['text'] ) ? $result['text'] : '';
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

    public static function get_default_system_prompt() {
        return 'You are a helpful assistant that can perform actions in WordPress. ' .
               'You have access to various tools that allow you to search, create and edit content. ' .
               'When a user asks you to do something, use the appropriate tool to accomplish the task. ' .
               'Do not explain or interpret tool results. When no further tool calls are needed, simply indicate completion with minimal explanation. ' .
               'Do not use markdown formatting in your responses. ' .
               'If you are unable to perform a requested action, explain why and suggest alternatives.';
    }

    /**
     * Get the system prompt for the OpenAI API.
     *
     * @return string The system prompt.
     */
    public function get_system_prompt() {
        // Default system prompt if option is not set
        $default_prompt = $this->get_default_system_prompt();

        // Get the system prompt from options, fallback to default if empty
        $system_prompt = get_option( 'ai_commander_system_prompt', $default_prompt );
        
        if ( empty( $system_prompt ) ) {
            $system_prompt = $default_prompt;
        }
        
        // Apply filter to allow developers to modify the system prompt
        return apply_filters( 'ai_commander_filter_system_prompt', $system_prompt );
    }

    /**
     * Send a request to the OpenAI API.
     *
     * @param array $messages The messages to send.
     * @param array $tools The tools to make available to the API.
     * @return array|\WP_Error The API response, or \WP_Error on failure.
     */
    private function send_request( $messages, $tools ) {
        if ($this->debug_mode) {
            error_log('OpenAI API Request: ' . wp_json_encode(array(
                'model' => $this->model,
                'messages' => $messages,
                // 'tools' => $tools,
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

        $response = wp_remote_post( $this->chat_api_endpoint, $args );

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
     * Extract the content of the response from the OpenAI API, while
     * keeping the tool calls unchanged.
     *
     * It is important to keep the tool calls unchanged, as they will be
     * needed to later reference the actual tool calls made.
     * 
     * @param array $response The API response.
     * @return array The processed response.
     */
    private function process_response( $response ) {
        if ($this->debug_mode) {
            error_log('OpenAI API Response: ' . wp_json_encode($response, JSON_PRETTY_PRINT));
        }
        
        $message = $response['choices'][0]['message'];
        $content = $message['content'] ?? '';

        return array(
            'content' => $content,
            'tool_calls' => $message['tool_calls'] ?? array(),
        );
    }

    /**
     * Get the MIME types for audio files.
     *
     * @return array The MIME types for audio files.
     */
    public static function get_audio_mime_types() {
        return array(
            'm4a' => 'audio/mp4',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'ogg' => 'audio/ogg',
            'oga' => 'audio/ogg',
            'webm' => 'audio/webm',
            'mpga' => 'audio/mpeg',
        );
    }
}
