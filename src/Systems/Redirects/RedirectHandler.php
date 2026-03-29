<?php

namespace DigitalRoyalty\Beacon\Systems\Redirects;

use DigitalRoyalty\Beacon\Repositories\RedirectsRepository;

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

        $this->repo->incrementHitCount((int) $redirect['id']);

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
