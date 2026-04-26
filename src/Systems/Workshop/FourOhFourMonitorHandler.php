<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Repositories\FourOhFourLogsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;

final class FourOhFourMonitorHandler
{
    public function __construct(
        private readonly FourOhFourLogsRepository $repo
    ) {}

    public function register(): void
    {
        add_action('template_redirect', [$this, 'handle'], 999);
    }

    public function handle(): void
    {
        if (!is_404()) {
            return;
        }

        $excluded = array_values(array_filter(array_map('trim', (array) get_option('dr_beacon_404_exclusions', []))));

        $path     = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $referrer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

        foreach ($excluded as $pattern) {
            if ($pattern !== '' && stripos($path, $pattern) !== false) {
                return;
            }
        }

        // Truncate to fit column
        $path     = mb_substr($path, 0, 2048);
        $referrer = $referrer !== null ? mb_substr($referrer, 0, 2048) : null;
        $userAgent = $userAgent !== null ? mb_substr($userAgent, 0, 1024) : null;
        $ipHash = $ip !== '' ? wp_hash($ip) : null;

        try {
            $this->repo->record($path, $referrer, $userAgent, $ipHash);
        } catch (\Throwable $e) {
            // 404 telemetry is silently lost if the insert fails — log so a
            // suddenly-empty 404 report has a trail leading to schema/charset
            // issues. Never re-throw: a logging hiccup must not break the
            // 404 page itself.
            try {
                Services::logger()->warning(
                    LogScopeEnum::SYSTEM,
                    '404_record_failed',
                    "Failed to record 404 hit for path '{$path}': {$e->getMessage()}",
                    [
                        'path' => $path,
                        'exception' => get_class($e),
                        'exception_message' => $e->getMessage(),
                    ]
                );
            } catch (\Throwable) {
                // ignore
            }
        }
    }
}
