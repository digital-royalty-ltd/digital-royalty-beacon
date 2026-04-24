<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Systems\Social\SocialPlatformRegistry;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for the Social Media Content Sharer tool.
 */
final class SocialShareController
{
    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        register_rest_route('beacon/v1', '/admin/social-share/platforms', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPlatforms'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/social-share/sources', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getSources'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/social-share/posts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPosts'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/social-share/generate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate'],
            'permission_callback' => $perm,
        ]);
    }

    // -----------------------------------------------------------------------
    // Platforms
    // -----------------------------------------------------------------------

    public function getPlatforms(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(SocialPlatformRegistry::all(), 200);
    }

    // -----------------------------------------------------------------------
    // Sources (public post types)
    // -----------------------------------------------------------------------

    public function getSources(WP_REST_Request $request): WP_REST_Response
    {
        $postTypes = get_post_types(['public' => true], 'objects');
        $sources   = [];

        foreach ($postTypes as $pt) {
            if ($pt->name === 'attachment') {
                continue;
            }

            $count = (int) wp_count_posts($pt->name)->publish;

            $sources[] = [
                'slug'  => $pt->name,
                'label' => $pt->labels->name ?? $pt->name,
                'count' => $count,
            ];
        }

        return new WP_REST_Response($sources, 200);
    }

    // -----------------------------------------------------------------------
    // Posts (for single mode — pick a specific item)
    // -----------------------------------------------------------------------

    public function getPosts(WP_REST_Request $request): WP_REST_Response
    {
        $postType = sanitize_key((string) ($request->get_param('post_type') ?? 'post'));
        $search   = trim((string) ($request->get_param('search') ?? ''));

        $args = [
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($search !== '') {
            $args['s'] = $search;
        }

        $query = new \WP_Query($args);
        $posts = [];

        foreach ($query->posts as $post) {
            $posts[] = [
                'id'      => $post->ID,
                'title'   => get_the_title($post),
                'url'     => get_permalink($post),
                'excerpt' => wp_trim_words(strip_tags($post->post_content), 30),
                'date'    => $post->post_date,
            ];
        }

        return new WP_REST_Response($posts, 200);
    }

    // -----------------------------------------------------------------------
    // Generate
    // -----------------------------------------------------------------------

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        $postId    = (int) ($request->get_param('post_id') ?? 0);
        $platforms = $request->get_param('platforms');

        if ($postId <= 0) {
            return new WP_REST_Response(['error' => 'post_id is required.'], 400);
        }

        if (!is_array($platforms) || empty($platforms)) {
            return new WP_REST_Response(['error' => 'At least one platform is required.'], 400);
        }

        $post = get_post($postId);

        if (!$post || $post->post_status !== 'publish') {
            return new WP_REST_Response(['error' => 'Post not found or not published.'], 404);
        }

        $apiPayload = [
            'title'        => get_the_title($post),
            'body_text'    => wp_trim_words(strip_tags($post->post_content), 200),
            'url'          => get_permalink($post),
            'content_type' => $post->post_type,
            'platforms'    => array_values(array_map('sanitize_key', $platforms)),
            'adapter_context' => [
                'post_id'   => $postId,
                'post_type' => $post->post_type,
            ],
        ];

        $response = Services::apiClient()->generateSocialPosts($apiPayload);

        if (!$response->ok) {
            return new WP_REST_Response([
                'error' => $response->message ?? 'Social post generation failed.',
            ], $response->code >= 400 ? $response->code : 500);
        }

        return new WP_REST_Response([
            'status'  => 'queued',
            'message' => 'Social posts are being generated. They will be published to your connected platforms when ready.',
        ], 202);
    }
}
