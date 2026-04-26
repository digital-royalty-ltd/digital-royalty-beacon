<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Services\Services;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Proxy controller — WP admin UI calls these; they forward to Laravel.
 *
 * All marketing campaign data lives on Laravel. This plugin just provides
 * a UI and proxies the calls using the client's API key.
 */
final class MarketingCampaignsController
{
    public function registerRoutes(): void
    {
        $perm = fn () => current_user_can('manage_options');

        register_rest_route('beacon/v1', '/admin/marketing/agents', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listAgents'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/marketing/campaigns', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'listCampaigns'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'createCampaign'],
                'permission_callback' => $perm,
            ],
        ]);

        register_rest_route('beacon/v1', '/admin/marketing/campaigns/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getCampaign'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'updateCampaign'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'deleteCampaign'],
                'permission_callback' => $perm,
            ],
        ]);

        register_rest_route('beacon/v1', '/admin/marketing/campaigns/(?P<id>\d+)/ledger', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getLedger'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/marketing/campaigns/(?P<id>\d+)/sessions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getSessions'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/marketing/campaigns/(?P<id>\d+)/memory', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getMemory'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/marketing/campaigns/(?P<id>\d+)/watcher-events', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getWatcherEvents'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/marketing/campaigns/(?P<id>\d+)/action-log', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getActionLog'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('beacon/v1', '/admin/marketing/campaigns/(?P<id>\d+)/channels/(?P<channel>[a-z]+)', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'updateChannel'],
            'permission_callback' => $perm,
        ]);
    }

    // -----------------------------------------------------------------------

    public function listAgents(WP_REST_Request $request): WP_REST_Response
    {
        $res = Services::apiClient()->listMarketingAgents();
        return $this->respond($res);
    }

    public function listCampaigns(WP_REST_Request $request): WP_REST_Response
    {
        $res = Services::apiClient()->listMarketingCampaigns();
        return $this->respond($res);
    }

    public function createCampaign(WP_REST_Request $request): WP_REST_Response
    {
        $payload = (array) $request->get_json_params();
        $res = Services::apiClient()->createMarketingCampaign($payload);
        return $this->respond($res, 201);
    }

    public function getCampaign(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $res = Services::apiClient()->getMarketingCampaign($id);
        return $this->respond($res);
    }

    public function updateCampaign(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $payload = (array) $request->get_json_params();
        $res = Services::apiClient()->updateMarketingCampaign($id, $payload);
        return $this->respond($res);
    }

    public function deleteCampaign(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $res = Services::apiClient()->deleteMarketingCampaign($id);
        return $this->respond($res);
    }

    public function getLedger(WP_REST_Request $request): WP_REST_Response
    {
        $id    = (int) $request->get_param('id');
        $limit = (int) ($request->get_param('limit') ?? 50);
        $res   = Services::apiClient()->getMarketingCampaignLedger($id, $limit);
        return $this->respond($res);
    }

    public function updateChannel(WP_REST_Request $request): WP_REST_Response
    {
        $id      = (int) $request->get_param('id');
        $channel = (string) $request->get_param('channel');
        $payload = (array) $request->get_json_params();
        $res     = Services::apiClient()->updateMarketingCampaignChannel($id, $channel, $payload);
        return $this->respond($res);
    }

    public function getSessions(WP_REST_Request $request): WP_REST_Response
    {
        $id    = (int) $request->get_param('id');
        $limit = (int) ($request->get_param('limit') ?? 25);
        $res   = Services::apiClient()->getMarketingCampaignSessions($id, $limit);
        return $this->respond($res);
    }

    public function getMemory(WP_REST_Request $request): WP_REST_Response
    {
        $id  = (int) $request->get_param('id');
        $res = Services::apiClient()->getMarketingCampaignMemory($id);
        return $this->respond($res);
    }

    public function getWatcherEvents(WP_REST_Request $request): WP_REST_Response
    {
        $id        = (int) $request->get_param('id');
        $limit     = (int) ($request->get_param('limit') ?? 50);
        $severity  = is_string($request->get_param('severity')) ? $request->get_param('severity') : null;
        $sinceDays = (int) ($request->get_param('since_days') ?? 30);
        $res       = Services::apiClient()->getMarketingCampaignWatcherEvents($id, $limit, $severity, $sinceDays);
        return $this->respond($res);
    }

    public function getActionLog(WP_REST_Request $request): WP_REST_Response
    {
        $id    = (int) $request->get_param('id');
        $limit = (int) ($request->get_param('limit') ?? 50);
        $res   = Services::apiClient()->getMarketingCampaignActionLog($id, $limit);
        return $this->respond($res);
    }

    /**
     * Forward the Laravel response to the WP REST consumer, preserving status.
     */
    private function respond($response, int $successStatus = 200): WP_REST_Response
    {
        if (!$response->ok) {
            $status = $response->code >= 400 ? $response->code : 500;
            return new WP_REST_Response([
                'error' => $response->message ?? 'Upstream request failed.',
            ], $status);
        }

        return new WP_REST_Response($response->data, $successStatus);
    }
}
