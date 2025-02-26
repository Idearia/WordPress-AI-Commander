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
use WPNaturalLanguageCommands\Includes\ToolRegistry;
use WPNaturalLanguageCommands\Tools\PostCreationTool;
use WPNaturalLanguageCommands\Tools\PostEditingTool;
use WPNaturalLanguageCommands\Tools\ContentOrganizationTool;
use WPNaturalLanguageCommands\Tools\ContentRetrievalTool;

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
    // Activation tasks
    // Create necessary database tables if needed
    // Set default options
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
}

/**
 * Initialize the plugin.
 */
function wp_natural_language_commands_init() {
    // Load dependencies
    wp_natural_language_commands_load_dependencies();
    
    // Initialize the tool registry
    $tool_registry = ToolRegistry::get_instance();
    
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

/**
 * AJAX handler for processing chatbot commands.
 */
function wp_natural_language_commands_process_command() {
    // Check nonce for security
    check_ajax_referer( 'wp_nlc_nonce', 'nonce' );
    
    // Check user capabilities
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
    }
    
    // Get the command from the request
    $command = isset( $_POST['command'] ) ? sanitize_text_field( $_POST['command'] ) : '';
    
    if ( empty( $command ) ) {
        wp_send_json_error( array( 'message' => 'No command provided' ) );
    }
    
    // Process the command
    $command_processor = new CommandProcessor();
    $result = $command_processor->process( $command );
    
    // Return the result
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_wp_nlc_process_command', 'wp_natural_language_commands_process_command' );

/**
 * AJAX handler for executing tools.
 */
function wp_natural_language_commands_execute_tool() {
    // Check nonce for security
    check_ajax_referer( 'wp_nlc_nonce', 'nonce' );
    
    // Check user capabilities
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
    }
    
    // Get the tool and parameters from the request
    $tool = isset( $_POST['tool'] ) ? sanitize_text_field( $_POST['tool'] ) : '';
    $params = isset( $_POST['params'] ) ? $_POST['params'] : array();
    
    if ( empty( $tool ) ) {
        wp_send_json_error( array( 'message' => 'No tool specified' ) );
    }
    
    // Get the tool registry
    $tool_registry = ToolRegistry::get_instance();
    
    // Check if the tool exists
    if ( ! $tool_registry->has_tool( $tool ) ) {
        wp_send_json_error( array( 'message' => 'Tool not found: ' . $tool ) );
    }
    
    // Execute the tool
    try {
        $result = $tool_registry->execute_tool( $tool, $params );
        wp_send_json_success( $result );
    } catch ( Exception $e ) {
        wp_send_json_error( array( 'message' => $e->getMessage() ) );
    }
}
add_action( 'wp_ajax_wp_nlc_execute_tool', 'wp_natural_language_commands_execute_tool' );
