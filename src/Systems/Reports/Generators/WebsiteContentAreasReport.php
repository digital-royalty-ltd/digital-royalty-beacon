<?php

namespace DigitalRoyalty\Beacon\Systems\Reports\Generators;

use DigitalRoyalty\Beacon\Systems\Reports\ReportGeneratorInterface;

final class WebsiteContentAreasReport implements ReportGeneratorInterface
{
    public function type(): string
    {
        return 'website_content_areas';
    }

    public function version(): int
    {
        return 1;
    }

    public function generate(): array
    {
        return [
            'areas' => []
        ];
    }
}
