<?php

namespace DigitalRoyalty\Beacon\Systems\Reports;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;

final class ReportManager
{
    public const OPTION_STATUS = 'dr_beacon_onboarding_status';

    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const STATUS_NEEDS_ATTENTION = 'needs_attention';
    public const STATUS_PAUSED = 'paused';


    public const ACTION_RUN_NEXT = 'dr_beacon_run_next_report';
    public const ACTION_RUN_REPORT = 'dr_beacon_run_report';

    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly ReportsRepository $repository
    ) {}

    public function start(): void
    {
        update_option(self::OPTION_STATUS, self::STATUS_RUNNING, false);

        foreach ($this->registry->required() as $report) {
            $this->repository->upsertPending($report->type(), $report->version());
        }

        $this->enqueueNext();
    }

    public function enqueueNext(): void
    {
        if (!function_exists('as_enqueue_async_action')) {
            update_option(self::OPTION_STATUS, self::STATUS_FAILED, false);
            update_option('dr_beacon_reports_last_error', 'Action Scheduler not available.', false);
            return;
        }

        // Prevent duplicate coordinator actions.
        if (function_exists('as_next_scheduled_action')) {
            $next = as_next_scheduled_action(self::ACTION_RUN_NEXT, [], 'dr-beacon');
            if (!empty($next)) {
                return;
            }
        }

        as_enqueue_async_action(self::ACTION_RUN_NEXT, [], 'dr-beacon');
    }

    public function runNext(): void
    {
        foreach ($this->registry->required() as $report) {
            $existing = $this->repository->getByTypeAndVersion(
                $report->type(),
                $report->version()
            );

            if (!$existing || ($existing['status'] ?? '') !== 'submitted') {
                $this->enqueueReport($report->type(), $report->version());
                return;
            }
        }

        update_option(self::OPTION_STATUS, self::STATUS_COMPLETED, false);
    }

    public function enqueueReport(string $type, int $version): void
    {
        if (!function_exists('as_enqueue_async_action')) {
            update_option(self::OPTION_STATUS, self::STATUS_FAILED, false);
            update_option('dr_beacon_reports_last_error', 'Action Scheduler not available.', false);
            return;
        }

        as_enqueue_async_action(
            self::ACTION_RUN_REPORT,
            [$type, $version],
            'dr-beacon'
        );
    }

    public function getEffectiveStatus(): string
    {
        $required = $this->registry->required();

        $allSubmitted = true;
        $anyFailed = false;
        $anyGeneratedOrPending = false;

        foreach ($required as $report) {
            $row = $this->repository->getByTypeAndVersion($report->type(), $report->version());
            $rowStatus = is_array($row) ? (string) ($row['status'] ?? 'pending') : 'pending';

            if ($rowStatus !== 'submitted') {
                $allSubmitted = false;
            }

            if ($rowStatus === 'failed') {
                $anyFailed = true;
            }

            if (in_array($rowStatus, ['pending', 'generated'], true)) {
                $anyGeneratedOrPending = true;
            }
        }

        if ($allSubmitted) {
            return self::STATUS_COMPLETED;
        }

        $queueActive = $this->isQueueActive();

        // If something failed and there is no queued work to continue, it needs attention.
        if (($anyFailed && !$queueActive) || ($anyFailed && $queueActive) ) {
            return self::STATUS_NEEDS_ATTENTION;
        }

        if ($queueActive) {
            return self::STATUS_RUNNING;
        }

        // Incomplete but nothing queued. Treat as paused (or not_started).
        if ($anyGeneratedOrPending && $anyFailed) {
            return self::STATUS_PAUSED;
        }

        return self::STATUS_NOT_STARTED;
    }

    public function isQueueActive(): bool
    {
        if (!function_exists('as_get_scheduled_actions')) {
            return false;
        }

        $hooks = [self::ACTION_RUN_NEXT, self::ACTION_RUN_REPORT];

        foreach ($hooks as $hook) {
            $actions = as_get_scheduled_actions([
                'hook' => $hook,
                'group' => 'dr-beacon',
                'status' => 'pending',
                'per_page' => 1,
            ]);

            if (!empty($actions)) {
                return true;
            }
        }

        return false;
    }

}
