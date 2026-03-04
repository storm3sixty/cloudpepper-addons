import { createElement, createRoot, useEffect, useMemo, useState } from '@wordpress/element';
import {
  Button,
  Card,
  CardBody,
  Notice,
  SelectControl,
  TabPanel,
  TextControl,
  TextareaControl,
  ToggleControl
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const el = createElement;
const cfg = window.SwiftPrintAdmin || {};

if (!cfg.nonce) {
  // eslint-disable-next-line no-console
  console.warn('[SwiftPrint] Missing REST nonce in localized config.');
} else {
  apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));
}

const DEFAULT_SCHEMA = {
  enabled: false,
  pricing_mode: 'LOOKUP',
  discount_enabled: false,
  discount_type: 'FIXED',
  discount_value: 0,
  weight_value: 0,
  weight_per: 'UNIT',
  same_day_timezone: cfg.timezone || 'UTC',
  quantity_settings: { display_type: 'TEXTBOX', breaks: [25, 50, 100], min_qty: 1, max_qty: 100000, step: 1 },
  print_modes: { sides: 'SINGLE', allow_full_colour: true, allow_bw: true, allow_mixed_front_back: false, keys: ['SIMPLE_S1'] },
  standard_sizes: [],
  turnarounds: [],
  pricing_rows: []
};

const toNum = (v, fallback = 0) => {
  const n = Number(v);
  return Number.isFinite(n) ? n : fallback;
};

