<?php
/**
 * Plugin Name: SwiftPrint Configurator & Pricing Engine for WooCommerce
 * Description: High-performance print configurator with React UI, instant pricing, and server-validated quote tokens.
 * Version: 1.0.0
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Author: SwiftPrint
 * Text Domain: swiftprint-configurator
 */

defined('ABSPATH') || exit;

define('SWIFTPRINT_VERSION', '1.0.0');
define('SWIFTPRINT_PLUGIN_FILE', __FILE__);
define('SWIFTPRINT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SWIFTPRINT_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once SWIFTPRINT_PLUGIN_DIR . 'includes/class-swiftprint-plugin.php';

add_action('plugins_loaded', static function () {
    if (! class_exists('WooCommerce')) {
        return;
    }

    \SwiftPrint\Plugin::instance()->boot();
});

register_activation_hook(__FILE__, static function () {
    require_once SWIFTPRINT_PLUGIN_DIR . 'includes/class-swiftprint-installer.php';
    \SwiftPrint\Installer::install();
});
