<?php

/**
 * Settings Page Class
 *
 * @package AICommander
 */

namespace AICommander\Admin;

use AICommander\Includes\OpenaiClient;

if (! defined('WPINC')) {
    die;
}

/**
 * Settings page class.
 *
 * This class handles the settings page in the admin.
 */
class SettingsPage extends AdminPage
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add the admin menu items.
     */
    public function add_admin_menu()
    {
        // Don't call parent::add_admin_menu() to avoid duplicate top-level menu

        // Add the settings submenu page
        add_submenu_page(
            $this->parent_slug,
            __('Settings', 'ai-commander'),
            __('Settings', 'ai-commander'),
            'manage_options', // Only administrators can access settings
            'ai-commander-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings.
     */
    public function register_settings()
    {
        // OpenAI API settings
        register_setting(
            'ai_commander_settings',
            'ai_commander_openai_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );

        register_setting(
            'ai_commander_settings',
            'ai_commander_openai_chat_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-4o',
            )
        );

        register_setting(
            'ai_commander_settings',
            'ai_commander_openai_transcription_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-4o-transcribe',
            )
        );

        register_setting(
            'ai_commander_settings',
            'ai_commander_openai_speech_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-4o-mini-tts',
            )
        );

        register_setting(
            'ai_commander_settings',
            'ai_commander_openai_realtime_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-4o-realtime-preview-2024-12-17',
            )
        );

        // Debug settings
        register_setting(
            'ai_commander_settings',
            'ai_commander_openai_debug_mode',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );

        // Chatbot settings
        register_setting(
            'ai_commander_settings',
            'ai_commander_chatbot_system_prompt',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default' => 'You are a helpful assistant that can perform actions in WordPress. ' .
                    'You have access to various tools that allow you to search, create and edit content. ' .
                    'When a user asks you to do something, use the appropriate tool to accomplish the task. ' .
                    'Do not explain or interpret tool results. When no further tool calls are needed, simply indicate completion with minimal explanation. ' .
                    'If you are unable to perform a requested action, explain why and suggest alternatives.',
            )
        );

        register_setting(
            'ai_commander_settings',
            'ai_commander_chatbot_greeting',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default' => \AICommander\Includes\ConversationManager::get_default_assistant_greeting(),
            )
        );

        register_setting(
            'ai_commander_settings',
            'ai_commander_chatbot_speech_language',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );

        // Realtime API settings
        register_setting(
            'ai_commander_settings',
            'ai_commander_realtime_voice',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'verse', // Default realtime voice
            )
        );

        register_setting(
            'ai_commander_settings',
            'ai_commander_realtime_system_prompt',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default' => '', // Default empty
            )
        );

        // Add new setting for TTS override
        register_setting(
            'ai_commander_settings',
            'ai_commander_use_custom_tts',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );

        // Add new setting for realtime input transcription
        register_setting(
            'ai_commander_settings',
            'ai_commander_realtime_input_transcription',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false, // Disabled by default
            )
        );

        // Rename register_setting key
        register_setting(
            'ai_commander_settings',
            'ai_commander_realtime_tts_instructions',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default' => '', // Default empty
            )
        );

        // Add new setting for showing tool calls
        register_setting(
            'ai_commander_settings',
            'ai_commander_realtime_show_tool_calls',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true,
            )
        );

        // Add settings field for input audio noise reduction
        register_setting(
            'ai_commander_settings',
            'ai_commander_realtime_input_audio_noise_reduction',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'far_field',
            )
        );

        // Add settings section for OpenAI API
        add_settings_section(
            'ai_commander_openai_settings',
            __('OpenAI API Settings', 'ai-commander'),
            array($this, 'render_openai_settings_section'),
            'ai_commander_settings'
        );

        // Add settings fields for OpenAI API
        add_settings_field(
            'ai_commander_openai_api_key',
            __('API Key', 'ai-commander'),
            array($this, 'render_openai_api_key_field'),
            'ai_commander_settings',
            'ai_commander_openai_settings'
        );

        add_settings_field(
            'ai_commander_openai_chat_model',
            __('Chat Completion Model', 'ai-commander'),
            array($this, 'render_openai_chat_model_field'),
            'ai_commander_settings',
            'ai_commander_openai_settings'
        );


        add_settings_field(
            'ai_commander_openai_transcription_model',
            __('Transcription Model', 'ai-commander'),
            array($this, 'render_openai_transcription_model_field'),
            'ai_commander_settings',
            'ai_commander_openai_settings'
        );

        add_settings_field(
            'ai_commander_openai_speech_model',
            __('Speech Model', 'ai-commander'),
            array($this, 'render_openai_speech_model_field'),
            'ai_commander_settings',
            'ai_commander_openai_settings'
        );

        add_settings_field(
            'ai_commander_openai_realtime_model',
            __('Realtime Model', 'ai-commander'),
            array($this, 'render_openai_realtime_model_field'),
            'ai_commander_settings',
            'ai_commander_openai_settings'
        );

        add_settings_field(
            'ai_commander_openai_debug_mode',
            __('Debug Mode', 'ai-commander'),
            array($this, 'render_openai_debug_mode_field'),
            'ai_commander_settings',
            'ai_commander_openai_settings'
        );

        // Add settings section for chatbot
        add_settings_section(
            'ai_commander_chatbot_settings',
            __('Chatbot Settings', 'ai-commander'),
            array($this, 'render_chatbot_settings_section'),
            'ai_commander_settings'
        );

        // Add settings fields for chatbot
        add_settings_field(
            'ai_commander_chatbot_system_prompt',
            __('Chatbot System Prompt', 'ai-commander'),
            array($this, 'render_chatbot_system_prompt_field'),
            'ai_commander_settings',
            'ai_commander_chatbot_settings'
        );

        add_settings_field(
            'ai_commander_chatbot_greeting',
            __('Chatbot Greeting', 'ai-commander'),
            array($this, 'render_chatbot_greeting_field'),
            'ai_commander_settings',
            'ai_commander_chatbot_settings'
        );

        add_settings_field(
            'ai_commander_chatbot_speech_language',
            __('Speech language', 'ai-commander'),
            array($this, 'render_chatbot_speech_language_field'),
            'ai_commander_settings',
            'ai_commander_chatbot_settings'
        );

        // Add settings section for Realtime API
        add_settings_section(
            'ai_commander_realtime_settings',
            __('Realtime API Settings', 'ai-commander'),
            array($this, 'render_realtime_settings_section'),
            'ai_commander_settings' // Add to the main settings page group
        );

        // Add settings fields for Realtime API
        add_settings_field(
            'ai_commander_realtime_voice',
            __('Realtime Voice', 'ai-commander'),
            array($this, 'render_realtime_voice_field'),
            'ai_commander_settings',
            'ai_commander_realtime_settings'
        );

        add_settings_field(
            'ai_commander_realtime_system_prompt',
            __('Realtime System Prompt', 'ai-commander'),
            array($this, 'render_realtime_system_prompt_field'),
            'ai_commander_settings',
            'ai_commander_realtime_settings'
        );

        // Add field for custom TTS override
        add_settings_field(
            'ai_commander_use_custom_tts',
            __('Use Custom TTS', 'ai-commander'),
            array($this, 'render_custom_tts_field'),
            'ai_commander_settings',
            'ai_commander_realtime_settings'
        );

        // Add settings field for TTS instructions
        add_settings_field(
            'ai_commander_realtime_tts_instructions',
            __('TTS Instructions', 'ai-commander'),
            array($this, 'render_tts_instructions_field'),
            'ai_commander_settings',
            'ai_commander_realtime_settings'
        );

        // Add settings field for realtime input transcription
        add_settings_field(
            'ai_commander_realtime_input_transcription',
            __('Enable Input Transcription', 'ai-commander'),
            array($this, 'render_realtime_input_transcription_field'),
            'ai_commander_settings',
            'ai_commander_realtime_settings'
        );

        // Add settings field for showing tool calls
        add_settings_field(
            'ai_commander_realtime_show_tool_calls',
            __('Show Tool Calls', 'ai-commander'),
            array($this, 'render_realtime_show_tool_calls_field'),
            'ai_commander_settings',
            'ai_commander_realtime_settings'
        );

        // Add settings field for input audio noise reduction
        add_settings_field(
            'ai_commander_realtime_input_audio_noise_reduction',
            __('Input Audio Noise Reduction', 'ai-commander'),
            array($this, 'render_realtime_input_audio_noise_reduction_field'),
            'ai_commander_settings',
            'ai_commander_realtime_settings'
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page()
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php $this->render_tab_wrapper(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('ai_commander_settings');
                do_settings_sections('ai_commander_settings');
                submit_button();
                ?>
            </form>

            <div class="card">
                <h2><?php esc_html_e('Getting an OpenAI API Key', 'ai-commander'); ?></h2>
                <ol>
                    <li><?php esc_html_e('Go to the OpenAI website and sign up for an account if you don\'t already have one.', 'ai-commander'); ?></li>
                    <li><?php esc_html_e('Navigate to the API section of your account.', 'ai-commander'); ?></li>
                    <li><?php esc_html_e('Create a new API key and copy it.', 'ai-commander'); ?></li>
                    <li><?php esc_html_e('Paste the API key into the field above and save your settings.', 'ai-commander'); ?></li>
                </ol>
                <p>
                    <a href="https://platform.openai.com/api-keys" target="_blank" class="button">
                        <?php esc_html_e('Get API Key from OpenAI', 'ai-commander'); ?>
                    </a>
                </p>
            </div>
        </div>
    <?php
    }

    /**
     * Render the OpenAI settings section.
     */
    public function render_openai_settings_section()
    {
    ?>
        <p><?php esc_html_e('Configure your OpenAI API settings below. An API key is required for the chatbot to function.', 'ai-commander'); ?></p>
    <?php
    }

    /**
     * Render the API key field.
     */
    public function render_openai_api_key_field()
    {
        $api_key = get_option('ai_commander_openai_api_key', '');
        $masked_key = ! empty($api_key) ? substr($api_key, 0, 4) . '...' . substr($api_key, -4) : '';
    ?>
        <input type="password" id="ai_commander_openai_api_key" name="ai_commander_openai_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off" />
        <p class="description">
            <?php
            if (! empty($api_key)) {
                printf(
                    /* translators: %s: Masked API key */
                    esc_html__('Current key: %s', 'ai-commander'),
                    '<code>' . esc_html($masked_key) . '</code>'
                );
            } else {
                esc_html_e('Enter your OpenAI API key here.', 'ai-commander');
            }
            ?>
        </p>
    <?php
    }

    /**
     * Render the model field.
     */
    public function render_openai_chat_model_field()
    {
        $chat_model = get_option('ai_commander_openai_chat_model', 'gpt-4o');
    ?>
        <input type="text" id="ai_commander_openai_chat_model" name="ai_commander_openai_chat_model" value="<?php echo esc_attr($chat_model); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e('Enter the OpenAI model to use for chat completion, e.g., gpt-4o.', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Render the transcription model field.
     */
    public function render_openai_transcription_model_field()
    {
        $transcription_model = get_option('ai_commander_openai_transcription_model', 'gpt-4o-transcribe');
    ?>
        <input type="text" id="ai_commander_openai_transcription_model" name="ai_commander_openai_transcription_model" value="<?php echo esc_attr($transcription_model); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e('Enter the OpenAI model to generate text from audio (speech-to-text), e.g., gpt-4o-transcribe.', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Render the speech model field.
     */
    public function render_openai_speech_model_field()
    {
        $speech_model = get_option('ai_commander_openai_speech_model', 'gpt-4o-mini-tts');
    ?>
        <input type="text" id="ai_commander_openai_speech_model" name="ai_commander_openai_speech_model" value="<?php echo esc_attr($speech_model); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e('Enter the OpenAI model to use to generate audio from text (text-to-speech), e.g., gpt-4o-mini-tts.', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Render the Realtime model field.
     */
    public function render_openai_realtime_model_field()
    {
        $model = get_option('ai_commander_openai_realtime_model', 'gpt-4o-realtime-preview-2024-12-17');
    ?>
        <input type="text" id="ai_commander_openai_realtime_model" name="ai_commander_openai_realtime_model" value="<?php echo esc_attr($model); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e('Enter the OpenAI Realtime model to use (e.g., gpt-4o-realtime-preview-2024-12-17).', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Render the debug mode field.
     */
    public function render_openai_debug_mode_field()
    {
        $debug_mode = get_option('ai_commander_openai_debug_mode', false);
    ?>
        <label for="ai_commander_openai_debug_mode">
            <input type="checkbox" id="ai_commander_openai_debug_mode" name="ai_commander_openai_debug_mode" value="1" <?php checked($debug_mode, true); ?> />
            <?php esc_html_e('Enable debug logging', 'ai-commander'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('When enabled, detailed API request and response information will be logged to the WordPress debug log. This is useful for troubleshooting but should be disabled in production.', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Render the speech settings section.
     */
    public function render_chatbot_settings_section()
    {
    ?>
        <p><?php esc_html_e('Configure settings for the chatbot interface.', 'ai-commander'); ?></p>
    <?php
    }

    /**
     * Render the system prompt field.
     */
    public function render_chatbot_system_prompt_field()
    {
        $system_prompt = get_option('ai_commander_chatbot_system_prompt', '');
        $filtered_prompt = apply_filters('ai_commander_filter_chatbot_system_prompt', $system_prompt);
        $is_filtered = $filtered_prompt !== $system_prompt;
    ?>
        <?php if (! $is_filtered) : ?>
            <textarea id="ai_commander_chatbot_system_prompt" name="ai_commander_chatbot_system_prompt" rows="6" class="large-text code"><?php echo esc_textarea($system_prompt); ?></textarea>
            <p class="description">
                <?php esc_html_e('The system prompt sets the behavior and capabilities of the AI assistant. Customize this to change how the assistant responds to user requests.  If empty, the following default system prompt will be used:', 'ai-commander'); ?>
            </p>
            <p>
                <?php echo $this->format_long_string(esc_html(OpenaiClient::get_default_system_prompt())); ?>
            </p>
        <?php else : ?>
            <div class="notice notice-warning inline">
                <p>
                    <strong><?php esc_html_e('Note:', 'ai-commander'); ?></strong>
                    <?php esc_html_e('The system prompt is currently set by code using the `ai_commander_filter_chatbot_system_prompt` filter. This is the actual value used:', 'ai-commander'); ?>
                    <p><?php echo $this->format_long_string(esc_html($filtered_prompt)); ?></p>
                </p>
            </div>
        <?php endif; ?>
    <?php
    }

    /**
     * Render the assistant greeting field.
     */
    public function render_chatbot_greeting_field()
    {
        $greeting = get_option('ai_commander_chatbot_greeting', '');
        $filtered_greeting = apply_filters('ai_commander_filter_chatbot_greeting', $greeting);
        $is_filtered = $filtered_greeting !== $greeting;
    ?>
        <?php if (! $is_filtered) : ?>
            <textarea id="ai_commander_chatbot_greeting" name="ai_commander_chatbot_greeting" rows="3" class="large-text"><?php echo esc_textarea($greeting); ?></textarea>
            <p class="description">
                <?php esc_html_e('The initial greeting message shown to users when starting a new conversation. If empty, the default greeting will be used:', 'ai-commander'); ?>
            </p>
            <p>
                <code>
                    <?php echo esc_html(\AICommander\Includes\ConversationManager::get_default_assistant_greeting()); ?>
                </code>
            </p>
        <?php else : ?>
            <div class="notice notice-warning inline">
                <p>
                    <strong><?php esc_html_e('Note:', 'ai-commander'); ?></strong>
                    <?php esc_html_e('The assistant greeting is currently set by code using the `ai_commander_filter_chatbot_greeting` filter. This is the actual value used:', 'ai-commander'); ?>
                    <code><?php echo esc_html($filtered_greeting); ?></code>
                </p>
            </div>
        <?php endif; ?>
    <?php
    }

    /**
     * Render the speech language field.
     */
    public function render_chatbot_speech_language_field()
    {
        $language = get_option('ai_commander_chatbot_speech_language', '');
        $languages = array(
            '' => __('Auto-detect (recommended)', 'ai-commander'),
            'en' => __('English', 'ai-commander'),
            'es' => __('Spanish', 'ai-commander'),
            'fr' => __('French', 'ai-commander'),
            'de' => __('German', 'ai-commander'),
            'it' => __('Italian', 'ai-commander'),
            'pt' => __('Portuguese', 'ai-commander'),
            'nl' => __('Dutch', 'ai-commander'),
            'ja' => __('Japanese', 'ai-commander'),
            'zh' => __('Chinese', 'ai-commander'),
            'ru' => __('Russian', 'ai-commander'),
            'ar' => __('Arabic', 'ai-commander'),
            'hi' => __('Hindi', 'ai-commander'),
        );
    ?>
        <select id="ai_commander_chatbot_speech_language" name="ai_commander_chatbot_speech_language">
            <?php foreach ($languages as $code => $name) : ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($language, $code); ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Select a language to improve transcription accuracy. Auto-detect works well for most cases, but specifying a language can improve accuracy and processing speed.', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Render the Realtime settings section.
     */
    public function render_realtime_settings_section()
    {
    ?>
        <p><?php esc_html_e('Configure settings specific to the Realtime voice conversation feature.', 'ai-commander'); ?></p>
    <?php
    }

    /**
     * Render the Realtime voice field.
     */
    public function render_realtime_voice_field()
    {
        $voice = get_option('ai_commander_realtime_voice', 'verse');
        $available_voices = array('alloy', 'ash', 'ballad', 'coral', 'echo', 'sage', 'shimmer', 'verse'); // From OpenAI docs
    ?>
        <select id="ai_commander_realtime_voice" name="ai_commander_realtime_voice">
            <?php foreach ($available_voices as $v) : ?>
                <option value="<?php echo esc_attr($v); ?>" <?php selected($voice, $v); ?>>
                    <?php echo esc_html(ucfirst($v)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Select the voice for the AI assistant\'s audio responses.', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Render the Realtime system prompt field.
     */
    public function render_realtime_system_prompt_field()
    {
        $realtime_prompt = get_option('ai_commander_realtime_system_prompt', '');
    ?>
        <textarea id="ai_commander_realtime_system_prompt" name="ai_commander_realtime_system_prompt" rows="4" class="large-text code"><?php echo esc_textarea($realtime_prompt); ?></textarea>
        <p class="description">
            <?php esc_html_e('Optional: Add specific instructions for the Realtime voice assistant. This will be appended to the main System Prompt.', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Render the Custom TTS override field.
     */
    public function render_custom_tts_field()
    {
        $use_custom_tts = get_option('ai_commander_use_custom_tts', false);
    ?>
        <label for="ai_commander_use_custom_tts">
            <input type="checkbox" id="ai_commander_use_custom_tts" name="ai_commander_use_custom_tts" value="1" <?php checked($use_custom_tts, true); ?> />
            <?php esc_html_e('Use custom text-to-speech for audio responses', 'ai-commander'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('When enabled, the plugin will use your configured TTS model instead of Realtime API\'s built-in voice. This may provide better quality or different voice options depending on your settings.', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Render the TTS instructions field.
     */
    public function render_tts_instructions_field()
    {
        $tts_instructions = get_option('ai_commander_realtime_tts_instructions', '');
        $filtered_instructions = apply_filters('ai_commander_filter_tts_instructions', $tts_instructions);
        $is_filtered = $filtered_instructions !== $tts_instructions;
    ?>
        <?php if (! $is_filtered) : ?>
            <textarea id="ai_commander_realtime_tts_instructions" name="ai_commander_realtime_tts_instructions" rows="4" class="large-text code"><?php echo esc_textarea($tts_instructions); ?></textarea>
            <p class="description">
                <?php esc_html_e('Optional: Provide additional guidance for how the TTS model should speak (tone, style, etc.).  Ignored unless custom TTS is enabled.  Ignored for tts-1 models.', 'ai-commander'); ?>
            </p>
        <?php else : ?>
            <div class="notice notice-warning inline">
                <p>
                    <strong><?php esc_html_e('Note:', 'ai-commander'); ?></strong>
                    <?php esc_html_e('The TTS instructions are currently set by code using the `ai_commander_filter_tts_instructions` filter. This is the actual value used:', 'ai-commander'); ?>
                    <code><?php echo esc_html($filtered_instructions); ?></code>
                </p>
            </div>
        <?php endif; ?>
    <?php
    }

    /**
     * Render the Realtime input transcription field.
     */
    public function render_realtime_input_transcription_field()
    {
        $input_transcription_enabled = get_option('ai_commander_realtime_input_transcription', false);
    ?>
        <label for="ai_commander_realtime_input_transcription">
            <input type="checkbox" id="ai_commander_realtime_input_transcription" name="ai_commander_realtime_input_transcription" value="1" <?php checked($input_transcription_enabled, true); ?> />
            <?php esc_html_e('Enable input audio transcription', 'ai-commander'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('When enabled, the transcript of your speech will be displayed in the conversation. Disabling this can save API costs.', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Render the Realtime show tool calls field.
     */
    public function render_realtime_show_tool_calls_field()
    {
        $show_tool_calls = get_option('ai_commander_realtime_show_tool_calls', true);
    ?>
        <label for="ai_commander_realtime_show_tool_calls">
            <input type="checkbox" id="ai_commander_realtime_show_tool_calls" name="ai_commander_realtime_show_tool_calls" value="1" <?php checked($show_tool_calls, true); ?> />
            <?php esc_html_e('Show Tool Calls', 'ai-commander'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('When enabled, the tool calls and their results will be displayed in the conversation. This provides more visibility into how the AI is working behind the scenes, but will also make the conversation log more technical.', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Render the Input Audio Noise Reduction field.
     */
    public function render_realtime_input_audio_noise_reduction_field()
    {
        $value = get_option('ai_commander_realtime_input_audio_noise_reduction', 'far_field');
        $options = array(
            'none' => __('None (off)', 'ai-commander'),
            'near_field' => __('Near field (for close-talking microphones such as headphones)', 'ai-commander'),
            'far_field' => __('Far field (for laptop or conference room microphones)', 'ai-commander'),
        );
    ?>
        <select id="ai_commander_realtime_input_audio_noise_reduction" name="ai_commander_realtime_input_audio_noise_reduction">
            <?php foreach ($options as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('Configure input audio noise reduction. "Near field" is for close-talking microphones such as headphones. "Far field" is for laptop or conference room microphones. Select "None" to turn off noise reduction.', 'ai-commander'); ?>
        </p>
    <?php
    }

    /**
     * Make sure newlines are formatted correctly in the textarea fields.
     */
    private function format_long_string($string)
    {
        return str_replace("\n", "<br>", $string);
    }
}
