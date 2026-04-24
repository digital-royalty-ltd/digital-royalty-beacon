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
 * Describes the News Article Generator tool as an automation entry.
 *
 * This automation is interactive — the user provides a topic and niche,
 * and Beacon finds a relevant news article online, then rewrites it as
 * an original news report delivered to the chosen destination.
 */
final class NewsArticleGeneratorAutomation extends AbstractAutomation
{
    public function key(): string
    {
        return AutomationTypeEnum::NEWS_ARTICLE_GENERATOR;
    }

    public function label(): string
    {
        return 'News Article Generator';
    }

    public function description(): string
    {
        return 'Stay on top of your industry. Beacon finds the latest news on any topic, then writes an original report from your perspective — ready to publish.';
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
        return [AutomationModeEnum::SINGLE, AutomationModeEnum::SCHEDULED];
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topic' => [
                    'type'        => 'string',
                    'description' => 'News topic to search for (e.g. "AI adoption in finance").',
                ],
                'niche' => [
                    'type'        => 'string',
                    'description' => 'Industry or niche scope for the search.',
                ],
                'post_type' => [
                    'type'        => 'string',
                    'description' => 'WordPress post type for the generated article.',
                ],
            ],
            'required' => ['topic'],
        ];
    }

    /**
     * Dispatches the Laravel news-article tool, polls to completion, creates
     * a WP draft post from the returned article.
     *
     * @param array<string, mixed> $parameters
     */
    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        $topic = is_string($parameters['topic'] ?? null) ? trim((string) $parameters['topic']) : '';
        if ($topic === '') {
            return InvocationResult::failed('topic is required.', 'invalid_parameters');
        }
        $niche = is_string($parameters['niche'] ?? null) ? (string) $parameters['niche'] : 'general';
        $postType = is_string($parameters['post_type'] ?? null) ? (string) $parameters['post_type'] : 'post';

        $payload = [
            'topic'           => $topic,
            'niche'           => $niche,
            'adapter_context' => ['post_type' => $postType],
        ];

        $result = DispatchedToolRunner::run('tools/news-article/generate', $payload, timeoutSeconds: 120);
        if (! $result['ok']) {
            return InvocationResult::failed($result['error'] ?? 'News article generation failed.', $result['error_code'] ?? null);
        }

        $artifactPayload = is_array($result['artifact']['payload'] ?? null) ? $result['artifact']['payload'] : [];
        $articleTitle = is_string($artifactPayload['title'] ?? null) ? (string) $artifactPayload['title'] : $topic;
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
            message: "News report drafted: '{$articleTitle}' (post #{$postId}).",
            data: [
                'post_id'   => (int) $postId,
                'post_type' => $postType,
                'title'     => $articleTitle,
                'topic'     => $topic,
                'credits'   => (int) ($result['credits'] ?? 0),
                'actor'     => $actor->toString(),
            ],
        );
    }
}
