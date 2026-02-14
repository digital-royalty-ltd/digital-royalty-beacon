<?php

namespace DigitalRoyalty\Beacon\Admin\Actions\Reports;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScope;
use DigitalRoyalty\Beacon\Systems\Reports\ReportManager;
use DigitalRoyalty\Beacon\Systems\Reports\ReportRegistry;

final class ReportAdminActions
{
    public const ACTION_START = 'dr_beacon_reports_start';
    public const ACTION_RETRY_SUBMIT = 'dr_beacon_reports_retry_submit';
    public const ACTION_RERUN = 'dr_beacon_reports_rerun';

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION_START, [$this, 'start']);
        add_action('admin_post_' . self::ACTION_RETRY_SUBMIT, [$this, 'retrySubmit']);
        add_action('admin_post_' . self::ACTION_RERUN, [$this, 'rerun']);
    }

    public function start(): void
    {
        if (!current_user_can('manage_options')) {
            Services::logger()->warning(LogScope::ADMIN, 'reports_start_forbidden', 'Forbidden: missing capability manage_options.');
            wp_die('Forbidden');
        }

        check_admin_referer(self::ACTION_START);

        Services::logger()->info(LogScope::ADMIN, 'reports_start_requested', 'Scans start requested.');

        global $wpdb;

        $manager = new ReportManager(
            new ReportRegistry(),
            new ReportsRepository($wpdb)
        );

        try {
            $manager->start();

            Services::logger()->info(LogScope::ADMIN, 'reports_start_success', 'Scans started.');
            $this->redirect(true, 'Scans started.');
        } catch (\Throwable $e) {
            Services::logger()->error(LogScope::ADMIN, 'reports_start_failed', $e->getMessage());
            $this->redirect(false, 'Failed to start scans.');
        }
    }

    public function retrySubmit(): void
    {
        if (!current_user_can('manage_options')) {
            Services::logger()->warning(LogScope::ADMIN, 'reports_retry_submit_forbidden', 'Forbidden: missing capability manage_options.');
            wp_die('Forbidden');
        }

        check_admin_referer(self::ACTION_RETRY_SUBMIT);

        $type = isset($_REQUEST['type']) ? sanitize_key((string) $_REQUEST['type']) : '';
        $version = isset($_REQUEST['version']) ? (int) $_REQUEST['version'] : 0;

        if ($type === '' || $version <= 0) {
            Services::logger()->warning(LogScope::ADMIN, 'reports_retry_submit_invalid', 'Missing report type or version.', [
                'type' => $type,
                'version' => $version,
            ]);

            $this->redirect(false, 'Missing report type or version.');
        }

        Services::logger()->info(LogScope::ADMIN, 'reports_retry_submit_requested', 'Retry submit requested.', [
            'type' => $type,
            'version' => $version,
        ]);

        global $wpdb;

        $manager = new ReportManager(
            new ReportRegistry(),
            new ReportsRepository($wpdb)
        );

        try {
            $manager->enqueueReport($type, $version);

            Services::logger()->info(LogScope::ADMIN, 'reports_retry_submit_queued', 'Retry submit queued.', [
                'type' => $type,
                'version' => $version,
            ]);

            $this->redirect(true, "Queued retry submit for {$type} v{$version}.");
        } catch (\Throwable $e) {
            Services::logger()->error(LogScope::ADMIN, 'reports_retry_submit_failed', $e->getMessage(), [
                'type' => $type,
                'version' => $version,
            ]);

            $this->redirect(false, 'Failed to queue retry submit.');
        }
    }

    public function rerun(): void
    {
        if (!current_user_can('manage_options')) {
            Services::logger()->warning(LogScope::ADMIN, 'reports_rerun_forbidden', 'Forbidden: missing capability manage_options.');
            wp_die('Forbidden');
        }

        check_admin_referer(self::ACTION_RERUN);

        $type = isset($_REQUEST['type']) ? sanitize_key((string) $_REQUEST['type']) : '';
        $version = isset($_REQUEST['version']) ? (int) $_REQUEST['version'] : 0;

        if ($type === '' || $version <= 0) {
            Services::logger()->warning(LogScope::ADMIN, 'reports_rerun_invalid', 'Missing report type or version.', [
                'type' => $type,
                'version' => $version,
            ]);

            $this->redirect(false, 'Missing report type or version.');
        }

        Services::logger()->info(LogScope::ADMIN, 'reports_rerun_requested', 'Rerun requested.', [
            'type' => $type,
            'version' => $version,
        ]);

        global $wpdb;

        $manager = new ReportManager(
            new ReportRegistry(),
            new ReportsRepository($wpdb)
        );

        try {
            $manager->enqueueReport($type, $version);

            Services::logger()->info(LogScope::ADMIN, 'reports_rerun_queued', 'Rerun queued.', [
                'type' => $type,
                'version' => $version,
            ]);

            $this->redirect(true, "Queued rerun for {$type} v{$version}.");
        } catch (\Throwable $e) {
            Services::logger()->error(LogScope::ADMIN, 'reports_rerun_failed', $e->getMessage(), [
                'type' => $type,
                'version' => $version,
            ]);

            $this->redirect(false, 'Failed to queue rerun.');
        }
    }

    private function redirect(bool $ok, string $msg): void
    {
        $url = add_query_arg([
            'page' => 'dr-beacon',
            'dr_beacon_ok' => $ok ? '1' : '0',
            'dr_beacon_msg' => rawurlencode($msg),
        ], admin_url('admin.php'));

        wp_safe_redirect($url);
        exit;
    }
}