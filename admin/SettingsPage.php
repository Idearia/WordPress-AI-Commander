<?php
/**
 * Settings Page Class
 *
 * @package AICommander
 */

namespace AICommander\Admin;

use AICommander\Includes\OpenaiClient;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Settings page class.
 *
 * This class handles the settings page in the admin.
 */
class SettingsPage extends AdminPage {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct();
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add the admin menu items.
     */
    public function add_admin_menu() {
        // Don't call parent::add_admin_menu() to avoid duplicate top-level menu
        
        // Add the settings submenu page
        add_submenu_page(
            $this->parent_slug,
            __( 'Settings', 'ai-commander' ),
            __( 'Settings', 'ai-commander' ),
            'manage_options', // Only administrators can access settings
            'ai-commander-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        // Register the settings
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
            'ai_commander_openai_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-4o',
            )
        );
        
        register_setting(
            'ai_commander_settings',
            'ai_commander_debug_mode',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );
        
        // System prompt setting
        register_setting(
            'ai_commander_settings',
            'ai_commander_system_prompt',
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
        
        // Assistant greeting setting
        register_setting(
            'ai_commander_settings',
            'ai_commander_assistant_greeting',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default' => \AICommander\Includes\ConversationManager::get_default_assistant_greeting(),
            )
        );
        
