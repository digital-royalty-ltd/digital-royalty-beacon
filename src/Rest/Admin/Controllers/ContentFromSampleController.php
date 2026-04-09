<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoint for the "Create Content From Sample" AI tool.
 *
 * Accepts a URL and/or body text, forwards to the Beacon API which runs the
 * two-step chain (sample → brief → rewritten article) and creates a WP draft
 * when the deferred result arrives.
 */
final class ContentFromSampleController
{
    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/admin/content-from-sample/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);
    }

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        $url = trim((string) ($request->get_param('url') ?? ''));
        $bodyText = trim((string) ($request->get_param('body_text') ?? ''));

        if ($url === '' && $bodyText === '') {
            return new WP_REST_Response([
                'error' => 'Provide a URL or paste content to generate from.',
            ], 400);
        }

        $payload = array_filter([
            'url' => $url ?: null,
            'body_text' => $bodyText ?: null,
            'post_type' => 'post',
        ]);

        $response = Services::apiClient()->contentFromSample($payload);

        if (!$response->ok) {
            return new WP_REST_Response([
                'error' => $response->message ?? 'Request failed.',
            ], $response->code >= 400 ? $response->code : 500);
        }

        return new WP_REST_Response([
            'status' => 'queued',
            'message' => 'Your draft is being generated. It will appear in your posts when ready.',
        ], 202);
    }
}
