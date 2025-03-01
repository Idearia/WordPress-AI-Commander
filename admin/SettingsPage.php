<?php
/**
 * Settings Page Class
 *
 * @package WPNL
 */

namespace WPNL\Admin;

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
            __( 'Settings', 'wpnl' ),
            __( 'Settings', 'wpnl' ),
            'manage_options', // Only administrators can access settings
            'wpnl-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        // Register the settings
        register_setting(
            'wpnl_settings',
            'wpnl_openai_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
        
        register_setting(
            'wpnl_settings',
            'wpnl_openai_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-4o',
            )
        );
        
        register_setting(
            'wpnl_settings',
            'wpnl_debug_mode',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
        );
        
        // Speech-to-text settings
        register_setting(
            'wpnl_settings',
            'wpnl_enable_speech_to_text',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true,
            )
        );
        
        register_setting(
            'wpnl_settings',
            'wpnl_speech_language',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
        
        // Add settings sections
        add_settings_section(
            'wpnl_openai_settings',
            __( 'OpenAI API Settings', 'wpnl' ),
            array( $this, 'render_openai_settings_section' ),
            'wpnl_settings'
        );
        
        add_settings_section(
            'wpnl_speech_settings',
            __( 'Speech-to-Text Settings', 'wpnl' ),
            array( $this, 'render_speech_settings_section' ),
            'wpnl_settings'
        );
        
        // Add settings fields for OpenAI API
        add_settings_field(
            'wpnl_openai_api_key',
            __( 'API Key', 'wpnl' ),
            array( $this, 'render_api_key_field' ),
            'wpnl_settings',
            'wpnl_openai_settings'
        );
        
        add_settings_field(
            'wpnl_openai_model',
            __( 'Model', 'wpnl' ),
            array( $this, 'render_model_field' ),
            'wpnl_settings',
            'wpnl_openai_settings'
        );
        
        add_settings_field(
            'wpnl_debug_mode',
            __( 'Debug Mode', 'wpnl' ),
            array( $this, 'render_debug_mode_field' ),
            'wpnl_settings',
            'wpnl_openai_settings'
        );
        
        // Add settings fields for Speech-to-Text
        add_settings_field(
            'wpnl_enable_speech_to_text',
            __( 'Enable Speech-to-Text', 'wpnl' ),
            array( $this, 'render_enable_speech_field' ),
            'wpnl_settings',
            'wpnl_speech_settings'
        );
        
        add_settings_field(
            'wpnl_speech_language',
            __( 'Language', 'wpnl' ),
            array( $this, 'render_speech_language_field' ),
            'wpnl_settings',
            'wpnl_speech_settings'
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
                    <?php esc_html_e( 'Overview', 'wpnl' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpnl-chatbot' ) ); ?>" class="nav-tab">
                    <?php esc_html_e( 'Chatbot', 'wpnl' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpnl-settings' ) ); ?>" class="nav-tab nav-tab-active">
                    <?php esc_html_e( 'Settings', 'wpnl' ); ?>
                </a>
            </h2>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wpnl_settings' );
                do_settings_sections( 'wpnl_settings' );
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2><?php esc_html_e( 'Getting an OpenAI API Key', 'wpnl' ); ?></h2>
                <ol>
                    <li><?php esc_html_e( 'Go to the OpenAI website and sign up for an account if you don\'t already have one.', 'wpnl' ); ?></li>
                    <li><?php esc_html_e( 'Navigate to the API section of your account.', 'wpnl' ); ?></li>
                    <li><?php esc_html_e( 'Create a new API key and copy it.', 'wpnl' ); ?></li>
                    <li><?php esc_html_e( 'Paste the API key into the field above and save your settings.', 'wpnl' ); ?></li>
                </ol>
                <p>
                    <a href="https://platform.openai.com/api-keys" target="_blank" class="button">
                        <?php esc_html_e( 'Get API Key from OpenAI', 'wpnl' ); ?>
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
        <p><?php esc_html_e( 'Configure your OpenAI API settings below. An API key is required for the chatbot to function.', 'wpnl' ); ?></p>
        <?php
    }

    /**
     * Render the API key field.
     */
    public function render_api_key_field() {
        $api_key = get_option( 'wpnl_openai_api_key', '' );
        $masked_key = ! empty( $api_key ) ? substr( $api_key, 0, 4 ) . '...' . substr( $api_key, -4 ) : '';
        ?>
        <input type="password" id="wpnl_openai_api_key" name="wpnl_openai_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off" />
        <p class="description">
            <?php
            if ( ! empty( $api_key ) ) {
                printf(
                    /* translators: %s: Masked API key */
                    esc_html__( 'Current key: %s', 'wpnl' ),
                    '<code>' . esc_html( $masked_key ) . '</code>'
                );
            } else {
                esc_html_e( 'Enter your OpenAI API key here.', 'wpnl' );
            }
            ?>
        </p>
        <?php
    }

    /**
     * Render the model field.
     */
    public function render_model_field() {
        $model = get_option( 'wpnl_openai_model', 'gpt-4o' );
        ?>
        <input type="text" id="wpnl_openai_model" name="wpnl_openai_model" value="<?php echo esc_attr( $model ); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e( 'Enter the OpenAI model to use (e.g., gpt-4o). GPT-4 models provide better results but may be more expensive.', 'wpnl' ); ?>
        </p>
        <?php
    }

    /**
     * Render the debug mode field.
     */
    public function render_debug_mode_field() {
        $debug_mode = get_option( 'wpnl_debug_mode', false );
        ?>
        <label for="wpnl_debug_mode">
            <input type="checkbox" id="wpnl_debug_mode" name="wpnl_debug_mode" value="1" <?php checked( $debug_mode, true ); ?> />
            <?php esc_html_e( 'Enable debug logging', 'wpnl' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, detailed API request and response information will be logged to the WordPress debug log. This is useful for troubleshooting but should be disabled in production.', 'wpnl' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render the speech settings section.
     */
    public function render_speech_settings_section() {
        ?>
        <p><?php esc_html_e( 'Configure speech-to-text settings for the chatbot interface. This feature uses OpenAI\'s Whisper API to transcribe spoken messages.', 'wpnl' ); ?></p>
        <?php
    }
    
    /**
     * Render the enable speech field.
     */
    public function render_enable_speech_field() {
        $enable_speech = get_option( 'wpnl_enable_speech_to_text', true );
        ?>
        <label for="wpnl_enable_speech_to_text">
            <input type="checkbox" id="wpnl_enable_speech_to_text" name="wpnl_enable_speech_to_text" value="1" <?php checked( $enable_speech, true ); ?> />
            <?php esc_html_e( 'Enable speech-to-text functionality', 'wpnl' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, users can record voice messages using a microphone button in the chat interface.', 'wpnl' ); ?>
        </p>
        <?php
    }
    
    /**
     * Render the speech language field.
     */
    public function render_speech_language_field() {
        $language = get_option( 'wpnl_speech_language', '' );
        $languages = array(
            '' => __( 'Auto-detect (recommended)', 'wpnl' ),
            'en' => __( 'English', 'wpnl' ),
            'es' => __( 'Spanish', 'wpnl' ),
            'fr' => __( 'French', 'wpnl' ),
            'de' => __( 'German', 'wpnl' ),
            'it' => __( 'Italian', 'wpnl' ),
            'pt' => __( 'Portuguese', 'wpnl' ),
            'nl' => __( 'Dutch', 'wpnl' ),
            'ja' => __( 'Japanese', 'wpnl' ),
            'zh' => __( 'Chinese', 'wpnl' ),
            'ru' => __( 'Russian', 'wpnl' ),
            'ar' => __( 'Arabic', 'wpnl' ),
            'hi' => __( 'Hindi', 'wpnl' ),
        );
        ?>
        <select id="wpnl_speech_language" name="wpnl_speech_language">
            <?php foreach ( $languages as $code => $name ) : ?>
                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $language, $code ); ?>>
                    <?php echo esc_html( $name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select a language to improve transcription accuracy. Auto-detect works well for most cases, but specifying a language can improve accuracy and processing speed.', 'wpnl' ); ?>
        </p>
        <?php
    }
}
