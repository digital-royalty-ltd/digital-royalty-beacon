<?php

namespace DigitalRoyalty\Beacon\Systems\Reports\Generators;

use DigitalRoyalty\Beacon\Repositories\ReportsRepository;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;

/**
 * Generates the website_imagery report.
 *
 * Sends the visual identity, site profile, and voice data to the Beacon
 * API for AI analysis to derive imagery style preferences: what kind of
 * images suit this brand, what to avoid, typical subjects, and composition.
 * Consumes credits for the AI call.
 */
final class WebsiteImageryReport implements ReportGeneratorInterface
{
    public function type(): string
    {
        return 'website_imagery';
    }

    public function version(): int
    {
        return 1;
    }

    public function generate(): array
    {
        $samples = $this->buildSamples();

        $resp = Services::apiClient()->analyseImageryDirection($samples);

        if ($resp->ok && is_array($resp->data['imagery'] ?? null)) {
            Services::logger()->info(LogScopeEnum::REPORTS, 'generator_imagery_analysed', 'Imagery direction analysis complete.', [
                'type'   => $this->type(),
                'tokens' => $resp->data['usage']['total_tokens'] ?? 0,
            ]);

            return $resp->data['imagery'];
        }

        Services::logger()->warning(LogScopeEnum::REPORTS, 'generator_imagery_failed', 'Imagery direction analysis unavailable.', [
            'type'        => $this->type(),
            'status_code' => $resp->code,
            'message'     => $resp->message,
        ]);

        return [
            'preferred_styles' => [],
            'disliked_styles'  => [],
            'subjects'         => [],
            'composition'      => [],
        ];
    }

    /** @return array<string, mixed> */
    private function buildSamples(): array
    {
        $siteName = (string) get_bloginfo('name');

        // Pull from existing reports
        $profile = $this->getReportPayload('website_profile');
        $visual  = $this->getReportPayload('website_visual');
        $voice   = $this->getReportPayload('website_voice');

        $enrichment = is_array($profile['ai_enrichment'] ?? null) ? $profile['ai_enrichment'] : [];

        return [
            'site_name'     => $siteName,
            'industry'      => (string) ($enrichment['industry'] ?? ''),
            'business_type' => (string) ($enrichment['business_type'] ?? ''),
            'tone'          => is_array($voice['tone'] ?? null) ? $voice['tone'] : [],
            'colors'        => is_array($visual['colors'] ?? null) ? $visual['colors'] : [],
            'fonts'         => is_array($visual['fonts'] ?? null) ? $visual['fonts'] : [],
            'visual_style'  => is_array($visual['style'] ?? null) ? $visual['style'] : [],
            'imagery_style' => is_string($visual['imagery']['style'] ?? null) ? $visual['imagery']['style'] : '',
        ];
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
