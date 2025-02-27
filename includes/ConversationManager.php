<?php
/**
 * Conversation Manager Class
 *
 * @package WP_Natural_Language_Commands
 */

namespace WPNaturalLanguageCommands\Includes;

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
            $wpdb->prefix . 'nlc_conversations',
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
            'Hello! I\'m your WordPress assistant. How can I help you today?'
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
                "SELECT * FROM {$wpdb->prefix}nlc_conversations WHERE conversation_uuid = %s",
                $conversation_uuid
            )
        );
    }
    
    /**
     * Add a message to a conversation.
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
            $wpdb->prefix . 'nlc_conversations',
            array( 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $conversation->id ),
            array( '%s' ),
            array( '%d' )
        );
        
        // Insert message
        $wpdb->insert(
            $wpdb->prefix . 'nlc_messages',
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
     * Get all messages for a conversation.
     *
     * @param string $conversation_uuid The conversation UUID.
     * @return array The messages.
     */
    public function get_messages( $conversation_uuid ) {
        global $wpdb;
        
        $conversation = $this->get_conversation( $conversation_uuid );
        if ( ! $conversation ) {
            return array();
        }
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nlc_messages WHERE conversation_id = %d ORDER BY id ASC",
                $conversation->id
            )
        );
    }
    
    /**
     * Format conversation messages for OpenAI API.
     *
     * @param string $conversation_uuid The conversation UUID.
     * @return array The formatted messages.
     */
    public function format_for_openai( $conversation_uuid ) {
        $messages = $this->get_messages( $conversation_uuid );
        $formatted = array();
        
        foreach ( $messages as $message ) {
            $formatted_message = array(
                'role' => $message->role,
                'content' => $message->content ? stripslashes($message->content) : ''
            );
            
            // Add tool_calls if present
            if ( $message->role === 'assistant' && ! empty( $message->tool_calls ) ) {
                $formatted_message['tool_calls'] = json_decode( $message->tool_calls, true );
            }
            
            // Add tool_call_id if this is a tool response
            if ( $message->role === 'tool' && ! empty( $message->tool_call_id ) ) {
                $formatted_message['tool_call_id'] = $message->tool_call_id;
                $formatted_message['type'] = 'function';
            }
            
            $formatted[] = $formatted_message;
        }
        
        return $formatted;
    }
    
    /**
     * Format messages for frontend display.
     *
     * @param string $conversation_uuid The conversation UUID.
     * @return array The formatted messages.
     */
    public function format_for_frontend( $conversation_uuid ) {
        $messages = $this->get_messages( $conversation_uuid );
        $formatted = array();
        
        foreach ( $messages as $message ) {
            $formatted_message = array(
                'role' => $message->role,
                'content' => $message->content ? stripslashes($message->content) : ''
            );
            
            // Add tool call information if present
            if ( $message->role === 'tool' ) {
                $formatted_message['isToolCall'] = true;
                $formatted_message['action'] = json_decode( $message->tool_calls, true );
            }
            
            $formatted[] = $formatted_message;
        }
        
        return $formatted;
    }
}
