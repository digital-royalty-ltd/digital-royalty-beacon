<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Systems\Api\ApiClient;
use DigitalRoyalty\Beacon\Systems\Reports\ReportManager;
use DigitalRoyalty\Beacon\Systems\Reports\ReportService;
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

        register_rest_route('beacon/v1', '/admin/config/api-key/verify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerify'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);
    }

    public function handleSave(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $apiKey = isset($params['api_key']) ? sanitize_text_field((string) $params['api_key']) : '';

        if ($apiKey === '') {
            Services::logger()->info(
                LogScopeEnum::ADMIN,
                LogEventEnum::CONNECT_FAILED,
                'API key save rejected: empty input.',
                ['user_id' => get_current_user_id() ?: null]
            );
            return new WP_REST_Response(['message' => 'API key is required.'], 422);
        }

        $client = new ApiClient($apiKey);
        $res    = $client->verifyApiKey();

        if (!$res->isOk()) {
            $msg = $res->message ?? ($res->isUnauthorized() ? 'Invalid API key.' : 'Verification failed.');

            Services::logger()->warning(LogScopeEnum::ADMIN, LogEventEnum::CONNECT_FAILED, $msg, [
                'code' => $res->code,
                'masked_key' => $this->maskKey($apiKey),
                'user_id' => get_current_user_id() ?: null,
            ]);

            return new WP_REST_Response(['message' => $msg], 422);
        }

        update_option(HomeViewOptionEnum::API_KEY, $apiKey, false);
        update_option(HomeViewOptionEnum::CONNECTED_AT, gmdate('c'), false);
        Services::reset();

        Services::logger()->info(LogScopeEnum::ADMIN, LogEventEnum::CONNECTED, 'Connected successfully.', [
            'masked_key' => $this->maskKey($apiKey),
            'user_id' => get_current_user_id() ?: null,
        ]);

        return new WP_REST_Response([
            'ok'           => true,
            'masked_key'   => $this->maskKey($apiKey),
            'connected_at' => (string) get_option(HomeViewOptionEnum::CONNECTED_AT, ''),
        ], 200);
    }

    public function handleVerify(WP_REST_Request $request): WP_REST_Response
    {
        $apiKey = (string) get_option(HomeViewOptionEnum::API_KEY, '');

        if ($apiKey === '') {
            Services::logger()->info(
                LogScopeEnum::ADMIN,
                'verify_api_key_no_key',
                'API key verify called with no stored key.',
                ['user_id' => get_current_user_id() ?: null]
            );
            return new WP_REST_Response(['ok' => false, 'message' => 'No API key configured.'], 422);
        }

        $client = new ApiClient($apiKey);
        $res    = $client->verifyApiKey();

        if (!$res->isOk()) {
            // Verify failures matter — they tell us when a previously-working
            // key has stopped working (rotated, revoked, server change).
            Services::logger()->warning(
                LogScopeEnum::ADMIN,
                'verify_api_key_failed',
                'Stored API key failed verification.',
                [
                    'code' => $res->code,
                    'message' => $res->message,
                    'masked_key' => $this->maskKey($apiKey),
                    'user_id' => get_current_user_id() ?: null,
                ]
            );

            return new WP_REST_Response([
                'ok'      => false,
                'message' => $res->message ?? 'Connection failed.',
            ], 422);
        }

        return new WP_REST_Response([
            'ok'      => true,
            'message' => 'Connection verified.',
        ], 200);
    }

    public function handleDisconnect(WP_REST_Request $request): WP_REST_Response
    {
        delete_option(HomeViewOptionEnum::API_KEY);
        delete_option(HomeViewOptionEnum::CONNECTED_AT);
        delete_option(ReportManager::OPTION_STATUS);

        // Cancel all pending API-dependent actions
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(ReportManager::ACTION_RUN_NEXT, [], 'dr-beacon');
            as_unschedule_all_actions(ReportManager::ACTION_RUN_REPORT, [], 'dr-beacon');
            as_unschedule_all_actions(ReportService::ACTION_REGENERATE_REPORT, [], 'dr-beacon');
        }

        Services::reset();
        Services::logger()->info(LogScopeEnum::ADMIN, LogEventEnum::DISCONNECTED, 'Disconnected.', [
            'user_id' => get_current_user_id() ?: null,
        ]);

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
