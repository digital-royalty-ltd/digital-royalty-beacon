<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Database\FourOhFourLogsTable;
use DigitalRoyalty\Beacon\Repositories\FourOhFourLogsRepository;
use DigitalRoyalty\Beacon\Support\Enums\Admin\AnnouncementBarEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\CodeInjectionEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\CustomAdminCssEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\CustomLoginUrlEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\DatabaseCleanupEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\LoginBrandingEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\MaintenanceModeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\PostExpiryEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\SiteFilesEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\SmtpEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\WorkshopToggleEnum;
use DigitalRoyalty\Beacon\Systems\Workshop\CustomLoginUrlHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\DatabaseCleanupHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\DisableCommentsHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\DisableFileEditingHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\DisableXmlRpcHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\SanitiseFilenamesHandler;
use DigitalRoyalty\Beacon\Systems\Workshop\SvgSupportHandler;
use WP_REST_Request;
use WP_REST_Response;

/**
 * All simple Workshop tool endpoints under beacon/v1/admin/workshop/*.
 */
final class WorkshopController
{
    private const OPTION_404_EXCLUSIONS = 'dr_beacon_404_exclusions';
    private const OPTION_SMTP_TEST_LOG  = 'dr_beacon_smtp_test_log';

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
        register_rest_route('beacon/v1', '/admin/workshop/toggles/xmlrpc-test', [
            ['methods' => 'POST', 'callback' => [$this, 'testXmlRpc'], 'permission_callback' => $perm],
        ]);

