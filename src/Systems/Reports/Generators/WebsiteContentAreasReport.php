<?php

namespace DigitalRoyalty\Beacon\Systems\Reports\Generators;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;

/**
 * Generates the website_content_areas report.
 *
 * Content areas are identified locally from site structure:
 * - Every collection (CPT / posts) is a content area
 * - Parent pages with children are section hubs
 *
 * The report stores the structural overview (pages, collections, menus,
 * front page) plus the identified content_areas. An optional AI call
 * enriches areas with intent descriptions and refined topics.
 *
 * A local routing map (`dr_beacon_content_area_map` WP option) is
 * persisted so tools can place generated content into the right
 * WP structure (post type, taxonomy, page ID).
 */
final class WebsiteContentAreasReport implements ReportGeneratorInterface
{
    public function type(): string
    {
        return 'website_content_areas';
    }

    public function version(): int
    {
        return 3;
    }

    public function generate(): array
    {
        [$structure, $refMap] = $this->buildSiteStructure();

        // Identify content areas locally from the site's own structure.
        $contentAreas = $this->identifyContentAreas($structure, $refMap);

        // Persist the routing map so tools can place content into the right WP structure.
        $localMap = $this->buildLocalContentAreaMap($contentAreas, $refMap);
        update_option('dr_beacon_content_area_map', $localMap, false);

        // Optionally enrich with AI-generated intent and topics.
        $contentAreas = $this->enrichWithAi($contentAreas, $structure, $refMap);

        $data = [
            'content_areas' => array_map(static function (array $area): array {
                unset($area['ref']);
                return $area;
            }, $contentAreas),
        ];

        Services::logger()->info(LogScopeEnum::REPORTS, 'generator_content_areas_complete', 'Content areas report generated.', [
            'type'    => $this->type(),
            'version' => $this->version(),
            'count'   => count($data['content_areas']),
        ]);

        return $data;
    }

    // -------------------------------------------------------------------------
    // Local content area identification
    //
    // Content areas are identified from structure alone — no API call needed.
    // A content area is: a collection (CPT/posts) or a page section with children.
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>                   $structure
     * @param  array<string, array<string, mixed>>    $refMap
     * @return array<int, array<string, mixed>>
     */
    private function identifyContentAreas(array $structure, array $refMap): array
    {
        $areas = [];

        // Every collection is a content area — it exists as a registered content type.
        foreach ($structure['collections'] ?? [] as $collection) {
            $ref = $collection['ref'] ?? '';

            $area = [
                'ref'        => $ref,
                'label'      => $collection['label'] ?? '',
                'item_count' => $collection['item_count'] ?? 0,
                'topics'     => $collection['categories'] ?? [],
            ];

            if ($collection['has_archive'] ?? false) {
                $area['has_archive'] = true;
            }

            $areas[] = $area;
        }

        // Parent pages with children are section hubs (e.g. Services → sub-service pages).
        foreach ($structure['pages'] ?? [] as $page) {
            $childCount = $page['child_count'] ?? count($page['children'] ?? []);
            if ($childCount === 0) {
                continue;
            }

            $areas[] = [
                'ref'        => $page['ref'] ?? '',
                'label'      => $page['title'] ?? '',
                'item_count' => $childCount,
                'topics'     => array_map(
                    fn(array $c): string => $c['title'] ?? '',
                    $page['children'] ?? []
                ),
            ];
        }

        return $areas;
    }

    /**
     * Try to enrich content areas with AI-generated intent and topics.
     * If the API call fails, the areas are returned as-is — still useful.
     *
     * @param  array<int, array<string, mixed>>       $areas
     * @param  array<string, mixed>                   $structure
     * @param  array<string, array<string, mixed>>    $refMap
     * @return array<int, array<string, mixed>>
     */
    private function enrichWithAi(array $areas, array $structure, array $refMap): array
    {
        $resp = Services::apiClient()->analyseContentAreas($structure);

        if (!$resp->ok || !is_array($resp->data['content_areas'] ?? null)) {
            Services::logger()->warning(LogScopeEnum::REPORTS, 'generator_content_areas_ai_skipped', 'AI enrichment unavailable; using locally identified areas.', [
                'status_code' => $resp->code,
                'message'     => $resp->message,
            ]);
            return $areas;
        }

        $aiAreas = $resp->data['content_areas'];

        // Update the routing map with any AI-identified areas we missed locally.
        $localMap = $this->buildLocalContentAreaMap($aiAreas, $refMap);
        update_option('dr_beacon_content_area_map', $localMap, false);

        // Merge AI data: replace local areas with AI versions (richer intent/topics),
        // and add any AI-identified areas that weren't found locally.
        $merged   = [];
        $usedRefs = [];

        foreach ($aiAreas as $aiArea) {
            if (!is_array($aiArea) || empty($aiArea['label'])) {
                continue;
            }
            $merged[]   = $aiArea;
            $usedRefs[] = $aiArea['ref'] ?? '';
        }

        // Keep any locally identified areas the AI didn't return.
        foreach ($areas as $local) {
            $ref = $local['ref'] ?? '';
            if ($ref !== '' && in_array($ref, $usedRefs, true)) {
                continue;
            }
            $merged[] = $local;
        }

        Services::logger()->info(LogScopeEnum::REPORTS, 'generator_content_areas_ai_enriched', 'AI enrichment applied to content areas.', [
            'ai_count'    => count($aiAreas),
            'local_count' => count($areas),
            'merged_count' => count($merged),
        ]);

        return $merged;
    }


