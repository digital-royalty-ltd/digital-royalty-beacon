<?php

namespace DigitalRoyalty\Beacon\Repositories;

use DigitalRoyalty\Beacon\Database\FourOhFourLogsTable;
use wpdb;

final class FourOhFourLogsRepository
{
    public function __construct(private readonly wpdb $wpdb) {}

    public function record(string $path, ?string $referrer): void
    {
        $table = FourOhFourLogsTable::tableName($this->wpdb);
        $now   = current_time('mysql');

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$table} WHERE path = %s LIMIT 1", $path)
        );

        if ($existing) {
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$table} SET hit_count = hit_count + 1, last_seen_at = %s WHERE id = %d",
                    $now,
                    (int) $existing
                )
            );
        } else {
            $this->wpdb->insert($table, [
                'path'         => $path,
                'referrer'     => $referrer,
                'hit_count'    => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ], ['%s', '%s', '%d', '%s', '%s']);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 200): array
    {
        $table = FourOhFourLogsTable::tableName($this->wpdb);
        $rows  = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY hit_count DESC, last_seen_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function count(): int
    {
        $table = FourOhFourLogsTable::tableName($this->wpdb);
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    public function clear(): void
    {
        $table = FourOhFourLogsTable::tableName($this->wpdb);
        $this->wpdb->query("TRUNCATE TABLE {$table}");
    }
}
