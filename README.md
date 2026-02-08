# CloudPepper Addons Cleanup

If you removed `pos_table_code` from code but still get a POS white screen / Owl error, your **database still has stale views/fields/assets**.

Example error:
- `"pos.config"."ui_enable_drag_and_drop" field is undefined`

## Apply cleanup

```bash
psql <YOUR_DB_NAME> -f scripts/cleanup_pos_table_code.sql
```

Then do all of the following in order:
1. Restart Odoo service.
2. Hard refresh browser (`Ctrl+Shift+R`).
3. Re-open POS in a new tab/window.

## If still broken
Run a base update once to rebuild registry/view cache:

```bash
odoo -d <YOUR_DB_NAME> -u base --stop-after-init
```

Then restart Odoo again and hard refresh.

## Why this happens even when addon code is removed
Odoo stores inherited views, custom fields metadata, and compiled asset attachments in DB. If these records are left behind, frontend/backend may still reference deleted fields and crash.
