import { createElement, useState } from '@wordpress/element';
import { Button, SelectControl, TabPanel, TextareaControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const el = createElement;

function AdminApp() {
  const [productId, setProductId] = useState(window.swiftprintAdmin.products?.[0]?.id || 0);
  const [json, setJson] = useState('{\n  "name": "Default",\n  "pricing_mode": "LOOKUP",\n  "quantity_settings": {"display_type":"TEXTBOX","min_qty":1,"max_qty":100000,"step":1},\n  "print_modes": [{"key":"SIMPLE_S1","label":"Single sided"}],\n  "standard_sizes": [{"size_id":"A5","name":"A5","width":148,"height":210}],\n  "turnarounds": [{"id":"standard","name":"Standard","cost_type":"FLAT","cost_value":0}],\n  "option_groups": [],\n  "pricing_rows": [{"size_id":"A5","print_mode_key":"SIMPLE_S1","quantity_break":100,"total_price":20}]\n}');

  const save = async () => {
    await apiFetch({ path: `${window.swiftprintAdmin.restUrl}/schema/${productId}`, method: 'POST', data: JSON.parse(json) });
    alert('Saved');
  };

  return el('div', {},
    el(SelectControl, {
      label: 'Product',
      value: String(productId),
      options: (window.swiftprintAdmin.products || []).map((p) => ({ label: `${p.name} (#${p.id})`, value: String(p.id) })),
      onChange: (v) => setProductId(Number(v))
    }),
    el(TabPanel, {
      className: 'swiftprint-tabs',
      tabs: [
        { name: 'general', title: 'General Info' },
        { name: 'quantities', title: 'Quantities' },
        { name: 'printmodes', title: 'Print Modes' },
        { name: 'sizes', title: 'Sizes' },
        { name: 'custom', title: 'Custom Sizes' },
        { name: 'turnarounds', title: 'Turnarounds' },
        { name: 'options', title: 'Order Options' },
        { name: 'triggers', title: 'Smart Triggers' },
        { name: 'pricing', title: 'Pricing Tables' }
      ]
    }, () => el(TextareaControl, { label: 'Schema JSON (MVP editor)', value: json, onChange: setJson, rows: 22 })),
    el(Button, { variant: 'primary', onClick: save }, 'Save schema')
  );
}

const root = document.getElementById('swiftprint-admin-root');
if (root) {
  window.wp.element.render(el(AdminApp), root);
}
