from odoo import fields, models


class RestaurantTable(models.Model):
    _inherit = "restaurant.table"

    table_code = fields.Char(
        string="Table Name",
        help="Custom table name shown in POS floor and receipts.",
    )

    def _register_hook(self):
        result = super()._register_hook()
        # Ensure dynamic floor/table view extension is applied on install and update.
        from ..hooks import ensure_dynamic_views

        ensure_dynamic_views(self.env)
        return result
