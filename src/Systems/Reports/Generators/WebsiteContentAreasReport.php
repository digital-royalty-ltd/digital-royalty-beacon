<?php

namespace DigitalRoyalty\Beacon\Systems\Reports\Generators;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;

/**
 * Generates the website_content_areas report.
 *
 * ## Agnosticism and local routing
 *
 * Beacon is CMS-agnostic, so the submitted report must never contain
 * WordPress-specific field names or values (post type keys, term IDs, page IDs).
 * However, when Beacon later instructs the plugin to place generated content
 * into a content area (e.g. "Services"), the plugin must know how to route
 * that back to the correct WP structure.
 *
 * This is resolved with two parallel outputs:
 *
 * 1. **Submitted report** — agnostic. content_areas contains only:
 *    label, intent, topics. The sitemap includes slugs (neutral) but not
 *    WP post type keys.
 *
 * 2. **Local map** (`dr_beacon_content_area_map` WP option) — WP-specific.
 *    Keyed by normalised label. Each entry holds the full routing info
 *    (post_type, taxonomy, page_id, etc.) needed to place content in WP.
 *
 * The correlation is done via temporary `ref` fields added to the sitemap
 * before it is sent to the AI helper. The AI echoes those refs back so the
 * plugin can look up routing info per content area. Refs are stripped from
 * everything before submission to Beacon.
 */
final class WebsiteContentAreasReport implements ReportGeneratorInterface
{
    public function type(): string
    {
        return 'website_content_areas';
    }

    public function version(): int
    {
        return 2;
    }

