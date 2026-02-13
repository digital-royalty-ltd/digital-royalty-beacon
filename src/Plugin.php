<?php

namespace DigitalRoyalty\Beacon;

use DigitalRoyalty\Beacon\Admin\Actions\Reports\ReportAdminActions;
use DigitalRoyalty\Beacon\Admin\Config\AdminMenu;
use DigitalRoyalty\Beacon\Admin\Pages\DebugPage;
use DigitalRoyalty\Beacon\Admin\Pages\HomePage;
use DigitalRoyalty\Beacon\Database\ReportsTable;
use DigitalRoyalty\Beacon\Rest\RestService;
use DigitalRoyalty\Beacon\Systems\Reports\ReportService;

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
        // Ensure DB schema exists (activation hooks can be missed on some hosts).
        if (is_admin()) {
            ReportsTable::install();
        }

        // Services
        (new ReportService())->register();

        // Admin Actions
        (new ReportAdminActions())->register();

        // Admin Pages
        if (is_admin()) {
            $home = new HomePage();
            $home->register();

            $debug = new DebugPage();
            $debug->register();

            (new AdminMenu($home, $debug))->register();
        }

        // REST API endpoints
        (new RestService())->register();
    }

    public static function activate(): void
    {
        ReportsTable::install();
    }

    public static function deactivate(): void
    {
        // Later: unschedule cron, release locks, etc.
    }
}