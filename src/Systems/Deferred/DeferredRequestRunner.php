<?php

namespace DigitalRoyalty\Beacon\Systems\Deferred;

use DigitalRoyalty\Beacon\Repositories\DeferredRequestsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEventEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use Throwable;

final class DeferredRequestRunner
{
    public const CRON_HOOK = 'dr_beacon_run_deferred_requests';

    private const MAX_ATTEMPTS = 20;
    private const BATCH_SIZE = 20;

    public function __construct(
        private readonly DeferredRequestsRepository $repo,
        private readonly DeferredCompletionRouter $router
    ) {}

    public function register(): void
    {
        // Important: this must be called on all request types (admin, frontend, cron, REST)
        add_action(self::CRON_HOOK, [$this, 'run']);
    }

    public function run(): void
    {
        $logger = Services::logger();

        $logger->info(
            LogScopeEnum::SYSTEM,
            LogEventEnum::DEFERRED_RUN_START,
            'Deferred runner fired.',
            [
                'hook' => self::CRON_HOOK,
                'now_utc' => gmdate('Y-m-d H:i:s') . ' UTC',
                'doing_cron' => (defined('DOING_CRON') && DOING_CRON) ? 'yes' : 'no',
            ]
        );

        try {
            $rows = $this->repo->due(self::BATCH_SIZE);
        } catch (Throwable $e) {
            $logger->info(
                LogScopeEnum::SYSTEM,
                LogEventEnum::DEFERRED_RUN_ERROR,
                'Deferred runner failed while fetching due rows.',
                [
                    'exception' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]
            );

            $this->scheduleNextIfNeeded(forceSoon: true);
            return;
        }

        $logger->info(
            LogScopeEnum::SYSTEM,
            LogEventEnum::DEFERRED_RUN_DUE_COUNT,
            'Deferred due rows fetched.',
            [
                'count' => is_array($rows) ? count($rows) : 0,
            ]
        );

        if (!is_array($rows) || $rows === []) {
            $this->scheduleNextIfNeeded();

            $logger->info(
                LogScopeEnum::SYSTEM,
                LogEventEnum::DEFERRED_RUN_END,
                'Deferred runner finished (no due rows).',
                ['hook' => self::CRON_HOOK]
            );
            return;
        }

        foreach ($rows as $row) {
            try {
                $this->processRow($row);
            } catch (Throwable $e) {
                $id = (int) ($row['id'] ?? 0);

                $logger->info(
                    LogScopeEnum::SYSTEM,
                    LogEventEnum::DEFERRED_ROW_EXCEPTION,
                    'Deferred runner hit exception while processing row.',
                    [
                        'deferred_id' => $id,
                        'exception' => get_class($e),
                        'exception_message' => $e->getMessage(),
                    ]
                );

                if ($id > 0) {
                    $this->repo->reschedule($id, 60, 'Exception: ' . $e->getMessage());
                }
            }
        }

        $this->scheduleNextIfNeeded();

        $logger->info(
            LogScopeEnum::SYSTEM,
            LogEventEnum::DEFERRED_RUN_END,
            'Deferred runner finished.',
            ['hook' => self::CRON_HOOK]
        );
    }

    /**
     * @param array<string,mixed> $row
     */
    private function processRow(array $row): void
    {
        $logger = Services::logger();

        $id = (int) ($row['id'] ?? 0);
        $pollPath = (string) ($row['poll_path'] ?? '');
        $attempts = (int) ($row['attempts'] ?? 0);
        $requestKey = isset($row['request_key']) ? (string) $row['request_key'] : null;

        if ($id <= 0 || $pollPath === '') {
            return;
        }

        $logger->info(
            LogScopeEnum::SYSTEM,
            LogEventEnum::DEFERRED_ROW_PROCESS_START,
            'Processing deferred row.',
            [
                'deferred_id' => $id,
                'request_key' => $requestKey,
                'poll_path' => $pollPath,
                'attempts' => $attempts,
            ]
        );

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->repo->markFailed($id, 'Max attempts reached.');

            $logger->info(
                LogScopeEnum::SYSTEM,
                LogEventEnum::DEFERRED_ROW_FAILED,
                'Deferred row failed (max attempts).',
                ['deferred_id' => $id]
            );
            return;
        }

        $response = Services::apiClient()->pollDeferred($pollPath);

        // Still processing
        if ($response->code === 202) {
            $delay = $response->retryAfterSeconds ?? 15;
            $this->repo->reschedule($id, $delay, null);

            $logger->info(
                LogScopeEnum::SYSTEM,
                LogEventEnum::DEFERRED_ROW_RESCHEDULED,
                'Deferred row still processing, rescheduled.',
                [
                    'deferred_id' => $id,
                    'delay_seconds' => $delay,
                ]
            );
            return;
        }

