<?php
/**
 * موجودی محصولات فروشگاه به تفکیک شعبه + استعلام ادمین/منشی
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return string
 */
function sc_product_branch_stock_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_product_branch_stock';
}

/**
 * @param bool $reset
 * @return bool
 */
function sc_product_branch_stock_table_exists($reset = false) {
    global $wpdb;
    static $exists = null;
    if ($reset) {
        $exists = null;
    }
    if ($exists !== null) {
        return $exists;
    }
    $table = sc_product_branch_stock_table();
    $exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
    return $exists;
}

/**
 * اطمینان از وجود جدول
 */
function sc_product_branch_stock_ensure_table() {
    if (!sc_product_branch_stock_table_exists() && function_exists('sc_create_product_branch_stock_table')) {
        sc_create_product_branch_stock_table();
        sc_product_branch_stock_table_exists(true);
    }
    if (get_option('sc_product_branch_stock_v1', '0') !== '1') {
        update_option('sc_product_branch_stock_v1', '1');
    }
}

/**
 * @return string[]
 */
function sc_product_branch_stock_get_chapter_names() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_chapter_categories';
    $names = [];

    // کوئری مستقیم — بدون وابستگی به helperهای دیگر
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- نام جدول از prefix
    $rows = $wpdb->get_col("SELECT name FROM `{$table}` WHERE TRIM(name) <> '' ORDER BY name ASC");
    if (is_array($rows) && !empty($rows)) {
        $names = $rows;
    } elseif (function_exists('sc_secretary_get_all_chapter_names')) {
        $names = sc_secretary_get_all_chapter_names();
    }

    $out = [];
    foreach ((array) $names as $n) {
        $n = trim(wp_strip_all_tags((string) $n));
        if ($n !== '') {
            $out[$n] = $n;
        }
    }
    return array_values($out);
}

/**
 * آیا کاربر می‌تواند استعلام موجودی ببیند؟
 */
function sc_user_can_product_stock_inquiry() {
    return current_user_can('manage_woocommerce');
}

/**
 * @param int $product_id
 * @return array<string,int> chapter_name => qty
 */
function sc_get_product_branch_stock_map($product_id) {
    global $wpdb;
    $product_id = absint($product_id);
    if (!$product_id || !sc_product_branch_stock_table_exists()) {
        return [];
    }
    $table = sc_product_branch_stock_table();
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT chapter_name, qty FROM `$table` WHERE product_id = %d ORDER BY chapter_name ASC",
        $product_id
    ));
    $map = [];
    foreach ((array) $rows as $row) {
        $name = trim((string) $row->chapter_name);
        if ($name !== '') {
            $map[$name] = (int) $row->qty;
        }
    }
    return $map;
}

/**
 * @param int $product_id
 * @param string $chapter_name
 * @return int
 */
function sc_get_product_branch_qty($product_id, $chapter_name) {
    $map = sc_get_product_branch_stock_map($product_id);
    $chapter_name = trim((string) $chapter_name);
    return isset($map[$chapter_name]) ? (int) $map[$chapter_name] : 0;
}

/**
 * آیا برای این محصول/variation ردیف موجودی شعبه تعریف شده؟
 *
 * @param int $product_id
 */
function sc_product_has_branch_stock($product_id) {
    global $wpdb;
    $product_id = absint($product_id);
    if (!$product_id || !sc_product_branch_stock_table_exists()) {
        return false;
    }
    $table = sc_product_branch_stock_table();
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `$table` WHERE product_id = %d",
        $product_id
    ));
    return $count > 0;
}

/**
 * مجموع موجودی شعبه‌ها
 *
 * @param int $product_id
 */
function sc_get_product_branch_stock_total($product_id) {
    global $wpdb;
    $product_id = absint($product_id);
    if (!$product_id || !sc_product_branch_stock_table_exists()) {
        return 0;
    }
    $table = sc_product_branch_stock_table();
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(qty), 0) FROM `$table` WHERE product_id = %d",
        $product_id
    ));
}

/**
 * ذخیره نقشه موجودی شعبه‌ها برای یک محصول/variation
 *
 * @param int $product_id
 * @param array<string,int|string> $stock_map chapter => qty (فقط شعبه‌های با qty >= 0 و تیک‌خورده)
 * @param int $parent_id
 */
function sc_save_product_branch_stock($product_id, array $stock_map, $parent_id = 0) {
    global $wpdb;
    sc_product_branch_stock_ensure_table();
    if (!sc_product_branch_stock_table_exists()) {
        return false;
    }

    $product_id = absint($product_id);
    $parent_id = absint($parent_id);
    if (!$product_id) {
        return false;
    }

    $table = sc_product_branch_stock_table();
    $now = current_time('mysql');
    $valid_chapters = sc_product_branch_stock_get_chapter_names();
    $valid_lookup = array_fill_keys($valid_chapters, true);

    $clean = [];
    foreach ($stock_map as $chapter => $qty) {
        $chapter = trim(sanitize_text_field((string) $chapter));
        if ($chapter === '' || empty($valid_lookup[$chapter])) {
            continue;
        }
        $qty = (int) $qty;
        if ($qty < 0) {
            $qty = 0;
        }
        $clean[$chapter] = $qty;
    }

    $wpdb->delete($table, ['product_id' => $product_id], ['%d']);

    foreach ($clean as $chapter => $qty) {
        $wpdb->insert(
            $table,
            [
                'product_id' => $product_id,
                'parent_id' => $parent_id,
                'chapter_name' => $chapter,
                'qty' => $qty,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%s', '%d', '%s', '%s']
        );
    }

    sc_sync_wc_stock_from_branch_total($product_id);
    return true;
}

/**
 * همگام‌سازی موجودی کلی ووکامرس با مجموع شعبه‌ها (اگر ردیف شعبه داشته باشد)
 *
 * @param int $product_id
 */
function sc_sync_wc_stock_from_branch_total($product_id) {
    if (!function_exists('wc_get_product')) {
        return;
    }
    $product_id = absint($product_id);
    $product = wc_get_product($product_id);
    if (!$product) {
        return;
    }
    if (!sc_product_has_branch_stock($product_id)) {
        return;
    }
    $total = sc_get_product_branch_stock_total($product_id);
    $product->set_manage_stock(true);
    $product->set_stock_quantity($total);
    $product->set_stock_status($total > 0 ? 'instock' : 'outofstock');
    $product->save();
}

/**
 * کم/زیاد کردن موجودی یک شعبه
 *
 * @param int $product_id
 * @param string $chapter_name
 * @param int $delta منفی = کاهش
 * @return bool|WP_Error
 */
function sc_adjust_product_branch_stock($product_id, $chapter_name, $delta) {
    global $wpdb;
    $product_id = absint($product_id);
    $chapter_name = trim(sanitize_text_field((string) $chapter_name));
    $delta = (int) $delta;
    if (!$product_id || $chapter_name === '' || $delta === 0) {
        return false;
    }
    if (!sc_product_branch_stock_table_exists()) {
        return new WP_Error('no_table', 'جدول موجودی شعبه موجود نیست.');
    }

    $table = sc_product_branch_stock_table();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, qty, parent_id FROM `$table` WHERE product_id = %d AND chapter_name = %s LIMIT 1",
        $product_id,
        $chapter_name
    ));
    if (!$row) {
        return new WP_Error('no_row', 'موجودی این شعبه برای محصول تعریف نشده است.');
    }

    $new_qty = (int) $row->qty + $delta;
    if ($new_qty < 0) {
        return new WP_Error('insufficient', 'موجودی شعبه کافی نیست.');
    }

    $wpdb->update(
        $table,
        [
            'qty' => $new_qty,
            'updated_at' => current_time('mysql'),
        ],
        ['id' => (int) $row->id],
        ['%d', '%s'],
        ['%d']
    );

    sc_sync_wc_stock_from_branch_total($product_id);
    return true;
}

