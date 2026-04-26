<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;
use DigitalRoyalty\Beacon\Systems\Actions\ActionInvokerRegistry;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationRegistry;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /admin/marketing/channels/{channel}/capabilities
 *
 * Returns the full capability bundle for an agent on a given channel:
 *   - Laravel-side: signals, actions, synthesis tools, watchers
 *     (fetched from /beacon/v1/marketing-channels/{channel}/capabilities)
 *   - Plugin-side:  automations the agent can request_automation against,
 *                   and the WP action invokers the agent can dispatch
 *
 * The Capabilities modal in the React admin uses this to show operators
 * exactly what their hired agent can do — read-only transparency, never
 * something the operator runs themselves.
 */
final class CapabilitiesController
{
    private const VALID_CHANNELS = ['content', 'seo', 'ppc', 'social'];

    public function registerRoutes(): void
    {
        register_rest_route('beacon/v1', '/admin/marketing/channels/(?P<channel>[a-z]+)/capabilities', [
            'methods' => 'GET',
            'callback' => [$this, 'handle'],
            'permission_callback' => fn () => current_user_can('manage_options'),
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');

        if (! in_array($channel, self::VALID_CHANNELS, true)) {
            return new WP_REST_Response(['message' => 'Invalid channel.'], 422);
        }

        $upstream = Services::apiClient()->getMarketingChannelCapabilities($channel);

        if (! $upstream->ok) {
            // Don't 502 the SPA on a Laravel hiccup — return the plugin-side
            // capabilities and an `upstream_unavailable` flag so the modal
            // can still show *something* useful (the WP-local bits) and warn
            // the operator the rest are missing.
            Services::logger()->warning(
                LogScopeEnum::API,
                'capabilities_upstream_failed',
                "Capabilities fetch from Laravel failed for channel '{$channel}': {$upstream->message}",
                [
                    'channel' => $channel,
                    'response_code' => $upstream->code,
                    'response_message' => $upstream->message,
                ]
            );

            return new WP_REST_Response([
                'channel' => $channel,
                'label' => $this->channelLabel($channel),
                'signals' => [],
                'actions' => [],
                'synthesis_tools' => [],
                'watchers' => [],
                'automations' => $this->localAutomations($channel),
                'wp_actions' => $this->wpActions($channel),
                'upstream_unavailable' => true,
                'upstream_message' => $upstream->message ?? 'Could not reach Beacon API.',
            ], 200);
        }

        $data = is_array($upstream->data) ? $upstream->data : [];

        return new WP_REST_Response([
            'channel' => $channel,
            'label' => is_string($data['label'] ?? null) ? $data['label'] : $this->channelLabel($channel),
            'signals' => is_array($data['signals'] ?? null) ? $data['signals'] : [],
            'actions' => is_array($data['actions'] ?? null) ? $data['actions'] : [],
            'synthesis_tools' => is_array($data['synthesis_tools'] ?? null) ? $data['synthesis_tools'] : [],
            'watchers' => is_array($data['watchers'] ?? null) ? $data['watchers'] : [],
            'automations' => $this->localAutomations($channel),
            'wp_actions' => $this->wpActions($channel),
            'upstream_unavailable' => false,
        ], 200);
    }

    /**
     * Pull the plugin's automation registry. Every automation is shown to
     * any channel right now — the agent decides which apply. If we later
     * tag automations with channel hints, filter here.
     *
     * @return list<array<string, mixed>>
     */
    private function localAutomations(string $channel): array
    {
        $registry = new AutomationRegistry();
        $rows = [];

        foreach ($registry->all() as $automation) {
            $categories = $automation->categories();
            // Only show automations whose category overlaps with the channel.
            // 'content' category fits content/seo channels; 'social' fits social;
            // others fall through and show on every channel.
            if ($categories !== [] && ! $this->categoryFitsChannel($categories, $channel)) {
                continue;
            }

            $rows[] = [
                'key' => $automation->key(),
                'label' => $automation->label(),
                'description' => $automation->description(),
                'categories' => $categories,
                'modes' => $automation->supportedModes(),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $categories
     */
    private function categoryFitsChannel(array $categories, string $channel): bool
    {
        // Best-effort mapping: most automations work for content/seo channels;
        // social automations are social-only. Adjust as the catalogue grows.
        return match ($channel) {
            'social' => in_array('social', $categories, true),
            'ppc' => in_array('ppc', $categories, true),
            default => true, // content + seo see everything by default
        };
    }

    /**
     * The four WP action invokers — these are atomic CMS writes the agent
     * can dispatch via dispatch_action when an automation isn't a fit.
     *
     * @return list<array<string, mixed>>
     */
    private function wpActions(string $channel): array
    {
        // Only content-bearing channels need WP-side mutations.
        if (! in_array($channel, ['content', 'seo'], true)) {
            return [];
        }

        $registry = new ActionInvokerRegistry();
        $rows = [];

        foreach ($registry->all() as $invoker) {
            $rows[] = [
                'slug' => $invoker->slug(),
                'description' => $this->describeWpAction($invoker->slug()),
            ];
        }

        return $rows;
    }

    private function describeWpAction(string $slug): string
    {
        return match ($slug) {
            'wp.post.publish_draft' => 'Publish an existing draft post.',
            'wp.post.update_meta' => 'Update SEO meta title and description on a post or page.',
            'wp.post.update_excerpt' => 'Update the excerpt on a post.',
            'wp.post.add_internal_link' => 'Add an internal link from one post to another.',
            default => '(see plugin source for details)',
        };
    }

    private function channelLabel(string $channel): string
    {
        return match ($channel) {
            'content' => 'Content',
            'seo' => 'SEO',
            'ppc' => 'PPC',
            'social' => 'Social Media',
            default => ucfirst($channel),
        };
    }
}
