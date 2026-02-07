from odoo import api, fields, models


class RestaurantTable(models.Model):
    _inherit = "restaurant.table"

    table_code = fields.Char(
        string="Table Name",
        help="Custom table name shown in POS floor and receipts.",
    )

    @api.model_create_multi
    def create(self, vals_list):
        for vals in vals_list:
            code = vals.get("table_code")
            if code:
                vals["name"] = code
        return super().create(vals_list)

    def write(self, vals):
        code = vals.get("table_code")
        if code:
            vals = dict(vals, name=code)
        return super().write(vals)

    def _load_pos_data_fields(self, config_id):
        try:
            fields_list = super()._load_pos_data_fields(config_id)
        except AttributeError:
            fields_list = ["name"]
        if "table_code" not in fields_list:
            fields_list.append("table_code")
        if "name" not in fields_list:
            fields_list.append("name")
        return fields_list
