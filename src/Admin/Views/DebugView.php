<?php

namespace DigitalRoyalty\Beacon\Admin\Views;

use DigitalRoyalty\Beacon\Admin\Components\Tables\LogsListTable;
use DigitalRoyalty\Beacon\Admin\Components\Tables\SchedulerListTable;
use DigitalRoyalty\Beacon\Repositories\LogsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Admin\DebugViewActionEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminQueryEnum;
use DigitalRoyalty\Beacon\Repositories\SchedulerRepository;
use DigitalRoyalty\Beacon\Admin\Components\Tables\DeferredRequestsListTable;
use DigitalRoyalty\Beacon\Repositories\DeferredRequestsRepository;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEventEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Health\HealthcheckService;


final class DebugView implements ViewInterface
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $okParam = isset($_GET[AdminQueryEnum::OK]) ? (string)$_GET[AdminQueryEnum::OK] : '0';
        $isOk = $okParam === '1';
        $msg = isset($_GET[AdminQueryEnum::MSG]) ? (string)$_GET[AdminQueryEnum::MSG] : '';

        global $wpdb;
        $logsRepo = new LogsRepository($wpdb);
        $logsTable = new LogsListTable($logsRepo);
        $logsTable->prepare_items();

        $deferredRepo = new DeferredRequestsRepository($wpdb);
        $deferredTable = new DeferredRequestsListTable($deferredRepo);
        $deferredTable->prepare_items();

        $schedulerRepo = new SchedulerRepository($wpdb);
        $schedulerTable = new SchedulerListTable($schedulerRepo);
        $schedulerTable->prepare_items();

        $health = new HealthcheckService($wpdb);
        $healthSnapshot = $health->snapshot();

        $tabParam = isset($_GET[AdminQueryEnum::TAB]) ? (string)$_GET[AdminQueryEnum::TAB] : 'logs';
        $activeTab = in_array($tabParam, ['logs', 'deferred', 'scheduler', 'reports', 'reset'], true) ? $tabParam : 'logs';

        $baseUrl = add_query_arg(
                ['page' => $this->slug()],
                admin_url('admin.php')
        );

        $tabUrl = static function (string $tab) use ($baseUrl): string {
            return esc_url(add_query_arg([AdminQueryEnum::TAB => $tab], $baseUrl));
        };

        ?>
        <div class="wrap">
            <h1><?= $this->title() ?></h1>

            <?php if ($msg !== '') : ?>
                <div class="notice notice-<?php echo esc_attr($isOk ? 'success' : 'error'); ?> is-dismissible">
                    <p><?php echo esc_html($msg); ?></p>
                </div>
            <?php endif; ?>

            <p class="description">
                Developer and advanced tools for resetting Beacon state.
            </p>

            <div style="display:flex; gap:16px; align-items:flex-start;">
                <!-- Left column -->
                <div style="flex:1; min-width:0;">

                    <h2 class="nav-tab-wrapper" style="margin-bottom:12px;">
                        <a href="<?php echo $tabUrl('logs'); ?>"
                           class="nav-tab <?php echo $activeTab === 'logs' ? 'nav-tab-active' : ''; ?>">
                            Logs
                        </a>
                        <a href="<?php echo $tabUrl('deferred'); ?>"
                           class="nav-tab <?php echo $activeTab === 'deferred' ? 'nav-tab-active' : ''; ?>">
                            Deferred Requests
                        </a>
                        <a href="<?php echo $tabUrl('scheduler'); ?>"
                           class="nav-tab <?php echo $activeTab === 'scheduler' ? 'nav-tab-active' : ''; ?>">
                            Scheduled Actions
                        </a>
                        <a href="<?php echo $tabUrl('reports'); ?>"
                           class="nav-tab <?php echo $activeTab === 'reports' ? 'nav-tab-active' : ''; ?>">
                            Report Snapshots
                        </a>
                        <a href="<?php echo $tabUrl('reset'); ?>"
                           class="nav-tab <?php echo $activeTab === 'reset' ? 'nav-tab-active' : ''; ?>">
                            Reset
                        </a>
                    </h2>

                    <?php if ($activeTab === 'logs') : ?>
                        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;">
                            <h2 style="margin-top:0;">Logs</h2>
                            <p class="description">Newest first. Use pagination for older entries.</p>

                            <div style="display:flex;justify-content:flex-end;gap:8px;margin:0 0 10px;">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                      style="margin:0;">
                                    <input type="hidden" name="action"
                                           value="<?php echo esc_attr(DebugViewActionEnum::CLEAR_LOGS); ?>">
                                    <?php wp_nonce_field(DebugViewActionEnum::CLEAR_LOGS); ?>
                                    <?php submit_button('Clear logs', 'secondary', 'submit', false); ?>
                                </form>
                            </div>

                            <form method="get" style="margin:0;">
                                <input type="hidden" name="page" value="<?php echo esc_attr($this->slug()); ?>"/>
                                <input type="hidden" name="<?php echo esc_attr(AdminQueryEnum::TAB); ?>" value="logs"/>
                                <?php $logsTable->display(); ?>
                            </form>
                        </div>

                    <?php elseif ($activeTab === 'deferred') : ?>
                        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;">
                            <h2 style="margin-top:0;">Deferred Requests</h2>
                            <p class="description">
                                Requests queued by 202 Accepted responses. Useful for debugging async tool runs.
                            </p>

                            <form method="get" style="margin:0;">
                                <input type="hidden" name="page" value="<?php echo esc_attr($this->slug()); ?>"/>
                                <input type="hidden" name="<?php echo esc_attr(AdminQueryEnum::TAB); ?>"
                                       value="deferred"/>
                                <?php $deferredTable->display(); ?>
                            </form>
                        </div>

                    <?php elseif ($activeTab === 'scheduler') : ?>
                        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;">
                            <h2 style="margin-top:0;">Scheduled Actions</h2>
                            <p class="description">
                                Action Scheduler entries in group <code>dr-beacon</code>.
                            </p>

                            <div style="display:flex;justify-content:flex-end;gap:8px;margin:0 0 10px;">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                      style="margin:0;">
                                    <input type="hidden" name="action"
                                           value="<?php echo esc_attr(DebugViewActionEnum::CLEAR_SCHEDULER); ?>">
                                    <?php wp_nonce_field(DebugViewActionEnum::CLEAR_SCHEDULER); ?>
                                    <?php submit_button('Clear completed scheduled actions', 'secondary', 'submit', false); ?>
                                </form>
                            </div>

                            <?php if (!function_exists('as_get_scheduled_actions')) : ?>
                                <div class="notice notice-warning" style="margin:0;">
                                    <p style="margin:0;">Action Scheduler not available.</p>
                                </div>
                            <?php else : ?>
                                <form method="get" style="margin:0;">
                                    <input type="hidden" name="page" value="<?php echo esc_attr($this->slug()); ?>"/>
                                    <input type="hidden" name="<?php echo esc_attr(AdminQueryEnum::TAB); ?>"
                                           value="scheduler"/>
                                    <?php $schedulerTable->display(); ?>
                                </form>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($activeTab === 'reports') : ?>
                        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;">
                            <h2 style="margin-top:0;">Report Snapshots</h2>
                            <p class="description">Deletes all rows from the report snapshots table.</p>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                  style="margin:0;">
                                <input type="hidden" name="action"
                                       value="<?php echo esc_attr(DebugViewActionEnum::CLEAR_REPORTS); ?>">
                                <?php wp_nonce_field(DebugViewActionEnum::CLEAR_REPORTS); ?>
                                <?php submit_button('Delete saved report snapshots', 'secondary', 'submit', false); ?>
                            </form>
                        </div>
                    <?php elseif ($activeTab === 'reset') : ?>
                        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;">
                            <h2 style="margin-top:0;">Reset</h2>

                            <h3 style="margin-top:0;">Onboarding state</h3>
                            <p class="description">Resets onboarding status so Beacon returns to the Start scans
                                screen.</p>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                  style="margin:0;">
                                <input type="hidden" name="action"
                                       value="<?php echo esc_attr(DebugViewActionEnum::RESET_STATUS); ?>">
                                <?php wp_nonce_field(DebugViewActionEnum::RESET_STATUS); ?>
                                <?php submit_button('Reset onboarding status', 'secondary', 'submit', false); ?>
                            </form>

                            <hr />

                            <h3 style="margin-top:0;">Action Scheduler</h3>
                            <p class="description">Removes queued Beacon report actions from Action Scheduler.</p>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                  style="margin:0;">
                                <input type="hidden" name="action"
                                       value="<?php echo esc_attr(DebugViewActionEnum::UNSCHEDULE); ?>">
                                <?php wp_nonce_field(DebugViewActionEnum::UNSCHEDULE); ?>
                                <?php submit_button('Unschedule queued report jobs', 'secondary', 'submit', false); ?>
                            </form>

                            <hr />

                            <h3 style="margin-top:0;">Full reset</h3>
                            <p class="description">
                                Clears report snapshots, onboarding state, and queued jobs. Useful for clean testing.
                            </p>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                  style="margin:0;">
                                <input type="hidden" name="action"
                                       value="<?php echo esc_attr(DebugViewActionEnum::FULL_RESET); ?>">
                                <?php wp_nonce_field(DebugViewActionEnum::FULL_RESET); ?>
                                <?php submit_button('Full reset', 'primary', 'submit', false); ?>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right column -->
                <div style="width:400px; flex:0 0 450px;">
                    <?php $this->renderHealthcheck($healthSnapshot); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, array<int, array{label:string, value:string, ok:bool|null, hint?:string}>> $snapshot
     */
    private function renderHealthcheck(array $snapshot): void
    {
        ?>
        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;position:sticky;top:32px;">
            <h2 style="margin-top:0;">Healthcheck</h2>
            <p class="description" style="margin-top:-6px;">
                Compatibility and operational status for this site.
            </p>

            <?php foreach ($snapshot as $section => $rows) : ?>
                <div style="margin-top:14px;">
                    <h3 style="margin:0 0 8px;font-size:13px;"><?php echo esc_html($section); ?></h3>

                    <div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                        <?php foreach ($rows as $i => $row) : ?>
                            <?php
                            $ok = $row['ok'];
                            $bg = ($i % 2 === 0) ? '#fff' : '#fafafa';

                            $badge = '';
                            if ($ok === true) {
                                $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#ecfdf3;border:1px solid #b7ebc6;color:#0f5132;font-size:11px;">OK</span>';
                            } elseif ($ok === false) {
                                $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#fff1f2;border:1px solid #f5c2c7;color:#842029;font-size:11px;">Issue</span>';
                            }
                            ?>
                            <div style="display:flex;gap:10px;justify-content:space-between;align-items:flex-start;padding:10px;background:<?php echo esc_attr($bg); ?>;">
                                <div style="min-width:0;">
                                    <div style="font-size:12px;color:#111827;font-weight:600;">
                                        <?php echo esc_html($row['label']); ?>
                                    </div>
                                    <?php if (!empty($row['hint'])) : ?>
                                        <div style="font-size:12px;color:#6b7280;margin-top:2px;">
                                            <?php echo esc_html((string)$row['hint']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div style="text-align:right;white-space:nowrap;">
                                    <?php if ($badge !== '') : ?>
                                        <div style="margin-bottom:6px;"><?php echo $badge; ?></div>
                                    <?php endif; ?>
                                    <div style="font-size:12px;color:#111827;">
                                        <?php echo esc_html($row['value']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function slug(): string
    {
        return AdminPageEnum::DEBUG;
    }

    public function title(): string
    {
        return 'Debug';
    }

    public function description(): string
    {
        return '';
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
