<?php

namespace DigitalRoyalty\Beacon\Systems\Api;

final class ApiClient
{
    private string $baseUrl;
    private ?string $apiKey;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey ? trim($apiKey) : null;

        $this->baseUrl = $baseUrl
            ? rtrim($baseUrl, '/')
            : $this->resolveBaseUrl();
    }

    /**
     * Allow setting/changing the API key after construction (optional).
     */
    public function withApiKey(?string $apiKey): self
    {
        $clone = clone $this;
        $clone->apiKey = $apiKey ? trim($apiKey) : null;

        return $clone;
    }

    /**
     * Verify API key against Beacon backend.
     * Payload is only client meta (auto included).
     */
    public function verifyApiKey(): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'verify-api-key',
            payload: []
        );
    }

    /**
     * Submit a report envelope to the backend.
     */
    public function submitReports(array $envelope): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'reports/submit',
            payload: $envelope
        );
    }

    /**
     * Standard JSON request wrapper for Beacon API.
     */
    private function request(
        string $method,
        string $path,
        array $payload = [],
        bool $includeClientMeta = true,
        bool $requireAuth = true
    ): ApiResponse {
        $url = $this->endpoint($path);

        if ($requireAuth && !$this->apiKey) {
            return new ApiResponse(
                ok: false,
                code: 401,
                message: 'Missing Beacon API key.',
                data: []
            );
        }

        if ($includeClientMeta) {
            $payload = $this->withClientMeta($payload);
        }

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent'   => $this->userAgent(),
        ];

        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $args = [
            'timeout'     => 15,
            'redirection' => 3,
            'method'      => strtoupper($method),
            'headers'     => $headers,
            'body'        => wp_json_encode($payload),
        ];

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

    /**
     * Merge standard client meta into payload.
     * Meta overwrites any spoofed values.
     */
    private function withClientMeta(array $payload): array
    {
        return array_merge($payload, [
            'site_url'       => home_url('/'),
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => PHP_VERSION,
            'plugin_version' => defined('DR_BEACON_VERSION') ? DR_BEACON_VERSION : null,
        ]);
    }

    private function userAgent(): string
    {
        return 'DigitalRoyaltyBeacon/' . (defined('DR_BEACON_VERSION') ? DR_BEACON_VERSION : 'dev');
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