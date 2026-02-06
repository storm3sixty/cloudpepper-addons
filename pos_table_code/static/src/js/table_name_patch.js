/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";

const getTableLabel = (table) => table?.table_code || table?.name || "";

function applyLabelToTable(table) {
    const label = getTableLabel(table);
    if (!label || typeof table !== "object" || !table) {
        return;
    }
    table.name = label;
    table.display_name = label;
    table.table_number = label;
}

function walkAndRelabelTables(root) {
    const seen = new Set();
    const queue = [root];

    while (queue.length) {
        const node = queue.shift();
        if (!node || typeof node !== "object" || seen.has(node)) {
            continue;
        }
        seen.add(node);

        // Heuristic: table-like records used in POS contain table_number and id.
        if (("table_number" in node || "table_code" in node) && "id" in node) {
            applyLabelToTable(node);
        }

        if (Array.isArray(node)) {
            for (const item of node) {
                queue.push(item);
            }
        } else {
            for (const value of Object.values(node)) {
                if (value && typeof value === "object") {
                    queue.push(value);
                }
            }
        }
    }
}

patch(PosStore.prototype, {
    async _processData(loadedData) {
        walkAndRelabelTables(loadedData);
        await super._processData(...arguments);
        walkAndRelabelTables(this);
    },
});
