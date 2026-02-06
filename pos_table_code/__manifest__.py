{
    "name": "POS Table Text Names",
    "summary": "Allow text table names in restaurant tables and POS",
    "description": "Adds a table_code field to restaurant tables and uses it in POS floor and receipts.",
    "version": "18.0.1.0",
    "category": "Point of Sale",
    "author": "CloudHopper",
    "website": "https://cloudhopper.example.com",
    "license": "LGPL-3",
    "depends": ["point_of_sale", "pos_restaurant"],
    "data": [],
    "assets": {
        "point_of_sale._assets_pos": [
            "pos_table_code/static/src/js/table_name_patch.js",
        ],
    },
    "post_init_hook": "post_init_hook",
    "installable": True,
    "application": False,
}
