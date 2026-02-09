# CloudPepper Addons Cleanup

If you removed `pos_table_code` from code but still get a POS white screen / Owl error, your DB likely still has stale view/field/asset records.

Example error:
- `"pos.config"."ui_enable_drag_and_drop" field is undefined`

## 1) Diagnose leftovers

```bash
psql <YOUR_DB_NAME> -f scripts/diagnose_pos_white_screen.sql
```

If any rows are returned in view/custom view/field queries, continue with cleanup.

## 2) Cleanup leftovers

```bash
psql <YOUR_DB_NAME> -f scripts/cleanup_pos_table_code.sql
```

## 3) Rebuild and reload (required order)

1. Restart Odoo service.
2. Run base update once:

   ```bash
   odoo -d <YOUR_DB_NAME> -u base --stop-after-init
   ```

3. Restart Odoo service again.
4. Hard refresh browser (`Ctrl+Shift+R`).
5. Re-open POS in a new tab/window.

## Why this happens even after removing addon code

Odoo persists inherited views, custom user view overrides, field metadata, and compiled web assets in the database. Any stale reference to removed fields can still crash Owl rendering.
