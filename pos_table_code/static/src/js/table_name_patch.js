/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";

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