/**
 * رندر جدول موجودی شعبه‌ها در ادمین محصول
 *
 * @param int $product_id
 * @param string $field_name_prefix مثلا sc_branch_stock یا sc_branch_stock_var[0]
 * @param string $enable_field_name نام فیلد فعال‌سازی
 * @param string $css_class
 */
function sc_render_product_branch_stock_fields($product_id, $field_name_prefix, $enable_field_name = 'sc_branch_stock_enable', $css_class = '') {
    $chapters = sc_product_branch_stock_get_chapter_names();
    $map = sc_get_product_branch_stock_map($product_id);
    // اگر قبلاً ذخیره شده فعال بماند؛ در غیر این صورت وقتی شعبه وجود دارد پیش‌فرض فعال
    $enabled = !empty($map) || !empty($chapters);

    echo '<div class="sc-pbs-admin ' . esc_attr($css_class) . '">';
    echo '<input type="hidden" name="' . esc_attr($enable_field_name) . '" value="0" />';
    echo '<p class="sc-pbs-admin-intro"><label><input type="checkbox" class="sc-pbs-enable" name="' . esc_attr($enable_field_name) . '" value="1" ' . checked($enabled, true, false) . ' /> ';
    echo esc_html__('مدیریت موجودی به تفکیک شعبه', 'sportclub-manager') . '</label></p>';

    if (empty($chapters)) {
        $chapter_url = admin_url('admin.php?page=sc_chapter');
        echo '<p class="description sc-pbs-admin-empty">';
        echo esc_html__('هنوز شعبه‌ای در باشگاه تعریف نشده است.', 'sportclub-manager');
        echo ' <a href="' . esc_url($chapter_url) . '">' . esc_html__('افزودن شعبه', 'sportclub-manager') . '</a>';
        echo '</p></div>';
        return;
    }

    // همیشه لیست شعبه‌ها را نشان بده (حتی قبل از فعال‌سازی)
    echo '<div class="sc-pbs-admin-rows">';
    echo '<table class="widefat sc-pbs-admin-table"><thead><tr>';
    echo '<th>' . esc_html__('شعبه', 'sportclub-manager') . '</th>';
    echo '<th>' . esc_html__('فعال در شعبه', 'sportclub-manager') . '</th>';
    echo '<th>' . esc_html__('موجودی', 'sportclub-manager') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($chapters as $chapter) {
        $has = array_key_exists($chapter, $map);
        $qty = $has ? (int) $map[$chapter] : 0;
        // از ایندکس عددی امن برای نام فیلد استفاده می‌کنیم تا نام فارسی شعبه در name خراب نشود
        $idx = rawurlencode($chapter);
        $name_base = $field_name_prefix . '[' . $idx . ']';
        echo '<tr class="sc-pbs-admin-row">';
        echo '<td><strong>' . esc_html($chapter) . '</strong>';
        echo '<input type="hidden" name="' . esc_attr($name_base) . '[chapter]" value="' . esc_attr($chapter) . '" />';
        echo '</td>';
        echo '<td><input type="checkbox" class="sc-pbs-chapter-on" name="' . esc_attr($name_base) . '[on]" value="1" ' . checked($has, true, false) . ' /></td>';
        echo '<td><input type="number" class="sc-pbs-qty short" min="0" step="1" name="' . esc_attr($name_base) . '[qty]" value="' . esc_attr((string) $qty) . '" /></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<p class="description">' . esc_html__('شعبه‌های موردنظر را تیک بزنید و موجودی را وارد کنید. موجودی کلی ووکامرس با مجموع شعبه‌ها همگام می‌شود.', 'sportclub-manager') . '</p>';
    echo '</div></div>';
}

/**
 * پارس ورودی فرم موجودی شعبه
 *
 * @param mixed $raw
 * @return array<string,int>
 */
function sc_parse_product_branch_stock_post($raw) {
    $out = [];
    if (!is_array($raw)) {
        return $out;
    }
    foreach ($raw as $key => $row) {
        if (!is_array($row)) {
            continue;
        }
        if (empty($row['on'])) {
            continue;
        }
        $chapter = '';
        if (!empty($row['chapter'])) {
            $chapter = trim(sanitize_text_field((string) $row['chapter']));
        } else {
            // سازگاری با کلید قدیمی (نام شعبه در ایندکس)
            $decoded = rawurldecode((string) $key);
            $chapter = trim(sanitize_text_field($decoded));
        }
        if ($chapter === '') {
            continue;
        }
        $out[$chapter] = isset($row['qty']) ? max(0, (int) $row['qty']) : 0;
    }
    return $out;
}

/* --------------------------------------------------------------------------
 * Product edit UI (simple + variable)
 * -------------------------------------------------------------------------- */

add_filter('woocommerce_product_data_tabs', 'sc_pbs_add_product_data_tab');
/**
 * @param array $tabs
 * @return array
 */
function sc_pbs_add_product_data_tab($tabs) {
    $tabs['sc_branch_stock'] = [
        'label' => __('موجودی شعبه‌ها', 'sportclub-manager'),
        'target' => 'sc_branch_stock_product_data',
        'class' => ['show_if_simple', 'show_if_variable'],
        'priority' => 25,
    ];
    return $tabs;
}

add_action('woocommerce_product_data_panels', 'sc_pbs_render_product_data_panel');
function sc_pbs_render_product_data_panel() {
    global $post;
    if (!$post || !current_user_can('edit_products')) {
        return;
    }
    $product = function_exists('wc_get_product') ? wc_get_product($post->ID) : null;
    $is_variable = $product && $product->is_type('variable');
    ?>
    <div id="sc_branch_stock_product_data" class="panel woocommerce_options_panel hidden">
        <div class="options_group">
            <?php if ($is_variable) : ?>
                <p class="form-field" style="padding:12px 12px 0;">
                    <strong><?php echo esc_html__('محصول متغیر', 'sportclub-manager'); ?></strong><br>
                    <span class="description"><?php echo esc_html__('برای محصول متغیر، موجودی هر شعبه را داخل هر variation (تب تغییرات) تنظیم کنید. جدول زیر فقط برای یادآوری است و ذخیره نمی‌شود.', 'sportclub-manager'); ?></span>
                </p>
                <div class="sc-pbs-admin" style="margin:12px;">
                    <?php
                    $chapters = sc_product_branch_stock_get_chapter_names();
                    if (empty($chapters)) {
                        echo '<p class="description">' . esc_html__('شعبه‌ای یافت نشد.', 'sportclub-manager') . ' <a href="' . esc_url(admin_url('admin.php?page=sc_chapter')) . '">' . esc_html__('مدیریت شعبه‌ها', 'sportclub-manager') . '</a></p>';
                    } else {
                        echo '<ul class="sc-pbs-chapter-list">';
                        foreach ($chapters as $ch) {
                            echo '<li>' . esc_html($ch) . '</li>';
                        }
                        echo '</ul>';
                    }
                    ?>
                </div>
            <?php else : ?>
                <p class="form-field" style="padding:12px 12px 0;">
                    <strong><?php echo esc_html__('موجودی به تفکیک شعبه', 'sportclub-manager'); ?></strong>
                </p>
                <div style="padding:0 12px 12px;">
                    <?php sc_render_product_branch_stock_fields((int) $post->ID, 'sc_branch_stock', 'sc_branch_stock_enable', 'sc-pbs-simple'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

add_action('woocommerce_product_options_inventory_product_data', 'sc_pbs_render_simple_product_fields');
function sc_pbs_render_simple_product_fields() {
    // فیلد اصلی در تب «موجودی شعبه‌ها» است؛ اینجا فقط یادآوری کوتاه
    echo '<div class="options_group sc-pbs-options-group show_if_simple">';
    echo '<p class="form-field"><span class="description">' . esc_html__('برای تنظیم موجودی هر شعبه به تب «موجودی شعبه‌ها» بروید.', 'sportclub-manager') . '</span></p>';
    echo '</div>';
}

add_action('woocommerce_process_product_meta', 'sc_pbs_save_simple_product_fields', 25, 1);
function sc_pbs_save_simple_product_fields($product_id) {
    $product_id = absint($product_id);
    if (!$product_id || !current_user_can('edit_product', $product_id)) {
        return;
    }
    $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
    if (!$product || $product->is_type('variable')) {
        return;
    }
    if (!isset($_POST['sc_branch_stock_enable'])) {
        return;
    }
    $enabled = !empty($_POST['sc_branch_stock_enable']);
    if (!$enabled) {
        sc_save_product_branch_stock($product_id, [], 0);
        return;
    }
    $map = sc_parse_product_branch_stock_post(
        isset($_POST['sc_branch_stock']) ? wp_unslash($_POST['sc_branch_stock']) : []
    );
    sc_save_product_branch_stock($product_id, $map, 0);
}

add_action('woocommerce_product_after_variable_attributes', 'sc_pbs_render_variation_fields', 20, 3);
/**
 * @param int     $loop
 * @param array   $variation_data
 * @param WP_Post $variation
 */
function sc_pbs_render_variation_fields($loop, $variation_data, $variation) {
    if (!current_user_can('edit_products')) {
        return;
    }
    $variation_id = is_object($variation) ? (int) $variation->ID : 0;
    echo '<div class="sc-pbs-variation-wrap form-row form-row-full">';
    echo '<p><strong>' . esc_html__('موجودی شعبه‌ها', 'sportclub-manager') . '</strong></p>';
    sc_render_product_branch_stock_fields(
        $variation_id,
        'sc_branch_stock_var[' . (int) $loop . ']',
        'sc_branch_stock_var_enable[' . (int) $loop . ']',
        'sc-pbs-variation'
    );
    echo '<input type="hidden" name="sc_branch_stock_var_id[' . (int) $loop . ']" value="' . esc_attr((string) $variation_id) . '" />';
    echo '</div>';
}

add_action('woocommerce_save_product_variation', 'sc_pbs_save_variation_fields', 25, 2);
/**
 * @param int $variation_id
 * @param int $loop
 */
function sc_pbs_save_variation_fields($variation_id, $loop) {
    $variation_id = absint($variation_id);
    if (!$variation_id || !current_user_can('edit_product', $variation_id)) {
        return;
    }
    if (!isset($_POST['sc_branch_stock_var_enable'][$loop])) {
        return;
    }
    $parent_id = 0;
    if (function_exists('wc_get_product')) {
        $variation = wc_get_product($variation_id);
        if ($variation) {
            $parent_id = (int) $variation->get_parent_id();
        }
    }
    $enabled = !empty($_POST['sc_branch_stock_var_enable'][$loop]);
    if (!$enabled) {
        sc_save_product_branch_stock($variation_id, [], $parent_id);
        return;
    }
    $raw = isset($_POST['sc_branch_stock_var'][$loop]) ? wp_unslash($_POST['sc_branch_stock_var'][$loop]) : [];
    $map = sc_parse_product_branch_stock_post(is_array($raw) ? $raw : []);
    sc_save_product_branch_stock($variation_id, $map, $parent_id);
}

add_action('admin_enqueue_scripts', 'sc_pbs_enqueue_product_admin_assets');
function sc_pbs_enqueue_product_admin_assets($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'product') {
        return;
    }
    wp_enqueue_script(
        'sc-product-branch-stock-admin',
        SC_ASSETS_URL . 'js/product-branch-stock-admin.js',
        ['jquery'],
        defined('SC_PLUGIN_VERSION') ? SC_PLUGIN_VERSION : '1.0',
        true
    );
}

/* --------------------------------------------------------------------------
 * Frontend: انتخاب شعبه هنگام افزودن به سبد
 * -------------------------------------------------------------------------- */

add_action('woocommerce_before_add_to_cart_button', 'sc_pbs_render_branch_select_on_product');
function sc_pbs_render_branch_select_on_product() {
    global $product;
    if (!$product instanceof WC_Product) {
        return;
    }
    // برای متغیر در variation form جداگانه هندل می‌شود
    if ($product->is_type('variable')) {
        return;
    }
    if (!sc_product_has_branch_stock($product->get_id())) {
        return;
    }
    sc_pbs_echo_branch_select_html($product->get_id(), 'sc_chapter');
}

add_action('woocommerce_before_single_variation', 'sc_pbs_render_branch_select_on_variable');
function sc_pbs_render_branch_select_on_variable() {
    global $product;
    if (!$product instanceof WC_Product || !$product->is_type('variable')) {
        return;
    }
    // داده‌های موجودی هر variation برای JS
    $children = $product->get_children();
    $data = [];
    $any = false;
    foreach ($children as $vid) {
        $map = sc_get_product_branch_stock_map((int) $vid);
        if (!empty($map)) {
            $any = true;
        }
        $data[(string) $vid] = $map;
    }
    if (!$any) {
        return;
    }
    echo '<div class="sc-pbs-branch-select-wrap" id="sc-pbs-variable-branch-wrap" style="display:none;margin:12px 0;">';
    echo '<label for="sc_chapter_var">' . esc_html__('انتخاب شعبه', 'sportclub-manager') . '</label> ';
    echo '<select name="sc_chapter" id="sc_chapter_var" class="sc-pbs-branch-select">';
    echo '<option value="">' . esc_html__('انتخاب کنید…', 'sportclub-manager') . '</option>';
    echo '</select></div>';
    echo '<script type="application/json" id="sc-pbs-variation-stock-json">' . wp_json_encode($data) . '</script>';
    ?>
    <script>
    (function ($) {
        var stockMap = {};
        try {
            stockMap = JSON.parse(document.getElementById('sc-pbs-variation-stock-json').textContent || '{}');
        } catch (e) { stockMap = {}; }
        var $wrap = $('#sc-pbs-variable-branch-wrap');
        var $sel = $('#sc_chapter_var');
        $('form.variations_form').on('found_variation', function (e, variation) {
            var id = variation && variation.variation_id ? String(variation.variation_id) : '';
            var map = stockMap[id] || {};
            $sel.empty().append($('<option/>').val('').text('انتخاب کنید…'));
            var keys = Object.keys(map);
            if (!keys.length) {
                $wrap.hide();
                return;
            }
            keys.forEach(function (ch) {
                var qty = parseInt(map[ch], 10) || 0;
                if (qty <= 0) return;
                $sel.append($('<option/>').val(ch).text(ch + ' (موجودی: ' + qty + ')'));
            });
            $wrap.toggle($sel.find('option').length > 1);
        }).on('reset_data', function () {
            $wrap.hide();
            $sel.empty().append($('<option/>').val('').text('انتخاب کنید…'));
        });
    })(jQuery);
    </script>
    <?php
}

/**
 * @param int $product_id
 * @param string $name
 */
function sc_pbs_echo_branch_select_html($product_id, $name = 'sc_chapter') {
    $map = sc_get_product_branch_stock_map($product_id);
    if (empty($map)) {
        return;
    }
    echo '<div class="sc-pbs-branch-select-wrap" style="margin:12px 0;">';
    echo '<label for="' . esc_attr($name) . '">' . esc_html__('انتخاب شعبه', 'sportclub-manager') . '</label> ';
    echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="sc-pbs-branch-select" required>';
    echo '<option value="">' . esc_html__('انتخاب کنید…', 'sportclub-manager') . '</option>';
    foreach ($map as $chapter => $qty) {
        if ((int) $qty <= 0) {
            continue;
        }
        echo '<option value="' . esc_attr($chapter) . '">' . esc_html($chapter . ' (موجودی: ' . (int) $qty . ')') . '</option>';
    }
    echo '</select></div>';
}

add_filter('woocommerce_add_to_cart_validation', 'sc_pbs_validate_add_to_cart', 20, 5);
/**
 * @param bool $passed
 * @param int $product_id
 * @param int $quantity
 * @param int $variation_id
 * @param array $variations
 */
function sc_pbs_validate_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variations = []) {
    $stock_id = $variation_id > 0 ? (int) $variation_id : (int) $product_id;
    if (!sc_product_has_branch_stock($stock_id)) {
        return $passed;
    }
    $chapter = isset($_REQUEST['sc_chapter']) ? sanitize_text_field(wp_unslash($_REQUEST['sc_chapter'])) : '';
    if ($chapter === '') {
        wc_add_notice(__('لطفاً شعبه را انتخاب کنید.', 'sportclub-manager'), 'error');
        return false;
    }
    $available = sc_get_product_branch_qty($stock_id, $chapter);
    if ($available < (int) $quantity) {
        wc_add_notice(
            sprintf(
                /* translators: 1: chapter 2: qty */
                __('موجودی شعبه «%1$s» کافی نیست (موجودی: %2$d).', 'sportclub-manager'),
                $chapter,
                $available
            ),
            'error'
        );
        return false;
    }
    return $passed;
}

add_filter('woocommerce_add_cart_item_data', 'sc_pbs_add_cart_item_data', 20, 3);
/**
 * @param array $cart_item_data
 * @param int $product_id
 * @param int $variation_id
 */
function sc_pbs_add_cart_item_data($cart_item_data, $product_id, $variation_id) {
    $stock_id = $variation_id > 0 ? (int) $variation_id : (int) $product_id;
    if (!sc_product_has_branch_stock($stock_id)) {
        return $cart_item_data;
    }
    $chapter = isset($_REQUEST['sc_chapter']) ? sanitize_text_field(wp_unslash($_REQUEST['sc_chapter'])) : '';
    if ($chapter !== '') {
        $cart_item_data['sc_chapter'] = $chapter;
        $cart_item_data['unique_key'] = md5($stock_id . '|' . $chapter . '|' . microtime(true));
    }
    return $cart_item_data;
}

add_filter('woocommerce_get_item_data', 'sc_pbs_display_cart_item_chapter', 20, 2);
/**
 * @param array $item_data
 * @param array $cart_item
 */
function sc_pbs_display_cart_item_chapter($item_data, $cart_item) {
    if (!empty($cart_item['sc_chapter'])) {
        $item_data[] = [
            'key' => __('شعبه', 'sportclub-manager'),
            'value' => esc_html($cart_item['sc_chapter']),
        ];
    }
    return $item_data;
}

add_action('woocommerce_checkout_create_order_line_item', 'sc_pbs_save_order_item_chapter', 20, 4);
/**
 * @param WC_Order_Item_Product $item
 * @param string $cart_item_key
 * @param array $values
 * @param WC_Order $order
 */
function sc_pbs_save_order_item_chapter($item, $cart_item_key, $values, $order) {
    if (!empty($values['sc_chapter'])) {
        $item->add_meta_data('_sc_chapter', sanitize_text_field($values['sc_chapter']), true);
        $item->add_meta_data(__('شعبه', 'sportclub-manager'), sanitize_text_field($values['sc_chapter']), true);
    }
}

add_action('woocommerce_check_cart_items', 'sc_pbs_validate_cart_branch_stock');
function sc_pbs_validate_cart_branch_stock() {
    if (!WC()->cart) {
        return;
    }
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product = $cart_item['data'] ?? null;
        if (!$product instanceof WC_Product) {
            continue;
        }
        $stock_id = $product->get_id();
        if (!sc_product_has_branch_stock($stock_id)) {
            continue;
        }
        $chapter = isset($cart_item['sc_chapter']) ? (string) $cart_item['sc_chapter'] : '';
        if ($chapter === '') {
            wc_add_notice(__('برای برخی محصولات انتخاب شعبه الزامی است.', 'sportclub-manager'), 'error');
            continue;
        }
        $qty = (int) ($cart_item['quantity'] ?? 0);
        $available = sc_get_product_branch_qty($stock_id, $chapter);
        if ($available < $qty) {
            wc_add_notice(
                sprintf(
                    __('موجودی شعبه «%1$s» برای «%2$s» کافی نیست.', 'sportclub-manager'),
                    $chapter,
                    $product->get_name()
                ),
                'error'
            );
        }
    }
}

