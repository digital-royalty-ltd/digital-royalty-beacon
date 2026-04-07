<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\CodeInjectionEnum;

final class CodeInjectionHandler
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'outputHead'], 999);
        add_action('wp_footer', [$this, 'outputFooter'], 999);
    }

    public function outputHead(): void
    {
        $code = $this->resolveCode('header');

        if ($code !== '') {
            echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }

    public function outputFooter(): void
    {
        $code = $this->resolveCode('footer');

        if ($code !== '') {
            echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }

    private function resolveCode(string $slot): string
    {
        $snippets = (array) get_option(CodeInjectionEnum::OPTION_SNIPPETS, []);
        $entry    = $snippets[$slot] ?? '';

        if (is_string($entry)) {
            return $entry;
        }

        if (!is_array($entry) || empty($entry['enabled'])) {
            return '';
        }

        if (!$this->matchesConditions($entry)) {
            return '';
        }

        return (string) ($entry['code'] ?? '');
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function matchesConditions(array $entry): bool
    {
        $postTypes   = array_values(array_filter(array_map('sanitize_key', (array) ($entry['post_types'] ?? []))));
        $urlContains = trim((string) ($entry['url_contains'] ?? ''));
        $location    = (string) ($entry['location'] ?? 'all');
        $homepageOnly = !empty($entry['homepage_only']);
        $loggedInOnly = !empty($entry['logged_in_only']);

        if ($homepageOnly && !is_front_page() && !is_home()) {
            return false;
        }

        if ($loggedInOnly && !is_user_logged_in()) {
            return false;
        }

        $userRoles = array_values(array_filter(array_map('sanitize_key', (array) ($entry['user_roles'] ?? []))));

        if ($userRoles !== []) {
            if (!is_user_logged_in()) {
                return false;
            }
            $user = wp_get_current_user();
            $hasRole = false;
            foreach ((array) $user->roles as $role) {
                if (in_array($role, $userRoles, true)) {
                    $hasRole = true;
                    break;
                }
            }
            if (!$hasRole) {
                return false;
            }
        }

        if ($location === 'singular' && !is_singular()) {
            return false;
        }

        if ($location === 'archive' && !is_archive()) {
            return false;
        }

        if ($location === '404' && !is_404()) {
            return false;
        }

        if ($postTypes !== []) {
            if (!is_singular()) {
                return false;
            }

            $post = get_queried_object();
            $type = is_object($post) && isset($post->post_type) ? (string) $post->post_type : '';

            if ($type === '' || !in_array($type, $postTypes, true)) {
                return false;
            }
        }

        if ($urlContains !== '') {
            $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');

            if ($requestUri === '' || stripos($requestUri, $urlContains) === false) {
                return false;
            }
        }

        return true;
    }
}
