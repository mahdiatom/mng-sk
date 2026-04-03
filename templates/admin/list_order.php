<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class orders_List_Table extends WP_List_Table {

    public function display() {

        
        echo '<form method="post">';
        wp_nonce_field('bulk-' . $this->_args['plural']);
        
        parent::display();
      
        echo '</form>';
    }

    
   
    public function get_columns() {
        return [
             'cb' => '<input type="checkbox" />',
             'full_name' => 'شماره سفارش+نام مشتری',
            'date_order' => 'تاریخ سفارش',
           'phone' => 'شماره تماس',
           'status' => 'وضعیت',
           'items' => 'جزئیات',
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
                    'wc-on-hold' => ['label' => 'در حال بررسی', 'color' => '#2271b1', 'bg' => '#e5f5fa'],
                    'wc-processing' => ['label' => 'پرداخت شده', 'color' => '#00a32a', 'bg' => '#d4edda'],
                    'wc-completed' => ['label' => 'تایید پرداخت', 'color' => '#00a32a', 'bg' => '#d4edda'],
                    'wc-cancelled' => ['label' => 'لغو شده', 'color' => '#d63638', 'bg' => '#ffeaea'],
                    'wc-refunded' => ['label' => 'بازگشت شده', 'color' => '#d63638', 'bg' => '#ffeaea'],
                    'wc-failed' => ['label' => 'ناموفق', 'color' => '#d63638', 'bg' => '#ffeaea'],
                    'trash' => ['label' => 'پاک شده - زباله دان', 'color' => '#d63638', 'bg' => '#ffeaea'],
                    'wc-checkout-draft' => ['label' => 'پیش نویس', 'color' => '#d63638', 'bg' => '#ffeaea'],
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
                return '<input type="checkbox" value="' . $item->id_order . ' " name="order[]" />';
            }
        public function column_items($item) {
                return $item->products_with_quantity;
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
        return get_hidden_columns(get_current_screen());
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
        print_r($_POST);
            // print_r($action);

        // دریافت ID های انتخاب شده
        $invoice_ids = isset($_POST['order']) ? $_POST['order'] : [];
        $invoice_ids = array_map('absint', $invoice_ids);
        print_r($invoice_ids);
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
                // حذف صورت حساب‌ها
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
                    
                    // حذف سفارش WooCommerce اگر وجود دارد
                    if ($invoice && !empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
                        $order = wc_get_order($invoice->woocommerce_order_id);
                        if ($order) {
                            $order->delete(true); // true = force delete
                        }
                    }
                    
                    // حذف صورت حساب
                    $wpdb->delete($orders_table, ['id' => $invoice_id], ['%d']);
                    $wpdb->delete($woocommerce_order_items_table, ['order_id' => $invoice_id], ['%d']);


                    if (function_exists('sc_log_activity') && $invoice) {
                        sc_log_activity('deleted', 'invoice', $invoice_id, 'صورتحساب #' . $invoice_id . ' (عضو ' . $invoice->member_id . ') حذف شد', (array) $invoice, null);
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
                        $order = wc_get_order($invoice->id);
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
                    $order = wc_get_order($invoice->woocommerce_order_id);
                    if ($order) {
                        $order->update_status($new_status, 'تغییر وضعیت از طریق bulk action');
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
        $woocommerce_order_items_table = $wpdb->prefix . 'woocommerce_order_items';
        $woocommerce_order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';
        
        
        $per_page = $this->get_items_per_page('list_orders_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // پردازش فیلترها
        $filter_member     = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
        $filter_type       = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';
        $filter_status     = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_date_from  = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
        $filter_date_to    = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
        
        $search            = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // ساخت شرط WHERE
        $where_conditions = [];
        $where_values = [];
        
        if ($filter_member > 0) {
            $where_conditions[] = "wt.member_id = %d";
            $where_values[] = $filter_member;
        }
        
        if ($filter_type !== 'all') {
            $where_conditions[] = "wt.transaction_type = %s";
            $where_values[] = $filter_type;
        }
        
        if ($filter_status !== 'all') {
            $where_conditions[] = "wt.status = %s";
            $where_values[] = $filter_status;
        }

        if ($filter_date_from) {
            $where_conditions[] = "DATE(wt.created_at) >= %s";
            $where_values[]     = $filter_date_from;
        }

        if ($filter_date_to) {
            $where_conditions[] = "DATE(wt.created_at) <= %s";
            $where_values[]     = $filter_date_to;
        }

     
        
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = "(m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s OR wt.description LIKE %s)";
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
        }
        
        $where_clause = " woi.order_item_type = 'line_item' ";
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // محاسبه تعداد کل
        $count_query = "SELECT COUNT(*) AS total_records
        FROM (
            SELECT 
                wo.id AS id_order
            FROM 
                $members_table sm
            INNER JOIN $orders_table wo ON wo.customer_id = sm.user_id
            INNER JOIN $woocommerce_order_items_table woi ON woi.order_id = wo.id
            -- تعداد هر محصول (از order_itemmeta)
            INNER JOIN $woocommerce_order_itemmeta woi_qty 
                ON woi.order_item_id = woi_qty.order_item_id 
                AND woi_qty.meta_key = '_qty'
            WHERE 
                woi.order_item_type = 'line_item'
            GROUP BY 
                wo.id, sm.first_name, sm.last_name, sm.player_phone, wo.status, wo.total_amount, wo.payment_method_title
        ) AS subquery;";
                
                if (!empty($where_values)) {
                    $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
                } else {
                    $total_items = $wpdb->get_var($count_query);
                }
                
                // مرتب‌سازی
                $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
                if (!in_array($orderby, ['created_at', 'amount'])) {
                    $orderby = 'created_at';
                }
                
                $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
                
                // دریافت داده‌ها
                $query = "SELECT 
            wo.id AS id_order,
            sm.first_name,
            sm.last_name,
            sm.player_phone,
            wo.status,
            wo.payment_method_title,
            GROUP_CONCAT(
                CONCAT(
                    woi.order_item_name,
                    ' (', 
                    woi_qty.meta_value, 
                    ')'
                ) 
                SEPARATOR '<br>'
            ) AS products_with_quantity,
            wo.total_amount,
            wo.payment_method_title,
            wo.date_created_gmt
        FROM 
            $members_table sm
            INNER JOIN $orders_table wo ON wo.customer_id = sm.user_id
            INNER JOIN $woocommerce_order_items_table woi ON woi.order_id = wo.id
            -- تعداد هر محصول (از order_itemmeta)
            INNER JOIN $woocommerce_order_itemmeta woi_qty 
                ON woi.order_item_id = woi_qty.order_item_id 
                AND woi_qty.meta_key = '_qty'
        WHERE 
            $where_clause  -- فقط محصولات

        GROUP BY 
            wo.id, sm.first_name, sm.last_name, sm.player_phone, wo.status, wo.total_amount, wo.payment_method_title
        ORDER BY 
            wo.id DESC";
                
                $where_values[] = $per_page;
                $where_values[] = $offset;
                
                $this->items = $wpdb->get_results($wpdb->prepare($query, $where_values));
                
                $this->set_pagination_args([
                    'total_items' => $total_items,
                    'per_page' => $per_page
                ]);
                
                $this->_column_headers = [$this->get_columns(), $this->get_hidden_columns(), $this->get_sortable_columns()];
    }
        


}