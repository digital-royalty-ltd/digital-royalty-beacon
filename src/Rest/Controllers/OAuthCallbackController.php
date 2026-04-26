<?php

namespace DigitalRoyalty\Beacon\Rest\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\ConfigurationEnum;
use DigitalRoyalty\Beacon\Support\Enums\Api\OAuthProviderEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
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
        $logger = Services::logger();

        if ($code === '' || $state === '') {
            $logger->warning(
                LogScopeEnum::ADMIN,
                'oauth_callback_invalid_params',
                'OAuth callback received without code or state.',
                ['has_code' => $code !== '', 'has_state' => $state !== '']
            );
            $this->redirectToConfiguration(false, 'Invalid OAuth callback parameters.');
            return;
        }

        /** @var array<string, string>|string $stored */
        $stored = get_option(ConfigurationEnum::OPTION_STATE, '');

        $storedState    = is_array($stored) ? (string) ($stored['state'] ?? '') : '';
        $storedProvider = is_array($stored) ? (string) ($stored['provider'] ?? '') : '';

        if ($storedState === '' || !hash_equals($storedState, $state)) {
            // State mismatch is the OAuth CSRF check. Logging it lets us
            // distinguish "user clicked an old link" from "actual attack".
            $logger->warning(
                LogScopeEnum::ADMIN,
                'oauth_state_mismatch',
                'OAuth callback state did not match the stored value.',
                [
                    'has_stored_state' => $storedState !== '',
                    'stored_provider' => $storedProvider,
                    'user_id' => get_current_user_id() ?: null,
                ]
            );
            $this->redirectToConfiguration(false, 'OAuth state mismatch. Please try connecting again.');
            return;
        }

        if (!OAuthProviderEnum::isValid($storedProvider)) {
            $logger->warning(
                LogScopeEnum::ADMIN,
                'oauth_provider_invalid',
                "OAuth callback completed but stored provider '{$storedProvider}' is not recognised.",
                ['stored_provider' => $storedProvider]
            );
            $this->redirectToConfiguration(false, 'OAuth provider could not be determined. Please try again.');
            return;
        }

        $provider     = $storedProvider;
        $codeVerifier = is_array($stored) ? ($stored['code_verifier'] ?? null) : null;

        delete_option(ConfigurationEnum::OPTION_STATE);

        $callbackUrl = rest_url('beacon/v1/oauth/callback');

        $result = Services::apiClient()->completeOAuth($provider, $code, $state, $callbackUrl, $codeVerifier);

        if (!$result->ok) {
            $logger->warning(
                LogScopeEnum::ADMIN,
                'oauth_complete_failed',
                "OAuth completion failed for provider '{$provider}': {$result->message}",
                [
                    'provider' => $provider,
                    'response_code' => $result->code,
                    'response_message' => $result->message,
                ]
            );
            $this->redirectToConfiguration(false, $result->message ?? 'OAuth connection failed.');
            return;
        }

        $this->saveConnection($provider);

        $logger->info(
            LogScopeEnum::ADMIN,
            'oauth_connected',
            "OAuth connection succeeded for provider '{$provider}'.",
            ['provider' => $provider, 'user_id' => get_current_user_id() ?: null]
        );

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

        $saved = update_option(ConfigurationEnum::OPTION_CONNECTIONS, $connections, false);

        if (!$saved) {
            // OAuth succeeded with the API but we couldn't persist it
            // locally — the next page-load won't know we're connected.
            try {
                Services::logger()->warning(
                    LogScopeEnum::ADMIN,
                    'oauth_connection_persist_failed',
                    "OAuth completed for '{$provider}' but local connection state could not be persisted.",
                    ['provider' => $provider]
                );
            } catch (\Throwable) {
                // ignore
            }
        }
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
