<?php

namespace DigitalRoyalty\Beacon\Repositories;

use DigitalRoyalty\Beacon\Database\FourOhFourLogsTable;
use wpdb;

final class FourOhFourLogsRepository
{
    public function __construct(private readonly wpdb $wpdb) {}

    public function record(string $path, ?string $referrer, ?string $userAgent = null, ?string $ipHash = null): void
    {
        $table = FourOhFourLogsTable::tableName($this->wpdb);
        $now   = current_time('mysql');

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$table} WHERE path = %s LIMIT 1", $path)
        );

        if ($existing) {
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$table} SET hit_count = hit_count + 1, referrer = %s, user_agent = %s, ip_hash = %s, last_seen_at = %s WHERE id = %d",
                    $referrer,
                    $userAgent,
                    $ipHash,
                    $now,
                    (int) $existing
                )
            );
            return;
        }

        $this->wpdb->insert($table, [
            'path'          => $path,
            'referrer'      => $referrer,
            'user_agent'    => $userAgent,
            'ip_hash'       => $ipHash,
            'hit_count'     => 1,
            'first_seen_at' => $now,
            'last_seen_at'  => $now,
        ], ['%s', '%s', '%s', '%s', '%d', '%s', '%s']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 200, string $sort = 'hits', ?string $search = null): array
    {
        $table  = FourOhFourLogsTable::tableName($this->wpdb);
        $orderBy = match ($sort) {
            'recent'   => 'last_seen_at DESC',
            'path'     => 'path ASC',
            'referrer' => 'referrer ASC',
            default    => 'hit_count DESC, last_seen_at DESC',
        };

        $sql = "SELECT * FROM {$table}";
        $args = [];

        if ($search !== null && $search !== '') {
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $sql .= ' WHERE path LIKE %s OR referrer LIKE %s';
            $args[] = $like;
            $args[] = $like;
        }

        $sql .= " ORDER BY {$orderBy} LIMIT %d";
        $args[] = $limit;

        $rows  = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$args),
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

    public function clearOlderThanDays(int $days): int
    {
        $table = FourOhFourLogsTable::tableName($this->wpdb);
        $threshold = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $result = $this->wpdb->query(
            $this->wpdb->prepare("DELETE FROM {$table} WHERE last_seen_at < %s", $threshold)
        );

        return $result !== false ? (int) $result : 0;
    }

    public function deleteById(int $id): bool
    {
        $table = FourOhFourLogsTable::tableName($this->wpdb);
        $result = $this->wpdb->delete($table, ['id' => $id], ['%d']);

        return $result !== false && $result > 0;
    }
}
