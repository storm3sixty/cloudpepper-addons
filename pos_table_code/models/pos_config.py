from odoo import fields, models


class PosConfig(models.Model):
    _inherit = "pos.config"

    ui_button_labels_json = fields.Text(
        string="POS Button Labels (JSON)",
        default='{"Plan": "Plan", "Table": "Table"}',
        help='JSON map to rename POS buttons. Example: {"Plan": "Zones", "Table": "Tables"}',
    )
    ui_enable_drag_and_drop = fields.Boolean(
        string="Enable POS Header Drag & Drop",
        default=False,
        help="Allow drag-and-drop reordering for top header buttons in POS UI.",
    )
    ui_sales_receipt_note = fields.Text(
        string="Sales Receipt Extra Text",
        help="Extra text appended to sales receipt from POS.",
    )
    ui_kitchen_receipt_note = fields.Text(
        string="Kitchen Receipt Extra Text",
        help="Extra text appended to kitchen receipt from POS.",
    )
