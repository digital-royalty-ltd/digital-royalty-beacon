<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Api;

final class OAuthProviderEnum
{
    public const GOOGLE_SEARCH_CONSOLE = 'google-search-console';
    public const GOOGLE_ANALYTICS      = 'google-analytics';
    public const GOOGLE_ADS            = 'google-ads';
    public const BING_ADS              = 'bing-ads';
    public const FACEBOOK              = 'facebook';
    public const TWITTER               = 'twitter';
    public const LINKEDIN              = 'linkedin';

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::GOOGLE_SEARCH_CONSOLE,
            self::GOOGLE_ANALYTICS,
            self::GOOGLE_ADS,
            self::BING_ADS,
            self::FACEBOOK,
            self::TWITTER,
            self::LINKEDIN,
        ];
    }

    public static function isValid(string $provider): bool
    {
        return in_array($provider, self::all(), true);
    }

    private function __construct() {}
}
