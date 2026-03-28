<?php

declare(strict_types=1);

namespace SwiftPrint;

defined('ABSPATH') || exit;

final class Admin {
    private string $pageHookSuffix = '';

    public function __construct(private Repository $repository) {}

    public function hooks(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void {
        $capability = current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';

        $this->pageHookSuffix = (string) add_submenu_page(
            'woocommerce',
            __('SwiftPrint', 'swiftprint-configurator'),
            __('SwiftPrint', 'swiftprint-configurator'),
            $capability,
            'swiftprint',
            [$this, 'render_page']
        );
    }

    public function render_page(): void {
        if (! current_user_can('manage_woocommerce') && ! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access SwiftPrint settings.', 'swiftprint-configurator'));
        }

        echo '<div class="wrap"><h1>' . esc_html__('SwiftPrint Settings', 'swiftprint-configurator') . '</h1></div>';
        echo '<div id="swiftprint-admin-root"></div>';
    }

    public function enqueue(string $hook): void {
        if ($hook !== 'woocommerce_page_swiftprint' && $hook !== 'toplevel_page_swiftprint') {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SwiftPrint] admin_enqueue_scripts hook: ' . $hook . '; registered page: ' . $this->pageHookSuffix);
        }

        $assetFile = SWIFTPRINT_PLUGIN_DIR . 'assets/build/admin.asset.php';
        $asset = [
            'dependencies' => ['wp-element', 'wp-components', 'wp-api-fetch'],
            'version' => SWIFTPRINT_VERSION,
        ];

        if (file_exists($assetFile)) {
            $loadedAsset = require $assetFile;
            if (is_array($loadedAsset)) {
                $asset = array_merge($asset, $loadedAsset);
            }
        }

        wp_enqueue_script(
            'swiftprint-admin',
            plugins_url('assets/build/admin.js', SWIFTPRINT_PLUGIN_FILE),
            $asset['dependencies'],
            (string) ($asset['version'] ?? SWIFTPRINT_VERSION),
            true
        );

        $stylePath = SWIFTPRINT_PLUGIN_DIR . 'assets/build/admin.css';
        if (file_exists($stylePath)) {
            wp_enqueue_style(
                'swiftprint-admin',
                plugins_url('assets/build/admin.css', SWIFTPRINT_PLUGIN_FILE),
                [],
                (string) ($asset['version'] ?? SWIFTPRINT_VERSION)
            );
        }

        wp_localize_script('swiftprint-admin', 'SwiftPrintAdmin', [
            'restUrl' => esc_url_raw(rest_url('swiftprint/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'pluginUrl' => esc_url_raw(SWIFTPRINT_PLUGIN_URL),
            'version' => SWIFTPRINT_VERSION,
            'initialProductId' => isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0,
        ]);
    }
}
