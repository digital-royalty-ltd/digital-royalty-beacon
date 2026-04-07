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

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        $settings = (array) get_option(WorkshopToggleEnum::SANITISE_FILENAMES_SETTINGS, []);

        return [
            'lowercase'    => !array_key_exists('lowercase', $settings) || !empty($settings['lowercase']),
            'transliterate' => !array_key_exists('transliterate', $settings) || !empty($settings['transliterate']),
            'separator'    => ($settings['separator'] ?? 'hyphen') === 'underscore' ? 'underscore' : 'hyphen',
        ];
    }

    public function sanitise(string $filename): string
    {
        return self::preview($filename, self::settings());
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function preview(string $filename, array $settings): string
    {
        $info      = pathinfo($filename);
        $extension = isset($info['extension']) ? '.' . strtolower((string) $info['extension']) : '';
        $name      = (string) ($info['filename'] ?? $filename);
        $separator = ($settings['separator'] ?? 'hyphen') === 'underscore' ? '_' : '-';

        if (!empty($settings['transliterate'])) {
            $name = remove_accents($name);
        }

        if (!empty($settings['lowercase'])) {
            $name = strtolower($name);
        }

        $name = preg_replace('/[\s_]+/', $separator, $name) ?? $name;
        $name = preg_replace('/[^A-Za-z0-9\-_.]/', '', $name) ?? $name;
        $name = preg_replace('/[' . preg_quote($separator, '/') . ']{2,}/', $separator, $name) ?? $name;
        $name = trim($name, '-_.');

        if ($name === '') {
            $name = 'file';
        }

        return $name . $extension;
    }
}
