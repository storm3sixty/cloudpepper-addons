(function (wp) {
  var el = wp.element.createElement;
  var useMemo = wp.element.useMemo;
  var useState = wp.element.useState;

  function preindex(rows) {
    var idx = {};
    (rows || []).forEach(function (r) {
      var key = r.size_id + '::' + r.print_mode_key;
      idx[key] = idx[key] || [];
      idx[key].push({ q: Number(r.quantity_break), p: Number(r.total_price) });
    });
    Object.keys(idx).forEach(function (k) {
      idx[k].sort(function (a, b) { return a.q - b.q; });
    });
    return idx;
  }

  function Configurator(props) {
    var schema = props.schema || {};
    var productId = props.productId;
    var restUrl = props.restUrl;

    var _useState = useState(100), quantity = _useState[0], setQuantity = _useState[1];
    var _useState2 = useState(schema.standard_sizes && schema.standard_sizes[0] ? schema.standard_sizes[0].size_id : ''), sizeId = _useState2[0], setSizeId = _useState2[1];
    var _useState3 = useState(schema.print_modes && schema.print_modes[0] ? schema.print_modes[0].key : 'SIMPLE_S1'), printModeKey = _useState3[0], setPrintModeKey = _useState3[1];
    var _useState4 = useState(schema.turnarounds && schema.turnarounds[0] ? schema.turnarounds[0].id : ''), turnaroundId = _useState4[0], setTurnaroundId = _useState4[1];
    var _useState5 = useState(null), quote = _useState5[0], setQuote = _useState5[1];

    var pricingIndex = useMemo(function () {
      return preindex(schema.pricing_rows || []);
    }, [schema]);

    var estimate = useMemo(function () {
      var rows = pricingIndex[sizeId + '::' + printModeKey] || [];
      if (!rows.length) return 0;
      var lower = rows[0];
      rows.forEach(function (r) { if (r.q <= quantity) lower = r; });
      return lower.p;
    }, [pricingIndex, sizeId, printModeKey, quantity]);

    function lockPrice() {
      var selections = { quantity: quantity, size_id: sizeId, print_mode_key: printModeKey, turnaround_id: turnaroundId, selected_option_items: [] };
      wp.apiFetch({ path: restUrl + '/quote', method: 'POST', data: { product_id: productId, base_price_set_id: Number(schema.id), schema_version: Number(schema.schema_version), selections: selections } }).then(function (response) {
        setQuote(response);
        var token = document.getElementById('swiftprint_quote_token');
        var selection = document.getElementById('swiftprint_selection');
        if (token) token.value = response.quote_token;
        if (selection) selection.value = JSON.stringify(selections);
      });
    }

    return el('div', { className: 'swiftprint-configurator' },
      el('h3', {}, 'SwiftPrint Configurator'),
      el('label', {}, 'Quantity'),
      el('input', { type: 'number', value: quantity, min: 1, onChange: function (e) { setQuantity(Number(e.target.value) || 1); } }),
      el('label', {}, 'Size'),
      el('select', { value: sizeId, onChange: function (e) { setSizeId(e.target.value); } }, (schema.standard_sizes || []).map(function (s) { return el('option', { key: s.size_id, value: s.size_id }, s.name); })),
      el('label', {}, 'Print mode'),
      el('select', { value: printModeKey, onChange: function (e) { setPrintModeKey(e.target.value); } }, (schema.print_modes || []).map(function (m) { return el('option', { key: m.key, value: m.key }, m.label || m.key); })),
      el('label', {}, 'Turnaround'),
      el('select', { value: turnaroundId, onChange: function (e) { setTurnaroundId(e.target.value); } }, (schema.turnarounds || []).map(function (t) { return el('option', { key: t.id, value: t.id }, t.name); })),
      el('p', {}, 'Instant estimate: ' + Number(estimate).toFixed(2)),
      el('button', { type: 'button', onClick: lockPrice }, 'Lock price'),
      quote ? el('p', {}, 'Total: ' + quote.total + ' ' + quote.currency) : null
    );
  }

  var root = document.getElementById('swiftprint-configurator-root');
  if (root && window.swiftprintFrontend && window.swiftprintFrontend.schema) {
    wp.element.render(el(Configurator, {
      schema: window.swiftprintFrontend.schema,
      productId: window.swiftprintFrontend.productId,
      restUrl: window.swiftprintFrontend.restUrl
    }), root);
  }
}(window.wp));