/* --------------------------------------------------------------------------
 * کاهش / بازگردانی موجودی با سفارش
 * -------------------------------------------------------------------------- */

add_action('woocommerce_reduce_order_stock', 'sc_pbs_reduce_order_branch_stock', 20, 1);
/**
 * @param WC_Order $order
 */
function sc_pbs_reduce_order_branch_stock($order) {
    if (!$order instanceof WC_Order) {
        return;
    }
    if ($order->get_meta('_sc_branch_stock_reduced') === 'yes') {
        return;
    }
    foreach ($order->get_items() as $item) {
        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }
        $product = $item->get_product();
        if (!$product) {
            continue;
        }
        $stock_id = $product->get_id();
        if (!sc_product_has_branch_stock($stock_id)) {
            continue;
        }
        $chapter = (string) $item->get_meta('_sc_chapter', true);
        if ($chapter === '') {
            $chapter = (string) $item->get_meta(__('شعبه', 'sportclub-manager'), true);
        }
        if ($chapter === '') {
            continue;
        }
        $qty = (int) $item->get_quantity();
        $result = sc_adjust_product_branch_stock($stock_id, $chapter, -$qty);
        if (is_wp_error($result)) {
            $order->add_order_note('خطا در کاهش موجودی شعبه: ' . $result->get_error_message());
        } else {
            $item->add_meta_data('_sc_branch_stock_reduced_qty', $qty, true);
            $item->save();
        }
    }
    $order->update_meta_data('_sc_branch_stock_reduced', 'yes');
    $order->save();
}

