/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";

const getTableLabel = (table) => table?.table_code || table?.name || "";

function relabelTableRecords(records) {
    if (!Array.isArray(records)) {
        return;
    }
    for (const table of records) {
        if (!table || typeof table !== "object") {
            continue;
        }
        const label = getTableLabel(table);
        if (!label) {
            continue;
        }
        table.name = label;
        table.display_name = label;
        table.table_number = label;
    }
}

patch(PosStore.prototype, {
    async _processData(loadedData) {
        // Keep patch intentionally minimal to avoid blocking POS boot.
        relabelTableRecords(loadedData?.["restaurant.table"]);
        if (super._processData) {
            await super._processData(...arguments);
        }

        // Ensure cloned/normalized in-memory records are also relabeled.
        relabelTableRecords(this.models?.["restaurant.table"]);
    },
});
