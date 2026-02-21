<?php

namespace DigitalRoyalty\Beacon\Admin\Actions\Views;

use DigitalRoyalty\Beacon\Database\LogsTable;
use DigitalRoyalty\Beacon\Database\ReportsTable;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminQueryEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\DebugViewActionEnum;
use DigitalRoyalty\Beacon\Support\Enums\Scheduler\SchedulerGroupEnum;
use DigitalRoyalty\Beacon\Systems\Reports\ReportManager;


final class DebugPageAdminActions
{
    public const SLUG = AdminPageEnum::DEBUG;

    public function register(): void
    {
        add_action('admin_post_' . DebugViewActionEnum::CLEAR_REPORTS, [$this, 'handleClearReports']);
        add_action('admin_post_' . DebugViewActionEnum::RESET_STATUS, [$this, 'handleResetStatus']);
        add_action('admin_post_' . DebugViewActionEnum::FULL_RESET, [$this, 'handleFullReset']);
        add_action('admin_post_' . DebugViewActionEnum::UNSCHEDULE, [$this, 'handleUnschedule']);
        add_action('admin_post_' . DebugViewActionEnum::CLEAR_LOGS, [$this, 'handleClearLogs']);
        add_action('admin_post_' . DebugViewActionEnum::CLEAR_SCHEDULER, [$this, 'handleClearScheduler']);
    }

    public function handleClearLogs(): void
    {
        $this->guard(DebugViewActionEnum::CLEAR_LOGS);

        global $wpdb;
        $table = LogsTable::tableName($wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("TRUNCATE TABLE {$table}");

        $this->redirect(true, 'Logs cleared.');
    }

    public function handleClearScheduler(): void
    {
        $this->guard(DebugViewActionEnum::CLEAR_SCHEDULER);

        global $wpdb;

        $actionsTable = $wpdb->prefix . 'actionscheduler_actions';
        $groupsTable  = $wpdb->prefix . 'actionscheduler_groups';

        // Get group_id for our slug
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $groupId = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT group_id FROM {$groupsTable} WHERE slug = %s LIMIT 1",
                SchedulerGroupEnum::BEACON
            )
        );

        if ($groupId <= 0) {
            $this->redirect(false, 'Beacon Action Scheduler group not found.');
        }

        // SAFER DEFAULT: only delete completed actions.
        // If you want "delete everything", remove the status clause.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $deleted = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$actionsTable} WHERE group_id = %d AND status = %s",
                $groupId,
                'complete'
            )
        );

        $this->redirect(true, "Cleared {$deleted} completed scheduled actions.");
    }

    public function handleClearReports(): void
    {
        $this->guard(DebugViewActionEnum::CLEAR_REPORTS);

        global $wpdb;
        $table = ReportsTable::tableName($wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("TRUNCATE TABLE {$table}");

        $this->redirect(true, 'Deleted saved report snapshots.');
    }

    public function handleResetStatus(): void
    {
        $this->guard(DebugViewActionEnum::RESET_STATUS);

        delete_option(ReportManager::OPTION_STATUS);
        delete_option('dr_beacon_reports_last_error');
        delete_option('dr_beacon_onboarding_scan_last_error');
        delete_option('dr_beacon_last_runner_heartbeat');

        $this->redirect(true, 'Onboarding status reset.');
    }

    public function handleUnschedule(): void
    {
        $this->guard(DebugViewActionEnum::UNSCHEDULE);

        if (!function_exists('as_unschedule_all_actions')) {
            $this->redirect(false, 'Action Scheduler not available.');
        }

        as_unschedule_all_actions(ReportManager::ACTION_RUN_NEXT, [], 'dr-beacon');
        as_unschedule_all_actions(ReportManager::ACTION_RUN_REPORT, [], 'dr-beacon');

        $this->redirect(true, 'Queued report jobs removed.');
    }

    public function handleFullReset(): void
    {
        $this->guard(DebugViewActionEnum::FULL_RESET);

        $this->handleUnscheduleInternal();
        $this->handleResetStatusInternal();
        $this->handleClearReportsInternal();

        $this->redirect(true, 'Full reset complete.');
    }

    private function handleUnscheduleInternal(): void
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(ReportManager::ACTION_RUN_NEXT, [], 'dr-beacon');
            as_unschedule_all_actions(ReportManager::ACTION_RUN_REPORT, [], 'dr-beacon');
        }
    }

    private function handleResetStatusInternal(): void
    {
        delete_option(ReportManager::OPTION_STATUS);
        delete_option('dr_beacon_reports_last_error');
        delete_option('dr_beacon_onboarding_scan_last_error');
        delete_option('dr_beacon_last_runner_heartbeat');
    }

    private function handleClearReportsInternal(): void
    {
        global $wpdb;
        $table = ReportsTable::tableName($wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("TRUNCATE TABLE {$table}");
    }

    private function guard(string $nonceAction): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        check_admin_referer($nonceAction);
    }

    private function redirect(bool $ok, string $message): void
    {
        $url = add_query_arg(
            [
                'page' => self::SLUG,
                AdminQueryEnum::OK => $ok ? '1' : '0',
                AdminQueryEnum::MSG => rawurlencode($message),
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
