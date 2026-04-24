<?php

namespace DigitalRoyalty\Beacon\Systems\Social;

/**
 * Registry of supported social media platforms and their connection status.
 *
 * Connection credentials are stored in WP options keyed by
 * dr_beacon_social_{platform}. Each automation checks connection
 * status before attempting to post. The actual API integrations
 * are pluggable — this class only tracks what is available and configured.
 */
final class SocialPlatformRegistry
{
    public const OPTION_PREFIX = 'dr_beacon_social_';

    /**
     * @return array<int, array{slug: string, label: string, connected: bool}>
     */
    public static function all(): array
    {
        $platforms = self::definitions();
        $result    = [];

        foreach ($platforms as $slug => $label) {
            $result[] = [
                'slug'      => $slug,
                'label'     => $label,
                'connected' => self::isConnected($slug),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{slug: string, label: string}>
     */
    public static function connected(): array
    {
        return array_values(array_filter(self::all(), fn ($p) => $p['connected']));
    }

    public static function isConnected(string $slug): bool
    {
        $config = get_option(self::OPTION_PREFIX . $slug, []);

        if (!is_array($config) || empty($config)) {
            return false;
        }

        // A platform is considered connected if it has a non-empty token or api_key.
        return !empty($config['access_token']) || !empty($config['api_key']);
    }

    /**
     * @return array<string, string>  slug => display label
     */
    public static function definitions(): array
    {
        return [
            'facebook'  => 'Facebook',
            'x'         => 'X (Twitter)',
            'linkedin'  => 'LinkedIn',
            'instagram' => 'Instagram',
        ];
    }
}
