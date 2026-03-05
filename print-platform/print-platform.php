<?php
/**
 * Plugin Name: Print Platform
 * Description: PrintNow-style WooCommerce admin product manager for print products.
 * Version: 1.1.0
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Text Domain: print-platform
 */

defined('ABSPATH') || exit;

final class Print_Platform_Plugin {
    private const DB_VERSION = 1;

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

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

        add_action('plugins_loaded', [$this, 'maybe_upgrade']);
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

        dbDelta("CREATE TABLE {$sets} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            category_term_id BIGINT UNSIGNED NOT NULL,
            pricing_mode VARCHAR(20) NOT NULL DEFAULT 'LUPI',
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
    }

    public function register_menu(): void {
        if (! class_exists('WooCommerce')) {
            return;
        }

        add_submenu_page(
            'woocommerce',
            __('Print Platform Products', 'print-platform'),
            __('Print Platform', 'print-platform'),
            'manage_woocommerce',
            'print-platform-products',
            [$this, 'render_products_page']
        );

        add_submenu_page(
            'woocommerce',
            __('Base Price Sets', 'print-platform'),
            __('Base Price Sets', 'print-platform'),
            'manage_woocommerce',
            'print-platform-base-price-sets',
            [$this, 'render_base_price_sets_page']
        );

        add_submenu_page(
            null,
            __('Edit Print Product', 'print-platform'),
            __('Edit Print Product', 'print-platform'),
            'manage_woocommerce',
            'print-platform-edit-product',
            [$this, 'render_edit_page']
        );
    }

