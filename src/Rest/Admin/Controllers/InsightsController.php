<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Insights hub backend.
 *
 * GET  /admin/insights/registry          — returns the catalogue of signals/actions
 *                                          the Insights React page should render as tiles.
 * POST /admin/insights/signal            — proxies a signal lookup to Laravel.
 *
 * The registry is hardcoded here for now (small set, easier to ship). When
 * Laravel exposes a `/beacon/v1/registry` endpoint we can swap to fetching it.
 */
final class InsightsController
{
    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/admin/insights/registry', [
            'methods' => 'GET',
            'callback' => [$this, 'registry'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);

        register_rest_route('beacon/v1', '/admin/insights/signal', [
            'methods' => 'POST',
            'callback' => [$this, 'callSignal'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);
    }

    public function registry(WP_REST_Request $request): WP_REST_Response
    {
        // Try fetching the live registry from Laravel first so newly-shipped
        // signals appear in the hub without a plugin redeploy. Fall back to
        // the local catalogue if the API is unreachable.
        try {
            $res = Services::apiClient()->getSignalsRegistry();
            if ($res->isOk() && is_array($res->data['signals'] ?? null)) {
                return new WP_REST_Response(['signals' => $res->data['signals'], 'source' => 'laravel'], 200);
            }
        } catch (\Throwable $e) {
            // fall through to fallback
        }

        return new WP_REST_Response(['signals' => $this->signalCatalogue(), 'source' => 'fallback'], 200);
    }

    public function callSignal(WP_REST_Request $request): WP_REST_Response
    {
        $provider = is_string($request->get_param('provider')) ? $request->get_param('provider') : '';
        $operation = is_string($request->get_param('operation')) ? $request->get_param('operation') : '';
        $args = is_array($request->get_param('args')) ? $request->get_param('args') : [];
        $options = is_array($request->get_param('options')) ? $request->get_param('options') : [];

        if ($provider === '' || $operation === '') {
            return new WP_REST_Response(['message' => 'provider and operation are required.'], 422);
        }

        $res = Services::apiClient()->callSignal($provider, $operation, $args, $options);

        if (! $res->isOk()) {
            return new WP_REST_Response([
                'message' => $res->message ?? 'Signal call failed.',
                'data' => is_array($res->data) ? $res->data : null,
            ], $res->code ?: 502);
        }

        return new WP_REST_Response(is_array($res->data) ? $res->data : [], 200);
    }

    /**
     * Static catalogue of operations the React hub renders. Each entry maps
     * to a tile and declares its input fields; the page renders inputs from
     * this without any custom logic per tile.
     *
     * @return list<array<string, mixed>>
     */
    private function signalCatalogue(): array
    {
        return [
            [
                'slug' => 'dataforseo.backlinks.summary',
                'provider' => 'dataforseo',
                'operation' => 'backlinks.summary',
                'label' => 'Backlink Summary',
                'description' => 'Total backlinks, referring domains, broken links, anchor and country distributions for any domain or URL.',
                'discipline' => 'SEO',
                'auth' => 'central',
                'cost_credits' => 5,
                'cache_ttl_label' => '7 days',
                'inputs' => [
                    ['key' => 'target', 'label' => 'Target domain or URL', 'type' => 'text', 'required' => true, 'placeholder' => 'example.com'],
                ],
            ],
            [
                'slug' => 'dataforseo.keywords.suggestions',
                'provider' => 'dataforseo',
                'operation' => 'keywords.suggestions',
                'label' => 'Keyword Suggestions',
                'description' => 'Search-volume-ranked keyword ideas for a seed term, with competition and CPC.',
                'discipline' => 'Content',
                'auth' => 'central',
                'cost_credits' => 3,
                'cache_ttl_label' => '30 days',
                'inputs' => [
                    ['key' => 'keyword', 'label' => 'Seed keyword', 'type' => 'text', 'required' => true, 'placeholder' => 'wordpress seo'],
                    ['key' => 'location_code', 'label' => 'Location code', 'type' => 'text', 'required' => false, 'placeholder' => '2826 (UK)'],
                ],
            ],
            [
                'slug' => 'dataforseo.serp.organic',
                'provider' => 'dataforseo',
                'operation' => 'serp.organic',
                'label' => 'SERP Snapshot',
                'description' => 'Top organic results for a query — useful for spotting who you\'re competing against.',
                'discipline' => 'SEO',
                'auth' => 'central',
                'cost_credits' => 4,
                'cache_ttl_label' => '24 hours',
                'inputs' => [
                    ['key' => 'keyword', 'label' => 'Keyword', 'type' => 'text', 'required' => true, 'placeholder' => 'best wordpress seo plugin'],
                ],
            ],
            [
                'slug' => 'gsc.queries.top',
                'provider' => 'gsc',
                'operation' => 'queries.top',
                'label' => 'Top Search Queries (GSC)',
                'description' => 'Your highest-impression queries with clicks, CTR, and average position. Requires Google Search Console connected.',
                'discipline' => 'Content',
                'auth' => 'oauth_per_project',
                'auth_provider' => 'google-search-console',
                'cost_credits' => 0,
                'cache_ttl_label' => '6 hours',
                'inputs' => [
                    ['key' => 'days', 'label' => 'Window (days)', 'type' => 'number', 'required' => false, 'placeholder' => '28'],
                ],
            ],
            [
                'slug' => 'gsc.pages.top',
                'provider' => 'gsc',
                'operation' => 'pages.top',
                'label' => 'Top Pages (GSC)',
                'description' => 'Your best-performing pages by clicks and impressions. Requires Google Search Console connected.',
                'discipline' => 'Content',
                'auth' => 'oauth_per_project',
                'auth_provider' => 'google-search-console',
                'cost_credits' => 0,
                'cache_ttl_label' => '6 hours',
                'inputs' => [
                    ['key' => 'days', 'label' => 'Window (days)', 'type' => 'number', 'required' => false, 'placeholder' => '28'],
                ],
            ],
        ];
    }
}
