<?php

namespace DigitalRoyalty\Beacon\Systems\Deferred\Handlers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
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

        // Resolve WP routing from adapter_context (echoed back by Laravel)
        // or fall back to the content area map for older requests.
        $adapterContext = isset($data['adapter_context']) && is_array($data['adapter_context'])
            ? $data['adapter_context']
            : [];

        $postType = 'post';
        $taxInput = [];

        if (!empty($adapterContext)) {
            // New path: adapter_context carries explicit post_type + taxonomies.
            if (isset($adapterContext['post_type']) && is_string($adapterContext['post_type']) && post_type_exists($adapterContext['post_type'])) {
                $postType = $adapterContext['post_type'];
            }
            if (isset($adapterContext['taxonomies']) && is_array($adapterContext['taxonomies'])) {
                $taxInput = $adapterContext['taxonomies'];
            }
        } else {
            // Legacy path: resolve from content area map.
            $original       = $this->decodeJsonField($row['payload'] ?? null);
            $contentAreaKey = isset($original['content_area_key']) && is_string($original['content_area_key'])
                ? $original['content_area_key']
                : '';

            if ($contentAreaKey !== '') {
                $map   = get_option('dr_beacon_content_area_map', []);
                $entry = is_array($map[$contentAreaKey] ?? null) ? $map[$contentAreaKey] : [];
                $routing = is_array($entry['routing'] ?? null) ? $entry['routing'] : [];
                if (isset($routing['post_type']) && is_string($routing['post_type']) && post_type_exists($routing['post_type'])) {
                    $postType = $routing['post_type'];
                }
            }
        }

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
                    $this->setTermsAndLog($postId, $termIds, $taxonomy);
                }

                continue;
            }

            $names = array_filter(array_map('trim', explode(',', (string) $value)));
            if ($names) {
                $this->setTermsAndLog($postId, $names, $taxonomy);
            }
        }
    }

    /**
     * Wrap wp_set_object_terms so failures (non-existent taxonomy at write
     * time, capability check, DB errors) are logged. Without this, drafts
     * silently land without tags/categories and the operator only finds out
     * by clicking through every post.
     *
     * @param array<int,int|string> $terms
     */
    private function setTermsAndLog(int $postId, array $terms, string $taxonomy): void
    {
        $result = wp_set_object_terms($postId, $terms, $taxonomy, false);

        if ($result instanceof WP_Error) {
            Services::logger()->warning(
                LogScopeEnum::REPORTS,
                'taxonomy_assignment_failed',
                "Could not apply '{$taxonomy}' to draft post {$postId}: {$result->get_error_message()}",
                [
                    'post_id' => $postId,
                    'taxonomy' => $taxonomy,
                    'terms' => $terms,
                    'error_code' => $result->get_error_code(),
                    'error_message' => $result->get_error_message(),
                ]
            );
        }
    }
}