<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Repositories\RedirectsRepository;
use DigitalRoyalty\Beacon\Support\Enums\Admin\UserSwitcherEnum;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Interactive tool endpoints for Workshop phase-5 tools.
 * Handles CRUD, search, mutations, and file operations.
 */
final class WorkshopInteractiveController
{
    private const OPTION_CLONE_SETTINGS = 'dr_beacon_clone_post_settings';

    public function __construct(
        private readonly RedirectsRepository $redirectsRepo
    ) {}

    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        // Redirects
        register_rest_route('beacon/v1', '/admin/workshop/redirects', [
            ['methods' => 'GET',  'callback' => [$this, 'listRedirects'],   'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'createRedirect'],  'permission_callback' => $perm],
            ['methods' => 'DELETE', 'callback' => [$this, 'bulkDeleteRedirects'], 'permission_callback' => $perm],
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/redirects/(?P<id>\d+)', [
            ['methods' => 'PUT',    'callback' => [$this, 'updateRedirect'], 'permission_callback' => $perm],
            ['methods' => 'DELETE', 'callback' => [$this, 'deleteRedirect'], 'permission_callback' => $perm],
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/redirects/test', [
            'methods'             => 'POST',
            'callback'            => [$this, 'testRedirect'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/redirects/import', [
            'methods'             => 'POST',
            'callback'            => [$this, 'importRedirects'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/redirects/export', [
            'methods'             => 'GET',
            'callback'            => [$this, 'exportRedirects'],
            'permission_callback' => $perm,
        ]);

        // Post search (shared by several tools)
        register_rest_route('beacon/v1', '/admin/workshop/post-search', [
            'methods'             => 'GET',
            'callback'            => [$this, 'postSearch'],
            'permission_callback' => $perm,
        ]);

        // Post Type Switcher
        register_rest_route('beacon/v1', '/admin/workshop/post-type-switch', [
            'methods'             => 'POST',
            'callback'            => [$this, 'switchPostType'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/post-types', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listPostTypes'],
            'permission_callback' => $perm,
        ]);

        // Clone Post
        register_rest_route('beacon/v1', '/admin/workshop/clone-post', [
            'methods'             => 'POST',
            'callback'            => [$this, 'clonePost'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/clone-post/preview', [
            'methods'             => 'GET',
            'callback'            => [$this, 'clonePostPreview'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/clone-post/settings', [
            ['methods' => 'GET', 'callback' => [$this, 'getCloneSettings'], 'permission_callback' => $perm],
            ['methods' => 'POST', 'callback' => [$this, 'saveCloneSettings'], 'permission_callback' => $perm],
        ]);

        // Find & Replace
        register_rest_route('beacon/v1', '/admin/workshop/find-replace/preview', [
            'methods'             => 'POST',
            'callback'            => [$this, 'findReplacePreview'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/find-replace/execute', [
            'methods'             => 'POST',
            'callback'            => [$this, 'findReplaceExecute'],
            'permission_callback' => $perm,
        ]);

        // Media Replace
        register_rest_route('beacon/v1', '/admin/workshop/media-replace', [
            'methods'             => 'POST',
            'callback'            => [$this, 'mediaReplace'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/media-replace/preview', [
            'methods'             => 'GET',
            'callback'            => [$this, 'mediaReplacePreview'],
            'permission_callback' => $perm,
        ]);

        // User Switcher
        register_rest_route('beacon/v1', '/admin/workshop/users', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listUsers'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/user-switch-url/(?P<user_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getUserSwitchUrl'],
            'permission_callback' => $perm,
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/user-switch-log/settings', [
            ['methods' => 'POST', 'callback' => [$this, 'saveUserSwitchSettings'], 'permission_callback' => $perm],
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/user-switch-log/export', [
            ['methods' => 'GET', 'callback' => [$this, 'exportUserSwitchLog'], 'permission_callback' => $perm],
        ]);
    }

    // -----------------------------------------------------------------------
    // Redirects
    // -----------------------------------------------------------------------

    public function listRedirects(WP_REST_Request $request): WP_REST_Response
    {
        $search = trim((string) ($request->get_param('search') ?? ''));

        $rows = $this->redirectsRepo->all($search);
        foreach ($rows as &$row) {
            $row['conditions'] = json_decode((string) ($row['conditions'] ?? '[]'), true) ?: [];
        }
        unset($row);
        return new WP_REST_Response($rows, 200);
    }

    public function createRedirect(WP_REST_Request $request): WP_REST_Response
    {
        $source = trim((string) ($request->get_param('source_path') ?? ''));
        $target = trim((string) ($request->get_param('target_url')  ?? ''));
        $type   = (int) ($request->get_param('redirect_type') ?? 301);
        $regex  = !empty($request->get_param('regex_enabled'));

        if ($source === '' || $target === '') {
            return new WP_REST_Response(['error' => 'source_path and target_url are required'], 400);
        }

        if (!in_array($type, [301, 302], true)) {
            $type = 301;
        }

        $validation = $this->validateRedirectInput($source, $target, $regex);
        if ($validation !== null) {
            return $validation;
        }

        $conditions = wp_json_encode((array) $request->get_param('conditions'));

        $id  = $this->redirectsRepo->create($source, $target, $type, $regex, $conditions);
        $row = $this->redirectsRepo->find($id);
        if (is_array($row)) {
            $row['conditions'] = json_decode((string) ($row['conditions'] ?? '[]'), true) ?: [];
        }

        return new WP_REST_Response($row, 201);
    }

    public function updateRedirect(WP_REST_Request $request): WP_REST_Response
    {
        $id     = (int) $request->get_param('id');
        $source = trim((string) ($request->get_param('source_path') ?? ''));
        $target = trim((string) ($request->get_param('target_url')  ?? ''));
        $type   = (int) ($request->get_param('redirect_type') ?? 301);
        $active = (bool) ($request->get_param('is_active') ?? true);
        $regex  = !empty($request->get_param('regex_enabled'));

        if ($source === '' || $target === '') {
            return new WP_REST_Response(['error' => 'source_path and target_url are required'], 400);
        }

        if (!in_array($type, [301, 302], true)) {
            $type = 301;
        }

        $validation = $this->validateRedirectInput($source, $target, $regex, $id);
        if ($validation !== null) {
            return $validation;
        }

        $conditions = wp_json_encode((array) $request->get_param('conditions'));

        $ok  = $this->redirectsRepo->update($id, $source, $target, $type, $active, $regex, $conditions);
        $row = $this->redirectsRepo->find($id);
        if (is_array($row)) {
            $row['conditions'] = json_decode((string) ($row['conditions'] ?? '[]'), true) ?: [];
        }

        return new WP_REST_Response($row ?? ['error' => 'not found'], $ok ? 200 : 404);
    }

    public function deleteRedirect(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $ok = $this->redirectsRepo->delete($id);

        return new WP_REST_Response(null, $ok ? 204 : 404);
    }

    public function bulkDeleteRedirects(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $ids    = array_values(array_filter(array_map('intval', (array) ($params['ids'] ?? []))));

        return new WP_REST_Response([
            'ok'      => true,
            'deleted' => $this->redirectsRepo->bulkDelete($ids),
        ], 200);
    }

    public function testRedirect(WP_REST_Request $request): WP_REST_Response
    {
        $source = trim((string) ($request->get_param('source_path') ?? ''));

        if ($source === '') {
            return new WP_REST_Response(['error' => 'source_path is required'], 400);
        }

        $match = $this->redirectsRepo->findBySourcePath($source);

        return new WP_REST_Response([
            'matched'  => $match !== null,
            'redirect' => $match,
        ], 200);
    }

    public function importRedirects(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $items  = array_values(array_filter((array) ($params['items'] ?? []), 'is_array'));
        $created = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $source = trim((string) ($item['source_path'] ?? ''));
            $target = trim((string) ($item['target_url'] ?? ''));
            $type   = (int) ($item['redirect_type'] ?? 301);
            $regex  = !empty($item['regex_enabled']);

            if ($source === '' || $target === '' || $this->validateRedirectInput($source, $target, $regex) !== null) {
                $skipped++;
                continue;
            }

            $this->redirectsRepo->create($source, $target, in_array($type, [301, 302], true) ? $type : 301, $regex);
            $created++;
        }

        return new WP_REST_Response([
            'ok'      => true,
            'created' => $created,
            'skipped' => $skipped,
        ], 200);
    }

    public function exportRedirects(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'exported_at' => current_time('mysql'),
            'items'       => $this->redirectsRepo->all(),
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Post Search
    // -----------------------------------------------------------------------

    public function postSearch(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $q       = trim((string) ($request->get_param('q') ?? ''));
        $typeArg = (string) ($request->get_param('post_type') ?? '');

        if ($q === '') {
            return new WP_REST_Response([], 200);
        }

        $likeQ    = '%' . $wpdb->esc_like($q) . '%';
        $typeWhere = '';

        if ($typeArg !== '') {
            $typeWhere = $wpdb->prepare('AND post_type = %s', $typeArg);
        } else {
            $typeWhere = "AND post_type IN ('post','page')";
        }

        $searchColumn = $typeArg === 'attachment'
            ? '(post_title LIKE %s OR guid LIKE %s)'
            : 'post_title LIKE %s';

        $params = $typeArg === 'attachment' ? [$likeQ, $likeQ] : [$likeQ];

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_type, post_status
                 FROM {$wpdb->posts}
                 WHERE post_status IN ('publish','draft')
                   {$typeWhere}
                   AND {$searchColumn}
                 ORDER BY post_title ASC
                 LIMIT 20",
                ...$params
            ),
            ARRAY_A
        );

        return new WP_REST_Response(is_array($rows) ? $rows : [], 200);
    }

    // -----------------------------------------------------------------------
    // Post Type Switcher
    // -----------------------------------------------------------------------

    public function switchPostType(WP_REST_Request $request): WP_REST_Response
    {
        $newPostType = (string) ($request->get_param('post_type') ?? '');
        $postIds     = array_values(array_filter(array_map('intval', (array) ($request->get_param('post_ids') ?? []))));
        $singleId    = (int) ($request->get_param('post_id') ?? 0);

        if ($singleId > 0 && $postIds === []) {
            $postIds = [$singleId];
        }

        if ($postIds === [] || $newPostType === '') {
            return new WP_REST_Response(['error' => 'post_id or post_ids and post_type are required'], 400);
        }

        if (!post_type_exists($newPostType)) {
            return new WP_REST_Response(['error' => 'Invalid post type'], 400);
        }

        $results = [];
        $updated = 0;

        foreach ($postIds as $postId) {
            $post = get_post($postId);
            if (!$post) {
                continue;
            }

            $warnings = $this->getPostTypeSwitchWarnings($post, $newPostType);
            $result = wp_update_post(['ID' => $postId, 'post_type' => $newPostType]);

            if (is_wp_error($result)) {
                $results[] = [
                    'post_id'   => $postId,
                    'post_type' => $post->post_type,
                    'edit_url'  => get_edit_post_link($postId, 'raw') ?: '',
                    'warnings'  => array_merge($warnings, [$result->get_error_message()]),
                    'ok'        => false,
                ];
                continue;
            }

            $updated++;
            $results[] = [
                'post_id'   => $postId,
                'post_type' => $newPostType,
                'edit_url'  => get_edit_post_link($postId, 'raw') ?: '',
                'warnings'  => $warnings,
                'ok'        => true,
            ];
        }

        if (count($results) === 1) {
            return new WP_REST_Response($results[0], 200);
        }

        return new WP_REST_Response([
            'ok'      => true,
            'updated' => $updated,
            'items'   => $results,
        ], 200);
    }

    public function listPostTypes(WP_REST_Request $request): WP_REST_Response
    {
        $objects = get_post_types(['show_ui' => true], 'objects');
        $items   = [];

        foreach ($objects as $slug => $object) {
            $items[] = [
                'slug'  => (string) $slug,
                'label' => (string) ($object->labels->singular_name ?? $object->label ?? $slug),
            ];
        }

        return new WP_REST_Response($items, 200);
    }

    // -----------------------------------------------------------------------
    // Clone Post
    // -----------------------------------------------------------------------

    public function clonePost(WP_REST_Request $request): WP_REST_Response
    {
        $postIds = array_values(array_filter(array_map('intval', (array) ($request->get_param('post_ids') ?? []))));
        $singleId = (int) ($request->get_param('post_id') ?? 0);

        if ($singleId > 0 && $postIds === []) {
            $postIds = [$singleId];
        }

        if ($postIds === []) {
            return new WP_REST_Response(['error' => 'post_id or post_ids is required'], 400);
        }

        $settings = (array) get_option(self::OPTION_CLONE_SETTINGS, []);
        $excludedMetaKeys = array_values(array_filter(array_map('sanitize_text_field', (array) ($settings['excluded_meta_keys'] ?? []))));
        $items = [];

        foreach ($postIds as $postId) {
            $post = get_post($postId);
            if (!$post) {
                continue;
            }

            $newId = wp_insert_post([
                'post_title'   => $post->post_title . ' (Copy)',
                'post_content' => $post->post_content,
                'post_excerpt' => $post->post_excerpt,
                'post_type'    => $post->post_type,
                'post_status'  => 'draft',
                'post_author'  => get_current_user_id(),
            ]);

            if (is_wp_error($newId)) {
                continue;
            }

            $metaRows = get_post_meta($postId);
            foreach ((array) $metaRows as $key => $values) {
                if (in_array((string) $key, $excludedMetaKeys, true)) {
                    continue;
                }
                foreach ($values as $value) {
                    add_post_meta($newId, $key, maybe_unserialize($value));
                }
            }

            $taxonomies = get_object_taxonomies($post->post_type);
            foreach ($taxonomies as $taxonomy) {
                $termIds = wp_get_object_terms($postId, $taxonomy, ['fields' => 'ids']);
                if (!is_wp_error($termIds) && is_array($termIds)) {
                    wp_set_object_terms($newId, $termIds, $taxonomy, false);
                }
            }

            $items[] = [
                'new_post_id' => $newId,
                'source_post_id' => $postId,
                'title'       => $post->post_title . ' (Copy)',
                'edit_url'    => get_edit_post_link($newId, 'raw') ?: '',
            ];
        }

        if ($items === []) {
            return new WP_REST_Response(['error' => 'No posts could be cloned.'], 500);
        }

        if (count($items) === 1) {
            return new WP_REST_Response($items[0], 201);
        }

        return new WP_REST_Response([
            'ok'    => true,
            'count' => count($items),
            'items' => $items,
        ], 201);
    }

    public function clonePostPreview(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) ($request->get_param('post_id') ?? 0);
        $post   = get_post($postId);

        if (!$post) {
            return new WP_REST_Response(['error' => 'Post not found'], 404);
        }

        $settings = (array) get_option(self::OPTION_CLONE_SETTINGS, []);
        $excludedMetaKeys = array_values(array_filter(array_map('sanitize_text_field', (array) ($settings['excluded_meta_keys'] ?? []))));
        $metaRows = array_keys((array) get_post_meta($postId));
        $includedMeta = array_values(array_filter($metaRows, fn ($key) => !in_array((string) $key, $excludedMetaKeys, true)));
        $taxonomies = get_object_taxonomies($post->post_type, 'names');

        return new WP_REST_Response([
            'post_id'           => $postId,
            'title'             => (string) $post->post_title,
            'post_type'         => (string) $post->post_type,
            'post_status'       => (string) $post->post_status,
            'author'            => get_the_author_meta('display_name', (int) $post->post_author) ?: '',
            'featured_image_id' => (int) get_post_thumbnail_id($postId),
            'included_meta'     => array_values($includedMeta),
            'excluded_meta'     => $excludedMetaKeys,
            'taxonomy_count'    => count($taxonomies),
            'taxonomies'        => array_values($taxonomies),
            'edit_url'          => get_edit_post_link($postId, 'raw') ?: '',
        ], 200);
    }

    public function getCloneSettings(WP_REST_Request $request): WP_REST_Response
    {
        $settings = (array) get_option(self::OPTION_CLONE_SETTINGS, []);

        return new WP_REST_Response([
            'excluded_meta_keys' => array_values(array_filter(array_map('sanitize_text_field', (array) ($settings['excluded_meta_keys'] ?? [])))),
        ], 200);
    }

    public function saveCloneSettings(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $excludedMetaKeys = array_values(array_filter(array_map('sanitize_text_field', (array) ($params['excluded_meta_keys'] ?? []))));

        update_option(self::OPTION_CLONE_SETTINGS, [
            'excluded_meta_keys' => $excludedMetaKeys,
        ], false);

        return new WP_REST_Response([
            'ok'                 => true,
            'excluded_meta_keys' => $excludedMetaKeys,
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Find & Replace
    // -----------------------------------------------------------------------

    public function findReplacePreview(WP_REST_Request $request): WP_REST_Response
    {
        $find    = (string) ($request->get_param('find')    ?? '');
        $replace = (string) ($request->get_param('replace') ?? '');
        $scope   = (string) ($request->get_param('scope')   ?? 'post_content');
        $regex   = !empty($request->get_param('regex'));
        $caseSensitive = !empty($request->get_param('case_sensitive'));
        $tables  = array_values(array_filter(array_map('sanitize_key', (array) ($request->get_param('tables') ?? ['posts']))));

        if ($find === '') {
            return new WP_REST_Response(['error' => 'find is required'], 400);
        }

        [$tables, $scopeError] = $this->normalizeFindReplaceTargets($tables, $scope);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $result = $this->buildFindReplacePreview($find, $replace, $scope, $tables, $regex, $caseSensitive);

        return new WP_REST_Response([
            'total'    => (int) $result['total'],
            'find'     => $find,
            'replace'  => $replace,
            'scope'    => $scope,
            'tables'   => $tables,
            'regex'    => $regex,
            'case_sensitive' => $caseSensitive,
            'serialised_safe' => true,
            'truncated' => !empty($result['truncated']),
            'progress_mode' => 'immediate',
            'previews' => $result['previews'],
        ], 200);
    }

    public function findReplaceExecute(WP_REST_Request $request): WP_REST_Response
    {
        $find    = (string) ($request->get_param('find')    ?? '');
        $replace = (string) ($request->get_param('replace') ?? '');
        $scope   = (string) ($request->get_param('scope')   ?? 'post_content');
        $regex   = !empty($request->get_param('regex'));
        $caseSensitive = !empty($request->get_param('case_sensitive'));
        $tables  = array_values(array_filter(array_map('sanitize_key', (array) ($request->get_param('tables') ?? ['posts']))));

        if ($find === '') {
            return new WP_REST_Response(['error' => 'find is required'], 400);
        }

        [$tables, $scopeError] = $this->normalizeFindReplaceTargets($tables, $scope);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $updated = $this->executeFindReplace($find, $replace, $scope, $tables, $regex, $caseSensitive);

        return new WP_REST_Response([
            'updated'        => (int) $updated,
            'find'           => $find,
            'replace'        => $replace,
            'scope'          => $scope,
            'tables'         => $tables,
            'regex'          => $regex,
            'serialised_safe' => true,
            'case_sensitive' => $caseSensitive,
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Media Replace
    // -----------------------------------------------------------------------

    public function mediaReplace(WP_REST_Request $request): WP_REST_Response
    {
        $attachmentId = (int) ($request->get_param('attachment_id') ?? 0);
        $preserveFilename = !empty($request->get_param('preserve_filename'));

        if ($attachmentId === 0) {
            return new WP_REST_Response(['error' => 'attachment_id is required'], 400);
        }

        $post = get_post($attachmentId);
        if (!$post || $post->post_type !== 'attachment') {
            return new WP_REST_Response(['error' => 'Attachment not found'], 404);
        }

        if (empty($_FILES['file'])) {
            return new WP_REST_Response(['error' => 'No file uploaded'], 400);
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $uploaded = wp_handle_upload($_FILES['file'], ['test_form' => false]);

        if (isset($uploaded['error'])) {
            return new WP_REST_Response(['error' => $uploaded['error']], 422);
        }

        // Replace attachment file
        $oldFile = get_attached_file($attachmentId);
        $oldMime = (string) get_post_mime_type($attachmentId);
        $newMime = (string) ($uploaded['type'] ?? '');
        $mimeChanged = $oldMime !== '' && $newMime !== '' && $oldMime !== $newMime;

        $targetFile = $uploaded['file'];

        if ($preserveFilename && $oldFile) {
            $targetFile = trailingslashit(dirname($oldFile)) . basename($oldFile);
            @copy($uploaded['file'], $targetFile);
            @unlink($uploaded['file']);
        }

        if ($oldFile && file_exists($oldFile)) {
            @unlink($oldFile);
        }

        update_attached_file($attachmentId, $targetFile);
        $metadata = wp_generate_attachment_metadata($attachmentId, $targetFile);
        wp_update_attachment_metadata($attachmentId, $metadata);

        return new WP_REST_Response([
            'attachment_id' => $attachmentId,
            'url'           => wp_get_attachment_url($attachmentId) ?: $uploaded['url'],
            'mime_changed'  => $mimeChanged,
            'old_mime'      => $oldMime,
            'new_mime'      => $newMime,
            'generated_sizes' => count((array) ($metadata['sizes'] ?? [])),
        ], 200);
    }

    public function mediaReplacePreview(WP_REST_Request $request): WP_REST_Response
    {
        $attachmentId = (int) ($request->get_param('attachment_id') ?? 0);
        $post = get_post($attachmentId);

        if (!$post || $post->post_type !== 'attachment') {
            return new WP_REST_Response(['error' => 'Attachment not found'], 404);
        }

        $file = get_attached_file($attachmentId);
        $metadata = wp_get_attachment_metadata($attachmentId);

        return new WP_REST_Response([
            'attachment_id' => $attachmentId,
            'title'         => (string) $post->post_title,
            'url'           => wp_get_attachment_url($attachmentId) ?: '',
            'file'          => is_string($file) ? basename($file) : '',
            'path'          => is_string($file) ? $file : '',
            'mime_type'     => (string) get_post_mime_type($attachmentId),
            'filesize'      => (is_string($file) && file_exists($file)) ? (int) filesize($file) : 0,
            'width'         => (int) ($metadata['width'] ?? 0),
            'height'        => (int) ($metadata['height'] ?? 0),
            'generated_sizes' => array_keys((array) ($metadata['sizes'] ?? [])),
            'edit_url'      => get_edit_post_link($attachmentId, 'raw') ?: '',
        ], 200);
    }

    // -----------------------------------------------------------------------
    // User Switcher
    // -----------------------------------------------------------------------

    public function listUsers(WP_REST_Request $request): WP_REST_Response
    {
        $q = trim((string) ($request->get_param('q') ?? ''));

        $args = [
            'number'  => 50,
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'fields'  => ['ID', 'display_name', 'user_email', 'user_login'],
        ];

        if ($q !== '') {
            $args['search']         = '*' . $q . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $users = get_users($args);

        $currentId = get_current_user_id();
        $items     = [];

        foreach ($users as $user) {
            if ((int) $user->ID === $currentId) {
                continue;
            }

            $roles   = (array) (get_userdata((int) $user->ID)->roles ?? []);
            $eligible = current_user_can('manage_options');
            $reason = '';

            if (is_multisite() && !is_user_member_of_blog((int) $user->ID, get_current_blog_id())) {
                $eligible = false;
                $reason = 'Not a member of this site.';
            }

            $items[] = [
                'id'           => (int)    $user->ID,
                'display_name' => (string) $user->display_name,
                'email'        => (string) $user->user_email,
                'login'        => (string) $user->user_login,
                'role'         => $roles[0] ?? 'subscriber',
                'eligible'     => $eligible,
                'ineligible_reason' => $reason,
            ];
        }

        return new WP_REST_Response([
            'rows' => $items,
            'switch_log' => array_values(array_filter((array) get_option(UserSwitcherEnum::OPTION_SWITCH_LOG, []), 'is_array')),
            'multisite' => is_multisite(),
            'settings'  => [
                'retention_days' => max(1, (int) (((array) get_option(UserSwitcherEnum::OPTION_SWITCH_SETTINGS, []))['retention_days'] ?? 30)),
            ],
        ], 200);
    }

    public function getUserSwitchUrl(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('user_id');

        if (!get_userdata($userId)) {
            return new WP_REST_Response(['error' => 'User not found'], 404);
        }

        if (is_multisite() && !is_user_member_of_blog($userId, get_current_blog_id())) {
            return new WP_REST_Response(['error' => 'That user is not a member of this site'], 422);
        }

        // Build a signed admin-post URL that the SPA can navigate to (full page load sets auth cookie)
        $url = add_query_arg(
            [
                'action'   => UserSwitcherEnum::ACTION_SWITCH_TO,
                'user_id'  => $userId,
                '_wpnonce' => wp_create_nonce(UserSwitcherEnum::ACTION_SWITCH_TO),
            ],
            admin_url('admin-post.php')
        );

        return new WP_REST_Response(['url' => $url], 200);
    }

    public function saveUserSwitchSettings(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $retentionDays = max(1, (int) ($params['retention_days'] ?? 30));

        update_option(UserSwitcherEnum::OPTION_SWITCH_SETTINGS, [
            'retention_days' => $retentionDays,
        ], false);

        return new WP_REST_Response(['ok' => true, 'retention_days' => $retentionDays], 200);
    }

    public function exportUserSwitchLog(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'exported_at' => current_time('mysql'),
            'rows'        => array_values(array_filter((array) get_option(UserSwitcherEnum::OPTION_SWITCH_LOG, []), 'is_array')),
        ], 200);
    }

    private function validateRedirectInput(string $source, string $target, bool $regexEnabled, ?int $excludeId = null): ?WP_REST_Response
    {
        if (!$regexEnabled && !str_starts_with($source, '/')) {
            return new WP_REST_Response(['error' => 'Source paths must start with /.'], 422);
        }

        if (!$regexEnabled && parse_url($target, PHP_URL_PATH) === $source) {
            return new WP_REST_Response(['error' => 'Source and target cannot point to the same path.'], 422);
        }

        if ($regexEnabled && @preg_match('#' . $source . '#', '/') === false) {
            return new WP_REST_Response(['error' => 'Invalid regex pattern.'], 422);
        }

        if ($this->redirectsRepo->findDuplicateSource($source, $regexEnabled, $excludeId) !== null) {
            return new WP_REST_Response(['error' => 'A redirect with that source already exists.'], 422);
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function getPostTypeSwitchWarnings(\WP_Post $post, string $newPostType): array
    {
        $warnings = [];
        $sourceObject = get_post_type_object($post->post_type);
        $targetObject = get_post_type_object($newPostType);

        if ($sourceObject && $targetObject) {
            $taxonomies = get_object_taxonomies($post->post_type, 'objects');
            foreach ($taxonomies as $taxonomy) {
                if (!is_object_in_taxonomy($newPostType, $taxonomy->name)) {
                    $warnings[] = sprintf('Destination type does not support taxonomy "%s".', $taxonomy->label);
                }
            }
        }

        return $warnings;
    }

    /**
     * @param string[] $tables
     * @return array{0: string[], 1: ?WP_REST_Response}
     */
    private function normalizeFindReplaceTargets(array $tables, string $scope): array
    {
        $allowedScopes = ['post_content', 'post_title', 'post_excerpt'];
        if (!in_array($scope, $allowedScopes, true)) {
            return [$tables, new WP_REST_Response(['error' => 'Invalid scope'], 400)];
        }

        $allowedTables = ['posts', 'postmeta', 'options'];
        $tables = array_values(array_intersect($allowedTables, $tables));
        if ($tables === []) {
            $tables = ['posts'];
        }

        return [$tables, null];
    }

    /**
     * @param string[] $tables
     * @return array<string, mixed>
     */
    private function buildFindReplacePreview(string $find, string $replace, string $scope, array $tables, bool $regex, bool $caseSensitive): array
    {
        $previews = [];
        $total = 0;

        foreach ($tables as $table) {
            $rows = $this->queryFindReplaceRows($table, $find, $scope);
            foreach ($rows as $row) {
                if (count($previews) >= 50) {
                    return [
                        'total' => $total,
                        'truncated' => true,
                        'previews' => $previews,
                    ];
                }

                $count = 0;
                $after = $this->transformFindReplaceValue($row['field_value'], $find, $replace, $regex, $caseSensitive, $count);
                if ($count <= 0 || $after === $row['field_value']) {
                    continue;
                }

                $total++;
                $previews[] = [
                    'id'            => (int) $row['id'],
                    'title'         => (string) $row['title'],
                    'post_type'     => (string) $row['type'],
                    'match_count'   => $count,
                    'before'        => mb_substr($this->stringifyFindReplaceValue($row['field_value']), 0, 200),
                    'after'         => mb_substr($this->stringifyFindReplaceValue($after), 0, 200),
                    'table'         => $table,
                    'identifier'    => (string) $row['identifier'],
                    'serialised'    => is_array($row['field_value']) || is_object($row['field_value']),
                ];
            }
        }

        return [
            'total' => $total,
            'truncated' => false,
            'previews' => $previews,
        ];
    }

    /**
     * @param string[] $tables
     */
    private function executeFindReplace(string $find, string $replace, string $scope, array $tables, bool $regex, bool $caseSensitive): int
    {
        global $wpdb;

        $updated = 0;

        foreach ($tables as $table) {
            $rows = $this->queryFindReplaceRows($table, $find, $scope, 5000);
            foreach ($rows as $row) {
                $count = 0;
                $newValue = $this->transformFindReplaceValue($row['field_value'], $find, $replace, $regex, $caseSensitive, $count);
                if ($count <= 0 || $newValue === $row['field_value']) {
                    continue;
                }

                $storedValue = $this->encodeFindReplaceValue($newValue, $row['serialised']);

                switch ($table) {
                    case 'posts':
                        $result = $wpdb->update($wpdb->posts, [$scope => $storedValue], ['ID' => (int) $row['id']], ['%s'], ['%d']);
                        break;
                    case 'postmeta':
                        $result = $wpdb->update($wpdb->postmeta, ['meta_value' => $storedValue], ['meta_id' => (int) $row['id']], ['%s'], ['%d']);
                        break;
                    case 'options':
                        $result = $wpdb->update($wpdb->options, ['option_value' => $storedValue], ['option_id' => (int) $row['id']], ['%s'], ['%d']);
                        break;
                    default:
                        $result = false;
                        break;
                }

                if ($result !== false) {
                    $updated++;
                }
            }
        }

        return $updated;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function queryFindReplaceRows(string $table, string $find, string $scope, int $limit = 100): array
    {
        global $wpdb;

        $like = '%' . $wpdb->esc_like($find) . '%';

        switch ($table) {
            case 'postmeta':
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT pm.meta_id AS id, pm.meta_value AS field_value, pm.meta_key AS identifier, p.post_title AS title, p.post_type AS type
                         FROM {$wpdb->postmeta} pm
                         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                         WHERE p.post_status IN ('publish','draft')
                           AND pm.meta_value LIKE %s
                         LIMIT %d",
                        $like,
                        $limit
                    ),
                    ARRAY_A
                );
                break;

            case 'options':
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT option_id AS id, option_value AS field_value, option_name AS identifier, option_name AS title, 'option' AS type
                         FROM {$wpdb->options}
                         WHERE option_value LIKE %s
                         LIMIT %d",
                        $like,
                        $limit
                    ),
                    ARRAY_A
                );
                break;

            case 'posts':
            default:
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT ID AS id, post_title AS title, post_type AS type, {$scope} AS field_value, 'posts.{$scope}' AS identifier
                         FROM {$wpdb->posts}
                         WHERE post_status IN ('publish','draft')
                           AND post_type IN ('post','page')
                           AND {$scope} LIKE %s
                         LIMIT %d",
                        $like,
                        $limit
                    ),
                    ARRAY_A
                );
                break;
        }

        $items = [];
        foreach ((array) $rows as $row) {
            $decoded = maybe_unserialize((string) $row['field_value']);
            $items[] = [
                'id'         => (int) ($row['id'] ?? 0),
                'title'      => (string) ($row['title'] ?? ''),
                'type'       => (string) ($row['type'] ?? ''),
                'identifier' => (string) ($row['identifier'] ?? ''),
                'field_value'=> $decoded,
                'serialised' => is_serialized((string) ($row['field_value'] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function transformFindReplaceValue($value, string $find, string $replace, bool $regex, bool $caseSensitive, int &$count)
    {
        $count = 0;

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $child) {
                $childCount = 0;
                $result[$key] = $this->transformFindReplaceValue($child, $find, $replace, $regex, $caseSensitive, $childCount);
                $count += $childCount;
            }
            return $result;
        }

        if (is_object($value)) {
            $result = clone $value;
            foreach (get_object_vars($value) as $key => $child) {
                $childCount = 0;
                $result->{$key} = $this->transformFindReplaceValue($child, $find, $replace, $regex, $caseSensitive, $childCount);
                $count += $childCount;
            }
            return $result;
        }

        if (!is_string($value)) {
            return $value;
        }

        return $this->applyFindReplace($value, $find, $replace, $regex, $caseSensitive, $count);
    }

    /**
     * @param mixed $value
     */
    private function stringifyFindReplaceValue($value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        $json = wp_json_encode($value);
        return is_string($json) ? $json : '';
    }

    /**
     * @param mixed $value
     */
    private function encodeFindReplaceValue($value, bool $serialised): string
    {
        if ($serialised) {
            return maybe_serialize($value);
        }

        return is_string($value) ? $value : $this->stringifyFindReplaceValue($value);
    }

    private function applyFindReplace(string $subject, string $find, string $replace, bool $regex, bool $caseSensitive, int &$count): string
    {
        $count = 0;

        if ($regex) {
            $pattern = '/' . str_replace('/', '\/', $find) . '/' . ($caseSensitive ? '' : 'i');
            $result = @preg_replace($pattern, $replace, $subject, -1, $count);
            return is_string($result) ? $result : $subject;
        }

        if ($caseSensitive) {
            return str_replace($find, $replace, $subject, $count);
        }

        return str_ireplace($find, $replace, $subject, $count);
    }
}
