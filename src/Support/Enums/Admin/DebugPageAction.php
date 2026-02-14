<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class DebugPageAction
{
    public const CLEAR_REPORTS = 'dr_beacon_debug_clear_reports';
    public const RESET_STATUS = 'dr_beacon_debug_reset_status';
    public const FULL_RESET = 'dr_beacon_debug_full_reset';
    public const UNSCHEDULE = 'dr_beacon_debug_unschedule';

    public const CLEAR_LOGS = 'dr_beacon_debug_clear_logs';
    public const CLEAR_SCHEDULER = 'dr_beacon_debug_clear_scheduler';

    private function __construct() {}
}