<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Automations;

/**
 * Marketing categories an automation can belong to.
 *
 * An automation may belong to more than one category.
 */
final class AutomationCategoryEnum
{
    public const CONTENT = 'content';
    public const SEO     = 'seo';
    public const PPC     = 'ppc';
    public const SOCIAL  = 'social';

    /**
     * @return array<string, string>  slug => display label
     */
    public static function labels(): array
    {
        return [
            self::CONTENT => 'Content',
            self::SEO     => 'SEO',
            self::PPC     => 'PPC',
            self::SOCIAL  => 'Social',
        ];
    }
}
