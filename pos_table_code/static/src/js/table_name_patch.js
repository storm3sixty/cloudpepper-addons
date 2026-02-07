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
        if (("table_number" in node || "table_code" in node) && "id" in node) {
            applyLabelToTable(node);
        }
        if (Array.isArray(node)) {
            queue.push(...node);
        } else {
            for (const value of Object.values(node)) {
                if (value && typeof value === "object") {
                    queue.push(value);
                }
            }
        }
    }
}

function relabelInStore(store, loadedData) {
    walkAndRelabelTables(loadedData);
    walkAndRelabelTables(store);
}

patch(PosStore.prototype, {
    async _processData(loadedData) {
        relabelInStore(this, loadedData);
        await super._processData(...arguments);
        relabelInStore(this, loadedData);
    },

    async _processPosData(loadedData) {
        relabelInStore(this, loadedData);
        await super._processPosData(...arguments);
        relabelInStore(this, loadedData);
    },

    async processServerData(...args) {
        const loadedData = args[0] || {};
        relabelInStore(this, loadedData);
        const result = await super.processServerData(...args);
        relabelInStore(this, loadedData);
        return result;
    },
});
