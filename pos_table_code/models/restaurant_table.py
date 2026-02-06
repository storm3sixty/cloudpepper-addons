from odoo import models, fields

class RestaurantTable(models.Model):
    _inherit = 'restaurant.table'

    table_code = fields.Char(string='Table Name')  # This allows letters, numbers, anything
