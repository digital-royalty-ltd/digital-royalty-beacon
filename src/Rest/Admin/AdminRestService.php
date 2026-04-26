<?php

namespace DigitalRoyalty\Beacon\Rest\Admin;

use DigitalRoyalty\Beacon\Repositories\ApiKeysRepository;
use DigitalRoyalty\Beacon\Repositories\ApiLogsRepository;
use DigitalRoyalty\Beacon\Repositories\FourOhFourLogsRepository;
use DigitalRoyalty\Beacon\Repositories\LogsRepository;
use DigitalRoyalty\Beacon\Repositories\RedirectsRepository;
use DigitalRoyalty\Beacon\Repositories\DeferredRequestsRepository;
use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Repositories\SchedulerRepository;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\ApiManagerController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\AutomationsController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\BootstrapController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\CampaignsController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\ConfigController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\ConnectionsController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\ContentEnrichmentController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\ContentFromSampleController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\ContentGeneratorController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\GenerateImageController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\InsightsController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\NewsArticleGeneratorController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\AutomationScheduleController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\SocialShareController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\DebugController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\LogsController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\MarketingCampaignsController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\OnboardingController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\ReportsController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\UpdateChannelController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\WorkshopAuditController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\WorkshopController;
use DigitalRoyalty\Beacon\Rest\Admin\Controllers\WorkshopInteractiveController;

/**
 * Registers all admin-facing REST endpoints under beacon/v1/admin/*.
 *
 * These routes are for the React SPA only — they require manage_options
 * capability and are completely separate from the Beacon platform API
 * (which faces Laravel and is CMS-agnostic).
 */
final class AdminRestService
{
    public function __construct(
        private readonly ReportsRepository          $reportsRepo,
        private readonly LogsRepository             $logsRepo,
        private readonly FourOhFourLogsRepository   $fourOhFourRepo,
        private readonly RedirectsRepository        $redirectsRepo,
        private readonly DeferredRequestsRepository $deferredRepo,
        private readonly SchedulerRepository        $schedulerRepo,
        private readonly ApiKeysRepository          $apiKeysRepo,
        private readonly ApiLogsRepository          $apiLogsRepo
    ) {}

    public function register(): void
    {
        add_action('rest_api_init', function () {
            (new AutomationsController($this->reportsRepo, $this->deferredRepo))->registerRoutes();
            (new BootstrapController($this->reportsRepo))->registerRoutes();
            (new CampaignsController())->registerRoutes();
            (new ConfigController())->registerRoutes();
            (new ConnectionsController())->registerRoutes();
            (new ContentGeneratorController())->registerRoutes();
            (new ContentFromSampleController())->registerRoutes();
            (new ContentEnrichmentController())->registerRoutes();
            (new GenerateImageController())->registerRoutes();
            (new InsightsController())->registerRoutes();
            (new NewsArticleGeneratorController())->registerRoutes();
            (new AutomationScheduleController())->registerRoutes();
            (new SocialShareController())->registerRoutes();
            (new LogsController($this->logsRepo))->registerRoutes();
            (new MarketingCampaignsController())->registerRoutes();
            (new OnboardingController())->registerRoutes();
            (new UpdateChannelController())->registerRoutes();
            (new DebugController($this->deferredRepo, $this->schedulerRepo, $this->reportsRepo, $this->logsRepo, $this->apiKeysRepo, $this->apiLogsRepo))->registerRoutes();
            (new ReportsController($this->reportsRepo))->registerRoutes();
            (new WorkshopController($this->fourOhFourRepo))->registerRoutes();
            (new WorkshopAuditController($this->redirectsRepo))->registerRoutes();
            (new WorkshopInteractiveController($this->redirectsRepo))->registerRoutes();
            (new ApiManagerController($this->apiKeysRepo, $this->apiLogsRepo))->registerRoutes();
        });
    }
}
