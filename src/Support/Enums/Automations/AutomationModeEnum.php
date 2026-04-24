<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Automations;

/**
 * Execution modes an automation can support.
 *
 * - SINGLE:    Run once per user action (form submission).
 * - MULTIPLE:  Apply the same operation to multiple items in one go.
 * - SCHEDULED: Run automatically on a recurring schedule.
 */
final class AutomationModeEnum
{
    public const SINGLE    = 'single';
    public const MULTIPLE  = 'multiple';
    public const SCHEDULED = 'scheduled';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::SINGLE,
            self::MULTIPLE,
            self::SCHEDULED,
        ];
    }
}
