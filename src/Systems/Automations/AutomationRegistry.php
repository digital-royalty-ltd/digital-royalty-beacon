<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

use DigitalRoyalty\Beacon\Systems\Automations\Automations\ContentGeneratorAutomation;
use DigitalRoyalty\Beacon\Systems\Automations\Automations\GapAnalysisAutomation;

final class AutomationRegistry
{
    /**
     * @return AutomationInterface[]
     */
    public function all(): array
    {
        return [
            new ContentGeneratorAutomation(),
            new GapAnalysisAutomation(),
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
