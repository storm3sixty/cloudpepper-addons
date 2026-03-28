-- Emergency recovery for POS white-screen caused by stale pos_table_code metadata.
-- Usage: psql <db_name> -f scripts/emergency_pos_whitescreen_recovery.sql
--
-- This script is intentionally aggressive and should be used when normal cleanup
-- didn't resolve startup/webclient white screen.

BEGIN;

-- 1) Disable offending views instead of hard delete (safer rollback behavior).
UPDATE ir_ui_view
SET active = FALSE
WHERE active = TRUE
  AND (
      name IN (
          'pos.table.code.floor.form.extension',
          'pos.table.code.table.form.extension',
          'pos.table.code.pos.config.ui.designer.extension'
      )
      OR arch_db ILIKE '%ui_enable_drag_and_drop%'
      OR arch_db ILIKE '%ui_button_labels_json%'
      OR arch_db ILIKE '%ui_table_alias_json%'
      OR arch_db ILIKE '%ui_sales_receipt_note%'
      OR arch_db ILIKE '%ui_kitchen_receipt_note%'
      OR arch_db ILIKE '%table_code%'
  );

-- 2) Remove user custom view overlays that can keep broken fields alive.
DELETE FROM ir_ui_view_custom
WHERE arch ILIKE '%ui_enable_drag_and_drop%'
   OR arch ILIKE '%ui_button_labels_json%'
   OR arch ILIKE '%ui_table_alias_json%'
   OR arch ILIKE '%ui_sales_receipt_note%'
   OR arch ILIKE '%ui_kitchen_receipt_note%'
   OR arch ILIKE '%table_code%';

-- 3) Hard-remove module metadata so Odoo won't attempt related upgrades/state transitions.
DELETE FROM ir_model_data WHERE module = 'pos_table_code';
DELETE FROM ir_module_module_dependency WHERE module_id IN (
    SELECT id FROM ir_module_module WHERE name = 'pos_table_code'
);
DELETE FROM ir_module_module WHERE name = 'pos_table_code';

-- 4) Remove leftover field metadata.
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

-- 5) Purge compiled web bundles.
DELETE FROM ir_attachment
WHERE url ILIKE '/web/assets/%'
   OR name ILIKE '%web.assets%'
   OR name ILIKE '%point_of_sale.assets%'
   OR name ILIKE '%pos_table_code%';

COMMIT;
