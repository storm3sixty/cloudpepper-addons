<?php
/**
 * Product library catalogue import service.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catalogue importer service.
 */
class TPCW_Catalogue_Importer {

	/**
	 * Catalogue option key.
	 */
	const OPTION_CATALOGUE = 'tpcw_product_catalogue';

	/**
	 * Normalize raw catalogue payload.
	 *
	 * @param array  $payload Raw payload.
	 * @param string $source_mode Source mode.
	 *
	 * @return array
	 */
	public function normalize_catalogue_payload( $payload, $source_mode = 'manual_json' ) {
		$payload    = is_array( $payload ) ? $payload : array();
		$products   = isset( $payload['products'] ) && is_array( $payload['products'] ) ? $payload['products'] : array();
		$normalized = array();

		foreach ( $products as $product ) {
			if ( ! is_array( $product ) ) {
				continue;
			}

			$product_key = sanitize_text_field( isset( $product['productKey'] ) ? $product['productKey'] : '' );
			if ( '' === $product_key ) {
				continue;
			}

			$attributes = $this->normalize_attributes( isset( $product['attributes'] ) ? $product['attributes'] : array() );
			if ( empty( $attributes ) ) {
				continue;
			}

			$normalized[] = array(
				'product_key'      => $product_key,
				'product_title'    => sanitize_text_field( isset( $product['productTitle'] ) ? $product['productTitle'] : $product_key ),
				'attributes'       => $attributes,
				'attribute_count'  => count( $attributes ),
				'option_count'     => $this->count_options( $attributes ),
				'source_mode'      => sanitize_key( $source_mode ),
				'last_pulled_at'   => current_time( 'mysql' ),
				'last_imported_at' => '',
				'linked_product_id'=> 0,
			);
		}

		return $normalized;
	}

