<?php

namespace DigitalRoyalty\Beacon\Systems\Reports\Generators;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;

/**
 * Generates the website_voice report.
 *
 * Sends content samples to the Beacon API for AI analysis to derive
 * the site's voice, tone, keywords, and what to avoid.
 * Consumes credits for the AI call.
 */
final class WebsiteVoiceReport implements ReportGeneratorInterface
{
    public function type(): string
    {
        return 'website_voice';
    }

    public function version(): int
    {
        return 1;
    }

    public function generate(): array
    {
        $samples = $this->buildSamples();

        $resp = Services::apiClient()->analyseVoiceTone($samples);

        if ($resp->ok && is_array($resp->data['voice'] ?? null)) {
            Services::logger()->info(LogScopeEnum::REPORTS, 'generator_voice_analysed', 'Voice & tone analysis complete.', [
                'type'    => $this->type(),
                'tokens'  => $resp->data['usage']['total_tokens'] ?? 0,
            ]);

            return $resp->data['voice'];
        }

        Services::logger()->warning(LogScopeEnum::REPORTS, 'generator_voice_failed', 'Voice & tone analysis unavailable.', [
            'type'        => $this->type(),
            'status_code' => $resp->code,
            'message'     => $resp->message,
        ]);

        return [
            'tone'          => [],
            'writing_style' => [],
            'keywords'      => [],
            'avoid'         => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildSamples(): array
    {
        $siteName = (string) get_bloginfo('name');
        $tagline  = (string) get_bloginfo('description');

        // Pull industry/business_type from site profile if available
        $profile = $this->getReportPayload('website_profile');
        $enrichment = is_array($profile['ai_enrichment'] ?? null) ? $profile['ai_enrichment'] : [];

        $industry = (string) ($enrichment['industry'] ?? '');
        $businessType = (string) ($enrichment['business_type'] ?? '');

        // Gather content samples: homepage + recent posts + key pages
        $contentSamples = [];

        // Homepage excerpt
        $showOnFront = (string) get_option('show_on_front', 'posts');
        if ($showOnFront === 'page') {
            $frontId = (int) get_option('page_on_front', 0);
            if ($frontId > 0) {
                $page = get_post($frontId);
                if ($page instanceof \WP_Post) {
                    $contentSamples[] = [
                        'title' => $page->post_title,
                        'text'  => $this->extractText($page, 500),
                    ];
                }
            }
        }

        // Recent published posts
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ]);

        foreach ($posts as $post) {
            $contentSamples[] = [
                'title' => $post->post_title,
                'text'  => $this->extractText($post, 300),
            ];
        }

        // Key pages (about, services, etc.)
        $slugs = ['about', 'about-us', 'services', 'what-we-do', 'our-work'];
        foreach ($slugs as $slug) {
            $page = get_page_by_path($slug);
            if ($page instanceof \WP_Post && $page->post_status === 'publish') {
                $contentSamples[] = [
                    'title' => $page->post_title,
                    'text'  => $this->extractText($page, 300),
                ];
            }
        }

        return [
            'site_name'       => $siteName,
            'tagline'         => $tagline,
            'industry'        => $industry,
            'business_type'   => $businessType,
            'content_samples' => $contentSamples,
        ];
    }

    private function extractText(\WP_Post $post, int $maxChars): string
    {
        // Try Yoast meta description first
        $yoast = (string) get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        if (trim($yoast) !== '') {
            return trim($yoast);
        }

        if (!empty($post->post_excerpt)) {
            return trim(wp_strip_all_tags($post->post_excerpt));
        }

        $content = $post->post_content;
        $content = strip_shortcodes($content);
        $content = (string) preg_replace('/<!--.*?-->/s', '', $content);
        $content = wp_strip_all_tags($content);
        $content = (string) preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        if (strlen($content) > $maxChars) {
            $content = substr($content, 0, $maxChars);
            $lastSpace = strrpos($content, ' ');
            if ($lastSpace !== false) $content = substr($content, 0, $lastSpace);
            $content .= '…';
        }

        return $content;
    }

    /** @return array<string, mixed> */
    private function getReportPayload(string $reportType): array
    {
        global $wpdb;
        $repo = new ReportsRepository($wpdb);
        $row = $repo->getLatestByType($reportType);
        if (!$row) return [];
        $payload = is_string($row['payload'] ?? null) ? json_decode($row['payload'], true) : null;
        return is_array($payload) ? $payload : [];
    }
}
