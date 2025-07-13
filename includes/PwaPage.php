<?php

/**
 * PWA Page Handler
 *
 * Generates and serves the PWA HTML page with embedded configuration
 *
 * @package AICommander
 */

namespace AICommander\Includes;

use AICommander\Includes\Services\MobileTranslations;

if (! defined('WPINC')) {
    die;
}

/**
 * PWA Page class.
 */
class PwaPage
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('init', array($this, 'maybe_serve_pwa'), 1);
    }

    /**
     * Get the PWA path from settings with filter override.
     *
     * @return string The PWA path.
     */
    private function get_pwa_path()
    {
        $default_path = get_option('ai_commander_pwa_path', 'ai-commander/assistant');
        return apply_filters('ai_commander_filter_pwa_path', $default_path);
    }

    /**
     * Check if current request is for PWA and serve it.
     */
    public function maybe_serve_pwa()
    {
        // Get the current request path
        $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        
        // Remove the WordPress subdirectory if it exists
        $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
        if (!empty($home_path) && strpos($request_path, $home_path) === 0) {
            $request_path = substr($request_path, strlen($home_path));
            $request_path = trim($request_path, '/');
        }
        
        // Check if this is a request for our PWA
        if ($request_path === $this->get_pwa_path()) {
            $this->render_pwa_page();
            exit;
        }
    }

    /**
     * Render the PWA page.
     */
    private function render_pwa_page()
    {
        // Get configuration data
        $config = $this->generate_pwa_config();
        
        // Get the base URL for assets
        $plugin_url = untrailingslashit(plugin_dir_url(dirname(__FILE__)));
        $assets_url = $plugin_url . '/mobile/app/assets';
        
        // Set proper headers
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="<?php echo esc_attr($config['manifest']['theme_color']); ?>">
    <title><?php echo esc_html($config['manifest']['name']); ?></title>

    <!-- PWA Manifest - dynamically generated -->
    <link rel="manifest" href="<?php echo esc_url(get_site_url() . '/wp-json/ai-commander/v1/manifest'); ?>">

    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url($assets_url); ?>/favicon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url($assets_url); ?>/favicon.png">
    <link rel="apple-touch-icon" href="<?php echo esc_url($assets_url); ?>/favicon.png">

    <!-- Embed configuration -->
    <script>
    window.AI_COMMANDER_CONFIG = <?php echo wp_json_encode($config); ?>;
    </script>

    <!-- App styles and scripts from the built mobile app -->
    <?php $this->include_app_assets($assets_url); ?>
</head>
<body>
    <!-- React app will render here -->
    <div id="app">
        <div style="display: flex; align-items: center; justify-content: center; height: 100vh; font-family: system-ui;">
            <div><?php esc_html_e('Loading...', 'ai-commander'); ?></div>
        </div>
    </div>
</body>
</html>
        <?php
    }

    /**
     * Include app assets (CSS and JS files).
     *
     * @param string $assets_url The URL to the assets directory.
     */
    private function include_app_assets($assets_url)
    {
        // Get the app directory to scan for built assets
        $app_dir = plugin_dir_path(dirname(__FILE__)) . 'mobile/app/assets';
        
        if (is_dir($app_dir)) {
            $files = scandir($app_dir);
            
            // Include CSS files
            foreach ($files as $file) {
                if (preg_match('/^main-.*\.css$/', $file)) {
                    echo '<link rel="stylesheet" crossorigin href="' . esc_url($assets_url . '/' . $file) . '">' . "\n";
                }
            }
            
            // Include JS files
            foreach ($files as $file) {
                if (preg_match('/^main-.*\.js$/', $file)) {
                    echo '<script type="module" crossorigin src="' . esc_url($assets_url . '/' . $file) . '"></script>' . "\n";
                }
            }
        }
    }

    /**
     * Generate the PWA configuration.
     *
     * @return array The configuration array.
     */
    private function generate_pwa_config()
    {
        // Get site information
        $site_url = untrailingslashit(get_site_url());
        $locale = get_locale();

        // Get translations
        $translations = MobileTranslations::get_translations();

        // Generate manifest data
        $manifest = $this->generate_manifest_data($translations);

        return array(
            'baseUrl' => $site_url,
            'locale' => $locale,
            'translations' => $translations,
            'manifest' => $manifest,
            'pwaPath' => $this->get_pwa_path(),
            'version' => defined('AI_COMMANDER_VERSION') ? AI_COMMANDER_VERSION : '1.0.0',
        );
    }

    /**
     * Generate manifest data.
     *
     * @param array $translations Translation strings.
     * @return array Manifest data.
     */
    private function generate_manifest_data($translations)
    {
        // Apply filters for PWA customization
        $pwa_name = apply_filters('ai_commander_filter_pwa_name', $translations['mobile.manifest.name']);
        $pwa_short_name = apply_filters('ai_commander_filter_pwa_short_name', $translations['mobile.manifest.short_name']);
        $pwa_description = apply_filters('ai_commander_filter_pwa_description', $translations['mobile.manifest.description']);
        $pwa_theme_color = apply_filters('ai_commander_filter_pwa_theme_color', '#1e3c72');
        $pwa_background_color = apply_filters('ai_commander_filter_pwa_background_color', '#1e3c72');
        
        // Build manifest
        $manifest = array(
            'name' => $pwa_name,
            'short_name' => $pwa_short_name,
            'description' => $pwa_description,
            'display' => 'standalone',
            'background_color' => $pwa_background_color,
            'theme_color' => $pwa_theme_color,
            'orientation' => 'portrait',
            'start_url' => '/' . $this->get_pwa_path(),
            'scope' => '/',
            'icons' => apply_filters('ai_commander_filter_pwa_icons', array())
        );
        
        // Allow filtering the entire manifest
        return apply_filters('ai_commander_filter_pwa_manifest', $manifest);
    }
}