add_action('woocommerce_restore_order_stock', 'sc_pbs_restore_order_branch_stock', 20, 1);
/**
 * @param WC_Order $order
 */
function sc_pbs_restore_order_branch_stock($order) {
    if (!$order instanceof WC_Order) {
        return;
    }
    if ($order->get_meta('_sc_branch_stock_reduced') !== 'yes') {
        return;
    }
    foreach ($order->get_items() as $item) {
        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }
        $product = $item->get_product();
        if (!$product) {
            continue;
        }
        $stock_id = $product->get_id();
        $chapter = (string) $item->get_meta('_sc_chapter', true);
        if ($chapter === '') {
            continue;
        }
        $qty = (int) $item->get_meta('_sc_branch_stock_reduced_qty', true);
        if ($qty <= 0) {
            $qty = (int) $item->get_quantity();
        }
        sc_adjust_product_branch_stock($stock_id, $chapter, $qty);
        $item->delete_meta_data('_sc_branch_stock_reduced_qty');
        $item->save();
    }
    $order->delete_meta_data('_sc_branch_stock_reduced');
    $order->save();
}

/* --------------------------------------------------------------------------
 * استعلام AJAX
 * -------------------------------------------------------------------------- */

add_action('wp_ajax_sc_product_stock_search', 'sc_ajax_product_stock_search');
function sc_ajax_product_stock_search() {
    check_ajax_referer('sc_product_stock_inquiry', 'nonce');
    if (!sc_user_can_product_stock_inquiry()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $q = isset($_REQUEST['q']) ? sanitize_text_field(wp_unslash($_REQUEST['q'])) : '';
    $q = trim($q);
    if ($q === '' || mb_strlen($q) < 2) {
        wp_send_json_success(['items' => []]);
    }

    $product_ids = [];

    // جستجوی عنوان با WP_Query (بهتر از wc_get_products برای فارسی)
    $query = new WP_Query([
        'post_type' => ['product', 'product_variation'],
        'post_status' => ['publish', 'private'],
        's' => $q,
        'posts_per_page' => 40,
        'fields' => 'ids',
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => false,
    ]);
    if (!empty($query->posts)) {
        $product_ids = array_map('intval', $query->posts);
    }

    // جستجوی SKU (دقیق + جزئی)
    global $wpdb;
    $like = '%' . $wpdb->esc_like($q) . '%';
    $sku_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_sku'
           AND pm.meta_value LIKE %s
           AND p.post_type IN ('product', 'product_variation')
           AND p.post_status IN ('publish', 'private')
         LIMIT 40",
        $like
    ));
    if (!empty($sku_ids)) {
        $product_ids = array_merge($product_ids, array_map('intval', $sku_ids));
    }

    // تطبیق عنوان با LIKE مستقیم (پوشش بیشتر برای فارسی)
    $title_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type IN ('product', 'product_variation')
           AND post_status IN ('publish', 'private')
           AND post_title LIKE %s
         ORDER BY post_title ASC
         LIMIT 40",
        $like
    ));
    if (!empty($title_ids)) {
        $product_ids = array_merge($product_ids, array_map('intval', $title_ids));
    }

    $product_ids = array_values(array_unique(array_filter($product_ids)));
    $seen = [];
    $items = [];

    foreach ($product_ids as $pid) {
        if (count($items) >= 25) {
            break;
        }
        if (isset($seen[$pid])) {
            continue;
        }
        $product = wc_get_product($pid);
        if (!$product) {
            continue;
        }

        // اگر والد متغیر پیدا شد، variationها را برگردان
        if ($product->is_type('variable')) {
            $seen[$pid] = true;
            foreach ($product->get_children() as $vid) {
                if (isset($seen[$vid]) || count($items) >= 25) {
                    continue;
                }
                $variation = wc_get_product($vid);
                if (!$variation) {
                    continue;
                }
                $seen[$vid] = true;
                $label = $product->get_name();
                $attrs = wc_get_formatted_variation($variation, true, false, true);
                if ($attrs) {
                    $label .= ' — ' . $attrs;
                }
                $sku = $variation->get_sku();
                if ($sku) {
                    $label .= ' · ' . $sku;
                }
                $thumb_id = $variation->get_image_id() ?: $product->get_image_id();
                $items[] = [
                    'id' => (int) $vid,
                    'parent_id' => (int) $product->get_id(),
                    'label' => $label,
                    'name' => $label,
                    'type' => 'variation',
                    'thumb' => $thumb_id ? (string) wp_get_attachment_image_url($thumb_id, 'thumbnail') : '',
                ];
            }
            continue;
        }

        if ($product->is_type('variation')) {
            $seen[$pid] = true;
            $parent = wc_get_product($product->get_parent_id());
            $label = $parent ? $parent->get_name() : $product->get_name();
            $attrs = wc_get_formatted_variation($product, true, false, true);
            if ($attrs) {
                $label .= ' — ' . $attrs;
            }
            $sku = $product->get_sku();
            if ($sku) {
                $label .= ' · ' . $sku;
            }
            $thumb_id = $product->get_image_id() ?: ($parent ? $parent->get_image_id() : 0);
            $items[] = [
                'id' => (int) $pid,
                'parent_id' => (int) $product->get_parent_id(),
                'label' => $label,
                'name' => $label,
                'type' => 'variation',
                'thumb' => $thumb_id ? (string) wp_get_attachment_image_url($thumb_id, 'thumbnail') : '',
            ];
            continue;
        }

        // محصول ساده / سایر
        if ($product->is_type('simple') || $product->is_type('grouped') || $product->is_purchasable()) {
            $seen[$pid] = true;
            $label = $product->get_name();
            $sku = $product->get_sku();
            if ($sku) {
                $label .= ' · ' . $sku;
            }
            $thumb_id = $product->get_image_id();
            $items[] = [
                'id' => (int) $pid,
                'parent_id' => 0,
                'label' => $label,
                'name' => $product->get_name(),
                'type' => $product->get_type(),
                'thumb' => $thumb_id ? (string) wp_get_attachment_image_url($thumb_id, 'thumbnail') : '',
            ];
        }
    }

    wp_send_json_success(['items' => $items]);
}

