<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\LoginBrandingEnum;

final class LoginBrandingHandler
{
    public function register(): void
    {
        $settings = (array) get_option(LoginBrandingEnum::OPTION_SETTINGS, []);

        if (empty($settings)) {
            return;
        }

        add_action('login_head', [$this, 'outputStyles']);
        add_filter('login_headerurl', [$this, 'headerUrl']);
        add_filter('login_headertext', [$this, 'headerText']);
    }

    public function outputStyles(): void
    {
        $settings = (array) get_option(LoginBrandingEnum::OPTION_SETTINGS, []);

        $logoUrl  = esc_url((string) ($settings['logo_url'] ?? ''));
        $bgColor  = esc_attr((string) ($settings['bg_color'] ?? ''));
        $bgImage  = esc_url((string) ($settings['bg_image_url'] ?? ''));
        $customCss = wp_strip_all_tags((string) ($settings['custom_css'] ?? ''));

        echo '<style>';

        if ($bgColor !== '') {
            echo 'body.login { background-color: ' . $bgColor . '; }';
        }

        if ($bgImage !== '') {
            echo 'body.login { background-image: url("' . $bgImage . '"); background-size: cover; background-position: center; }';
        }

        if ($logoUrl !== '') {
            echo '#login h1 a, .login h1 a { background-image: url("' . $logoUrl . '"); background-size: contain; width: 100%; height: 80px; }';
        }

        if ($customCss !== '') {
            echo $customCss; // phpcs:ignore
        }

        echo '</style>' . "\n";
    }

    public function headerUrl(): string
    {
        return home_url('/');
    }

    public function headerText(): string
    {
        return get_bloginfo('name');
    }
}
