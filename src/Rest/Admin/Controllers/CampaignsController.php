<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Campaigns\CampaignAiEnum;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationRegistry;
use DigitalRoyalty\Beacon\Systems\Automations\AutomationRequestPoller;
use DigitalRoyalty\Beacon\Systems\Heartbeat\HeartbeatScheduler;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET  /admin/campaigns/ai — return selected AI + all character metadata
 * POST /admin/campaigns/ai — set selected AI
 */
final class CampaignsController
{
    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        // Agent metadata (emoji, colour, tagline). Read-only: the plugin serves
        // this locally so the admin UI never has to wait for Laravel. The
        // authoritative agent assignment per channel lives on Laravel and is
        // reached via the channel endpoints below.
        register_rest_route('beacon/v1', '/admin/campaigns/ai', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getAi'],
            'permission_callback' => $perm,
        ]);

        // Channel-based hiring: which agent handles which channel.
        register_rest_route('beacon/v1', '/admin/campaigns/channels', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getChannels'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/hire', [
            'methods'             => 'POST',
            'callback'            => [$this, 'hireAgent'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/hire-quote', [
            'methods'             => 'GET',
            'callback'            => [$this, 'hireQuote'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/resume', [
            'methods'             => 'POST',
            'callback'            => [$this, 'resumeChannel'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/ledger', [
            'methods'             => 'GET',
            'callback'            => [$this, 'channelLedger'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/memory', [
            'methods'             => 'GET',
            'callback'            => [$this, 'channelMemory'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/sessions/(?P<session>[a-f0-9-]+)/strike', [
            'methods'             => 'POST',
            'callback'            => [$this, 'strikeSession'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/sessions/(?P<session>[a-f0-9-]+)/unstrike', [
            'methods'             => 'POST',
            'callback'            => [$this, 'unstrikeSession'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/answers', [
            'methods'             => 'POST',
            'callback'            => [$this, 'answerChannelQuestion'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/onboarding', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getChannelOnboarding'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'submitChannelOnboarding'],
                'permission_callback' => $perm,
            ],
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/commitments', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getChannelCommitments'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/documents', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getChannelDocuments'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/documents/(?P<id>[a-f0-9-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getChannelDocument'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/progress', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getChannelProgress'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/progress/(?P<cycle>\d+)/calendar', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getCycleCalendar'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/commissions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getChannelCommissions'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)/commissions/(?P<id>[a-f0-9-]+)/(?P<action>approve|reject|mark-ordered|mark-delivered|cancel)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'commissionAction'],
            'permission_callback' => $perm,
        ]);

        // Diagnostics: run a heartbeat synchronously and return the full trace.
        register_rest_route('beacon/v1', '/admin/campaigns/diagnostics/heartbeat', [
            'methods'             => 'POST',
            'callback'            => [$this, 'runHeartbeatDiagnostic'],
            'permission_callback' => $perm,
        ]);

        // Diagnostics: run the automation poller synchronously and return the trace.
        register_rest_route('beacon/v1', '/admin/campaigns/diagnostics/poll', [
            'methods'             => 'POST',
            'callback'            => [$this, 'runPollerDiagnostic'],
            'permission_callback' => $perm,
        ]);

        // Diagnostics: read-only WP cron health for our plugin's hooks.
        register_rest_route('beacon/v1', '/admin/campaigns/diagnostics/cron-status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'cronStatus'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/channels/(?P<channel>[a-z_]+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'updateChannel'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'unhireChannel'],
                'permission_callback' => $perm,
            ],
        ]);
    }

    /**
     * Enrich agent keys coming back from Laravel with local WP-side metadata
     * (emoji, tagline, traits, description, color, image_url).
     */
    private function enrichChannels(WP_REST_Response $upstream): WP_REST_Response
    {
        $data = $upstream->get_data();
        if (!is_array($data) || !isset($data['channels']) || !is_array($data['channels'])) {
            return $upstream;
        }

        foreach ($data['channels'] as &$channel) {
            if (empty($channel['agent']) || !is_array($channel['agent'])) {
                continue;
            }

            $key = isset($channel['agent']['key']) ? (string) $channel['agent']['key'] : '';
            if ($key === '' || !CampaignAiEnum::isValid($key)) {
                continue;
            }

            $meta              = CampaignAiEnum::meta($key);
            $meta['key']       = $key;
            $meta['image_url'] = $this->resolveImageUrl($key);
            // Preserve anything Laravel included (e.g. name/id) while overlaying our display meta.
            $channel['agent'] = array_merge($channel['agent'], $meta);
        }
        unset($channel);

        $upstream->set_data($data);

        return $upstream;
    }

    /**
     * Forward a Laravel response to the WP REST consumer, preserving status.
     */
    private function forward($response, int $successStatus = 200): WP_REST_Response
    {
        if (!$response->ok) {
            $status = $response->code >= 400 ? $response->code : 500;
            return new WP_REST_Response([
                'message' => $response->message ?? 'Upstream request failed.',
            ], $status);
        }

        return new WP_REST_Response($response->data, $successStatus);
    }

    public function getChannels(WP_REST_Request $request): WP_REST_Response
    {
        $res = Services::apiClient()->listMarketingChannels();

        return $this->enrichChannels($this->forward($res));
    }

    public function hireAgent(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();

        $res = Services::apiClient()->hireMarketingAgent([
            'agent_key' => isset($params['agent_key']) ? (string) $params['agent_key'] : '',
            'channels'  => isset($params['channels']) && is_array($params['channels']) ? $params['channels'] : [],
        ]);

        return $this->enrichChannels($this->forward($res));
    }

    public function updateChannel(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');
        $params  = (array) $request->get_json_params();

        $res = Services::apiClient()->updateMarketingChannel($channel, $params);

        return $this->enrichChannels($this->forward($res));
    }

    public function unhireChannel(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');

        $res = Services::apiClient()->unhireMarketingChannel($channel);

        return $this->enrichChannels($this->forward($res));
    }

    public function hireQuote(WP_REST_Request $request): WP_REST_Response
    {
        $raw = $request->get_param('channels');
        $channels = is_array($raw) ? array_values(array_map('strval', $raw)) : [];

        $res = Services::apiClient()->quoteMarketingHire($channels);

        return $this->forward($res);
    }

    public function resumeChannel(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');

        $res = Services::apiClient()->resumeMarketingChannel($channel);

        return $this->enrichChannels($this->forward($res));
    }

    public function channelLedger(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');
        $limit = (int) ($request->get_param('limit') ?? 50);

        $res = Services::apiClient()->getMarketingChannelLedger($channel, $limit);

        return $this->forward($res);
    }

    public function channelMemory(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');

        $res = Services::apiClient()->getMarketingChannelMemory($channel);

        return $this->forward($res);
    }

    public function strikeSession(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');
        $session = (string) $request->get_param('session');
        $body = (array) $request->get_json_params();
        $reason = isset($body['reason']) && is_string($body['reason']) ? trim($body['reason']) : null;

        // Stamp the WP user id so Laravel's audit trail can attribute the
        // strike. Laravel doesn't share user ids with the plugin, but we
        // record the WP-side id alongside the operator's reason.
        $userId = get_current_user_id() ?: null;

        $res = Services::apiClient()->strikeMarketingSession($channel, $session, $reason, $userId);

        return $this->forward($res);
    }

    public function unstrikeSession(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');
        $session = (string) $request->get_param('session');

        $res = Services::apiClient()->unstrikeMarketingSession($channel, $session);

        return $this->forward($res);
    }

    public function answerChannelQuestion(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');
        $params = (array) $request->get_json_params();

        $res = Services::apiClient()->answerMarketingQuestion($channel, $params);

        return $this->forward($res);
    }

    public function getChannelOnboarding(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');

        $res = Services::apiClient()->getChannelOnboarding($channel);

        return $this->forward($res);
    }

    public function submitChannelOnboarding(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');
        $body = (array) $request->get_json_params();
        $answers = is_array($body['answers'] ?? null) ? $body['answers'] : [];

        $res = Services::apiClient()->submitChannelOnboarding($channel, $answers);

        // Successful submission flips the channel out of awaiting_onboarding
        // and Laravel returns the fresh channels list — enrich it with
        // local agent meta so the React side sees the same shape it does
        // from /admin/campaigns/channels.
        return $this->enrichChannels($this->forward($res));
    }

    public function getChannelCommitments(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');

        $res = Services::apiClient()->getChannelCommitments($channel);

        return $this->forward($res);
    }

    public function getChannelDocuments(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');
        $type    = $request->get_param('type');

        $res = Services::apiClient()->getChannelDocuments($channel, is_string($type) ? $type : null);

        return $this->forward($res);
    }

    public function getChannelDocument(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');
        $id      = (string) $request->get_param('id');

        $res = Services::apiClient()->getChannelDocument($channel, $id);

        return $this->forward($res);
    }

    public function getChannelProgress(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');

        $res = Services::apiClient()->getChannelProgress($channel);

        return $this->forward($res);
    }

    public function getCycleCalendar(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');
        $cycle   = (int) $request->get_param('cycle');

        $res = Services::apiClient()->getCycleCalendar($channel, $cycle);

        return $this->forward($res);
    }

    public function getChannelCommissions(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');

        $res = Services::apiClient()->getChannelCommissions($channel);

        return $this->forward($res);
    }

    public function commissionAction(WP_REST_Request $request): WP_REST_Response
    {
        $channel = (string) $request->get_param('channel');
        $id      = (string) $request->get_param('id');
        $action  = (string) $request->get_param('action');
        $payload = (array) $request->get_json_params();

        $client = Services::apiClient();
        $res = match ($action) {
            'approve'         => $client->approveCommission($channel, $id, $payload),
            'reject'          => $client->rejectCommission($channel, $id, $payload),
            'mark-ordered'    => $client->markCommissionOrdered($channel, $id, $payload),
            'mark-delivered'  => $client->markCommissionDelivered($channel, $id, $payload),
            'cancel'          => $client->cancelCommission($channel, $id, $payload),
            default           => null,
        };

        if ($res === null) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Unknown commission action.'], 400);
        }

        return $this->forward($res);
    }

    /**
     * POST /admin/campaigns/diagnostics/heartbeat
     *
     * Body: { force_catalog?: bool }
     * Runs the heartbeat + catalog publish synchronously and returns the full
     * trace so operators can see what's being sent + what Laravel returns.
     */
    public function runHeartbeatDiagnostic(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $force = (bool) ($params['force_catalog'] ?? false);

        $result = HeartbeatScheduler::runDiagnostic($force);

        return new WP_REST_Response($result, 200);
    }

    /**
     * POST /admin/campaigns/diagnostics/poll
     *
     * Fires the automation poller synchronously and returns a trace of what
     * was fetched, claimed, invoked, and how each ended. Lets an operator
     * run the plugin → Laravel pull queue without waiting for WP cron.
     */
    public function runPollerDiagnostic(WP_REST_Request $request): WP_REST_Response
    {
        $poller = new AutomationRequestPoller(new AutomationRegistry());
        $trace = $poller->runTickWithTrace();

        return new WP_REST_Response($trace, 200);
    }

    /**
     * GET /admin/campaigns/diagnostics/cron-status
     *
     * Reports on WP cron health so operators can tell at a glance whether
     * the plugin's scheduled events will fire. The most common "my poller
     * isn't running" cause is DISABLE_WP_CRON in wp-config — this surfaces
     * it directly rather than making the operator dig into code.
     */
    public function cronStatus(WP_REST_Request $request): WP_REST_Response
    {
        $hooks = [
            'dr_beacon_heartbeat'       => 'Heartbeat (daily)',
            'dr_beacon_automation_poll' => 'Automation poller (every 5 min)',
        ];

        $events = [];
        foreach ($hooks as $hook => $label) {
            $nextTs = wp_next_scheduled($hook);
            $event = wp_get_scheduled_event($hook);
            $events[] = [
                'hook'        => $hook,
                'label'       => $label,
                'scheduled'   => (bool) $nextTs,
                'next_run_at' => $nextTs ? gmdate('c', (int) $nextTs) : null,
                'recurrence'  => $event ? ($event->schedule ?? null) : null,
                'interval'    => $event && isset($event->interval) ? (int) $event->interval : null,
            ];
        }

        return new WP_REST_Response([
            'disable_wp_cron'   => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'alternate_wp_cron' => defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON,
            'doing_cron'        => defined('DOING_CRON') && DOING_CRON,
            'server_time_utc'   => gmdate('c'),
            'events'            => $events,
        ], 200);
    }

    public function getAi(WP_REST_Request $request): WP_REST_Response
    {
        $characters = [];
        foreach (CampaignAiEnum::all() as $key) {
            $meta              = CampaignAiEnum::meta($key);
            $meta['image_url'] = $this->resolveImageUrl($key);
            $characters[$key]  = $meta;
        }

        return new WP_REST_Response([
            // `selected` is retained for shape compatibility with the existing
            // AiResponse type on the frontend. The new channel-centric flow
            // doesn't use it — selected agents live per-channel on Laravel.
            'selected'   => null,
            'characters' => $characters,
        ], 200);
    }

    private function resolveImageUrl(string $key): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $relative = 'assets/images/ai/' . $key . '.' . $ext;
            $absolute = DR_BEACON_DIR . DIRECTORY_SEPARATOR . $relative;

            if (file_exists($absolute)) {
                return plugins_url($relative, DR_BEACON_FILE);
            }
        }

        return null;
    }

}
