from odoo import models


class PosSession(models.Model):
    _inherit = "pos.session"

    def _loader_params_restaurant_table(self):
        params = super()._loader_params_restaurant_table()
        fields = params.setdefault("search_params", {}).setdefault("fields", [])
        for field_name in ("table_code", "table_number", "display_name"):
            if field_name not in fields:
                fields.append(field_name)
        return params

    def _get_pos_ui_restaurant_table(self, params):
        tables = super()._get_pos_ui_restaurant_table(params)
        for table in tables:
            label = table.get("table_code")
            if not label:
                continue
            table["display_name"] = label
            table["table_name"] = label
        return tables
