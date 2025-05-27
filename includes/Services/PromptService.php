<?php
/**
 * Helper class for getting the system prompts for the various models.
 *
 * @package AICommander
 */

namespace AICommander\Includes\Services;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Prompt Service class.
 *
 * This class provides a service layer for managing system prompts and
 * keeping prompt-related logic separate from the OpenAI client.
 */
class PromptService {

    /**
     * Get the default system prompt.
     *
     * @return string The default system prompt.
     */
    public function get_default_system_prompt() {
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
        $system_prompt = get_option( 'ai_commander_chatbot_system_prompt', $default_prompt );

        if ( empty( $system_prompt ) ) {
            $system_prompt = $default_prompt;
        }

        // Apply filter to allow developers to modify the system prompt
        return apply_filters( 'ai_commander_filter_chatbot_system_prompt', $system_prompt );
    }

    /**
     * Get the system prompt for realtime sessions.
     *
     * @return string The system prompt for realtime sessions.
     */
    public function get_realtime_system_prompt() {
        $main_prompt = $this->get_system_prompt();
        $realtime_specific_prompt = get_option( 'ai_commander_realtime_system_prompt', '' );
        $combined_instructions = $main_prompt;
        if ( ! empty( $realtime_specific_prompt ) ) {
            $combined_instructions .= "\n\n" . $realtime_specific_prompt;
        }

        // Apply filter to allow developers to modify the system prompt
        return apply_filters( 'ai_commander_filter_realtime_system_prompt', $combined_instructions, $main_prompt, $realtime_specific_prompt );
    }

    /**
     * Get the instructions prompt for the TTS API.
     *
     * @return string The instructions prompt for TTS.
     */
    public function get_tts_instructions() {
        // Get the instructions from options, fallback to empty string
        $instructions = get_option( 'ai_commander_realtime_tts_instructions', '' );

        // Apply filter to allow developers to modify the TTS instructions
        return apply_filters( 'ai_commander_filter_tts_instructions', $instructions );
    }
}