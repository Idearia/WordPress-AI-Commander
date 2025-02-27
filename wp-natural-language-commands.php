<?php
/**
 * Plugin Name: WP Natural Language Commands
 * Plugin URI: https://example.com/wp-natural-language-commands
 * Description: A WordPress plugin that allows users to issue commands in natural language via a chatbot interface.
 * Version: 1.0.0
 * Author: WordPress Developer
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-natural-language-commands
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

use WPNaturalLanguageCommands\Admin\AdminPage;
use WPNaturalLanguageCommands\Admin\ChatbotPage;
use WPNaturalLanguageCommands\Admin\SettingsPage;
use WPNaturalLanguageCommands\Includes\CommandProcessor;
use WPNaturalLanguageCommands\Includes\RestApi;
use WPNaturalLanguageCommands\Includes\ToolRegistry;
use WPNaturalLanguageCommands\Tools\PostCreationTool;
use WPNaturalLanguageCommands\Tools\PostEditingTool;
use WPNaturalLanguageCommands\Tools\ContentOrganizationTool;
use WPNaturalLanguageCommands\Tools\ContentRetrievalTool;
use WPNaturalLanguageCommands\Tools\SiteInformationTool;

/**
 * Currently plugin version.
 */
define( 'WP_NATURAL_LANGUAGE_COMMANDS_VERSION', '1.0.0' );
define( 'WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_wp_natural_language_commands() {
    global $wpdb;
    
    // Include WordPress database upgrade functions
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Define table names with proper prefixing
    $conversations_table = $wpdb->prefix . 'nlc_conversations';
    $messages_table = $wpdb->prefix . 'nlc_messages';
    
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
    add_option( 'wp_nlc_db_version', WP_NATURAL_LANGUAGE_COMMANDS_VERSION );
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wp_natural_language_commands() {
    // Deactivation tasks
    // Clean up if necessary
}

register_activation_hook( __FILE__, 'activate_wp_natural_language_commands' );
register_deactivation_hook( __FILE__, 'deactivate_wp_natural_language_commands' );

/**
 * Load the required dependencies for this plugin.
 */
function wp_natural_language_commands_load_dependencies() {
    // Include core plugin classes
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'includes/ToolRegistry.php';
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'includes/OpenaiClient.php';
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'includes/CommandProcessor.php';
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'includes/ConversationManager.php';
    
    // Include service classes
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'includes/Services/ConversationService.php';
    
    // Include REST API class
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'includes/RestApi.php';
    
    // Include AJAX handlers class
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'includes/AjaxHandlers.php';
    
    // Include admin classes
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'admin/AdminPage.php';
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'admin/ChatbotPage.php';
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'admin/SettingsPage.php';
    
    // Include base tool class
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'tools/BaseTool.php';
    
    // Include specific tool implementations
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'tools/PostCreationTool.php';
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'tools/PostEditingTool.php';
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'tools/ContentOrganizationTool.php';
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'tools/ContentRetrievalTool.php';
    require_once WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'tools/SiteInformationTool.php';
}

/**
 * Check if database needs updating.
 */
function wp_natural_language_commands_check_db_updates() {
    $current_version = get_option( 'wp_nlc_db_version', '0' );
    
    if ( version_compare( $current_version, WP_NATURAL_LANGUAGE_COMMANDS_VERSION, '<' ) ) {
        // Run activation function to update tables
        activate_wp_natural_language_commands();
        
        // Update version in options
        update_option( 'wp_nlc_db_version', WP_NATURAL_LANGUAGE_COMMANDS_VERSION );
    }
}
add_action( 'plugins_loaded', 'wp_natural_language_commands_check_db_updates', 5 ); // Run before main init

/**
 * Initialize the plugin.
 */
function wp_natural_language_commands_init() {
    // Load dependencies
    wp_natural_language_commands_load_dependencies();
    
    // Initialize the tool registry
    $tool_registry = ToolRegistry::get_instance();
    
    // Initialize the REST API
    $rest_api = new RestApi();
    
    // Initialize the AJAX handlers
    $ajax_handlers = new WPNaturalLanguageCommands\Includes\AjaxHandlers();
    
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
add_action( 'plugins_loaded', 'wp_natural_language_commands_init' );

/**
 * Register all available tools.
 */
function wp_natural_language_commands_register_tools() {
    // Initialize all tool classes
    new PostCreationTool();
    new PostEditingTool();
    new ContentOrganizationTool();
    new ContentRetrievalTool();
    new SiteInformationTool();
    
    // You can add more tools here as they are developed
}
add_action( 'init', 'wp_natural_language_commands_register_tools' );

/**
 * Enqueue scripts and styles for admin pages.
 */
function wp_natural_language_commands_enqueue_admin_scripts( $hook ) {
    // Only load on our plugin pages
    if ( strpos( $hook, 'wp-natural-language-commands' ) === false ) {
        return;
    }
    
    // Note: The chat interface scripts and styles are now loaded in ChatbotPage class
    // This function is kept for backward compatibility and for loading common scripts
    
    // Localize script with necessary data
    wp_localize_script( 'jquery', 'wpNlcData', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'wp_nlc_nonce' ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'wp_natural_language_commands_enqueue_admin_scripts' );

// AJAX handlers have been moved to the AjaxHandlers class
