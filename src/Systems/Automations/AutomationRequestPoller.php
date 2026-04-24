<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEventEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Api\ApiClient;
use DigitalRoyalty\Beacon\Systems\Api\ApiResponse;

/**
 * Pulls pending automation requests from Laravel and dispatches them locally.
 *
 * Runs on its own WP cron schedule (more frequent than the daily heartbeat)
 * so agent-initiated work completes in reasonable time. Each tick:
 *   1. GET /automation-requests/pending
 *   2. For each request: POST /claim → invoke() → POST /complete or /fail
 *
 * Errors in individual requests never abort the batch — they're reported
 * back to Laravel as failures and we move on.
 */
final class AutomationRequestPoller
{
    public const CRON_HOOK = 'dr_beacon_automation_poll';

    private const RECURRENCE = 'dr_beacon_every_five_minutes';

    /** How many requests to claim per tick. */
    private const BATCH_SIZE = 5;

    public function __construct(
        private readonly AutomationRegistry $registry
    ) {}

    public function register(): void
    {
        add_action(self::CRON_HOOK, [$this, 'tick']);
        add_filter('cron_schedules', [$this, 'addCronSchedule']);

        // Self-heal: schedule the cron event if it's missing. The cron_schedules
        // filter above has been added, so wp_schedule_event can resolve our
        // custom recurrence name even on this same request.
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, self::RECURRENCE, self::CRON_HOOK);
        }
    }

    /** @param array<string, array{interval:int,display:string}> $schedules */
    public function addCronSchedule(array $schedules): array
    {
        if (! isset($schedules[self::RECURRENCE])) {
            $schedules[self::RECURRENCE] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => 'Every 5 minutes (Beacon automation poll)',
            ];
        }

        return $schedules;
    }

    public static function onActivation(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, self::RECURRENCE, self::CRON_HOOK);
        }
    }

    public static function onDeactivation(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Cron tick — fetch and process pending automation requests.
     */
    public function tick(): void
    {
        $this->runTick();
    }

    /**
     * Diagnostic variant: runs a tick synchronously and returns a full trace
     * of what was fetched, claimed, and how each invocation ended. Used by
     * the admin Debug panel's "Run poller now" button.
     *
     * @return array{
     *   poll_ok: bool, poll_code: ?int, poll_message: ?string,
     *   pending_count: int,
     *   processed: array<int, array{id: string, key: string, action: string, message: ?string}>,
     *   duration_ms: int
     * }
     */
    public function runTickWithTrace(): array
    {
        return $this->runTick(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function runTick(bool $trace = false): array
    {
        $start = microtime(true);
        $result = [
            'poll_ok'       => false,
            'poll_code'     => null,
            'poll_message'  => null,
            'pending_count' => 0,
            'processed'     => [],
            'duration_ms'   => 0,
        ];

        try {
            // Use Services::apiClient() so the stored API key is attached.
            $client = Services::apiClient();

            $pollResponse = $client->pollAutomationRequests(self::BATCH_SIZE);
            $result['poll_ok']       = (bool) $pollResponse->ok;
            $result['poll_code']     = $pollResponse->code;
            $result['poll_message']  = $pollResponse->message;

            if (! $pollResponse->ok) {
                $result['duration_ms'] = (int) ((microtime(true) - $start) * 1000);

                return $result;
            }

            $requests = $pollResponse->data['data'] ?? [];
            if (! is_array($requests)) {
                $requests = [];
            }
            $result['pending_count'] = count($requests);

            foreach ($requests as $request) {
                if (! is_array($request) || empty($request['id'])) {
                    continue;
                }
                $result['processed'][] = $this->processOne($client, $request);
            }
        } catch (\Throwable $e) {
            $this->logError("Automation poll tick failed: {$e->getMessage()}");
            $result['poll_message'] = 'Exception: '.$e->getMessage();
        }

        $result['duration_ms'] = (int) ((microtime(true) - $start) * 1000);

        return $result;
    }

    /**
     * Claim → dispatch → report back for a single pending request.
     *
     * @param  array<string, mixed>  $request
     * @return array{id: string, key: string, action: string, message: ?string}
     */
    private function processOne(ApiClient $client, array $request): array
    {
        $id            = (string) $request['id'];
        $automationKey = (string) ($request['automation_key'] ?? '');
        $parameters    = is_array($request['parameters'] ?? null) ? $request['parameters'] : [];
        $agentKey      = $request['agent_id'] ?? null;

        $trace = ['id' => $id, 'key' => $automationKey, 'action' => 'skipped', 'message' => null];

        $automation = $this->registry->find($automationKey);
        if (! $automation) {
            $this->reportFail($client, $id, "Unknown automation key: {$automationKey}");

            return ['id' => $id, 'key' => $automationKey, 'action' => 'rejected_unknown_key', 'message' => null];
        }

        // Claim first — another poller may have grabbed it in a race.
        $claim = $client->claimAutomationRequest($id);
        if (! $claim->ok) {
            // 409 means another poller got it; anything else we just skip this tick.
            return ['id' => $id, 'key' => $automationKey, 'action' => 'claim_failed', 'message' => $claim->message];
        }

        // Build the actor context. Agent-initiated if we have an agent id,
        // otherwise attribute to the API (scheduler-equivalent from Laravel's side).
        $actor = $agentKey
            ? InvocationActor::agent('agent:'.$agentKey)
            : InvocationActor::api();

        try {
            $result = $automation->invoke($parameters, $actor);

            if ($result->ok) {
                $client->completeAutomationRequest($id, $result->toArray());
                $trace['action'] = 'completed';
                $trace['message'] = $result->message;
            } else {
                $client->failAutomationRequest($id, $result->message ?? 'Automation returned failure.');
                $trace['action'] = 'failed';
                $trace['message'] = $result->message;
            }
        } catch (\Throwable $e) {
            $this->reportFail($client, $id, "Invocation threw: {$e->getMessage()}");
            $trace['action'] = 'exception';
            $trace['message'] = $e->getMessage();
        }

        return $trace;
    }

    private function reportFail(ApiClient $client, string $id, string $error): void
    {
        try {
            $client->failAutomationRequest($id, $error);
        } catch (\Throwable $e) {
            $this->logError("Failed to report failure for automation request {$id}: {$e->getMessage()}");
        }
    }

    private function logError(string $message): void
    {
        if (class_exists(Services::class)) {
            Services::logger()->info(LogScopeEnum::API, LogEventEnum::API_RESPONSE_ERROR, $message);
        }
    }
}