        // Speech-to-text settings
        register_setting(
            'ai_commander_settings',
            'ai_commander_enable_speech_to_text',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true,
            )
        );
        
        register_setting(
            'ai_commander_settings',
            'ai_commander_speech_language',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
        
        // Realtime API settings
        register_setting(
            'ai_commander_settings',
            'ai_commander_realtime_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-4o-realtime-preview-2024-12-17', // Default realtime model
            )
        );

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
        
        // Add settings sections
        add_settings_section(
            'ai_commander_openai_settings',
            __( 'OpenAI API Settings', 'ai-commander' ),
            array( $this, 'render_openai_settings_section' ),
            'ai_commander_settings'
        );
        
        add_settings_section(
            'ai_commander_speech_settings',
            __( 'Speech-to-Text Settings', 'ai-commander' ),
            array( $this, 'render_speech_settings_section' ),
            'ai_commander_settings'
        );
        
        // Add settings fields for OpenAI API
        add_settings_field(
            'ai_commander_openai_api_key',
            __( 'API Key', 'ai-commander' ),
            array( $this, 'render_api_key_field' ),
            'ai_commander_settings',
            'ai_commander_openai_settings'
        );
        
        add_settings_field(
            'ai_commander_openai_model',
            __( 'Model', 'ai-commander' ),
            array( $this, 'render_model_field' ),
            'ai_commander_settings',
            'ai_commander_openai_settings'
        );
        
        add_settings_field(
            'ai_commander_debug_mode',
            __( 'Debug Mode', 'ai-commander' ),
            array( $this, 'render_debug_mode_field' ),
            'ai_commander_settings',
            'ai_commander_openai_settings'
        );
        
        add_settings_field(
            'ai_commander_system_prompt',
            __( 'System Prompt', 'ai-commander' ),
            array( $this, 'render_system_prompt_field' ),
            'ai_commander_settings',
            'ai_commander_openai_settings'
        );
        
        add_settings_field(
            'ai_commander_assistant_greeting',
            __( 'Assistant Greeting', 'ai-commander' ),
            array( $this, 'render_assistant_greeting_field' ),
            'ai_commander_settings',
            'ai_commander_openai_settings'
        );
        
        // Add settings fields for Speech-to-Text
        add_settings_field(
            'ai_commander_enable_speech_to_text',
            __( 'Enable Speech-to-Text', 'ai-commander' ),
            array( $this, 'render_enable_speech_field' ),
            'ai_commander_settings',
            'ai_commander_speech_settings'
        );
        
        add_settings_field(
            'ai_commander_speech_language',
            __( 'Language', 'ai-commander' ),
            array( $this, 'render_speech_language_field' ),
            'ai_commander_settings',
            'ai_commander_speech_settings'
        );

        // Add settings section for Realtime API
        add_settings_section(
            'ai_commander_realtime_settings',
            __( 'Realtime API Settings', 'ai-commander' ),
            array( $this, 'render_realtime_settings_section' ),
            'ai_commander_settings' // Add to the main settings page group
        );

        // Add settings fields for Realtime API
        add_settings_field(
            'ai_commander_realtime_model',
            __( 'Realtime Model', 'ai-commander' ),
            array( $this, 'render_realtime_model_field' ),
            'ai_commander_settings',
            'ai_commander_realtime_settings'
        );

        add_settings_field(
            'ai_commander_realtime_voice',
            __( 'Realtime Voice', 'ai-commander' ),
            array( $this, 'render_realtime_voice_field' ),
            'ai_commander_settings',
            'ai_commander_realtime_settings'
        );

        add_settings_field(
            'ai_commander_realtime_system_prompt',
            __( 'Realtime System Prompt', 'ai-commander' ),
            array( $this, 'render_realtime_system_prompt_field' ),
            'ai_commander_settings',
            'ai_commander_realtime_settings'
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->parent_slug ) ); ?>" class="nav-tab">
                    <?php esc_html_e( 'Overview', 'ai-commander' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-commander-chatbot' ) ); ?>" class="nav-tab">
                    <?php esc_html_e( 'Chatbot', 'ai-commander' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-commander-realtime' ) ); ?>" class="nav-tab">
                    <?php esc_html_e( 'Realtime', 'ai-commander' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ai-commander-settings' ) ); ?>" class="nav-tab nav-tab-active">
                    <?php esc_html_e( 'Settings', 'ai-commander' ); ?>
                </a>
            </h2>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'ai_commander_settings' );
                do_settings_sections( 'ai_commander_settings' );
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2><?php esc_html_e( 'Getting an OpenAI API Key', 'ai-commander' ); ?></h2>
                <ol>
                    <li><?php esc_html_e( 'Go to the OpenAI website and sign up for an account if you don\'t already have one.', 'ai-commander' ); ?></li>
                    <li><?php esc_html_e( 'Navigate to the API section of your account.', 'ai-commander' ); ?></li>
                    <li><?php esc_html_e( 'Create a new API key and copy it.', 'ai-commander' ); ?></li>
                    <li><?php esc_html_e( 'Paste the API key into the field above and save your settings.', 'ai-commander' ); ?></li>
                </ol>
                <p>
                    <a href="https://platform.openai.com/api-keys" target="_blank" class="button">
                        <?php esc_html_e( 'Get API Key from OpenAI', 'ai-commander' ); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render the OpenAI settings section.
     */
    public function render_openai_settings_section() {
        ?>
        <p><?php esc_html_e( 'Configure your OpenAI API settings below. An API key is required for the chatbot to function.', 'ai-commander' ); ?></p>
        <?php
    }

    /**
     * Render the API key field.
     */
    public function render_api_key_field() {
        $api_key = get_option( 'ai_commander_openai_api_key', '' );
        $masked_key = ! empty( $api_key ) ? substr( $api_key, 0, 4 ) . '...' . substr( $api_key, -4 ) : '';
        ?>
        <input type="password" id="ai_commander_openai_api_key" name="ai_commander_openai_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off" />
        <p class="description">
            <?php
            if ( ! empty( $api_key ) ) {
                printf(
                    /* translators: %s: Masked API key */
                    esc_html__( 'Current key: %s', 'ai-commander' ),
                    '<code>' . esc_html( $masked_key ) . '</code>'
                );
            } else {
                esc_html_e( 'Enter your OpenAI API key here.', 'ai-commander' );
            }
            ?>
        </p>
        <?php
    }

    /**
     * Render the model field.
     */
    public function render_model_field() {
        $model = get_option( 'ai_commander_openai_model', 'gpt-4o' );
        ?>
        <input type="text" id="ai_commander_openai_model" name="ai_commander_openai_model" value="<?php echo esc_attr( $model ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Enter the OpenAI model to use (e.g., gpt-4o). GPT-4 models provide better results but may be more expensive.', 'ai-commander' ); ?>
        </p>
        <?php
    }

    /**
     * Render the debug mode field.
     */
    public function render_debug_mode_field() {
        $debug_mode = get_option( 'ai_commander_debug_mode', false );
        ?>
        <label for="ai_commander_debug_mode">
            <input type="checkbox" id="ai_commander_debug_mode" name="ai_commander_debug_mode" value="1" <?php checked( $debug_mode, true ); ?> />
            <?php esc_html_e( 'Enable debug logging', 'ai-commander' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, detailed API request and response information will be logged to the WordPress debug log. This is useful for troubleshooting but should be disabled in production.', 'ai-commander' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render the speech settings section.
     */
    public function render_speech_settings_section() {
        ?>
        <p><?php esc_html_e( 'Configure speech-to-text settings for the chatbot interface. This feature uses OpenAI\'s Whisper API to transcribe spoken messages.', 'ai-commander' ); ?></p>
        <?php
    }
    
    /**
     * Render the enable speech field.
     */
    public function render_enable_speech_field() {
        $enable_speech = get_option( 'ai_commander_enable_speech_to_text', true );
        ?>
        <label for="ai_commander_enable_speech_to_text">
            <input type="checkbox" id="ai_commander_enable_speech_to_text" name="ai_commander_enable_speech_to_text" value="1" <?php checked( $enable_speech, true ); ?> />
            <?php esc_html_e( 'Enable speech-to-text functionality', 'ai-commander' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, users can record voice messages using a microphone button in the chat interface.', 'ai-commander' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render the system prompt field.
     */
    public function render_system_prompt_field() {
        $system_prompt = get_option( 'ai_commander_system_prompt', '' );
        $filtered_prompt = apply_filters( 'ai_commander_filter_system_prompt', $system_prompt );
        $is_filtered = $filtered_prompt !== $system_prompt;
        ?>
        <?php if ( ! $is_filtered ) : ?>
            <textarea id="ai_commander_system_prompt" name="ai_commander_system_prompt" rows="6" class="large-text code"><?php echo esc_textarea( $system_prompt ); ?></textarea>
            <p class="description">
            <?php esc_html_e( 'The system prompt sets the behavior and capabilities of the AI assistant. Customize this to change how the assistant responds to user requests.  If empty, the following default system prompt will be used:', 'ai-commander' ); ?>
            </p>
            <p>
                <code>
                    <?php echo esc_html( OpenaiClient::get_default_system_prompt() ); ?>
                </code>
            </p>
        <?php else : ?>
            <div class="notice notice-warning inline">
                <p>
                    <strong><?php esc_html_e( 'Note:', 'ai-commander' ); ?></strong>
                    <?php esc_html_e( 'The system prompt is currently set by code using the `ai_commander_filter_system_prompt` filter. This is the actual value used:', 'ai-commander' ); ?>
                    <code><?php echo esc_html( $filtered_prompt ); ?></code>
                </p>
            </div>
        <?php endif; ?>        
        <?php
    }

    /**
     * Render the assistant greeting field.
     */
    public function render_assistant_greeting_field() {
        $greeting = get_option( 'ai_commander_assistant_greeting', '' );
        $filtered_greeting = apply_filters( 'ai_commander_filter_assistant_greeting', $greeting );
        $is_filtered = $filtered_greeting !== $greeting;
        ?>
        <?php if ( ! $is_filtered ) : ?>
            <textarea id="ai_commander_assistant_greeting" name="ai_commander_assistant_greeting" rows="3" class="large-text"><?php echo esc_textarea( $greeting ); ?></textarea>
            <p class="description">
            <?php esc_html_e( 'The initial greeting message shown to users when starting a new conversation. If empty, the default greeting will be used:', 'ai-commander' ); ?>
            </p>
            <p>
                <code>
                    <?php echo esc_html( \AICommander\Includes\ConversationManager::get_default_assistant_greeting() ); ?>
                </code>
            </p>
        <?php else : ?>
            <div class="notice notice-warning inline">
                <p>
                    <strong><?php esc_html_e( 'Note:', 'ai-commander' ); ?></strong>
                    <?php esc_html_e( 'The assistant greeting is currently set by code using the `ai_commander_filter_assistant_greeting` filter. This is the actual value used:', 'ai-commander' ); ?>
                    <code><?php echo esc_html( $filtered_greeting ); ?></code>
                </p>
            </div>
        <?php endif; ?>        
        <?php
    }
    
    /**
     * Render the speech language field.
     */
    public function render_speech_language_field() {
        $language = get_option( 'ai_commander_speech_language', '' );
        $languages = array(
            '' => __( 'Auto-detect (recommended)', 'ai-commander' ),
            'en' => __( 'English', 'ai-commander' ),
            'es' => __( 'Spanish', 'ai-commander' ),
            'fr' => __( 'French', 'ai-commander' ),
            'de' => __( 'German', 'ai-commander' ),
            'it' => __( 'Italian', 'ai-commander' ),
            'pt' => __( 'Portuguese', 'ai-commander' ),
            'nl' => __( 'Dutch', 'ai-commander' ),
            'ja' => __( 'Japanese', 'ai-commander' ),
            'zh' => __( 'Chinese', 'ai-commander' ),
            'ru' => __( 'Russian', 'ai-commander' ),
            'ar' => __( 'Arabic', 'ai-commander' ),
            'hi' => __( 'Hindi', 'ai-commander' ),
        );
        ?>
        <select id="ai_commander_speech_language" name="ai_commander_speech_language">
            <?php foreach ( $languages as $code => $name ) : ?>
                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $language, $code ); ?>>
                    <?php echo esc_html( $name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select a language to improve transcription accuracy. Auto-detect works well for most cases, but specifying a language can improve accuracy and processing speed.', 'ai-commander' ); ?>
        </p>
        <?php
    }

    /**
     * Render the Realtime settings section.
     */
    public function render_realtime_settings_section() {
        ?>
        <p><?php esc_html_e( 'Configure settings specific to the Realtime voice conversation feature.', 'ai-commander' ); ?></p>
        <?php
    }

    /**
     * Render the Realtime model field.
     */
    public function render_realtime_model_field() {
        $model = get_option( 'ai_commander_realtime_model', 'gpt-4o-realtime-preview-2024-12-17' );
        ?>
        <input type="text" id="ai_commander_realtime_model" name="ai_commander_realtime_model" value="<?php echo esc_attr( $model ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Enter the OpenAI Realtime model to use (e.g., gpt-4o-realtime-preview-2024-12-17).', 'ai-commander' ); ?>
        </p>
        <?php
    }

    /**
     * Render the Realtime voice field.
     */
    public function render_realtime_voice_field() {
        $voice = get_option( 'ai_commander_realtime_voice', 'verse' );
        $available_voices = array( 'alloy', 'ash', 'ballad', 'coral', 'echo', 'sage', 'shimmer', 'verse' ); // From OpenAI docs
        ?>
        <select id="ai_commander_realtime_voice" name="ai_commander_realtime_voice">
            <?php foreach ( $available_voices as $v ) : ?>
                <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $voice, $v ); ?>>
                    <?php echo esc_html( ucfirst( $v ) ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select the voice for the AI assistant\'s audio responses.', 'ai-commander' ); ?>
        </p>
        <?php
    }

    /**
     * Render the Realtime system prompt field.
     */
    public function render_realtime_system_prompt_field() {
        $realtime_prompt = get_option( 'ai_commander_realtime_system_prompt', '' );
        ?>
        <textarea id="ai_commander_realtime_system_prompt" name="ai_commander_realtime_system_prompt" rows="4" class="large-text code"><?php echo esc_textarea( $realtime_prompt ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Optional: Add specific instructions for the Realtime voice assistant. This will be appended to the main System Prompt.', 'ai-commander' ); ?>
        </p>
        <?php
    }
}
