<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Database\LogsTable;
use DigitalRoyalty\Beacon\Repositories\LogsRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET    /beacon/v1/admin/logs — paginated log entries (optional ?scope=)
 * DELETE /beacon/v1/admin/logs — truncate logs table
 */
final class LogsController
{
    public function __construct(
        private readonly LogsRepository $logsRepo
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/admin/logs', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handleIndex'],
                'permission_callback' => fn () => current_user_can('manage_options'),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'handleClear'],
                'permission_callback' => fn () => current_user_can('manage_options'),
            ],
        ]);
    }

    public function handleIndex(WP_REST_Request $request): WP_REST_Response
    {
        $scope   = sanitize_key((string) ($request->get_param('scope') ?? ''));
        $perPage = (int) ($request->get_param('per_page') ?? 100);
        $page    = (int) ($request->get_param('page') ?? 1);

        $result = $this->logsRepo->paginate([
            'per_page' => $perPage,
            'page'     => $page,
            'scope'    => $scope !== '' ? $scope : null,
        ]);

        return new WP_REST_Response($result, 200);
    }

    public function handleClear(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = LogsTable::tableName($wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("TRUNCATE TABLE {$table}");

        return new WP_REST_Response(['ok' => true], 200);
    }
}
