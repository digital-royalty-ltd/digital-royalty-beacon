<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load autoloader so the heartbeat class is available
require_once __DIR__ . '/src/Support/Autoloader.php';
\DigitalRoyalty\Beacon\Support\Autoloader::register(__DIR__ . '/src');

// Notify the dashboard that the plugin has been uninstalled
\DigitalRoyalty\Beacon\Systems\Heartbeat\HeartbeatScheduler::onUninstall();