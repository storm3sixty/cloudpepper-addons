/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";

function parseJsonMap(raw) {
    if (!raw) {
        return {};
    }
    try {
        const value = JSON.parse(raw);
        return value && typeof value === "object" ? value : {};
    } catch {
        return {};
    }
}

function getRawTableNumber(table) {
    const raw = table?._raw_table_number ?? table?.table_number;
    return raw != null ? String(raw).trim() : "";
}

function buildTableLabel(table, aliasMap = {}) {
    if (!table || typeof table !== "object") {
        return "";
    }
    const numberText = getRawTableNumber(table);
    const alias = (aliasMap[numberText] || "").trim();
    const code = alias || (table.table_code || "").trim();
    if (!code) {
        return numberText;
    }
    if (!numberText || numberText === "0" || numberText === code) {
        return code;
    }
    return `${numberText} - ${code}`;
}

function applyTableLabel(table, aliasMap = {}) {
    const label = buildTableLabel(table, aliasMap);
    if (!label || !table || typeof table !== "object") {
        return;
    }
    if (!Object.prototype.hasOwnProperty.call(table, "_raw_table_number")) {
        table._raw_table_number = table.table_number;
    }
    table.table_number = label;
    table.display_name = label;
    table.table_name = label;
}

function relabelTablesList(tables, aliasMap) {
    if (!Array.isArray(tables)) {
        return;
    }
    for (const table of tables) {
        applyTableLabel(table, aliasMap);
    }
}

function patchOrderPrinting(store) {
    const order = store?.get_order?.();
    const proto = order?.constructor?.prototype;
    if (!proto || proto.__tableCodePatched) {
        return;
    }

    const salesNote = store.config?.ui_sales_receipt_note || "";
    const kitchenNote = store.config?.ui_kitchen_receipt_note || "";

    const original = proto.export_for_printing;
    if (typeof original === "function") {
        proto.export_for_printing = function (...args) {
            const result = original.apply(this, args);
            const label = buildTableLabel(this.getTable ? this.getTable() : null, store.__tableAliasMap || {});
            if (label && result && typeof result === "object") {
                result.table = label;
                result.table_name = label;
                result.table_number = label;
            }
            if (salesNote && result && typeof result === "object") {
                result.footer = [result.footer || "", salesNote].filter(Boolean).join("\n");
            }
            return result;
        };
    }

    const kitchenOriginal = proto.export_for_kitchen_printing;
    if (typeof kitchenOriginal === "function") {
        proto.export_for_kitchen_printing = function (...args) {
            const result = kitchenOriginal.apply(this, args);
            const label = buildTableLabel(this.getTable ? this.getTable() : null, store.__tableAliasMap || {});
            if (label && result && typeof result === "object") {
                result.table = label;
                result.table_name = label;
                result.table_number = label;
            }
            if (kitchenNote && result && typeof result === "object") {
                result.note = [result.note || "", kitchenNote].filter(Boolean).join("\n");
            }
            return result;
        };
    }

    proto.__tableCodePatched = true;
}

patch(PosStore.prototype, {
    async _processData(loadedData) {
        this.__tableAliasMap = parseJsonMap(this.config?.ui_table_alias_json);

        relabelTablesList(loadedData?.["restaurant.table"], this.__tableAliasMap);
        relabelTablesList(loadedData?.["pos.restaurant.table"], this.__tableAliasMap);

        await super._processData(...arguments);

        this.__tableAliasMap = parseJsonMap(this.config?.ui_table_alias_json);
        relabelTablesList(this.models?.["restaurant.table"], this.__tableAliasMap);
        relabelTablesList(this.models?.["pos.restaurant.table"], this.__tableAliasMap);

        patchOrderPrinting(this);
    },
});
