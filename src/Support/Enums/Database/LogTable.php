<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Database;

final class LogTable
{
    public const TABLE_SLUG = 'dr_beacon_logs';
    public const SCHEMA_VERSION_OPTION = 'dr_beacon_logs_table_schema_version';
    public const SCHEMA_VERSION = 1;

    private function __construct() {}
}
