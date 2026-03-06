<?php
/**
 * Plugin Name: Tradeprint Configurator for WooCommerce
 * Plugin URI:  https://example.com/
 * Description: Scaffold plugin for Tradeprint product configuration with WooCommerce.
 * Version:     1.0.1
 * Author:      Tradeprint
 * License:     GPL-2.0+
 * Text Domain: tradeprint-configurator
 * Domain Path: /languages
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TPCW_VERSION', '1.0.1' );
define( 'TPCW_PLUGIN_FILE', __FILE__ );
define( 'TPCW_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'TPCW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once TPCW_PLUGIN_PATH . 'includes/class-loader.php';

/**
 * Activate plugin.
 *
 * @return void
 */
function tpcw_activate_plugin() {
	if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		deactivate_plugins( plugin_basename( TPCW_PLUGIN_FILE ) );

		wp_die(
			esc_html__( 'Tradeprint Configurator for WooCommerce requires WooCommerce to be installed and active.', 'tradeprint-configurator' ),
			esc_html__( 'Plugin dependency check failed', 'tradeprint-configurator' ),
			array(
				'back_link' => true,
			)
		);
	}
}
register_activation_hook( TPCW_PLUGIN_FILE, 'tpcw_activate_plugin' );

/**
 * Plugin bootstrap.
 */
final class TPCW_Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var TPCW_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Main loader.
	 *
	 * @var TPCW_Loader
	 */
	private $loader;

	/**
	 * Singleton access.
	 *
	 * @return TPCW_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->loader = new TPCW_Loader();
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}

	/**
	 * Initialize plugin services.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_required_notice' ) );
			return;
		}

		new TPCW_Admin( $this->loader );
		new TPCW_Product_Data( $this->loader );
		new TPCW_Frontend( $this->loader );
		new TPCW_REST_Controller( $this->loader );
		new TPCW_Order_Integration( $this->loader );
	}

	/**
	 * Check WooCommerce dependency.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Display admin notice when WooCommerce is missing.
	 *
	 * @return void
	 */
	public function woocommerce_required_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Tradeprint Configurator for WooCommerce requires WooCommerce to be active.', 'tradeprint-configurator' );
		echo '</p></div>';
	}
}

TPCW_Plugin::instance();
