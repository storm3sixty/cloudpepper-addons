<?php

declare(strict_types=1);

namespace SwiftPrint;

defined('ABSPATH') || exit;

final class Cart {
    public function __construct(private Quote_Service $quoteService) {}

    public function hooks(): void {
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_quote'], 20, 5);
        add_action('woocommerce_before_calculate_totals', [$this, 'apply_swiftprint_price']);
        add_filter('woocommerce_cart_item_name', [$this, 'cart_item_name'], 10, 3);
        add_action('woocommerce_order_again_cart_item_data', [$this, 'order_again'], 10, 3);
    }

    public function validate_quote(bool $passed, int $productId, int $quantity, int $variationId = 0, array $variations = []): bool {
        if (get_post_meta($productId, '_swiftprint_enabled', true) !== 'yes') {
            return $passed;
        }

        $token = sanitize_text_field((string) ($_POST['swiftprint_quote_token'] ?? ''));
        $selection = json_decode(wp_unslash((string) ($_POST['swiftprint_selection'] ?? '[]')), true);
        if (! $token || ! is_array($selection)) {
            wc_add_notice(__('SwiftPrint quote is required.', 'swiftprint-configurator'), 'error');
            return false;
        }

        $result = $this->quoteService->verify_quote_token($token, $productId, $selection);
        if (is_wp_error($result)) {
            wc_add_notice($result->get_error_message(), 'error');
            return false;
        }

        WC()->session->set('swiftprint_pending_' . $productId, $result);

        return $passed;
    }

    public function apply_swiftprint_price($cart): void {
        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $key => $item) {
            if (empty($item['swiftprint_quote_token']) || empty($item['swiftprint_selection'])) {
                continue;
            }

            $productId = (int) $item['product_id'];
            $verified = $this->quoteService->verify_quote_token((string) $item['swiftprint_quote_token'], $productId, (array) $item['swiftprint_selection']);
            if (is_wp_error($verified)) {
                continue;
            }

            $unit = ((float) $verified['total']) / max(1, (int) ($item['quantity'] ?? 1));
            $item['data']->set_price($unit);
            $cart->cart_contents[$key]['swiftprint_breakdown'] = $verified['breakdown'];
        }
    }

    public function cart_item_name(string $name, array $cartItem, string $cartItemKey): string {
        if (empty($cartItem['swiftprint_selection'])) {
            return $name;
        }

        return $name . '<br/><small>' . esc_html__('Configured with SwiftPrint', 'swiftprint-configurator') . '</small>';
    }

    public function order_again(array $cartItemData, array $item, $order): array {
        $selection = $item->get_meta('_swiftprint_selection');
        if ($selection) {
            $cartItemData['swiftprint_selection'] = json_decode((string) $selection, true);
        }
        return $cartItemData;
    }
}
