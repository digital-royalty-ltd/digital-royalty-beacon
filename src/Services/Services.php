<?php

namespace DigitalRoyalty\Beacon\Services;

use DigitalRoyalty\Beacon\Systems\Api\ApiClient;
use DigitalRoyalty\Beacon\Systems\Reports\ReportSubmitter;

final class Services
{
    private const OPTION_API_KEY = 'dr_beacon_api_key';

    private static ?ApiClient $apiClient = null;
    private static ?ReportSubmitter $reportSubmitter = null;

    public static function apiClient(): ApiClient
    {
        if (self::$apiClient instanceof ApiClient) {
            return self::$apiClient;
        }

        $apiKey = trim((string) get_option(self::OPTION_API_KEY, ''));

        self::$apiClient = new ApiClient($apiKey !== '' ? $apiKey : null);

        return self::$apiClient;
    }

    public static function reportSubmitter(): ReportSubmitter
    {
        if (self::$reportSubmitter instanceof ReportSubmitter) {
            return self::$reportSubmitter;
        }

        self::$reportSubmitter = new ReportSubmitter(self::apiClient());

        return self::$reportSubmitter;
    }

    /**
     * Call after connect/disconnect so cached services pick up new options.
     */
    public static function reset(): void
    {
        self::$apiClient = null;
        self::$reportSubmitter = null;
    }
}
