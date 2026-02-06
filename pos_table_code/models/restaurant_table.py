# models/restaurant_table.py
from odoo import models, fields

class RestaurantTable(models.Model):
    _inherit = "restaurant.table"

    table_code = fields.Char(string="Table Code")















