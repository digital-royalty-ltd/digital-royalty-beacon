<?php

namespace DigitalRoyalty\Beacon\Systems\MaintenanceMode;

use DigitalRoyalty\Beacon\Support\Enums\Admin\MaintenanceModeEnum;

final class MaintenanceModeHandler
{
    public function register(): void
    {
        add_action('template_redirect', [$this, 'handle'], 1);
    }

    public function handle(): void
    {
        if (is_admin()) {
            return;
        }

        $settings = (array) get_option(MaintenanceModeEnum::OPTION_SETTINGS, []);

        if (empty($settings['enabled'])) {
            return;
        }

        $this->maybeApplyBypassCookie();

        $previewing = !empty($_GET[MaintenanceModeEnum::PREVIEW_QUERY_ARG]);
        $allowedCapability = sanitize_key((string) ($settings['allowed_capability'] ?? 'manage_options'));

        if (!$previewing && $this->canBypass($allowedCapability)) {
            return;
        }

        $this->serveMaintenance($settings);
    }

    private function maybeApplyBypassCookie(): void
    {
        $token = sanitize_text_field((string) ($_GET[MaintenanceModeEnum::BYPASS_QUERY_ARG] ?? ''));

        if ($token === '') {
            return;
        }

        $stored = (string) get_option(MaintenanceModeEnum::OPTION_BYPASS_TOKEN, '');

        if ($stored === '' || !hash_equals($stored, $token)) {
            return;
        }

        setcookie(
            MaintenanceModeEnum::BYPASS_COOKIE,
            wp_hash($stored),
            time() + DAY_IN_SECONDS,
            COOKIEPATH ?: '/',
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );

        $redirect = remove_query_arg(MaintenanceModeEnum::BYPASS_QUERY_ARG);
        wp_safe_redirect($redirect ?: home_url('/'));
        exit;
    }

    private function canBypass(string $allowedCapability): bool
    {
        if ($this->hasBypassCookie()) {
            return true;
        }

        return $allowedCapability !== '' && current_user_can($allowedCapability);
    }

    private function hasBypassCookie(): bool
    {
        $stored = (string) get_option(MaintenanceModeEnum::OPTION_BYPASS_TOKEN, '');
        $cookie = (string) ($_COOKIE[MaintenanceModeEnum::BYPASS_COOKIE] ?? '');

        if ($stored === '' || $cookie === '') {
            return false;
        }

        return hash_equals(wp_hash($stored), $cookie);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function serveMaintenance(array $settings): void
    {
        $siteName = get_bloginfo('name');
        $headline = (string) ($settings['headline'] ?? 'Scheduled Maintenance');
        $message = (string) ($settings['message'] ?? MaintenanceModeEnum::DEFAULT_MESSAGE);
        $returnDate = (string) ($settings['return_date'] ?? '');
        $bgColor = (string) ($settings['bg_color'] ?? '#f6f1fb');
        $bgImage = (string) ($settings['bg_image_url'] ?? '');
        $responseCode = (int) ($settings['response_code'] ?? 503);
        $responseCode = in_array($responseCode, [200, 503], true) ? $responseCode : 503;

        status_header($responseCode);
        if ($responseCode === 503) {
            header('Retry-After: 3600');
        }
        header('Content-Type: text/html; charset=utf-8');

        $backgroundStyle = 'background:' . esc_attr($bgColor) . ';';
        if ($bgImage !== '') {
            $backgroundStyle .= 'background-image:url(' . esc_url($bgImage) . ');background-size:cover;background-position:center;';
        }

        $returnDateHtml = $returnDate !== ''
            ? '<p style="margin-top:16px;font-size:14px;color:#666;">Expected return: ' . esc_html($returnDate) . '</p>'
            : '';

        wp_die(
            '<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px;' . $backgroundStyle . '">'
            . '<div style="text-align:center;max-width:560px;padding:40px;background:rgba(255,255,255,0.88);border-radius:20px;box-shadow:0 8px 30px rgba(0,0,0,0.08);font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">'
            . '<p style="font-size:13px;letter-spacing:0.14em;text-transform:uppercase;color:#7c3aed;margin-bottom:12px;">' . esc_html($siteName) . '</p>'
            . '<h1 style="font-size:32px;margin:0 0 16px;">' . esc_html($headline) . '</h1>'
            . '<div style="font-size:16px;color:#444;line-height:1.6;">' . wp_kses($message, ['strong' => [], 'em' => [], 'br' => [], 'p' => [], 'a' => ['href' => [], 'target' => []]]) . '</div>'
            . $returnDateHtml
            . '</div>'
            . '</div>',
            esc_html($siteName) . ' - Maintenance',
            ['response' => $responseCode]
        );
    }
}
