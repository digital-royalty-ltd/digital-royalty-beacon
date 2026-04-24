<?php

namespace DigitalRoyalty\Beacon\Rest\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\ConfigurationEnum;
use DigitalRoyalty\Beacon\Support\Enums\Api\OAuthProviderEnum;
use WP_REST_Request;

final class OAuthCallbackController
{
    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/oauth/callback', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(WP_REST_Request $request): void
    {
        $code  = sanitize_text_field((string) ($request->get_param('code') ?? ''));
        $state = sanitize_text_field((string) ($request->get_param('state') ?? ''));

        if ($code === '' || $state === '') {
            $this->redirectToConfiguration(false, 'Invalid OAuth callback parameters.');
            return;
        }

        /** @var array<string, string>|string $stored */
        $stored = get_option(ConfigurationEnum::OPTION_STATE, '');

        $storedState    = is_array($stored) ? (string) ($stored['state'] ?? '') : '';
        $storedProvider = is_array($stored) ? (string) ($stored['provider'] ?? '') : '';

        if ($storedState === '' || !hash_equals($storedState, $state)) {
            $this->redirectToConfiguration(false, 'OAuth state mismatch. Please try connecting again.');
            return;
        }

        if (!OAuthProviderEnum::isValid($storedProvider)) {
            $this->redirectToConfiguration(false, 'OAuth provider could not be determined. Please try again.');
            return;
        }

        $provider     = $storedProvider;
        $codeVerifier = is_array($stored) ? ($stored['code_verifier'] ?? null) : null;

        delete_option(ConfigurationEnum::OPTION_STATE);

        $callbackUrl = rest_url('beacon/v1/oauth/callback');

        $result = Services::apiClient()->completeOAuth($provider, $code, $state, $callbackUrl, $codeVerifier);

        if (!$result->ok) {
            $this->redirectToConfiguration(false, $result->message ?? 'OAuth connection failed.');
            return;
        }

        $this->saveConnection($provider);
        $this->redirectToConfiguration(true, 'Connected successfully.');
    }

    private function saveConnection(string $provider): void
    {
        /** @var array<string, mixed> $connections */
        $connections = (array) get_option(ConfigurationEnum::OPTION_CONNECTIONS, []);

        $connections[$provider] = [
            'connected'    => true,
            'connected_at' => current_time('mysql'),
        ];

        update_option(ConfigurationEnum::OPTION_CONNECTIONS, $connections, false);
    }

    private function redirectToConfiguration(bool $ok, string $message): void
    {
        $url = add_query_arg(
            [
                'page'          => AdminPageEnum::CONFIGURATION,
                'dr_beacon_ok'  => $ok ? '1' : '0',
                'dr_beacon_msg' => rawurlencode($message),
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
