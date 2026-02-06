{
    'name': 'POS Table Text Names',
    'version': '1.0',
    'category': 'Point of Sale',
    'summary': 'Allow text table names in restaurant tables and POS',
    'description': 'This module adds a table_code field to restaurant tables and shows it in POS.',
    'author': 'Your Name',
    'website': 'https://yourwebsite.com',
    'depends': ['point_of_sale', 'pos_restaurant'],
    'data': [
        'views/x_table_code_views.xml',
    ],
    'installable': True,
    'application': False,
    'auto_install': False,
}
