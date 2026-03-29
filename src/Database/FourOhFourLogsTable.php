<?php

namespace DigitalRoyalty\Beacon\Database;

use wpdb;

final class FourOhFourLogsTable
{
    public const TABLE_SLUG            = 'dr_beacon_404_logs';
    public const SCHEMA_VERSION_OPTION = 'dr_beacon_404_logs_schema_version';
    public const SCHEMA_VERSION        = 1;

    public static function tableName(wpdb $wpdb): string
    {
        return $wpdb->prefix . self::TABLE_SLUG;
    }

    public static function install(): void
    {
        global $wpdb;

        $installedVersion = (int) get_option(self::SCHEMA_VERSION_OPTION, 0);

        if ($installedVersion === self::SCHEMA_VERSION && self::exists($wpdb)) {
            return;
        }

        self::createOrUpdateTable($wpdb);
        update_option(self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, true);
    }

    private static function exists(wpdb $wpdb): bool
    {
        $table  = self::tableName($wpdb);
        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return is_string($result) && $result === $table;
    }

    private static function createOrUpdateTable(wpdb $wpdb): void
    {
        $table          = self::tableName($wpdb);
        $charsetCollate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                path VARCHAR(2048) NOT NULL,
                referrer VARCHAR(2048) NULL,
                hit_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
                first_seen_at DATETIME NOT NULL,
                last_seen_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY path_idx (path(191)),
                KEY last_seen_at_idx (last_seen_at)
            ) {$charsetCollate};
        ";

        dbDelta($sql);
    }
}
