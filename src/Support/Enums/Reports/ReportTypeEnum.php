<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Reports;

final class ReportTypeEnum
{
    public const WEBSITE_PROFILE      = 'website_profile';
    public const WEBSITE_CONTENT_AREAS = 'website_content_areas';
    public const WEBSITE_SITEMAP      = 'website_sitemap';
    public const WEBSITE_VISUAL       = 'website_visual';
    public const WEBSITE_VOICE        = 'website_voice';
    public const WEBSITE_IMAGERY      = 'website_imagery';

    public static function label(string $type): string
    {
        return match ($type) {
            self::WEBSITE_PROFILE       => 'Website Profile',
            self::WEBSITE_CONTENT_AREAS => 'Content Areas',
            self::WEBSITE_SITEMAP       => 'Site Map',
            self::WEBSITE_VISUAL        => 'Visual Identity',
            self::WEBSITE_VOICE         => 'Voice & Tone',
            self::WEBSITE_IMAGERY       => 'Imagery Direction',
            default                     => ucwords(str_replace('_', ' ', $type)),
        };
    }

    public static function maxAgeDays(string $type): ?int
    {
        return match ($type) {
            self::WEBSITE_PROFILE       => 30,
            self::WEBSITE_CONTENT_AREAS => 14,
            self::WEBSITE_SITEMAP       => 7,
            self::WEBSITE_VISUAL        => 30,
            self::WEBSITE_VOICE         => 30,
            self::WEBSITE_IMAGERY       => 30,
            default                     => null,
        };
    }

    /** @return array<string, int|null> */
    public static function staleDaysMap(): array
    {
        return [
            self::WEBSITE_PROFILE       => self::maxAgeDays(self::WEBSITE_PROFILE),
            self::WEBSITE_CONTENT_AREAS => self::maxAgeDays(self::WEBSITE_CONTENT_AREAS),
            self::WEBSITE_SITEMAP       => self::maxAgeDays(self::WEBSITE_SITEMAP),
            self::WEBSITE_VISUAL        => self::maxAgeDays(self::WEBSITE_VISUAL),
            self::WEBSITE_VOICE         => self::maxAgeDays(self::WEBSITE_VOICE),
            self::WEBSITE_IMAGERY       => self::maxAgeDays(self::WEBSITE_IMAGERY),
        ];
    }
}
