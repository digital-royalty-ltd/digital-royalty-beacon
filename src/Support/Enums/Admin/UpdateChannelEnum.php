<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class UpdateChannelEnum
{
    public const STABLE       = 'stable';
    public const EXPERIMENTAL = 'experimental';

    public const OPTION_CHANNEL = 'dr_beacon_update_channel';

    private function __construct() {}
}
