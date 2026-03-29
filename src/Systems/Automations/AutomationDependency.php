<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

/**
 * Declares a report dependency for an automation, including freshness requirements.
 *
 * $maxAgeDays = null means the report must exist but has no freshness constraint.
 * $maxAgeDays = N means the report's submitted_at must be within N days.
 */
final class AutomationDependency
{
    public function __construct(
        public readonly string $reportType,
        public readonly ?int   $maxAgeDays = null
    ) {}
}
