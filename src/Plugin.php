<?php

namespace DigitalRoyalty\Beacon;

use DigitalRoyalty\Beacon\Admin\Actions\Workshop\UserSwitcherAdminActions;
use DigitalRoyalty\Beacon\Admin\Assets\AdminAssets;
use DigitalRoyalty\Beacon\Admin\Config\AdminMenu;
use DigitalRoyalty\Beacon\Admin\Screens\AutomationsScreen;
use DigitalRoyalty\Beacon\Admin\Screens\ConfigurationScreen;
use DigitalRoyalty\Beacon\Admin\Screens\ApiScreen;
use DigitalRoyalty\Beacon\Admin\Screens\DevelopmentScreen;
use DigitalRoyalty\Beacon\Admin\Screens\DebugScreen;
use DigitalRoyalty\Beacon\Admin\Screens\HomeScreen;
use DigitalRoyalty\Beacon\Admin\Screens\MissionControlScreen;
use DigitalRoyalty\Beacon\Admin\Screens\ScreenRegistry;
use DigitalRoyalty\Beacon\Admin\Screens\WorkshopScreen;
use DigitalRoyalty\Beacon\Database\ApiKeysTable;
use DigitalRoyalty\Beacon\Database\ApiLogsTable;
use DigitalRoyalty\Beacon\Database\DeferredRequestsTable;
use DigitalRoyalty\Beacon\Database\FourOhFourLogsTable;
use DigitalRoyalty\Beacon\Database\LogsTable;
use DigitalRoyalty\Beacon\Database\RedirectsTable;
use DigitalRoyalty\Beacon\Database\ReportsTable;
use DigitalRoyalty\Beacon\Repositories\ApiKeysRepository;
use DigitalRoyalty\Beacon\Repositories\ApiLogsRepository;
use DigitalRoyalty\Beacon\Repositories\DeferredRequestsRepository;
use DigitalRoyalty\Beacon\Repositories\FourOhFourLogsRepository;
use DigitalRoyalty\Beacon\Repositories\LogsRepository;
use DigitalRoyalty\Beacon\Repositories\RedirectsRepository;
use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Repositories\SchedulerRepository;
use DigitalRoyalty\Beacon\Rest\Admin\AdminRestService;
use DigitalRoyalty\Beacon\Rest\RestService;
use DigitalRoyalty\Beacon\Support\Enums\Deferred\DeferredRequestKeyEnum;
use DigitalRoyalty\Beacon\Systems\Deferred\DeferredCompletionRouter;
use DigitalRoyalty\Beacon\Systems\Deferred\DeferredRequestRunner;
use DigitalRoyalty\Beacon\Systems\Deferred\Handlers\ContentGeneratorDraftHandler;
use DigitalRoyalty\Beacon\Systems\Deferred\Handlers\GapAnalysisResultHandler;
use DigitalRoyalty\Beacon\Systems\MaintenanceMode\MaintenanceModeHandler;
use DigitalRoyalty\Beacon\Systems\Redirects\RedirectHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\AnnouncementBarHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\CodeInjectionHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\CustomAdminCssHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\CustomLoginUrlHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\DisableCommentsHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\DisableFileEditingHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\DisableXmlRpcHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\FourOhFourMonitorHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\LoginBrandingHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\PostExpiryHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\RobotsTxtHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\SanitiseFilenamesHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\SmtpHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\SvgSupportHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\UserSwitcherHandler;
use DigitalRoyalty\Beacon\Systems\Api\PublicApiDocsPage;
use DigitalRoyalty\Beacon\Systems\Reports\ReportService;
use DigitalRoyalty\Beacon\Systems\Updater\GitHubUpdater;

