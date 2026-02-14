<?php

namespace DigitalRoyalty\Beacon\Repositories;

use DigitalRoyalty\Beacon\Support\Enums\Scheduler\SchedulerGroup;

final class SchedulerRepository
{
    public function __construct(
        private readonly \wpdb $wpdb
    ) {}

    /**
     * @return array{rows: array<int, array<string,mixed>>, total: int}
     */
    public function paginateBeaconActions(int $perPage, int $page): array
    {
        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $actionsTable = $this->wpdb->prefix . 'actionscheduler_actions';
        $groupsTable  = $this->wpdb->prefix . 'actionscheduler_groups';

        // Find group_id for slug
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $groupId = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT group_id FROM {$groupsTable} WHERE slug = %s LIMIT 1",
                SchedulerGroup::BEACON
            )
        );

        if ($groupId <= 0) {
            return ['rows' => [], 'total' => 0];
        }

        // Total count
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$actionsTable} WHERE group_id = %d",
                $groupId
            )
        );

        // Page rows
        // Note: args is stored as JSON string in newer AS versions, but can also be serialized in older ones.
        // We'll show it raw.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = (array) $this->wpdb->get_results(
            $this->wpdb->prepare(
                "
                SELECT
                    a.action_id AS id,
                    a.hook,
                    a.status,
                    a.scheduled_date_local AS next_run,
                    a.claim_id,
                    a.attempts,
                    a.args,
                    g.slug AS `group`
                FROM {$actionsTable} a
                INNER JOIN {$groupsTable} g ON g.group_id = a.group_id
                WHERE a.group_id = %d
                ORDER BY a.action_id DESC
                LIMIT %d OFFSET %d
                ",
                $groupId,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }
}
