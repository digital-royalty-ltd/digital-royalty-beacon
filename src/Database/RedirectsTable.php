<?php

namespace DigitalRoyalty\Beacon\Database;

use wpdb;

final class RedirectsTable
{
    public const TABLE_SLUG = 'dr_beacon_redirects';
    public const SCHEMA_VERSION_OPTION = 'dr_beacon_redirects_table_schema_version';
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
                source_path VARCHAR(2048) NOT NULL,
                target_url VARCHAR(2048) NOT NULL,
                redirect_type SMALLINT(3) UNSIGNED NOT NULL DEFAULT 301,
                hit_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY source_path_idx (source_path(191)),
                KEY is_active_idx (is_active)
            ) {$charsetCollate};
        ";

        dbDelta($sql);
    }
}
