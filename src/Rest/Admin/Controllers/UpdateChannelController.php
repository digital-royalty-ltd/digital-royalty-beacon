<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Support\Enums\Admin\UpdateChannelEnum;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET  /admin/update-channel — return current channel + available version info
 * POST /admin/update-channel — switch channel
 */
final class UpdateChannelController
{
    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        register_rest_route('beacon/v1', '/admin/update-channel', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'update'],
                'permission_callback' => $perm,
            ],
        ]);
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'channel'         => get_option(UpdateChannelEnum::OPTION_CHANNEL, UpdateChannelEnum::STABLE),
            'current_version' => DR_BEACON_VERSION,
        ], 200);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $params  = (array) $request->get_json_params();
        $channel = isset($params['channel']) ? sanitize_key((string) $params['channel']) : '';

        if (!in_array($channel, [UpdateChannelEnum::STABLE, UpdateChannelEnum::EXPERIMENTAL], true)) {
            return new WP_REST_Response(['message' => 'Invalid channel.'], 422);
        }

        update_option(UpdateChannelEnum::OPTION_CHANNEL, $channel, false);

        // Bust the GitHub release transient so the next update check is fresh.
        delete_transient('dr_beacon_github_release');

        return new WP_REST_Response([
            'ok'      => true,
            'channel' => $channel,
        ], 200);
    }
}
