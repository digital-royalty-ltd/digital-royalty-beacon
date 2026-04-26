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
use DigitalRoyalty\Beacon\Admin\Screens\InsightsScreen;
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
use DigitalRoyalty\Beacon\Rest\Observability\RestErrorLogger;
use DigitalRoyalty\Beacon\Rest\RestService;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Deferred\DeferredRequestKeyEnum;
use DigitalRoyalty\Beacon\Systems\Deferred\DeferredCompletionRouter;
use DigitalRoyalty\Beacon\Systems\Deferred\DeferredRequestRunner;
use DigitalRoyalty\Beacon\Systems\Deferred\Handlers\ContentEnrichmentImageHandler;
use DigitalRoyalty\Beacon\Systems\Deferred\Handlers\ContentGeneratorDraftHandler;
use DigitalRoyalty\Beacon\Systems\Deferred\Handlers\GapAnalysisResultHandler;
use DigitalRoyalty\Beacon\Systems\Deferred\Handlers\GenerateImageHandler;
use DigitalRoyalty\Beacon\Systems\Deferred\Handlers\SocialShareHandler;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationRegistry;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationRequestPoller;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationScheduler;
use DigitalRoyalty\Beacon\Systems\MaintenanceMode\MaintenanceModeHandler;
use DigitalRoyalty\Beacon\Systems\Redirects\RedirectHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\AnnouncementBarHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\CodeInjectionHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\CustomAdminCssHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\CustomLoginUrlHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\DatabaseCleanupHandler;
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
use DigitalRoyalty\Beacon\Systems\Workshop\PostTypeSwitcherIntegration;
use DigitalRoyalty\Beacon\Systems\Workshop\ClonePostIntegration;
use DigitalRoyalty\Beacon\Systems\Workshop\MediaReplaceIntegration;
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
        $this->registerHeartbeat();
        $this->registerAutomationPoller();
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
        $installResult = self::installTables();

        // Log activation outcome — without this, an activation that ends with
        // a broken schema looks identical to a healthy activation. The logger
        // call is best-effort because the logs table itself may have failed
        // to install.
        try {
            $level = $installResult['failed'] === [] ? 'info' : 'error';
            $message = $installResult['failed'] === []
                ? 'Plugin activated; schema installed.'
                : 'Plugin activation completed with schema errors — some tables did not install.';

            Services::logger()->{$level}(
                LogScopeEnum::SYSTEM,
                'plugin_activated',
                $message,
                $installResult
            );
        } catch (\Throwable) {
            // Logger may itself be unusable if LogsTable failed to install;
            // do not let activation crash because of logging.
        }

        \DigitalRoyalty\Beacon\Systems\Heartbeat\HeartbeatScheduler::onActivation();
        AutomationRequestPoller::onActivation();
    }

    public static function deactivate(): void
    {
        $cleared = [];
        $missing = [];

        \DigitalRoyalty\Beacon\Systems\Heartbeat\HeartbeatScheduler::onDeactivation();
        $cleared[] = 'heartbeat';

        AutomationRequestPoller::onDeactivation();
        $cleared[] = 'automation_request_poller';

        AutomationScheduler::unschedule();
        $cleared[] = 'automation_scheduler';

        // Clear deferred runner cron
        $deferredHook = \DigitalRoyalty\Beacon\Systems\Deferred\DeferredRequestRunner::CRON_HOOK;
        $ts = wp_next_scheduled($deferredHook);
        if ($ts) {
            wp_unschedule_event($ts, $deferredHook);
            $cleared[] = 'deferred_request_runner';
        } else {
            $missing[] = 'deferred_request_runner';
        }

        // Cancel all pending Action Scheduler actions in our group
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(\DigitalRoyalty\Beacon\Systems\Reports\ReportManager::ACTION_RUN_NEXT, [], 'dr-beacon');
            as_unschedule_all_actions(\DigitalRoyalty\Beacon\Systems\Reports\ReportManager::ACTION_RUN_REPORT, [], 'dr-beacon');
            as_unschedule_all_actions(\DigitalRoyalty\Beacon\Systems\Reports\ReportService::ACTION_REGENERATE_REPORT, [], 'dr-beacon');
            $cleared[] = 'action_scheduler_reports';
        } else {
            $missing[] = 'action_scheduler_reports';
        }

        // Log the deactivation summary so ghost crons after a botched
        // upgrade are auditable. Best-effort because the logs table may
        // already be uninstalled by the time this runs in some flows.
        try {
            Services::logger()->info(
                LogScopeEnum::SYSTEM,
                'plugin_deactivated',
                'Plugin deactivated; scheduled hooks cleared.',
                ['cleared' => $cleared, 'missing' => $missing]
            );
        } catch (\Throwable) {
            // ignore — deactivation must always succeed.
        }
    }

    private function ensureSchema(): void
    {
        if (!is_admin()) {
            return;
        }

        // Best-effort: dbDelta is idempotent on healthy schemas, and we
        // already log full outcomes from activate(). Silent here so we don't
        // log on every admin page view.
        self::installTables();
    }

    /**
     * Install or upgrade all custom tables. Captures per-table failures so
     * activate() can log a complete picture instead of failing on the first
     * exception (a half-installed schema is harder to diagnose than knowing
     * exactly which tables failed and why).
     *
     * @return array{installed: list<string>, failed: array<string, string>}
     */
    private static function installTables(): array
    {
        $tables = [
            'logs' => LogsTable::class,
            'reports' => ReportsTable::class,
            'deferred_requests' => DeferredRequestsTable::class,
            'redirects' => RedirectsTable::class,
            'four_oh_four_logs' => FourOhFourLogsTable::class,
            'api_keys' => ApiKeysTable::class,
            'api_logs' => ApiLogsTable::class,
        ];

        $installed = [];
        $failed = [];

        foreach ($tables as $key => $class) {
            try {
                $class::install();
                $installed[] = $key;
            } catch (\Throwable $e) {
                $failed[$key] = $e->getMessage();
            }
        }

        return ['installed' => $installed, 'failed' => $failed];
    }

    private function registerServices(): void
    {
        (new ReportService())->register();
        (new PublicApiDocsPage())->register();
    }

    private function registerHeartbeat(): void
    {
        (new \DigitalRoyalty\Beacon\Systems\Heartbeat\HeartbeatScheduler())->register();
    }

    private function registerAutomationPoller(): void
    {
        (new AutomationRequestPoller(new AutomationRegistry()))->register();
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
            new InsightsScreen(),
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
        (new RestErrorLogger())->register();

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
            DeferredRequestKeyEnum::CONTENT_FROM_SAMPLE,
            new ContentGeneratorDraftHandler() // Same output shape (Article artifact → WP draft)
        );

        $router->register(
            DeferredRequestKeyEnum::GAP_ANALYSIS,
            new GapAnalysisResultHandler()
        );

        $router->register(
            DeferredRequestKeyEnum::GENERATE_IMAGE,
            new GenerateImageHandler()
        );

        $router->register(
            DeferredRequestKeyEnum::CONTENT_ENRICHMENT_IMAGE,
            new ContentEnrichmentImageHandler()
        );

        $router->register(
            DeferredRequestKeyEnum::NEWS_ARTICLE_GENERATE,
            new ContentGeneratorDraftHandler() // Same output shape (Article artifact → WP draft)
        );

        $router->register(
            DeferredRequestKeyEnum::SOCIAL_SHARE_GENERATE,
            new SocialShareHandler()
        );

        $runner = new DeferredRequestRunner($repo, $router);
        $runner->register();

        if (!wp_next_scheduled(DeferredRequestRunner::CRON_HOOK)) {
            wp_schedule_single_event(time() + 60, DeferredRequestRunner::CRON_HOOK);
        }

        // Automation scheduler (recurring scheduled runs)
        $scheduler = new AutomationScheduler();
        $scheduler->register();
        $scheduler->ensureScheduled();
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
        (new DatabaseCleanupHandler())->register();
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
        (new PostTypeSwitcherIntegration())->register();
        (new ClonePostIntegration())->register();
        (new MediaReplaceIntegration())->register();
    }
}
