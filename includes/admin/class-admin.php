<?php
/**
 * Admin menu/settings.
 *
 * @package TradeprintConfigurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller.
 */
class TPCW_Admin {

	/**
	 * Loader.
	 *
	 * @var TPCW_Loader
	 */
	private $loader;

	/**
	 * Parent menu slug.
	 *
	 * @var string
	 */
	private $menu_slug = 'tpcw-dashboard';

	/**
	 * Constructor.
	 *
	 * @param TPCW_Loader $loader Loader.
	 */
	public function __construct( TPCW_Loader $loader ) {
		$this->loader = $loader;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_tpcw_clear_logs', array( $this, 'handle_clear_logs' ) );
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		add_menu_page(
			esc_html__( 'Tradeprint Configurator', 'tradeprint-configurator' ),
			esc_html__( 'Tradeprint Configurator', 'tradeprint-configurator' ),
			'manage_woocommerce',
			$this->menu_slug,
			array( $this, 'render_dashboard_page' ),
			'dashicons-admin-generic',
			56
		);

		add_submenu_page(
			$this->menu_slug,
			esc_html__( 'Settings', 'tradeprint-configurator' ),
			esc_html__( 'Settings', 'tradeprint-configurator' ),
			'manage_woocommerce',
			'tpcw-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			$this->menu_slug,
			esc_html__( 'Logs', 'tradeprint-configurator' ),
			esc_html__( 'Logs', 'tradeprint-configurator' ),
			'manage_woocommerce',
			'tpcw-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'tpcw_settings_group',
			TPCW_Loader::OPTION_KEY,
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param array $settings Raw settings.
	 *
	 * @return array
	 */
	public function sanitize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'sandbox_enabled'        => ! empty( $settings['sandbox_enabled'] ) ? 'yes' : 'no',
			'api_bearer_token'       => isset( $settings['api_bearer_token'] ) ? sanitize_text_field( wp_unslash( $settings['api_bearer_token'] ) ) : '',
			'global_commission'      => isset( $settings['global_commission'] ) ? (float) $settings['global_commission'] : 0,
			'extend_delivery_days'   => isset( $settings['extend_delivery_days'] ) ? absint( $settings['extend_delivery_days'] ) : 0,
			'default_order_mode'     => isset( $settings['default_order_mode'] ) && in_array( $settings['default_order_mode'], array( 'manual', 'auto' ), true ) ? $settings['default_order_mode'] : 'manual',
			'enable_debug_logging'   => ! empty( $settings['enable_debug_logging'] ) ? 'yes' : 'no',
			'submission_mode'        => isset( $settings['submission_mode'] ) && in_array( $settings['submission_mode'], array( 'mock', 'live' ), true ) ? $settings['submission_mode'] : 'mock',
		);
	}

