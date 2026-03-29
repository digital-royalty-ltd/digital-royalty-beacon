<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use WP_REST_Request;
use WP_REST_Response;

/**
 * POST /beacon/v1/admin/onboarding/dismiss
 *
 * Marks a named screen as permanently dismissed for the current user.
 * Stored in user meta so it persists across sessions and devices.
 */
final class OnboardingController
{
    private const META_KEY = 'dr_beacon_dismissed_onboarding';

    private const ALLOWED_SCREENS = [
        'dashboard',
        'workshop',
        'automations',
        'campaigns',
        'configuration',
        'debug',
    ];

    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/admin/onboarding/dismiss', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => fn () => is_user_logged_in(),
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $screen = (string) ($request->get_param('screen') ?? '');

        if (!in_array($screen, self::ALLOWED_SCREENS, true)) {
            return new WP_REST_Response(['error' => 'Unknown screen.'], 400);
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return new WP_REST_Response(['error' => 'Not authenticated.'], 401);
        }

        $dismissed = get_user_meta($userId, self::META_KEY, true);
        $dismissed = is_array($dismissed) ? $dismissed : [];

        if (!in_array($screen, $dismissed, true)) {
            $dismissed[] = $screen;
            update_user_meta($userId, self::META_KEY, $dismissed);
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * Returns the list of screens the given user has permanently dismissed.
     *
     * @return string[]
     */
    public static function dismissedForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $dismissed = get_user_meta($userId, self::META_KEY, true);

        return is_array($dismissed) ? $dismissed : [];
    }
}
