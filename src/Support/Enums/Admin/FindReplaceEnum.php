<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class FindReplaceEnum
{
    public const ACTION_PREVIEW = 'dr_beacon_find_replace_preview';
    public const ACTION_REPLACE = 'dr_beacon_find_replace_replace';

    public const PARAM_FIND           = 'fr_find';
    public const PARAM_REPLACE        = 'fr_replace';
    public const PARAM_POST_TYPES     = 'fr_post_types';
    public const PARAM_SCOPE          = 'fr_scope';
    public const PARAM_CASE_SENSITIVE = 'fr_case_sensitive';
    public const PARAM_PREVIEW_KEY    = 'fr_preview_key';

    public const TRANSIENT_PREFIX = 'dr_beacon_fr_';
    public const TRANSIENT_TTL    = 120; // seconds
}
