<?php
/**
 * WooCommerce product data tab integration.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product data controller.
 */
class TPCW_Product_Data {

	/**
	 * Loader reference.
	 *
	 * @var TPCW_Loader
	 */
	private $loader;

	/**
	 * Constructor.
	 *
	 * @param TPCW_Loader $loader Loader.
	 */
	public function __construct( TPCW_Loader $loader ) {
		$this->loader = $loader;

		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_data' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add Tradeprint tab.
	 *
	 * @param array $tabs Existing tabs.
	 *
	 * @return array
	 */
	public function add_product_tab( $tabs ) {
		$tabs['tpcw_tradeprint'] = array(
			'label'    => esc_html__( 'Tradeprint', 'tradeprint-configurator' ),
			'target'   => 'tpcw_tradeprint_product_data',
			'class'    => array(),
			'priority' => 90,
		);

		return $tabs;
	}

	/**
	 * Enqueue assets for product edit screen only.
	 *
	 * @param string $hook_suffix Hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type || ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'tpcw-admin', TPCW_PLUGIN_URL . 'assets/css/admin.css', array(), TPCW_VERSION );
		wp_enqueue_script( 'tpcw-admin', TPCW_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), TPCW_VERSION, true );
	}

	/**
	 * Render product panel.
	 *
	 * @return void
	 */
	public function render_product_panel() {
		global $post;

		$config         = get_post_meta( $post->ID, TPCW_Loader::META_CONFIG, true );
		$config         = is_array( $config ) ? $config : array();
		$attributes     = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : array();
		$extra_services = isset( $config['extra_services'] ) && is_array( $config['extra_services'] ) ? $config['extra_services'] : array();
			$matrix            = isset( $config['matrix'] ) && is_array( $config['matrix'] ) ? $config['matrix'] : array();
			$preview_mappings  = isset( $config['preview_mappings'] ) && is_array( $config['preview_mappings'] ) ? $config['preview_mappings'] : array();
			$conditional_rules = isset( $config['conditional_rules'] ) && is_array( $config['conditional_rules'] ) ? $config['conditional_rules'] : array();
		?>
		<div id="tpcw_tradeprint_product_data" class="panel woocommerce_options_panel hidden">
			<?php wp_nonce_field( 'tpcw_save_product_data', 'tpcw_product_nonce' ); ?>

			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'    => '_tpcw_enabled',
						'label' => esc_html__( 'Enable Tradeprint product', 'tradeprint-configurator' ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => '_tpcw_product_key',
						'label'       => esc_html__( 'Tradeprint product key', 'tradeprint-configurator' ),
						'desc_tip'    => true,
						'description' => esc_html__( 'Reference key from Tradeprint catalog.', 'tradeprint-configurator' ),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => '_tpcw_product_commission_override',
						'label'             => esc_html__( 'Product commission override (%)', 'tradeprint-configurator' ),
						'type'              => 'number',
						'custom_attributes' => array(
							'min'  => '0',
							'max'  => '100',
							'step' => '0.01',
						),
					)
				);

				woocommerce_wp_select(
					array(
						'id'      => '_tpcw_order_mode_override',
						'label'   => esc_html__( 'Order mode override', 'tradeprint-configurator' ),
						'options' => array(
							'inherit' => esc_html__( 'Inherit global setting', 'tradeprint-configurator' ),
							'manual'  => esc_html__( 'Manual', 'tradeprint-configurator' ),
							'auto'    => esc_html__( 'Auto', 'tradeprint-configurator' ),
						),
					)
				);

				woocommerce_wp_select(
					array(
						'id'      => '_tpcw_pricing_display_mode',
						'label'   => esc_html__( 'Pricing display mode', 'tradeprint-configurator' ),
						'options' => array(
							'standard' => esc_html__( 'Standard', 'tradeprint-configurator' ),
							'matrix'   => esc_html__( 'Matrix', 'tradeprint-configurator' ),
						),
					)
				);
				?>
			</div>

			<div class="options_group tpcw-structured-config">
				<p><strong><?php echo esc_html__( 'Attribute Builder', 'tradeprint-configurator' ); ?></strong></p>
				<div id="tpcw-attribute-builder" class="tpcw-attribute-builder">
					<?php foreach ( $attributes as $attribute_index => $attribute ) : ?>
						<?php $this->render_attribute_row( (int) $attribute_index, $attribute ); ?>
					<?php endforeach; ?>
				</div>
				<p>
					<button type="button" class="button button-secondary" id="tpcw-add-attribute"><?php echo esc_html__( 'Add attribute', 'tradeprint-configurator' ); ?></button>
				</p>
			</div>

			<div class="options_group tpcw-matrix-config">
				<p><strong><?php echo esc_html__( 'Pricing Matrix', 'tradeprint-configurator' ); ?></strong></p>
				<?php $this->render_matrix_fields( $matrix ); ?>
			</div>

			<div class="options_group tpcw-preview-mapping-config">
				<p><strong><?php echo esc_html__( 'Preview Image Mapping', 'tradeprint-configurator' ); ?></strong></p>
				<div id="tpcw-preview-mappings" class="tpcw-repeater">
					<?php foreach ( $preview_mappings as $mapping_index => $mapping ) : ?>
						<?php $this->render_preview_mapping_row( (int) $mapping_index, $mapping ); ?>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button" id="tpcw-add-preview-mapping"><?php echo esc_html__( 'Add preview mapping', 'tradeprint-configurator' ); ?></button></p>
			</div>

			<div class="options_group tpcw-conditional-rules-config">
				<p><strong><?php echo esc_html__( 'Conditional Logic', 'tradeprint-configurator' ); ?></strong></p>
				<div id="tpcw-conditional-rules" class="tpcw-repeater">
					<?php foreach ( $conditional_rules as $rule_index => $rule ) : ?>
						<?php $this->render_conditional_rule_row( (int) $rule_index, $rule, $attributes ); ?>
					<?php endforeach; ?>
				</div>
				<datalist id="tpcw-attribute-keys">
					<?php foreach ( $attributes as $attribute ) : ?>
						<?php $attribute_key = isset( $attribute['attribute_key'] ) ? sanitize_key( $attribute['attribute_key'] ) : ''; ?>
						<?php if ( '' !== $attribute_key ) : ?>
							<option value="<?php echo esc_attr( $attribute_key ); ?>"></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</datalist>
				<p><button type="button" class="button" id="tpcw-add-conditional-rule"><?php echo esc_html__( 'Add rule', 'tradeprint-configurator' ); ?></button></p>
			</div>

			<div class="options_group tpcw-extra-services-config">
				<p><strong><?php echo esc_html__( 'Extra Services', 'tradeprint-configurator' ); ?></strong></p>
				<?php $this->render_service_fields( 'preflight', $extra_services ); ?>
				<?php $this->render_service_fields( 'design', $extra_services ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render matrix fields.
	 *
	 * @param array $matrix Matrix configuration.
	 *
	 * @return void
	 */
	private function render_matrix_fields( $matrix ) {
		$settings   = isset( $matrix['settings'] ) && is_array( $matrix['settings'] ) ? $matrix['settings'] : array();
		$services   = isset( $matrix['services'] ) && is_array( $matrix['services'] ) ? $matrix['services'] : array();
		$quantities = isset( $matrix['quantities'] ) && is_array( $matrix['quantities'] ) ? $matrix['quantities'] : array();
		$cells      = isset( $matrix['cells'] ) && is_array( $matrix['cells'] ) ? $matrix['cells'] : array();
		?>
		<div class="tpcw-grid">
			<p><label><input type="checkbox" name="tpcw_config[matrix][settings][enabled]" value="1" <?php checked( isset( $settings['enabled'] ) ? $settings['enabled'] : 'no', 'yes' ); ?> /> <?php echo esc_html__( 'Enable matrix pricing', 'tradeprint-configurator' ); ?></label></p>
			<p><label><?php echo esc_html__( 'Matrix title', 'tradeprint-configurator' ); ?></label><input type="text" name="tpcw_config[matrix][settings][title]" value="<?php echo esc_attr( isset( $settings['title'] ) ? $settings['title'] : 'Choose Quantity & Delivery' ); ?>" /></p>
			<p><label><input type="checkbox" name="tpcw_config[matrix][settings][show_unit_price]" value="1" <?php checked( isset( $settings['show_unit_price'] ) ? $settings['show_unit_price'] : 'yes', 'yes' ); ?> /> <?php echo esc_html__( 'Show unit price', 'tradeprint-configurator' ); ?></label></p>
			<p><label><input type="checkbox" name="tpcw_config[matrix][settings][show_estimated_delivery]" value="1" <?php checked( isset( $settings['show_estimated_delivery'] ) ? $settings['show_estimated_delivery'] : 'yes', 'yes' ); ?> /> <?php echo esc_html__( 'Show estimated delivery date', 'tradeprint-configurator' ); ?></label></p>
			<p><label><input type="checkbox" name="tpcw_config[matrix][settings][allow_custom_quantity]" value="1" <?php checked( isset( $settings['allow_custom_quantity'] ) ? $settings['allow_custom_quantity'] : 'no', 'yes' ); ?> /> <?php echo esc_html__( 'Allow custom quantity', 'tradeprint-configurator' ); ?></label></p>
			<p><label><?php echo esc_html__( 'Custom quantity min', 'tradeprint-configurator' ); ?></label><input type="number" min="1" name="tpcw_config[matrix][settings][custom_min]" value="<?php echo esc_attr( isset( $settings['custom_min'] ) ? $settings['custom_min'] : 1 ); ?>" /></p>
			<p><label><?php echo esc_html__( 'Custom quantity max', 'tradeprint-configurator' ); ?></label><input type="number" min="1" name="tpcw_config[matrix][settings][custom_max]" value="<?php echo esc_attr( isset( $settings['custom_max'] ) ? $settings['custom_max'] : 100000 ); ?>" /></p>
			<p><label><?php echo esc_html__( 'Custom quantity step', 'tradeprint-configurator' ); ?></label><input type="number" min="1" name="tpcw_config[matrix][settings][custom_step]" value="<?php echo esc_attr( isset( $settings['custom_step'] ) ? $settings['custom_step'] : 1 ); ?>" /></p>
			<p><label><input type="checkbox" name="tpcw_config[matrix][settings][highlight_best_value]" value="1" <?php checked( isset( $settings['highlight_best_value'] ) ? $settings['highlight_best_value'] : 'yes', 'yes' ); ?> /> <?php echo esc_html__( 'Highlight best value', 'tradeprint-configurator' ); ?></label></p>
			<p><label><input type="checkbox" name="tpcw_config[matrix][settings][highlight_recommended]" value="1" <?php checked( isset( $settings['highlight_recommended'] ) ? $settings['highlight_recommended'] : 'yes', 'yes' ); ?> /> <?php echo esc_html__( 'Highlight recommended option', 'tradeprint-configurator' ); ?></label></p>
		</div>

		<p><strong><?php echo esc_html__( 'Service Levels', 'tradeprint-configurator' ); ?></strong></p>
		<div id="tpcw-matrix-services" class="tpcw-repeater">
			<?php foreach ( $services as $service_index => $service ) : ?>
				<?php $this->render_matrix_service_row( (int) $service_index, $service ); ?>
			<?php endforeach; ?>
		</div>
		<p><button type="button" class="button" id="tpcw-add-matrix-service"><?php echo esc_html__( 'Add service level', 'tradeprint-configurator' ); ?></button></p>

		<p><strong><?php echo esc_html__( 'Quantity Rows', 'tradeprint-configurator' ); ?></strong></p>
		<div id="tpcw-matrix-quantities" class="tpcw-repeater">
			<?php foreach ( $quantities as $quantity_index => $quantity ) : ?>
				<?php $this->render_matrix_quantity_row( (int) $quantity_index, $quantity ); ?>
			<?php endforeach; ?>
		</div>
		<p><button type="button" class="button" id="tpcw-add-matrix-quantity"><?php echo esc_html__( 'Add quantity row', 'tradeprint-configurator' ); ?></button></p>

		<p><strong><?php echo esc_html__( 'Matrix Grid Editor', 'tradeprint-configurator' ); ?></strong></p>
		<div class="tpcw-matrix-grid-wrap">
			<table id="tpcw-matrix-grid" class="widefat striped" data-cells="<?php echo esc_attr( wp_json_encode( $cells ) ); ?>">
				<thead><tr><th><?php echo esc_html__( 'Quantity', 'tradeprint-configurator' ); ?></th></tr></thead>
				<tbody></tbody>
			</table>
		</div>
		<p class="description"><?php echo esc_html__( 'Edit prices inline. Grid updates automatically when services or quantities change.', 'tradeprint-configurator' ); ?></p>
		<?php
	}
	/**
	 * Render matrix service row.
	 *
	 * @param int   $service_index Service index.
	 * @param array $service Service payload.
	 *
	 * @return void
	 */
	private function render_matrix_service_row( $service_index, $service ) {
		$service = is_array( $service ) ? $service : array();
		?>
		<div class="tpcw-repeater-row tpcw-matrix-service-row" data-index="<?php echo esc_attr( (string) $service_index ); ?>">
			<div class="tpcw-grid">
				<p><label><?php echo esc_html__( 'Service key', 'tradeprint-configurator' ); ?></label><input type="text" name="tpcw_config[matrix][services][<?php echo esc_attr( (string) $service_index ); ?>][service_key]" value="<?php echo esc_attr( isset( $service['service_key'] ) ? $service['service_key'] : '' ); ?>" /></p>
				<p><label><?php echo esc_html__( 'Service label', 'tradeprint-configurator' ); ?></label><input type="text" name="tpcw_config[matrix][services][<?php echo esc_attr( (string) $service_index ); ?>][service_label]" value="<?php echo esc_attr( isset( $service['service_label'] ) ? $service['service_label'] : '' ); ?>" /></p>
				<p><label><?php echo esc_html__( 'Service description', 'tradeprint-configurator' ); ?></label><input type="text" name="tpcw_config[matrix][services][<?php echo esc_attr( (string) $service_index ); ?>][service_description]" value="<?php echo esc_attr( isset( $service['service_description'] ) ? $service['service_description'] : '' ); ?>" /></p>
				<p><label><?php echo esc_html__( 'Estimated production days', 'tradeprint-configurator' ); ?></label><input type="number" min="0" name="tpcw_config[matrix][services][<?php echo esc_attr( (string) $service_index ); ?>][production_days]" value="<?php echo esc_attr( isset( $service['production_days'] ) ? $service['production_days'] : 0 ); ?>" /></p>
				<p><label><?php echo esc_html__( 'Estimated delivery label', 'tradeprint-configurator' ); ?></label><input type="text" name="tpcw_config[matrix][services][<?php echo esc_attr( (string) $service_index ); ?>][delivery_label]" value="<?php echo esc_attr( isset( $service['delivery_label'] ) ? $service['delivery_label'] : '' ); ?>" /></p>
				<p><label><?php echo esc_html__( 'Badge text', 'tradeprint-configurator' ); ?></label><input type="text" name="tpcw_config[matrix][services][<?php echo esc_attr( (string) $service_index ); ?>][badge]" value="<?php echo esc_attr( isset( $service['badge'] ) ? $service['badge'] : '' ); ?>" /></p>
				<p><label><input type="checkbox" name="tpcw_config[matrix][services][<?php echo esc_attr( (string) $service_index ); ?>][show_service]" value="1" <?php checked( isset( $service['show_service'] ) ? $service['show_service'] : 'yes', 'yes' ); ?> /> <?php echo esc_html__( 'Show service', 'tradeprint-configurator' ); ?></label></p>
				<p><label><?php echo esc_html__( 'Sort order', 'tradeprint-configurator' ); ?></label><input type="number" min="0" name="tpcw_config[matrix][services][<?php echo esc_attr( (string) $service_index ); ?>][sort_order]" value="<?php echo esc_attr( isset( $service['sort_order'] ) ? $service['sort_order'] : 0 ); ?>" /></p>
			</div>
			<p><button type="button" class="button-link-delete tpcw-remove-row"><?php echo esc_html__( 'Remove service', 'tradeprint-configurator' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Render matrix quantity row.
	 *
	 * @param int   $quantity_index Quantity index.
	 * @param array $quantity Quantity payload.
	 *
	 * @return void
	 */
	private function render_matrix_quantity_row( $quantity_index, $quantity ) {
		$quantity = is_array( $quantity ) ? $quantity : array();
		?>
		<div class="tpcw-repeater-row tpcw-matrix-quantity-row" data-index="<?php echo esc_attr( (string) $quantity_index ); ?>">
			<div class="tpcw-grid">
				<p><label><?php echo esc_html__( 'Quantity value', 'tradeprint-configurator' ); ?></label><input type="number" min="1" name="tpcw_config[matrix][quantities][<?php echo esc_attr( (string) $quantity_index ); ?>][quantity]" value="<?php echo esc_attr( isset( $quantity['quantity'] ) ? $quantity['quantity'] : 1 ); ?>" /></p>
				<p><label><input type="checkbox" name="tpcw_config[matrix][quantities][<?php echo esc_attr( (string) $quantity_index ); ?>][show_quantity]" value="1" <?php checked( isset( $quantity['show_quantity'] ) ? $quantity['show_quantity'] : 'yes', 'yes' ); ?> /> <?php echo esc_html__( 'Show quantity', 'tradeprint-configurator' ); ?></label></p>
				<p><label><?php echo esc_html__( 'Sort order', 'tradeprint-configurator' ); ?></label><input type="number" min="0" name="tpcw_config[matrix][quantities][<?php echo esc_attr( (string) $quantity_index ); ?>][sort_order]" value="<?php echo esc_attr( isset( $quantity['sort_order'] ) ? $quantity['sort_order'] : 0 ); ?>" /></p>
			</div>
			<p><button type="button" class="button-link-delete tpcw-remove-row"><?php echo esc_html__( 'Remove quantity', 'tradeprint-configurator' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Render preview mapping row.
	 *
	 * @param int   $mapping_index Mapping index.
	 * @param array $mapping Mapping payload.
	 *
	 * @return void
	 */
	private function render_preview_mapping_row( $mapping_index, $mapping ) {
		$mapping = is_array( $mapping ) ? $mapping : array();
		?>
		<div class="tpcw-repeater-row tpcw-preview-mapping-row" data-index="<?php echo esc_attr( (string) $mapping_index ); ?>">
			<div class="tpcw-grid">
				<p><label><?php echo esc_html__( 'Attribute key', 'tradeprint-configurator' ); ?></label><input type="text" name="tpcw_config[preview_mappings][<?php echo esc_attr( (string) $mapping_index ); ?>][attribute_key]" value="<?php echo esc_attr( isset( $mapping['attribute_key'] ) ? $mapping['attribute_key'] : '' ); ?>" /></p>
				<p><label><?php echo esc_html__( 'Option value key', 'tradeprint-configurator' ); ?></label><input type="text" name="tpcw_config[preview_mappings][<?php echo esc_attr( (string) $mapping_index ); ?>][option_value_key]" value="<?php echo esc_attr( isset( $mapping['option_value_key'] ) ? $mapping['option_value_key'] : '' ); ?>" /></p>
				<p><label><?php echo esc_html__( 'Preview image attachment ID', 'tradeprint-configurator' ); ?></label><input type="number" class="small-text tpcw-media-id" min="0" name="tpcw_config[preview_mappings][<?php echo esc_attr( (string) $mapping_index ); ?>][image_id]" value="<?php echo esc_attr( isset( $mapping['image_id'] ) ? $mapping['image_id'] : 0 ); ?>" /> <button type="button" class="button tpcw-media-select"><?php echo esc_html__( 'Select image', 'tradeprint-configurator' ); ?></button></p>
				<p><label><?php echo esc_html__( 'Preview label (optional)', 'tradeprint-configurator' ); ?></label><input type="text" name="tpcw_config[preview_mappings][<?php echo esc_attr( (string) $mapping_index ); ?>][label]" value="<?php echo esc_attr( isset( $mapping['label'] ) ? $mapping['label'] : '' ); ?>" /></p>
				<p><label><?php echo esc_html__( 'Sort order', 'tradeprint-configurator' ); ?></label><input type="number" min="0" name="tpcw_config[preview_mappings][<?php echo esc_attr( (string) $mapping_index ); ?>][sort_order]" value="<?php echo esc_attr( isset( $mapping['sort_order'] ) ? $mapping['sort_order'] : 0 ); ?>" /></p>
			</div>
			<p><button type="button" class="button-link-delete tpcw-remove-row"><?php echo esc_html__( 'Remove mapping', 'tradeprint-configurator' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Render conditional rule row.
	 *
	 * @param int   $rule_index Rule index.
	 * @param array $rule Rule payload.
	 * @param array $attributes Attributes config.
	 *
	 * @return void
	 */
	private function render_conditional_rule_row( $rule_index, $rule, $attributes ) {
		$rule       = is_array( $rule ) ? $rule : array();
		$attributes = is_array( $attributes ) ? $attributes : array();
		?>
		<div class="tpcw-repeater-row tpcw-conditional-rule-row" data-index="<?php echo esc_attr( (string) $rule_index ); ?>">
			<div class="tpcw-grid">
				<p>
					<label><?php echo esc_html__( 'Rule target type', 'tradeprint-configurator' ); ?></label>
					<select name="tpcw_config[conditional_rules][<?php echo esc_attr( (string) $rule_index ); ?>][target_type]">
						<option value="attribute" <?php selected( isset( $rule['target_type'] ) ? $rule['target_type'] : 'attribute', 'attribute' ); ?>><?php echo esc_html__( 'Attribute', 'tradeprint-configurator' ); ?></option>
						<option value="option" <?php selected( isset( $rule['target_type'] ) ? $rule['target_type'] : '', 'option' ); ?>><?php echo esc_html__( 'Option', 'tradeprint-configurator' ); ?></option>
					</select>
				</p>
				<p>
					<label><?php echo esc_html__( 'Target attribute key', 'tradeprint-configurator' ); ?></label>
					<input type="text" class="tpcw-attribute-key-list" list="tpcw-attribute-keys" name="tpcw_config[conditional_rules][<?php echo esc_attr( (string) $rule_index ); ?>][target_attribute_key]" value="<?php echo esc_attr( isset( $rule['target_attribute_key'] ) ? $rule['target_attribute_key'] : '' ); ?>" />
				</p>
				<p>
					<label><?php echo esc_html__( 'Target option value key (optional)', 'tradeprint-configurator' ); ?></label>
					<input type="text" name="tpcw_config[conditional_rules][<?php echo esc_attr( (string) $rule_index ); ?>][target_option_value_key]" value="<?php echo esc_attr( isset( $rule['target_option_value_key'] ) ? $rule['target_option_value_key'] : '' ); ?>" />
				</p>
				<p>
					<label><?php echo esc_html__( 'Condition attribute key', 'tradeprint-configurator' ); ?></label>
					<input type="text" class="tpcw-attribute-key-list" list="tpcw-attribute-keys" name="tpcw_config[conditional_rules][<?php echo esc_attr( (string) $rule_index ); ?>][condition_attribute_key]" value="<?php echo esc_attr( isset( $rule['condition_attribute_key'] ) ? $rule['condition_attribute_key'] : '' ); ?>" />
				</p>
				<p>
					<label><?php echo esc_html__( 'Condition operator', 'tradeprint-configurator' ); ?></label>
					<select name="tpcw_config[conditional_rules][<?php echo esc_attr( (string) $rule_index ); ?>][operator]">
						<option value="equals" <?php selected( isset( $rule['operator'] ) ? $rule['operator'] : 'equals', 'equals' ); ?>><?php echo esc_html__( 'Equals', 'tradeprint-configurator' ); ?></option>
						<option value="not_equals" <?php selected( isset( $rule['operator'] ) ? $rule['operator'] : '', 'not_equals' ); ?>><?php echo esc_html__( 'Not equals', 'tradeprint-configurator' ); ?></option>
						<option value="in_list" <?php selected( isset( $rule['operator'] ) ? $rule['operator'] : '', 'in_list' ); ?>><?php echo esc_html__( 'In list', 'tradeprint-configurator' ); ?></option>
					</select>
				</p>
				<p>
					<label><?php echo esc_html__( 'Condition value(s)', 'tradeprint-configurator' ); ?></label>
					<input type="text" name="tpcw_config[conditional_rules][<?php echo esc_attr( (string) $rule_index ); ?>][condition_values]" value="<?php echo esc_attr( isset( $rule['condition_values'] ) ? implode( ',', (array) $rule['condition_values'] ) : '' ); ?>" placeholder="<?php echo esc_attr__( 'matt,gloss', 'tradeprint-configurator' ); ?>" />
				</p>
				<p>
					<label><?php echo esc_html__( 'Action', 'tradeprint-configurator' ); ?></label>
					<select name="tpcw_config[conditional_rules][<?php echo esc_attr( (string) $rule_index ); ?>][action]">
						<option value="show" <?php selected( isset( $rule['action'] ) ? $rule['action'] : 'show', 'show' ); ?>><?php echo esc_html__( 'Show', 'tradeprint-configurator' ); ?></option>
						<option value="hide" <?php selected( isset( $rule['action'] ) ? $rule['action'] : '', 'hide' ); ?>><?php echo esc_html__( 'Hide', 'tradeprint-configurator' ); ?></option>
					</select>
				</p>
				<p>
					<label><?php echo esc_html__( 'Rule priority / sort order', 'tradeprint-configurator' ); ?></label>
					<input type="number" min="0" name="tpcw_config[conditional_rules][<?php echo esc_attr( (string) $rule_index ); ?>][sort_order]" value="<?php echo esc_attr( isset( $rule['sort_order'] ) ? $rule['sort_order'] : 0 ); ?>" />
				</p>
			</div>
			<p><button type="button" class="button-link-delete tpcw-remove-row"><?php echo esc_html__( 'Remove rule', 'tradeprint-configurator' ); ?></button></p>
		</div>
		<?php

	}


	/**
	 * Render service fields.
	 *
	 * @param string $service_key Service key.
	 * @param array  $extra_services Extra services config.
	 *
	 * @return void
	 */
	private function render_service_fields( $service_key, $extra_services ) {
		$service = isset( $extra_services[ $service_key ] ) && is_array( $extra_services[ $service_key ] ) ? $extra_services[ $service_key ] : array();
		$title   = 'preflight' === $service_key ? esc_html__( 'Preflight service', 'tradeprint-configurator' ) : esc_html__( 'Design service', 'tradeprint-configurator' );
		?>
		<div class="tpcw-service-row">
			<p class="form-field">
				<label>
					<input type="checkbox" name="tpcw_config[extra_services][<?php echo esc_attr( $service_key ); ?>][enabled]" value="1" <?php checked( isset( $service['enabled'] ) ? $service['enabled'] : 'no', 'yes' ); ?> />
					<?php echo esc_html( sprintf( __( 'Enable %s', 'tradeprint-configurator' ), strtolower( $title ) ) ); ?>
				</label>
			</p>
			<p class="form-field">
				<label><?php echo esc_html( $title . ' ' . __( 'title', 'tradeprint-configurator' ) ); ?></label>
				<input type="text" class="short" name="tpcw_config[extra_services][<?php echo esc_attr( $service_key ); ?>][title]" value="<?php echo esc_attr( isset( $service['title'] ) ? $service['title'] : '' ); ?>" />
			</p>
			<p class="form-field">
				<label><?php echo esc_html( $title . ' ' . __( 'description', 'tradeprint-configurator' ) ); ?></label>
				<textarea rows="2" class="short" name="tpcw_config[extra_services][<?php echo esc_attr( $service_key ); ?>][description]"><?php echo esc_textarea( isset( $service['description'] ) ? $service['description'] : '' ); ?></textarea>
			</p>
			<p class="form-field">
				<label><?php echo esc_html( $title . ' ' . __( 'image attachment ID', 'tradeprint-configurator' ) ); ?></label>
				<input type="number" class="small-text tpcw-media-id" name="tpcw_config[extra_services][<?php echo esc_attr( $service_key ); ?>][image_id]" value="<?php echo esc_attr( isset( $service['image_id'] ) ? $service['image_id'] : 0 ); ?>" min="0" />
				<button type="button" class="button tpcw-media-select"><?php echo esc_html__( 'Select image', 'tradeprint-configurator' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Render single attribute row.
	 *
	 * @param int   $attribute_index Attribute index.
	 * @param array $attribute Attribute payload.
	 *
	 * @return void
	 */
	private function render_attribute_row( $attribute_index, $attribute ) {
		$attribute = is_array( $attribute ) ? $attribute : array();
		$options   = isset( $attribute['options'] ) && is_array( $attribute['options'] ) ? $attribute['options'] : array();
		?>
		<div class="tpcw-attribute" data-attribute-index="<?php echo esc_attr( (string) $attribute_index ); ?>">
			<div class="tpcw-attribute-header">
				<button type="button" class="button-link tpcw-toggle-attribute">▾</button>
				<strong><?php echo esc_html( isset( $attribute['attribute_label'] ) && '' !== $attribute['attribute_label'] ? $attribute['attribute_label'] : __( 'New attribute', 'tradeprint-configurator' ) ); ?></strong>
				<button type="button" class="button-link-delete tpcw-remove-attribute"><?php echo esc_html__( 'Remove attribute', 'tradeprint-configurator' ); ?></button>
			</div>
			<div class="tpcw-attribute-body">
				<div class="tpcw-grid">
					<p>
						<label><?php echo esc_html__( 'Attribute key', 'tradeprint-configurator' ); ?></label>
						<input type="text" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][attribute_key]" value="<?php echo esc_attr( isset( $attribute['attribute_key'] ) ? $attribute['attribute_key'] : '' ); ?>" />
					</p>
					<p>
						<label><?php echo esc_html__( 'Attribute label', 'tradeprint-configurator' ); ?></label>
						<input type="text" class="tpcw-attribute-label" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][attribute_label]" value="<?php echo esc_attr( isset( $attribute['attribute_label'] ) ? $attribute['attribute_label'] : '' ); ?>" />
					</p>
					<p>
						<label><?php echo esc_html__( 'Frontend display type', 'tradeprint-configurator' ); ?></label>
						<select name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][display_type]">
							<?php foreach ( array( 'dropdown', 'icon', 'image', 'text' ) as $display_type ) : ?>
								<option value="<?php echo esc_attr( $display_type ); ?>" <?php selected( isset( $attribute['display_type'] ) ? $attribute['display_type'] : 'dropdown', $display_type ); ?>><?php echo esc_html( ucfirst( $display_type ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label><?php echo esc_html__( 'Help text / tooltip', 'tradeprint-configurator' ); ?></label>
						<input type="text" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][help_text]" value="<?php echo esc_attr( isset( $attribute['help_text'] ) ? $attribute['help_text'] : '' ); ?>" />
					</p>
					<p>
						<label><?php echo esc_html__( 'Sort order', 'tradeprint-configurator' ); ?></label>
						<input type="number" min="0" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][sort_order]" value="<?php echo esc_attr( isset( $attribute['sort_order'] ) ? $attribute['sort_order'] : 0 ); ?>" />
					</p>
					<p>
						<label>
							<input type="checkbox" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][show_attribute]" value="1" <?php checked( isset( $attribute['show_attribute'] ) ? $attribute['show_attribute'] : 'yes', 'yes' ); ?> />
							<?php echo esc_html__( 'Show attribute', 'tradeprint-configurator' ); ?>
						</label>
						<br />
						<label>
							<input type="checkbox" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][required]" value="1" <?php checked( isset( $attribute['required'] ) ? $attribute['required'] : 'no', 'yes' ); ?> />
							<?php echo esc_html__( 'Required', 'tradeprint-configurator' ); ?>
						</label>
					</p>
				</div>

				<div class="tpcw-option-builder" data-attribute-index="<?php echo esc_attr( (string) $attribute_index ); ?>">
					<?php foreach ( $options as $option_index => $option ) : ?>
						<?php $this->render_option_row( $attribute_index, (int) $option_index, $option, isset( $attribute['default_option'] ) ? $attribute['default_option'] : '' ); ?>
					<?php endforeach; ?>
				</div>
				<p>
					<button type="button" class="button tpcw-add-option"><?php echo esc_html__( 'Add option', 'tradeprint-configurator' ); ?></button>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render option row.
	 *
	 * @param int    $attribute_index Attribute index.
	 * @param int    $option_index Option index.
	 * @param array  $option Option payload.
	 * @param string $default_option Default key.
	 *
	 * @return void
	 */
	private function render_option_row( $attribute_index, $option_index, $option, $default_option ) {
		$option = is_array( $option ) ? $option : array();
		?>
		<div class="tpcw-option-row" data-option-index="<?php echo esc_attr( (string) $option_index ); ?>">
			<div class="tpcw-grid tpcw-option-grid">
				<p>
					<label><?php echo esc_html__( 'Option value key', 'tradeprint-configurator' ); ?></label>
					<input type="text" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][options][<?php echo esc_attr( (string) $option_index ); ?>][value_key]" value="<?php echo esc_attr( isset( $option['value_key'] ) ? $option['value_key'] : '' ); ?>" />
				</p>
				<p>
					<label><?php echo esc_html__( 'Option label', 'tradeprint-configurator' ); ?></label>
					<input type="text" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][options][<?php echo esc_attr( (string) $option_index ); ?>][label]" value="<?php echo esc_attr( isset( $option['label'] ) ? $option['label'] : '' ); ?>" />
				</p>
				<p>
					<label><?php echo esc_html__( 'Option description', 'tradeprint-configurator' ); ?></label>
					<input type="text" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][options][<?php echo esc_attr( (string) $option_index ); ?>][description]" value="<?php echo esc_attr( isset( $option['description'] ) ? $option['description'] : '' ); ?>" />
				</p>
				<p>
					<label><?php echo esc_html__( 'Badge text', 'tradeprint-configurator' ); ?></label>
					<input type="text" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][options][<?php echo esc_attr( (string) $option_index ); ?>][badge]" value="<?php echo esc_attr( isset( $option['badge'] ) ? $option['badge'] : '' ); ?>" />
				</p>
				<p>
					<label><?php echo esc_html__( 'Icon / image attachment ID', 'tradeprint-configurator' ); ?></label>
					<input type="number" class="small-text tpcw-media-id" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][options][<?php echo esc_attr( (string) $option_index ); ?>][image_id]" value="<?php echo esc_attr( isset( $option['image_id'] ) ? $option['image_id'] : 0 ); ?>" min="0" />
					<button type="button" class="button tpcw-media-select"><?php echo esc_html__( 'Select media', 'tradeprint-configurator' ); ?></button>
				</p>
				<p>
					<label>
						<input type="checkbox" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][options][<?php echo esc_attr( (string) $option_index ); ?>][show_option]" value="1" <?php checked( isset( $option['show_option'] ) ? $option['show_option'] : 'yes', 'yes' ); ?> />
						<?php echo esc_html__( 'Show option', 'tradeprint-configurator' ); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="tpcw_config[attributes][<?php echo esc_attr( (string) $attribute_index ); ?>][default_option]" value="<?php echo esc_attr( isset( $option['value_key'] ) ? $option['value_key'] : '' ); ?>" <?php checked( isset( $option['value_key'] ) ? $option['value_key'] : '', $default_option ); ?> />
						<?php echo esc_html__( 'Default option', 'tradeprint-configurator' ); ?>
					</label>
				</p>
			</div>
			<p><button type="button" class="button-link-delete tpcw-remove-option"><?php echo esc_html__( 'Remove option', 'tradeprint-configurator' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Save product data.
	 *
	 * @param int $post_id Product ID.
	 *
	 * @return void
	 */
	public function save_product_data( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['tpcw_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tpcw_product_nonce'] ) ), 'tpcw_save_product_data' ) ) {
			return;
		}

		update_post_meta( $post_id, '_tpcw_enabled', isset( $_POST['_tpcw_enabled'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_tpcw_product_key', isset( $_POST['_tpcw_product_key'] ) ? sanitize_text_field( wp_unslash( $_POST['_tpcw_product_key'] ) ) : '' );
		update_post_meta( $post_id, '_tpcw_product_commission_override', isset( $_POST['_tpcw_product_commission_override'] ) ? (float) wp_unslash( $_POST['_tpcw_product_commission_override'] ) : '' );

		$order_mode = isset( $_POST['_tpcw_order_mode_override'] ) ? sanitize_key( wp_unslash( $_POST['_tpcw_order_mode_override'] ) ) : 'inherit';
		$order_mode = in_array( $order_mode, array( 'inherit', 'manual', 'auto' ), true ) ? $order_mode : 'inherit';
		update_post_meta( $post_id, '_tpcw_order_mode_override', $order_mode );

		$pricing_mode = isset( $_POST['_tpcw_pricing_display_mode'] ) ? sanitize_key( wp_unslash( $_POST['_tpcw_pricing_display_mode'] ) ) : 'standard';
		$pricing_mode = in_array( $pricing_mode, array( 'standard', 'matrix' ), true ) ? $pricing_mode : 'standard';
		update_post_meta( $post_id, '_tpcw_pricing_display_mode', $pricing_mode );

		$config_input = isset( $_POST['tpcw_config'] ) && is_array( $_POST['tpcw_config'] ) ? wp_unslash( $_POST['tpcw_config'] ) : array();
			$config       = array(
				'attributes'     => $this->sanitize_attributes( isset( $config_input['attributes'] ) ? $config_input['attributes'] : array() ),
				'matrix'           => $this->sanitize_matrix( isset( $config_input['matrix'] ) ? $config_input['matrix'] : array() ),
				'preview_mappings' => $this->sanitize_preview_mappings( isset( $config_input['preview_mappings'] ) ? $config_input['preview_mappings'] : array() ),
				'conditional_rules'=> $this->sanitize_conditional_rules( isset( $config_input['conditional_rules'] ) ? $config_input['conditional_rules'] : array() ),
				'extra_services'   => $this->sanitize_extra_services( isset( $config_input['extra_services'] ) ? $config_input['extra_services'] : array() ),
			);

		update_post_meta( $post_id, TPCW_Loader::META_CONFIG, $config );
	}

	/**
	 * Sanitize matrix settings and rows.
	 *
	 * @param array $matrix Matrix payload.
	 *
	 * @return array
	 */
	private function sanitize_matrix( $matrix ) {
		$matrix   = is_array( $matrix ) ? $matrix : array();
		$settings = isset( $matrix['settings'] ) && is_array( $matrix['settings'] ) ? $matrix['settings'] : array();

		$sanitized = array(
			'settings' => array(
				'enabled'                 => ! empty( $settings['enabled'] ) ? 'yes' : 'no',
				'title'                   => ! empty( $settings['title'] ) ? sanitize_text_field( $settings['title'] ) : 'Choose Quantity & Delivery',
				'show_unit_price'         => ! empty( $settings['show_unit_price'] ) ? 'yes' : 'no',
				'show_estimated_delivery' => ! empty( $settings['show_estimated_delivery'] ) ? 'yes' : 'no',
				'allow_custom_quantity'   => ! empty( $settings['allow_custom_quantity'] ) ? 'yes' : 'no',
				'custom_min'              => max( 1, isset( $settings['custom_min'] ) ? absint( $settings['custom_min'] ) : 1 ),
				'custom_max'              => max( 1, isset( $settings['custom_max'] ) ? absint( $settings['custom_max'] ) : 100000 ),
				'custom_step'             => max( 1, isset( $settings['custom_step'] ) ? absint( $settings['custom_step'] ) : 1 ),
				'highlight_best_value'    => ! empty( $settings['highlight_best_value'] ) ? 'yes' : 'no',
				'highlight_recommended'   => ! empty( $settings['highlight_recommended'] ) ? 'yes' : 'no',
			),
			'services'   => array(),
			'quantities' => array(),
			'cells'      => array(),
		);

		if ( $sanitized['settings']['custom_min'] > $sanitized['settings']['custom_max'] ) {
			$sanitized['settings']['custom_max'] = $sanitized['settings']['custom_min'];
		}

		$services       = isset( $matrix['services'] ) && is_array( $matrix['services'] ) ? $matrix['services'] : array();
		$seen_services  = array();
		foreach ( $services as $service ) {
			if ( ! is_array( $service ) ) {
				continue;
			}

			$service_key = isset( $service['service_key'] ) ? sanitize_key( $service['service_key'] ) : '';
			if ( '' === $service_key || in_array( $service_key, $seen_services, true ) ) {
				continue;
			}
			$seen_services[] = $service_key;

			$sanitized['services'][] = array(
				'service_key'         => $service_key,
				'service_label'       => isset( $service['service_label'] ) ? sanitize_text_field( $service['service_label'] ) : '',
				'service_description' => isset( $service['service_description'] ) ? sanitize_text_field( $service['service_description'] ) : '',
				'production_days'     => isset( $service['production_days'] ) ? absint( $service['production_days'] ) : 0,
				'delivery_label'      => isset( $service['delivery_label'] ) ? sanitize_text_field( $service['delivery_label'] ) : '',
				'badge'               => isset( $service['badge'] ) ? sanitize_text_field( $service['badge'] ) : '',
				'show_service'        => ! empty( $service['show_service'] ) ? 'yes' : 'no',
				'sort_order'          => isset( $service['sort_order'] ) ? absint( $service['sort_order'] ) : 0,
			);
		}

		$quantities      = isset( $matrix['quantities'] ) && is_array( $matrix['quantities'] ) ? $matrix['quantities'] : array();
		$seen_quantities = array();
		foreach ( $quantities as $quantity ) {
			if ( ! is_array( $quantity ) ) {
				continue;
			}

			$quantity_value = isset( $quantity['quantity'] ) ? absint( $quantity['quantity'] ) : 0;
			if ( $quantity_value <= 0 || in_array( $quantity_value, $seen_quantities, true ) ) {
				continue;
			}
			$seen_quantities[] = $quantity_value;

			$sanitized['quantities'][] = array(
				'quantity'      => $quantity_value,
				'show_quantity' => ! empty( $quantity['show_quantity'] ) ? 'yes' : 'no',
				'sort_order'    => isset( $quantity['sort_order'] ) ? absint( $quantity['sort_order'] ) : 0,
			);
		}

		usort(
			$sanitized['services'],
			function ( $left, $right ) {
				return (int) $left['sort_order'] - (int) $right['sort_order'];
			}
		);
		usort(
			$sanitized['quantities'],
			function ( $left, $right ) {
				return (int) $left['sort_order'] - (int) $right['sort_order'];
			}
		);

		$cells_by_pair = array();
		$grid          = isset( $matrix['grid'] ) && is_array( $matrix['grid'] ) ? $matrix['grid'] : array();
		foreach ( $grid as $quantity_key => $service_map ) {
			$quantity_value = absint( $quantity_key );
			if ( $quantity_value <= 0 || ! is_array( $service_map ) ) {
				continue;
			}

			foreach ( $service_map as $service_key => $cell ) {
				$service_key = sanitize_key( $service_key );
				if ( '' === $service_key || ! is_array( $cell ) ) {
					continue;
				}
				$pair_key = $quantity_value . '|' . $service_key;
				$cells_by_pair[ $pair_key ] = array(
					'quantity'    => $quantity_value,
					'service_key' => $service_key,
					'base_price'  => isset( $cell['base_price'] ) && is_numeric( $cell['base_price'] ) ? (float) $cell['base_price'] : 0,
					'final_price' => isset( $cell['final_price'] ) && is_numeric( $cell['final_price'] ) ? (float) $cell['final_price'] : 0,
					'unit_price'  => isset( $cell['unit_price'] ) && is_numeric( $cell['unit_price'] ) ? (float) $cell['unit_price'] : 0,
					'available'   => ! empty( $cell['available'] ) ? 'yes' : 'no',
					'recommended' => ! empty( $cell['recommended'] ) ? 'yes' : 'no',
				);
			}
		}

		// Backward compatibility: accept legacy matrix[cells] repeater payload if grid is not present.
		if ( empty( $cells_by_pair ) ) {
			$legacy_cells = isset( $matrix['cells'] ) && is_array( $matrix['cells'] ) ? $matrix['cells'] : array();
			foreach ( $legacy_cells as $cell ) {
				if ( ! is_array( $cell ) ) {
					continue;
				}
				$quantity_value = isset( $cell['quantity'] ) ? absint( $cell['quantity'] ) : 0;
				$service_key    = isset( $cell['service_key'] ) ? sanitize_key( $cell['service_key'] ) : '';
				if ( $quantity_value <= 0 || '' === $service_key ) {
					continue;
				}
				$pair_key = $quantity_value . '|' . $service_key;
				$cells_by_pair[ $pair_key ] = array(
					'quantity'    => $quantity_value,
					'service_key' => $service_key,
					'base_price'  => isset( $cell['base_price'] ) && is_numeric( $cell['base_price'] ) ? (float) $cell['base_price'] : 0,
					'final_price' => isset( $cell['final_price'] ) && is_numeric( $cell['final_price'] ) ? (float) $cell['final_price'] : 0,
					'unit_price'  => isset( $cell['unit_price'] ) && is_numeric( $cell['unit_price'] ) ? (float) $cell['unit_price'] : 0,
					'available'   => ! empty( $cell['available'] ) ? 'yes' : 'no',
					'recommended' => ! empty( $cell['recommended'] ) ? 'yes' : 'no',
				);
			}
		}

		$allowed_services  = wp_list_pluck( $sanitized['services'], 'service_key' );
		$allowed_quantities = wp_list_pluck( $sanitized['quantities'], 'quantity' );
		foreach ( $cells_by_pair as $cell ) {
			if ( ! in_array( $cell['service_key'], $allowed_services, true ) ) {
				continue;
			}
			if ( ! in_array( $cell['quantity'], $allowed_quantities, true ) ) {
				continue;
			}
			$sanitized['cells'][] = $cell;
		}

		return $sanitized;
	}
	/**
	 * Sanitize attributes list.
	 *
	 * @param array $attributes Attributes input.
	 *
	 * @return array
	 */
	private function sanitize_attributes( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$sanitized  = array();

		foreach ( $attributes as $attribute ) {
			if ( ! is_array( $attribute ) ) {
				continue;
			}

			$display_type = isset( $attribute['display_type'] ) ? sanitize_key( $attribute['display_type'] ) : 'dropdown';
			$display_type = in_array( $display_type, array( 'dropdown', 'icon', 'image', 'text' ), true ) ? $display_type : 'dropdown';

			$options        = isset( $attribute['options'] ) && is_array( $attribute['options'] ) ? $attribute['options'] : array();
			$clean_options  = array();
			$default_option = isset( $attribute['default_option'] ) ? sanitize_text_field( $attribute['default_option'] ) : '';

			foreach ( $options as $option ) {
				if ( ! is_array( $option ) ) {
					continue;
				}

				$value_key = isset( $option['value_key'] ) ? sanitize_text_field( $option['value_key'] ) : '';
				if ( '' === $value_key ) {
					continue;
				}

				$clean_options[] = array(
					'value_key'   => $value_key,
					'label'       => isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '',
					'description' => isset( $option['description'] ) ? sanitize_text_field( $option['description'] ) : '',
					'show_option' => ! empty( $option['show_option'] ) ? 'yes' : 'no',
					'badge'       => isset( $option['badge'] ) ? sanitize_text_field( $option['badge'] ) : '',
					'image_id'    => isset( $option['image_id'] ) ? absint( $option['image_id'] ) : 0,
				);
			}

			if ( empty( $clean_options ) ) {
				continue;
			}

			$default_in_options = wp_list_pluck( $clean_options, 'value_key' );
			$default_option     = in_array( $default_option, $default_in_options, true ) ? $default_option : $clean_options[0]['value_key'];

			$sanitized[] = array(
				'attribute_key'   => isset( $attribute['attribute_key'] ) ? sanitize_text_field( $attribute['attribute_key'] ) : '',
				'attribute_label' => isset( $attribute['attribute_label'] ) ? sanitize_text_field( $attribute['attribute_label'] ) : '',
				'show_attribute'  => ! empty( $attribute['show_attribute'] ) ? 'yes' : 'no',
				'display_type'    => $display_type,
				'help_text'       => isset( $attribute['help_text'] ) ? sanitize_text_field( $attribute['help_text'] ) : '',
				'required'        => ! empty( $attribute['required'] ) ? 'yes' : 'no',
				'sort_order'      => isset( $attribute['sort_order'] ) ? absint( $attribute['sort_order'] ) : 0,
				'default_option'  => $default_option,
				'options'         => $clean_options,
			);
		}

		usort(
			$sanitized,
			function ( $left, $right ) {
				return (int) $left['sort_order'] - (int) $right['sort_order'];
			}
		);

		return $sanitized;
	}

	/**
	 * Sanitize preview mapping rows.
	 *
	 * @param array $mappings Preview mappings payload.
	 *
	 * @return array
	 */
	private function sanitize_preview_mappings( $mappings ) {
		$mappings  = is_array( $mappings ) ? $mappings : array();
		$sanitized = array();

		foreach ( $mappings as $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}
			$attribute_key = sanitize_key( isset( $mapping['attribute_key'] ) ? $mapping['attribute_key'] : '' );
			$option_key    = sanitize_text_field( isset( $mapping['option_value_key'] ) ? $mapping['option_value_key'] : '' );
			$image_id      = absint( isset( $mapping['image_id'] ) ? $mapping['image_id'] : 0 );
			if ( '' === $attribute_key || '' === $option_key || $image_id <= 0 ) {
				continue;
			}

			$sanitized[] = array(
				'attribute_key'    => $attribute_key,
				'option_value_key' => $option_key,
				'image_id'         => $image_id,
				'label'            => sanitize_text_field( isset( $mapping['label'] ) ? $mapping['label'] : '' ),
				'sort_order'       => absint( isset( $mapping['sort_order'] ) ? $mapping['sort_order'] : 0 ),
			);
		}

		usort(
			$sanitized,
			function ( $left, $right ) {
				return (int) $left['sort_order'] - (int) $right['sort_order'];
			}
		);

		return $sanitized;
	}

	/**
	 * Sanitize conditional rules.
	 *
	 * @param array $rules Rule payload.
	 *
	 * @return array
	 */
	private function sanitize_conditional_rules( $rules ) {
		$rules     = is_array( $rules ) ? $rules : array();
		$sanitized = array();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$target_type = isset( $rule['target_type'] ) ? sanitize_key( $rule['target_type'] ) : 'attribute';
			$target_type = in_array( $target_type, array( 'attribute', 'option' ), true ) ? $target_type : 'attribute';
			$operator    = isset( $rule['operator'] ) ? sanitize_key( $rule['operator'] ) : 'equals';
			$operator    = in_array( $operator, array( 'equals', 'not_equals', 'in_list' ), true ) ? $operator : 'equals';
			$action      = isset( $rule['action'] ) ? sanitize_key( $rule['action'] ) : 'show';
			$action      = in_array( $action, array( 'show', 'hide' ), true ) ? $action : 'show';

			$target_attribute_key = sanitize_key( isset( $rule['target_attribute_key'] ) ? $rule['target_attribute_key'] : '' );
			$condition_key        = sanitize_key( isset( $rule['condition_attribute_key'] ) ? $rule['condition_attribute_key'] : '' );
			$target_option_key    = sanitize_text_field( isset( $rule['target_option_value_key'] ) ? $rule['target_option_value_key'] : '' );
			$values_raw           = isset( $rule['condition_values'] ) ? $rule['condition_values'] : array();

			if ( ! is_array( $values_raw ) ) {
				$values_raw = explode( ',', (string) $values_raw );
			}

			$condition_values = array();
			foreach ( $values_raw as $value ) {
				$value = sanitize_text_field( trim( (string) $value ) );
				if ( '' !== $value ) {
					$condition_values[] = $value;
				}
			}
			$condition_values = array_values( array_unique( $condition_values ) );

			if ( '' === $target_attribute_key || '' === $condition_key || empty( $condition_values ) ) {
				continue;
			}

			if ( 'option' === $target_type && '' === $target_option_key ) {
				continue;
			}

			$sanitized[] = array(
				'target_type'             => $target_type,
				'target_attribute_key'    => $target_attribute_key,
				'target_option_value_key' => $target_option_key,
				'condition_attribute_key' => $condition_key,
				'operator'                => $operator,
				'condition_values'        => $condition_values,
				'action'                  => $action,
				'sort_order'              => isset( $rule['sort_order'] ) ? absint( $rule['sort_order'] ) : 0,
			);
		}

		usort(
			$sanitized,
			function ( $left, $right ) {
				return (int) $left['sort_order'] - (int) $right['sort_order'];
			}
		);

		return $sanitized;
	}


	/**
	 * Sanitize extra services config.
	 *
	 * @param array $extra_services Extra services payload.
	 *
	 * @return array
	 */
	private function sanitize_extra_services( $extra_services ) {
		$extra_services = is_array( $extra_services ) ? $extra_services : array();
		$sanitized      = array();

		foreach ( array( 'preflight', 'design' ) as $service_key ) {
			$current                   = isset( $extra_services[ $service_key ] ) && is_array( $extra_services[ $service_key ] ) ? $extra_services[ $service_key ] : array();
			$sanitized[ $service_key ] = array(
				'enabled'     => ! empty( $current['enabled'] ) ? 'yes' : 'no',
				'title'       => isset( $current['title'] ) ? sanitize_text_field( $current['title'] ) : '',
				'description' => isset( $current['description'] ) ? sanitize_textarea_field( $current['description'] ) : '',
				'image_id'    => isset( $current['image_id'] ) ? absint( $current['image_id'] ) : 0,
			);
		}

		return $sanitized;
	}
}
