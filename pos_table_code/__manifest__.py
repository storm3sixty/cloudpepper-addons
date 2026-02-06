{
    'name': "POS Table Code",
    'version': '1.0',
    'summary': "Allows text table names in POS restaurant tables",
    'description': """
        This module allows using text-based table names in POS restaurant tables.
        It adds a new field 'Table Name / Code' to the restaurant table model.
        The table name will appear in POS, on kitchen receipts, and payment receipts.
    """,
    'author': "Your Name",
    'website': "https://www.yourcompany.com",
    'category': 'Point of Sale',
    'license': 'LGPL-3',
    'depends': [
        'point_of_sale',
        'pos_restaurant',  # Make sure this module is installed
    ],
    'data': [
        'views/x_table_code_views.xml',
        'views/pos_receipts.xml',  # <- add this line
    ],
    'assets': {
        'point_of_sale.assets': [
            'pos_table_code/static/src/js/load_table_code.js',
            'pos_table_code/static/src/js/table_label.js',
        ],
    },
    'installable': True,
    'application': False,
    'auto_install': False,
}

