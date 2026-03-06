<?php
/**
 * Class autoload and shared helpers.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once TPCW_PLUGIN_PATH . 'includes/admin/class-admin.php';
require_once TPCW_PLUGIN_PATH . 'includes/admin/class-product-data.php';
require_once TPCW_PLUGIN_PATH . 'includes/frontend/class-frontend.php';
require_once TPCW_PLUGIN_PATH . 'includes/api/class-rest-controller.php';
require_once TPCW_PLUGIN_PATH . 'includes/class-pricing-service.php';
require_once TPCW_PLUGIN_PATH . 'includes/class-mock-submission-service.php';
require_once TPCW_PLUGIN_PATH . 'includes/class-order-integration.php';

/**
 * Lightweight loader utility.
 */
class TPCW_Loader {

	/**
	 * Option key for global settings.
	 */
	const OPTION_KEY = 'tpcw_settings';

	/**
	 * Meta key to enable Tradeprint products.
	 */
	const META_ENABLED = '_tpcw_enabled';

	/**
	 * Meta key for structured configuration.
	 */
	const META_CONFIG = '_tpcw_config';
}
