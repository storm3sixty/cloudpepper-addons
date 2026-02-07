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
            table_code = (table.get("table_code") or "").strip()
            table_number = table.get("table_number")
            if not table_code:
                continue

            prefix = str(table_number) if table_number not in (None, False, "") else ""
            label = f"{prefix} - {table_code}" if prefix else table_code

            # POS floor UI in Odoo 18 primarily renders table_number.
            # We keep DB numeric value untouched and only alias payload.
            table["table_name"] = label
            table["display_name"] = label
            table["table_number"] = label
        return tables