    // -------------------------------------------------------------------------
    // Site structure snapshot
    // -------------------------------------------------------------------------

    /**
     * Build a lightweight structural snapshot of the site for the AI analyser.
     *
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>}
     */
    private function buildSiteStructure(): array
    {
        $refMap = [];

        return [
            [
                'pages'       => $this->buildPageSkeleton($refMap),
                'collections' => $this->buildCollectionSkeleton($refMap),
                'menus'       => $this->buildMenuSkeleton(),
                'front_page'  => $this->buildFrontPageInfo(),
            ],
            $refMap,
        ];
    }

    // -------------------------------------------------------------------------
    // Pages — just the skeleton: slug, title, child count
    // -------------------------------------------------------------------------

    /**
     * @param array<string, array<string, mixed>> $refMap Modified by reference
     * @return array<int, array<string, mixed>>
     */
    private function buildPageSkeleton(array &$refMap): array
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

        return $this->buildPageNodes($childrenByParent, 0, $refMap);
    }

    /**
     * @param array<int, \WP_Post[]> $childrenByParent
     * @param array<string, array<string, mixed>> $refMap Modified by reference
     * @return array<int, array<string, mixed>>
     */
    private function buildPageNodes(array $childrenByParent, int $parentId, array &$refMap): array
    {
        $nodes = [];

        foreach ($childrenByParent[$parentId] ?? [] as $page) {
            $ref          = 'page:' . $page->post_name;
            $refMap[$ref] = [
                'type'      => 'section',
                'post_type' => 'page',
                'slug'      => $page->post_name,
                'page_id'   => $page->ID,
            ];

            $children    = $this->buildPageNodes($childrenByParent, $page->ID, $refMap);
            $childCount  = count($childrenByParent[$page->ID] ?? []);

            $node = [
                'ref'   => $ref,
                'slug'  => $page->post_name,
                'title' => $page->post_title,
            ];

            if ($childCount > 0) {
                $node['child_count'] = $childCount;
                $node['children']    = array_map(fn(array $c): array => [
                    'slug'  => $c['slug'],
                    'title' => $c['title'],
                ], $children);
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    // -------------------------------------------------------------------------
    // Collections — label, archive flag, item count, taxonomy term names
    // -------------------------------------------------------------------------

    /**
     * @param array<string, array<string, mixed>> $refMap Modified by reference
     * @return array<int, array<string, mixed>>
     */
    private function buildCollectionSkeleton(array &$refMap): array
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

            $primaryTaxonomy = $this->pickPrimaryTaxonomy($postType->name);

            $ref          = 'collection:' . $postType->name;
            $refMap[$ref] = [
                'type'             => 'collection',
                'post_type'        => $postType->name,
                'primary_taxonomy' => $primaryTaxonomy,
            ];

            $entry = [
                'ref'         => $ref,
                'label'       => $postType->label,
                'has_archive' => (bool) $postType->has_archive,
                'item_count'  => $itemCount,
            ];

            // Just the term names — enough for the AI to understand topics
            if ($primaryTaxonomy !== null) {
                $entry['categories'] = $this->getTermNames($primaryTaxonomy);
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function getTermNames(string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => 10,
            'fields'     => 'names',
        ]);

        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        return array_values($terms);
    }

    // -------------------------------------------------------------------------
    // Menus — lightweight list of titles (shows what the site considers important)
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMenuSkeleton(): array
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
                'items'    => array_map(fn($item): string => $item->title, $items),
            ];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Front page info
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function buildFrontPageInfo(): array
    {
        $showOnFront = (string) get_option('show_on_front', 'posts');
        $frontPageId = (int) get_option('page_on_front', 0);
        $feedPageId  = (int) get_option('page_for_posts', 0);

        return [
            'type'      => $showOnFront === 'page' ? 'static_page' : 'feed',
            'page_slug' => $frontPageId > 0 ? (string) get_post_field('post_name', $frontPageId) : null,
            'feed_slug' => $feedPageId > 0 ? (string) get_post_field('post_name', $feedPageId) : null,
        ];
    }

    // -------------------------------------------------------------------------
    // Local content area map
    // -------------------------------------------------------------------------

    /**
     * Correlate AI-identified content areas with WP routing info via refs.
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
    // Helpers
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
}
