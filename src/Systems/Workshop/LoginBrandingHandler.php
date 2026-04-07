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

        $logoUrl   = esc_url((string) ($settings['logo_url'] ?? ''));
        $bgColor   = esc_attr((string) ($settings['bg_color'] ?? ''));
        $bgImage   = esc_url((string) ($settings['bg_image_url'] ?? ''));
        $customCss = (string) ($settings['custom_css'] ?? '');

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
        $settings = (array) get_option(LoginBrandingEnum::OPTION_SETTINGS, []);
        $url = esc_url_raw((string) ($settings['logo_link_url'] ?? ''));

        return $url !== '' ? $url : home_url('/');
    }

    public function headerText(): string
    {
        $settings = (array) get_option(LoginBrandingEnum::OPTION_SETTINGS, []);
        $text = sanitize_text_field((string) ($settings['logo_alt_text'] ?? ''));

        return $text !== '' ? $text : get_bloginfo('name');
    }
}
