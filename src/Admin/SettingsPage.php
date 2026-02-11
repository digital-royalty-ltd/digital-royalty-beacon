<?php

namespace DigitalRoyalty\Beacon\Admin;

use DigitalRoyalty\Beacon\Services\ApiClient;
use DigitalRoyalty\Beacon\Admin\Tools\ToolPageInterface;
use DigitalRoyalty\Beacon\Admin\Tools\ContentGeneratorPage;
use DigitalRoyalty\Beacon\Admin\Tools\MetaGeneratorPage;
use DigitalRoyalty\Beacon\Admin\Tools\GapSuggestionsPage;

final class SettingsPage
{
    private const OPTION_API_KEY = 'dr_beacon_api_key';
    private const OPTION_SITE_ID = 'dr_beacon_site_id';
    private const OPTION_CONNECTED_AT = 'dr_beacon_connected_at';

    /** @var ToolPageInterface[] */
    private array $tools;

    public function __construct()
    {
        $this->tools = [
                new ContentGeneratorPage(),
                new MetaGeneratorPage(),
                new GapSuggestionsPage(),
        ];
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);

        add_action('admin_post_dr_beacon_verify_save', [$this, 'handleVerifyAndSave']);
        add_action('admin_post_dr_beacon_disconnect', [$this, 'handleDisconnect']);
    }

    public function addMenu(): void
    {
        add_options_page(
                'Beacon',
                'Beacon',
                'manage_options',
                'dr-beacon',
                [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $apiKey      = (string) get_option(self::OPTION_API_KEY, '');
        $siteId      = (string) get_option(self::OPTION_SITE_ID, '');
        $connectedAt = (string) get_option(self::OPTION_CONNECTED_AT, '');

        $isConnected = ($apiKey !== '' && $siteId !== '');

        $toolSlug = isset($_GET['tool']) ? sanitize_key((string) $_GET['tool']) : '';

        $okParam = isset($_GET['dr_beacon_ok']) ? (string) $_GET['dr_beacon_ok'] : '0';
        $isOk = $okParam === '1';
        $msg = isset($_GET['dr_beacon_msg']) ? (string) $_GET['dr_beacon_msg'] : '';

        ?>
        <div class="wrap">
            <h1>Beacon</h1>

            <?php if ($msg !== '') : ?>
                <div class="notice notice-<?php echo esc_attr($isOk ? 'success' : 'error'); ?> is-dismissible">
                    <p><?php echo esc_html($msg); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$isConnected) : ?>
                <?php $this->renderConnectScreen($apiKey); ?>
            <?php else : ?>
                <?php
                if ($toolSlug !== '') {
                    $tool = $this->findTool($toolSlug);

                    if (!$tool) {
                        $this->renderConnectedHome($siteId, $connectedAt, $apiKey);
                    } else {
                        if (!$tool->isAvailable()) {
                            $this->renderComingSoon($tool);
                        } else {
                            $tool->render();
                        }
                    }
                } else {
                    $this->renderConnectedHome($siteId, $connectedAt, $apiKey);
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
                <input type="hidden" name="action" value="dr_beacon_verify_save" />
                <?php wp_nonce_field('dr_beacon_verify_save'); ?>

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

    private function renderConnectedHome(string $siteId, string $connectedAt, string $apiKey): void
    {
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

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;display:flex;justify-content:flex-start;">
                <input type="hidden" name="action" value="dr_beacon_disconnect" />
                <?php wp_nonce_field('dr_beacon_disconnect'); ?>
                <?php submit_button('Disconnect', 'secondary', 'submit', false); ?>
            </form>

            <p class="description" style="margin-top:10px;">
                Disconnecting removes the saved API key from this WordPress site.
            </p>
        </div>

        <div style="max-width:920px;">
            <h2>Tools</h2>
            <p class="description">
                Manage Beacon features individually. Each tool has its own settings and actions.
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:14px;">
                <?php foreach ($this->tools as $tool) : ?>
                    <?php
                    echo $this->toolCard([
                            'title' => $tool->title(),
                            'description' => $tool->description(),
                            'status' => $tool->isAvailable() ? 'Available' : 'Coming soon',
                            'cta' => $tool->isAvailable() ? 'Open' : 'View',
                            'url' => $this->toolUrl($tool->slug()),
                            'disabled' => !$tool->isAvailable(),
                    ]);
                    ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function renderComingSoon(ToolPageInterface $tool): void
    {
        $backUrl = $this->toolUrl('');
        ?>
        <p><a href="<?php echo esc_url($backUrl); ?>" class="button">← Back to Tools</a></p>
        <h2><?php echo esc_html($tool->title()); ?></h2>
        <p class="description"><?php echo esc_html($tool->description()); ?></p>

        <div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;max-width:920px;">
            <p class="description" style="margin:0;">Coming soon.</p>
        </div>
        <?php
    }

    private function findTool(string $slug): ?ToolPageInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->slug() === $slug) {
                return $tool;
            }
        }
        return null;
    }

    public function handleVerifyAndSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        check_admin_referer('dr_beacon_verify_save');

        $apiKey = isset($_POST['dr_beacon_api_key'])
                ? sanitize_text_field((string) $_POST['dr_beacon_api_key'])
                : '';

        if ($apiKey === '') {
            $this->redirectWithMessage(false, 'API key is required.');
        }

        $client = new ApiClient();
        $res = $client->verifyApiKey($apiKey);

        if (!$res->isOk()) {
            $msg = $res->message ?? ($res->isUnauthorized() ? 'Invalid API key.' : 'API key verification failed.');
            $this->redirectWithMessage(false, $msg);
        }

        $siteId = (string) $res->get('site_id', '');
        if ($siteId === '') {
            $this->redirectWithMessage(false, 'Beacon API did not return a site_id.');
        }

        update_option(self::OPTION_API_KEY, $apiKey, false);
        update_option(self::OPTION_SITE_ID, $siteId, false);
        update_option(self::OPTION_CONNECTED_AT, gmdate('c'), false);

        $this->redirectWithMessage(true, 'Connected successfully.');
    }

    public function handleDisconnect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        check_admin_referer('dr_beacon_disconnect');

        delete_option(self::OPTION_API_KEY);
        delete_option(self::OPTION_SITE_ID);
        delete_option(self::OPTION_CONNECTED_AT);

        $this->redirectWithMessage(true, 'Disconnected.');
    }

    private function redirectWithMessage(bool $ok, string $message): void
    {
        $url = add_query_arg([
                'page' => 'dr-beacon',
                'dr_beacon_ok' => $ok ? '1' : '0',
                'dr_beacon_msg' => rawurlencode($message),
        ], admin_url('options-general.php'));

        wp_safe_redirect($url);
        exit;
    }

    private function toolUrl(string $tool): string
    {
        $args = ['page' => 'dr-beacon'];
        if ($tool !== '') {
            $args['tool'] = $tool;
        }

        return add_query_arg($args, admin_url('options-general.php'));
    }

    /**
     * @param array{
     *   title:string,
     *   description:string,
     *   status:string,
     *   cta:string,
     *   url:string,
     *   disabled?:bool
     * } $tool
     */
    private function toolCard(array $tool): string
    {
        $title = (string) ($tool['title'] ?? '');
        $desc = (string) ($tool['description'] ?? '');
        $status = (string) ($tool['status'] ?? '');
        $cta = (string) ($tool['cta'] ?? 'Open');
        $url = (string) ($tool['url'] ?? '#');
        $disabled = !empty($tool['disabled']);

        $badgeStyle = 'border:1px solid #dcdcde;background:#f6f7f7;color:#646970;';

        $card = '<div style="background:#fff;border:1px solid #ccd0d4;border-radius:10px;padding:16px;display:flex;flex-direction:column;gap:10px;min-height:178px;">';
        $card .= '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">';
        $card .= '<div>';
        $card .= '<h3 style="margin:0 0 6px;font-size:16px;line-height:1.2;">' . esc_html($title) . '</h3>';
        $card .= '<div class="description" style="margin:0;">' . esc_html($desc) . '</div>';
        $card .= '</div>';
        $card .= '<div style="font-size:12px;white-space:nowrap;padding:4px 8px;border-radius:999px;' . esc_attr($badgeStyle) . '">' . esc_html($status) . '</div>';
        $card .= '</div>';

        $card .= '<div style="margin-top:auto;display:flex;justify-content:flex-end;">';

        if ($disabled) {
            $card .= '<span class="button disabled" style="pointer-events:none;opacity:0.65;">' . esc_html($cta) . '</span>';
        } else {
            $card .= '<a href="' . esc_url($url) . '" class="button button-primary" style="text-decoration:none;">' . esc_html($cta) . '</a>';
        }

        $card .= '</div>';
        $card .= '</div>';

        return $card;
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
}
