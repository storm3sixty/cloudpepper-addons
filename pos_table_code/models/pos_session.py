from odoo import models


class PosSession(models.Model):
    _inherit = "pos.session"

    def _loader_params_restaurant_table(self):
        params = super()._loader_params_restaurant_table()
        fields = params.setdefault("search_params", {}).setdefault("fields", [])
        for field_name in ("table_code", "name"):
            if field_name not in fields:
                fields.append(field_name)
        return params
