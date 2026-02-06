{
    'name': 'POS Table Code',
    'version': '1.0',
    'summary': 'Add custom table names in POS',
    'category': 'Point of Sale',
    'depends': ['point_of_sale', 'pos_restaurant'],
    'data': [
        'views/restaurant_table_views.xml',
    ],
    'assets': {
        'point_of_sale.assets': [
            'pos_table_code/static/src/js/load_table_code.js',
            'pos_table_code/static/src/js/table_label.js',
        ],
    },
    'installable': True,
    'application': True,
}







