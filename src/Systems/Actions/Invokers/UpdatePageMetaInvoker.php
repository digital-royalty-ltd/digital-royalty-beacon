<?php

namespace DigitalRoyalty\Beacon\Systems\Actions\Invokers;

use DigitalRoyalty\Beacon\Systems\Actions\ActionInvokerInterface;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationActor;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationResult;

/**
 * wp.page.update_meta
 *
 * Updates a post or page's title and/or meta description.
 *
 * Parameters (agnostic — never WP-specific):
 *   - identifier (required) — opaque adapter identifier; we expect
 *     "post:<id>" or "page:<id>". Anything else is rejected.
 *   - title (optional)        — new post_title
 *   - description (optional)  — new meta description (Yoast/RankMath/native)
 */
final class UpdatePageMetaInvoker implements ActionInvokerInterface
{
    public function slug(): string
    {
        return 'wp.page.update_meta';
    }

    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        $identifier = is_string($parameters['identifier'] ?? null) ? (string) $parameters['identifier'] : '';
        $title = is_string($parameters['title'] ?? null) ? trim((string) $parameters['title']) : null;
        $description = is_string($parameters['description'] ?? null) ? trim((string) $parameters['description']) : null;

        if ($identifier === '') {
            return InvocationResult::failed('identifier is required.');
        }

        $postId = $this->resolveIdentifier($identifier);
        if ($postId === null) {
            return InvocationResult::failed("Cannot resolve identifier: {$identifier}");
        }

        $post = get_post($postId);
        if (! $post) {
            return InvocationResult::failed("Post not found: {$postId}");
        }

        $changed = [];

        if ($title !== null && $title !== '' && $title !== $post->post_title) {
            $update = wp_update_post([
                'ID' => $postId,
                'post_title' => $title,
            ], true);
            if (is_wp_error($update)) {
                return InvocationResult::failed('Title update failed: '.$update->get_error_message());
            }
            $changed['title'] = $title;
        }

        if ($description !== null) {
            $this->writeMetaDescription($postId, $description);
            $changed['description'] = $description;
        }

        if ($changed === []) {
            return InvocationResult::completed('No changes — values matched existing.', [
                'post_id' => $postId,
                'changed' => [],
            ]);
        }

        return InvocationResult::completed('Page meta updated.', [
            'post_id' => $postId,
            'changed' => $changed,
        ]);
    }

    /**
     * Resolve an opaque identifier into a WP post ID.
     * We accept "post:<id>", "page:<id>", or just "<id>".
     */
    private function resolveIdentifier(string $identifier): ?int
    {
        if (ctype_digit($identifier)) {
            return (int) $identifier;
        }

        if (preg_match('/^[a-z_]+:(\d+)$/', $identifier, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Write to whichever SEO plugin is active, falling back to a generic
     * post meta key if neither Yoast nor RankMath is detected.
     */
    private function writeMetaDescription(int $postId, string $description): void
    {
        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            update_post_meta($postId, '_yoast_wpseo_metadesc', $description);

            return;
        }

        // Rank Math
        if (defined('RANK_MATH_VERSION')) {
            update_post_meta($postId, 'rank_math_description', $description);

            return;
        }

        // Generic fallback — at least the data is recorded.
        update_post_meta($postId, '_dr_beacon_meta_description', $description);
    }
}
