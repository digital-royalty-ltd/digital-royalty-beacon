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
        $settings = get_option(CustomAdminCssEnum::OPTION_CSS, '');
        $css = is_array($settings)
            ? (string) ($settings['css'] ?? '')
            : (string) $settings;

        if ($css === '') {
            return;
        }

        echo '<style id="dr-beacon-admin-css">' . $css . '</style>' . "\n"; // phpcs:ignore
    }
}
