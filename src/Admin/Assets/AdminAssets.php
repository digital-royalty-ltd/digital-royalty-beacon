<?php

namespace DigitalRoyalty\Beacon\Admin\Assets;

use DigitalRoyalty\Beacon\Rest\Admin\Controllers\OnboardingController;
use DigitalRoyalty\Beacon\Support\Enums\Admin\HomeViewOptionEnum;

final class AdminAssets
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);

        // wp_script_add_data( 'type', 'module' ) is unreliable across WP versions
        // and plugin configurations. Rewriting the tag directly is guaranteed.
        add_filter('script_loader_tag', [$this, 'addModuleType'], 10, 2);
    }

    public function addModuleType(string $tag, string $handle): string
    {
        if ($handle === 'dr-beacon-admin') {
            $tag = str_replace('<script ', '<script type="module" ', $tag);
        }

        return $tag;
    }

    public function enqueue(string $hookSuffix): void
    {
        if (!str_contains($hookSuffix, 'dr-beacon')) {
            return;
        }

        $manifest = $this->manifest();
        if ($manifest === null) {
            return;
        }

        $entry = $manifest['assets/admin/src/main.tsx'] ?? null;
        if (!is_array($entry)) {
            return;
        }

        $jsFile  = (string) ($entry['file'] ?? '');
        $cssFiles = (array) ($entry['css'] ?? []);

        if ($jsFile !== '') {
            wp_enqueue_script(
                'dr-beacon-admin',
                plugins_url("dist/admin/{$jsFile}", DR_BEACON_FILE),
                [],
                null,
                true
            );

            wp_localize_script('dr-beacon-admin', 'BeaconData', $this->scriptData());
        }

        foreach ($cssFiles as $cssFile) {
            wp_enqueue_style(
                'dr-beacon-admin-' . md5($cssFile),
                plugins_url("dist/admin/{$cssFile}", DR_BEACON_FILE),
                [],
                null
            );
        }
    }

    /** @return array<string, mixed>|null */
    private function manifest(): ?array
    {
        $path = DR_BEACON_DIR . '/dist/admin/.vite/manifest.json';

        if (!file_exists($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @return array<string, mixed> */
    private function scriptData(): array
    {
        $apiKey      = (string) get_option(HomeViewOptionEnum::API_KEY, '');
        $isConnected = $apiKey !== '';

        return [
            // REST API
            'nonce'    => wp_create_nonce('wp_rest'),
            'restBase' => rest_url('beacon/v1/admin'),
            'adminUrl' => admin_url(),

            // Connection state (static — no API call needed on load)
            'hasApiKey'   => $isConnected,
            'isConnected' => $isConnected,
            'siteUrl'       => get_site_url(),
            'siteName'      => get_bloginfo('name'),
            'pluginVersion' => defined('DR_BEACON_VERSION') ? DR_BEACON_VERSION : '',
            'beaconApiBase' => defined('DR_BEACON_API_BASE') ? rtrim(DR_BEACON_API_BASE, '/') . '/beacon/' . DR_BEACON_API_VERSION : '',
            // dashboardUrl points at the human-facing Dashboard root, not the
            // API base. DR_BEACON_API_BASE typically ends in /api (and may
            // optionally include /v1) — strip both so links like
            // {dashboardUrl}/dashboard/projects/overview resolve correctly.
            'dashboardUrl'  => defined('DR_BEACON_API_BASE') ? self::deriveDashboardUrl(DR_BEACON_API_BASE) : '',

            // Per-user permanently dismissed onboarding screens
            'dismissedOnboardingScreens' => OnboardingController::dismissedForUser(get_current_user_id()),
        ];
    }

    /**
     * Strip the trailing /api or /api/vN segment from the configured API base
     * so `dashboardUrl` points at the human-facing Dashboard root.
     */
    private static function deriveDashboardUrl(string $apiBase): string
    {
        $base = rtrim($apiBase, '/');
        // Trim any trailing /vN first (e.g. someone set DR_BEACON_API_BASE to
        // include a version segment), then a trailing /api.
        $base = preg_replace('#/v\d+$#', '', $base) ?? $base;
        $base = preg_replace('#/api$#', '', $base) ?? $base;

        return $base;
    }
}
