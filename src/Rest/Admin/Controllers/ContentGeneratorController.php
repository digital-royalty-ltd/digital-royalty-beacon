<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Admin\UserSwitcherEnum;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for the Content Generator AI tool and user-switch status.
 */
final class ContentGeneratorController
{
    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        register_rest_route('beacon/v1', '/admin/content-generator/content-areas', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getContentAreas'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/content-generator/generate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate'],
            'permission_callback' => $perm,
        ]);

        // User switch-back status — lives here to avoid bloating WorkshopInteractiveController.
        register_rest_route('beacon/v1', '/admin/user-switch-status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'userSwitchStatus'],
            'permission_callback' => fn () => is_user_logged_in(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Content areas
    // -----------------------------------------------------------------------

    public function getContentAreas(WP_REST_Request $request): WP_REST_Response
    {
        $map = get_option('dr_beacon_content_area_map', []);

        if (!is_array($map) || empty($map)) {
            return new WP_REST_Response([], 200);
        }

        $areas = [];
        foreach ($map as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $areas[] = [
                'key'     => (string) $key,
                'label'   => (string) ($entry['label']  ?? $key),
                'intent'  => (string) ($entry['intent'] ?? ''),
                'topics'  => array_values(array_filter((array) ($entry['topics'] ?? []), 'is_string')),
            ];
        }

        return new WP_REST_Response($areas, 200);
    }

    // -----------------------------------------------------------------------
    // Generate
    // -----------------------------------------------------------------------

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        $contentAreaKey = trim((string) ($request->get_param('content_area_key') ?? ''));
        $topic          = trim((string) ($request->get_param('topic')            ?? ''));

        if ($contentAreaKey === '') {
            return new WP_REST_Response(['error' => 'content_area_key is required'], 400);
        }

        if ($topic === '') {
            return new WP_REST_Response(['error' => 'topic is required'], 400);
        }

        $map   = get_option('dr_beacon_content_area_map', []);
        $entry = is_array($map[$contentAreaKey] ?? null) ? $map[$contentAreaKey] : [];

        if (empty($entry)) {
            return new WP_REST_Response(['error' => 'Content area not found. Re-run site reports to refresh areas.'], 404);
        }

        // Build an agnostic payload for the Beacon API.
        // WordPress-specific routing (post_type, taxonomy) is stored under
        // _routing for use by ContentGeneratorDraftHandler on completion.
        // The Beacon API receives only CMS-neutral fields.
        $apiPayload = [
            'content_area'     => (string) ($entry['label']  ?? $contentAreaKey),
            'intent'           => (string) ($entry['intent'] ?? ''),
            'topic'            => $topic,
            'suggested_topics' => array_values(array_filter((array) ($entry['topics'] ?? []), 'is_string')),
            // Routing stored for the deferred handler — not forwarded to Beacon API
            // (the API receives this key but ignores it; the handler reads it back).
            'content_area_key' => $contentAreaKey,
        ];

        $response = Services::apiClient()->generateContentDraft($apiPayload);

        if (!$response->ok) {
            return new WP_REST_Response([
                'error' => $response->message ?? 'Generation request failed.',
            ], $response->code >= 400 ? $response->code : 500);
        }

        // 202 Accepted — deferred system will poll and create the draft.
        return new WP_REST_Response([
            'status'  => 'queued',
            'message' => 'Your draft is being generated. It will appear in your posts when ready.',
        ], 202);
    }

    // -----------------------------------------------------------------------
    // User switch status
    // -----------------------------------------------------------------------

    public function userSwitchStatus(WP_REST_Request $request): WP_REST_Response
    {
        $currentId  = get_current_user_id();
        $originalId = (int) get_user_meta($currentId, UserSwitcherEnum::META_SWITCHED_FROM, true);

        if ($originalId <= 0) {
            return new WP_REST_Response(['is_switched' => false], 200);
        }

        $originalUser = get_user_by('id', $originalId);

        $switchBackUrl = add_query_arg(
            [
                'action'   => UserSwitcherEnum::ACTION_SWITCH_BACK,
                '_wpnonce' => wp_create_nonce(UserSwitcherEnum::ACTION_SWITCH_BACK),
            ],
            admin_url('admin-post.php')
        );

        return new WP_REST_Response([
            'is_switched'   => true,
            'original_user' => $originalUser ? [
                'id'           => $originalId,
                'display_name' => $originalUser->display_name,
            ] : null,
            'switch_back_url' => $switchBackUrl,
        ], 200);
    }
}
