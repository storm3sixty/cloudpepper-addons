# -*- coding: utf-8 -*-
{
    'name': 'POS Table Code',
    'version': '1.0',
    'summary': 'Allow text names for POS restaurant tables',
    'description': """
This module allows restaurant tables to have custom text names
instead of only numbers. Table names will appear in the POS table selection.
""",
    'author': 'Your Name',
    'website': 'https://yourwebsite.com',
    'category': 'Point of Sale',
    'license': 'LGPL-3',
    'depends': [
        'point_of_sale',
    ],
    'data': [
        'views/x_table_code_views.xml',
    ],
    'installable': True,
    'application': False,
    'auto_install': False,
}
