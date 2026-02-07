/** @odoo-module **/

import { patch } from "@web/core/utils/patch";
import { PosStore } from "@point_of_sale/app/store/pos_store";

const TABLE_CONTAINER_SELECTORS = [".table", ".floor-table", ".restaurant-table", "[data-table-id]", "[data-id]"];

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

function getAliasForTable(table, aliasMap) {
    const raw = table?._raw_table_number ?? table?.table_number;
    const key = raw != null ? String(raw).trim() : "";
    if (!key) {
        return "";
    }
    return (aliasMap?.[key] || "").trim();
}

function buildTableLabel(table, aliasMap = {}) {
    if (!table || typeof table !== "object") {
        return "";
    }
    const alias = getAliasForTable(table, aliasMap);
    const code = alias || (table.table_code || "").trim();
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

function applyTableLabel(table, labelMap, aliasMap = {}) {
    const label = buildTableLabel(table, aliasMap);
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

function walkTables(root, labelMap, aliasMap = {}) {
    const seen = new Set();
    const queue = [root];
    while (queue.length) {
        const node = queue.shift();
        if (!node || typeof node !== "object" || seen.has(node)) {
            continue;
        }
        seen.add(node);
        if ("id" in node && ("table_code" in node || "table_number" in node || "table_name" in node)) {
            applyTableLabel(node, labelMap, aliasMap);
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

function relabelFloorDOM(labelMap) {
    const all = document.querySelectorAll("div, span");
    for (const el of all) {
        if (!el || el.children.length) {
            continue;
        }
        const current = (el.textContent || "").trim();
        if (!current || !/^\d+$/.test(current)) {
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

function applyButtonTextOverrides(labelMap) {
    if (!labelMap || typeof labelMap !== "object") {
        return;
    }
    const buttons = document.querySelectorAll("button, .btn, .nav-link");
    for (const button of buttons) {
        if (!button || button.children.length > 2) {
            continue;
        }
        const text = (button.textContent || "").trim();
        const replacement = labelMap[text];
        if (replacement && replacement !== text) {
            button.textContent = replacement;
        }
    }
}

function enableHeaderDragAndDrop() {
    const headers = document.querySelectorAll(".top-content .btn, header .btn, .pos-topheader .btn");
    if (!headers.length) {
        return;
    }
    for (const btn of headers) {
        btn.setAttribute("draggable", "true");
        btn.addEventListener("dragstart", (ev) => {
            ev.dataTransfer?.setData("text/plain", btn.textContent || "");
            btn.classList.add("o_dragging");
        });
        btn.addEventListener("dragend", () => btn.classList.remove("o_dragging"));
        btn.addEventListener("dragover", (ev) => ev.preventDefault());
        btn.addEventListener("drop", (ev) => {
            ev.preventDefault();
            const sourceText = ev.dataTransfer?.getData("text/plain");
            if (!sourceText) {
                return;
            }
            const sourceBtn = [...headers].find((h) => (h.textContent || "") === sourceText);
            if (sourceBtn && sourceBtn !== btn && btn.parentNode) {
                btn.parentNode.insertBefore(sourceBtn, btn);
            }
        });
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


function debugTablePayload(loadedData) {
    if (window.__posTableCodeDebugDone) {
        return;
    }
    window.__posTableCodeDebugDone = true;
    const tables = loadedData?.["restaurant.table"] || loadedData?.["pos.restaurant.table"] || [];
    if (tables.length) {
        console.info("[pos_table_code] first loaded table record", tables[0]);
    } else {
        console.warn("[pos_table_code] no restaurant table records found in loaded payload");
    }
}
function ensureObserver(store) {
    if (store.__tableCodeObserverReady) {
        relabelFloorDOM(store.__tableCodeLabelMap || {});
        applyButtonTextOverrides(store.__uiButtonMap || {});
        if (store.config?.ui_enable_drag_and_drop) {
            enableHeaderDragAndDrop();
        }
        return;
    }

    store.__tableCodeObserverReady = true;
    const callback = () => {
        relabelFloorDOM(store.__tableCodeLabelMap || {});
        applyButtonTextOverrides(store.__uiButtonMap || {});
        if (store.config?.ui_enable_drag_and_drop) {
            enableHeaderDragAndDrop();
        }
    };
    const observer = new MutationObserver(callback);
    observer.observe(document.body, { subtree: true, childList: true, characterData: true });
    store.__tableCodeObserver = observer;
    callback();
}

patch(PosStore.prototype, {
    async _processData(loadedData) {
        this.__tableCodeLabelMap = this.__tableCodeLabelMap || {};
        debugTablePayload(loadedData);
        this.__uiButtonMap = parseJsonMap(this.config?.ui_button_labels_json);
        this.__tableAliasMap = parseJsonMap(this.config?.ui_table_alias_json);
        walkTables(loadedData, this.__tableCodeLabelMap, this.__tableAliasMap);
        await super._processData(...arguments);
        this.__uiButtonMap = parseJsonMap(this.config?.ui_button_labels_json);
        this.__tableAliasMap = parseJsonMap(this.config?.ui_table_alias_json);
        walkTables(this, this.__tableCodeLabelMap, this.__tableAliasMap);
        patchOrderPrinting(this);
        ensureObserver(this);
    },
});
