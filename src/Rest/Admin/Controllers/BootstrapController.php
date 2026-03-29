<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Support\Enums\Admin\ConfigurationEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\HomeViewOptionEnum;
use DigitalRoyalty\Beacon\Support\Enums\Api\OAuthProviderEnum;
use DigitalRoyalty\Beacon\Support\Enums\Reports\ReportTypeEnum;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /beacon/v1/admin/bootstrap
 *
 * Returns everything the SPA needs on initial load that cannot
 * be injected via wp_localize_script (i.e. dynamic / per-request data).
 * Static config (nonce, REST base, site URL) is already in BeaconData.
 */
final class BootstrapController
{
    public function __construct(
        private readonly ReportsRepository $reportsRepo
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/admin/bootstrap', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $apiKey      = (string) get_option(HomeViewOptionEnum::API_KEY, '');
        $connectedAt = (string) get_option(HomeViewOptionEnum::CONNECTED_AT, '');

        return new WP_REST_Response([
            'connection' => [
                'has_api_key'  => $apiKey !== '',
                'masked_key'   => $this->maskKey($apiKey),
                'connected_at' => $connectedAt,
            ],
            'reports'          => $this->reportsRepo->allLatest(),
            'report_stale_days' => ReportTypeEnum::staleDaysMap(),
            'oauth_connections' => $this->oauthConnections(),
        ], 200);
    }

    /**
     * @return array<string, bool>
     */
    private function oauthConnections(): array
    {
        /** @var array<string, array<string, mixed>> $stored */
        $stored = (array) get_option(ConfigurationEnum::OPTION_CONNECTIONS, []);
        $result = [];

        foreach (OAuthProviderEnum::all() as $key) {
            $conn           = isset($stored[$key]) && is_array($stored[$key]) ? $stored[$key] : [];
            $result[$key]   = !empty($conn['connected']);
        }

        return $result;
    }

    private function maskKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        $len = strlen($key);
        if ($len <= 10) {
            return str_repeat('*', $len);
        }
        return substr($key, 0, 6) . str_repeat('*', max(0, $len - 10)) . substr($key, -4);
    }
}
