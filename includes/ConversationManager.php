<?php
/**
 * Conversation Manager Class
 *
 * @package AICommander
 */

namespace AICommander\Includes;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Conversation Manager class.
 *
 * This class manages conversation storage and retrieval.
 */
class ConversationManager {
    /**
     * Get the default assistant greeting.
     *
     * @return string The default assistant greeting.
     */
    public static function get_default_assistant_greeting() {
        return 'Hello! I\'m your WordPress assistant. How can I help you today?';
    }
    
    /**
     * Get the assistant greeting.
     *
     * @return string The assistant greeting.
     */
    public function get_assistant_greeting() {
        // Default greeting if option is not set
        $default_greeting = self::get_default_assistant_greeting();

        // Get the greeting from options, fallback to default if empty
        $greeting = get_option( 'ai_commander_chatbot_greeting', $default_greeting );
        
        if ( empty( $greeting ) ) {
            $greeting = $default_greeting;
        }
        
        // Apply filter to allow developers to modify the greeting
        return apply_filters( 'ai_commander_filter_chatbot_greeting', $greeting );
    }
    
    /**
     * Create a new conversation.
     *
     * @param int $user_id The WordPress user ID.
     * @return string The conversation UUID.
     */
    public function create_conversation( $user_id ) {
        global $wpdb;
        
        // Generate UUID
        $uuid = wp_generate_uuid4();
        $now = current_time( 'mysql' );
        
        // Insert conversation record
        $wpdb->insert(
            $wpdb->prefix . 'ai_commander_conversations',
            array(
                'conversation_uuid' => $uuid,
                'user_id' => $user_id,
                'created_at' => $now,
                'updated_at' => $now
            ),
            array( '%s', '%d', '%s', '%s' )
        );
        
        // Add initial assistant greeting
        $this->add_message(
            $uuid,
            'assistant',
            $this->get_assistant_greeting()
        );
        
        return $uuid;
    }
    
