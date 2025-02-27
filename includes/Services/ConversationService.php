<?php
/**
 * Conversation Service Class
 *
 * @package WP_Natural_Language_Commands
 */

namespace WPNaturalLanguageCommands\Includes\Services;

use WPNaturalLanguageCommands\Includes\ConversationManager;
use WPNaturalLanguageCommands\Includes\CommandProcessor;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Conversation Service class.
 *
 * This class provides a service layer for conversation-related operations,
 * which can be used by both AJAX handlers and REST API endpoints.
 */
class ConversationService {
    /**
     * The conversation manager.
     *
     * @var ConversationManager
     */
    private $conversation_manager;

    /**
     * The command processor.
     *
     * @var CommandProcessor
     */
    private $command_processor;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->conversation_manager = new ConversationManager();
        $this->command_processor = new CommandProcessor();
    }

    /**
     * Create a new conversation.
     *
     * @param int $user_id The WordPress user ID.
     * @return array The conversation data.
     */
    public function create_conversation( $user_id ) {
        $conversation_uuid = $this->conversation_manager->create_conversation( $user_id );
        $messages = $this->conversation_manager->format_for_frontend( $conversation_uuid );
        
        return array(
            'conversation_uuid' => $conversation_uuid,
            'messages' => $messages
        );
    }

    /**
     * Get a conversation.
     *
     * @param string $conversation_uuid The conversation UUID.
     * @param int $user_id The WordPress user ID.
     * @return array|false The conversation data, or false if not found or not authorized.
     */
    public function get_conversation( $conversation_uuid, $user_id ) {
        $conversation = $this->conversation_manager->get_conversation( $conversation_uuid );
        
        if ( ! $conversation ) {
            return false;
        }
        
        if ( $conversation->user_id != $user_id ) {
            return false;
        }
        
        $messages = $this->conversation_manager->format_for_frontend( $conversation_uuid );
        
        return array(
            'conversation_uuid' => $conversation_uuid,
            'messages' => $messages
        );
    }
    
    /**
     * Get all conversations for a user.
     *
     * @param int $user_id The WordPress user ID.
     * @return array The array of conversation data.
     */
    public function get_user_conversations( $user_id ) {
        $conversations = $this->conversation_manager->get_user_conversations( $user_id );
        $formatted_conversations = array();
        
        foreach ( $conversations as $conversation ) {
            // Get the first user message to use as a preview
            $messages = $this->conversation_manager->get_messages( $conversation->conversation_uuid );
            $preview = '';
            
            foreach ( $messages as $message ) {
                if ( $message->role === 'user' ) {
                    $preview = $message->content;
                    break;
                }
            }
            
            // Truncate preview if it's too long
            if ( strlen( $preview ) > 100 ) {
                $preview = substr( $preview, 0, 97 ) . '...';
            }
            
            $formatted_conversations[] = array(
                'conversation_uuid' => $conversation->conversation_uuid,
                'created_at' => $conversation->created_at,
                'updated_at' => $conversation->updated_at,
                'preview' => $preview
            );
        }
        
        return $formatted_conversations;
    }

    /**
     * Process a command.
     *
     * @param string $command The command to process.
     * @param string|null $conversation_uuid The conversation UUID. If null, a new conversation will be created.
     * @param int $user_id The WordPress user ID.
     * @return array|false The result of processing the command, or false if not found or not authorized.
     */
    public function process_command( $command, $conversation_uuid = null, $user_id = null ) {
        // If a conversation UUID is provided, verify ownership
        if ( ! empty( $conversation_uuid ) ) {
            $conversation = $this->conversation_manager->get_conversation( $conversation_uuid );
            
            if ( ! $conversation ) {
                return false;
            }
            
            if ( $user_id !== null && $conversation->user_id != $user_id ) {
                return false;
            }
        }
        
        // Process the command - if conversation_uuid is null, a new one will be created
        // inside the CommandProcessor
        return $this->command_processor->process( $command, $conversation_uuid );
    }
}
