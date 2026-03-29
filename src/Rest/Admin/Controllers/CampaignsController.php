<?php

namespace DigitalRoyalty\Beacon\Rest\Admin\Controllers;

use DigitalRoyalty\Beacon\Support\Enums\Campaigns\CampaignAiEnum;
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

        register_rest_route('beacon/v1', '/admin/campaigns/onboarding', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getOnboarding'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'setOnboarding'],
                'permission_callback' => $perm,
            ],
        ]);

        register_rest_route('beacon/v1', '/admin/campaigns/ai', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getAi'],
                'permission_callback' => $perm,
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'setAi'],
                'permission_callback' => $perm,
            ],
        ]);
    }

    public function getOnboarding(WP_REST_Request $request): WP_REST_Response
    {
        $data = get_option(CampaignAiEnum::OPTION_ONBOARDING, null);

        return new WP_REST_Response(['data' => $data ?: null], 200);
    }

    public function setOnboarding(WP_REST_Request $request): WP_REST_Response
    {
        $params  = (array) $request->get_json_params();
        $allowed = ['general', 'content', 'seo', 'ppc', 'social'];
        $clean   = [];

        foreach ($allowed as $section) {
            if (!isset($params[$section]) || !is_array($params[$section])) {
                continue;
            }
            $clean[$section] = array_map(
                static fn ($v) => is_array($v)
                    ? array_values(array_map('sanitize_text_field', $v))
                    : sanitize_textarea_field((string) $v),
                $params[$section]
            );
        }

        $clean['updated_at'] = gmdate('c');

        update_option(CampaignAiEnum::OPTION_ONBOARDING, $clean, false);

        return new WP_REST_Response(['ok' => true], 200);
    }

    public function getAi(WP_REST_Request $request): WP_REST_Response
    {
        $selected = (string) get_option(CampaignAiEnum::OPTION_SELECTED_AI, '');

        $characters = [];
        foreach (CampaignAiEnum::all() as $key) {
            $meta              = CampaignAiEnum::meta($key);
            $meta['image_url'] = $this->resolveImageUrl($key);
            $characters[$key]  = $meta;
        }

        return new WP_REST_Response([
            'selected'   => $selected !== '' ? $selected : null,
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

    public function setAi(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $key    = isset($params['key']) ? sanitize_key((string) $params['key']) : '';

        if ($key !== '' && !CampaignAiEnum::isValid($key)) {
            return new WP_REST_Response(['message' => 'Invalid AI key.'], 422);
        }

        if ($key === '') {
            delete_option(CampaignAiEnum::OPTION_SELECTED_AI);
        } else {
            update_option(CampaignAiEnum::OPTION_SELECTED_AI, $key, false);
        }

        return new WP_REST_Response(['ok' => true, 'selected' => $key !== '' ? $key : null], 200);
    }
}
