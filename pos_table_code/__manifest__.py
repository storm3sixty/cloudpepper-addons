{
    "name": "POS Table Alphanumeric Codes",
    "version": "19.0.1.0.0",
    "depends": ["pos_restaurant"],
    "data": [
        "views/restaurant_table_views.xml",
    ],
    "assets": {
        "point_of_sale.assets": [
            "static/src/js/load_table_code.js",
            "static/src/js/table_label.js",
        ],
    },
    "installable": True,
    "depends": ["pos_restaurant"],
    "application": False,
    "auto_install": False,
}



