odoo.define('pos_table_code.load_table_code', function(require){
    "use strict";

    const PosModel = require('point_of_sale.models');

    // Extend table model to show name instead of number
    PosModel.PosModel = PosModel.PosModel.extend({
        initialize: function (session, attributes) {
            this._super(session, attributes);
            this.tables.forEach((table) => {
                table.display_name = table.name || table.number.toString();
            });
        },
    });

});




