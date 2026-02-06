from odoo import models, fields

class RestaurantTable(models.Model):
    _inherit = 'restaurant.table'

    name = fields.Char(string='Table Name', required=True)
    number = fields.Integer(string='Table Number')

    def name_get(self):
        result = []
        for table in self:
            # Use custom name if available, otherwise fallback to number
            display_name = table.name if table.name else str(table.number)
            result.append((table.id, display_name))
        return result



