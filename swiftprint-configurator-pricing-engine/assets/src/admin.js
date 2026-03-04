import { createElement, createRoot, useState } from '@wordpress/element';
import { Button, Card, CardBody, Notice, SelectControl, TabPanel } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const el = createElement;

const cfg = window.SwiftPrintAdmin || {};

if (!cfg.nonce) {
  // eslint-disable-next-line no-console
  console.warn('[SwiftPrint] Missing REST nonce in localized config.');
} else {
  apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));
}

function PlaceholderTab({ title, children }) {
  return el(Card, { style: { marginTop: '16px' } }, el(CardBody, {}, el('h2', {}, title), children));
}

function AdminApp() {
  const products = cfg.products || [];
  const [productId, setProductId] = useState(products[0]?.id || 0);
  const [message, setMessage] = useState('');

  const savePlaceholder = async () => {
    if (!productId) {
      setMessage('Select a product first.');
      return;
    }

    const payload = {
      name: 'Default',
      pricing_mode: 'LOOKUP',
      quantity_settings: { display_type: 'TEXTBOX', min_qty: 1, max_qty: 100000, step: 1 },
      print_modes: [{ key: 'SIMPLE_S1', label: 'Single sided' }],
      standard_sizes: [{ size_id: 'A5', name: 'A5', width: 148, height: 210 }],
      turnarounds: [{ id: 'standard', name: 'Standard', cost_type: 'FLAT', cost_value: 0 }],
      option_groups: [],
      pricing_rows: [{ size_id: 'A5', print_mode_key: 'SIMPLE_S1', quantity_break: 100, total_price: 20 }]
    };

    await apiFetch({ path: `${cfg.restUrl}/schema/${productId}`, method: 'POST', data: payload });
    setMessage('SwiftPrint schema placeholder saved.');
  };

  return el('div', { className: 'swiftprint-admin-app' },
    el('h1', {}, 'SwiftPrint Settings'),
    message ? el(Notice, { status: 'success', isDismissible: true, onRemove: () => setMessage('') }, message) : null,
    el(SelectControl, {
      label: 'Product',
      value: String(productId),
      options: products.length
        ? products.map((p) => ({ label: `${p.name} (#${p.id})`, value: String(p.id) }))
        : [{ label: 'No products found', value: '0' }],
      onChange: (v) => setProductId(Number(v))
    }),
    el(TabPanel, {
      className: 'swiftprint-tabs',
      tabs: [
        { name: 'general', title: 'General Info' },
        { name: 'quantities', title: 'Quantities' },
        { name: 'printmodes', title: 'Print Modes' },
        { name: 'sizes', title: 'Sizes' },
        { name: 'turnarounds', title: 'Turnarounds' }
      ]
    }, (tab) => el(PlaceholderTab, { title: tab.title }, el('p', {}, 'React admin UI is mounted and ready for full editor controls.'))),
    el(Button, { variant: 'primary', onClick: savePlaceholder }, 'Save Placeholder Schema')
  );
}

const rootEl = document.getElementById('swiftprint-admin-root');
if (!rootEl) {
  // eslint-disable-next-line no-console
  console.warn('[SwiftPrint] Missing #swiftprint-admin-root mount element.');
} else {
  createRoot(rootEl).render(el(AdminApp));
}
