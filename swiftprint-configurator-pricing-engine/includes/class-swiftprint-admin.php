<?php

declare(strict_types=1);

namespace SwiftPrint;

defined('ABSPATH') || exit;

final class Admin {
    public function __construct(private Repository $repository) {}

    public function hooks(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void {
        add_submenu_page(
            'woocommerce',
            __('SwiftPrint', 'swiftprint-configurator'),
            __('SwiftPrint', 'swiftprint-configurator'),
            'manage_woocommerce',
            'swiftprint',
            [$this, 'render_page']
        );
    }

    public function render_page(): void {
        echo '<div class="wrap"><h1>SwiftPrint Configurator</h1><div id="swiftprint-admin-root"></div></div>';
    }

    public function enqueue(string $hook): void {
        if ($hook !== 'woocommerce_page_swiftprint') {
            return;
        }

        wp_enqueue_script(
            'swiftprint-admin',
            SWIFTPRINT_PLUGIN_URL . 'assets/build/admin.js',
            ['wp-element', 'wp-components', 'wp-api-fetch'],
            SWIFTPRINT_VERSION,
            true
        );

        wp_enqueue_style(
            'swiftprint-admin',
            SWIFTPRINT_PLUGIN_URL . 'assets/build/admin.css',
            [],
            SWIFTPRINT_VERSION
        );

        wp_localize_script('swiftprint-admin', 'swiftprintAdmin', [
            'restUrl' => esc_url_raw(rest_url('swiftprint/v1/admin')),
            'nonce' => wp_create_nonce('wp_rest'),
            'products' => $this->products(),
        ]);
    }

    private function products(): array {
        $products = wc_get_products(['status' => ['publish', 'draft'], 'limit' => 200]);
        return array_map(static fn ($p) => ['id' => $p->get_id(), 'name' => $p->get_name()], $products);
    }
}
