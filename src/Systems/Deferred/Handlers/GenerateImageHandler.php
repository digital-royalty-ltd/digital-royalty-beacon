<?php

namespace DigitalRoyalty\Beacon\Systems\Deferred\Handlers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Systems\Deferred\DeferredCompletionHandlerInterface;

final class GenerateImageHandler implements DeferredCompletionHandlerInterface
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
        $payload  = is_array($artifact['payload'] ?? null) ? $artifact['payload'] : [];

        $imageUrl = isset($payload['url']) && is_string($payload['url']) ? trim($payload['url']) : '';

        if ($imageUrl === '') {
            return [
                'ok' => false,
                'message' => 'Artifact missing image URL.',
            ];
        }

        // Read adapter_context from the completed poll response (echoed by Laravel).
        $adapterContext = isset($data['adapter_context']) && is_array($data['adapter_context'])
            ? $data['adapter_context']
            : [];

        $destinationId = isset($adapterContext['destination_id']) ? (int) $adapterContext['destination_id'] : 0;

        // Get the original request payload for metadata.
        $originalPayload = [];
        if (isset($row['payload']) && is_string($row['payload'])) {
            $decoded = json_decode($row['payload'], true);
            if (is_array($decoded)) {
                $originalPayload = $decoded;
            }
        }

        $postTitle = '';
        if ($destinationId > 0) {
            $postTitle = get_the_title($destinationId) ?: '';
        }
        if ($postTitle === '') {
            $postTitle = (string) ($originalPayload['title'] ?? 'AI Generated Image');
        }

        $revisedPrompt = (string) ($payload['revised_prompt'] ?? '');

        // Download and insert into the media library with proper metadata.
        $attachmentId = $this->sideloadImage($imageUrl, $destinationId, $postTitle);

        if (is_string($attachmentId)) {
            return [
                'ok' => false,
                'message' => $attachmentId,
            ];
        }

        // Set alt text — use the post title (concise, descriptive).
        update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($postTitle));

        // Set description — use DALL-E's revised prompt as the image description.
        if ($revisedPrompt !== '') {
            wp_update_post([
                'ID'           => $attachmentId,
                'post_content' => sanitize_textarea_field($revisedPrompt),
            ]);
        }

        // Set as featured image if a destination post was specified.
        if ($destinationId > 0 && get_post($destinationId)) {
            set_post_thumbnail($destinationId, $attachmentId);
        }

        return [
            'ok' => true,
            'message' => 'Image generated and attached.',
            'meta' => [
                'attachment_id'  => $attachmentId,
                'destination_id' => $destinationId,
                'artifact_id'    => (string) $artifactId,
            ],
        ];
    }

    /**
     * Download a remote image and insert it into the WordPress media library.
     *
     * @return int|string Attachment ID on success, error message string on failure.
     */
    private function sideloadImage(string $url, int $parentPostId, string $title): int|string
    {
        if (!function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Use the post title as the image description (shows in media library).
        $result = media_sideload_image(
            $url,
            $parentPostId > 0 ? $parentPostId : 0,
            $title,
            'id'
        );

        if (is_wp_error($result)) {
            return $result->get_error_message();
        }

        $attachmentId = (int) $result;

        // Rename the attachment to a friendly slug based on the title.
        $slug = sanitize_title($title);
        wp_update_post([
            'ID'        => $attachmentId,
            'post_name' => $slug,
            'post_title' => $title,
        ]);

        return $attachmentId;
    }
}
