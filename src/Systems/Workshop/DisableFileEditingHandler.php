<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\WorkshopToggleEnum;

final class DisableFileEditingHandler
{
    public function register(): void
    {
        if (!get_option(WorkshopToggleEnum::DISABLE_FILE_EDITING)) {
            return;
        }

        add_action('admin_menu', [$this, 'removeEditorMenus'], 999);
        add_action('admin_init', [$this, 'blockEditorPages']);
    }

    public function removeEditorMenus(): void
    {
        remove_submenu_page('themes.php', 'theme-editor.php');
        remove_submenu_page('plugins.php', 'plugin-editor.php');
    }

    public function blockEditorPages(): void
    {
        global $pagenow;

        if (in_array($pagenow, ['theme-editor.php', 'plugin-editor.php'], true)) {
            wp_die(
                'File editing is disabled by Beacon Workshop.',
                'File Editing Disabled',
                ['response' => 403, 'back_link' => true]
            );
        }
    }
}
