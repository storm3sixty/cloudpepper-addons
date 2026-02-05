import { PosStore } from "@point_of_sale/app/store/pos_store";
import { patch } from "@web/core/utils/patch";

patch(PosStore.prototype, {
    async _processData(loadedData) {
        await super._processData(...arguments);

        if (loadedData['restaurant.table']) {
            loadedData['restaurant.table'].forEach(table => {
                table.display_name =
                    table.table_code || table.table_number.toString();
            });
        }
    },
});
