from odoo import models


class PosSession(models.Model):
    _inherit = "pos.session"

    def _loader_params_restaurant_table(self):
        params = super()._loader_params_restaurant_table()
        fields = params.setdefault("search_params", {}).setdefault("fields", [])
        for field_name in ("table_code", "name", "table_number", "display_name"):
            if field_name not in fields:
                fields.append(field_name)
        return params

    def _get_pos_ui_restaurant_table(self, params):
        tables = super()._get_pos_ui_restaurant_table(params)
        for table in tables:
            label = table.get("table_code") or table.get("name")
            if not label:
                continue
            # Some POS UI builds read numeric `table_number`, others read
            # `name`/`display_name`. Force all known keys to the text label.
            table["name"] = label
            table["display_name"] = label
            table["table_number"] = label
        return tables
