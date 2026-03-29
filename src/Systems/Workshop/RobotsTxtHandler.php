<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\SiteFilesEnum;

final class RobotsTxtHandler
{
    public function register(): void
    {
        add_filter('robots_txt', [$this, 'override'], 999, 1);
    }

    public function override(string $output): string
    {
        $custom = (string) get_option(SiteFilesEnum::OPTION_ROBOTS, '');

        return $custom !== '' ? $custom : $output;
    }
}
