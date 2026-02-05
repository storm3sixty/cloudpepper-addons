{
    "name": "POS Table Alphanumeric Codes",
    "version": "18.0.1.0.0",
    "depends": ["pos_restaurant"],
    "data": [
        "views/restaurant_table_views.xml",
    ],
    "assets": {
        "point_of_sale.assets": [
            "pos_table_code/static/src/js/load_table_code.js",
            "pos_table_code/static/src/js/table_label.js",
        ],
    },
    "installable": True,
}
