<?php

namespace DigitalRoyalty\Beacon\Systems\Redirects;

use DigitalRoyalty\Beacon\Repositories\RedirectsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;

final class RedirectHandler
{
    public function __construct(
        private readonly RedirectsRepository $repo
    ) {}

    public function register(): void
    {
        add_action('template_redirect', [$this, 'handle']);
    }

    public function handle(): void
    {
        $sourcePath = $this->currentPath();

        $redirect = $this->repo->findBySourcePath($sourcePath);

        if ($redirect === null) {
            return;
        }

        // Failed hit-count updates make redirect analytics drift silently.
        // Catch + debug-log so the operator can spot drift, but never let
        // a metric write fail the redirect itself.
        try {
            $this->repo->incrementHitCount((int) $redirect['id']);
        } catch (\Throwable $e) {
            try {
                Services::logger()->debug(
                    LogScopeEnum::SYSTEM,
                    'redirect_hit_count_failed',
                    "Could not increment hit count for redirect #{$redirect['id']}: {$e->getMessage()}",
                    [
                        'redirect_id' => (int) $redirect['id'],
                        'source_path' => $sourcePath,
                        'exception' => get_class($e),
                        'exception_message' => $e->getMessage(),
                    ]
                );
            } catch (\Throwable) {
                // ignore
            }
        }

        $type = (int) $redirect['redirect_type'];
        $url  = (string) $redirect['target_url'];

        wp_redirect($url, $type);
        exit;
    }

    private function currentPath(): string
    {
        $requestUri = isset($_SERVER['REQUEST_URI'])
            ? (string) $_SERVER['REQUEST_URI']
            : '/';

        // Strip query string, keep only the path component.
        $path = (string) parse_url($requestUri, PHP_URL_PATH);

        // Normalise: ensure leading slash, strip trailing slash (except root).
        $path = '/' . ltrim($path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }
}
