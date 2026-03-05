<?php
/**
 * Plugin Name: Print Platform
 * Description: PrintNow-style WooCommerce admin product manager for print products.
 * Version: 1.0.0
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Text Domain: print-platform
 */

defined('ABSPATH') || exit;

final class Print_Platform_Plugin {
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
            null,
            __('Edit Print Product', 'print-platform'),
            __('Edit Print Product', 'print-platform'),
            'manage_woocommerce',
            'print-platform-edit-product',
            [$this, 'render_edit_page']
        );
    }

    public function enqueue_assets(string $hook): void {
        if (! in_array($hook, ['woocommerce_page_print-platform-products', 'admin_page_print-platform-edit-product'], true)) {
            return;
        }

        wp_enqueue_style(
            'print-platform-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            '1.0.0'
        );
    }

    public function render_products_page(): void {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Unauthorized.', 'print-platform'));
        }

        $searchId = isset($_GET['pp_search_id']) ? absint($_GET['pp_search_id']) : 0;
        $args = [
            'post_type' => 'product',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => 200,
            'meta_query' => [
                [
                    'key' => '_pp_is_print_product',
                    'value' => '1',
                ],
            ],
        ];

        if ($searchId) {
            $args['post__in'] = [$searchId];
        }

        $query = new WP_Query($args);

        echo '<div class="wrap pp-wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Print Platform – Products', 'print-platform') . '</h1>';
        echo '<hr class="wp-header-end" />';

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
        echo '<input type="hidden" name="action" value="pp_import_csv" />';
        echo '<input type="file" name="pp_csv" accept=".csv" required /> ';
        submit_button(__('Import', 'print-platform'), 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="print-platform-products" />';
        echo '<input type="number" name="pp_search_id" placeholder="' . esc_attr__('Search by Product ID', 'print-platform') . '" value="' . esc_attr((string) $searchId) . '" /> ';
        submit_button(__('Search', 'print-platform'), 'secondary', 'submit', false);
        echo '</form>';
        echo '</div>';

        echo '<table class="widefat striped pp-table"><thead><tr>';
        echo '<th>ID</th><th>Thumbnail</th><th>Sort</th><th>Name</th><th>Last Saved</th><th>Published</th><th>Action</th>';
        echo '</tr></thead><tbody>';

        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (! $product) {
                continue;
            }

            $editUrl = admin_url('admin.php?page=print-platform-edit-product&product_id=' . $post->ID);
            $lastSaved = (string) get_post_meta($post->ID, '_pp_last_saved', true);
            $published = $post->post_status === 'publish';
            $thumb = get_the_post_thumbnail($post->ID, [40, 40]) ?: '—';

            echo '<tr>';
            echo '<td>' . esc_html((string) $post->ID) . '</td>';
            echo '<td>' . $thumb . '</td>';
            echo '<td>' . esc_html((string) $post->menu_order) . '</td>';
            echo '<td><a href="' . esc_url($editUrl) . '">' . esc_html($post->post_title ?: '(no title)') . '</a></td>';
            echo '<td>' . esc_html($lastSaved ?: '—') . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pp_toggle_published_' . $post->ID);
            echo '<input type="hidden" name="action" value="pp_toggle_published" />';
            echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $post->ID) . '" />';
            echo '<label><input type="checkbox" name="published" value="1" ' . checked($published, true, false) . ' onchange="this.form.submit()"/> ' . esc_html__('Published', 'print-platform') . '</label>';
            echo '</form>';
            echo '</td>';
            echo '<td>';
            echo '<details><summary>⋮</summary><div class="pp-actions">';
            echo '<a href="' . esc_url($editUrl) . '">' . esc_html__('Edit', 'print-platform') . '</a>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pp_duplicate_' . $post->ID);
            echo '<input type="hidden" name="action" value="pp_duplicate_product" />';
            echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $post->ID) . '" />';
            submit_button(__('Duplicate', 'print-platform'), 'link', 'submit', false);
            echo '</form>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(__('Delete this product?', 'print-platform')) . '\');">';
            wp_nonce_field('pp_delete_' . $post->ID);
            echo '<input type="hidden" name="action" value="pp_delete_product" />';
            echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $post->ID) . '" />';
            submit_button(__('Delete', 'print-platform'), 'link-delete', 'submit', false);
            echo '</form>';

            echo '</div></details>';
            echo '</td>';
            echo '</tr>';
        }

        if (empty($query->posts)) {
            echo '<tr><td colspan="7">' . esc_html__('No print products found.', 'print-platform') . '</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function render_edit_page(): void {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Unauthorized.', 'print-platform'));
        }

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
            echo '<input type="hidden" name="action" value="pp_save_product" />';
            echo '<input type="hidden" name="product_id" value="' . esc_attr((string) $productId) . '" />';
            submit_button(__('Save', 'print-platform'), 'primary', 'submit', false, ['style' => 'margin:0']);
            echo '</div>';
        } else {
            echo '</div>';
        }

        echo '<nav class="pp-tabs">';
        foreach ($tabs as $slug => $label) {
            $url = admin_url('admin.php?page=print-platform-edit-product&product_id=' . $productId . '&tab=' . $slug);
            $class = $slug === $tab ? 'pp-tab is-active' : 'pp-tab';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        if ($tab !== 'product-information') {
            echo '<div class="pp-card"><h2>' . esc_html($tabs[$tab] ?? 'Tab') . '</h2><p>' . esc_html__('This section is available in the next phase.', 'print-platform') . '</p></div>';
            echo '</div>';
            return;
        }

        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $currentCat = wp_get_post_terms($productId, 'product_cat', ['fields' => 'ids']);
        $currentCatId = $currentCat[0] ?? 0;

        $basePriceSets = get_option('pp_base_price_sets', [
            ['id' => 'standard', 'name' => 'Standard Price Set', 'sizes' => ['A4', 'A5', 'Letter']],
            ['id' => 'large', 'name' => 'Large Format Set', 'sizes' => ['A2', 'A1', 'A0']],
        ]);

        echo '<div class="pp-grid">';

        echo '<section class="pp-card"><h2>BASIC INFORMATION</h2>';
        $this->field_text('CMS PageLink', 'pp_cms_pagelink', $meta('_pp_cms_pagelink'));
        $this->field_text('Name', 'post_title', $product->get_name());
        $this->field_textarea('Description', 'post_content', $product->get_description());
        $this->field_select('Product Type', 'pp_product_type', $meta('_pp_product_type', 'standard_template'), [
            'standard_template' => 'Standard Template',
            'static' => 'Static',
            'blank' => 'Blank',
        ]);

        echo '<p><label>Category<br/><select name="pp_category" class="regular-text">';
        echo '<option value="0">—</option>';
        foreach ($categories as $cat) {
            echo '<option value="' . esc_attr((string) $cat->term_id) . '" ' . selected((int) $currentCatId, (int) $cat->term_id, false) . '>' . esc_html($cat->name) . '</option>';
        }
        echo '</select></label></p>';

        $this->field_text('Vendor', 'pp_vendor', $meta('_pp_vendor'));
        $this->field_text('Hot Folder', 'pp_hot_folder', $meta('_pp_hot_folder'));
        $this->field_checkbox('Global Product', 'pp_global_product', $meta('_pp_global_product') === '1');
        $this->field_checkbox('Published', 'pp_published', get_post_status($productId) === 'publish');
        echo '</section>';

        echo '<section class="pp-card"><h2>PRICE MAPPING</h2>';
        echo '<p><label>Base Price<br/><select name="pp_base_price" id="pp_base_price" class="regular-text">';
        $currentBase = $meta('_pp_base_price', 'standard');
        foreach ($basePriceSets as $set) {
            echo '<option value="' . esc_attr((string) $set['id']) . '" ' . selected($currentBase, (string) $set['id'], false) . '>' . esc_html((string) $set['name']) . '</option>';
        }
        echo '</select></label></p>';

        echo '<p><label>Size<br/><select name="pp_size" id="pp_size" class="regular-text"></select></label></p>';
        echo '</section>';

        echo '<section class="pp-card"><h2>PRODUCT NUMBERS</h2>';
        $this->field_text('Item Number', 'pp_item_number', $meta('_pp_item_number'));
        $this->field_text('Model Number', 'pp_model_number', $meta('_pp_model_number'));
        $this->field_text('Integration', 'pp_integration', $meta('_pp_integration'));
        echo '</section>';

        echo '</div>';
        echo '</form>';

        $sizesMap = [];
        foreach ($basePriceSets as $set) {
            $sizesMap[$set['id']] = $set['sizes'] ?? [];
        }
        $currentSize = $meta('_pp_size', '');
        echo '<script>window.ppSizesMap=' . wp_json_encode($sizesMap) . ';window.ppCurrentSize=' . wp_json_encode($currentSize) . ';</script>';
        echo '<script>(function(){const b=document.getElementById("pp_base_price"),s=document.getElementById("pp_size");function r(){const arr=(window.ppSizesMap&&window.ppSizesMap[b.value])||[];s.innerHTML="";arr.forEach(function(v){const o=document.createElement("option");o.value=v;o.textContent=v;if(v===window.ppCurrentSize){o.selected=true;}s.appendChild(o);});}if(b&&s){b.addEventListener("change",r);r();}})();</script>';

        echo '</div>';
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

        $id = wp_insert_post([
            'post_type' => 'product',
            'post_status' => 'draft',
            'post_title' => 'New Print Product',
        ]);

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

        $newId = wp_insert_post([
            'post_type' => 'product',
            'post_status' => 'draft',
            'post_title' => $post->post_title . ' (Copy)',
            'post_content' => $post->post_content,
        ]);

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
            '_pp_base_price' => sanitize_text_field((string) ($_POST['pp_base_price'] ?? '')),
            '_pp_size' => sanitize_text_field((string) ($_POST['pp_size'] ?? '')),
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
        fputcsv($out, ['ID', 'Name', 'Published', 'Category', 'Base Price', 'Size', 'Vendor', 'Item Number', 'Model Number', 'Integration', 'CMS PageLink']);

        foreach ($posts as $post) {
            $cat = wp_get_post_terms($post->ID, 'product_cat', ['fields' => 'names']);
            fputcsv($out, [
                $post->ID,
                $post->post_title,
                get_post_status($post->ID) === 'publish' ? 1 : 0,
                $cat[0] ?? '',
                get_post_meta($post->ID, '_pp_base_price', true),
                get_post_meta($post->ID, '_pp_size', true),
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
                wp_update_post([
                    'ID' => $id,
                    'post_title' => $name,
                    'post_status' => $published ? 'publish' : 'draft',
                ]);
            } else {
                $id = wp_insert_post([
                    'post_type' => 'product',
                    'post_status' => $published ? 'publish' : 'draft',
                    'post_title' => $name,
                ]);
            }

            if (! $id) {
                continue;
            }

            update_post_meta($id, '_pp_is_print_product', '1');
            update_post_meta($id, '_pp_published', $published ? '1' : '0');
            update_post_meta($id, '_pp_base_price', sanitize_text_field((string) ($row[$index['Base Price']] ?? '')));
            update_post_meta($id, '_pp_size', sanitize_text_field((string) ($row[$index['Size']] ?? '')));
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

new Print_Platform_Plugin();
