<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Logging;

final class LogEvent
{
    // Admin connection lifecycle
    public const CONNECT_FAILED = 'connect_failed';
    public const CONNECTED = 'connected';
    public const DISCONNECTED = 'disconnected';

    // Reports lifecycle (future)
    public const REPORT_GENERATED = 'report_generated';
    public const REPORT_SUBMIT_ATTEMPT = 'report_submit_attempt';
    public const REPORT_SUBMIT_OK = 'report_submit_ok';
    public const REPORT_SUBMIT_FAILED = 'report_submit_failed';

    // API client (future)
    public const API_REQUEST_FAILED = 'api_request_failed';

    private function __construct() {}
}
