(function (wp) {
  var el = wp.element.createElement;
  var createRoot = wp.element.createRoot;
  var useState = wp.element.useState;
  var Button = wp.components.Button;
  var Card = wp.components.Card;
  var CardBody = wp.components.CardBody;
  var Notice = wp.components.Notice;
  var SelectControl = wp.components.SelectControl;
  var TabPanel = wp.components.TabPanel;

  var cfg = window.SwiftPrintAdmin || {};

  if (!cfg.nonce) {
    console.warn('[SwiftPrint] Missing REST nonce in localized config.');
  } else if (wp.apiFetch && wp.apiFetch.createNonceMiddleware) {
    wp.apiFetch.use(wp.apiFetch.createNonceMiddleware(cfg.nonce));
  }

  function PlaceholderTab(props) {
    return el(Card, { style: { marginTop: '16px' } }, el(CardBody, {}, el('h2', {}, props.title), props.children));
  }

  function AdminApp() {
    var products = cfg.products || [];
    var _useState = useState(products[0] ? products[0].id : 0);
    var productId = _useState[0];
    var setProductId = _useState[1];
    var _useState2 = useState('');
    var message = _useState2[0];
    var setMessage = _useState2[1];

    function savePlaceholder() {
      if (!productId) {
        setMessage('Select a product first.');
        return;
      }

      var payload = {
        name: 'Default',
        pricing_mode: 'LOOKUP',
        quantity_settings: { display_type: 'TEXTBOX', min_qty: 1, max_qty: 100000, step: 1 },
        print_modes: [{ key: 'SIMPLE_S1', label: 'Single sided' }],
        standard_sizes: [{ size_id: 'A5', name: 'A5', width: 148, height: 210 }],
        turnarounds: [{ id: 'standard', name: 'Standard', cost_type: 'FLAT', cost_value: 0 }],
        option_groups: [],
        pricing_rows: [{ size_id: 'A5', print_mode_key: 'SIMPLE_S1', quantity_break: 100, total_price: 20 }]
      };

      wp.apiFetch({ path: cfg.restUrl + '/schema/' + productId, method: 'POST', data: payload }).then(function () {
        setMessage('SwiftPrint schema placeholder saved.');
      });
    }

    return el('div', { className: 'swiftprint-admin-app' },
      el('h1', {}, 'SwiftPrint Settings'),
      message ? el(Notice, { status: 'success', isDismissible: true, onRemove: function () { setMessage(''); } }, message) : null,
      el(SelectControl, {
        label: 'Product',
        value: String(productId),
        options: products.length ? products.map(function (p) { return { label: p.name + ' (#' + p.id + ')', value: String(p.id) }; }) : [{ label: 'No products found', value: '0' }],
        onChange: function (v) { setProductId(Number(v)); }
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
      }, function (tab) {
        return el(PlaceholderTab, { title: tab.title }, el('p', {}, 'React admin UI is mounted and ready for full editor controls.'));
      }),
      el(Button, { variant: 'primary', onClick: savePlaceholder }, 'Save Placeholder Schema')
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
