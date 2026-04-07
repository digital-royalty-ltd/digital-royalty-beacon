<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class PostExpiryEnum
{
    public const ACTION_SET    = 'dr_beacon_post_expiry_set';
    public const ACTION_REMOVE = 'dr_beacon_post_expiry_remove';
    public const META_KEY      = '_dr_beacon_expire_at';
    public const META_ACTION_KEY = '_dr_beacon_expire_action';
    public const CRON_HOOK     = 'dr_beacon_post_expiry_check';
    public const OPTION_SETTINGS = 'dr_beacon_post_expiry_settings';
}
