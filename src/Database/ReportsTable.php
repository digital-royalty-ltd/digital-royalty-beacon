<?php

namespace DigitalRoyalty\Beacon\Database;

use wpdb;

final class ReportsTable
{
    public const TABLE_SLUG = 'dr_beacon_reports';
    public const SCHEMA_VERSION_OPTION = 'dr_beacon_reports_table_schema_version';
    public const SCHEMA_VERSION = 1;

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
                type VARCHAR(64) NOT NULL,
                version SMALLINT(5) UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                payload LONGTEXT NULL,
                payload_hash CHAR(64) NULL,
                generated_at DATETIME NULL,
                submitted_at DATETIME NULL,
                last_error TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY type_version_unique (type, version),
                KEY type_idx (type),
                KEY status_idx (status),
                KEY generated_at_idx (generated_at),
                KEY submitted_at_idx (submitted_at)
            ) {$charsetCollate};
        ";

        dbDelta($sql);
    }
}
