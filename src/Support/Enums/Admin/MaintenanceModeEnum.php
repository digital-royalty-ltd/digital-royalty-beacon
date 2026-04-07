<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class MaintenanceModeEnum
{
    public const ACTION_SAVE         = 'dr_beacon_maintenance_mode_save';
    public const OPTION_SETTINGS     = 'dr_beacon_maintenance_mode';
    public const OPTION_BYPASS_TOKEN = 'dr_beacon_maintenance_bypass_token';
    public const BYPASS_QUERY_ARG    = 'beacon_bypass';
    public const PREVIEW_QUERY_ARG   = 'beacon_preview_maintenance';
    public const BYPASS_COOKIE       = 'dr_beacon_maintenance_bypass';

    public const DEFAULT_MESSAGE = 'We\'re currently down for scheduled maintenance. We\'ll be back shortly - thank you for your patience.';
}
