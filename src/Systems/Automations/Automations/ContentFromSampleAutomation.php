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
 * Describes the "Create Content From Sample" tool as an automation entry.
 *
 * This automation is interactive — the user provides a URL or pastes content,
 * and Beacon analyses it, extracts a brief, then rewrites it as a fresh draft.
 */
final class ContentFromSampleAutomation extends AbstractAutomation
{
    public function key(): string
    {
        return AutomationTypeEnum::CONTENT_FROM_SAMPLE;
    }

    public function label(): string
    {
        return 'Create Content From Sample';
    }

    public function description(): string
    {
        return 'Provide a URL or paste existing content, and Beacon will analyse it, extract the key themes, and produce a fresh rewritten draft as a new post.';
    }

    public function dependencies(): array
    {
        return [
            new AutomationDependency(ReportTypeEnum::WEBSITE_PROFILE),
            new AutomationDependency(ReportTypeEnum::WEBSITE_VOICE),
        ];
    }

    public function deferredKey(): ?string
    {
        return null; // Interactive tool — user triggers it manually.
    }

    public function categories(): array
    {
        return [AutomationCategoryEnum::CONTENT];
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
                'sample_url' => [
                    'type'        => 'string',
                    'description' => 'URL of the existing content to rewrite from.',
                ],
                'sample_text' => [
                    'type'        => 'string',
                    'description' => 'Pasted text content to rewrite from. Alternative to sample_url.',
                ],
                'post_type' => [
                    'type'        => 'string',
                    'description' => 'WordPress post type for the new draft.',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        $sampleUrl  = is_string($parameters['sample_url'] ?? null) ? trim((string) $parameters['sample_url']) : '';
        $sampleText = is_string($parameters['sample_text'] ?? null) ? trim((string) $parameters['sample_text']) : '';
        if ($sampleUrl === '' && $sampleText === '') {
            return InvocationResult::failed('Either sample_url or sample_text is required.', 'invalid_parameters');
        }
        $postType = is_string($parameters['post_type'] ?? null) ? (string) $parameters['post_type'] : 'post';

        $payload = array_filter([
            'url'             => $sampleUrl !== '' ? $sampleUrl : null,
            'body_text'       => $sampleText !== '' ? $sampleText : null,
            'post_type'       => $postType,
            'adapter_context' => ['post_type' => $postType],
        ], fn ($v) => $v !== null);

        $result = DispatchedToolRunner::run('tools/content-from-sample', $payload, timeoutSeconds: 120);
        if (! $result['ok']) {
            return InvocationResult::failed($result['error'] ?? 'Content generation failed.', $result['error_code'] ?? null);
        }

        $artifactPayload = is_array($result['artifact']['payload'] ?? null) ? $result['artifact']['payload'] : [];
        $articleTitle = is_string($artifactPayload['title'] ?? null) ? (string) $artifactPayload['title'] : 'Draft from sample';
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

        return InvocationResult::completed(
            message: "Drafted '{$articleTitle}' from sample (post #{$postId}).",
            data: [
                'post_id'   => (int) $postId,
                'post_type' => $postType,
                'title'     => $articleTitle,
                'credits'   => (int) ($result['credits'] ?? 0),
                'actor'     => $actor->toString(),
            ],
        );
    }
}
