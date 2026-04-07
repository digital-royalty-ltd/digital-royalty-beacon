<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class WorkshopToggleEnum
{
    public const ACTION_SAVE = 'dr_beacon_workshop_toggle_save';

    // Option keys
    public const SVG_SUPPORT          = 'dr_beacon_svg_support';
    public const SVG_SUPPORT_SETTINGS = 'dr_beacon_svg_support_settings';
    public const SVG_SUPPORT_STATUS   = 'dr_beacon_svg_support_status';

    public const DISABLE_COMMENTS          = 'dr_beacon_disable_comments';
    public const DISABLE_COMMENTS_SETTINGS = 'dr_beacon_disable_comments_settings';

    public const DISABLE_XMLRPC          = 'dr_beacon_disable_xmlrpc';
    public const DISABLE_XMLRPC_SETTINGS = 'dr_beacon_disable_xmlrpc_settings';

    public const DISABLE_FILE_EDITING = 'dr_beacon_disable_file_editing';
    public const DISABLE_FILE_EDITING_SETTINGS = 'dr_beacon_disable_file_editing_settings';

    public const SANITISE_FILENAMES          = 'dr_beacon_sanitise_filenames';
    public const SANITISE_FILENAMES_SETTINGS = 'dr_beacon_sanitise_filenames_settings';

    /** @return string[] */
    public static function allowed(): array
    {
        return [
            self::SVG_SUPPORT,
            self::DISABLE_COMMENTS,
            self::DISABLE_XMLRPC,
            self::DISABLE_FILE_EDITING,
            self::SANITISE_FILENAMES,
        ];
    }
}
