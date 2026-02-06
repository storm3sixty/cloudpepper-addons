{
    "name": "POS Table Code",
    "version": "18.0.1.0.0",
    "category": "Point of Sale",
    "summary": "Add custom table names for POS",
    "author": "Your Name",
    "depends": ["point_of_sale", "restaurant", "pos_restaurant"],
    "data": [
        "views/x_table_code_views.xml",
    ],
    "assets": {
        "point_of_sale.assets": [
            "pos_table_code/static/src/js/load_table_code.js",
            "pos_table_code/static/src/js/table_label.js",
        ],
    },
    "installable": True,
    "application": False,
}
