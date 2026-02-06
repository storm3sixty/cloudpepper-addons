odoo.define('pos_table_code.table_name', function (require) {
    "use strict";

    const Registries = require('point_of_sale.Registries');
    const { PosComponent } = require('point_of_sale.PosComponent');

    const TableWidget = require('pos_restaurant.TableWidget');

    // Extend the TableWidget to show table_code
    const PosTableWidget = TableWidget =>
        class extends TableWidget {
            get tableName() {
                return this.props.table.table_code || this.props.table.table_number;
            }
        };

    Registries.Component.extend(TableWidget, PosTableWidget);
});
