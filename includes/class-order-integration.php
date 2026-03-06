<?php
/**
 * WooCommerce cart/order integration for Tradeprint.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order integration service.
 */
class TPCW_Order_Integration {

	/**
	 * Cart item payload key.
	 */
	const CART_KEY = 'tpcw_config';

	/**
	 * Item meta keys.
	 */
	const META_CONFIG = '_tpcw_config_payload';
	const META_MODE = '_tpcw_order_mode';
	const META_STATUS = '_tpcw_submission_status';
	const META_EXTERNAL_ID = '_tpcw_external_order_id';
	const META_SUBMITTED_AT = '_tpcw_submitted_at';
	const META_MESSAGE = '_tpcw_submission_message';
	const META_IS_TPCW = '_tpcw_is_tradeprint';
	const META_PRODUCT_KEY = '_tpcw_product_key';

	/**
	 * Submission statuses.
	 */
	const STATUS_NOT_APPLICABLE = 'not_applicable';
	const STATUS_PENDING_MANUAL = 'pending_manual';
	const STATUS_READY_FOR_AUTO = 'ready_for_auto';
	const STATUS_SUBMITTED = 'submitted';
	const STATUS_FAILED = 'failed';

	/**
	 * Pricing service.
	 *
	 * @var TPCW_Pricing_Service
	 */
	private $pricing_service;

	/**
	 * Mock submission service.
	 *
	 * @var TPCW_Mock_Submission_Service
	 */
	private $submission_service;

