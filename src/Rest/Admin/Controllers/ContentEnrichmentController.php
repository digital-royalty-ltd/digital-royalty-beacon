<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for the Content Image Enrichment automation.
 *
 * - /posts     : list posts available for enrichment (with count of H2s
 *                missing images)
 * - /analyze   : return H2 headings + their current image status for a post
 * - /generate  : dispatch image generation for each H2 missing an image
 */
final class ContentEnrichmentController
{
    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        register_rest_route('beacon/v1', '/admin/content-enrichment/posts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPosts'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/content-enrichment/analyze', [
            'methods'             => 'GET',
            'callback'            => [$this, 'analyze'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/content-enrichment/generate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate'],
            'permission_callback' => $perm,
        ]);
    }

    // -----------------------------------------------------------------------
    // Posts list
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
            $analysis = $this->analyzeH2s($post);

            $posts[] = [
                'id'                 => $post->ID,
                'title'              => get_the_title($post->ID) ?: '(no title)',
                'post_type'          => $post->post_type,
                'status'             => $post->post_status,
                'h2_total'           => $analysis['total'],
                'h2_missing_images'  => $analysis['missing'],
            ];
        }

        return new WP_REST_Response([
            'post_types' => $typeList,
            'posts'      => $posts,
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Analyze a single post
    // -----------------------------------------------------------------------

    public function analyze(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) ($request->get_param('post_id') ?? 0);
        if ($postId <= 0) {
            return new WP_REST_Response(['error' => 'post_id is required'], 400);
        }

        $post = get_post($postId);
        if (!$post) {
            return new WP_REST_Response(['error' => 'Post not found.'], 404);
        }

        $analysis = $this->analyzeH2s($post);

        return new WP_REST_Response([
            'post_id' => $postId,
            'title'   => get_the_title($postId) ?: '(no title)',
            'h2s'     => $analysis['h2s'],
            'total'   => $analysis['total'],
            'missing' => $analysis['missing'],
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Generate (dispatch image generation for each H2 missing an image)
    // -----------------------------------------------------------------------

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        $postIds     = (array) ($request->get_param('post_ids') ?? []);
        $postIds     = array_values(array_filter(array_map('intval', $postIds), fn ($id) => $id > 0));
        $styleHint   = trim((string) ($request->get_param('style_hint') ?? 'illustration'));
        $aspectRatio = trim((string) ($request->get_param('aspect_ratio') ?? 'landscape'));

        if (empty($postIds)) {
            return new WP_REST_Response(['error' => 'post_ids is required'], 400);
        }

        $validStyles = ['photographic', 'illustration', '3d', 'abstract', 'minimalist'];
        if (!in_array($styleHint, $validStyles, true)) {
            $styleHint = 'illustration';
        }

        $validAspects = ['landscape', 'square', 'portrait'];
        if (!in_array($aspectRatio, $validAspects, true)) {
            $aspectRatio = 'landscape';
        }

        $dispatched = 0;
        $skipped    = 0;
        $errors     = [];

        // Pull brand context once — shared across all dispatches.
        $visual = $this->getReportPayload('website_visual');
        $profile = $this->getReportPayload('website_profile');
        $enrichment = is_array($profile['ai_enrichment'] ?? null) ? $profile['ai_enrichment'] : [];

        $brandColors   = is_array($visual['colors'] ?? null) ? $visual['colors'] : [];
        $brandIndustry = (string) ($enrichment['industry'] ?? '');
        $brandTone     = (string) ($enrichment['tone'] ?? '');
        $audience      = '';
        $rawAudience   = $enrichment['target_audience'] ?? '';
        if (is_array($rawAudience)) {
            $audience = implode(', ', $rawAudience);
        } elseif (is_string($rawAudience)) {
            $audience = $rawAudience;
        }

        foreach ($postIds as $postId) {
            $post = get_post($postId);
            if (!$post) {
                $errors[] = "Post {$postId} not found.";
                continue;
            }

            $analysis = $this->analyzeH2s($post);

            foreach ($analysis['h2s'] as $h2) {
                if ($h2['has_image']) {
                    $skipped++;
                    continue;
                }

                $subject = $this->buildSubjectForH2($h2);

                $apiPayload = [
                    'title'           => (string) get_the_title($postId),
                    'subject'         => $subject,
                    'style_hint'      => $styleHint,
                    'aspect_ratio'    => $aspectRatio,
                    'brand_colors'    => $brandColors,
                    'brand_industry'  => $brandIndustry,
                    'brand_tone'      => $brandTone,
                    'audience'        => $audience,
                    'content_type'    => 'inline section image',
                    'keywords'        => [],
                    'adapter_context' => [
                        'post_id'    => $postId,
                        'h2_index'   => $h2['index'],
                        'h2_text'    => $h2['text'],
                        'alt_text'   => $h2['text'],
                        'image_role' => 'inline',
                    ],
                ];

                $response = Services::apiClient()->generateContentEnrichmentImage($apiPayload);

                if (!$response->ok) {
                    $errors[] = "Post {$postId} H2 #{$h2['index']}: " . ($response->message ?? 'dispatch failed');
                    continue;
                }

                $dispatched++;
            }
        }

        return new WP_REST_Response([
            'status'     => 'queued',
            'dispatched' => $dispatched,
            'skipped'    => $skipped,
            'errors'     => $errors,
            'message'    => "{$dispatched} image generation(s) queued. {$skipped} H2(s) skipped (already have images).",
        ], 202);
    }

    // -----------------------------------------------------------------------
    // H2 analysis
    // -----------------------------------------------------------------------

    /**
     * Parse a post's content for H2 headings and whether each has an image
     * immediately following it.
     *
     * @return array{total: int, missing: int, h2s: array<int, array{index: int, text: string, content: string, has_image: bool}>}
     */
    private function analyzeH2s(\WP_Post $post): array
    {
        $content = $post->post_content;
        if ($content === '') {
            return ['total' => 0, 'missing' => 0, 'h2s' => []];
        }

        $h2s = [];

        if (preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/si', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => $match) {
                $fullTag   = $match[0];
                $startPos  = $match[1];
                $endPos    = $startPos + strlen($fullTag);
                $h2Text    = wp_strip_all_tags((string) $matches[1][$i][0]);

                // Check if an image immediately follows the H2.
                $peek = substr($content, $endPos, 500);
                $hasImage = (bool) preg_match('/^\s*(<p>\s*)?(<figure\b|<img\b|<!-- wp:image)/i', $peek);

                // Extract content from this H2 to the next H2 (or end).
                $nextStart = isset($matches[0][$i + 1]) ? $matches[0][$i + 1][1] : strlen($content);
                $sectionContent = substr($content, $endPos, $nextStart - $endPos);
                $sectionText = wp_strip_all_tags(strip_shortcodes($sectionContent));
                $sectionText = (string) preg_replace('/\s+/', ' ', $sectionText);
                $sectionText = trim($sectionText);
                if (mb_strlen($sectionText) > 1000) {
                    $sectionText = mb_substr($sectionText, 0, 1000);
                }

                $h2s[] = [
                    'index'     => $i,
                    'text'      => trim($h2Text),
                    'content'   => $sectionText,
                    'has_image' => $hasImage,
                ];
            }
        }

        $missing = count(array_filter($h2s, fn ($h) => !$h['has_image']));

        return [
            'total'   => count($h2s),
            'missing' => $missing,
            'h2s'     => $h2s,
        ];
    }

    /**
     * Build a concise visual concept from the H2 text + section content.
     */
    private function buildSubjectForH2(array $h2): string
    {
        $text = trim((string) ($h2['text'] ?? ''));
        $content = trim((string) ($h2['content'] ?? ''));

        // Prefer H2 text — it's usually the semantic heading of the section.
        // Add a short content snippet if H2 is very short or generic.
        if (mb_strlen($text) < 15 && $content !== '') {
            $snippet = mb_substr($content, 0, 120);
            return "{$text} — {$snippet}";
        }

        return $text;
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
