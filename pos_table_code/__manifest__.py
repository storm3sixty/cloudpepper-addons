# -*- coding: utf-8 -*-
{
    'name': "POS Table Code",
    'version': "1.0",
    'category': "Point of Sale",
    'summary': "Add table codes (like A1, A2, A3) to restaurant tables",
    'description': """
This module adds a new field 'table_code' to restaurant tables in POS Restaurant.
It allows you to assign codes like A1, A2, A3 to tables.
""",
    'author': "Your Name / Your Company",
    'website': "https://www.example.com",
    'depends': [
        'pos_restaurant',
        'pos_self_order',  # if you need it
    ],
    'data': [
        # Views
        'views/x_table_code_views.xml',  # Correct path, inside views/
        
        # If you ever have security or data files, include them here
        # 'security/ir.model.access.csv',
        # 'data/some_data.xml',
    ],
    'assets': {
        'web.assets_backend': [
            'pos_table_code/static/src/js/load_table_code.js',
            'pos_table_code/static/src/js/table_label.js',
        ],
    },
    'installable': True,
    'application': False,
    'auto_install': False,
}
















