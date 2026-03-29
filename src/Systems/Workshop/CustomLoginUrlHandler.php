<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\CustomLoginUrlEnum;

final class CustomLoginUrlHandler
{
    public function register(): void
    {
        $slug = (string) get_option(CustomLoginUrlEnum::OPTION_SLUG, '');

        if ($slug === '') {
            return;
        }

        add_action('init', [$this, 'handleRequest'], 1);
        add_filter('login_url', [$this, 'rewriteLoginUrl'], 10, 3);
        add_filter('logout_url', [$this, 'rewriteLogoutUrl'], 10, 2);
    }

    public function handleRequest(): void
    {
        $slug = (string) get_option(CustomLoginUrlEnum::OPTION_SLUG, '');

        if ($slug === '') {
            return;
        }

        $requestPath = trim(parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/', '/');

        // Allow access if the custom slug matches
        if ($requestPath === $slug) {
            // Let WordPress handle wp-login.php normally by including it
            $_SERVER['REQUEST_URI'] = '/wp-login.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
            require_once ABSPATH . 'wp-login.php';
            exit;
        }

        // Block direct access to wp-login.php (except for admin-ajax, cron, REST)
        $wpLogin = trim(parse_url(wp_login_url(), PHP_URL_PATH) ?? '', '/');

        if ($requestPath === $wpLogin && !$this->isLoggedIn()) {
            wp_safe_redirect(home_url('/404'));
            exit;
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

    private function customUrl(string $url): string
    {
        $slug = (string) get_option(CustomLoginUrlEnum::OPTION_SLUG, '');

        if ($slug === '') {
            return $url;
        }

        return str_replace('wp-login.php', $slug, $url);
    }

    private function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}
