<?php

namespace DigitalRoyalty\Beacon\Systems\Reports\Generators;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;

final class WebsiteProfileReport implements ReportGeneratorInterface
{
    public function type(): string
    {
        return 'website_profile';
    }

    public function version(): int
    {
        return 2;
    }

    public function generate(): array
    {
        $allPageStubs = $this->fetchAllPageStubs();

        $data = [
            'site'            => $this->buildSiteInfo(),
            'platform'        => $this->buildPlatformInfo(),
            'homepage'        => $this->buildHomepageProfile(),
            'key_pages'       => $this->buildKeyPages($allPageStubs),
            'content_summary' => $this->buildContentSummary(),
        ];

        $enrichResp = Services::apiClient()->analyseSiteProfile($this->buildEnrichmentSamples($data));

        if ($enrichResp->ok && is_array($enrichResp->data['analysis'] ?? null)) {
            $data['ai_enrichment'] = (array) $enrichResp->data['analysis'];

            Services::logger()->info(LogScopeEnum::REPORTS, 'generator_enriched', 'AI enrichment merged into website profile.', [
                'type'    => $this->type(),
                'version' => $this->version(),
            ]);
        } else {
            Services::logger()->warning(LogScopeEnum::REPORTS, 'generator_enrichment_skipped', 'AI enrichment unavailable; proceeding without it.', [
                'type'        => $this->type(),
                'version'     => $this->version(),
                'status_code' => $enrichResp->code,
                'message'     => $enrichResp->message,
            ]);
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // Enrichment samples — extracted from programmatic data for AI analysis
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function buildEnrichmentSamples(array $data): array
    {
        $homepage = is_array($data['homepage'] ?? null) ? $data['homepage'] : [];
        $keyPages = is_array($data['key_pages'] ?? null) ? $data['key_pages'] : [];

        return [
            'site_name'          => (string) ($data['site']['name'] ?? ''),
            'site_description'   => (string) ($data['site']['description'] ?? ''),
            'site_url'           => (string) ($data['site']['url'] ?? ''),
            'homepage_summary'   => (string) ($homepage['excerpt'] ?? ''),
            'key_page_summaries' => array_map(
                fn(array $p): array => [
                    'title'   => (string) ($p['title'] ?? ''),
                    'summary' => (string) ($p['excerpt'] ?? ''),
                ],
                array_filter($keyPages, fn($p) => is_array($p))
            ),
        ];
    }

    // -------------------------------------------------------------------------
    // Site info
    // -------------------------------------------------------------------------

    private function buildSiteInfo(): array
    {
        return [
            'name'        => (string) get_bloginfo('name'),
            'description' => (string) get_bloginfo('description'),
            'url'         => (string) home_url('/'),
            'language'    => (string) get_bloginfo('language'),
            'charset'     => (string) get_bloginfo('charset'),
        ];
    }

    // -------------------------------------------------------------------------
    // Platform info — agnostic envelope; Beacon does not assume WordPress
    // -------------------------------------------------------------------------

    private function buildPlatformInfo(): array
    {
        return [
            'name'            => 'wordpress',
            'version'         => (string) get_bloginfo('version'),
            'runtime'         => 'php',
            'runtime_version' => PHP_VERSION,
        ];
    }

    // -------------------------------------------------------------------------
    // Homepage
    // -------------------------------------------------------------------------

    private function buildHomepageProfile(): array
    {
        $showOnFront = (string) get_option('show_on_front', 'posts');

        if ($showOnFront === 'page') {
            $frontPageId = (int) get_option('page_on_front', 0);
            if ($frontPageId > 0) {
                $page = get_post($frontPageId);
                if ($page instanceof \WP_Post && $page->post_status === 'publish') {
                    return [
                        'type'    => 'static_page',
                        'title'   => $page->post_title,
                        'url'     => (string) get_permalink($page),
                        'excerpt' => $this->extractTextExcerpt($page, 600),
                    ];
                }
            }
        }

        // 'posts' in WP means a reverse-chronological content feed
        return [
            'type'  => 'feed',
            'title' => (string) get_bloginfo('name'),
            'url'   => (string) home_url('/'),
        ];
    }

    // -------------------------------------------------------------------------
    // Key pages — AI-selected from all published pages
    // -------------------------------------------------------------------------

    /**
     * Collect all published pages as lightweight identifier/title stubs (up to 50).
     * Ordered by menu_order so the most intentionally positioned pages come first.
     * The identifier is the page's URL slug — agnostic to Beacon, WP-specific locally.
     *
     * @return array<int, array{identifier: string, title: string}>
     */
    private function fetchAllPageStubs(): array
    {
        /** @var \WP_Post[] $pages */
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        return array_map(fn(\WP_Post $p) => [
            'identifier' => $p->post_name,
            'title'      => $p->post_title,
        ], $pages);
    }

    /**
     * Ask the Beacon API to identify the most important pages from the full list,
     * then fetch their content and return rich summaries.
     *
     * Also persists dr_beacon_key_pages_map (page_id + identifier) so the
     * plugin can resolve pages by ID later even if their identifiers change.
     *
     * Falls back to the first N stubs (by menu order) if the API call fails or
     * returns no usable identifiers, so the report is never empty on a fresh site.
     *
     * @param  array<int, array{identifier: string, title: string}> $stubs
     * @return array<int, array<string, mixed>>
     */
    private function buildKeyPages(array $stubs): array
    {
        if (empty($stubs)) {
            return [];
        }

        $resp     = Services::apiClient()->identifyKeyPages($stubs, 6);
        $selected = [];

        if ($resp->ok && is_array($resp->data['identifiers'] ?? null)) {
            foreach ($resp->data['identifiers'] as $identifier) {
                if (!is_string($identifier)) {
                    continue;
                }
                $page = get_page_by_path($identifier);
                if ($page instanceof \WP_Post && $page->post_status === 'publish') {
                    $selected[] = $page;
                }
            }
        }

        // Fallback: first 6 stubs by menu order (API failed or returned nothing).
        if (empty($selected)) {
            foreach (array_slice($stubs, 0, 6) as $stub) {
                $page = get_page_by_path((string) ($stub['identifier'] ?? ''));
                if ($page instanceof \WP_Post && $page->post_status === 'publish') {
                    $selected[] = $page;
                }
            }
        }

        // Persist slug → page_id map so future lookups survive slug changes.
        $map = array_map(fn(\WP_Post $p): array => [
            'page_id' => $p->ID,
            'slug'    => $p->post_name,
            'title'   => $p->post_title,
        ], $selected);
        update_option('dr_beacon_key_pages_map', $map, false);

        return array_map([$this, 'buildPageSummary'], $selected);
    }

    private function buildPageSummary(\WP_Post $page): array
    {
        return [
            'title'   => $page->post_title,
            'slug'    => $page->post_name,
            'url'     => (string) get_permalink($page),
            'excerpt' => $this->extractTextExcerpt($page, 500),
        ];
    }

    // -------------------------------------------------------------------------
    // Content summary — agnostic: array of collections, not WP post type keys
    // -------------------------------------------------------------------------

    private function buildContentSummary(): array
    {
        $postTypes = get_post_types(['public' => true], 'objects');
        $summary   = [];

        foreach ($postTypes as $postType) {
            $count     = wp_count_posts($postType->name);
            $summary[] = [
                'key'        => $postType->name,
                'label'      => $postType->label,
                'item_count' => (int) ($count->publish ?? 0),
            ];
        }

        return $summary;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function extractTextExcerpt(\WP_Post $post, int $maxChars = 500): string
    {
        if (!empty($post->post_excerpt)) {
            $text = wp_strip_all_tags($post->post_excerpt);
            $text = (string) preg_replace('/\s+/', ' ', $text);
            return trim($text);
        }

        $content = $post->post_content;
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);
        $content = (string) preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        if (strlen($content) > $maxChars) {
            $content   = substr($content, 0, $maxChars);
            $lastSpace = strrpos($content, ' ');
            if ($lastSpace !== false) {
                $content = substr($content, 0, $lastSpace);
            }
            $content .= '…';
        }

        return $content;
    }
}
