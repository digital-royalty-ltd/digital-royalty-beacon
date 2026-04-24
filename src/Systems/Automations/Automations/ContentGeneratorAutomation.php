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
 * Describes the Content Generator tool as an automation entry.
 *
 * This automation has no background deferred key — it is interactive.
 * The admin UI presents a link into the Content Generator tool rather
 * than a "Run" button.
 */
final class ContentGeneratorAutomation extends AbstractAutomation
{
    public function key(): string
    {
        return AutomationTypeEnum::CONTENT_GENERATOR;
    }

    public function label(): string
    {
        return 'Content Generator';
    }

    public function description(): string
    {
        return 'Generate AI-drafted content for any content area on your site. Choose a topic and Beacon writes the full draft directly into your posts.';
    }

    public function dependencies(): array
    {
        return [
            new AutomationDependency(ReportTypeEnum::WEBSITE_PROFILE),
            new AutomationDependency(ReportTypeEnum::WEBSITE_CONTENT_AREAS),
            new AutomationDependency(ReportTypeEnum::WEBSITE_VOICE),
        ];
    }

    public function deferredKey(): ?string
    {
        return null; // Interactive tool — no background deferred job.
    }

    public function categories(): array
    {
        return [AutomationCategoryEnum::CONTENT, AutomationCategoryEnum::SEO];
    }

    public function supportedModes(): array
    {
        return [AutomationModeEnum::SINGLE];
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content_area_key' => [
                    'type'        => 'string',
                    'description' => 'Key of the content area to generate into. If provided, post_type and taxonomies auto-resolve.',
                ],
                'post_type' => [
                    'type'        => 'string',
                    'description' => 'WordPress post type to create. Alternative to content_area_key.',
                ],
                'title' => [
                    'type'        => 'string',
                    'description' => 'Optional working title or topic.',
                ],
                'brief' => [
                    'type'        => 'string',
                    'description' => 'Optional creative brief describing angle, tone, or specific points.',
                ],
                'taxonomies' => [
                    'type'        => 'object',
                    'description' => 'Taxonomy term IDs keyed by taxonomy slug.',
                    'additionalProperties' => [
                        'type'  => 'array',
                        'items' => ['type' => 'integer'],
                    ],
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * Dispatches the Laravel content-generator tool, polls to completion, then
     * creates a WP draft post from the returned article artifact.
     *
     * @param array<string, mixed> $parameters
     */
    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        $postType = is_string($parameters['post_type'] ?? null) ? (string) $parameters['post_type'] : 'post';
        $title    = is_string($parameters['title'] ?? null) ? (string) $parameters['title'] : null;
        $brief    = is_string($parameters['brief'] ?? null) ? (string) $parameters['brief'] : null;
        $taxonomies = is_array($parameters['taxonomies'] ?? null) ? $parameters['taxonomies'] : [];

        $payload = array_filter([
            'content_area_key' => $parameters['content_area_key'] ?? null,
            'topic'            => $title,
            'intent'           => $brief,
            'adapter_context'  => [
                'post_type'  => $postType,
                'taxonomies' => $taxonomies,
            ],
        ], fn ($v) => $v !== null);

        $result = DispatchedToolRunner::run('tools/content-generator/generate', $payload);
        if (! $result['ok']) {
            return InvocationResult::failed($result['error'] ?? 'Content generation failed.', $result['error_code'] ?? null);
        }

        $artifactPayload = is_array($result['artifact']['payload'] ?? null) ? $result['artifact']['payload'] : [];
        $articleTitle = is_string($artifactPayload['title'] ?? null) ? (string) $artifactPayload['title'] : ($title ?? 'Draft');
        $contentHtml  = is_string($artifactPayload['content_html'] ?? null) ? (string) $artifactPayload['content_html'] : '';

        $postId = wp_insert_post([
            'post_title'   => $articleTitle,
            'post_content' => $contentHtml,
            'post_type'    => $postType,
            'post_status'  => 'draft',
        ], true);

        if (is_wp_error($postId)) {
            return InvocationResult::failed('wp_insert_post failed: '.$postId->get_error_message(), 'wp_insert_failed');
        }

        // Apply taxonomies from the agent's request (if any).
        foreach ($taxonomies as $taxonomy => $termIds) {
            if (is_string($taxonomy) && is_array($termIds)) {
                wp_set_post_terms((int) $postId, array_map('intval', $termIds), $taxonomy);
            }
        }

        return InvocationResult::completed(
            message: "Drafted '{$articleTitle}' (post #{$postId}).",
            data: [
                'post_id'     => (int) $postId,
                'post_type'   => $postType,
                'title'       => $articleTitle,
                'artifact_id' => $result['artifact_id'] ?? null,
                'credits'     => (int) ($result['credits'] ?? 0),
                'actor'       => $actor->toString(),
            ],
        );
    }
}
