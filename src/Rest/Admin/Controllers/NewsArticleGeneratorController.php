<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for the News Article Generator tool.
 *
 * The tool searches the web for recent news on a topic/niche, then rewrites
 * the article as an original news report delivered to the chosen destination.
 */
final class NewsArticleGeneratorController
{
    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        register_rest_route('beacon/v1', '/admin/news-article-generator/destinations', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getDestinations'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/news-article-generator/generate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate'],
            'permission_callback' => $perm,
        ]);
    }

    // -----------------------------------------------------------------------
    // Destinations (post types + taxonomies)
    // -----------------------------------------------------------------------

    public function getDestinations(WP_REST_Request $request): WP_REST_Response
    {
        $postTypes = get_post_types(['public' => true], 'objects');
        $destinations = [];

        foreach ($postTypes as $pt) {
            if ($pt->name === 'attachment') {
                continue;
            }

            $taxonomies = [];
            $taxObjects = get_object_taxonomies($pt->name, 'objects');

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

            $destinations[] = [
                'slug'       => $pt->name,
                'label'      => $pt->labels->singular_name ?? $pt->name,
                'taxonomies' => $taxonomies,
            ];
        }

        return new WP_REST_Response($destinations, 200);
    }

    // -----------------------------------------------------------------------
    // Generate
    // -----------------------------------------------------------------------

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        $topic    = trim((string) ($request->get_param('topic')    ?? ''));
        $niche    = trim((string) ($request->get_param('niche')    ?? ''));
        $postType = trim((string) ($request->get_param('post_type') ?? ''));
        $taxonomies = $request->get_param('taxonomies');

        if ($topic === '') {
            return new WP_REST_Response(['error' => 'topic is required'], 400);
        }

        if ($niche === '') {
            return new WP_REST_Response(['error' => 'niche is required'], 400);
        }

        // Resolve the destination post type.
        $resolvedPostType = 'post';
        if ($postType !== '' && post_type_exists($postType)) {
            $resolvedPostType = $postType;
        }

        // Build taxonomy input from the request (term IDs keyed by taxonomy slug).
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

        // Build a CMS-agnostic payload for the Beacon API.
        // adapter_context carries WP-specific routing that Laravel echoes back unchanged.
        $apiPayload = [
            'topic'           => $topic,
            'niche'           => $niche,
            'tone'            => trim((string) ($request->get_param('tone')     ?? 'professional')),
            'audience'        => trim((string) ($request->get_param('audience') ?? 'general')),
            'locale'          => trim((string) ($request->get_param('locale')   ?? 'en-GB')),
            'content_type'    => trim((string) ($request->get_param('content_type') ?? 'article')),
            'adapter_context' => [
                'post_type'  => $resolvedPostType,
                'taxonomies' => $taxInput,
            ],
        ];

        $response = Services::apiClient()->generateNewsArticle($apiPayload);

        if (!$response->ok) {
            return new WP_REST_Response([
                'error' => $response->message ?? 'News article generation request failed.',
            ], $response->code >= 400 ? $response->code : 500);
        }

        // 202 Accepted — deferred system will poll and create the draft.
        return new WP_REST_Response([
            'status'  => 'queued',
            'message' => 'Your news article is being generated. It will appear in your drafts when ready.',
        ], 202);
    }
}
