<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Repositories\DeferredRequestsRepository;
use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Repositories\SchedulerRepository;
use DigitalRoyalty\Beacon\Systems\Reports\ReportManager;
use WP_REST_Request;
use WP_REST_Response;

final class DebugController
{
    public function __construct(
        private readonly DeferredRequestsRepository $deferredRepo,
        private readonly SchedulerRepository        $schedulerRepo,
        private readonly ReportsRepository          $reportsRepo
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/admin/debug', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);

        register_rest_route('beacon/v1', '/admin/debug/deferred-requests', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleDeferredRequests'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);

        register_rest_route('beacon/v1', '/admin/debug/scheduler-actions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleSchedulerActions'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);

        register_rest_route('beacon/v1', '/admin/debug/reset', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleReset'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $nextRun       = null;
        $lastHeartbeat = (string) get_option('dr_beacon_last_runner_heartbeat', '');

        if (function_exists('as_next_scheduled_action')) {
            $ts = as_next_scheduled_action(ReportManager::ACTION_RUN_NEXT, [], 'dr-beacon');
            if ($ts && is_numeric($ts)) {
                $nextRun = gmdate('Y-m-d H:i:s', (int) $ts) . ' UTC';
            }
        }

        return new WP_REST_Response([
            'scheduler' => [
                'next_run'       => $nextRun,
                'last_heartbeat' => $lastHeartbeat !== '' ? $lastHeartbeat . ' UTC' : null,
            ],
            'report_status' => (string) get_option(ReportManager::OPTION_STATUS, ReportManager::STATUS_NOT_STARTED),
        ], 200);
    }

    public function handleDeferredRequests(WP_REST_Request $request): WP_REST_Response
    {
        $perPage = max(1, min(100, (int) ($request->get_param('per_page') ?? 50)));
        $page    = max(1, (int) ($request->get_param('page') ?? 1));

        return new WP_REST_Response([
            'rows'     => $this->deferredRepo->list($perPage, $page),
            'total'    => $this->deferredRepo->countAll(),
            'per_page' => $perPage,
            'page'     => $page,
        ], 200);
    }

    public function handleSchedulerActions(WP_REST_Request $request): WP_REST_Response
    {
        $perPage = max(1, min(100, (int) ($request->get_param('per_page') ?? 50)));
        $page    = max(1, (int) ($request->get_param('page') ?? 1));

        $result = $this->schedulerRepo->paginateBeaconActions($perPage, $page);

        return new WP_REST_Response([
            'rows'     => $result['rows'],
            'total'    => $result['total'],
            'per_page' => $perPage,
            'page'     => $page,
        ], 200);
    }

    public function handleReset(WP_REST_Request $request): WP_REST_Response
    {
        $action = (string) ($request->get_param('action') ?? '');

        switch ($action) {
            case 'clear-reports':
                $this->clearReports();
                break;

            case 'clear-deferred':
                $this->deferredRepo->deleteAll();
                break;

            case 'unschedule':
                $this->unscheduleAll();
                break;

            case 'full-reset':
                $this->clearReports();
                $this->deferredRepo->deleteAll();
                $this->unscheduleAll();
                delete_option(ReportManager::OPTION_STATUS);
                delete_option('dr_beacon_last_runner_heartbeat');
                break;

            default:
                return new WP_REST_Response(['error' => 'Unknown reset action.'], 400);
        }

        return new WP_REST_Response(['ok' => true, 'action' => $action], 200);
    }

    private function clearReports(): void
    {
        $this->reportsRepo->deleteAll();
        delete_option('dr_beacon_content_area_map');
        delete_option('dr_beacon_key_pages_map');
    }

    private function unscheduleAll(): void
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(ReportManager::ACTION_RUN_NEXT, [], 'dr-beacon');
            as_unschedule_all_actions(ReportManager::ACTION_RUN_REPORT, [], 'dr-beacon');
        }
    }
}