    public function generate(): array
    {
        // Build the sitemap annotated with ref IDs, and a parallel refMap
        // that maps each ref → WP routing info. The refs are ephemeral: they
        // travel to the AI helper and back, then are stripped before anything
        // is stored or submitted.
        [$sitemapWithRefs, $refMap] = $this->buildSitemapWithRefs();

        // The submitted report gets a clean, ref-free sitemap without WP keys.
        $sitemapForReport = $this->stripRoutingData($sitemapWithRefs);

        $data = [
            'reading_settings' => $this->buildReadingSettings(),
            'navigation'       => $this->buildNavigation(),
            'sitemap'          => $sitemapForReport,
            'content_areas'    => [],
        ];

        $resp = Services::apiClient()->analyseContentAreas($sitemapWithRefs);

        if ($resp->ok && is_array($resp->data['content_areas'] ?? null)) {
            $rawAreas = $resp->data['content_areas'];

            // Build and persist the local WP routing map before stripping refs.
            $localMap = $this->buildLocalContentAreaMap($rawAreas, $refMap);
            update_option('dr_beacon_content_area_map', $localMap, false);

            // Strip refs from the AI response — the submitted report is agnostic.
            $data['content_areas'] = array_map(static function (array $area): array {
                unset($area['ref']);
                return $area;
            }, array_filter($rawAreas, 'is_array'));

            Services::logger()->info(LogScopeEnum::REPORTS, 'generator_content_areas_analysed', 'AI content area analysis merged into report.', [
                'type'    => $this->type(),
                'version' => $this->version(),
                'count'   => count($data['content_areas']),
            ]);
        } else {
            Services::logger()->warning(LogScopeEnum::REPORTS, 'generator_content_areas_skipped', 'AI content area analysis unavailable; content_areas left empty.', [
                'type'        => $this->type(),
                'version'     => $this->version(),
                'status_code' => $resp->code,
                'message'     => $resp->message,
            ]);
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // Reading settings — agnostic field names, no CMS-internal IDs
    // -------------------------------------------------------------------------

    private function buildReadingSettings(): array
    {
        $showOnFront = (string) get_option('show_on_front', 'posts');
        $frontPageId = (int) get_option('page_on_front', 0);
        $feedPageId  = (int) get_option('page_for_posts', 0);

        return [
            'front_page_type' => $showOnFront === 'page' ? 'static_page' : 'feed',
            'front_page_slug' => $frontPageId > 0 ? (string) get_post_field('post_name', $frontPageId) : null,
            'feed_page_slug'  => $feedPageId > 0 ? (string) get_post_field('post_name', $feedPageId) : null,
            'items_per_page'  => (int) get_option('posts_per_page', 10),
        ];
    }

    // -------------------------------------------------------------------------
    // Navigation menus
    // -------------------------------------------------------------------------

    private function buildNavigation(): array
    {
        $menus = wp_get_nav_menus();
        if (empty($menus) || !is_array($menus)) {
            return [];
        }

        $locations      = get_nav_menu_locations();
        $locationByMenu = array_flip($locations);

        $result = [];
        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            if (!is_array($items)) {
                continue;
            }

            $result[] = [
                'name'     => $menu->name,
                'location' => $locationByMenu[$menu->term_id] ?? null,
                'items'    => $this->buildMenuTree($items, 0, 3),
            ];
        }

        return $result;
    }

    private function buildMenuTree(array $items, int $parentId, int $maxDepth): array
    {
        if ($maxDepth === 0) {
            return [];
        }

        $tree = [];
        foreach ($items as $item) {
            if ((int) $item->menu_item_parent !== $parentId) {
                continue;
            }

            $node = [
                'title' => $item->title,
                'url'   => $item->url,
            ];

            $children = $this->buildMenuTree($items, (int) $item->ID, $maxDepth - 1);
            if (!empty($children)) {
                $node['children'] = $children;
            }

            $tree[] = $node;
        }

        return $tree;
    }

    // -------------------------------------------------------------------------
    // Sitemap with refs
    //
    // Refs are temporary correlation handles added solely for the AI round-trip.
    // Format: "page:{slug}" for pages, "collection:{post_type}" for CPTs.
    // They let the plugin correlate each AI-identified content area back to the
    // specific WP structure it maps to (page ID, post type, taxonomy, etc.).
    // -------------------------------------------------------------------------

    /**
     * Build the full sitemap annotated with ephemeral ref IDs, and a parallel
     * refMap that carries the WP-specific routing info keyed by those refs.
     *
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>}
     */
    private function buildSitemapWithRefs(): array
    {
        $refMap = [];

        $pages       = $this->buildPageTreeWithRefs($refMap);
        $collections = $this->buildCollectionSitemapsWithRefs($refMap);

        return [
            ['pages' => $pages, 'collections' => $collections],
            $refMap,
        ];
    }

    /**
     * Build hierarchical page tree. Each node gets a ref and its WP routing
     * info is added to $refMap. Only top-level pages get refs — child pages
     * are under the same content area as their parent section.
     *
     * @param  array<string, array<string, mixed>> $refMap  Modified by reference
     * @return array<int, array<string, mixed>>
     */
    private function buildPageTreeWithRefs(array &$refMap): array
    {
        /** @var \WP_Post[] $allPages */
        $allPages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        if (empty($allPages)) {
            return [];
        }

        /** @var array<int, \WP_Post[]> $childrenByParent */
        $childrenByParent = [];
        foreach ($allPages as $page) {
            $childrenByParent[(int) $page->post_parent][] = $page;
        }

        return $this->buildPageNodesWithRefs($childrenByParent, 0, 3, true, $refMap);
    }

    /**
     * @param  array<int, \WP_Post[]>              $childrenByParent
     * @param  array<string, array<string, mixed>> $refMap  Modified by reference
     * @return array<int, array<string, mixed>>
     */
    private function buildPageNodesWithRefs(
        array $childrenByParent,
        int $parentId,
        int $maxDepth,
        bool $addRef,
        array &$refMap
    ): array {
        $nodes = [];

        foreach ($childrenByParent[$parentId] ?? [] as $page) {
            $node = [
                'slug'    => $page->post_name,
                'title'   => $page->post_title,
                'excerpt' => $this->extractTextExcerpt($page, 120),
            ];

            if ($addRef) {
                $ref          = 'page:' . $page->post_name;
                $node['ref']  = $ref;
                $refMap[$ref] = [
                    'type'      => 'section',
                    'post_type' => 'page',
                    'slug'      => $page->post_name,
                    'page_id'   => $page->ID,
                ];
            }

            if ($maxDepth > 1 && !empty($childrenByParent[$page->ID])) {
                // Children share the parent's content area — no separate refs
                $node['children'] = $this->buildPageNodesWithRefs(
                    $childrenByParent,
                    $page->ID,
                    $maxDepth - 1,
                    false,
                    $refMap
                );
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * Build collection entries. Each collection gets a ref and its WP routing
     * info (post type, primary taxonomy) is added to $refMap.
     *
     * @param  array<string, array<string, mixed>> $refMap  Modified by reference
     * @return array<int, array<string, mixed>>
     */
    private function buildCollectionSitemapsWithRefs(array &$refMap): array
    {
        $postTypes = get_post_types(['public' => true], 'objects');
        $skip      = ['page', 'attachment'];
        $result    = [];

        foreach ($postTypes as $postType) {
            if (in_array($postType->name, $skip, true)) {
                continue;
            }

            $count     = wp_count_posts($postType->name);
            $itemCount = (int) ($count->publish ?? 0);

            if ($itemCount === 0) {
                continue;
            }

            $primaryTaxonomy = $this->pickPrimaryTaxonomy($postType->name);

            $ref          = 'collection:' . $postType->name;
            $refMap[$ref] = [
                'type'             => 'collection',
                'post_type'        => $postType->name,
                'primary_taxonomy' => $primaryTaxonomy,
            ];

            $entry = [
                'ref'         => $ref,
                'key'         => $postType->name,   // present for helper only; stripped before submission
                'label'       => $postType->label,
                'archive_url' => $this->resolveArchiveUrl($postType),
                'item_count'  => $itemCount,
            ];

            if ($primaryTaxonomy !== null) {
                $entry['groups'] = $this->buildTaxonomyGroups($postType->name, $primaryTaxonomy);
            } else {
                $entry['items'] = $this->buildFlatItems($postType->name, 20);
            }

            $result[] = $entry;
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Strip WP routing data before report submission
    // -------------------------------------------------------------------------

    /**
     * Return a clean, agnostic copy of the sitemap:
     * - 'ref' removed from all page nodes (recursively) and collection entries
     * - 'key' (WP post type name) removed from collection entries
     *
     * @param  array<string, mixed> $sitemap
     * @return array<string, mixed>
     */
    private function stripRoutingData(array $sitemap): array
    {
        $pages = array_map(
            fn(array $page): array => $this->stripPageRef($page),
            $sitemap['pages'] ?? []
        );

        $collections = array_map(static function (array $collection): array {
            unset($collection['ref'], $collection['key']);
            return $collection;
        }, $sitemap['collections'] ?? []);

        return ['pages' => $pages, 'collections' => $collections];
    }

    /** @param array<string, mixed> $page */
    private function stripPageRef(array $page): array
    {
        unset($page['ref']);

        if (!empty($page['children'])) {
            $page['children'] = array_map(
                fn(array $child): array => $this->stripPageRef($child),
                $page['children']
            );
        }

        return $page;
    }

    // -------------------------------------------------------------------------
    // Local content area map
    //
    // Stored in dr_beacon_content_area_map WP option.
    // Keyed by normalised label so the plugin can look up WP routing when
    // Beacon later references a content area by label (e.g. "Services").
    // -------------------------------------------------------------------------

    /**
     * Correlate AI-identified content areas with WP routing info via refs,
     * and return the local map ready for storage.
     *
     * @param  array<int, mixed>                   $aiContentAreas
     * @param  array<string, array<string, mixed>> $refMap
     * @return array<string, array<string, mixed>>
     */
    private function buildLocalContentAreaMap(array $aiContentAreas, array $refMap): array
    {
        $map = [];

        foreach ($aiContentAreas as $area) {
            if (!is_array($area)) {
                continue;
            }

            $label = trim((string) ($area['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $key = $this->normalizeLabel($label);

            $entry = [
                'label'   => $label,
                'intent'  => (string) ($area['intent'] ?? ''),
                'topics'  => array_values(array_filter((array) ($area['topics'] ?? []), 'is_string')),
                'routing' => [],
            ];

            $ref = trim((string) ($area['ref'] ?? ''));
            if ($ref !== '' && isset($refMap[$ref])) {
                $entry['routing'] = $refMap[$ref];
            }

            $map[$key] = $entry;
        }

        return $map;
    }

    private function normalizeLabel(string $label): string
    {
        $lower = strtolower($label);
        return (string) preg_replace('/[^a-z0-9]+/', '_', $lower);
    }

    // -------------------------------------------------------------------------
    // Taxonomy grouping helpers
    // -------------------------------------------------------------------------

    private function pickPrimaryTaxonomy(string $postType): ?string
    {
        $taxNames = get_object_taxonomies($postType);

        foreach ($taxNames as $name) {
            $tax = get_taxonomy($name);
            if ($tax && $tax->public && $tax->hierarchical) {
                return $name;
            }
        }

        foreach ($taxNames as $name) {
            $tax = get_taxonomy($name);
            if ($tax && $tax->public) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTaxonomyGroups(string $postType, string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => 8,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $groups = [];
        foreach ($terms as $term) {
            /** @var \WP_Post[] $posts */
            $posts = get_posts([
                'post_type'     => $postType,
                'post_status'   => 'publish',
                'numberposts'   => 5,
                'orderby'       => 'date',
                'order'         => 'DESC',
                'no_found_rows' => true,
                'tax_query'     => [[
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ]],
            ]);

            if (empty($posts)) {
                continue;
            }

            $groups[] = [
                'term_slug'  => $term->slug,
                'term_label' => $term->name,
                'items'      => array_map(fn(\WP_Post $p): array => [
                    'slug'    => $p->post_name,
                    'title'   => $p->post_title,
                    'excerpt' => $this->extractTextExcerpt($p, 80),
                ], $posts),
            ];
        }

        return $groups;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFlatItems(string $postType, int $limit): array
    {
        /** @var \WP_Post[] $posts */
        $posts = get_posts([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        return array_map(fn(\WP_Post $p): array => [
            'slug'    => $p->post_name,
            'title'   => $p->post_title,
            'excerpt' => $this->extractTextExcerpt($p, 80),
        ], $posts);
    }

    private function resolveArchiveUrl(\WP_Post_Type $postType): ?string
    {
        if ($postType->name === 'post') {
            $feedPageId = (int) get_option('page_for_posts', 0);
            if ($feedPageId > 0) {
                return (string) get_permalink($feedPageId);
            }
            return (string) home_url('/');
        }

        if ($postType->has_archive) {
            $link = get_post_type_archive_link($postType->name);
            return $link !== false ? (string) $link : null;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function extractTextExcerpt(\WP_Post $post, int $maxChars = 120): string
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
