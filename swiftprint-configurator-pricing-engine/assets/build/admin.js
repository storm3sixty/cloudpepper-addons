(function (wp) {
  var el = wp.element.createElement;
  var createRoot = wp.element.createRoot;
  var useEffect = wp.element.useEffect;
  var useMemo = wp.element.useMemo;
  var useState = wp.element.useState;
  var C = wp.components;
  var apiFetch = wp.apiFetch;
  var cfg = window.SwiftPrintAdmin || {};

  if (!cfg.nonce) {
    console.warn('[SwiftPrint] Missing REST nonce in localized config.');
  } else if (apiFetch && apiFetch.createNonceMiddleware) {
    apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));
  }

  var DEFAULT_SCHEMA = {
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

  function toNum(v, fallback) {
    var n = Number(v);
    return Number.isFinite(n) ? n : fallback;
  }

  function AdminApp() {
    var _useState = useState([]), products = _useState[0], setProducts = _useState[1];
    var _useState2 = useState(Number(cfg.initialProductId || 0)), productId = _useState2[0], setProductId = _useState2[1];
    var _useState3 = useState(''), search = _useState3[0], setSearch = _useState3[1];
    var _useState4 = useState(DEFAULT_SCHEMA), schema = _useState4[0], setSchema = _useState4[1];
    var _useState5 = useState(DEFAULT_SCHEMA), loadedSchema = _useState5[0], setLoadedSchema = _useState5[1];
    var _useState6 = useState(null), notice = _useState6[0], setNotice = _useState6[1];
    var _useState7 = useState(false), loading = _useState7[0], setLoading = _useState7[1];
    var _useState8 = useState(''), csvText = _useState8[0], setCsvText = _useState8[1];

    var dirty = useMemo(function () { return JSON.stringify(schema) !== JSON.stringify(loadedSchema); }, [schema, loadedSchema]);

    useEffect(function () {
      function onBeforeUnload(event) {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
      }
      window.addEventListener('beforeunload', onBeforeUnload);
      return function () { window.removeEventListener('beforeunload', onBeforeUnload); };
    }, [dirty]);

    function fetchProducts(term) {
      return apiFetch({ path: cfg.restUrl + '/products?search=' + encodeURIComponent(term || '') }).then(function (response) {
        var items = response.items || [];
        setProducts(items);
        if (!productId && items[0]) setProductId(items[0].id);
      });
    }

    function fetchSchema(pid) {
      if (!pid) return;
      setLoading(true);
      apiFetch({ path: cfg.restUrl + '/schema/' + pid }).then(function (response) {
        var merged = Object.assign({}, DEFAULT_SCHEMA, response || {});
        setSchema(merged);
        setLoadedSchema(merged);
        setNotice(null);
      }).catch(function (e) {
        setNotice({ status: 'error', message: e.message || 'Failed to load schema.' });
      }).finally(function () { setLoading(false); });
    }

    useEffect(function () { fetchProducts(search); }, []);
    useEffect(function () { if (productId) fetchSchema(productId); }, [productId]);

    function save() {
      if (!productId) {
        setNotice({ status: 'error', message: 'Select a product first.' });
        return;
      }
      apiFetch({ path: cfg.restUrl + '/schema/' + productId, method: 'POST', data: schema }).then(function (response) {
        var merged = Object.assign({}, DEFAULT_SCHEMA, (response.schema || schema));
        setSchema(merged);
        setLoadedSchema(merged);
        setNotice({ status: 'success', message: 'Saved successfully (schema v' + (response.schema_version || '?') + ')' });
      }).catch(function (e) {
        setNotice({ status: 'error', message: e.message || 'Save failed.' });
      });
    }

    function update(path, value) {
      setSchema(function (prev) {
        var next = Object.assign({}, prev);
        var keys = path.split('.');
        var cursor = next;
        keys.slice(0, -1).forEach(function (k) {
          cursor[k] = cursor[k] || {};
          cursor = cursor[k];
        });
        cursor[keys[keys.length - 1]] = value;
        return next;
      });
    }

    function addSize() {
      update('standard_sizes', (schema.standard_sizes || []).concat([{ size_id: 'size_' + Date.now(), name: '', width: 0, height: 0, units: 'mm', bleed: 0, safe_area: 0, weight_per_unit: 0 }]));
    }

    function addTurnaround() {
      update('turnarounds', (schema.turnarounds || []).concat([{ id: 'turn_' + Date.now(), name: '', production_days: 1, cost_type: 'FLAT', cost_value: 0, min_qty: '', max_qty: '', cutoff_time: '', same_day_only: false, enabled: true }]));
    }

    function addPricingRow() {
      update('pricing_rows', (schema.pricing_rows || []).concat([{ size_id: '', print_mode_key: 'SIMPLE_S1', quantity_break: 100, total_price: 0 }]));
    }

    function exportCsv() {
      var header = 'size_id,print_mode_key,quantity_break,total_price';
      var rows = (schema.pricing_rows || []).map(function (r) { return [r.size_id, r.print_mode_key, r.quantity_break, r.total_price].join(','); });
      var csv = [header].concat(rows).join('\n');
      var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'swiftprint-pricing-' + productId + '.csv';
      a.click();
    }

    function importCsv() {
      var lines = csvText.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
      if (lines.length < 2) return;
      var rows = lines.slice(1).map(function (line) {
        var parts = line.split(',');
        return { size_id: parts[0], print_mode_key: parts[1], quantity_break: toNum(parts[2], 1), total_price: toNum(parts[3], 0) };
      });
      update('pricing_rows', rows);
    }

    function tabContent(tab) {
      if (tab.name === 'general') {
        return el(C.Card, {}, el(C.CardBody, {},
          el(C.ToggleControl, { label: 'Enable SwiftPrint', checked: !!schema.enabled, onChange: function (v) { update('enabled', v); } }),
          el(C.SelectControl, { label: 'Pricing Mode', value: schema.pricing_mode, options: ['LOOKUP', 'LPI', 'LUPI', 'UP'].map(function (m) { return { label: m, value: m }; }), onChange: function (v) { update('pricing_mode', v); } }),
          el(C.ToggleControl, { label: 'Discount enabled', checked: !!schema.discount_enabled, onChange: function (v) { update('discount_enabled', v); } }),
          schema.discount_enabled ? el(C.SelectControl, { label: 'Discount type', value: schema.discount_type, options: [{ label: 'Fixed', value: 'FIXED' }, { label: 'Percent', value: 'PERCENT' }], onChange: function (v) { update('discount_type', v); } }) : null,
          schema.discount_enabled ? el(C.TextControl, { label: 'Discount value', type: 'number', value: String(schema.discount_value || 0), onChange: function (v) { update('discount_value', toNum(v, 0)); } }) : null,
          el(C.TextControl, { label: 'Weight value', type: 'number', value: String(schema.weight_value || 0), onChange: function (v) { update('weight_value', toNum(v, 0)); } }),
          el(C.SelectControl, { label: 'Weight per', value: schema.weight_per || 'UNIT', options: [{ label: 'UNIT', value: 'UNIT' }, { label: 'AREA', value: 'AREA' }], onChange: function (v) { update('weight_per', v); } }),
          el(C.TextControl, { label: 'Same-day cutoff timezone', value: schema.same_day_timezone || 'UTC', onChange: function (v) { update('same_day_timezone', v); } })
        ));
      }

      if (tab.name === 'quantities') {
        return el(C.Card, {}, el(C.CardBody, {},
          el(C.SelectControl, { label: 'Display Type', value: (schema.quantity_settings || {}).display_type || 'TEXTBOX', options: [{ label: 'Textbox', value: 'TEXTBOX' }, { label: 'Dropdown', value: 'DROPDOWN' }], onChange: function (v) { update('quantity_settings.display_type', v); } }),
          ((schema.quantity_settings || {}).display_type || 'TEXTBOX') === 'TEXTBOX'
            ? el('div', {},
              el(C.TextControl, { label: 'Min Qty', type: 'number', value: String((schema.quantity_settings || {}).min_qty || 1), onChange: function (v) { update('quantity_settings.min_qty', toNum(v, 1)); } }),
              el(C.TextControl, { label: 'Max Qty', type: 'number', value: String((schema.quantity_settings || {}).max_qty || 100000), onChange: function (v) { update('quantity_settings.max_qty', toNum(v, 100000)); } }),
              el(C.TextControl, { label: 'Step', type: 'number', value: String((schema.quantity_settings || {}).step || 1), onChange: function (v) { update('quantity_settings.step', toNum(v, 1)); } })
            )
            : el(C.TextControl, {
              label: 'Quantity breaks (comma separated)',
              value: ((schema.quantity_settings || {}).breaks || []).join(','),
              onChange: function (v) { update('quantity_settings.breaks', v.split(',').map(function (n) { return toNum(n.trim(), 0); }).filter(Boolean)); }
            })
        ));
      }

      if (tab.name === 'printmodes') {
        return el(C.Card, {}, el(C.CardBody, {},
          el(C.SelectControl, { label: 'Sides', value: (schema.print_modes || {}).sides || 'SINGLE', options: [{ label: 'Single', value: 'SINGLE' }, { label: 'Double', value: 'DOUBLE' }], onChange: function (v) { update('print_modes.sides', v); } }),
          el(C.ToggleControl, { label: 'Allow Full Colour', checked: !!(schema.print_modes || {}).allow_full_colour, onChange: function (v) { update('print_modes.allow_full_colour', v); } }),
          el(C.ToggleControl, { label: 'Allow B/W', checked: !!(schema.print_modes || {}).allow_bw, onChange: function (v) { update('print_modes.allow_bw', v); } }),
          el(C.ToggleControl, { label: 'Allow mixed front/back', checked: !!(schema.print_modes || {}).allow_mixed_front_back, onChange: function (v) { update('print_modes.allow_mixed_front_back', v); } }),
          el(C.TextControl, {
            label: 'Print mode keys (comma separated)',
            value: ((schema.print_modes || {}).keys || []).join(','),
            onChange: function (v) { update('print_modes.keys', v.split(',').map(function (k) { return k.trim(); }).filter(Boolean)); }
          })
        ));
      }

      if (tab.name === 'sizes') {
        return el(C.Card, {}, el(C.CardBody, {},
          el(C.Button, { variant: 'secondary', onClick: addSize }, 'Add Size'),
          (schema.standard_sizes || []).map(function (s, i) {
            return el('div', { key: s.size_id || i, style: { borderTop: '1px solid #ddd', marginTop: 10, paddingTop: 10 } },
              el(C.TextControl, { label: 'Size ID', value: s.size_id || '', onChange: function (v) { var next = (schema.standard_sizes || []).slice(); next[i].size_id = v; update('standard_sizes', next); } }),
              el(C.TextControl, { label: 'Name', value: s.name || '', onChange: function (v) { var next = (schema.standard_sizes || []).slice(); next[i].name = v; update('standard_sizes', next); } }),
              el(C.TextControl, { label: 'Width', type: 'number', value: String(s.width || 0), onChange: function (v) { var next = (schema.standard_sizes || []).slice(); next[i].width = toNum(v, 0); update('standard_sizes', next); } }),
              el(C.TextControl, { label: 'Height', type: 'number', value: String(s.height || 0), onChange: function (v) { var next = (schema.standard_sizes || []).slice(); next[i].height = toNum(v, 0); update('standard_sizes', next); } }),
              el(C.TextControl, { label: 'Units', value: s.units || 'mm', onChange: function (v) { var next = (schema.standard_sizes || []).slice(); next[i].units = v; update('standard_sizes', next); } }),
              el(C.Button, { isDestructive: true, onClick: function () { var next = (schema.standard_sizes || []).slice(); next.splice(i, 1); update('standard_sizes', next); } }, 'Remove')
            );
          })
        ));
      }

      if (tab.name === 'turnarounds') {
        return el(C.Card, {}, el(C.CardBody, {},
          el(C.Button, { variant: 'secondary', onClick: addTurnaround }, 'Add Turnaround'),
          (schema.turnarounds || []).map(function (t, i) {
            var now = new Date();
            var hhmm = (String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0'));
            var eligible = !t.cutoff_time || hhmm <= t.cutoff_time;
            return el('div', { key: t.id || i, style: { borderTop: '1px solid #ddd', marginTop: 10, paddingTop: 10 } },
              el(C.TextControl, { label: 'ID', value: t.id || '', onChange: function (v) { var next = (schema.turnarounds || []).slice(); next[i].id = v; update('turnarounds', next); } }),
              el(C.TextControl, { label: 'Name', value: t.name || '', onChange: function (v) { var next = (schema.turnarounds || []).slice(); next[i].name = v; update('turnarounds', next); } }),
              el(C.TextControl, { label: 'Production days', type: 'number', value: String(t.production_days || 1), onChange: function (v) { var next = (schema.turnarounds || []).slice(); next[i].production_days = toNum(v, 1); update('turnarounds', next); } }),
              el(C.SelectControl, { label: 'Cost type', value: t.cost_type || 'FLAT', options: [{ label: 'Flat', value: 'FLAT' }, { label: '% Base', value: 'PERCENT_BASE' }, { label: '% Subtotal', value: 'PERCENT_SUBTOTAL' }], onChange: function (v) { var next = (schema.turnarounds || []).slice(); next[i].cost_type = v; update('turnarounds', next); } }),
              el(C.TextControl, { label: 'Cost value', type: 'number', value: String(t.cost_value || 0), onChange: function (v) { var next = (schema.turnarounds || []).slice(); next[i].cost_value = toNum(v, 0); update('turnarounds', next); } }),
              el(C.TextControl, { label: 'Min qty', value: String(t.min_qty || ''), onChange: function (v) { var next = (schema.turnarounds || []).slice(); next[i].min_qty = v; update('turnarounds', next); } }),
              el(C.TextControl, { label: 'Max qty', value: String(t.max_qty || ''), onChange: function (v) { var next = (schema.turnarounds || []).slice(); next[i].max_qty = v; update('turnarounds', next); } }),
              el(C.TextControl, { label: 'Cutoff time (HH:MM)', value: t.cutoff_time || '', onChange: function (v) { var next = (schema.turnarounds || []).slice(); next[i].cutoff_time = v; update('turnarounds', next); } }),
              el(C.ToggleControl, { label: 'Only show before cutoff (same day)', checked: !!t.same_day_only, onChange: function (v) { var next = (schema.turnarounds || []).slice(); next[i].same_day_only = v; update('turnarounds', next); } }),
              el('p', {}, 'Countdown eligibility preview: ' + (eligible ? 'Eligible now' : 'After cutoff')),
              el(C.Button, { isDestructive: true, onClick: function () { var next = (schema.turnarounds || []).slice(); next.splice(i, 1); update('turnarounds', next); } }, 'Remove')
            );
          })
        ));
      }

      return el(C.Card, {}, el(C.CardBody, {},
        el(C.Button, { variant: 'secondary', onClick: addPricingRow }, 'Add Pricing Row'),
        el(C.Button, { variant: 'secondary', onClick: exportCsv, style: { marginLeft: 8 } }, 'Export CSV'),
        (schema.pricing_rows || []).map(function (r, i) {
          return el('div', { key: (r.size_id + '-' + r.print_mode_key + '-' + i), style: { display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr auto', gap: '8px', marginTop: '8px' } },
            el(C.TextControl, { label: 'Size ID', value: r.size_id || '', onChange: function (v) { var next = (schema.pricing_rows || []).slice(); next[i].size_id = v; update('pricing_rows', next); } }),
            el(C.TextControl, { label: 'Print Mode Key', value: r.print_mode_key || '', onChange: function (v) { var next = (schema.pricing_rows || []).slice(); next[i].print_mode_key = v; update('pricing_rows', next); } }),
            el(C.TextControl, { label: 'Qty Break', type: 'number', value: String(r.quantity_break || 0), onChange: function (v) { var next = (schema.pricing_rows || []).slice(); next[i].quantity_break = toNum(v, 1); update('pricing_rows', next); } }),
            el(C.TextControl, { label: 'Price', type: 'number', value: String(r.total_price || 0), onChange: function (v) { var next = (schema.pricing_rows || []).slice(); next[i].total_price = toNum(v, 0); update('pricing_rows', next); } }),
            el(C.Button, { isDestructive: true, onClick: function () { var next = (schema.pricing_rows || []).slice(); next.splice(i, 1); update('pricing_rows', next); } }, 'Remove')
          );
        }),
        el(C.TextareaControl, { label: 'CSV Import', value: csvText, onChange: setCsvText, help: 'Header: size_id,print_mode_key,quantity_break,total_price' }),
        el(C.Button, { variant: 'secondary', onClick: importCsv }, 'Import CSV')
      ));
    }

    return el('div', { className: 'swiftprint-admin-app' },
      el('h1', {}, 'SwiftPrint Settings'),
      notice ? el(C.Notice, { status: notice.status, isDismissible: true, onRemove: function () { setNotice(null); } }, notice.message) : null,
      el('div', { style: { display: 'grid', gridTemplateColumns: '2fr 1fr auto', gap: '12px', alignItems: 'end' } },
        el(C.TextControl, { label: 'Search Products', value: search, onChange: setSearch }),
        el(C.SelectControl, {
          label: 'Product',
          value: String(productId || ''),
          options: [{ label: 'Select a product', value: '' }].concat((products || []).map(function (p) { return { label: p.name + ' (#' + p.id + ')', value: String(p.id) }; })),
          onChange: function (v) {
            if (dirty && !window.confirm('You have unsaved changes. Continue?')) return;
            setProductId(toNum(v, 0));
          }
        }),
        el(C.Button, { variant: 'secondary', onClick: function () { fetchProducts(search); } }, 'Search')
      ),
      loading ? el('p', {}, 'Loading schema...') : null,
      el(C.TabPanel, {
        className: 'swiftprint-tabs',
        tabs: [
          { name: 'general', title: 'General Info' },
          { name: 'quantities', title: 'Quantities' },
          { name: 'printmodes', title: 'Print Modes' },
          { name: 'sizes', title: 'Sizes' },
          { name: 'turnarounds', title: 'Turnarounds' },
          { name: 'pricing', title: 'Pricing Tables' }
        ]
      }, tabContent),
      el(C.Button, { variant: 'primary', onClick: save, disabled: !dirty }, dirty ? 'Save Changes' : 'Saved')
    );
  }

  var rootEl = document.getElementById('swiftprint-admin-root');
  if (!rootEl) {
    console.warn('[SwiftPrint] Missing #swiftprint-admin-root mount element.');
    return;
  }

  if (typeof createRoot === 'function') {
    createRoot(rootEl).render(el(AdminApp));
  } else {
    wp.element.render(el(AdminApp), rootEl);
  }
}(window.wp));
