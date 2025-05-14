<?php

/**
 * Command Processor Class
 *
 * @package AICommander
 */

namespace AICommander\Includes;

use AICommander\Includes\Services\PromptService;

if (! defined('WPINC')) {
    die;
}

/**
 * Command Processor class.
 *
 * This class processes natural language commands and executes the appropriate tools.
 */
class CommandProcessor
{

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
     * The conversation manager.
     *
     * @var ConversationManager
     */
    private $conversation_manager;

    /**
     * The prompt service.
     *
     * @var PromptService
     */
    private $prompt_service;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->openai_client = new OpenaiClient();
        $this->tool_registry = ToolRegistry::get_instance();
        $this->conversation_manager = new ConversationManager();
        $this->prompt_service = new PromptService();
    }

    /**
     * Process a command.
     *
     * @param string $command The command to process.
     * @param string|null $conversation_uuid The conversation UUID. If null, a new conversation will be created.
     * @return array|\WP_Error The result of processing the command.
     */
    public function process($command, $chat_model, $conversation_uuid = null)
    {
        // Get the tool definitions for OpenAI function calling
        $tool_definitions = $this->tool_registry->get_tool_definitions('chat_completion');

        // If no conversation UUID is provided, create a new conversation
        if (empty($conversation_uuid)) {
            $user_id = get_current_user_id();
            $conversation_uuid = $this->conversation_manager->create_conversation($user_id);
        }

        // Add the user message to the conversation
        $this->conversation_manager->add_message($conversation_uuid, 'user', $command);

        // Initialize variables for the loop
        $has_tool_calls = true;
        $actions = array();
        $final_message = '';

        // Get the system prompt
        $system_prompt = $this->prompt_service->get_system_prompt();

        // Continue processing until no more tool calls are needed
        while ($has_tool_calls) {
            // Get the formatted conversation history for OpenAI
            $messages = $this->conversation_manager->format_for_openai($conversation_uuid);

            // Process the command using the OpenAI API with conversation history
            $response = $this->openai_client->process_command_with_history(
                $messages,
                $chat_model,
                $tool_definitions,
                $system_prompt
            );

            // Errors at this point are likely to be due to the API key being
            // invalid or the quota being exceeded
            if (is_wp_error($response)) {
                return $response;
            }

            // Add the assistant response to the conversation
            $this->conversation_manager->add_message(
                $conversation_uuid,
                'assistant',
                $response['content'],
                empty($response['tool_calls']) ? null : $response['tool_calls']
            );

            // Update the final message with the latest response
            $final_message = $response['content'];

            // If there are no tool calls, break the loop
            if (empty($response['tool_calls'])) {
                $has_tool_calls = false;
                continue;
            }

            // Execute the tool calls suggested by the assistant
            foreach ($response['tool_calls'] as $tool_call) {
                // Execute tool
                $name = $tool_call['function']['name'];
                $arguments = json_decode($tool_call['function']['arguments'], true);
                $result = $this->execute_tool($name, $arguments);

                if (is_wp_error($result)) {
                    return $result;
                }

                // Get the tool instance to access its properties
                $tool = $this->tool_registry->get_tool($name);

                // Outcome of the tool call
                $title = sprintf('Executed %s successfully.', $name);

                // Let the tool generate a summary based on the result and arguments
                $summary = $tool->get_result_summary($result, $arguments);

                // Get action buttons from the tool
                $action_buttons = $tool->get_action_buttons($result, $arguments);

                // Create the complete action object with all necessary information
                $action = array(
                    'tool' => $name,
                    'tool_call_id' => isset($tool_call['id']) ? $tool_call['id'] : null,
                    'arguments' => $arguments,
                    'result' => $result,
                    'title' => $title,
                    'summary' => $summary,
                    'action_buttons' => $action_buttons,
                );

                // Add the tool response to the conversation with the complete action data
                $this->conversation_manager->add_message(
                    $conversation_uuid,
                    'tool',
                    wp_json_encode($result),
                    $action, // Store the complete action object including title and summary
                    isset($tool_call['id']) ? $tool_call['id'] : null
                );

                $actions[] = $action;
            }
        }

        return array(
            'success' => true,
            'message' => $final_message,
            'actions' => $actions,
            'conversation_uuid' => $conversation_uuid,
        );
    }

    /**
     * Execute a tool.
     *
     * @param string $name The name of the tool to execute.
     * @param array $params The parameters to use when executing the tool.
     * @return array|\WP_Error The result of executing the tool.
     */
    private function execute_tool($name, $params)
    {
        return $this->tool_registry->execute_tool($name, $params);
    }
}
