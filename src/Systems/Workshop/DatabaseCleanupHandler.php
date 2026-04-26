<?php

namespace DigitalRoyalty\Beacon\Systems\Workshop;

use DigitalRoyalty\Beacon\Services\Services;
use DigitalRoyalty\Beacon\Support\Enums\Admin\DatabaseCleanupEnum;
use DigitalRoyalty\Beacon\Support\Enums\Logging\LogScopeEnum;

final class DatabaseCleanupHandler
{
    public function register(): void
    {
        add_action(DatabaseCleanupEnum::CRON_HOOK, [$this, 'runScheduledCleanup']);
        add_filter('cron_schedules', [$this, 'registerWeeklySchedule']);

        $this->syncSchedule();
    }

    /**
     * @param array<string, mixed> $schedules
     * @return array<string, mixed>
     */
    public function registerWeeklySchedule(array $schedules): array
    {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display'  => __('Once Weekly', 'digital-royalty-beacon'),
            ];
        }

        return $schedules;
    }

    public function syncSchedule(): void
    {
        $settings = self::settings();
        $next = wp_next_scheduled(DatabaseCleanupEnum::CRON_HOOK);

        if (empty($settings['enabled'])) {
            if ($next) {
                wp_unschedule_event($next, DatabaseCleanupEnum::CRON_HOOK);
            }
            return;
        }

        if ($next) {
            return;
        }

        wp_schedule_event(time() + HOUR_IN_SECONDS, $settings['frequency'], DatabaseCleanupEnum::CRON_HOOK);
    }

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        $settings = (array) get_option(DatabaseCleanupEnum::OPTION_SETTINGS, []);

        return [
            'enabled' => !empty($settings['enabled']),
            'frequency' => ($settings['frequency'] ?? 'weekly') === 'daily' ? 'daily' : 'weekly',
            'types' => array_values(array_filter(array_map('sanitize_key', (array) ($settings['types'] ?? [])))),
        ];
    }

    public function runScheduledCleanup(): void
    {
        global $wpdb;

        $start = microtime(true);
        $types = self::settings()['types'];
        /** @var array<string,int> $counts */
        $counts = [];

        foreach ($types as $type) {
            switch ($type) {
                case 'revisions':
                    $ids = (array) $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'");
                    foreach ($ids as $id) {
                        wp_delete_post_revision((int) $id);
                    }
                    $counts['revisions'] = count($ids);
                    break;
                case 'auto_drafts':
                    $counts['auto_drafts'] = (int) $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
                    break;
                case 'trashed_posts':
                    $ids = (array) $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'");
                    foreach ($ids as $id) {
                        wp_delete_post((int) $id, true);
                    }
                    $counts['trashed_posts'] = count($ids);
                    break;
                case 'spam_comments':
                    $counts['spam_comments'] = (int) $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
                    break;
                case 'trashed_comments':
                    $counts['trashed_comments'] = (int) $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'");
                    break;
                case 'orphan_postmeta':
                    $counts['orphan_postmeta'] = (int) $wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL");
                    break;
                case 'orphan_commentmeta':
                    $counts['orphan_commentmeta'] = (int) $wpdb->query("DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL");
                    break;
                case 'transients':
                    // Delete expired transients: timeout rows whose value is in the past
                    $expired = (int) $wpdb->query(
                        $wpdb->prepare(
                            "DELETE a, b FROM {$wpdb->options} a
                            LEFT JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, '_transient_timeout_', '_transient_')
                            WHERE a.option_name LIKE %s AND a.option_value < %d",
                            '_transient_timeout_%',
                            time()
                        )
                    );
                    // Also clean up orphaned timeout rows with no matching value
                    $orphaned = (int) $wpdb->query(
                        $wpdb->prepare(
                            "DELETE a FROM {$wpdb->options} a
                            LEFT JOIN {$wpdb->options} b ON b.option_name = REPLACE(a.option_name, '_transient_timeout_', '_transient_')
                            WHERE a.option_name LIKE %s AND b.option_id IS NULL",
                            '_transient_timeout_%'
                        )
                    );
                    $counts['transients'] = $expired + $orphaned;
                    break;
            }
        }

        $totalRows = array_sum($counts);
        $durationMs = (int) ((microtime(true) - $start) * 1000);

        // Without this log the operator can't answer "did the weekly cleanup
        // even run, and what did it do?" — particularly important since this
        // is a destructive operation we want a paper trail for.
        try {
            Services::logger()->info(
                LogScopeEnum::BACKGROUND,
                'database_cleanup_complete',
                "Database cleanup complete: removed {$totalRows} rows across " . count($counts) . ' type(s).',
                [
                    'counts_by_type' => $counts,
                    'total_rows' => $totalRows,
                    'duration_ms' => $durationMs,
                ]
            );
        } catch (\Throwable) {
            // ignore
        }
    }
}
