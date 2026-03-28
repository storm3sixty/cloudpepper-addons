<?php
/**
 * Product library admin page.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product library controller.
 */
class TPCW_Product_Library {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'tpcw-product-library';

	/**
	 * Import service.
	 *
	 * @var TPCW_Catalogue_Importer
	 */
	private $importer;

	/**
	 * Constructor.
	 *
	 * @param TPCW_Loader $loader Loader.
	 */
	public function __construct( TPCW_Loader $loader ) {
		$this->importer = new TPCW_Catalogue_Importer();
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Register product library submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		add_submenu_page(
			'tpcw-dashboard',
			esc_html__( 'Product Library', 'tradeprint-configurator' ),
			esc_html__( 'Product Library', 'tradeprint-configurator' ),
			'manage_woocommerce',
			$this->page_slug,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle page actions.
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! isset( $_POST['tpcw_library_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['tpcw_library_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'tpcw_product_library_action' ) ) {
			return;
		}

		$action = isset( $_POST['tpcw_library_action'] ) ? sanitize_key( wp_unslash( $_POST['tpcw_library_action'] ) ) : '';
		if ( '' === $action ) {
			return;
		}

		if ( 'tpcw-product-library' !== ( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' ) ) {
			return;
		}

		if ( 'pull_products' === $action || 'refresh_catalogue' === $action ) {
			$json_payload = isset( $_POST['tpcw_catalogue_json'] ) ? wp_unslash( $_POST['tpcw_catalogue_json'] ) : '';
			$json_payload = is_string( $json_payload ) ? trim( $json_payload ) : '';
			if ( '' === $json_payload ) {
				$json_payload = $this->get_default_mock_catalogue_json();
			}

			$items = $this->importer->pull_from_json( $json_payload, 'manual_json' );
			if ( is_wp_error( $items ) ) {
				add_settings_error( 'tpcw_product_library', 'tpcw_library_error', $items->get_error_message(), 'error' );
			} else {
				/*
				 * TODO: Replace manual JSON source with live Tradeprint catalogue API pull.
				 * The response can be fed into normalize_catalogue_payload() to reuse this pipeline.
				 */
				add_settings_error( 'tpcw_product_library', 'tpcw_library_pulled', sprintf( esc_html__( 'Catalogue updated. %d product(s) available.', 'tradeprint-configurator' ), count( $items ) ), 'updated' );
			}
		}

		if ( 'add_to_site' === $action || 'sync_item' === $action ) {
			$product_key = isset( $_POST['product_key'] ) ? sanitize_text_field( wp_unslash( $_POST['product_key'] ) ) : '';
			$result      = $this->importer->import_product_to_site( $product_key, 'sync_item' === $action );
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'tpcw_product_library', 'tpcw_library_import_error', $result->get_error_message(), 'error' );
			} else {
				$message = ! empty( $result['created'] )
					? esc_html__( 'WooCommerce product created and linked.', 'tradeprint-configurator' )
					: esc_html__( 'WooCommerce product synced from library source.', 'tradeprint-configurator' );
				add_settings_error( 'tpcw_product_library', 'tpcw_library_imported', $message, 'updated' );
			}
		}
	}

