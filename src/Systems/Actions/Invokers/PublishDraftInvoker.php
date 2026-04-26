<?php

namespace DigitalRoyalty\Beacon\Systems\Actions\Invokers;

use DigitalRoyalty\Beacon\Systems\Actions\ActionInvokerInterface;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationActor;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationResult;

/**
 * wp.post.publish_draft
 *
 * Publish a draft. Refuses to act on already-published posts so the action
 * never silently undoes a manual scheduling decision.
 *
 * Parameters:
 *   - identifier (required)
 */
final class PublishDraftInvoker implements ActionInvokerInterface
{
    public function slug(): string
    {
        return 'wp.post.publish_draft';
    }

    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        $identifier = is_string($parameters['identifier'] ?? null) ? (string) $parameters['identifier'] : '';
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

        if ($post->post_status !== 'draft') {
            return InvocationResult::failed("Refusing to publish — post status is '{$post->post_status}', not draft.");
        }

        $update = wp_update_post([
            'ID' => $postId,
            'post_status' => 'publish',
        ], true);
        if (is_wp_error($update)) {
            return InvocationResult::failed('Publish failed: '.$update->get_error_message());
        }

        return InvocationResult::completed('Draft published.', [
            'post_id' => $postId,
            'permalink' => get_permalink($postId) ?: null,
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
