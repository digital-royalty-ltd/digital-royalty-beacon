<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Support\Enums\Admin\WorkshopToggleEnum;

final class DisableXmlRpcHandler
{
    public function register(): void
    {
        if (!get_option(WorkshopToggleEnum::DISABLE_XMLRPC)) {
            return;
        }

        $mode = self::settings()['mode'];

        if ($mode === 'full') {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('xmlrpc_methods', '__return_empty_array');
        } else {
            add_filter('xmlrpc_methods', [$this, 'removePingbackMethods']);
        }

        add_filter('wp_headers', [$this, 'removeXRpcHeader']);
    }

    /**
     * @return array<string, string>
     */
    public static function settings(): array
    {
        $settings = (array) get_option(WorkshopToggleEnum::DISABLE_XMLRPC_SETTINGS, []);

        return [
            'mode' => ($settings['mode'] ?? 'full') === 'pingback' ? 'pingback' : 'full',
        ];
    }

    /**
     * @param array<string, mixed> $methods
     * @return array<string, mixed>
     */
    public function removePingbackMethods(array $methods): array
    {
        unset(
            $methods['pingback.ping'],
            $methods['pingback.extensions.getPingbacks']
        );

        return $methods;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function removeXRpcHeader(array $headers): array
    {
        unset($headers['X-Pingback']);
        return $headers;
    }
}
