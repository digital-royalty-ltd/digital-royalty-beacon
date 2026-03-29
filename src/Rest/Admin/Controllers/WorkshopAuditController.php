<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Repositories\RedirectsRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Audit scan endpoints for Workshop tools.
 * All endpoints are read-only scans — no mutations.
 */
final class WorkshopAuditController
{
    public function __construct(
        private readonly RedirectsRepository $redirectsRepo
    ) {}

    public function registerRoutes(): void
    {
        $perm  = fn () => current_user_can('manage_options');
        $scans = [
            'meta'             => 'scanMeta',
            'headings'         => 'scanHeadings',
            'orphans'          => 'scanOrphans',
            'duplicates'       => 'scanDuplicates',
            'image-alt'        => 'scanImageAlt',
            'unused-media'     => 'scanUnusedMedia',
            'noindex'          => 'scanNoindex',
            'redirect-chains'  => 'scanRedirectChains',
            'broken-links'     => 'scanBrokenLinks',
        ];

        foreach ($scans as $slug => $method) {
            register_rest_route('beacon/v1', "/admin/workshop/audit/{$slug}", [
                'methods'             => 'GET',
                'callback'            => [$this, $method],
                'permission_callback' => $perm,
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // Meta Auditor
    // -----------------------------------------------------------------------

    public function scanMeta(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
             LIMIT 500",
            ARRAY_A
        );

        $seoDescKeys = ['_yoast_wpseo_metadesc', '_aioseo_description', 'rank_math_description'];
        $items       = [];

        foreach ((array) $posts as $post) {
            $id     = (int) $post['ID'];
            $title  = (string) $post['post_title'];
            $issues = [];
            $len    = mb_strlen($title);

            if ($len < 30) {
                $issues[] = 'title_too_short';
            } elseif ($len > 60) {
                $issues[] = 'title_too_long';
            }

            $hasMeta = false;
            foreach ($seoDescKeys as $key) {
                $val = get_post_meta($id, $key, true);
                if (!empty($val)) {
                    $hasMeta = true;
                    break;
                }
            }

            if (!$hasMeta) {
                $issues[] = 'no_meta_description';
            }

            if (!empty($issues)) {
                $items[] = [
                    'id'        => $id,
                    'title'     => $title,
                    'post_type' => $post['post_type'],
                    'url'       => get_permalink($id) ?: '',
                    'issues'    => $issues,
                ];
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    // -----------------------------------------------------------------------
    // Heading Structure
    // -----------------------------------------------------------------------

    public function scanHeadings(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_content
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_content != ''
             LIMIT 200",
            ARRAY_A
        );

        $items = [];

        foreach ((array) $posts as $post) {
            $content = (string) $post['post_content'];
            $issues  = $this->analyseHeadings($content);

            if (!empty($issues)) {
                $items[] = [
                    'id'        => (int) $post['ID'],
                    'title'     => (string) $post['post_title'],
                    'post_type' => (string) $post['post_type'],
                    'url'       => get_permalink((int) $post['ID']) ?: '',
                    'issues'    => $issues,
                ];
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    /** @return string[] */
    private function analyseHeadings(string $content): array
    {
        preg_match_all('/<h([1-6])[^>]*>/i', $content, $matches);
        $levels  = array_map('intval', $matches[1] ?? []);
        $issues  = [];
        $h1Count = count(array_filter($levels, fn ($l) => $l === 1));

        if ($h1Count === 0) {
            $issues[] = 'missing_h1';
        } elseif ($h1Count > 1) {
            $issues[] = 'multiple_h1';
        }

        // Check for skipped levels (e.g. H1 then H3 without H2)
        $prev = 0;
        foreach ($levels as $level) {
            if ($prev > 0 && $level > $prev + 1) {
                $issues[] = 'skipped_heading_level';
                break;
            }
            $prev = $level;
        }

        return $issues;
    }

    // -----------------------------------------------------------------------
    // Orphaned Content
    // -----------------------------------------------------------------------

    public function scanOrphans(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_name
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
             LIMIT 100",
            ARRAY_A
        );

        // Get all post_content at once for link scanning
        $allContent = (string) $wpdb->get_var(
            "SELECT GROUP_CONCAT(post_content SEPARATOR ' ')
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
             LIMIT 500"
        );

        $items = [];

        foreach ((array) $posts as $post) {
            $id   = (int)    $post['ID'];
            $slug = (string) $post['post_name'];

            // Check if this post's slug appears in any content (as a URL segment)
            if ($slug === '' || stripos($allContent, "/{$slug}") === false) {
                $items[] = [
                    'id'        => $id,
                    'title'     => (string) $post['post_title'],
                    'post_type' => (string) $post['post_type'],
                    'url'       => get_permalink($id) ?: '',
                ];
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    // -----------------------------------------------------------------------
    // Duplicate Titles
    // -----------------------------------------------------------------------

    public function scanDuplicates(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT post_title, GROUP_CONCAT(ID ORDER BY ID) AS ids, COUNT(*) AS total
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_title != ''
             GROUP BY post_title
             HAVING COUNT(*) > 1
             ORDER BY total DESC
             LIMIT 200",
            ARRAY_A
        );

        $items = [];
        foreach ((array) $rows as $row) {
            $ids    = array_map('intval', explode(',', (string) $row['ids']));
            $items[] = [
                'title' => (string) $row['post_title'],
                'count' => (int)    $row['total'],
                'ids'   => $ids,
                'urls'  => array_map(fn ($id) => get_permalink($id) ?: '', $ids),
            ];
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    // -----------------------------------------------------------------------
    // Image Alt Auditor
    // -----------------------------------------------------------------------

    public function scanImageAlt(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_content
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_content LIKE '%<img%'
             LIMIT 300",
            ARRAY_A
        );

        $items = [];

        foreach ((array) $posts as $post) {
            $content = (string) $post['post_content'];
            preg_match_all('/<img[^>]+>/i', $content, $imgMatches);

            foreach ($imgMatches[0] as $imgTag) {
                // Missing alt or empty alt
                if (!preg_match('/\balt\s*=/i', $imgTag) || preg_match('/\balt\s*=\s*["\']["\']/', $imgTag)) {
                    $src = '';
                    if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)/i', $imgTag, $srcM)) {
                        $src = $srcM[1];
                    }
                    $items[] = [
                        'id'        => (int)    $post['ID'],
                        'title'     => (string) $post['post_title'],
                        'post_type' => (string) $post['post_type'],
                        'src'       => basename($src),
                        'url'       => get_permalink((int) $post['ID']) ?: '',
                    ];
                }
            }

            if (count($items) >= 200) {
                break;
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    // -----------------------------------------------------------------------
    // Unused Media
    // -----------------------------------------------------------------------

    public function scanUnusedMedia(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $attachments = $wpdb->get_results(
            "SELECT ID, post_title, guid
             FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
               AND post_status = 'inherit'
             ORDER BY ID DESC
             LIMIT 300",
            ARRAY_A
        );

        // Get all post_content to scan for references
        $allContent = (string) $wpdb->get_var(
            "SELECT GROUP_CONCAT(post_content SEPARATOR ' ')
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
             LIMIT 500"
        );

        // Also check post thumbnail IDs
        $thumbnailIds = array_map('intval', (array) $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'"
        ));

        $items = [];

        foreach ((array) $attachments as $att) {
            $id  = (int)    $att['ID'];
            $url = (string) $att['guid'];

            if (in_array($id, $thumbnailIds, true)) {
                continue;
            }

            $filename = basename(parse_url($url, PHP_URL_PATH) ?: $url);

            if ($filename !== '' && stripos($allContent, $filename) !== false) {
                continue;
            }

            $items[] = [
                'id'    => $id,
                'title' => (string) ($att['post_title'] ?: $filename),
                'url'   => $url,
            ];

            if (count($items) >= 200) {
                break;
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    // -----------------------------------------------------------------------
    // Noindex Checker
    // -----------------------------------------------------------------------

    public function scanNoindex(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        // Check Yoast SEO
        $yoastRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, p.post_type
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_status = 'publish'
                   AND p.post_type IN ('post','page')
                   AND pm.meta_key = %s
                   AND pm.meta_value = '1'
                 LIMIT 200",
                '_yoast_wpseo_meta-robots-noindex'
            ),
            ARRAY_A
        );

        // Check AIOSEO
        $aioseoRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, p.post_type
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_status = 'publish'
                   AND p.post_type IN ('post','page')
                   AND pm.meta_key = %s
                   AND pm.meta_value = '1'
                 LIMIT 200",
                '_aioseo_robots_noindex'
            ),
            ARRAY_A
        );

        $seen  = [];
        $items = [];

        foreach (array_merge((array) $yoastRows, (array) $aioseoRows) as $row) {
            $id = (int) $row['ID'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $items[]   = [
                'id'        => $id,
                'title'     => (string) $row['post_title'],
                'post_type' => (string) $row['post_type'],
                'url'       => get_permalink($id) ?: '',
            ];
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    // -----------------------------------------------------------------------
    // Redirect Chains
    // -----------------------------------------------------------------------

    public function scanRedirectChains(WP_REST_Request $request): WP_REST_Response
    {
        $redirects = $this->redirectsRepo->all();

        // Build source_path → target_url map
        $map = [];
        foreach ($redirects as $r) {
            $map[(string) $r['source_path']] = (string) $r['target_url'];
        }

        $items = [];

        foreach ($map as $source => $target) {
            // Check if target is another redirect's source (chain)
            $targetPath = parse_url($target, PHP_URL_PATH) ?: $target;

            if (!isset($map[$targetPath])) {
                continue;
            }

            // Walk the chain
            $chain   = [$source, $target];
            $visited = [$source => true, $targetPath => true];
            $current = $targetPath;
            $type    = 'chain';

            while (isset($map[$current])) {
                $next     = parse_url($map[$current], PHP_URL_PATH) ?: $map[$current];
                $chain[]  = $map[$current];

                if (isset($visited[$next])) {
                    $type = 'loop';
                    break;
                }

                $visited[$next] = true;
                $current        = $next;

                if (count($chain) > 10) {
                    break;
                }
            }

            if (count($chain) > 2) {
                $items[] = [
                    'chain'  => $chain,
                    'type'   => $type,
                    'length' => count($chain),
                ];
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    // -----------------------------------------------------------------------
    // Broken Internal Links
    // -----------------------------------------------------------------------

    public function scanBrokenLinks(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $siteUrl  = rtrim((string) get_site_url(), '/');
        $sitePath = parse_url($siteUrl, PHP_URL_PATH) ?: '';

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_content
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_content LIKE '%href=%'
             LIMIT 100",
            ARRAY_A
        );

        // Get all published post slugs for fast lookup
        $slugs = array_flip(
            (array) $wpdb->get_col(
                "SELECT post_name FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_type IN ('post','page')
                   AND post_name != ''"
            )
        );

        $items = [];

        foreach ((array) $posts as $post) {
            $content = (string) $post['post_content'];
            preg_match_all('/href=["\']([^"\']+)["\']/', $content, $hrefMatches);

            foreach ($hrefMatches[1] as $href) {
                $isInternal = str_starts_with($href, $siteUrl)
                    || (str_starts_with($href, '/') && !str_starts_with($href, '//'));

                if (!$isInternal) {
                    continue;
                }

                // Extract the path
                $path     = parse_url($href, PHP_URL_PATH) ?: $href;
                $path     = ltrim(str_replace($sitePath, '', $path), '/');
                $segments = array_filter(explode('/', $path));
                $slug     = end($segments) ?: '';

                if ($slug !== '' && !isset($slugs[$slug])) {
                    $items[] = [
                        'id'         => (int)    $post['ID'],
                        'title'      => (string) $post['post_title'],
                        'post_type'  => (string) $post['post_type'],
                        'broken_url' => $href,
                        'url'        => get_permalink((int) $post['ID']) ?: '',
                    ];
                }
            }

            if (count($items) >= 200) {
                break;
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }
}
