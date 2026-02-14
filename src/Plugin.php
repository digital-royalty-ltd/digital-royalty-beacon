<?php

namespace DigitalRoyalty\Beacon;

use DigitalRoyalty\Beacon\Admin\Actions\Views\DebugPageAdminActions;
use DigitalRoyalty\Beacon\Admin\Actions\Views\HomePageAdminActions;
use DigitalRoyalty\Beacon\Admin\Actions\Reports\ReportAdminActions;
use DigitalRoyalty\Beacon\Admin\Config\AdminMenu;
use DigitalRoyalty\Beacon\Admin\Screens\ConfigurationScreen;
use DigitalRoyalty\Beacon\Admin\Screens\DebugScreen;
use DigitalRoyalty\Beacon\Admin\Screens\HomeScreen;
use DigitalRoyalty\Beacon\Admin\Screens\ScreenRegistry;
use DigitalRoyalty\Beacon\Admin\Screens\ToolsScreen;
use DigitalRoyalty\Beacon\Admin\Views\ConfigurationView;
use DigitalRoyalty\Beacon\Admin\Views\DebugView;
use DigitalRoyalty\Beacon\Admin\Views\HomeView;
use DigitalRoyalty\Beacon\Admin\Views\ToolsView;
use DigitalRoyalty\Beacon\Database\LogsTable;
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
            LogsTable::install();
            ReportsTable::install();
        }

        // Services
        (new ReportService())->register();

        // Admin Actions (existing)
        (new ReportAdminActions())->register();
        (new HomePageAdminActions())->register();
        (new DebugPageAdminActions())->register();

        // Admin UI (new Screens/Views system)
        if (is_admin()) {
            // Views
            $homeView = new HomeView();
            $toolsView = new ToolsView();
            $configurationView = new ConfigurationView();
            $debugView = new DebugView();

            // Screens
            $screenRegistry = new ScreenRegistry([
                new HomeScreen($homeView),
                new ToolsScreen($toolsView),
                new ConfigurationScreen($configurationView),
                new DebugScreen($debugView),
            ]);

            // Menu
            (new AdminMenu($screenRegistry))->register();
        }

        // REST API endpoints
        (new RestService())->register();
    }

    public static function activate(): void
    {
        LogsTable::install();
        ReportsTable::install();
    }

    public static function deactivate(): void
    {
        // Later: unschedule cron, release locks, etc.
    }
}