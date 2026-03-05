<?php
/**
 * Plugin Name: Print Platform
 * Description: PrintNow-style WooCommerce admin product manager for print products.
 * Version: 1.2.0
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Text Domain: print-platform
 */

defined('ABSPATH') || exit;

final class Print_Platform_Plugin {
    private const DB_VERSION = 2;

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('plugins_loaded', [$this, 'maybe_upgrade']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        add_action('admin_post_pp_add_product', [$this, 'handle_add_product']);
        add_action('admin_post_pp_toggle_published', [$this, 'handle_toggle_published']);
        add_action('admin_post_pp_duplicate_product', [$this, 'handle_duplicate_product']);
        add_action('admin_post_pp_delete_product', [$this, 'handle_delete_product']);
        add_action('admin_post_pp_save_product', [$this, 'handle_save_product']);
        add_action('admin_post_pp_export_csv', [$this, 'handle_export_csv']);
        add_action('admin_post_pp_import_csv', [$this, 'handle_import_csv']);

        add_action('admin_post_pp_save_base_price_set', [$this, 'handle_save_base_price_set']);
        add_action('admin_post_pp_delete_base_price_set', [$this, 'handle_delete_base_price_set']);
        add_action('admin_post_pp_save_base_price_size', [$this, 'handle_save_base_price_size']);
        add_action('admin_post_pp_delete_base_price_size', [$this, 'handle_delete_base_price_size']);

        add_action('admin_post_pp_save_quantities', [$this, 'handle_save_quantities']);
        add_action('admin_post_pp_save_print_modes', [$this, 'handle_save_print_modes']);
        add_action('admin_post_pp_save_pricing_row', [$this, 'handle_save_pricing_row']);
        add_action('admin_post_pp_delete_pricing_row', [$this, 'handle_delete_pricing_row']);
        add_action('admin_post_pp_export_pricing_csv', [$this, 'handle_export_pricing_csv']);
        add_action('admin_post_pp_import_pricing_csv', [$this, 'handle_import_pricing_csv']);
    }

    public static function activate(): void {
        self::create_tables();
        update_option('pp_db_version', self::DB_VERSION);
    }

    public function maybe_upgrade(): void {
        if ((int) get_option('pp_db_version', 0) < self::DB_VERSION) {
            self::create_tables();
            update_option('pp_db_version', self::DB_VERSION);
        }
    }

    private static function create_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $sets = $wpdb->prefix . 'pp_base_price_sets';
        $sizes = $wpdb->prefix . 'pp_base_price_sizes';
        $pricing = $wpdb->prefix . 'pp_pricing_rows';

        dbDelta("CREATE TABLE {$sets} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            category_term_id BIGINT UNSIGNED NOT NULL,
            pricing_mode VARCHAR(20) NOT NULL DEFAULT 'LUPI',
            quantity_mode VARCHAR(20) NOT NULL DEFAULT 'dropdown',
            qty_breaks LONGTEXT NULL,
            min_qty INT UNSIGNED NULL,
            max_qty INT UNSIGNED NULL,
            step INT UNSIGNED NULL,
            print_modes LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY category_term_id (category_term_id)
        ) {$charset}");

        dbDelta("CREATE TABLE {$sizes} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            base_price_set_id BIGINT UNSIGNED NOT NULL,
            size_key VARCHAR(64) NOT NULL,
            label VARCHAR(190) NOT NULL,
            width DECIMAL(10,3) NULL,
            height DECIMAL(10,3) NULL,
            unit VARCHAR(10) NOT NULL DEFAULT 'mm',
            bleed DECIMAL(10,3) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY base_price_set_id (base_price_set_id),
            KEY size_key (size_key)
        ) {$charset}");

