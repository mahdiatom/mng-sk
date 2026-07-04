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
            'id' => 'شناسه',
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
            'id' => ['id', false],
            'created_at' => ['created_at', false],
            'amount' => ['amount', false]
        ];
    }

    public function get_hidden_columns() {
        return get_hidden_columns(get_current_screen());
    }

    protected function get_primary_column_name() {
        return 'member_name';
    }

    protected function sc_format_amount($amount) {
        $amount = floatval($amount);
        if (function_exists('sc_format_amount_display')) {
            return sc_format_amount_display($amount) . ' تومان';
        }
        return number_format($amount, 0, '.', ',') . ' تومان';
    }

    protected function sc_is_credit_type($type) {
        return in_array($type, ['charge', 'refund'], true);
    }

    public function column_id($item) {
        $id = absint($item->id ?? 0);
        return '<span class="sc-wallet-tx-id">#' . esc_html($id) . '</span>';
    }

    public function column_member_name($item) {
        $name = trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? ''));
        if ($name === '') {
            return '<span class="sc-badge sc-badge--muted">—</span>';
        }

        $national_id = $item->national_id ?? '';
        $photo = !empty($item->personal_photo) ? $item->personal_photo : '';
        $member_id = absint($item->member_id ?? 0);

        $initials = '';
        $fn = trim((string) ($item->first_name ?? ''));
        $ln = trim((string) ($item->last_name ?? ''));
        if ($fn !== '') {
            $initials .= mb_substr($fn, 0, 1);
        }
        if ($ln !== '') {
            $initials .= mb_substr($ln, 0, 1);
        }
        if ($initials === '') {
            $initials = '؟';
        }

        if ($photo) {
            $avatar_html = '<span class="sc-member-avatar"><img src="' . esc_url($photo) . '" alt="" loading="lazy"></span>';
        } else {
            $avatar_html = '<span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true">' . esc_html($initials) . '</span>';
        }

        $meta_parts = [];
        if ($national_id !== '') {
            $meta_parts[] = '<span class="sc-member-meta-item">' . esc_html($national_id) . '</span>';
        }
        $meta_html = !empty($meta_parts)
            ? '<span class="sc-member-meta">' . implode('<span class="sc-member-meta-dot"></span>', $meta_parts) . '</span>'
            : '';

        $name_html = esc_html($name);
        if ($member_id > 0) {
            $view_url = admin_url('admin.php?page=sc-wallet-manage&member_id=' . $member_id);
            $name_html = '<a class="sc-member-name" href="' . esc_url($view_url) . '">' . $name_html . '</a>';
        } else {
            $name_html = '<span class="sc-member-name">' . $name_html . '</span>';
        }

        $actions = [];
        if ($member_id > 0) {
            $actions['manage'] = '<a href="' . esc_url(admin_url('admin.php?page=sc-wallet-manage&member_id=' . $member_id)) . '">مدیریت شارژ</a>';
            $actions['charge'] = '<a href="' . esc_url(admin_url('admin.php?page=sc-wallet-charge&member_id=' . $member_id)) . '">شارژ</a>';
        }

        $name_block = '<span class="sc-member-identity">'
            . $avatar_html
            . '<span class="sc-member-identity-text">'
            . $name_html
            . $meta_html
            . '</span>'
            . '</span>';

        return $name_block . $this->row_actions($actions);
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

        $badge_map = [
            'charge' => 'sc-badge--success',
            'deduct' => 'sc-badge--danger',
            'payment' => 'sc-badge--purple',
            'refund' => 'sc-badge--warning',
            'session_fee' => 'sc-badge--soft',
        ];
        $badge_class = $badge_map[$type] ?? 'sc-badge--muted';

        $icon_map = [
            'charge' => '↑',
            'deduct' => '↓',
            'payment' => '−',
            'refund' => '↩',
            'session_fee' => '•',
        ];
        $icon = $icon_map[$type] ?? '•';

        return '<span class="sc-badge ' . esc_attr($badge_class) . ' sc-wallet-type-badge">'
            . '<span class="sc-wallet-type-icon" aria-hidden="true">' . esc_html($icon) . '</span>'
            . esc_html($label)
            . '</span>';
    }

    public function column_amount($item) {
        $amount = floatval($item->amount ?? 0);
        $type = $item->transaction_type ?? '';
        $is_credit = $this->sc_is_credit_type($type);
        $class = $is_credit ? 'sc-wallet-amount is-credit' : 'sc-wallet-amount is-debit';
        $prefix = $is_credit ? '+' : '−';

        return '<span class="' . esc_attr($class) . '">'
            . esc_html($prefix . ' ' . $this->sc_format_amount($amount))
            . '</span>';
    }

    public function column_balance_after($item) {
        $balance = floatval($item->balance_after ?? 0);
        $class = 'sc-wallet-balance';
        if ($balance < 0) {
            $class .= ' is-negative';
        } elseif ($balance > 0) {
            $class .= ' is-positive';
        }

        return '<span class="' . esc_attr($class) . '">' . esc_html($this->sc_format_amount($balance)) . '</span>';
    }

    public function column_description($item) {
        $description = $item->description ?? '';
        if ($description === '') {
            return '<span class="sc-badge sc-badge--muted">—</span>';
        }

        $truncated = mb_strlen($description) > 50 ? mb_substr($description, 0, 50) . '...' : $description;
        return '<span class="sc-wallet-desc" title="' . esc_attr($description) . '">' . esc_html($truncated) . '</span>';
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

        $badge_map = [
            'completed' => 'sc-badge--success',
            'pending' => 'sc-badge--warning',
            'failed' => 'sc-badge--danger',
            'cancelled' => 'sc-badge--muted',
        ];
        $badge_class = $badge_map[$status] ?? 'sc-badge--soft';

        return '<span class="sc-badge ' . esc_attr($badge_class) . '">' . esc_html($label) . '</span>';
    }

    public function column_created_at($item) {
        $date = $item->created_at ?? '';
        if ($date === '') {
            return '<span class="sc-badge sc-badge--muted">—</span>';
        }
        return '<span class="sc-wallet-date">' . esc_html(sc_date_shamsi($date, 'Y/m/d H:i')) . '</span>';
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
        if (!in_array($orderby, ['id', 'created_at', 'amount'], true)) {
            $orderby = 'created_at';
        }

        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

        // دریافت داده‌ها
        $query = "SELECT wt.*, 
                         m.first_name, 
                         m.last_name, 
                         m.national_id,
                         m.personal_photo
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
