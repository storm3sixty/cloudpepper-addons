import { Table } from "@pos_restaurant/app/floor_screen/table";
import { patch } from "@web/core/utils/patch";

patch(Table.prototype, {
    get label() {
        return this.props.table.table_code ||
               this.props.table.table_number.toString();
    },
});
