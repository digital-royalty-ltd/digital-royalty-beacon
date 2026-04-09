<?php

namespace DigitalRoyalty\Beacon\Systems\Heartbeat;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEventEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Api\ApiClient;

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
     * Send a lifecycle signal to the Beacon API.
     */
    private static function sendLifecycleSignal(string $status): void
    {
        try {
            $client = new ApiClient();

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
                    LogEventEnum::API_RESPONSE_SUCCESS,
                    "Heartbeat sent: {$status}"
                );
            }
        } catch (\Throwable $e) {
            // Heartbeat failures are non-fatal — log and continue
            if (class_exists(Services::class)) {
                Services::logger()->info(
                    LogScopeEnum::API,
                    LogEventEnum::API_RESPONSE_ERROR,
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
