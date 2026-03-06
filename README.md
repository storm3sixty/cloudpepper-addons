# Tradeprint Configurator for WooCommerce

Production-ready scaffold for a WooCommerce extension that hosts a Tradeprint-style product configurator using saved product meta (no live API yet).

## Version 1 scope

✅ Included:
- WooCommerce dependency guard + admin notice.
- Plugin admin menu with **Dashboard**, **Settings**, and **Logs** placeholder.
- Secure settings model for sandbox mode, bearer token, commission, delivery extension, order mode, and debug logging.
- WooCommerce product data tab (**Tradeprint**) with:
  - Product-level controls (enabled, product key, commission override, order mode override, pricing display mode).
  - Structured repeatable attribute builder.
  - Nested option builder with media selector support.
	  - Pricing matrix builder with spreadsheet-style grid editor (settings, services, quantities, inline cells).
	  - Extra services builder (preflight + design).
	  - Preview image mapping builder for attribute option-based product previews.
	  - Conditional logic builder for attribute/option visibility rules.
- Frontend renderer for enabled products that reads saved product meta only.
- Sticky summary with client-side live updates for options/services/matrix/custom quantity via REST pricing requests.
- REST namespace with config payload plus meta-driven price resolver endpoint (`/price`) and placeholder preflight endpoint.
- WooCommerce cart/order integration for Tradeprint configurator payload capture and mock manual/auto submission workflows.

❌ Not included yet:
- Live Tradeprint API requests.
- Live pricing calculation.
- Checkout/order placement API workflow.

## File structure

```text
tradeprint-configurator-for-woocommerce.php
includes/
  class-loader.php
  class-pricing-service.php
  class-mock-submission-service.php
  class-order-integration.php
  admin/
    class-admin.php
    class-product-data.php
  frontend/
    class-frontend.php
  api/
    class-rest-controller.php
assets/
  css/
    admin.css
    frontend.css
  js/
    admin.js
    frontend.js
README.md
```

## Security notes

- Input is sanitized before saving.
- Output is escaped in admin/frontend templates.
- Product saves require nonce validation and capability checks.
- Bearer token is stored in options and never exposed through frontend scripts.

## Next implementation steps

1. Add service classes for Tradeprint auth/catalog/pricing requests.
2. Replace mock pricing and matrix placeholders with real endpoint-driven logic.
3. Add stricter REST permission and request validation.
4. Add logging and diagnostics UI in the Logs screen.
5. Add test coverage and CI checks (PHPCS/PHPStan/WooCommerce smoke tests).