add_action('wp_ajax_sc_product_stock_inquiry', 'sc_ajax_product_stock_inquiry');
function sc_ajax_product_stock_inquiry() {
    check_ajax_referer('sc_product_stock_inquiry', 'nonce');
    if (!sc_user_can_product_stock_inquiry()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $product_id = isset($_REQUEST['product_id']) ? absint($_REQUEST['product_id']) : 0;
    if (!$product_id || !function_exists('wc_get_product')) {
        wp_send_json_error(['message' => 'محصول نامعتبر است.']);
    }
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error(['message' => 'محصول یافت نشد.']);
    }

    $parent = null;
    $display_name = $product->get_name();
    if ($product->is_type('variation')) {
        $parent = wc_get_product($product->get_parent_id());
        $display_name = ($parent ? $parent->get_name() . ' — ' : '') . wc_get_formatted_variation($product, true, false, true);
    }

    $price = $product->get_price();
    $price_html = $product->get_price_html();
    $map = sc_get_product_branch_stock_map($product_id);

    // محدوده منشی
    $allowed_chapters = null;
    if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()) {
        $allowed_chapters = function_exists('sc_secretary_get_effective_chapters')
            ? sc_secretary_get_effective_chapters()
            : sc_get_secretary_chapters();
    }

    $branches = [];
    $all_chapters = sc_product_branch_stock_get_chapter_names();
    $chapters_to_show = !empty($map) ? array_keys($map) : $all_chapters;

    foreach ($chapters_to_show as $chapter) {
        if (is_array($allowed_chapters) && !in_array($chapter, $allowed_chapters, true)) {
            continue;
        }
        $qty = isset($map[$chapter]) ? (int) $map[$chapter] : 0;
        $has_row = array_key_exists($chapter, $map);
        if (!$has_row && !empty($map)) {
            // فقط شعبه‌هایی که برای محصول تعریف شده‌اند
            continue;
        }
        $branches[] = [
            'chapter' => $chapter,
            'qty' => $qty,
            'in_stock' => $qty > 0,
            'configured' => $has_row,
            'price' => $price,
            'price_html' => $price_html,
            'price_formatted' => function_exists('wc_price') ? wc_price($price) : number_format_i18n((float) $price),
        ];
    }

    // اگر هیچ ردیف شعبه‌ای نبود، پیام مناسب
    $has_branch_config = !empty($map);

    $thumb_id = $product->get_image_id();
    if (!$thumb_id && $parent) {
        $thumb_id = $parent->get_image_id();
    }

    $max_qty = 0;
    foreach ($branches as $b) {
        if ($b['qty'] > $max_qty) {
            $max_qty = $b['qty'];
        }
    }

    wp_send_json_success([
        'product' => [
            'id' => (int) $product_id,
            'name' => $display_name,
            'sku' => (string) $product->get_sku(),
            'type' => $product->get_type(),
            'price' => $price,
            'price_html' => $price_html,
            'thumb' => $thumb_id ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '',
            'edit_url' => get_edit_post_link($product->is_type('variation') ? $product->get_parent_id() : $product_id, 'raw'),
            'has_branch_config' => $has_branch_config,
            'wc_stock' => $product->managing_stock() ? (int) $product->get_stock_quantity() : null,
            'stock_status' => $product->get_stock_status(),
        ],
        'branches' => $branches,
        'all_chapters' => array_values(array_filter($all_chapters, static function ($ch) use ($allowed_chapters) {
            if (!is_array($allowed_chapters)) {
                return true;
            }
            return in_array($ch, $allowed_chapters, true);
        })),
        'max_qty' => $max_qty,
    ]);
}

