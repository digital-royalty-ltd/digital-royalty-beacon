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
        ]);
        register_rest_route('beacon/v1', '/admin/workshop/redirects/(?P<id>\d+)', [
            ['methods' => 'PUT',    'callback' => [$this, 'updateRedirect'], 'permission_callback' => $perm],
            ['methods' => 'DELETE', 'callback' => [$this, 'deleteRedirect'], 'permission_callback' => $perm],
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

        // Clone Post
        register_rest_route('beacon/v1', '/admin/workshop/clone-post', [
            'methods'             => 'POST',
            'callback'            => [$this, 'clonePost'],
            'permission_callback' => $perm,
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
    }

    // -----------------------------------------------------------------------
    // Redirects
    // -----------------------------------------------------------------------

    public function listRedirects(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->redirectsRepo->all(), 200);
    }

    public function createRedirect(WP_REST_Request $request): WP_REST_Response
    {
        $source = trim((string) ($request->get_param('source_path') ?? ''));
        $target = trim((string) ($request->get_param('target_url')  ?? ''));
        $type   = (int) ($request->get_param('redirect_type') ?? 301);

        if ($source === '' || $target === '') {
            return new WP_REST_Response(['error' => 'source_path and target_url are required'], 400);
        }

        if (!in_array($type, [301, 302], true)) {
            $type = 301;
        }

        $id  = $this->redirectsRepo->create($source, $target, $type);
        $row = $this->redirectsRepo->find($id);

        return new WP_REST_Response($row, 201);
    }

    public function updateRedirect(WP_REST_Request $request): WP_REST_Response
    {
        $id     = (int) $request->get_param('id');
        $source = trim((string) ($request->get_param('source_path') ?? ''));
        $target = trim((string) ($request->get_param('target_url')  ?? ''));
        $type   = (int) ($request->get_param('redirect_type') ?? 301);
        $active = (bool) ($request->get_param('is_active') ?? true);

        if ($source === '' || $target === '') {
            return new WP_REST_Response(['error' => 'source_path and target_url are required'], 400);
        }

        if (!in_array($type, [301, 302], true)) {
            $type = 301;
        }

        $ok  = $this->redirectsRepo->update($id, $source, $target, $type, $active);
        $row = $this->redirectsRepo->find($id);

        return new WP_REST_Response($row ?? ['error' => 'not found'], $ok ? 200 : 404);
    }

    public function deleteRedirect(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $ok = $this->redirectsRepo->delete($id);

        return new WP_REST_Response(null, $ok ? 204 : 404);
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

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_type, post_status
                 FROM {$wpdb->posts}
                 WHERE post_status IN ('publish','draft')
                   {$typeWhere}
                   AND post_title LIKE %s
                 ORDER BY post_title ASC
                 LIMIT 20",
                $likeQ
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
        $postId      = (int)    ($request->get_param('post_id')   ?? 0);
        $newPostType = (string) ($request->get_param('post_type') ?? '');

        if ($postId === 0 || $newPostType === '') {
            return new WP_REST_Response(['error' => 'post_id and post_type are required'], 400);
        }

        $post = get_post($postId);
        if (!$post) {
            return new WP_REST_Response(['error' => 'Post not found'], 404);
        }

        if (!post_type_exists($newPostType)) {
            return new WP_REST_Response(['error' => 'Invalid post type'], 400);
        }

        $result = wp_update_post(['ID' => $postId, 'post_type' => $newPostType]);

        if (is_wp_error($result)) {
            return new WP_REST_Response(['error' => $result->get_error_message()], 500);
        }

        return new WP_REST_Response([
            'post_id'   => $postId,
            'post_type' => $newPostType,
            'edit_url'  => get_edit_post_link($postId, 'raw') ?: '',
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Clone Post
    // -----------------------------------------------------------------------

    public function clonePost(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) ($request->get_param('post_id') ?? 0);

        if ($postId === 0) {
            return new WP_REST_Response(['error' => 'post_id is required'], 400);
        }

        $post = get_post($postId);
        if (!$post) {
            return new WP_REST_Response(['error' => 'Post not found'], 404);
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
            return new WP_REST_Response(['error' => $newId->get_error_message()], 500);
        }

        // Copy post meta
        $metaRows = get_post_meta($postId);
        foreach ((array) $metaRows as $key => $values) {
            foreach ($values as $value) {
                add_post_meta($newId, $key, maybe_unserialize($value));
            }
        }

        return new WP_REST_Response([
            'new_post_id' => $newId,
            'title'       => $post->post_title . ' (Copy)',
            'edit_url'    => get_edit_post_link($newId, 'raw') ?: '',
        ], 201);
    }

    // -----------------------------------------------------------------------
    // Find & Replace
    // -----------------------------------------------------------------------

    public function findReplacePreview(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $find    = (string) ($request->get_param('find')    ?? '');
        $replace = (string) ($request->get_param('replace') ?? '');
        $scope   = (string) ($request->get_param('scope')   ?? 'post_content');

        if ($find === '') {
            return new WP_REST_Response(['error' => 'find is required'], 400);
        }

        $allowedScopes = ['post_content', 'post_title', 'post_excerpt'];
        if (!in_array($scope, $allowedScopes, true)) {
            return new WP_REST_Response(['error' => 'Invalid scope'], 400);
        }

        $like = '%' . $wpdb->esc_like($find) . '%';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_type, {$scope} AS field_value
                 FROM {$wpdb->posts}
                 WHERE post_status IN ('publish','draft')
                   AND post_type IN ('post','page')
                   AND {$scope} LIKE %s
                 LIMIT 50",
                $like
            ),
            ARRAY_A
        );

        $previews = [];
        foreach ((array) $rows as $row) {
            $original = (string) $row['field_value'];
            $preview  = str_replace($find, $replace, $original);
            $count    = substr_count($original, $find);

            $previews[] = [
                'id'            => (int)    $row['ID'],
                'title'         => (string) $row['post_title'],
                'post_type'     => (string) $row['post_type'],
                'match_count'   => $count,
                'before'        => mb_substr($original, 0, 200),
                'after'         => mb_substr($preview,  0, 200),
            ];
        }

        return new WP_REST_Response([
            'total'    => count($previews),
            'find'     => $find,
            'replace'  => $replace,
            'scope'    => $scope,
            'previews' => $previews,
        ], 200);
    }

    public function findReplaceExecute(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $find    = (string) ($request->get_param('find')    ?? '');
        $replace = (string) ($request->get_param('replace') ?? '');
        $scope   = (string) ($request->get_param('scope')   ?? 'post_content');

        if ($find === '') {
            return new WP_REST_Response(['error' => 'find is required'], 400);
        }

        $allowedScopes = ['post_content', 'post_title', 'post_excerpt'];
        if (!in_array($scope, $allowedScopes, true)) {
            return new WP_REST_Response(['error' => 'Invalid scope'], 400);
        }

        $like    = '%' . $wpdb->esc_like($find) . '%';
        $findEsc = $wpdb->esc_like($find);

        // Use REPLACE() SQL function for atomic update
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->posts}
                 SET {$scope} = REPLACE({$scope}, %s, %s)
                 WHERE post_status IN ('publish','draft')
                   AND post_type IN ('post','page')
                   AND {$scope} LIKE %s",
                $find,
                $replace,
                $like
            )
        );

        return new WP_REST_Response([
            'updated' => (int) $updated,
            'find'    => $find,
            'replace' => $replace,
            'scope'   => $scope,
        ], 200);
    }

    // -----------------------------------------------------------------------
    // Media Replace
    // -----------------------------------------------------------------------

    public function mediaReplace(WP_REST_Request $request): WP_REST_Response
    {
        $attachmentId = (int) ($request->get_param('attachment_id') ?? 0);

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

        if ($oldFile && file_exists($oldFile)) {
            @unlink($oldFile);
        }

        update_attached_file($attachmentId, $uploaded['file']);
        wp_update_attachment_metadata(
            $attachmentId,
            wp_generate_attachment_metadata($attachmentId, $uploaded['file'])
        );

        return new WP_REST_Response([
            'attachment_id' => $attachmentId,
            'url'           => $uploaded['url'],
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
            $items[] = [
                'id'           => (int)    $user->ID,
                'display_name' => (string) $user->display_name,
                'email'        => (string) $user->user_email,
                'login'        => (string) $user->user_login,
                'role'         => $roles[0] ?? 'subscriber',
            ];
        }

        return new WP_REST_Response($items, 200);
    }

    public function getUserSwitchUrl(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('user_id');

        if (!get_userdata($userId)) {
            return new WP_REST_Response(['error' => 'User not found'], 404);
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
}
