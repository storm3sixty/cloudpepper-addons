# CloudPepper Addons Cleanup

This repository now only keeps cleanup assets for removing residual `pos_table_code` traces.

## Fix for Owl error after app removal
If you see errors like:
- `"pos.config"."ui_enable_drag_and_drop" field is undefined`

it means database views/assets still reference fields from the removed addon.

Run:

```bash
psql <YOUR_DB_NAME> -f scripts/cleanup_pos_table_code.sql
```

Then:
1. Restart Odoo service.
2. Hard refresh browser (`Ctrl+Shift+R`).
3. Open POS again.

Optionally run Odoo update cache command:

```bash
odoo -d <YOUR_DB_NAME> -u base --stop-after-init
```
