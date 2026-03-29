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
        // Always: plugin details modal + row meta links regardless of channel.
        add_filter('plugins_api',    [$this, 'pluginDetails'], 10, 3);
        add_filter('plugin_row_meta', [$this, 'rowMeta'],      10, 2);

        // Experimental only: inject update info and fix extracted directory name.
        if (get_option(UpdateChannelEnum::OPTION_CHANNEL, UpdateChannelEnum::STABLE) !== UpdateChannelEnum::EXPERIMENTAL) {
            return;
        }

        add_filter('pre_set_site_transient_update_plugins', [$this, 'injectUpdateInfo']);
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
    /**
     * Add "View details" thickbox link and "Visit plugin site" to the plugin row meta.
     *
     * @param  string[] $links
     * @return string[]
     */
    public function rowMeta(array $links, string $file): array
    {
        if ($file !== $this->pluginBasename) {
            return $links;
        }

        $detailsUrl = add_query_arg([
            'tab'       => 'plugin-information',
            'plugin'    => DR_BEACON_GITHUB_REPO,
            'section'   => 'description',
            'TB_iframe' => 'true',
            'width'     => 600,
            'height'    => 550,
        ], network_admin_url('plugin-install.php'));

        $links[] = '<a href="' . esc_url($detailsUrl) . '" class="thickbox open-plugin-details-modal">'
                 . __('View details')
                 . '</a>';

        return $links;
    }

    public function pluginDetails(mixed $result, string $action, object $args): mixed
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (($args->slug ?? '') !== DR_BEACON_GITHUB_REPO) {
            return $result;
        }

        // Fetch release data — optional. Description always renders; changelog only when available.
        $release = $this->fetchLatestRelease();
        $version = $release !== null
            ? ($this->parseVersion($release['tag_name'] ?? '') ?? DR_BEACON_VERSION)
            : DR_BEACON_VERSION;
        $zipUrl  = $release !== null ? ($this->findZipAsset($release) ?? '') : '';
        $body    = $release !== null ? trim((string) ($release['body'] ?? '')) : '';

        $sections = ['description' => $this->descriptionHtml()];

        if ($release !== null) {
            $sections['changelog'] = $this->changelogHtml($body, $version, $release);
        }

        return (object) [
            'name'             => 'Beacon by Digital Royalty',
            'slug'             => DR_BEACON_GITHUB_REPO,
            'version'          => $version,
            'author'           => '<a href="https://digitalroyalty.co.uk">Digital Royalty</a>',
            'author_profile'   => 'https://digitalroyalty.co.uk',
            'homepage'         => 'https://github.com/' . DR_BEACON_GITHUB_OWNER . '/' . DR_BEACON_GITHUB_REPO,
            'short_description' => 'Connect WordPress to the Digital Royalty platform for AI-driven content intelligence, automation, and publishing.',
            'requires'         => '6.0',
            'requires_php'     => '8.1',
            'tested'           => get_bloginfo('version'),
            'last_updated'     => $release['published_at'] ?? '',
            'download_link'    => $zipUrl,
            'external'         => true,
            'sections'         => $sections,
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
    // Modal content
    // -------------------------------------------------------------------------

    private function descriptionHtml(): string
    {
        return '
<p>Beacon by Digital Royalty connects your WordPress site to the Digital Royalty platform,
enabling AI-driven content intelligence, automation workflows, and publishing tools —
all powered by Digital Royalty\'s proven agency methodology.</p>

<h3>Features</h3>
<ul>
    <li><strong>Site reports</strong> — Analyses your content structure, key pages, and site profile, then submits rich context to Digital Royalty.</li>
    <li><strong>Content generation</strong> — AI-powered drafts guided by agency strategy, written directly into your WordPress posts.</li>
    <li><strong>Automations</strong> — Scheduled workflows that run agency-grade content and SEO processes continuously in the background.</li>
    <li><strong>Campaigns</strong> — Choose a Digital Royalty campaign strategy and let AI execute it across your site.</li>
    <li><strong>Workshop</strong> — Practical site management tools: redirects, 404 monitoring, SMTP, code injection, login branding, and more.</li>
    <li><strong>Public API</strong> — Issue API keys, toggle endpoints, enforce rate limits, and share auto-generated developer documentation.</li>
</ul>

<h3>Requirements</h3>
<ul>
    <li>WordPress 6.0 or higher</li>
    <li>PHP 8.1 or higher</li>
    <li>A Digital Royalty account with a connected API key</li>
</ul>

<h3>Update channel</h3>
<p>You are on the <strong>Experimental</strong> channel. This delivers releases directly from the
<a href="https://github.com/' . DR_BEACON_GITHUB_OWNER . '/' . DR_BEACON_GITHUB_REPO . '" target="_blank">GitHub repository</a>
as soon as they are published. Bugs are expected — please
<a href="https://github.com/' . DR_BEACON_GITHUB_OWNER . '/' . DR_BEACON_GITHUB_REPO . '/issues" target="_blank">open an issue</a>
if you encounter any.</p>';
    }

    /**
     * @param array<string,mixed> $release
     */
    private function changelogHtml(string $body, string $version, array $release): string
    {
        $date      = isset($release['published_at'])
            ? date('j F Y', (int) strtotime((string) $release['published_at']))
            : '';
        $repoUrl   = 'https://github.com/' . DR_BEACON_GITHUB_OWNER . '/' . DR_BEACON_GITHUB_REPO;
        $tag       = esc_attr($release['tag_name'] ?? ('v' . $version));
        $releaseUrl = esc_url($repoUrl . '/releases/tag/' . $tag);

        $heading = '<h3>Version ' . esc_html($version) . ($date ? ' <span style="font-weight:400;color:#6b7280;">— ' . esc_html($date) . '</span>' : '') . '</h3>';
        $link    = '<p style="margin-bottom:12px"><a href="' . $releaseUrl . '" target="_blank">View release on GitHub ↗</a></p>';
        $notes   = $body !== ''
            ? $this->markdownToHtml($body)
            : '<p style="color:#6b7280;">No release notes provided.</p>';

        return $heading . $link . $notes;
    }

    /**
     * Minimal Markdown → HTML converter covering GitHub release note conventions.
     * Handles: ## headings, - / * lists, **bold**, `code`, [text](url), @mentions, #refs.
     */
    private function markdownToHtml(string $markdown): string
    {
        $lines  = explode("\n", str_replace("\r\n", "\n", $markdown));
        $html   = '';
        $inList = false;

        foreach ($lines as $line) {
            $trimmed = rtrim($line);

            // h2
            if (preg_match('/^## (.+)$/', $trimmed, $m)) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<h3>' . $this->inlineMarkdown($m[1]) . '</h3>';
                continue;
            }

            // h3
            if (preg_match('/^### (.+)$/', $trimmed, $m)) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<h4>' . $this->inlineMarkdown($m[1]) . '</h4>';
                continue;
            }

            // list item (- or *)
            if (preg_match('/^[\*\-] (.+)$/', $trimmed, $m)) {
                if (!$inList) { $html .= '<ul>'; $inList = true; }
                $html .= '<li>' . $this->inlineMarkdown($m[1]) . '</li>';
                continue;
            }

            // blank line
            if ($trimmed === '') {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                continue;
            }

            // paragraph
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<p>' . $this->inlineMarkdown($trimmed) . '</p>';
        }

        if ($inList) {
            $html .= '</ul>';
        }

        return $html;
    }

    private function inlineMarkdown(string $text): string
    {
        $repoUrl = 'https://github.com/' . DR_BEACON_GITHUB_OWNER . '/' . DR_BEACON_GITHUB_REPO;

        // Escape HTML first so remaining replacements operate on safe text.
        $text = esc_html($text);

        // [label](url)
        $text = (string) preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            fn ($m) => '<a href="' . esc_url(html_entity_decode($m[2])) . '" target="_blank">' . $m[1] . '</a>',
            $text
        );

        // **bold**
        $text = (string) preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);

        // `code`
        $text = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        // @mention → GitHub profile link
        $text = (string) preg_replace(
            '/@([A-Za-z0-9\-]+)/',
            '<a href="https://github.com/$1" target="_blank">@$1</a>',
            $text
        );

        // #123 → GitHub issue/PR link
        $text = (string) preg_replace(
            '/#(\d+)/',
            '<a href="' . esc_url($repoUrl) . '/issues/$1" target="_blank">#$1</a>',
            $text
        );

        return $text;
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
