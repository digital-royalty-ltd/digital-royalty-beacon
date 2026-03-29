<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Reports;

final class ReportTypeEnum
{
    public const WEBSITE_PROFILE      = 'website_profile';
    public const WEBSITE_CONTENT_AREAS = 'website_content_areas';
    public const WEBSITE_SITEMAP      = 'website_sitemap';

    /**
     * Human-readable label for display in admin UI and dependency lists.
     */
    public static function label(string $type): string
    {
        return match ($type) {
            self::WEBSITE_PROFILE       => 'Website Profile',
            self::WEBSITE_CONTENT_AREAS => 'Content Areas',
            self::WEBSITE_SITEMAP       => 'Site Map',
            default                     => ucwords(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Number of days after which a submitted report is considered stale.
     * Returns null for report types with no freshness requirement.
     */
    public static function maxAgeDays(string $type): ?int
    {
        return match ($type) {
            self::WEBSITE_PROFILE       => 30,
            self::WEBSITE_CONTENT_AREAS => 14,
            self::WEBSITE_SITEMAP       => 7,
            default                     => null,
        };
    }

    /**
     * Returns all known types mapped to their stale-after days (null = no limit).
     *
     * @return array<string, int|null>
     */
    public static function staleDaysMap(): array
    {
        return [
            self::WEBSITE_PROFILE       => self::maxAgeDays(self::WEBSITE_PROFILE),
            self::WEBSITE_CONTENT_AREAS => self::maxAgeDays(self::WEBSITE_CONTENT_AREAS),
            self::WEBSITE_SITEMAP       => self::maxAgeDays(self::WEBSITE_SITEMAP),
        ];
    }
}
