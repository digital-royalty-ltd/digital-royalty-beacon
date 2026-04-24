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

        register_rest_route('beacon/v1', '/admin/content-generator/destinations', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getDestinations'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/content-generator/existing-content', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getExistingContent'],
            'permission_callback' => $perm,
        ]);

        // User switch-back status — lives here to avoid bloating WorkshopInteractiveController.
        register_rest_route('beacon/v1', '/admin/user-switch-status', [
            'methods'             => 'GET',
            'callback'            => fn () => is_user_logged_in(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Content areas (with routing info for the UI)
    // -----------------------------------------------------------------------

    public function getContentAreas(WP_REST_Request $request): WP_REST_Response
    {
        $map = get_option('dr_beacon_content_area_map', []);

        if (!is_array($map) || empty($map)) {
            $map = $this->rebuildContentAreaMapFromReport();
        }

        if (!is_array($map) || empty($map)) {
            return new WP_REST_Response([], 200);
        }

        $areas = [];
        foreach ($map as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $routing  = is_array($entry['routing'] ?? null) ? $entry['routing'] : [];
            $postType = isset($routing['post_type']) && is_string($routing['post_type']) && post_type_exists($routing['post_type'])
                ? $routing['post_type']
                : null;

            $area = [
                'key'     => (string) $key,
                'label'   => (string) ($entry['label']  ?? $key),
                'intent'  => (string) ($entry['intent'] ?? ''),
                'topics'  => array_values(array_filter((array) ($entry['topics'] ?? []), 'is_string')),
            ];

            // Include resolved routing so the UI knows the destination without a separate lookup.
            if ($postType !== null) {
                $ptObject = get_post_type_object($postType);
                $area['post_type']       = $postType;
                $area['post_type_label'] = $ptObject ? ($ptObject->labels->singular_name ?? $postType) : $postType;
                $area['taxonomies']      = $this->getTaxonomiesForPostType($postType);
            }

            $areas[] = $area;
        }

        return new WP_REST_Response($areas, 200);
    }

    // -----------------------------------------------------------------------
    // Destinations (post types + taxonomies) — used for "Create as" mode
    // -----------------------------------------------------------------------

    public function getDestinations(WP_REST_Request $request): WP_REST_Response
    {
        $postTypes = get_post_types(['public' => true], 'objects');
        $destinations = [];

        foreach ($postTypes as $pt) {
            if ($pt->name === 'attachment') {
                continue;
            }

            $destinations[] = [
                'slug'       => $pt->name,
                'label'      => $pt->labels->singular_name ?? $pt->name,
                'taxonomies' => $this->getTaxonomiesForPostType($pt->name),
            ];
        }

        return new WP_REST_Response($destinations, 200);
    }

    // -----------------------------------------------------------------------
    // Existing content — titles + excerpts from a destination for AI context
    // -----------------------------------------------------------------------

    public function getExistingContent(WP_REST_Request $request): WP_REST_Response
    {
        $postType = sanitize_key((string) ($request->get_param('post_type') ?? 'post'));

        if (!post_type_exists($postType)) {
            return new WP_REST_Response([], 200);
        }

        /** @var \WP_Post[] $posts */
        $posts = get_posts([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        $items = [];
        foreach ($posts as $post) {
            $excerpt = !empty($post->post_excerpt)
                ? wp_strip_all_tags($post->post_excerpt)
                : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 25, '…');

            $items[] = [
                'title'   => $post->post_title,
                'excerpt' => trim($excerpt),
            ];
        }

        return new WP_REST_Response($items, 200);
    }

    // -----------------------------------------------------------------------
    // Generate
    // -----------------------------------------------------------------------

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        $contentAreaKey = trim((string) ($request->get_param('content_area_key') ?? ''));
        $topic          = trim((string) ($request->get_param('topic')            ?? ''));
        $brief          = trim((string) ($request->get_param('brief')            ?? ''));
        $postType       = trim((string) ($request->get_param('post_type')        ?? ''));
        $taxonomies     = $request->get_param('taxonomies');
        $existingContent = $request->get_param('existing_content');

        // Must have either a content area or an explicit post type.
        if ($contentAreaKey === '' && $postType === '') {
            return new WP_REST_Response(['error' => 'Choose a content area or a destination type.'], 400);
        }

        // Resolve content area from map (if provided).
        $map   = get_option('dr_beacon_content_area_map', []);
        $entry = ($contentAreaKey !== '' && is_array($map[$contentAreaKey] ?? null))
            ? $map[$contentAreaKey]
            : [];

        // Resolve the destination post type.
        $resolvedPostType = 'post';
        if ($postType !== '' && post_type_exists($postType)) {
            $resolvedPostType = $postType;
        } elseif (!empty($entry)) {
            $routing = is_array($entry['routing'] ?? null) ? $entry['routing'] : [];
            if (isset($routing['post_type']) && is_string($routing['post_type']) && post_type_exists($routing['post_type'])) {
                $resolvedPostType = $routing['post_type'];
            }
        }

        // Build taxonomy input from the request.
        $taxInput = [];
        if (is_array($taxonomies)) {
            foreach ($taxonomies as $taxSlug => $termIds) {
                $taxSlug = sanitize_key((string) $taxSlug);
                if ($taxSlug === '' || !is_array($termIds)) {
                    continue;
                }
                $taxInput[$taxSlug] = array_values(array_filter(array_map('absint', $termIds)));
            }
        }

        // Build the topic/brief for the AI.
        $aiTopic = $topic !== '' ? $topic : ($brief !== '' ? $brief : '');

        // If no user-provided brief, include existing content for context.
        $existingContext = [];
        if ($aiTopic === '' && is_array($existingContent) && !empty($existingContent)) {
            $existingContext = array_slice($existingContent, 0, 20);
        }

        // Build a CMS-agnostic payload for the Beacon API.
        $apiPayload = [
            'content_area'     => (string) ($entry['label']  ?? $contentAreaKey ?: $resolvedPostType),
            'intent'           => (string) ($entry['intent'] ?? ''),
            'topic'            => $aiTopic,
            'suggested_topics' => array_values(array_filter((array) ($entry['topics'] ?? []), 'is_string')),
            'content_area_key' => $contentAreaKey ?: null,
            'adapter_context'  => [
                'post_type'  => $resolvedPostType,
                'taxonomies' => $taxInput,
            ],
        ];

        if (!empty($existingContext)) {
            $apiPayload['existing_content'] = $existingContext;
        }

        if ($brief !== '') {
            $apiPayload['brief'] = $brief;
        }

        $response = Services::apiClient()->generateContentDraft($apiPayload);

        if (!$response->ok) {
            return new WP_REST_Response([
                'error' => $response->message ?? 'Generation request failed.',
            ], $response->code >= 400 ? $response->code : 500);
        }

        return new WP_REST_Response([
            'status'  => 'queued',
            'message' => 'Your draft is being generated. It will appear in your drafts when ready.',
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

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Get public taxonomies + terms for a post type.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTaxonomiesForPostType(string $postType): array
    {
        $taxObjects = get_object_taxonomies($postType, 'objects');
        $taxonomies = [];

        foreach ($taxObjects as $tax) {
            if (!$tax->public || !$tax->show_ui) {
                continue;
            }

            $terms = get_terms([
                'taxonomy'   => $tax->name,
                'hide_empty' => false,
                'number'     => 100,
            ]);

            if (is_wp_error($terms)) {
                continue;
            }

            $taxonomies[] = [
                'slug'         => $tax->name,
                'label'        => $tax->labels->singular_name ?? $tax->name,
                'hierarchical' => (bool) $tax->hierarchical,
                'terms'        => array_map(fn ($t) => [
                    'id'   => $t->term_id,
                    'name' => $t->name,
                ], $terms),
            ];
        }

        return $taxonomies;
    }

    /**
     * Rebuild the content area map from the stored report payload.
     *
     * @return array<string, array<string, mixed>>
     */
    private function rebuildContentAreaMapFromReport(): array
    {
        global $wpdb;

        $repo   = new \DigitalRoyalty\Beacon\Repositories\ReportsRepository($wpdb);
        $row    = $repo->getLatestByType('website_content_areas');

        if (!$row) {
            return [];
        }

        $payloadJson = $row['payload'] ?? null;
        $payload     = is_string($payloadJson) ? json_decode($payloadJson, true) : null;

        if (!is_array($payload) || empty($payload['content_areas'])) {
            return [];
        }

        $map = [];

        foreach ($payload['content_areas'] as $area) {
            if (!is_array($area)) {
                continue;
            }

            $label = trim((string) ($area['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $key = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $label));

            $map[$key] = [
                'label'   => $label,
                'intent'  => (string) ($area['intent'] ?? ''),
                'topics'  => array_values(array_filter((array) ($area['topics'] ?? []), 'is_string')),
                'routing' => [],
            ];
        }

        if (!empty($map)) {
            update_option('dr_beacon_content_area_map', $map, false);
        }

        return $map;
    }
}
