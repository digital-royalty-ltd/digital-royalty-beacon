<?php

namespace DigitalRoyalty\Beacon\Repositories;

use DigitalRoyalty\Beacon\Database\ApiLogsTable;

final class ApiLogsRepository
{
    public function __construct(
        private readonly \wpdb $wpdb
    ) {}

    /**
     * Record a single API request.
     *
     * @param array{api_key_id:int, endpoint_key?:string|null, method:string, path:string, status_code:int, response_time_ms?:int|null, ip_address?:string|null} $data
     */
    public function insert(array $data): void
    {
        $table = ApiLogsTable::tableName($this->wpdb);

        $this->wpdb->insert($table, [
            'api_key_id'       => (int)    $data['api_key_id'],
            'endpoint_key'     => isset($data['endpoint_key'])     ? (string) $data['endpoint_key']     : null,
            'method'           => strtoupper((string) $data['method']),
            'path'             => (string) $data['path'],
            'status_code'      => (int)    $data['status_code'],
            'response_time_ms' => isset($data['response_time_ms']) ? (int)    $data['response_time_ms'] : null,
            'ip_address'       => isset($data['ip_address'])       ? (string) $data['ip_address']       : null,
            'created_at'       => current_time('mysql', true),
        ]);
    }

    /**
     * Paginated log entries for a specific API key, newest first.
     *
     * @param array{per_page?:int, page?:int} $args
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(int $apiKeyId, array $args = []): array
    {
        $perPage = isset($args['per_page']) ? (int) $args['per_page'] : 25;
        $page    = isset($args['page'])     ? (int) $args['page']     : 1;

        $perPage = max(10, min(100, $perPage));
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $table = ApiLogsTable::tableName($this->wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE api_key_id = %d", $apiKeyId)
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = (array) $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE api_key_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
                $apiKeyId,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Count requests for a key since a given UTC datetime string.
     * Used for hourly/daily rate limit checks.
     */
    public function countSince(int $apiKeyId, string $since): int
    {
        $table = ApiLogsTable::tableName($this->wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE api_key_id = %d AND created_at >= %s",
                $apiKeyId,
                $since
            )
        );
    }

    /**
     * Count requests in the last N seconds (for concurrent/burst limiting).
     */
    public function countRecent(int $apiKeyId, int $seconds = 5): int
    {
        $table = ApiLogsTable::tableName($this->wpdb);
        $since = gmdate('Y-m-d H:i:s', time() - $seconds);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE api_key_id = %d AND created_at >= %s",
                $apiKeyId,
                $since
            )
        );
    }
}
