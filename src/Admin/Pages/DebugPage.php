<?php

namespace DigitalRoyalty\Beacon\Admin\Pages;

use DigitalRoyalty\Beacon\Database\ReportsTable;
use DigitalRoyalty\Beacon\Systems\Reports\ReportManager;

final class DebugPage
{
    public const SLUG = 'dr-beacon-debug';

    private const ACTION_CLEAR_REPORTS = 'dr_beacon_debug_clear_reports';
    private const ACTION_RESET_STATUS = 'dr_beacon_debug_reset_status';
    private const ACTION_FULL_RESET = 'dr_beacon_debug_full_reset';
    private const ACTION_UNSCHEDULE = 'dr_beacon_debug_unschedule';

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION_CLEAR_REPORTS, [$this, 'handleClearReports']);
        add_action('admin_post_' . self::ACTION_RESET_STATUS, [$this, 'handleResetStatus']);
        add_action('admin_post_' . self::ACTION_FULL_RESET, [$this, 'handleFullReset']);
        add_action('admin_post_' . self::ACTION_UNSCHEDULE, [$this, 'handleUnschedule']);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options') || !$this->isEnabled()) {
            return;
        }

        $okParam = isset($_GET['dr_beacon_ok']) ? (string) $_GET['dr_beacon_ok'] : '0';
        $isOk = $okParam === '1';
        $msg = isset($_GET['dr_beacon_msg']) ? (string) $_GET['dr_beacon_msg'] : '';

        ?>
        <div class="wrap">
            <h1>Beacon Debug</h1>

            <?php if ($msg !== '') : ?>
                <div class="notice notice-<?php echo esc_attr($isOk ? 'success' : 'error'); ?> is-dismissible">
                    <p><?php echo esc_html($msg); ?></p>
                </div>
            <?php endif; ?>

            <p class="description">
                Developer and advanced tools for resetting Beacon state.
            </p>

            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
                <h2 style="margin-top:0;">Report snapshots</h2>
                <p class="description">Deletes all rows from the report snapshots table.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_CLEAR_REPORTS); ?>">
                    <?php wp_nonce_field(self::ACTION_CLEAR_REPORTS); ?>
                    <?php submit_button('Delete saved report snapshots', 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div style="height:16px;"></div>

            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
                <h2 style="margin-top:0;">Onboarding state</h2>
                <p class="description">Resets onboarding status so Beacon returns to the Start scans screen.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_RESET_STATUS); ?>">
                    <?php wp_nonce_field(self::ACTION_RESET_STATUS); ?>
                    <?php submit_button('Reset onboarding status', 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div style="height:16px;"></div>

            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
                <h2 style="margin-top:0;">Action Scheduler</h2>
                <p class="description">Removes queued Beacon report actions from Action Scheduler.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_UNSCHEDULE); ?>">
                    <?php wp_nonce_field(self::ACTION_UNSCHEDULE); ?>
                    <?php submit_button('Unschedule queued report jobs', 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div style="height:16px;"></div>

            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
                <h2 style="margin-top:0;">Full reset</h2>
                <p class="description">
                    Clears report snapshots, onboarding state, and queued jobs. Useful for clean testing.
                </p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_FULL_RESET); ?>">
                    <?php wp_nonce_field(self::ACTION_FULL_RESET); ?>
                    <?php submit_button('Full reset', 'primary', 'submit', false); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function handleClearReports(): void
    {
        $this->guard(self::ACTION_CLEAR_REPORTS);

        global $wpdb;
        $table = ReportsTable::tableName($wpdb);

        $wpdb->query("TRUNCATE TABLE {$table}");

        $this->redirect(true, 'Deleted saved report snapshots.');
    }

    public function handleResetStatus(): void
    {
        $this->guard(self::ACTION_RESET_STATUS);

        delete_option(ReportManager::OPTION_STATUS);
        delete_option('dr_beacon_reports_last_error');
        delete_option('dr_beacon_onboarding_scan_last_error');
        delete_option('dr_beacon_last_runner_heartbeat');

        $this->redirect(true, 'Onboarding status reset.');
    }

    public function handleUnschedule(): void
    {
        $this->guard(self::ACTION_UNSCHEDULE);

        if (!function_exists('as_unschedule_all_actions')) {
            $this->redirect(false, 'Action Scheduler not available.');
        }

        // Remove both coordinator and runner jobs.
        as_unschedule_all_actions(ReportManager::ACTION_RUN_NEXT, [], 'dr-beacon');
        as_unschedule_all_actions(ReportManager::ACTION_RUN_REPORT, [], 'dr-beacon');

        $this->redirect(true, 'Queued report jobs removed.');
    }

    public function handleFullReset(): void
    {
        $this->guard(self::ACTION_FULL_RESET);

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
        $wpdb->query("TRUNCATE TABLE {$table}");
    }

    private function guard(string $nonceAction): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        if (!$this->isEnabled()) {
            wp_die('Debug tools disabled.', 403);
        }

        check_admin_referer($nonceAction);
    }

    private function redirect(bool $ok, string $message): void
    {
        $url = add_query_arg(
            [
                'page' => self::SLUG,
                'dr_beacon_ok' => $ok ? '1' : '0',
                'dr_beacon_msg' => rawurlencode($message),
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    public function isEnabled(): bool
    {
        return true;
        // Dev only for now. Later you can replace this with a “developer mode” setting.
        return defined('WP_DEBUG') && WP_DEBUG;
    }
}
