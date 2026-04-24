<?php

namespace DigitalRoyalty\Beacon\Systems\Reports;

use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;
use DigitalRoyalty\Beacon\Systems\Reports\Generators\WebsiteContentAreasReport;
use DigitalRoyalty\Beacon\Systems\Reports\Generators\WebsiteImageryReport;
use DigitalRoyalty\Beacon\Systems\Reports\Generators\WebsiteProfileReport;
use DigitalRoyalty\Beacon\Systems\Reports\Generators\WebsiteSitemapReport;
use DigitalRoyalty\Beacon\Systems\Reports\Generators\WebsiteVisualReport;
use DigitalRoyalty\Beacon\Systems\Reports\Generators\WebsiteVoiceReport;

final class ReportRegistry
{
    /**
     * @return ReportGeneratorInterface[]
     */
    public function required(): array
    {
        return [
            new WebsiteProfileReport(),
            new WebsiteContentAreasReport(),
            new WebsiteSitemapReport(),
            new WebsiteVisualReport(),
            new WebsiteVoiceReport(),
            new WebsiteImageryReport(),
        ];
    }

    public function find(string $type, int $version): ?ReportGeneratorInterface
    {
        foreach ($this->required() as $report) {
            if ($report->type() === $type && $report->version() === $version) {
                return $report;
            }
        }

        return null;
    }
}
