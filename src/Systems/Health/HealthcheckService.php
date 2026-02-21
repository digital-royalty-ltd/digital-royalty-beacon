<?php

namespace DigitalRoyalty\Beacon\Systems\Health;

use wpdb;

final class HealthcheckService
{
    public function __construct(private wpdb $wpdb) {}

    /**
     * @return array<string, array<int, array{label:string, value:string, ok:bool|null, hint?:string}>>
     */
    public function snapshot(): array
    {
        return [
            'WordPress' => [
                $this->row('WP version', (string) get_bloginfo('version'), null),
                $this->row('Site URL', (string) site_url(), null),
                $this->row('Home URL', (string) home_url(), null),
                $this->row('Multisite', is_multisite() ? 'Yes' : 'No', null),
                $this->row('Debug mode (WP_DEBUG)', defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled', null),
            ],
            'Runtime' => [
                $this->row('PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>=') ? true : false, 'Recommended: PHP 8.0+'),
                $this->row('Memory limit', $this->ini('memory_limit'), null),
                $this->row('Max execution time', $this->ini('max_execution_time') . 's', null),
                $this->row('Max input vars', $this->ini('max_input_vars'), null),
            ],
            'Cron and Scheduling' => [
                $this->cronEnabledRow(),
                $this->row(
                    'WP Cron alternate (ALTERNATE_WP_CRON)',
                    (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON) ? 'Enabled' : 'Disabled',
                    null
                ),
                $this->row(
                    'DISABLE_WP_CRON',
                    (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 'True' : 'False',
                    (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? false : true,
                    'If True, you must run real cron to hit wp-cron.php'
                ),
                $this->actionSchedulerRow(),
                $this->nextScheduledRow('dr_beacon_run_deferred_requests'),
            ],
            'Beacon' => [
                $this->row('Beacon API key set', $this->boolToYesNo((string) get_option('dr_beacon_api_key', '') !== ''), null),
                $this->row('Beacon site id set', $this->boolToYesNo((string) get_option('dr_beacon_site_id', '') !== ''), null),
                $this->row('Connected at set', $this->boolToYesNo((string) get_option('dr_beacon_connected_at', '') !== ''), null),
                $this->row('Plugin version', defined('DR_BEACON_VERSION') ? (string) DR_BEACON_VERSION : 'Unknown', null),
            ],
            'Database' => array_merge(
                [
                    $this->dbRow('DB name', $this->wpdb->dbname ?? 'Unknown'),
                    $this->dbRow('DB host', $this->wpdb->dbhost ?? 'Unknown'),
                    $this->dbRow('DB charset', $this->wpdb->charset ?? 'Unknown'),
                    $this->dbRow('DB collate', $this->wpdb->collate ?? 'Unknown'),
                ],
                $this->beaconTableSizesRows()
            ),
        ];
    }

    private function row(string $label, string $value, ?bool $ok, ?string $hint = null): array
    {
        $row = ['label' => $label, 'value' => $value, 'ok' => $ok];
        if ($hint !== null && $hint !== '') {
            $row['hint'] = $hint;
        }
        return $row;
    }

    private function dbRow(string $label, string $value): array
    {
        return $this->row($label, $value, null);
    }

    private function ini(string $key): string
    {
        $v = ini_get($key);
        return is_string($v) && $v !== '' ? $v : 'Unknown';
    }

    private function boolToYesNo(bool $v): string
    {
        return $v ? 'Yes' : 'No';
    }

    private function cronEnabledRow(): array
    {
        // Not perfect, but useful: if DISABLE_WP_CRON true, WP cron is effectively off unless server cron calls wp-cron.php.
        $disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        return $this->row(
            'WP Cron enabled',
            $disabled ? 'No' : 'Yes',
            $disabled ? false : true,
            $disabled ? 'DISABLE_WP_CRON is true' : null
        );
    }

    private function actionSchedulerRow(): array
    {
        $available = function_exists('as_get_scheduled_actions');
        return $this->row(
            'Action Scheduler available',
            $available ? 'Yes' : 'No',
            $available ? true : false,
            $available ? null : 'Required for scheduled Beacon jobs in this plugin'
        );
    }

    private function nextScheduledRow(string $hook): array
    {
        $ts = wp_next_scheduled($hook);
        if (!$ts) {
            return $this->row("Next scheduled: {$hook}", 'Not scheduled', false, 'Nothing queued right now');
        }

        $gmt = gmdate('Y-m-d H:i:s', (int) $ts) . ' UTC';
        return $this->row("Next scheduled: {$hook}", $gmt, true);
    }

    /**
     * @return array<int, array{label:string, value:string, ok:bool|null, hint?:string}>
     */
    private function beaconTableSizesRows(): array
    {
        $db = $this->wpdb->dbname;
        if (!is_string($db) || $db === '') {
            return [$this->row('Beacon tables size', 'Unknown (no DB name)', null)];
        }

        // Adjust to match your actual table names.
        // If you already have constants for these, use them.
        $tables = [
            $this->wpdb->prefix . 'dr_beacon_logs' => 'Logs table',
            $this->wpdb->prefix . 'dr_beacon_deferred_requests' => 'Deferred requests table',
            $this->wpdb->prefix . 'dr_beacon_scheduler' => 'Scheduler table',
            $this->wpdb->prefix . 'dr_beacon_report_snapshots' => 'Report snapshots table',
        ];

        $rows = [];
        $totalBytes = 0;

        foreach ($tables as $table => $label) {
            $bytes = $this->tableSizeBytes($db, $table);
            if ($bytes === null) {
                $rows[] = $this->row($label . ' size', 'Not found', false, 'Table missing, check install/migrations');
                continue;
            }

            $totalBytes += $bytes;
            $rows[] = $this->row($label . ' size', $this->formatBytes($bytes), null);
        }

        $rows[] = $this->row('Beacon tables total', $this->formatBytes($totalBytes), null);

        return $rows;
    }

    private function tableSizeBytes(string $dbName, string $tableName): ?int
    {
        // Use information_schema, works in most MySQL/MariaDB environments.
        $sql = "
            SELECT (DATA_LENGTH + INDEX_LENGTH) AS bytes
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = %s
              AND TABLE_NAME = %s
            LIMIT 1
        ";

        $prepared = $this->wpdb->prepare($sql, $dbName, $tableName);
        $val = $this->wpdb->get_var($prepared);

        if ($val === null) {
            return null;
        }

        $bytes = (int) $val;
        return $bytes >= 0 ? $bytes : null;
    }

    private function formatBytes(int $bytes): string
    {
        // Always show MB with 2dp for your “how many MB” requirement, but still sensible for tiny values.
        $mb = $bytes / 1024 / 1024;
        if ($mb < 0.01) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return number_format($mb, 2) . ' MB';
    }
}