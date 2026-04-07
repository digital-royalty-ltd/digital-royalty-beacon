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
        add_filter('wp_handle_upload_prefilter', [$this, 'validateUpload']);
        add_filter('wp_handle_upload', [$this, 'sanitiseSvg']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'allowed_roles'    => ['administrator'],
            'inline_rendering' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        $settings = (array) get_option(WorkshopToggleEnum::SVG_SUPPORT_SETTINGS, []);

        return [
            'allowed_roles'    => self::sanitizeRoles((array) ($settings['allowed_roles'] ?? ['administrator'])),
            'inline_rendering' => !empty($settings['inline_rendering']),
        ];
    }

    /**
     * @param array<string, string> $mimes
     * @return array<string, string>
     */
    public function addSvgMime(array $mimes): array
    {
        if (!$this->canCurrentUserUploadSvg()) {
            return $mimes;
        }

        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|false $checkData
     * @return array<string, mixed>
     */
    public function fixSvgFiletype(array $data, $checkData, string $file, string $filename): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (($ext === 'svg' || $ext === 'svgz') && $this->canCurrentUserUploadSvg()) {
            $data['ext']  = $ext;
            $data['type'] = 'image/svg+xml';
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    public function validateUpload(array $file): array
    {
        $name = (string) ($file['name'] ?? '');
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($ext !== 'svg' && $ext !== 'svgz') {
            return $file;
        }

        if (!$this->canCurrentUserUploadSvg()) {
            $file['error'] = __('Your role is not allowed to upload SVG files.', 'digital-royalty-beacon');
            $this->storeStatus('warning', 'Blocked an SVG upload because the current user role is not permitted.');
            return $file;
        }

        return $file;
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

        $file    = $upload['file'] ?? '';
        $content = is_string($file) ? file_get_contents($file) : false;

        if ($content === false) {
            $upload['error'] = __('Beacon could not read the uploaded SVG to sanitise it.', 'digital-royalty-beacon');
            $this->storeStatus('error', 'Beacon could not read the uploaded SVG to sanitise it.');
            return $upload;
        }

        $sanitised = $this->sanitise($content);

        if (stripos($sanitised, '<svg') === false) {
            $upload['error'] = __('SVG sanitisation removed the required markup, so the upload was rejected.', 'digital-royalty-beacon');
            $this->storeStatus('error', 'Beacon rejected an SVG because sanitisation removed the required markup.');
            return $upload;
        }

        file_put_contents($file, $sanitised);
        $this->storeStatus('protected', 'Last SVG upload was sanitised successfully.');

        return $upload;
    }

    private function sanitise(string $svg): string
    {
        $svg = preg_replace('/<\?php.*?\?>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<script[\s\S]*?<\/script>/i', '', $svg) ?? $svg;
        $svg = preg_replace('/<foreignObject[\s\S]*?<\/foreignObject>/i', '', $svg) ?? $svg;
        $svg = preg_replace('/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $svg) ?? $svg;
        $svg = preg_replace('/(href|xlink:href)\s*=\s*(["\'])\s*(?:javascript:|data:)[^"\']*\2/i', '', $svg) ?? $svg;
        $svg = preg_replace('/url\((["\'])?(?:https?:)?\/\/.*?\1\)/i', 'none', $svg) ?? $svg;

        return $svg;
    }

    private function canCurrentUserUploadSvg(): bool
    {
        $allowedRoles = self::settings()['allowed_roles'];

        if ($allowedRoles === []) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $user = wp_get_current_user();

        foreach ((array) $user->roles as $role) {
            if (in_array($role, $allowedRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $roles
     * @return string[]
     */
    private static function sanitizeRoles(array $roles): array
    {
        return array_values(array_filter(array_map('sanitize_key', $roles)));
    }

    private function storeStatus(string $state, string $message): void
    {
        update_option(WorkshopToggleEnum::SVG_SUPPORT_STATUS, [
            'state'        => $state,
            'message'      => $message,
            'updated_at'   => current_time('mysql'),
            'sanitiser'    => 'beacon-regex-sanitiser',
            'bypass_active' => false,
        ], false);
    }
}
