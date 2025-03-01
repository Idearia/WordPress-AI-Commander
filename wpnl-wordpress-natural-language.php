<?php
/**
 * Plugin Name: WPNL: WordPress Natural Language
 * Plugin URI: https://github.com/Idearia/WPNL-WordPress-Natural-Language
 * Description: Issue commands in natural language to WordPress via voice, chatbot interface or REST API endpoint
 * Version: 1.0.0
 * Author: WordPress Developer
 * Author URI: https://github.com/Idearia
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wpnl
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

use WPNL\Admin\AdminPage;
use WPNL\Admin\ChatbotPage;
use WPNL\Admin\SettingsPage;
use WPNL\Includes\RestApi;
use WPNL\Includes\ToolRegistry;
use WPNL\Tools\PostCreationTool;
use WPNL\Tools\PostEditingTool;
use WPNL\Tools\ContentOrganizationTool;
use WPNL\Tools\ContentRetrievalTool;
use WPNL\Tools\SiteInformationTool;
use WPNL\Tools\DateTool;

/**
 * Currently plugin version.
 */
define( 'WPNL_VERSION', '1.0.0' );
define( 'WPNL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPNL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_wpnl() {
    global $wpdb;
    
    // Include WordPress database upgrade functions
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Define table names with proper prefixing
    $conversations_table = $wpdb->prefix . 'wpnl_conversations';
    $messages_table = $wpdb->prefix . 'wpnl_messages';
    
    // SQL for conversations table
    $conversations_sql = "CREATE TABLE $conversations_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        conversation_uuid VARCHAR(36) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY conversation_uuid (conversation_uuid),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    // SQL for messages table
    $messages_sql = "CREATE TABLE $messages_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        conversation_id BIGINT(20) UNSIGNED NOT NULL,
        role VARCHAR(20) NOT NULL,
        content LONGTEXT,
        tool_calls LONGTEXT,
        tool_call_id VARCHAR(255),
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY conversation_id (conversation_id)
    ) $charset_collate;";
    
    // Use dbDelta to create/update tables
    dbDelta( $conversations_sql );
    dbDelta( $messages_sql );
    
    // Add version to options for future updates
    add_option( 'wpnl_db_version', WPNL_VERSION );
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wpnl() {
    // Deactivation tasks
    // Clean up if necessary
}

register_activation_hook( __FILE__, 'activate_wpnl' );
register_deactivation_hook( __FILE__, 'deactivate_wpnl' );

/**
 * Load the required dependencies for this plugin.
 */
function wpnl_load_dependencies() {
    // Include core plugin classes
    require_once WPNL_PLUGIN_DIR . 'includes/ToolRegistry.php';
    require_once WPNL_PLUGIN_DIR . 'includes/OpenaiClient.php';
    require_once WPNL_PLUGIN_DIR . 'includes/CommandProcessor.php';
    require_once WPNL_PLUGIN_DIR . 'includes/ConversationManager.php';
    
    // Include service classes
    require_once WPNL_PLUGIN_DIR . 'includes/Services/ConversationService.php';
    
    // Include REST API class
    require_once WPNL_PLUGIN_DIR . 'includes/RestApi.php';
    
    // Include AJAX handlers class
    require_once WPNL_PLUGIN_DIR . 'includes/AjaxHandlers.php';
    
    // Include admin classes
    require_once WPNL_PLUGIN_DIR . 'admin/AdminPage.php';
    require_once WPNL_PLUGIN_DIR . 'admin/ChatbotPage.php';
    require_once WPNL_PLUGIN_DIR . 'admin/SettingsPage.php';
    
    // Include base tool class
    require_once WPNL_PLUGIN_DIR . 'tools/BaseTool.php';
    
    // Include specific tool implementations
    require_once WPNL_PLUGIN_DIR . 'tools/PostCreationTool.php';
    require_once WPNL_PLUGIN_DIR . 'tools/PostEditingTool.php';
    require_once WPNL_PLUGIN_DIR . 'tools/ContentOrganizationTool.php';
    require_once WPNL_PLUGIN_DIR . 'tools/ContentRetrievalTool.php';
    require_once WPNL_PLUGIN_DIR . 'tools/SiteInformationTool.php';
    require_once WPNL_PLUGIN_DIR . 'tools/DateTool.php';
}

/**
 * Check if database needs updating.
 */
function wpnl_check_db_updates() {
    $current_version = get_option( 'wpnl_db_version', '0' );
    
    if ( version_compare( $current_version, WPNL_VERSION, '<' ) ) {
        // Run activation function to update tables
        activate_wpnl();
        
        // Update version in options
        update_option( 'wpnl_db_version', WPNL_VERSION );
    }
}
add_action( 'plugins_loaded', 'wpnl_check_db_updates', 5 ); // Run before main init

/**
 * Initialize the plugin.
 */
function wpnl_init() {
    // Load dependencies
    wpnl_load_dependencies();
    
    // Initialize the tool registry
    $tool_registry = ToolRegistry::get_instance();
    
    // Initialize the REST API
    $rest_api = new RestApi();
    
    // Initialize the AJAX handlers
    $ajax_handlers = new WPNL\Includes\AjaxHandlers();
    
    // Register admin pages
    if ( is_admin() ) {
        // Only create one instance of the admin page class
        // The child classes will add their own submenu items
        $admin_page = new AdminPage();
        
        // Create instances of the child classes
        $chatbot_page = new ChatbotPage();
        $settings_page = new SettingsPage();
    }
}
add_action( 'plugins_loaded', 'wpnl_init' );

/**
 * Register all available tools.
 */
function wpnl_register_tools() {
    // Initialize all tool classes
    new PostCreationTool();
    new PostEditingTool();
    new ContentOrganizationTool();
    new ContentRetrievalTool();
    new SiteInformationTool();
    new DateTool();
    
    // You can add more tools here as they are developed
}
add_action( 'init', 'wpnl_register_tools' );

/**
 * Enqueue scripts and styles for admin pages.
 */
function wpnl_enqueue_admin_scripts( $hook ) {
    // Only load on our plugin pages
    if ( strpos( $hook, 'wpnl' ) === false ) {
        return;
    }
    
    // Note: The chat interface scripts and styles are now loaded in ChatbotPage class
    // This function is kept for backward compatibility and for loading common scripts
    
    // Localize script with necessary data
    wp_localize_script( 'jquery', 'wpnlData', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'wpnl_nonce' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'wpnl_enqueue_admin_scripts' );

// AJAX handlers have been moved to the AjaxHandlers class
