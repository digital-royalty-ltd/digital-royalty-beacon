<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Admin;

final class WorkshopToggleEnum
{
    public const ACTION_SAVE = 'dr_beacon_workshop_toggle_save';

    // Option keys
    public const SVG_SUPPORT         = 'dr_beacon_svg_support';
    public const DISABLE_COMMENTS    = 'dr_beacon_disable_comments';
    public const DISABLE_XMLRPC      = 'dr_beacon_disable_xmlrpc';
    public const DISABLE_FILE_EDITING = 'dr_beacon_disable_file_editing';
    public const SANITISE_FILENAMES  = 'dr_beacon_sanitise_filenames';

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
