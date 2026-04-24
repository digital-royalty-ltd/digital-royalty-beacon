<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEventEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;

/**
 * Synchronous runner for Laravel tool endpoints used by agent-driven
 * automation invoke() methods.
 *
 * The background `DeferredRequestRunner` handles admin-UI-initiated runs
 * asynchronously across multiple WP cron ticks. Agent-driven automations
 * instead need to return an `InvocationResult` synchronously so the pull
 * queue can report completion back to Laravel. This helper therefore
 * block-polls the tool run up to a configurable timeout, fetching the
 * final artifact when done.
 *
 * Usage:
 *   $result = DispatchedToolRunner::run('tools/content-generator/generate', $payload);
 *   if (! $result['ok']) return InvocationResult::failed($result['error']);
 *   $artifact = $result['artifact']['payload'];
 *   $credits = $result['credits'];
 */
final class DispatchedToolRunner
{
    /** Default per-attempt poll wait. */
    private const POLL_INTERVAL_SECONDS = 3;

    /** Hard cap on total block-poll time. Plugin invoke() already runs on a cron so a minute is fine. */
    private const TOTAL_TIMEOUT_SECONDS = 90;

    /**
     * Dispatch a tool, poll to completion, and fetch the primary output artifact.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   error_code?: string,
     *   run_id?: string,
     *   artifact?: array<string, mixed>,
     *   artifact_id?: string,
     *   adapter_context?: array<string, mixed>,
     *   credits?: int
     * }
     */
    public static function run(
        string $toolPath,
        array $payload,
        int $timeoutSeconds = self::TOTAL_TIMEOUT_SECONDS,
    ): array {
        $logger = Services::logger();
        $client = Services::apiClient();

        // 1. Dispatch — expect 202 with run_id + poll_path.
        $dispatch = $client->dispatchToolRaw($toolPath, $payload);
        if (! $dispatch->ok || $dispatch->code !== 202) {
            return [
                'ok'         => false,
                'error'      => $dispatch->message ?? "Tool dispatch failed ({$dispatch->code})",
                'error_code' => 'dispatch_failed',
            ];
        }

        $runId = is_string($dispatch->data['run_id'] ?? null) ? (string) $dispatch->data['run_id'] : '';
        $pollPath = is_string($dispatch->data['poll_path'] ?? null) ? (string) $dispatch->data['poll_path'] : "tool-runs/{$runId}";
        if ($runId === '') {
            return ['ok' => false, 'error' => 'Tool dispatch returned no run_id', 'error_code' => 'no_run_id'];
        }

        // 2. Poll until completed or timed out.
        $deadline = time() + $timeoutSeconds;
        $lastPoll = null;
        $attempts = 0;
        while (time() < $deadline) {
            $attempts++;
            $poll = $client->pollToolRun($pollPath);
            $lastPoll = $poll;

            // 200 with status=completed → done.
            if ($poll->code === 200 && ($poll->data['status'] ?? null) === 'completed') {
                break;
            }

            // Any other 2xx → completed with unusual shape; treat as done anyway.
            if ($poll->code >= 200 && $poll->code < 300 && ($poll->data['status'] ?? null) === 'completed') {
                break;
            }

            // 422 → failed permanently on Laravel side.
            if ($poll->code === 422 || ($poll->data['status'] ?? null) === 'failed') {
                $err = is_array($poll->data['error'] ?? null) ? $poll->data['error'] : [];
                return [
                    'ok'         => false,
                    'run_id'     => $runId,
                    'error'      => (string) ($err['message'] ?? 'Tool run failed.'),
                    'error_code' => (string) ($err['code'] ?? 'tool_failed'),
                ];
            }

            // Anything else (mainly 202) → not ready yet. Wait and retry.
            $wait = $poll->retryAfterSeconds ?? self::POLL_INTERVAL_SECONDS;
            sleep(min($wait, self::POLL_INTERVAL_SECONDS * 2));
        }

        if (! $lastPoll || $lastPoll->code !== 200 || ($lastPoll->data['status'] ?? null) !== 'completed') {
            $logger->info(
                LogScopeEnum::API,
                LogEventEnum::API_REQUEST_FAILED,
                'Tool run did not complete within timeout.',
                ['run_id' => $runId, 'attempts' => $attempts, 'final_code' => $lastPoll?->code]
            );

            return [
                'ok'         => false,
                'run_id'     => $runId,
                'error'      => 'Tool run did not complete in '.$timeoutSeconds.'s. It may still be processing.',
                'error_code' => 'poll_timeout',
            ];
        }

        // 3. Resolve the output artifact.
        $outputs = is_array($lastPoll->data['outputs'] ?? null) ? $lastPoll->data['outputs'] : [];
        $adapterContext = is_array($lastPoll->data['adapter_context'] ?? null) ? $lastPoll->data['adapter_context'] : [];

        if (empty($outputs) || empty($outputs[0]['artifact_id'])) {
            return [
                'ok'         => false,
                'run_id'     => $runId,
                'error'      => 'Completed run has no output artifact.',
                'error_code' => 'no_output',
            ];
        }

        $artifactId = (string) $outputs[0]['artifact_id'];
        $artifactResponse = $client->getArtifact($artifactId);
        if (! $artifactResponse->ok) {
            return [
                'ok'         => false,
                'run_id'     => $runId,
                'artifact_id' => $artifactId,
                'error'      => 'Could not fetch artifact: '.($artifactResponse->message ?? 'unknown'),
                'error_code' => 'artifact_fetch_failed',
            ];
        }

        $artifact = is_array($artifactResponse->data['artifact'] ?? null) ? $artifactResponse->data['artifact'] : [];

        // Credits: Laravel tool endpoints usually charge via AiUsageTracker at run
        // time. There's no single per-run credit figure currently surfaced, so we
        // return 0 here and let automation-level logic compute a reasonable cost
        // figure (e.g. from tokens or a flat expected rate) if it knows one.
        return [
            'ok'              => true,
            'run_id'          => $runId,
            'artifact'        => $artifact,
            'artifact_id'     => $artifactId,
            'adapter_context' => $adapterContext,
            'credits'         => 0,
        ];
    }
}