/**
 * Main plugin bootstrap class.
 *
 * Responsible for coordinating initialization of:
 * - Database schema
 * - Core services
 * - Admin UI (React SPA mount)
 * - REST endpoints
 * - Deferred async processing
 *
 * This class intentionally contains only orchestration logic.
 */
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
        $this->ensureSchema();

        $this->registerServices();
        $this->registerUpdater();
        $this->registerAdminActions();
        $this->registerAdminUi();
        $this->registerRest();
        $this->registerDeferred();
        $this->registerRedirects();
        $this->registerWorkshopHandlers();
    }

    public static function activate(): void
    {
        self::installTables();
    }

    public static function deactivate(): void
    {
        // Later: unschedule cron, release locks, etc.
    }

    private function ensureSchema(): void
    {
        if (!is_admin()) {
            return;
        }

        self::installTables();
    }

    private static function installTables(): void
    {
        LogsTable::install();
        ReportsTable::install();
        DeferredRequestsTable::install();
        RedirectsTable::install();
        FourOhFourLogsTable::install();
        ApiKeysTable::install();
        ApiLogsTable::install();
    }

    private function registerServices(): void
    {
        (new ReportService())->register();
        (new PublicApiDocsPage())->register();
    }

    private function registerUpdater(): void
    {
        (new GitHubUpdater())->register();
    }

    /**
     * Register admin-post.php action handlers that still require a full page load.
     * All other mutations are handled by the REST API (beacon/v1/admin/*).
     */
    private function registerAdminActions(): void
    {
        // User switching requires a full page load to set auth cookies — cannot be done via REST.
        (new UserSwitcherAdminActions())->register();
    }

    private function registerAdminUi(): void
    {
        if (!is_admin()) {
            return;
        }

        $screenRegistry = new ScreenRegistry([
            new HomeScreen(),
            new WorkshopScreen(),
            new AutomationsScreen(),
            new MissionControlScreen(),
            new ConfigurationScreen(),
            new DebugScreen(),
            new ApiScreen(),
            new DevelopmentScreen(),
        ]);

        (new AdminMenu($screenRegistry))->register();
        (new AdminAssets())->register();
    }

    private function registerRest(): void
    {
        (new RestService())->register();

        global $wpdb;
        (new AdminRestService(
            new ReportsRepository($wpdb),
            new LogsRepository($wpdb),
            new FourOhFourLogsRepository($wpdb),
            new RedirectsRepository($wpdb),
            new DeferredRequestsRepository($wpdb),
            new SchedulerRepository($wpdb),
            new ApiKeysRepository($wpdb),
            new ApiLogsRepository($wpdb)
        ))->register();
    }

    private function registerDeferred(): void
    {
        global $wpdb;

        $repo   = new DeferredRequestsRepository($wpdb);
        $router = new DeferredCompletionRouter();

        $router->register(
            DeferredRequestKeyEnum::CONTENT_GENERATOR_GENERATE,
            new ContentGeneratorDraftHandler()
        );

        $router->register(
            DeferredRequestKeyEnum::GAP_ANALYSIS,
            new GapAnalysisResultHandler()
        );

        $runner = new DeferredRequestRunner($repo, $router);
        $runner->register();

        if (!wp_next_scheduled(DeferredRequestRunner::CRON_HOOK)) {
            wp_schedule_single_event(time() + 60, DeferredRequestRunner::CRON_HOOK);
        }
    }

    private function registerRedirects(): void
    {
        global $wpdb;

        (new RedirectHandler(new RedirectsRepository($wpdb)))->register();
        (new MaintenanceModeHandler())->register();
    }

    /**
     * Register Workshop tool system handlers.
     * Each handler reads its own option to decide whether to activate.
     */
    private function registerWorkshopHandlers(): void
    {
        global $wpdb;

        (new SvgSupportHandler())->register();
        (new DisableCommentsHandler())->register();
        (new DisableXmlRpcHandler())->register();
        (new DisableFileEditingHandler())->register();
        (new SanitiseFilenamesHandler())->register();
        (new CodeInjectionHandler())->register();
        (new CustomAdminCssHandler())->register();
        (new SmtpHandler())->register();
        (new RobotsTxtHandler())->register();
        (new PostExpiryHandler())->register();
        (new FourOhFourMonitorHandler(new FourOhFourLogsRepository($wpdb)))->register();
        (new CustomLoginUrlHandler())->register();
        (new LoginBrandingHandler())->register();
        (new AnnouncementBarHandler())->register();
        (new UserSwitcherHandler())->register();
    }
}
