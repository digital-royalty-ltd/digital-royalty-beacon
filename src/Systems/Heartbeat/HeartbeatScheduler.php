<?php

namespace DigitalRoyalty\Beacon\Systems\Heartbeat;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEventEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Api\ApiClient;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationCatalogPublisher;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationRegistry;

/**
 * Sends a daily heartbeat to the Beacon API so the dashboard knows this
 * site has an active WP Beacon installation. Also fires lifecycle signals
 * on plugin activation, deactivation, and uninstall.
 */
final class HeartbeatScheduler
{
    public const CRON_HOOK = 'dr_beacon_heartbeat';

    private const RECURRENCE = 'daily';

    private const WEBHOOK_SECRET_OPTION = 'dr_beacon_webhook_secret';

    public function register(): void
    {
        add_action(self::CRON_HOOK, [$this, 'sendHeartbeat']);

        // Self-heal: if the cron event wasn't scheduled (e.g. the plugin was
        // activated before this scheduler existed), schedule it now so it
        // doesn't silently stay missing until someone deactivates+reactivates.
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, self::RECURRENCE, self::CRON_HOOK);
        }
    }

    /**
     * Schedule the heartbeat cron on plugin activation and send an immediate ping.
     */
    public static function onActivation(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), self::RECURRENCE, self::CRON_HOOK);
        }

        // Fire immediately so the dashboard knows right away
        self::sendLifecycleSignal('active');
    }

    /**
     * Unschedule heartbeat and notify dashboard on plugin deactivation.
     */
    public static function onDeactivation(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        self::sendLifecycleSignal('deactivated');
    }

    /**
     * Notify dashboard on plugin uninstall.
     * Called from uninstall.php (static context, no instance available).
     */
    public static function onUninstall(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        self::sendLifecycleSignal('uninstalled');
        delete_option(self::WEBHOOK_SECRET_OPTION);
    }

    /**
     * WP cron callback — send the daily heartbeat.
     */
    public function sendHeartbeat(): void
    {
        self::sendLifecycleSignal('active');
    }

    /**
     * Synchronous diagnostic run: fire a heartbeat + attempt catalog publish and
     * return the full trace. Used by the admin "Run heartbeat now" button so
     * operators can see exactly what goes out and what comes back.
     *
     * @return array{
     *   heartbeat: array{payload: array<string, mixed>, ok: bool, code: ?int, message: ?string, body: ?array<string, mixed>},
     *   catalog:   array<string, mixed>,
     *   duration_ms: int
     * }
     */
    public static function runDiagnostic(bool $forceCatalog = false): array
    {
        $start = microtime(true);
        $client = Services::apiClient();

        $payload = [
            'status'         => 'active',
            'plugin_version' => defined('DR_BEACON_VERSION') ? DR_BEACON_VERSION : '0.0.0',
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => PHP_VERSION,
            'site_url'       => get_site_url(),
            'webhook_url'    => rest_url('dr-beacon/v1/webhook'),
            'webhook_secret' => self::getOrCreateWebhookSecret(),
        ];

        Services::logger()->info(
            LogScopeEnum::API,
            LogEventEnum::API_REQUEST_START,
            'Diagnostic heartbeat attempted.',
            ['payload' => $payload + ['webhook_secret' => '[redacted]']]
        );

        $hbResponse = null;
        $hbError = null;
        try {
            $hbResponse = $client->heartbeat($payload);
        } catch (\Throwable $e) {
            $hbError = $e->getMessage();
        }

        $heartbeat = [
            'payload' => $payload + ['webhook_secret' => '[redacted]'],
            'ok'      => (bool) ($hbResponse?->ok ?? false),
            'code'    => $hbResponse?->code,
            'message' => $hbError ?? $hbResponse?->message,
            'body'    => is_array($hbResponse->data ?? null) ? $hbResponse->data : null,
        ];

        Services::logger()->info(
            LogScopeEnum::API,
            $heartbeat['ok'] ? LogEventEnum::API_REQUEST_OK : LogEventEnum::API_REQUEST_FAILED,
            'Diagnostic heartbeat response.',
            ['ok' => $heartbeat['ok'], 'code' => $heartbeat['code'], 'body' => $heartbeat['body']]
        );

        // Only attempt catalog if heartbeat succeeded — same order as the live flow.
        $catalog = ['skipped_reason' => 'heartbeat failed — catalog not attempted'];
        if ($heartbeat['ok']) {
            try {
                $catalog = (new AutomationCatalogPublisher(new AutomationRegistry()))->publishIfChanged($forceCatalog);
            } catch (\Throwable $e) {
                $catalog = ['error' => $e->getMessage()];
            }
        }

        return [
            'heartbeat'   => $heartbeat,
            'catalog'     => $catalog,
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
        ];
    }

    /**
     * Send a lifecycle signal to the Beacon API.
     */
    private static function sendLifecycleSignal(string $status): void
    {
        try {
            $client = Services::apiClient();

            $response = $client->heartbeat([
                'status' => $status,
                'plugin_version' => defined('DR_BEACON_VERSION') ? DR_BEACON_VERSION : '0.0.0',
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'site_url' => get_site_url(),
                'webhook_url' => rest_url('dr-beacon/v1/webhook'),
                'webhook_secret' => self::getOrCreateWebhookSecret(),
            ]);

            if ($response->ok) {
                Services::logger()->info(
                    LogScopeEnum::API,
                    LogEventEnum::API_REQUEST_OK,
                    "Heartbeat sent: {$status}"
                );

                // Also publish the automation catalog (only hits the API if changed).
                try {
                    (new AutomationCatalogPublisher(new AutomationRegistry()))->publishIfChanged();
                } catch (\Throwable $e) {
                    Services::logger()->warning(
                        LogScopeEnum::SYSTEM,
                        LogEventEnum::API_REQUEST_FAILED,
                        'Catalog publish threw: '.$e->getMessage()
                    );
                }
            } else {
                Services::logger()->warning(
                    LogScopeEnum::API,
                    LogEventEnum::API_REQUEST_FAILED,
                    "Heartbeat rejected by API: {$response->message} (code {$response->code})"
                );
            }
        } catch (\Throwable $e) {
            // Heartbeat failures are non-fatal — log and continue
            if (class_exists(Services::class)) {
                Services::logger()->warning(
                    LogScopeEnum::API,
                    LogEventEnum::API_REQUEST_FAILED,
                    "Heartbeat failed: {$e->getMessage()}"
                );
            }
        }
    }

    /**
     * Get or create a stable webhook secret for this installation.
     */
    private static function getOrCreateWebhookSecret(): string
    {
        $secret = get_option(self::WEBHOOK_SECRET_OPTION);

        if (!$secret || !is_string($secret) || strlen($secret) < 32) {
            $secret = wp_generate_password(64, false);
            update_option(self::WEBHOOK_SECRET_OPTION, $secret, false);
        }

        return $secret;
    }
}
