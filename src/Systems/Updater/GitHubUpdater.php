<?php

namespace DigitalRoyalty\Beacon\Systems\Updater;

use DigitalRoyalty\Beacon\Support\Enums\Admin\UpdateChannelEnum;

/**
 * Hooks into WordPress's update system to deliver experimental releases
 * straight from GitHub when the user has opted into the experimental channel.
 *
 * Stable channel: no-op — WordPress.org handles updates as normal.
 * Experimental channel: checks the GitHub Releases API, injects update info
 *   into the WP transient, and serves the plugin details modal.
 *
 * GitHub releases must have:
 *   - A semver tag (e.g. v0.2.0)
 *   - A release asset zip named digital-royalty-beacon.zip
 */
final class GitHubUpdater
{
    private const TRANSIENT_KEY    = 'dr_beacon_github_release';
    private const TRANSIENT_EXPIRY = 6 * HOUR_IN_SECONDS;

    /** Plugin file relative to wp-content/plugins/ — used as the WP update key. */
    private string $pluginBasename;

    public function __construct()
    {
        $this->pluginBasename = plugin_basename(DR_BEACON_FILE);
    }

    public function register(): void
    {
        if (get_option(UpdateChannelEnum::OPTION_CHANNEL, UpdateChannelEnum::STABLE) !== UpdateChannelEnum::EXPERIMENTAL) {
            return;
        }

        add_filter('pre_set_site_transient_update_plugins', [$this, 'injectUpdateInfo']);
        add_filter('plugins_api', [$this, 'pluginDetails'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'fixSourceDir'], 10, 4);
    }

    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    /**
     * Inject GitHub release into WP's update transient when a newer version exists.
     *
     * @param  object $transient
     * @return object
     */
    public function injectUpdateInfo(object $transient): object
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->fetchLatestRelease();

        if ($release === null) {
            return $transient;
        }

        $latestVersion = $this->parseVersion($release['tag_name'] ?? '');

        if ($latestVersion === null) {
            return $transient;
        }

        if (!version_compare($latestVersion, DR_BEACON_VERSION, '>')) {
            return $transient;
        }

        $zipUrl = $this->findZipAsset($release);

        if ($zipUrl === null) {
            return $transient;
        }

        $transient->response[$this->pluginBasename] = (object) [
            'slug'        => DR_BEACON_GITHUB_REPO,
            'plugin'      => $this->pluginBasename,
            'new_version' => $latestVersion,
            'url'         => $release['html_url'] ?? '',
            'package'     => $zipUrl,
            'tested'      => get_bloginfo('version'),
            'requires'    => '6.0',
            'requires_php' => '8.1',
        ];

        return $transient;
    }

    /**
     * Serve the "View version details" modal when WordPress requests our plugin's info.
     *
     * @param  false|object $result
     * @param  string       $action
     * @param  object       $args
     * @return false|object
     */
    public function pluginDetails(mixed $result, string $action, object $args): mixed
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (($args->slug ?? '') !== DR_BEACON_GITHUB_REPO) {
            return $result;
        }

        $release = $this->fetchLatestRelease();

        if ($release === null) {
            return $result;
        }

        $version = $this->parseVersion($release['tag_name'] ?? '') ?? DR_BEACON_VERSION;
        $zipUrl  = $this->findZipAsset($release) ?? '';
        $body    = $release['body'] ?? '';

        return (object) [
            'name'          => 'Beacon by Digital Royalty',
            'slug'          => DR_BEACON_GITHUB_REPO,
            'version'       => $version,
            'author'        => 'Digital Royalty',
            'homepage'      => 'https://github.com/' . DR_BEACON_GITHUB_OWNER . '/' . DR_BEACON_GITHUB_REPO,
            'short_description' => 'Experimental release from GitHub.',
            'sections'      => [
                'description'  => '<p>Experimental channel release. ' . esc_html($body) . '</p>',
                'changelog'    => '<p>' . nl2br(esc_html($body)) . '</p>',
            ],
            'download_link' => $zipUrl,
            'requires'      => '6.0',
            'requires_php'  => '8.1',
            'tested'        => get_bloginfo('version'),
            'last_updated'  => $release['published_at'] ?? '',
            'external'      => true,
        ];
    }

    /**
     * GitHub zips the repo with a folder named {repo}-{tag}, e.g. digital-royalty-beacon-0.2.0.
     * WordPress expects the folder to match the plugin slug. Rename it if needed.
     *
     * @param  string     $source
     * @param  string     $remoteSource
     * @param  object     $upgrader
     * @param  array<string,mixed> $hookExtra
     * @return string
     */
    public function fixSourceDir(string $source, string $remoteSource, object $upgrader, array $hookExtra): string
    {
        if (($hookExtra['plugin'] ?? '') !== $this->pluginBasename) {
            return $source;
        }

        global $wp_filesystem;

        $expectedDir = trailingslashit(dirname($source)) . DR_BEACON_GITHUB_REPO . '/';

        if ($source !== $expectedDir && $wp_filesystem->move($source, $expectedDir)) {
            return $expectedDir;
        }

        return $source;
    }

    // -------------------------------------------------------------------------
    // GitHub API
    // -------------------------------------------------------------------------

    /**
     * Fetch the latest GitHub release, with transient caching.
     *
     * @return array<string,mixed>|null
     */
    private function fetchLatestRelease(): ?array
    {
        $cached = get_transient(self::TRANSIENT_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $url      = sprintf('https://api.github.com/repos/%s/%s/releases/latest', DR_BEACON_GITHUB_OWNER, DR_BEACON_GITHUB_REPO);
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Beacon-Plugin/' . DR_BEACON_VERSION,
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body)) {
            return null;
        }

        set_transient(self::TRANSIENT_KEY, $body, self::TRANSIENT_EXPIRY);

        return $body;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Strip leading 'v' from tag names like v0.2.0 → 0.2.0 */
    private function parseVersion(string $tag): ?string
    {
        $version = ltrim($tag, 'vV');

        return preg_match('/^\d+\.\d+\.\d+/', $version) ? $version : null;
    }

    /**
     * Find the first .zip release asset, falling back to the GitHub-generated source zip.
     *
     * @param array<string,mixed> $release
     */
    private function findZipAsset(array $release): ?string
    {
        foreach ((array) ($release['assets'] ?? []) as $asset) {
            if (is_array($asset) && str_ends_with((string) ($asset['name'] ?? ''), '.zip')) {
                return (string) ($asset['browser_download_url'] ?? '');
            }
        }

        // Fall back to GitHub's auto-generated source archive
        $tag = $release['tag_name'] ?? '';
        if ($tag !== '') {
            return sprintf(
                'https://github.com/%s/%s/archive/refs/tags/%s.zip',
                DR_BEACON_GITHUB_OWNER,
                DR_BEACON_GITHUB_REPO,
                rawurlencode($tag)
            );
        }

        return null;
    }
}
