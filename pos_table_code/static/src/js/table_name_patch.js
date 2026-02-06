/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";
import { Order } from "@point_of_sale/app/store/models";

const getTableLabel = (table) => table?.table_code || table?.name || "";

patch(PosStore.prototype, {
    async _processData(loadedData) {
        const tableRecords = loadedData["restaurant.table"] || [];
        for (const table of tableRecords) {
            const label = getTableLabel(table);
            if (label) {
                table.name = label;
            }
        }
        await super._processData(...arguments);
    },
});

patch(Order.prototype, {
    export_for_printing() {
        const result = super.export_for_printing(...arguments);
        const table = this.getTable ? this.getTable() : null;
        const label = getTableLabel(table);
        if (label) {
            result.table = label;
            result.table_name = label;
        }
        return result;
    },
});
