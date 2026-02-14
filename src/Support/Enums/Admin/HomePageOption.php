<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class HomePageOption
{
    public const API_KEY = 'dr_beacon_api_key';
    public const SITE_ID = 'dr_beacon_site_id';
    public const CONNECTED_AT = 'dr_beacon_connected_at';

    private function __construct()
    {
        // Disallow instantiation
    }
}
