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

        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('xmlrpc_methods', '__return_empty_array');
        add_filter('wp_headers', [$this, 'removeXRpcHeader']);
    }

    /** @param array<string, string> $headers */
    public function removeXRpcHeader(array $headers): array
    {
        unset($headers['X-Pingback']);
        return $headers;
    }
}
