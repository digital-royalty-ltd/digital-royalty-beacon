<?php

namespace DigitalRoyalty\Beacon\Database;

use DigitalRoyalty\Beacon\Support\Enums\Database\DeferredRequestsTableEnum;
use wpdb;

final class DeferredRequestsTable
{
    public static function tableName(wpdb $wpdb): string
    {
        return $wpdb->prefix . DeferredRequestsTableEnum::TABLE_SLUG;
    }

    public static function install(): void
    {
        global $wpdb;

        $installedVersion = (int) get_option(DeferredRequestsTableEnum::SCHEMA_VERSION_OPTION, 0);

        if ($installedVersion === DeferredRequestsTableEnum::SCHEMA_VERSION && self::exists($wpdb)) {
            return;
        }

        self::createOrUpdateTable($wpdb);

        update_option(DeferredRequestsTableEnum::SCHEMA_VERSION_OPTION, DeferredRequestsTableEnum::SCHEMA_VERSION, true);
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
                request_key VARCHAR(80) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                poll_path TEXT NOT NULL,
                external_id VARCHAR(128) NULL,
                payload LONGTEXT NULL,
                attempts SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
                next_attempt_at DATETIME NOT NULL,
                last_error TEXT NULL,
                result LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY status_next_attempt_idx (status, next_attempt_at),
                KEY request_key_idx (request_key),
                KEY external_id_idx (external_id)
            ) {$charsetCollate};
        ";

        dbDelta($sql);
    }
}
