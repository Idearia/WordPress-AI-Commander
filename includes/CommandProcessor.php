<?php
/**
 * Command Processor Class
 *
 * @package WP_Natural_Language_Commands
 */

namespace WPNaturalLanguageCommands\Includes;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Command Processor class.
 *
 * This class processes natural language commands and executes the appropriate tools.
 */
class CommandProcessor {

    /**
     * The OpenAI client.
     *
     * @var OpenaiClient
     */
    private $openai_client;

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
        $this->openai_client = new OpenaiClient();
        $this->tool_registry = ToolRegistry::get_instance();
    }

    /**
     * Process a command.
     *
     * @param string $command The command to process.
     * @return array The result of processing the command.
     */
    public function process( $command ) {
        // Get the tool definitions for OpenAI function calling
        $tool_definitions = $this->tool_registry->get_tool_definitions();
        
        // Process the command using the OpenAI API
        $response = $this->openai_client->process_command( $command, $tool_definitions );
        
        if ( $response instanceof \WP_Error ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }
        
        // If there are no tool calls, just return the content
        if ( empty( $response['tool_calls'] ) ) {
            return array(
                'success' => true,
                'message' => $response['content'],
                'actions' => array(),
            );
        }
        
        // Execute the tool calls
        $actions = array();
        foreach ( $response['tool_calls'] as $tool_call ) {
            $result = $this->execute_tool( $tool_call['name'], $tool_call['arguments'] );
            
            // Get the tool instance to access its properties
            $tool = $this->tool_registry->get_tool( $tool_call['name'] );
            
            // Generate a summary message for the action
            $summary = '';
            if ( is_wp_error( $result ) ) {
                $summary = $result->get_error_message();
            } elseif ( isset( $result['message'] ) ) {
                // Use the message from the result if available
                $summary = $result['message'];
            } elseif ( $tool ) {
                // Let the tool generate a summary based on the result and arguments
                $summary = $tool->get_result_summary( $result, $tool_call['arguments'] );
            }
            
            $actions[] = array(
                'tool' => $tool_call['name'],
                'arguments' => $tool_call['arguments'],
                'result' => $result,
                'summary' => $summary,
            );
        }
        
        return array(
            'success' => true,
            'message' => $response['content'],
            'actions' => $actions,
        );
    }

    /**
     * Execute a tool.
     *
     * @param string $name The name of the tool to execute.
     * @param array $params The parameters to use when executing the tool.
     * @return array|\WP_Error The result of executing the tool.
     */
    private function execute_tool( $name, $params ) {
        return $this->tool_registry->execute_tool( $name, $params );
    }
}
