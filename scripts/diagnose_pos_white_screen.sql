-- Diagnose leftover references that can cause POS Owl white screen.
-- Usage: psql <db_name> -f scripts/diagnose_pos_white_screen.sql

\echo '=== Remaining ir_ui_view references ==='
SELECT id, name, model
FROM ir_ui_view
WHERE arch_db ILIKE '%ui_enable_drag_and_drop%'
   OR arch_db ILIKE '%ui_button_labels_json%'
   OR arch_db ILIKE '%ui_table_alias_json%'
   OR arch_db ILIKE '%ui_sales_receipt_note%'
   OR arch_db ILIKE '%ui_kitchen_receipt_note%'
   OR arch_db ILIKE '%table_code%'
ORDER BY id DESC;

\echo '=== Remaining ir_ui_view_custom references ==='
SELECT id, ref_id, user_id
FROM ir_ui_view_custom
WHERE arch ILIKE '%ui_enable_drag_and_drop%'
   OR arch ILIKE '%ui_button_labels_json%'
   OR arch ILIKE '%ui_table_alias_json%'
   OR arch ILIKE '%ui_sales_receipt_note%'
   OR arch ILIKE '%ui_kitchen_receipt_note%'
   OR arch ILIKE '%table_code%'
ORDER BY id DESC;

\echo '=== Remaining field metadata ==='
SELECT id, model, name
FROM ir_model_fields
WHERE (model = 'pos.config' AND name IN (
    'ui_enable_drag_and_drop',
    'ui_button_labels_json',
    'ui_table_alias_json',
    'ui_sales_receipt_note',
    'ui_kitchen_receipt_note'
))
OR (model = 'restaurant.table' AND name = 'table_code')
ORDER BY id DESC;

\echo '=== Module state ==='
SELECT id, name, state
FROM ir_module_module
WHERE name = 'pos_table_code';
