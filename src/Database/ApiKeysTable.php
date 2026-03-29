<?php

namespace DigitalRoyalty\Beacon\Database;

use DigitalRoyalty\Beacon\Support\Enums\Database\ApiKeyTableEnum;
use wpdb;

final class ApiKeysTable
{
    public static function tableName(wpdb $wpdb): string
    {
        return $wpdb->prefix . ApiKeyTableEnum::TABLE_SLUG;
    }

    public static function install(): void
    {
        global $wpdb;

        $installedVersion = (int) get_option(ApiKeyTableEnum::SCHEMA_VERSION_OPTION, 0);

        if ($installedVersion === ApiKeyTableEnum::SCHEMA_VERSION && self::exists($wpdb)) {
            return;
        }

        self::createOrUpdateTable($wpdb);

        update_option(ApiKeyTableEnum::SCHEMA_VERSION_OPTION, ApiKeyTableEnum::SCHEMA_VERSION, true);
    }

    private static function exists(wpdb $wpdb): bool
    {
        $table  = self::tableName($wpdb);
        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return is_string($result) && $result === $table;
    }

    private static function createOrUpdateTable(wpdb $wpdb): void
    {
        $table         = self::tableName($wpdb);
        $charsetCollate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "
            CREATE TABLE {$table} (
                id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name           VARCHAR(120) NOT NULL,
                key_hash       VARCHAR(64)  NOT NULL,
                key_prefix     VARCHAR(20)  NOT NULL,
                is_active      TINYINT(1)   NOT NULL DEFAULT 1,
                max_concurrent INT(5)  UNSIGNED NOT NULL DEFAULT 1,
                hourly_limit   INT(10) UNSIGNED NOT NULL DEFAULT 60,
                daily_limit    INT(10) UNSIGNED NOT NULL DEFAULT 500,
                last_used_at   DATETIME NULL,
                created_at     DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY key_hash_idx (key_hash),
                KEY is_active_idx (is_active)
            ) {$charsetCollate};
        ";

        dbDelta($sql);
    }
}
