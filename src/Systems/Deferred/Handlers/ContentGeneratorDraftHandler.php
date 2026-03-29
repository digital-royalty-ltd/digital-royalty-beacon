<?php

namespace DigitalRoyalty\Beacon\Systems\Deferred\Handlers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Systems\Deferred\DeferredCompletionHandlerInterface;
use WP_Error;
use WP_Taxonomy;

final class ContentGeneratorDraftHandler implements DeferredCompletionHandlerInterface
{
    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $data
     * @return array{ok: bool, message?: string, meta?: array<string,mixed>}
     */
    public function handle(array $row, array $data): array
    {
        // Expect poll endpoint contract
        $outputs = isset($data['outputs']) && is_array($data['outputs']) ? $data['outputs'] : [];
        $first = $outputs[0] ?? null;

        $artifactId = is_array($first) && isset($first['artifact_id']) && is_string($first['artifact_id'])
            ? $first['artifact_id']
            : null;

        if (!$artifactId) {
            return [
                'ok' => false,
                'message' => 'No output artifact_id returned by poll.',
            ];
        }

        $artifactRes = Services::apiClient()->getArtifact($artifactId);

        if (!$artifactRes->ok) {
            return [
                'ok' => false,
                'message' => $artifactRes->message ?? 'Failed to fetch artifact.',
            ];
        }

        $artifact = is_array($artifactRes->data['artifact'] ?? null) ? $artifactRes->data['artifact'] : [];
        $payload = is_array($artifact['payload'] ?? null) ? $artifact['payload'] : [];

        $title = isset($payload['title']) && is_string($payload['title']) && trim($payload['title']) !== ''
            ? trim($payload['title'])
            : 'Generated Draft';

        // Your tool currently stores HTML under content_html
        $content = isset($payload['content_html']) && is_string($payload['content_html'])
            ? $payload['content_html']
            : '';

        // Resolve WP routing from the local content area map.
        // The payload stores content_area_key (the normalised map key) so we
        // can look up post_type and primary taxonomy without WP-specific fields
        // ever appearing in the Beacon API payload.
        $original       = $this->decodeJsonField($row['payload'] ?? null);
        $contentAreaKey = isset($original['content_area_key']) && is_string($original['content_area_key'])
            ? $original['content_area_key']
            : '';

        $routing  = [];
        if ($contentAreaKey !== '') {
            $map   = get_option('dr_beacon_content_area_map', []);
            $entry = is_array($map[$contentAreaKey] ?? null) ? $map[$contentAreaKey] : [];
            $routing = is_array($entry['routing'] ?? null) ? $entry['routing'] : [];
        }

        $postType = isset($routing['post_type']) && is_string($routing['post_type']) && post_type_exists($routing['post_type'])
            ? $routing['post_type']
            : 'post';

        // Build tax_input from primary_taxonomy if present; terms are assigned
        // by the editor after reviewing the draft.
        $taxInput = [];

        $postId = wp_insert_post([
            'post_type' => $postType,
            'post_status' => 'draft',
            'post_title' => $title,
            'post_content' => $content,
        ], true);

        if ($postId instanceof WP_Error) {
            return [
                'ok' => false,
                'message' => $postId->get_error_message(),
            ];
        }

        $this->applyTaxonomies((int) $postId, $postType, $taxInput);

        return [
            'ok' => true,
            'message' => 'Draft created.',
            'meta' => [
                'post_id' => (int) $postId,
                'artifact_id' => (string) $artifactId,
            ],
        ];
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>
     */
    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $taxInput
     */
    private function applyTaxonomies(int $postId, string $postType, array $taxInput): void
    {
        $allowedTaxonomies = get_object_taxonomies($postType, 'names');

        foreach ($taxInput as $taxonomy => $value) {
            $taxonomy = sanitize_key((string) $taxonomy);

            if (!in_array($taxonomy, $allowedTaxonomies, true)) {
                continue;
            }

            $taxObj = get_taxonomy($taxonomy);
            if (!$taxObj instanceof WP_Taxonomy) {
                continue;
            }

            if ($taxObj->hierarchical) {
                $termIds = is_array($value) ? array_map('absint', $value) : [];
                $termIds = array_values(array_filter($termIds, static fn ($id) => $id > 0));

                if ($termIds) {
                    wp_set_object_terms($postId, $termIds, $taxonomy, false);
                }

                continue;
            }

            $names = array_filter(array_map('trim', explode(',', (string) $value)));
            if ($names) {
                wp_set_object_terms($postId, $names, $taxonomy, false);
            }
        }
    }
}