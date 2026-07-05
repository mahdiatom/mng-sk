<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if (!function_exists('sc_admin_sc_products_get_request_scalar_string')) {
    function sc_admin_sc_products_get_request_scalar_string($key, $default = '') {
        if (!isset($_GET[$key])) {
            return $default;
        }
        $v = wp_unslash($_GET[$key]);
        if (is_array($v)) {
            $v = reset($v);
        }
        if (!is_scalar($v) || $v === null) {
            return $default;
        }
        return (string) $v;
    }
}

if (!function_exists('sc_admin_sc_products_get_request_scalar_int')) {
    function sc_admin_sc_products_get_request_scalar_int($key, $default = 0) {
        $s = sc_admin_sc_products_get_request_scalar_string($key, '');
        if ($s === '' || !is_numeric($s)) {
            return $default;
        }
        return (int) $s;
    }
}

class products_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'sc_product',
            'plural' => 'sc_products',
            'ajax' => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'name' => 'محصول',
            'sku' => 'SKU',
            'price' => 'قیمت',
            'stock' => 'موجودی',
            'categories' => 'دسته‌ها',
            'product_status' => 'وضعیت',
            'date' => 'تاریخ',
        ];
    }

    protected function get_sortable_columns() {
        return [
            'name' => ['title', false],
            'sku' => ['sku', false],
            'price' => ['price', false],
            'date' => ['date', true],
        ];
    }

    public function get_bulk_actions() {
        return [
            'trash' => 'انتقال به زباله‌دان',
        ];
    }

    public function process_bulk_action() {
        if ($this->current_action() !== 'trash') {
            return;
        }
        $ids = isset($_GET[$this->_args['singular']]) ? array_map('absint', (array) $_GET[$this->_args['singular']]) : [];
        if (empty($ids)) {
            return;
        }
        check_admin_referer('bulk-' . $this->_args['plural']);
        foreach ($ids as $id) {
            if ($id > 0) {
                wp_trash_post($id);
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=sc-products&sc_status=bulk_trashed'));
        exit;
    }

    protected function get_primary_column_name() {
        return 'name';
    }

    public function column_cb($item) {
        return '<input type="checkbox" value="' . esc_attr($item['ID']) . '" name="' . esc_attr($this->_args['singular']) . '[]" />';
    }

    public function column_name($item) {
        $edit_url = get_edit_post_link($item['ID'], 'raw');
        $title = get_the_title($item['ID']);
        if ($title === '') {
            $title = '(بدون عنوان)';
        }

        $thumb_id = get_post_thumbnail_id($item['ID']);
        if ($thumb_id) {
            $thumb_url = wp_get_attachment_image_url($thumb_id, [96, 96]);
            if (!$thumb_url) {
                $thumb_url = wp_get_attachment_image_url($thumb_id, 'thumbnail');
            }
            $thumb_html = '<span class="sc-shop-products-thumb"><img src="' . esc_url($thumb_url) . '" alt="" width="48" height="48" loading="lazy"></span>';
        } else {
            $initial = mb_substr($title, 0, 1) ?: '؟';
            $thumb_html = '<span class="sc-shop-products-thumb sc-shop-products-thumb--placeholder" aria-hidden="true">' . esc_html($initial) . '</span>';
        }

        $sku = get_post_meta($item['ID'], '_sku', true);
        $meta = $sku !== '' ? '<span class="sc-shop-products-meta">SKU: ' . esc_html($sku) . '</span>' : '';

        $name_block = '<span class="sc-shop-products-row-identity">'
            . $thumb_html
            . '<span class="sc-shop-products-row-text">'
            . '<span class="sc-shop-products-name">' . esc_html($title) . '</span>'
            . $meta
            . '</span>'
            . '</span>';

        $actions = [
            'edit' => '<a href="' . esc_url($edit_url) . '">ویرایش</a>',
            'view' => '<a href="' . esc_url(get_permalink($item['ID'])) . '" target="_blank">مشاهده</a>',
        ];

        return '<a href="' . esc_url($edit_url) . '" class="sc-shop-products-name-link">' . $name_block . '</a>' . $this->row_actions($actions);
    }

    public function column_sku($item) {
        $sku = get_post_meta($item['ID'], '_sku', true);
        return $sku !== '' ? esc_html($sku) : '<span style="color:#9ca3af;">—</span>';
    }

    public function column_price($item) {
        if (!function_exists('wc_get_product')) {
            return '—';
        }
        $product = wc_get_product($item['ID']);
        if (!$product) {
            return '—';
        }
        return '<span class="sc-shop-products-price">' . wp_kses_post($product->get_price_html()) . '</span>';
    }

    public function column_stock($item) {
        if (!function_exists('wc_get_product')) {
            return '—';
        }
        $product = wc_get_product($item['ID']);
        if (!$product) {
            return '—';
        }
        if (!$product->managing_stock()) {
            $status = $product->get_stock_status();
            $labels = [
                'instock' => ['موجود', 'sc-shop-products-pill--success'],
                'outofstock' => ['ناموجود', 'sc-shop-products-pill--danger'],
                'onbackorder' => ['پیش‌سفارش', 'sc-shop-products-pill--warning'],
            ];
            $info = $labels[$status] ?? [$status, 'sc-shop-products-pill--muted'];
            return '<span class="sc-shop-products-pill ' . esc_attr($info[1]) . '">' . esc_html($info[0]) . '</span>';
        }
        $qty = $product->get_stock_quantity();
        $class = ($qty !== null && (int) $qty <= 0) ? 'sc-shop-products-pill--danger' : 'sc-shop-products-pill--success';
        return '<span class="sc-shop-products-pill ' . esc_attr($class) . '">' . esc_html((string) $qty) . '</span>';
    }

    public function column_categories($item) {
        $terms = get_the_terms($item['ID'], 'product_cat');
        if (empty($terms) || is_wp_error($terms)) {
            return '<span style="color:#9ca3af;">—</span>';
        }
        $names = wp_list_pluck($terms, 'name');
        return esc_html(implode('، ', $names));
    }

    public function column_product_status($item) {
        $status = get_post_status($item['ID']);
        $labels = [
            'publish' => ['منتشر شده', 'sc-shop-products-pill--success'],
            'draft' => ['پیش‌نویس', 'sc-shop-products-pill--muted'],
            'pending' => ['در انتظار', 'sc-shop-products-pill--warning'],
            'private' => ['خصوصی', 'sc-shop-products-pill--purple'],
        ];
        $info = $labels[$status] ?? [$status, 'sc-shop-products-pill--muted'];
        return '<span class="sc-shop-products-pill ' . esc_attr($info[1]) . '">' . esc_html($info[0]) . '</span>';
    }

    public function column_date($item) {
        $ts = get_post_time('U', true, $item['ID']);
        return $ts ? esc_html(sc_date_shamsi(gmdate('Y-m-d H:i:s', $ts), 'Y/m/d H:i')) : '—';
    }

    public function column_default($item, $column_name) {
        return '';
    }

    public function no_items() {
        echo isset($_GET['s']) ? 'محصولی با این مشخصات یافت نشد.' : 'هنوز محصولی ثبت نشده است.';
    }

    public function prepare_items() {
        $this->process_bulk_action();

        $per_page = $this->get_items_per_page('list_products_per_page', 20);
        $current_page = max(1, $this->get_pagenum());
        $offset = ($current_page - 1) * $per_page;

        $orderby = sc_admin_sc_products_get_request_scalar_string('orderby', 'date');
        $order = strtoupper(sc_admin_sc_products_get_request_scalar_string('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $args = [
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'orderby' => $orderby === 'sku' || $orderby === 'price' ? 'meta_value' : $orderby,
            'order' => $order,
            's' => sc_admin_sc_products_get_request_scalar_string('s', ''),
        ];

        if ($orderby === 'sku') {
            $args['meta_key'] = '_sku';
        } elseif ($orderby === 'price') {
            $args['meta_key'] = '_price';
            $args['orderby'] = 'meta_value_num';
        } elseif ($orderby === 'title') {
            $args['orderby'] = 'title';
        }

        $filter_category = sc_admin_sc_products_get_request_scalar_int('filter_category', 0);
        $filter_tag = sc_admin_sc_products_get_request_scalar_int('filter_tag', 0);
        $filter_stock = sc_admin_sc_products_get_request_scalar_string('filter_stock', 'all');
        $filter_type = sc_admin_sc_products_get_request_scalar_string('filter_type', 'all');
        $filter_publish = sc_admin_sc_products_get_request_scalar_string('filter_publish', 'all');
        $filter_featured = sc_admin_sc_products_get_request_scalar_string('filter_featured', 'all');
        $filter_price_min = sc_admin_sc_products_get_request_scalar_string('filter_price_min', '');
        $filter_price_max = sc_admin_sc_products_get_request_scalar_string('filter_price_max', '');

        $tax_query = ['relation' => 'AND'];
        if ($filter_category > 0) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => [$filter_category],
            ];
        }
        if ($filter_tag > 0) {
            $tax_query[] = [
                'taxonomy' => 'product_tag',
                'field' => 'term_id',
                'terms' => [$filter_tag],
            ];
        }
        if ($filter_type !== 'all') {
            $tax_query[] = [
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => [$filter_type],
            ];
        }
        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }

        $meta_query = ['relation' => 'AND'];
        if ($filter_stock !== 'all') {
            $meta_query[] = [
                'key' => '_stock_status',
                'value' => $filter_stock,
                'compare' => '=',
            ];
        }
        if ($filter_featured === 'yes') {
            $meta_query[] = [
                'key' => '_featured',
                'value' => 'yes',
                'compare' => '=',
            ];
        } elseif ($filter_featured === 'no') {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => '_featured',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_featured',
                    'value' => 'yes',
                    'compare' => '!=',
                ],
            ];
        }
        if ($filter_price_min !== '' && is_numeric($filter_price_min)) {
            $meta_query[] = [
                'key' => '_price',
                'value' => (float) $filter_price_min,
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        }
        if ($filter_price_max !== '' && is_numeric($filter_price_max)) {
            $meta_query[] = [
                'key' => '_price',
                'value' => (float) $filter_price_max,
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }
        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }

        $search = sc_admin_sc_products_get_request_scalar_string('s', '');
        if ($search !== '') {
            $args['s'] = $search;
            add_filter('posts_search', [$this, 'extend_search_to_sku'], 10, 2);
        }

        if ($filter_publish !== 'all') {
            $args['post_status'] = [$filter_publish];
        }

        $query = new WP_Query($args);
        if ($search !== '') {
            remove_filter('posts_search', [$this, 'extend_search_to_sku'], 10);
        }
        $this->items = [];
        foreach ($query->posts as $post) {
            $this->items[] = ['ID' => $post->ID];
        }

        $this->set_pagination_args([
            'total_items' => (int) $query->found_posts,
            'per_page' => $per_page,
            'total_pages' => (int) ceil($query->found_posts / $per_page),
        ]);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }

    /**
     * جستجو در SKU علاوه بر عنوان محصول
     */
    public function extend_search_to_sku($search, $wp_query) {
        global $wpdb;
        if (empty($wp_query->query_vars['s'])) {
            return $search;
        }
        $term = $wp_query->query_vars['s'];
        $like = '%' . $wpdb->esc_like($term) . '%';
        $search .= $wpdb->prepare(
            " OR ({$wpdb->posts}.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s))",
            $like
        );
        return $search;
    }
}
