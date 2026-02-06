from odoo import models, fields

class RestaurantTable(models.Model):
    _inherit = 'restaurant.table'

    x_table_code = fields.Char(string='Table Name')  # your custom field
