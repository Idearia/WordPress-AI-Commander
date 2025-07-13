<?php
/**
 * Mobile App Translations Service
 *
 * This class provides all translation strings for the mobile app.
 * Separating mobile translations helps translators identify which strings
 * are used in the mobile PWA versus the WordPress admin interface.
 *
 * @package AICommander
 */

namespace AICommander\Includes\Services;

if (! defined('WPINC')) {
    die;
}

/**
 * Mobile Translations class.
 *
 * Centralizes all translation strings used by the mobile application.
 * This allows translators to easily identify mobile-specific strings
 * in the .po files by their file reference.
 */
class MobileTranslations
{
    /**
     * Get all mobile app translation strings.
     *
     * @return array Array of translation key => translated string pairs.
     */
    public static function get_translations()
    {
        return array(
            // Status messages - displayed to users during app operation
            'mobile.status.disconnected' => __('Press to start', 'ai-commander'),
            'mobile.status.connecting' => __('Connecting...', 'ai-commander'),
            'mobile.status.recording' => __('Listening...', 'ai-commander'),
            'mobile.status.processing' => __('Processing...', 'ai-commander'),
            'mobile.status.speaking' => __('Response in progress...', 'ai-commander'),
            'mobile.status.speaking_interruptible' => __('Press to interrupt', 'ai-commander'),
            'mobile.status.tool_wait' => __('Executing command...', 'ai-commander'),
            'mobile.status.idle' => __('Waiting...', 'ai-commander'),
            'mobile.status.error' => __('Error', 'ai-commander'),

            // Error messages - shown when something goes wrong
            'mobile.error.invalid_url' => __('Invalid URL. Enter a complete URL (e.g. https://www.yoursite.com)', 'ai-commander'),
            'mobile.error.invalid_credentials' => __('Invalid credentials. Check username and password.', 'ai-commander'),
            'mobile.error.access_denied' => __('Access denied. Check user permissions on the site.', 'ai-commander'),
            'mobile.error.connection_failed' => __('Unable to connect to WordPress site', 'ai-commander'),
            'mobile.error.session_failed' => __('Unable to start session', 'ai-commander'),
            'mobile.error.tool_execution_failed' => __('Tool execution failed', 'ai-commander'),
            'mobile.error.network_error' => __('Network error', 'ai-commander'),
            'mobile.error.data_channel_not_open' => __('Data channel not open', 'ai-commander'),
            'mobile.error.tts_failed' => __('Error in custom audio playback.', 'ai-commander'),
            'mobile.error.communication_error' => __('Communication error', 'ai-commander'),
            'mobile.error.unknown_error' => __('Unknown error', 'ai-commander'),

            // User Interface labels and text - main app interface
            'mobile.ui.title' => __('AI Commander Voice Assistant', 'ai-commander'),
            'mobile.ui.text_logo' => __('AI', 'ai-commander'),
            'mobile.ui.assistant_name' => __('AI Commander Assistant', 'ai-commander'),
            'mobile.ui.change_config' => __('Change configuration', 'ai-commander'),
            'mobile.ui.disconnect' => __('Disconnect', 'ai-commander'),
            'mobile.ui.greeting' => __('Hello! 👋', 'ai-commander'),
            'mobile.ui.greeting_text' => __('I am your Voice Assistant by AI Commander. I can help you interact with your WordPress site.', 'ai-commander'),

            // User Interface labels and text - config screen
            'mobile.ui.config.subtitle_embedded' => __('Enter your WordPress credentials to continue', 'ai-commander'),
            'mobile.ui.config.subtitle_vite' => __('Development mode - Enter your WordPress credentials', 'ai-commander'),
            'mobile.ui.config.subtitle_default' => __('Enter the URL of your WordPress site and credentials', 'ai-commander'),
            'mobile.ui.config.note_title' => __('Note:', 'ai-commander'),
            'mobile.ui.config.note_text' => __('For the password, use an "Application Password" generated from your WordPress profile, not the normal password.', 'ai-commander'),
            'mobile.ui.config.site_url_label' => __('Site URL', 'ai-commander'),
            'mobile.ui.config.site_url_placeholder' => __('https://www.yoursite.com', 'ai-commander'),
            'mobile.ui.config.site_url_hint' => __('The complete URL of your WordPress site', 'ai-commander'),
            'mobile.ui.config.username_label' => __('Username', 'ai-commander'),
            'mobile.ui.config.username_placeholder' => __('john.doe', 'ai-commander'),
            'mobile.ui.config.username_hint' => __('Your WordPress username', 'ai-commander'),
            'mobile.ui.config.app_password_label' => __('App password', 'ai-commander'),
            'mobile.ui.config.app_password_placeholder' => __('xxxx xxxx xxxx xxxx', 'ai-commander'),
            'mobile.ui.config.app_password_hint' => __('The application password generated in WordPress (not the normal password)', 'ai-commander'),
            'mobile.ui.config.connect_btn' => __('Connect', 'ai-commander'),
            'mobile.ui.config.connecting_btn' => __('Connecting...', 'ai-commander'),


            // Suggestion examples - shown to help users understand what they can ask
            'mobile.suggestion.customer_check' => __('💬 "Is license plate XX333TT our customer?"', 'ai-commander'),
            'mobile.suggestion.book_service' => __('📅 "Schedule a service for tomorrow"', 'ai-commander'),
            'mobile.suggestion.show_appointments' => __('🔍 "Show today\'s appointments"', 'ai-commander'),

            // Confirmation messages - for user actions that need confirmation
            'mobile.confirm.logout' => __('Do you want to disconnect and delete saved credentials?', 'ai-commander'),

            // Dynamic messages - generated based on app state
            'mobile.dynamic.session_expired' => __('Session expired. Please log in again.', 'ai-commander'),
            'mobile.dynamic.credentials_not_found' => __('Credentials not found. Please log in again.', 'ai-commander'),
            'mobile.dynamic.url_must_start_with_http' => __('URL must start with http:// or https://', 'ai-commander'),
            'mobile.dynamic.connection_test_failed' => __('Unable to connect. Check your data and try again.', 'ai-commander'),

            // PWA Manifest strings - for dynamic manifest.json generation
            'mobile.manifest.name' => __('AI Commander Voice Assistant', 'ai-commander'),
            'mobile.manifest.short_name' => __('AI Commander', 'ai-commander'),
            'mobile.manifest.description' => __('Voice assistant for WordPress content management', 'ai-commander'),
        );
    }
}
