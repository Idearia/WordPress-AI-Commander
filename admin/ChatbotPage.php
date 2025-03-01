<?php
/**
 * Chatbot Page Class
 *
 * @package WPNL
 */

namespace WPNL\Admin;

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
            __( 'Chatbot', 'wpnl' ),
            __( 'Chatbot', 'wpnl' ),
            $this->capability,
            'wpnl-chatbot',
            array( $this, 'render_chatbot_page' )
        );
    }

    /**
     * Enqueue chat interface scripts and styles.
     */
    public function enqueue_chat_interface_scripts( $hook ) {
        // Only load in the chatbot page
        if ( $hook !== 'wpnl_page_wpnl-chatbot' ) {
            return;
        }

        // Enqueue React and ReactDOM from WordPress
        wp_enqueue_script( 'wp-element' );
        
        // Enqueue chat interface assets
        wp_enqueue_style( 'wpnl-chat-interface', WPNL_PLUGIN_URL . 'assets/css/chat-interface.css', array(), WPNL_VERSION );
        wp_enqueue_script( 'wpnl-react-chat-interface', WPNL_PLUGIN_URL . 'assets/js/react-chat-interface.js', array( 'jquery', 'wp-element' ), WPNL_VERSION, true );
        
        // Localize script with necessary data
        wp_localize_script( 'wpnl-react-chat-interface', 'wpnlData', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wpnl_nonce' ),
            'api_key' => get_option( 'wpnl_openai_api_key', '' ),
            'model' => get_option( 'wpnl_openai_model', 'gpt-4o' ),
            'enable_speech_to_text' => get_option( 'wpnl_enable_speech_to_text', '1' ),
            'speech_language' => get_option( 'wpnl_speech_language', '' ),
        ) );
    }

    /**
     * Render the chatbot page.
     */
    public function render_chatbot_page() {
        // Check if the OpenAI API key is set
        $api_key = get_option( 'wpnl_openai_api_key', '' );
        $api_key_set = ! empty( $api_key );
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->parent_slug ) ); ?>" class="nav-tab">
                    <?php esc_html_e( 'Overview', 'wpnl' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpnl-chatbot' ) ); ?>" class="nav-tab nav-tab-active">
                    <?php esc_html_e( 'Chatbot', 'wpnl' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpnl-settings' ) ); ?>" class="nav-tab">
                    <?php esc_html_e( 'Settings', 'wpnl' ); ?>
                </a>
            </h2>
            
            <div class="tab-content">
                <?php if ( ! $api_key_set ) : ?>
                    <div class="notice notice-warning">
                        <p>
                            <?php
                            echo sprintf(
                                /* translators: %s: URL to settings page */
                                esc_html__( 'OpenAI API key is not set. Please configure it in the %s.', 'wpnl' ),
                                '<a href="' . esc_url( admin_url( 'admin.php?page=wpnl-settings' ) ) . '">' . esc_html__( 'Settings', 'wpnl' ) . '</a>'
                            );
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <?php if ( $api_key_set ) : ?>
                        <div id="wpnl-chat-interface">
                            <!-- React chat interface will be initialized here via JavaScript -->
                            <div class="wpnl-loading">
                                <span class="spinner is-active"></span>
                                <?php esc_html_e( 'Loading chatbot interface...', 'wpnl' ); ?>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="wpnl-no-api-key">
                            <p><?php esc_html_e( 'Please set your OpenAI API key in the Settings page to use the chatbot.', 'wpnl' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <h2><?php esc_html_e( 'Tips for Using the Chatbot', 'wpnl' ); ?></h2>
                    <ul>
                        <li><?php esc_html_e( 'Be specific about what you want to create or edit.', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'You can use voice input by clicking the microphone button next to the send button.', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'To record a voice message, click the microphone button, speak your command, then click the button again to stop recording and send.', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'For best results with voice input, speak clearly and use natural language.', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'Example commands:', 'wpnl' ); ?>
                            <ul>
                                <li><?php esc_html_e( '"Create a new draft post titled \'Summer Recipes\' with three Italian recipes"', 'wpnl' ); ?></li>
                                <li><?php esc_html_e( '"Edit the title into \'Italian Summer Recipes" and publish it"', 'wpnl' ); ?></li>
                                <li><?php esc_html_e( '"Add the tag \'recipes\' to the post', 'wpnl' ); ?></li>
                            </ul>
                        </li>
                    </ul>
                </div>
                
                <?php if ( get_option( 'wpnl_enable_speech_to_text', true ) ) : ?>
                <div class="card">
                    <h2><?php esc_html_e( 'Speech-to-Text Requirements', 'wpnl' ); ?></h2>
                    <p><?php esc_html_e( 'To use the speech-to-text feature, please ensure:', 'wpnl' ); ?></p>
                    <ul>
                        <li><?php esc_html_e( 'Your site is served over HTTPS (required for microphone access in most browsers).', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'You are using a modern browser that supports the MediaRecorder API (Chrome, Edge, Firefox, Safari 14.1+).', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'You have granted microphone permissions to this site. Look for the microphone icon in your browser\'s address bar to check or change permissions.', 'wpnl' ); ?></li>
                    </ul>
                    <p>
                        <?php esc_html_e( 'Note: If you\'re using this plugin on a local development environment, you can access it via http://localhost without HTTPS.', 'wpnl' ); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
    }
}
