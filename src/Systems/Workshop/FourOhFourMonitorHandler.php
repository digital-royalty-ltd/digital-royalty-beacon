<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Repositories\FourOhFourLogsRepository;

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

        $this->repo->record($path, $referrer, $userAgent, $ipHash);
    }
}
