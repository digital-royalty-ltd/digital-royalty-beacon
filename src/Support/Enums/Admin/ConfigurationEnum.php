<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class ConfigurationEnum
{
    public const ACTION_CONNECT    = 'dr_beacon_oauth_connect';
    public const ACTION_DISCONNECT = 'dr_beacon_oauth_disconnect';

    public const OPTION_CONNECTIONS = 'dr_beacon_oauth_connections';
    public const OPTION_STATE       = 'dr_beacon_oauth_state';

    private function __construct() {}
}
