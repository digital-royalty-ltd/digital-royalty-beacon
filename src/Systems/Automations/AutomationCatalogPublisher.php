<?php

namespace DigitalRoyalty\Beacon\Systems\Automations;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;

/**
 * Serialises the plugin's automation registry into a catalog payload and
 * publishes it to the Beacon API.
 *
 * Stores a local hash so we only hit the API when the catalog actually
 * changes. Called on heartbeat + after plugin activation/updates.
 */
final class AutomationCatalogPublisher
{
    private const LAST_HASH_OPTION = 'dr_beacon_catalog_hash';

    public function __construct(
        private readonly AutomationRegistry $registry
    ) {}

    /**
     * Serialise the registry into the catalog payload shape expected by
     * the Beacon API.
     *
     * @return array{automations: array<int, array<string, mixed>>}
     */
    public function buildCatalog(): array
    {
        $automations = [];

        foreach ($this->registry->all() as $automation) {
            $dependencies = array_map(
                static fn (AutomationDependency $dep): array => [
                    'report_type'  => $dep->reportType,
                    'max_age_days' => $dep->maxAgeDays,
                ],
                $automation->dependencies()
            );

            $automations[] = [
                'key'              => $automation->key(),
                'label'            => $automation->label(),
                'description'      => $automation->description(),
                'categories'       => $automation->categories(),
                'supported_modes'  => $automation->supportedModes(),
                'dependencies'     => $dependencies,
                'deferred_key'     => $automation->deferredKey(),
                'parameter_schema' => $automation->parameterSchema(),
            ];
        }

        // Sort by key for deterministic hashing.
        usort($automations, fn ($a, $b) => strcmp((string) $a['key'], (string) $b['key']));

        return ['automations' => $automations];
    }

    /**
     * Compute a stable hash of the catalog — used to skip unchanged publishes.
     */
    public function catalogHash(array $catalog): string
    {
        return hash('sha256', (string) wp_json_encode($catalog, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Publish the catalog to Beacon API if it has changed.
     *
     * @return array{
     *   published: bool, changed: bool, skipped_reason: ?string,
     *   hash: string, previous_hash: string, count: int,
     *   response_code: ?int, response_body: ?array<string, mixed>,
     *   response_message: ?string,
     *   catalog_keys: array<int, string>
     * }
     */
    public function publishIfChanged(bool $force = false): array
    {
        $catalog = $this->buildCatalog();
        $hash    = $this->catalogHash($catalog);
        $lastHash = (string) get_option(self::LAST_HASH_OPTION, '');
        $count = count($catalog['automations']);
        $keys = array_map(static fn ($a) => (string) ($a['key'] ?? ''), $catalog['automations']);

        $base = [
            'hash'             => $hash,
            'previous_hash'    => $lastHash,
            'count'            => $count,
            'catalog_keys'     => $keys,
            'response_code'    => null,
            'response_body'    => null,
            'response_message' => null,
        ];

        Services::logger()->info(
            LogScopeEnum::SYSTEM,
            'catalog_publish_attempt',
            'Automation catalog publish evaluated.',
            [
                'force'         => $force,
                'local_hash'    => $hash,
                'previous_hash' => $lastHash,
                'count'         => $count,
                'would_publish' => $force || $lastHash !== $hash,
            ]
        );

        if (! $force && $lastHash === $hash) {
            return $base + [
                'published'      => false,
                'changed'        => false,
                'skipped_reason' => 'hash unchanged',
            ];
        }

        $response = Services::apiClient()->publishAutomationCatalog($catalog);

        $base['response_code']    = $response->code;
        $base['response_body']    = is_array($response->data ?? null) ? $response->data : null;
        $base['response_message'] = $response->message;

        if (! $response->ok) {
            Services::logger()->warning(
                LogScopeEnum::SYSTEM,
                'catalog_publish_failed',
                'Automation catalog publish failed.',
                [
                    'status_code' => $response->code,
                    'message'     => $response->message,
                    'body'        => $base['response_body'],
                ]
            );

            return $base + [
                'published'      => false,
                'changed'        => true,
                'skipped_reason' => null,
            ];
        }

        update_option(self::LAST_HASH_OPTION, $hash, false);

        Services::logger()->info(
            LogScopeEnum::SYSTEM,
            'catalog_published',
            'Automation catalog published to Beacon.',
            [
                'hash'  => $hash,
                'count' => $count,
                'keys'  => $keys,
            ]
        );

        return $base + [
            'published'      => true,
            'changed'        => true,
            'skipped_reason' => null,
        ];
    }
}
