# -*- coding: utf-8 -*-
{
    'name': 'POS Table Code',
    'version': '1.0',
    'summary': 'Allow text names for restaurant tables in POS',
    'description': """
This module allows restaurant tables to have custom text names
instead of only numbers. Table names will appear in the POS table selection.
""",
    'author': 'Your Name or Company',
    'website': 'https://yourwebsite.com',
    'category': 'Point of Sale',
    'license': 'LGPL-3',
    'depends': [
        'point_of_sale',
        'restaurant',  # make sure this module is installed
    ],
    'data': [
        'views/x_table_code_views.xml',  # form view for table_code field
    ],
    'demo': [
        # optional demo data if you want
    ],
    'installable': True,
    'application': False,
    'auto_install': False,
}
