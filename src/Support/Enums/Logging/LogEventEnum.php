<?php

namespace DigitalRoyalty\Beacon\Support\Enums\Logging;

final class LogEventEnum
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

    // API client
    public const API_AUTH_MISSING = 'api_auth_missing';
    public const API_REQUEST_START = 'api_request_start';
    public const API_REQUEST_OK = 'api_request_ok';
    public const API_REQUEST_DEFERRED = 'api_request_deferred';
    public const API_REQUEST_WP_ERROR = 'api_request_wp_error';
    public const API_RESPONSE_INVALID_JSON = 'api_response_invalid_json';
    public const API_REQUEST_UNAUTHORIZED = 'api_request_unauthorized';
    public const API_REQUEST_HTTP_ERROR = 'api_request_http_error';
    public const API_DEFERRED_ENQUEUE_OK = 'api_deferred_enqueue_ok';
    public const API_DEFERRED_ENQUEUE_FAILED = 'api_deferred_enqueue_failed';

    // Back-compat (keep existing constant)
    public const API_REQUEST_FAILED = 'api_request_failed';

    public const DEFERRED_RUN_SCHEDULED = 'deferred_run_scheduled';
    public const DEFERRED_RUN_ALREADY_SCHEDULED = 'deferred_run_already_scheduled';
    public const CRON_LOOPBACK_TEST = 'cron_loopback_test';

    const DEFERRED_RUN_START = 'deferred_run_start';
    const DEFERRED_RUN_ERROR = 'deferred_run_error';
    const DEFERRED_RUN_DUE_COUNT = 'deferred_run_due_count';
    const DEFERRED_RUN_END = 'deferred_run_end';
    const DEFERRED_ROW_EXCEPTION = 'deferred_row_exception';
    const DEFERRED_ROW_PROCESS_START = 'deferred_row_process_start';
    const DEFERRED_ROW_FAILED = 'deferred_row_failed';
    const DEFERRED_ROW_RESCHEDULED = 'deferred_row_rescheduled';
    const DEFERRED_ROW_COMPLETED = 'deferred_row_completed';

    // Automation scheduler
    public const AUTOMATION_SCHEDULED_RUN = 'automation_scheduled_run';

    private function __construct() {}
}
