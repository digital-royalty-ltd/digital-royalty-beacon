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

        if (self::settings()['mode'] === 'mods') {
            add_filter('file_mod_allowed', '__return_false', 10, 2);
            add_filter('automatic_updater_disabled', '__return_true');
            add_filter('map_meta_cap', [$this, 'denyFileModCaps'], 10, 4);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function settings(): array
    {
        $settings = (array) get_option(WorkshopToggleEnum::DISABLE_FILE_EDITING_SETTINGS, []);

        return [
            'mode' => ($settings['mode'] ?? 'editor') === 'mods' ? 'mods' : 'editor',
        ];
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

    /**
     * @param string[] $caps
     * @return string[]
     */
    public function denyFileModCaps(array $caps, string $cap, int $userId, array $args): array
    {
        $blockedCaps = [
            'install_plugins',
            'update_plugins',
            'delete_plugins',
            'install_themes',
            'update_themes',
            'delete_themes',
            'update_core',
        ];

        if (in_array($cap, $blockedCaps, true)) {
            return ['do_not_allow'];
        }

        return $caps;
    }
}
