from odoo import models


class PosSession(models.Model):
    _inherit = "pos.session"

    @staticmethod
    def _append_fields(params, extra_fields):
        fields = params.setdefault("search_params", {}).setdefault("fields", [])
        for field_name in extra_fields:
            if field_name not in fields:
                fields.append(field_name)
        return params

    def _loader_params_restaurant_table(self):
        params = super()._loader_params_restaurant_table()
        return self._append_fields(params, ("table_code", "table_number", "display_name"))

    # Odoo 18 variants may use this method name for restaurant table loading.
    def _loader_params_pos_restaurant_table(self):
        params = super()._loader_params_pos_restaurant_table()
        return self._append_fields(params, ("table_code", "table_number", "display_name"))

    def _loader_params_pos_config(self):
        params = super()._loader_params_pos_config()
        return self._append_fields(
            params,
            (
                "ui_button_labels_json",
                "ui_enable_drag_and_drop",
                "ui_table_alias_json",
                "ui_sales_receipt_note",
                "ui_kitchen_receipt_note",
            ),
        )

    def _get_pos_ui_restaurant_table(self, params):
        tables = super()._get_pos_ui_restaurant_table(params)
        for table in tables:
            code = (table.get("table_code") or "").strip()
            number = table.get("table_number")
            if not code:
                continue
            prefix = str(number).strip() if number not in (None, False, "") else ""
            label = f"{prefix} - {code}" if prefix and prefix != "0" else code
            table["display_name"] = label
            table["table_name"] = label
        return tables

    def _get_pos_ui_pos_restaurant_table(self, params):
        tables = super()._get_pos_ui_pos_restaurant_table(params)
        for table in tables:
            code = (table.get("table_code") or "").strip()
            number = table.get("table_number")
            if not code:
                continue
            prefix = str(number).strip() if number not in (None, False, "") else ""
            label = f"{prefix} - {code}" if prefix and prefix != "0" else code
            table["display_name"] = label
            table["table_name"] = label
        return tables
