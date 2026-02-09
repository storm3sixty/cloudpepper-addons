# CloudPepper Addons Cleanup

If Odoo goes white-screen **as soon as you add this addons path**, the DB still has stale metadata/views/assets from old custom modules.

Example error:
- `"pos.config"."ui_enable_drag_and_drop" field is undefined`

## Recommended recovery order

### 1) Keep problematic custom addons path removed temporarily
Start Odoo in stable mode first (without that path) so DB cleanup can be applied.

### 2) Diagnose leftovers
```bash
psql <YOUR_DB_NAME> -f scripts/diagnose_pos_white_screen.sql
```

### 3) Run normal cleanup
```bash
psql <YOUR_DB_NAME> -f scripts/cleanup_pos_table_code.sql
```

### 4) If still white-screen, run emergency recovery
```bash
psql <YOUR_DB_NAME> -f scripts/emergency_pos_whitescreen_recovery.sql
```

### 5) Rebuild and reload (required order)
1. Restart Odoo service.
2. Run:
   ```bash
   odoo -d <YOUR_DB_NAME> -u base --stop-after-init
   ```
3. Restart Odoo service again.
4. Hard refresh browser (`Ctrl+Shift+R`).
5. Re-open POS/webclient.

## Why this happens
Odoo stores inherited views, custom user view overrides, field metadata, module state, and compiled web assets in DB. Removing Python files alone is not enough if stale DB records still reference removed fields.