/* --------------------------------------------------------------------------
 * انتقال موجودی بین شعبه‌ها
 * -------------------------------------------------------------------------- */

/**
 * اطمینان از وجود ردیف موجودی برای یک شعبه (در صورت نبود، با qty=0 ساخته می‌شود)
 *
 * @param int $product_id
 * @param string $chapter_name
 * @param int $parent_id
 * @return bool|WP_Error
 */
function sc_ensure_product_branch_stock_row($product_id, $chapter_name, $parent_id = 0) {
    global $wpdb;
    sc_product_branch_stock_ensure_table();
    if (!sc_product_branch_stock_table_exists()) {
        return new WP_Error('no_table', 'جدول موجودی شعبه موجود نیست.');
    }
    $product_id = absint($product_id);
    $chapter_name = trim(sanitize_text_field((string) $chapter_name));
    $parent_id = absint($parent_id);
    if (!$product_id || $chapter_name === '') {
        return new WP_Error('invalid', 'پارامتر نامعتبر است.');
    }
    $table = sc_product_branch_stock_table();
    $exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `$table` WHERE product_id = %d AND chapter_name = %s LIMIT 1",
        $product_id,
        $chapter_name
    ));
    if ($exists > 0) {
        return true;
    }
    if (!$parent_id && function_exists('wc_get_product')) {
        $p = wc_get_product($product_id);
        if ($p && $p->is_type('variation')) {
            $parent_id = (int) $p->get_parent_id();
        }
    }
    $now = current_time('mysql');
    $ok = $wpdb->insert(
        $table,
        [
            'product_id' => $product_id,
            'parent_id' => $parent_id,
            'chapter_name' => $chapter_name,
            'qty' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%d', '%d', '%s', '%d', '%s', '%s']
    );
    return $ok ? true : new WP_Error('insert_failed', 'ایجاد ردیف موجودی شعبه ناموفق بود.');
}

/**
 * انتقال موجودی از یک شعبه به شعبه دیگر
 *
 * @param int $product_id
 * @param string $from_chapter
 * @param string $to_chapter
 * @param int $qty
 * @return true|WP_Error
 */
function sc_transfer_product_branch_stock($product_id, $from_chapter, $to_chapter, $qty) {
    $product_id = absint($product_id);
    $from_chapter = trim(sanitize_text_field((string) $from_chapter));
    $to_chapter = trim(sanitize_text_field((string) $to_chapter));
    $qty = absint($qty);

    if (!$product_id || $qty < 1) {
        return new WP_Error('invalid', 'محصول یا تعداد نامعتبر است.');
    }
    if ($from_chapter === '' || $to_chapter === '') {
        return new WP_Error('invalid', 'شعبه مبدأ و مقصد الزامی است.');
    }
    if ($from_chapter === $to_chapter) {
        return new WP_Error('same_chapter', 'شعبه مبدأ و مقصد نباید یکسان باشند.');
    }
    if (function_exists('sc_secretary_chapter_in_scope')) {
        if (!sc_secretary_chapter_in_scope($from_chapter) || !sc_secretary_chapter_in_scope($to_chapter)) {
            return new WP_Error('forbidden', 'به این شعبه دسترسی ندارید.');
        }
    }

    $available = sc_get_product_branch_qty($product_id, $from_chapter);
    if ($available < $qty) {
        return new WP_Error('insufficient', 'موجودی شعبه مبدأ کافی نیست (موجودی: ' . $available . ').');
    }

    $ensure = sc_ensure_product_branch_stock_row($product_id, $to_chapter);
    if (is_wp_error($ensure)) {
        return $ensure;
    }

    $dec = sc_adjust_product_branch_stock($product_id, $from_chapter, -$qty);
    if (is_wp_error($dec)) {
        return $dec;
    }
    $inc = sc_adjust_product_branch_stock($product_id, $to_chapter, $qty);
    if (is_wp_error($inc)) {
        // rollback
        sc_adjust_product_branch_stock($product_id, $from_chapter, $qty);
        return $inc;
    }
    return true;
}

add_action('wp_ajax_sc_product_stock_transfer', 'sc_ajax_product_stock_transfer');
function sc_ajax_product_stock_transfer() {
    check_ajax_referer('sc_product_stock_inquiry', 'nonce');
    if (!sc_user_can_product_stock_inquiry()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $from = isset($_POST['from_chapter']) ? sanitize_text_field(wp_unslash($_POST['from_chapter'])) : '';
    $to = isset($_POST['to_chapter']) ? sanitize_text_field(wp_unslash($_POST['to_chapter'])) : '';
    $qty = isset($_POST['qty']) ? absint($_POST['qty']) : 0;

    $result = sc_transfer_product_branch_stock($product_id, $from, $to, $qty);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success([
        'message' => sprintf('انتقال %d عدد از «%s» به «%s» انجام شد.', $qty, $from, $to),
        'branches' => sc_get_product_branch_stock_map($product_id),
    ]);
}

/* --------------------------------------------------------------------------
 * ثبت سفارش آنی فروشگاه
 * -------------------------------------------------------------------------- */

/**
 * یافتن یا ساخت عضو بر اساس نام و موبایل
 *
 * @return array{member:object,user_id:int,member_id:int,created:bool}|WP_Error
 */
function sc_pbs_resolve_or_create_shop_customer($first_name, $last_name, $mobile) {
    global $wpdb;
    $first_name = sanitize_text_field((string) $first_name);
    $last_name = sanitize_text_field((string) $last_name);
    $mobile = preg_replace('/\D/', '', (string) $mobile);

    if (!preg_match('/^09\d{9}$/', $mobile)) {
        return new WP_Error('bad_mobile', 'شماره موبایل معتبر نیست (مثال: 09123456789).');
    }
    if ($first_name === '' || $last_name === '') {
        return new WP_Error('bad_name', 'نام و نام خانوادگی الزامی است.');
    }

    $members_table = $wpdb->prefix . 'sc_members';
    $created = false;
    $user_id = 0;
    $member_id = 0;

    // جستجو با موبایل
    if (function_exists('sc_login_register_get_users_by_phone')) {
        $users = sc_login_register_get_users_by_phone($mobile);
        if (!empty($users[0]) && !empty($users[0]->ID)) {
            $user_id = (int) $users[0]->ID;
        }
    }
    if (!$user_id) {
        $by_login = get_user_by('login', $mobile);
        if ($by_login) {
            $user_id = (int) $by_login->ID;
        }
    }

    if ($user_id > 0) {
        $member_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$members_table} WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        if ($member_id < 1 && function_exists('sc_auto_create_member_on_user_register')) {
            sc_auto_create_member_on_user_register($user_id);
            $member_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$members_table} WHERE user_id = %d LIMIT 1",
                $user_id
            ));
        }
    } else {
        if (username_exists($mobile) || email_exists($mobile . '@sportclub.local')) {
            return new WP_Error('exists', 'این شماره قبلاً ثبت شده ولی پروفایل بازیکن یافت نشد.');
        }
        $password = wp_generate_password(12, true);
        $user_id = wp_insert_user([
            'user_login' => $mobile,
            'user_email' => $mobile . '@sportclub.local',
            'user_pass' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'subscriber',
            'display_name' => trim($first_name . ' ' . $last_name),
        ]);
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        $user_id = (int) $user_id;
        update_user_meta($user_id, 'billing_phone', $mobile);
        $created = true;
        if (function_exists('sc_auto_create_member_on_user_register')) {
            sc_auto_create_member_on_user_register($user_id);
        }
        $member_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$members_table} WHERE user_id = %d LIMIT 1",
            $user_id
        ));
    }

    if ($member_id < 1) {
        return new WP_Error('no_member', 'خطا در یافتن/ایجاد پروفایل بازیکن.');
    }

    $wpdb->update(
        $members_table,
        [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'player_phone' => $mobile,
            'is_active' => 1,
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $member_id],
        ['%s', '%s', '%s', '%d', '%s'],
        ['%d']
    );

    $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$members_table} WHERE id = %d LIMIT 1", $member_id));
    if (!$member) {
        return new WP_Error('no_member', 'پروفایل بازیکن یافت نشد.');
    }

    return [
        'member' => $member,
        'user_id' => $user_id,
        'member_id' => $member_id,
        'created' => $created,
    ];
}