        // Error polling
        if (!$response->ok) {
            $delay = $response->retryAfterSeconds ?? 30;
            $this->repo->reschedule($id, $delay, $response->message ?? 'Deferred poll failed.');

            $logger->info(
                LogScopeEnum::SYSTEM,
                LogEventEnum::DEFERRED_ROW_RESCHEDULED,
                'Deferred row poll failed, rescheduled.',
                [
                    'deferred_id' => $id,
                    'delay_seconds' => $delay,
                    'message' => $response->message,
                    'status_code' => $response->code,
                ]
            );
            return;
        }

        // Ready (200)
        $handler = $this->router->resolve($requestKey);

        if (!$handler) {
            $this->repo->markFailed(
                $id,
                'No completion handler registered for: ' . (string) ($requestKey ?? '(missing)')
            );

            $logger->info(
                LogScopeEnum::SYSTEM,
                LogEventEnum::DEFERRED_ROW_FAILED,
                'Deferred row failed (no handler).',
                [
                    'deferred_id' => $id,
                    'request_key' => $requestKey,
                ]
            );
            return;
        }

        $result = $handler->handle($row, $response->data);

        if (!($result['ok'] ?? false)) {
            $msg = isset($result['message']) && is_string($result['message']) && trim($result['message']) !== ''
                ? $result['message']
                : 'Completion handler failed.';

            $this->repo->markFailed($id, $msg);

            $logger->info(
                LogScopeEnum::SYSTEM,
                LogEventEnum::DEFERRED_ROW_FAILED,
                'Deferred row failed (handler failed).',
                [
                    'deferred_id' => $id,
                    'message' => $msg,
                ]
            );
            return;
        }

        $meta = isset($result['meta']) && is_array($result['meta']) ? $result['meta'] : [];

        $completedResult = [
            'poll' => $response->data,
            'meta' => $meta,
        ];

        $this->repo->markCompleted($id, $completedResult);

        $logger->info(
            LogScopeEnum::SYSTEM,
            LogEventEnum::DEFERRED_ROW_COMPLETED,
            'Deferred row completed.',
            [
                'deferred_id' => $id,
                'request_key' => $requestKey,
            ]
        );
    }

    private function scheduleNextIfNeeded(bool $forceSoon = false): void
    {
        $logger = Services::logger();

        // Prefer the queue's earliest next_attempt time if the repository supports it.
        $nextDue = null;
        if (!$forceSoon && method_exists($this->repo, 'nextPendingAttemptTimestampUtc')) {
            try {
                /** @var int|null $nextDue */
                $nextDue = $this->repo->nextPendingAttemptTimestampUtc();
            } catch (Throwable $e) {
                $nextDue = null;
            }
        }

        // Default: wake up soon. If there's a known due time, use it (but not in the past).
        $target = time() + 60;

        if ($nextDue !== null) {
            $target = max(time() + 5, (int) $nextDue);
        }

        if ($forceSoon) {
            $target = time() + 30;
        }

        $next = wp_next_scheduled(self::CRON_HOOK);

        // If not scheduled, or scheduled later than our target, pull it forward.
        if (!$next || $next > ($target + 10)) {

            if ($next) {
                wp_unschedule_event($next, self::CRON_HOOK);
            }

            wp_schedule_single_event($target, self::CRON_HOOK);

            $logger->info(
                LogScopeEnum::SYSTEM,
                LogEventEnum::DEFERRED_RUN_SCHEDULED,
                'Deferred runner scheduled.',
                [
                    'hook' => self::CRON_HOOK,
                    'target_utc' => gmdate('Y-m-d H:i:s', (int) $target) . ' UTC',
                    'prev_next_utc' => $next ? (gmdate('Y-m-d H:i:s', (int) $next) . ' UTC') : null,
                    'next_due_utc' => $nextDue ? (gmdate('Y-m-d H:i:s', (int) $nextDue) . ' UTC') : null,
                    'force_soon' => $forceSoon ? 'yes' : 'no',
                ]
            );
            return;
        }

        $logger->info(
            LogScopeEnum::SYSTEM,
            LogEventEnum::DEFERRED_RUN_ALREADY_SCHEDULED,
            'Deferred runner already scheduled soon enough.',
            [
                'hook' => self::CRON_HOOK,
                'next_utc' => $next ? (gmdate('Y-m-d H:i:s', (int) $next) . ' UTC') : null,
                'target_utc' => gmdate('Y-m-d H:i:s', (int) $target) . ' UTC',
                'force_soon' => $forceSoon ? 'yes' : 'no',
            ]
        );
    }
}