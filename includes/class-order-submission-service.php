<?php
/**
 * Tradeprint order submission orchestration service.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order submission service.
 */
class TPCW_Order_Submission_Service {

	/**
	 * Live API service.
	 *
	 * @var TPCW_Tradeprint_Service
	 */
	private $tradeprint_service;

	/**
	 * Mock fallback service.
	 *
	 * @var TPCW_Mock_Submission_Service
	 */
	private $mock_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->tradeprint_service = new TPCW_Tradeprint_Service();
		$this->mock_service       = new TPCW_Mock_Submission_Service();
	}

	/**
	 * Submit one item using configured mode.
	 *
	 * @param WC_Order      $order Order.
	 * @param WC_Order_Item $item Item.
	 * @param array         $config Config payload.
	 * @param string        $mode Manual/auto.
	 *
	 * @return array
	 */
	public function submit_item( WC_Order $order, WC_Order_Item $item, $config, $mode ) {
		$settings        = get_option( TPCW_Loader::OPTION_KEY, array() );
		$submission_mode = isset( $settings['submission_mode'] ) && in_array( $settings['submission_mode'], array( 'mock', 'live' ), true ) ? $settings['submission_mode'] : 'mock';
		if ( 'live' !== $submission_mode ) {
			$result                     = $this->mock_service->submit_item( $order, $item, $config, $mode );
			$result['integration_mode'] = 'mock';
			return $result;
		}

		$payload = $this->build_live_order_payload( $order, $item, $config );
		$response = $this->tradeprint_service->submit_order( $payload );
		if ( is_wp_error( $response ) ) {
			return array(
				'success'          => false,
				'message'          => $response->get_error_message(),
				'submission_mode'  => $mode,
				'integration_mode' => 'live',
				'status'           => 'failed',
			);
		}

		$body             = isset( $response['body'] ) && is_array( $response['body'] ) ? $response['body'] : array();
		$order_reference  = ! empty( $body['orderReference'] ) ? sanitize_text_field( $body['orderReference'] ) : 'TPCW-LIVE-' . $order->get_id();
		$item_reference   = ! empty( $body['itemReference'] ) ? sanitize_text_field( $body['itemReference'] ) : 'ITEM-' . $item->get_id();

		return array(
			'success'            => true,
			'external_order_id'  => $order_reference,
			'order_reference'    => $order_reference,
			'item_reference'     => $item_reference,
			'submitted_at'       => current_time( 'mysql' ),
			'submission_mode'    => $mode,
			'integration_mode'   => 'live',
			'request_preview'    => $payload,
			'live_response'      => $body,
			'status'             => 'submitted',
		);
	}

	/**
	 * Fetch order statuses by references.
	 *
	 * @param array $references References.
	 *
	 * @return array|WP_Error
	 */
	public function fetch_statuses( $references ) {
		$references = array_values( array_filter( array_map( 'sanitize_text_field', (array) $references ) ) );
		if ( empty( $references ) ) {
			return new WP_Error( 'tpcw_missing_references', __( 'No order references found for status refresh.', 'tradeprint-configurator' ) );
		}

		return $this->tradeprint_service->get_orders_status( array( 'orderReferences' => $references ) );
	}

	/**
	 * Cancel order item by references.
	 *
	 * @param string $order_reference Order reference.
	 * @param string $item_reference Item reference.
	 *
	 * @return array|WP_Error
	 */
	public function cancel_order_item( $order_reference, $item_reference ) {
		return $this->tradeprint_service->cancel_order_item( $order_reference, $item_reference );
	}

	/**
	 * Build payload for /v2/orders/.
	 *
	 * @param WC_Order      $order Order.
	 * @param WC_Order_Item $item Item.
	 * @param array         $config Config payload.
	 *
	 * @return array
	 */
	private function build_live_order_payload( WC_Order $order, WC_Order_Item $item, $config ) {
		$config = is_array( $config ) ? $config : array();
		$product_id = sanitize_text_field( isset( $config['tradeprint_product_key'] ) ? $config['tradeprint_product_key'] : '' );
		$item_reference = 'ITEM-' . $order->get_id() . '-' . $item->get_id();
		$order_reference = 'WC-' . $order->get_id();

		return array(
			'currency'       => $order->get_currency(),
			'orderReference' => $order_reference,
			'billingAddress' => array(
				'firstName' => $order->get_billing_first_name(),
				'lastName'  => $order->get_billing_last_name(),
				'email'     => $order->get_billing_email(),
				'phone'     => $order->get_billing_phone(),
				'line1'     => $order->get_billing_address_1(),
				'line2'     => $order->get_billing_address_2(),
				'city'      => $order->get_billing_city(),
				'postcode'  => $order->get_billing_postcode(),
				'country'   => $order->get_billing_country(),
			),
			'orderItems'      => array(
				array(
					'itemReference'         => $item_reference,
					'productId'             => $product_id,
					'withoutArtwork'        => empty( $config['file_urls'] ) ? true : false,
					'artworkService'        => in_array( 'preflight', isset( $config['selected_extra_services'] ) ? (array) $config['selected_extra_services'] : array(), true ) ? 'preflight' : '',
					'quantity'              => isset( $config['selected_quantity'] ) ? absint( $config['selected_quantity'] ) : absint( $item->get_quantity() ),
					'fileUrls'              => isset( $config['file_urls'] ) && is_array( $config['file_urls'] ) ? array_map( 'esc_url_raw', $config['file_urls'] ) : array(),
					'serviceLevel'          => isset( $config['selected_service'] ) ? sanitize_text_field( $config['selected_service'] ) : '',
					'productionData'        => isset( $config['selected_attributes'] ) && is_array( $config['selected_attributes'] ) ? $config['selected_attributes'] : array(),
					'partnerContactDetails' => array(
						'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
						'email' => $order->get_billing_email(),
					),
					'deliveryAddress'       => array(
						'line1'    => $order->get_shipping_address_1(),
						'line2'    => $order->get_shipping_address_2(),
						'city'     => $order->get_shipping_city(),
						'postcode' => $order->get_shipping_postcode(),
						'country'  => $order->get_shipping_country(),
					),
					'extraData'             => array(
						'wooOrderId' => $order->get_id(),
						'wooItemId'  => $item->get_id(),
					),
				),
			),
		);
	}
}
