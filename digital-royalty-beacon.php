<?php
/**
 * Plugin Name: Beacon by Digital Royalty
 * Description: Connect WordPress via Beacon to gain: automation, content intelligence, and publishing.
 * Version: 0.1.0
 * Author: Digital Royalty
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: digital-royalty-beacon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DR_BEACON_VERSION', '0.1.0');
define('DR_BEACON_FILE', __FILE__);
define('DR_BEACON_DIR', __DIR__);

require_once DR_BEACON_DIR . '/src/Support/Autoloader.php';

\DigitalRoyalty\Beacon\Support\Autoloader::register(DR_BEACON_DIR . '/src');

register_activation_hook(__FILE__, [\DigitalRoyalty\Beacon\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [\DigitalRoyalty\Beacon\Plugin::class, 'deactivate']);

add_action('plugins_loaded', static function () {
    \DigitalRoyalty\Beacon\Plugin::instance()->boot();
});
