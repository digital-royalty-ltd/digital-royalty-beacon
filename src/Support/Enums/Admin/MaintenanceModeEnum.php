<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class MaintenanceModeEnum
{
    public const ACTION_SAVE     = 'dr_beacon_maintenance_mode_save';
    public const OPTION_SETTINGS = 'dr_beacon_maintenance_mode';

    public const DEFAULT_MESSAGE = 'We\'re currently down for scheduled maintenance. We\'ll be back shortly — thank you for your patience.';
}
