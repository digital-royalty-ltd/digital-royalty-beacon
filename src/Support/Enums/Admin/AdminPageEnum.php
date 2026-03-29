<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class AdminPageEnum
{
    public const HOME = 'dr-beacon';
    public const AUTOMATIONS = 'dr-beacon-automations';
    public const CONFIGURATION = 'dr-beacon-configuration';
    public const DEBUG = 'dr-beacon-debug';
    public const WORKSHOP = 'dr-beacon-workshop';
    public const MISSION_CONTROL = 'dr-beacon-mission-control';

    private function __construct() {}
}
