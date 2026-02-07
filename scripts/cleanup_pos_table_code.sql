-- Cleanup leftover DB artifacts from removed pos_table_code addon.
-- Use with psql: psql <db_name> -f scripts/cleanup_pos_table_code.sql

BEGIN;

-- 1) Remove broken inherited views that still reference removed fields.
DELETE FROM ir_ui_view
WHERE name IN (
    'pos.table.code.floor.form.extension',
    'pos.table.code.table.form.extension',
    'pos.table.code.pos.config.ui.designer.extension'
)
OR arch_db ILIKE '%ui_enable_drag_and_drop%'
OR arch_db ILIKE '%ui_button_labels_json%'
OR arch_db ILIKE '%ui_table_alias_json%'
OR arch_db ILIKE '%ui_sales_receipt_note%'
OR arch_db ILIKE '%ui_kitchen_receipt_note%'
OR arch_db ILIKE '%table_code%';

-- 2) Remove custom fields from prior module (if they still exist).
DELETE FROM ir_model_fields
WHERE model = 'pos.config'
  AND name IN (
    'ui_enable_drag_and_drop',
    'ui_button_labels_json',
    'ui_table_alias_json',
    'ui_sales_receipt_note',
    'ui_kitchen_receipt_note'
  );

DELETE FROM ir_model_fields
WHERE model = 'restaurant.table'
  AND name = 'table_code';

-- 3) Remove stale assets to prevent old JS from loading.
DELETE FROM ir_attachment
WHERE url ILIKE '/web/assets/%'
   OR name ILIKE '%web.assets%'
   OR name ILIKE '%point_of_sale.assets%'
   OR name ILIKE '%pos_table_code%';

-- 4) Mark module as uninstalled if record remains.
UPDATE ir_module_module
SET state = 'uninstalled'
WHERE name = 'pos_table_code';

COMMIT;
