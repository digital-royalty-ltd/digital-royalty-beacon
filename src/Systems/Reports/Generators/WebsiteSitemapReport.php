<?php

namespace DigitalRoyalty\Beacon\Systems\Reports\Generators;

use DigitalRoyalty\Beacon\Support\Enums\Reports\ReportTypeEnum;
use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;

/**
 * Generates a neutral, agnostic snapshot of the site's URL and content structure.
 *
 * Includes a full page tree and, for each collection (post type), the most
 * recent published items with title, URL, and date. This serves as a
 * lightweight content inventory that AI tools can slice into for:
 * - Deduplication (don't write about something that already exists)
 * - Internal linking (reference other pages/posts naturally)
 */
final class WebsiteSitemapReport implements ReportGeneratorInterface
{
    public function type(): string
    {
        return ReportTypeEnum::WEBSITE_SITEMAP;
    }

    public function version(): int
    {
        return 3;
    }

    public function generate(): array
    {
        $pages = $this->fetchAllPublishedPages();
        $collections = $this->buildCollections();

        return [
            'pages'                  => $this->buildPageTree($pages, 0, 0),
            'collections'            => $collections,
            'total_pages'            => count($pages),
            'total_collection_items' => array_sum(array_column($collections, 'item_count')),
        ];
    }

    // -----------------------------------------------------------------------
    // Page tree
    // -----------------------------------------------------------------------

    /** @return \WP_Post[] */
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
                'slug'         => $page->post_name,
                'title'        => $page->post_title,
                'url'          => (string) get_permalink($page),
                'depth'        => $depth,
                // Page age — without this the agent can't distinguish a
                // brand new page (low impressions because it hasn't ramped)
                // from a stale underperformer (low impressions because it's
                // mature and weak). Refresh decisions need this.
                'published_at' => $page->post_date_gmt ? gmdate('Y-m-d', strtotime($page->post_date_gmt)) : null,
                'modified_at'  => $page->post_modified_gmt ? gmdate('Y-m-d', strtotime($page->post_modified_gmt)) : null,
                'children'     => $children,
            ];
        }

        return $tree;
    }

    // -----------------------------------------------------------------------
    // Collections (non-page post types) with recent items
    // -----------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function buildCollections(): array
    {
        $collections = [];

        // Standard blog posts
        $postCounts = wp_count_posts('post');
        $postPublished = (int) ($postCounts->publish ?? 0);

        if ($postPublished > 0) {
            $collections[] = [
                'key'        => 'post',
                'label'      => 'Blog Posts',
                'item_count' => $postPublished,
                'items'      => $this->recentItems('post'),
            ];
        }

        // Custom public post types
        $postTypes = get_post_types(['public' => true, '_builtin' => false], 'objects');

        foreach ($postTypes as $pt) {
            if ($pt->name === 'attachment') {
                continue;
            }

            $counts    = wp_count_posts($pt->name);
            $published = (int) ($counts->publish ?? 0);

            if ($published === 0) {
                continue;
            }

            $collections[] = [
                'key'        => $pt->name,
                'label'      => $pt->label,
                'item_count' => $published,
                'items'      => $this->recentItems($pt->name),
            ];
        }

        return $collections;
    }

    /**
     * Fetch all published items for a post type.
     * Only stores title, URL, and date — lightweight enough for full inventories.
     *
     * @return array<int, array{title: string, url: string, date: string|null}>
     */
    private function recentItems(string $postType): array
    {
        /** @var \WP_Post[] $posts */
        $posts = get_posts([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        $items = [];
        foreach ($posts as $post) {
            $items[] = [
                'title' => $post->post_title,
                'url'   => (string) get_permalink($post),
                'date'  => $post->post_date ? date('Y-m-d', strtotime($post->post_date)) : null,
            ];
        }

        return $items;
    }
}
