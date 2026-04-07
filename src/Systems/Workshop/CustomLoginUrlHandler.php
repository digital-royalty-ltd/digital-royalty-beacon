<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\CustomLoginUrlEnum;

final class CustomLoginUrlHandler
{
    private const RECOVERY_QUERY_ARG = 'beacon_login_recovery';

    public function register(): void
    {
        $slug        = (string) get_option(CustomLoginUrlEnum::OPTION_SLUG, '');
        $recoveryKey = (string) get_option(CustomLoginUrlEnum::OPTION_RECOVERY_KEY, '');

        if ($slug === '' && $recoveryKey === '') {
            return;
        }

        add_action('init', [$this, 'handleRequest'], 1);
        add_filter('login_url', [$this, 'rewriteLoginUrl'], 10, 3);
        add_filter('logout_url', [$this, 'rewriteLogoutUrl'], 10, 2);
    }

    public function handleRequest(): void
    {
        if ($this->handleRecoveryRequest()) {
            return;
        }

        $slug = (string) get_option(CustomLoginUrlEnum::OPTION_SLUG, '');

        if ($slug === '') {
            return;
        }

        $requestPath = trim(parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/', '/');
        $wpAdminPath = trim(parse_url(admin_url(), PHP_URL_PATH) ?? '', '/');

        if ($requestPath === $slug) {
            $_SERVER['REQUEST_URI'] = '/wp-login.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
            require_once ABSPATH . 'wp-login.php';
            exit;
        }

        $wpLogin = trim(parse_url(wp_login_url(), PHP_URL_PATH) ?? '', '/');

        if ($requestPath === $wpLogin && !$this->isLoggedIn()) {
            $this->renderNotFound();
        }

        if ($requestPath === $wpAdminPath && !$this->isLoggedIn()) {
            $this->renderNotFound();
        }
    }

    public function rewriteLoginUrl(string $url, string $redirect, bool $forceReauth): string
    {
        return $this->customUrl($url);
    }

    public function rewriteLogoutUrl(string $url, string $redirect): string
    {
        return $this->customUrl($url);
    }

    public static function ensureRecoveryKey(): string
    {
        $key = (string) get_option(CustomLoginUrlEnum::OPTION_RECOVERY_KEY, '');

        if ($key === '') {
            $key = wp_generate_password(32, false, false);
            update_option(CustomLoginUrlEnum::OPTION_RECOVERY_KEY, $key, false);
        }

        return $key;
    }

    private function customUrl(string $url): string
    {
        $slug = (string) get_option(CustomLoginUrlEnum::OPTION_SLUG, '');

        if ($slug === '') {
            return $url;
        }

        return str_replace('wp-login.php', $slug, $url);
    }

    private function handleRecoveryRequest(): bool
    {
        $requestKey  = sanitize_text_field((string) ($_GET[self::RECOVERY_QUERY_ARG] ?? ''));
        $recoveryKey = (string) get_option(CustomLoginUrlEnum::OPTION_RECOVERY_KEY, '');

        if ($requestKey === '' || $recoveryKey === '' || !hash_equals($recoveryKey, $requestKey)) {
            return false;
        }

        update_option(CustomLoginUrlEnum::OPTION_SLUG, '', false);
        update_option(CustomLoginUrlEnum::OPTION_RECOVERY_KEY, wp_generate_password(32, false, false), false);

        wp_safe_redirect(add_query_arg('beacon_login_recovered', '1', wp_login_url()));
        exit;
    }

    private function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    private function renderNotFound(): void
    {
        global $wp_query;

        if (isset($wp_query) && $wp_query instanceof \WP_Query) {
            $wp_query->set_404();
        }

        status_header(404);
        nocache_headers();
        $template = get_query_template('404');
        if (is_string($template) && $template !== '') {
            include $template;
            exit;
        }

        wp_die(esc_html__('Page not found.', 'digital-royalty-beacon'), '404', ['response' => 404]);
        exit;
    }
}
