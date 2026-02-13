<?php

namespace DigitalRoyalty\Beacon\Repositories;

use DigitalRoyalty\Beacon\Database\ReportsTable;
use wpdb;

final class ReportsRepository
{
    public function __construct(
        private readonly wpdb $wpdb
    ) {}

    public function upsertGenerated(
        string $type,
        int $version,
        string $payloadJson,
        ?string $payloadHash,
        string $generatedAtMysql
    ): void {
        $table = ReportsTable::tableName($this->wpdb);
        $now = current_time('mysql');

        // Use INSERT ... ON DUPLICATE KEY UPDATE for the (type, version) unique index.
        $sql = "
            INSERT INTO {$table}
                (type, version, status, payload, payload_hash, generated_at, last_error, created_at, updated_at)
            VALUES
                (%s, %d, %s, %s, %s, %s, NULL, %s, %s)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                payload = VALUES(payload),
                payload_hash = VALUES(payload_hash),
                generated_at = VALUES(generated_at),
                last_error = NULL,
                updated_at = VALUES(updated_at)
        ";

        $this->wpdb->query(
            $this->wpdb->prepare(
                $sql,
                $type,
                $version,
                'generated',
                $payloadJson,
                $payloadHash,
                $generatedAtMysql,
                $now,
                $now
            )
        );
    }


    public function markFailed(string $type, int $version, string $errorMessage): void
    {
        $table = ReportsTable::tableName($this->wpdb);

        $this->wpdb->update(
            $table,
            [
                'status' => 'failed',
                'last_error' => $errorMessage,
                'updated_at' => current_time('mysql'),
            ],
            [
                'type' => $type,
                'version' => $version,
            ],
            ['%s', '%s', '%s'],
            ['%s', '%d']
        );
    }

    public function getLatestByType(string $type): ?array
    {
        $table = ReportsTable::tableName($this->wpdb);

        $sql = "
            SELECT *
            FROM {$table}
            WHERE type = %s
            ORDER BY version DESC
            LIMIT 1
        ";

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $type),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function getByTypeAndVersion(string $type, int $version): ?array
    {
        $table = ReportsTable::tableName($this->wpdb);

        $sql = "
            SELECT *
            FROM {$table}
            WHERE type = %s AND version = %d
            LIMIT 1
        ";

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $type, $version),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function deleteOlderVersions(string $type, int $keepVersion): int
    {
        $table = ReportsTable::tableName($this->wpdb);

        $sql = "
            DELETE FROM {$table}
            WHERE type = %s AND version < %d
        ";

        $this->wpdb->query($this->wpdb->prepare($sql, $type, $keepVersion));

        return (int) $this->wpdb->rows_affected;
    }

    public function listByTypes(array $types): array
    {
        $table = \DigitalRoyalty\Beacon\Database\ReportsTable::tableName($this->wpdb);

        if (empty($types)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        $sql = "SELECT * FROM {$table} WHERE type IN ({$placeholders}) ORDER BY type ASC, version DESC";

        $prepared = $this->wpdb->prepare($sql, ...array_values($types));
        $rows = $this->wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function upsertPending(string $type, int $version): void
    {
        $table = \DigitalRoyalty\Beacon\Database\ReportsTable::tableName($this->wpdb);
        $now = current_time('mysql');

        $sql = "
        INSERT INTO {$table} (type, version, status, created_at, updated_at)
        VALUES (%s, %d, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            updated_at = VALUES(updated_at)
    ";

        $this->wpdb->query(
            $this->wpdb->prepare($sql, $type, $version, 'pending', $now, $now)
        );
    }

    public function markGenerated(string $type, int $version, string $payloadJson, string $hash, string $generatedAt): void
    {
        // If you already have upsertGenerated, keep that instead.
        $this->upsertGenerated($type, $version, $payloadJson, $hash, $generatedAt);
    }

    public function markSubmitted(string $type, int $version, string $submittedAt): void
    {
        $table = \DigitalRoyalty\Beacon\Database\ReportsTable::tableName($this->wpdb);

        $this->wpdb->update(
            $table,
            [
                'status' => 'submitted',
                'submitted_at' => $submittedAt,
                'last_error' => null,
                'updated_at' => current_time('mysql'),
            ],
            ['type' => $type, 'version' => $version],
            ['%s', '%s', '%s', '%s'],
            ['%s', '%d']
        );
    }

}
