odoo.define('pos_table_code.floor_plan', function(require){
    "use strict";

    var models = require('point_of_sale.models');
    var screens = require('point_of_sale.screens');

    var _super = models.PosModel.prototype;
    models.PosModel = models.PosModel.extend({
        initialize: function(session, attributes){
            _super.initialize.call(this, session, attributes);
            // override tables to use table_code
            this.tables = this.tables.map(function(table){
                table.name = table.table_code || table.table_number;
                return table;
            });
        },
    });
});