	/**
	 * Constructor.
	 *
	 * @param TPCW_Loader $loader Loader.
	 */
	public function __construct( TPCW_Loader $loader ) {
		$this->pricing_service    = new TPCW_Pricing_Service();
		$this->submission_service = new TPCW_Mock_Submission_Service();

		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 5 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'auto_submit_order_items' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_order_metabox' ) );
		add_action( 'admin_post_tpcw_manual_send', array( $this, 'handle_manual_send' ) );
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'render_admin_item_meta' ), 10, 3 );
	}

	/**
	 * Validate add-to-cart payload.
	 *
	 * @param bool   $passed Passed flag.
	 * @param int    $product_id Product ID.
	 * @param int    $quantity Quantity.
	 * @param int    $variation_id Variation ID.
	 * @param array  $variations Variations.
	 *
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		if ( 'yes' !== get_post_meta( $product_id, TPCW_Loader::META_ENABLED, true ) ) {
			return $passed;
		}

		$payload = $this->get_posted_config_payload();
		if ( empty( $payload ) ) {
			wc_add_notice( __( 'Please configure your Tradeprint options before adding to cart.', 'tradeprint-configurator' ), 'error' );
			return false;
		}

		$config = get_post_meta( $product_id, TPCW_Loader::META_CONFIG, true );
		$config = is_array( $config ) ? $config : array();

		if ( ! $this->validate_required_attributes( $config, isset( $payload['selected_attributes'] ) ? $payload['selected_attributes'] : array() ) ) {
			wc_add_notice( __( 'Please select all required Tradeprint options.', 'tradeprint-configurator' ), 'error' );
			return false;
		}

		$matrix_settings = isset( $config['matrix']['settings'] ) && is_array( $config['matrix']['settings'] ) ? $config['matrix']['settings'] : array();
		if ( 'yes' === ( isset( $matrix_settings['enabled'] ) ? $matrix_settings['enabled'] : 'no' ) ) {
			if ( empty( $payload['selected_quantity'] ) && empty( $payload['custom_quantity'] ) ) {
				wc_add_notice( __( 'Please select a quantity from the pricing matrix.', 'tradeprint-configurator' ), 'error' );
				return false;
			}
			if ( empty( $payload['selected_service'] ) && empty( $payload['custom_quantity'] ) ) {
				wc_add_notice( __( 'Please select a delivery service option.', 'tradeprint-configurator' ), 'error' );
				return false;
			}
		}

		$pricing_input = array(
			'product_id'               => $product_id,
			'selected_attributes'      => isset( $payload['selected_attributes'] ) ? $payload['selected_attributes'] : array(),
			'selected_service'         => isset( $payload['selected_service'] ) ? $payload['selected_service'] : '',
			'selected_quantity'        => isset( $payload['selected_quantity'] ) ? $payload['selected_quantity'] : 0,
			'custom_quantity'          => isset( $payload['custom_quantity'] ) ? $payload['custom_quantity'] : 0,
			'selected_extra_services'  => isset( $payload['selected_extra_services'] ) ? $payload['selected_extra_services'] : array(),
		);
		$resolved = $this->pricing_service->resolve_price( $pricing_input );
		if ( is_wp_error( $resolved ) ) {
			wc_add_notice( $resolved->get_error_message(), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Add Tradeprint payload to cart item data.
	 *
	 * @param array $cart_item_data Existing cart item data.
	 * @param int   $product_id Product ID.
	 * @param int   $variation_id Variation ID.
	 *
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( 'yes' !== get_post_meta( $product_id, TPCW_Loader::META_ENABLED, true ) ) {
			return $cart_item_data;
		}

		$payload = $this->sanitize_cart_payload( $this->get_posted_config_payload(), $product_id );
		if ( empty( $payload ) ) {
			return $cart_item_data;
		}

		$cart_item_data[ self::CART_KEY ] = $payload;
		$cart_item_data['unique_key']     = md5( wp_json_encode( $payload ) . '|' . microtime( true ) );

		return $cart_item_data;
	}

	/**
	 * Display readable Tradeprint data in cart/checkout.
	 *
	 * @param array $item_data Item data.
	 * @param array $cart_item Cart item.
	 *
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) || ! is_array( $cart_item[ self::CART_KEY ] ) ) {
			return $item_data;
		}

		$config = $cart_item[ self::CART_KEY ];
		if ( ! empty( $config['selected_attributes'] ) && is_array( $config['selected_attributes'] ) ) {
			foreach ( $config['selected_attributes'] as $key => $value ) {
				$item_data[] = array(
					'key'   => wc_clean( ucwords( str_replace( '_', ' ', (string) $key ) ) ),
					'value' => wc_clean( (string) $value ),
				);
			}
		}

		if ( ! empty( $config['selected_quantity'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Tradeprint Quantity', 'tradeprint-configurator' ),
				'value' => wc_clean( (string) $config['selected_quantity'] ),
			);
		}

		if ( ! empty( $config['selected_service'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Tradeprint Service', 'tradeprint-configurator' ),
				'value' => wc_clean( (string) $config['selected_service'] ),
			);
		}

		if ( ! empty( $config['custom_quantity'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Custom Quantity', 'tradeprint-configurator' ),
				'value' => wc_clean( (string) $config['custom_quantity'] ),
			);
		}

		if ( ! empty( $config['selected_extra_services'] ) && is_array( $config['selected_extra_services'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Extra Services', 'tradeprint-configurator' ),
				'value' => wc_clean( implode( ', ', $config['selected_extra_services'] ) ),
			);
		}

		return $item_data;
	}

	/**
	 * Add order line item meta.
	 *
	 * @param WC_Order_Item_Product $item Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values Cart item values.
	 * @param WC_Order              $order Order.
	 *
	 * @return void
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		$payload = isset( $values[ self::CART_KEY ] ) && is_array( $values[ self::CART_KEY ] ) ? $values[ self::CART_KEY ] : array();
		if ( empty( $payload ) ) {
			$item->add_meta_data( self::META_IS_TPCW, 'no', true );
			$item->add_meta_data( self::META_STATUS, self::STATUS_NOT_APPLICABLE, true );
			return;
		}

		$mode = $this->resolve_order_mode_for_product( $item->get_product_id() );

		$item->add_meta_data( self::META_IS_TPCW, 'yes', true );
		$item->add_meta_data( self::META_CONFIG, $payload, true );
		$item->add_meta_data( self::META_PRODUCT_KEY, isset( $payload['tradeprint_product_key'] ) ? $payload['tradeprint_product_key'] : '', true );
		$item->add_meta_data( self::META_MODE, $mode, true );
		$item->add_meta_data( self::META_STATUS, 'manual' === $mode ? self::STATUS_PENDING_MANUAL : self::STATUS_READY_FOR_AUTO, true );

		$item->add_meta_data( __( 'Tradeprint Mode', 'tradeprint-configurator' ), $mode, true );
		if ( ! empty( $payload['selected_service'] ) ) {
			$item->add_meta_data( __( 'Tradeprint Service', 'tradeprint-configurator' ), $payload['selected_service'], true );
		}
		if ( ! empty( $payload['selected_quantity'] ) ) {
			$item->add_meta_data( __( 'Tradeprint Quantity', 'tradeprint-configurator' ), $payload['selected_quantity'], true );
		}
	}

	/**
	 * Auto-submit eligible Tradeprint items when order enters processing.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public function auto_submit_order_items( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( 'yes' !== $item->get_meta( self::META_IS_TPCW, true ) ) {
				continue;
			}

			$status = $item->get_meta( self::META_STATUS, true );
			$mode   = $item->get_meta( self::META_MODE, true );
			if ( self::STATUS_SUBMITTED === $status || 'auto' !== $mode ) {
				continue;
			}

			$this->submit_item_and_persist( $order, $item, 'auto' );
		}

		$order->save();
	}

	/**
	 * Register order metabox.
	 *
	 * @return void
	 */
	public function register_order_metabox() {
		add_meta_box(
			'tpcw-tradeprint-order',
			esc_html__( 'Tradeprint Submission', 'tradeprint-configurator' ),
			array( $this, 'render_order_metabox' ),
			'shop_order',
			'side',
			'default'
		);
	}

	/**
	 * Render order metabox.
	 *
	 * @param WP_Post $post Order post.
	 *
	 * @return void
	 */
	public function render_order_metabox( $post ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$order = wc_get_order( $post->ID );
		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order unavailable.', 'tradeprint-configurator' ) . '</p>';
			return;
		}

		$has_manual = false;
		echo '<ul>';
		foreach ( $order->get_items() as $item ) {
			if ( 'yes' !== $item->get_meta( self::META_IS_TPCW, true ) ) {
				continue;
			}
			$mode   = $item->get_meta( self::META_MODE, true );
			$status = $item->get_meta( self::META_STATUS, true );
			echo '<li>' . esc_html( $item->get_name() . ': ' . $status . ' (' . $mode . ')' ) . '</li>';
			if ( 'manual' === $mode && self::STATUS_SUBMITTED !== $status ) {
				$has_manual = true;
			}
		}
		echo '</ul>';

		if ( $has_manual ) {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=tpcw_manual_send&order_id=' . absint( $order->get_id() ) ),
				'tpcw_manual_send_' . $order->get_id()
			);
			echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Send Tradeprint Items', 'tradeprint-configurator' ) . '</a></p>';
		}
	}

	/**
	 * Manual send handler.
	 *
	 * @return void
	 */
	public function handle_manual_send() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'tradeprint-configurator' ) );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( ! $order_id || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'tpcw_manual_send_' . $order_id ) ) {
			wp_die( esc_html__( 'Invalid manual submission request.', 'tradeprint-configurator' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
			exit;
		}

		$submitted = 0;
		foreach ( $order->get_items() as $item ) {
			if ( 'yes' !== $item->get_meta( self::META_IS_TPCW, true ) ) {
				continue;
			}
			$mode   = $item->get_meta( self::META_MODE, true );
			$status = $item->get_meta( self::META_STATUS, true );
			if ( 'manual' !== $mode || self::STATUS_SUBMITTED === $status ) {
				continue;
			}
			if ( $this->submit_item_and_persist( $order, $item, 'manual' ) ) {
				++$submitted;
			}
		}

		$order->add_order_note(
			$submitted > 0
				? sprintf( __( 'Tradeprint mock submission completed for %d item(s).', 'tradeprint-configurator' ), $submitted )
				: __( 'No pending manual Tradeprint items were found.', 'tradeprint-configurator' )
		);
		$order->save();

		wp_safe_redirect( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) );
		exit;
	}

	/**
	 * Render admin order item Tradeprint details.
	 *
	 * @param int           $item_id Item ID.
	 * @param WC_Order_Item $item Item object.
	 * @param WC_Product    $product Product.
	 *
	 * @return void
	 */
	public function render_admin_item_meta( $item_id, $item, $product ) {
		if ( ! is_admin() || ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		if ( 'yes' !== $item->get_meta( self::META_IS_TPCW, true ) ) {
			return;
		}

		$status      = $item->get_meta( self::META_STATUS, true );
		$mode        = $item->get_meta( self::META_MODE, true );
		$external_id = $item->get_meta( self::META_EXTERNAL_ID, true );
		$submitted   = $item->get_meta( self::META_SUBMITTED_AT, true );
		$message     = $item->get_meta( self::META_MESSAGE, true );

		echo '<div class="tpcw-order-item-meta">';
		echo '<p><strong>' . esc_html__( 'Tradeprint', 'tradeprint-configurator' ) . ':</strong> ' . esc_html__( 'Enabled', 'tradeprint-configurator' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Mode', 'tradeprint-configurator' ) . ':</strong> ' . esc_html( $mode ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Submission status', 'tradeprint-configurator' ) . ':</strong> ' . esc_html( $status ) . '</p>';
		if ( $external_id ) {
			echo '<p><strong>' . esc_html__( 'External order ID', 'tradeprint-configurator' ) . ':</strong> ' . esc_html( $external_id ) . '</p>';
		}
		if ( $submitted ) {
			echo '<p><strong>' . esc_html__( 'Submitted at', 'tradeprint-configurator' ) . ':</strong> ' . esc_html( $submitted ) . '</p>';
		}
		if ( $message ) {
			echo '<p><strong>' . esc_html__( 'Message', 'tradeprint-configurator' ) . ':</strong> ' . esc_html( $message ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Submit item and persist meta.
	 *
	 * @param WC_Order      $order Order.
	 * @param WC_Order_Item $item Item.
	 * @param string        $mode Mode.
	 *
	 * @return bool
	 */
	private function submit_item_and_persist( WC_Order $order, WC_Order_Item $item, $mode ) {
		$config = $item->get_meta( self::META_CONFIG, true );
		$config = is_array( $config ) ? $config : array();

		$result = $this->submission_service->submit_item( $order, $item, $config, $mode );
		if ( empty( $result['success'] ) ) {
			$item->update_meta_data( self::META_STATUS, self::STATUS_FAILED );
			$item->update_meta_data( self::META_MESSAGE, isset( $result['message'] ) ? sanitize_text_field( $result['message'] ) : __( 'Mock submission failed.', 'tradeprint-configurator' ) );
			$item->save();
			return false;
		}

		$item->update_meta_data( self::META_STATUS, self::STATUS_SUBMITTED );
		$item->update_meta_data( self::META_EXTERNAL_ID, isset( $result['external_order_id'] ) ? sanitize_text_field( $result['external_order_id'] ) : '' );
		$item->update_meta_data( self::META_SUBMITTED_AT, isset( $result['submitted_at'] ) ? sanitize_text_field( $result['submitted_at'] ) : '' );
		$item->update_meta_data( self::META_MESSAGE, isset( $result['status'] ) ? sanitize_text_field( $result['status'] ) : '' );
		$item->update_meta_data( '_tpcw_submission_preview', isset( $result['request_preview'] ) ? $result['request_preview'] : array() );
		$item->save();

		return true;
	}

	/**
	 * Get posted configurator payload JSON.
	 *
	 * @return array
	 */
	private function get_posted_config_payload() {
		if ( ! isset( $_POST['tpcw_config_payload'] ) ) {
			return array();
		}

		$raw     = wp_unslash( $_POST['tpcw_config_payload'] );
		$decoded = json_decode( (string) $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Sanitize cart payload.
	 *
	 * @param array $payload Payload.
	 * @param int   $product_id Product ID.
	 *
	 * @return array
	 */
	private function sanitize_cart_payload( $payload, $product_id ) {
		$payload = is_array( $payload ) ? $payload : array();

		$pricing_input = array(
			'product_id'              => $product_id,
			'selected_attributes'     => isset( $payload['selected_attributes'] ) ? $payload['selected_attributes'] : array(),
			'selected_service'        => isset( $payload['selected_service'] ) ? $payload['selected_service'] : '',
			'selected_quantity'       => isset( $payload['selected_quantity'] ) ? $payload['selected_quantity'] : 0,
			'custom_quantity'         => isset( $payload['custom_quantity'] ) ? $payload['custom_quantity'] : 0,
			'selected_extra_services' => isset( $payload['selected_extra_services'] ) ? $payload['selected_extra_services'] : array(),
		);

		$resolved = $this->pricing_service->resolve_price( $pricing_input );
		if ( is_wp_error( $resolved ) ) {
			return array();
		}

		return array(
			'selected_attributes'    => isset( $resolved['selected_attributes'] ) ? $resolved['selected_attributes'] : array(),
			'selected_quantity'      => isset( $resolved['selected_quantity'] ) ? absint( $resolved['selected_quantity'] ) : 0,
			'selected_service'       => isset( $resolved['selected_service'] ) ? sanitize_key( $resolved['selected_service'] ) : '',
			'custom_quantity'        => isset( $pricing_input['custom_quantity'] ) ? absint( $pricing_input['custom_quantity'] ) : 0,
			'selected_extra_services'=> isset( $resolved['selected_extras'] ) ? array_map( 'sanitize_text_field', (array) $resolved['selected_extras'] ) : array(),
			'pricing_payload'        => $resolved,
			'pricing_mode'           => sanitize_key( (string) get_post_meta( $product_id, '_tpcw_pricing_display_mode', true ) ),
			'tradeprint_product_key' => sanitize_text_field( (string) get_post_meta( $product_id, '_tpcw_product_key', true ) ),
		);
	}

	/**
	 * Validate required attributes selections.
	 *
	 * @param array $config Product config.
	 * @param array $selected Selected attributes.
	 *
	 * @return bool
	 */
	private function validate_required_attributes( $config, $selected ) {
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : array();
		$selected   = is_array( $selected ) ? $selected : array();

		foreach ( $attributes as $attribute ) {
			if ( ! is_array( $attribute ) ) {
				continue;
			}
			if ( 'yes' !== ( isset( $attribute['show_attribute'] ) ? $attribute['show_attribute'] : 'no' ) ) {
				continue;
			}
			if ( 'yes' !== ( isset( $attribute['required'] ) ? $attribute['required'] : 'no' ) ) {
				continue;
			}

			$key = sanitize_key( isset( $attribute['attribute_key'] ) ? $attribute['attribute_key'] : '' );
			if ( ! $key || empty( $selected[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resolve order mode by global settings + product override.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return string
	 */
	private function resolve_order_mode_for_product( $product_id ) {
		$settings    = get_option( TPCW_Loader::OPTION_KEY, array() );
		$global_mode = isset( $settings['default_order_mode'] ) && in_array( $settings['default_order_mode'], array( 'manual', 'auto' ), true ) ? $settings['default_order_mode'] : 'manual';
		$override    = get_post_meta( $product_id, '_tpcw_order_mode_override', true );
		$override    = in_array( $override, array( 'inherit', 'manual', 'auto' ), true ) ? $override : 'inherit';

		return 'inherit' === $override ? $global_mode : $override;
	}
}
