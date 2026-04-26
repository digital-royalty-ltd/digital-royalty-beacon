<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationModeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEventEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;

/**
 * Manages recurring automation schedules.
 *
 * Schedules are stored in a WP option as a simple array of configs.
 * A WP cron hook fires periodically to check for due schedules and
 * dispatch them through the same API client path as manual runs.
 *
 * End behaviors:
 * - infinite:       Runs forever on the frequency. No item tracking.
 * - exhaust:        Processes items from source types; stops when all done.
 * - infinite_cycle: Processes items from source types; resets and starts over.
 */
final class AutomationScheduler
{
    public const OPTION_KEY = 'dr_beacon_automation_schedules';
    public const CRON_HOOK  = 'dr_beacon_run_scheduled_automations';

    private AutomationRegistry $registry;

    public function __construct(?AutomationRegistry $registry = null)
    {
        $this->registry = $registry ?? new AutomationRegistry();
    }

    public function register(): void
    {
        add_action(self::CRON_HOOK, [$this, 'runDue']);
    }

    public function ensureScheduled(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
    }

    // -----------------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------------

    /** @return array<int, array<string,mixed>> */
    public function all(): array
    {
        $schedules = get_option(self::OPTION_KEY, []);

        return is_array($schedules) ? array_values($schedules) : [];
    }

