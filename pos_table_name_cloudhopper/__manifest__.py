{
    "name": "POS Restaurant Table Names",
    "summary": "Use custom table names in POS restaurant flows",
    "version": "18.0.1.0.0",
    "category": "Point of Sale",
    "author": "CloudHopper",
    "license": "LGPL-3",
    "depends": ["point_of_sale", "pos_restaurant"],
    "data": [
        "views/restaurant_table_views.xml",
    ],
    "assets": {
        "point_of_sale._assets_pos": [
            "pos_table_name_cloudhopper/static/src/js/table_name_patch.js",
        ],
    },
    "installable": True,
    "application": False,
}
