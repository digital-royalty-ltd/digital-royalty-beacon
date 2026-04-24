<?php

namespace DigitalRoyalty\Beacon\Systems\Automations\Automations;

use DigitalRoyalty\Beacon\Services\Services;
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
 * Describes the Social Media Content Sharer tool.
 *
 * Single mode: pick a specific post, pick platforms, share it.
 * Scheduled mode: pick source types, pick platforms, configure cycling,
 * and Beacon shares content automatically on the schedule.
 */
final class SocialShareAutomation extends AbstractAutomation
{
    public function key(): string
    {
        return AutomationTypeEnum::SOCIAL_SHARE;
    }

    public function label(): string
    {
        return 'Social Media Sharer';
    }

    public function description(): string
    {
        return 'Turn your content into platform-ready social posts. Pick what to share, choose your platforms, and Beacon writes tailored posts — on demand or on autopilot.';
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
        return [AutomationCategoryEnum::SOCIAL, AutomationCategoryEnum::CONTENT];
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
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'Post ID to share.',
                ],
                'platforms' => [
                    'type'        => 'array',
                    'description' => 'Platforms to share to.',
                    'items'       => [
                        'type' => 'string',
                        'enum' => ['x', 'facebook', 'linkedin', 'instagram'],
                    ],
                    'minItems'    => 1,
                ],
            ],
            'required' => ['post_id', 'platforms'],
        ];
    }

    /**
     * Dispatches the social-share tool, polls to completion, then publishes
     * each returned post to its platform via Laravel's social-publish endpoint.
     *
     * @param array<string, mixed> $parameters
     */
    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        $postId = is_numeric($parameters['post_id'] ?? null) ? (int) $parameters['post_id'] : 0;
        $platforms = is_array($parameters['platforms'] ?? null)
            ? array_values(array_filter(array_map('strval', $parameters['platforms'])))
            : [];
        if ($postId <= 0 || empty($platforms)) {
            return InvocationResult::failed('post_id and platforms are required.', 'invalid_parameters');
        }

        $post = get_post($postId);
        if (! $post || $post->post_status !== 'publish') {
            return InvocationResult::failed("Post #{$postId} must exist and be published before sharing.", 'post_not_shareable');
        }

        $payload = [
            'title'           => (string) $post->post_title,
            'body_text'       => wp_strip_all_tags(mb_substr((string) $post->post_content, 0, 3000)),
            'url'             => (string) get_permalink($postId),
            'platforms'       => $platforms,
            'adapter_context' => ['source_post_id' => $postId],
        ];

        $result = DispatchedToolRunner::run('tools/social-share/generate', $payload, timeoutSeconds: 120);
        if (! $result['ok']) {
            return InvocationResult::failed($result['error'] ?? 'Social share generation failed.', $result['error_code'] ?? null);
        }

        $posts = is_array($result['artifact']['payload']['posts'] ?? null) ? $result['artifact']['payload']['posts'] : [];
        if (empty($posts)) {
            return InvocationResult::failed('Social share returned no posts.', 'no_posts');
        }

        // Publish each generated post. The publish endpoint handles per-platform
        // OAuth + rate limiting; we accumulate a results array for the ledger.
        $client = Services::apiClient();
        $platformResults = [];
        foreach ($posts as $p) {
            if (! is_array($p)) {
                continue;
            }
            $platform = (string) ($p['platform'] ?? '');
            $text = (string) ($p['text'] ?? '');
            if ($platform === '' || $text === '') {
                continue;
            }

            $publishResponse = $client->publishSocialPost([
                'platform'  => $platform,
                'text'      => $text,
                'hashtags'  => is_array($p['hashtags'] ?? null) ? $p['hashtags'] : [],
                'url'       => (string) get_permalink($postId),
                'source_post_id' => $postId,
            ]);

            $platformResults[] = [
                'platform' => $platform,
                'status'   => $publishResponse->ok ? 'published' : 'failed',
                'message'  => $publishResponse->message,
                'code'     => $publishResponse->code,
            ];
        }

        $published = array_filter($platformResults, fn ($r) => $r['status'] === 'published');
        $summary = count($published).' of '.count($platformResults).' platforms published successfully.';

        return InvocationResult::completed(
            message: $summary,
            data: [
                'post_id'          => $postId,
                'platform_results' => $platformResults,
                'credits'          => (int) ($result['credits'] ?? 0),
                'actor'            => $actor->toString(),
            ],
        );
    }
}
