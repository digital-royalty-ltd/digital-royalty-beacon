<?php

namespace DigitalRoyalty\Beacon\Systems\Automations\Automations;

use DigitalRoyalty\Beacon\Support\Enums\Automations\AutomationTypeEnum;
use DigitalRoyalty\Beacon\Support\Enums\Reports\ReportTypeEnum;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationDependency;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationInterface;

/**
 * Describes the Content Generator tool as an automation entry.
 *
 * This automation has no background deferred key — it is interactive.
 * The admin UI presents a link into the Content Generator tool rather
 * than a "Run" button.
 */
final class ContentGeneratorAutomation implements AutomationInterface
{
    public function key(): string
    {
        return AutomationTypeEnum::CONTENT_GENERATOR;
    }

    public function label(): string
    {
        return 'Content Generator';
    }

    public function description(): string
    {
        return 'Generate AI-drafted content for any content area on your site. Choose a topic and Beacon writes the full draft directly into your posts.';
    }

    public function dependencies(): array
    {
        return [
            new AutomationDependency(ReportTypeEnum::WEBSITE_PROFILE),
            new AutomationDependency(ReportTypeEnum::WEBSITE_CONTENT_AREAS),
        ];
    }

    public function deferredKey(): ?string
    {
        return null; // Interactive tool — no background deferred job.
    }
}
