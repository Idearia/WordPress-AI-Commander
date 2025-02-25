<?php
/**
 * Settings Page Class
 *
 * @package WP_Natural_Language_Commands
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Settings page class.
 *
 * This class handles the settings page in the admin.
 */
class WP_NLC_Settings_Page extends WP_NLC_Admin_Page {

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
            __( 'Settings', 'wp-natural-language-commands' ),
            __( 'Settings', 'wp-natural-language-commands' ),
            'manage_options', // Only administrators can access settings
            'wp-natural-language-commands-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        // Register the settings
        register_setting(
            'wp_nlc_settings',
            'wp_nlc_openai_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
        
        register_setting(
            'wp_nlc_settings',
            'wp_nlc_openai_model',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'gpt-4-turbo',
            )
        );
        
        // Add settings section
        add_settings_section(
            'wp_nlc_openai_settings',
            __( 'OpenAI API Settings', 'wp-natural-language-commands' ),
            array( $this, 'render_openai_settings_section' ),
            'wp_nlc_settings'
        );
        
        // Add settings fields
        add_settings_field(
            'wp_nlc_openai_api_key',
            __( 'API Key', 'wp-natural-language-commands' ),
            array( $this, 'render_api_key_field' ),
            'wp_nlc_settings',
            'wp_nlc_openai_settings'
        );
        
        add_settings_field(
            'wp_nlc_openai_model',
            __( 'Model', 'wp-natural-language-commands' ),
            array( $this, 'render_model_field' ),
            'wp_nlc_settings',
            'wp_nlc_openai_settings'
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
                    <?php esc_html_e( 'Overview', 'wp-natural-language-commands' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-natural-language-commands-chatbot' ) ); ?>" class="nav-tab">
                    <?php esc_html_e( 'Chatbot', 'wp-natural-language-commands' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-natural-language-commands-settings' ) ); ?>" class="nav-tab nav-tab-active">
                    <?php esc_html_e( 'Settings', 'wp-natural-language-commands' ); ?>
                </a>
            </h2>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wp_nlc_settings' );
                do_settings_sections( 'wp_nlc_settings' );
                submit_button();
                ?>
            </form>
            
            <div class="card">
                <h2><?php esc_html_e( 'Getting an OpenAI API Key', 'wp-natural-language-commands' ); ?></h2>
                <ol>
                    <li><?php esc_html_e( 'Go to the OpenAI website and sign up for an account if you don\'t already have one.', 'wp-natural-language-commands' ); ?></li>
                    <li><?php esc_html_e( 'Navigate to the API section of your account.', 'wp-natural-language-commands' ); ?></li>
                    <li><?php esc_html_e( 'Create a new API key and copy it.', 'wp-natural-language-commands' ); ?></li>
                    <li><?php esc_html_e( 'Paste the API key into the field above and save your settings.', 'wp-natural-language-commands' ); ?></li>
                </ol>
                <p>
                    <a href="https://platform.openai.com/api-keys" target="_blank" class="button">
                        <?php esc_html_e( 'Get API Key from OpenAI', 'wp-natural-language-commands' ); ?>
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
        <p><?php esc_html_e( 'Configure your OpenAI API settings below. An API key is required for the chatbot to function.', 'wp-natural-language-commands' ); ?></p>
        <?php
    }

    /**
     * Render the API key field.
     */
    public function render_api_key_field() {
        $api_key = get_option( 'wp_nlc_openai_api_key', '' );
        $masked_key = ! empty( $api_key ) ? substr( $api_key, 0, 4 ) . '...' . substr( $api_key, -4 ) : '';
        ?>
        <input type="password" id="wp_nlc_openai_api_key" name="wp_nlc_openai_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="off" />
        <p class="description">
            <?php
            if ( ! empty( $api_key ) ) {
                printf(
                    /* translators: %s: Masked API key */
                    esc_html__( 'Current key: %s', 'wp-natural-language-commands' ),
                    '<code>' . esc_html( $masked_key ) . '</code>'
                );
            } else {
                esc_html_e( 'Enter your OpenAI API key here.', 'wp-natural-language-commands' );
            }
            ?>
        </p>
        <?php
    }

    /**
     * Render the model field.
     */
    public function render_model_field() {
        $model = get_option( 'wp_nlc_openai_model', 'gpt-4-turbo' );
        $models = array(
            'gpt-4-turbo' => __( 'GPT-4 Turbo (Recommended)', 'wp-natural-language-commands' ),
            'gpt-4' => __( 'GPT-4', 'wp-natural-language-commands' ),
            'gpt-3.5-turbo' => __( 'GPT-3.5 Turbo', 'wp-natural-language-commands' ),
        );
        ?>
        <select id="wp_nlc_openai_model" name="wp_nlc_openai_model">
            <?php foreach ( $models as $model_id => $model_name ) : ?>
                <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $model, $model_id ); ?>>
                    <?php echo esc_html( $model_name ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select the OpenAI model to use. GPT-4 models provide better results but may be more expensive.', 'wp-natural-language-commands' ); ?>
        </p>
        <?php
    }
}
