<?php
/**
 * Mock submission service for Tradeprint order flow.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mock order submission service.
 */
class TPCW_Mock_Submission_Service {

	/**
	 * Submit a Tradeprint line item (mock only).
	 *
	 * @param WC_Order      $order WooCommerce order.
	 * @param WC_Order_Item $item Order item.
	 * @param array         $config Configurator payload.
	 * @param string        $mode Submission mode.
	 *
	 * @return array
	 */
	public function submit_item( WC_Order $order, WC_Order_Item $item, $config, $mode ) {
		$config = is_array( $config ) ? $config : array();
		$mode   = in_array( $mode, array( 'manual', 'auto' ), true ) ? $mode : 'manual';

		$timestamp = current_time( 'mysql' );

		$response = array(
			'success'           => true,
			'external_order_id' => 'TPCW-' . $order->get_id() . '-' . $item->get_id() . '-' . wp_generate_password( 6, false, false ),
			'submitted_at'      => $timestamp,
			'submission_mode'   => $mode,
			'request_preview'   => array(
				'order_id'         => $order->get_id(),
				'order_item_id'    => $item->get_id(),
				'product_id'       => $item->get_product_id(),
				'quantity'         => $item->get_quantity(),
				'config_selection' => $config,
			),
			'status'            => 'manual' === $mode ? 'pending_manual' : 'submitted',
		);

		// TODO: Replace this mock response with real Tradeprint order submission API call.
		// TODO: Add request signing, retry handling, and response mapping for live integration.

		return $response;
	}
}
