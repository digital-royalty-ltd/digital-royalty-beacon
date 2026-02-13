<?php

namespace DigitalRoyalty\Beacon\Admin\Config;

use DigitalRoyalty\Beacon\Admin\Pages\DebugPage;
use DigitalRoyalty\Beacon\Admin\Pages\HomePage;

final class AdminMenu
{
    public const PARENT_SLUG = 'dr-beacon';

    public function __construct(
        private readonly HomePage $homePage,
        private readonly DebugPage $debugPage
    ) {}

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
    }

    public function addMenu(): void
    {
        add_menu_page(
            'Beacon AI',
            'Beacon AI',
            'manage_options',
            self::PARENT_SLUG,
            [$this->homePage, 'render'],
            'dashicons-admin-site-alt3',
            58
        );

        add_submenu_page(
            self::PARENT_SLUG,
            'General',
            'General',
            'manage_options',
            self::PARENT_SLUG,
            [$this->homePage, 'render']
        );

        // Optional: Debug submenu
        if (method_exists($this->debugPage, 'isEnabled') ? $this->debugPage->isEnabled() : true) {
            add_submenu_page(
                self::PARENT_SLUG,
                'Debug',
                'Debug',
                'manage_options',
                DebugPage::SLUG,
                [$this->debugPage, 'render']
            );
        }
    }
}
