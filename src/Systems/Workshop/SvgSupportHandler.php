<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\WorkshopToggleEnum;

final class SvgSupportHandler
{
    public function register(): void
    {
        if (!get_option(WorkshopToggleEnum::SVG_SUPPORT)) {
            return;
        }

        add_filter('upload_mimes', [$this, 'addSvgMime']);
        add_filter('wp_check_filetype_and_ext', [$this, 'fixSvgFiletype'], 10, 4);
        add_filter('wp_handle_upload', [$this, 'sanitiseSvg']);
    }

    /** @param array<string, string> $mimes */
    public function addSvgMime(array $mimes): array
    {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|false $checkData unused
     * @return array<string, mixed>
     */
    public function fixSvgFiletype(array $data, $checkData, string $file, string $filename): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($ext === 'svg' || $ext === 'svgz') {
            $data['ext']  = $ext;
            $data['type'] = 'image/svg+xml';
        }

        return $data;
    }

    /**
     * @param array<string, string> $upload
     * @return array<string, string>
     */
    public function sanitiseSvg(array $upload): array
    {
        if (!isset($upload['type']) || $upload['type'] !== 'image/svg+xml') {
            return $upload;
        }

        $file    = $upload['file'];
        $content = file_get_contents($file);

        if ($content === false) {
            return $upload;
        }

        $content = $this->sanitise($content);

        file_put_contents($file, $content);

        return $upload;
    }

    private function sanitise(string $svg): string
    {
        // Strip PHP tags
        $svg = preg_replace('/<\?php.*?\?>/is', '', $svg) ?? $svg;

        // Remove <script> blocks
        $svg = preg_replace('/<script[\s\S]*?<\/script>/i', '', $svg) ?? $svg;

        // Remove on* event attributes
        $svg = preg_replace('/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $svg) ?? $svg;

        // Remove javascript: href/xlink:href values
        $svg = preg_replace('/(href|xlink:href)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '', $svg) ?? $svg;

        return $svg;
    }
}
