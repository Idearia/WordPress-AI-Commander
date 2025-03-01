<?php
/**
 * Admin Page Class
 *
 * @package WPNL
 */

namespace WPNL\Admin;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Base admin page class.
 *
 * This class handles the creation of admin pages for the plugin.
 */
class AdminPage {

    /**
     * The parent slug for admin pages.
     *
     * @var string
     */
    protected $parent_slug = 'wpnl';

    /**
     * The capability required to access admin pages.
     *
     * @var string
     */
    protected $capability = 'edit_posts';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    /**
     * Add the admin menu items.
     */
    public function add_admin_menu() {
        // Add the top-level menu page
        add_menu_page(
            __( 'WPNL', 'wpnl' ),
            __( 'WPNL', 'wpnl' ),
            $this->capability,
            $this->parent_slug,
            array( $this, 'render_page' ),
            'dashicons-microphone',
            30
        );
        
        // Add the overview submenu page with a different name to avoid duplicates
        add_submenu_page(
            $this->parent_slug,
            __( 'Overview', 'wpnl' ),
            __( 'Overview', 'wpnl' ),
            $this->capability,
            $this->parent_slug,
            array( $this, 'render_page' )
        );
    }

    /**
     * Render the admin page.
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php esc_html_e( 'Welcome to WPNL: WordPress Natural Language. Use the tabs below to navigate.', 'wpnl' ); ?></p>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->parent_slug ) ); ?>" class="nav-tab nav-tab-active">
                    <?php esc_html_e( 'Overview', 'wpnl' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpnl-chatbot' ) ); ?>" class="nav-tab">
                    <?php esc_html_e( 'Chatbot', 'wpnl' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpnl-settings' ) ); ?>" class="nav-tab">
                    <?php esc_html_e( 'Settings', 'wpnl' ); ?>
                </a>
            </h2>
            
            <div class="tab-content">
                <div class="card">
                    <h2><?php esc_html_e( 'About WPNL: WordPress Natural Language', 'wpnl' ); ?></h2>
                    <p><?php esc_html_e( 'This plugin allows you to issue commands to WordPress using natural language through a chatbot interface.', 'wpnl' ); ?></p>
                    <p><?php esc_html_e( 'You can create and edit posts, manage categories and tags, and more, all using simple, conversational language.', 'wpnl' ); ?></p>
                </div>
                
                <div class="card">
                    <h2><?php esc_html_e( 'Getting Started', 'wpnl' ); ?></h2>
                    <ol>
                        <li><?php esc_html_e( 'Go to the Settings tab and enter your OpenAI API key.', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'Navigate to the Chatbot tab to start issuing commands.', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'Try saying something like "Create a new post titled \'Hello World\' with the content \'This is my first post created with natural language.\'".', 'wpnl' ); ?></li>
                    </ol>
                </div>
                
                <div class="card">
                    <h2><?php esc_html_e( 'Available Commands', 'wpnl' ); ?></h2>
                    <p><?php esc_html_e( 'You can use natural language to perform the following actions:', 'wpnl' ); ?></p>
                    <ul>
                        <li><?php esc_html_e( 'Create new posts and pages', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'Edit existing content', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'Assign categories and tags', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'Set featured images', 'wpnl' ); ?></li>
                        <li><?php esc_html_e( 'Schedule posts for publication', 'wpnl' ); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
}
