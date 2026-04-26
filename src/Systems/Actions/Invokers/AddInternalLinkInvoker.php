<?php

namespace DigitalRoyalty\Beacon\Systems\Actions\Invokers;

use DigitalRoyalty\Beacon\Systems\Actions\ActionInvokerInterface;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationActor;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationResult;

/**
 * wp.post.add_internal_link
 *
 * Inserts an internal link from one post into another, in the first paragraph
 * after a context hint match (or at the end if no hint provided).
 *
 * Parameters (agnostic):
 *   - source_identifier (required) — post the link should be added IN
 *   - target_identifier (required) — post the link points TO
 *   - anchor_text (required)
 *   - context_hint (optional) — text to anchor the insertion near
 *
 * Refuses if a link to the target URL already exists in the source — won't
 * accidentally duplicate links the agent forgot it already created.
 */
final class AddInternalLinkInvoker implements ActionInvokerInterface
{
    public function slug(): string
    {
        return 'wp.post.add_internal_link';
    }

    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        $sourceId = $this->resolveIdentifier((string) ($parameters['source_identifier'] ?? ''));
        $targetId = $this->resolveIdentifier((string) ($parameters['target_identifier'] ?? ''));
        $anchor = is_string($parameters['anchor_text'] ?? null) ? trim((string) $parameters['anchor_text']) : '';
        $hint = is_string($parameters['context_hint'] ?? null) ? trim((string) $parameters['context_hint']) : '';

        if ($sourceId === null || $targetId === null) {
            return InvocationResult::failed('source_identifier and target_identifier are required (use "post:<id>" or "<id>").');
        }
        if ($anchor === '') {
            return InvocationResult::failed('anchor_text is required.');
        }
        if ($sourceId === $targetId) {
            return InvocationResult::failed('source and target cannot be the same post.');
        }

        $source = get_post($sourceId);
        if (! $source) {
            return InvocationResult::failed("Source post not found: {$sourceId}");
        }
        $targetUrl = get_permalink($targetId);
        if (! $targetUrl) {
            return InvocationResult::failed("Target post not found: {$targetId}");
        }

        $content = (string) $source->post_content;

        // Idempotency: bail if the source already links to the target URL.
        if (str_contains($content, 'href="'.esc_url($targetUrl).'"')) {
            return InvocationResult::completed('Link already exists — no change.', [
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'changed' => false,
            ]);
        }

        $linkHtml = '<a href="'.esc_url($targetUrl).'">'.esc_html($anchor).'</a>';
        $insertion = ' '.$linkHtml;

        $newContent = $hint !== '' && str_contains($content, $hint)
            ? $this->insertAfter($content, $hint, $insertion)
            : trim($content)."\n\n".$linkHtml;

        $update = wp_update_post([
            'ID' => $sourceId,
            'post_content' => $newContent,
        ], true);

        if (is_wp_error($update)) {
            return InvocationResult::failed('Failed to update post content: '.$update->get_error_message());
        }

        return InvocationResult::completed('Internal link inserted.', [
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'target_url' => $targetUrl,
            'anchor_text' => $anchor,
            'changed' => true,
        ]);
    }

    private function resolveIdentifier(string $identifier): ?int
    {
        if ($identifier === '') {
            return null;
        }
        if (ctype_digit($identifier)) {
            return (int) $identifier;
        }
        if (preg_match('/^[a-z_]+:(\d+)$/', $identifier, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Insert $insertion immediately after the first occurrence of $needle in $haystack.
     * If the needle isn't found, returns $haystack unchanged.
     */
    private function insertAfter(string $haystack, string $needle, string $insertion): string
    {
        $pos = strpos($haystack, $needle);
        if ($pos === false) {
            return $haystack;
        }
        $end = $pos + strlen($needle);

        return substr($haystack, 0, $end).$insertion.substr($haystack, $end);
    }
}
