<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\CodeInjectionEnum;

final class CodeInjectionHandler
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'outputHead'], 999);
        add_action('wp_footer', [$this, 'outputFooter'], 999);
    }

    public function outputHead(): void
    {
        $snippets = (array) get_option(CodeInjectionEnum::OPTION_SNIPPETS, []);
        $code     = (string) ($snippets['head'] ?? '');

        if ($code !== '') {
            echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }

    public function outputFooter(): void
    {
        $snippets = (array) get_option(CodeInjectionEnum::OPTION_SNIPPETS, []);
        $code     = (string) ($snippets['footer'] ?? '');

        if ($code !== '') {
            echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }
}
