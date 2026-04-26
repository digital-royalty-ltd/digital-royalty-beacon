<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Repositories\ApiKeysRepository;
use DigitalRoyalty\Beacon\Repositories\ApiLogsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Api\PublicApiEndpointRegistry;
use WP_REST_Request;
use WP_REST_Response;

final class ApiManagerController
{
    private const NAMESPACE = 'beacon/v1';

    public function __construct(
        private readonly ApiKeysRepository $keysRepo,
        private readonly ApiLogsRepository $logsRepo
    ) {}

    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        // ── Endpoint toggles ──────────────────────────────────────────────

        register_rest_route(self::NAMESPACE, '/admin/api-endpoints', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'indexEndpoints'],
                'permission_callback' => $perm,
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/api-endpoints/(?P<key>[a-z0-9._-]+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'toggleEndpoint'],
                'permission_callback' => $perm,
                'args'                => [
                    'enabled' => ['required' => true, 'type' => 'boolean'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/api-keys', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'create'],
                'permission_callback' => $perm,
                'args'                => [
                    'name'           => ['required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_text_field'],
                    'max_concurrent' => ['required' => false, 'type' => 'integer', 'default' => 1,   'minimum' => 1],
                    'hourly_limit'   => ['required' => false, 'type' => 'integer', 'default' => 60,  'minimum' => 1],
                    'daily_limit'    => ['required' => false, 'type' => 'integer', 'default' => 500, 'minimum' => 1],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/api-keys/(?P<id>\d+)/logs', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'keyLogs'],
                'permission_callback' => $perm,
                'args'                => [
                    'page'     => ['required' => false, 'type' => 'integer', 'default' => 1,  'minimum' => 1],
                    'per_page' => ['required' => false, 'type' => 'integer', 'default' => 25, 'minimum' => 10, 'maximum' => 100],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/api-keys/(?P<id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'update'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'destroy'],
                'permission_callback' => $perm,
            ],
        ]);
    }

    public function keyLogs(WP_REST_Request $request): WP_REST_Response
    {
        $id  = (int) $request->get_param('id');
        $row = $this->keysRepo->findById($id);

        if ($row === null) {
            return new WP_REST_Response(['message' => 'Not found.'], 404);
        }

        $result = $this->logsRepo->paginate($id, [
            'page'     => (int) $request->get_param('page'),
            'per_page' => (int) $request->get_param('per_page'),
        ]);

        return new WP_REST_Response($result, 200);
    }

    public function indexEndpoints(): WP_REST_Response
    {
        return new WP_REST_Response(PublicApiEndpointRegistry::allWithState(), 200);
    }

    public function toggleEndpoint(WP_REST_Request $request): WP_REST_Response
    {
        $key     = (string) $request->get_param('key');
        $enabled = (bool)   $request->get_param('enabled');

        $all = array_column(PublicApiEndpointRegistry::all(), 'key');
        if (!in_array($key, $all, true)) {
            return new WP_REST_Response(['message' => 'Unknown endpoint key.'], 404);
        }

        $current = PublicApiEndpointRegistry::enabledKeys();

        if ($enabled) {
            $current[] = $key;
            $current   = array_values(array_unique($current));
        } else {
            $current = array_values(array_filter($current, fn ($k) => $k !== $key));
        }

        PublicApiEndpointRegistry::setEnabled($current);

        return new WP_REST_Response(['success' => true], 200);
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response($this->keysRepo->all(), 200);
    }

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $name = trim((string) $request->get_param('name'));

        if ($name === '') {
            return new WP_REST_Response(['message' => 'Name is required.'], 422);
        }

        // Generate a secure random key: drb_ + 32 random hex chars
        $raw    = 'drb_' . bin2hex(random_bytes(16));
        $hash   = hash('sha256', $raw);
        $prefix = substr($raw, 0, 12); // "drb_" + 8 chars

        $maxConcurrent = (int) ($request->get_param('max_concurrent') ?? 1);
        $hourlyLimit   = (int) ($request->get_param('hourly_limit')   ?? 60);
        $dailyLimit    = (int) ($request->get_param('daily_limit')    ?? 500);

