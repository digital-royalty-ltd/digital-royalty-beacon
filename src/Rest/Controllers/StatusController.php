<?php

namespace DigitalRoyalty\Beacon\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;

final class StatusController
{
    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/status', [
            'methods' => 'GET',
            'callback' => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'ok' => true,
            'plugin' => 'digital-royalty-beacon',
            'version' => defined('DR_BEACON_VERSION') ? DR_BEACON_VERSION : null,
        ], 200);
    }
}