	/**
	 * Enqueue admin assets only for plugin screens.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		$allowed = array(
			'toplevel_page_' . $this->menu_slug,
			$this->menu_slug . '_page_tpcw-settings',
			$this->menu_slug . '_page_tpcw-logs',
			$this->menu_slug . '_page_tpcw-product-library',
		);

		if ( ! in_array( $hook_suffix, $allowed, true ) ) {
			return;
		}

		wp_enqueue_style(
			'tpcw-admin',
			TPCW_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			TPCW_VERSION
		);

		wp_enqueue_script(
			'tpcw-admin',
			TPCW_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			TPCW_VERSION,
			true
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Tradeprint Configurator', 'tradeprint-configurator' ); ?></h1>
			<p><?php echo esc_html__( 'Version 1 scaffold is active. API wiring will be added in the next implementation step.', 'tradeprint-configurator' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = get_option( TPCW_Loader::OPTION_KEY, array() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Tradeprint Configurator Settings', 'tradeprint-configurator' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'tpcw_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row"><label for="tpcw_sandbox_enabled"><?php echo esc_html__( 'Sandbox enabled', 'tradeprint-configurator' ); ?></label></th>
						<td><input type="checkbox" id="tpcw_sandbox_enabled" name="tpcw_settings[sandbox_enabled]" value="1" <?php checked( isset( $settings['sandbox_enabled'] ) ? $settings['sandbox_enabled'] : 'no', 'yes' ); ?> /></td>
					</tr>
					<tr>
						<th scope="row"><label for="tpcw_api_bearer_token"><?php echo esc_html__( 'API bearer token', 'tradeprint-configurator' ); ?></label></th>
						<td><input type="password" class="regular-text" id="tpcw_api_bearer_token" name="tpcw_settings[api_bearer_token]" value="<?php echo esc_attr( isset( $settings['api_bearer_token'] ) ? $settings['api_bearer_token'] : '' ); ?>" autocomplete="new-password" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="tpcw_global_commission"><?php echo esc_html__( 'Global commission percent', 'tradeprint-configurator' ); ?></label></th>
						<td><input type="number" min="0" max="100" step="0.01" id="tpcw_global_commission" name="tpcw_settings[global_commission]" value="<?php echo esc_attr( isset( $settings['global_commission'] ) ? $settings['global_commission'] : 0 ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="tpcw_extend_delivery_days"><?php echo esc_html__( 'Extend estimated delivery days', 'tradeprint-configurator' ); ?></label></th>
						<td><input type="number" min="0" step="1" id="tpcw_extend_delivery_days" name="tpcw_settings[extend_delivery_days]" value="<?php echo esc_attr( isset( $settings['extend_delivery_days'] ) ? $settings['extend_delivery_days'] : 0 ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="tpcw_default_order_mode"><?php echo esc_html__( 'Default order mode', 'tradeprint-configurator' ); ?></label></th>
						<td>
							<select id="tpcw_default_order_mode" name="tpcw_settings[default_order_mode]">
								<option value="manual" <?php selected( isset( $settings['default_order_mode'] ) ? $settings['default_order_mode'] : 'manual', 'manual' ); ?>><?php echo esc_html__( 'Manual', 'tradeprint-configurator' ); ?></option>
								<option value="auto" <?php selected( isset( $settings['default_order_mode'] ) ? $settings['default_order_mode'] : 'manual', 'auto' ); ?>><?php echo esc_html__( 'Auto', 'tradeprint-configurator' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tpcw_enable_debug_logging"><?php echo esc_html__( 'Enable debug logging', 'tradeprint-configurator' ); ?></label></th>
						<td><input type="checkbox" id="tpcw_enable_debug_logging" name="tpcw_settings[enable_debug_logging]" value="1" <?php checked( isset( $settings['enable_debug_logging'] ) ? $settings['enable_debug_logging'] : 'no', 'yes' ); ?> /></td>
					</tr>
					<tr>
						<th scope="row"><label for="tpcw_submission_mode"><?php echo esc_html__( 'Order submission mode', 'tradeprint-configurator' ); ?></label></th>
						<td>
							<select id="tpcw_submission_mode" name="tpcw_settings[submission_mode]">
								<option value="mock" <?php selected( isset( $settings['submission_mode'] ) ? $settings['submission_mode'] : 'mock', 'mock' ); ?>><?php echo esc_html__( 'Mock mode', 'tradeprint-configurator' ); ?></option>
								<option value="live" <?php selected( isset( $settings['submission_mode'] ) ? $settings['submission_mode'] : 'mock', 'live' ); ?>><?php echo esc_html__( 'Live submission mode', 'tradeprint-configurator' ); ?></option>
							</select>
						</td>
					</tr>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render logs placeholder.
	 *
	 * @return void
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$entries = array_reverse( TPCW_Logger::get_logs() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Tradeprint Logs', 'tradeprint-configurator' ); ?></h1>
			<p><?php echo esc_html__( 'Recent safe debug entries. Bearer token values are never stored in logs.', 'tradeprint-configurator' ); ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpcw_clear_logs' ), 'tpcw_clear_logs' ) ); ?>"><?php echo esc_html__( 'Clear Logs', 'tradeprint-configurator' ); ?></a>
			</p>
			<?php if ( empty( $entries ) ) : ?>
				<p><?php echo esc_html__( 'No logs available.', 'tradeprint-configurator' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php echo esc_html__( 'Time', 'tradeprint-configurator' ); ?></th><th><?php echo esc_html__( 'Level', 'tradeprint-configurator' ); ?></th><th><?php echo esc_html__( 'Message', 'tradeprint-configurator' ); ?></th><th><?php echo esc_html__( 'Context', 'tradeprint-configurator' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $entries as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $entry['timestamp'] ) ? $entry['timestamp'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $entry['level'] ) ? $entry['level'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $entry['message'] ) ? $entry['message'] : '' ); ?></td>
							<td><code><?php echo esc_html( wp_json_encode( isset( $entry['context'] ) ? $entry['context'] : array() ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle clear logs action.
	 *
	 * @return void
	 */
	public function handle_clear_logs() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to clear logs.', 'tradeprint-configurator' ) );
		}

		check_admin_referer( 'tpcw_clear_logs' );
		TPCW_Logger::clear_logs();
		wp_safe_redirect( admin_url( 'admin.php?page=tpcw-logs' ) );
		exit;
	}
}
