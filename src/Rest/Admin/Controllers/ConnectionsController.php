<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Admin\ConfigurationEnum;
use DigitalRoyalty\Beacon\Support\Enums\Api\OAuthProviderEnum;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET    /admin/connections              — list all providers + connection status
 * POST   /admin/connections/{provider}/initiate — start OAuth flow, returns redirect URL
 * DELETE /admin/connections/{provider}  — disconnect a provider
 */
final class ConnectionsController
{
    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/admin/connections', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);

        register_rest_route('beacon/v1', '/admin/connections/(?P<provider>[a-z][a-z0-9-]*)/initiate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'initiate'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);

        register_rest_route('beacon/v1', '/admin/connections/(?P<provider>[a-z][a-z0-9-]*)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'disconnect'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        /** @var array<string, array<string, mixed>> $stored */
        $stored = (array) get_option(ConfigurationEnum::OPTION_CONNECTIONS, []);

        $providers = [];
        foreach (OAuthProviderEnum::all() as $key) {
            $conn = isset($stored[$key]) && is_array($stored[$key]) ? $stored[$key] : [];
            $providers[] = [
                'key'          => $key,
                'connected'    => !empty($conn['connected']),
                'connected_at' => $conn['connected_at'] ?? null,
            ];
        }

        return new WP_REST_Response(['providers' => $providers], 200);
    }

    public function initiate(WP_REST_Request $request): WP_REST_Response
    {
        $provider = (string) $request->get_param('provider');

        if (!OAuthProviderEnum::isValid($provider)) {
            return new WP_REST_Response(['message' => 'Unknown provider.'], 422);
        }

        $state       = wp_generate_password(32, false);
        $callbackUrl = rest_url('beacon/v1/oauth/callback');

        update_option(ConfigurationEnum::OPTION_STATE, [
            'state'    => $state,
            'provider' => $provider,
        ], false);

        $res = Services::apiClient()->initiateOAuth($provider, $callbackUrl, $state);

        if (!$res->isOk()) {
            return new WP_REST_Response(['message' => $res->message ?? 'Could not start OAuth flow.'], 502);
        }

        $url = is_array($res->data) ? (string) ($res->data['url'] ?? '') : '';

        if ($url === '') {
            return new WP_REST_Response(['message' => 'No redirect URL returned by Beacon.'], 502);
        }

        return new WP_REST_Response(['url' => $url], 200);
    }

    public function disconnect(WP_REST_Request $request): WP_REST_Response
    {
        $provider = (string) $request->get_param('provider');

        if (!OAuthProviderEnum::isValid($provider)) {
            return new WP_REST_Response(['message' => 'Unknown provider.'], 422);
        }

        $res = Services::apiClient()->disconnectOAuth($provider);

        if (!$res->isOk()) {
            return new WP_REST_Response(['message' => $res->message ?? 'Disconnect failed.'], 502);
        }

        /** @var array<string, array<string, mixed>> $stored */
        $stored = (array) get_option(ConfigurationEnum::OPTION_CONNECTIONS, []);
        unset($stored[$provider]);
        update_option(ConfigurationEnum::OPTION_CONNECTIONS, $stored, false);

        return new WP_REST_Response(['ok' => true], 200);
    }
}
