<?php

namespace DigitalRoyalty\Beacon\Systems\Automations\Automations;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationCategoryEnum;
use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationModeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationTypeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Deferred\DeferredRequestKeyEnum;
use DigitalRoyalty\Beacon\Support\Enums\Reports\ReportTypeEnum;
use DigitalRoyalty\Beacon\Systems\Automations\AbstractAutomation;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationDependency;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationActor;
use DigitalRoyalty\Beacon\Systems\Automations\InvocationResult;

/**
 * Gap Analysis automation.
 *
 * Sends site profile, content areas, and sitemap to the Beacon API.
 * Laravel AI produces two artifact lists:
 *   - Content recommendations: topics to create within existing content areas.
 *   - Area recommendations: entirely new content silos the site should build.
 *
 * The deferred completion handler stores artifact IDs. The plugin fetches
 * the full artifact data from Laravel in real time when displaying results.
 */
final class GapAnalysisAutomation extends AbstractAutomation
{
    public function key(): string
    {
        return AutomationTypeEnum::GAP_ANALYSIS;
    }

    public function label(): string
    {
        return 'Content Gap Analysis';
    }

    public function description(): string
    {
        return 'Identifies content opportunities by comparing your site profile and existing content areas against what\'s missing. Returns recommendations for new content within existing silos, and new silos to build.';
    }

    public function dependencies(): array
    {
        return [
            new AutomationDependency(ReportTypeEnum::WEBSITE_PROFILE,       maxAgeDays: 30),
            new AutomationDependency(ReportTypeEnum::WEBSITE_CONTENT_AREAS,  maxAgeDays: 30),
            new AutomationDependency(ReportTypeEnum::WEBSITE_SITEMAP,        maxAgeDays: 7),
        ];
    }

    public function deferredKey(): ?string
    {
        return DeferredRequestKeyEnum::GAP_ANALYSIS;
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
            'type'       => 'object',
            'properties' => [
                'focus' => [
                    'type'        => 'string',
                    'description' => 'Optional: narrow the analysis to a specific theme or content area (e.g. "SEO for SMBs").',
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * Gathers the site's content inventory + local brand reports and sends
     * them to Laravel's analyse-content-gaps endpoint. Returns the ranked
     * gap list + credit cost for the channel's monthly_work_spent tracker.
     *
     * @param array<string, mixed> $parameters
     */
    public function invoke(array $parameters, InvocationActor $actor): InvocationResult
    {
        $focus = is_string($parameters['focus'] ?? null) ? trim((string) $parameters['focus']) : null;

        try {
            $inventory = $this->buildInventory();
            $context   = $this->loadReportContext();

            $response = Services::apiClient()->analyseContentGaps([
                'inventory'     => $inventory,
                'content_areas' => $context['content_areas'],
                'profile'       => $context['profile'],
                'voice'         => $context['voice'],
                'focus'         => $focus,
            ]);

            if (! $response->ok) {
                return InvocationResult::failed(
                    'Gap analysis API call failed: '.($response->message ?? 'unknown error'),
                    'api_call_failed'
                );
            }

            $data = is_array($response->data) ? $response->data : [];
            $gaps = is_array($data['gaps'] ?? null) ? $data['gaps'] : [];
            $summary = is_string($data['summary'] ?? null) ? $data['summary'] : '';
            $credits = (int) ($data['credits'] ?? 0);

            return InvocationResult::completed(
                message: $summary !== '' ? $summary : sprintf('Identified %d content gaps.', count($gaps)),
                data: [
                    'summary'         => $summary,
                    'gaps'            => $gaps,
                    'credits'         => $credits,
                    'inventory_count' => count($inventory),
                    'focus'           => $focus,
                    'actor'           => $actor->toString(),
                ],
            );
        } catch (\Throwable $e) {
            return InvocationResult::failed(
                'Gap analysis threw: '.$e->getMessage(),
                'exception'
            );
        }
    }

    /**
     * Summarise the site's current published content for the analyser.
     * Keeps it lightweight: recent posts/pages by title + excerpt, capped so
     * the prompt stays small.
     *
     * @return array<int, array{title: string, collection: string, summary: string}>
     */
    private function buildInventory(): array
    {
        $inventory = [];

        foreach (get_post_types(['public' => true], 'names') as $postType) {
            if ($postType === 'attachment') {
                continue;
            }

            $posts = get_posts([
                'post_type'      => $postType,
                'post_status'    => 'publish',
                'numberposts'    => 40,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'suppress_filters' => false,
            ]);

            foreach ($posts as $post) {
                $excerpt = has_excerpt($post) ? get_the_excerpt($post) : '';
                if ($excerpt === '') {
                    $excerpt = wp_trim_words(wp_strip_all_tags((string) $post->post_content), 30, '…');
                }

                $inventory[] = [
                    'title'      => (string) $post->post_title,
                    'collection' => $postType,
                    'summary'    => $excerpt,
                ];
            }
        }

        return $inventory;
    }

    /**
     * Pull cached brand reports from the plugin's local store to enrich the
     * gaps prompt.
     *
     * @return array{content_areas: array<int, array<string, mixed>>, profile: array<string, mixed>, voice: array<string, mixed>}
     */
    private function loadReportContext(): array
    {
        global $wpdb;
        $repo = new ReportsRepository($wpdb);

        $decode = static function ($row): array {
            if (! is_array($row) || ! isset($row['payload'])) {
                return [];
            }
            $decoded = json_decode((string) $row['payload'], true);

            return is_array($decoded) ? $decoded : [];
        };

        $contentAreas = $decode($repo->getLatestByType(ReportTypeEnum::WEBSITE_CONTENT_AREAS));

        return [
            'content_areas' => is_array($contentAreas['content_areas'] ?? null) ? $contentAreas['content_areas'] : [],
            'profile'       => $decode($repo->getLatestByType(ReportTypeEnum::WEBSITE_PROFILE)),
            'voice'         => $decode($repo->getLatestByType(ReportTypeEnum::WEBSITE_VOICE)),
        ];
    }
}
