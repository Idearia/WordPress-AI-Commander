<?php

/**
 * Realtime Page Class
 *
 * @package AICommander
 */

namespace AICommander\Admin;

if (! defined('WPINC')) {
    die;
}

/**
 * Realtime admin page class.
 *
 * This class handles the Realtime conversation page in the admin.
 */
class RealtimePage extends AdminPage
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Add the admin menu items.
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            $this->parent_slug,
            __('Realtime', 'ai-commander'),
            __('Realtime', 'ai-commander'),
            $this->capability,
            'ai-commander-realtime',
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue scripts and styles for the Realtime page.
     */
    public function enqueue_scripts($hook)
    {
        // Only load on the Realtime page
        if ($hook !== 'ai-commander_page_ai-commander-realtime') {
            return;
        }

        // Enqueue realtime scritp (with React and ReactDOM as dependencies)
        wp_enqueue_script(
            'ai-commander-realtime-interface',
            AI_COMMANDER_PLUGIN_URL . 'assets/js/react-realtime-interface.js',
            array('wp-element', 'jquery', 'wp-i18n'), // Add wp-element for React and wp-i18n for translations
            AI_COMMANDER_VERSION,
            true // Load in footer
        );

        // Set up translations for JavaScript
        wp_set_script_translations('ai-commander-realtime-interface', 'ai-commander', plugin_dir_path(dirname(__FILE__)) . 'languages');

        // Enqueue styles
        wp_enqueue_style(
            'ai-commander-realtime-interface-styles',
            AI_COMMANDER_PLUGIN_URL . 'assets/css/realtime-interface.css',
            array(),
            AI_COMMANDER_VERSION
        );

        // Enqueue admin.css for chat bubbles
        wp_enqueue_style(
            'ai-commander-admin-styles',
            AI_COMMANDER_PLUGIN_URL . 'assets/css/ai-commander-admin.css',
            array(),
            AI_COMMANDER_VERSION
        );

        // Localize script with necessary data
        wp_localize_script(
            'ai-commander-realtime-interface',
            'aiCommanderRealtimeData', // Use a different name from the chatbot's data
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ai_commander_nonce'),
                'realtime_api_base_url' => 'https://api.openai.com/v1/realtime',
                'realtime_model' => get_option('ai_commander_openai_realtime_model', 'gpt-4o-realtime-preview-2025-06-03'),
                'realtime_voice' => get_option('ai_commander_realtime_voice', 'verse'),
                'use_custom_tts' => (bool) get_option('ai_commander_use_custom_tts', false),
                'realtime_show_tool_calls' => (bool) get_option('ai_commander_realtime_show_tool_calls', true),
            )
        );
    }

    /**
     * Render the admin page.
     */
    public function render_page()
    {
?>
        <div class="wrap ai-commander-realtime-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php $this->render_tab_wrapper(); ?>

            <p><?php esc_html_e('Start a real-time voice conversation with AI Commander.', 'ai-commander'); ?></p>

            <?php
            // Check if OpenAI API key is set
            $api_key = get_option('ai_commander_openai_api_key', '');
            if (empty($api_key)) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    /* translators: %s: Link to settings page */
                    wp_kses_post(__('Error: OpenAI API Key is not set. Please <a href="%s">configure it in the settings</a> to use the Realtime feature.', 'ai-commander')),
                    esc_url(admin_url('admin.php?page=ai-commander-settings'))
                );
                echo '</p></div>';
            } else {
                // Container for the React app
                echo '<div id="ai-commander-realtime-interface"><p>Loading Realtime Interface...</p></div>';
            }
            ?>
        </div>
<?php
    }
}
