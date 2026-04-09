<?php

namespace DigitalRoyalty\Beacon\Systems\Automations\Automations;

use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationTypeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Reports\ReportTypeEnum;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationDependency;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationInterface;

/**
 * Describes the "Create Content From Sample" tool as an automation entry.
 *
 * This automation is interactive — the user provides a URL or pastes content,
 * and Beacon analyses it, extracts a brief, then rewrites it as a fresh draft.
 */
final class ContentFromSampleAutomation implements AutomationInterface
{
    public function key(): string
    {
        return AutomationTypeEnum::CONTENT_FROM_SAMPLE;
    }

    public function label(): string
    {
        return 'Create Content From Sample';
    }

    public function description(): string
    {
        return 'Provide a URL or paste existing content, and Beacon will analyse it, extract the key themes, and produce a fresh rewritten draft as a new post.';
    }

    public function dependencies(): array
    {
        return [
            new AutomationDependency(ReportTypeEnum::WEBSITE_PROFILE),
        ];
    }

    public function deferredKey(): ?string
    {
        return null; // Interactive tool — user triggers it manually.
    }
}
