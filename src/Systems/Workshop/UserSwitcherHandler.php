<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\AdminPageEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\UserSwitcherEnum;

final class UserSwitcherHandler
{
    public function register(): void
    {
        add_action('admin_bar_menu', [$this, 'addAdminBarNode'], 100);
        add_action('wp_footer',      [$this, 'renderSwitchBackForm']);
        add_action('admin_footer',   [$this, 'renderSwitchBackForm']);
    }

    public function addAdminBarNode(\WP_Admin_Bar $adminBar): void
    {
        if (!is_user_logged_in() || !is_admin_bar_showing()) {
            return;
        }

        $currentId   = get_current_user_id();
        $switchedFrom = (int) get_user_meta($currentId, UserSwitcherEnum::META_SWITCHED_FROM, true);

        if ($switchedFrom > 0) {
            $originalUser = get_user_by('id', $switchedFrom);
            $label        = $originalUser !== false
                ? 'Switch back to ' . $originalUser->display_name
                : 'Switch back';

            $adminBar->add_node([
                'id'     => 'dr-beacon-switch-back',
                'title'  => '&#x21A9; ' . esc_html($label),
                'href'   => '#',
                'meta'   => [
                    'onclick' => 'document.getElementById(\'dr-beacon-switch-back-form\').submit(); return false;',
                    'style'   => 'color:#d63638;font-weight:600;',
                ],
            ]);
        } elseif (current_user_can('manage_options')) {
            $toolUrl = add_query_arg(
                ['page' => AdminPageEnum::WORKSHOP, 'tool' => 'user-switcher'],
                admin_url('admin.php')
            );

            $adminBar->add_node([
                'id'     => 'dr-beacon-user-switcher',
                'title'  => 'Switch User',
                'href'   => esc_url($toolUrl),
                'meta'   => ['title' => 'Switch to another user account'],
            ]);
        }
    }

    public function renderSwitchBackForm(): void
    {
        $currentId   = get_current_user_id();
        $switchedFrom = (int) get_user_meta($currentId, UserSwitcherEnum::META_SWITCHED_FROM, true);

        if ($switchedFrom <= 0 || !is_admin_bar_showing()) {
            return;
        }
        ?>
        <form id="dr-beacon-switch-back-form" method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" style="display:none;">
            <input type="hidden" name="action" value="<?= esc_attr(UserSwitcherEnum::ACTION_SWITCH_BACK) ?>">
            <?= wp_nonce_field(UserSwitcherEnum::ACTION_SWITCH_BACK, '_wpnonce', true, false) ?>
        </form>
        <?php
    }
}
