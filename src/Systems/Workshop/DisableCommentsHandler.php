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
        add_action('admin_init', [$this, 'redirectCommentsPage']);
        add_filter('comments_open', '__return_false', 20);
        add_filter('pings_open', '__return_false', 20);
        add_filter('comments_array', '__return_empty_array', 10);
        add_filter('wp_count_comments', [$this, 'fakeCommentCount']);
    }

    public function closeComments(): void
    {
        $types = get_post_types();

        foreach ($types as $type) {
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

    public function redirectCommentsPage(): void
    {
        global $pagenow;

        if ($pagenow === 'edit-comments.php' || $pagenow === 'comment.php') {
            wp_safe_redirect(admin_url());
            exit;
        }
    }

    /** @param mixed $count */
    public function fakeCommentCount($count): object
    {
        return (object) ['approved' => 0, 'spam' => 0, 'trash' => 0, 'post-trashed' => 0, 'total_comments' => 0, 'all' => 0, 'moderated' => 0];
    }
}