        // Code Injection
        register_rest_route('beacon/v1', '/admin/workshop/code-injection', [
            ['methods' => 'GET',  'callback' => [$this, 'getCodeInjection'],  'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveCodeInjection'], 'permission_callback' => $perm],
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/code-injection/restore', [
            ['methods' => 'POST', 'callback' => [$this, 'restoreCodeInjectionRevision'], 'permission_callback' => $perm],
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
        register_rest_route('beacon/v1', '/admin/workshop/robots/test', [
            ['methods' => 'GET',  'callback' => [$this, 'testRobots'], 'permission_callback' => $perm],
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
        register_rest_route('beacon/v1', '/admin/workshop/database-cleanup/settings', [
            ['methods' => 'POST', 'callback' => [$this, 'saveDbCleanupSettings'], 'permission_callback' => $perm],
        ]);

        // Permalink Flush
        register_rest_route('beacon/v1', '/admin/workshop/permalink-flush', [
            ['methods' => 'GET',  'callback' => [$this, 'flushPermalinks'], 'permission_callback' => $perm],
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
        register_rest_route('beacon/v1', '/admin/workshop/404-logs/export', [
            ['methods' => 'GET', 'callback' => [$this, 'export404Logs'], 'permission_callback' => $perm],
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/404-logs/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delete404Log'], 'permission_callback' => $perm],
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/404-logs/settings', [
            ['methods' => 'POST', 'callback' => [$this, 'save404Settings'], 'permission_callback' => $perm],
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/404-logs/redirect', [
            ['methods' => 'POST', 'callback' => [$this, 'createRedirectFrom404'], 'permission_callback' => $perm],
        ]);
    }

    // -----------------------------------------------------------------------
    // Toggles
    // -----------------------------------------------------------------------

    public function getToggles(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'available_roles'       => $this->getAvailableRoles(),
            'available_post_types'  => $this->getCommentablePostTypes(),
            'svg_support'          => $this->getSvgSupportData(),
            'disable_comments'     => $this->getDisableCommentsData(),
            'disable_xmlrpc'       => $this->getDisableXmlRpcData(),
            'disable_file_editing' => $this->getDisableFileEditingData(),
            'sanitise_filenames'   => $this->getSanitiseFilenamesData(),
        ], 200);
    }

    public function saveToggle(WP_REST_Request $request): WP_REST_Response
    {
        $params  = (array) $request->get_json_params();
        $key     = $this->mapToggleKey(sanitize_key((string) ($params['key'] ?? '')));
        $enabled = !empty($params['enabled']);
        $settings = (array) ($params['settings'] ?? []);

        if (!in_array($key, WorkshopToggleEnum::allowed(), true)) {
            return new WP_REST_Response(['message' => 'Invalid toggle key.'], 422);
        }

        update_option($key, $enabled, false);

        switch ($key) {
            case WorkshopToggleEnum::SVG_SUPPORT:
                update_option(WorkshopToggleEnum::SVG_SUPPORT_SETTINGS, [
                    'allowed_roles'    => array_values(array_filter(array_map('sanitize_key', (array) ($settings['allowed_roles'] ?? ['administrator'])))),
                    'inline_rendering' => !empty($settings['inline_rendering']),
                ], false);
                break;

            case WorkshopToggleEnum::DISABLE_COMMENTS:
                update_option(WorkshopToggleEnum::DISABLE_COMMENTS_SETTINGS, [
                    'mode'       => ($settings['mode'] ?? 'all') === 'selected' ? 'selected' : 'all',
                    'post_types' => array_values(array_filter(array_map('sanitize_key', (array) ($settings['post_types'] ?? [])))),
                ], false);
                break;

            case WorkshopToggleEnum::DISABLE_XMLRPC:
                update_option(WorkshopToggleEnum::DISABLE_XMLRPC_SETTINGS, [
                    'mode' => ($settings['mode'] ?? 'full') === 'pingback' ? 'pingback' : 'full',
                ], false);
                break;

            case WorkshopToggleEnum::DISABLE_FILE_EDITING:
                update_option(WorkshopToggleEnum::DISABLE_FILE_EDITING_SETTINGS, [
                    'mode' => ($settings['mode'] ?? 'editor') === 'mods' ? 'mods' : 'editor',
                ], false);
                break;

            case WorkshopToggleEnum::SANITISE_FILENAMES:
                update_option(WorkshopToggleEnum::SANITISE_FILENAMES_SETTINGS, [
                    'lowercase'     => !array_key_exists('lowercase', $settings) || !empty($settings['lowercase']),
                    'transliterate' => !array_key_exists('transliterate', $settings) || !empty($settings['transliterate']),
                    'separator'     => ($settings['separator'] ?? 'hyphen') === 'underscore' ? 'underscore' : 'hyphen',
                ], false);
                break;
        }

        return new WP_REST_Response(['ok' => true, 'key' => $key, 'enabled' => $enabled], 200);
    }

    public function testXmlRpc(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->runXmlRpcTest(), 200);
    }

    // -----------------------------------------------------------------------
    // Code Injection
    // -----------------------------------------------------------------------

    public function getCodeInjection(WP_REST_Request $request): WP_REST_Response
    {
        $snippets = $this->normalizeCodeInjection((array) get_option(CodeInjectionEnum::OPTION_SNIPPETS, []));
        return new WP_REST_Response([
            'header'  => $snippets['header'],
            'footer'  => $snippets['footer'],
            'history' => $snippets['history'],
        ], 200);
    }

    public function saveCodeInjection(WP_REST_Request $request): WP_REST_Response
    {
        $params   = (array) $request->get_json_params();
        $existing = $this->normalizeCodeInjection((array) get_option(CodeInjectionEnum::OPTION_SNIPPETS, []));

        $header = $this->sanitizeCodeInjectionSlot((array) ($params['header'] ?? []), $existing['header']);
        $footer = $this->sanitizeCodeInjectionSlot((array) ($params['footer'] ?? []), $existing['footer']);
        $savedAt = current_time('mysql');

        $history = $existing['history'];
        array_unshift($history, [
            'saved_at' => $savedAt,
            'header'   => $existing['header'],
            'footer'   => $existing['footer'],
        ]);
        $history = array_slice($history, 0, 5);

        $header['updated_at'] = $savedAt;
        $footer['updated_at'] = $savedAt;

        update_option(CodeInjectionEnum::OPTION_SNIPPETS, [
            'header'  => $header,
            'footer'  => $footer,
            'history' => $history,
        ], false);
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function restoreCodeInjectionRevision(WP_REST_Request $request): WP_REST_Response
    {
        $params   = (array) $request->get_json_params();
        $savedAt  = sanitize_text_field((string) ($params['saved_at'] ?? ''));
        $existing = $this->normalizeCodeInjection((array) get_option(CodeInjectionEnum::OPTION_SNIPPETS, []));

        if ($savedAt === '') {
            return new WP_REST_Response(['message' => 'saved_at is required.'], 422);
        }

        foreach ($existing['history'] as $revision) {
            if ((string) ($revision['saved_at'] ?? '') !== $savedAt) {
                continue;
            }

            $restoredAt = current_time('mysql');
            $header = $this->normalizeCodeInjectionSlot((array) ($revision['header'] ?? []));
            $footer = $this->normalizeCodeInjectionSlot((array) ($revision['footer'] ?? []));
            $history = $existing['history'];
            array_unshift($history, [
                'saved_at' => $restoredAt,
                'header'   => $existing['header'],
                'footer'   => $existing['footer'],
            ]);

            $header['updated_at'] = $restoredAt;
            $footer['updated_at'] = $restoredAt;

            update_option(CodeInjectionEnum::OPTION_SNIPPETS, [
                'header'  => $header,
                'footer'  => $footer,
                'history' => array_slice($history, 0, 5),
            ], false);

            return new WP_REST_Response(['ok' => true, 'restored_at' => $restoredAt], 200);
        }

        return new WP_REST_Response(['message' => 'Revision not found.'], 404);
    }

    // -----------------------------------------------------------------------
    // Admin CSS
    // -----------------------------------------------------------------------

    public function getAdminCss(WP_REST_Request $request): WP_REST_Response
    {
        $settings = get_option(CustomAdminCssEnum::OPTION_CSS, []);

        if (is_string($settings)) {
            $settings = [
                'css'        => $settings,
                'updated_at' => '',
            ];
        } elseif (!is_array($settings)) {
            $settings = [
                'css'        => '',
                'updated_at' => '',
            ];
        }

        return new WP_REST_Response([
            'css'        => (string) ($settings['css'] ?? ''),
            'updated_at' => (string) ($settings['updated_at'] ?? ''),
        ], 200);
    }

    public function saveAdminCss(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();

        if (!empty($params['reset'])) {
            update_option(CustomAdminCssEnum::OPTION_CSS, [
                'css'        => '',
                'updated_at' => current_time('mysql'),
            ], false);

            return new WP_REST_Response(['ok' => true, 'reset' => true], 200);
        }

        update_option(CustomAdminCssEnum::OPTION_CSS, [
            'css'        => wp_unslash((string) ($params['css'] ?? '')),
            'updated_at' => current_time('mysql'),
        ], false);
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
            'force_from' => !empty($s['force_from']),
            'has_password' => !empty($s['password']),
            'last_test'  => get_option(self::OPTION_SMTP_TEST_LOG, []),
            'mail_log'   => (array) get_option('dr_beacon_smtp_mail_log', []),
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
            'force_from' => !empty($p['force_from']),
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
        $log = [
            'ok'        => $sent,
            'to'        => $to,
            'sent_at'   => current_time('mysql'),
            'host'      => (string) (((array) get_option(SmtpEnum::OPTION_SETTINGS, []))['host'] ?? ''),
            'message'   => $sent ? "Test email sent to {$to}." : 'Failed to send test email. Check your SMTP settings.',
        ];
        update_option(self::OPTION_SMTP_TEST_LOG, $log, false);

        return new WP_REST_Response([
            'ok'      => $sent,
            'message' => (string) $log['message'],
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

        $physicalPath   = ABSPATH . 'robots.txt';
        $physicalExists = file_exists($physicalPath);
        $physicalContent = $physicalExists ? (string) file_get_contents($physicalPath) : '';

        return new WP_REST_Response([
            'content'          => (string) $saved,
            'physical_exists'  => $physicalExists,
            'physical_content' => $physicalContent,
            'effective_source' => $physicalExists ? 'physical' : 'virtual',
            'default_content'  => "User-agent: *\nDisallow:",
            'served_url'       => home_url('/robots.txt'),
        ], 200);
    }

    public function testRobots(WP_REST_Request $request): WP_REST_Response
    {
        $url = home_url('/robots.txt');
        $response = wp_remote_get($url, ['timeout' => 8, 'redirection' => 3]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'ok'      => false,
                'url'     => $url,
                'status'  => 0,
                'content' => '',
                'message' => $response->get_error_message(),
            ], 200);
        }

        return new WP_REST_Response([
            'ok'      => true,
            'url'     => $url,
            'status'  => (int) wp_remote_retrieve_response_code($response),
            'content' => (string) wp_remote_retrieve_body($response),
            'message' => 'Fetched live robots.txt response.',
        ], 200);
    }

    public function saveRobots(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();

        if (!empty($params['reset'])) {
            update_option(SiteFilesEnum::OPTION_ROBOTS, "User-agent: *\nDisallow:", false);
            return new WP_REST_Response(['ok' => true, 'reset' => true], 200);
        }

        $content = trim(wp_unslash((string) ($params['content'] ?? '')));

        if ($content === '' || stripos($content, 'User-agent:') !== 0) {
            return new WP_REST_Response(['message' => 'robots.txt must begin with a User-agent directive.'], 422);
        }

        update_option(SiteFilesEnum::OPTION_ROBOTS, $content, false);
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
        $backup   = (array) get_option(SiteFilesEnum::OPTION_HTACCESS_BACKUP, []);

        return new WP_REST_Response([
            'content'         => $content,
            'writable'        => $writable,
            'backup_content'  => (string) ($backup['content'] ?? ''),
            'backup_at'       => (string) ($backup['saved_at'] ?? ''),
            'has_wp_rewrites' => strpos($content, '# BEGIN WordPress') !== false && strpos($content, '# END WordPress') !== false,
        ], 200);
    }

    public function saveHtaccess(WP_REST_Request $request): WP_REST_Response
    {
        $path = ABSPATH . '.htaccess';

        if (!is_writable($path)) {
            return new WP_REST_Response(['message' => '.htaccess is not writable.'], 422);
        }

        $params  = (array) $request->get_json_params();
        if (!empty($params['restore_backup'])) {
            $backup = (array) get_option(SiteFilesEnum::OPTION_HTACCESS_BACKUP, []);
            $content = (string) ($backup['content'] ?? '');

            if ($content === '') {
                return new WP_REST_Response(['message' => 'No backup is available to restore.'], 422);
            }

            file_put_contents($path, $content);
            return new WP_REST_Response(['ok' => true, 'restored' => true], 200);
        }

        $content = wp_unslash((string) ($params['content'] ?? ''));
        $current = file_exists($path) ? (string) file_get_contents($path) : '';
        $confirmed = !empty($params['confirm_write']);

        update_option(SiteFilesEnum::OPTION_HTACCESS_BACKUP, [
            'content'  => $current,
            'saved_at' => current_time('mysql'),
        ], false);

        $requiresWordPressMarkers = !empty($params['require_wp_rewrites']);

        if ($requiresWordPressMarkers && (strpos($content, '# BEGIN WordPress') === false || strpos($content, '# END WordPress') === false)) {
            return new WP_REST_Response(['message' => 'WordPress rewrite markers are missing from the proposed .htaccess file.'], 422);
        }

        if (!$confirmed) {
            return new WP_REST_Response(['message' => 'Confirm the final .htaccess review before saving.'], 422);
        }

        file_put_contents($path, $content);

        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // Database Cleanup
    // -----------------------------------------------------------------------

    public function getDbCounts(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $counts = [
            'revisions'          => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"),
            'auto_drafts'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"),
            'trashed_posts'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'"),
            'spam_comments'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"),
            'trashed_comments'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'"),
            'orphan_postmeta'    => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
            ),
            'orphan_commentmeta' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL"
            ),
            'transients'         => (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s", '_transient_%', '_transient_timeout_%')
            ),
        ];

        $estimatePerItem = [
            'revisions'          => 4096,
            'auto_drafts'        => 2048,
            'trashed_posts'      => 3072,
            'spam_comments'      => 1024,
            'trashed_comments'   => 1024,
            'orphan_postmeta'    => 512,
            'orphan_commentmeta' => 512,
            'transients'         => 768,
        ];

        $estimatedSavings = [];
        foreach ($counts as $key => $count) {
            $estimatedSavings[$key] = $count * ($estimatePerItem[$key] ?? 0);
        }

        $settings = DatabaseCleanupHandler::settings();

        return new WP_REST_Response([
            'counts'            => $counts,
            'estimated_savings' => $estimatedSavings,
            'table_sizes'       => $this->getDatabaseCleanupTableSizes(),
            'settings'          => [
                'enabled'  => !empty($settings['enabled']),
                'frequency'=> (string) $settings['frequency'],
                'types'    => $settings['types'],
                'next_run' => wp_next_scheduled(DatabaseCleanupEnum::CRON_HOOK)
                    ? wp_date('Y-m-d H:i:s', (int) wp_next_scheduled(DatabaseCleanupEnum::CRON_HOOK))
                    : null,
            ],
        ], 200);
    }

    public function runDbCleanup(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $params  = (array) $request->get_json_params();
        $type    = sanitize_key((string) ($params['type'] ?? ''));
        $dryRun  = !empty($params['dry_run']);
        $confirm = !empty($params['confirm']);
        $deleted = -1;

        $countData = (array) $this->getDbCounts($request)->get_data();
        $counts = (array) ($countData['counts'] ?? []);

        if (!isset($counts[$type])) {
            return new WP_REST_Response(['message' => 'Unknown cleanup type.'], 422);
        }

        if ($dryRun || !$confirm) {
            return new WP_REST_Response([
                'ok'      => true,
                'dry_run' => true,
                'type'    => $type,
                'matched' => (int) $counts[$type],
                'message' => 'Dry run only. Send confirm=true to perform the cleanup.',
            ], 200);
        }

        $beforeSizes = $this->getTableSizes($wpdb);

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

        $afterSizes = $this->getTableSizes($wpdb);
        $reclaimed = 0;
        foreach ($beforeSizes as $table => $size) {
            $reclaimed += max(0, $size - ($afterSizes[$table] ?? 0));
        }

        return new WP_REST_Response(['ok' => true, 'deleted' => $deleted, 'reclaimed_bytes' => $reclaimed], 200);
    }

    public function saveDbCleanupSettings(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $types = array_values(array_filter(array_map('sanitize_key', (array) ($params['types'] ?? []))));
        $frequency = ($params['frequency'] ?? 'weekly') === 'daily' ? 'daily' : 'weekly';

        update_option(DatabaseCleanupEnum::OPTION_SETTINGS, [
            'enabled'   => !empty($params['enabled']),
            'frequency' => $frequency,
            'types'     => $types,
        ], false);

        (new DatabaseCleanupHandler())->syncSchedule();

        return new WP_REST_Response([
            'ok'       => true,
            'enabled'  => !empty($params['enabled']),
            'frequency'=> $frequency,
            'types'    => $types,
            'next_run' => wp_next_scheduled(DatabaseCleanupEnum::CRON_HOOK)
                ? wp_date('Y-m-d H:i:s', (int) wp_next_scheduled(DatabaseCleanupEnum::CRON_HOOK))
                : null,
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Permalink Flush
    // -----------------------------------------------------------------------

    public function flushPermalinks(WP_REST_Request $request): WP_REST_Response
    {
        if ($request->get_method() === 'GET') {
            return new WP_REST_Response([
                'structure'    => (string) get_option('permalink_structure', ''),
                'settings_url' => admin_url('options-permalink.php'),
                'guidance'     => 'Flush rewrite rules after registering or removing post types, taxonomies, or custom rewrites. Use Settings > Permalinks for structural changes.',
                'rule_count'   => count((array) $this->getRewriteRulesPreview()),
                'rules'        => array_slice($this->getRewriteRulesPreview(), 0, 10, true),
            ], 200);
        }

        flush_rewrite_rules(true);
        return new WP_REST_Response([
            'ok'         => true,
            'flushed_at' => current_time('mysql'),
            'structure'  => (string) get_option('permalink_structure', ''),
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Maintenance Mode
    // -----------------------------------------------------------------------

    public function getMaintenanceMode(WP_REST_Request $request): WP_REST_Response
    {
        $s = (array) get_option(MaintenanceModeEnum::OPTION_SETTINGS, []);
        $bypassToken = $this->ensureMaintenanceBypassToken();

        return new WP_REST_Response([
            'enabled'            => !empty($s['enabled']),
            'headline'           => (string) ($s['headline'] ?? 'Scheduled Maintenance'),
            'message'            => (string) ($s['message'] ?? MaintenanceModeEnum::DEFAULT_MESSAGE),
            'return_date'        => (string) ($s['return_date'] ?? ''),
            'bg_color'           => (string) ($s['bg_color'] ?? '#f6f1fb'),
            'bg_image_url'       => (string) ($s['bg_image_url'] ?? ''),
            'response_code'      => (int) ($s['response_code'] ?? 503),
            'allowed_capability' => sanitize_key((string) ($s['allowed_capability'] ?? 'manage_options')),
            'bypass_url'         => add_query_arg(MaintenanceModeEnum::BYPASS_QUERY_ARG, $bypassToken, home_url('/')),
            'preview_url'        => add_query_arg(MaintenanceModeEnum::PREVIEW_QUERY_ARG, '1', home_url('/')),
            'available_capabilities' => $this->getMaintenanceCapabilities(),
        ], 200);
    }

    public function saveMaintenanceMode(WP_REST_Request $request): WP_REST_Response
    {
        $p = (array) $request->get_json_params();
        $responseCode = (int) ($p['response_code'] ?? 503);
        $allowedCapability = sanitize_key((string) ($p['allowed_capability'] ?? 'manage_options'));

        update_option(MaintenanceModeEnum::OPTION_SETTINGS, [
            'enabled'            => !empty($p['enabled']),
            'headline'           => sanitize_text_field((string) ($p['headline'] ?? 'Scheduled Maintenance')),
            'message'            => wp_kses_post((string) ($p['message'] ?? '')),
            'return_date'        => sanitize_text_field((string) ($p['return_date'] ?? '')),
            'bg_color'           => sanitize_hex_color((string) ($p['bg_color'] ?? '#f6f1fb')) ?? '#f6f1fb',
            'bg_image_url'       => esc_url_raw((string) ($p['bg_image_url'] ?? '')),
            'response_code'      => in_array($responseCode, [200, 503], true) ? $responseCode : 503,
            'allowed_capability' => $allowedCapability !== '' ? $allowedCapability : 'manage_options',
        ], false);
        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // Custom Login URL
    // -----------------------------------------------------------------------

    public function getLoginUrl(WP_REST_Request $request): WP_REST_Response
    {
        $slug = (string) get_option(CustomLoginUrlEnum::OPTION_SLUG, '');
        $recoveryKey = CustomLoginUrlHandler::ensureRecoveryKey();

        return new WP_REST_Response([
            'slug'       => $slug,
            'login_url'  => $slug !== '' ? home_url('/' . $slug . '/') : wp_login_url(),
            'site_url'   => home_url('/'),
            'blocks_default_login' => $slug !== '',
            'recovery_url' => add_query_arg('beacon_login_recovery', $recoveryKey, home_url('/')),
            'conflicts'    => $this->detectLoginUrlConflicts(),
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

        if ($slug !== '' && get_page_by_path($slug, OBJECT, ['page', 'post']) !== null) {
            return new WP_REST_Response(['message' => 'That slug is already used by existing content.'], 422);
        }

        update_option(CustomLoginUrlEnum::OPTION_SLUG, $slug, false);
        CustomLoginUrlHandler::ensureRecoveryKey();
        return new WP_REST_Response(['ok' => true, 'slug' => $slug], 200);
    }

    // -----------------------------------------------------------------------
    // Login Branding
    // -----------------------------------------------------------------------

    public function getLoginBranding(WP_REST_Request $request): WP_REST_Response
    {
        $s = (array) get_option(LoginBrandingEnum::OPTION_SETTINGS, []);
        return new WP_REST_Response([
            'logo_url'       => (string) ($s['logo_url']      ?? ''),
            'bg_color'       => (string) ($s['bg_color']      ?? '#f0f0f1'),
            'bg_image_url'   => (string) ($s['bg_image_url']  ?? ''),
            'logo_link_url'  => (string) ($s['logo_link_url'] ?? home_url('/')),
            'logo_alt_text'  => (string) ($s['logo_alt_text'] ?? get_bloginfo('name')),
            'custom_css'     => (string) ($s['custom_css']    ?? ''),
            'preview_url'    => wp_login_url(),
        ], 200);
    }

    public function saveLoginBranding(WP_REST_Request $request): WP_REST_Response
    {
        $p = (array) $request->get_json_params();
        update_option(LoginBrandingEnum::OPTION_SETTINGS, [
            'logo_url'      => esc_url_raw((string) ($p['logo_url']      ?? '')),
            'bg_color'      => sanitize_hex_color((string) ($p['bg_color']      ?? '')) ?? '',
            'bg_image_url'  => esc_url_raw((string) ($p['bg_image_url']  ?? '')),
            'logo_link_url' => esc_url_raw((string) ($p['logo_link_url'] ?? home_url('/'))),
            'logo_alt_text' => sanitize_text_field((string) ($p['logo_alt_text'] ?? get_bloginfo('name'))),
            'custom_css'    => wp_unslash((string) ($p['custom_css']    ?? '')),
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
            'enabled'      => !empty($s['enabled']),
            'message'      => (string) ($s['message']       ?? ''),
            'button_label' => (string) ($s['button_label']  ?? ''),
            'button_url'   => (string) ($s['button_url']    ?? ''),
            'bg_color'     => (string) ($s['bg_color']      ?? '#1d2327'),
            'text_color'   => (string) ($s['text_color']    ?? '#ffffff'),
            'button_color' => (string) ($s['button_color']  ?? '#ffffff'),
            'start_at'     => (string) ($s['start_at']      ?? ''),
            'end_at'       => (string) ($s['end_at']        ?? ''),
            'dismissible'  => !empty($s['dismissible']),
            'dismiss_version' => (int) ($s['dismiss_version'] ?? 1),
        ], 200);
    }

    public function saveAnnouncementBar(WP_REST_Request $request): WP_REST_Response
    {
        $p = (array) $request->get_json_params();
        $current = (array) get_option(AnnouncementBarEnum::OPTION_SETTINGS, []);
        $dismissVersion = (int) ($current['dismiss_version'] ?? 1);
        if (!empty($p['reset_dismissals'])) {
            $dismissVersion++;
        }
        $dismissVersion = max($dismissVersion, (int) ($p['dismiss_version'] ?? $dismissVersion));

        update_option(AnnouncementBarEnum::OPTION_SETTINGS, [
            'enabled'      => !empty($p['enabled']),
            'message'      => wp_kses((string) ($p['message']       ?? ''), ['a' => ['href' => [], 'target' => []], 'strong' => [], 'em' => [], 'br' => []]),
            'button_label' => sanitize_text_field((string) ($p['button_label'] ?? '')),
            'button_url'   => esc_url_raw((string) ($p['button_url'] ?? '')),
            'bg_color'     => sanitize_hex_color((string) ($p['bg_color']     ?? '#1d2327')) ?? '#1d2327',
            'text_color'   => sanitize_hex_color((string) ($p['text_color']   ?? '#ffffff')) ?? '#ffffff',
            'button_color' => sanitize_hex_color((string) ($p['button_color'] ?? '#ffffff')) ?? '#ffffff',
            'start_at'     => sanitize_text_field((string) ($p['start_at']    ?? '')),
            'end_at'       => sanitize_text_field((string) ($p['end_at']      ?? '')),
            'dismissible'  => !empty($p['dismissible']),
            'dismiss_version' => max(1, $dismissVersion),
        ], false);
        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // Post Expiry
    // -----------------------------------------------------------------------

    public function getPostExpiry(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $postType = sanitize_key((string) ($request->get_param('post_type') ?? ''));
        $dateFrom = sanitize_text_field((string) ($request->get_param('date_from') ?? ''));
        $dateTo   = sanitize_text_field((string) ($request->get_param('date_to') ?? ''));
        $where    = ["p.post_status IN ('publish','draft','private')"];
        $args     = [PostExpiryEnum::META_KEY, PostExpiryEnum::META_ACTION_KEY];

        if ($postType !== '') {
            $where[] = 'p.post_type = %s';
            $args[] = $postType;
        }

        if ($dateFrom !== '') {
            $where[] = 'pm.meta_value >= %s';
            $args[] = str_replace('T', ' ', $dateFrom) . ':00';
        }

        if ($dateTo !== '') {
            $where[] = 'pm.meta_value <= %s';
            $args[] = str_replace('T', ' ', $dateTo) . ':00';
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, p.post_status, p.post_type, pm.meta_value AS expire_at, action_pm.meta_value AS expire_action
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
                 LEFT JOIN {$wpdb->postmeta} action_pm ON action_pm.post_id = p.ID AND action_pm.meta_key = %s
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY pm.meta_value ASC
                 LIMIT 200",
                ...$args
            ),
            ARRAY_A
        );

        $settings = (array) get_option(PostExpiryEnum::OPTION_SETTINGS, []);

        return new WP_REST_Response([
            'rows'     => is_array($rows) ? $rows : [],
            'settings' => [
                'notify_email' => (string) ($settings['notify_email'] ?? ''),
            ],
        ], 200);
    }

    public function setPostExpiry(WP_REST_Request $request): WP_REST_Response
    {
        $p       = (array) $request->get_json_params();
        $postIds = array_values(array_filter(array_map('intval', (array) ($p['post_ids'] ?? []))));
        $singlePostId  = (int) ($p['post_id']  ?? 0);
        $expireAt = sanitize_text_field((string) ($p['expire_at'] ?? ''));
        $action   = sanitize_key((string) ($p['expire_action'] ?? 'draft'));
        $notifyEmail = sanitize_email((string) ($p['notify_email'] ?? ''));

        if ($singlePostId > 0 && $postIds === []) {
            $postIds = [$singlePostId];
        }

        if ($postIds === []) {
            return new WP_REST_Response(['message' => 'At least one valid post ID is required.'], 422);
        }

        if ($expireAt === '') {
            return new WP_REST_Response(['message' => 'Expiry date is required.'], 422);
        }

        // Convert datetime-local format (YYYY-MM-DDTHH:MM) to MySQL datetime
        $mysqlDate = str_replace('T', ' ', $expireAt) . ':00';
        if (!in_array($action, ['draft', 'private', 'trash'], true)) {
            $action = 'draft';
        }

        $updated = 0;
        foreach ($postIds as $postId) {
            if (!get_post($postId)) {
                continue;
            }
            update_post_meta($postId, PostExpiryEnum::META_KEY, $mysqlDate);
            update_post_meta($postId, PostExpiryEnum::META_ACTION_KEY, $action);
            $updated++;
        }

        if ($notifyEmail !== '') {
            update_option(PostExpiryEnum::OPTION_SETTINGS, [
                'notify_email' => $notifyEmail,
            ], false);
        }

        return new WP_REST_Response(['ok' => true, 'expire_action' => $action, 'updated' => $updated], 200);
    }

    public function removePostExpiry(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');

        if ($postId <= 0 || !get_post($postId)) {
            return new WP_REST_Response(['message' => 'Invalid post ID.'], 422);
        }

        delete_post_meta($postId, PostExpiryEnum::META_KEY);
        delete_post_meta($postId, PostExpiryEnum::META_ACTION_KEY);

        return new WP_REST_Response(['ok' => true], 200);
    }

    // -----------------------------------------------------------------------
    // 404 Logs
    // -----------------------------------------------------------------------

    public function get404Logs(WP_REST_Request $request): WP_REST_Response
    {
        $search = trim((string) ($request->get_param('search') ?? ''));
        $sort   = sanitize_key((string) ($request->get_param('sort') ?? 'hits'));
        $limit  = max(1, min(500, (int) ($request->get_param('limit') ?? 200)));

        return new WP_REST_Response([
            'rows'     => $this->fourOhFourRepo->recent($limit, $sort, $search),
            'settings' => [
                'exclusions' => array_values(array_filter(array_map('trim', (array) get_option(self::OPTION_404_EXCLUSIONS, [])))),
            ],
        ], 200);
    }

    public function clear404Logs(WP_REST_Request $request): WP_REST_Response
    {
        $days = (int) ($request->get_param('older_than_days') ?? 0);

        if ($days > 0) {
            return new WP_REST_Response([
                'ok'      => true,
                'deleted' => $this->fourOhFourRepo->clearOlderThanDays($days),
            ], 200);
        }

        $this->fourOhFourRepo->clear();
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function delete404Log(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        return new WP_REST_Response(['ok' => $this->fourOhFourRepo->deleteById($id)], 200);
    }

    public function export404Logs(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'exported_at' => current_time('mysql'),
            'rows'        => $this->fourOhFourRepo->recent(1000, sanitize_key((string) ($request->get_param('sort') ?? 'hits')), trim((string) ($request->get_param('search') ?? ''))),
        ], 200);
    }

    public function save404Settings(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $exclusions = array_values(array_filter(array_map('sanitize_text_field', (array) ($params['exclusions'] ?? []))));

        update_option(self::OPTION_404_EXCLUSIONS, $exclusions, false);

        return new WP_REST_Response(['ok' => true, 'exclusions' => $exclusions], 200);
    }

    public function createRedirectFrom404(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $params = (array) $request->get_json_params();
        $path   = sanitize_text_field((string) ($params['path'] ?? ''));
        $target = esc_url_raw((string) ($params['target_url'] ?? ''));
        $type   = (int) ($params['redirect_type'] ?? 301);

        if ($path === '' || $target === '') {
            return new WP_REST_Response(['message' => 'path and target_url are required.'], 422);
        }

        $redirectsTable = $wpdb->prefix . 'dr_beacon_redirects';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$redirectsTable} WHERE source_path = %s LIMIT 1", $path));

        if ($exists) {
            return new WP_REST_Response(['message' => 'A redirect for that path already exists.'], 422);
        }

        $now = current_time('mysql');
        $wpdb->insert($redirectsTable, [
            'source_path'      => $path,
            'target_url'       => $target,
            'redirect_type'    => in_array($type, [301, 302], true) ? $type : 301,
            'regex_enabled'    => 0,
            'hit_count'        => 0,
            'last_accessed_at' => null,
            'is_active'        => 1,
            'created_at'       => $now,
            'updated_at'       => $now,
        ], ['%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s']);

        return new WP_REST_Response(['ok' => true], 201);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getAvailableRoles(): array
    {
        global $wp_roles;

        if (!isset($wp_roles) || !$wp_roles instanceof \WP_Roles) {
            return [];
        }

        $roles = [];

        foreach ((array) $wp_roles->roles as $key => $role) {
            $roles[] = [
                'value' => sanitize_key((string) $key),
                'label' => translate_user_role((string) ($role['name'] ?? $key)),
            ];
        }

        return $roles;
    }

    private function mapToggleKey(string $key): string
    {
        return match ($key) {
            'svg_support' => WorkshopToggleEnum::SVG_SUPPORT,
            'disable_comments' => WorkshopToggleEnum::DISABLE_COMMENTS,
            'disable_xmlrpc' => WorkshopToggleEnum::DISABLE_XMLRPC,
            'disable_file_editing' => WorkshopToggleEnum::DISABLE_FILE_EDITING,
            'sanitise_filenames' => WorkshopToggleEnum::SANITISE_FILENAMES,
            default => $key,
        };
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getCommentablePostTypes(): array
    {
        $types = get_post_types(['show_ui' => true], 'objects');
        $rows = [];

        foreach ($types as $type) {
            if (!$type instanceof \WP_Post_Type) {
                continue;
            }

            $rows[] = [
                'value' => $type->name,
                'label' => $type->labels->singular_name ?: $type->label,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSvgSupportData(): array
    {
        $enabled  = (bool) get_option(WorkshopToggleEnum::SVG_SUPPORT, false);
        $settings = SvgSupportHandler::settings();
        $status   = (array) get_option(WorkshopToggleEnum::SVG_SUPPORT_STATUS, []);

        return [
            'enabled'          => $enabled,
            'allowed_roles'    => $settings['allowed_roles'],
            'inline_rendering' => !empty($settings['inline_rendering']),
            'sanitiser'        => (string) ($status['sanitiser'] ?? 'beacon-regex-sanitiser'),
            'security_state'   => !$enabled ? 'disabled' : (($status['state'] ?? '') !== '' ? (string) $status['state'] : 'protected'),
            'status_message'   => (string) ($status['message'] ?? 'SVG uploads are sanitised before WordPress stores them.'),
            'updated_at'       => (string) ($status['updated_at'] ?? ''),
            'bypass_active'    => !empty($status['bypass_active']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDisableCommentsData(): array
    {
        $enabled  = (bool) get_option(WorkshopToggleEnum::DISABLE_COMMENTS, false);
        $settings = DisableCommentsHandler::settings();

        return [
            'enabled'     => $enabled,
            'mode'        => $settings['mode'],
            'post_types'  => $settings['post_types'],
            'cleanup'     => [
                'admin_menu'       => true,
                'admin_bar'        => true,
                'rest_comments'    => true,
                'media_comments'   => $settings['mode'] === 'all' || in_array('attachment', $settings['post_types'], true),
                'discussion_boxes' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDisableXmlRpcData(): array
    {
        $enabled  = (bool) get_option(WorkshopToggleEnum::DISABLE_XMLRPC, false);
        $settings = DisableXmlRpcHandler::settings();
        $source   = 'beacon';
        $warnings = [];

        if (defined('XMLRPC_ENABLED') && XMLRPC_ENABLED === false) {
            $source = 'wp-config.php';
            $warnings[] = 'XML-RPC is already disabled elsewhere via XMLRPC_ENABLED.';
        }

        $effectiveState = 'enabled';
        if ($enabled) {
            $effectiveState = $settings['mode'] === 'pingback' ? 'pingback-disabled' : 'disabled';
        } elseif ($source !== 'beacon') {
            $effectiveState = 'disabled';
        }

        return [
            'enabled'         => $enabled,
            'mode'            => $settings['mode'],
            'effective_state' => $effectiveState,
            'source'          => $source,
            'warnings'        => $warnings,
            'endpoint'        => home_url('/xmlrpc.php'),
            'test'            => $this->runXmlRpcTest(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runXmlRpcTest(): array
    {
        $response = wp_remote_get(home_url('/xmlrpc.php'), ['timeout' => 10, 'redirection' => 0]);

        if (is_wp_error($response)) {
            return [
                'ok'      => false,
                'summary' => $response->get_error_message(),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = trim(wp_strip_all_tags((string) wp_remote_retrieve_body($response)));

        return [
            'ok'      => $code > 0,
            'code'    => $code,
            'summary' => $body !== '' ? $body : 'No response body returned.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDisableFileEditingData(): array
    {
        $enabled  = (bool) get_option(WorkshopToggleEnum::DISABLE_FILE_EDITING, false);
        $settings = DisableFileEditingHandler::settings();
        $source   = $enabled ? 'beacon' : 'wordpress';
        $warnings = [];
        $conflict = false;

        if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) {
            $source = 'wp-config.php';
            $warnings[] = 'DISALLOW_FILE_MODS is already active and is stronger than Beacon editor-only mode.';
            $conflict = $enabled && $settings['mode'] !== 'mods';
        } elseif (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) {
            $source = 'wp-config.php';
            $warnings[] = 'DISALLOW_FILE_EDIT is already active from wp-config.php.';
        }

        return [
            'enabled'         => $enabled,
            'mode'            => $settings['mode'],
            'effective'       => [
                'editor_disabled' => $enabled || (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) || (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS),
                'mods_disabled'   => ($enabled && $settings['mode'] === 'mods') || (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS),
                'source'          => $source,
                'conflict'        => $conflict,
                'warnings'        => $warnings,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSanitiseFilenamesData(): array
    {
        $enabled  = (bool) get_option(WorkshopToggleEnum::SANITISE_FILENAMES, false);
        $settings = SanitiseFilenamesHandler::settings();
        $sample   = 'Crème brûlée Banner 2026.svg';

        return [
            'enabled'       => $enabled,
            'lowercase'     => !empty($settings['lowercase']),
            'transliterate' => !empty($settings['transliterate']),
            'separator'     => (string) $settings['separator'],
            'sample_input'  => $sample,
            'sample_output' => SanitiseFilenamesHandler::preview($sample, $settings),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function detectLoginUrlConflicts(): array
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $conflicts = [];

        if (is_plugin_active('wps-hide-login/wps-hide-login.php')) {
            $conflicts[] = 'WPS Hide Login is active and may conflict with custom login URL.';
        }
        if (is_plugin_active('all-in-one-wp-security-and-firewall/wp-security.php')) {
            $conflicts[] = 'All In One WP Security may conflict via its own login URL feature.';
        }
        if (is_plugin_active('ithemes-security-pro/itsec.php') || is_plugin_active('better-wp-security/better-wp-security.php')) {
            $conflicts[] = 'iThemes Security has its own hide-login feature that may conflict.';
        }
        if (is_plugin_active('wordfence/wordfence.php')) {
            $conflicts[] = 'Wordfence login security features may need configuration updates for custom login paths.';
        }
        if (is_plugin_active('jetpack/jetpack.php')) {
            $conflicts[] = 'Jetpack SSO uses wp-login.php and may need manual configuration with custom login URLs.';
        }
        if (is_plugin_active('theme-my-login/theme-my-login.php')) {
            $conflicts[] = 'Theme My Login replaces wp-login.php and will conflict with a custom login slug.';
        }
        if (is_plugin_active('loginizer/loginizer.php')) {
            $conflicts[] = 'Loginizer login protection may need to be updated to recognise the custom login path.';
        }
        if (defined('FORCE_SSL_LOGIN') && FORCE_SSL_LOGIN) {
            $conflicts[] = 'FORCE_SSL_LOGIN is defined in wp-config.php — ensure the custom login URL also uses HTTPS.';
        }

        return array_values(array_unique($conflicts));
    }

    /**
     * @return string[]
     */
    private function getMaintenanceCapabilities(): array
    {
        return [
            'manage_options',
            'edit_pages',
            'publish_posts',
            'edit_theme_options',
            'upload_files',
        ];
    }

    private function ensureMaintenanceBypassToken(): string
    {
        $token = (string) get_option(MaintenanceModeEnum::OPTION_BYPASS_TOKEN, '');

        if ($token === '') {
            $token = wp_generate_password(24, false, false);
            update_option(MaintenanceModeEnum::OPTION_BYPASS_TOKEN, $token, false);
        }

        return $token;
    }

    /**
     * @return array<string, int>
     */
    private function getDatabaseCleanupTableSizes(): array
    {
        global $wpdb;

        $sizes = [];
        foreach ([$wpdb->posts, $wpdb->postmeta, $wpdb->comments, $wpdb->commentmeta, $wpdb->options] as $table) {
            $row = $wpdb->get_row($wpdb->prepare("SHOW TABLE STATUS LIKE %s", $table), ARRAY_A);
            $sizes[$table] = is_array($row)
                ? (int) (($row['Data_length'] ?? 0) + ($row['Index_length'] ?? 0))
                : 0;
        }

        return $sizes;
    }

    /**
     * @return array<string, int>
     */
    private function getTableSizes(\wpdb $wpdb): array
    {
        $results = $wpdb->get_results("SELECT TABLE_NAME, DATA_LENGTH + INDEX_LENGTH AS size FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()", ARRAY_A);
        $sizes = [];
        foreach ((array) $results as $row) {
            $sizes[(string) $row['TABLE_NAME']] = (int) $row['size'];
        }
        return $sizes;
    }

    /**
     * @return array<string, string>
     */
    private function getRewriteRulesPreview(): array
    {
        global $wp_rewrite;

        if (!isset($wp_rewrite) || !$wp_rewrite instanceof \WP_Rewrite) {
            return [];
        }

        $rules = $wp_rewrite->wp_rewrite_rules();
        return is_array($rules) ? $rules : [];
    }

    /**
     * @param array<string, mixed> $snippets
     * @return array<string, mixed>
     */
    private function normalizeCodeInjection(array $snippets): array
    {
        $header = $this->normalizeCodeInjectionSlot($snippets['header'] ?? ($snippets['head'] ?? ''));
        $footer = $this->normalizeCodeInjectionSlot($snippets['footer'] ?? '');
        $history = array_values(array_filter((array) ($snippets['history'] ?? []), 'is_array'));

        return [
            'header'  => $header,
            'footer'  => $footer,
            'history' => $history,
        ];
    }

    /**
     * @param array<string, mixed>|string $slot
     * @return array<string, mixed>
     */
    private function normalizeCodeInjectionSlot(array|string $slot): array
    {
        if (is_string($slot)) {
            return [
                'enabled'     => $slot !== '',
                'code'        => $slot,
                'post_types'  => [],
                'url_contains'=> '',
                'location'    => 'all',
                'homepage_only' => false,
                'logged_in_only' => false,
                'updated_at'  => '',
            ];
        }

        return [
            'enabled'      => !empty($slot['enabled']),
            'code'         => (string) ($slot['code'] ?? ''),
            'post_types'   => array_values(array_filter(array_map('sanitize_key', (array) ($slot['post_types'] ?? [])))),
            'url_contains' => (string) ($slot['url_contains'] ?? ''),
            'location'     => in_array((string) ($slot['location'] ?? 'all'), ['all', 'singular', 'archive', '404'], true) ? (string) ($slot['location'] ?? 'all') : 'all',
            'homepage_only' => !empty($slot['homepage_only']),
            'logged_in_only' => !empty($slot['logged_in_only']),
            'updated_at'   => (string) ($slot['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $incoming
     * @param array<string, mixed> $fallback
     * @return array<string, mixed>
     */
    private function sanitizeCodeInjectionSlot(array $incoming, array $fallback): array
    {
        return [
            'enabled'      => !empty($incoming['enabled']),
            'code'         => wp_unslash((string) ($incoming['code'] ?? ($fallback['code'] ?? ''))),
            'post_types'   => array_values(array_filter(array_map('sanitize_key', (array) ($incoming['post_types'] ?? [])))),
            'url_contains' => sanitize_text_field((string) ($incoming['url_contains'] ?? '')),
            'location'     => in_array((string) ($incoming['location'] ?? ($fallback['location'] ?? 'all')), ['all', 'singular', 'archive', '404'], true) ? (string) ($incoming['location'] ?? ($fallback['location'] ?? 'all')) : 'all',
            'homepage_only' => !empty($incoming['homepage_only']),
            'logged_in_only' => !empty($incoming['logged_in_only']),
            'updated_at'   => (string) ($fallback['updated_at'] ?? ''),
        ];
    }
}