	/**
	 * Pull catalogue from JSON string.
	 *
	 * @param string $json JSON payload.
	 * @param string $source_mode Source mode.
	 *
	 * @return array|WP_Error
	 */
	public function pull_from_json( $json, $source_mode = 'manual_json' ) {
		$json = is_string( $json ) ? trim( $json ) : '';
		if ( '' === $json ) {
			return new WP_Error( 'tpcw_empty_catalogue_json', __( 'Catalogue JSON is empty.', 'tradeprint-configurator' ) );
		}

		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'tpcw_invalid_catalogue_json', __( 'Invalid JSON format. Please check the catalogue payload.', 'tradeprint-configurator' ) );
		}

		$items = $this->normalize_catalogue_payload( $payload, $source_mode );
		if ( empty( $items ) ) {
			return new WP_Error( 'tpcw_empty_catalogue_items', __( 'No valid products found in catalogue payload.', 'tradeprint-configurator' ) );
		}

		$this->store_catalogue_entries( $items );

		return $items;
	}

	/**
	 * Store catalogue entries.
	 *
	 * @param array $entries Entries.
	 *
	 * @return array
	 */
	public function store_catalogue_entries( $entries ) {
		$entries  = is_array( $entries ) ? $entries : array();
		$catalogue = $this->get_catalogue();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['product_key'] ) ) {
				continue;
			}
			$key      = sanitize_text_field( $entry['product_key'] );
			$existing = isset( $catalogue['products'][ $key ] ) && is_array( $catalogue['products'][ $key ] ) ? $catalogue['products'][ $key ] : array();

			$catalogue['products'][ $key ] = array_merge(
				$existing,
				$entry,
				array(
					'product_key'       => $key,
					'linked_product_id' => isset( $existing['linked_product_id'] ) ? absint( $existing['linked_product_id'] ) : ( isset( $entry['linked_product_id'] ) ? absint( $entry['linked_product_id'] ) : 0 ),
					'last_imported_at'  => isset( $existing['last_imported_at'] ) ? sanitize_text_field( $existing['last_imported_at'] ) : '',
				)
			);
		}

		$catalogue['updated_at'] = current_time( 'mysql' );
		update_option( self::OPTION_CATALOGUE, $catalogue, false );

		return $catalogue;
	}

	/**
	 * Import or sync one catalogue item to WooCommerce product.
	 *
	 * @param string $product_key Product key.
	 * @param bool   $force_sync Sync existing product.
	 *
	 * @return array|WP_Error
	 */
	public function import_product_to_site( $product_key, $force_sync = false ) {
		$product_key = sanitize_text_field( $product_key );
		$catalogue   = $this->get_catalogue();
		$item        = isset( $catalogue['products'][ $product_key ] ) && is_array( $catalogue['products'][ $product_key ] ) ? $catalogue['products'][ $product_key ] : array();

		if ( empty( $item ) ) {
			return new WP_Error( 'tpcw_catalogue_item_missing', __( 'Selected catalogue product was not found.', 'tradeprint-configurator' ) );
		}

		$linked_product_id = isset( $item['linked_product_id'] ) ? absint( $item['linked_product_id'] ) : 0;
		if ( ! $linked_product_id ) {
			$linked_product_id = $this->find_existing_product_by_key( $product_key );
		}

		$created = false;
		if ( ! $linked_product_id ) {
			$linked_product_id = wp_insert_post(
				array(
					'post_type'   => 'product',
					'post_title'  => isset( $item['product_title'] ) ? sanitize_text_field( $item['product_title'] ) : $product_key,
					'post_status' => 'draft',
				),
				true
			);

			if ( is_wp_error( $linked_product_id ) ) {
				return $linked_product_id;
			}
			$created = true;
		}

		if ( ! $created && ! $force_sync && isset( $item['linked_product_id'] ) && absint( $item['linked_product_id'] ) ) {
			return new WP_Error( 'tpcw_already_linked', __( 'This product is already linked. Use Sync / Re-import to refresh.', 'tradeprint-configurator' ) );
		}

		$this->apply_item_to_product( $linked_product_id, $item );

		$catalogue['products'][ $product_key ]['linked_product_id'] = $linked_product_id;
		$catalogue['products'][ $product_key ]['last_imported_at']  = current_time( 'mysql' );
		$catalogue['updated_at']                                     = current_time( 'mysql' );
		update_option( self::OPTION_CATALOGUE, $catalogue, false );

		return array(
			'product_id'   => $linked_product_id,
			'created'      => $created,
			'product_key'  => $product_key,
			'product_title'=> isset( $item['product_title'] ) ? $item['product_title'] : '',
		);
	}

	/**
	 * Get current catalogue.
	 *
	 * @return array
	 */
	public function get_catalogue() {
		$catalogue = get_option( self::OPTION_CATALOGUE, array() );
		$catalogue = is_array( $catalogue ) ? $catalogue : array();

		if ( ! isset( $catalogue['products'] ) || ! is_array( $catalogue['products'] ) ) {
			$catalogue['products'] = array();
		}

		return $catalogue;
	}

	/**
	 * Normalize attributes from source payload.
	 *
	 * @param array $attributes Attributes.
	 *
	 * @return array
	 */
	private function normalize_attributes( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$normalized = array();

		foreach ( $attributes as $index => $attribute ) {
			if ( ! is_array( $attribute ) ) {
				continue;
			}

			$key = sanitize_key( isset( $attribute['key'] ) ? $attribute['key'] : '' );
			if ( '' === $key ) {
				continue;
			}

			$options = array();
			$raw_options = isset( $attribute['options'] ) && is_array( $attribute['options'] ) ? $attribute['options'] : array();
			foreach ( $raw_options as $option ) {
				if ( ! is_array( $option ) ) {
					continue;
				}
				$option_key = sanitize_text_field( isset( $option['key'] ) ? $option['key'] : '' );
				if ( '' === $option_key ) {
					continue;
				}
				$options[] = array(
					'value_key'   => $option_key,
					'label'       => sanitize_text_field( isset( $option['label'] ) ? $option['label'] : $option_key ),
					'description' => sanitize_text_field( isset( $option['description'] ) ? $option['description'] : '' ),
					'show_option' => 'yes',
					'badge'       => sanitize_text_field( isset( $option['badge'] ) ? $option['badge'] : '' ),
					'image_id'    => isset( $option['image_id'] ) ? absint( $option['image_id'] ) : 0,
				);
			}

			if ( empty( $options ) ) {
				continue;
			}

			$normalized[] = array(
				'attribute_key'   => $key,
				'attribute_label' => sanitize_text_field( isset( $attribute['label'] ) ? $attribute['label'] : $key ),
				'show_attribute'  => 'yes',
				'display_type'    => 'dropdown',
				'help_text'       => '',
				'required'        => ! empty( $attribute['required'] ) ? 'yes' : 'no',
				'sort_order'      => isset( $attribute['sort_order'] ) ? absint( $attribute['sort_order'] ) : absint( $index ),
				'default_option'  => $options[0]['value_key'],
				'options'         => $options,
			);
		}

		usort(
			$normalized,
			function ( $left, $right ) {
				return (int) $left['sort_order'] - (int) $right['sort_order'];
			}
		);

		return $normalized;
	}

	/**
	 * Apply catalogue entry to WooCommerce product.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $item Item payload.
	 *
	 * @return void
	 */
	private function apply_item_to_product( $product_id, $item ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return;
		}

		$current_config = get_post_meta( $product_id, TPCW_Loader::META_CONFIG, true );
		$current_config = is_array( $current_config ) ? $current_config : array();

		$imported_attributes = isset( $item['attributes'] ) ? $item['attributes'] : array();
		$merged_attributes   = $this->merge_attributes( $imported_attributes, isset( $current_config['attributes'] ) ? $current_config['attributes'] : array() );

		$current_config['attributes'] = $merged_attributes;
		$current_config['imported_product'] = array(
			'product_key'   => isset( $item['product_key'] ) ? sanitize_text_field( $item['product_key'] ) : '',
			'product_title' => isset( $item['product_title'] ) ? sanitize_text_field( $item['product_title'] ) : '',
			'source_mode'   => isset( $item['source_mode'] ) ? sanitize_key( $item['source_mode'] ) : 'manual_json',
		);
		$current_config['import_meta'] = array(
			'last_synced_at' => current_time( 'mysql' ),
			'attribute_count'=> isset( $item['attribute_count'] ) ? absint( $item['attribute_count'] ) : 0,
			'option_count'   => isset( $item['option_count'] ) ? absint( $item['option_count'] ) : 0,
		);

		update_post_meta( $product_id, TPCW_Loader::META_ENABLED, 'yes' );
		update_post_meta( $product_id, '_tpcw_product_key', isset( $item['product_key'] ) ? sanitize_text_field( $item['product_key'] ) : '' );
		update_post_meta( $product_id, '_tpcw_commission_mode', 'inherit_global' );
		update_post_meta( $product_id, '_tpcw_commission_custom_percentage', '' );
		update_post_meta( $product_id, '_tpcw_product_commission_override', '' );
		update_post_meta( $product_id, TPCW_Loader::META_CONFIG, $current_config );

		if ( isset( $item['product_title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $product_id,
					'post_title' => sanitize_text_field( $item['product_title'] ),
				)
			);
		}
	}

	/**
	 * Merge imported attributes with existing configured attributes.
	 *
	 * @param array $imported Imported attributes.
	 * @param array $existing Existing attributes.
	 *
	 * @return array
	 */
	private function merge_attributes( $imported, $existing ) {
		$imported = is_array( $imported ) ? $imported : array();
		$existing = is_array( $existing ) ? $existing : array();

		$existing_map = array();
		foreach ( $existing as $attribute ) {
			if ( ! is_array( $attribute ) ) {
				continue;
			}
			$key = sanitize_key( isset( $attribute['attribute_key'] ) ? $attribute['attribute_key'] : '' );
			if ( '' !== $key ) {
				$existing_map[ $key ] = $attribute;
			}
		}

		$merged = array();
		foreach ( $imported as $attribute ) {
			if ( ! is_array( $attribute ) ) {
				continue;
			}
			$attribute_key = sanitize_key( isset( $attribute['attribute_key'] ) ? $attribute['attribute_key'] : '' );
			if ( '' === $attribute_key ) {
				continue;
			}

			$existing_attribute = isset( $existing_map[ $attribute_key ] ) && is_array( $existing_map[ $attribute_key ] ) ? $existing_map[ $attribute_key ] : array();
			$attribute['attribute_label'] = isset( $existing_attribute['attribute_label'] ) && '' !== $existing_attribute['attribute_label'] ? sanitize_text_field( $existing_attribute['attribute_label'] ) : $attribute['attribute_label'];
			$attribute['display_type']    = isset( $existing_attribute['display_type'] ) ? sanitize_key( $existing_attribute['display_type'] ) : $attribute['display_type'];
			$attribute['help_text']       = isset( $existing_attribute['help_text'] ) ? sanitize_text_field( $existing_attribute['help_text'] ) : '';
			$attribute['required']        = isset( $existing_attribute['required'] ) && 'yes' === $existing_attribute['required'] ? 'yes' : $attribute['required'];
			$attribute['show_attribute']  = isset( $existing_attribute['show_attribute'] ) && 'no' === $existing_attribute['show_attribute'] ? 'no' : 'yes';

			$attribute['options'] = $this->merge_options(
				isset( $attribute['options'] ) ? $attribute['options'] : array(),
				isset( $existing_attribute['options'] ) ? $existing_attribute['options'] : array()
			);

			$existing_default = isset( $existing_attribute['default_option'] ) ? sanitize_text_field( $existing_attribute['default_option'] ) : '';
			$option_keys      = wp_list_pluck( $attribute['options'], 'value_key' );
			$attribute['default_option'] = in_array( $existing_default, $option_keys, true ) ? $existing_default : $attribute['default_option'];

			$merged[] = $attribute;
		}

		return $merged;
	}

	/**
	 * Merge imported options with existing option customizations.
	 *
	 * @param array $imported Imported options.
	 * @param array $existing Existing options.
	 *
	 * @return array
	 */
	private function merge_options( $imported, $existing ) {
		$imported = is_array( $imported ) ? $imported : array();
		$existing = is_array( $existing ) ? $existing : array();
		$existing_map = array();

		foreach ( $existing as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}
			$key = sanitize_text_field( isset( $option['value_key'] ) ? $option['value_key'] : '' );
			if ( '' !== $key ) {
				$existing_map[ $key ] = $option;
			}
		}

		$merged = array();
		foreach ( $imported as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}
			$value_key = sanitize_text_field( isset( $option['value_key'] ) ? $option['value_key'] : '' );
			if ( '' === $value_key ) {
				continue;
			}
			$existing_option = isset( $existing_map[ $value_key ] ) && is_array( $existing_map[ $value_key ] ) ? $existing_map[ $value_key ] : array();

			$option['label']       = isset( $existing_option['label'] ) && '' !== $existing_option['label'] ? sanitize_text_field( $existing_option['label'] ) : $option['label'];
			$option['description'] = isset( $existing_option['description'] ) ? sanitize_text_field( $existing_option['description'] ) : $option['description'];
			$option['show_option'] = isset( $existing_option['show_option'] ) && 'no' === $existing_option['show_option'] ? 'no' : 'yes';
			$option['badge']       = isset( $existing_option['badge'] ) ? sanitize_text_field( $existing_option['badge'] ) : $option['badge'];
			$option['image_id']    = isset( $existing_option['image_id'] ) ? absint( $existing_option['image_id'] ) : $option['image_id'];
			$merged[]              = $option;
		}

		return $merged;
	}

	/**
	 * Find linked product by Tradeprint product key.
	 *
	 * @param string $product_key Product key.
	 *
	 * @return int
	 */
	private function find_existing_product_by_key( $product_key ) {
		$posts = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'draft', 'publish', 'pending', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_tpcw_product_key',
						'value' => sanitize_text_field( $product_key ),
					),
				),
			)
		);

		return ! empty( $posts ) ? absint( $posts[0] ) : 0;
	}

	/**
	 * Count options across attributes.
	 *
	 * @param array $attributes Attributes.
	 *
	 * @return int
	 */
	private function count_options( $attributes ) {
		$count = 0;
		foreach ( $attributes as $attribute ) {
			if ( is_array( $attribute ) && isset( $attribute['options'] ) && is_array( $attribute['options'] ) ) {
				$count += count( $attribute['options'] );
			}
		}

		return absint( $count );
	}
}
