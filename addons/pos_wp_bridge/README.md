# POS WordPress Bridge (Odoo 18)

This addon gives Odoo a configurable bridge to WordPress so you can:

- Store WordPress connection details in Odoo (base URL + passcode + Woo keys).
- Pull bookings from WordPress REST API.
- Pull orders from WordPress/Woo endpoint.
- Accept push webhooks from WordPress (`/pos_wp_bridge/webhook`).
- Publish booking events to POS bus channel (`pos_wp_booking_channel`) for front-end notifications.

## Odoo setup

1. Install module `pos_wp_bridge`.
2. Open **Point of Sale → POS WP Bridge → Connections**.
3. Create a connection and fill:
   - WordPress base URL
   - Odoo passcode (must match WordPress plugin passcode)
   - Woo key/secret (optional, only needed for order pull)
4. Use **Test Bridge**.
5. Use **Pull Bookings** / **Pull Orders** or send webhooks from WordPress.

## WordPress plugin pairing

Install `wordpress-plugin/wp-odoo-reservation-bridge.php` in WordPress and configure:

- Odoo webhook URL: `https://your-odoo-host/pos_wp_bridge/webhook`
- Shared passcode: same value as Odoo connector

The plugin exposes:

- `GET /wp-json/odoo-bridge/v1/ping`
- `GET /wp-json/odoo-bridge/v1/bookings`
- `GET /wp-json/odoo-bridge/v1/orders`

All these endpoints require `X-Odoo-Passcode`.
