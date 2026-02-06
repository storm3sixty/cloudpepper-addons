odoo.define('pos_table_code.table_labels', function(require){
    "use strict";

    var screens = require('point_of_sale.screens');

    screens.FloorplanWidget.include({
        render_element: function(){
            this._super();
            var self = this;
            this.pos.tables.forEach(function(table){
                var $el = self.$('.table[data-id="'+table.id+'"]');
                if($el.length){
                    // Use x_table_code if set, otherwise table_number
                    var label = table.x_table_code || table.table_number;
                    $el.find('.label').text(label);
                }
            });
        },
    });
});
