<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Database\FourOhFourLogsTable;
use DigitalRoyalty\Beacon\Repositories\FourOhFourLogsRepository;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AnnouncementBarEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\CodeInjectionEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\CustomAdminCssEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\CustomLoginUrlEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\LoginBrandingEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\MaintenanceModeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\PostExpiryEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\SiteFilesEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\SmtpEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\WorkshopToggleEnum;
use WP_REST_Request;
use WP_REST_Response;

/**
 * All simple Workshop tool endpoints under beacon/v1/admin/workshop/*.
 */
final class WorkshopController
{
    public function __construct(
        private readonly FourOhFourLogsRepository $fourOhFourRepo
    ) {}

    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        // Toggles
        register_rest_route('beacon/v1', '/admin/workshop/toggles', [
            ['methods' => 'GET',  'callback' => [$this, 'getToggles'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveToggle'],  'permission_callback' => $perm],
        ]);

        // Code Injection
        register_rest_route('beacon/v1', '/admin/workshop/code-injection', [
            ['methods' => 'GET',  'callback' => [$this, 'getCodeInjection'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveCodeInjection'], 'permission_callback' => $perm],
        ]);

        // Admin CSS
        register_rest_route('beacon/v1', '/admin/workshop/admin-css', [
            ['methods' => 'GET',  'callback' => [$this, 'getAdminCss'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveAdminCss'], 'permission_callback' => $perm],
        ]);

        // SMTP
        register_rest_route('beacon/v1', '/admin/workshop/smtp', [
            ['methods' => 'GET',  'callback' => [$this, 'getSmtp'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveSmtp'], 'permission_callback' => $perm],
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/test-email', [
            ['methods' => 'POST', 'callback' => [$this, 'sendTestEmail'], 'permission_callback' => $perm],
        ]);

        // Site Files
        register_rest_route('beacon/v1', '/admin/workshop/robots', [
            ['methods' => 'GET',  'callback' => [$this, 'getRobots'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveRobots'], 'permission_callback' => $perm],
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/htaccess', [
            ['methods' => 'GET',  'callback' => [$this, 'getHtaccess'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveHtaccess'], 'permission_callback' => $perm],
        ]);

        // Database Cleanup
        register_rest_route('beacon/v1', '/admin/workshop/database-cleanup', [
            ['methods' => 'GET',  'callback' => [$this, 'getDbCounts'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'runDbCleanup'], 'permission_callback' => $perm],
        ]);

        // Permalink Flush
        register_rest_route('beacon/v1', '/admin/workshop/permalink-flush', [
            ['methods' => 'POST', 'callback' => [$this, 'flushPermalinks'], 'permission_callback' => $perm],
        ]);

        // Maintenance Mode
        register_rest_route('beacon/v1', '/admin/workshop/maintenance-mode', [
            ['methods' => 'GET',  'callback' => [$this, 'getMaintenanceMode'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveMaintenanceMode'], 'permission_callback' => $perm],
        ]);

        // Custom Login URL
        register_rest_route('beacon/v1', '/admin/workshop/login-url', [
            ['methods' => 'GET',  'callback' => [$this, 'getLoginUrl'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveLoginUrl'], 'permission_callback' => $perm],
        ]);

        // Login Branding
        register_rest_route('beacon/v1', '/admin/workshop/login-branding', [
            ['methods' => 'GET',  'callback' => [$this, 'getLoginBranding'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveLoginBranding'], 'permission_callback' => $perm],
        ]);

        // Announcement Bar
        register_rest_route('beacon/v1', '/admin/workshop/announcement-bar', [
            ['methods' => 'GET',  'callback' => [$this, 'getAnnouncementBar'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveAnnouncementBar'], 'permission_callback' => $perm],
        ]);

        // Post Expiry
        register_rest_route('beacon/v1', '/admin/workshop/post-expiry', [
            ['methods' => 'GET',  'callback' => [$this, 'getPostExpiry'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'setPostExpiry'],  'permission_callback' => $perm],
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/post-expiry/(?P<post_id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'removePostExpiry'], 'permission_callback' => $perm],
        ]);

        // 404 Logs
        register_rest_route('beacon/v1', '/admin/workshop/404-logs', [
            ['methods' => 'GET',    'callback' => [$this, 'get404Logs'],   'permission_callback' => $perm],
            ['methods' => 'DELETE', 'callback' => [$this, 'clear404Logs'], 'permission_callback' => $perm],
        ]);
    }

    // -----------------------------------------------------------------------
    // Toggles
    // -----------------------------------------------------------------------

    public function getToggles(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'svg_support'          => (bool) get_option(WorkshopToggleEnum::SVG_SUPPORT, false),
            'disable_comments'     => (bool) get_option(WorkshopToggleEnum::DISABLE_COMMENTS, false),
            'disable_xmlrpc'       => (bool) get_option(WorkshopToggleEnum::DISABLE_XMLRPC, false),
            'disable_file_editing' => (bool) get_option(WorkshopToggleEnum::DISABLE_FILE_EDITING, false),
            'sanitise_filenames'   => (bool) get_option(WorkshopToggleEnum::SANITISE_FILENAMES, false),
        ], 200);
    }

    public function saveToggle(WP_REST_Request $request): WP_REST_Response
    {
        $params  = (array) $request->get_json_params();
        $key     = sanitize_key((string) ($params['key'] ?? ''));
        $enabled = !empty($params['enabled']);

        if (!in_array($key, WorkshopToggleEnum::allowed(), true)) {
            return new WP_REST_Response(['message' => 'Invalid toggle key.'], 422);
        }

        update_option($key, $enabled, false);

        return new WP_REST_Response(['ok' => true, 'key' => $key, 'enabled' => $enabled], 200);
    }

    // -----------------------------------------------------------------------
    // Code Injection
    // -----------------------------------------------------------------------

    public function getCodeInjection(WP_REST_Request $request): WP_REST_Response
    {
        $snippets = (array) get_option(CodeInjectionEnum::OPTION_SNIPPETS, []);
        return new WP_REST_Response([
            'header' => (string) ($snippets['header'] ?? ''),
            'footer' => (string) ($snippets['footer'] ?? ''),
        ], 200);
    }

    public function saveCodeInjection(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        update_option(CodeInjectionEnum::OPTION_SNIPPETS, [
            'header' => wp_unslash((string) ($params['header'] ?? '')),
            'footer' => wp_unslash((string) ($params['footer'] ?? '')),
        ], false);
        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // Admin CSS
    // -----------------------------------------------------------------------

    public function getAdminCss(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'css' => (string) get_option(CustomAdminCssEnum::OPTION_CSS, ''),
        ], 200);
    }

    public function saveAdminCss(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        update_option(CustomAdminCssEnum::OPTION_CSS, wp_strip_all_tags((string) ($params['css'] ?? '')), false);
        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // SMTP
    // -----------------------------------------------------------------------

    public function getSmtp(WP_REST_Request $request): WP_REST_Response
    {
        $s = (array) get_option(SmtpEnum::OPTION_SETTINGS, []);
        return new WP_REST_Response([
            'host'       => (string) ($s['host']       ?? ''),
            'port'       => (int)    ($s['port']       ?? 587),
            'encryption' => (string) ($s['encryption'] ?? 'tls'),
            'username'   => (string) ($s['username']   ?? ''),
            'from_email' => (string) ($s['from_email'] ?? ''),
            'from_name'  => (string) ($s['from_name']  ?? ''),
        ], 200);
    }

    public function saveSmtp(WP_REST_Request $request): WP_REST_Response
    {
        $p   = (array) $request->get_json_params();
        $cur = (array) get_option(SmtpEnum::OPTION_SETTINGS, []);

        $newPassword = sanitize_text_field((string) ($p['password'] ?? ''));

        update_option(SmtpEnum::OPTION_SETTINGS, [
            'host'       => sanitize_text_field((string) ($p['host']       ?? '')),
            'port'       => (int) ($p['port'] ?? 587),
            'encryption' => sanitize_key((string) ($p['encryption'] ?? 'tls')),
            'username'   => sanitize_text_field((string) ($p['username']   ?? '')),
            'password'   => $newPassword !== '' ? $newPassword : (string) ($cur['password'] ?? ''),
            'from_email' => sanitize_email((string) ($p['from_email'] ?? '')),
            'from_name'  => sanitize_text_field((string) ($p['from_name']  ?? '')),
        ], false);

        return new WP_REST_Response(['ok' => true], 200);
    }

    public function sendTestEmail(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $to     = sanitize_email((string) ($params['to'] ?? get_option('admin_email')));

        if (!is_email($to)) {
            return new WP_REST_Response(['message' => 'Invalid email address.'], 422);
        }

        $sent = wp_mail($to, 'Beacon Test Email', 'This is a test email from the Beacon WordPress plugin.');

        return new WP_REST_Response([
            'ok'      => $sent,
            'message' => $sent ? "Test email sent to {$to}." : 'Failed to send test email. Check your SMTP settings.',
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Robots.txt
    // -----------------------------------------------------------------------

    public function getRobots(WP_REST_Request $request): WP_REST_Response
    {
        $saved = get_option(SiteFilesEnum::OPTION_ROBOTS, null);
        if ($saved === null || $saved === false) {
            $saved = "User-agent: *\nDisallow:";
        }
        return new WP_REST_Response(['content' => (string) $saved], 200);
    }

    public function saveRobots(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        update_option(SiteFilesEnum::OPTION_ROBOTS, wp_unslash((string) ($params['content'] ?? '')), false);
        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // .htaccess
    // -----------------------------------------------------------------------

    public function getHtaccess(WP_REST_Request $request): WP_REST_Response
    {
        $path     = ABSPATH . '.htaccess';
        $writable = is_writable($path);
        $content  = file_exists($path) ? (string) file_get_contents($path) : '';

        return new WP_REST_Response([
            'content'  => $content,
            'writable' => $writable,
        ], 200);
    }

    public function saveHtaccess(WP_REST_Request $request): WP_REST_Response
    {
        $path = ABSPATH . '.htaccess';

        if (!is_writable($path)) {
            return new WP_REST_Response(['message' => '.htaccess is not writable.'], 422);
        }

        $params  = (array) $request->get_json_params();
        $content = wp_unslash((string) ($params['content'] ?? ''));

        file_put_contents($path, $content);

        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // Database Cleanup
    // -----------------------------------------------------------------------

    public function getDbCounts(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        return new WP_REST_Response([
            'revisions'         => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"),
            'auto_drafts'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"),
            'trashed_posts'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'"),
            'spam_comments'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"),
            'trashed_comments'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'"),
            'orphan_postmeta'   => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
            ),
            'orphan_commentmeta' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL"
            ),
            'transients'        => (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s", '_transient_%', '_transient_timeout_%')
            ),
        ], 200);
    }

    public function runDbCleanup(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $params  = (array) $request->get_json_params();
        $type    = sanitize_key((string) ($params['type'] ?? ''));
        $deleted = -1;

        switch ($type) {
            case 'revisions':
                $ids = (array) $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'");
                foreach ($ids as $id) {
                    wp_delete_post_revision((int) $id);
                }
                $deleted = count($ids);
                break;

            case 'auto_drafts':
                $deleted = (int) $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
                break;

            case 'trashed_posts':
                $ids = (array) $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'");
                foreach ($ids as $id) {
                    wp_delete_post((int) $id, true);
                }
                $deleted = count($ids);
                break;

            case 'spam_comments':
                $deleted = (int) $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
                break;

            case 'trashed_comments':
                $deleted = (int) $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'");
                break;

            case 'orphan_postmeta':
                $deleted = (int) $wpdb->query(
                    "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
                );
                break;

            case 'orphan_commentmeta':
                $deleted = (int) $wpdb->query(
                    "DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL"
                );
                break;

            case 'transients':
                $deleted = (int) $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
                        '_transient_%',
                        '_transient_timeout_%'
                    )
                );
                break;
        }

        if ($deleted < 0) {
            return new WP_REST_Response(['message' => 'Unknown cleanup type.'], 422);
        }

        return new WP_REST_Response(['ok' => true, 'deleted' => $deleted], 200);
    }

    // -----------------------------------------------------------------------
    // Permalink Flush
    // -----------------------------------------------------------------------

    public function flushPermalinks(WP_REST_Request $request): WP_REST_Response
    {
        flush_rewrite_rules(true);
        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // Maintenance Mode
    // -----------------------------------------------------------------------

    public function getMaintenanceMode(WP_REST_Request $request): WP_REST_Response
    {
        $s = (array) get_option(MaintenanceModeEnum::OPTION_SETTINGS, []);
        return new WP_REST_Response([
            'enabled' => !empty($s['enabled']),
            'message' => (string) ($s['message'] ?? MaintenanceModeEnum::DEFAULT_MESSAGE),
        ], 200);
    }

    public function saveMaintenanceMode(WP_REST_Request $request): WP_REST_Response
    {
        $p = (array) $request->get_json_params();
        update_option(MaintenanceModeEnum::OPTION_SETTINGS, [
            'enabled' => !empty($p['enabled']),
            'message' => wp_kses_post((string) ($p['message'] ?? '')),
        ], false);
        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // Custom Login URL
    // -----------------------------------------------------------------------

    public function getLoginUrl(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'slug' => (string) get_option(CustomLoginUrlEnum::OPTION_SLUG, ''),
        ], 200);
    }

    public function saveLoginUrl(WP_REST_Request $request): WP_REST_Response
    {
        $params   = (array) $request->get_json_params();
        $slug     = sanitize_title((string) ($params['slug'] ?? ''));
        $reserved = ['admin', 'wp-admin', 'wp-login', 'wp-login.php', 'login', 'dashboard'];

        if (in_array($slug, $reserved, true)) {
            return new WP_REST_Response(['message' => "'{$slug}' is a reserved path."], 422);
        }

        update_option(CustomLoginUrlEnum::OPTION_SLUG, $slug, false);
        return new WP_REST_Response(['ok' => true, 'slug' => $slug], 200);
    }

    // -----------------------------------------------------------------------
    // Login Branding
    // -----------------------------------------------------------------------

    public function getLoginBranding(WP_REST_Request $request): WP_REST_Response
    {
        $s = (array) get_option(LoginBrandingEnum::OPTION_SETTINGS, []);
        return new WP_REST_Response([
            'logo_url'    => (string) ($s['logo_url']    ?? ''),
            'bg_color'    => (string) ($s['bg_color']    ?? ''),
            'bg_image_url'=> (string) ($s['bg_image_url'] ?? ''),
            'custom_css'  => (string) ($s['custom_css']  ?? ''),
        ], 200);
    }

    public function saveLoginBranding(WP_REST_Request $request): WP_REST_Response
    {
        $p = (array) $request->get_json_params();
        update_option(LoginBrandingEnum::OPTION_SETTINGS, [
            'logo_url'     => esc_url_raw((string) ($p['logo_url']    ?? '')),
            'bg_color'     => sanitize_hex_color((string) ($p['bg_color']    ?? '')) ?? '',
            'bg_image_url' => esc_url_raw((string) ($p['bg_image_url'] ?? '')),
            'custom_css'   => wp_strip_all_tags((string) ($p['custom_css']  ?? '')),
        ], false);
        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // Announcement Bar
    // -----------------------------------------------------------------------

    public function getAnnouncementBar(WP_REST_Request $request): WP_REST_Response
    {
        $s = (array) get_option(AnnouncementBarEnum::OPTION_SETTINGS, []);
        return new WP_REST_Response([
            'enabled'     => !empty($s['enabled']),
            'message'     => (string) ($s['message']    ?? ''),
            'link'        => (string) ($s['link']        ?? ''),
            'bg_color'    => (string) ($s['bg_color']    ?? '#1d2327'),
            'text_color'  => (string) ($s['text_color']  ?? '#ffffff'),
            'dismissible' => !empty($s['dismissible']),
        ], 200);
    }

    public function saveAnnouncementBar(WP_REST_Request $request): WP_REST_Response
    {
        $p = (array) $request->get_json_params();
        update_option(AnnouncementBarEnum::OPTION_SETTINGS, [
            'enabled'     => !empty($p['enabled']),
            'message'     => wp_kses((string) ($p['message']   ?? ''), ['a' => ['href' => [], 'target' => []], 'strong' => [], 'em' => []]),
            'link'        => esc_url_raw((string) ($p['link']       ?? '')),
            'bg_color'    => sanitize_hex_color((string) ($p['bg_color']   ?? '#1d2327')) ?? '#1d2327',
            'text_color'  => sanitize_hex_color((string) ($p['text_color'] ?? '#ffffff')) ?? '#ffffff',
            'dismissible' => !empty($p['dismissible']),
        ], false);
        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // Post Expiry
    // -----------------------------------------------------------------------

    public function getPostExpiry(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, p.post_status, p.post_type, pm.meta_value AS expire_at
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
                 WHERE p.post_status IN ('publish','draft','private')
                 ORDER BY pm.meta_value ASC
                 LIMIT 200",
                PostExpiryEnum::META_KEY
            ),
            ARRAY_A
        );

        return new WP_REST_Response(is_array($rows) ? $rows : [], 200);
    }

    public function setPostExpiry(WP_REST_Request $request): WP_REST_Response
    {
        $p       = (array) $request->get_json_params();
        $postId  = (int) ($p['post_id']  ?? 0);
        $expireAt = sanitize_text_field((string) ($p['expire_at'] ?? ''));

        if ($postId <= 0 || !get_post($postId)) {
            return new WP_REST_Response(['message' => 'Invalid post ID.'], 422);
        }

        if ($expireAt === '') {
            return new WP_REST_Response(['message' => 'Expiry date is required.'], 422);
        }

        // Convert datetime-local format (YYYY-MM-DDTHH:MM) to MySQL datetime
        $mysqlDate = str_replace('T', ' ', $expireAt) . ':00';
        update_post_meta($postId, PostExpiryEnum::META_KEY, $mysqlDate);

        return new WP_REST_Response(['ok' => true], 200);
    }

    public function removePostExpiry(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');

        if ($postId <= 0 || !get_post($postId)) {
            return new WP_REST_Response(['message' => 'Invalid post ID.'], 422);
        }

        delete_post_meta($postId, PostExpiryEnum::META_KEY);

        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // 404 Logs
    // -----------------------------------------------------------------------

    public function get404Logs(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->fourOhFourRepo->recent(500), 200);
    }

    public function clear404Logs(WP_REST_Request $request): WP_REST_Response
    {
        $this->fourOhFourRepo->clear();
        return new WP_REST_Response(['ok' => true], 200);
    }
}
