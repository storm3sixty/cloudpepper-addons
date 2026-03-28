<?php
/**
 * Pricing resolver service using saved product meta.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pricing service.
 */
class TPCW_Pricing_Service {

	/**
	 * Resolve pricing using local product config/meta only.
	 *
	 * @param array $input Request payload.
	 *
	 * @return array|WP_Error
	 */
	public function resolve_price( $input ) {
		$input = is_array( $input ) ? $input : array();

		$product_id = isset( $input['product_id'] ) ? absint( $input['product_id'] ) : 0;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error( 'tpcw_invalid_product', __( 'Invalid product ID.', 'tradeprint-configurator' ), array( 'status' => 404 ) );
		}

		if ( 'yes' !== get_post_meta( $product_id, TPCW_Loader::META_ENABLED, true ) ) {
			return new WP_Error( 'tpcw_disabled_product', __( 'Tradeprint is not enabled on this product.', 'tradeprint-configurator' ), array( 'status' => 400 ) );
		}

		$config = get_post_meta( $product_id, TPCW_Loader::META_CONFIG, true );
		$config = is_array( $config ) ? $config : array();
		$matrix = isset( $config['matrix'] ) && is_array( $config['matrix'] ) ? $config['matrix'] : array();

		$matrix_settings = isset( $matrix['settings'] ) && is_array( $matrix['settings'] ) ? $matrix['settings'] : array();
		if ( 'yes' !== ( isset( $matrix_settings['enabled'] ) ? $matrix_settings['enabled'] : 'no' ) ) {
			return new WP_Error( 'tpcw_matrix_disabled', __( 'Matrix pricing is not enabled for this product.', 'tradeprint-configurator' ), array( 'status' => 400 ) );
		}

		$selected_attributes = $this->sanitize_selected_attributes( isset( $input['selected_attributes'] ) ? $input['selected_attributes'] : array() );
		$selected_service    = isset( $input['selected_service'] ) ? sanitize_key( $input['selected_service'] ) : '';
		$selected_quantity   = isset( $input['selected_quantity'] ) ? absint( $input['selected_quantity'] ) : 0;
		$custom_quantity     = isset( $input['custom_quantity'] ) ? absint( $input['custom_quantity'] ) : 0;
		$selected_extras     = $this->sanitize_string_array( isset( $input['selected_extra_services'] ) ? $input['selected_extra_services'] : array() );
		$commission_context = $this->resolve_commission_context( $product_id );

		if ( ! $selected_quantity && $custom_quantity ) {
			$selected_quantity = $custom_quantity;
		}

		if ( ! $selected_quantity ) {
			return new WP_Error( 'tpcw_missing_quantity', __( 'Please select a quantity.', 'tradeprint-configurator' ), array( 'status' => 400 ) );
		}

		if ( ! $selected_service && ! $custom_quantity ) {
			return new WP_Error( 'tpcw_missing_service', __( 'Please select a delivery/service option.', 'tradeprint-configurator' ), array( 'status' => 400 ) );
		}

		$services = isset( $matrix['services'] ) && is_array( $matrix['services'] ) ? $matrix['services'] : array();
		$cells    = isset( $matrix['cells'] ) && is_array( $matrix['cells'] ) ? $matrix['cells'] : array();

		$service = $this->find_service( $services, $selected_service );
		$cell    = $this->find_cell( $cells, $selected_quantity, $selected_service );

		if ( ! $cell ) {
			if ( $custom_quantity ) {
				return array(
					'success'             => true,
					'pricing_status'      => 'custom_pending',
					'message'             => __( 'Custom quantity pricing not yet available.', 'tradeprint-configurator' ),
					'product_id'          => $product_id,
					'selected_quantity'   => $selected_quantity,
					'selected_service'    => $selected_service,
					'selected_attributes' => $selected_attributes,
					'selected_extras'     => $selected_extras,
						'commission_mode'     => $commission_context['mode'],
						'commission_percent'  => $commission_context['percent'],
					'summary_lines'       => array(
						array(
							'label' => __( 'Quantity', 'tradeprint-configurator' ),
							'value' => sprintf( __( 'Custom (%d)', 'tradeprint-configurator' ), $selected_quantity ),
						),
					),
				);
			}

			return new WP_Error( 'tpcw_matrix_not_found', __( 'No pricing cell found for the selected quantity and service.', 'tradeprint-configurator' ), array( 'status' => 400 ) );
		}

		$available = 'yes' === ( isset( $cell['available'] ) ? $cell['available'] : 'no' );
		if ( ! $available ) {
			return new WP_Error( 'tpcw_unavailable_cell', __( 'The selected quantity/service combination is unavailable.', 'tradeprint-configurator' ), array( 'status' => 400 ) );
		}

		$base_price  = isset( $cell['base_price'] ) ? (float) $cell['base_price'] : 0.0;
		$final_price = isset( $cell['final_price'] ) ? (float) $cell['final_price'] : 0.0;
		$unit_price  = isset( $cell['unit_price'] ) ? (float) $cell['unit_price'] : 0.0;

		// TODO: Replace/augment this local resolver with live Tradeprint pricing API integration.
		$response = array(
			'success'               => true,
			'pricing_status'        => 'exact_match',
			'product_id'            => $product_id,
			'selected_quantity'     => $selected_quantity,
			'selected_service'      => $selected_service,
			'service_label'         => isset( $service['service_label'] ) ? $service['service_label'] : $selected_service,
			'estimated_delivery'    => isset( $service['delivery_label'] ) ? $service['delivery_label'] : '',
			'base_price'            => $base_price,
			'base_price_html'       => wc_price( $base_price ),
			'final_display_price'   => $final_price,
			'final_display_price_html' => wc_price( $final_price ),
			'unit_price'            => $unit_price,
			'unit_price_html'       => $unit_price > 0 ? wc_price( $unit_price ) : '',
			'available'             => $available,
			'selected_attributes'   => $selected_attributes,
			'selected_extras'       => $selected_extras,
				'commission_mode'       => $commission_context['mode'],
				'commission_percent'    => $commission_context['percent'],
			'summary_lines'         => $this->build_summary_lines( $selected_attributes, $selected_extras, $selected_quantity, isset( $service['service_label'] ) ? $service['service_label'] : $selected_service, $final_price, $unit_price, isset( $service['delivery_label'] ) ? $service['delivery_label'] : '' ),
		);

