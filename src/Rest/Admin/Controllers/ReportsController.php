<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Reports\ReportTypeEnum;
use DigitalRoyalty\Beacon\Systems\Reports\ReportManager;
use DigitalRoyalty\Beacon\Systems\Reports\ReportRegistry;
use WP_REST_Request;
use WP_REST_Response;

final class ReportsController
{
    public function __construct(
        private readonly ReportsRepository $reportsRepo
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/admin/reports/run', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRun'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);

        register_rest_route('beacon/v1', '/admin/reports', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleList'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);

        register_rest_route('beacon/v1', '/admin/reports/(?P<type>[a-z][a-z0-9_]*)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleGet'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);

        register_rest_route('beacon/v1', '/admin/reports/(?P<type>[a-z][a-z0-9_]*)/resubmit', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleResubmit'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);
    }

    public function handleRun(WP_REST_Request $request): WP_REST_Response
    {
        $manager = new ReportManager(new ReportRegistry(), $this->reportsRepo);
        $manager->start();

        return new WP_REST_Response(['ok' => true], 200);
    }

    public function handleList(WP_REST_Request $request): WP_REST_Response
    {
        $rows = $this->reportsRepo->allLatest();

        $items = array_map(function (array $row): array {
            return [
                'type'         => $row['type'],
                'label'        => ReportTypeEnum::label($row['type']),
                'version'      => (int) $row['version'],
                'status'       => $row['status'],
                'generated_at' => $row['generated_at'] ?? null,
                'submitted_at' => $row['submitted_at'] ?? null,
            ];
        }, $rows);

        return new WP_REST_Response($items, 200);
    }

    public function handleGet(WP_REST_Request $request): WP_REST_Response
    {
        $type = (string) $request->get_param('type');
        $row  = $this->reportsRepo->getLatestByType($type);

        if (!$row) {
            return new WP_REST_Response(['error' => 'Report not found.'], 404);
        }

        $payload = !empty($row['payload']) ? json_decode($row['payload'], true) : null;

        return new WP_REST_Response([
            'type'         => $row['type'],
            'label'        => ReportTypeEnum::label($row['type']),
            'version'      => (int) $row['version'],
            'status'       => $row['status'],
            'generated_at' => $row['generated_at'] ?? null,
            'submitted_at' => $row['submitted_at'] ?? null,
            'payload'      => $payload,
        ], 200);
    }

    public function handleResubmit(WP_REST_Request $request): WP_REST_Response
    {
        $type = (string) $request->get_param('type');
        $body = $request->get_json_params();

        if (!isset($body['payload'])) {
            return new WP_REST_Response(['error' => 'payload is required.'], 400);
        }

        $row = $this->reportsRepo->getLatestByType($type);

        if (!$row) {
            return new WP_REST_Response(['error' => 'Report not found.'], 404);
        }

        $editedPayload = $body['payload'];
        $payloadJson   = wp_json_encode($editedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash          = md5($payloadJson);
        $now           = current_time('mysql');
        $version       = (int) $row['version'];

        // Persist the edited payload locally before submitting
        $this->reportsRepo->upsertGenerated($type, $version, $payloadJson, $hash, $now);

        // Build submission envelope
        $envelope = [
            'report_type'    => $type,
            'report_version' => $version,
            'payload'        => $editedPayload,
        ];

        $result = Services::reportSubmitter()->submit($envelope);

        if (!$result['ok']) {
            return new WP_REST_Response([
                'error' => $result['error'] ?? 'Submission failed.',
            ], 502);
        }

        $this->reportsRepo->markSubmitted($type, $version, $now);

        return new WP_REST_Response(['ok' => true], 200);
    }
}