        dbDelta("CREATE TABLE {$pricing} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            base_price_set_id BIGINT UNSIGNED NOT NULL,
            size_id BIGINT UNSIGNED NOT NULL,
            print_mode_key VARCHAR(64) NOT NULL,
            qty_break INT UNSIGNED NOT NULL,
            price_total DECIMAL(12,4) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_row (base_price_set_id,size_id,print_mode_key,qty_break),
            KEY set_idx (base_price_set_id),
            KEY size_idx (size_id)
        ) {$charset}");
    }

    public function register_rest_routes(): void {
        register_rest_route('print-platform/v1', '/quote', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'rest_quote'],
        ]);
    }

    public function register_menu(): void {
        if (! class_exists('WooCommerce')) {
            return;
        }

        add_submenu_page('woocommerce', __('Print Platform Products', 'print-platform'), __('Print Platform', 'print-platform'), 'manage_woocommerce', 'print-platform-products', [$this, 'render_products_page']);
        add_submenu_page('woocommerce', __('Base Price Sets', 'print-platform'), __('Base Price Sets', 'print-platform'), 'manage_woocommerce', 'print-platform-base-price-sets', [$this, 'render_base_price_sets_page']);
        add_submenu_page(null, __('Edit Print Product', 'print-platform'), __('Edit Print Product', 'print-platform'), 'manage_woocommerce', 'print-platform-edit-product', [$this, 'render_edit_page']);
    }

    public function enqueue_assets(string $hook): void {
        if (! in_array($hook, ['woocommerce_page_print-platform-products', 'admin_page_print-platform-edit-product', 'woocommerce_page_print-platform-base-price-sets'], true)) {
            return;
        }
        wp_enqueue_style('print-platform-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], '1.2.0');
    }

    private function pricing_mode_labels(): array {
        return [
            'LOOKUP' => 'Exact price breaks',
            'LPI' => 'Smooth pricing (total)',
            'LUPI' => 'Smooth pricing (per unit)',
            'UP' => 'Step pricing (per unit)',
        ];
    }

    public function render_products_page(): void {
        $this->must_manage();
        $searchId = isset($_GET['pp_search_id']) ? absint($_GET['pp_search_id']) : 0;
        $args = [
            'post_type' => 'product', 'post_status' => ['publish', 'draft'], 'posts_per_page' => 200,
            'meta_query' => [['key' => '_pp_is_print_product', 'value' => '1']],
        ];
        if ($searchId) {
            $args['post__in'] = [$searchId];
        }
        $query = new WP_Query($args);

        echo '<div class="wrap pp-wrap"><h1 class="wp-heading-inline">' . esc_html__('Print Platform – Products', 'print-platform') . '</h1><hr class="wp-header-end" />';
        echo '<div class="pp-toolbar">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('pp_add_product');
        echo '<input type="hidden" name="action" value="pp_add_product" />'; submit_button(__('+ Add Product', 'print-platform'), 'primary', 'submit', false); echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('pp_export_csv');
        echo '<input type="hidden" name="action" value="pp_export_csv" />'; submit_button(__('Export', 'print-platform'), 'secondary', 'submit', false); echo '</form>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('pp_import_csv');
        echo '<input type="hidden" name="action" value="pp_import_csv" /><input type="file" name="pp_csv" accept=".csv" required /> '; submit_button(__('Import', 'print-platform'), 'secondary', 'submit', false); echo '</form>';
        echo '<form method="get"><input type="hidden" name="page" value="print-platform-products" /><input type="number" name="pp_search_id" placeholder="' . esc_attr__('Search by Product ID', 'print-platform') . '" value="' . esc_attr((string) $searchId) . '" /> '; submit_button(__('Search', 'print-platform'), 'secondary', 'submit', false); echo '</form></div>';

        echo '<table class="widefat striped pp-table"><thead><tr><th>ID</th><th>Thumbnail</th><th>Sort</th><th>Name</th><th>Last Saved</th><th>Published</th><th>Action</th></tr></thead><tbody>';
        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID); if (! $product) continue;
            $editUrl = admin_url('admin.php?page=print-platform-edit-product&product_id=' . $post->ID);
            $lastSaved = (string) get_post_meta($post->ID, '_pp_last_saved', true);
            $published = $post->post_status === 'publish';
            $thumb = get_the_post_thumbnail($post->ID, [40, 40]) ?: '—';

            echo '<tr><td>' . esc_html((string) $post->ID) . '</td><td>' . $thumb . '</td><td>' . esc_html((string) $post->menu_order) . '</td><td><a href="' . esc_url($editUrl) . '">' . esc_html($post->post_title ?: '(no title)') . '</a></td><td>' . esc_html($lastSaved ?: '—') . '</td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('pp_toggle_published_' . $post->ID);
            echo '<input type="hidden" name="action" value="pp_toggle_published" /><input type="hidden" name="product_id" value="' . esc_attr((string) $post->ID) . '" />';
            echo '<label><input type="checkbox" name="published" value="1" ' . checked($published, true, false) . ' onchange="this.form.submit()"/> ' . esc_html__('Published', 'print-platform') . '</label></form></td><td>';

            echo '<details><summary>⋮</summary><div class="pp-actions"><a href="' . esc_url($editUrl) . '">Edit</a>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('pp_duplicate_' . $post->ID);
            echo '<input type="hidden" name="action" value="pp_duplicate_product" /><input type="hidden" name="product_id" value="' . esc_attr((string) $post->ID) . '" />'; submit_button('Duplicate', 'link', 'submit', false); echo '</form>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js('Delete this product?') . '\');">'; wp_nonce_field('pp_delete_' . $post->ID);
            echo '<input type="hidden" name="action" value="pp_delete_product" /><input type="hidden" name="product_id" value="' . esc_attr((string) $post->ID) . '" />'; submit_button('Delete', 'link-delete', 'submit', false); echo '</form>';
            echo '</div></details></td></tr>';
        }
        if (empty($query->posts)) echo '<tr><td colspan="7">No print products found.</td></tr>';
        echo '</tbody></table></div>';
    }

    public function render_base_price_sets_page(): void {
        $this->must_manage();
        $action = isset($_GET['view']) ? sanitize_key((string) $_GET['view']) : 'list';
        echo '<div class="wrap pp-wrap"><h1 class="wp-heading-inline">Base Price Sets</h1><hr class="wp-header-end" />';
        if ($action === 'edit') {
            $this->render_base_price_set_edit();
            echo '</div>';
            return;
        }

        $sets = $this->get_base_price_sets();
        $labels = $this->pricing_mode_labels();
        $catNames = [];
        foreach (get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]) as $term) {
            $catNames[(int) $term->term_id] = $term->name;
        }

        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=print-platform-base-price-sets&view=edit')) . '">+ Add Base Price Set</a></p>';
        echo '<table class="widefat striped pp-table"><thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Pricing Mode</th><th>Sizes Count</th><th>Updated</th><th>Actions</th></tr></thead><tbody>';
        foreach ($sets as $set) {
            $id = (int) $set['id'];
            $editUrl = admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $id);
            echo '<tr><td>' . $id . '</td><td>' . esc_html($set['name']) . '</td><td>' . esc_html($catNames[(int) $set['category_term_id']] ?? '—') . '</td><td>' . esc_html($labels[$set['pricing_mode']] ?? $set['pricing_mode']) . '</td><td>' . esc_html((string) $this->count_sizes($id)) . '</td><td>' . esc_html((string) $set['updated_at']) . '</td><td>';
            echo '<a class="button button-small" href="' . esc_url($editUrl) . '">Edit</a> ';
            echo '<form style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js('Delete this base price set?') . '\')">';
            wp_nonce_field('pp_delete_base_price_set_' . $id);
            echo '<input type="hidden" name="action" value="pp_delete_base_price_set" /><input type="hidden" name="id" value="' . esc_attr((string) $id) . '" />'; submit_button('Delete', 'link-delete', 'submit', false);
            echo '</form></td></tr>';
        }
        if (empty($sets)) echo '<tr><td colspan="7">No base price sets created yet.</td></tr>';
        echo '</tbody></table></div>';
    }

    private function render_base_price_set_edit(): void {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $set = $id ? $this->get_base_price_set($id) : null;
        $viewTab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'general';

        echo '<nav class="pp-tabs">';
        foreach (['general' => 'General Info', 'quantities' => 'Quantities', 'print-modes' => 'Print Modes', 'pricing' => 'Pricing Table'] as $tabKey => $label) {
            $url = admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $id . '&tab=' . $tabKey);
            echo '<a class="' . esc_attr($viewTab === $tabKey ? 'pp-tab is-active' : 'pp-tab') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        if ($viewTab === 'general') {
            $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
            $labels = $this->pricing_mode_labels();
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pp-card" style="max-width:700px">';
            wp_nonce_field('pp_save_base_price_set');
            echo '<input type="hidden" name="action" value="pp_save_base_price_set" /><input type="hidden" name="id" value="' . esc_attr((string) $id) . '" />';
            echo '<h2>' . esc_html($id ? 'Edit Base Price Set' : 'Add Base Price Set') . '</h2>';
            echo '<p><label>Name<br/><input class="regular-text" type="text" name="name" value="' . esc_attr((string) ($set['name'] ?? '')) . '" required/></label></p>';
            echo '<p><label>Category<br/><select class="regular-text" name="category_term_id" required><option value="">Select category</option>';
            foreach ($terms as $term) echo '<option value="' . esc_attr((string) $term->term_id) . '" ' . selected((int) ($set['category_term_id'] ?? 0), (int) $term->term_id, false) . '>' . esc_html($term->name) . '</option>';
            echo '</select></label></p>';
            $mode = (string) ($set['pricing_mode'] ?? 'LUPI');
            echo '<p><label>Pricing mode<br/><select class="regular-text" name="pricing_mode">';
            foreach ($labels as $k => $v) echo '<option value="' . esc_attr($k) . '" ' . selected($mode, $k, false) . '>' . esc_html($v) . '</option>';
            echo '</select></label></p>';
            submit_button('Save Base Price Set');
            echo '</form>';

            if ($id > 0) {
                $this->render_sizes_section($id);
            }
            return;
        }

        if ($id <= 0) {
            echo '<div class="pp-card"><p>Save General Info first to configure this tab.</p></div>';
            return;
        }

        if ($viewTab === 'quantities') {
            $qtyMode = (string) ($set['quantity_mode'] ?? 'dropdown');
            $qtyBreaks = is_string($set['qty_breaks'] ?? null) ? json_decode((string) $set['qty_breaks'], true) : [];
            if (! is_array($qtyBreaks)) $qtyBreaks = [];
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pp-card" style="max-width:700px">';
            wp_nonce_field('pp_save_quantities_' . $id);
            echo '<input type="hidden" name="action" value="pp_save_quantities" /><input type="hidden" name="id" value="' . esc_attr((string) $id) . '" />';
            echo '<h2>Quantities</h2>';
            echo '<p><label>Quantity mode<br/><select name="quantity_mode" class="regular-text"><option value="dropdown" ' . selected($qtyMode, 'dropdown', false) . '>Dropdown breaks</option><option value="textbox" ' . selected($qtyMode, 'textbox', false) . '>Textbox (min/max/step)</option></select></label></p>';
            echo '<p><label>Qty breaks (comma-separated integers)<br/><input class="regular-text" name="qty_breaks" value="' . esc_attr(implode(',', array_map('intval', $qtyBreaks))) . '" /></label></p>';
            echo '<p><label>Min qty<br/><input type="number" name="min_qty" value="' . esc_attr((string) ($set['min_qty'] ?? 1)) . '" /></label></p>';
            echo '<p><label>Max qty<br/><input type="number" name="max_qty" value="' . esc_attr((string) ($set['max_qty'] ?? 100000)) . '" /></label></p>';
            echo '<p><label>Step<br/><input type="number" name="step" value="' . esc_attr((string) ($set['step'] ?? 1)) . '" /></label></p>';
            submit_button('Save Quantities');
            echo '</form>';
            return;
        }

        if ($viewTab === 'print-modes') {
            $printModes = is_string($set['print_modes'] ?? null) ? json_decode((string) $set['print_modes'], true) : [];
            if (! is_array($printModes) || empty($printModes)) {
                $printModes = [
                    ['key' => 'SINGLE', 'label' => 'Single sided'],
                    ['key' => 'DOUBLE', 'label' => 'Double sided'],
                ];
            }
            $raw = implode("\n", array_map(static fn($m) => ($m['key'] ?? '') . '|' . ($m['label'] ?? ''), $printModes));
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pp-card" style="max-width:700px">';
            wp_nonce_field('pp_save_print_modes_' . $id);
            echo '<input type="hidden" name="action" value="pp_save_print_modes" /><input type="hidden" name="id" value="' . esc_attr((string) $id) . '" />';
            echo '<h2>Print Modes</h2><p class="description">MVP defaults: SINGLE and DOUBLE. One mode per line: KEY|Label</p>';
            echo '<textarea class="large-text" rows="8" name="print_modes_raw">' . esc_textarea($raw) . '</textarea>';
            submit_button('Save Print Modes');
            echo '</form>';
            return;
        }

        $this->render_pricing_table_tab($id, $set);
    }

    private function render_sizes_section(int $setId): void {
        $sizes = $this->get_sizes_for_set($setId);
        echo '<div class="pp-card" style="margin-top:16px"><h2>Sizes</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Key</th><th>Label</th><th>Width</th><th>Height</th><th>Unit</th><th>Bleed</th><th>Action</th></tr></thead><tbody>';
        foreach ($sizes as $size) {
            echo '<tr><td>' . esc_html((string) $size['id']) . '</td><td>' . esc_html($size['size_key']) . '</td><td>' . esc_html($size['label']) . '</td><td>' . esc_html((string) $size['width']) . '</td><td>' . esc_html((string) $size['height']) . '</td><td>' . esc_html($size['unit']) . '</td><td>' . esc_html((string) $size['bleed']) . '</td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pp_delete_base_price_size_' . (int) $size['id']);
            echo '<input type="hidden" name="action" value="pp_delete_base_price_size" /><input type="hidden" name="id" value="' . esc_attr((string) $size['id']) . '" /><input type="hidden" name="base_price_set_id" value="' . esc_attr((string) $setId) . '" />';
            submit_button('Delete', 'link-delete', 'submit', false);
            echo '</form></td></tr>';
        }
        if (empty($sizes)) echo '<tr><td colspan="8">No sizes yet.</td></tr>';
        echo '</tbody></table><h3 style="margin-top:16px">Add Size</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pp_save_base_price_size');
        echo '<input type="hidden" name="action" value="pp_save_base_price_size" /><input type="hidden" name="base_price_set_id" value="' . esc_attr((string) $setId) . '" />';
        echo '<p><label>Size key<br/><input class="regular-text" name="size_key" required /></label></p>';
        echo '<p><label>Label<br/><input class="regular-text" name="label" required /></label></p>';
        echo '<p><label>Width<br/><input type="number" step="0.001" name="width" /></label></p>';
        echo '<p><label>Height<br/><input type="number" step="0.001" name="height" /></label></p>';
        echo '<p><label>Unit<br/><input class="small-text" name="unit" value="mm" /></label></p>';
        echo '<p><label>Bleed<br/><input type="number" step="0.001" name="bleed" /></label></p>';
        submit_button('Add Size');
        echo '</form></div>';
    }

    private function render_pricing_table_tab(int $setId, array $set): void {
        $rows = $this->get_pricing_rows($setId);
        $sizes = $this->get_sizes_for_set($setId);
        $printModes = is_string($set['print_modes'] ?? null) ? json_decode((string) $set['print_modes'], true) : [];
        if (! is_array($printModes) || empty($printModes)) {
            $printModes = [['key' => 'SINGLE', 'label' => 'Single sided'], ['key' => 'DOUBLE', 'label' => 'Double sided']];
        }

        echo '<div class="pp-card"><h2>Pricing Table</h2>';
        echo '<div class="pp-toolbar">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pp_export_pricing_csv_' . $setId);
        echo '<input type="hidden" name="action" value="pp_export_pricing_csv" /><input type="hidden" name="base_price_set_id" value="' . esc_attr((string) $setId) . '" />';
        submit_button('Export CSV', 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pp_import_pricing_csv_' . $setId);
        echo '<input type="hidden" name="action" value="pp_import_pricing_csv" /><input type="hidden" name="base_price_set_id" value="' . esc_attr((string) $setId) . '" /><input type="file" name="pp_pricing_csv" accept=".csv" required /> ';
        submit_button('Import CSV', 'secondary', 'submit', false);
        echo '</form></div>';

        echo '<table class="widefat striped"><thead><tr><th>Size</th><th>Print Mode</th><th>Qty Break</th><th>Price Total</th><th>Action</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html($this->size_label((int) $row['size_id'])) . '</td><td>' . esc_html($row['print_mode_key']) . '</td><td>' . esc_html((string) $row['qty_break']) . '</td><td>' . esc_html((string) $row['price_total']) . '</td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pp_delete_pricing_row_' . (int) $row['id']);
            echo '<input type="hidden" name="action" value="pp_delete_pricing_row" /><input type="hidden" name="id" value="' . esc_attr((string) $row['id']) . '" /><input type="hidden" name="base_price_set_id" value="' . esc_attr((string) $setId) . '" />';
            submit_button('Delete', 'link-delete', 'submit', false);
            echo '</form></td></tr>';
        }
        if (empty($rows)) echo '<tr><td colspan="5">No pricing rows yet.</td></tr>';
        echo '</tbody></table>';

        echo '<h3 style="margin-top:16px">Add Pricing Row</h3><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pp_save_pricing_row_' . $setId);
        echo '<input type="hidden" name="action" value="pp_save_pricing_row" /><input type="hidden" name="base_price_set_id" value="' . esc_attr((string) $setId) . '" />';
        echo '<p><label>Size<br/><select name="size_id" required>';
        foreach ($sizes as $size) echo '<option value="' . esc_attr((string) $size['id']) . '">' . esc_html($size['label']) . '</option>';
        echo '</select></label></p>';
        echo '<p><label>Print mode<br/><select name="print_mode_key" required>';
        foreach ($printModes as $m) echo '<option value="' . esc_attr((string) $m['key']) . '">' . esc_html((string) ($m['label'] ?? $m['key'])) . '</option>';
        echo '</select></label></p>';
        echo '<p><label>Quantity break<br/><input type="number" name="qty_break" min="1" required /></label></p>';
        echo '<p><label>Price total<br/><input type="number" step="0.0001" name="price_total" required /></label></p>';
        submit_button('Add Pricing Row');
        echo '</form></div>';
    }

    public function render_edit_page(): void {
        $this->must_manage();
        $productId = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
        $product = $productId ? wc_get_product($productId) : null;
        if (! $product) wp_die('Invalid product.');

        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'product-information';
        $tabs = ['product-information' => 'Product Information', 'print-editor' => 'Print Editor', 'attributes' => 'Attributes', 'related-products' => 'Related Products', 'alternate-view' => 'Alternate View', 'comments' => 'Comments'];
        $meta = function (string $key, string $default = '') use ($productId): string { $v = get_post_meta($productId, $key, true); return $v === '' ? $default : (string) $v; };

        echo '<div class="wrap pp-wrap"><div class="pp-head"><h1>Edit Product: ' . esc_html($product->get_name()) . '</h1>';
        if ($tab === 'product-information') {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pp_save_product_' . $productId);
            echo '<input type="hidden" name="action" value="pp_save_product" /><input type="hidden" name="product_id" value="' . esc_attr((string) $productId) . '" />';
            submit_button('Save', 'primary', 'submit', false, ['style' => 'margin:0']);
            echo '</div>';
        } else echo '</div>';

        echo '<nav class="pp-tabs">';
        foreach ($tabs as $slug => $label) echo '<a class="' . esc_attr($slug === $tab ? 'pp-tab is-active' : 'pp-tab') . '" href="' . esc_url(admin_url('admin.php?page=print-platform-edit-product&product_id=' . $productId . '&tab=' . $slug)) . '">' . esc_html($label) . '</a>';
        echo '</nav>';

        if ($tab !== 'product-information') {
            echo '<div class="pp-card"><h2>' . esc_html($tabs[$tab] ?? 'Tab') . '</h2><p>This section is available in the next phase.</p></div></div>';
            return;
        }

        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $currentCat = wp_get_post_terms($productId, 'product_cat', ['fields' => 'ids']);
        $currentCatId = (int) ($currentCat[0] ?? 0);
        $selectedSetId = (int) get_post_meta($productId, '_pp_base_price_set_id', true);
        $selectedSizeId = (int) get_post_meta($productId, '_pp_size_id', true);

        $allSets = $this->get_base_price_sets();
        $setsByCategory = [];
        $sizesBySet = [];
        foreach ($allSets as $set) {
            $catId = (int) $set['category_term_id'];
            $setsByCategory[$catId][] = ['id' => (int) $set['id'], 'name' => $set['name']];
            $sizesBySet[(int) $set['id']] = array_map(static fn($s) => ['id' => (int) $s['id'], 'label' => $s['label']], $this->get_sizes_for_set((int) $set['id']));
        }

        echo '<div class="pp-grid"><section class="pp-card"><h2>BASIC INFORMATION</h2>';
        $this->field_text('CMS PageLink', 'pp_cms_pagelink', $meta('_pp_cms_pagelink'));
        $this->field_text('Name', 'post_title', $product->get_name());
        $this->field_textarea('Description', 'post_content', $product->get_description());
        $this->field_select('Product Type', 'pp_product_type', $meta('_pp_product_type', 'standard_template'), ['standard_template' => 'Standard Template', 'static' => 'Static', 'blank' => 'Blank']);
        echo '<p><label>Category<br/><select name="pp_category" id="pp_category" class="regular-text"><option value="0">—</option>';
        foreach ($categories as $cat) echo '<option value="' . esc_attr((string) $cat->term_id) . '" ' . selected($currentCatId, (int) $cat->term_id, false) . '>' . esc_html($cat->name) . '</option>';
        echo '</select></label></p>';
        $this->field_text('Vendor', 'pp_vendor', $meta('_pp_vendor'));
        $this->field_text('Hot Folder', 'pp_hot_folder', $meta('_pp_hot_folder'));
        $this->field_checkbox('Global Product', 'pp_global_product', $meta('_pp_global_product') === '1');
        $this->field_checkbox('Published', 'pp_published', get_post_status($productId) === 'publish');
        echo '</section>';

        echo '<section class="pp-card"><h2>PRICE MAPPING</h2><p><label>Base Price<br/><select name="pp_base_price_set_id" id="pp_base_price_set_id" class="regular-text"></select></label></p><p><label>Size<br/><select name="pp_size_id" id="pp_size_id" class="regular-text"></select></label></p><p class="description" id="pp_price_mapping_notice"></p></section>';

        echo '<section class="pp-card"><h2>PRODUCT NUMBERS</h2>';
        $this->field_text('Item Number', 'pp_item_number', $meta('_pp_item_number'));
        $this->field_text('Model Number', 'pp_model_number', $meta('_pp_model_number'));
        $this->field_text('Integration', 'pp_integration', $meta('_pp_integration'));
        echo '</section></div></form>';

        echo '<script>window.ppSetsByCategory=' . wp_json_encode($setsByCategory) . ';window.ppSizesBySet=' . wp_json_encode($sizesBySet) . ';window.ppSelectedSetId=' . (int) $selectedSetId . ';window.ppSelectedSizeId=' . (int) $selectedSizeId . ';</script>';
        echo '<script>(function(){const cat=document.getElementById("pp_category"),setSel=document.getElementById("pp_base_price_set_id"),sizeSel=document.getElementById("pp_size_id"),n=document.getElementById("pp_price_mapping_notice");if(!cat||!setSel||!sizeSel){return;}function renderSets(){const c=parseInt(cat.value||"0",10),sets=(window.ppSetsByCategory&&window.ppSetsByCategory[c])||[];setSel.innerHTML="";if(!c){const o=document.createElement("option");o.value="";o.textContent="Select a category first";setSel.appendChild(o);sizeSel.innerHTML="";n.textContent="Select a category to load base price sets.";return;}if(!sets.length){const o=document.createElement("option");o.value="";o.textContent="No base price sets for this category";setSel.appendChild(o);sizeSel.innerHTML="";n.textContent="Create a Base Price Set for this category.";return;}n.textContent="";sets.forEach(function(s){const o=document.createElement("option");o.value=s.id;o.textContent=s.name;if(parseInt(window.ppSelectedSetId||0,10)===parseInt(s.id,10))o.selected=true;setSel.appendChild(o);});renderSizes();}function renderSizes(){const sid=parseInt(setSel.value||"0",10),sizes=(window.ppSizesBySet&&window.ppSizesBySet[sid])||[];sizeSel.innerHTML="";if(!sid){const o=document.createElement("option");o.value="";o.textContent="Select base price first";sizeSel.appendChild(o);return;}if(!sizes.length){const o=document.createElement("option");o.value="";o.textContent="No sizes for selected base price";sizeSel.appendChild(o);return;}sizes.forEach(function(s){const o=document.createElement("option");o.value=s.id;o.textContent=s.label;if(parseInt(window.ppSelectedSizeId||0,10)===parseInt(s.id,10))o.selected=true;sizeSel.appendChild(o);});}cat.addEventListener("change",function(){window.ppSelectedSetId=0;window.ppSelectedSizeId=0;renderSets();});setSel.addEventListener("change",function(){window.ppSelectedSizeId=0;renderSizes();});renderSets();})();</script>';

        echo '</div>';
    }

    private function get_base_price_sets(): array {
        global $wpdb; return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}pp_base_price_sets ORDER BY id DESC", ARRAY_A) ?: [];
    }
    private function get_base_price_set(int $id): ?array {
        global $wpdb; $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_base_price_sets WHERE id=%d", $id), ARRAY_A); return $row ?: null;
    }
    private function get_sizes_for_set(int $setId): array {
        global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_base_price_sizes WHERE base_price_set_id=%d ORDER BY id ASC", $setId), ARRAY_A) ?: [];
    }
    private function count_sizes(int $setId): int {
        global $wpdb; return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pp_base_price_sizes WHERE base_price_set_id=%d", $setId));
    }
    private function size_label(int $sizeId): string {
        global $wpdb; return (string) ($wpdb->get_var($wpdb->prepare("SELECT label FROM {$wpdb->prefix}pp_base_price_sizes WHERE id=%d", $sizeId)) ?: 'Unknown');
    }
    private function get_pricing_rows(int $setId): array {
        global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_pricing_rows WHERE base_price_set_id=%d ORDER BY size_id,print_mode_key,qty_break", $setId), ARRAY_A) ?: [];
    }

    public function handle_save_base_price_set(): void {
        $this->must_manage(); check_admin_referer('pp_save_base_price_set');
        global $wpdb; $table = $wpdb->prefix . 'pp_base_price_sets';
        $id = absint($_POST['id'] ?? 0);
        $data = [
            'name' => sanitize_text_field((string) ($_POST['name'] ?? '')),
            'category_term_id' => absint($_POST['category_term_id'] ?? 0),
            'pricing_mode' => sanitize_text_field((string) ($_POST['pricing_mode'] ?? 'LUPI')),
            'updated_at' => current_time('mysql'),
        ];
        if ($id > 0) $wpdb->update($table, $data, ['id' => $id]);
        else { $data += ['created_at' => current_time('mysql'), 'quantity_mode' => 'dropdown', 'qty_breaks' => wp_json_encode([25,50,100]), 'min_qty' => 1, 'max_qty' => 100000, 'step' => 1, 'print_modes' => wp_json_encode([['key'=>'SINGLE','label'=>'Single sided'],['key'=>'DOUBLE','label'=>'Double sided']])]; $wpdb->insert($table, $data); $id = (int) $wpdb->insert_id; }
        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $id . '&tab=general')); exit;
    }

    public function handle_save_quantities(): void {
        $this->must_manage();
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('pp_save_quantities_' . $id);
        global $wpdb;
        $qtyBreaks = array_values(array_filter(array_map('intval', array_map('trim', explode(',', (string) ($_POST['qty_breaks'] ?? '')))), static fn($v) => $v > 0));
        sort($qtyBreaks);
        $wpdb->update($wpdb->prefix . 'pp_base_price_sets', [
            'quantity_mode' => sanitize_text_field((string) ($_POST['quantity_mode'] ?? 'dropdown')),
            'qty_breaks' => wp_json_encode($qtyBreaks),
            'min_qty' => absint($_POST['min_qty'] ?? 1),
            'max_qty' => absint($_POST['max_qty'] ?? 100000),
            'step' => max(1, absint($_POST['step'] ?? 1)),
            'updated_at' => current_time('mysql'),
        ], ['id' => $id]);
        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $id . '&tab=quantities')); exit;
    }

    public function handle_save_print_modes(): void {
        $this->must_manage();
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('pp_save_print_modes_' . $id);
        global $wpdb;
        $raw = (string) ($_POST['print_modes_raw'] ?? '');
        $modes = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = array_map('trim', explode('|', $line, 2));
            $key = strtoupper(sanitize_key($parts[0] ?? ''));
            $label = sanitize_text_field($parts[1] ?? ($parts[0] ?? ''));
            if ($key !== '') $modes[] = ['key' => $key, 'label' => $label];
        }
        if (empty($modes)) $modes = [['key' => 'SINGLE', 'label' => 'Single sided'], ['key' => 'DOUBLE', 'label' => 'Double sided']];
        $wpdb->update($wpdb->prefix . 'pp_base_price_sets', ['print_modes' => wp_json_encode($modes), 'updated_at' => current_time('mysql')], ['id' => $id]);
        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $id . '&tab=print-modes')); exit;
    }

    public function handle_save_pricing_row(): void {
        $this->must_manage();
        $setId = absint($_POST['base_price_set_id'] ?? 0);
        check_admin_referer('pp_save_pricing_row_' . $setId);
        global $wpdb;
        $table = $wpdb->prefix . 'pp_pricing_rows';
        $data = [
            'base_price_set_id' => $setId,
            'size_id' => absint($_POST['size_id'] ?? 0),
            'print_mode_key' => sanitize_text_field((string) ($_POST['print_mode_key'] ?? 'SINGLE')),
            'qty_break' => absint($_POST['qty_break'] ?? 1),
            'price_total' => (float) ($_POST['price_total'] ?? 0),
            'updated_at' => current_time('mysql'),
        ];
        $existingId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE base_price_set_id=%d AND size_id=%d AND print_mode_key=%s AND qty_break=%d",
            $data['base_price_set_id'], $data['size_id'], $data['print_mode_key'], $data['qty_break']
        ));
        if ($existingId > 0) {
            $wpdb->update($table, ['price_total' => $data['price_total'], 'updated_at' => $data['updated_at']], ['id' => $existingId]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
        }
        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $setId . '&tab=pricing')); exit;
    }

    public function handle_delete_pricing_row(): void {
        $this->must_manage();
        $id = absint($_POST['id'] ?? 0);
        $setId = absint($_POST['base_price_set_id'] ?? 0);
        check_admin_referer('pp_delete_pricing_row_' . $id);
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'pp_pricing_rows', ['id' => $id]);
        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $setId . '&tab=pricing')); exit;
    }

    public function handle_export_pricing_csv(): void {
        $this->must_manage();
        $setId = absint($_POST['base_price_set_id'] ?? 0);
        check_admin_referer('pp_export_pricing_csv_' . $setId);
        $rows = $this->get_pricing_rows($setId);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=base-price-set-' . $setId . '-pricing.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['size_id', 'print_mode_key', 'qty_break', 'price_total']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['size_id'], $r['print_mode_key'], $r['qty_break'], $r['price_total']]);
        }
        fclose($out);
        exit;
    }

    public function handle_import_pricing_csv(): void {
        $this->must_manage();
        $setId = absint($_POST['base_price_set_id'] ?? 0);
        check_admin_referer('pp_import_pricing_csv_' . $setId);
        if (empty($_FILES['pp_pricing_csv']['tmp_name'])) {
            wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $setId . '&tab=pricing')); exit;
        }
        $fh = fopen($_FILES['pp_pricing_csv']['tmp_name'], 'r');
        if (! $fh) {
            wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $setId . '&tab=pricing')); exit;
        }
        $header = fgetcsv($fh);
        $idx = array_flip($header ?: []);
        while (($row = fgetcsv($fh)) !== false) {
            $_POST = [
                'base_price_set_id' => $setId,
                'size_id' => absint($row[$idx['size_id']] ?? 0),
                'print_mode_key' => sanitize_text_field((string) ($row[$idx['print_mode_key']] ?? 'SINGLE')),
                'qty_break' => absint($row[$idx['qty_break']] ?? 1),
                'price_total' => (float) ($row[$idx['price_total']] ?? 0),
            ];
            global $wpdb;
            $table = $wpdb->prefix . 'pp_pricing_rows';
            $existingId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE base_price_set_id=%d AND size_id=%d AND print_mode_key=%s AND qty_break=%d",
                $_POST['base_price_set_id'], $_POST['size_id'], $_POST['print_mode_key'], $_POST['qty_break']
            ));
            if ($existingId > 0) {
                $wpdb->update($table, ['price_total' => $_POST['price_total'], 'updated_at' => current_time('mysql')], ['id' => $existingId]);
            } else {
                $wpdb->insert($table, [
                    'base_price_set_id' => $_POST['base_price_set_id'],
                    'size_id' => $_POST['size_id'],
                    'print_mode_key' => $_POST['print_mode_key'],
                    'qty_break' => $_POST['qty_break'],
                    'price_total' => $_POST['price_total'],
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
            }
        }
        fclose($fh);
        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $setId . '&tab=pricing')); exit;
    }

    public function handle_delete_base_price_set(): void {
        $this->must_manage(); $id = absint($_POST['id'] ?? 0); check_admin_referer('pp_delete_base_price_set_' . $id);
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'pp_base_price_sizes', ['base_price_set_id' => $id]);
        $wpdb->delete($wpdb->prefix . 'pp_pricing_rows', ['base_price_set_id' => $id]);
        $wpdb->delete($wpdb->prefix . 'pp_base_price_sets', ['id' => $id]);
        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets')); exit;
    }

    public function handle_save_base_price_size(): void {
        $this->must_manage(); check_admin_referer('pp_save_base_price_size');
        global $wpdb; $setId = absint($_POST['base_price_set_id'] ?? 0);
        $wpdb->insert($wpdb->prefix . 'pp_base_price_sizes', [
            'base_price_set_id' => $setId,
            'size_key' => sanitize_text_field((string) ($_POST['size_key'] ?? '')),
            'label' => sanitize_text_field((string) ($_POST['label'] ?? '')),
            'width' => $_POST['width'] !== '' ? (float) $_POST['width'] : null,
            'height' => $_POST['height'] !== '' ? (float) $_POST['height'] : null,
            'unit' => sanitize_text_field((string) ($_POST['unit'] ?? 'mm')),
            'bleed' => $_POST['bleed'] !== '' ? (float) $_POST['bleed'] : null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $setId . '&tab=general')); exit;
    }

    public function handle_delete_base_price_size(): void {
        $this->must_manage();
        $id = absint($_POST['id'] ?? 0); $setId = absint($_POST['base_price_set_id'] ?? 0);
        check_admin_referer('pp_delete_base_price_size_' . $id);
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'pp_base_price_sizes', ['id' => $id]);
        $wpdb->delete($wpdb->prefix . 'pp_pricing_rows', ['size_id' => $id]);
        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $setId . '&tab=general')); exit;
    }

    public function handle_add_product(): void {
        $this->must_manage(); check_admin_referer('pp_add_product');
        $id = wp_insert_post(['post_type' => 'product', 'post_status' => 'draft', 'post_title' => 'New Print Product']);
        if ($id) {
            update_post_meta($id, '_pp_is_print_product', '1');
            update_post_meta($id, '_pp_published', '0');
            update_post_meta($id, '_pp_last_saved', current_time('mysql'));
        }
        wp_safe_redirect(admin_url('admin.php?page=print-platform-edit-product&product_id=' . absint((int) $id))); exit;
    }
    public function handle_toggle_published(): void {
        $this->must_manage(); $id = absint($_POST['product_id'] ?? 0); check_admin_referer('pp_toggle_published_' . $id);
        $published = ! empty($_POST['published']);
        wp_update_post(['ID' => $id, 'post_status' => $published ? 'publish' : 'draft']);
        update_post_meta($id, '_pp_published', $published ? '1' : '0'); update_post_meta($id, '_pp_last_saved', current_time('mysql'));
        wp_safe_redirect(admin_url('admin.php?page=print-platform-products')); exit;
    }
    public function handle_duplicate_product(): void {
        $this->must_manage(); $id = absint($_POST['product_id'] ?? 0); check_admin_referer('pp_duplicate_' . $id);
        $post = get_post($id); if (! $post || $post->post_type !== 'product') { wp_safe_redirect(admin_url('admin.php?page=print-platform-products')); exit; }
        $newId = wp_insert_post(['post_type' => 'product', 'post_status' => 'draft', 'post_title' => $post->post_title . ' (Copy)', 'post_content' => $post->post_content]);
        if ($newId) {
            foreach (get_post_meta($id) as $key => $values) foreach ($values as $value) add_post_meta($newId, $key, maybe_unserialize($value));
            update_post_meta($newId, '_pp_last_saved', current_time('mysql'));
            wp_set_object_terms($newId, wp_get_post_terms($id, 'product_cat', ['fields' => 'ids']), 'product_cat');
        }
        wp_safe_redirect(admin_url('admin.php?page=print-platform-products')); exit;
    }
    public function handle_delete_product(): void {
        $this->must_manage(); $id = absint($_POST['product_id'] ?? 0); check_admin_referer('pp_delete_' . $id);
        wp_delete_post($id, true); wp_safe_redirect(admin_url('admin.php?page=print-platform-products')); exit;
    }

    public function handle_save_product(): void {
        $this->must_manage(); $id = absint($_POST['product_id'] ?? 0); check_admin_referer('pp_save_product_' . $id);
        wp_update_post(['ID' => $id, 'post_title' => sanitize_text_field((string) ($_POST['post_title'] ?? '')), 'post_content' => wp_kses_post((string) ($_POST['post_content'] ?? '')), 'post_status' => ! empty($_POST['pp_published']) ? 'publish' : 'draft']);
        $metaMap = [
            '_pp_cms_pagelink' => sanitize_text_field((string) ($_POST['pp_cms_pagelink'] ?? '')),
            '_pp_product_type' => sanitize_text_field((string) ($_POST['pp_product_type'] ?? 'standard_template')),
            '_pp_vendor' => sanitize_text_field((string) ($_POST['pp_vendor'] ?? '')),
            '_pp_hot_folder' => sanitize_text_field((string) ($_POST['pp_hot_folder'] ?? '')),
            '_pp_global_product' => ! empty($_POST['pp_global_product']) ? '1' : '0',
            '_pp_published' => ! empty($_POST['pp_published']) ? '1' : '0',
            '_pp_base_price_set_id' => absint($_POST['pp_base_price_set_id'] ?? 0),
            '_pp_size_id' => absint($_POST['pp_size_id'] ?? 0),
            '_pp_item_number' => sanitize_text_field((string) ($_POST['pp_item_number'] ?? '')),
            '_pp_model_number' => sanitize_text_field((string) ($_POST['pp_model_number'] ?? '')),
            '_pp_integration' => sanitize_text_field((string) ($_POST['pp_integration'] ?? '')),
            '_pp_is_print_product' => '1',
            '_pp_last_saved' => current_time('mysql'),
        ];
        foreach ($metaMap as $k => $v) update_post_meta($id, $k, $v);
        $catId = absint($_POST['pp_category'] ?? 0); if ($catId > 0) wp_set_object_terms($id, [$catId], 'product_cat');
        wp_safe_redirect(admin_url('admin.php?page=print-platform-edit-product&product_id=' . $id . '&tab=product-information&saved=1')); exit;
    }

    public function handle_export_csv(): void {
        $this->must_manage(); check_admin_referer('pp_export_csv');
        $posts = get_posts(['post_type' => 'product', 'post_status' => ['publish', 'draft'], 'numberposts' => -1, 'meta_key' => '_pp_is_print_product', 'meta_value' => '1']);
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=print-platform-products.csv');
        $out = fopen('php://output', 'w'); fputcsv($out, ['ID', 'Name', 'Published', 'Category', 'Base Price Set ID', 'Size ID', 'Vendor', 'Item Number', 'Model Number', 'Integration', 'CMS PageLink']);
        foreach ($posts as $post) {
            $cat = wp_get_post_terms($post->ID, 'product_cat', ['fields' => 'names']);
            fputcsv($out, [$post->ID, $post->post_title, get_post_status($post->ID) === 'publish' ? 1 : 0, $cat[0] ?? '', get_post_meta($post->ID, '_pp_base_price_set_id', true), get_post_meta($post->ID, '_pp_size_id', true), get_post_meta($post->ID, '_pp_vendor', true), get_post_meta($post->ID, '_pp_item_number', true), get_post_meta($post->ID, '_pp_model_number', true), get_post_meta($post->ID, '_pp_integration', true), get_post_meta($post->ID, '_pp_cms_pagelink', true)]);
        }
        fclose($out); exit;
    }

    public function handle_import_csv(): void {
        $this->must_manage(); check_admin_referer('pp_import_csv');
        if (empty($_FILES['pp_csv']['tmp_name'])) { wp_safe_redirect(admin_url('admin.php?page=print-platform-products')); exit; }
        $fh = fopen($_FILES['pp_csv']['tmp_name'], 'r'); if (! $fh) { wp_safe_redirect(admin_url('admin.php?page=print-platform-products')); exit; }
        $header = fgetcsv($fh); $index = array_flip($header ?: []);
        while (($row = fgetcsv($fh)) !== false) {
            $id = absint($row[$index['ID']] ?? 0); $name = sanitize_text_field((string) ($row[$index['Name']] ?? 'Imported Product')); $published = ! empty($row[$index['Published']] ?? '');
            if ($id && get_post($id)) wp_update_post(['ID' => $id, 'post_title' => $name, 'post_status' => $published ? 'publish' : 'draft']);
            else $id = wp_insert_post(['post_type' => 'product', 'post_status' => $published ? 'publish' : 'draft', 'post_title' => $name]);
            if (! $id) continue;
            update_post_meta($id, '_pp_is_print_product', '1'); update_post_meta($id, '_pp_published', $published ? '1' : '0');
            update_post_meta($id, '_pp_base_price_set_id', absint($row[$index['Base Price Set ID']] ?? 0)); update_post_meta($id, '_pp_size_id', absint($row[$index['Size ID']] ?? 0));
            update_post_meta($id, '_pp_vendor', sanitize_text_field((string) ($row[$index['Vendor']] ?? ''))); update_post_meta($id, '_pp_item_number', sanitize_text_field((string) ($row[$index['Item Number']] ?? '')));
            update_post_meta($id, '_pp_model_number', sanitize_text_field((string) ($row[$index['Model Number']] ?? ''))); update_post_meta($id, '_pp_integration', sanitize_text_field((string) ($row[$index['Integration']] ?? '')));
            update_post_meta($id, '_pp_cms_pagelink', sanitize_text_field((string) ($row[$index['CMS PageLink']] ?? ''))); update_post_meta($id, '_pp_last_saved', current_time('mysql'));
            $catName = sanitize_text_field((string) ($row[$index['Category']] ?? ''));
            if ($catName !== '') { $term = term_exists($catName, 'product_cat'); if (! $term) $term = wp_insert_term($catName, 'product_cat'); if (is_array($term) && ! empty($term['term_id'])) wp_set_object_terms($id, [(int) $term['term_id']], 'product_cat'); }
        }
        fclose($fh); wp_safe_redirect(admin_url('admin.php?page=print-platform-products')); exit;
    }

    public function rest_quote(WP_REST_Request $request): WP_REST_Response {
        $productId = absint($request->get_param('product_id'));
        $quantity = max(1, (int) $request->get_param('quantity'));
        $sizeId = absint($request->get_param('size_id'));
        $printModeKey = strtoupper(sanitize_text_field((string) $request->get_param('print_mode_key')));

        $setId = (int) get_post_meta($productId, '_pp_base_price_set_id', true);
        $set = $this->get_base_price_set($setId);
        if (! $set) {
            return new WP_REST_Response(['error' => 'No mapped base price set.'], 400);
        }

        $rows = $this->get_pricing_rows($setId);
        $rows = array_values(array_filter($rows, static fn($r) => (int) $r['size_id'] === $sizeId && strtoupper((string) $r['print_mode_key']) === $printModeKey));
        if (empty($rows)) {
            return new WP_REST_Response(['error' => 'No pricing rows for selection.'], 400);
        }

        usort($rows, static fn($a, $b) => ((int) $a['qty_break']) <=> ((int) $b['qty_break']));
        $lower = $rows[0];
        $upper = $rows[count($rows) - 1];
        foreach ($rows as $r) {
            if ((int) $r['qty_break'] <= $quantity) $lower = $r;
            if ((int) $r['qty_break'] >= $quantity) { $upper = $r; break; }
        }

        $mode = (string) ($set['pricing_mode'] ?? 'LOOKUP');
        $x1 = (int) $lower['qty_break']; $x2 = (int) $upper['qty_break'];
        $y1 = (float) $lower['price_total']; $y2 = (float) $upper['price_total'];

        if ($mode === 'LOOKUP') {
            $total = $y1;
        } elseif ($mode === 'UP') {
            $unit = $y1 / max(1, $x1);
            $total = $unit * $quantity;
        } elseif ($x1 === $x2) {
            $total = $y1;
        } elseif ($mode === 'LUPI') {
            $u1 = $y1 / $x1; $u2 = $y2 / $x2;
            $unit = $u1 + ($quantity - $x1) * (($u2 - $u1) / ($x2 - $x1));
            $total = $unit * $quantity;
        } else { // LPI
            $total = $y1 + ($quantity - $x1) * (($y2 - $y1) / ($x2 - $x1));
        }

        return new WP_REST_Response([
            'total' => round($total, 2),
            'breakdown' => [['label' => 'Base Price', 'amount' => round($total, 2)]],
            'used_breaks' => ['lower' => $x1, 'upper' => $x2],
            'mode' => $mode,
        ], 200);
    }

    private function must_manage(): void {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Unauthorized.', 'print-platform'));
        }
    }
}

register_activation_hook(__FILE__, ['Print_Platform_Plugin', 'activate']);
new Print_Platform_Plugin();
