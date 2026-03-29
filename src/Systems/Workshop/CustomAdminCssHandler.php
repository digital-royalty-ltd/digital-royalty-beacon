<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\CustomAdminCssEnum;

final class CustomAdminCssHandler
{
    public function register(): void
    {
        add_action('admin_head', [$this, 'output'], 999);
    }

    public function output(): void
    {
        $css = (string) get_option(CustomAdminCssEnum::OPTION_CSS, '');

        if ($css === '') {
            return;
        }

        echo '<style id="dr-beacon-admin-css">' . wp_strip_all_tags($css) . '</style>' . "\n"; // phpcs:ignore
    }
}
