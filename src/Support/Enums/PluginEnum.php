<?php

namespace DigitalRoyalty\Beacon\Support\Enums;

/**
 * Brand-facing display strings for the plugin.
 *
 * Use these whenever the plugin name appears in UI, page titles, or menu labels.
 * Internal identifiers (option keys, slugs, namespaces) are intentionally separate
 * and must not be changed alongside brand renames.
 */
final class PluginEnum
{
    public const NAME        = 'WP Beacon';
    public const DESCRIPTION = 'Connect WordPress via Beacon to gain: automation, content intelligence, and publishing.';
    public const AUTHOR      = 'Digital Royalty';
    public const AUTHOR_URL  = 'https://digitalroyalty.co.uk';
}
