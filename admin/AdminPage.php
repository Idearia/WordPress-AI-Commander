<?php

/**
 * Admin Page Class
 *
 * @package AICommander
 */

namespace AICommander\Admin;

if (! defined('WPINC')) {
    die;
}

/**
 * Base admin page class.
 *
 * This class handles the creation of admin pages for the plugin.
 */
class AdminPage
{

    /**
     * The parent slug for admin pages.
     *
     * @var string
     */
    protected $parent_slug = 'ai-commander';

    /**
     * The capability required to access admin pages.
     *
     * @var string
     */
    protected $capability = 'edit_posts';

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_overview_styles'));
    }

    /**
     * Enqueue styles for the overview page.
     */
    public function enqueue_overview_styles($hook)
    {
        // Only load on the main plugin page
        if ($hook !== 'toplevel_page_ai_commander') {
            return;
        }

        wp_enqueue_style('ai-commander-admin-overview', AI_COMMANDER_PLUGIN_URL . 'assets/css/admin-overview.css', array(), AI_COMMANDER_VERSION);
    }

    /**
     * Add the admin menu items.
     */
    public function add_admin_menu()
    {
        // Add the top-level menu page
        add_menu_page(
            'AI Commander',
            'AI Commander',
            $this->capability,
            $this->parent_slug,
            array($this, 'render_page'),
            'dashicons-microphone',
            30
        );

        // Add the overview submenu page with a different name to avoid duplicates
        add_submenu_page(
            $this->parent_slug,
            __('Overview', 'ai-commander'),
            __('Overview', 'ai-commander'),
            $this->capability,
            $this->parent_slug,
            array($this, 'render_page')
        );
    }

    /**
     * Render the tab navigation
     */
    public function render_tab_wrapper()
    {
        $screen = get_current_screen();
        $current_page = $screen->base;
?>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-commander')); ?>" class="nav-tab <?php echo $current_page === 'toplevel_page_ai-commander' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Overview', 'ai-commander'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-commander-chatbot')); ?>" class="nav-tab <?php echo $current_page === 'ai-commander_page_ai-commander-chatbot' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Chatbot', 'ai-commander'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-commander-realtime')); ?>" class="nav-tab <?php echo $current_page === 'ai-commander_page_ai-commander-realtime' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Realtime', 'ai-commander'); ?>
                </a>
                <?php if (current_user_can('manage_options')): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-commander-settings')); ?>" class="nav-tab <?php echo $current_page === 'ai-commander_page_ai-commander-settings' ? 'nav-tab-active' : ''; ?>">
                        <?php esc_html_e('Settings', 'ai-commander'); ?>
                    </a>
                <?php endif; ?>
            </h2>
<?php
    }

    /**
     * Render the admin page.
     */
    public function render_page()
    {
?>
        <div class="wrap ai-commander-overview">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php esc_html_e('Welcome to AI Commander! Use the tabs below to navigate.', 'ai-commander'); ?></p>

            <?php $this->render_tab_wrapper(); ?>

            <div class="tab-content">
                <div class="card">
                    <h2><?php esc_html_e('About AI Commander', 'ai-commander'); ?></h2>
                    <p><?php esc_html_e('This plugin allows you to issue commands to WordPress using natural language through a chatbot interface.', 'ai-commander'); ?></p>
                    <p><?php esc_html_e('You can create and edit posts, manage categories and tags, and more, all using simple, conversational language.', 'ai-commander'); ?></p>
                </div>

                <div class="card">
                    <h2><?php esc_html_e('Getting Started', 'ai-commander'); ?></h2>
                    <ol>
                        <li><?php esc_html_e('Go to the Settings tab and enter your OpenAI API key.', 'ai-commander'); ?></li>
                        <li><?php esc_html_e('Navigate to the Chatbot tab to start issuing commands.', 'ai-commander'); ?></li>
                        <li><?php esc_html_e('Try saying something like "Create a post on the topic \'Best practices to index a website on Google\' and tag it as \'SEO\' and \'How-to\'.', 'ai-commander'); ?></li>
                    </ol>
                </div>

                <div class="card available-commands">
                    <h2><?php esc_html_e('Available Commands', 'ai-commander'); ?></h2>
                    <p><?php esc_html_e('You can use natural language to perform the following actions:', 'ai-commander'); ?></p>
                    <ul>
                        <?php
                        // Get all registered tools from the ToolRegistry
                        $tool_registry = \AICommander\Includes\ToolRegistry::get_instance();
                        $tools = $tool_registry->get_tools();

                        // Loop through each tool and display its name and description
                        foreach ($tools as $tool_name => $tool) {
                            $description = $tool->get_description();
                            $has_permission = current_user_can($tool->get_required_capability());

                            // If user doesn't have permission, show the tool as disabled
                            if (! $has_permission) {
                                echo '<li class="tool-disabled" style="opacity: 0.5; text-decoration: line-through;">';
                            } else {
                                echo '<li>';
                            }

                            echo '<strong>' . esc_html($tool_name) . '</strong>: ' . esc_html($description);
                            echo '</li>';
                        }

                        // If no tools are registered, show a message
                        if (empty($tools)) {
                            echo '<li>' . esc_html__('No commands available. Please check your plugin configuration.', 'ai-commander') . '</li>';
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
<?php
    }
}
