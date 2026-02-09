<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Wallet_Transactions_List_Table extends WP_List_Table {

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

    protected function get_hidden_columns() {
        return [];
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
            'refund' => 'بازگشت وجه'
        ];
        $type_label = isset($type_labels[$item->transaction_type]) ? $type_labels[$item->transaction_type] : $item->transaction_type;
        $type_color = [
            'charge' => '#28a745',
            'deduct' => '#dc3545',
            'payment' => '#007bff',
            'refund' => '#ffc107'
        ];
        $type_bg = [
            'charge' => '#d4edda',
            'deduct' => '#f8d7da',
            'payment' => '#d1ecf1',
            'refund' => '#fff3cd'
        ];
        
        return '<span style="
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            background: ' . esc_attr($type_bg[$item->transaction_type] ?? '#f0f0f0') . ';
            color: ' . esc_attr($type_color[$item->transaction_type] ?? '#333') . ';
            font-size: 12px;
            font-weight: 600;
        ">' . esc_html($type_label) . '</span>';
    }

    public function column_amount($item) {
        $type_color = [
            'charge' => '#28a745',
            'deduct' => '#dc3545',
            'payment' => '#007bff',
            'refund' => '#ffc107'
        ];
        
        return '<strong style="color: ' . esc_attr($type_color[$item->transaction_type] ?? '#333') . ';">' . 
               number_format(floatval($item->amount), 0, '.', ',') . ' تومان</strong>';
    }

    public function column_balance_after($item) {
        $balance = floatval($item->balance_after);
        return '<strong style="color: ' . ($balance < 0 ? '#dc3545' : '#28a745') . ';">' . 
               number_format($balance, 0, '.', ',') . ' تومان</strong>';
    }

    public function column_description($item) {
        $desc = $item->description ?? '';
        if (empty($desc)) {
            return '<span style="color: #999;">-</span>';
        }
        // محدود کردن طول توضیحات
        if (mb_strlen($desc) > 50) {
            return '<span title="' . esc_attr($desc) . '">' . esc_html(mb_substr($desc, 0, 50)) . '...</span>';
        }
        return esc_html($desc);
    }

    public function column_status($item) {
        $status_labels = [
            'completed' => 'تکمیل شده',
            'pending' => 'در انتظار',
            'failed' => 'ناموفق',
            'cancelled' => 'لغو شده'
        ];
        $status_label = isset($status_labels[$item->status]) ? $status_labels[$item->status] : $item->status;
        $status_color = [
            'completed' => '#28a745',
            'pending' => '#ffc107',
            'failed' => '#dc3545',
            'cancelled' => '#6c757d'
        ];
        
        return '<span style="
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            background: #f0f0f0;
            color: ' . esc_attr($status_color[$item->status] ?? '#333') . ';
            font-size: 12px;
            font-weight: 600;
        ">' . esc_html($status_label) . '</span>';
    }

    public function column_created_at($item) {
        return esc_html(sc_date_shamsi($item->created_at, 'Y/m/d H:i'));
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item->id;
            default:
                return '-';
        }
    }

    public function prepare_items() {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'sc_wallet_transactions';
        $members_table = $wpdb->prefix . 'sc_members';
        
        $per_page = $this->get_items_per_page('wallet_transactions_per_page', 20);
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // پردازش فیلترها
        $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
        $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
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
        
        if (!empty($search)) {
            $where_conditions[] = "(m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s OR wt.description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // مرتب‌سازی
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) == 'ASC' ? 'ASC' : 'DESC';
        if (empty($orderby)) {
            $orderby = 'created_at';
        }
        
        // تعداد کل
        $count_query = "SELECT COUNT(*) 
                        FROM $transactions_table wt
                        LEFT JOIN $members_table m ON wt.member_id = m.id
                        $where_clause";
        
        if (!empty($where_values)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
        } else {
            $total_items = $wpdb->get_var($count_query);
        }
        
        // دریافت داده‌ها
        $query = "SELECT wt.*, m.first_name, m.last_name, m.national_id, m.player_phone
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
        
        $hidden = $this->get_hidden_columns();
        if (!is_array($hidden)) {
            $hidden = [];
        }
        $this->_column_headers = [$this->get_columns(), $hidden, $this->get_sortable_columns()];
    }
}

// دریافت لیست اعضا برای فیلتر
global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id 
     FROM $members_table 
     WHERE is_active = 1 
     ORDER BY last_name, first_name"
);

