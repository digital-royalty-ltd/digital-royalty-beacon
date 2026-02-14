<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class HomePageAction
{
    public const VERIFY_SAVE = 'dr_beacon_verify_save';
    public const DISCONNECT = 'dr_beacon_disconnect';

    private function __construct()
    {
        // Disallow instantiation
    }
}
