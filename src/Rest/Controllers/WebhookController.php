<?php

namespace DigitalRoyalty\Beacon\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;

final class WebhookController
{
    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => '__return_true', // later: verify HMAC signature
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        // Later: verify signature, enqueue a job, return 202.
        return new WP_REST_Response([
            'received' => true,
        ], 200);
    }
}
