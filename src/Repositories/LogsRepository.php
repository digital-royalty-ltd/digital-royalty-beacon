<?php

namespace DigitalRoyalty\Beacon\Repositories;

use DigitalRoyalty\Beacon\Database\LogsTable;
use DigitalRoyalty\Beacon\Support\Enums\Database\LogTable;

final class LogsRepository
{
    public function __construct(
        private readonly \wpdb $wpdb
    ) {}

    /**
     * @param array<string,mixed> $row
     */
    public function insert(array $row): void
    {
        $table = $this->wpdb->prefix . LogTable::TABLE_SLUG;
        $this->wpdb->insert($table, $row);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function latest(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        $table = $this->wpdb->prefix . LogTable::TABLE_SLUG;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (array) $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $limit),
            ARRAY_A
        );
    }

    /**
     * @param array{per_page?:int,page?:int} $args
     * @return array{rows: array<int, array<string,mixed>>, total: int}
     */
    public function paginate(array $args = []): array
    {
        $perPage = isset($args['per_page']) ? (int) $args['per_page'] : 50;
        $page = isset($args['page']) ? (int) $args['page'] : 1;

        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);

        $offset = ($page - 1) * $perPage;

        $table = LogsTable::tableName($this->wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = (array) $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $perPage, $offset),
            ARRAY_A
        );

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }
}
