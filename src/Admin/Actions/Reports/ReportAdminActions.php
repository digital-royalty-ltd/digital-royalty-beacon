<?php

namespace DigitalRoyalty\Beacon\Admin\Actions\Reports;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
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
            wp_die('Forbidden');
        }

        check_admin_referer(self::ACTION_START);

        global $wpdb;

        $manager = new ReportManager(
            new ReportRegistry(),
            new ReportsRepository($wpdb)
        );

        $manager->start();

        wp_safe_redirect(admin_url('admin.php?page=dr-beacon'));
        exit;
    }

    public function retrySubmit(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }

        check_admin_referer(self::ACTION_RETRY_SUBMIT);

        $type = isset($_GET['type']) ? sanitize_key((string) $_GET['type']) : '';
        $version = isset($_GET['version']) ? (int) $_GET['version'] : 0;

        if ($type === '' || $version <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=dr-beacon'));
            exit;
        }

        global $wpdb;

        // Queue just a submit attempt via the normal run path (will regenerate by default).
        // If you want "submit only", we can add a dedicated action later.
        $manager = new ReportManager(
            new ReportRegistry(),
            new ReportsRepository($wpdb)
        );

        $manager->enqueueReport($type, $version);

        wp_safe_redirect(admin_url('admin.php?page=dr-beacon'));
        exit;
    }

    public function rerun(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }

        check_admin_referer(self::ACTION_RERUN);

        $type = isset($_GET['type']) ? sanitize_key((string) $_GET['type']) : '';
        $version = isset($_GET['version']) ? (int) $_GET['version'] : 0;

        if ($type === '' || $version <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=dr-beacon'));
            exit;
        }

        global $wpdb;

        $manager = new ReportManager(
            new ReportRegistry(),
            new ReportsRepository($wpdb)
        );

        $manager->enqueueReport($type, $version);

        wp_safe_redirect(admin_url('admin.php?page=dr-beacon'));
        exit;
    }
}
