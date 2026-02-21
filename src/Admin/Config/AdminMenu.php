<?php

namespace DigitalRoyalty\Beacon\Admin\Config;

use DigitalRoyalty\Beacon\Admin\Screens\AdminScreenInterface;
use DigitalRoyalty\Beacon\Admin\Screens\ScreenRegistry;

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
            // 1. Register top-level (Beacon AI)
            $this->registerTopLevel($screen);

            // 2. Register the first submenu item (General)
            $this->registerParentAsFirstSubmenu($screen);

            // 3. Register other child screens
            foreach ($this->screens->childrenOf($screen->slug()) as $child) {
                $this->registerSubmenu($child);
            }
        }
    }

    private function registerTopLevel(AdminScreenInterface $screen): void
    {
        add_menu_page(
            $screen->pageTitle(),      // Browser title
            'Beacon AI',               // Main sidebar label
            $screen->capability(),
            $screen->slug(),
            [$screen, 'render'],
            $screen->icon(),
            $screen->position()
        );
    }

    private function registerParentAsFirstSubmenu(AdminScreenInterface $screen): void
    {
        add_submenu_page(
            $screen->slug(),           // Parent slug
            $screen->pageTitle(),
            $screen->pageTitle(),                 // Submenu label
            $screen->capability(),
            $screen->slug(),           // Same slug as parent
            [$screen, 'render']
        );
    }

    private function registerSubmenu(AdminScreenInterface $screen): void
    {
        add_submenu_page(
            (string) $screen->parentSlug(),
            $screen->pageTitle(),
            $screen->menuTitle(),
            $screen->capability(),
            $screen->slug(),
            [$screen, 'render']
        );
    }
}
