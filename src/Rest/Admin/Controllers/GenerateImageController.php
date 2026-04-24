<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for the Image Generator tool.
 */
final class GenerateImageController
{
    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        register_rest_route('beacon/v1', '/admin/generate-image/posts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPosts'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/generate-image/generate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate'],
            'permission_callback' => $perm,
        ]);
    }

    // -----------------------------------------------------------------------
    // List posts available for image generation
    // -----------------------------------------------------------------------

    public function getPosts(WP_REST_Request $request): WP_REST_Response
    {
        $search   = trim((string) ($request->get_param('search') ?? ''));
        $postType = trim((string) ($request->get_param('post_type') ?? ''));

        $postTypes = get_post_types(['public' => true], 'objects');
        $typeList  = [];

        foreach ($postTypes as $pt) {
            if ($pt->name === 'attachment') {
                continue;
            }
            $typeList[] = [
                'slug'  => $pt->name,
                'label' => $pt->labels->singular_name ?? $pt->name,
            ];
        }

        $queryArgs = [
            'post_type'      => $postType !== '' ? [$postType] : array_column($typeList, 'slug'),
            'post_status'    => ['publish', 'draft', 'pending', 'future'],
            'posts_per_page' => 30,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ];

        if ($search !== '') {
            $queryArgs['s'] = $search;
        }

        $query = new \WP_Query($queryArgs);
        $posts = [];

        foreach ($query->posts as $post) {
            $thumbnailId  = get_post_thumbnail_id($post->ID);
            $thumbnailUrl = $thumbnailId ? wp_get_attachment_image_url($thumbnailId, 'thumbnail') : null;

            $posts[] = [
                'id'            => $post->ID,
                'title'         => get_the_title($post->ID) ?: '(no title)',
                'post_type'     => $post->post_type,
                'status'        => $post->post_status,
                'has_thumbnail' => (bool) $thumbnailId,
                'thumbnail_url' => $thumbnailUrl ?: null,
            ];
        }

        return new WP_REST_Response([
            'post_types' => $typeList,
            'posts'      => $posts,
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Generate image
    // -----------------------------------------------------------------------

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        $postId      = (int) ($request->get_param('post_id') ?? 0);
        $styleHint   = trim((string) ($request->get_param('style_hint') ?? 'photographic'));
        $aspectRatio = trim((string) ($request->get_param('aspect_ratio') ?? 'landscape'));
        $subject     = trim((string) ($request->get_param('subject') ?? ''));
        $mood        = trim((string) ($request->get_param('mood') ?? ''));
        $composition = trim((string) ($request->get_param('composition') ?? ''));

        if ($postId <= 0) {
            return new WP_REST_Response(['error' => 'post_id is required'], 400);
        }

        $post = get_post($postId);

        if (!$post) {
            return new WP_REST_Response(['error' => 'Post not found.'], 404);
        }

        $title = get_the_title($postId) ?: '(no title)';

        // Extract keywords from the post's categories/tags.
        $keywords = $this->extractKeywords($postId);

        // Pull visual identity report for brand colors.
        $visual = $this->getReportPayload('website_visual');
        $brandColors = is_array($visual['colors'] ?? null) ? $visual['colors'] : [];

        // Pull imagery report for composition defaults.
        $imagery = $this->getReportPayload('website_imagery');
        $imageryComp = is_array($imagery['composition'] ?? null) ? $imagery['composition'] : [];

        // Default composition from imagery report if user didn't specify.
        if ($composition === '' && ($imageryComp['minimal'] ?? false)) {
            $composition = 'single clear focal point, clean and minimal, no collage';
        }

        // Pull profile context for industry/audience/tone.
        $profile = $this->getReportPayload('website_profile');
        $enrichment = is_array($profile['ai_enrichment'] ?? null) ? $profile['ai_enrichment'] : [];

        $brandIndustry = (string) ($enrichment['industry'] ?? '');
        $brandTone     = (string) ($enrichment['tone'] ?? '');

        // target_audience is an array — join for the API payload.
        $audience = '';
        $rawAudience = $enrichment['target_audience'] ?? '';
        if (is_array($rawAudience)) {
            $audience = implode(', ', $rawAudience);
        } elseif (is_string($rawAudience)) {
            $audience = $rawAudience;
        }

        // Determine content type label.
        $postTypeObj = get_post_type_object($post->post_type);
        $contentType = $postTypeObj ? ($postTypeObj->labels->singular_name ?? $post->post_type) : $post->post_type;

        $apiPayload = [
            'title'           => $title,
            'subject'         => $subject, // Empty if user didn't provide — Laravel picks a visual metaphor from title.
            'style_hint'      => $styleHint,
            'aspect_ratio'    => $aspectRatio,
            'mood'            => $mood,
            'composition'     => $composition,
            'brand_colors'    => $brandColors,
            'brand_industry'  => $brandIndustry,
            'brand_tone'      => $brandTone,
            'audience'        => $audience,
            'content_type'    => $contentType,
            'keywords'        => $keywords,
            'adapter_context' => [
                'destination_id' => $postId,
                'set_as'         => 'featured_image',
            ],
        ];

        $response = Services::apiClient()->generateImage($apiPayload);

        if (!$response->ok) {
            return new WP_REST_Response([
                'error' => $response->message ?? 'Image generation request failed.',
            ], $response->code >= 400 ? $response->code : 500);
        }

        return new WP_REST_Response([
            'status'  => 'queued',
            'message' => 'Your image is being generated. It will be set as the featured image when ready.',
        ], 202);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @return string[]
     */
    private function extractKeywords(int $postId): array
    {
        $keywords = [];

        $categories = get_the_category($postId);
        if (is_array($categories)) {
            foreach ($categories as $cat) {
                $keywords[] = html_entity_decode($cat->name, ENT_QUOTES, 'UTF-8');
            }
        }

        $tags = get_the_tags($postId);
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $keywords[] = html_entity_decode($tag->name, ENT_QUOTES, 'UTF-8');
            }
        }

        return array_values(array_unique(array_slice($keywords, 0, 10)));
    }

    /**
     * @return array<string, mixed>
     */
    private function getReportPayload(string $reportType): array
    {
        global $wpdb;

        $repo = new ReportsRepository($wpdb);
        $row  = $repo->getLatestByType($reportType);

        if (!$row) {
            return [];
        }

        $payloadJson = $row['payload'] ?? null;
        $payload     = is_string($payloadJson) ? json_decode($payloadJson, true) : null;

        return is_array($payload) ? $payload : [];
    }
}
