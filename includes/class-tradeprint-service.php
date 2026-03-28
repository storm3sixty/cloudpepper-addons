<?php
/**
 * Tradeprint live service adapter.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tradeprint service.
 */
class TPCW_Tradeprint_Service {

	/**
	 * API client.
	 *
	 * @var TPCW_API_Client
	 */
	private $client;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->client = new TPCW_API_Client();
	}

	public function initiate_preflight( $payload ) {
		return $this->client->request( 'POST', '/v2/preflight', is_array( $payload ) ? $payload : array() );
	}

	public function get_preflight_status( $request_id ) {
		$request_id = rawurlencode( sanitize_text_field( (string) $request_id ) );
		return $this->client->request( 'GET', '/v2/preflight/' . $request_id );
	}

	public function get_quantities_v2( $payload ) {
		return $this->client->request( 'POST', '/v2/products-v2/quantities-v2', is_array( $payload ) ? $payload : array() );
	}

	public function get_expected_delivery_date( $payload ) {
		return $this->client->request( 'POST', '/v2/products/expectedDeliveryDate', is_array( $payload ) ? $payload : array() );
	}

	public function get_prices_v2( $payload ) {
		return $this->client->request( 'POST', '/v2/products-v2/prices-v2', is_array( $payload ) ? $payload : array() );
	}

	public function submit_order( $payload ) {
		return $this->client->request( 'POST', '/v2/orders/', is_array( $payload ) ? $payload : array() );
	}

	public function get_orders_status( $payload ) {
		return $this->client->request( 'POST', '/v2/orders/ordersStatus', is_array( $payload ) ? $payload : array() );
	}

	public function cancel_order_item( $order_reference, $item_reference ) {
		$order_reference = rawurlencode( sanitize_text_field( (string) $order_reference ) );
		$item_reference  = rawurlencode( sanitize_text_field( (string) $item_reference ) );
		return $this->client->request( 'DELETE', '/v2/orders/' . $order_reference . '/orderItems/' . $item_reference );
	}
}
