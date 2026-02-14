<?php

namespace DigitalRoyalty\Beacon\Admin\Actions\Pages;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Systems\Api\ApiClient;
use DigitalRoyalty\Beacon\Systems\Reports\ReportManager;
use DigitalRoyalty\Beacon\Support\Enums\Admin\HomePageAction;
use DigitalRoyalty\Beacon\Support\Enums\Admin\HomePageOption;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEvent;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScope;


final class HomePageAdminActions
{
    public function register(): void
    {
        add_action('admin_post_' . HomePageAction::VERIFY_SAVE, [$this, 'handleVerifyAndSave']);
        add_action('admin_post_' . HomePageAction::DISCONNECT, [$this, 'handleDisconnect']);
    }

    public function handleVerifyAndSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        check_admin_referer(HomePageAction::VERIFY_SAVE);

        $apiKey = isset($_POST[HomePageOption::API_KEY])
            ? sanitize_text_field((string) $_POST[HomePageOption::API_KEY])
            : '';

        if ($apiKey === '') {
            $this->redirectWithMessage(false, 'API key is required.');
        }

        $client = new ApiClient($apiKey);
        $res = $client->verifyApiKey();

        if (!$res->isOk()) {
            $msg = $res->message ?? ($res->isUnauthorized() ? 'Invalid API key.' : 'API key verification failed.');

            Services::logger()->warning(LogScope::ADMIN, LogEvent::CONNECT_FAILED, $msg, [
                'code' => $res->code,
            ]);

            $this->redirectWithMessage(false, $msg);
        }

        $siteId = (string) $res->get('site_id', '');
        if ($siteId === '') {
            Services::logger()->warning(LogScope::ADMIN, LogEvent::CONNECT_FAILED, 'Beacon API did not return a site_id.', [
                'code' => $res->code,
                'data' => $res->data,
            ]);

            $this->redirectWithMessage(false, 'Beacon API did not return a site_id.');
        }

        update_option(HomePageOption::API_KEY, $apiKey, false);
        update_option(HomePageOption::SITE_ID, $siteId, false);
        update_option(HomePageOption::CONNECTED_AT, gmdate('c'), false);

        Services::reset();

        Services::logger()->info(LogScope::ADMIN, LogEvent::CONNECTED, 'Connected successfully.', [
            'site_id' => $siteId,
        ]);

        $this->redirectWithMessage(true, 'Connected successfully.');
    }

    public function handleDisconnect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        check_admin_referer(HomePageAction::DISCONNECT);

        $existingSiteId = (string) get_option(HomePageOption::SITE_ID, '');

        delete_option(HomePageOption::API_KEY);
        delete_option(HomePageOption::SITE_ID);
        delete_option(HomePageOption::CONNECTED_AT);

        // Reports onboarding
        delete_option(ReportManager::OPTION_STATUS);

        Services::reset();

        Services::logger()->info(LogScope::ADMIN, LogEvent::DISCONNECTED, 'Disconnected.', [
            'site_id' => $existingSiteId,
        ]);

        $this->redirectWithMessage(true, 'Disconnected.');
    }

    private function redirectWithMessage(bool $ok, string $message): void
    {
        $url = add_query_arg([
            'page' => 'dr-beacon',
            'dr_beacon_ok' => $ok ? '1' : '0',
            'dr_beacon_msg' => rawurlencode($message),
        ], admin_url('admin.php'));

        wp_safe_redirect($url);
        exit;
    }
}
