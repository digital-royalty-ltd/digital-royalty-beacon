<?php

namespace DigitalRoyalty\Beacon;

use DigitalRoyalty\Beacon\Admin\Actions\Views\DebugPageAdminActions;
use DigitalRoyalty\Beacon\Admin\Actions\Views\HomePageAdminActions;
use DigitalRoyalty\Beacon\Admin\Actions\Reports\ReportAdminActions;
use DigitalRoyalty\Beacon\Admin\Actions\Views\Tools\ContentGeneratorAdminActions;
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
use DigitalRoyalty\Beacon\Database\DeferredRequestsTable;
use DigitalRoyalty\Beacon\Database\LogsTable;
use DigitalRoyalty\Beacon\Database\ReportsTable;
use DigitalRoyalty\Beacon\Repositories\DeferredRequestsRepository;
use DigitalRoyalty\Beacon\Rest\RestService;
use DigitalRoyalty\Beacon\Support\Enums\Deferred\DeferredRequestKeyEnum;
use DigitalRoyalty\Beacon\Systems\Deferred\DeferredCompletionRouter;
use DigitalRoyalty\Beacon\Systems\Deferred\DeferredRequestRunner;
use DigitalRoyalty\Beacon\Systems\Deferred\Handlers\ContentGeneratorDraftHandler;
use DigitalRoyalty\Beacon\Systems\Reports\ReportService;

/**
 * Main plugin bootstrap class.
 *
 * Responsible for coordinating initialization of:
 * - Database schema
 * - Core services
 * - Admin actions and UI
 * - REST endpoints
 * - Deferred async processing
 *
 * This class intentionally contains only orchestration logic.
 */
final class Plugin
{
    /**
     * Singleton instance.
     */
    private static ?self $instance = null;

    /**
     * Retrieve singleton instance of the plugin.
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boot the plugin.
     *
     * Called on plugin load to initialize all subsystems.
     */
    public function boot(): void
    {
        $this->ensureSchema();

        $this->registerServices();
        $this->registerAdminActions();
        $this->registerAdminUi();
        $this->registerRest();
        $this->registerDeferred();
    }

    /**
     * Plugin activation hook.
     *
     * Ensures required database tables exist.
     */
    public static function activate(): void
    {
        self::installTables();
    }

    /**
     * Plugin deactivation hook.
     *
     * Reserved for future cleanup tasks such as unscheduling cron jobs.
     */
    public static function deactivate(): void
    {
        // Later: unschedule cron, release locks, etc.
    }

    /**
     * Ensure database schema exists.
     *
     * This runs in admin context to guard against missed activation hooks
     * on certain hosting environments.
     */
    private function ensureSchema(): void
    {
        if (!is_admin()) {
            return;
        }

        self::installTables();
    }

    /**
     * Install all plugin database tables.
     *
     * Centralized so schema changes are defined in one place.
     */
    private static function installTables(): void
    {
        LogsTable::install();
        ReportsTable::install();
        DeferredRequestsTable::install();
    }

    /**
     * Register core backend services.
     */
    private function registerServices(): void
    {
        (new ReportService())->register();
    }

    /**
     * Register all admin-side POST actions and handlers.
     */
    private function registerAdminActions(): void
    {
        (new ReportAdminActions())->register();
        (new HomePageAdminActions())->register();
        (new DebugPageAdminActions())->register();
        (new ContentGeneratorAdminActions())->register();
    }

    /**
     * Register admin UI screens, views, and menu structure.
     */
    private function registerAdminUi(): void
    {
        if (!is_admin()) {
            return;
        }

        $homeView = new HomeView();
        $toolsView = new ToolsView();
        $configurationView = new ConfigurationView();
        $debugView = new DebugView();

        $screenRegistry = new ScreenRegistry([
            new HomeScreen($homeView),
            new ToolsScreen($toolsView),
            new ConfigurationScreen($configurationView),
            new DebugScreen($debugView),
        ]);

        (new AdminMenu($screenRegistry))->register();
    }

    /**
     * Register REST API endpoints for Beacon.
     */
    private function registerRest(): void
    {
        (new RestService())->register();
    }

    /**
     * Register deferred async processing system.
     *
     * Wires:
     * - DeferredRequestsRepository
     * - Completion router
     * - Tool-specific completion handlers
     * - Cron-based runner
     */
    private function registerDeferred(): void
    {
        global $wpdb;

        $repo = new DeferredRequestsRepository($wpdb);
        $router = new DeferredCompletionRouter();

        $router->register(
            DeferredRequestKeyEnum::CONTENT_GENERATOR_GENERATE,
            new ContentGeneratorDraftHandler()
        );

        $runner = new DeferredRequestRunner($repo, $router);
        $runner->register();

        // Ensure cron hook is scheduled
        if (!wp_next_scheduled(DeferredRequestRunner::CRON_HOOK)) {
            wp_schedule_single_event(time() + 60, DeferredRequestRunner::CRON_HOOK);
        }
    }
}