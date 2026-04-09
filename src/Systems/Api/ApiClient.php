<?php

namespace DigitalRoyalty\Beacon\Systems\Api;

use DigitalRoyalty\Beacon\Repositories\DeferredRequestsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Deferred\DeferredRequestKeyEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEventEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Deferred\DeferredRequestRunner;

/**
 * Beacon API client for WordPress plugin.
 *
 * Responsibilities:
 * - Perform authenticated JSON requests to the Beacon backend.
 * - Handle 202 Accepted responses by enqueuing a deferred poll job.
 * - Provide helper endpoints used by deferred completion handlers (e.g. fetch artifact).
 *
 * Important behaviour:
 * - For deferred jobs, we store a stable request key (enum) rather than a URL-derived key.
 * - Poll paths are stored as relative paths under /beacon/{version}/..., even if the API returns an absolute Location URL.
 */
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
     * Allow setting or changing the API key after construction (optional).
     */
    public function withApiKey(?string $apiKey): self
    {
        $clone = clone $this;
        $clone->apiKey = $apiKey ? trim($apiKey) : null;

        return $clone;
    }

    /**
     * Verify API key against Beacon backend.
     *
     * Payload is only client meta (auto included by request()).
     */
    public function verifyApiKey(): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'verify-api-key',
            payload: [],
            includeClientMeta: true,
            requireAuth: true
        );
    }

    /**
     * Send a heartbeat/lifecycle signal to the backend.
     *
     * @param array{status: string, plugin_version?: string, wp_version?: string, php_version?: string, site_url?: string, webhook_url?: string, webhook_secret?: string} $payload
     */
    public function heartbeat(array $payload): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'heartbeat',
            payload: $payload,
            includeClientMeta: false,
            requireAuth: true
        );
    }

    /**
     * Submit a report envelope to the backend.
     *
     * @param array<string,mixed> $envelope
     */
    public function submitReports(array $envelope): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'reports/submit',
            payload: $envelope,
            includeClientMeta: true,
            requireAuth: true
        );
    }

    /**
     * Request the dashboard to generate content for a draft post.
     *
     * This endpoint can return 202, in which case the request is enqueued
     * for polling and completion via the deferred system.
     *
     * @param array<string,mixed> $payload
     */
    public function generateContentDraft(array $payload): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'tools/content-generator/generate',
            payload: $payload,
            includeClientMeta: true,
            requireAuth: true,
            requestKey: DeferredRequestKeyEnum::CONTENT_GENERATOR_GENERATE
        );
    }

    /**
     * Request content generation from a sample URL or page content.
     *
     * Triggers a two-step AI chain: sample analysis → content rewrite.
     * Returns 202 if accepted for async processing.
     *
     * @param array<string,mixed> $payload
     */
    public function contentFromSample(array $payload): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'tools/content-from-sample',
            payload: $payload,
            includeClientMeta: true,
            requireAuth: true,
            requestKey: DeferredRequestKeyEnum::CONTENT_FROM_SAMPLE
        );
    }

    /**
     * Transient content areas analysis helper.
     *
     * Sends the full site sitemap (pages tree + collections) to the Beacon
     * endpoint and returns AI-identified content areas with intent and topics.
     * Nothing is persisted on the Beacon side.
     *
     * On success: $response->ok === true, $response->data['content_areas'] is array[].
     * On failure: $response->ok === false; caller leaves content_areas empty.
     *
     * @param array<string, mixed> $sitemap
     */
    public function analyseContentAreas(array $sitemap): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'tools/analyse-content-areas',
            payload: ['sitemap' => $sitemap],
            includeClientMeta: true,
            requireAuth: true
        );
    }

    /**
     * Transient key pages identification helper.
     *
     * Sends a flat list of slug/title pairs to the Beacon endpoint and returns
     * the slugs the AI considers most important for understanding the site.
     * Nothing is persisted on the Beacon side.
     *
     * On success: $response->ok === true, $response->data['identifiers'] is string[].
     * On failure: $response->ok === false; caller should fall back to first N stubs.
     *
     * @param array<int, array{identifier: string, title: string}> $pages
     */
    public function identifyKeyPages(array $pages, int $max = 6): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'tools/identify-key-pages',
            payload: ['pages' => $pages, 'max' => $max],
            includeClientMeta: true,
            requireAuth: true
        );
    }

    /**
     * Transient site profile analysis helper.
     *
     * Sends content samples to the Beacon analysis endpoint and returns the
     * AI-derived profile synchronously (200 OK). Nothing is persisted on the
     * Beacon side; the result is merged into the local report payload by the caller.
     *
     * On success: $response->ok === true, $response->data['analysis'] is the array.
     * On failure: $response->ok === false with an error message.
     *
     * @param array<string, mixed> $samples
     */
    public function analyseSiteProfile(array $samples): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'tools/analyse-site-profile',
            payload: ['samples' => $samples],
            includeClientMeta: true,
            requireAuth: true
        );
    }

    /**
     * Initiate an OAuth connection for a third-party provider.
     *
     * Laravel returns the full OAuth redirect URL for the given provider.
     * The plugin redirects the user to that URL; Laravel owns the client credentials.
     *
     * On success: $response->data['url'] contains the OAuth redirect URL.
     */
    public function initiateOAuth(string $provider, string $callbackUrl, string $state): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'oauth/initiate',
            payload: [
                'provider'     => $provider,
                'callback_url' => $callbackUrl,
                'state'        => $state,
            ],
            includeClientMeta: true,
            requireAuth: true
        );
    }

    /**
     * Complete an OAuth flow by forwarding the provider's callback code to Laravel.
     *
     * Laravel exchanges the code for tokens and stores them against the project.
     */
    public function completeOAuth(string $provider, string $code, string $state, string $callbackUrl): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'oauth/callback',
            payload: [
                'provider'     => $provider,
                'code'         => $code,
                'state'        => $state,
                'callback_url' => $callbackUrl,
            ],
            includeClientMeta: true,
            requireAuth: true
        );
    }

    /**
     * Disconnect a previously connected OAuth provider.
     *
     * Laravel revokes and deletes the stored tokens for this provider and project.
     */
    public function disconnectOAuth(string $provider): ApiResponse
    {
        return $this->request(
            method: 'DELETE',
            path: 'oauth/' . rawurlencode($provider),
            payload: [],
            includeClientMeta: true,
            requireAuth: true
        );
    }

    /**
     * Run a Content Gap Analysis.
     *
     * Sends the site profile, content areas, and sitemap to the Beacon API.
     * Laravel dispatches a queue job and returns 202; the deferred system
     * polls and routes completion to GapAnalysisResultHandler.
     *
     * @param array<string,mixed> $payload
     */
    public function runGapAnalysis(array $payload): ApiResponse
    {
        return $this->request(
            method: 'POST',
            path: 'tools/gap-analysis/run',
            payload: $payload,
            includeClientMeta: true,
            requireAuth: true,
            requestKey: DeferredRequestKeyEnum::GAP_ANALYSIS
        );
    }

    /**
     * Poll a previously deferred request.
     *
     * $pollPath should be stored as a relative path under /beacon/{version}/...
     * For safety we normalise any leading slashes here.
     */
    public function pollDeferred(string $pollPath): ApiResponse
    {
        return $this->request(
            method: 'GET',
            path: ltrim($pollPath, '/'),
            payload: [],
            includeClientMeta: true,
            requireAuth: true
        );
    }

    /**
     * Fetch an artifact by ID (used by deferred completion handlers).
     */
    public function getArtifact(string $artifactId): ApiResponse
    {
        return $this->request(
            method: 'GET',
            path: 'artifacts/' . rawurlencode($artifactId),
            payload: [],
            includeClientMeta: true,
            requireAuth: true
        );
    }

    /**
     * Standard JSON request wrapper for Beacon API.
     *
     * - Adds client meta if requested.
     * - Adds Authorization header if required.
     * - Handles 202 Accepted by enqueueing a row in deferred_requests.
     *
     * @param array<string,mixed> $payload
     */
    /**
     * @param array<string,mixed> $payload
     */
    private function request(
        string $method,
        string $path,
        array $payload = [],
        bool $includeClientMeta = true,
        bool $requireAuth = true,
        ?string $requestKey = null
    ): ApiResponse {
        $logger = Services::logger();

        $methodUpper = strtoupper($method);
        $url = $this->endpoint($path);

        $logContext = [
            'method' => $methodUpper,
            'path' => ltrim($path, '/'),
            'url' => $url,
            'request_key' => $requestKey,
            'require_auth' => $requireAuth,
            'has_api_key' => (bool) $this->apiKey,
        ];

        if ($requireAuth && !$this->apiKey) {
            $logger->info(LogScopeEnum::API, LogEventEnum::API_AUTH_MISSING, 'API request blocked, missing API key.', $logContext);

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
            'method'      => $methodUpper,
            'headers'     => $headers,
        ];

        if ($methodUpper !== 'GET') {
            $args['body'] = wp_json_encode($payload);
        }

        // Do not log payload values, only shape.
        $payloadShape = [
            'payload_keys' => array_values(array_map('strval', array_keys($payload))),
            'payload_bytes' => $methodUpper !== 'GET' && isset($args['body']) && is_string($args['body']) ? strlen($args['body']) : 0,
        ];

        $logger->info(
            LogScopeEnum::API,
            LogEventEnum::API_REQUEST_START,
            'API request started.',
            array_merge($logContext, $payloadShape)
        );

        $t0 = microtime(true);

        $response = wp_remote_request($url, $args);

        $durationMs = (int) round((microtime(true) - $t0) * 1000);

        if (is_wp_error($response)) {
            $logger->info(
                LogScopeEnum::API,
                LogEventEnum::API_REQUEST_WP_ERROR,
                'API request failed (WP_Error).',
                array_merge($logContext, [
                    'duration_ms' => $durationMs,
                    'wp_error_code' => $response->get_error_code(),
                    'wp_error_message' => $response->get_error_message(),
                ])
            );

            return new ApiResponse(
                ok: false,
                code: 0,
                message: $response->get_error_message(),
                data: []
            );
        }

        $respHeaders = wp_remote_retrieve_headers($response);
        $retryAfterSeconds = $this->parseRetryAfterSeconds($respHeaders);
        $location = $this->parseLocation($respHeaders);

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        $logger->info(
            LogScopeEnum::API,
            ($code >= 200 && $code < 300) ? LogEventEnum::API_REQUEST_OK : LogEventEnum::API_REQUEST_HTTP_ERROR,
            'API response received.',
            array_merge($logContext, [
                'duration_ms' => $durationMs,
                'status_code' => $code,
                'retry_after_seconds' => $retryAfterSeconds,
                'has_location' => (bool) $location,
                'body_bytes' => strlen($body),
            ])
        );

        $json = $body !== '' ? json_decode($body, true) : [];
        if ($body !== '' && !is_array($json)) {
            $logger->info(
                LogScopeEnum::API,
                LogEventEnum::API_RESPONSE_INVALID_JSON,
                'API response invalid JSON.',
                array_merge($logContext, [
                    'duration_ms' => $durationMs,
                    'status_code' => $code,
                    'body_prefix' => substr($body, 0, 200),
                ])
            );

            return new ApiResponse(
                ok: false,
                code: $code,
                message: 'Invalid response from Beacon API.',
                data: []
            );
        }

        /** @var array<string,mixed> $data */
        $data = is_array($json) ? $json : [];

        // Auth failures
        if ($code === 401 || $code === 403) {
            $logger->info(
                LogScopeEnum::API,
                LogEventEnum::API_REQUEST_UNAUTHORIZED,
                'API request unauthorized.',
                array_merge($logContext, [
                    'duration_ms' => $durationMs,
                    'status_code' => $code,
                    'message' => isset($data['message']) && is_string($data['message']) ? $data['message'] : null,
                ])
            );

            return new ApiResponse(
                ok: false,
                code: $code,
                message: $data['message'] ?? 'Unauthorized.',
                data: $data,
                retryAfterSeconds: $retryAfterSeconds,
                location: $location
            );
        }

        // Deferred job
        if ($code === 202) {
            $logger->info(
                LogScopeEnum::API,
                LogEventEnum::API_REQUEST_DEFERRED,
                'API request accepted as deferred (202).',
                array_merge($logContext, [
                    'duration_ms' => $durationMs,
                    'retry_after_seconds' => $retryAfterSeconds,
                    'location' => $location,
                    'has_request_key' => (bool) $requestKey,
                    'run_id' => isset($data['run_id']) && is_string($data['run_id']) ? $data['run_id'] : null,
                ])
            );

            if (!$requestKey) {
                $logger->info(
                    LogScopeEnum::API,
                    LogEventEnum::API_REQUEST_FAILED,
                    'Deferred request key missing for 202 response.',
                    $logContext
                );

                return new ApiResponse(
                    ok: false,
                    code: 202,
                    message: 'Deferred request key missing.',
                    data: $data,
                    retryAfterSeconds: $retryAfterSeconds,
                    location: $location
                );
            }

            $delay = $retryAfterSeconds ?? 15;

            $externalId = isset($data['run_id']) && is_string($data['run_id']) && trim($data['run_id']) !== ''
                ? trim($data['run_id'])
                : null;

            $pollPath = $this->normalisePollPath($location);

            if (!$pollPath && isset($data['poll_path']) && is_string($data['poll_path'])) {
                $pollPath = $this->normalisePollPath($data['poll_path']);
            }

            if (!$pollPath) {
                $logger->info(
                    LogScopeEnum::API,
                    LogEventEnum::API_REQUEST_FAILED,
                    'API returned 202 but no Location or poll_path.',
                    array_merge($logContext, [
                        'retry_after_seconds' => $retryAfterSeconds,
                        'location' => $location,
                        'has_poll_path_body' => isset($data['poll_path']),
                    ])
                );

                return new ApiResponse(
                    ok: false,
                    code: 202,
                    message: 'Beacon API returned 202 but did not provide Location or poll_path.',
                    data: $data,
                    retryAfterSeconds: $retryAfterSeconds,
                    location: $location
                );
            }

            global $wpdb;
            $repo = new DeferredRequestsRepository($wpdb);

            try {
                $deferredId = $repo->enqueue(
                    requestKey: $requestKey,
                    pollPath: $pollPath,
                    externalId: $externalId,
                    delaySeconds: $delay,
                    payload: $payload
                );

                if ($deferredId <= 0) {
                    Services::logger()->info(
                        LogScopeEnum::API,
                        LogEventEnum::API_DEFERRED_ENQUEUE_FAILED,
                        'Deferred enqueue returned invalid insert id.',
                        [
                            'request_key' => $requestKey,
                            'poll_path' => $pollPath,
                            'external_id' => $externalId,
                            'delay_seconds' => $delay,
                        ]
                    );

                    return new ApiResponse(
                        ok: false,
                        code: 500,
                        message: 'Deferred request could not be persisted.',
                        data: $data,
                        retryAfterSeconds: $retryAfterSeconds,
                        location: $location
                    );
                }

                $logger->info(
                    LogScopeEnum::API,
                    LogEventEnum::API_DEFERRED_ENQUEUE_OK,
                    'Deferred request enqueued.',
                    array_merge($logContext, [
                        'deferred_request_id' => $deferredId,
                        'poll_path' => $pollPath,
                        'external_id' => $externalId,
                        'delay_seconds' => $delay,
                    ])
                );
            } catch (\Throwable $e) {
                $logger->info(
                    LogScopeEnum::API,
                    LogEventEnum::API_DEFERRED_ENQUEUE_FAILED,
                    'Deferred enqueue failed.',
                    array_merge($logContext, [
                        'poll_path' => $pollPath,
                        'external_id' => $externalId,
                        'delay_seconds' => $delay,
                        'exception' => get_class($e),
                        'exception_message' => $e->getMessage(),
                    ])
                );

                return new ApiResponse(
                    ok: false,
                    code: 202,
                    message: 'Deferred enqueue failed.',
                    data: $data,
                    retryAfterSeconds: $retryAfterSeconds,
                    location: $location
                );
            }

            if (!wp_next_scheduled(DeferredRequestRunner::CRON_HOOK)) {
                wp_schedule_single_event(time() + 60, DeferredRequestRunner::CRON_HOOK);

                $logger->info(
                    LogScopeEnum::SYSTEM,
                    LogEventEnum::DEFERRED_RUN_SCHEDULED,
                    'Deferred runner scheduled.',
                    [
                        'hook' => DeferredRequestRunner::CRON_HOOK,
                        'next' => wp_next_scheduled(DeferredRequestRunner::CRON_HOOK),
                    ]
                );
            } else {
                $logger->info(
                    LogScopeEnum::SYSTEM,
                    LogEventEnum::DEFERRED_RUN_ALREADY_SCHEDULED,
                    'Deferred runner already scheduled.',
                    [
                        'hook' => DeferredRequestRunner::CRON_HOOK,
                        'next' => wp_next_scheduled(DeferredRequestRunner::CRON_HOOK),
                    ]
                );
            }

            return new ApiResponse(
                ok: (bool) ($data['ok'] ?? true),
                code: 202,
                message: $data['message'] ?? null,
                data: $data,
                retryAfterSeconds: $retryAfterSeconds,
                location: $location,
                deferredRequestId: $deferredId
            );
        }

        if ($code < 200 || $code >= 300) {
            $logger->info(
                LogScopeEnum::API,
                LogEventEnum::API_REQUEST_HTTP_ERROR,
                'API returned non-2xx.',
                array_merge($logContext, [
                    'duration_ms' => $durationMs,
                    'status_code' => $code,
                    'message' => isset($data['message']) && is_string($data['message']) ? $data['message'] : null,
                ])
            );

            return new ApiResponse(
                ok: false,
                code: $code,
                message: $data['message'] ?? ('Beacon API error (' . $code . ').'),
                data: $data,
                retryAfterSeconds: $retryAfterSeconds,
                location: $location
            );
        }

        $logger->info(
            LogScopeEnum::API,
            LogEventEnum::API_REQUEST_OK,
            'API request completed successfully.',
            array_merge($logContext, [
                'duration_ms' => $durationMs,
                'status_code' => $code,
            ])
        );

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
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
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

    /**
     * Build a Beacon API endpoint URL from a relative path.
     */
    private function endpoint(string $path): string
    {
        $prefix = '/beacon/' . DR_BEACON_API_VERSION;

        return $this->baseUrl . $prefix . '/' . ltrim($path, '/');
    }

    /**
     * Resolve the API base URL (filterable).
     */
    private function resolveBaseUrl(): string
    {
        $url = DR_BEACON_API_BASE;

        return rtrim(
            apply_filters('dr_beacon_api_base_url', $url),
            '/'
        );
    }

    /**
     * Parse Retry-After header into seconds (supports seconds or HTTP date).
     *
     * @param mixed $headers
     */
    private function parseRetryAfterSeconds($headers): ?int
    {
        if (!is_array($headers)) {
            return null;
        }

        $raw = $headers['retry-after'] ?? $headers['Retry-After'] ?? null;

        if ($raw === null) {
            return null;
        }

        // WP_HTTP_Headers may return array values
        if (is_array($raw)) {
            $raw = (string) reset($raw);
        }

        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        // seconds format
        if (ctype_digit($raw)) {
            return max(0, (int) $raw);
        }

        // HTTP date format
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        $delta = $ts - time();
        return $delta > 0 ? $delta : 0;
    }

    /**
     * Extract Location header (case-insensitive).
     *
     * @param mixed $headers
     */
    private function parseLocation($headers): ?string
    {
        if (!is_array($headers)) {
            return null;
        }

        $raw = $headers['location'] ?? $headers['Location'] ?? null;

        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            $raw = (string) reset($raw);
        }

        $raw = trim((string) $raw);
        return $raw !== '' ? $raw : null;
    }

    /**
     * Convert an absolute Location URL or relative path into a stored relative poll path.
     *
     * We store poll_path relative to "/beacon/{version}/" so that pollDeferred()
     * can safely call endpoint() without duplicating domains or prefixes.
     */
    private function normalisePollPath(?string $locationOrPath): ?string
    {
        if (!$locationOrPath) {
            return null;
        }

        $s = trim($locationOrPath);
        if ($s === '') {
            return null;
        }

        // Already a relative path
        if (!str_starts_with($s, 'http://') && !str_starts_with($s, 'https://')) {
            return ltrim($s, '/');
        }

        $parts = wp_parse_url($s);
        if (!is_array($parts) || empty($parts['path'])) {
            return null;
        }

        $path = (string) $parts['path'];

        // Strip everything up to and including "/beacon/{version}/"
        $prefix = '/beacon/' . DR_BEACON_API_VERSION . '/';
        $pos = strpos($path, $prefix);

        if ($pos !== false) {
            $path = substr($path, $pos + strlen($prefix));
        }

        return ltrim($path, '/');
    }
}