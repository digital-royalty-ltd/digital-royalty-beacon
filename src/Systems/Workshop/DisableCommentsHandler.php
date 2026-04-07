<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\WorkshopToggleEnum;

final class DisableCommentsHandler
{
    public function register(): void
    {
        if (!get_option(WorkshopToggleEnum::DISABLE_COMMENTS)) {
            return;
        }

        add_action('init', [$this, 'closeComments']);
        add_action('admin_menu', [$this, 'removeMenus']);
        add_action('admin_bar_menu', [$this, 'removeAdminBarNode'], 100);
        add_action('admin_init', [$this, 'redirectCommentsPage']);
        add_action('add_meta_boxes', [$this, 'removeDiscussionBoxes'], 99);
        add_filter('comments_open', [$this, 'commentsOpen'], 20, 2);
        add_filter('pings_open', [$this, 'commentsOpen'], 20, 2);
        add_filter('comments_array', [$this, 'commentsArray'], 10, 2);
        add_filter('wp_count_comments', [$this, 'fakeCommentCount']);
        add_filter('rest_endpoints', [$this, 'filterRestEndpoints']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        $settings = (array) get_option(WorkshopToggleEnum::DISABLE_COMMENTS_SETTINGS, []);

        return [
            'mode'       => ($settings['mode'] ?? 'all') === 'selected' ? 'selected' : 'all',
            'post_types' => array_values(array_filter(array_map('sanitize_key', (array) ($settings['post_types'] ?? [])))),
        ];
    }

    public function closeComments(): void
    {
        foreach ($this->targetedPostTypes() as $type) {
            if (post_type_supports($type, 'comments')) {
                remove_post_type_support($type, 'comments');
                remove_post_type_support($type, 'trackbacks');
            }
        }
    }

    public function removeMenus(): void
    {
        remove_menu_page('edit-comments.php');
    }

    public function removeAdminBarNode(\WP_Admin_Bar $adminBar): void
    {
        $adminBar->remove_node('comments');
    }

    public function redirectCommentsPage(): void
    {
        global $pagenow;

        if ($pagenow === 'edit-comments.php' || $pagenow === 'comment.php') {
            wp_safe_redirect(admin_url());
            exit;
        }
    }

    public function removeDiscussionBoxes(string $postType): void
    {
        if (!$this->isTypeDisabled($postType)) {
            return;
        }

        remove_meta_box('commentstatusdiv', $postType, 'normal');
        remove_meta_box('commentsdiv', $postType, 'normal');
        remove_meta_box('trackbacksdiv', $postType, 'normal');
    }

    public function commentsOpen(bool $open, int $postId): bool
    {
        $postType = get_post_type($postId) ?: 'post';
        return $this->isTypeDisabled($postType) ? false : $open;
    }

    /**
     * @param array<int, mixed> $comments
     * @return array<int, mixed>
     */
    public function commentsArray(array $comments, int $postId): array
    {
        $postType = get_post_type($postId) ?: 'post';
        return $this->isTypeDisabled($postType) ? [] : $comments;
    }

    /** @param mixed $count */
    public function fakeCommentCount($count)
    {
        if (self::settings()['mode'] !== 'all') {
            return $count;
        }

        return (object) ['approved' => 0, 'spam' => 0, 'trash' => 0, 'post-trashed' => 0, 'total_comments' => 0, 'all' => 0, 'moderated' => 0];
    }

    /**
     * @param array<string, mixed> $endpoints
     * @return array<string, mixed>
     */
    public function filterRestEndpoints(array $endpoints): array
    {
        unset($endpoints['/wp/v2/comments'], $endpoints['/wp/v2/comments/(?P<id>[\d]+)']);
        return $endpoints;
    }

    private function isTypeDisabled(string $postType): bool
    {
        $settings = self::settings();

        if ($settings['mode'] === 'all') {
            return true;
        }

        return in_array($postType, $settings['post_types'], true);
    }

    /**
     * @return string[]
     */
    private function targetedPostTypes(): array
    {
        $settings = self::settings();

        if ($settings['mode'] === 'all') {
            return get_post_types(['show_ui' => true]);
        }

        return $settings['post_types'];
    }
}