function AdminApp() {
  const [products, setProducts] = useState([]);
  const [productId, setProductId] = useState(Number(cfg.initialProductId || 0));
  const [search, setSearch] = useState('');
  const [schema, setSchema] = useState(DEFAULT_SCHEMA);
  const [loadedSchema, setLoadedSchema] = useState(DEFAULT_SCHEMA);
  const [notice, setNotice] = useState(null);
  const [loading, setLoading] = useState(false);
  const [csvText, setCsvText] = useState('');

  const dirty = useMemo(() => JSON.stringify(schema) !== JSON.stringify(loadedSchema), [schema, loadedSchema]);

  useEffect(() => {
    const onBeforeUnload = (event) => {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', onBeforeUnload);
    return () => window.removeEventListener('beforeunload', onBeforeUnload);
  }, [dirty]);

  const fetchProducts = async (term = '') => {
    const response = await apiFetch({ path: `${cfg.restUrl}/products?search=${encodeURIComponent(term)}` });
    const items = response.items || [];
    setProducts(items);
    if (!productId && items[0]) {
      setProductId(items[0].id);
    }
  };

  const fetchSchema = async (pid) => {
    if (!pid) return;
    setLoading(true);
    try {
      const response = await apiFetch({ path: `${cfg.restUrl}/schema/${pid}` });
      const merged = { ...DEFAULT_SCHEMA, ...response };
      setSchema(merged);
      setLoadedSchema(merged);
      setNotice(null);
    } catch (e) {
      setNotice({ status: 'error', message: e.message || 'Failed to load schema.' });
    }
    setLoading(false);
  };

  useEffect(() => { fetchProducts(search); }, []);
  useEffect(() => { if (productId) fetchSchema(productId); }, [productId]);

  const save = async () => {
    if (!productId) {
      setNotice({ status: 'error', message: 'Select a product first.' });
      return;
    }
    try {
      const response = await apiFetch({ path: `${cfg.restUrl}/schema/${productId}`, method: 'POST', data: schema });
      const merged = { ...DEFAULT_SCHEMA, ...(response.schema || schema) };
      setSchema(merged);
      setLoadedSchema(merged);
      setNotice({ status: 'success', message: `Saved successfully (schema v${response.schema_version || '?'})` });
    } catch (e) {
      setNotice({ status: 'error', message: e.message || 'Save failed.' });
    }
  };

  const update = (path, value) => {
    setSchema((prev) => {
      const next = { ...prev };
      const keys = path.split('.');
      let cursor = next;
      keys.slice(0, -1).forEach((k) => {
        cursor[k] = cursor[k] || {};
        cursor = cursor[k];
      });
      cursor[keys[keys.length - 1]] = value;
      return next;
    });
  };

  const addSize = () => update('standard_sizes', [...(schema.standard_sizes || []), { size_id: `size_${Date.now()}`, name: '', width: 0, height: 0, units: 'mm', bleed: 0, safe_area: 0, weight_per_unit: 0 }]);
  const addTurnaround = () => update('turnarounds', [...(schema.turnarounds || []), { id: `turn_${Date.now()}`, name: '', production_days: 1, cost_type: 'FLAT', cost_value: 0, min_qty: '', max_qty: '', cutoff_time: '', same_day_only: false, enabled: true }]);
  const addPricingRow = () => update('pricing_rows', [...(schema.pricing_rows || []), { size_id: '', print_mode_key: 'SIMPLE_S1', quantity_break: 100, total_price: 0 }]);

  const exportCsv = () => {
    const header = 'size_id,print_mode_key,quantity_break,total_price';
    const rows = (schema.pricing_rows || []).map((r) => [r.size_id, r.print_mode_key, r.quantity_break, r.total_price].join(','));
    const csv = [header, ...rows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `swiftprint-pricing-${productId}.csv`;
    a.click();
  };

  const importCsv = () => {
    const lines = csvText.split('\n').map((s) => s.trim()).filter(Boolean);
    if (lines.length < 2) return;
    const rows = lines.slice(1).map((line) => {
      const [size_id, print_mode_key, quantity_break, total_price] = line.split(',');
      return { size_id, print_mode_key, quantity_break: toNum(quantity_break, 1), total_price: toNum(total_price, 0) };
    });
    update('pricing_rows', rows);
  };

  return el('div', { className: 'swiftprint-admin-app' },
    el('h1', {}, 'SwiftPrint Settings'),
    notice ? el(Notice, { status: notice.status, isDismissible: true, onRemove: () => setNotice(null) }, notice.message) : null,
    el('div', { style: { display: 'grid', gridTemplateColumns: '2fr 1fr auto', gap: '12px', alignItems: 'end' } },
      el(TextControl, { label: 'Search Products', value: search, onChange: setSearch }),
      el(SelectControl, {
        label: 'Product',
        value: String(productId || ''),
        options: [{ label: 'Select a product', value: '' }, ...(products || []).map((p) => ({ label: `${p.name} (#${p.id})`, value: String(p.id) }))],
        onChange: (v) => {
          if (dirty && !window.confirm('You have unsaved changes. Continue?')) return;
          setProductId(toNum(v, 0));
        }
      }),
      el(Button, { variant: 'secondary', onClick: () => fetchProducts(search) }, 'Search')
    ),
    loading ? el('p', {}, 'Loading schema...') : null,
    el(TabPanel, {
      className: 'swiftprint-tabs',
      tabs: [
        { name: 'general', title: 'General Info' },
        { name: 'quantities', title: 'Quantities' },
        { name: 'printmodes', title: 'Print Modes' },
        { name: 'sizes', title: 'Sizes' },
        { name: 'turnarounds', title: 'Turnarounds' },
        { name: 'pricing', title: 'Pricing Tables' }
      ]
    }, (tab) => {
      if (tab.name === 'general') return el(Card, {}, el(CardBody, {},
        el(ToggleControl, { label: 'Enable SwiftPrint', checked: !!schema.enabled, onChange: (v) => update('enabled', v) }),
        el(SelectControl, { label: 'Pricing Mode', value: schema.pricing_mode, options: ['LOOKUP', 'LPI', 'LUPI', 'UP'].map((m) => ({ label: m, value: m })), onChange: (v) => update('pricing_mode', v) }),
        el(ToggleControl, { label: 'Discount enabled', checked: !!schema.discount_enabled, onChange: (v) => update('discount_enabled', v) }),
        schema.discount_enabled ? el(SelectControl, { label: 'Discount type', value: schema.discount_type, options: [{ label: 'Fixed', value: 'FIXED' }, { label: 'Percent', value: 'PERCENT' }], onChange: (v) => update('discount_type', v) }) : null,
        schema.discount_enabled ? el(TextControl, { label: 'Discount value', type: 'number', value: String(schema.discount_value || 0), onChange: (v) => update('discount_value', toNum(v, 0)) }) : null,
        el(TextControl, { label: 'Weight value', type: 'number', value: String(schema.weight_value || 0), onChange: (v) => update('weight_value', toNum(v, 0)) }),
        el(SelectControl, { label: 'Weight per', value: schema.weight_per || 'UNIT', options: [{ label: 'UNIT', value: 'UNIT' }, { label: 'AREA', value: 'AREA' }], onChange: (v) => update('weight_per', v) }),
        el(TextControl, { label: 'Same-day cutoff timezone', value: schema.same_day_timezone || 'UTC', onChange: (v) => update('same_day_timezone', v) })
      ));

      if (tab.name === 'quantities') return el(Card, {}, el(CardBody, {},
        el(SelectControl, { label: 'Display Type', value: schema.quantity_settings?.display_type || 'TEXTBOX', options: [{ label: 'Textbox', value: 'TEXTBOX' }, { label: 'Dropdown', value: 'DROPDOWN' }], onChange: (v) => update('quantity_settings.display_type', v) }),
        (schema.quantity_settings?.display_type || 'TEXTBOX') === 'TEXTBOX'
          ? el('div', {},
            el(TextControl, { label: 'Min Qty', type: 'number', value: String(schema.quantity_settings?.min_qty || 1), onChange: (v) => update('quantity_settings.min_qty', toNum(v, 1)) }),
            el(TextControl, { label: 'Max Qty', type: 'number', value: String(schema.quantity_settings?.max_qty || 100000), onChange: (v) => update('quantity_settings.max_qty', toNum(v, 100000)) }),
            el(TextControl, { label: 'Step', type: 'number', value: String(schema.quantity_settings?.step || 1), onChange: (v) => update('quantity_settings.step', toNum(v, 1)) })
          )
          : el('div', {},
            el(TextControl, {
              label: 'Quantity breaks (comma separated)',
              value: (schema.quantity_settings?.breaks || []).join(','),
              onChange: (v) => update('quantity_settings.breaks', v.split(',').map((n) => toNum(n.trim(), 0)).filter(Boolean))
            })
          )
      ));

      if (tab.name === 'printmodes') return el(Card, {}, el(CardBody, {},
        el(SelectControl, { label: 'Sides', value: schema.print_modes?.sides || 'SINGLE', options: [{ label: 'Single', value: 'SINGLE' }, { label: 'Double', value: 'DOUBLE' }], onChange: (v) => update('print_modes.sides', v) }),
        el(ToggleControl, { label: 'Allow Full Colour', checked: !!schema.print_modes?.allow_full_colour, onChange: (v) => update('print_modes.allow_full_colour', v) }),
        el(ToggleControl, { label: 'Allow B/W', checked: !!schema.print_modes?.allow_bw, onChange: (v) => update('print_modes.allow_bw', v) }),
        el(ToggleControl, { label: 'Allow mixed front/back', checked: !!schema.print_modes?.allow_mixed_front_back, onChange: (v) => update('print_modes.allow_mixed_front_back', v) }),
        el(TextControl, {
          label: 'Print mode keys (comma separated)',
          value: (schema.print_modes?.keys || []).join(','),
          onChange: (v) => update('print_modes.keys', v.split(',').map((k) => k.trim()).filter(Boolean))
        })
      ));

      if (tab.name === 'sizes') return el(Card, {}, el(CardBody, {},
        el(Button, { variant: 'secondary', onClick: addSize }, 'Add Size'),
        (schema.standard_sizes || []).map((s, i) => el('div', { key: s.size_id || i, style: { borderTop: '1px solid #ddd', marginTop: 10, paddingTop: 10 } },
          el(TextControl, { label: 'Size ID', value: s.size_id || '', onChange: (v) => { const next = [...schema.standard_sizes]; next[i].size_id = v; update('standard_sizes', next); } }),
          el(TextControl, { label: 'Name', value: s.name || '', onChange: (v) => { const next = [...schema.standard_sizes]; next[i].name = v; update('standard_sizes', next); } }),
          el(TextControl, { label: 'Width', type: 'number', value: String(s.width || 0), onChange: (v) => { const next = [...schema.standard_sizes]; next[i].width = toNum(v, 0); update('standard_sizes', next); } }),
          el(TextControl, { label: 'Height', type: 'number', value: String(s.height || 0), onChange: (v) => { const next = [...schema.standard_sizes]; next[i].height = toNum(v, 0); update('standard_sizes', next); } }),
          el(TextControl, { label: 'Units', value: s.units || 'mm', onChange: (v) => { const next = [...schema.standard_sizes]; next[i].units = v; update('standard_sizes', next); } }),
          el(Button, { isDestructive: true, onClick: () => { const next = [...schema.standard_sizes]; next.splice(i, 1); update('standard_sizes', next); } }, 'Remove')
        ))
      ));

      if (tab.name === 'turnarounds') return el(Card, {}, el(CardBody, {},
        el(Button, { variant: 'secondary', onClick: addTurnaround }, 'Add Turnaround'),
        (schema.turnarounds || []).map((t, i) => {
          const now = new Date();
          const hhmm = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
          const eligible = !t.cutoff_time || hhmm <= t.cutoff_time;
          return el('div', { key: t.id || i, style: { borderTop: '1px solid #ddd', marginTop: 10, paddingTop: 10 } },
            el(TextControl, { label: 'ID', value: t.id || '', onChange: (v) => { const next = [...schema.turnarounds]; next[i].id = v; update('turnarounds', next); } }),
            el(TextControl, { label: 'Name', value: t.name || '', onChange: (v) => { const next = [...schema.turnarounds]; next[i].name = v; update('turnarounds', next); } }),
            el(TextControl, { label: 'Production days', type: 'number', value: String(t.production_days || 1), onChange: (v) => { const next = [...schema.turnarounds]; next[i].production_days = toNum(v, 1); update('turnarounds', next); } }),
            el(SelectControl, { label: 'Cost type', value: t.cost_type || 'FLAT', options: [{ label: 'Flat', value: 'FLAT' }, { label: '% Base', value: 'PERCENT_BASE' }, { label: '% Subtotal', value: 'PERCENT_SUBTOTAL' }], onChange: (v) => { const next = [...schema.turnarounds]; next[i].cost_type = v; update('turnarounds', next); } }),
            el(TextControl, { label: 'Cost value', type: 'number', value: String(t.cost_value || 0), onChange: (v) => { const next = [...schema.turnarounds]; next[i].cost_value = toNum(v, 0); update('turnarounds', next); } }),
            el(TextControl, { label: 'Min qty', value: String(t.min_qty || ''), onChange: (v) => { const next = [...schema.turnarounds]; next[i].min_qty = v; update('turnarounds', next); } }),
            el(TextControl, { label: 'Max qty', value: String(t.max_qty || ''), onChange: (v) => { const next = [...schema.turnarounds]; next[i].max_qty = v; update('turnarounds', next); } }),
            el(TextControl, { label: 'Cutoff time (HH:MM)', value: t.cutoff_time || '', onChange: (v) => { const next = [...schema.turnarounds]; next[i].cutoff_time = v; update('turnarounds', next); } }),
            el(ToggleControl, { label: 'Only show before cutoff (same day)', checked: !!t.same_day_only, onChange: (v) => { const next = [...schema.turnarounds]; next[i].same_day_only = v; update('turnarounds', next); } }),
            el('p', {}, `Countdown eligibility preview: ${eligible ? 'Eligible now' : 'After cutoff'}`),
            el(Button, { isDestructive: true, onClick: () => { const next = [...schema.turnarounds]; next.splice(i, 1); update('turnarounds', next); } }, 'Remove')
          );
        })
      ));

      return el(Card, {}, el(CardBody, {},
        el(Button, { variant: 'secondary', onClick: addPricingRow }, 'Add Pricing Row'),
        el(Button, { variant: 'secondary', onClick: exportCsv, style: { marginLeft: 8 } }, 'Export CSV'),
        (schema.pricing_rows || []).map((r, i) => el('div', { key: `${r.size_id}-${r.print_mode_key}-${i}`, style: { display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr auto', gap: '8px', marginTop: '8px' } },
          el(TextControl, { label: 'Size ID', value: r.size_id || '', onChange: (v) => { const next = [...schema.pricing_rows]; next[i].size_id = v; update('pricing_rows', next); } }),
          el(TextControl, { label: 'Print Mode Key', value: r.print_mode_key || '', onChange: (v) => { const next = [...schema.pricing_rows]; next[i].print_mode_key = v; update('pricing_rows', next); } }),
          el(TextControl, { label: 'Qty Break', type: 'number', value: String(r.quantity_break || 0), onChange: (v) => { const next = [...schema.pricing_rows]; next[i].quantity_break = toNum(v, 1); update('pricing_rows', next); } }),
          el(TextControl, { label: 'Price', type: 'number', value: String(r.total_price || 0), onChange: (v) => { const next = [...schema.pricing_rows]; next[i].total_price = toNum(v, 0); update('pricing_rows', next); } }),
          el(Button, { isDestructive: true, onClick: () => { const next = [...schema.pricing_rows]; next.splice(i, 1); update('pricing_rows', next); } }, 'Remove')
        )),
        el(TextareaControl, { label: 'CSV Import', value: csvText, onChange: setCsvText, help: 'Header: size_id,print_mode_key,quantity_break,total_price' }),
        el(Button, { variant: 'secondary', onClick: importCsv }, 'Import CSV')
      ));
    }),
    el(Button, { variant: 'primary', onClick: save, disabled: !dirty }, dirty ? 'Save Changes' : 'Saved')
  );
}

const rootEl = document.getElementById('swiftprint-admin-root');
if (!rootEl) {
  // eslint-disable-next-line no-console
  console.warn('[SwiftPrint] Missing #swiftprint-admin-root mount element.');
} else {
  createRoot(rootEl).render(el(AdminApp));
}
