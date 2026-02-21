<?php

namespace DigitalRoyalty\Beacon\Admin\Actions\Views;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Systems\Api\ApiClient;
use DigitalRoyalty\Beacon\Systems\Reports\ReportManager;
use DigitalRoyalty\Beacon\Support\Enums\Admin\HomeViewActionEnum;
use DigitalRoyalty\Beacon\Support\Enums\Admin\HomeViewOptionEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEventEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;


final class HomePageAdminActions
{
    public function register(): void
    {
        add_action('admin_post_' . HomeViewActionEnum::VERIFY_SAVE, [$this, 'handleVerifyAndSave']);
        add_action('admin_post_' . HomeViewActionEnum::DISCONNECT, [$this, 'handleDisconnect']);
    }

    public function handleVerifyAndSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        check_admin_referer(HomeViewActionEnum::VERIFY_SAVE);

        $apiKey = isset($_POST[HomeViewOptionEnum::API_KEY])
            ? sanitize_text_field((string) $_POST[HomeViewOptionEnum::API_KEY])
            : '';

        if ($apiKey === '') {
            $this->redirectWithMessage(false, 'API key is required.');
        }

        $client = new ApiClient($apiKey);
        $res = $client->verifyApiKey();

        if (!$res->isOk()) {
            $msg = $res->message ?? ($res->isUnauthorized() ? 'Invalid API key.' : 'API key verification failed.');

            Services::logger()->warning(LogScopeEnum::ADMIN, LogEventEnum::CONNECT_FAILED, $msg, [
                'code' => $res->code,
            ]);

            $this->redirectWithMessage(false, $msg);
        }

        update_option(HomeViewOptionEnum::API_KEY, $apiKey, false);
        update_option(HomeViewOptionEnum::CONNECTED_AT, gmdate('c'), false);

        Services::reset();

        Services::logger()->info(LogScopeEnum::ADMIN, LogEventEnum::CONNECTED, 'Connected successfully.');

        $this->redirectWithMessage(true, 'Connected successfully.');
    }

    public function handleDisconnect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        check_admin_referer(HomeViewActionEnum::DISCONNECT);

        delete_option(HomeViewOptionEnum::API_KEY);
        delete_option(HomeViewOptionEnum::CONNECTED_AT);

        // Report onboarding
        delete_option(ReportManager::OPTION_STATUS);

        Services::reset();

        Services::logger()->info(LogScopeEnum::ADMIN, LogEventEnum::DISCONNECTED, 'Disconnected.');

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
