<?php
/**
 * Chatbot Page Class
 *
 * @package WP_Natural_Language_Commands
 */

namespace WPNaturalLanguageCommands\Admin;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Chatbot page class.
 *
 * This class handles the chatbot interface in the admin using a React-based chat interface.
 */
class ChatbotPage extends AdminPage {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct();
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_chat_interface_scripts' ) );
    }

    /**
     * Add the admin menu items.
     */
    public function add_admin_menu() {
        // Don't call parent::add_admin_menu() to avoid duplicate top-level menu
        
        // Add the chatbot submenu page
        add_submenu_page(
            $this->parent_slug,
            __( 'Chatbot', 'wp-natural-language-commands' ),
            __( 'Chatbot', 'wp-natural-language-commands' ),
            $this->capability,
            'wp-natural-language-commands-chatbot',
            array( $this, 'render_chatbot_page' )
        );
    }

    /**
     * Enqueue chat interface scripts and styles.
     */
    public function enqueue_chat_interface_scripts( $hook ) {
        // Only load in the chatbot page        error_log( 'Hook: ' . $hook );
        if ( $hook !== 'nl-commands_page_wp-natural-language-commands-chatbot' ) {
            return;
        }

        // Enqueue React and ReactDOM from WordPress
        wp_enqueue_script( 'wp-element' );
        
        // Enqueue chat interface assets
        wp_enqueue_style( 'wp-nlc-chat-interface', WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_URL . 'assets/css/chat-interface.css', array(), WP_NATURAL_LANGUAGE_COMMANDS_VERSION );
        wp_enqueue_script( 'wp-nlc-react-chat-interface', WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_URL . 'assets/js/react-chat-interface.js', array( 'jquery', 'wp-element' ), WP_NATURAL_LANGUAGE_COMMANDS_VERSION, true );
        
        // Localize script with necessary data
        wp_localize_script( 'wp-nlc-react-chat-interface', 'wpNlcData', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wp_nlc_nonce' ),
            'api_key' => get_option( 'wp_nlc_openai_api_key', '' ),
            'model' => get_option( 'wp_nlc_openai_model', 'gpt-4-turbo' ),
        ) );
    }

    /**
     * Render the chatbot page.
     */
    public function render_chatbot_page() {
        // Check if the OpenAI API key is set
        $api_key = get_option( 'wp_nlc_openai_api_key', '' );
        $api_key_set = ! empty( $api_key );
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->parent_slug ) ); ?>" class="nav-tab">
                    <?php esc_html_e( 'Overview', 'wp-natural-language-commands' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-natural-language-commands-chatbot' ) ); ?>" class="nav-tab nav-tab-active">
                    <?php esc_html_e( 'Chatbot', 'wp-natural-language-commands' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-natural-language-commands-settings' ) ); ?>" class="nav-tab">
                    <?php esc_html_e( 'Settings', 'wp-natural-language-commands' ); ?>
                </a>
            </h2>
            
            <div class="tab-content">
                <?php if ( ! $api_key_set ) : ?>
                    <div class="notice notice-warning">
                        <p>
                            <?php
                            echo sprintf(
                                /* translators: %s: URL to settings page */
                                esc_html__( 'OpenAI API key is not set. Please configure it in the %s.', 'wp-natural-language-commands' ),
                                '<a href="' . esc_url( admin_url( 'admin.php?page=wp-natural-language-commands-settings' ) ) . '">' . esc_html__( 'Settings', 'wp-natural-language-commands' ) . '</a>'
                            );
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <?php if ( $api_key_set ) : ?>
                        <div id="wp-nlc-chat-interface">
                            <!-- React chat interface will be initialized here via JavaScript -->
                            <div class="wp-nlc-loading">
                                <span class="spinner is-active"></span>
                                <?php esc_html_e( 'Loading chatbot interface...', 'wp-natural-language-commands' ); ?>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="wp-nlc-no-api-key">
                            <p><?php esc_html_e( 'Please set your OpenAI API key in the Settings page to use the chatbot.', 'wp-natural-language-commands' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <h2><?php esc_html_e( 'Tips for Using the Chatbot', 'wp-natural-language-commands' ); ?></h2>
                    <ul>
                        <li><?php esc_html_e( 'Be specific about what you want to create or edit.', 'wp-natural-language-commands' ); ?></li>
                        <li><?php esc_html_e( 'You can use voice input by clicking the microphone button in the chat interface.', 'wp-natural-language-commands' ); ?></li>
                        <li><?php esc_html_e( 'For best results, speak clearly and use natural language.', 'wp-natural-language-commands' ); ?></li>
                        <li><?php esc_html_e( 'Example commands:', 'wp-natural-language-commands' ); ?>
                            <ul>
                                <li><?php esc_html_e( '"Create a new post titled \'Summer Recipes\' with the content \'Here are my favorite summer recipes...\'"', 'wp-natural-language-commands' ); ?></li>
                                <li><?php esc_html_e( '"Edit the post titled \'Welcome\' and change the content to \'Welcome to my blog!\'"', 'wp-natural-language-commands' ); ?></li>
                                <li><?php esc_html_e( '"Add the tag \'recipes\' to the post titled \'Summer Recipes\'"', 'wp-natural-language-commands' ); ?></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <?php
    }
}
