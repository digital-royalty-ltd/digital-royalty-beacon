<?php

namespace DigitalRoyalty\Beacon\Systems\Reports\Generators;

use DigitalRoyalty\Beacon\Support\Enums\Reports\ReportTypeEnum;
use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;

/**
 * Generates a neutral, agnostic snapshot of the site's URL and content structure.
 *
 * Unlike WebsiteContentAreasReport (which uses AI to identify intent and topics),
 * this report is a plain structural inventory: what pages exist, how they are
 * hierarchically arranged, and what collection types (non-page post types) the
 * site contains. No AI helper call is made.
 *
 * Used by Gap Analysis to understand what content the site actually has, so the
 * AI can compare it against declared content areas and surface opportunities.
 *
 * This report will grow over time (e.g. traffic stats, SEO metrics) without
 * affecting WebsiteContentAreasReport's domain.
 */
final class WebsiteSitemapReport implements ReportGeneratorInterface
{
    public function type(): string
    {
        return ReportTypeEnum::WEBSITE_SITEMAP;
    }

    public function version(): int
    {
        return 1;
    }

    public function generate(): array
    {
        $pages = $this->fetchAllPublishedPages();

        $collections   = $this->buildCollections();
        $totalItems    = array_sum(array_column($collections, 'item_count'));

        return [
            'pages'                => $this->buildPageTree($pages, 0, 0),
            'collections'          => $collections,
            'total_pages'          => count($pages),
            'total_collection_items' => $totalItems,
        ];
    }

    // -----------------------------------------------------------------------
    // Page tree
    // -----------------------------------------------------------------------

    /**
     * @return \WP_Post[]
     */
    private function fetchAllPublishedPages(): array
    {
        $results = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ]);

        return is_array($results) ? $results : [];
    }

    /**
     * Recursively build a neutral page tree.
     *
     * @param \WP_Post[] $pages
     * @return array<int, array<string,mixed>>
     */
    private function buildPageTree(array $pages, int $parentId, int $depth): array
    {
        $tree = [];

        foreach ($pages as $page) {
            if ((int) $page->post_parent !== $parentId) {
                continue;
            }

            $children = $this->buildPageTree($pages, $page->ID, $depth + 1);

            $tree[] = [
                'slug'     => $page->post_name,
                'title'    => $page->post_title,
                'depth'    => $depth,
                'children' => $children,
            ];
        }

        return $tree;
    }

    // -----------------------------------------------------------------------
    // Collections (non-page post types)
    // -----------------------------------------------------------------------

    /**
     * @return array<int, array{key: string, label: string, item_count: int}>
     */
    private function buildCollections(): array
    {
        $collections = [];

        // Custom (non-built-in) public post types.
        $postTypes = get_post_types(['public' => true, '_builtin' => false], 'objects');

        foreach ($postTypes as $pt) {
            $counts    = wp_count_posts($pt->name);
            $published = (int) ($counts->publish ?? 0);

            if ($published === 0) {
                continue;
            }

            $collections[] = [
                'key'        => sanitize_title($pt->label),
                'label'      => $pt->label,
                'item_count' => $published,
            ];
        }

        // Standard blog posts — include only if the site publishes them.
        $postCounts = wp_count_posts('post');
        $postPublished = (int) ($postCounts->publish ?? 0);

        if ($postPublished > 0) {
            array_unshift($collections, [
                'key'        => 'blog-posts',
                'label'      => 'Blog Posts',
                'item_count' => $postPublished,
            ]);
        }

        return $collections;
    }
}
