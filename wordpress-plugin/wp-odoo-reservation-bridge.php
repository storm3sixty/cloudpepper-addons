<?php
/**
 * Plugin Name: WP Odoo Reservation Bridge
 * Description: Bridge bookings/orders from WordPress + WooCommerce to Odoo POS with passcode protection.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

const WP_ODOO_OPT_PASSCODE = 'wp_odoo_bridge_passcode';
const WP_ODOO_OPT_ODOO_WEBHOOK = 'wp_odoo_bridge_odoo_webhook';

function wp_odoo_bridge_get_passcode() {
    return (string) get_option(WP_ODOO_OPT_PASSCODE, '');
}

function wp_odoo_bridge_is_authorized_request(WP_REST_Request $request) {
    $incoming = (string) $request->get_header('X-Odoo-Passcode');
    $saved = wp_odoo_bridge_get_passcode();
    return !empty($saved) && hash_equals($saved, $incoming);
}

function wp_odoo_bridge_send_to_odoo($type, array $data) {
    $webhook = (string) get_option(WP_ODOO_OPT_ODOO_WEBHOOK, '');
    $passcode = wp_odoo_bridge_get_passcode();
    if (empty($webhook) || empty($passcode)) {
        return;
    }

    $body = wp_json_encode([
        'type' => $type,
        'data' => $data,
        'source' => home_url(),
        'sent_at' => gmdate('c'),
    ]);

    $signature = hash_hmac('sha256', $body, $passcode);

    wp_remote_post($webhook, [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Odoo-Passcode' => $passcode,
            'X-WP-Signature' => $signature,
        ],
        'body' => $body,
        'timeout' => 15,
    ]);
}

function wp_odoo_bridge_format_booking($booking_id) {
    return [
        'id' => (string) $booking_id,
        'name' => (string) get_post_meta($booking_id, 'Name', true),
        'phone' => (string) get_post_meta($booking_id, 'Phone', true),
        'email' => (string) get_post_meta($booking_id, 'Email', true),
        'party_size' => (int) get_post_meta($booking_id, 'Party', true),
        'datetime' => trim((string) get_post_meta($booking_id, 'Date', true) . ' ' . (string) get_post_meta($booking_id, 'Time', true)),
        'status' => (string) get_post_status($booking_id),
        'notes' => (string) get_post_meta($booking_id, 'Message', true),
    ];
}



function wp_odoo_bridge_format_order($order) {
    if (!$order) {
        return [];
    }

    $items = [];
    foreach ($order->get_items() as $item) {
        $items[] = [
            'name' => $item->get_name(),
            'quantity' => (float) $item->get_quantity(),
        ];
    }

    return [
        'id' => (string) $order->get_id(),
        'currency' => $order->get_currency(),
        'total' => $order->get_total(),
        'status' => $order->get_status(),
        'customer_note' => (string) $order->get_customer_note(),
        'billing' => [
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'address_1' => $order->get_billing_address_1(),
            'address_2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'postcode' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country(),
        ],
        'shipping' => [
            'first_name' => $order->get_shipping_first_name(),
            'last_name' => $order->get_shipping_last_name(),
            'address_1' => $order->get_shipping_address_1(),
            'address_2' => $order->get_shipping_address_2(),
            'city' => $order->get_shipping_city(),
            'state' => $order->get_shipping_state(),
            'postcode' => $order->get_shipping_postcode(),
            'country' => $order->get_shipping_country(),
        ],
        'line_items' => $items,
    ];
}

function wp_odoo_bridge_rest_permission(WP_REST_Request $request) {
    return wp_odoo_bridge_is_authorized_request($request) ?: new WP_Error('forbidden', 'Invalid passcode', ['status' => 403]);
}

add_action('rest_api_init', function () {
    register_rest_route('odoo-bridge/v1', '/ping', [
        'methods' => 'GET',
        'permission_callback' => 'wp_odoo_bridge_rest_permission',
        'callback' => function () {
            return ['ok' => true, 'site' => home_url()];
        },
    ]);

    register_rest_route('odoo-bridge/v1', '/bookings', [
        'methods' => 'GET',
        'permission_callback' => 'wp_odoo_bridge_rest_permission',
        'callback' => function () {
            $query = new WP_Query([
                'post_type' => 'rtb_booking',
                'post_status' => 'any',
                'posts_per_page' => 100,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);

            $result = [];
            foreach ($query->posts as $post) {
                $result[] = wp_odoo_bridge_format_booking($post->ID);
            }
            return $result;
        },
    ]);

    register_rest_route('odoo-bridge/v1', '/orders', [
        'methods' => 'GET',
        'permission_callback' => 'wp_odoo_bridge_rest_permission',
        'callback' => function () {
            if (!function_exists('wc_get_orders')) {
                return new WP_Error('wc_missing', 'WooCommerce not installed', ['status' => 400]);
            }
            $orders = wc_get_orders([
                'limit' => 100,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);

            $result = [];
            foreach ($orders as $order) {
                $result[] = wp_odoo_bridge_format_order($order);
            }
            return $result;
        },
    ]);
});

add_action('admin_menu', function () {
    add_options_page(
        'Odoo Bridge',
        'Odoo Bridge',
        'manage_options',
        'wp-odoo-bridge',
        'wp_odoo_bridge_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('wp_odoo_bridge', WP_ODOO_OPT_PASSCODE);
    register_setting('wp_odoo_bridge', WP_ODOO_OPT_ODOO_WEBHOOK);
});

function wp_odoo_bridge_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['wp_odoo_generate_passcode']) && check_admin_referer('wp_odoo_generate_passcode_action')) {
        update_option(WP_ODOO_OPT_PASSCODE, wp_generate_password(40, false, false));
        echo '<div class="notice notice-success"><p>New passcode generated.</p></div>';
    }

    $passcode = esc_attr((string) get_option(WP_ODOO_OPT_PASSCODE, ''));
    $webhook = esc_url((string) get_option(WP_ODOO_OPT_ODOO_WEBHOOK, ''));
    ?>
    <div class="wrap">
        <h1>WP Odoo Bridge</h1>
        <p>Use this passcode inside Odoo module configuration.</p>

        <form method="post" action="options.php">
            <?php settings_fields('wp_odoo_bridge'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="<?php echo esc_attr(WP_ODOO_OPT_PASSCODE); ?>">Passcode</label></th>
                    <td>
                        <input type="text" class="regular-text" id="<?php echo esc_attr(WP_ODOO_OPT_PASSCODE); ?>" name="<?php echo esc_attr(WP_ODOO_OPT_PASSCODE); ?>" value="<?php echo $passcode; ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="<?php echo esc_attr(WP_ODOO_OPT_ODOO_WEBHOOK); ?>">Odoo webhook URL</label></th>
                    <td>
                        <input type="url" class="regular-text" id="<?php echo esc_attr(WP_ODOO_OPT_ODOO_WEBHOOK); ?>" name="<?php echo esc_attr(WP_ODOO_OPT_ODOO_WEBHOOK); ?>" value="<?php echo $webhook; ?>" />
                    </td>
                </tr>
            </table>
            <?php submit_button('Save bridge settings'); ?>
        </form>

        <form method="post">
            <?php wp_nonce_field('wp_odoo_generate_passcode_action'); ?>
            <input type="hidden" name="wp_odoo_generate_passcode" value="1" />
            <?php submit_button('Generate New Passcode', 'secondary'); ?>
        </form>

        <h2>Available API endpoints</h2>
        <ul>
            <li><code><?php echo esc_html(home_url('/wp-json/odoo-bridge/v1/ping')); ?></code></li>
            <li><code><?php echo esc_html(home_url('/wp-json/odoo-bridge/v1/bookings')); ?></code></li>
            <li><code><?php echo esc_html(home_url('/wp-json/odoo-bridge/v1/orders')); ?></code></li>
        </ul>
    </div>
    <?php
}

add_action('rtb_booking_saved', function ($booking) {
    if (empty($booking) || empty($booking->ID)) {
        return;
    }
    wp_odoo_bridge_send_to_odoo('booking', wp_odoo_bridge_format_booking($booking->ID));
}, 10, 1);

add_action('woocommerce_new_order', function ($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    wp_odoo_bridge_send_to_odoo('order', wp_odoo_bridge_format_order($order));
}, 10, 1);
