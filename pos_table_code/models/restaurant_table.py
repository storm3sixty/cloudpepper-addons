from odoo import fields, models


class RestaurantTable(models.Model):
    _inherit = "restaurant.table"

    table_code = fields.Char(
        string="Table Name",
        help="Custom table name shown in POS floor and receipts.",
    )
