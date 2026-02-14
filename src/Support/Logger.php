<?php

namespace DigitalRoyalty\Beacon\Support;

use DigitalRoyalty\Beacon\Database\LogsTable;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogLevel;
use wpdb;

final class Logger
{
    private static ?string $requestId = null;

    public function __construct(
        private readonly wpdb $wpdb
    ) {}

    /**
     * @param array<string,mixed> $context
     */
    public function debug(string $scope, string $event, string $message = '', array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $scope, $event, $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function info(string $scope, string $event, string $message = '', array $context = []): void
    {
        $this->log(LogLevel::INFO, $scope, $event, $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function warning(string $scope, string $event, string $message = '', array $context = []): void
    {
        $this->log(LogLevel::WARNING, $scope, $event, $message, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public function error(string $scope, string $event, string $message = '', array $context = []): void
    {
        $this->log(LogLevel::ERROR, $scope, $event, $message, $context);
    }

    /**
     * Convenience: log an event related to a report type/version.
     *
     * @param array<string,mixed> $context
     */
    public function report(
        string $level,
        string $event,
        string $reportType,
        int $reportVersion,
        string $message = '',
        array $context = []
    ): void {
        $context = array_merge($context, [
            'report_type' => $reportType,
            'report_version' => $reportVersion,
        ]);

        $this->log(
            level: $level,
            scope: 'reports',
            event: $event,
            message: $message,
            context: $context,
            reportType: $reportType,
            reportVersion: $reportVersion
        );
    }

    /**
     * @param array<string,mixed> $context
     */
    private function log(
        string $level,
        string $scope,
        string $event,
        string $message = '',
        array $context = [],
        ?string $reportType = null,
        ?int $reportVersion = null
    ): void {
        try {
            $table = LogsTable::tableName($this->wpdb);

            $row = [
                'level' => $this->cleanLevel($level),
                'scope' => $this->clip($scope, 60),
                'event' => $this->clip($event, 80),
                'message' => $message !== '' ? $message : null,
                'context' => !empty($context) ? wp_json_encode($this->sanitiseContext($context)) : null,
                'request_id' => $this->requestId(),
                'report_type' => $reportType ? $this->clip($reportType, 64) : null,
                'report_version' => $reportVersion,
                'created_at' => current_time('mysql'),
            ];

            $formats = [
                '%s', // level
                '%s', // scope
                '%s', // event
                '%s', // message
                '%s', // context
                '%s', // request_id
                '%s', // report_type
                '%d', // report_version
                '%s', // created_at
            ];

            // wpdb expects formats count to match values count.
            // Build formats dynamically to match nullables.
            $finalFormats = [];
            $finalRow = [];

            foreach ($row as $key => $val) {
                $finalRow[$key] = $val;

                $finalFormats[] = match ($key) {
                    'report_version' => '%d',
                    default => '%s',
                };
            }

            $this->wpdb->insert($table, $finalRow, $finalFormats);
        } catch (\Throwable $e) {
            // Logging must never break plugin execution.
            // Only emit to PHP error log if WP_DEBUG enabled.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Beacon Logger] Failed to write log: ' . $e->getMessage());
            }
        }
    }

    private function requestId(): string
    {
        if (self::$requestId) {
            return self::$requestId;
        }

        self::$requestId = function_exists('wp_generate_uuid4')
            ? wp_generate_uuid4()
            : bin2hex(random_bytes(16));

        return self::$requestId;
    }

    private function cleanLevel(string $level): string
    {
        $level = strtolower(trim($level));

        return match ($level) {
            LogLevel::DEBUG,
            LogLevel::INFO,
            LogLevel::WARNING,
            LogLevel::ERROR => $level,
            default => LogLevel::INFO,
        };
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return (string) mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }

    /**
     * Ensure context is JSON encodable and safe-ish.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function sanitiseContext(array $context): array
    {
        $out = [];

        foreach ($context as $k => $v) {
            $key = is_string($k) ? $this->clip($k, 64) : (string) $k;

            if ($v === null || is_scalar($v)) {
                $out[$key] = $v;
                continue;
            }

            if (is_array($v)) {
                $out[$key] = $v;
                continue;
            }

            if ($v instanceof \Throwable) {
                $out[$key] = [
                    'type' => get_class($v),
                    'message' => $v->getMessage(),
                    'file' => $v->getFile(),
                    'line' => $v->getLine(),
                ];
                continue;
            }

            if (is_object($v)) {
                $out[$key] = [
                    'type' => get_class($v),
                    'string' => method_exists($v, '__toString') ? (string) $v : null,
                ];
                continue;
            }

            $out[$key] = (string) $v;
        }

        return $out;
    }
}
