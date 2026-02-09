<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$transactions_table = $wpdb->prefix . 'sc_wallet_transactions';
$members_table = $wpdb->prefix . 'sc_members';

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

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// تعداد کل رکوردها
$count_query = "SELECT COUNT(*) 
                FROM $transactions_table wt
                LEFT JOIN $members_table m ON wt.member_id = m.id
                $where_clause";

if (!empty($where_values)) {
    $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
} else {
    $total_items = $wpdb->get_var($count_query);
}

$total_pages = ceil($total_items / $per_page);

// دریافت تراکنش‌ها
$query = "SELECT wt.*, m.first_name, m.last_name, m.national_id, m.player_phone
          FROM $transactions_table wt
          LEFT JOIN $members_table m ON wt.member_id = m.id
          $where_clause
          ORDER BY wt.created_at DESC
          LIMIT %d OFFSET %d";

$where_values[] = $per_page;
$where_values[] = $offset;

$transactions = $wpdb->get_results($wpdb->prepare($query, $where_values));

// دریافت لیست اعضا برای فیلتر
$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id 
     FROM $members_table 
     WHERE is_active = 1 
     ORDER BY last_name, first_name"
);
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
                <div>
                    <label for="filter_member" style="display: block; margin-bottom: 5px; font-weight: 600;">بازیکن:</label>
                    <select name="filter_member" id="filter_member" style="min-width: 200px;">
                        <option value="0">همه</option>
                        <?php foreach ($members as $member) : ?>
                            <option value="<?php echo esc_attr($member->id); ?>" <?php selected($filter_member, $member->id); ?>>
                                <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' (' . $member->national_id . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="filter_type" style="display: block; margin-bottom: 5px; font-weight: 600;">نوع تراکنش:</label>
                    <select name="filter_type" id="filter_type">
                        <option value="all" <?php selected($filter_type, 'all'); ?>>همه</option>
                        <option value="charge" <?php selected($filter_type, 'charge'); ?>>شارژ</option>
                        <option value="deduct" <?php selected($filter_type, 'deduct'); ?>>کاهش</option>
                        <option value="payment" <?php selected($filter_type, 'payment'); ?>>پرداخت</option>
                        <option value="refund" <?php selected($filter_type, 'refund'); ?>>بازگشت وجه</option>
                    </select>
                </div>

                <div>
                    <label for="filter_status" style="display: block; margin-bottom: 5px; font-weight: 600;">وضعیت:</label>
                    <select name="filter_status" id="filter_status">
                        <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                        <option value="completed" <?php selected($filter_status, 'completed'); ?>>تکمیل شده</option>
                        <option value="pending" <?php selected($filter_status, 'pending'); ?>>در انتظار</option>
                        <option value="failed" <?php selected($filter_status, 'failed'); ?>>ناموفق</option>
                        <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>لغو شده</option>
                    </select>
                </div>

                <div>
                    <label for="s" style="display: block; margin-bottom: 5px; font-weight: 600;">جستجو:</label>
                    <input type="text" name="s" id="s" value="<?php echo esc_attr($search); ?>" placeholder="نام، نام خانوادگی، کد ملی، توضیحات">
                </div>

                <div>
                    <input type="submit" class="button button-primary" value="اعمال فیلتر">
                    <a href="<?php echo admin_url('admin.php?page=sc-wallet'); ?>" class="button">پاک کردن</a>
                </div>
            </div>
        </form>
    </div>

    <!-- جدول تراکنش‌ها -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 80px;">شناسه</th>
                <th>بازیکن</th>
                <th style="width: 120px;">نوع تراکنش</th>
                <th style="width: 150px;">مبلغ</th>
                <th style="width: 150px;">موجودی قبل</th>
                <th style="width: 150px;">موجودی بعد</th>
                <th>توضیحات</th>
                <th style="width: 120px;">وضعیت</th>
                <th style="width: 150px;">تاریخ</th>
                <th style="width: 100px;">ایجاد شده توسط</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)) : ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 20px;">
                        تراکنشی یافت نشد.
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($transactions as $transaction) : 
                    // دریافت اطلاعات کاربر ایجادکننده
                    $created_by_user = get_userdata($transaction->created_by);
                    $created_by_name = $created_by_user ? $created_by_user->display_name : 'نامشخص';
                    
                    // تعیین نوع تراکنش
                    $type_labels = [
                        'charge' => 'شارژ',
                        'deduct' => 'کاهش',
                        'payment' => 'پرداخت',
                        'refund' => 'بازگشت وجه'
                    ];
                    $type_label = isset($type_labels[$transaction->transaction_type]) ? $type_labels[$transaction->transaction_type] : $transaction->transaction_type;
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
                    
                    // تعیین وضعیت
                    $status_labels = [
                        'completed' => 'تکمیل شده',
                        'pending' => 'در انتظار',
                        'failed' => 'ناموفق',
                        'cancelled' => 'لغو شده'
                    ];
                    $status_label = isset($status_labels[$transaction->status]) ? $status_labels[$transaction->status] : $transaction->status;
                    $status_color = [
                        'completed' => '#28a745',
                        'pending' => '#ffc107',
                        'failed' => '#dc3545',
                        'cancelled' => '#6c757d'
                    ];
                ?>
                    <tr>
                        <td><?php echo esc_html($transaction->id); ?></td>
                        <td>
                            <?php if ($transaction->first_name || $transaction->last_name) : ?>
                                <strong><?php echo esc_html($transaction->first_name . ' ' . $transaction->last_name); ?></strong><br>
                                <small style="color: #666;"><?php echo esc_html($transaction->national_id); ?></small>
                            <?php else : ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="
                                display: inline-block;
                                padding: 4px 8px;
                                border-radius: 4px;
                                background: <?php echo esc_attr($type_bg[$transaction->transaction_type] ?? '#f0f0f0'); ?>;
                                color: <?php echo esc_attr($type_color[$transaction->transaction_type] ?? '#333'); ?>;
                                font-size: 12px;
                                font-weight: 600;
                            ">
                                <?php echo esc_html($type_label); ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: <?php echo esc_attr($type_color[$transaction->transaction_type] ?? '#333'); ?>;">
                                <?php echo number_format(floatval($transaction->amount), 0, '.', ','); ?> تومان
                            </strong>
                        </td>
                        <td><?php echo number_format(floatval($transaction->balance_before), 0, '.', ','); ?> تومان</td>
                        <td>
                            <strong style="color: <?php echo floatval($transaction->balance_after) < 0 ? '#dc3545' : '#28a745'; ?>;">
                                <?php echo number_format(floatval($transaction->balance_after), 0, '.', ','); ?> تومان
                            </strong>
                        </td>
                        <td><?php echo esc_html($transaction->description ?: '-'); ?></td>
                        <td>
                            <span style="
                                display: inline-block;
                                padding: 4px 8px;
                                border-radius: 4px;
                                background: #f0f0f0;
                                color: <?php echo esc_attr($status_color[$transaction->status] ?? '#333'); ?>;
                                font-size: 12px;
                                font-weight: 600;
                            ">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(sc_date_shamsi_date_time($transaction->created_at)); ?></td>
                        <td><?php echo esc_html($created_by_name); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $page_links = paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo; قبلی',
                    'next_text' => 'بعدی &raquo;',
                    'total' => $total_pages,
                    'current' => $current_page
                ]);
                echo $page_links;
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