/**
 * ثبت سفارش آنی فروشگاه از پنل استعلام
 *
 * @param array<string,mixed> $args
 * @return array{success:bool,message:string,order_id?:int,invoice_id?:int}|WP_Error
 */
function sc_pbs_create_instant_shop_order($args) {
    if (!function_exists('wc_create_order') || !function_exists('wc_get_product')) {
        return new WP_Error('no_wc', 'ووکامرس در دسترس نیست.');
    }

    $product_id = absint($args['product_id'] ?? 0);
    $qty = max(1, absint($args['qty'] ?? 1));
    $chapter = trim(sanitize_text_field((string) ($args['chapter'] ?? '')));
    $payment_status = sanitize_text_field((string) ($args['payment_status'] ?? 'pending'));
    $allowed_status = ['pending', 'processing', 'completed'];
    if (!in_array($payment_status, $allowed_status, true)) {
        $payment_status = 'pending';
    }

    $product = wc_get_product($product_id);
    if (!$product || $product->is_type('variable')) {
        return new WP_Error('bad_product', 'محصول نامعتبر است. برای محصول متغیر، یک variation انتخاب کنید.');
    }

    if ($chapter === '') {
        return new WP_Error('no_chapter', 'انتخاب شعبه الزامی است.');
    }
    if (function_exists('sc_secretary_chapter_in_scope') && !sc_secretary_chapter_in_scope($chapter)) {
        return new WP_Error('forbidden', 'به این شعبه دسترسی ندارید.');
    }

    $has_branch = sc_product_has_branch_stock($product_id);
    if ($has_branch) {
        $available = sc_get_product_branch_qty($product_id, $chapter);
        if ($available < $qty) {
            return new WP_Error('insufficient', 'موجودی شعبه «' . $chapter . '» کافی نیست (موجودی: ' . $available . ').');
        }
    }

    $customer = sc_pbs_resolve_or_create_shop_customer(
        $args['first_name'] ?? '',
        $args['last_name'] ?? '',
        $args['mobile'] ?? ''
    );
    if (is_wp_error($customer)) {
        return $customer;
    }

    $order = wc_create_order([
        'customer_id' => (int) $customer['user_id'],
        'status' => 'pending',
    ]);
    if (is_wp_error($order)) {
        return $order;
    }

    $item_id = $order->add_product($product, $qty);
    if (!$item_id) {
        $order->delete(true);
        return new WP_Error('add_product', 'افزودن محصول به سفارش ناموفق بود.');
    }

    $item = $order->get_item($item_id);
    if ($item) {
        $item->add_meta_data('_sc_chapter', $chapter, true);
        $item->add_meta_data('شعبه', $chapter, true);
        $item->save();
    }

    if (function_exists('sc_apply_member_billing_to_order')) {
        sc_apply_member_billing_to_order($order, $customer['member'], (int) $customer['user_id']);
    } else {
        $order->set_customer_id((int) $customer['user_id']);
        $order->set_billing_first_name((string) $customer['member']->first_name);
        $order->set_billing_last_name((string) $customer['member']->last_name);
        $order->set_billing_phone((string) $customer['member']->player_phone);
    }

    $order->set_created_via('sc_instant_shop');
    $order->update_meta_data('_sc_instant_shop', 'yes');
    $order->update_meta_data('_sc_chapter', $chapter);

    // تاریخ سفارش (شمسی → میلادی)
    $order_date_mysql = '';
    $order_date_shamsi = trim(sanitize_text_field((string) ($args['order_date'] ?? '')));
    if ($order_date_shamsi !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
        $g = sc_shamsi_to_gregorian_date($order_date_shamsi);
        if ($g !== '') {
            $order_date_mysql = $g . ' ' . current_time('H:i:s');
            try {
                if (function_exists('wc_string_to_datetime')) {
                    $order->set_date_created(wc_string_to_datetime($order_date_mysql));
                } else {
                    $order->set_date_created($order_date_mysql);
                }
            } catch (Exception $e) {
                // نادیده؛ تاریخ پیش‌فرض ووکامرس می‌ماند
                $order_date_mysql = '';
            }
        }
    }

    $order->calculate_totals(false);
    $subtotal = (float) $order->get_subtotal();
    if (function_exists('sc_invoice_fees_apply_tax_to_order') && $subtotal > 0) {
        sc_invoice_fees_apply_tax_to_order($order, $subtotal, 'shop');
        $order->calculate_totals(false);
    }

    $order->set_status('pending', 'سفارش آنی از استعلام موجودی');
    $order->save();

    $order_id = (int) $order->get_id();
    $invoice_id = 0;
    if (function_exists('sc_ensure_shop_invoice_for_order')) {
        $invoice_id = (int) sc_ensure_shop_invoice_for_order($order_id, (int) $customer['member_id']);
    }

    // همگام‌سازی تاریخ فاکتور با تاریخ سفارش
    if ($invoice_id > 0 && $order_date_mysql !== '') {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'sc_invoices',
            [
                'created_at' => $order_date_mysql,
                'updated_at' => $order_date_mysql,
            ],
            ['id' => $invoice_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    if ($payment_status !== 'pending') {
        // تاریخ پرداخت = تاریخ سفارش (در صورت تعیین)
        if ($order_date_mysql !== '') {
            $fresh_for_paid = wc_get_order($order_id);
            if ($fresh_for_paid) {
                try {
                    if (function_exists('wc_string_to_datetime')) {
                        $fresh_for_paid->set_date_paid(wc_string_to_datetime($order_date_mysql));
                    } else {
                        $fresh_for_paid->set_date_paid($order_date_mysql);
                    }
                    $fresh_for_paid->save();
                } catch (Exception $e) {
                    // ignore
                }
            }
        }
        if ($invoice_id > 0 && function_exists('sc_secretary_apply_invoice_payment_status')) {
            $status_res = sc_secretary_apply_invoice_payment_status($invoice_id, $payment_status);
            if (is_wp_error($status_res)) {
                $order->update_status($payment_status, 'وضعیت پرداخت سفارش آنی');
            }
        } else {
            $order->update_status($payment_status, 'وضعیت پرداخت سفارش آنی');
        }
        // اگر apply_invoice تاریخ پرداخت را الان گذاشت، دوباره تاریخ انتخابی را بنویس
        if ($invoice_id > 0 && $order_date_mysql !== '') {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'sc_invoices',
                [
                    'payment_date' => $order_date_mysql,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $invoice_id],
                ['%s', '%s'],
                ['%d']
            );
            $fresh_paid = wc_get_order($order_id);
            if ($fresh_paid) {
                try {
                    if (function_exists('wc_string_to_datetime')) {
                        $fresh_paid->set_date_paid(wc_string_to_datetime($order_date_mysql));
                    } else {
                        $fresh_paid->set_date_paid($order_date_mysql);
                    }
                    // تاریخ ایجاد را هم بعد از update_status حفظ کن
                    if (function_exists('wc_string_to_datetime')) {
                        $fresh_paid->set_date_created(wc_string_to_datetime($order_date_mysql));
                    }
                    $fresh_paid->save();
                } catch (Exception $e) {
                    // ignore
                }
            }
        }
    }

    // اطمینان از کاهش موجودی شعبه اگر پرداخت شده و هوک WC اجرا نشده
    if (in_array($payment_status, ['processing', 'completed'], true) && $has_branch) {
        $order = wc_get_order($order_id);
        if ($order && $order->get_meta('_sc_branch_stock_reduced') !== 'yes') {
            sc_pbs_reduce_order_branch_stock($order);
        }
    }

    $status_labels = [
        'pending' => 'در انتظار پرداخت',
        'processing' => 'پرداخت شده',
        'completed' => 'تایید پرداخت',
    ];
    $pay_label = $status_labels[$payment_status] ?? $payment_status;

    $edit_url = '';
    $fresh = wc_get_order($order_id);
    if ($fresh && is_callable([$fresh, 'get_edit_order_url'])) {
        $edit_url = $fresh->get_edit_order_url();
    }
    if ($edit_url === '') {
        $edit_url = admin_url('admin.php?page=sc_orders');
    }

    return [
        'success' => true,
        'message' => sprintf(
            'سفارش #%d ثبت شد (%s).%s',
            $order_id,
            $pay_label,
            $invoice_id ? ' فاکتور فروشگاه نیز صادر شد.' : ''
        ),
        'order_id' => $order_id,
        'invoice_id' => $invoice_id,
        'order_url' => $edit_url,
        'branches' => sc_get_product_branch_stock_map($product_id),
    ];
}

add_action('wp_ajax_sc_product_stock_instant_order', 'sc_ajax_product_stock_instant_order');
function sc_ajax_product_stock_instant_order() {
    check_ajax_referer('sc_product_stock_inquiry', 'nonce');
    if (!sc_user_can_product_stock_inquiry()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }

    $result = sc_pbs_create_instant_shop_order([
        'product_id' => isset($_POST['product_id']) ? absint($_POST['product_id']) : 0,
        'qty' => isset($_POST['qty']) ? absint($_POST['qty']) : 1,
        'chapter' => isset($_POST['chapter']) ? sanitize_text_field(wp_unslash($_POST['chapter'])) : '',
        'payment_status' => isset($_POST['payment_status']) ? sanitize_text_field(wp_unslash($_POST['payment_status'])) : 'pending',
        'first_name' => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
        'last_name' => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
        'mobile' => isset($_POST['mobile']) ? sanitize_text_field(wp_unslash($_POST['mobile'])) : '',
        'order_date' => isset($_POST['order_date']) ? sanitize_text_field(wp_unslash($_POST['order_date'])) : '',
    ]);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success($result);
}

/**
 * لیست محصولات انبار با موجودی شعبه‌ها
 *
 * @param array{search?:string,page?:int,per_page?:int,allowed_chapters?:string[]|null} $args
 * @return array{items:array<int,array>,total:int}
 */
function sc_warehouse_get_product_stock_items($args = []) {
    if (!function_exists('wc_get_products') || !function_exists('wc_get_product')) {
        return ['items' => [], 'total' => 0];
    }

    $search = trim((string) ($args['search'] ?? ''));
    $page = max(1, (int) ($args['page'] ?? 1));
    $per_page = max(1, min(100, (int) ($args['per_page'] ?? 20)));
    $allowed = $args['allowed_chapters'] ?? null;

    $query_args = [
        'status' => ['publish', 'private'],
        'limit' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'return' => 'ids',
        'type' => ['simple', 'variable'],
    ];
    if ($search !== '') {
        $query_args['s'] = $search;
    }

    $parent_ids = wc_get_products($query_args);
    if (!is_array($parent_ids)) {
        $parent_ids = [];
    }

    // SKU search supplement
    if ($search !== '' && function_exists('wc_get_product_id_by_sku')) {
        $sku_id = wc_get_product_id_by_sku($search);
        if ($sku_id) {
            $p = wc_get_product($sku_id);
            if ($p) {
                $pid = $p->is_type('variation') ? (int) $p->get_parent_id() : (int) $sku_id;
                if ($pid && !in_array($pid, $parent_ids, true)) {
                    $parent_ids[] = $pid;
                }
            }
        }
    }

    $flat = [];
    foreach ($parent_ids as $pid) {
        $product = wc_get_product((int) $pid);
        if (!$product) {
            continue;
        }
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $vid) {
                $variation = wc_get_product((int) $vid);
                if (!$variation) {
                    continue;
                }
                if ($search !== '') {
                    $label = $product->get_name() . ' ' . $variation->get_name() . ' ' . $variation->get_sku();
                    if (function_exists('mb_stripos')) {
                        if (mb_stripos($label, $search) === false && mb_stripos($product->get_name(), $search) === false) {
                            // keep if parent matched via WC search
                        }
                    }
                }
                $flat[] = sc_warehouse_format_stock_row($variation, $product, $allowed);
            }
        } else {
            $flat[] = sc_warehouse_format_stock_row($product, null, $allowed);
        }
    }

    $total = count($flat);
    $offset = ($page - 1) * $per_page;
    $slice = array_slice($flat, $offset, $per_page);

    return [
        'items' => $slice,
        'total' => $total,
    ];
}

