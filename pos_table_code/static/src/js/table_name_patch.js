/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";

const TABLE_CONTAINER_SELECTORS = [
    ".table",
    ".floor-table",
    ".restaurant-table",
    "[data-table-id]",
    "[data-id]",
];

function buildTableLabel(table) {
    if (!table || typeof table !== "object") {
        return "";
    }
    const code = (table.table_code || "").trim();
    const rawNumber = table._raw_table_number ?? table.table_number;
    const numberText = rawNumber != null ? String(rawNumber).trim() : "";
    if (!code) {
        return numberText;
    }
    if (!numberText || numberText === "0" || numberText === code || numberText.endsWith(` - ${code}`)) {
        return code;
    }
    return `${numberText} - ${code}`;
}

function applyLabel(table, labelMap) {
    const label = buildTableLabel(table);
    if (!label || !table || typeof table !== "object") {
        return;
    }
    const rawNumber = table._raw_table_number ?? table.table_number;
    const rawKey = rawNumber != null ? String(rawNumber).trim() : "";
    if (rawKey) {
        labelMap[rawKey] = label;
    }

    if (!Object.prototype.hasOwnProperty.call(table, "_raw_table_number")) {
        table._raw_table_number = table.table_number;
    }
    table.table_number = label;
    table.display_name = label;
    table.table_name = label;
}

function walkAndApply(root, labelMap) {
    const seen = new Set();
    const queue = [root];
    while (queue.length) {
        const node = queue.shift();
        if (!node || typeof node !== "object" || seen.has(node)) {
            continue;
        }
        seen.add(node);

        if ("id" in node && ("table_code" in node || "table_number" in node || "table_name" in node)) {
            applyLabel(node, labelMap);
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

function isSimpleNumericText(text) {
    return /^\d+$/.test(text.trim());
}

function relabelFloorDOM(labelMap) {
    const all = document.querySelectorAll("div, span");
    for (const el of all) {
        if (!el || el.children.length) {
            continue;
        }
        const current = (el.textContent || "").trim();
        if (!current || !isSimpleNumericText(current)) {
            continue;
        }
        if (el.classList.contains("badge") || el.closest(".badge")) {
            continue;
        }
        if (!el.closest(TABLE_CONTAINER_SELECTORS.join(","))) {
            continue;
        }
        const replacement = labelMap[current];
        if (replacement && replacement !== current) {
            el.textContent = replacement;
        }
    }
}

function ensureDOMObserver(store) {
    if (store.__tableCodeObserverReady) {
        relabelFloorDOM(store.__tableCodeLabelMap || {});
        return;
    }

    store.__tableCodeObserverReady = true;
    const callback = () => relabelFloorDOM(store.__tableCodeLabelMap || {});
    const observer = new MutationObserver(callback);
    observer.observe(document.body, { subtree: true, childList: true, characterData: true });
    store.__tableCodeObserver = observer;
    callback();
}

function patchOrderPrinting(store) {
    const order = store?.get_order?.();
    const proto = order?.constructor?.prototype;
    if (!proto || proto.__tableCodePatched) {
        return;
    }

    const original = proto.export_for_printing;
    if (typeof original === "function") {
        proto.export_for_printing = function (...args) {
            const result = original.apply(this, args);
            const label = buildTableLabel(this.getTable ? this.getTable() : null);
            if (label && result && typeof result === "object") {
                result.table = label;
                result.table_name = label;
                result.table_number = label;
            }
            return result;
        };
    }

    const kitchenOriginal = proto.export_for_kitchen_printing;
    if (typeof kitchenOriginal === "function") {
        proto.export_for_kitchen_printing = function (...args) {
            const result = kitchenOriginal.apply(this, args);
            const label = buildTableLabel(this.getTable ? this.getTable() : null);
            if (label && result && typeof result === "object") {
                result.table = label;
                result.table_name = label;
                result.table_number = label;
            }
            return result;
        };
    }

    proto.__tableCodePatched = true;
}

patch(PosStore.prototype, {
    async _processData(loadedData) {
        this.__tableCodeLabelMap = this.__tableCodeLabelMap || {};
        walkAndApply(loadedData, this.__tableCodeLabelMap);
        await super._processData(...arguments);
        walkAndApply(this, this.__tableCodeLabelMap);
        patchOrderPrinting(this);
        ensureDOMObserver(this);
    },
});
