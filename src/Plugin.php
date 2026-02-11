<?php

namespace DigitalRoyalty\Beacon;

use DigitalRoyalty\Beacon\Admin\SettingsPage;
use DigitalRoyalty\Beacon\Rest\RestService;

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        // Admin UI
        if (is_admin()) {
            (new SettingsPage())->register();
        }

        // REST API endpoints
        (new RestService())->register();
    }

    public static function activate(): void
    {
        // Later: create custom tables, default options, etc.
        // Keep it safe and minimal for now.
    }

    public static function deactivate(): void
    {
        // Later: unschedule cron, release locks, etc.
    }
}
