<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Wallet_Transactions_List_Table extends WP_List_Table {

    public function __construct($args = []) {
        parent::__construct([
            'singular' => 'transaction',
            'plural' => 'transactions',
            'ajax' => false
        ]);
    }

    public function get_columns() {
        return [
            'member_name' => 'بازیکن',
            'transaction_type' => 'نوع',
            'amount' => 'مبلغ',
            'balance_after' => 'موجودی بعد',
            'description' => 'توضیحات',
            'status' => 'وضعیت',
            'created_at' => 'تاریخ'
        ];
    }

    protected function get_sortable_columns() {
        return [
            'created_at' => ['created_at', false],
            'amount' => ['amount', false]
        ];
    }

    public function get_hidden_columns() {
        return get_hidden_columns(get_current_screen());
    }

    public function column_member_name($item) {
        $name = trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? ''));
        if (empty($name)) {
            return '<span style="color: #999;">-</span>';
        }
        
        $national_id = $item->national_id ?? '';
        $output = '<strong>' . esc_html($name) . '</strong>';
        if ($national_id) {
            $output .= '<br><small style="color: #666;">' . esc_html($national_id) . '</small>';
        }
        return $output;
    }

    public function column_transaction_type($item) {
        $type_labels = [
            'charge' => 'شارژ',
            'deduct' => 'کاهش',
            'payment' => 'پرداخت',
            'refund' => 'بازگشت وجه',
            'session_fee' => 'کسر جلسه'
        ];
        
        $type = $item->transaction_type ?? '';
        $label = $type_labels[$type] ?? $type;

        $type_icons = [
            'charge' => '➕',
            'deduct' => '➖',
            'payment' => '💳',
            'refund' => '↩',
            'session_fee' => '📋'
        ];
        
        $colors = [
            'charge' => '#00a32a',
            'deduct' => '#d63638',
            'payment' => '#2271b1',
            'refund' => '#f0b849',
            'session_fee' => '#856404'
        ];
        
        $color = $colors[$type] ?? '#666';
        $icon  = $type_icons[$type] ?? '';
        return '<span style="color: ' . esc_attr($color) . '; font-weight: bold;">' .
               '<span style="margin-left:3px;">' . esc_html($icon) . '</span>' .
               esc_html($label) .
               '</span>';
    }

    public function column_amount($item) {
        $amount = floatval($item->amount ?? 0);
        if (function_exists('wc_price')) {
            return wc_price($amount);
        }
        return (function_exists('sc_format_amount_display') ? sc_format_amount_display($amount) : number_format($amount, 0, '.', ',')) . ' تومان';
    }

    public function column_balance_after($item) {
        $balance = floatval($item->balance_after ?? 0);
        $color = $balance < 0 ? '#d63638' : ($balance == 0 ? '#666' : '#00a32a');
        
        if (function_exists('wc_price')) {
            $formatted = wc_price($balance);
        } else {
            $formatted = (function_exists('sc_format_amount_display') ? sc_format_amount_display($balance) : number_format($balance, 0, '.', ',')) . ' تومان';
        }
        
        return '<span style="color: ' . esc_attr($color) . '; font-weight: bold;">' . $formatted . '</span>';
    }

    public function column_description($item) {
        $description = $item->description ?? '';
        if (empty($description)) {
            return '<span style="color: #999;">-</span>';
        }
        
        $truncated = mb_strlen($description) > 50 ? mb_substr($description, 0, 50) . '...' : $description;
        return '<span title="' . esc_attr($description) . '">' . esc_html($truncated) . '</span>';
    }

    public function column_status($item) {
        $status = $item->status ?? '';
        $status_labels = [
            'completed' => 'تکمیل شده',
            'pending' => 'در انتظار',
            'failed' => 'ناموفق',
            'cancelled' => 'لغو شده'
        ];
        
        $label = $status_labels[$status] ?? $status;
        
        $colors = [
            'completed' => '#00a32a',
            'pending' => '#f0b849',
            'failed' => '#d63638',
            'cancelled' => '#666'
        ];
        
        $color = $colors[$status] ?? '#666';
        return '<span style="color: ' . esc_attr($color) . '; font-weight: bold;">' . esc_html($label) . '</span>';
    }

    public function column_created_at($item) {
        $date = $item->created_at ?? '';
        if (empty($date)) {
            return '<span style="color: #999;">-</span>';
        }
        return esc_html(sc_date_shamsi($date, 'Y/m/d H:i'));
    }

    public function column_default($item, $column_name) {
        return '-';
    }

    public function prepare_items() {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'sc_wallet_transactions';
        $members_table = $wpdb->prefix . 'sc_members';
        
        $per_page = $this->get_items_per_page('wallet_transactions_per_page', 20);
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
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // محاسبه تعداد کل
        $count_query = "SELECT COUNT(*) FROM $transactions_table wt
                        LEFT JOIN $members_table m ON wt.member_id = m.id
                        $where_clause";
        
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
        $query = "SELECT wt.*, 
                         m.first_name, 
                         m.last_name, 
                         m.national_id
                  FROM $transactions_table wt
                  LEFT JOIN $members_table m ON wt.member_id = m.id
                  $where_clause
                  ORDER BY wt.$orderby $order
                  LIMIT %d OFFSET %d";
        
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

