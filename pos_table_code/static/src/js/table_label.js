odoo.define('pos_table_code.table_label', function(require){
    "use strict";

    const screens = require('point_of_sale.screens');

    // Example: render table labels using table.display_name
    screens.TableWidget.include({
        renderElement: function(){
            this._super();
            this.$el.find('.table-name').text(this.pos_table.display_name);
        },
    });

});




