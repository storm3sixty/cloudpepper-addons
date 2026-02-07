/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";

function buildTableLabel(table) {
    if (!table || typeof table !== "object") {
        return "";
    }
    const code = (table.table_code || "").trim();
    const number = table._raw_table_number ?? table.table_number;
    const numberText = number != null ? String(number).trim() : "";
    if (!code) {
        return numberText;
    }
    if (!numberText || numberText === code || numberText.endsWith(` - ${code}`)) {
        return code;
    }
    return `${numberText} - ${code}`;
}

function applyTableLabel(table) {
    const label = buildTableLabel(table);
    if (!label) {
        return;
    }
    if (!Object.prototype.hasOwnProperty.call(table, "_raw_table_number")) {
        table._raw_table_number = table.table_number;
    }
    table.display_name = label;
    table.table_name = label;
    table.table_number = label;
}

function applyTableLabels(tableRecords) {
    if (!Array.isArray(tableRecords)) {
        return;
    }
    for (const table of tableRecords) {
        applyTableLabel(table);
    }
}

patch(PosStore.prototype, {
    async _processData(loadedData) {
        applyTableLabels(loadedData?.["restaurant.table"]);
        await super._processData(...arguments);
        applyTableLabels(this.models?.["restaurant.table"]);
        applyTableLabels(this.data?.["restaurant.table"]);
    },
});
