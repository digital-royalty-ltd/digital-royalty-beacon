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
        // Never block the admin area
        if (is_admin()) {
            return;
        }

        /** @var array<string, mixed> $settings */
        $settings = (array) get_option(MaintenanceModeEnum::OPTION_SETTINGS, []);

        if (empty($settings['enabled'])) {
            return;
        }

        // Check if current user has an allowed role
        $allowedRoles = (array) ($settings['allowed_roles'] ?? ['administrator']);

        if ($this->currentUserHasRole($allowedRoles)) {
            return;
        }

        $message = (string) ($settings['message'] ?? MaintenanceModeEnum::DEFAULT_MESSAGE);

        $this->serveMaintenance($message);
    }

    /**
     * @param string[] $roles
     */
    private function currentUserHasRole(array $roles): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();

        foreach ($roles as $role) {
            if (in_array($role, (array) $user->roles, true)) {
                return true;
            }
        }

        return false;
    }

    private function serveMaintenance(string $message): void
    {
        $siteName = get_bloginfo('name');

        status_header(503);
        header('Retry-After: 3600');
        header('Content-Type: text/html; charset=utf-8');

        wp_die(
            '<div style="text-align:center;max-width:520px;margin:80px auto;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">'
            . '<h1 style="font-size:28px;margin-bottom:16px;">' . esc_html($siteName) . '</h1>'
            . '<p style="font-size:16px;color:#444;line-height:1.6;">' . wp_kses($message, ['strong' => [], 'em' => [], 'br' => [], 'p' => [], 'a' => ['href' => [], 'target' => []]]) . '</p>'
            . '</div>',
            esc_html($siteName) . ' &mdash; Maintenance',
            ['response' => 503]
        );
    }
}
