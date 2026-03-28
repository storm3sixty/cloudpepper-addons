<?php

declare(strict_types=1);

namespace SwiftPrint;

defined('ABSPATH') || exit;

final class Product {
    public function __construct(private Repository $repository) {}

    public function hooks(): void {
        add_action('woocommerce_product_options_general_product_data', [$this, 'render_product_flag']);
        add_action('woocommerce_product_options_general_product_data', [$this, 'render_edit_link']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_flag']);
        add_action('woocommerce_before_add_to_cart_form', [$this, 'render_configurator_root']);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 5);
        add_filter('woocommerce_add_cart_item_data', [$this, 'attach_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);
    }

    public function render_product_flag(): void {
        woocommerce_wp_checkbox([
            'id' => '_swiftprint_enabled',
            'label' => __('Enable SwiftPrint Configurator', 'swiftprint-configurator'),
            'description' => __('Replace standard add-to-cart with SwiftPrint configurator.', 'swiftprint-configurator'),
        ]);
    }


    public function render_edit_link(): void {
        global $post;
        if (! $post) {
            return;
        }

        $url = admin_url('admin.php?page=swiftprint&product_id=' . absint($post->ID));
        echo '<p class="form-field"><a class="button" href="' . esc_url($url) . '">' . esc_html__('Edit SwiftPrint Settings', 'swiftprint-configurator') . '</a></p>';
    }

    public function save_product_flag(int $productId): void {
        $enabled = isset($_POST['_swiftprint_enabled']) ? 'yes' : 'no';
        update_post_meta($productId, '_swiftprint_enabled', $enabled);
    }

    public function render_configurator_root(): void {
        global $product;
        if (! $product || get_post_meta($product->get_id(), '_swiftprint_enabled', true) !== 'yes') {
            return;
        }

        wp_enqueue_script(
            'swiftprint-frontend',
            SWIFTPRINT_PLUGIN_URL . 'assets/build/frontend.js',
            ['wp-element', 'wp-api-fetch'],
            SWIFTPRINT_VERSION,
            true
        );

        wp_localize_script('swiftprint-frontend', 'swiftprintFrontend', [
            'productId' => $product->get_id(),
            'schema' => $this->repository->get_set_by_product($product->get_id()),
            'restUrl' => esc_url_raw(rest_url('swiftprint/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        echo '<div id="swiftprint-configurator-root"></div>';
        echo '<input type="hidden" name="swiftprint_quote_token" id="swiftprint_quote_token" />';
        echo '<input type="hidden" name="swiftprint_selection" id="swiftprint_selection" />';
    }

    public function validate_add_to_cart(bool $passed, int $productId, int $quantity, int $variationId = 0, array $variations = []): bool {
        if (get_post_meta($productId, '_swiftprint_enabled', true) !== 'yes') {
            return $passed;
        }

        $token = sanitize_text_field((string) ($_POST['swiftprint_quote_token'] ?? ''));
        $selection = json_decode(wp_unslash((string) ($_POST['swiftprint_selection'] ?? '[]')), true);

        if (! $token || ! is_array($selection)) {
            wc_add_notice(__('SwiftPrint configuration missing.', 'swiftprint-configurator'), 'error');
            return false;
        }

        $quote = Plugin::instance(); // trigger initialization guard.
        unset($quote);

        return $passed;
    }

    public function attach_cart_item_data(array $data, int $productId, int $variationId): array {
        if (get_post_meta($productId, '_swiftprint_enabled', true) !== 'yes') {
            return $data;
        }

        $data['swiftprint_quote_token'] = sanitize_text_field((string) ($_POST['swiftprint_quote_token'] ?? ''));
        $data['swiftprint_selection'] = json_decode(wp_unslash((string) ($_POST['swiftprint_selection'] ?? '[]')), true) ?: [];
        $data['swiftprint_hash'] = md5(wp_json_encode($data['swiftprint_selection']) . $data['swiftprint_quote_token']);

        return $data;
    }

    public function display_cart_item_data(array $itemData, array $cartItem): array {
        if (empty($cartItem['swiftprint_selection'])) {
            return $itemData;
        }

        $itemData[] = [
            'name' => __('SwiftPrint Config', 'swiftprint-configurator'),
            'value' => wc_clean(wp_json_encode($cartItem['swiftprint_selection'])),
            'display' => wc_clean(wp_json_encode($cartItem['swiftprint_selection'])),
        ];

        return $itemData;
    }

    public function save_order_item_meta($item, $cartItemKey, $values, $order): void {
        if (empty($values['swiftprint_selection'])) {
            return;
        }

        $item->add_meta_data('_swiftprint_selection', wp_json_encode($values['swiftprint_selection']), true);
        if (! empty($values['swiftprint_breakdown'])) {
            $item->add_meta_data('_swiftprint_breakdown', wp_json_encode($values['swiftprint_breakdown']), true);
        }
    }
}
