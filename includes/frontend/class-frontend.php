<?php
/**
 * Frontend rendering hooks.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend controller.
 */
class TPCW_Frontend {

	/**
	 * Constructor.
	 *
	 * @param TPCW_Loader $loader Loader.
	 */
	public function __construct( TPCW_Loader $loader ) {
		add_action( 'wp', array( $this, 'setup_product_hooks' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_cart_payload_field' ) );
	}

	/**
	 * Determine if product is Tradeprint enabled.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return bool
	 */
	private function is_enabled_product( $product_id = 0 ) {
		$product_id = $product_id ? absint( $product_id ) : absint( get_queried_object_id() );
		if ( ! $product_id || ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}

		return 'yes' === get_post_meta( $product_id, TPCW_Loader::META_ENABLED, true );
	}

	/**
	 * Set product hooks for enabled products.
	 *
	 * @return void
	 */
	public function setup_product_hooks() {
		if ( ! $this->is_enabled_product() ) {
			return;
		}

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_placeholder' ), 30 );
	}

	/**
	 * Enqueue frontend assets only for enabled products.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_enabled_product() ) {
			return;
		}

		wp_enqueue_style( 'tpcw-frontend', TPCW_PLUGIN_URL . 'assets/css/frontend.css', array(), TPCW_VERSION );
		wp_enqueue_script( 'tpcw-frontend', TPCW_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), TPCW_VERSION, true );
		wp_localize_script(
			'tpcw-frontend',
			'tpcwFrontend',
			array(
				'priceEndpoint' => esc_url_raw( rest_url( TPCW_REST_Controller::NAMESPACE . '/price' ) ),
				'pricingModeLabel' => __( 'Pricing mode', 'tradeprint-configurator' ),
				'loadingLabel' => __( 'Updating price…', 'tradeprint-configurator' ),
				'errorLabel' => __( 'Unable to update pricing right now. Please try again.', 'tradeprint-configurator' ),
			)
		);
	}


	/**
	 * Render hidden payload field inside add-to-cart form.
	 *
	 * @return void
	 */
	public function render_cart_payload_field() {
		if ( ! $this->is_enabled_product() ) {
			return;
		}

		echo '<input type="hidden" id="tpcw-config-payload" name="tpcw_config_payload" value="" />';
		echo '<div id="tpcw-cart-validation" class="tpcw-inline-message" aria-live="polite"></div>';
	}

	/**
	 * Render configurator placeholder container.
	 *
	 * @return void
	 */
	public function render_placeholder() {
		$product_id = absint( get_the_ID() );
		if ( ! $this->is_enabled_product( $product_id ) ) {
			return;
		}

		$config         = get_post_meta( $product_id, TPCW_Loader::META_CONFIG, true );
		$config         = is_array( $config ) ? $config : array();
		$attributes     = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : array();
		$extra_services = isset( $config['extra_services'] ) && is_array( $config['extra_services'] ) ? $config['extra_services'] : array();
		$matrix         = isset( $config['matrix'] ) && is_array( $config['matrix'] ) ? $config['matrix'] : array();
		$pricing_mode   = get_post_meta( $product_id, '_tpcw_pricing_display_mode', true );
		$pricing_mode   = in_array( $pricing_mode, array( 'standard', 'matrix' ), true ) ? $pricing_mode : 'standard';
		?>
		<div id="tpcw-configurator" class="tpcw-configurator" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>" data-pricing-mode="<?php echo esc_attr( $pricing_mode ); ?>">
			<h3><?php echo esc_html__( 'Tradeprint Configurator', 'tradeprint-configurator' ); ?></h3>
			<div class="tpcw-section tpcw-options">
				<strong><?php echo esc_html__( 'Options', 'tradeprint-configurator' ); ?></strong>
				<?php $this->render_attributes( $attributes ); ?>
			</div>
			<div class="tpcw-section tpcw-matrix">
				<?php $this->render_matrix( $matrix ); ?>
			</div>
			<div class="tpcw-section tpcw-services">
				<strong><?php echo esc_html__( 'Extra Services', 'tradeprint-configurator' ); ?></strong>
				<?php $this->render_extra_services( $extra_services ); ?>
			</div>
			<div class="tpcw-section tpcw-summary tpcw-sticky-summary" id="tpcw-summary">
				<strong><?php echo esc_html__( 'Sticky Summary', 'tradeprint-configurator' ); ?></strong>
				<div class="tpcw-summary-content"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render matrix section.
	 *
	 * @param array $matrix Matrix config.
	 *
	 * @return void
	 */
	private function render_matrix( $matrix ) {
		$matrix    = is_array( $matrix ) ? $matrix : array();
		$settings  = isset( $matrix['settings'] ) && is_array( $matrix['settings'] ) ? $matrix['settings'] : array();
		$services  = isset( $matrix['services'] ) && is_array( $matrix['services'] ) ? $matrix['services'] : array();
		$rows      = isset( $matrix['quantities'] ) && is_array( $matrix['quantities'] ) ? $matrix['quantities'] : array();
		$cells     = isset( $matrix['cells'] ) && is_array( $matrix['cells'] ) ? $matrix['cells'] : array();
		$is_active = 'yes' === ( isset( $settings['enabled'] ) ? $settings['enabled'] : 'no' );

		if ( ! $is_active ) {
			echo '<strong>' . esc_html__( 'Quantity / Delivery Matrix', 'tradeprint-configurator' ) . '</strong>';
			echo '<p>' . esc_html__( 'Matrix pricing is not configured for this product.', 'tradeprint-configurator' ) . '</p>';
			return;
		}

		$visible_services = array_values(
			array_filter(
				$services,
				function ( $service ) {
					return is_array( $service ) && 'yes' === ( isset( $service['show_service'] ) ? $service['show_service'] : 'no' ) && ! empty( $service['service_key'] );
				}
			)
		);
		$visible_rows     = array_values(
			array_filter(
				$rows,
				function ( $row ) {
					return is_array( $row ) && 'yes' === ( isset( $row['show_quantity'] ) ? $row['show_quantity'] : 'no' ) && ! empty( $row['quantity'] );
				}
			)
		);

		if ( empty( $visible_services ) || empty( $visible_rows ) ) {
			echo '<strong>' . esc_html__( 'Quantity / Delivery Matrix', 'tradeprint-configurator' ) . '</strong>';
			echo '<p>' . esc_html__( 'No matrix rows/services configured yet.', 'tradeprint-configurator' ) . '</p>';
			return;
		}

		$cell_map      = array();
		$best_unit     = null;
		$highlight_best = 'yes' === ( isset( $settings['highlight_best_value'] ) ? $settings['highlight_best_value'] : 'no' );
		$highlight_recommended = 'yes' === ( isset( $settings['highlight_recommended'] ) ? $settings['highlight_recommended'] : 'no' );
		foreach ( $cells as $cell ) {
			if ( ! is_array( $cell ) || empty( $cell['quantity'] ) || empty( $cell['service_key'] ) ) {
				continue;
			}
			$key = absint( $cell['quantity'] ) . '|' . sanitize_key( $cell['service_key'] );
			$cell_map[ $key ] = $cell;
			if ( $highlight_best && 'yes' === ( isset( $cell['available'] ) ? $cell['available'] : 'no' ) && isset( $cell['unit_price'] ) && '' !== (string) $cell['unit_price'] ) {
				$current_unit = (float) $cell['unit_price'];
				if ( null === $best_unit || $current_unit < $best_unit ) {
					$best_unit = $current_unit;
				}
			}
		}

		$title = ! empty( $settings['title'] ) ? $settings['title'] : 'Choose Quantity & Delivery';
		echo '<strong>' . esc_html( $title ) . '</strong>';
		echo '<div class="tpcw-matrix-scroll"><table class="tpcw-pricing-matrix"><thead><tr><th>' . esc_html__( 'Quantity', 'tradeprint-configurator' ) . '</th>';
		foreach ( $visible_services as $service ) {
			echo '<th>' . esc_html( $service['service_label'] );
			if ( ! empty( $service['badge'] ) ) {
				echo ' <span class="tpcw-badge">' . esc_html( $service['badge'] ) . '</span>';
			}
			echo '<small>' . esc_html( isset( $service['service_description'] ) ? $service['service_description'] : '' ) . '</small>';
			echo '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $visible_rows as $row ) {
			$quantity = absint( $row['quantity'] );
			echo '<tr><th scope="row">' . esc_html( (string) $quantity ) . '</th>';
			foreach ( $visible_services as $service ) {
				$service_key = sanitize_key( $service['service_key'] );
				$map_key     = $quantity . '|' . $service_key;
				$cell        = isset( $cell_map[ $map_key ] ) ? $cell_map[ $map_key ] : array();
				$available   = 'yes' === ( isset( $cell['available'] ) ? $cell['available'] : 'no' );
				$final       = isset( $cell['final_price'] ) ? (float) $cell['final_price'] : 0;
				$unit        = isset( $cell['unit_price'] ) ? (float) $cell['unit_price'] : 0;
				$delivery    = ! empty( $service['delivery_label'] ) ? $service['delivery_label'] : '';
				$is_best     = $highlight_best && null !== $best_unit && $available && $unit === (float) $best_unit;
				$is_rec      = $highlight_recommended && ( 'yes' === ( isset( $cell['recommended'] ) ? $cell['recommended'] : 'no' ) || ! empty( $service['badge'] ) );
				$classes     = array( 'tpcw-matrix-cell' );
				if ( ! $available ) {
					$classes[] = 'is-unavailable';
				}
				if ( $is_best ) {
					$classes[] = 'is-best-value';
				}
				if ( $is_rec ) {
					$classes[] = 'is-recommended';
				}

				echo '<td><button type="button" class="' . esc_attr( implode( ' ', $classes ) ) . '" ' . ( $available ? '' : 'disabled="disabled"' ) . ' data-quantity="' . esc_attr( (string) $quantity ) . '" data-service-key="' . esc_attr( $service_key ) . '" data-service-label="' . esc_attr( isset( $service['service_label'] ) ? $service['service_label'] : $service_key ) . '" data-final-price="' . esc_attr( (string) $final ) . '" data-unit-price="' . esc_attr( (string) $unit ) . '">';
				echo '<span class="tpcw-cell-price">' . esc_html( wc_price( $final ) ) . '</span>';
				if ( 'yes' === ( isset( $settings['show_unit_price'] ) ? $settings['show_unit_price'] : 'no' ) && $unit > 0 ) {
					echo '<small class="tpcw-cell-unit">' . esc_html( sprintf( __( '%s / unit', 'tradeprint-configurator' ), wc_price( $unit ) ) ) . '</small>';
				}
				if ( 'yes' === ( isset( $settings['show_estimated_delivery'] ) ? $settings['show_estimated_delivery'] : 'no' ) && $delivery ) {
					echo '<small class="tpcw-cell-delivery">' . esc_html( $delivery ) . '</small>';
				}
				if ( $is_rec ) {
					echo '<span class="tpcw-chip">' . esc_html__( 'Recommended', 'tradeprint-configurator' ) . '</span>';
				}
				if ( $is_best ) {
					echo '<span class="tpcw-chip tpcw-chip-best">' . esc_html__( 'Best Value', 'tradeprint-configurator' ) . '</span>';
				}
				echo '</button></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';

		if ( 'yes' === ( isset( $settings['allow_custom_quantity'] ) ? $settings['allow_custom_quantity'] : 'no' ) ) {
			$min  = isset( $settings['custom_min'] ) ? absint( $settings['custom_min'] ) : 1;
			$max  = isset( $settings['custom_max'] ) ? absint( $settings['custom_max'] ) : 100000;
			$step = isset( $settings['custom_step'] ) ? absint( $settings['custom_step'] ) : 1;
			echo '<div class="tpcw-custom-quantity">';
			echo '<label for="tpcw-custom-qty">' . esc_html__( 'Custom quantity', 'tradeprint-configurator' ) . '</label>';
			echo '<input id="tpcw-custom-qty" type="number" min="' . esc_attr( (string) $min ) . '" max="' . esc_attr( (string) $max ) . '" step="' . esc_attr( (string) $step ) . '" value="' . esc_attr( (string) $min ) . '" />';
			echo '<button type="button" class="button" id="tpcw-add-custom-qty">' . esc_html__( 'Add Custom Quantity', 'tradeprint-configurator' ) . '</button>';
			echo '</div>';
		}
	}

	/**
	 * Render attributes from saved config.
	 *
	 * @param array $attributes Attributes.
	 *
	 * @return void
	 */
	private function render_attributes( $attributes ) {
		if ( empty( $attributes ) ) {
			echo '<p>' . esc_html__( 'No Tradeprint attributes configured yet.', 'tradeprint-configurator' ) . '</p>';
			return;
		}

		foreach ( $attributes as $index => $attribute ) {
			if ( ! is_array( $attribute ) || 'yes' !== ( isset( $attribute['show_attribute'] ) ? $attribute['show_attribute'] : 'no' ) ) {
				continue;
			}

			$options = isset( $attribute['options'] ) && is_array( $attribute['options'] ) ? $attribute['options'] : array();
			$visible = array_filter(
				$options,
				function ( $option ) {
					return is_array( $option ) && 'yes' === ( isset( $option['show_option'] ) ? $option['show_option'] : 'no' ) && ! empty( $option['value_key'] );
				}
			);

			if ( empty( $visible ) ) {
				continue;
			}

			$attribute_key   = isset( $attribute['attribute_key'] ) ? $attribute['attribute_key'] : 'attribute_' . $index;
			$attribute_label = isset( $attribute['attribute_label'] ) ? $attribute['attribute_label'] : $attribute_key;
			$default_key     = isset( $attribute['default_option'] ) ? $attribute['default_option'] : '';
			$display_type    = isset( $attribute['display_type'] ) ? $attribute['display_type'] : 'dropdown';
			$required        = 'yes' === ( isset( $attribute['required'] ) ? $attribute['required'] : 'no' );

			echo '<div class="tpcw-attribute-render" data-attribute-key="' . esc_attr( $attribute_key ) . '">';
			echo '<label class="tpcw-attribute-title">' . esc_html( $attribute_label );
			if ( $required ) {
				echo ' <span class="tpcw-required">*</span>';
			}
			echo '</label>';
			if ( ! empty( $attribute['help_text'] ) ) {
				echo '<p class="tpcw-help-text">' . esc_html( $attribute['help_text'] ) . '</p>';
			}

			if ( 'dropdown' === $display_type ) {
				$this->render_dropdown( $attribute_key, $visible, $default_key, $required );
			} else {
				$this->render_cards( $attribute_key, $visible, $default_key, $display_type );
			}

			echo '</div>';
		}
	}

	/**
	 * Render dropdown display type.
	 *
	 * @param string $attribute_key Attribute key.
	 * @param array  $options Options list.
	 * @param string $default_key Default key.
	 * @param bool   $required Required.
	 *
	 * @return void
	 */
	private function render_dropdown( $attribute_key, $options, $default_key, $required ) {
		echo '<select class="tpcw-select" data-attribute-key="' . esc_attr( $attribute_key ) . '" ' . ( $required ? 'required' : '' ) . '>';
		echo '<option value="">' . esc_html__( 'Select an option', 'tradeprint-configurator' ) . '</option>';
		foreach ( $options as $option ) {
			$selected = $default_key === $option['value_key'] ? ' selected="selected"' : '';
			echo '<option value="' . esc_attr( $option['value_key'] ) . '" data-label="' . esc_attr( $option['label'] ) . '"' . $selected . '>' . esc_html( $option['label'] ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Render icon/image/text card options.
	 *
	 * @param string $attribute_key Attribute key.
	 * @param array  $options Options.
	 * @param string $default_key Default key.
	 * @param string $display_type Display type.
	 *
	 * @return void
	 */
	private function render_cards( $attribute_key, $options, $default_key, $display_type ) {
		echo '<div class="tpcw-card-options tpcw-display-' . esc_attr( $display_type ) . '" role="radiogroup">';
		foreach ( $options as $option ) {
			$image_url = ! empty( $option['image_id'] ) ? wp_get_attachment_image_url( (int) $option['image_id'], 'thumbnail' ) : '';
			$selected  = $default_key === $option['value_key'];
			echo '<button type="button" class="tpcw-option-card' . ( $selected ? ' is-selected' : '' ) . '" data-attribute-key="' . esc_attr( $attribute_key ) . '" data-value-key="' . esc_attr( $option['value_key'] ) . '" data-label="' . esc_attr( $option['label'] ) . '">';
			if ( $image_url && in_array( $display_type, array( 'icon', 'image' ), true ) ) {
				echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $option['label'] ) . '" />';
			}
			if ( ! empty( $option['badge'] ) ) {
				echo '<span class="tpcw-badge">' . esc_html( $option['badge'] ) . '</span>';
			}
			echo '<span class="tpcw-option-label">' . esc_html( $option['label'] ) . '</span>';
			if ( ! empty( $option['description'] ) ) {
				echo '<small class="tpcw-option-desc">' . esc_html( $option['description'] ) . '</small>';
			}
			echo '</button>';
		}
		echo '</div>';
	}

	/**
	 * Render extra services cards.
	 *
	 * @param array $extra_services Extra services.
	 *
	 * @return void
	 */
	private function render_extra_services( $extra_services ) {
		$extra_services = is_array( $extra_services ) ? $extra_services : array();
		echo '<div class="tpcw-services-grid">';
		foreach ( array( 'preflight' => esc_html__( 'Preflight Service', 'tradeprint-configurator' ), 'design' => esc_html__( 'Design Service', 'tradeprint-configurator' ) ) as $service_key => $default_title ) {
			$service = isset( $extra_services[ $service_key ] ) && is_array( $extra_services[ $service_key ] ) ? $extra_services[ $service_key ] : array();
			if ( 'yes' !== ( isset( $service['enabled'] ) ? $service['enabled'] : 'no' ) ) {
				continue;
			}

			$title       = ! empty( $service['title'] ) ? $service['title'] : $default_title;
			$description = isset( $service['description'] ) ? $service['description'] : '';
			$image_url   = ! empty( $service['image_id'] ) ? wp_get_attachment_image_url( (int) $service['image_id'], 'thumbnail' ) : '';

			echo '<label class="tpcw-service-card">';
			echo '<input type="checkbox" class="tpcw-service-toggle" value="' . esc_attr( $service_key ) . '" data-label="' . esc_attr( $title ) . '" />';
			if ( $image_url ) {
				echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $title ) . '" />';
			}
			echo '<span class="tpcw-service-title">' . esc_html( $title ) . '</span>';
			if ( $description ) {
				echo '<small>' . esc_html( $description ) . '</small>';
			}
			echo '</label>';
		}
		echo '</div>';
	}
}
