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
    private const OPTION_ORPHAN_EXCEPTIONS       = 'dr_beacon_workshop_orphan_exceptions';
    private const OPTION_UNUSED_MEDIA_EXCLUSIONS = 'dr_beacon_workshop_unused_media_exclusions';
    private const OPTION_BROKEN_LINK_DISMISSALS  = 'dr_beacon_workshop_broken_link_dismissals';
    private const META_BEACON_DESCRIPTION        = '_dr_beacon_meta_description';
    private const META_BEACON_NOINDEX            = '_dr_beacon_noindex';

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

        register_rest_route('beacon/v1', '/admin/workshop/audit/meta/update', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateMetaItem'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/audit/orphans/exceptions', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateOrphanException'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/audit/image-alt/update', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateImageAlt'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/audit/unused-media/exclusions', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getUnusedMediaExclusions'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'saveUnusedMediaExclusions'],
                'permission_callback' => $perm,
            ],
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/audit/unused-media/delete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'deleteUnusedMediaItem'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/audit/broken-links/dismiss', [
            'methods'             => 'POST',
            'callback'            => [$this, 'dismissBrokenLink'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/audit/broken-links/update', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateBrokenLinkUrl'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/audit/broken-links/unlink', [
            'methods'             => 'POST',
            'callback'            => [$this, 'unlinkBrokenLink'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/audit/noindex/update', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateNoindex'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/audit/redirect-chains/collapse', [
            'methods'             => 'POST',
            'callback'            => [$this, 'collapseRedirectChain'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/workshop/audit/meta/bulk-update', [
            'methods'             => 'POST',
            'callback'            => [$this, 'bulkUpdateMeta'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/workshop/audit/duplicates/rename', [
            'methods'             => 'POST',
            'callback'            => [$this, 'renameDuplicate'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/workshop/audit/duplicates/bulk-rename', [
            'methods'             => 'POST',
            'callback'            => [$this, 'bulkRenameDuplicates'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/workshop/audit/broken-links/schedule', [
            'methods'             => 'POST',
            'callback'            => [$this, 'saveBrokenLinksSchedule'],
            'permission_callback' => $perm,
        ]);
    }

    // -----------------------------------------------------------------------
    // Meta Auditor
    // -----------------------------------------------------------------------

    public function scanMeta(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_status
             FROM {$wpdb->posts}
             WHERE post_status IN ('publish','draft')
               AND post_type IN ('post','page')
             ORDER BY post_modified DESC
             LIMIT 500",
            ARRAY_A
        );

        $titleCounts = [];
        $metaCounts  = [];
        $prepared    = [];

        foreach ((array) $posts as $post) {
            $id = (int) $post['ID'];
            $title = trim((string) $post['post_title']);
            $meta = $this->getMetaDescriptionForPost($id);
            $prepared[] = [
                'id'          => $id,
                'title'       => $title,
                'post_type'   => (string) $post['post_type'],
                'post_status' => (string) $post['post_status'],
                'meta'        => $meta,
            ];

            if ($title !== '') {
                $titleCounts[mb_strtolower($title)] = ($titleCounts[mb_strtolower($title)] ?? 0) + 1;
            }

            $metaText = trim((string) ($meta['value'] ?? ''));
            if ($metaText !== '') {
                $metaCounts[mb_strtolower($metaText)] = ($metaCounts[mb_strtolower($metaText)] ?? 0) + 1;
            }
        }

        $items = [];

        foreach ($prepared as $item) {
            $issues      = [];
            $titleLength = mb_strlen($item['title']);
            $metaValue   = trim((string) ($item['meta']['value'] ?? ''));
            $metaLength  = mb_strlen($metaValue);

            if ($titleLength > 0 && $titleLength < 30) {
                $issues[] = 'title_too_short';
            } elseif ($titleLength > 60) {
                $issues[] = 'title_too_long';
            }

            if ($metaValue === '') {
                $issues[] = 'no_meta_description';
            } elseif ($metaLength < 70) {
                $issues[] = 'meta_too_short';
            } elseif ($metaLength > 160) {
                $issues[] = 'meta_too_long';
            }

            if ($item['title'] !== '' && ($titleCounts[mb_strtolower($item['title'])] ?? 0) > 1) {
                $issues[] = 'duplicate_title';
            }

            if ($metaValue !== '' && ($metaCounts[mb_strtolower($metaValue)] ?? 0) > 1) {
                $issues[] = 'duplicate_meta_description';
            }

            if ($issues !== []) {
                $items[] = [
                    'id'               => $item['id'],
                    'title'            => $item['title'],
                    'post_type'        => $item['post_type'],
                    'post_status'      => $item['post_status'],
                    'url'              => get_permalink($item['id']) ?: '',
                    'edit_url'         => get_edit_post_link($item['id'], 'raw') ?: '',
                    'issues'           => $issues,
                    'title_length'     => $titleLength,
                    'meta_description' => $metaValue,
                    'meta_length'      => $metaLength,
                    'meta_source'      => (string) ($item['meta']['source'] ?? 'none'),
                ];
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    public function updateMetaItem(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) ($request->get_param('post_id') ?? 0);
        $title  = sanitize_text_field((string) ($request->get_param('post_title') ?? ''));
        $meta   = sanitize_textarea_field((string) ($request->get_param('meta_description') ?? ''));

        if ($postId === 0 || !get_post($postId)) {
            return new WP_REST_Response(['error' => 'Post not found'], 404);
        }

        if ($title !== '') {
            wp_update_post([
                'ID'         => $postId,
                'post_title' => $title,
            ]);
        }

        $metaConfig = $this->getMetaDescriptionForPost($postId);
        update_post_meta($postId, (string) ($metaConfig['key'] ?? self::META_BEACON_DESCRIPTION), $meta);

        return new WP_REST_Response([
            'ok'               => true,
            'post_id'          => $postId,
            'post_title'       => get_the_title($postId),
            'meta_description' => $meta,
        ], 200);
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
             ORDER BY post_modified DESC
             LIMIT 200",
            ARRAY_A
        );

        $items = [];

        foreach ((array) $posts as $post) {
            $analysis = $this->analyseHeadings((string) $post['post_content']);

            if ($analysis['issues'] !== []) {
                $items[] = [
                    'id'        => (int) $post['ID'],
                    'title'     => (string) $post['post_title'],
                    'post_type' => (string) $post['post_type'],
                    'url'       => get_permalink((int) $post['ID']) ?: '',
                    'edit_url'  => get_edit_post_link((int) $post['ID'], 'raw') ?: '',
                    'issues'    => $analysis['issues'],
                    'outline'   => $analysis['outline'],
                ];
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    private function analyseHeadings(string $content): array
    {
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER);
        $levels  = [];
        $issues  = [];
        $outline = [];

        foreach ($matches as $match) {
            $level = (int) ($match[1] ?? 0);
            $text  = trim(wp_strip_all_tags(html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES)));
            $levels[] = $level;
            $outline[] = [
                'level' => $level,
                'text'  => $text,
                'empty' => $text === '',
            ];

            if ($text === '') {
                $issues[] = 'empty_heading';
            }
        }

        $h1Count = count(array_filter($levels, static fn ($level) => $level === 1));

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

        return [
            'issues'  => array_values(array_unique($issues)),
            'outline' => $outline,
        ];
    }

    // -----------------------------------------------------------------------
    // Orphaned Content
    // -----------------------------------------------------------------------

    public function scanOrphans(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_content, post_parent, post_modified
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
             ORDER BY post_modified DESC
             LIMIT 300",
            ARRAY_A
        );

        $ignoredIds = $this->getIgnoredOrphanIds();
        $frontPage  = (int) get_option('page_on_front');
        $postsPage  = (int) get_option('page_for_posts');
        $menuLinked = $this->getMenuLinkedObjectIds();
        $inboundMap = $this->buildInboundLinkMap($posts);
        $items = [];

        foreach ((array) $posts as $post) {
            $id = (int) $post['ID'];
            if ($id === $frontPage || $id === $postsPage || isset($menuLinked[$id])) {
                continue;
            }

            if (($inboundMap[$id] ?? 0) === 0) {
                $actions = ['Add an internal link from a relevant hub or category page.'];
                if ((string) $post['post_type'] === 'page' && (int) $post['post_parent'] === 0) {
                    $actions[] = 'Assign a parent page if this belongs in a page hierarchy.';
                }
                $actions[] = 'Review whether this URL should be linked from navigation or kept intentionally isolated.';

                $items[] = [
                    'id'                => $id,
                    'title'             => (string) $post['post_title'],
                    'post_type'         => (string) $post['post_type'],
                    'url'               => get_permalink($id) ?: '',
                    'edit_url'          => get_edit_post_link($id, 'raw') ?: '',
                    'modified_at'       => mysql2date('Y-m-d H:i', (string) $post['post_modified']),
                    'word_count'        => str_word_count(wp_strip_all_tags((string) $post['post_content'])),
                    'ignored'           => isset($ignoredIds[$id]),
                    'suggested_actions' => $actions,
                ];
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    public function updateOrphanException(WP_REST_Request $request): WP_REST_Response
    {
        $postId  = (int) ($request->get_param('post_id') ?? 0);
        $ignored = !empty($request->get_param('ignored'));

        if ($postId === 0 || !get_post($postId)) {
            return new WP_REST_Response(['error' => 'Post not found'], 404);
        }

        $ids = array_keys($this->getIgnoredOrphanIds());
        if ($ignored && !in_array($postId, $ids, true)) {
            $ids[] = $postId;
        }
        if (!$ignored) {
            $ids = array_values(array_filter($ids, static fn ($id) => (int) $id !== $postId));
        }

        update_option(self::OPTION_ORPHAN_EXCEPTIONS, array_values(array_unique(array_map('intval', $ids))), false);

        return new WP_REST_Response(['ok' => true, 'post_id' => $postId, 'ignored' => $ignored], 200);
    }

    // -----------------------------------------------------------------------
    // Duplicate Titles
    // -----------------------------------------------------------------------

    public function scanDuplicates(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT post_title
             FROM {$wpdb->posts}
             WHERE post_status IN ('publish','draft','pending','private')
               AND post_type IN ('post','page')
               AND post_title != ''
             GROUP BY post_title
             HAVING COUNT(*) > 1
             ORDER BY COUNT(*) DESC
             LIMIT 200",
            ARRAY_A
        );

        $items = [];
        foreach ((array) $rows as $row) {
            $title = (string) $row['post_title'];
            $conflictRows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, post_type, post_status
                     FROM {$wpdb->posts}
                     WHERE post_title = %s
                       AND post_status IN ('publish','draft','pending','private')
                       AND post_type IN ('post','page')
                     ORDER BY post_modified DESC
                     LIMIT 25",
                    $title
                ),
                ARRAY_A
            );
            $conflicts = [];
            foreach ((array) $conflictRows as $conflict) {
                $id = (int) $conflict['ID'];
                $conflicts[] = [
                    'id'          => $id,
                    'post_type'   => (string) $conflict['post_type'],
                    'post_status' => (string) $conflict['post_status'],
                    'url'         => get_permalink($id) ?: '',
                    'edit_url'    => get_edit_post_link($id, 'raw') ?: '',
                ];
            }
            $items[] = [
                'title'     => $title,
                'count'     => count($conflicts),
                'conflicts' => $conflicts,
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

        $items = [];
        $attachments = $wpdb->get_results(
            "SELECT ID, post_title, guid
             FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
               AND post_mime_type LIKE 'image/%'
             ORDER BY post_modified DESC
             LIMIT 250",
            ARRAY_A
        );

        foreach ((array) $attachments as $attachment) {
            $id   = (int) $attachment['ID'];
            $alt  = trim((string) get_post_meta($id, '_wp_attachment_image_alt', true));
            if ($alt !== '') {
                continue;
            }

            $url  = wp_get_attachment_url($id) ?: (string) $attachment['guid'];
            $file = get_attached_file($id);
            $items[] = [
                'id'            => $id,
                'attachment_id' => $id,
                'context'       => 'library',
                'title'         => (string) $attachment['post_title'],
                'filename'      => $file ? basename($file) : basename(parse_url($url, PHP_URL_PATH) ?: $url),
                'thumbnail_url' => wp_get_attachment_image_url($id, 'thumbnail') ?: $url,
                'current_alt'   => '',
                'edit_url'      => get_edit_post_link($id, 'raw') ?: '',
                'url'           => $url,
            ];
        }

        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_content
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_content LIKE '%<img%'
             ORDER BY post_modified DESC
             LIMIT 250",
            ARRAY_A
        );

        foreach ((array) $posts as $post) {
            preg_match_all('/<img\b[^>]*>/i', (string) $post['post_content'], $imgMatches);

            foreach ((array) ($imgMatches[0] ?? []) as $imgTag) {
                $currentAlt = $this->extractAttribute($imgTag, 'alt');
                if ($currentAlt !== null && trim($currentAlt) !== '') {
                    continue;
                }

                $src = (string) ($this->extractAttribute($imgTag, 'src') ?? '');
                if ($src === '') {
                    continue;
                }

                $attachmentId = function_exists('attachment_url_to_postid') ? (int) attachment_url_to_postid($src) : 0;
                $items[] = [
                    'id'            => (int) $post['ID'],
                    'post_id'       => (int) $post['ID'],
                    'attachment_id' => $attachmentId > 0 ? $attachmentId : null,
                    'context'       => 'content',
                    'title'         => (string) $post['post_title'],
                    'post_type'     => (string) $post['post_type'],
                    'filename'      => basename(parse_url($src, PHP_URL_PATH) ?: $src),
                    'thumbnail_url' => $attachmentId > 0 ? (wp_get_attachment_image_url($attachmentId, 'thumbnail') ?: $src) : $src,
                    'current_alt'   => '',
                    'image_src'     => $src,
                    'edit_url'      => get_edit_post_link((int) $post['ID'], 'raw') ?: '',
                    'url'           => get_permalink((int) $post['ID']) ?: '',
                ];
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => array_slice($items, 0, 300)], 200);
    }

    public function updateImageAlt(WP_REST_Request $request): WP_REST_Response
    {
        $context = sanitize_key((string) ($request->get_param('context') ?? ''));
        $altText = sanitize_text_field((string) ($request->get_param('alt_text') ?? ''));

        if ($context === 'library') {
            $attachmentId = (int) ($request->get_param('attachment_id') ?? 0);
            if ($attachmentId === 0 || get_post_type($attachmentId) !== 'attachment') {
                return new WP_REST_Response(['error' => 'Attachment not found'], 404);
            }

            update_post_meta($attachmentId, '_wp_attachment_image_alt', $altText);
            return new WP_REST_Response(['ok' => true, 'attachment_id' => $attachmentId, 'alt_text' => $altText], 200);
        }

        $postId = (int) ($request->get_param('post_id') ?? 0);
        $src    = (string) ($request->get_param('image_src') ?? '');
        if ($context !== 'content' || $postId === 0 || !get_post($postId) || $src === '') {
            return new WP_REST_Response(['error' => 'Valid content image data is required'], 422);
        }

        $content = (string) (get_post($postId)?->post_content ?? '');
        $updated = preg_replace_callback(
            '/<img\b[^>]*>/i',
            function (array $match) use ($src, $altText): string {
                $imgTag = (string) ($match[0] ?? '');
                $tagSrc = (string) ($this->extractAttribute($imgTag, 'src') ?? '');
                if ($tagSrc !== $src) {
                    return $imgTag;
                }

                if (preg_match('/\balt\s*=\s*("|\').*?\1/i', $imgTag) === 1) {
                    return (string) preg_replace('/\balt\s*=\s*("|\').*?\1/i', 'alt="' . esc_attr($altText) . '"', $imgTag, 1);
                }

                return str_replace('<img', '<img alt="' . esc_attr($altText) . '"', $imgTag);
            },
            $content,
            1,
            $count
        );

        if (!is_string($updated) || $count < 1) {
            return new WP_REST_Response(['error' => 'Matching image tag not found'], 404);
        }

        wp_update_post(['ID' => $postId, 'post_content' => $updated]);

        $attachmentId = (int) ($request->get_param('attachment_id') ?? 0);
        if ($attachmentId > 0 && get_post_type($attachmentId) === 'attachment') {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $altText);
        }

        return new WP_REST_Response(['ok' => true, 'post_id' => $postId, 'alt_text' => $altText], 200);
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
             ORDER BY post_modified DESC
             LIMIT 300",
            ARRAY_A
        );

        // Get all post_content to scan for references
        $allContent = (string) $wpdb->get_var(
            "SELECT GROUP_CONCAT(post_content SEPARATOR ' ')
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
            "
        );

        // Also check post thumbnail IDs
        $thumbnailIds = array_map('intval', (array) $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'"
        ));

        $exclusions = $this->getUnusedMediaExclusionPatterns();
        $items = [];
        $totalBytes = 0;

        foreach ((array) $attachments as $att) {
            $id       = (int)    $att['ID'];
            $url      = (string) $att['guid'];
            $filePath = get_attached_file($id);

            if (in_array($id, $thumbnailIds, true)) {
                continue;
            }

            $filename = $filePath ? basename($filePath) : basename(parse_url($url, PHP_URL_PATH) ?: $url);
            if ($this->matchesExcludedPattern([$filename, $url, (string) $att['post_title']], $exclusions)) {
                continue;
            }

            if (($filename !== '' && stripos($allContent, $filename) !== false) || ($url !== '' && stripos($allContent, $url) !== false)) {
                continue;
            }

            if ($this->attachmentReferencedInMeta($id, $url, $filename)) {
                continue;
            }

            $bytes = ($filePath && file_exists($filePath)) ? (int) filesize($filePath) : 0;
            $totalBytes += $bytes;
            $items[] = [
                'id'            => $id,
                'title'         => (string) ($att['post_title'] ?: $filename),
                'filename'      => $filename,
                'url'           => $url,
                'thumbnail_url' => wp_get_attachment_image_url($id, 'thumbnail') ?: $url,
                'bytes'         => $bytes,
                'size'          => size_format($bytes),
                'edit_url'      => get_edit_post_link($id, 'raw') ?: '',
            ];
        }

        return new WP_REST_Response([
            'total'                   => count($items),
            'items'                   => $items,
            'reclaimable_bytes_total' => $totalBytes,
            'reclaimable_size_total'  => size_format($totalBytes),
            'exclusions'              => $exclusions,
        ], 200);
    }

    public function getUnusedMediaExclusions(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(['patterns' => $this->getUnusedMediaExclusionPatterns()], 200);
    }

    public function saveUnusedMediaExclusions(WP_REST_Request $request): WP_REST_Response
    {
        $patterns = array_values(array_filter(array_map(
            static fn ($pattern) => sanitize_text_field((string) $pattern),
            (array) ($request->get_param('patterns') ?? [])
        )));
        update_option(self::OPTION_UNUSED_MEDIA_EXCLUSIONS, $patterns, false);
        return new WP_REST_Response(['ok' => true, 'patterns' => $patterns], 200);
    }

    public function deleteUnusedMediaItem(WP_REST_Request $request): WP_REST_Response
    {
        $attachmentId = (int) ($request->get_param('attachment_id') ?? 0);
        $confirmed    = !empty($request->get_param('confirm'));
        if ($attachmentId === 0 || get_post_type($attachmentId) !== 'attachment') {
            return new WP_REST_Response(['error' => 'Attachment not found'], 404);
        }
        if (!$confirmed) {
            return new WP_REST_Response(['error' => 'Deletion requires explicit confirmation'], 422);
        }

        $deleted = wp_delete_attachment($attachmentId, true);
        return new WP_REST_Response(['ok' => $deleted !== false, 'attachment_id' => $attachmentId], $deleted !== false ? 200 : 500);
    }

    // -----------------------------------------------------------------------
    // Noindex Checker
    // -----------------------------------------------------------------------

    public function scanNoindex(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $sitewideNoindex = get_option('blog_public') === '0';
        $frontPage = (int) get_option('page_on_front');
        $menuLinked = $this->getMenuLinkedObjectIds();
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
             ORDER BY post_modified DESC
             LIMIT 300",
            ARRAY_A
        );
        $items = [];

        foreach ((array) $posts as $post) {
            $id = (int) $post['ID'];
            $sources = $this->getNoindexSourcesForPost($id, $sitewideNoindex);
            if ($sources === []) {
                continue;
            }

            $items[] = [
                'id'             => $id,
                'title'          => (string) $post['post_title'],
                'post_type'      => (string) $post['post_type'],
                'url'            => get_permalink($id) ?: '',
                'edit_url'       => get_edit_post_link($id, 'raw') ?: '',
                'sources'        => $sources,
                'primary_source' => (string) ($sources[0]['label'] ?? 'Unknown'),
                'is_homepage'    => $id === $frontPage,
                'is_nav_linked'  => isset($menuLinked[$id]),
                'can_toggle'     => $this->canToggleNoindexSources($sources),
            ];
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    public function updateNoindex(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) ($request->get_param('post_id') ?? 0);
        $enabled = !empty($request->get_param('enabled'));
        if ($postId === 0 || !get_post($postId)) {
            return new WP_REST_Response(['error' => 'Post not found'], 404);
        }

        $sources = $this->getNoindexSourcesForPost($postId, get_option('blog_public') === '0');
        $handled = false;
        foreach ($sources as $source) {
            $key = (string) ($source['key'] ?? '');
            if ($key === '') {
                continue;
            }
            if ($key === 'rank_math_robots') {
                $robots = array_filter(array_map('trim', explode(',', (string) get_post_meta($postId, $key, true))));
                $robots = $enabled ? array_values(array_unique([...$robots, 'noindex'])) : array_values(array_filter($robots, static fn ($value) => $value !== 'noindex'));
                update_post_meta($postId, $key, implode(',', $robots));
                $handled = true;
                continue;
            }
            update_post_meta($postId, $key, $enabled ? '1' : '0');
            $handled = true;
        }
        if (!$handled) {
            update_post_meta($postId, self::META_BEACON_NOINDEX, $enabled ? '1' : '0');
        }

        return new WP_REST_Response(['ok' => true, 'post_id' => $postId, 'enabled' => $enabled], 200);
    }

    // -----------------------------------------------------------------------
    // Redirect Chains
    // -----------------------------------------------------------------------

    public function scanRedirectChains(WP_REST_Request $request): WP_REST_Response
    {
        $redirects = $this->redirectsRepo->all();
        $map = [];
        foreach ($redirects as $redirect) {
            $source = (string) ($redirect['source_path'] ?? '');
            if ($source !== '') {
                $map[$source] = $redirect;
            }
        }

        $items = [];
        foreach ($map as $source => $redirect) {
            $target = (string) ($redirect['target_url'] ?? '');
            $targetPath = (string) (parse_url($target, PHP_URL_PATH) ?: $target);
            if (!isset($map[$targetPath])) {
                continue;
            }

            $chain = [$source, $target];
            $visited = [$source => true];
            $current = $targetPath;
            $type = 'chain';
            $finalUrl = $target;

            while (isset($map[$current])) {
                $currentRedirect = $map[$current];
                $finalUrl = (string) ($currentRedirect['target_url'] ?? $finalUrl);
                $next = (string) (parse_url($finalUrl, PHP_URL_PATH) ?: $finalUrl);
                $chain[] = $finalUrl;

                if (isset($visited[$current])) {
                    $type = 'loop';
                    break;
                }

                $visited[$current] = true;
                $current = $next;

                if (count($chain) > 12) {
                    break;
                }
            }

            if (count($chain) > 2) {
                $items[] = [
                    'redirect_id'  => (int) ($redirect['id'] ?? 0),
                    'source_path'  => $source,
                    'chain'        => $chain,
                    'type'         => $type,
                    'length'       => count($chain) - 1,
                    'final_url'    => $finalUrl,
                    'can_collapse' => $type === 'chain' && $finalUrl !== '',
                ];
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => $items], 200);
    }

    public function collapseRedirectChain(WP_REST_Request $request): WP_REST_Response
    {
        $redirectId = (int) ($request->get_param('redirect_id') ?? 0);
        $finalUrl   = trim((string) ($request->get_param('final_url') ?? ''));
        $redirect   = $this->redirectsRepo->find($redirectId);

        if (!$redirect) {
            return new WP_REST_Response(['error' => 'Redirect not found'], 404);
        }
        if ($finalUrl === '') {
            return new WP_REST_Response(['error' => 'Final URL is required'], 422);
        }

        $updated = $this->redirectsRepo->update(
            $redirectId,
            (string) $redirect['source_path'],
            $finalUrl,
            (int) $redirect['redirect_type'],
            !empty($redirect['is_active']),
            !empty($redirect['regex_enabled'])
        );

        return new WP_REST_Response(['ok' => $updated, 'redirect_id' => $redirectId, 'final_url' => $finalUrl], $updated ? 200 : 500);
    }

    public function scanBrokenLinks(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $dismissed = $this->getBrokenLinkDismissals();
        $siteUrl   = rtrim((string) get_site_url(), '/');
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_content
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_content LIKE '%<a %'
             ORDER BY post_modified DESC
             LIMIT 150",
            ARRAY_A
        );

        $items = [];
        $checkedAt = current_time('mysql');

        foreach ((array) $posts as $post) {
            preg_match_all('/<a\b[^>]*href=("|\')(.*?)\1[^>]*>(.*?)<\/a>/is', (string) $post['post_content'], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $anchorHtml = (string) ($match[0] ?? '');
                $href       = html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES);
                $anchorText = trim(wp_strip_all_tags((string) ($match[3] ?? '')));
                if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                    continue;
                }

                $isInternal = str_starts_with($href, $siteUrl) || (str_starts_with($href, '/') && !str_starts_with($href, '//'));
                $diagnostic = $isInternal ? $this->diagnoseInternalLink($href) : $this->diagnoseExternalLink($href);
                if ((int) ($diagnostic['http_status'] ?? 0) < 400) {
                    continue;
                }

                $signature = md5((int) $post['ID'] . '|' . $href . '|' . $anchorText);
                if (isset($dismissed[$signature])) {
                    continue;
                }

                $items[] = [
                    'id'           => (int) $post['ID'],
                    'post_id'      => (int) $post['ID'],
                    'title'        => (string) $post['post_title'],
                    'post_type'    => (string) $post['post_type'],
                    'broken_url'   => $href,
                    'anchor_text'  => $anchorText,
                    'anchor_html'  => $anchorHtml,
                    'link_type'    => $isInternal ? 'internal' : 'external',
                    'http_status'  => (int) ($diagnostic['http_status'] ?? 0),
                    'http_label'   => (string) ($diagnostic['label'] ?? ''),
                    'last_checked' => $checkedAt,
                    'signature'    => $signature,
                    'url'          => get_permalink((int) $post['ID']) ?: '',
                    'edit_url'     => get_edit_post_link((int) $post['ID'], 'raw') ?: '',
                ];
            }
        }

        return new WP_REST_Response(['total' => count($items), 'items' => array_slice($items, 0, 250)], 200);
    }

    public function dismissBrokenLink(WP_REST_Request $request): WP_REST_Response
    {
        $signature = sanitize_text_field((string) ($request->get_param('signature') ?? ''));
        $dismissed = !empty($request->get_param('dismissed'));
        if ($signature === '') {
            return new WP_REST_Response(['error' => 'Signature is required'], 422);
        }

        $items = $this->getBrokenLinkDismissals();
        if ($dismissed) {
            $items[$signature] = current_time('mysql');
        } else {
            unset($items[$signature]);
        }
        update_option(self::OPTION_BROKEN_LINK_DISMISSALS, $items, false);

        return new WP_REST_Response(['ok' => true, 'signature' => $signature, 'dismissed' => $dismissed], 200);
    }

    public function updateBrokenLinkUrl(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) ($request->get_param('post_id') ?? 0);
        $originalUrl = (string) ($request->get_param('original_url') ?? '');
        $replacementUrl = esc_url_raw((string) ($request->get_param('replacement_url') ?? ''));
        if ($postId === 0 || !get_post($postId)) {
            return new WP_REST_Response(['error' => 'Post not found'], 404);
        }
        if ($originalUrl === '' || $replacementUrl === '') {
            return new WP_REST_Response(['error' => 'Original and replacement URLs are required'], 422);
        }

        $content = (string) (get_post($postId)?->post_content ?? '');
        $updated = str_replace('href="' . $originalUrl . '"', 'href="' . esc_attr($replacementUrl) . '"', $content, $count);
        if ($count < 1) {
            $updated = str_replace("href='" . $originalUrl . "'", "href='" . esc_attr($replacementUrl) . "'", $content, $count);
        }
        if ($count < 1) {
            return new WP_REST_Response(['error' => 'Matching link not found'], 404);
        }

        wp_update_post(['ID' => $postId, 'post_content' => $updated]);
        return new WP_REST_Response(['ok' => true, 'post_id' => $postId, 'replacement_url' => $replacementUrl], 200);
    }

    public function unlinkBrokenLink(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) ($request->get_param('post_id') ?? 0);
        $anchorHtml = (string) ($request->get_param('anchor_html') ?? '');
        if ($postId === 0 || !get_post($postId)) {
            return new WP_REST_Response(['error' => 'Post not found'], 404);
        }
        if ($anchorHtml === '') {
            return new WP_REST_Response(['error' => 'Anchor HTML is required'], 422);
        }

        $content = (string) (get_post($postId)?->post_content ?? '');
        $replacement = preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $anchorHtml, 1);
        $updated = str_replace($anchorHtml, is_string($replacement) ? $replacement : '', $content, $count);
        if ($count < 1) {
            return new WP_REST_Response(['error' => 'Matching anchor not found'], 404);
        }

        wp_update_post(['ID' => $postId, 'post_content' => $updated]);
        return new WP_REST_Response(['ok' => true, 'post_id' => $postId], 200);
    }

    private function getMetaDescriptionForPost(int $postId): array
    {
        $keys = [
            '_yoast_wpseo_metadesc'    => 'yoast',
            '_aioseo_description'      => 'aioseo',
            'rank_math_description'    => 'rank_math',
            self::META_BEACON_DESCRIPTION => 'beacon',
        ];

        foreach ($keys as $key => $source) {
            $value = trim((string) get_post_meta($postId, $key, true));
            if ($value !== '') {
                return ['value' => $value, 'source' => $source, 'key' => $key];
            }
        }

        return ['value' => '', 'source' => 'none', 'key' => self::META_BEACON_DESCRIPTION];
    }

    private function buildInboundLinkMap(array $posts): array
    {
        $map = [];
        foreach ($posts as $post) {
            $map[(int) ($post['ID'] ?? 0)] = 0;
        }

        foreach ($posts as $post) {
            $postId = (int) ($post['ID'] ?? 0);
            preg_match_all('/href=("|\')(.*?)\1/i', (string) ($post['post_content'] ?? ''), $matches);
            foreach ((array) ($matches[2] ?? []) as $href) {
                $targetId = url_to_postid((string) $href);
                if ($targetId > 0 && $targetId !== $postId) {
                    $map[$targetId] = ($map[$targetId] ?? 0) + 1;
                }
            }
        }

        return $map;
    }

    private function getIgnoredOrphanIds(): array
    {
        $ids = array_values(array_filter(array_map('intval', (array) get_option(self::OPTION_ORPHAN_EXCEPTIONS, []))));
        return array_fill_keys($ids, true);
    }

    private function getMenuLinkedObjectIds(): array
    {
        $ids = [];
        foreach ((array) wp_get_nav_menus() as $menu) {
            foreach ((array) wp_get_nav_menu_items($menu) as $item) {
                $objectId = (int) ($item->object_id ?? 0);
                if ($objectId > 0) {
                    $ids[$objectId] = true;
                }
            }
        }

        return $ids;
    }

    private function extractAttribute(string $tag, string $attribute): ?string
    {
        if (preg_match('/\b' . preg_quote($attribute, '/') . '\s*=\s*("|\')(.*?)\1/i', $tag, $match) === 1) {
            return html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES);
        }

        return null;
    }

    private function getUnusedMediaExclusionPatterns(): array
    {
        return array_values(array_filter(array_map(
            static fn ($value) => sanitize_text_field((string) $value),
            (array) get_option(self::OPTION_UNUSED_MEDIA_EXCLUSIONS, [])
        )));
    }

    private function matchesExcludedPattern(array $haystacks, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if ($pattern === '') {
                continue;
            }
            foreach ($haystacks as $haystack) {
                if ($haystack !== '' && stripos($haystack, $pattern) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function attachmentReferencedInMeta(int $attachmentId, string $url, string $filename): bool
    {
        global $wpdb;

        $idHit = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value = %s LIMIT 1", (string) $attachmentId));
        if ($idHit > 0) {
            return true;
        }

        if ($url !== '') {
            $urlHit = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s LIMIT 1", '%' . $wpdb->esc_like($url) . '%'));
            if ($urlHit > 0) {
                return true;
            }
        }

        if ($filename !== '') {
            $fileHit = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s LIMIT 1", '%' . $wpdb->esc_like($filename) . '%'));
            if ($fileHit > 0) {
                return true;
            }
        }

        return false;
    }

    private function getNoindexSourcesForPost(int $postId, bool $sitewideNoindex): array
    {
        $sources = [];
        if ($sitewideNoindex) {
            $sources[] = ['key' => '', 'label' => 'Site visibility', 'value' => 'Search engines are discouraged globally in Settings > Reading.'];
        }

        $checks = [
            self::META_BEACON_NOINDEX          => 'Beacon',
            '_yoast_wpseo_meta-robots-noindex' => 'Yoast SEO',
            '_aioseo_robots_noindex'           => 'All in One SEO',
        ];
        foreach ($checks as $key => $label) {
            if ((string) get_post_meta($postId, $key, true) === '1') {
                $sources[] = ['key' => $key, 'label' => $label, 'value' => 'Noindex enabled on this post.'];
            }
        }

        $rankMath = (string) get_post_meta($postId, 'rank_math_robots', true);
        if ($rankMath !== '' && stripos($rankMath, 'noindex') !== false) {
            $sources[] = ['key' => 'rank_math_robots', 'label' => 'Rank Math', 'value' => 'Robots directives include noindex.'];
        }

        return $sources;
    }

    private function canToggleNoindexSources(array $sources): bool
    {
        foreach ($sources as $source) {
            if ((string) ($source['key'] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function getBrokenLinkDismissals(): array
    {
        $items = get_option(self::OPTION_BROKEN_LINK_DISMISSALS, []);
        return is_array($items) ? array_filter($items, 'is_string') : [];
    }

    private function diagnoseInternalLink(string $href): array
    {
        $targetId = url_to_postid($href);
        if ($targetId > 0 && get_post_status($targetId) === 'publish') {
            return ['http_status' => 200, 'label' => 'OK'];
        }

        return ['http_status' => 404, 'label' => 'Target not found'];
    }

    private function diagnoseExternalLink(string $href): array
    {
        $response = wp_remote_head($href, ['timeout' => 5, 'redirection' => 3]);
        if (is_wp_error($response)) {
            return ['http_status' => 500, 'label' => $response->get_error_message()];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status === 405 || $status === 0) {
            $response = wp_remote_get($href, ['timeout' => 5, 'redirection' => 3]);
            if (is_wp_error($response)) {
                return ['http_status' => 500, 'label' => $response->get_error_message()];
            }
            $status = (int) wp_remote_retrieve_response_code($response);
        }

        return ['http_status' => $status, 'label' => $status >= 400 ? 'HTTP ' . $status : 'OK'];
    }

    // -----------------------------------------------------------------------
    // Bulk operations
    // -----------------------------------------------------------------------

    public function bulkUpdateMeta(WP_REST_Request $request): WP_REST_Response
    {
        $updates = (array) $request->get_param('updates');
        $count = 0;

        foreach ($updates as $update) {
            $postId = (int) ($update['post_id'] ?? 0);
            if ($postId <= 0 || !current_user_can('edit_post', $postId)) continue;

            $title = sanitize_text_field((string) ($update['post_title'] ?? ''));
            $meta  = sanitize_textarea_field((string) ($update['meta_description'] ?? ''));

            wp_update_post(['ID' => $postId, 'post_title' => $title]);
            update_post_meta($postId, '_beacon_meta_description', $meta);

            // Also update Yoast/RankMath if available
            if (defined('WPSEO_VERSION')) {
                update_post_meta($postId, '_yoast_wpseo_metadesc', $meta);
            }
            if (defined('STARTER_PLUGIN_FILE') || class_exists('RankMath')) {
                update_post_meta($postId, 'rank_math_description', $meta);
            }
            $count++;
        }

        return new WP_REST_Response(['ok' => true, 'updated' => $count], 200);
    }

    public function renameDuplicate(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        $title  = sanitize_text_field((string) $request->get_param('post_title'));

        if ($postId <= 0 || !current_user_can('edit_post', $postId)) {
            return new WP_REST_Response(['error' => 'Insufficient permissions.'], 403);
        }

        wp_update_post(['ID' => $postId, 'post_title' => $title]);

        return new WP_REST_Response(['ok' => true, 'post_id' => $postId, 'post_title' => $title], 200);
    }

    public function bulkRenameDuplicates(WP_REST_Request $request): WP_REST_Response
    {
        $updates = (array) $request->get_param('updates');
        $count = 0;

        foreach ($updates as $update) {
            $postId = (int) ($update['post_id'] ?? 0);
            $title  = sanitize_text_field((string) ($update['post_title'] ?? ''));
            if ($postId <= 0 || $title === '' || !current_user_can('edit_post', $postId)) continue;

            wp_update_post(['ID' => $postId, 'post_title' => $title]);
            $count++;
        }

        return new WP_REST_Response(['ok' => true, 'updated' => $count], 200);
    }

    public function saveBrokenLinksSchedule(WP_REST_Request $request): WP_REST_Response
    {
        $frequency = sanitize_key((string) $request->get_param('frequency'));
        $hook = 'dr_beacon_broken_links_scan';

        $next = wp_next_scheduled($hook);
        if ($next) {
            wp_unschedule_event($next, $hook);
        }

        if ($frequency === 'daily' || $frequency === 'weekly') {
            wp_schedule_event(time() + HOUR_IN_SECONDS, $frequency, $hook);
        }

        update_option('dr_beacon_broken_links_schedule', $frequency, false);

        return new WP_REST_Response(['ok' => true, 'frequency' => $frequency], 200);
    }
}
