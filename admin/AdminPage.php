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
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_overview_styles' ) );
}

/**
 * Enqueue styles for the overview page.
 */
public function enqueue_overview_styles( $hook ) {
    // Only load on the main plugin page
    if ( $hook !== 'toplevel_page_wpnl' ) {
        return;
    }
    
    wp_enqueue_style( 'wpnl-admin-overview', WPNL_PLUGIN_URL . 'assets/css/admin-overview.css', array(), WPNL_VERSION );
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
        <div class="wrap wpnl-overview">
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
                
                <div class="card available-commands">
                    <h2><?php esc_html_e( 'Available Commands', 'wpnl' ); ?></h2>
                    <p><?php esc_html_e( 'You can use natural language to perform the following actions:', 'wpnl' ); ?></p>
                    <ul>
                        <?php
                        // Get all registered tools from the ToolRegistry
                        $tool_registry = \WPNL\Includes\ToolRegistry::get_instance();
                        $tools = $tool_registry->get_tools();
                        
                        // Loop through each tool and display its name and description
                        foreach ( $tools as $tool_name => $tool ) {
                            $description = $tool->get_description();
                            $has_permission = current_user_can( $tool->get_required_capability() );
                            
                            // If user doesn't have permission, show the tool as disabled
                            if ( ! $has_permission ) {
                                echo '<li class="tool-disabled" style="opacity: 0.5; text-decoration: line-through;">';
                            } else {
                                echo '<li>';
                            }
                            
                            echo '<strong>' . esc_html( $tool_name ) . '</strong>: ' . esc_html( $description );
                            echo '</li>';
                        }
                        
                        // If no tools are registered, show a message
                        if ( empty( $tools ) ) {
                            echo '<li>' . esc_html__( 'No commands available. Please check your plugin configuration.', 'wpnl' ) . '</li>';
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
}
