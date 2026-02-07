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

## Troubleshooting (module not visible in Odoo)

Having `wordpress-plugin/` in the same Git repo is **not** a problem.
Odoo only loads folders that contain `__manifest__.py` and are reachable through `addons_path`.

Check these points:

1. Your Odoo `addons_path` must include the directory that contains `pos_wp_bridge`.
   - If your path is `/workspace/cloudpepper-addons`, module must be `/workspace/cloudpepper-addons/pos_wp_bridge`.
   - In this repository, module is at `/workspace/cloudpepper-addons/addons/pos_wp_bridge`, so `addons_path` should include `/workspace/cloudpepper-addons/addons`.
2. Restart Odoo service after changing `addons_path`.
3. In Apps, enable Developer Mode and click **Update Apps List**.
4. Search `pos_wp_bridge` with Apps filter removed.

The `wordpress-plugin/` folder is ignored by Odoo because it has no Odoo manifest.

## White screen after editing `addons_path`

This is usually an Odoo config issue, not this repository layout.

**Most common cause:** replacing the default Odoo addons paths instead of appending yours.

Example (keep core paths + add custom path):

```ini
addons_path = /usr/lib/python3/dist-packages/odoo/addons,/opt/odoo/addons,/workspace/cloudpepper-addons/addons
```

If core paths are missing, Odoo web assets fail to load and you can get a white screen.

After fixing `addons_path`:

1. Restart Odoo.
2. Clear browser cache / hard refresh.
3. Open Developer Tools and check first failing request.
4. Check Odoo logs for traceback around startup or `/web` requests.
