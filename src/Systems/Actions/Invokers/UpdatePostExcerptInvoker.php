<?php

namespace DigitalRoyalty\Beacon\Systems\Actions\Invokers;

use DigitalRoyalty\Beacon\Systems\Actions\ActionInvokerInterface;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationActor;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationResult;

/**
 * wp.post.update_excerpt
 *
 * Updates a post's excerpt. Idempotent — bails when the new excerpt matches
 * the current value so re-runs are safe.
 *
 * Parameters:
 *   - identifier (required) — "post:<id>" / "page:<id>" / "<id>"
 *   - excerpt (required)
 */
final class UpdatePostExcerptInvoker implements ActionInvokerInterface
{
    public function slug(): string
    {
        return 'wp.post.update_excerpt';
    }

    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        $identifier = is_string($parameters['identifier'] ?? null) ? (string) $parameters['identifier'] : '';
        $excerpt = is_string($parameters['excerpt'] ?? null) ? trim((string) $parameters['excerpt']) : '';

        if ($identifier === '') {
            return InvocationResult::failed('identifier is required.');
        }
        if ($excerpt === '') {
            return InvocationResult::failed('excerpt is required.');
        }

        $postId = $this->resolveIdentifier($identifier);
        if ($postId === null) {
            return InvocationResult::failed("Cannot resolve identifier: {$identifier}");
        }

        $post = get_post($postId);
        if (! $post) {
            return InvocationResult::failed("Post not found: {$postId}");
        }

        if ((string) $post->post_excerpt === $excerpt) {
            return InvocationResult::completed('Excerpt unchanged — value matched existing.', [
                'post_id' => $postId,
                'changed' => false,
            ]);
        }

        $update = wp_update_post([
            'ID' => $postId,
            'post_excerpt' => $excerpt,
        ], true);
        if (is_wp_error($update)) {
            return InvocationResult::failed('Excerpt update failed: '.$update->get_error_message());
        }

        return InvocationResult::completed('Excerpt updated.', [
            'post_id' => $postId,
            'changed' => true,
            'excerpt' => $excerpt,
        ]);
    }

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
}