/**
 * @param WC_Product $product
 * @param WC_Product|null $parent
 * @param string[]|null $allowed_chapters
 * @return array<string,mixed>
 */
function sc_warehouse_format_stock_row($product, $parent = null, $allowed_chapters = null) {
    $product_id = (int) $product->get_id();
    $map = function_exists('sc_get_product_branch_stock_map') ? sc_get_product_branch_stock_map($product_id) : [];
    $price_html = $product->get_price_html();

    $name = $product->get_name();
    $type_label = 'ساده';
    if ($product->is_type('variation')) {
        $type_label = 'متغیر';
        if ($parent) {
            $attrs = function_exists('wc_get_formatted_variation')
                ? wc_get_formatted_variation($product, true, false, true)
                : '';
            $name = $parent->get_name() . ($attrs ? ' — ' . $attrs : '');
        }
    }

    $branches = [];
    $max_qty = 0;
    $total_qty = 0;
    foreach ($map as $chapter => $qty) {
        if (is_array($allowed_chapters) && !in_array($chapter, $allowed_chapters, true)) {
            continue;
        }
        $qty = (int) $qty;
        $total_qty += $qty;
        if ($qty > $max_qty) {
            $max_qty = $qty;
        }
        $branches[] = [
            'chapter' => $chapter,
            'qty' => $qty,
            'price_html' => $price_html,
        ];
    }

    $thumb_id = $product->get_image_id();
    if (!$thumb_id && $parent) {
        $thumb_id = $parent->get_image_id();
    }

    $edit_id = $product->is_type('variation') ? (int) $product->get_parent_id() : $product_id;

    return [
        'id' => $product_id,
        'name' => $name,
        'sku' => (string) $product->get_sku(),
        'type' => $product->get_type(),
        'type_label' => $type_label,
        'price_html' => $price_html,
        'thumb' => $thumb_id ? (string) wp_get_attachment_image_url($thumb_id, 'thumbnail') : '',
        'edit_url' => get_edit_post_link($edit_id, 'raw'),
        'branches' => $branches,
        'total_qty' => $total_qty,
        'max_qty' => $max_qty,
        'has_branch_config' => !empty($map),
    ];
}
