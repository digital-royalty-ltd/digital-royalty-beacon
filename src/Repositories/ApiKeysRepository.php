<?php

namespace DigitalRoyalty\Beacon\Repositories;

use DigitalRoyalty\Beacon\Database\ApiKeysTable;

final class ApiKeysRepository
{
    public function __construct(
        private readonly \wpdb $wpdb
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $table = ApiKeysTable::tableName($this->wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (array) $this->wpdb->get_results(
            "SELECT id, name, key_prefix, is_active, last_used_at, created_at FROM {$table} ORDER BY id DESC",
            ARRAY_A
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $table = ApiKeysTable::tableName($this->wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByHash(string $hash): ?array
    {
        $table = ApiKeysTable::tableName($this->wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$table} WHERE key_hash = %s AND is_active = 1", $hash),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function insert(
        string $name,
        string $keyHash,
        string $keyPrefix,
        int    $maxConcurrent = 1,
        int    $hourlyLimit   = 60,
        int    $dailyLimit    = 500
    ): int {
        $table = ApiKeysTable::tableName($this->wpdb);

        $this->wpdb->insert($table, [
            'name'           => $name,
            'key_hash'       => $keyHash,
            'key_prefix'     => $keyPrefix,
            'is_active'      => 1,
            'max_concurrent' => max(1, $maxConcurrent),
            'hourly_limit'   => max(1, $hourlyLimit),
            'daily_limit'    => max(1, $dailyLimit),
            'created_at'     => current_time('mysql', true),
        ]);

        return (int) $this->wpdb->insert_id;
    }

    public function updateLimits(int $id, int $maxConcurrent, int $hourlyLimit, int $dailyLimit): void
    {
        $table = ApiKeysTable::tableName($this->wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$table} SET max_concurrent = %d, hourly_limit = %d, daily_limit = %d WHERE id = %d",
                max(1, $maxConcurrent),
                max(1, $hourlyLimit),
                max(1, $dailyLimit),
                $id
            )
        );
    }

    public function setActive(int $id, bool $active): void
    {
        $table = ApiKeysTable::tableName($this->wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query(
            $this->wpdb->prepare("UPDATE {$table} SET is_active = %d WHERE id = %d", $active ? 1 : 0, $id)
        );
    }

    public function rename(int $id, string $name): void
    {
        $table = ApiKeysTable::tableName($this->wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query(
            $this->wpdb->prepare("UPDATE {$table} SET name = %s WHERE id = %d", $name, $id)
        );
    }

    public function delete(int $id): void
    {
        $table = ApiKeysTable::tableName($this->wpdb);
        $this->wpdb->delete($table, ['id' => $id], ['%d']);
    }

    public function touchLastUsed(int $id): void
    {
        $table = ApiKeysTable::tableName($this->wpdb);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$table} SET last_used_at = %s WHERE id = %d",
                current_time('mysql', true),
                $id
            )
        );
    }
}
