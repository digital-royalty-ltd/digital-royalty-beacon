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
 * Content Image Enrichment — scans a post for H2 headings and generates
 * a contextual image after each one that doesn't already have an image.
 *
 * Idempotent: re-running skips H2s that already have an image, so it
 * works for both new content and partially enriched older content.
 */
final class ContentImageEnrichmentAutomation extends AbstractAutomation
{
    public function key(): string
    {
        return AutomationTypeEnum::CONTENT_IMAGE_ENRICHMENT;
    }

    public function label(): string
    {
        return 'Content Image Enrichment';
    }

    public function description(): string
    {
        return 'Break up long articles with relevant imagery. Beacon scans your content for section headings and generates a contextual image after each one, only adding where it is missing.';
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
        return null; // Interactive — each H2 spawns its own deferred image gen.
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
                'post_ids' => [
                    'type'        => 'array',
                    'description' => 'WordPress post IDs to enrich. One or more.',
                    'items'       => ['type' => 'integer'],
                    'minItems'    => 1,
                ],
                'style_hint' => [
                    'type'        => 'string',
                    'description' => 'Visual style for generated images.',
                    'enum'        => ['illustration', 'photographic', '3d', 'abstract', 'minimalist'],
                    'default'     => 'illustration',
                ],
                'aspect_ratio' => [
                    'type'        => 'string',
                    'description' => 'Aspect ratio for generated images.',
                    'enum'        => ['landscape', 'square', 'portrait'],
                    'default'     => 'landscape',
                ],
            ],
            'required' => ['post_ids'],
        ];
    }

    /**
     * For each provided post, scans the content for H2 headings and queues
     * one image generation per H2 that doesn't already have one immediately
     * after it. Uses the same generate-image tool as the featured-image
     * automation but inserts the image inline after the target H2.
     *
     * V1 scope: processes the FIRST missing H2 per post per invocation
     * (keeps per-turn credit cost bounded and gives the agent the chance to
     * review before enriching further). Re-invoking walks the next H2.
     *
     * @param array<string, mixed> $parameters
     */
    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        $postIds = is_array($parameters['post_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $parameters['post_ids']), fn ($id) => $id > 0))
            : [];
        if (empty($postIds)) {
            return InvocationResult::failed('post_ids is required.', 'invalid_parameters');
        }
        $styleHint = is_string($parameters['style_hint'] ?? null) ? $parameters['style_hint'] : 'illustration';
        $aspectRatio = is_string($parameters['aspect_ratio'] ?? null) ? $parameters['aspect_ratio'] : 'landscape';

        $enriched = [];
        $totalCredits = 0;
        $errors = [];

        foreach ($postIds as $postId) {
            $post = get_post($postId);
            if (! $post) {
                $errors[] = "Post #{$postId} not found.";
                continue;
            }

            $content = (string) $post->post_content;
            $h2s = $this->findH2Headings($content);
            $target = $this->firstMissingH2($content, $h2s);
            if ($target === null) {
                // Nothing to enrich — post is already fully enriched or has no H2s.
                continue;
            }

            $payload = array_filter([
                'title'           => (string) $post->post_title,
                'body_text'       => wp_strip_all_tags(mb_substr($content, 0, 2000)),
                'style_hint'      => $styleHint,
                'aspect_ratio'    => $aspectRatio,
                'subject'         => $target['text'],
                'adapter_context' => [
                    'post_id'  => $postId,
                    'h2_index' => $target['index'],
                    'h2_text'  => $target['text'],
                ],
            ], fn ($v) => $v !== null);

            $result = DispatchedToolRunner::run('tools/generate-image', $payload, timeoutSeconds: 120);
            if (! $result['ok']) {
                $errors[] = "Post #{$postId} image gen failed: ".($result['error'] ?? 'unknown');
                continue;
            }

            $url = $result['artifact']['payload']['url'] ?? '';
            if (! is_string($url) || $url === '') {
                $errors[] = "Post #{$postId} image artifact had no url.";
                continue;
            }

            $attachmentId = $this->sideloadImage((string) $url, $postId, $target['text']);
            if (is_wp_error($attachmentId)) {
                $errors[] = "Post #{$postId} sideload failed: ".$attachmentId->get_error_message();
                continue;
            }

            $updated = $this->insertImageAfterH2($content, $target['index'], (int) $attachmentId, (string) $url, $target['text']);
            wp_update_post(['ID' => $postId, 'post_content' => $updated]);

            $enriched[] = [
                'post_id'       => $postId,
                'h2_index'      => $target['index'],
                'h2_text'       => $target['text'],
                'attachment_id' => (int) $attachmentId,
            ];
            $totalCredits += (int) ($result['credits'] ?? 0);
        }

        if (empty($enriched) && ! empty($errors)) {
            return InvocationResult::failed('No posts were enriched. Errors: '.implode('; ', $errors), 'all_failed');
        }

        $summary = empty($enriched)
            ? 'No posts had missing H2 images to enrich.'
            : 'Enriched '.count($enriched).' post(s) with inline images.';

        return InvocationResult::completed(
            message: $summary,
            data: [
                'enriched' => $enriched,
                'errors'   => $errors,
                'credits'  => $totalCredits,
                'actor'    => $actor->toString(),
            ],
        );
    }

    /**
     * @return array<int, array{index: int, text: string, offset: int, length: int}>
     */
    private function findH2Headings(string $content): array
    {
        if (! preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $list = [];
        foreach ($matches[0] as $i => $match) {
            $list[] = [
                'index'  => $i,
                'text'   => wp_strip_all_tags((string) $matches[1][$i][0]),
                'offset' => (int) $match[1],
                'length' => strlen((string) $match[0]),
            ];
        }

        return $list;
    }

    /**
     * @param array<int, array{index: int, text: string, offset: int, length: int}> $h2s
     * @return array{index: int, text: string, offset: int, length: int}|null
     */
    private function firstMissingH2(string $content, array $h2s): ?array
    {
        foreach ($h2s as $h2) {
            // Look at the 400 chars immediately after this H2. If no <img and no
            // wp-image class, treat as missing.
            $after = substr($content, $h2['offset'] + $h2['length'], 400);
            if (! preg_match('/<img|wp-block-image|class="[^"]*wp-image/i', (string) $after)) {
                return $h2;
            }
        }

        return null;
    }

    private function insertImageAfterH2(string $content, int $h2Index, int $attachmentId, string $url, string $altText): string
    {
        $h2s = $this->findH2Headings($content);
        if (! isset($h2s[$h2Index])) {
            return $content;
        }
        $target = $h2s[$h2Index];
        $insertPos = $target['offset'] + $target['length'];

        $alt = esc_attr($altText);
        $src = esc_url($url);
        $block = "\n<!-- wp:image {\"id\":{$attachmentId}} -->\n"
               ."<figure class=\"wp-block-image\"><img src=\"{$src}\" alt=\"{$alt}\" class=\"wp-image-{$attachmentId}\"/></figure>\n"
               ."<!-- /wp:image -->\n";

        return substr($content, 0, $insertPos).$block.substr($content, $insertPos);
    }

    /**
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
