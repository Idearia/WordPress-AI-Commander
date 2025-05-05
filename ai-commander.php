<?php
/**
 * Plugin Name: AI Commander
 * Plugin URI: https://github.com/Idearia/WordPress-AI-Commander
 * Description: Control WordPress with natural language or voice, with API support
 * Version: 1.0.0
 * Author: Idearia Web Agency
 * Author URI: https://www.idearia.it
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-commander
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

use AICommander\Admin\AdminPage;
use AICommander\Admin\ChatbotPage;
use AICommander\Admin\SettingsPage;
use AICommander\Admin\RealtimePage;
use AICommander\Includes\RestApi;
use AICommander\Includes\ToolRegistry;
use AICommander\Tools\PostCreationTool;
use AICommander\Tools\PostEditingTool;
use AICommander\Tools\ContentOrganizationTool;
use AICommander\Tools\ContentRetrievalTool;
use AICommander\Tools\SiteInformationTool;
use AICommander\Tools\GetTodayDateTool;

/**
 * Currently plugin version.
 */
define( 'AI_COMMANDER_VERSION', '1.0.0' );
define( 'AI_COMMANDER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_COMMANDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_ai_commander() {
    global $wpdb;
    
    // Include WordPress database upgrade functions
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Define table names with proper prefixing
    $conversations_table = $wpdb->prefix . 'ai_commander_conversations';
    $messages_table = $wpdb->prefix . 'ai_commander_messages';
    
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
    add_option( 'ai_commander_db_version', AI_COMMANDER_VERSION );
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_ai_commander() {
    // Deactivation tasks
    // Clean up if necessary
}

register_activation_hook( __FILE__, 'activate_ai_commander' );
register_deactivation_hook( __FILE__, 'deactivate_ai_commander' );

/**
 * Load the required dependencies for this plugin.
 */
function ai_commander_load_dependencies() {
    // Include core plugin classes
    require_once AI_COMMANDER_PLUGIN_DIR . 'includes/ToolRegistry.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'includes/OpenaiClient.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'includes/CommandProcessor.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'includes/ConversationManager.php';
    
    // Include service classes
    require_once AI_COMMANDER_PLUGIN_DIR . 'includes/Services/ConversationService.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'includes/Services/PromptService.php';
    
    // Include REST API class
    require_once AI_COMMANDER_PLUGIN_DIR . 'includes/RestApi.php';
    
    // Include AJAX handlers class
    require_once AI_COMMANDER_PLUGIN_DIR . 'includes/AjaxHandlers.php';
    
    // Include admin classes
    require_once AI_COMMANDER_PLUGIN_DIR . 'admin/AdminPage.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'admin/ChatbotPage.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'admin/RealtimePage.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'admin/SettingsPage.php';

    // Include base tool class
    require_once AI_COMMANDER_PLUGIN_DIR . 'tools/BaseTool.php';
    
    // Include specific tool implementations
    require_once AI_COMMANDER_PLUGIN_DIR . 'tools/PostCreationTool.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'tools/PostEditingTool.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'tools/ContentOrganizationTool.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'tools/ContentRetrievalTool.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'tools/SiteInformationTool.php';
    require_once AI_COMMANDER_PLUGIN_DIR . 'tools/GetTodayDateTool.php';
}

/**
 * Check if database needs updating.
 */
function ai_commander_check_db_updates() {
    $current_version = get_option( 'ai_commander_db_version', '0' );
    
    if ( version_compare( $current_version, AI_COMMANDER_VERSION, '<' ) ) {
        // Run activation function to update tables
        activate_ai_commander();
        
        // Update version in options
        update_option( 'ai_commander_db_version', AI_COMMANDER_VERSION );
    }
}
add_action( 'plugins_loaded', 'ai_commander_check_db_updates', 5 ); // Run before main init

/**
 * Initialize the plugin.
 */
function ai_commander_init() {
    // Load dependencies
    ai_commander_load_dependencies();
    
    // Initialize the tool registry
    $tool_registry = ToolRegistry::get_instance();
    
    // Initialize the REST API
    $rest_api = new RestApi();
    
    // Initialize the AJAX handlers
    $ajax_handlers = new AICommander\Includes\AjaxHandlers();
    
    // Register admin pages
    if ( is_admin() ) {
        // Only create one instance of the admin page class
        // The child classes will add their own submenu items
        $admin_page = new AdminPage();
        
        // Create instances of the child classes
        $chatbot_page = new ChatbotPage();
        $realtime_page = new RealtimePage();
        $settings_page = new SettingsPage();
    }
}
add_action( 'plugins_loaded', 'ai_commander_init' );

/**
 * Register all available tools.
 */
function ai_commander_register_tools() {
    new PostCreationTool();
    new PostEditingTool();
    new ContentOrganizationTool();
    new ContentRetrievalTool();
    new SiteInformationTool();
    new GetTodayDateTool();
}
add_action( 'init', 'ai_commander_register_tools' );
