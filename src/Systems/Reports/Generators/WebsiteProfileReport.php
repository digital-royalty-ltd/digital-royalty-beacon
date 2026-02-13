<?php

namespace DigitalRoyalty\Beacon\Systems\Reports\Generators;

use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;

final class WebsiteProfileReport implements ReportGeneratorInterface
{
    public function type(): string
    {
        return 'website_profile';
    }

    public function version(): int
    {
        return 1;
    }

    public function generate(): array
    {
        return [
            'site' => [
                'name' => (string) get_bloginfo('name'),
                'description' => (string) get_bloginfo('description'),
                'url' => (string) home_url('/'),
            ],
            'environment' => [
                'wp_version' => (string) get_bloginfo('version'),
                'php_version' => PHP_VERSION,
            ],
        ];
    }
}
