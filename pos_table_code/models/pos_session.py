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

    def _loader_params_pos_config(self):
        params = super()._loader_params_pos_config()
        fields = params.setdefault("search_params", {}).setdefault("fields", [])
        for field_name in (
            "ui_button_labels_json",
            "ui_enable_drag_and_drop",
            "ui_sales_receipt_note",
            "ui_kitchen_receipt_note",
        ):
            if field_name not in fields:
                fields.append(field_name)
        return params
