<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\WorkshopToggleEnum;

final class SanitiseFilenamesHandler
{
    public function register(): void
    {
        if (!get_option(WorkshopToggleEnum::SANITISE_FILENAMES)) {
            return;
        }

        add_filter('sanitize_file_name', [$this, 'sanitise'], 10);
    }

    public function sanitise(string $filename): string
    {
        $info = pathinfo($filename);
        $ext  = isset($info['extension']) ? '.' . strtolower($info['extension']) : '';
        $name = $info['filename'];

        // Lowercase
        $name = strtolower($name);

        // Replace spaces and underscores with hyphens
        $name = preg_replace('/[\s_]+/', '-', $name) ?? $name;

        // Remove any character that is not alphanumeric or a hyphen
        $name = preg_replace('/[^a-z0-9\-]/', '', $name) ?? $name;

        // Collapse multiple hyphens
        $name = preg_replace('/-{2,}/', '-', $name) ?? $name;

        $name = trim($name, '-');

        if ($name === '') {
            $name = 'file';
        }

        return $name . $ext;
    }
}
