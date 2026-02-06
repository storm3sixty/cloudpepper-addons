odoo.define('pos_table_code.load_table_code', function(require){
    "use strict";

    const models = require('point_of_sale.models');

    models.load_fields('restaurant.table', ['table_code']);
});