    public function enqueue_assets(string $hook): void {
        if (! in_array($hook, ['woocommerce_page_print-platform-products', 'admin_page_print-platform-edit-product', 'woocommerce_page_print-platform-base-price-sets'], true)) {
            return;
        }

        wp_enqueue_style('print-platform-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], '1.1.0');
    }

    public function render_products_page(): void {
        $this->must_manage();

        $searchId = isset($_GET['pp_search_id']) ? absint($_GET['pp_search_id']) : 0;
        $args = [
            'post_type' => 'product',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => 200,
            'meta_query' => [[
                'key' => '_pp_is_print_product',
                'value' => '1',
            ]],
        ];
        if ($searchId) {
            $args['post__in'] = [$searchId];
        }

        $query = new WP_Query($args);

        echo '<div class="wrap pp-wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Print Platform – Products', 'print-platform') . '</h1><hr class="wp-header-end" />';

        echo '<div class="pp-toolbar">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pp_add_product');
        echo '<input type="hidden" name="action" value="pp_add_product" />';
        submit_button(__('+ Add Product', 'print-platform'), 'primary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pp_export_csv');
        echo '<input type="hidden" name="action" value="pp_export_csv" />';
        submit_button(__('Export', 'print-platform'), 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pp_import_csv');
        echo '<input type="hidden" name="action" value="pp_import_csv" /><input type="file" name="pp_csv" accept=".csv" required /> ';
        submit_button(__('Import', 'print-platform'), 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="get"><input type="hidden" name="page" value="print-platform-products" />';
        echo '<input type="number" name="pp_search_id" placeholder="' . esc_attr__('Search by Product ID', 'print-platform') . '" value="' . esc_attr((string) $searchId) . '" /> ';
        submit_button(__('Search', 'print-platform'), 'secondary', 'submit', false);
        echo '</form></div>';

        echo '<table class="widefat striped pp-table"><thead><tr><th>ID</th><th>Thumbnail</th><th>Sort</th><th>Name</th><th>Last Saved</th><th>Published</th><th>Action</th></tr></thead><tbody>';
        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (! $product) {
                continue;
            }

            $editUrl = admin_url('admin.php?page=print-platform-edit-product&product_id=' . $post->ID);
            $lastSaved = (string) get_post_meta($post->ID, '_pp_last_saved', true);
            $published = $post->post_status === 'publish';
            $thumb = get_the_post_thumbnail($post->ID, [40, 40]) ?: '—';

            echo '<tr><td>' . esc_html((string) $post->ID) . '</td><td>' . $thumb . '</td><td>' . esc_html((string) $post->menu_order) . '</td>';
            echo '<td><a href="' . esc_url($editUrl) . '">' . esc_html($post->post_title ?: '(no title)') . '</a></td><td>' . esc_html($lastSaved ?: '—') . '</td><td>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pp_toggle_published_' . $post->ID);
            echo '<input type="hidden" name="action" value="pp_toggle_published" /><input type="hidden" name="product_id" value="' . esc_attr((string) $post->ID) . '" />';
            echo '<label><input type="checkbox" name="published" value="1" ' . checked($published, true, false) . ' onchange="this.form.submit()"/> ' . esc_html__('Published', 'print-platform') . '</label></form></td><td>';

            echo '<details><summary>⋮</summary><div class="pp-actions"><a href="' . esc_url($editUrl) . '">' . esc_html__('Edit', 'print-platform') . '</a>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pp_duplicate_' . $post->ID);
            echo '<input type="hidden" name="action" value="pp_duplicate_product" /><input type="hidden" name="product_id" value="' . esc_attr((string) $post->ID) . '" />';
            submit_button(__('Duplicate', 'print-platform'), 'link', 'submit', false);
            echo '</form>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Delete this product?', 'print-platform')) . '\');">';
            wp_nonce_field('pp_delete_' . $post->ID);
            echo '<input type="hidden" name="action" value="pp_delete_product" /><input type="hidden" name="product_id" value="' . esc_attr((string) $post->ID) . '" />';
            submit_button(__('Delete', 'print-platform'), 'link-delete', 'submit', false);
            echo '</form></div></details></td></tr>';
        }

        if (empty($query->posts)) {
            echo '<tr><td colspan="7">' . esc_html__('No print products found.', 'print-platform') . '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    public function render_base_price_sets_page(): void {
        $this->must_manage();
        $action = isset($_GET['view']) ? sanitize_key((string) $_GET['view']) : 'list';

        echo '<div class="wrap pp-wrap"><h1 class="wp-heading-inline">' . esc_html__('Base Price Sets', 'print-platform') . '</h1><hr class="wp-header-end" />';

        if ($action === 'edit') {
            $this->render_base_price_set_edit();
            echo '</div>';
            return;
        }

        $sets = $this->get_base_price_sets();
        $catNames = [];
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        foreach ($terms as $term) {
            $catNames[(int) $term->term_id] = $term->name;
        }

        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=print-platform-base-price-sets&view=edit')) . '">+ ' . esc_html__('Add Base Price Set', 'print-platform') . '</a></p>';
        echo '<table class="widefat striped pp-table"><thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Pricing Mode</th><th>Sizes Count</th><th>Updated</th><th>Actions</th></tr></thead><tbody>';
        foreach ($sets as $set) {
            $editUrl = admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . (int) $set['id']);
            $count = $this->count_sizes((int) $set['id']);
            echo '<tr><td>' . esc_html((string) $set['id']) . '</td><td>' . esc_html($set['name']) . '</td><td>' . esc_html($catNames[(int) $set['category_term_id']] ?? '—') . '</td>';
            echo '<td>' . esc_html($set['pricing_mode']) . '</td><td>' . esc_html((string) $count) . '</td><td>' . esc_html((string) $set['updated_at']) . '</td><td>';
            echo '<a class="button button-small" href="' . esc_url($editUrl) . '">' . esc_html__('Edit', 'print-platform') . '</a> ';
            echo '<form style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Delete this base price set?', 'print-platform')) . '\')">';
            wp_nonce_field('pp_delete_base_price_set_' . (int) $set['id']);
            echo '<input type="hidden" name="action" value="pp_delete_base_price_set" /><input type="hidden" name="id" value="' . esc_attr((string) $set['id']) . '" />';
            submit_button(__('Delete', 'print-platform'), 'link-delete', 'submit', false);
            echo '</form></td></tr>';
        }
        if (empty($sets)) {
            echo '<tr><td colspan="7">' . esc_html__('No base price sets created yet.', 'print-platform') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private function render_base_price_set_edit(): void {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $set = $id ? $this->get_base_price_set($id) : null;
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pp-card" style="max-width:700px">';
        wp_nonce_field('pp_save_base_price_set');
        echo '<input type="hidden" name="action" value="pp_save_base_price_set" /><input type="hidden" name="id" value="' . esc_attr((string) $id) . '" />';
        echo '<h2>' . esc_html($id ? 'Edit Base Price Set' : 'Add Base Price Set') . '</h2>';

        echo '<p><label>Name<br/><input class="regular-text" type="text" name="name" value="' . esc_attr((string) ($set['name'] ?? '')) . '" required/></label></p>';
        echo '<p><label>Category<br/><select class="regular-text" name="category_term_id" required><option value="">Select category</option>';
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr((string) $term->term_id) . '" ' . selected((int) ($set['category_term_id'] ?? 0), (int) $term->term_id, false) . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select></label></p>';

        $mode = (string) ($set['pricing_mode'] ?? 'LUPI');
        echo '<p><label>Pricing mode<br/><select class="regular-text" name="pricing_mode">';
        foreach (['LOOKUP', 'LPI', 'LUPI', 'UP'] as $m) {
            echo '<option value="' . esc_attr($m) . '" ' . selected($mode, $m, false) . '>' . esc_html($m) . '</option>';
        }
        echo '</select></label></p>';

        submit_button(__('Save Base Price Set', 'print-platform'));
        echo '</form>';

        if (! $id) {
            return;
        }

        $sizes = $this->get_sizes_for_set($id);
        echo '<div class="pp-card" style="margin-top:16px"><h2>Sizes</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Key</th><th>Label</th><th>Width</th><th>Height</th><th>Unit</th><th>Bleed</th><th>Action</th></tr></thead><tbody>';
        foreach ($sizes as $size) {
            echo '<tr><td>' . esc_html((string) $size['id']) . '</td><td>' . esc_html($size['size_key']) . '</td><td>' . esc_html($size['label']) . '</td><td>' . esc_html((string) $size['width']) . '</td><td>' . esc_html((string) $size['height']) . '</td><td>' . esc_html($size['unit']) . '</td><td>' . esc_html((string) $size['bleed']) . '</td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pp_delete_base_price_size_' . (int) $size['id']);
            echo '<input type="hidden" name="action" value="pp_delete_base_price_size" /><input type="hidden" name="id" value="' . esc_attr((string) $size['id']) . '" /><input type="hidden" name="base_price_set_id" value="' . esc_attr((string) $id) . '" />';
            submit_button(__('Delete', 'print-platform'), 'link-delete', 'submit', false);
            echo '</form></td></tr>';
        }
        if (empty($sizes)) {
            echo '<tr><td colspan="8">No sizes yet.</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h3 style="margin-top:16px">Add Size</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pp_save_base_price_size');
        echo '<input type="hidden" name="action" value="pp_save_base_price_size" /><input type="hidden" name="base_price_set_id" value="' . esc_attr((string) $id) . '" />';
        echo '<p><label>Size key<br/><input class="regular-text" name="size_key" required /></label></p>';
        echo '<p><label>Label<br/><input class="regular-text" name="label" required /></label></p>';
        echo '<p><label>Width<br/><input type="number" step="0.001" name="width" /></label></p>';
        echo '<p><label>Height<br/><input type="number" step="0.001" name="height" /></label></p>';
        echo '<p><label>Unit<br/><input class="small-text" name="unit" value="mm" /></label></p>';
        echo '<p><label>Bleed<br/><input type="number" step="0.001" name="bleed" /></label></p>';
        submit_button(__('Add Size', 'print-platform'));
        echo '</form></div>';
    }

    public function render_edit_page(): void {
        $this->must_manage();

        $productId = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
        $product = $productId ? wc_get_product($productId) : null;
        if (! $product) {
            wp_die(esc_html__('Invalid product.', 'print-platform'));
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'product-information';
        $tabs = [
            'product-information' => 'Product Information',
            'print-editor' => 'Print Editor',
            'attributes' => 'Attributes',
            'related-products' => 'Related Products',
            'alternate-view' => 'Alternate View',
            'comments' => 'Comments',
        ];

        $meta = function (string $key, string $default = '') use ($productId): string {
            $v = get_post_meta($productId, $key, true);
            return $v === '' ? $default : (string) $v;
        };

        echo '<div class="wrap pp-wrap">';
        echo '<div class="pp-head"><h1>' . esc_html__('Edit Product', 'print-platform') . ': ' . esc_html($product->get_name()) . '</h1>';

        if ($tab === 'product-information') {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pp_save_product_' . $productId);
            echo '<input type="hidden" name="action" value="pp_save_product" /><input type="hidden" name="product_id" value="' . esc_attr((string) $productId) . '" />';
            submit_button(__('Save', 'print-platform'), 'primary', 'submit', false, ['style' => 'margin:0']);
            echo '</div>';
        } else {
            echo '</div>';
        }

        echo '<nav class="pp-tabs">';
        foreach ($tabs as $slug => $label) {
            $url = admin_url('admin.php?page=print-platform-edit-product&product_id=' . $productId . '&tab=' . $slug);
            echo '<a class="' . esc_attr($slug === $tab ? 'pp-tab is-active' : 'pp-tab') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        if ($tab !== 'product-information') {
            echo '<div class="pp-card"><h2>' . esc_html($tabs[$tab] ?? 'Tab') . '</h2><p>' . esc_html__('This section is available in the next phase.', 'print-platform') . '</p></div></div>';
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
            $sizes = $this->get_sizes_for_set((int) $set['id']);
            $sizesBySet[(int) $set['id']] = array_map(static fn($s) => ['id' => (int) $s['id'], 'label' => $s['label']], $sizes);
        }

        echo '<div class="pp-grid">';

        echo '<section class="pp-card"><h2>BASIC INFORMATION</h2>';
        $this->field_text('CMS PageLink', 'pp_cms_pagelink', $meta('_pp_cms_pagelink'));
        $this->field_text('Name', 'post_title', $product->get_name());
        $this->field_textarea('Description', 'post_content', $product->get_description());
        $this->field_select('Product Type', 'pp_product_type', $meta('_pp_product_type', 'standard_template'), ['standard_template' => 'Standard Template', 'static' => 'Static', 'blank' => 'Blank']);

        echo '<p><label>Category<br/><select name="pp_category" id="pp_category" class="regular-text"><option value="0">—</option>';
        foreach ($categories as $cat) {
            echo '<option value="' . esc_attr((string) $cat->term_id) . '" ' . selected($currentCatId, (int) $cat->term_id, false) . '>' . esc_html($cat->name) . '</option>';
        }
        echo '</select></label></p>';

        $this->field_text('Vendor', 'pp_vendor', $meta('_pp_vendor'));
        $this->field_text('Hot Folder', 'pp_hot_folder', $meta('_pp_hot_folder'));
        $this->field_checkbox('Global Product', 'pp_global_product', $meta('_pp_global_product') === '1');
        $this->field_checkbox('Published', 'pp_published', get_post_status($productId) === 'publish');
        echo '</section>';

        echo '<section class="pp-card"><h2>PRICE MAPPING</h2>';
        echo '<p><label>Base Price<br/><select name="pp_base_price_set_id" id="pp_base_price_set_id" class="regular-text"></select></label></p>';
        echo '<p><label>Size<br/><select name="pp_size_id" id="pp_size_id" class="regular-text"></select></label></p>';
        echo '<p class="description" id="pp_price_mapping_notice"></p>';
        echo '</section>';

        echo '<section class="pp-card"><h2>PRODUCT NUMBERS</h2>';
        $this->field_text('Item Number', 'pp_item_number', $meta('_pp_item_number'));
        $this->field_text('Model Number', 'pp_model_number', $meta('_pp_model_number'));
        $this->field_text('Integration', 'pp_integration', $meta('_pp_integration'));
        echo '</section>';

        echo '</div></form>';

        echo '<script>window.ppSetsByCategory=' . wp_json_encode($setsByCategory) . ';window.ppSizesBySet=' . wp_json_encode($sizesBySet) . ';window.ppSelectedSetId=' . (int) $selectedSetId . ';window.ppSelectedSizeId=' . (int) $selectedSizeId . ';</script>';
        echo '<script>(function(){const cat=document.getElementById("pp_category"),setSel=document.getElementById("pp_base_price_set_id"),sizeSel=document.getElementById("pp_size_id"),n=document.getElementById("pp_price_mapping_notice");if(!cat||!setSel||!sizeSel){return;}function renderSets(){const c=parseInt(cat.value||"0",10);const sets=(window.ppSetsByCategory&&window.ppSetsByCategory[c])||[];setSel.innerHTML="";if(!c){const o=document.createElement("option");o.value="";o.textContent="Select a category first";setSel.appendChild(o);sizeSel.innerHTML="";n.textContent="Select a category to load base price sets.";return;}if(!sets.length){const o=document.createElement("option");o.value="";o.textContent="No base price sets for this category";setSel.appendChild(o);sizeSel.innerHTML="";n.textContent="Create a Base Price Set for this category.";return;}n.textContent="";sets.forEach(function(s){const o=document.createElement("option");o.value=s.id;o.textContent=s.name;if(parseInt(window.ppSelectedSetId||0,10)===parseInt(s.id,10)){o.selected=true;}setSel.appendChild(o);});renderSizes();}
function renderSizes(){const sid=parseInt(setSel.value||"0",10);const sizes=(window.ppSizesBySet&&window.ppSizesBySet[sid])||[];sizeSel.innerHTML="";if(!sid){const o=document.createElement("option");o.value="";o.textContent="Select base price first";sizeSel.appendChild(o);return;}if(!sizes.length){const o=document.createElement("option");o.value="";o.textContent="No sizes for selected base price";sizeSel.appendChild(o);return;}sizes.forEach(function(s){const o=document.createElement("option");o.value=s.id;o.textContent=s.label;if(parseInt(window.ppSelectedSizeId||0,10)===parseInt(s.id,10)){o.selected=true;}sizeSel.appendChild(o);});}
cat.addEventListener("change",function(){window.ppSelectedSetId=0;window.ppSelectedSizeId=0;renderSets();});setSel.addEventListener("change",function(){window.ppSelectedSizeId=0;renderSizes();});renderSets();})();</script>';

        echo '</div>';
    }

    private function get_base_price_sets(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_base_price_sets';
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A) ?: [];
    }

    private function get_base_price_set(int $id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_base_price_sets';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
        return $row ?: null;
    }

    private function get_sizes_for_set(int $setId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_base_price_sizes';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE base_price_set_id=%d ORDER BY id ASC", $setId), ARRAY_A) ?: [];
    }

    private function count_sizes(int $setId): int {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_base_price_sizes';
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE base_price_set_id=%d", $setId));
    }

    public function handle_save_base_price_set(): void {
        $this->must_manage();
        check_admin_referer('pp_save_base_price_set');

        global $wpdb;
        $table = $wpdb->prefix . 'pp_base_price_sets';

        $id = absint($_POST['id'] ?? 0);
        $data = [
            'name' => sanitize_text_field((string) ($_POST['name'] ?? '')),
            'category_term_id' => absint($_POST['category_term_id'] ?? 0),
            'pricing_mode' => sanitize_text_field((string) ($_POST['pricing_mode'] ?? 'LUPI')),
            'updated_at' => current_time('mysql'),
        ];

        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
            $id = (int) $wpdb->insert_id;
        }

        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $id));
        exit;
    }

    public function handle_delete_base_price_set(): void {
        $this->must_manage();
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('pp_delete_base_price_set_' . $id);

        global $wpdb;
        $sets = $wpdb->prefix . 'pp_base_price_sets';
        $sizes = $wpdb->prefix . 'pp_base_price_sizes';

        $wpdb->delete($sizes, ['base_price_set_id' => $id]);
        $wpdb->delete($sets, ['id' => $id]);

        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets'));
        exit;
    }

    public function handle_save_base_price_size(): void {
        $this->must_manage();
        check_admin_referer('pp_save_base_price_size');

        global $wpdb;
        $table = $wpdb->prefix . 'pp_base_price_sizes';
        $setId = absint($_POST['base_price_set_id'] ?? 0);

        $wpdb->insert($table, [
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

        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $setId));
        exit;
    }

    public function handle_delete_base_price_size(): void {
        $this->must_manage();
        $id = absint($_POST['id'] ?? 0);
        $setId = absint($_POST['base_price_set_id'] ?? 0);
        check_admin_referer('pp_delete_base_price_size_' . $id);

        global $wpdb;
        $table = $wpdb->prefix . 'pp_base_price_sizes';
        $wpdb->delete($table, ['id' => $id]);

        wp_safe_redirect(admin_url('admin.php?page=print-platform-base-price-sets&view=edit&id=' . $setId));
        exit;
    }

    private function field_text(string $label, string $name, string $value): void {
        echo '<p><label>' . esc_html($label) . '<br/><input class="regular-text" type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"/></label></p>';
    }

    private function field_textarea(string $label, string $name, string $value): void {
        echo '<p><label>' . esc_html($label) . '<br/><textarea class="large-text" rows="4" name="' . esc_attr($name) . '">' . esc_textarea($value) . '</textarea></label></p>';
    }

    private function field_checkbox(string $label, string $name, bool $checked): void {
        echo '<p><label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . '/> ' . esc_html($label) . '</label></p>';
    }

    private function field_select(string $label, string $name, string $value, array $options): void {
        echo '<p><label>' . esc_html($label) . '<br/><select name="' . esc_attr($name) . '" class="regular-text">';
        foreach ($options as $k => $v) {
            echo '<option value="' . esc_attr((string) $k) . '" ' . selected($value, (string) $k, false) . '>' . esc_html((string) $v) . '</option>';
        }
        echo '</select></label></p>';
    }

    public function handle_add_product(): void {
        $this->must_manage();
        check_admin_referer('pp_add_product');

        $id = wp_insert_post(['post_type' => 'product', 'post_status' => 'draft', 'post_title' => 'New Print Product']);
        if ($id) {
            update_post_meta($id, '_pp_is_print_product', '1');
            update_post_meta($id, '_pp_published', '0');
            update_post_meta($id, '_pp_last_saved', current_time('mysql'));
        }

        wp_safe_redirect(admin_url('admin.php?page=print-platform-edit-product&product_id=' . absint((int) $id)));
        exit;
    }

    public function handle_toggle_published(): void {
        $this->must_manage();
        $id = absint($_POST['product_id'] ?? 0);
        check_admin_referer('pp_toggle_published_' . $id);

        $published = ! empty($_POST['published']);
        wp_update_post(['ID' => $id, 'post_status' => $published ? 'publish' : 'draft']);
        update_post_meta($id, '_pp_published', $published ? '1' : '0');
        update_post_meta($id, '_pp_last_saved', current_time('mysql'));

        wp_safe_redirect(admin_url('admin.php?page=print-platform-products'));
        exit;
    }

    public function handle_duplicate_product(): void {
        $this->must_manage();
        $id = absint($_POST['product_id'] ?? 0);
        check_admin_referer('pp_duplicate_' . $id);

        $post = get_post($id);
        if (! $post || $post->post_type !== 'product') {
            wp_safe_redirect(admin_url('admin.php?page=print-platform-products'));
            exit;
        }

        $newId = wp_insert_post(['post_type' => 'product', 'post_status' => 'draft', 'post_title' => $post->post_title . ' (Copy)', 'post_content' => $post->post_content]);
        if ($newId) {
            $allMeta = get_post_meta($id);
            foreach ($allMeta as $key => $values) {
                foreach ($values as $value) {
                    add_post_meta($newId, $key, maybe_unserialize($value));
                }
            }
            update_post_meta($newId, '_pp_last_saved', current_time('mysql'));
            wp_set_object_terms($newId, wp_get_post_terms($id, 'product_cat', ['fields' => 'ids']), 'product_cat');
        }

        wp_safe_redirect(admin_url('admin.php?page=print-platform-products'));
        exit;
    }

    public function handle_delete_product(): void {
        $this->must_manage();
        $id = absint($_POST['product_id'] ?? 0);
        check_admin_referer('pp_delete_' . $id);

        wp_delete_post($id, true);
        wp_safe_redirect(admin_url('admin.php?page=print-platform-products'));
        exit;
    }

    public function handle_save_product(): void {
        $this->must_manage();
        $id = absint($_POST['product_id'] ?? 0);
        check_admin_referer('pp_save_product_' . $id);

        wp_update_post([
            'ID' => $id,
            'post_title' => sanitize_text_field((string) ($_POST['post_title'] ?? '')),
            'post_content' => wp_kses_post((string) ($_POST['post_content'] ?? '')),
            'post_status' => ! empty($_POST['pp_published']) ? 'publish' : 'draft',
        ]);

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
        foreach ($metaMap as $key => $value) {
            update_post_meta($id, $key, $value);
        }

        $catId = absint($_POST['pp_category'] ?? 0);
        if ($catId > 0) {
            wp_set_object_terms($id, [$catId], 'product_cat');
        }

        wp_safe_redirect(admin_url('admin.php?page=print-platform-edit-product&product_id=' . $id . '&tab=product-information&saved=1'));
        exit;
    }

    public function handle_export_csv(): void {
        $this->must_manage();
        check_admin_referer('pp_export_csv');

        $posts = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft'],
            'numberposts' => -1,
            'meta_key' => '_pp_is_print_product',
            'meta_value' => '1',
        ]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=print-platform-products.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Name', 'Published', 'Category', 'Base Price Set ID', 'Size ID', 'Vendor', 'Item Number', 'Model Number', 'Integration', 'CMS PageLink']);

        foreach ($posts as $post) {
            $cat = wp_get_post_terms($post->ID, 'product_cat', ['fields' => 'names']);
            fputcsv($out, [
                $post->ID,
                $post->post_title,
                get_post_status($post->ID) === 'publish' ? 1 : 0,
                $cat[0] ?? '',
                get_post_meta($post->ID, '_pp_base_price_set_id', true),
                get_post_meta($post->ID, '_pp_size_id', true),
                get_post_meta($post->ID, '_pp_vendor', true),
                get_post_meta($post->ID, '_pp_item_number', true),
                get_post_meta($post->ID, '_pp_model_number', true),
                get_post_meta($post->ID, '_pp_integration', true),
                get_post_meta($post->ID, '_pp_cms_pagelink', true),
            ]);
        }

        fclose($out);
        exit;
    }

    public function handle_import_csv(): void {
        $this->must_manage();
        check_admin_referer('pp_import_csv');

        if (empty($_FILES['pp_csv']['tmp_name'])) {
            wp_safe_redirect(admin_url('admin.php?page=print-platform-products'));
            exit;
        }

        $fh = fopen($_FILES['pp_csv']['tmp_name'], 'r');
        if (! $fh) {
            wp_safe_redirect(admin_url('admin.php?page=print-platform-products'));
            exit;
        }

        $header = fgetcsv($fh);
        $index = array_flip($header ?: []);

        while (($row = fgetcsv($fh)) !== false) {
            $id = absint($row[$index['ID']] ?? 0);
            $name = sanitize_text_field((string) ($row[$index['Name']] ?? 'Imported Product'));
            $published = ! empty($row[$index['Published']] ?? '');

            if ($id && get_post($id)) {
                wp_update_post(['ID' => $id, 'post_title' => $name, 'post_status' => $published ? 'publish' : 'draft']);
            } else {
                $id = wp_insert_post(['post_type' => 'product', 'post_status' => $published ? 'publish' : 'draft', 'post_title' => $name]);
            }
            if (! $id) {
                continue;
            }

            update_post_meta($id, '_pp_is_print_product', '1');
            update_post_meta($id, '_pp_published', $published ? '1' : '0');
            update_post_meta($id, '_pp_base_price_set_id', absint($row[$index['Base Price Set ID']] ?? 0));
            update_post_meta($id, '_pp_size_id', absint($row[$index['Size ID']] ?? 0));
            update_post_meta($id, '_pp_vendor', sanitize_text_field((string) ($row[$index['Vendor']] ?? '')));
            update_post_meta($id, '_pp_item_number', sanitize_text_field((string) ($row[$index['Item Number']] ?? '')));
            update_post_meta($id, '_pp_model_number', sanitize_text_field((string) ($row[$index['Model Number']] ?? '')));
            update_post_meta($id, '_pp_integration', sanitize_text_field((string) ($row[$index['Integration']] ?? '')));
            update_post_meta($id, '_pp_cms_pagelink', sanitize_text_field((string) ($row[$index['CMS PageLink']] ?? '')));
            update_post_meta($id, '_pp_last_saved', current_time('mysql'));

            $catName = sanitize_text_field((string) ($row[$index['Category']] ?? ''));
            if ($catName !== '') {
                $term = term_exists($catName, 'product_cat');
                if (! $term) {
                    $term = wp_insert_term($catName, 'product_cat');
                }
                if (is_array($term) && ! empty($term['term_id'])) {
                    wp_set_object_terms($id, [(int) $term['term_id']], 'product_cat');
                }
            }
        }

        fclose($fh);
        wp_safe_redirect(admin_url('admin.php?page=print-platform-products'));
        exit;
    }

    private function must_manage(): void {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Unauthorized.', 'print-platform'));
        }
    }
}

register_activation_hook(__FILE__, ['Print_Platform_Plugin', 'activate']);
new Print_Platform_Plugin();
