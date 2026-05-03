<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class orders_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'order',
            'plural' => 'orders',
            'ajax' => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'full_name' => 'شماره سفارش+نام مشتری',
            'date_order' => 'تاریخ سفارش',
            'phone' => 'شماره تماس',
            'status' => 'وضعیت',
            'items' => 'جزئیات',
            'product_categories' => 'دسته محصولات',
            'order_addresses' => 'آدرس',
            'total_amount' => 'هزینه کل',
        ];
    }
    public function column_full_name($item) {

         $full_name = $item->first_name . ' ' . $item->last_name;
            
         $url = add_query_arg(
        [
            'page'   => 'wc-orders',
            'action' => 'edit',
            'id'     => $item->id_order,
        ],
        admin_url('admin.php')
        );
        $order_number = $item->id_order;

        return sprintf(
        '<a href="%s" target="_blank"><strong>%s - %s#</strong></a>',
        esc_url($url),
        esc_html($full_name),esc_html($order_number)
        );

    }
    public function column_phone($item) {
            return sanitize_iran_phone($item->player_phone) ?? '-';
    }
    public function column_date_order($item) {
           $date = $item->date_created_gmt ?? '';
        if (empty($date)) {
            return '<span style="color: #999;">-</span>';
        }
        return esc_html(sc_date_shamsi($date, 'Y/m/d -- H:i'));
    }
    public function column_status($item) {
        $status = $item->status;
                
        // برچسب‌های وضعیت WooCommerce
                $status_labels = [
                    'wc-pending' => ['label' => 'در انتظار پرداخت', 'color' => '#f0a000', 'bg' => '#fff8e1'],
                    'wc-under_review' => ['label' => 'در حال بررسی', 'color' => '#2271b1', 'bg' => '#e5f5fa'],
                    'wc-on-hold' => ['label' => 'در حال بررسی', 'color' => '#2271b1', 'bg' => '#e5f5fa'],
                    'wc-processing' => ['label' => 'پرداخت شده', 'color' => '#00a32a', 'bg' => '#d4edda'],
                    'wc-completed' => ['label' => 'تایید پرداخت', 'color' => '#00a32a', 'bg' => '#d4edda'],
                    'wc-cancelled' => ['label' => 'لغو شده', 'color' => '#d63638', 'bg' => '#ffeaea'],
                    'wc-refunded' => ['label' => 'بازگشت شده', 'color' => '#d63638', 'bg' => '#ffeaea'],
                    'wc-failed' => ['label' => 'ناموفق', 'color' => '#d63638', 'bg' => '#ffeaea'],
                    'trash' => ['label' => 'پاک شده - زباله دان', 'color' => '#d63638', 'bg' => '#ffeaea'],
                    'wc-checkout-draft' => ['label' => 'در انتظار پرداحت', 'color' => '#d63638', 'bg' => '#ffeaea'],
                ];
                
                $status_info = isset($status_labels[$status]) ? $status_labels[$status] : ['label' => $status, 'color' => '#666', 'bg' => '#f5f5f5'];
                
                return sprintf(
                    '<span style="padding: 5px 10px; border-radius: 4px; font-weight: bold; background-color: %s; color: %s;">%s</span>',
                    esc_attr($status_info['bg']),
                    esc_attr($status_info['color']),
                    esc_html($status_info['label'])
                );
                }
                public function column_cb($item) {
                return '<input type="checkbox" value="' . esc_attr($item->id_order) . '" name="order[]" />';
            }
        public function column_items($item) {
                return $item->products_with_quantity;
        }

    public function column_product_categories($item) {
        $txt = isset($item->product_categories) ? trim((string) $item->product_categories) : '';
        if ($txt === '' && function_exists('wc_get_order')) {
            $order = wc_get_order((int) $item->id_order);
            if ($order) {
                $names = [];
                foreach ($order->get_items('line_item') as $li) {
                    if (!is_object($li) || !method_exists($li, 'get_product_id')) {
                        continue;
                    }
                    $pid = (int) $li->get_product_id();
                    if (!$pid) {
                        continue;
                    }
                    $id_for_terms = $pid;
                    if (function_exists('wc_get_product')) {
                        $prod = wc_get_product($pid);
                        if ($prod && $prod->is_type('variation')) {
                            $id_for_terms = (int) $prod->get_parent_id();
                        }
                    }
                    $terms = get_the_terms($id_for_terms, 'product_cat');
                    if (!empty($terms) && !is_wp_error($terms)) {
                        foreach ($terms as $t) {
                            $names[$t->term_id] = $t->name;
                        }
                    }
                }
                $txt = $names ? implode('، ', $names) : '';
            }
        }
        if ($txt === '') {
            return '<span style="color:#999;">—</span>';
        }
        return '<span style="font-size:12px;line-height:1.5;">' . esc_html($txt) . '</span>';
    }

    public function column_order_addresses($item) {
        $bill = isset($item->billing_address) ? trim((string) $item->billing_address) : '';
        $ship = isset($item->shipping_address) ? trim((string) $item->shipping_address) : '';

        if (($bill === '' && $ship === '') && function_exists('wc_get_order')) {
            $order = wc_get_order((int) $item->id_order);
            if ($order) {
                $bill = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(str_replace(['<br/>', '<br />', '<br>'], '، ', $order->get_formatted_billing_address()))));
                $ship = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(str_replace(['<br/>', '<br />', '<br>'], '، ', $order->get_formatted_shipping_address()))));
            }
        }

        $parts = [];
        if ($bill !== '') {
            $parts[] = '<strong style="display:block;margin-bottom:4px;">صورتحساب</strong><span style="font-size:12px;line-height:1.5;">' . esc_html($bill) . '</span>';
        }
        if ($ship !== '') {
            $parts[] = '<strong style="display:block;margin:6px 0 4px;">ارسال</strong><span style="font-size:12px;line-height:1.5;">' . esc_html($ship) . '</span>';
        }
        if (empty($parts)) {
            return '<span style="color:#999;">—</span>';
        }
        return '<div style="max-width:280px;">' . implode('', $parts) . '</div>';
    }

        public function column_total_amount($item) {
            $price = number_format((int) $item->total_amount);
            $order = wc_get_order($item->id_order) ?? 0;
            $pay='';
            if ($order) {
                        $pay_value = $order->get_meta('pay'); // دریافت مقدار فیلد دلخواه
                        if (!empty($pay_value)) {
                            $pay = '<strong>روش پرداخت :</strong> ' . esc_html($pay_value);
                        }
                    }

                return $price . ' ' . 'تومان' . '<br>' .  $pay ;
    }
    public function get_hidden_columns() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen ? get_hidden_columns($screen) : [];
    }  
    public function column_default($item, $column_name) {
        return '-';
    }
    public function no_items() {
        if (isset($_GET['s'])) {
            echo "سفارشی  با این مشخصات یافت نشد!";
        } else {
            echo "هنوز هیچ سفارشی ثبت نشده است.";
        }
    }
    public function get_sortable_columns() {
        return [
            'total_amount' => ['total_amount', true],
            'date_order' => ['date_order', true],
            'status' => ['status', true]
        ];
    }
     public function get_bulk_actions() {
        return [
            'mark_pending' => 'تغییر وضعیت به: در انتظار پرداخت',
            'mark_processing' => 'تغییر وضعیت به: پرداخت شده',
            'mark_on-hold' => 'تغییر وضعیت به: در حال بررسی',
            'mark_completed' => 'تغییر وضعیت به: تایید پرداخت',
            'mark_cancelled' => 'تغییر وضعیت به: لغو شده',
            'mark_failed' => 'تغییر وضعیت به: ناموفق',
            'mark_card_to_card' => 'پرداخت کارت به کارت',
            'delete' => 'حذف'
            

        ];
    }
    public function process_bulk_action() {
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }

        // دریافت ID های انتخاب شده
        $invoice_ids = isset($_POST['order']) ? $_POST['order'] : [];
        $invoice_ids = array_map('absint', $invoice_ids);
        if (empty($invoice_ids)) {
            return;
        }
      

        // بررسی nonce
        check_admin_referer('bulk-' . $this->_args['plural']);

        global $wpdb;
        $members_table = $wpdb->prefix . 'sc_members';
        $orders_table = $wpdb->prefix . 'wc_orders';
        $woocommerce_order_items_table = $wpdb->prefix . 'woocommerce_order_items';
        $woocommerce_order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

        // تعیین وضعیت جدید بر اساس action
        $new_status = '';
        switch ($action) {
            case 'mark_pending':
                $new_status = 'wc-pending';
                break;
            case 'mark_processing':
                $new_status = 'wc-processing';
                break;
            case 'mark_on-hold':
                $new_status = 'wc-on-hold';
                break;
            case 'mark_completed':
                $new_status = 'wc-completed';
                break;
            case 'mark_cancelled':
                $new_status = 'wc-cancelled';
                break;
            case 'mark_failed':
                $new_status = 'wc-failed';
                break;
            case 'delete':
                foreach ($invoice_ids as $invoice_id) {
                    $deleted = false;
                    if (function_exists('wc_get_order')) {
                        $order = wc_get_order($invoice_id);
                        if ($order) {
                            $order->delete(true);
                            $deleted = true;
                        }
                    }
                    if (!$deleted) {
                        $wpdb->delete($orders_table, ['id' => $invoice_id], ['%d']);
                        $wpdb->delete($woocommerce_order_items_table, ['order_id' => $invoice_id], ['%d']);
                    }
                    if (function_exists('sc_log_activity')) {
                        sc_log_activity('deleted', 'order', $invoice_id, 'سفارش #' . $invoice_id . ' حذف شد', null, null);
                    }
                }
                 
                
                wp_redirect(admin_url('admin.php?page=sc_orders&sc_status=bulk_deleted'));
                exit;

            case 'mark_card_to_card':
                $updated = 0;
                foreach ($invoice_ids as $invoice_id) {
                    $invoice = $wpdb->get_row($wpdb->prepare(
                        "SELECT wo.id 
                        from $orders_table wo
                        INNER JOIN $woocommerce_order_items_table woi ON wo.id = woi.order_id
                        WHERE woi.order_item_type NOT IN ('fee') AND wo.customer_id NOT IN (0) AND wo.id = %d
                        GROUP BY 
                            wo.id, wo.status, wo.total_amount, wo.payment_method_title;",
                                                $invoice_id
                    ));
                    if ($invoice && !empty($invoice->id) && function_exists('wc_get_order')) {
                        $order = wc_get_order((int) $invoice->id);
                        if ($order) {
                            $order->update_meta_data('pay', 'کارت به کارت');
                            $order->save();
                            $updated++;
                        }
                    }
                }
                wp_redirect(admin_url('admin.php?page=sc_orders&sc_status=pay_card_to_card&updated=' . $updated));
                exit;

            default:
                return;
        }

        // به‌روزرسانی وضعیت همه صورت حساب‌های انتخاب شده
        foreach ($invoice_ids as $invoice_id) {
            $update_data = [
                'status' => $new_status,
               
            ];
            $update_format = ['%s'];
            
            
            
            $wpdb->update(
                $orders_table,
                $update_data,
                ['id' => $invoice_id],
                $update_format,
                ['%d']
            );
            
            // اگر سفارش WooCommerce وجود دارد، وضعیت آن را هم به‌روزرسانی کن
            if (function_exists('wc_get_order')) {
                $invoice = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $orders_table WHERE id = %d",
                    $invoice_id
                ));
                
                if ($invoice) {
                    $order = wc_get_order((int) $invoice_id);
                    if ($order) {
                        $slug = preg_replace('/^wc-/', '', $new_status);
                        $order->update_status($slug, 'تغییر وضعیت از طریق bulk action');
                    }
                }
            }
        }

        if (function_exists('sc_log_activity') && !empty($new_status)) {
            sc_log_activity('updated', 'invoice', 0, 'وضعیت ' . count($invoice_ids) . ' صورتحساب به «' . $new_status . '» تغییر کرد', null, ['invoice_ids' => $invoice_ids, 'new_status' => $new_status]);
        }
        // ریدایرکت با پیام موفقیت
        wp_redirect(admin_url('admin.php?page=sc_orders&sc_status=bulk_status_updated'));
        exit;
    }    
    public function prepare_items() {
        global $wpdb;
        $this->process_bulk_action();

        $members_table = $wpdb->prefix . 'sc_members';
        $orders_table = $wpdb->prefix . 'wc_orders';
        $invoices_table = $wpdb->prefix . 'sc_invoices';
        $woocommerce_order_items_table = $wpdb->prefix . 'woocommerce_order_items';
        $woocommerce_order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

        $per_page = $this->get_items_per_page('list_orders_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
        $filter_product_cat = isset($_GET['filter_product_cat']) ? absint($_GET['filter_product_cat']) : 0;
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all';

        $filter_date_from = '';
        $filter_date_to = '';
        if (!empty($_GET['filter_date_from_shamsi']) && function_exists('sc_shamsi_to_gregorian_date')) {
            $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])));
        } elseif (!empty($_GET['filter_date_from'])) {
            $filter_date_from = sanitize_text_field(wp_unslash($_GET['filter_date_from']));
        }
        if (!empty($_GET['filter_date_to_shamsi']) && function_exists('sc_shamsi_to_gregorian_date')) {
            $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])));
        } elseif (!empty($_GET['filter_date_to'])) {
            $filter_date_to = sanitize_text_field(wp_unslash($_GET['filter_date_to']));
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $where_conditions = [
            'woi.order_item_type = %s',
            'wo.customer_id > 0',
        ];
        $where_values = ['line_item'];

        $type_col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$orders_table}` LIKE %s", 'type'));
        if ($type_col) {
            $where_conditions[] = 'wo.type = %s';
            $where_values[] = 'shop_order';
        }

        if ($filter_member > 0) {
            $where_conditions[] = 'sm.id = %d';
            $where_values[] = $filter_member;
        }

        if ($filter_product_cat > 0 && taxonomy_exists('product_cat')) {
            $posts_table = $wpdb->posts;
            $where_conditions[] = "EXISTS (
                SELECT 1 FROM {$woocommerce_order_items_table} woi_fc
                INNER JOIN {$woocommerce_order_itemmeta} oim_fc ON oim_fc.order_item_id = woi_fc.order_item_id AND oim_fc.meta_key = '_product_id'
                INNER JOIN {$posts_table} p_fc ON CAST(oim_fc.meta_value AS UNSIGNED) = p_fc.ID AND p_fc.post_type IN ('product','product_variation')
                INNER JOIN {$posts_table} pr_fc ON pr_fc.ID = IF(p_fc.post_type = 'product_variation', p_fc.post_parent, p_fc.ID)
                INNER JOIN {$wpdb->term_relationships} tr_fc ON tr_fc.object_id = pr_fc.ID
                INNER JOIN {$wpdb->term_taxonomy} tt_fc ON tt_fc.term_taxonomy_id = tr_fc.term_taxonomy_id AND tt_fc.taxonomy = 'product_cat' AND tt_fc.term_id = %d
                WHERE woi_fc.order_id = wo.id AND woi_fc.order_item_type = 'line_item'
            )";
            $where_values[] = $filter_product_cat;
        }

        if ($filter_status !== 'all' && $filter_status !== 'penalty') {
            if ($filter_status === 'completed') {
                $where_conditions[] = '(wo.status = %s OR wo.status = %s)';
                $where_values[] = 'wc-completed';
                $where_values[] = 'wc-processing';
            } elseif ($filter_status === 'on-hold') {
                $where_conditions[] = '(wo.status = %s OR wo.status = %s)';
                $where_values[] = 'wc-on-hold';
                $where_values[] = 'wc-under_review';
            } elseif ($filter_status === 'pending') {
                $where_conditions[] = '(wo.status = %s OR wo.status = %s)';
                $where_values[] = 'wc-pending';
                $where_values[] = 'wc-checkout-draft';
            } elseif ($filter_status === 'paid') {
                $where_conditions[] = 'wo.status = %s';
                $where_values[] = 'wc-completed';
            } else {
                $map = [
                    'processing' => 'wc-processing',
                    'cancelled' => 'wc-cancelled',
                    'failed' => 'wc-failed',
                    'refunded' => 'wc-refunded',
                ];
                if (isset($map[$filter_status])) {
                    $where_conditions[] = 'wo.status = %s';
                    $where_values[] = $map[$filter_status];
                }
            }
        } elseif ($filter_status === 'penalty') {
            $where_conditions[] = "EXISTS (SELECT 1 FROM {$invoices_table} invp WHERE invp.woocommerce_order_id = wo.id AND (invp.penalty_amount > 0 OR invp.penalty_applied = 1))";
        }

        if ($filter_date_from !== '') {
            $where_conditions[] = 'DATE(wo.date_created_gmt) >= %s';
            $where_values[] = $filter_date_from;
        }
        if ($filter_date_to !== '') {
            $where_conditions[] = 'DATE(wo.date_created_gmt) <= %s';
            $where_values[] = $filter_date_to;
        }

        if ($search !== '') {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = '(CAST(wo.id AS CHAR) LIKE %s OR sm.first_name LIKE %s OR sm.last_name LIKE %s OR sm.national_id LIKE %s OR sm.player_phone LIKE %s OR woi.order_item_name LIKE %s)';
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
        }

        $where_sql = implode(' AND ', $where_conditions);

        $from_sql = "
            FROM {$members_table} sm
            INNER JOIN {$orders_table} wo ON wo.customer_id = sm.user_id
            INNER JOIN {$woocommerce_order_items_table} woi ON woi.order_id = wo.id
            INNER JOIN {$woocommerce_order_itemmeta} woi_qty
                ON woi.order_item_id = woi_qty.order_item_id AND woi_qty.meta_key = '_qty'
            WHERE {$where_sql}
        ";

        $count_sql = "SELECT COUNT(DISTINCT wo.id) {$from_sql}";
        $total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $where_values));

        $orderby_raw = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'date_order';
        $order_dir = isset($_GET['order']) && strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) === 'ASC' ? 'ASC' : 'DESC';
        $orderby_sql = 'wo.date_created_gmt';
        if ($orderby_raw === 'total_amount') {
            $orderby_sql = 'wo.total_amount';
        } elseif ($orderby_raw === 'status') {
            $orderby_sql = 'wo.status';
        } elseif ($orderby_raw === 'date_order') {
            $orderby_sql = 'wo.date_created_gmt';
        }

        $posts_table = $wpdb->posts;
        $extra_select = ",
            (SELECT GROUP_CONCAT(DISTINCT t_pc.name ORDER BY t_pc.name SEPARATOR '، ')
                FROM {$woocommerce_order_items_table} woi_pc
                INNER JOIN {$woocommerce_order_itemmeta} oim_pc ON oim_pc.order_item_id = woi_pc.order_item_id AND oim_pc.meta_key = '_product_id'
                INNER JOIN {$posts_table} p_pc ON CAST(oim_pc.meta_value AS UNSIGNED) = p_pc.ID AND p_pc.post_type IN ('product','product_variation')
                INNER JOIN {$posts_table} pr_pc ON pr_pc.ID = IF(p_pc.post_type = 'product_variation', p_pc.post_parent, p_pc.ID)
                INNER JOIN {$wpdb->term_relationships} tr_pc ON tr_pc.object_id = pr_pc.ID
                INNER JOIN {$wpdb->term_taxonomy} tt_pc ON tt_pc.term_taxonomy_id = tr_pc.term_taxonomy_id AND tt_pc.taxonomy = 'product_cat'
                INNER JOIN {$wpdb->terms} t_pc ON t_pc.term_id = tt_pc.term_id
                WHERE woi_pc.order_id = wo.id AND woi_pc.order_item_type = 'line_item'
            ) AS product_categories";

        $addr_tbl = $wpdb->prefix . 'wc_order_addresses';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $addr_tbl)) === $addr_tbl) {
            $extra_select .= ",
            (SELECT TRIM(CONCAT_WS('، ', NULLIF(oa_b.address_1, ''), NULLIF(oa_b.address_2, ''), NULLIF(oa_b.city, ''), NULLIF(oa_b.state, ''), NULLIF(oa_b.postcode, '')))
                FROM {$addr_tbl} oa_b WHERE oa_b.order_id = wo.id AND oa_b.address_type = 'billing' LIMIT 1) AS billing_address,
            (SELECT TRIM(CONCAT_WS('، ', NULLIF(oa_s.address_1, ''), NULLIF(oa_s.address_2, ''), NULLIF(oa_s.city, ''), NULLIF(oa_s.state, ''), NULLIF(oa_s.postcode, '')))
                FROM {$addr_tbl} oa_s WHERE oa_s.order_id = wo.id AND oa_s.address_type = 'shipping' LIMIT 1) AS shipping_address";
        } else {
            $extra_select .= ",
            NULL AS billing_address,
            NULL AS shipping_address";
        }

        $data_sql = "SELECT
            wo.id AS id_order,
            sm.first_name,
            sm.last_name,
            sm.player_phone,
            wo.status,
            wo.payment_method_title,
            GROUP_CONCAT(
                CONCAT(woi.order_item_name, ' (', woi_qty.meta_value, ')')
                ORDER BY woi.order_item_id
                SEPARATOR '<br>'
            ) AS products_with_quantity,
            wo.total_amount,
            wo.date_created_gmt
            {$extra_select}
            {$from_sql}
            GROUP BY wo.id, sm.first_name, sm.last_name, sm.player_phone, wo.status, wo.total_amount, wo.payment_method_title, wo.date_created_gmt
            ORDER BY {$orderby_sql} {$order_dir}
            LIMIT %d OFFSET %d";

        $data_values = array_merge($where_values, [$per_page, $offset]);
        $this->items = $wpdb->get_results($wpdb->prepare($data_sql, $data_values));

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => (int) ceil(max(1, $total_items) / max(1, $per_page)),
        ]);

        $this->_column_headers = [$this->get_columns(), $this->get_hidden_columns(), $this->get_sortable_columns()];
    }
        


}