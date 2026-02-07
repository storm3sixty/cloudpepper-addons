/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";
import { Order } from "@point_of_sale/app/store/models";

function buildTableLabel(table) {
    const code = (table?.table_code || "").trim();
    const number = table?.table_number != null ? String(table.table_number) : "";
    return code ? (number ? `${number} - ${code}` : code) : number;
}

patch(PosStore.prototype, {
    async _processData(loadedData) {
        const tables = loadedData?.["restaurant.table"] || [];
        for (const table of tables) {
            const label = buildTableLabel(table);
            if (!label) {
                continue;
            }
            table.table_label = label;
            table.display_name = label;
            table.table_name = label;
            table.table_number = label;
        }
        await super._processData(...arguments);
    },
});

patch(Order.prototype, {
    export_for_printing() {
        const result = super.export_for_printing(...arguments);
        const table = this.getTable ? this.getTable() : null;
        const label = buildTableLabel(table);
        if (label) {
            result.table = label;
            result.table_name = label;
            result.table_number = label;
        }
        return result;
    },
});
