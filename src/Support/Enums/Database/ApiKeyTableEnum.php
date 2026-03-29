<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Database;

final class ApiKeyTableEnum
{
    public const TABLE_SLUG           = 'dr_beacon_api_keys';
    public const SCHEMA_VERSION_OPTION = 'dr_beacon_api_keys_table_schema_version';
    public const SCHEMA_VERSION        = 2;

    private function __construct() {}
}
