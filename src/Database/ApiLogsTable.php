<?php

namespace DigitalRoyalty\Beacon\Database;

use DigitalRoyalty\Beacon\Support\Enums\Database\ApiLogTableEnum;
use wpdb;

final class ApiLogsTable
{
    public static function tableName(wpdb $wpdb): string
    {
        return $wpdb->prefix . ApiLogTableEnum::TABLE_SLUG;
    }

    public static function install(): void
    {
        global $wpdb;

        $installedVersion = (int) get_option(ApiLogTableEnum::SCHEMA_VERSION_OPTION, 0);

        if ($installedVersion === ApiLogTableEnum::SCHEMA_VERSION && self::exists($wpdb)) {
            return;
        }

        self::createOrUpdateTable($wpdb);

        update_option(ApiLogTableEnum::SCHEMA_VERSION_OPTION, ApiLogTableEnum::SCHEMA_VERSION, true);
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
                id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                api_key_id       BIGINT(20) UNSIGNED NOT NULL,
                endpoint_key     VARCHAR(80)  NULL,
                method           VARCHAR(10)  NOT NULL,
                path             VARCHAR(255) NOT NULL,
                status_code      SMALLINT(5)  UNSIGNED NOT NULL,
                response_time_ms INT(10)      UNSIGNED NULL,
                ip_address       VARCHAR(45)  NULL,
                created_at       DATETIME     NOT NULL,
                PRIMARY KEY (id),
                KEY api_key_id_idx (api_key_id),
                KEY api_key_created_idx (api_key_id, created_at),
                KEY created_at_idx (created_at)
            ) {$charsetCollate};
        ";

        dbDelta($sql);
    }
}
