<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Reports\ReportTypeEnum;
use DigitalRoyalty\Beacon\Systems\Reports\ReportManager;
use DigitalRoyalty\Beacon\Systems\Reports\ReportRegistry;
use DigitalRoyalty\Beacon\Systems\Reports\ReportService;
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

        register_rest_route('beacon/v1', '/admin/reports/(?P<type>[a-z][a-z0-9_]*)/regenerate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRegenerate'],
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

        // Index existing reports by type.
        $existing = [];
        foreach ($rows as $row) {
            $existing[$row['type']] = [
                'type'         => $row['type'],
                'label'        => ReportTypeEnum::label($row['type']),
                'version'      => (int) $row['version'],
                'status'       => $row['status'],
                'generated_at' => $row['generated_at'] ?? null,
                'submitted_at' => $row['submitted_at'] ?? null,
            ];
        }

        // Include all registered report types so new ones appear immediately.
        $registry = new ReportRegistry();
        foreach ($registry->required() as $generator) {
            $type = $generator->type();
            if (!isset($existing[$type])) {
                $existing[$type] = [
                    'type'         => $type,
                    'label'        => ReportTypeEnum::label($type),
                    'version'      => $generator->version(),
                    'status'       => 'not_generated',
                    'generated_at' => null,
                    'submitted_at' => null,
                ];
            }
        }

        return new WP_REST_Response(array_values($existing), 200);
    }

    public function handleGet(WP_REST_Request $request): WP_REST_Response
    {
        $type = (string) $request->get_param('type');
        $row  = $this->reportsRepo->getLatestByType($type);

        if (!$row) {
            return new WP_REST_Response(['error' => 'Report not found.'], 404);
        }

        $payload = !empty($row['payload']) ? json_decode($row['payload'], true) : null;

        $response = [
            'type'         => $row['type'],
            'label'        => ReportTypeEnum::label($row['type']),
            'version'      => (int) $row['version'],
            'status'       => $row['status'],
            'generated_at' => $row['generated_at'] ?? null,
            'submitted_at' => $row['submitted_at'] ?? null,
            'payload'      => $payload,
        ];

        // Attach local routing maps for reports that maintain them.
        $localMaps = $this->getLocalMaps($type);
        if (!empty($localMaps)) {
            $response['local_maps'] = $localMaps;
        }

        return new WP_REST_Response($response, 200);
    }

    /**
     * Return local WP routing maps associated with a report type.
     *
     * @return array<string, mixed>
     */
    private function getLocalMaps(string $type): array
    {
        $mapsByType = [
            'website_content_areas' => [
                'content_area_map' => 'dr_beacon_content_area_map',
            ],
            'website_profile' => [
                'key_pages_map' => 'dr_beacon_key_pages_map',
            ],
        ];

        $optionKeys = $mapsByType[$type] ?? [];
        $maps = [];

        foreach ($optionKeys as $label => $optionName) {
            $value = get_option($optionName, []);
            if (!empty($value) && is_array($value)) {
                $maps[$label] = $value;
            }
        }

        return $maps;
    }

    public function handleRegenerate(WP_REST_Request $request): WP_REST_Response
    {
        $type = (string) $request->get_param('type');

        $registry  = new ReportRegistry();
        $generator = null;

        foreach ($registry->required() as $g) {
            if ($g->type() === $type) {
                $generator = $g;
                break;
            }
        }

        if (!$generator) {
            return new WP_REST_Response(['error' => 'Unknown report type.'], 404);
        }

        // Mark as pending and enqueue via the standalone hook (no chaining).
        $this->reportsRepo->upsertPending($type, $generator->version());

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(
                ReportService::ACTION_REGENERATE_REPORT,
                [$type, $generator->version()],
                'dr-beacon'
            );
        }

        return new WP_REST_Response([
            'ok'      => true,
            'message' => ReportTypeEnum::label($type) . ' report queued for regeneration.',
        ], 202);
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
