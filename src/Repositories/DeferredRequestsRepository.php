<?php

namespace DigitalRoyalty\Beacon\Repositories;

use DigitalRoyalty\Beacon\Database\DeferredRequestsTable;
use wpdb;
use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogEventEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;

final class DeferredRequestsRepository
{
    public function __construct(private readonly wpdb $wpdb) {}

    /**
     * @param array<string,mixed>|null $payload
     */
    public function enqueue(
        string $requestKey,
        string $pollPath,
        ?string $externalId,
        int $delaySeconds,
        ?array $payload = null
    ): int {
        $table = DeferredRequestsTable::tableName($this->wpdb);

        $now = gmdate('Y-m-d H:i:s');
        $next = gmdate('Y-m-d H:i:s', time() + max(1, $delaySeconds));

        $data = [
            'request_key' => $requestKey,
            'status' => 'pending',
            'poll_path' => $pollPath,
            'external_id' => $externalId,
            'payload' => $payload ? wp_json_encode($payload) : null,
            'attempts' => 0,
            'next_attempt_at' => $next,
            'last_error' => null,
            'result' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $formats = ['%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s'];

        $result = $this->wpdb->insert($table, $data, $formats);

        if ($result === false) {
            Services::logger()->info(
                LogScopeEnum::API,
                LogEventEnum::API_DEFERRED_ENQUEUE_FAILED,
                'Deferred request insert failed.',
                [
                    'table' => $table,
                    'wpdb_prefix' => $this->wpdb->prefix,
                    'request_key' => $requestKey,
                    'poll_path' => $pollPath,
                    'external_id' => $externalId,
                    'delay_seconds' => $delaySeconds,
                    'payload_bytes' => is_string($data['payload']) ? strlen($data['payload']) : 0,
                    'wpdb_last_error' => $this->wpdb->last_error,
                    'wpdb_last_query' => $this->wpdb->last_query,
                ]
            );

            return 0;
        }

        $id = (int) $this->wpdb->insert_id;

        Services::logger()->info(
            LogScopeEnum::API,
            LogEventEnum::API_DEFERRED_ENQUEUE_OK,
            'Deferred request inserted.',
            [
                'table' => $table,
                'wpdb_prefix' => $this->wpdb->prefix,
                'deferred_request_id' => $id,
                'request_key' => $requestKey,
                'poll_path' => $pollPath,
                'external_id' => $externalId,
                'delay_seconds' => $delaySeconds,
            ]
        );

        return $id;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function due(int $limit = 20): array
    {
        $table = DeferredRequestsTable::tableName($this->wpdb);
        $now = gmdate('Y-m-d H:i:s');

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = 'pending' AND next_attempt_at <= %s
             ORDER BY next_attempt_at ASC
             LIMIT %d",
            $now,
            $limit
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function reschedule(int $id, int $delaySeconds, ?string $lastError = null): void
    {
        $table = DeferredRequestsTable::tableName($this->wpdb);

        $next = gmdate('Y-m-d H:i:s', time() + max(1, $delaySeconds));
        $now = gmdate('Y-m-d H:i:s');

        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$table}
                 SET attempts = attempts + 1,
                     next_attempt_at = %s,
                     last_error = %s,
                     updated_at = %s
                 WHERE id = %d",
                $next,
                $lastError,
                $now,
                $id
            )
        );
    }

    public function markFailed(int $id, string $error): void
    {
        $table = DeferredRequestsTable::tableName($this->wpdb);
        $now = gmdate('Y-m-d H:i:s');

        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$table}
                 SET status = 'failed',
                     last_error = %s,
                     updated_at = %s
                 WHERE id = %d",
                $error,
                $now,
                $id
            )
        );
    }

    /**
     * @param array<string,mixed> $result
     */
    public function markCompleted(int $id, array $result): void
    {
        $table = DeferredRequestsTable::tableName($this->wpdb);
        $now = gmdate('Y-m-d H:i:s');

        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$table}
                 SET status = 'completed',
                     result = %s,
                     updated_at = %s
                 WHERE id = %d",
                wp_json_encode($result),
                $now,
                $id
            )
        );
    }

    /**
     * @return int
     */
    public function countAll(): int
    {
        $table = DeferredRequestsTable::tableName($this->wpdb);

        $sql = "SELECT COUNT(*) FROM {$table}";
        $count = $this->wpdb->get_var($sql);

        return (int) ($count ?? 0);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list(
        int $perPage,
        int $page,
        string $orderby = 'next_attempt_at',
        string $order = 'DESC'
    ): array {
        $table = DeferredRequestsTable::tableName($this->wpdb);

        $allowedOrderBy = [
            'id',
            'request_key',
            'status',
            'poll_path',
            'external_id',
            'attempts',
            'next_attempt_at',
            'created_at',
            'updated_at',
        ];

        if (!in_array($orderby, $allowedOrderBy, true)) {
            $orderby = 'next_attempt_at';
        }

        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $this->wpdb->prepare(
            "SELECT *
         FROM {$table}
         ORDER BY {$orderby} {$order}
         LIMIT %d OFFSET %d",
            $perPage,
            $offset
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Return the most recent deferred request row for a given request key,
     * regardless of status. Used by the automations controller to surface
     * the latest run state.
     *
     * @return array<string,mixed>|null
     */
    public function getLatestByKey(string $requestKey): ?array
    {
        $table = DeferredRequestsTable::tableName($this->wpdb);

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE request_key = %s ORDER BY created_at DESC LIMIT 1",
                $requestKey
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function nextDueTimestampUtc(): ?int
    {
        // Return earliest next_attempt time for pending rows as a Unix timestamp (UTC).
        // Use the exact column name you store, example assumes DATETIME UTC in `next_attempt_at`.
        $table = $this->wpdb->prefix . 'dr_beacon_deferred_requests';

        $min = $this->wpdb->get_var("
        SELECT MIN(next_attempt_at)
        FROM {$table}
        WHERE status = 'pending'
    ");

        if (!is_string($min) || trim($min) === '') {
            return null;
        }

        $ts = strtotime($min . ' UTC');
        return $ts === false ? null : $ts;
    }

    public function deleteAll(): void
    {
        $table = DeferredRequestsTable::tableName($this->wpdb);
        $this->wpdb->query("DELETE FROM {$table}");
    }

    /**
     * @return int|null Unix timestamp for earliest next attempt among pending rows (UTC)
     */
    public function nextPendingAttemptTimestampUtc(): ?int
    {
        $table = $this->wpdb->prefix . 'dr_beacon_deferred_requests';

        $min = $this->wpdb->get_var("
        SELECT MIN(next_attempt_at_utc)
        FROM {$table}
        WHERE status = 'pending'
    ");

        if (!is_string($min) || trim($min) === '') {
            return null;
        }

        $ts = strtotime($min . ' UTC');
        return $ts === false ? null : $ts;
    }
}