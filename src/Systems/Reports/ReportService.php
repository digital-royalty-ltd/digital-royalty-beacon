<?php

namespace DigitalRoyalty\Beacon\Systems\Reports;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;

final class ReportService
{
    public const ACTION_REGENERATE_REPORT = 'dr_beacon_regenerate_report';

    public function register(): void
    {
        add_action(ReportManager::ACTION_RUN_NEXT, [$this, 'handleRunNext']);
        add_action(ReportManager::ACTION_RUN_REPORT, [$this, 'handleRunReport'], 10, 2);
        add_action(self::ACTION_REGENERATE_REPORT, [$this, 'handleRegenerateReport'], 10, 2);
    }

    public function handleRunNext(): void
    {
        global $wpdb;

        $manager = new ReportManager(
            new ReportRegistry(),
            new ReportsRepository($wpdb)
        );

        $manager->runNext();
    }

    public function handleRunReport(string $type = '', int $version = 0): void
    {
        global $wpdb;

        $type = sanitize_key($type);
        $version = (int) $version;

        $runner = new ReportRunner(
            new ReportRegistry(),
            new ReportsRepository($wpdb),
            new ReportSubmitter()
        );

        $runner->run($type, $version);

        $manager = new ReportManager(
            new ReportRegistry(),
            new ReportsRepository($wpdb)
        );

        $manager->enqueueNext();
    }

    /**
     * Regenerate a single report without chaining to the next.
     * Used by the per-report refresh button in the admin UI.
     */
    public function handleRegenerateReport(string $type = '', int $version = 0): void
    {
        global $wpdb;

        $type    = sanitize_key($type);
        $version = (int) $version;

        // Operator-initiated single-report regeneration is a distinct event
        // from the lifecycle run — log it so the audit trail captures
        // "user X regenerated report Y at time Z".
        Services::logger()->info(
            LogScopeEnum::REPORTS,
            'report_regenerate_invoked',
            "Single-report regeneration triggered for {$type} v{$version}.",
            ['type' => $type, 'version' => $version]
        );

        $runner = new ReportRunner(
            new ReportRegistry(),
            new ReportsRepository($wpdb),
            new ReportSubmitter()
        );

        $runner->run($type, $version);
    }
}
