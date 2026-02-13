<?php

namespace DigitalRoyalty\Beacon\Systems\Reports;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;

final class ReportService
{
    public function register(): void
    {
        add_action(ReportManager::ACTION_RUN_NEXT, [$this, 'handleRunNext']);
        add_action(ReportManager::ACTION_RUN_REPORT, [$this, 'handleRunReport'], 10, 2);
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

}
