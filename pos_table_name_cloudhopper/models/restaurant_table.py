from odoo import api, fields, models


class RestaurantTable(models.Model):
    _inherit = "restaurant.table"

    custom_table_name = fields.Char(
        string="POS Table Name",
        help="Name shown in POS floor view and receipts.",
    )

    @api.depends("custom_table_name", "name", "table_number")
    def _compute_display_name(self):
        super()._compute_display_name()
        for table in self:
            if table.custom_table_name:
                table.display_name = table.custom_table_name
