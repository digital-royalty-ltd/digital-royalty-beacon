<?php

namespace DigitalRoyalty\Beacon\Services;

final class ApiClient
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = $baseUrl
            ? rtrim($baseUrl, '/')
            : $this->resolveBaseUrl();
    }

    /**
     * Verify API key against Beacon backend.
     */
    public function verifyApiKey(string $apiKey): ApiResponse
    {
        return $this->request(
            'POST',
            'verify-api-key',
            $apiKey,
            [
                'site_url'       => home_url('/'),
                'wp_version'     => get_bloginfo('version'),
                'php_version'    => PHP_VERSION,
                'plugin_version' => defined('DR_BEACON_VERSION') ? DR_BEACON_VERSION : null,
            ]
        );
    }

    /**
     * Standard JSON request wrapper for Beacon API.
     */
    private function request(
        string $method,
        string $path,
        ?string $apiKey = null,
        array $payload = []
    ): ApiResponse {
        $url = $this->endpoint($path);

        $args = [
            'timeout'     => 15,
            'redirection' => 3,
            'method'      => strtoupper($method),
            'headers'     => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent'   => 'DigitalRoyaltyBeacon/' . (defined('DR_BEACON_VERSION') ? DR_BEACON_VERSION : 'dev'),
            ],
        ];

        if ($apiKey) {
            $args['headers']['Authorization'] = 'Bearer ' . $apiKey;
        }

        if (!empty($payload)) {
            $args['body'] = wp_json_encode($payload);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return new ApiResponse(
                ok: false,
                code: 0,
                message: $response->get_error_message(),
                data: []
            );
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        $json = $body !== '' ? json_decode($body, true) : [];

        if ($body !== '' && !is_array($json)) {
            return new ApiResponse(
                ok: false,
                code: $code,
                message: 'Invalid response from Beacon API.',
                data: []
            );
        }

        $data = is_array($json) ? $json : [];

        if ($code === 401 || $code === 403) {
            return new ApiResponse(
                ok: false,
                code: $code,
                message: $data['message'] ?? 'Unauthorized.',
                data: $data
            );
        }

        if ($code < 200 || $code >= 300) {
            return new ApiResponse(
                ok: false,
                code: $code,
                message: $data['message'] ?? ('Beacon API error (' . $code . ').'),
                data: $data
            );
        }

        return new ApiResponse(
            ok: (bool) ($data['ok'] ?? true),
            code: $code,
            message: $data['message'] ?? null,
            data: $data
        );
    }

    private function endpoint(string $path): string
    {
        $prefix = '/beacon/' . DR_BEACON_API_VERSION;

        return $this->baseUrl . $prefix . '/' . ltrim($path, '/');
    }

    private function resolveBaseUrl(): string
    {
        $url = DR_BEACON_API_BASE;

        return rtrim(
            apply_filters('dr_beacon_api_base_url', $url),
            '/'
        );
    }
}