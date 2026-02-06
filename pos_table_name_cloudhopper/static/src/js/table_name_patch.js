/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";
import { Order } from "@point_of_sale/app/store/models";

function getTableLabel(table) {
    return table?.custom_table_name || table?.display_table_name || table?.name || "";
}

patch(PosStore.prototype, {
    async _processData(loadedData) {
        const tableRecords = loadedData["restaurant.table"] || [];
        for (const table of tableRecords) {
            const display = table.custom_table_name || table.name || "";
            table.display_table_name = display;
            if (display) {
                table.name = display;
            }
        }
        await super._processData(...arguments);
    },
});

patch(Order.prototype, {
    export_for_printing() {
        const result = super.export_for_printing(...arguments);
        const table = this.getTable ? this.getTable() : null;
        const tableLabel = getTableLabel(table);
        if (tableLabel) {
            result.table_name = tableLabel;
            result.table = tableLabel;
        }
        return result;
    },
});
