<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class DatabaseCleanupEnum
{
    public const ACTION_RUN       = 'dr_beacon_db_cleanup';
    public const OPTION_SETTINGS  = 'dr_beacon_db_cleanup_settings';
    public const CRON_HOOK        = 'dr_beacon_database_cleanup_run';
}
