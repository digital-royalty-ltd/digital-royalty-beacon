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

        $path     = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $referrer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : null;

        // Truncate to fit column
        $path     = mb_substr($path, 0, 2048);
        $referrer = $referrer !== null ? mb_substr($referrer, 0, 2048) : null;

        $this->repo->record($path, $referrer);
    }
}
