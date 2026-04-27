<?php
/**
 * Plugin Name: WP Beacon
 * Description: Connect WordPress via Beacon to gain: automation, content intelligence, and publishing.
 * Version: 0.7.1
 * Author: Digital Royalty
 * Author URI: https://digitalroyalty.co.uk
 * Plugin URI: https://digitalroyalty.co.uk
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: digital-royalty-beacon
 *
 * -----------------------------------------------------------------------------
 * Beacon Bootstrap File
 * -----------------------------------------------------------------------------
 *
 * This file is the single entry point for the Beacon plugin.
 *
 * Responsibilities:
 * - Define global plugin constants
 * - Register autoloading
 * - Register activation/deactivation hooks
 * - Boot the main Plugin class once WordPress is loaded
 *
 * This file intentionally contains no business logic.
 * All functionality is delegated to the Plugin class and its subsystems.
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access.
}

/**
 * Current plugin version.
 * Used for cache busting, API headers, etc.
 */
define('DR_BEACON_VERSION', '0.7.1');

/**
 * Absolute path to this plugin file.
 */
define('DR_BEACON_FILE', __FILE__);

/**
 * Absolute directory path of the plugin.
 */
define('DR_BEACON_DIR', __DIR__);

/**
 * Base URL for Beacon API.
 * Override in wp-config.php for local development:
 *   define('DR_BEACON_API_BASE', 'http://localhost:8000/api');
 */
if (!defined('DR_BEACON_API_BASE')) {
    define('DR_BEACON_API_BASE', 'https://app.digitalroyalty.co.uk/api');
}

/**
 * API namespace and version used for Beacon endpoints.
 */
define('DR_BEACON_API_NAMESPACE', 'beacon');
define('DR_BEACON_API_VERSION', 'v1');

/**
 * GitHub repository details for the experimental update channel.
 */
define('DR_BEACON_GITHUB_OWNER', 'digital-royalty-ltd');
define('DR_BEACON_GITHUB_REPO',  'digital-royalty-beacon');

/**
 * Register internal autoloader.
 *
 * Beacon uses a lightweight custom autoloader for namespaced classes
 * under the src/ directory.
 */
require_once DR_BEACON_DIR . '/src/Support/Autoloader.php';

\DigitalRoyalty\Beacon\Support\Autoloader::register(DR_BEACON_DIR . '/src');

/**
 * Register activation hook.
 *
 * Called once when the plugin is activated.
 * Used for installing database schema and initial setup.
 */
register_activation_hook(
    __FILE__,
    [\DigitalRoyalty\Beacon\Plugin::class, 'activate']
);

/**
 * Register deactivation hook.
 *
 * Called when the plugin is deactivated.
 * Reserved for cleanup tasks.
 */
register_deactivation_hook(
    __FILE__,
    [\DigitalRoyalty\Beacon\Plugin::class, 'deactivate']
);

/**
 * Boot the plugin after all plugins are loaded.
 *
 * This ensures:
 * - WordPress environment is ready
 * - Other plugins are available
 * - Hooks can safely be registered
 */
add_action('plugins_loaded', static function () {
    \DigitalRoyalty\Beacon\Plugin::instance()->boot();
});