// ایجاد instance از جدول
$wallet_table = new Wallet_Transactions_List_Table();
$wallet_table->prepare_items();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">لیست تراکنش‌های کیف پول</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-wallet-charge'); ?>" class="page-title-action">شارژ کیف پول</a>
    <a href="<?php echo admin_url('admin.php?page=sc-wallet-deduct'); ?>" class="page-title-action">کاهش کیف پول</a>
    <hr class="wp-header-end">

    <!-- فیلترها -->
    <div class="sc-wallet-filters" style="background: #f9f9f9; padding: 15px; margin: 20px 0; border: 1px solid #ddd; border-radius: 4px;">
        <form method="GET" action="">
            <input type="hidden" name="page" value="sc-wallet">
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div class="sc-filter-field" style="min-width: 200px; max-width: 300px;">
                    <label class="sc-filter-label">بازیکن</label>
                    
                    <div class="sc-searchable-dropdown">
                        <?php
                        $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
                        $selected_member_text = 'همه کاربران';
                        
                        if ($filter_member > 0) {
                            foreach ($members as $m) {
                                if ($m->id == $filter_member) {
                                    $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
                                    break;
                                }
                            }
                        }
                        ?>
                        
                        <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                        
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if ($filter_member) echo 'style="display:none"'; ?>>همه کاربران</span>
                            <span class="sc-dropdown-selected" <?php if (!$filter_member) echo 'style="display:none"'; ?>>
                                <?php echo esc_html($selected_member_text); ?>
                            </span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                            </div>
                            
                            <div class="sc-dropdown-options">
                                <?php
                                $display_count = 0;
                                $max_display = 10;
                                ?>
                                
                                <div class="sc-dropdown-option sc-visible"
                                     data-value="0"
                                     data-search="همه کاربران"
                                     onclick="scSelectMemberFilter(this,'0','همه کاربران')">
                                    همه کاربران
                                </div>
                                
                                <?php foreach ($members as $member) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                ?>
                                    <div class="sc-dropdown-option <?php echo $display_class; ?>"
                                         data-value="<?php echo esc_attr($member->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                         onclick="scSelectMemberFilter(this,'<?php echo esc_js($member->id); ?>','<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
                                        <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="filter_type" style="display: block; margin-bottom: 5px; font-weight: 600;">نوع تراکنش:</label>
                    <select name="filter_type" id="filter_type">
                        <option value="all" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : 'all', 'all'); ?>>همه</option>
                        <option value="charge" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : '', 'charge'); ?>>شارژ</option>
                        <option value="deduct" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : '', 'deduct'); ?>>کاهش</option>
                        <option value="payment" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : '', 'payment'); ?>>پرداخت</option>
                        <option value="refund" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : '', 'refund'); ?>>بازگشت وجه</option>
                    </select>
                </div>

                <div>
                    <label for="filter_status" style="display: block; margin-bottom: 5px; font-weight: 600;">وضعیت:</label>
                    <select name="filter_status" id="filter_status">
                        <option value="all" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : 'all', 'all'); ?>>همه</option>
                        <option value="completed" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : '', 'completed'); ?>>تکمیل شده</option>
                        <option value="pending" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : '', 'pending'); ?>>در انتظار</option>
                        <option value="failed" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : '', 'failed'); ?>>ناموفق</option>
                        <option value="cancelled" <?php selected(isset($_GET['filter_status']) ? $_GET['filter_status'] : '', 'cancelled'); ?>>لغو شده</option>
                    </select>
                </div>

                <div>
                    <label for="s" style="display: block; margin-bottom: 5px; font-weight: 600;">جستجو:</label>
                    <input type="text" name="s" id="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>" placeholder="نام، نام خانوادگی، کد ملی، توضیحات">
                </div>

                <div>
                    <input type="submit" class="button button-primary" value="اعمال فیلتر">
                    <a href="<?php echo admin_url('admin.php?page=sc-wallet'); ?>" class="button">پاک کردن</a>
                </div>
            </div>
        </form>
    </div>

    <!-- نمایش جدول -->
    <form method="GET">
        <input type="hidden" name="page" value="sc-wallet">
        <?php if (isset($_GET['filter_member'])) : ?>
            <input type="hidden" name="filter_member" value="<?php echo esc_attr($_GET['filter_member']); ?>">
        <?php endif; ?>
        <?php if (isset($_GET['filter_type'])) : ?>
            <input type="hidden" name="filter_type" value="<?php echo esc_attr($_GET['filter_type']); ?>">
        <?php endif; ?>
        <?php if (isset($_GET['filter_status'])) : ?>
            <input type="hidden" name="filter_status" value="<?php echo esc_attr($_GET['filter_status']); ?>">
        <?php endif; ?>
        <?php if (isset($_GET['s'])) : ?>
            <input type="hidden" name="s" value="<?php echo esc_attr($_GET['s']); ?>">
        <?php endif; ?>
        
        <?php $wallet_table->display(); ?>
    </form>
</div>
