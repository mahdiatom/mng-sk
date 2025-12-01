<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

if (!class_exists('Invoices_List_Table')) {
class Invoices_List_Table extends WP_List_Table {

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'row' => 'ردیف',
            'order_number' => 'سفارش',
            'member_name' => 'نام و نام خانوادگی کاربر',
            'status' => 'وضعیت',
            'created_at' => 'تاریخ ثبت سفارش',
            'course_title' => 'جزئیات سفارش',
            'total_amount' => 'مجموع قیمت',
            'phone' => 'شماره تماس'
        ];
    }

    public function column_row($item) {
        static $row_number = 0;
        $page = $this->get_pagenum();
        $per_page = $this->get_items_per_page('invoices_per_page', 20);
        $row_number++;
        return (($page - 1) * $per_page) + $row_number;
    }

    public function column_order_number($item) {
        $order_number = '#' . $item['id'];
        
        // اگر woocommerce_order_id وجود دارد، از شماره سفارش WooCommerce استفاده کن
        if (!empty($item['woocommerce_order_id'])) {
            if (function_exists('wc_get_order')) {
                $order = wc_get_order($item['woocommerce_order_id']);
                if ($order) {
                    $order_number = $order->get_order_number();
                } else {
                    $order_number = '#' . $item['woocommerce_order_id'];
                }
            } else {
                $order_number = '#' . $item['woocommerce_order_id'];
            }
        }
        
        return '<strong>' . esc_html($order_number) . '</strong>';
    }

    public function column_member_name($item) {
        return esc_html($item['first_name'] . ' ' . $item['last_name']);
    }

    public function column_status($item) {
        $status = $item['status'];
        $status_labels = [
            'pending' => ['label' => 'در انتظار پرداخت', 'color' => '#f0a000', 'bg' => '#fff8e1'],
            'processing' => ['label' => 'در حال پردازش', 'color' => '#2271b1', 'bg' => '#e5f5fa'],
            'completed' => ['label' => 'تکمیل شده', 'color' => '#00a32a', 'bg' => '#d4edda'],
            'cancelled' => ['label' => 'لغو شده', 'color' => '#d63638', 'bg' => '#ffeaea'],
            'refunded' => ['label' => 'بازگشت شده', 'color' => '#666', 'bg' => '#f5f5f5']
        ];
        
        $status_info = isset($status_labels[$status]) ? $status_labels[$status] : ['label' => $status, 'color' => '#666', 'bg' => '#f5f5f5'];
        
        return sprintf(
            '<span style="padding: 5px 10px; border-radius: 4px; font-weight: bold; background-color: %s; color: %s;">%s</span>',
            esc_attr($status_info['bg']),
            esc_attr($status_info['color']),
            esc_html($status_info['label'])
        );
    }

    public function column_created_at($item) {
        $date = date_i18n('Y/m/d H:i', strtotime($item['created_at']));
        return $date;
    }

    public function column_course_title($item) {
        $course_title = $item['course_title'] ?? '';
        $course_price = isset($item['course_price']) ? floatval($item['course_price']) : 0;
        $expense_name = $item['expense_name'] ?? '';
        $total_amount = isset($item['amount']) ? floatval($item['amount']) : 0;
        
        $parts = [];
        
        // نمایش دوره
        if (!empty($course_title) && trim($course_title) !== '') {
            $course_display = esc_html($course_title);
            if ($course_price > 0) {
                $course_display .= ' (' . number_format($course_price, 0, '.', ',') . ' تومان)';
            }
            $parts[] = '<strong>دوره:</strong> ' . $course_display;
        }
        
        // نمایش هزینه اضافی
        if (!empty($expense_name) && trim($expense_name) !== '') {
            $expense_display = esc_html($expense_name);
            // محاسبه مبلغ هزینه اضافی
            $expense_amount = $total_amount - $course_price;
            if ($expense_amount > 0) {
                $expense_display .= ' (' . number_format($expense_amount, 0, '.', ',') . ' تومان)';
            }
            $parts[] = '<strong>هزینه اضافی:</strong> ' . $expense_display;
        }
        
        if (empty($parts)) {
            return '<span style="color: #999; font-style: italic;">بدون دوره</span>';
        }
        
        return '<div style="line-height: 1.8;">' . implode('<br>', $parts) . '</div>';
    }

    public function column_total_amount($item) {
        $total = (float)$item['amount'] + (float)($item['penalty_amount'] ?? 0);
        
        if (function_exists('wc_price')) {
            return wc_price($total);
        } else {
            return number_format($total, 0, '.', ',') . ' تومان';
        }
    }

    public function column_phone($item) {
        $phone = $item['player_phone'] ?? '-';
        return esc_html($phone);
    }

    public function column_cb($item) {
        return '<input type="checkbox" value="' . $item['id'] . '" name="invoice[]" />';
    }

    public function column_default($item, $column_name) {
        return '-';
    }

    public function get_hidden_columns() {
        return get_hidden_columns(get_current_screen());
    }

    public function no_items() {
        if (isset($_GET['s'])) {
            echo "صورت حسابی با این مشخصات یافت نشد!";
        } else {
            echo "هنوز صورت حسابی ثبت نشده است.";
        }
    }

    public function get_sortable_columns() {
        return [
            'created_at' => ['created_at', true],
            'status' => ['status', false],
            'total_amount' => ['amount', true]
        ];
    }

    public function get_bulk_actions() {
        return [
            'change_status_pending' => 'تغییر وضعیت به: در انتظار پرداخت',
            'change_status_processing' => 'تغییر وضعیت به: در حال پردازش',
            'change_status_completed' => 'تغییر وضعیت به: تکمیل شده',
            'change_status_cancelled' => 'تغییر وضعیت به: لغو شده',
            'delete' => 'حذف'
        ];
    }

    public function process_bulk_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_invoices';

        $action = $this->current_action();
        
        if (!$action || !isset($_GET['invoice']) || !is_array($_GET['invoice'])) {
            return;
        }

        check_admin_referer('bulk-' . $this->_args['plural']);

        $invoice_ids = array_map('absint', $_GET['invoice']);

        switch ($action) {
            case 'change_status_pending':
                foreach ($invoice_ids as $invoice_id) {
                    $wpdb->update(
                        $table_name,
                        ['status' => 'pending', 'updated_at' => current_time('mysql')],
                        ['id' => $invoice_id],
                        ['%s', '%s'],
                        ['%d']
                    );
                }
                wp_redirect(admin_url('admin.php?page=sc-invoices&sc_status=bulk_status_updated'));
                exit;

            case 'change_status_processing':
                foreach ($invoice_ids as $invoice_id) {
                    $wpdb->update(
                        $table_name,
                        ['status' => 'processing', 'updated_at' => current_time('mysql')],
                        ['id' => $invoice_id],
                        ['%s', '%s'],
                        ['%d']
                    );
                }
                wp_redirect(admin_url('admin.php?page=sc-invoices&sc_status=bulk_status_updated'));
                exit;

            case 'change_status_completed':
                foreach ($invoice_ids as $invoice_id) {
                    $wpdb->update(
                        $table_name,
                        [
                            'status' => 'completed',
                            'payment_date' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        ],
                        ['id' => $invoice_id],
                        ['%s', '%s', '%s'],
                        ['%d']
                    );
                }
                wp_redirect(admin_url('admin.php?page=sc-invoices&sc_status=bulk_status_updated'));
                exit;

            case 'change_status_cancelled':
                foreach ($invoice_ids as $invoice_id) {
                    $wpdb->update(
                        $table_name,
                        ['status' => 'cancelled', 'updated_at' => current_time('mysql')],
                        ['id' => $invoice_id],
                        ['%s', '%s'],
                        ['%d']
                    );
                }
                wp_redirect(admin_url('admin.php?page=sc-invoices&sc_status=bulk_status_updated'));
                exit;

            case 'delete':
                foreach ($invoice_ids as $invoice_id) {
                    $wpdb->delete($table_name, ['id' => $invoice_id], ['%d']);
                }
                wp_redirect(admin_url('admin.php?page=sc-invoices&sc_status=bulk_deleted'));
                exit;
        }
    }

    public function get_views() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_invoices';

        // دریافت فیلترهای فعال
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
        $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
        $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
        $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // ساخت WHERE clause برای شمارش
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if ($filter_course > 0) {
            $where_conditions[] = "i.course_id = %d";
            $where_values[] = $filter_course;
        }
        
        if ($filter_member > 0) {
            $where_conditions[] = "i.member_id = %d";
            $where_values[] = $filter_member;
        }
        
        if ($filter_date_from) {
            $where_conditions[] = "DATE(i.created_at) >= %s";
            $where_values[] = $filter_date_from;
        }
        
        if ($filter_date_to) {
            $where_conditions[] = "DATE(i.created_at) <= %s";
            $where_values[] = $filter_date_to;
        }
        
        if ($search) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            if (is_numeric($search)) {
                $where_conditions[] = "(i.id = %d OR m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)";
                $where_values[] = intval($search);
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
            } else {
                $where_conditions[] = "(m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)";
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);

        $count_query = "SELECT COUNT(*) FROM $table_name i 
                        INNER JOIN {$wpdb->prefix}sc_members m ON i.member_id = m.id 
                        WHERE $where_clause";
        
        if (!empty($where_values)) {
            $count_all = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
        } else {
            $count_all = $wpdb->get_var($count_query);
        }

        $statuses = ['all' => 'همه', 'pending' => 'در انتظار پرداخت', 'processing' => 'در حال پردازش', 'completed' => 'تکمیل شده', 'cancelled' => 'لغو شده'];
        $views = [];

        foreach ($statuses as $status_key => $status_label) {
            $count = $count_all;
            
            if ($status_key !== 'all') {
                $count_where = $where_conditions;
                $count_where_values = $where_values;
                $count_where[] = "i.status = %s";
                $count_where_values[] = $status_key;
                $count_where_clause = implode(' AND ', $count_where);
                
                $count_query_status = "SELECT COUNT(*) FROM $table_name i 
                                       INNER JOIN {$wpdb->prefix}sc_members m ON i.member_id = m.id 
                                       WHERE $count_where_clause";
                
                if (!empty($count_where_values)) {
                    $count = $wpdb->get_var($wpdb->prepare($count_query_status, $count_where_values));
                } else {
                    $count = $wpdb->get_var($count_query_status);
                }
            }

            $url = admin_url('admin.php?page=sc-invoices');
            if ($status_key !== 'all') {
                $url = add_query_arg('filter_status', $status_key, $url);
            }
            if ($filter_course) {
                $url = add_query_arg('filter_course', $filter_course, $url);
            }
            if ($filter_member) {
                $url = add_query_arg('filter_member', $filter_member, $url);
            }
            if ($filter_date_from) {
                $url = add_query_arg('filter_date_from', $filter_date_from, $url);
            }
            if ($filter_date_to) {
                $url = add_query_arg('filter_date_to', $filter_date_to, $url);
            }
            if ($search) {
                $url = add_query_arg('s', $search, $url);
            }

            $class = ($filter_status === $status_key || ($status_key === 'all' && $filter_status === 'all')) ? 'current' : '';
            $views[$status_key] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url($url),
                $class,
                $status_label,
                $count
            );
        }

        return $views;
    }

    public function prepare_items() {
        $this->process_bulk_action();

        global $wpdb;
        $invoices_table = $wpdb->prefix . 'sc_invoices';
        $members_table = $wpdb->prefix . 'sc_members';
        $courses_table = $wpdb->prefix . 'sc_courses';

        $per_page = $this->get_items_per_page('invoices_per_page', 20);
        $page = $this->get_pagenum();
        $offset = ($page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';
        
        // امنیت برای orderby
        $allowed_orderby = ['created_at', 'status', 'amount', 'id'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'created_at';
        }
        
        // امنیت برای order
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }

        // دریافت فیلترها
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
        $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
        $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
        $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // ساخت WHERE clause
        $where_conditions = ['1=1'];
        $where_values = [];

        if ($filter_status !== 'all') {
            $where_conditions[] = "i.status = %s";
            $where_values[] = $filter_status;
        }

        if ($filter_course > 0) {
            $where_conditions[] = "i.course_id = %d";
            $where_values[] = $filter_course;
        }

        if ($filter_member > 0) {
            $where_conditions[] = "i.member_id = %d";
            $where_values[] = $filter_member;
        }

        if ($filter_date_from) {
            $where_conditions[] = "DATE(i.created_at) >= %s";
            $where_values[] = $filter_date_from;
        }

        if ($filter_date_to) {
            $where_conditions[] = "DATE(i.created_at) <= %s";
            $where_values[] = $filter_date_to;
        }

        if ($search) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            
            // اگر عدد است، جستجو بر اساس ID
            if (is_numeric($search)) {
                $where_conditions[] = "(i.id = %d OR m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)";
                $where_values[] = intval($search);
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
            } else {
                // جستجو بر اساس نام، نام خانوادگی یا کد ملی
                $where_conditions[] = "(m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)";
                $where_values[] = $search_like;
                $where_values[] = $search_like;
                $where_values[] = $search_like;
            }
        }

        $where_clause = implode(' AND ', $where_conditions);
        
        // ساخت query
        $base_query = "SELECT SQL_CALC_FOUND_ROWS 
                    i.id,
                    i.member_id,
                    i.course_id,
                    i.woocommerce_order_id,
                    i.amount,
                    i.expense_name,
                    i.penalty_amount,
                    i.status,
                    i.payment_date,
                    i.created_at,
                    i.updated_at,
                    m.first_name,
                    m.last_name,
                    m.player_phone,
                    c.title as course_title,
                    c.price as course_price
                  FROM $invoices_table i
                  INNER JOIN $members_table m ON i.member_id = m.id
                  LEFT JOIN $courses_table c ON i.course_id = c.id AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')
                  WHERE $where_clause
                  ORDER BY i.$orderby $order
                  LIMIT %d OFFSET %d";

        $query_values = array_merge($where_values, [$per_page, $offset]);

        if (!empty($query_values)) {
            $results = $wpdb->get_results($wpdb->prepare($base_query, $query_values), ARRAY_A);
        } else {
            $results = $wpdb->get_results($base_query, ARRAY_A);
        }

        $this->set_pagination_args([
            'total_items' => $wpdb->get_var("SELECT FOUND_ROWS()"),
            'per_page' => $per_page
        ]);

        $this->_column_headers = [$this->get_columns(), $this->get_hidden_columns(), $this->get_sortable_columns()];
        $this->items = $results;
    }
}
} // End if (!class_exists('Invoices_List_Table'))
?>

