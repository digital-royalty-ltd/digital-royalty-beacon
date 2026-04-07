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

        if (is_multisite() && !is_user_member_of_blog($targetId, get_current_blog_id())) {
            $this->redirect('user-switcher', false, 'That user is not a member of this site.');
            return;
        }

        // Store the original user ID in the target user's meta
        update_user_meta($targetId, UserSwitcherEnum::META_SWITCHED_FROM, $currentId);
        $this->appendLog('switch_to', $currentId, $targetId);

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
        $this->appendLog('switch_back', $currentId, $originalId);

        wp_clear_auth_cookie();
        wp_set_current_user($originalId);
        wp_set_auth_cookie($originalId, false);

        wp_safe_redirect(admin_url('admin.php?page=' . AdminPageEnum::HOME . '#/workshop/user-switcher'));
        exit;
    }

    private function appendLog(string $event, int $actorId, int $targetId): void
    {
        $log = array_values(array_filter((array) get_option(UserSwitcherEnum::OPTION_SWITCH_LOG, []), 'is_array'));
        array_unshift($log, [
            'event'       => $event,
            'actor_id'    => $actorId,
            'target_id'   => $targetId,
            'actor_name'  => get_the_author_meta('display_name', $actorId) ?: '',
            'target_name' => get_the_author_meta('display_name', $targetId) ?: '',
            'created_at'  => current_time('mysql'),
        ]);

        $settings = (array) get_option(UserSwitcherEnum::OPTION_SWITCH_SETTINGS, []);
        $retentionDays = max(1, (int) ($settings['retention_days'] ?? 30));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * DAY_IN_SECONDS));

        $log = array_values(array_filter($log, static function ($entry) use ($cutoff) {
            return (string) ($entry['created_at'] ?? '') >= $cutoff;
        }));

        update_option(UserSwitcherEnum::OPTION_SWITCH_LOG, array_slice($log, 0, 250), false);
    }
}
