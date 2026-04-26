<?php

namespace DigitalRoyalty\Beacon\Rest\Observability;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Observes every REST response and writes a log entry for any beacon/v1/*
 * route that returns 4xx or 5xx.
 *
 * Why: the React admin SPA used to swallow API failures behind generic
 * "Could not load …" toasts with nothing reaching the Debug Log. A doubled
 * URL prefix returned 404 invisibly. This hook is the floor that catches
 * those cases regardless of which controller (or non-existent route) was
 * hit.
 *
 * Severity mapping:
 *   5xx                → error
 *   401, 403           → info  (auth churn is expected, not an outage)
 *   404, 405, 410      → warning  (URL/method bugs — this is the case we cared about)
 *   422, 4xx other     → warning  (validation / client errors worth seeing)
 */
final class RestErrorLogger
{
    public function register(): void
    {
        // Priority 100 so other plugins/filters have already finalised the response.
        add_filter('rest_post_dispatch', [$this, 'observe'], 100, 3);
    }

    /**
     * @param  WP_HTTP_Response|WP_REST_Response|mixed  $response
     * @return WP_HTTP_Response|WP_REST_Response|mixed
     */
    public function observe($response, WP_REST_Server $server, WP_REST_Request $request)
    {
        try {
            if (! $response instanceof WP_HTTP_Response) {
                return $response;
            }

            $status = (int) $response->get_status();
            if ($status < 400) {
                return $response;
            }

            $route = (string) $request->get_route();
            if ($route === '' || ! str_starts_with($route, '/beacon/v1/')) {
                return $response;
            }

            $context = [
                'method' => (string) $request->get_method(),
                'route' => $route,
                'status' => $status,
                'user_id' => get_current_user_id() ?: null,
                'rest_code' => $this->extractCode($response),
                'message' => $this->extractMessage($response),
            ];

            // Capture validation errors when present — useful for 422s.
            $validation = $this->extractValidationErrors($response);
            if ($validation !== null) {
                $context['validation'] = $validation;
            }

            $logger = Services::logger();
            $message = sprintf('%s %s → %d', $context['method'], $route, $status);

            match ($this->severity($status)) {
                'error' => $logger->error(LogScopeEnum::API, 'rest_error', $message, $context),
                'warning' => $logger->warning(LogScopeEnum::API, 'rest_error', $message, $context),
                default => $logger->info(LogScopeEnum::API, 'rest_error', $message, $context),
            };
        } catch (\Throwable) {
            // Logging must never affect the response.
        }

        return $response;
    }

    private function severity(int $status): string
    {
        if ($status >= 500) {
            return 'error';
        }
        if ($status === 401 || $status === 403) {
            return 'info';
        }

        return 'warning';
    }

    /**
     * @param  WP_HTTP_Response|WP_REST_Response  $response
     */
    private function extractCode($response): ?string
    {
        $data = $response->get_data();
        if (is_array($data) && isset($data['code']) && is_string($data['code'])) {
            return $data['code'];
        }

        return null;
    }

    /**
     * @param  WP_HTTP_Response|WP_REST_Response  $response
     */
    private function extractMessage($response): ?string
    {
        $data = $response->get_data();
        if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
            // Cap to keep log rows lean.
            return mb_substr($data['message'], 0, 480);
        }

        return null;
    }

    /**
     * @param  WP_HTTP_Response|WP_REST_Response  $response
     * @return array<string, mixed>|null
     */
    private function extractValidationErrors($response): ?array
    {
        $data = $response->get_data();
        if (! is_array($data)) {
            return null;
        }
        if (isset($data['data']['params']) && is_array($data['data']['params'])) {
            return $data['data']['params'];
        }
        if (isset($data['errors']) && is_array($data['errors'])) {
            return $data['errors'];
        }

        return null;
    }
}
