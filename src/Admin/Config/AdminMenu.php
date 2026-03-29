<?php

namespace DigitalRoyalty\Beacon\Admin\Config;

use DigitalRoyalty\Beacon\Admin\Screens\AdminScreenInterface;
use DigitalRoyalty\Beacon\Admin\Screens\ScreenRegistry;
use DigitalRoyalty\Beacon\Support\Enums\PluginEnum;

final class AdminMenu
{
    public function __construct(private readonly ScreenRegistry $screens) {}

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
    }

    public function addMenu(): void
    {
        foreach ($this->screens->topLevel() as $screen) {
            $this->registerTopLevel($screen);

            // Remove the duplicate submenu WordPress auto-creates to match the top-level item.
            // The plugin is a SPA — navigation happens inside the React app, not via WP submenus.
            remove_submenu_page($screen->slug(), $screen->slug());
        }
    }

    private function registerTopLevel(AdminScreenInterface $screen): void
    {
        add_menu_page(
            $screen->pageTitle(),      // Browser title
            PluginEnum::NAME,          // Main sidebar label
            $screen->capability(),
            $screen->slug(),
            [$screen, 'render'],
            $screen->icon(),
            $screen->position()
        );
    }
}
