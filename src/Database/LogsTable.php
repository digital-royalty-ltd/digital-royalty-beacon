<?php

namespace DigitalRoyalty\Beacon\Database;

use DigitalRoyalty\Beacon\Support\Enums\Database\LogTable;
use wpdb;

final class LogsTable
{
    public static function tableName(wpdb $wpdb): string
    {
        return $wpdb->prefix . LogTable::TABLE_SLUG;
    }

    public static function install(): void
    {
        global $wpdb;

        $installedVersion = (int) get_option(LogTable::SCHEMA_VERSION_OPTION, 0);

        if ($installedVersion === LogTable::SCHEMA_VERSION && self::exists($wpdb)) {
            return;
        }

        self::createOrUpdateTable($wpdb);

        update_option(LogTable::SCHEMA_VERSION_OPTION, LogTable::SCHEMA_VERSION, true);
    }

    private static function exists(wpdb $wpdb): bool
    {
        $table = self::tableName($wpdb);

        $result = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        return is_string($result) && $result === $table;
    }

    private static function createOrUpdateTable(wpdb $wpdb): void
    {
        $table = self::tableName($wpdb);
        $charsetCollate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "
            CREATE TABLE {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                level VARCHAR(20) NOT NULL,
                scope VARCHAR(60) NOT NULL,
                event VARCHAR(80) NOT NULL,
                message TEXT NULL,
                context LONGTEXT NULL,
                request_id VARCHAR(64) NULL,
                report_type VARCHAR(64) NULL,
                report_version SMALLINT(5) UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY created_at_idx (created_at),
                KEY level_idx (level),
                KEY scope_event_idx (scope, event),
                KEY request_id_idx (request_id),
                KEY report_idx (report_type, report_version)
            ) {$charsetCollate};
        ";

        dbDelta($sql);
    }
}
