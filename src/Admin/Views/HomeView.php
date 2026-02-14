<?php

namespace DigitalRoyalty\Beacon\Admin\Views;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Admin\Actions\Reports\ReportAdminActions;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;
use DigitalRoyalty\Beacon\Systems\Reports\ReportManager;
use DigitalRoyalty\Beacon\Systems\Reports\ReportRegistry;
use DigitalRoyalty\Beacon\Support\Enums\Admin\HomeViewActionEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\HomeViewOptionEnum;

final class HomeView implements ViewInterface
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $apiKey      = (string) get_option(HomeViewOptionEnum::API_KEY, '');
        $siteId      = (string) get_option(HomeViewOptionEnum::SITE_ID, '');
        $connectedAt = (string) get_option(HomeViewOptionEnum::CONNECTED_AT, '');

        $isConnected = ($apiKey !== '' && $siteId !== '');

        $okParam = isset($_GET['dr_beacon_ok']) ? (string) $_GET['dr_beacon_ok'] : '0';
        $isOk = $okParam === '1';
        $msg = isset($_GET['dr_beacon_msg']) ? (string) $_GET['dr_beacon_msg'] : '';

        ?>
        <div class="wrap">
            <h1><?= $this->title() ?></h1>

            <?php if ($msg !== '') : ?>
                <div class="notice notice-<?php echo esc_attr($isOk ? 'success' : 'error'); ?> is-dismissible">
                    <p><?php echo esc_html($msg); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$isConnected) : ?>
                <?php $this->renderConnectScreen($apiKey); ?>
            <?php else : ?>
                <?php
                // Onboarding gating is now driven by reports system, not legacy "has scan" option.
                $isOnboardingComplete = $this->isReportsOnboardingComplete();

                if (!$isOnboardingComplete) {
                    $this->renderReportsOnboarding($siteId, $connectedAt, $apiKey);
                } else {
                    $this->renderGeneralHome($siteId, $connectedAt, $apiKey);
                }
                ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderConnectScreen(string $apiKey): void
    {
        ?>
        <p><strong>Status:</strong> <?php echo esc_html('Not connected'); ?></p>

        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
            <h2 style="margin-top:0;">Connect Beacon</h2>
            <p class="description" style="margin-bottom:14px;">
                Paste an API key from your Digital Royalty dashboard. Beacon will verify the key before saving it.
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(HomeViewActionEnum::VERIFY_SAVE); ?>" />
                <?php wp_nonce_field(HomeViewActionEnum::VERIFY_SAVE); ?>

                <table class="form-table" role="presentation" style="margin-top:0;">
                    <tr>
                        <th scope="row"><label for="dr_beacon_api_key">API Key</label></th>
                        <td>
                            <input
                                    name="dr_beacon_api_key"
                                    id="dr_beacon_api_key"
                                    type="password"
                                    class="regular-text"
                                    value="<?php echo esc_attr($apiKey); ?>"
                                    autocomplete="off"
                            />
                            <p class="description">Click Verify &amp; Save to connect.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Verify & Save'); ?>
            </form>
        </div>
        <?php
    }

    private function renderReportsOnboarding(string $siteId, string $connectedAt, string $apiKey): void
    {
        global $wpdb;

        $repo = new ReportsRepository($wpdb);
        $registry = new ReportRegistry();
        $required = $registry->required();

        $manager = $this->reportsManager();
        $effectiveStatus = $manager->getEffectiveStatus();

        ?>
        <p><strong>Status:</strong> <?php echo esc_html('Connected'); ?></p>

        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;margin-bottom:16px;">
            <h2 style="margin-top:0;">Connection</h2>

            <p style="margin:0 0 10px;">
                <strong>Site ID:</strong> <code><?php echo esc_html($siteId); ?></code>
            </p>

            <?php if ($connectedAt !== '') : ?>
                <p style="margin:0 0 10px;">
                    <strong>Connected at:</strong> <code><?php echo esc_html($connectedAt); ?></code>
                </p>
            <?php endif; ?>

            <p style="margin:0 0 14px;">
                <strong>API key:</strong> <code><?php echo esc_html($this->maskKey($apiKey)); ?></code>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;display:flex;justify-content:flex-end;gap:8px;">
                <input type="hidden" name="action" value="<?php echo esc_attr(HomeViewActionEnum::DISCONNECT); ?>" />
                <?php wp_nonce_field(HomeViewActionEnum::DISCONNECT); ?>
                <?php submit_button('Disconnect', 'secondary', 'submit', false); ?>
            </form>

            <p class="description" style="margin-top:10px;">
                Disconnecting removes the saved API key from this WordPress site.
            </p>
        </div>

        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
            <h2 style="margin-top:0;">Get started</h2>

            <p class="description" style="margin-bottom:14px;">
                Beacon needs an initial set of reports to understand your website. Once complete, your tools will appear.
            </p>

            <?php if ($effectiveStatus === ReportManager::STATUS_NOT_STARTED) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0 0 14px;">
                    <input type="hidden" name="action" value="<?php echo esc_attr(ReportAdminActions::ACTION_START); ?>" />
                    <?php wp_nonce_field(ReportAdminActions::ACTION_START); ?>
                    <?php submit_button('Start scans', 'primary', 'submit', false); ?>
                </form>
                <?php elseif ($effectiveStatus === ReportManager::STATUS_RUNNING) : ?>
                <div class="notice notice-info" style="margin:0 0 12px;">
                    <p style="margin:0;">Scans are running in the background. You can leave this page and come back.</p>
                </div>
            <?php else : ?>
                <div class="notice notice-warning" style="margin:0 0 12px;">
                    <p style="margin:0;">Scans require attention. Review the failures below and retry.</p>
                </div>
            <?php endif; ?>

            <table class="widefat striped" style="margin-top:10px;">
                <thead>
                <tr>
                    <th>Report</th>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Last error</th>
                    <th style="width:220px;">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($required as $report) : ?>
                    <?php
                    $type = $report->type();
                    $version = $report->version();
                    $row = $repo->getByTypeAndVersion($type, $version);

                    $rowStatus = is_array($row) ? (string) ($row['status'] ?? 'pending') : 'pending';
                    $lastError = is_array($row) ? (string) ($row['last_error'] ?? '') : '';

                    $badge = $this->statusBadge($rowStatus);
                    ?>
                    <tr>
                        <td><code><?php echo esc_html($type); ?></code></td>
                        <td><?php echo esc_html((string) $version); ?></td>
                        <td><?php echo $badge; ?></td>
                        <td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?php echo esc_html($lastError); ?>
                        </td>
                        <td>
                            <?php if ($rowStatus === 'submitted') : ?>
                                <span class="button disabled" style="pointer-events:none;opacity:0.65;">Complete</span>
                            <?php else : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;display:inline;">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(ReportAdminActions::ACTION_RERUN); ?>" />
                                    <input type="hidden" name="type" value="<?php echo esc_attr($type); ?>" />
                                    <input type="hidden" name="version" value="<?php echo esc_attr((string) $version); ?>" />
                                    <?php wp_nonce_field(ReportAdminActions::ACTION_RERUN); ?>
                                    <?php submit_button('Retry', 'secondary', 'submit', false); ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description" style="margin-top:12px;">
                Until the API endpoint is implemented, submissions will fail by design. This screen helps validate reliability, retries, and error reporting.
            </p>
        </div>
        <?php
    }

    private function renderGeneralHome(string $siteId, string $connectedAt, string $apiKey): void
    {
        $toolsUrl = add_query_arg(['page' => AdminPageEnum::TOOLS], admin_url('admin.php'));
        ?>
        <p><strong>Status:</strong> <?php echo esc_html('Connected'); ?></p>

        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;margin-bottom:16px;">
            <h2 style="margin-top:0;">Connection</h2>

            <p style="margin:0 0 10px;">
                <strong>Site ID:</strong> <code><?php echo esc_html($siteId); ?></code>
            </p>

            <?php if ($connectedAt !== '') : ?>
                <p style="margin:0 0 10px;">
                    <strong>Connected at:</strong> <code><?php echo esc_html($connectedAt); ?></code>
                </p>
            <?php endif; ?>

            <p style="margin:0 0 14px;">
                <strong>API key:</strong> <code><?php echo esc_html($this->maskKey($apiKey)); ?></code>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;display:flex;justify-content:flex-end;gap:8px;">
                <input type="hidden" name="action" value="<?php echo esc_attr(HomeViewActionEnum::DISCONNECT); ?>" />
                <?php wp_nonce_field(HomeViewActionEnum::DISCONNECT); ?>
                <?php submit_button('Disconnect', 'secondary', 'submit', false); ?>
            </form>

            <p class="description" style="margin-top:10px;">
                Disconnecting removes the saved API key from this WordPress site.
            </p>
        </div>

        <div style="max-width:920px;">
            <h2>Next</h2>
            <p class="description">Open Tools to generate content, metadata, and more.</p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url($toolsUrl); ?>">Open Tools</a>
            </p>
        </div>
        <?php
    }

    private function isReportsOnboardingComplete(): bool
    {
        return $this->reportsManager()->getEffectiveStatus() === ReportManager::STATUS_COMPLETED;
    }


    private function statusBadge(string $status): string
    {
        $status = strtolower(trim($status));

        $styles = [
                'pending' => 'border:1px solid #dcdcde;background:#f6f7f7;color:#646970;',
                'generated' => 'border:1px solid #8c8f94;background:#f6f7f7;color:#1d2327;',
                'submitted' => 'border:1px solid #00a32a;background:#edfaef;color:#0a4b1f;',
                'failed' => 'border:1px solid #d63638;background:#fcf0f1;color:#8a1f2b;',
        ];

        $label = $status !== '' ? ucfirst($status) : 'Unknown';
        $style = $styles[$status] ?? $styles['pending'];

        return '<span style="display:inline-block;font-size:12px;white-space:nowrap;padding:4px 8px;border-radius:999px;' . esc_attr($style) . '">' . esc_html($label) . '</span>';
    }


    private function maskKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }

        $len = strlen($key);
        if ($len <= 10) {
            return str_repeat('*', $len);
        }

        $start = substr($key, 0, 6);
        $end = substr($key, -4);

        return $start . str_repeat('*', max(0, $len - 10)) . $end;
    }

    private function reportsManager(): ReportManager
    {
        global $wpdb;

        return new ReportManager(
                new ReportRegistry(),
                new ReportsRepository($wpdb)
        );
    }

    public function slug(): string
    {
        return AdminPageEnum::HOME;
    }

    public function title(): string
    {
        return 'General';
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