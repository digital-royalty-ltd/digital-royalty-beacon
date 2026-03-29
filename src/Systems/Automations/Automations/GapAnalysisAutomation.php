<?php

namespace DigitalRoyalty\Beacon\Systems\Automations\Automations;

use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationTypeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Deferred\DeferredRequestKeyEnum;
use DigitalRoyalty\Beacon\Support\Enums\Reports\ReportTypeEnum;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationDependency;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationInterface;

/**
 * Gap Analysis automation.
 *
 * Sends site profile, content areas, and sitemap to the Beacon API.
 * Laravel AI produces two artifact lists:
 *   - Content recommendations: topics to create within existing content areas.
 *   - Area recommendations: entirely new content silos the site should build.
 *
 * The deferred completion handler stores artifact IDs. The plugin fetches
 * the full artifact data from Laravel in real time when displaying results.
 */
final class GapAnalysisAutomation implements AutomationInterface
{
    public function key(): string
    {
        return AutomationTypeEnum::GAP_ANALYSIS;
    }

    public function label(): string
    {
        return 'Content Gap Analysis';
    }

    public function description(): string
    {
        return 'Identifies content opportunities by comparing your site profile and existing content areas against what\'s missing. Returns recommendations for new content within existing silos, and new silos to build.';
    }

    public function dependencies(): array
    {
        return [
            new AutomationDependency(ReportTypeEnum::WEBSITE_PROFILE,       maxAgeDays: 30),
            new AutomationDependency(ReportTypeEnum::WEBSITE_CONTENT_AREAS,  maxAgeDays: 30),
            new AutomationDependency(ReportTypeEnum::WEBSITE_SITEMAP,        maxAgeDays: 7),
        ];
    }

    public function deferredKey(): ?string
    {
        return DeferredRequestKeyEnum::GAP_ANALYSIS;
    }
}