    /** @return array<string,mixed>|null */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $schedule) {
            if (($schedule['id'] ?? '') === $id) {
                return $schedule;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $parameters
     * @return array<string,mixed>
     */
    public function create(
        string $automationKey,
        string $frequency,
        string $time,
        ?string $dayOfWeek,
        string $endBehavior,
        array $parameters
    ): array {
        $automation = $this->registry->find($automationKey);

        if (!$automation) {
            throw new \InvalidArgumentException("Unknown automation: {$automationKey}");
        }

        if (!in_array(AutomationModeEnum::SCHEDULED, $automation->supportedModes(), true)) {
            throw new \InvalidArgumentException("Automation '{$automationKey}' does not support scheduled mode.");
        }

        $this->validateFrequency($frequency);
        $this->validateEndBehavior($endBehavior);

        $schedule = [
            'id'              => wp_generate_uuid4(),
            'automation_key'  => $automationKey,
            'frequency'       => $frequency,
            'time'            => $this->normaliseTime($time),
            'day_of_week'     => $frequency === 'weekly' ? ($dayOfWeek ?? 'monday') : null,
            'end_behavior'    => $endBehavior,
            'parameters'      => $parameters,
            'enabled'         => true,
            'next_run_at'     => $this->calculateNextRun($frequency, $this->normaliseTime($time), $dayOfWeek),
            'last_run_at'     => null,
            'processed_ids'   => [],
            'cycle_count'     => 0,
            'exhausted'       => false,
            'created_at'      => gmdate('Y-m-d H:i:s'),
        ];

        $schedules   = $this->all();
        $schedules[] = $schedule;
        update_option(self::OPTION_KEY, $schedules, false);

        return $schedule;
    }

    public function delete(string $id): bool
    {
        $schedules = $this->all();
        $filtered  = array_values(array_filter($schedules, fn ($s) => ($s['id'] ?? '') !== $id));

        if (count($filtered) === count($schedules)) {
            return false;
        }

        update_option(self::OPTION_KEY, $filtered, false);

        return true;
    }

    public function toggle(string $id, bool $enabled): bool
    {
        $schedules = $this->all();
        $found     = false;

        foreach ($schedules as &$schedule) {
            if (($schedule['id'] ?? '') === $id) {
                $schedule['enabled'] = $enabled;
                if ($enabled) {
                    $schedule['exhausted'] = false;
                    $schedule['next_run_at'] = $this->calculateNextRun(
                        $schedule['frequency'],
                        $schedule['time'],
                        $schedule['day_of_week'] ?? null
                    );
                }
                $found = true;
                break;
            }
        }

        if (!$found) {
            return false;
        }

        update_option(self::OPTION_KEY, $schedules, false);

        return true;
    }

    /**
     * Record that an item was processed by a schedule.
     * Returns true if the schedule should continue, false if exhausted.
     */
    public function recordProcessed(string $scheduleId, string $itemId): bool
    {
        $schedules = $this->all();

        foreach ($schedules as &$schedule) {
            if (($schedule['id'] ?? '') !== $scheduleId) {
                continue;
            }

            $processedIds = is_array($schedule['processed_ids'] ?? null) ? $schedule['processed_ids'] : [];

            if (!in_array($itemId, $processedIds, true)) {
                $processedIds[] = $itemId;
                $schedule['processed_ids'] = $processedIds;
            }

            update_option(self::OPTION_KEY, $schedules, false);

            return true;
        }

        return false;
    }

    /**
     * Called when all items in a source have been processed.
     * Handles cycle reset or exhaust based on end_behavior.
     */
    public function handleSourceExhausted(string $scheduleId): void
    {
        $schedules = $this->all();

        foreach ($schedules as &$schedule) {
            if (($schedule['id'] ?? '') !== $scheduleId) {
                continue;
            }

            $endBehavior = $schedule['end_behavior'] ?? 'infinite';

            if ($endBehavior === 'exhaust') {
                $schedule['exhausted'] = true;
                $schedule['enabled']   = false;

                Services::logger()->info(
                    LogScopeEnum::SYSTEM,
                    LogEventEnum::AUTOMATION_SCHEDULED_RUN,
                    'Schedule exhausted — all items processed.',
                    ['schedule_id' => $scheduleId]
                );
            } elseif ($endBehavior === 'infinite_cycle') {
                $schedule['processed_ids'] = [];
                $schedule['cycle_count']   = ((int) ($schedule['cycle_count'] ?? 0)) + 1;

                Services::logger()->info(
                    LogScopeEnum::SYSTEM,
                    LogEventEnum::AUTOMATION_SCHEDULED_RUN,
                    'Schedule cycling — resetting processed items.',
                    ['schedule_id' => $scheduleId, 'cycle_count' => $schedule['cycle_count']]
                );
            }
            // 'infinite' doesn't track items, so no action needed.

            break;
        }

        update_option(self::OPTION_KEY, $schedules, false);
    }

    // -----------------------------------------------------------------------
    // Runner
    // -----------------------------------------------------------------------

    public function runDue(): void
    {
        $logger = Services::logger();
        $schedules = $this->all();
        $now       = gmdate('Y-m-d H:i:s');
        $changed   = false;
        $dispatched = 0;
        $failed = 0;

        $logger->info(
            LogScopeEnum::BACKGROUND,
            'scheduler_tick_start',
            'Automation scheduler tick started.',
            ['total_schedules' => count($schedules), 'now' => $now]
        );

        foreach ($schedules as &$schedule) {
            if (!($schedule['enabled'] ?? false)) {
                continue;
            }

            if ($schedule['exhausted'] ?? false) {
                continue;
            }

            $nextRun = $schedule['next_run_at'] ?? null;

            if (!$nextRun || $nextRun > $now) {
                continue;
            }

            if ($this->dispatch($schedule)) {
                $dispatched++;
            } else {
                $failed++;
            }

            $schedule['last_run_at']  = $now;
            $schedule['next_run_at']  = $this->calculateNextRun(
                $schedule['frequency'],
                $schedule['time'],
                $schedule['day_of_week'] ?? null
            );
            $changed = true;
        }

        if ($changed) {
            update_option(self::OPTION_KEY, $schedules, false);
        }

        $logger->info(
            LogScopeEnum::BACKGROUND,
            'scheduler_tick_end',
            sprintf('Automation scheduler tick complete: dispatched=%d, failed=%d.', $dispatched, $failed),
            ['dispatched' => $dispatched, 'failed' => $failed]
        );
    }

    /**
     * Dispatch a single scheduled automation. Returns true if the API
     * accepted the request, false otherwise (no handler, transport error,
     * or non-2xx response). The caller logs a tick summary using these
     * counts so failed dispatches are no longer invisible.
     *
     * @param array<string,mixed> $schedule
     */
    private function dispatch(array $schedule): bool
    {
        $automationKey = $schedule['automation_key'] ?? '';
        $parameters    = is_array($schedule['parameters'] ?? null) ? $schedule['parameters'] : [];
        $scheduleId    = $schedule['id'] ?? '';

        $logger = Services::logger();

        $logger->info(
            LogScopeEnum::BACKGROUND,
            LogEventEnum::AUTOMATION_SCHEDULED_RUN,
            "Running scheduled automation: {$automationKey}",
            [
                'schedule_id'    => $scheduleId,
                'automation_key' => $automationKey,
                'frequency'      => $schedule['frequency'] ?? null,
                'end_behavior'   => $schedule['end_behavior'] ?? 'infinite',
                'cycle_count'    => $schedule['cycle_count'] ?? 0,
            ]
        );

        // Inject schedule_id into adapter_context so handlers can track progress.
        if (!isset($parameters['adapter_context'])) {
            $parameters['adapter_context'] = [];
        }
        if (is_array($parameters['adapter_context'])) {
            $parameters['adapter_context']['schedule_id'] = $scheduleId;
        }

        $apiClient = Services::apiClient();

        $response = match ($automationKey) {
            'news_article_generator' => $apiClient->generateNewsArticle($parameters),
            'social_share'           => $apiClient->generateSocialPosts($parameters),
            default => null,
        };

        if ($response === null) {
            $logger->warning(
                LogScopeEnum::BACKGROUND,
                LogEventEnum::AUTOMATION_SCHEDULED_RUN,
                "No dispatch handler for scheduled automation: {$automationKey}",
                ['schedule_id' => $scheduleId, 'automation_key' => $automationKey]
            );

            return false;
        }

        if (!$response->ok) {
            $logger->warning(
                LogScopeEnum::BACKGROUND,
                LogEventEnum::AUTOMATION_SCHEDULED_RUN,
                "Scheduled automation API call failed: {$automationKey}",
                [
                    'schedule_id' => $scheduleId,
                    'automation_key' => $automationKey,
                    'status_code' => $response->code,
                    'message' => $response->message,
                ]
            );

            return false;
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function validateFrequency(string $frequency): void
    {
        if (!in_array($frequency, self::validFrequencies(), true)) {
            // Log at source — the REST controller catches the exception and
            // returns a generic 400 without context, so without this log a
            // rejected schedule create is invisible to the operator.
            Services::logger()->warning(
                LogScopeEnum::SYSTEM,
                'schedule_validation_failed',
                "Schedule create rejected: invalid frequency '{$frequency}'.",
                ['field' => 'frequency', 'value' => $frequency, 'allowed' => self::validFrequencies()]
            );
            throw new \InvalidArgumentException("Invalid frequency: {$frequency}.");
        }
    }

    private function validateEndBehavior(string $endBehavior): void
    {
        if (!in_array($endBehavior, self::validEndBehaviors(), true)) {
            Services::logger()->warning(
                LogScopeEnum::SYSTEM,
                'schedule_validation_failed',
                "Schedule create rejected: invalid end_behavior '{$endBehavior}'.",
                ['field' => 'end_behavior', 'value' => $endBehavior, 'allowed' => self::validEndBehaviors()]
            );
            throw new \InvalidArgumentException("Invalid end_behavior: {$endBehavior}.");
        }
    }

    private function normaliseTime(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return '09:00';
    }

    private function calculateNextRun(string $frequency, string $time, ?string $dayOfWeek): string
    {
        $now  = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $hour = (int) substr($time, 0, 2);
        $min  = (int) substr($time, 3, 2);

        $candidate = $now->setTime($hour, $min, 0);

        if ($candidate <= $now) {
            $candidate = $candidate->modify('+1 day');
        }

        if ($frequency === 'weekly' && $dayOfWeek) {
            $targetDay = strtolower($dayOfWeek);
            $currentDay = strtolower($candidate->format('l'));

            if ($currentDay !== $targetDay) {
                $candidate = $candidate->modify("next {$targetDay}");
                $candidate = $candidate->setTime($hour, $min, 0);
            }
        } elseif ($frequency === 'every_other_day') {
            $candidate = $candidate->modify('+1 day');
        }

        return $candidate->format('Y-m-d H:i:s');
    }

    /** @return string[] */
    public static function validFrequencies(): array
    {
        return ['daily', 'every_other_day', 'weekly'];
    }

    /** @return string[] */
    public static function validEndBehaviors(): array
    {
        return ['infinite', 'exhaust', 'infinite_cycle'];
    }

    /** @return array<string,string> */
    public static function frequencyLabels(): array
    {
        return [
            'daily'          => 'Daily',
            'every_other_day' => 'Every other day',
            'weekly'         => 'Weekly',
        ];
    }

    /** @return array<string,string> */
    public static function endBehaviorLabels(): array
    {
        return [
            'infinite'       => 'Run forever',
            'exhaust'        => 'Stop when all items processed',
            'infinite_cycle' => 'Cycle forever (restart when done)',
        ];
    }
}