		return $response;
	}

	/**
	 * Find service by key.
	 *
	 * @param array  $services Services list.
	 * @param string $service_key Service key.
	 *
	 * @return array
	 */
	private function find_service( $services, $service_key ) {
		$services = is_array( $services ) ? $services : array();
		$service_key = sanitize_key( $service_key );

		foreach ( $services as $service ) {
			if ( ! is_array( $service ) ) {
				continue;
			}
			if ( $service_key === sanitize_key( isset( $service['service_key'] ) ? $service['service_key'] : '' ) ) {
				return $service;
			}
		}

		return array();
	}

	/**
	 * Find matrix cell.
	 *
	 * @param array  $cells Cells list.
	 * @param int    $quantity Quantity.
	 * @param string $service_key Service key.
	 *
	 * @return array
	 */
	private function find_cell( $cells, $quantity, $service_key ) {
		$cells       = is_array( $cells ) ? $cells : array();
		$quantity    = absint( $quantity );
		$service_key = sanitize_key( $service_key );

		foreach ( $cells as $cell ) {
			if ( ! is_array( $cell ) ) {
				continue;
			}

			$cell_qty  = isset( $cell['quantity'] ) ? absint( $cell['quantity'] ) : 0;
			$cell_serv = sanitize_key( isset( $cell['service_key'] ) ? $cell['service_key'] : '' );
			if ( $cell_qty === $quantity && $cell_serv === $service_key ) {
				return $cell;
			}
		}

		return array();
	}

	/**
	 * Sanitize selected attributes object.
	 *
	 * @param array $attributes Attributes payload.
	 *
	 * @return array
	 */
	private function sanitize_selected_attributes( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$clean      = array();

		foreach ( $attributes as $key => $value ) {
			$clean_key = sanitize_key( (string) $key );
			if ( '' === $clean_key ) {
				continue;
			}
			$clean[ $clean_key ] = sanitize_text_field( (string) $value );
		}

		return $clean;
	}

	/**
	 * Sanitize simple string array.
	 *
	 * @param array $values Values.
	 *
	 * @return array
	 */
	private function sanitize_string_array( $values ) {
		$values = is_array( $values ) ? $values : array();
		$clean  = array();

		foreach ( $values as $value ) {
			$text = sanitize_text_field( (string) $value );
			if ( '' !== $text ) {
				$clean[] = $text;
			}
		}

		return array_values( array_unique( $clean ) );
	}


	/**
	 * Resolve commission mode/percentage for a product.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return array
	 */
	private function resolve_commission_context( $product_id ) {
		$settings          = get_option( TPCW_Loader::OPTION_KEY, array() );
		$global_commission = isset( $settings['global_commission'] ) ? (float) $settings['global_commission'] : 0;
		$global_commission = max( 0, min( 100, $global_commission ) );

		$mode    = get_post_meta( $product_id, '_tpcw_commission_mode', true );
		$mode    = in_array( $mode, array( 'inherit_global', 'custom_percentage' ), true ) ? $mode : 'inherit_global';
		$custom  = (float) get_post_meta( $product_id, '_tpcw_commission_custom_percentage', true );
		$custom  = max( 0, min( 100, $custom ) );

		return array(
			'mode'    => $mode,
			'percent' => 'custom_percentage' === $mode ? $custom : $global_commission,
		);
	}

	/**
	 * Build summary lines.
	 *
	 * @param array  $attributes Attributes.
	 * @param array  $extras Extras.
	 * @param int    $quantity Quantity.
	 * @param string $service_label Service label.
	 * @param float  $final_price Final price.
	 * @param float  $unit_price Unit price.
	 * @param string $delivery Delivery label.
	 *
	 * @return array
	 */
	private function build_summary_lines( $attributes, $extras, $quantity, $service_label, $final_price, $unit_price, $delivery ) {
		$lines = array();

		foreach ( $attributes as $attribute_key => $attribute_value ) {
			$lines[] = array(
				'label' => ucwords( str_replace( '_', ' ', $attribute_key ) ),
				'value' => $attribute_value,
			);
		}

		$lines[] = array(
			'label' => __( 'Quantity', 'tradeprint-configurator' ),
			'value' => (string) $quantity,
		);
		$lines[] = array(
			'label' => __( 'Service', 'tradeprint-configurator' ),
			'value' => $service_label,
		);
		if ( ! empty( $extras ) ) {
			$lines[] = array(
				'label' => __( 'Extra services', 'tradeprint-configurator' ),
				'value' => implode( ', ', $extras ),
			);
		}
		if ( $delivery ) {
			$lines[] = array(
				'label' => __( 'Delivery estimate', 'tradeprint-configurator' ),
				'value' => $delivery,
			);
		}
		$lines[] = array(
			'label' => __( 'Final price', 'tradeprint-configurator' ),
			'value' => wc_price( $final_price ),
		);
		if ( $unit_price > 0 ) {
			$lines[] = array(
				'label' => __( 'Unit price', 'tradeprint-configurator' ),
				'value' => wc_price( $unit_price ),
			);
		}

		return $lines;
	}
}
