/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";

const getTableLabel = (table) => table?.table_code || table?.name || "";

function applyLabelToTable(table) {
    const label = getTableLabel(table);
    if (!label) {
        return;
    }
    // Different POS builds read different keys for floor-table captions.
    table.name = label;
    table.display_name = label;
    table.table_number = label;
}

patch(PosStore.prototype, {
    async _processData(loadedData) {
        const tableRecords = loadedData["restaurant.table"] || [];
        for (const table of tableRecords) {
            applyLabelToTable(table);
        }

        await super._processData(...arguments);

        // Also patch the in-memory records built by super for compatibility
        // with variants that clone/normalize payload after loading.
        const loadedTables = this.models?.["restaurant.table"] || [];
        for (const table of loadedTables) {
            applyLabelToTable(table);
        }
    },
});
