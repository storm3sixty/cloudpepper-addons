from odoo import models, fields

class RestaurantTable(models.Model):
    _inherit = 'restaurant.table'

    table_code = fields.Char(
        string="Table Code",
        help="Custom table label like A1, B2, VIP"
    )