	/**
	 * Render library page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$catalogue = $this->importer->get_catalogue();
		$products  = isset( $catalogue['products'] ) && is_array( $catalogue['products'] ) ? $catalogue['products'] : array();
		$textarea_default = $this->get_default_mock_catalogue_json();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Tradeprint Product Library', 'tradeprint-configurator' ); ?></h1>
			<p><?php echo esc_html__( 'Pull products from a mock/manual catalogue source now. Live Tradeprint catalogue pull can replace this source in a future update.', 'tradeprint-configurator' ); ?></p>
			<?php settings_errors( 'tpcw_product_library' ); ?>

			<form method="post">
				<?php wp_nonce_field( 'tpcw_product_library_action', 'tpcw_library_nonce' ); ?>
				<input type="hidden" name="tpcw_library_action" value="pull_products" />
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row"><label for="tpcw_catalogue_json"><?php echo esc_html__( 'Mock/manual catalogue JSON', 'tradeprint-configurator' ); ?></label></th>
						<td>
							<textarea id="tpcw_catalogue_json" name="tpcw_catalogue_json" rows="10" class="large-text code" placeholder="{ &quot;products&quot;: [] }"><?php echo esc_textarea( $textarea_default ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Use this fallback while live API pull is not connected.', 'tradeprint-configurator' ); ?></p>
						</td>
					</tr>
					</tbody>
				</table>
				<p>
					<button type="submit" class="button button-primary"><?php echo esc_html__( 'Pull Products', 'tradeprint-configurator' ); ?></button>
					<button type="submit" class="button" name="tpcw_library_action" value="refresh_catalogue"><?php echo esc_html__( 'Refresh Catalogue', 'tradeprint-configurator' ); ?></button>
				</p>
			</form>

			<?php if ( empty( $products ) ) : ?>
				<p><?php echo esc_html__( 'No products in your library yet. Pull products to begin importing.', 'tradeprint-configurator' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Product title', 'tradeprint-configurator' ); ?></th>
							<th><?php echo esc_html__( 'Product key', 'tradeprint-configurator' ); ?></th>
							<th><?php echo esc_html__( 'Imported status', 'tradeprint-configurator' ); ?></th>
							<th><?php echo esc_html__( 'Linked WooCommerce product', 'tradeprint-configurator' ); ?></th>
							<th><?php echo esc_html__( 'Attribute count', 'tradeprint-configurator' ); ?></th>
							<th><?php echo esc_html__( 'Option count', 'tradeprint-configurator' ); ?></th>
							<th><?php echo esc_html__( 'Source mode', 'tradeprint-configurator' ); ?></th>
							<th><?php echo esc_html__( 'Last pulled/imported', 'tradeprint-configurator' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'tradeprint-configurator' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $products as $item ) : ?>
						<?php $this->render_item_row( $item ); ?>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render one table row.
	 *
	 * @param array $item Catalogue item.
	 *
	 * @return void
	 */
	private function render_item_row( $item ) {
		$item            = is_array( $item ) ? $item : array();
		$product_key     = isset( $item['product_key'] ) ? sanitize_text_field( $item['product_key'] ) : '';
		$product_title   = isset( $item['product_title'] ) ? sanitize_text_field( $item['product_title'] ) : $product_key;
		$linked_product  = isset( $item['linked_product_id'] ) ? absint( $item['linked_product_id'] ) : 0;
		$product_status  = $linked_product ? get_post_status( $linked_product ) : '';
		$status_label    = $this->get_status_label( $item, $product_status );
		$linked_label    = $linked_product ? get_the_title( $linked_product ) : esc_html__( '—', 'tradeprint-configurator' );
		$edit_url        = $linked_product ? get_edit_post_link( $linked_product, '' ) : '';
		$last_sync       = isset( $item['last_imported_at'] ) && '' !== $item['last_imported_at'] ? $item['last_imported_at'] : esc_html__( 'Not yet imported', 'tradeprint-configurator' );
		$last_pulled     = isset( $item['last_pulled_at'] ) ? sanitize_text_field( $item['last_pulled_at'] ) : '';
		?>
		<tr>
			<td><?php echo esc_html( $product_title ); ?></td>
			<td><code><?php echo esc_html( $product_key ); ?></code></td>
			<td><?php echo esc_html( $status_label ); ?></td>
			<td>
				<?php if ( $linked_product ) : ?>
					<?php echo esc_html( $linked_label ); ?>
					(<?php echo esc_html( '#' . $linked_product ); ?>)
				<?php else : ?>
					<?php echo esc_html__( 'Not linked', 'tradeprint-configurator' ); ?>
				<?php endif; ?>
			</td>
			<td><?php echo esc_html( (string) ( isset( $item['attribute_count'] ) ? absint( $item['attribute_count'] ) : 0 ) ); ?></td>
			<td><?php echo esc_html( (string) ( isset( $item['option_count'] ) ? absint( $item['option_count'] ) : 0 ) ); ?></td>
			<td><?php echo esc_html( isset( $item['source_mode'] ) ? sanitize_text_field( $item['source_mode'] ) : 'manual_json' ); ?></td>
			<td>
				<?php echo esc_html( $last_pulled ); ?>
				<?php echo esc_html( ' / ' ); ?>
				<?php echo esc_html( $last_sync ); ?>
			</td>
			<td>
				<form method="post" style="display:inline-block; margin-right:6px;">
					<?php wp_nonce_field( 'tpcw_product_library_action', 'tpcw_library_nonce' ); ?>
					<input type="hidden" name="tpcw_library_action" value="<?php echo esc_attr( $linked_product ? 'sync_item' : 'add_to_site' ); ?>" />
					<input type="hidden" name="product_key" value="<?php echo esc_attr( $product_key ); ?>" />
					<button type="submit" class="button button-small"><?php echo esc_html( $linked_product ? __( 'Sync / Re-import', 'tradeprint-configurator' ) : __( 'Add Product to My Site', 'tradeprint-configurator' ) ); ?></button>
				</form>
				<?php if ( $edit_url ) : ?>
					<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html__( 'View/Edit linked WooCommerce product', 'tradeprint-configurator' ); ?></a>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Get library status label.
	 *
	 * @param array  $item Catalogue item.
	 * @param string $product_status Linked product status.
	 *
	 * @return string
	 */
	private function get_status_label( $item, $product_status ) {
		$linked_product = isset( $item['linked_product_id'] ) ? absint( $item['linked_product_id'] ) : 0;
		$last_imported  = isset( $item['last_imported_at'] ) ? sanitize_text_field( $item['last_imported_at'] ) : '';
		$last_pulled    = isset( $item['last_pulled_at'] ) ? sanitize_text_field( $item['last_pulled_at'] ) : '';

		if ( ! $linked_product ) {
			return __( 'Not Imported', 'tradeprint-configurator' );
		}

		if ( 'draft' === $product_status ) {
			return __( 'Draft WooCommerce Product', 'tradeprint-configurator' );
		}

		if ( 'publish' === $product_status ) {
			if ( $last_pulled && $last_imported && strtotime( $last_pulled ) > strtotime( $last_imported ) ) {
				return __( 'Needs Sync', 'tradeprint-configurator' );
			}
			return __( 'Published WooCommerce Product', 'tradeprint-configurator' );
		}

		return __( 'Imported / Linked', 'tradeprint-configurator' );
	}

	/**
	 * Provide default mock catalogue example.
	 *
	 * @return string
	 */
	private function get_default_mock_catalogue_json() {
		$payload = array(
			'products' => array(
				array(
					'productKey'   => 'PRD-BCARDS',
					'productTitle' => 'Business Cards',
					'attributes'   => array(
						array(
							'key'      => 'paper_type',
							'label'    => 'Paper Type',
							'required' => true,
							'options'  => array(
								array(
									'key'   => '350gsm_silk',
									'label' => '350gsm Silk',
								),
								array(
									'key'   => '450gsm_silk',
									'label' => '450gsm Silk',
								),
							),
						),
						array(
							'key'      => 'lamination',
							'label'    => 'Lamination',
							'required' => false,
							'options'  => array(
								array(
									'key'   => 'matt',
									'label' => 'Matt',
								),
								array(
									'key'   => 'gloss',
									'label' => 'Gloss',
								),
							),
						),
					),
				),
			),
		);

		return wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}
}
