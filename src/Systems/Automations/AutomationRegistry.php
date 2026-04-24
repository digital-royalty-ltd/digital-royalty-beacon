<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

use DigitalRoyalty\Beacon\Systems\Automations\Automations\ContentFromSampleAutomation;
use DigitalRoyalty\Beacon\Systems\Automations\Automations\ContentGeneratorAutomation;
use DigitalRoyalty\Beacon\Systems\Automations\Automations\ContentImageEnrichmentAutomation;
use DigitalRoyalty\Beacon\Systems\Automations\Automations\GapAnalysisAutomation;
use DigitalRoyalty\Beacon\Systems\Automations\Automations\GenerateImageAutomation;
use DigitalRoyalty\Beacon\Systems\Automations\Automations\NewsArticleGeneratorAutomation;
use DigitalRoyalty\Beacon\Systems\Automations\Automations\SocialShareAutomation;

final class AutomationRegistry
{
    /**
     * @return AutomationInterface[]
     */
    public function all(): array
    {
        return [
            new ContentGeneratorAutomation(),
            new ContentFromSampleAutomation(),
            new ContentImageEnrichmentAutomation(),
            new GapAnalysisAutomation(),
            new GenerateImageAutomation(),
            new NewsArticleGeneratorAutomation(),
            new SocialShareAutomation(),
        ];
    }

    public function find(string $key): ?AutomationInterface
    {
        foreach ($this->all() as $automation) {
            if ($automation->key() === $key) {
                return $automation;
            }
        }

        return null;
    }
}