    /**
     * Get a conversation by UUID.
     *
     * @param string $conversation_uuid The conversation UUID.
     * @return object|null The conversation object or null if not found.
     */
    public function get_conversation( $conversation_uuid ) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ai_commander_conversations WHERE conversation_uuid = %s",
                $conversation_uuid
            )
        );
    }
    
    /**
     * Get all conversations for a user.
     *
     * @param int $user_id The WordPress user ID.
     * @return array The array of conversation objects.
     */
    public function get_user_conversations( $user_id ) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ai_commander_conversations WHERE user_id = %d ORDER BY updated_at DESC",
                $user_id
            )
        );
    }
    
    /**
     * Add a message to a conversation.
     *
     * The tool_calls column is used in two different ways:
     * - for assistant messages, it contains the tool calls suggested by the assistant
     * - for tool messages, it contains the $action array with the result of the actual
     *   tool call, as defined in the CommandProcessor class.
     *
     * @param string $conversation_uuid The conversation UUID.
     * @param string $role The message role (user, assistant, tool).
     * @param string $content The message content.
     * @param array|null $tool_calls Tool calls data if applicable.
     * @param string|null $tool_call_id Tool call ID if this is a tool response.
     * @return int|false The message ID or false on failure.
     */
    public function add_message( $conversation_uuid, $role, $content, $tool_calls = null, $tool_call_id = null ) {
        global $wpdb;
        
        // Get conversation ID
        $conversation = $this->get_conversation( $conversation_uuid );
        if ( ! $conversation ) {
            return false;
        }
        
        // Update conversation timestamp
        $wpdb->update(
            $wpdb->prefix . 'ai_commander_conversations',
            array( 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $conversation->id ),
            array( '%s' ),
            array( '%d' )
        );
        
        // Insert message
        $wpdb->insert(
            $wpdb->prefix . 'ai_commander_messages',
            array(
                'conversation_id' => $conversation->id,
                'role' => $role,
                'content' => $content,
                'tool_calls' => $tool_calls ? wp_json_encode( $tool_calls ) : null,
                'tool_call_id' => $tool_call_id,
                'created_at' => current_time( 'mysql' )
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get all messages for a conversation with JSON fields decoded.
     *
     * @param string $conversation_uuid The conversation UUID.
     * @return array|\WP_Error The messages with JSON fields decoded, or an error if the JSON is invalid.
     */
    public function get_messages( $conversation_uuid ) {
        global $wpdb;
        
        $conversation = $this->get_conversation( $conversation_uuid );
        if ( ! $conversation ) {
            return array();
        }
        
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ai_commander_messages WHERE conversation_id = %d ORDER BY id ASC",
                $conversation->id
            )
        );
        
        $json_fields = array( 'tool_calls' );
        
        // Process each message to decode JSON fields
        foreach ( $messages as $message ) {
            foreach ( $json_fields as $field ) {
                if ( $message->$field !== null ) {
                    $decoded = json_decode( $message->$field, true );
                    if ( json_last_error() === JSON_ERROR_NONE ) {
                        $message->$field = $decoded;
                    } else {
                        return new \WP_Error( 'invalid_json', 'Invalid JSON in ' . $field . ' field: ' . json_last_error_msg() );
                    }
                }
            }
        }
        
        return $messages;
    }
    
    /**
     * Format conversation messages for OpenAI API.
     *
     * @param string $conversation_uuid The conversation UUID.
     * @return array|\WP_Error The formatted messages, or an error if the messages could not be retrieved.
     */
    public function format_for_openai( $conversation_uuid ) {
        $messages = $this->get_messages( $conversation_uuid );
        if ( is_wp_error( $messages ) ) {
            return $messages;
        }

        $formatted = array();
        
        foreach ( $messages as $message ) {
            $formatted_message = array(
                'role' => $message->role,
                'content' => $message->content ? stripslashes($message->content) : ''
            );
            
            // Add tool_calls suggested by assistant, if present
            if ( $message->role === 'assistant' && ! empty( $message->tool_calls ) ) {
                $formatted_message['tool_calls'] = $message->tool_calls;
            }
            
            // Add tool_call_id, if this is a tool response
            if ( $message->role === 'tool' && ! empty( $message->tool_call_id ) ) {
                $formatted_message['tool_call_id'] = $message->tool_call_id;
                
            }
            
            $formatted[] = $formatted_message;
        }
        
        return $formatted;
    }
    
    /**
     * Format messages for frontend display.
     * 
     * This is used to popolate an existing conversation at page load
     * or when a new conversation is created from the frontend.
     *
     * @param string $conversation_uuid The conversation UUID.
     * @param bool $hide_assistant_response_after_tool_calls Whether to hide the assistant response after a tool call.
     * @return array|\WP_Error The formatted messages, or an error if the messages could not be retrieved.
     */
    public function format_for_frontend( $conversation_uuid, $hide_assistant_response_after_tool_calls = true ) {
        $messages = $this->get_messages( $conversation_uuid );
        if ( is_wp_error( $messages ) ) {
            return $messages;
        }

        $formatted = array();
        
        // Track previous message for context-aware filtering
        $previous_message = null;
        
        foreach ( $messages as $message ) {
            $should_hide = false;
            
            // Hide assistant messages that follow tool messages if the flag is set
            if ( $hide_assistant_response_after_tool_calls && 
                 $message->role === 'assistant' && 
                 $previous_message && $previous_message->role === 'tool' ) {
                $should_hide = true;
            }
            
            // Skip this message if it should be hidden
            if ( $should_hide ) {
                continue;
            }
            
            $formatted_message = array(
                'role' => $message->role,
                'content' => $message->content ? stripslashes($message->content) : ''
            );
            
            // Add tool call information if present
            if ( $message->role === 'tool' ) {
                $formatted_message['isToolCall'] = true;
                $formatted_message['action'] = $message->tool_calls;
            }
            
            // Do not show empty assistant responses (e.g. when assistant
            // just suggests a tool call with no message added)
            if ( $message->role === 'assistant' && empty( $message->content ) ) {
                continue;
            }

            $formatted[] = $formatted_message;
            
            // Store this message for context in the next iteration
            $previous_message = $message;
        }

        return $formatted;
    }
}
