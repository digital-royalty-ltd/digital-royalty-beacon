<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Sink for client-side errors raised in the React admin SPA.
 *
 * The PHP-side rest_post_dispatch logger catches WP REST failures, but it
 * cannot see things that happen in the browser only — fetch network errors,
 * JSON parse failures, JS exceptions inside catch blocks, etc. This endpoint
 * exists so api.ts can fire-and-forget a record of those.
 *
 * Per-user rate limit (default 60 events / 5 minutes) prevents a runaway
 * page from flooding the log table during an outage.
 */
final class ClientErrorLogController
{
    private const RATE_LIMIT_WINDOW_SECONDS = 300; // 5 minutes
    private const RATE_LIMIT_MAX_EVENTS = 60;

    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/admin/log/client-error', [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        if ($this->isRateLimited()) {
            return new WP_REST_Response(['ok' => true, 'rate_limited' => true], 200);
        }

        $params = (array) $request->get_json_params();

        $type = $this->normaliseType($params['type'] ?? null);
        $path = $this->clip((string) ($params['path'] ?? ''), 240);
        $method = strtoupper($this->clip((string) ($params['method'] ?? ''), 10));
        $status = isset($params['status']) && is_numeric($params['status']) ? (int) $params['status'] : null;
        $message = $this->clip((string) ($params['message'] ?? ''), 480);
        $code = $this->clip((string) ($params['code'] ?? ''), 80);

        $context = [
            'type' => $type,
            'path' => $path,
            'method' => $method !== '' ? $method : null,
            'status' => $status,
            'code' => $code !== '' ? $code : null,
            'message' => $message !== '' ? $message : null,
            'user_id' => get_current_user_id() ?: null,
            'user_agent' => $this->clip((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 240),
            'screen' => $this->clip((string) ($params['screen'] ?? ''), 80),
        ];

        $logger = Services::logger();
        $event = "client_error_{$type}";
        $summary = sprintf(
            '[client] %s %s%s — %s',
            $method !== '' ? $method : 'GET',
            $path !== '' ? $path : '(unknown)',
            $status !== null ? " ({$status})" : '',
            $message !== '' ? $message : '(no message)'
        );

        match ($this->severity($type, $status)) {
            'error' => $logger->error(LogScopeEnum::ADMIN, $event, $summary, $context),
            'warning' => $logger->warning(LogScopeEnum::ADMIN, $event, $summary, $context),
            default => $logger->info(LogScopeEnum::ADMIN, $event, $summary, $context),
        };

        return new WP_REST_Response(['ok' => true], 200);
    }

    private function normaliseType(mixed $type): string
    {
        $type = is_string($type) ? strtolower(trim($type)) : '';

        return match ($type) {
            'api', 'network', 'javascript', 'parse' => $type,
            default => 'api',
        };
    }

    private function severity(string $type, ?int $status): string
    {
        if ($type === 'javascript') {
            return 'error';
        }
        if ($type === 'network' || $type === 'parse') {
            return 'warning';
        }
        // type === 'api'
        if ($status === null) {
            return 'warning';
        }
        if ($status >= 500) {
            return 'error';
        }
        if ($status === 401 || $status === 403) {
            return 'info';
        }

        return 'warning';
    }

    /**
     * Sliding-window rate limit, scoped per user.
     *
     * The client also rate-limits before posting, but a server-side bound
     * is the only thing that protects the log table from a misbehaving or
     * outdated client bundle.
     */
    private function isRateLimited(): bool
    {
        $userId = get_current_user_id() ?: 0;
        $key = "dr_beacon_client_err_rate_{$userId}";

        $events = get_transient($key);
        $events = is_array($events) ? $events : [];
        $now = time();
        $cutoff = $now - self::RATE_LIMIT_WINDOW_SECONDS;
        $events = array_values(array_filter($events, fn ($t) => is_numeric($t) && (int) $t >= $cutoff));

        if (count($events) >= self::RATE_LIMIT_MAX_EVENTS) {
            return true;
        }

        $events[] = $now;
        set_transient($key, $events, self::RATE_LIMIT_WINDOW_SECONDS);

        return false;
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return (string) mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }
}
