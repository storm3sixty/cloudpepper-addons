<?php
/**
 * REST API placeholder endpoints.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller.
 */
class TPCW_REST_Controller {

	/**
	 * Namespace.
	 */
	const NAMESPACE = 'tradeprint-configurator/v1';

	/**
	 * Pricing resolver service.
	 *
	 * @var TPCW_Pricing_Service
	 */
	private $pricing_service;

	/**
	 * Constructor.
	 *
	 * @param TPCW_Loader $loader Loader.
	 */
	public function __construct( TPCW_Loader $loader ) {
		$this->pricing_service = new TPCW_Pricing_Service();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register placeholder routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/config/(?P<product_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/price',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_price' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/preflight',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'post_preflight' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return mock config.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_config( WP_REST_Request $request ) {
		$product_id = absint( $request->get_param( 'product_id' ) );
		$config     = get_post_meta( $product_id, TPCW_Loader::META_CONFIG, true );
		$config     = is_array( $config ) ? $config : array();

		return new WP_REST_Response(
			array(
				'product_id'     => $product_id,
				'enabled'        => 'yes' === get_post_meta( $product_id, TPCW_Loader::META_ENABLED, true ),
				'pricing_mode'   => get_post_meta( $product_id, '_tpcw_pricing_display_mode', true ),
				'attributes'     => isset( $config['attributes'] ) ? $config['attributes'] : array(),
				'extra_services' => isset( $config['extra_services'] ) ? $config['extra_services'] : array(),
				'matrix'         => isset( $config['matrix'] ) ? $config['matrix'] : array(),
				'message'        => 'Mock config payload from saved product meta only.',
			),
			200
		);
	}

	/**
	 * Resolve price response from saved product meta.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function post_price( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : $request->get_params();
		$params = is_array( $params ) ? $params : array();

		$result = $this->pricing_service->resolve_price( $params );
		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data( 'status' );
			$status = $status ? absint( $status ) : 400;
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				$status
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Return mock preflight response.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function post_preflight( WP_REST_Request $request ) {
		return new WP_REST_Response(
			array(
				'valid'   => true,
				'errors'  => array(),
				'warning' => 'Mock preflight only. Attach real validation and stock checks later.',
			),
			200
		);
	}
}
