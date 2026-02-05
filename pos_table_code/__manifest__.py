{
    "name": "POS Table Alphanumeric Codes",
    "version": "19.0.1.0.0",
    "summary": "Add alphanumeric codes to restaurant tables in POS",
    "category": "Point of Sale",
    "author": "Your Name",
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
    "application": False,
    "auto_install": False,
}





