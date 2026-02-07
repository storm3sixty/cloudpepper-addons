/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";
import { Order } from "@point_of_sale/app/store/models";

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
    if (!("_raw_table_number" in table)) {
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

patch(Order.prototype, {
    export_for_printing() {
        const result = super.export_for_printing(...arguments);
        const label = buildTableLabel(this.getTable ? this.getTable() : null);
        if (label) {
            result.table = label;
            result.table_name = label;
            result.table_number = label;
        }
        return result;
    },
});

if (Order.prototype.export_for_kitchen_printing) {
    patch(Order.prototype, {
        export_for_kitchen_printing() {
            const result = super.export_for_kitchen_printing(...arguments);
            const label = buildTableLabel(this.getTable ? this.getTable() : null);
            if (label && result && typeof result === "object") {
                result.table = label;
                result.table_name = label;
                result.table_number = label;
            }
            return result;
        },
    });
}
