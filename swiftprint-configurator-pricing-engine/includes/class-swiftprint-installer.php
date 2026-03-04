<?php

declare(strict_types=1);

namespace SwiftPrint;

defined('ABSPATH') || exit;

final class Installer {
    public const DB_VERSION = 1;

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix . 'swiftprint_';

        $sql = [];
        $sql[] = "CREATE TABLE {$prefix}base_price_sets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(191) NOT NULL,
            pricing_mode VARCHAR(10) NOT NULL DEFAULT 'LOOKUP',
            is_static TINYINT(1) NOT NULL DEFAULT 0,
            schema_version INT UNSIGNED NOT NULL DEFAULT 1,
            quantity_settings LONGTEXT NULL,
            print_modes LONGTEXT NULL,
            standard_sizes LONGTEXT NULL,
            custom_size LONGTEXT NULL,
            turnarounds LONGTEXT NULL,
            option_groups LONGTEXT NULL,
            smart_triggers LONGTEXT NULL,
            discount_enabled TINYINT(1) NOT NULL DEFAULT 0,
            discount_type VARCHAR(10) NULL,
            discount_value DECIMAL(12,4) NOT NULL DEFAULT 0,
            weight_value DECIMAL(12,4) NOT NULL DEFAULT 0,
            weight_per VARCHAR(10) NOT NULL DEFAULT 'UNIT',
            ship_box_count INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL,
            KEY product_id (product_id)
        ) $charset";

        $sql[] = "CREATE TABLE {$prefix}pricing_rows (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            base_price_set_id BIGINT UNSIGNED NOT NULL,
            size_id VARCHAR(80) NOT NULL,
            print_mode_key VARCHAR(80) NOT NULL,
            quantity_break INT UNSIGNED NOT NULL,
            total_price DECIMAL(12,4) NOT NULL,
            UNIQUE KEY uniq_key (base_price_set_id, size_id, print_mode_key, quantity_break),
            KEY set_id (base_price_set_id)
        ) $charset";

        $sql[] = "CREATE TABLE {$prefix}quote_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token_hash CHAR(64) NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            payload LONGTEXT NOT NULL,
            total DECIMAL(12,4) NOT NULL,
            currency VARCHAR(10) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            KEY token_hash (token_hash),
            KEY expires_at (expires_at)
        ) $charset";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option('swiftprint_db_version', self::DB_VERSION);
    }

    public static function maybe_upgrade(): void {
        $current = (int) get_option('swiftprint_db_version', 0);
        if ($current < self::DB_VERSION) {
            self::install();
        }
    }
}
