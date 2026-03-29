<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class RedirectsEnum
{
    public const ACTION_CREATE = 'dr_beacon_redirect_create';
    public const ACTION_UPDATE = 'dr_beacon_redirect_update';
    public const ACTION_DELETE = 'dr_beacon_redirect_delete';

    public const PARAM_ID            = 'redirect_id';
    public const PARAM_SOURCE_PATH   = 'source_path';
    public const PARAM_TARGET_URL    = 'target_url';
    public const PARAM_REDIRECT_TYPE = 'redirect_type';
    public const PARAM_IS_ACTIVE     = 'is_active';
}
