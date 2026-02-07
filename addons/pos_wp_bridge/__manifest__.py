{
    "name": "POS WordPress Bridge",
    "version": "18.0.1.0.0",
    "summary": "Connect WordPress/WooCommerce bookings and orders with Odoo POS",
    "category": "Point of Sale",
    "author": "CloudPepper",
    "license": "LGPL-3",
    "depends": ["base", "point_of_sale", "web", "bus"],
    "data": [
        "security/ir.model.access.csv",
        "data/ir_cron.xml",
        "views/pos_wp_bridge_views.xml",
    ],
    "assets": {
        "point_of_sale._assets_pos": [
            "pos_wp_bridge/static/src/js/pos_wp_booking_button.js",
        ],
    },
    "installable": True,
    "application": True,
}
