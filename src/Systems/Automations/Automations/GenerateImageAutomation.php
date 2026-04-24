<?php

namespace DigitalRoyalty\Beacon\Systems\Automations\Automations;

use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationCategoryEnum;
use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationModeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationTypeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Reports\ReportTypeEnum;
use DigitalRoyalty\Beacon\Systems\Automations\AbstractAutomation;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationDependency;
use DigitalRoyalty\Beacon\Systems\Automations\DispatchedToolRunner;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationActor;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationResult;

/**
 * Describes the Generate Image tool as an automation entry.
 *
 * This automation has no background deferred key — it is interactive.
 * The admin UI presents a tool form where users pick a content piece
 * and generate a featured image for it.
 */
final class GenerateImageAutomation extends AbstractAutomation
{
    public function key(): string
    {
        return AutomationTypeEnum::GENERATE_IMAGE;
    }

    public function label(): string
    {
        return 'Image Generator';
    }

    public function description(): string
    {
        return 'Generate AI-created featured images for your content. Pick any page or post and Beacon creates a professional image and sets it as the featured image.';
    }

    public function dependencies(): array
    {
        return [
            new AutomationDependency(ReportTypeEnum::WEBSITE_PROFILE),
            new AutomationDependency(ReportTypeEnum::WEBSITE_VISUAL),
            new AutomationDependency(ReportTypeEnum::WEBSITE_IMAGERY),
        ];
    }

    public function deferredKey(): ?string
    {
        return null; // Interactive tool — no background deferred job.
    }

    public function categories(): array
    {
        return [AutomationCategoryEnum::CONTENT];
    }

    public function supportedModes(): array
    {
        return [AutomationModeEnum::SINGLE, AutomationModeEnum::MULTIPLE];
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'WordPress post ID to generate a featured image for.',
                ],
                'style_hint' => [
                    'type'        => 'string',
                    'description' => 'Visual style for the generated image.',
                    'enum'        => ['photographic', 'illustration', '3d', 'abstract', 'minimalist'],
                    'default'     => 'photographic',
                ],
                'aspect_ratio' => [
                    'type'        => 'string',
                    'description' => 'Aspect ratio.',
                    'enum'        => ['landscape', 'square', 'portrait'],
                    'default'     => 'landscape',
                ],
                'subject' => [
                    'type'        => 'string',
                    'description' => 'Optional visual concept to depict. Leave empty to derive from the post.',
                ],
            ],
            'required' => ['post_id'],
        ];
    }

    /**
     * Dispatches the Laravel generate-image tool for a specific post, polls
     * to completion, sideloads the returned image into the media library and
     * sets it as the featured image.
     *
     * @param array<string, mixed> $parameters
     */
    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        $postId = is_numeric($parameters['post_id'] ?? null) ? (int) $parameters['post_id'] : 0;
        if ($postId <= 0) {
            return InvocationResult::failed('post_id is required.', 'invalid_parameters');
        }

        $post = get_post($postId);
        if (! $post) {
            return InvocationResult::failed("Post #{$postId} not found.", 'post_not_found');
        }

        $bodyText = wp_strip_all_tags((string) $post->post_content);
        $payload = array_filter([
            'title'           => (string) $post->post_title,
            'body_text'       => mb_substr($bodyText, 0, 2000),
            'style_hint'      => is_string($parameters['style_hint'] ?? null) ? $parameters['style_hint'] : 'photographic',
            'aspect_ratio'    => is_string($parameters['aspect_ratio'] ?? null) ? $parameters['aspect_ratio'] : 'landscape',
            'subject'         => is_string($parameters['subject'] ?? null) ? $parameters['subject'] : null,
            'adapter_context' => ['destination_id' => $postId],
        ], fn ($v) => $v !== null);

        $result = DispatchedToolRunner::run('tools/generate-image', $payload, timeoutSeconds: 120);
        if (! $result['ok']) {
            return InvocationResult::failed($result['error'] ?? 'Image generation failed.', $result['error_code'] ?? null);
        }

        $artifactPayload = is_array($result['artifact']['payload'] ?? null) ? $result['artifact']['payload'] : [];
        $url = is_string($artifactPayload['url'] ?? null) ? (string) $artifactPayload['url'] : '';
        if ($url === '') {
            return InvocationResult::failed('Image artifact had no url.', 'no_image_url');
        }

        $attachmentId = $this->sideloadImage($url, $postId, (string) $post->post_title);
        if (is_wp_error($attachmentId)) {
            return InvocationResult::failed('Image sideload failed: '.$attachmentId->get_error_message(), 'sideload_failed');
        }

        set_post_thumbnail($postId, (int) $attachmentId);

        return InvocationResult::completed(
            message: "Set featured image on post #{$postId}.",
            data: [
                'post_id'       => $postId,
                'attachment_id' => (int) $attachmentId,
                'image_url'     => $url,
                'credits'       => (int) ($result['credits'] ?? 0),
                'actor'         => $actor->toString(),
            ],
        );
    }

    /**
     * Sideload an external image URL into the WP media library and return
     * the new attachment ID.
     *
     * @return int|\WP_Error
     */
    private function sideloadImage(string $url, int $parentPostId, string $desc)
    {
        if (! function_exists('media_sideload_image')) {
            require_once ABSPATH.'wp-admin/includes/media.php';
            require_once ABSPATH.'wp-admin/includes/file.php';
            require_once ABSPATH.'wp-admin/includes/image.php';
        }

        $id = media_sideload_image($url, $parentPostId, $desc, 'id');

        return is_wp_error($id) ? $id : (int) $id;
    }
}
