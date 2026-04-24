<?php

namespace DigitalRoyalty\Beacon\Systems\Deferred\Handlers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Systems\Deferred\DeferredCompletionHandlerInterface;

/**
 * Handles completed content-enrichment image generations.
 *
 * Downloads the image, adds it to the media library, then inserts it
 * into the target post's content immediately after the Nth H2 heading
 * (specified via adapter_context.h2_index). If an image already appears
 * after that H2, the insert is skipped (idempotent re-runs).
 */
final class ContentEnrichmentImageHandler implements DeferredCompletionHandlerInterface
{
    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $data
     * @return array{ok: bool, message?: string, meta?: array<string,mixed>}
     */
    public function handle(array $row, array $data): array
    {
        $outputs = isset($data['outputs']) && is_array($data['outputs']) ? $data['outputs'] : [];
        $first = $outputs[0] ?? null;

        $artifactId = is_array($first) && isset($first['artifact_id']) && is_string($first['artifact_id'])
            ? $first['artifact_id']
            : null;

        if (!$artifactId) {
            return ['ok' => false, 'message' => 'No output artifact_id returned by poll.'];
        }

        $artifactRes = Services::apiClient()->getArtifact($artifactId);

        if (!$artifactRes->ok) {
            return ['ok' => false, 'message' => $artifactRes->message ?? 'Failed to fetch artifact.'];
        }

        $artifact = is_array($artifactRes->data['artifact'] ?? null) ? $artifactRes->data['artifact'] : [];
        $payload  = is_array($artifact['payload'] ?? null) ? $artifact['payload'] : [];
        $imageUrl = isset($payload['url']) && is_string($payload['url']) ? trim($payload['url']) : '';

        if ($imageUrl === '') {
            return ['ok' => false, 'message' => 'Artifact missing image URL.'];
        }

        // Read adapter_context from the completed poll response.
        $adapterContext = isset($data['adapter_context']) && is_array($data['adapter_context'])
            ? $data['adapter_context']
            : [];

        $postId   = (int) ($adapterContext['post_id'] ?? 0);
        $h2Index  = (int) ($adapterContext['h2_index'] ?? -1);
        $h2Text   = (string) ($adapterContext['h2_text'] ?? '');
        $altText  = (string) ($adapterContext['alt_text'] ?? $h2Text);

        if ($postId <= 0 || $h2Index < 0) {
            return ['ok' => false, 'message' => 'Missing post_id or h2_index in adapter_context.'];
        }

        $post = get_post($postId);
        if (!$post) {
            return ['ok' => false, 'message' => "Post {$postId} not found."];
        }

        // Side-load the image into the media library.
        $attachmentId = $this->sideloadImage($imageUrl, $postId, $altText);
        if (is_string($attachmentId)) {
            return ['ok' => false, 'message' => $attachmentId];
        }

        // Set alt text for accessibility / SEO.
        update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($altText));

        // Insert the image into the post content after the target H2.
        $inserted = $this->insertImageAfterH2($post, $attachmentId, $h2Index);

        if (!$inserted) {
            return [
                'ok'      => true, // Image saved to library, just not placed.
                'message' => 'Image added to media library but H2 already had an image (skipped).',
                'meta'    => [
                    'attachment_id' => $attachmentId,
                    'post_id'       => $postId,
                    'h2_index'      => $h2Index,
                    'skipped'       => true,
                ],
            ];
        }

        return [
            'ok' => true,
            'message' => "Image inserted after H2 #{$h2Index}.",
            'meta' => [
                'attachment_id' => $attachmentId,
                'post_id'       => $postId,
                'h2_index'      => $h2Index,
            ],
        ];
    }

    /**
     * Download an image and insert it into the WordPress media library.
     *
     * @return int|string Attachment ID on success, error message on failure.
     */
    private function sideloadImage(string $url, int $parentPostId, string $title): int|string
    {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $result = media_sideload_image($url, $parentPostId, $title, 'id');

        if (is_wp_error($result)) {
            return $result->get_error_message();
        }

        $attachmentId = (int) $result;

        if ($title !== '') {
            $slug = sanitize_title($title);
            wp_update_post([
                'ID'         => $attachmentId,
                'post_name'  => $slug,
                'post_title' => $title,
            ]);
        }

        return $attachmentId;
    }

    /**
     * Insert an image into a post's content immediately after the Nth H2.
     *
     * Returns false if the H2 already has an image right after it, or the H2
     * doesn't exist. True if the image was inserted and the post updated.
     */
    private function insertImageAfterH2(\WP_Post $post, int $attachmentId, int $h2Index): bool
    {
        $content = $post->post_content;
        if ($content === '') {
            return false;
        }

        // Find all H2 headings — matches both HTML and Gutenberg block h2s.
        if (!preg_match_all('/<h2\b[^>]*>.*?<\/h2>/si', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        if (!isset($matches[0][$h2Index])) {
            return false;
        }

        $h2Match  = $matches[0][$h2Index];
        $h2End    = $h2Match[1] + strlen($h2Match[0]);

        // Peek at the next ~500 chars of content to see if an image already follows.
        $peek = substr($content, $h2End, 500);
        if (preg_match('/^\s*(<p>\s*)?(<figure\b|<img\b|<!-- wp:image)/i', $peek)) {
            return false; // Image already present — skip.
        }

        $imageTag = $this->buildImageFigure($attachmentId);

        $newContent = substr($content, 0, $h2End) . "\n\n" . $imageTag . "\n\n" . substr($content, $h2End);

        wp_update_post([
            'ID'           => $post->ID,
            'post_content' => $newContent,
        ]);

        return true;
    }

    /**
     * Build an HTML figure element for the image. Uses Gutenberg block
     * syntax if the existing post content uses blocks, otherwise plain HTML.
     */
    private function buildImageFigure(int $attachmentId): string
    {
        $url = wp_get_attachment_image_url($attachmentId, 'large');
        if (!$url) {
            return '';
        }

        $alt = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
        $alt = esc_attr($alt);

        // Use Gutenberg image block syntax so it renders cleanly in both editors.
        return sprintf(
            "<!-- wp:image {\"id\":%d,\"sizeSlug\":\"large\"} -->\n" .
            "<figure class=\"wp-block-image size-large\"><img src=\"%s\" alt=\"%s\" class=\"wp-image-%d\"/></figure>\n" .
            "<!-- /wp:image -->",
            $attachmentId,
            esc_url($url),
            $alt,
            $attachmentId
        );
    }
}
