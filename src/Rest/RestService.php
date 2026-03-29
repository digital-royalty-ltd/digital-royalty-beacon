<?php

namespace DigitalRoyalty\Beacon\Rest;

use DigitalRoyalty\Beacon\Rest\Controllers\OAuthCallbackController;
use DigitalRoyalty\Beacon\Rest\Controllers\StatusController;
use DigitalRoyalty\Beacon\Rest\Controllers\WebhookController;

final class RestService
{
    public function register(): void
    {
        add_action('rest_api_init', function () {
            (new StatusController())->registerRoutes();
            (new WebhookController())->registerRoutes();
            (new OAuthCallbackController())->registerRoutes();
        });
    }
}
