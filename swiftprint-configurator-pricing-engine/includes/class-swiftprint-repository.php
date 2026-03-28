<?php

declare(strict_types=1);

namespace SwiftPrint;

defined('ABSPATH') || exit;

final class Repository {
    public function get_set_by_product(int $productId): ?array {
        return $this->get_schema_for_product($productId);
    }

    public function save_set(int $productId, array $data): int {
        $this->save_schema_for_product($productId, $data);

        return $productId;
    }

    public function get_schema_for_product(int $productId): ?array {
        if ($productId <= 0) {
            return null;
        }

        $raw = get_post_meta($productId, '_swiftprint_schema', true);
        $schema = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (! is_array($schema)) {
            $schema = [];
        }

        $version = (int) get_post_meta($productId, '_swiftprint_schema_version', true);
        if ($version < 1) {
            $version = 1;
        }

        $enabled = get_post_meta($productId, '_swiftprint_enabled', true) === 'yes';

        $schema = $this->with_defaults($schema);
        $schema['product_id'] = $productId;
        $schema['schema_version'] = $version;
        $schema['enabled'] = $enabled;

        return $schema;
    }

    public function save_schema_for_product(int $productId, array $schema): array {
        $existingVersion = (int) get_post_meta($productId, '_swiftprint_schema_version', true);
        $nextVersion = max(1, $existingVersion + 1);

        $normalized = $this->with_defaults($schema);
        $normalized['schema_version'] = $nextVersion;

        update_post_meta($productId, '_swiftprint_schema', wp_json_encode($normalized));
        update_post_meta($productId, '_swiftprint_schema_version', $nextVersion);

        if (isset($schema['enabled'])) {
            update_post_meta($productId, '_swiftprint_enabled', ! empty($schema['enabled']) ? 'yes' : 'no');
        }

        $normalized['product_id'] = $productId;
        $normalized['enabled'] = get_post_meta($productId, '_swiftprint_enabled', true) === 'yes';

        return $normalized;
    }

    public function search_products(string $search = '', int $limit = 50): array {
        $args = [
            'status' => ['publish', 'draft'],
            'limit' => max(1, min(200, $limit)),
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
        }

        $products = wc_get_products($args);

        return array_map(static function ($p) {
            return [
                'id' => $p->get_id(),
                'name' => $p->get_name(),
                'enabled' => get_post_meta($p->get_id(), '_swiftprint_enabled', true) === 'yes',
                'schema_version' => (int) get_post_meta($p->get_id(), '_swiftprint_schema_version', true),
            ];
        }, $products);
    }

    private function with_defaults(array $schema): array {
        return wp_parse_args($schema, [
            'name' => 'Default',
            'pricing_mode' => 'LOOKUP',
            'enabled' => false,
            'discount_enabled' => false,
            'discount_type' => 'FIXED',
            'discount_value' => 0,
            'weight_value' => 0,
            'weight_per' => 'UNIT',
            'same_day_timezone' => wp_timezone_string() ?: 'UTC',
            'quantity_settings' => [
                'display_type' => 'TEXTBOX',
                'breaks' => [25, 50, 100],
                'min_qty' => 1,
                'max_qty' => 100000,
                'step' => 1,
            ],
            'print_modes' => [
                'sides' => 'SINGLE',
                'allow_full_colour' => true,
                'allow_bw' => true,
                'allow_mixed_front_back' => false,
                'keys' => ['SIMPLE_S1'],
            ],
            'standard_sizes' => [],
            'custom_size' => [
                'enabled' => false,
                'min_w' => 0,
                'max_w' => 0,
                'min_h' => 0,
                'max_h' => 0,
                'step' => 1,
                'area_unit' => 'sqmm',
                'area_ranges' => [],
            ],
            'turnarounds' => [],
            'option_groups' => [],
            'smart_triggers' => [],
            'pricing_rows' => [],
        ]);
    }
}
