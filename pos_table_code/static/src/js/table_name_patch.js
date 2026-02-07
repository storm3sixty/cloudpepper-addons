/** @odoo-module **/

import { PosStore } from "@point_of_sale/app/store/pos_store";

function applyTableAlias(node, seen = new Set()) {
    if (!node || typeof node !== "object" || seen.has(node)) {
        return;
    }
    seen.add(node);

    if ("id" in node && ("table_code" in node || "table_number" in node)) {
        const alias = node.table_code || node.table_name || node.display_name;
        if (alias) {
            node.display_name = alias;
            node.table_name = alias;

            // Force UI paths that render table_number to show alias text.
            if (!node.__table_alias_applied) {
                const rawNumber = node.table_number;
                Object.defineProperty(node, "_table_number_raw", {
                    value: rawNumber,
                    writable: true,
                    configurable: true,
                    enumerable: false,
                });
                Object.defineProperty(node, "table_number", {
                    get() {
                        return this.table_code || this.table_name || this.display_name || this._table_number_raw;
                    },
                    set(v) {
                        this._table_number_raw = v;
                    },
                    configurable: true,
                    enumerable: true,
                });
                Object.defineProperty(node, "__table_alias_applied", {
                    value: true,
                    writable: true,
                    configurable: true,
                    enumerable: false,
                });
            }
        }
    }

    if (Array.isArray(node)) {
        for (const item of node) {
            applyTableAlias(item, seen);
        }
    } else {
        for (const value of Object.values(node)) {
            if (value && typeof value === "object") {
                applyTableAlias(value, seen);
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
            applyTableAlias(args[0]);
        } catch {
            // never block POS startup
        }

        const result = await original.apply(this, args);

        try {
            applyTableAlias(this);
            applyTableAlias(this.models);
            applyTableAlias(this.data);
            applyTableAlias(this.floors);
            applyTableAlias(this.tables);
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
