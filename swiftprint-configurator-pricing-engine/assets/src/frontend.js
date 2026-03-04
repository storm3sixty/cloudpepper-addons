import { createElement, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const el = createElement;

function modesFromSchema(schema) {
  if (Array.isArray(schema?.print_modes)) return schema.print_modes;
  const keys = schema?.print_modes?.keys || ['SIMPLE_S1'];
  return keys.map((k) => ({ key: k, label: k }));
}

function preindex(rows = []) {
  const idx = {};
  rows.forEach((r) => {
    const key = `${r.size_id}::${r.print_mode_key}`;
    idx[key] = idx[key] || [];
    idx[key].push({ q: Number(r.quantity_break), p: Number(r.total_price) });
  });
  Object.values(idx).forEach((arr) => arr.sort((a, b) => a.q - b.q));
  return idx;
}

function Configurator({ schema, productId, restUrl }) {
  const modes = modesFromSchema(schema);
  const [quantity, setQuantity] = useState(100);
  const [sizeId, setSizeId] = useState(schema?.standard_sizes?.[0]?.size_id || '');
  const [printModeKey, setPrintModeKey] = useState(modes?.[0]?.key || 'SIMPLE_S1');
  const [turnaroundId, setTurnaroundId] = useState(schema?.turnarounds?.[0]?.id || '');
  const [quote, setQuote] = useState(null);
  const pricingIndex = useMemo(() => preindex(schema?.pricing_rows || []), [schema]);

  const estimate = useMemo(() => {
    const key = `${sizeId}::${printModeKey}`;
    const rows = pricingIndex[key] || [];
    if (!rows.length) return 0;
    let lower = rows[0];
    rows.forEach((r) => {
      if (r.q <= quantity) lower = r;
    });
    return lower.p;
  }, [quantity, sizeId, printModeKey, pricingIndex]);

  const requestQuote = async () => {
    const selections = { quantity, size_id: sizeId, print_mode_key: printModeKey, turnaround_id: turnaroundId, selected_option_items: [] };
    const response = await apiFetch({ path: `${restUrl}/quote`, method: 'POST', data: { product_id: productId, base_price_set_id: Number(schema.product_id || schema.id || productId), schema_version: Number(schema.schema_version || 1), selections } });
    setQuote(response);
    const token = document.getElementById('swiftprint_quote_token');
    const selection = document.getElementById('swiftprint_selection');
    if (token) token.value = response.quote_token;
    if (selection) selection.value = JSON.stringify(selections);
  };

  return el('div', { className: 'swiftprint-configurator' },
    el('h3', {}, 'SwiftPrint Configurator'),
    el('label', {}, 'Quantity'),
    el('input', { type: 'number', min: schema?.quantity_settings?.min_qty || 1, max: schema?.quantity_settings?.max_qty || 100000, step: schema?.quantity_settings?.step || 1, value: quantity, onChange: (e) => setQuantity(Number(e.target.value)) }),
    el('label', {}, 'Size'),
    el('select', { value: sizeId, onChange: (e) => setSizeId(e.target.value) }, (schema?.standard_sizes || []).map((s) => el('option', { key: s.size_id, value: s.size_id }, s.name))),
    el('label', {}, 'Print mode'),
    el('select', { value: printModeKey, onChange: (e) => setPrintModeKey(e.target.value) }, modes.map((m) => el('option', { key: m.key, value: m.key }, m.label || m.key))),
    el('label', {}, 'Turnaround'),
    el('select', { value: turnaroundId, onChange: (e) => setTurnaroundId(e.target.value) }, (schema?.turnarounds || []).map((t) => el('option', { key: t.id, value: t.id }, t.name))),
    el('p', {}, `Instant estimate: ${estimate.toFixed(2)}`),
    el('button', { type: 'button', onClick: requestQuote }, 'Lock price'),
    quote ? el('div', { className: 'swiftprint-breakdown' },
      el('strong', {}, `Total: ${quote.total} ${quote.currency}`)
    ) : null
  );
}

const root = document.getElementById('swiftprint-configurator-root');
if (root && window.swiftprintFrontend?.schema) {
  window.wp.element.render(el(Configurator, {
    schema: window.swiftprintFrontend.schema,
    productId: window.swiftprintFrontend.productId,
    restUrl: window.swiftprintFrontend.restUrl
  }), root);
}
