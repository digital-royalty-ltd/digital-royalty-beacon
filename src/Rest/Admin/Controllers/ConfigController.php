<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Systems\Api\ApiClient;
use DigitalRoyalty\Beacon\Systems\Reports\ReportManager;
use DigitalRoyalty\Beacon\Support\Enums\Admin\HomeViewOptionEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEventEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use WP_REST_Request;
use WP_REST_Response;

/**
 * POST /beacon/v1/admin/config/api-key  — verify + save API key
 * DELETE /beacon/v1/admin/config/api-key — disconnect
 */
final class ConfigController
{
    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/admin/config/api-key', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handleSave'],
                'permission_callback' => fn () => current_user_can('manage_options'),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'handleDisconnect'],
                'permission_callback' => fn () => current_user_can('manage_options'),
            ],
        ]);
    }

    public function handleSave(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $apiKey = isset($params['api_key']) ? sanitize_text_field((string) $params['api_key']) : '';

        if ($apiKey === '') {
            return new WP_REST_Response(['message' => 'API key is required.'], 422);
        }

        $client = new ApiClient($apiKey);
        $res    = $client->verifyApiKey();

        if (!$res->isOk()) {
            $msg = $res->message ?? ($res->isUnauthorized() ? 'Invalid API key.' : 'Verification failed.');

            Services::logger()->warning(LogScopeEnum::ADMIN, LogEventEnum::CONNECT_FAILED, $msg, [
                'code' => $res->code,
            ]);

            return new WP_REST_Response(['message' => $msg], 422);
        }

        update_option(HomeViewOptionEnum::API_KEY, $apiKey, false);
        update_option(HomeViewOptionEnum::CONNECTED_AT, gmdate('c'), false);
        Services::reset();

        Services::logger()->info(LogScopeEnum::ADMIN, LogEventEnum::CONNECTED, 'Connected successfully.');

        return new WP_REST_Response([
            'ok'           => true,
            'masked_key'   => $this->maskKey($apiKey),
            'connected_at' => (string) get_option(HomeViewOptionEnum::CONNECTED_AT, ''),
        ], 200);
    }

    public function handleDisconnect(WP_REST_Request $request): WP_REST_Response
    {
        delete_option(HomeViewOptionEnum::API_KEY);
        delete_option(HomeViewOptionEnum::CONNECTED_AT);
        delete_option(ReportManager::OPTION_STATUS);

        Services::reset();
        Services::logger()->info(LogScopeEnum::ADMIN, LogEventEnum::DISCONNECTED, 'Disconnected.');

        return new WP_REST_Response(['ok' => true], 200);
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
