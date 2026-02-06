from odoo import models


class PosSession(models.Model):
    _inherit = "pos.session"

    def _loader_params_restaurant_table(self):
        params = super()._loader_params_restaurant_table()
        fields = params.setdefault("search_params", {}).setdefault("fields", [])
        for field_name in ("custom_table_name", "name", "table_number"):
            if field_name not in fields:
                fields.append(field_name)
        return params
