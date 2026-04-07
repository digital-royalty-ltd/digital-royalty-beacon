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
    public function all(?string $search = null): array
    {
        $table = RedirectsTable::tableName($this->wpdb);
        $sql   = "SELECT * FROM {$table}";

        if ($search !== null && $search !== '') {
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $sql .= $this->wpdb->prepare(
                " WHERE source_path LIKE %s OR target_url LIKE %s",
                $like,
                $like
            );
        }

        $sql .= ' ORDER BY created_at DESC';

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

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

        $rows = $this->wpdb->get_results(
            "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY created_at DESC",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!empty($row['regex_enabled'])) {
                $pattern = (string) ($row['source_path'] ?? '');
                if ($pattern !== '' && @preg_match('#' . $pattern . '#', $sourcePath) === 1) {
                    return $row;
                }
                continue;
            }

            if ((string) ($row['source_path'] ?? '') === $sourcePath) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDuplicateSource(string $sourcePath, bool $regexEnabled, ?int $excludeId = null): ?array
    {
        $table = RedirectsTable::tableName($this->wpdb);
        $sql   = "SELECT * FROM {$table} WHERE source_path = %s AND regex_enabled = %d";
        $args  = [$sourcePath, $regexEnabled ? 1 : 0];

        if ($excludeId !== null) {
            $sql .= ' AND id != %d';
            $args[] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $row = $this->wpdb->get_row($this->wpdb->prepare($sql, ...$args), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function create(string $sourcePath, string $targetUrl, int $redirectType, bool $regexEnabled = false, string $conditions = '[]'): int
    {
        $table = RedirectsTable::tableName($this->wpdb);
        $now   = current_time('mysql');

        $this->wpdb->insert(
            $table,
            [
                'source_path'      => $sourcePath,
                'target_url'       => $targetUrl,
                'redirect_type'    => $redirectType,
                'regex_enabled'    => $regexEnabled ? 1 : 0,
                'conditions'       => $conditions,
                'hit_count'        => 0,
                'last_accessed_at' => null,
                'is_active'        => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            ['%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%s']
        );

        return (int) $this->wpdb->insert_id;
    }

    public function update(int $id, string $sourcePath, string $targetUrl, int $redirectType, bool $isActive, bool $regexEnabled = false, string $conditions = '[]'): bool
    {
        $table = RedirectsTable::tableName($this->wpdb);

        $result = $this->wpdb->update(
            $table,
            [
                'source_path'   => $sourcePath,
                'target_url'    => $targetUrl,
                'redirect_type' => $redirectType,
                'regex_enabled' => $regexEnabled ? 1 : 0,
                'conditions'    => $conditions,
                'is_active'     => $isActive ? 1 : 0,
                'updated_at'    => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%d', '%s', '%d', '%s'],
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

    /**
     * @param int[] $ids
     */
    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }

        $table = RedirectsTable::tableName($this->wpdb);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $result = $this->wpdb->query(
            $this->wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids)
        );

        return $result !== false ? (int) $result : 0;
    }

    public function incrementHitCount(int $id): void
    {
        $table = RedirectsTable::tableName($this->wpdb);
        $now   = current_time('mysql');

        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$table} SET hit_count = hit_count + 1, last_accessed_at = %s, updated_at = %s WHERE id = %d",
                $now,
                $now,
                $id
            )
        );
    }
}
