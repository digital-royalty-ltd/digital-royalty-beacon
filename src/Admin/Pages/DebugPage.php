<?php

namespace DigitalRoyalty\Beacon\Admin\Pages;

use DigitalRoyalty\Beacon\Admin\Tables\LogsListTable;
use DigitalRoyalty\Beacon\Repositories\LogsRepository;
use DigitalRoyalty\Beacon\Support\Enums\Admin\DebugPageAction;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPage;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminQuery;
use DigitalRoyalty\Beacon\Admin\Tables\SchedulerListTable;
use DigitalRoyalty\Beacon\Repositories\SchedulerRepository;


final class DebugPage
{
    public const SLUG = AdminPage::DEBUG;


    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $okParam = isset($_GET[AdminQuery::OK]) ? (string) $_GET[AdminQuery::OK] : '0';
        $isOk = $okParam === '1';
        $msg = isset($_GET[AdminQuery::MSG]) ? (string) $_GET[AdminQuery::MSG] : '';

        global $wpdb;
        $logsRepo = new LogsRepository($wpdb);
        $logsTable = new LogsListTable($logsRepo);
        $logsTable->prepare_items();

        $schedulerRepo = new SchedulerRepository($wpdb);
        $schedulerTable = new SchedulerListTable($schedulerRepo);
        $schedulerTable->prepare_items();

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

            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:1200px;">
                <h2 style="margin-top:0;">Logs</h2>
                <p class="description">Newest first. Use pagination for older entries.</p>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin:0 0 10px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                        <input type="hidden" name="action" value="<?php echo esc_attr(DebugPageAction::CLEAR_LOGS); ?>">
                        <?php wp_nonce_field(DebugPageAction::CLEAR_LOGS); ?>
                        <?php submit_button('Clear logs', 'secondary', 'submit', false); ?>
                    </form>
                </div>


                <form method="get" style="margin:0;">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>" />
                    <?php $logsTable->display(); ?>
                </form>
            </div>

            <div style="height:16px;"></div>

            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:1200px;">
                <h2 style="margin-top:0;">Beacon scheduled actions</h2>
                <p class="description">
                    Action Scheduler entries in group <code>dr-beacon</code>.
                </p>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin:0 0 10px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                        <input type="hidden" name="action" value="<?php echo esc_attr(DebugPageAction::CLEAR_SCHEDULER); ?>">
                        <?php wp_nonce_field(DebugPageAction::CLEAR_SCHEDULER); ?>
                        <?php submit_button('Clear completed scheduled actions', 'secondary', 'submit', false); ?>
                    </form>
                </div>

                <?php if (!function_exists('as_get_scheduled_actions')) : ?>
                    <div class="notice notice-warning" style="margin:0;">
                        <p style="margin:0;">Action Scheduler not available.</p>
                    </div>
                <?php else : ?>
                    <form method="get" style="margin:0;">
                        <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>" />
                        <?php $schedulerTable->display(); ?>
                    </form>
                <?php endif; ?>
            </div>


            <div style="height:16px;"></div>

            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
                <h2 style="margin-top:0;">Report snapshots</h2>
                <p class="description">Deletes all rows from the report snapshots table.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                    <input type="hidden" name="action" value="<?php echo esc_attr(DebugPageAction::CLEAR_REPORTS); ?>">
                    <?php wp_nonce_field(DebugPageAction::CLEAR_REPORTS); ?>
                    <?php submit_button('Delete saved report snapshots', 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div style="height:16px;"></div>

            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
                <h2 style="margin-top:0;">Onboarding state</h2>
                <p class="description">Resets onboarding status so Beacon returns to the Start scans screen.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                    <input type="hidden" name="action" value="<?php echo esc_attr(DebugPageAction::RESET_STATUS); ?>">
                    <?php wp_nonce_field(DebugPageAction::RESET_STATUS); ?>
                    <?php submit_button('Reset onboarding status', 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div style="height:16px;"></div>

            <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
                <h2 style="margin-top:0;">Action Scheduler</h2>
                <p class="description">Removes queued Beacon report actions from Action Scheduler.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                    <input type="hidden" name="action" value="<?php echo esc_attr(DebugPageAction::UNSCHEDULE); ?>">
                    <?php wp_nonce_field(DebugPageAction::UNSCHEDULE); ?>
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
                    <input type="hidden" name="action" value="<?php echo esc_attr(DebugPageAction::FULL_RESET); ?>">
                    <?php wp_nonce_field(DebugPageAction::FULL_RESET); ?>
                    <?php submit_button('Full reset', 'primary', 'submit', false); ?>
                </form>
            </div>

        </div>
        <?php
    }
}
