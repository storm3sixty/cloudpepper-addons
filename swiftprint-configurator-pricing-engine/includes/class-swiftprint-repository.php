<?php

declare(strict_types=1);

namespace SwiftPrint;

use wpdb;

defined('ABSPATH') || exit;

final class Repository {
    private wpdb $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    public function get_set_by_product(int $productId): ?array {
        $cacheKey = "swiftprint_schema_{$productId}";
        $cached   = wp_cache_get($cacheKey, 'swiftprint');
        if (is_array($cached)) {
            return $cached;
        }

        $table = $this->db->prefix . 'swiftprint_base_price_sets';
        $row   = $this->db->get_row($this->db->prepare("SELECT * FROM $table WHERE product_id = %d ORDER BY id DESC LIMIT 1", $productId), ARRAY_A);
        if (! $row) {
            return null;
        }

        $row['quantity_settings'] = $this->decode($row['quantity_settings']);
        $row['print_modes']       = $this->decode($row['print_modes']);
        $row['standard_sizes']    = $this->decode($row['standard_sizes']);
        $row['custom_size']       = $this->decode($row['custom_size']);
        $row['turnarounds']       = $this->decode($row['turnarounds']);
        $row['option_groups']     = $this->decode($row['option_groups']);
        $row['smart_triggers']    = $this->decode($row['smart_triggers']);
        $row['pricing_rows']      = $this->get_pricing_rows((int) $row['id']);

        wp_cache_set($cacheKey, $row, 'swiftprint', HOUR_IN_SECONDS);

        return $row;
    }

    public function save_set(int $productId, array $data): int {
        $table = $this->db->prefix . 'swiftprint_base_price_sets';

        $record = [
            'product_id'         => $productId,
            'name'               => sanitize_text_field((string) ($data['name'] ?? 'Default')),
            'pricing_mode'       => sanitize_text_field((string) ($data['pricing_mode'] ?? 'LOOKUP')),
            'is_static'          => ! empty($data['is_static']) ? 1 : 0,
            'schema_version'     => (int) ($data['schema_version'] ?? 1),
            'quantity_settings'  => wp_json_encode($data['quantity_settings'] ?? []),
            'print_modes'        => wp_json_encode($data['print_modes'] ?? []),
            'standard_sizes'     => wp_json_encode($data['standard_sizes'] ?? []),
            'custom_size'        => wp_json_encode($data['custom_size'] ?? []),
            'turnarounds'        => wp_json_encode($data['turnarounds'] ?? []),
            'option_groups'      => wp_json_encode($data['option_groups'] ?? []),
            'smart_triggers'     => wp_json_encode($data['smart_triggers'] ?? []),
            'discount_enabled'   => ! empty($data['discount_enabled']) ? 1 : 0,
            'discount_type'      => sanitize_text_field((string) ($data['discount_type'] ?? 'FIXED')),
            'discount_value'     => (float) ($data['discount_value'] ?? 0),
            'weight_value'       => (float) ($data['weight_value'] ?? 0),
            'weight_per'         => sanitize_text_field((string) ($data['weight_per'] ?? 'UNIT')),
            'ship_box_count'     => isset($data['ship_box_count']) ? (int) $data['ship_box_count'] : null,
            'updated_at'         => current_time('mysql'),
        ];

        $existing = $this->get_set_by_product($productId);
        if ($existing) {
            $this->db->update($table, $record, ['id' => (int) $existing['id']]);
            $setId = (int) $existing['id'];
        } else {
            $this->db->insert($table, $record);
            $setId = (int) $this->db->insert_id;
        }

        $this->replace_pricing_rows($setId, $data['pricing_rows'] ?? []);
        wp_cache_delete("swiftprint_schema_{$productId}", 'swiftprint');

        return $setId;
    }

    public function get_pricing_rows(int $setId): array {
        $table = $this->db->prefix . 'swiftprint_pricing_rows';
        $rows = $this->db->get_results($this->db->prepare("SELECT size_id, print_mode_key, quantity_break, total_price FROM $table WHERE base_price_set_id = %d", $setId), ARRAY_A);
        return $rows ?: [];
    }

    private function replace_pricing_rows(int $setId, array $rows): void {
        $table = $this->db->prefix . 'swiftprint_pricing_rows';
        $this->db->delete($table, ['base_price_set_id' => $setId]);

        foreach ($rows as $row) {
            if (! isset($row['size_id'], $row['print_mode_key'], $row['quantity_break'], $row['total_price'])) {
                continue;
            }
            $this->db->insert($table, [
                'base_price_set_id' => $setId,
                'size_id' => sanitize_text_field((string) $row['size_id']),
                'print_mode_key' => sanitize_text_field((string) $row['print_mode_key']),
                'quantity_break' => max(1, (int) $row['quantity_break']),
                'total_price' => (float) $row['total_price'],
            ]);
        }
    }

    private function decode(?string $raw): array {
        if (! $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
