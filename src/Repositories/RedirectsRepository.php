<?php

namespace DigitalRoyalty\Beacon\Repositories;

use DigitalRoyalty\Beacon\Database\RedirectsTable;
use wpdb;

final class RedirectsRepository
{
    public function __construct(
        private readonly wpdb $wpdb
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $table = RedirectsTable::tableName($this->wpdb);

        $rows = $this->wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY created_at DESC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $table = RedirectsTable::tableName($this->wpdb);

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySourcePath(string $sourcePath): ?array
    {
        $table = RedirectsTable::tableName($this->wpdb);

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE source_path = %s AND is_active = 1 LIMIT 1",
                $sourcePath
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function create(string $sourcePath, string $targetUrl, int $redirectType): int
    {
        $table = RedirectsTable::tableName($this->wpdb);
        $now   = current_time('mysql');

        $this->wpdb->insert(
            $table,
            [
                'source_path'   => $sourcePath,
                'target_url'    => $targetUrl,
                'redirect_type' => $redirectType,
                'hit_count'     => 0,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            ['%s', '%s', '%d', '%d', '%d', '%s', '%s']
        );

        return (int) $this->wpdb->insert_id;
    }

    public function update(int $id, string $sourcePath, string $targetUrl, int $redirectType, bool $isActive): bool
    {
        $table = RedirectsTable::tableName($this->wpdb);

        $result = $this->wpdb->update(
            $table,
            [
                'source_path'   => $sourcePath,
                'target_url'    => $targetUrl,
                'redirect_type' => $redirectType,
                'is_active'     => $isActive ? 1 : 0,
                'updated_at'    => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%d', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        $table = RedirectsTable::tableName($this->wpdb);

        $result = $this->wpdb->delete($table, ['id' => $id], ['%d']);

        return $result !== false;
    }

    public function incrementHitCount(int $id): void
    {
        $table = RedirectsTable::tableName($this->wpdb);

        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$table} SET hit_count = hit_count + 1, updated_at = %s WHERE id = %d",
                current_time('mysql'),
                $id
            )
        );
    }
}
