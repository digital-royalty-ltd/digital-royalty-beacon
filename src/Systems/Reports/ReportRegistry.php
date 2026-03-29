<?php

namespace DigitalRoyalty\Beacon\Systems\Reports;

use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;
use DigitalRoyalty\Beacon\Systems\Reports\Generators\WebsiteProfileReport;
use DigitalRoyalty\Beacon\Systems\Reports\Generators\WebsiteContentAreasReport;
use DigitalRoyalty\Beacon\Systems\Reports\Generators\WebsiteSitemapReport;

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