        $id = $this->keysRepo->insert($name, $hash, $prefix, $maxConcurrent, $hourlyLimit, $dailyLimit);

        // Audit log: key creation is a security-relevant event. We record
        // the prefix (not the full key) so an operator can correlate later
        // usage in api_logs against this specific issuance.
        Services::logger()->info(
            LogScopeEnum::ADMIN,
            'public_api_key_created',
            "Public API key '{$name}' created.",
            [
                'api_key_id' => $id,
                'name' => $name,
                'key_prefix' => $prefix,
                'max_concurrent' => max(1, $maxConcurrent),
                'hourly_limit' => max(1, $hourlyLimit),
                'daily_limit' => max(1, $dailyLimit),
                'user_id' => get_current_user_id() ?: null,
            ]
        );

        return new WP_REST_Response([
            'id'             => $id,
            'name'           => $name,
            'key'            => $raw,
            'key_prefix'     => $prefix,
            'is_active'      => true,
            'max_concurrent' => max(1, $maxConcurrent),
            'hourly_limit'   => max(1, $hourlyLimit),
            'daily_limit'    => max(1, $dailyLimit),
            'last_used_at'   => null,
            'created_at'     => current_time('mysql', true),
        ], 201);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $id  = (int) $request->get_param('id');
        $row = $this->keysRepo->findById($id);

        if ($row === null) {
            return new WP_REST_Response(['message' => 'Not found.'], 404);
        }

        $changes = [];

        if ($request->has_param('name')) {
            $name = trim(sanitize_text_field((string) $request->get_param('name')));
            if ($name !== '') {
                $this->keysRepo->rename($id, $name);
                $changes['name'] = $name;
            }
        }

        if ($request->has_param('is_active')) {
            $isActive = (bool) $request->get_param('is_active');
            $this->keysRepo->setActive($id, $isActive);
            $changes['is_active'] = $isActive;
        }

        $hasLimitUpdate = $request->has_param('max_concurrent')
            || $request->has_param('hourly_limit')
            || $request->has_param('daily_limit');

        if ($hasLimitUpdate) {
            $current = $this->keysRepo->findById($id);
            $maxConcurrent = (int) ($request->get_param('max_concurrent') ?? $current['max_concurrent'] ?? 1);
            $hourlyLimit = (int) ($request->get_param('hourly_limit') ?? $current['hourly_limit'] ?? 60);
            $dailyLimit = (int) ($request->get_param('daily_limit') ?? $current['daily_limit'] ?? 500);
            $this->keysRepo->updateLimits($id, $maxConcurrent, $hourlyLimit, $dailyLimit);
            $changes['limits'] = compact('maxConcurrent', 'hourlyLimit', 'dailyLimit');
        }

        if ($changes !== []) {
            // is_active changes are the highest-stakes mutation here — a key
            // being disabled silently means real third-party traffic could
            // start failing. Keep at info but emphasise via the message.
            Services::logger()->info(
                LogScopeEnum::ADMIN,
                'public_api_key_updated',
                "Public API key #{$id} updated.",
                [
                    'api_key_id' => $id,
                    'changes' => $changes,
                    'user_id' => get_current_user_id() ?: null,
                ]
            );
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        $id  = (int) $request->get_param('id');
        $row = $this->keysRepo->findById($id);

        if ($row === null) {
            return new WP_REST_Response(['message' => 'Not found.'], 404);
        }

        $this->keysRepo->delete($id);

        // Warn-level: key deletion is destructive and revoking access to a
        // third-party that depended on this key. Wants to stand out.
        Services::logger()->warning(
            LogScopeEnum::ADMIN,
            'public_api_key_deleted',
            "Public API key #{$id} ('{$row['name']}') deleted.",
            [
                'api_key_id' => $id,
                'name' => $row['name'] ?? null,
                'key_prefix' => $row['key_prefix'] ?? null,
                'user_id' => get_current_user_id() ?: null,
            ]
        );

        return new WP_REST_Response(['success' => true], 200);
    }
}
