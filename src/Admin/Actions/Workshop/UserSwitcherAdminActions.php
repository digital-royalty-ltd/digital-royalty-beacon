<?php

namespace DigitalRoyalty\Beacon\Admin\Actions\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\UserSwitcherEnum;

final class UserSwitcherAdminActions
{
    public function register(): void
    {
        add_action('admin_post_' . UserSwitcherEnum::ACTION_SWITCH_TO,   [$this, 'handleSwitchTo']);
        add_action('admin_post_' . UserSwitcherEnum::ACTION_SWITCH_BACK, [$this, 'handleSwitchBack']);
    }

    public function handleSwitchTo(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        check_admin_referer(UserSwitcherEnum::ACTION_SWITCH_TO);

        $targetId   = (int) ($_REQUEST['user_id'] ?? 0);
        $currentId  = get_current_user_id();

        if ($targetId <= 0 || $targetId === $currentId) {
            $this->redirect('user-switcher', false, 'Invalid user.');
            return;
        }

        $targetUser = get_user_by('id', $targetId);

        if ($targetUser === false) {
            $this->redirect('user-switcher', false, 'User not found.');
            return;
        }

        // Store the original user ID in the target user's meta
        update_user_meta($targetId, UserSwitcherEnum::META_SWITCHED_FROM, $currentId);

        // Switch session
        wp_clear_auth_cookie();
        wp_set_current_user($targetId);
        wp_set_auth_cookie($targetId, false);

        wp_safe_redirect(admin_url('admin.php?page=' . AdminPageEnum::HOME . '#/workshop/user-switcher'));
        exit;
    }

    public function handleSwitchBack(): void
    {
        if (!is_user_logged_in()) {
            wp_die('Not logged in.', 403);
        }

        check_admin_referer(UserSwitcherEnum::ACTION_SWITCH_BACK);

        $currentId  = get_current_user_id();
        $originalId = (int) get_user_meta($currentId, UserSwitcherEnum::META_SWITCHED_FROM, true);

        if ($originalId <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=' . AdminPageEnum::HOME . '#/workshop/user-switcher'));
            exit;
        }

        $originalUser = get_user_by('id', $originalId);

        if ($originalUser === false) {
            wp_safe_redirect(admin_url('admin.php?page=' . AdminPageEnum::HOME . '#/workshop/user-switcher'));
            exit;
        }

        delete_user_meta($currentId, UserSwitcherEnum::META_SWITCHED_FROM);

        wp_clear_auth_cookie();
        wp_set_current_user($originalId);
        wp_set_auth_cookie($originalId, false);

        wp_safe_redirect(admin_url('admin.php?page=' . AdminPageEnum::HOME . '#/workshop/user-switcher'));
        exit;
    }
}
