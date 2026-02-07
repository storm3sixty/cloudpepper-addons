/** @odoo-module **/

import { PosStore } from "@point_of_sale/app/store/pos_store";

const getLabel = (table) => table?.table_code || table?.table_number || table?.display_name || "";

function relabelNode(node, seen = new Set()) {
    if (!node || typeof node !== "object" || seen.has(node)) {
        return;
    }
    seen.add(node);

    if (("table_number" in node || "table_code" in node) && "id" in node) {
        const label = getLabel(node);
        if (label) {
            node.table_number = label;
            node.display_name = label;
            node.table_name = label;
        }
    }

    if (Array.isArray(node)) {
        for (const item of node) {
            relabelNode(item, seen);
        }
    } else {
        for (const value of Object.values(node)) {
            if (value && typeof value === "object") {
                relabelNode(value, seen);
            }
        }
    }
}

function wrapStoreMethod(methodName) {
    const proto = PosStore.prototype;
    const original = proto[methodName];
    if (typeof original !== "function" || original.__tableCodeWrapped) {
        return;
    }

    const wrapped = async function (...args) {
        try {
            relabelNode(args[0]);
        } catch {
            // never block POS startup
        }
        const result = await original.apply(this, args);
        try {
            relabelNode(this);
            relabelNode(this.models);
            relabelNode(this.data);
            relabelNode(this.floors);
        } catch {
            // never block POS startup
        }
        return result;
    };
    wrapped.__tableCodeWrapped = true;
    proto[methodName] = wrapped;
}

for (const methodName of ["_processData", "_processPosData", "processServerData"]) {
    wrapStoreMethod(methodName);
}
