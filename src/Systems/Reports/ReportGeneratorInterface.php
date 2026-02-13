<?php

namespace DigitalRoyalty\Beacon\Systems\Reports;

interface ReportGeneratorInterface
{
    public function type(): string;

    public function version(): int;

    /**
     * @return array<string, mixed>
     */
    public function generate(): array;
}
