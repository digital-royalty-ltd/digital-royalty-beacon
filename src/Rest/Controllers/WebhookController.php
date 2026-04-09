<?php

namespace DigitalRoyalty\Beacon\Rest\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Receives push notifications from the Beacon dashboard.
 *
 * The dashboard pushes signals (e.g. "job completed") to this endpoint
 * instead of relying on the plugin polling for results.
 */
final class WebhookController
{
    private const WEBHOOK_SECRET_OPTION = 'dr_beacon_webhook_secret';

    public function registerRoutes(): void
    {
        register_rest_route('dr-beacon/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => [$this, 'verifySignature'],
        ]);
    }

    /**
     * Validate the HMAC signature from the dashboard.
     */
    public function verifySignature(WP_REST_Request $request): bool
    {
        $signature = $request->get_header('X-Beacon-Signature');
        $secret = get_option(self::WEBHOOK_SECRET_OPTION);

        if (!$signature || !$secret) {
            Services::logger()->warning(
                LogScopeEnum::WEBHOOK,
                'webhook_signature_missing',
                'Webhook rejected: missing signature or secret.',
                [
                    'has_signature' => (bool) $signature,
                    'has_secret' => (bool) $secret,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]
            );
            return false;
        }

        $expected = hash_hmac('sha256', $request->get_body(), $secret);
        $valid = hash_equals($expected, $signature);

        if (!$valid) {
            Services::logger()->warning(
                LogScopeEnum::WEBHOOK,
                'webhook_signature_invalid',
                'Webhook rejected: HMAC signature mismatch.',
                ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']
            );
        }

        return $valid;
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        $type = (string) ($body['type'] ?? '');
        $jobId = (string) ($body['job_id'] ?? '');
        $toolKey = (string) ($body['tool_key'] ?? '');
        $artifactId = (string) ($body['artifact_id'] ?? '');
        $resultType = (string) ($body['result_type'] ?? '');

        Services::logger()->info(
            LogScopeEnum::WEBHOOK,
            'webhook_received',
            "Incoming webhook: type={$type}, tool={$toolKey}",
            [
                'type' => $type,
                'job_id' => $jobId,
                'tool_key' => $toolKey,
                'artifact_id' => $artifactId,
                'result_type' => $resultType,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]
        );

        if ($type === 'job_completed' && $artifactId !== '') {
            $inlineArtifact = is_array($body['artifact'] ?? null) ? $body['artifact'] : null;
            $this->handleJobCompleted($artifactId, $toolKey, $jobId, $inlineArtifact);
        } else {
            Services::logger()->info(
                LogScopeEnum::WEBHOOK,
                'webhook_ignored',
                "Webhook type '{$type}' not handled or missing artifact_id.",
                ['type' => $type, 'has_artifact' => $artifactId !== '']
            );
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * @param array<string,mixed>|null $inlineArtifact Artifact data included in the webhook payload
     */
    private function handleJobCompleted(string $artifactId, string $toolKey, string $jobId, ?array $inlineArtifact = null): void
    {
        $logger = Services::logger();

        try {
            // Use inline artifact from webhook payload if available,
            // otherwise fall back to fetching via API.
            if (is_array($inlineArtifact) && !empty($inlineArtifact['payload'])) {
                $artifact = $inlineArtifact;
                $payload = (array) $inlineArtifact['payload'];
                $artifactType = (string) ($inlineArtifact['type'] ?? '');

                $logger->info(
                    LogScopeEnum::WEBHOOK,
                    'webhook_artifact_inline',
                    "Using inline artifact from webhook payload: type={$artifactType}",
                    [
                        'artifact_id' => $artifactId,
                        'artifact_type' => $artifactType,
                        'has_title' => isset($payload['title']),
                        'has_content' => isset($payload['content_html']),
                    ]
                );
            } else {
                $logger->info(
                    LogScopeEnum::WEBHOOK,
                    'webhook_artifact_fetch_start',
                    "Fetching artifact {$artifactId} for job {$jobId}.",
                    ['artifact_id' => $artifactId, 'job_id' => $jobId]
                );

                $response = Services::apiClient()->getArtifact($artifactId);

                if (!$response->ok) {
                    $logger->warning(
                        LogScopeEnum::WEBHOOK,
                        'webhook_artifact_fetch_failed',
                        "Failed to fetch artifact {$artifactId}: {$response->message}",
                        [
                            'artifact_id' => $artifactId,
                            'job_id' => $jobId,
                            'status_code' => $response->code,
                            'message' => $response->message,
                        ]
                    );
                    return;
                }

                $artifact = is_array($response->data['artifact'] ?? null) ? $response->data['artifact'] : [];
                $payload = is_array($artifact['payload'] ?? null) ? $artifact['payload'] : [];
                $artifactType = (string) ($artifact['type'] ?? '');

                $logger->info(
                    LogScopeEnum::WEBHOOK,
                    'webhook_artifact_fetched',
                    "Artifact fetched via API: type={$artifactType}",
                    [
                        'artifact_id' => $artifactId,
                        'artifact_type' => $artifactType,
                        'has_title' => isset($payload['title']),
                        'has_content' => isset($payload['content_html']),
                    ]
                );
            }

            // Only create drafts for content-producing artifacts
            if (!in_array($artifactType, ['article', 'draft_content'], true)) {
                $logger->info(
                    LogScopeEnum::WEBHOOK,
                    'webhook_artifact_skipped',
                    "Artifact type '{$artifactType}' does not produce a draft, skipping.",
                    ['artifact_type' => $artifactType]
                );
                return;
            }

            $title = isset($payload['title']) && is_string($payload['title']) && trim($payload['title']) !== ''
                ? trim($payload['title'])
                : 'Generated Draft';

            $content = isset($payload['content_html']) && is_string($payload['content_html'])
                ? $payload['content_html']
                : '';

            if ($content === '') {
                $logger->warning(
                    LogScopeEnum::WEBHOOK,
                    'webhook_artifact_empty',
                    "Artifact {$artifactId} has no content_html, skipping draft creation.",
                    ['artifact_id' => $artifactId]
                );
                return;
            }

            $postId = wp_insert_post([
                'post_type' => 'post',
                'post_status' => 'draft',
                'post_title' => $title,
                'post_content' => $content,
                'meta_input' => [
                    '_beacon_artifact_id' => $artifactId,
                    '_beacon_job_id' => $jobId,
                    '_beacon_tool_key' => $toolKey,
                    '_beacon_source' => 'webhook',
                ],
            ], true);

            if ($postId instanceof WP_Error) {
                $logger->warning(
                    LogScopeEnum::WEBHOOK,
                    'webhook_draft_failed',
                    "Failed to create draft: {$postId->get_error_message()}",
                    [
                        'artifact_id' => $artifactId,
                        'job_id' => $jobId,
                        'wp_error' => $postId->get_error_message(),
                    ]
                );
                return;
            }

            $logger->info(
                LogScopeEnum::WEBHOOK,
                'webhook_draft_created',
                "Draft post #{$postId} created from webhook (tool: {$toolKey}).",
                [
                    'post_id' => (int) $postId,
                    'artifact_id' => $artifactId,
                    'job_id' => $jobId,
                    'tool_key' => $toolKey,
                    'title' => $title,
                ]
            );
        } catch (\Throwable $e) {
            $logger->error(
                LogScopeEnum::WEBHOOK,
                'webhook_handler_exception',
                "Webhook handler failed: {$e->getMessage()}",
                [
                    'artifact_id' => $artifactId,
                    'job_id' => $jobId,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]
            );
        }
    }
}
