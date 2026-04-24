<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Repositories\DeferredRequestsRepository;
use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationManager;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationRegistry;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for the Automations admin page.
 *
 * GET  /admin/automations              — list all automations with dependency + status info
 * POST /admin/automations/{key}/run    — trigger a background automation
 * GET  /admin/automations/{key}/result — fetch the latest result (artifact data live from Laravel)
 * POST /admin/automations/{key}/implement — trigger Content Generator for one or all recommendations
 */
final class AutomationsController
{
    private readonly AutomationRegistry $registry;
    private readonly AutomationManager  $manager;

    public function __construct(
        private readonly ReportsRepository         $reportsRepo,
        private readonly DeferredRequestsRepository $deferredRepo
    ) {
        $this->registry = new AutomationRegistry();
        $this->manager  = new AutomationManager($this->registry, $this->reportsRepo, $this->deferredRepo);
    }

    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        register_rest_route('beacon/v1', '/admin/automations', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listAutomations'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/automations/(?P<key>[a-z0-9_-]+)/run', [
            'methods'             => 'POST',
            'callback'            => [$this, 'runAutomation'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/automations/(?P<key>[a-z0-9_-]+)/result', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getResult'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/automations/(?P<key>[a-z0-9_-]+)/implement', [
            'methods'             => 'POST',
            'callback'            => [$this, 'implement'],
            'permission_callback' => $perm,
        ]);
    }

    // -----------------------------------------------------------------------
    // List
    // -----------------------------------------------------------------------

    public function listAutomations(WP_REST_Request $request): WP_REST_Response
    {
        $automations = [];

        foreach ($this->registry->all() as $automation) {
            $depCheck     = $this->manager->checkDependencies($automation);
            $deferredKey  = $automation->deferredKey();
            $latestRow    = $deferredKey ? $this->deferredRepo->getLatestByKey($deferredKey) : null;

            $automations[] = [
                'key'             => $automation->key(),
                'label'           => $automation->label(),
                'description'     => $automation->description(),
                'deferred_key'    => $deferredKey,
                'categories'      => $automation->categories(),
                'supported_modes' => $automation->supportedModes(),
                'dependencies'    => $depCheck['items'],
                'deps_met'        => $depCheck['met'],
                'status'          => $this->resolveStatus($automation->deferredKey(), $depCheck['met'], $latestRow),
                'latest_run'      => $latestRow ? $this->formatLatestRun($latestRow) : null,
            ];
        }

        return new WP_REST_Response($automations, 200);
    }

    // -----------------------------------------------------------------------
    // Run
    // -----------------------------------------------------------------------

    public function runAutomation(WP_REST_Request $request): WP_REST_Response
    {
        $key = (string) $request->get_param('key');

        $result = $this->manager->run($key);

        if (!$result['ok']) {
            return new WP_REST_Response(['error' => $result['message']], 400);
        }

        return new WP_REST_Response([
            'status'              => 'queued',
            'message'             => $result['message'],
            'deferred_request_id' => $result['deferred_request_id'] ?? null,
        ], 202);
    }

    // -----------------------------------------------------------------------
    // Result
    // -----------------------------------------------------------------------

    public function getResult(WP_REST_Request $request): WP_REST_Response
    {
        $key = (string) $request->get_param('key');

        $automation = $this->registry->find($key);

        if (!$automation || !$automation->deferredKey()) {
            return new WP_REST_Response(['error' => 'No results available for this automation.'], 404);
        }

        $row = $this->deferredRepo->getLatestByKey($automation->deferredKey());

        if (!$row) {
            return new WP_REST_Response(['status' => 'not_run'], 200);
        }

        $status = $row['status'] ?? 'pending';

        if ($status !== 'completed') {
            return new WP_REST_Response(['status' => $status], 200);
        }

        $resultJson = $row['result'] ?? null;
        $result     = is_string($resultJson) ? json_decode($resultJson, true) : null;
        $meta       = is_array($result) && is_array($result['meta'] ?? null) ? $result['meta'] : [];

        $contentRecsId = $meta['content_recs_artifact_id'] ?? null;
        $areaRecsId    = $meta['area_recs_artifact_id']    ?? null;

        $apiClient = Services::apiClient();

        $contentRecs = null;
        $areaRecs    = null;

        if (is_string($contentRecsId)) {
            $res = $apiClient->getArtifact($contentRecsId);
            if ($res->ok) {
                $contentRecs = $res->data['artifact']['payload'] ?? null;
            }
        }

        if (is_string($areaRecsId)) {
            $res = $apiClient->getArtifact($areaRecsId);
            if ($res->ok) {
                $areaRecs = $res->data['artifact']['payload'] ?? null;
            }
        }

        return new WP_REST_Response([
            'status'       => 'completed',
            'completed_at' => $row['updated_at'] ?? null,
            'content_recs' => $contentRecs,
            'area_recs'    => $areaRecs,
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Implement
    // -----------------------------------------------------------------------

    public function implement(WP_REST_Request $request): WP_REST_Response
    {
        $key              = (string) $request->get_param('key');
        $contentAreaKey   = trim((string) ($request->get_param('content_area_key') ?? ''));
        $topic            = trim((string) ($request->get_param('topic') ?? ''));
        $artifactId       = trim((string) ($request->get_param('artifact_id') ?? ''));

        if ($contentAreaKey === '' || $topic === '') {
            return new WP_REST_Response(['error' => 'content_area_key and topic are required.'], 400);
        }

        $map   = get_option('dr_beacon_content_area_map', []);
        $entry = is_array($map[$contentAreaKey] ?? null) ? $map[$contentAreaKey] : [];

        if (empty($entry)) {
            return new WP_REST_Response(['error' => 'Content area not found.'], 404);
        }

        $apiPayload = [
            'content_area'     => (string) ($entry['label']  ?? $contentAreaKey),
            'intent'           => (string) ($entry['intent'] ?? ''),
            'topic'            => $topic,
            'suggested_topics' => array_values(array_filter((array) ($entry['topics'] ?? []), 'is_string')),
            'content_area_key' => $contentAreaKey,
        ];

        if ($artifactId !== '') {
            $apiPayload['source_artifact_id'] = $artifactId;
        }

        $response = Services::apiClient()->generateContentDraft($apiPayload);

        if (!$response->ok) {
            return new WP_REST_Response([
                'error' => $response->message ?? 'Generation request failed.',
            ], $response->code >= 400 ? $response->code : 500);
        }

        return new WP_REST_Response([
            'status'  => 'queued',
            'message' => 'Draft queued from recommendation.',
        ], 202);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * @param array<string,mixed>|null $latestRow
     */
    private function resolveStatus(?string $deferredKey, bool $depsMet, ?array $latestRow): string
    {
        if (!$depsMet) {
            return 'dependencies_missing';
        }

        if (!$deferredKey) {
            return 'tool'; // interactive tool, no background job
        }

        if (!$latestRow) {
            return 'ready';
        }

        return match ($latestRow['status'] ?? 'pending') {
            'pending'   => 'running',
            'completed' => 'completed',
            'failed'    => 'failed',
            default     => 'ready',
        };
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function formatLatestRun(array $row): array
    {
        return [
            'id'           => (int) ($row['id'] ?? 0),
            'status'       => $row['status'] ?? null,
            'created_at'   => $row['created_at'] ?? null,
            'updated_at'   => $row['updated_at'] ?? null,
        ];
    }
}
