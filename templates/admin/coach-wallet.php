<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی
if (!current_user_can('sc_view_coach_salary')) {
    wp_die('شما دسترسی به این صفحه ندارید.');
}

global $wpdb;
$coaches_table = $wpdb->prefix . 'sc_coaches';
$wallet_table = $wpdb->prefix . 'sc_coach_wallet_transactions';
$withdrawal_table = $wpdb->prefix . 'sc_coach_withdrawal_requests';

// دریافت شناسه مربی لاگین شده
$current_user_id = get_current_user_id();
$coach = $wpdb->get_row($wpdb->prepare(
    "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
    $current_user_id
));

if (!$coach) {
    echo '<div class="wrap"><div class="notice notice-error"><p>شما به عنوان مربی ثبت نشده‌اید.</p></div></div>';
    return;
}

$coach_id = $coach->id;

// دریافت موجودی کیف پول
$wallet_balance = sc_get_coach_wallet_balance($coach_id);

// مجموع پرداختی (درخواست‌های برداشت با وضعیت paid)
$total_withdrawn = $wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM $withdrawal_table 
     WHERE coach_id = %d AND status = 'paid'",
    $coach_id
));
$total_withdrawn = floatval($total_withdrawn);

// تعداد و مجموع درخواست‌های برداشت بر اساس وضعیت
$withdrawal_stats = $wpdb->get_row($wpdb->prepare(
    "SELECT 
        SUM(IF(status = 'pending', 1, 0)) as count_pending,
        SUM(IF(status = 'approved', 1, 0)) as count_approved,
        SUM(IF(status = 'rejected', 1, 0)) as count_rejected,
        SUM(IF(status = 'paid', 1, 0)) as count_paid,
        COALESCE(SUM(IF(status = 'approved', amount, 0)), 0) as sum_approved,
        COALESCE(SUM(IF(status = 'pending', amount, 0)), 0) as sum_pending
     FROM $withdrawal_table WHERE coach_id = %d",
    $coach_id
), ARRAY_A);
$count_pending = (int) ($withdrawal_stats['count_pending'] ?? 0);
$count_approved = (int) ($withdrawal_stats['count_approved'] ?? 0);
$count_rejected = (int) ($withdrawal_stats['count_rejected'] ?? 0);
$count_paid = (int) ($withdrawal_stats['count_paid'] ?? 0);
$sum_approved = floatval($withdrawal_stats['sum_approved'] ?? 0);
$sum_pending = floatval($withdrawal_stats['sum_pending'] ?? 0);

// مجموع درآمد = (موجودی مثبت) + مجموع پرداختی + مجموع تایید شده (منتظر پرداخت) + مجموع در انتظار تایید
// اگر موجودی منفی باشد، در مجموع درآمد لحاظ نمی‌شود و به عنوان بدهی جداگانه نمایش داده می‌شود.
$wallet_balance_positive = max(0, $wallet_balance);
$wallet_debt = $wallet_balance < 0 ? abs($wallet_balance) : 0;
$total_income = $wallet_balance_positive + $total_withdrawn + $sum_approved + $sum_pending;

// پردازش فیلترهای تاریخ (شمسی به میلادی)
// اگر تاریخ‌ها خالی هستند، تاریخ پیش‌فرض امروز را تنظیم کن
$today_gregorian = current_time('Y-m-d');
$today = new DateTime(current_time('Y-m-d'));
$jalali = gregorian_to_jalali(
    (int)$today->format('Y'),
    (int)$today->format('m'),
    (int)$today->format('d')
);
$today_shamsi = $jalali[0] . '/' .
    str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
    str_pad($jalali[2], 2, '0', STR_PAD_LEFT);

$filter_date_from = '';
$filter_date_to = '';
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
$filter_date_to_shamsi   = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';

// اگر تاریخ‌ها خالی هستند، تاریخ پیش‌فرض امروز را تنظیم کن
if (empty($filter_date_from_shamsi) && empty($filter_date_to_shamsi)) {
    $filter_date_from_shamsi = $today_shamsi;
    $filter_date_to_shamsi = $today_shamsi;
    $filter_date_from = $today_gregorian;
    $filter_date_to = $today_gregorian;
} else {
    if (!empty($filter_date_from_shamsi)) {
        $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
    } elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
        $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
        $filter_date_from_shamsi = sc_date_shamsi_date_only($filter_date_from);
    }

    if (!empty($filter_date_to_shamsi)) {
        $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
    } elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
        $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
        $filter_date_to_shamsi = sc_date_shamsi_date_only($filter_date_to);
    }
}

// دریافت فیلترهای دیگر
$filter_search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';

// ساخت WHERE clause برای تراکنش‌ها
$where_conditions = ['coach_id = %d'];
$where_values = [$coach_id];

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(created_at) >= %s";
    $where_values[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(created_at) <= %s";
    $where_values[] = $filter_date_to;
}

if (!empty($filter_search)) {
    $where_conditions[] = "description LIKE %s";
    $search_term = '%' . $wpdb->esc_like($filter_search) . '%';
    $where_values[] = $search_term;
}

if ($filter_type !== 'all') {
    $where_conditions[] = "transaction_type = %s";
    $where_values[] = $filter_type;
}

$where_clause = implode(' AND ', $where_conditions);

// دریافت تعداد کل رکوردها برای pagination
$total_query = "SELECT COUNT(*) FROM $wallet_table WHERE $where_clause";
if (!empty($where_values)) {
    $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
} else {
    $total_items = $wpdb->get_var($total_query);
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// دریافت تراکنش‌های کیف پول
$query = "SELECT * FROM $wallet_table 
          WHERE $where_clause
          ORDER BY created_at DESC 
          LIMIT %d OFFSET %d";

$query_values = $where_values;
$query_values[] = $per_page;
$query_values[] = $offset;

if (!empty($query_values)) {
    $transactions = $wpdb->get_results($wpdb->prepare($query, $query_values));
} else {
    $transactions = $wpdb->get_results($query);
}

// محاسبه تعداد صفحات
$total_pages = ceil($total_items / $per_page);

// حداقل مبلغ برداشت
$min_withdrawal = floatval(sc_get_setting('coach_min_withdrawal_amount', '0'));

// متغیرهای پیام (برای جلوگیری از خطای undefined)
$withdrawal_message = '';
$withdrawal_message_type = '';

$active_filters_count = 0;
if ($filter_type !== 'all') {
    $active_filters_count++;
}
if ($filter_search !== '') {
    $active_filters_count++;
}
if (isset($_GET['filter_date_from_shamsi']) || isset($_GET['filter_date_to_shamsi'])
    || isset($_GET['filter_date_from']) || isset($_GET['filter_date_to'])) {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;
?>

<div class="wrap sc-wallet-list-wrap sc-coach-wallet-list-page">
    <div class="sc-wallet-list-header">
        <div class="sc-wallet-list-header-text">
            <h1 class="sc-wallet-list-title">کیف پول</h1>
            <p class="sc-wallet-list-desc">موجودی، خلاصه وضعیت و تاریخچه تراکنش‌های کیف پول شما.</p>
        </div>
        <div class="sc-wallet-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-withdrawals')); ?>" class="sc-wallet-list-add-btn">درخواست برداشت</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-salary')); ?>" class="sc-wallet-list-export-btn">لیست دستمزد</a>
        </div>
    </div>

    <?php if ($withdrawal_message): ?>
        <div class="notice notice-<?php echo $withdrawal_message_type; ?> is-dismissible">
            <p><?php echo esc_html($withdrawal_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="sc-wallet-list-stats">
        <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--green">
            <div class="sc-wallet-list-stat-label">موجودی کیف پول</div>
            <div class="sc-wallet-list-stat-value"><?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> <small>تومان</small></div>
        </div>
        <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--blue">
            <div class="sc-wallet-list-stat-label">مجموع درآمد</div>
            <div class="sc-wallet-list-stat-value"><?php echo esc_html(sc_format_amount_display($total_income)); ?> <small>تومان</small></div>
        </div>
        <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--purple">
            <div class="sc-wallet-list-stat-label">مجموع پرداختی</div>
            <div class="sc-wallet-list-stat-value"><?php echo esc_html(sc_format_amount_display($total_withdrawn)); ?> <small>تومان</small></div>
        </div>
        <?php if ($wallet_debt > 0): ?>
        <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--red">
            <div class="sc-wallet-list-stat-label">بدهی کیف پول</div>
            <div class="sc-wallet-list-stat-value"><?php echo esc_html(sc_format_amount_display($wallet_debt)); ?> <small>تومان</small></div>
        </div>
        <?php endif; ?>
        <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--amber">
            <div class="sc-wallet-list-stat-label">درخواست‌های برداشت</div>
            <div class="sc-wallet-list-stat-value sc-wallet-list-stat-value--split">
                <span class="is-credit">در انتظار: <?php echo (int) $count_pending; ?></span>
                <span class="is-debit">رد شده: <?php echo (int) $count_rejected; ?></span>
            </div>
        </div>
    </div>

    <div class="sc-wallet-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-wallet-list-filters-toolbar">
            <button type="button"
                    class="sc-wallet-list-filters-toggle"
                    id="sc-coach-wallet-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-coach-wallet-filters-panel">
                <span class="sc-wallet-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-wallet-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-wallet-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-wallet-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-wallet')); ?>" class="sc-wallet-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="GET" action="" class="sc-wallet-list-filters-panel" id="sc-coach-wallet-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-coach-wallet">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_type">نوع تراکنش</label>
                    <select name="filter_type" id="filter_type" class="sc-filter-control">
                        <option value="all" <?php selected($filter_type, 'all'); ?>>همه انواع</option>
                        <option value="salary_percentage" <?php selected($filter_type, 'salary_percentage'); ?>>دستمزد درصدی</option>
                        <option value="salary_fixed" <?php selected($filter_type, 'salary_fixed'); ?>>دستمزد ثابت</option>
                        <option value="charge" <?php selected($filter_type, 'charge'); ?>>شارژ</option>
                        <option value="deduct" <?php selected($filter_type, 'deduct'); ?>>کسر</option>
                        <option value="withdrawal" <?php selected($filter_type, 'withdrawal'); ?>>برداشت</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_search">جستجو</label>
                    <input type="search"
                           name="filter_search"
                           id="filter_search"
                           value="<?php echo esc_attr($filter_search); ?>"
                           class="sc-filter-control"
                           placeholder="جستجو در توضیحات...">
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_date_from_shamsi">از تاریخ</label>
                    <input type="text"
                           name="filter_date_from_shamsi"
                           id="filter_date_from_shamsi"
                           value="<?php echo esc_attr($filter_date_from_shamsi); ?>"
                           class="sc-filter-control persian-date-input"
                           placeholder="از تاریخ"
                           readonly>
                    <input type="hidden"
                           name="filter_date_from"
                           id="filter_date_from"
                           value="<?php echo esc_attr($filter_date_from); ?>">
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_date_to_shamsi">تا تاریخ</label>
                    <input type="text"
                           name="filter_date_to_shamsi"
                           id="filter_date_to_shamsi"
                           value="<?php echo esc_attr($filter_date_to_shamsi); ?>"
                           class="sc-filter-control persian-date-input"
                           placeholder="تا تاریخ"
                           readonly>
                    <input type="hidden"
                           name="filter_date_to"
                           id="filter_date_to"
                           value="<?php echo esc_attr($filter_date_to); ?>">
                </div>
            </div>

            <div class="sc-wallet-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-wallet')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-wallet-list-table-card">
        <?php if (empty($transactions)): ?>
            <p style="text-align:center;padding:32px 16px;color:#6b7280;margin:0;">هیچ تراکنشی ثبت نشده است.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-index">ردیف</th>
                        <th class="column-date">تاریخ</th>
                        <th class="column-type">نوع</th>
                        <th class="column-amount">مبلغ</th>
                        <th class="column-description">توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $start_number = ($current_page - 1) * $per_page;
                    $type_labels = [
                        'salary_percentage' => 'دستمزد درصدی',
                        'salary_fixed' => 'دستمزد ثابت',
                        'charge' => 'شارژ',
                        'deduct' => 'کسر',
                        'withdrawal' => 'برداشت',
                    ];
                    $badge_map = [
                        'salary_percentage' => 'sc-badge--success',
                        'salary_fixed' => 'sc-badge--success',
                        'charge' => 'sc-badge--success',
                        'deduct' => 'sc-badge--danger',
                        'withdrawal' => 'sc-badge--purple',
                    ];
                    $icon_map = [
                        'salary_percentage' => '↑',
                        'salary_fixed' => '↑',
                        'charge' => '↑',
                        'deduct' => '↓',
                        'withdrawal' => '−',
                    ];
                    $credit_types = ['charge', 'salary_percentage', 'salary_fixed'];
                    ?>
                    <?php foreach ($transactions as $index => $transaction): ?>
                        <?php
                        $row_number = $start_number + $index + 1;
                        $tx_type = $transaction->transaction_type ?? '';
                        $type_label = $type_labels[$tx_type] ?? $tx_type;
                        $badge_class = $badge_map[$tx_type] ?? 'sc-badge--muted';
                        $type_icon = $icon_map[$tx_type] ?? '•';
                        $is_credit = in_array($tx_type, $credit_types, true);
                        $description = $transaction->description ?? '';
                        $desc_display = $description === '' ? '—' : (mb_strlen($description) > 50 ? mb_substr($description, 0, 50) . '...' : $description);
                        ?>
                        <tr>
                            <td class="column-index"><?php echo (int) $row_number; ?></td>
                            <td class="column-date">
                                <span class="sc-wallet-date"><?php echo esc_html(sc_date_shamsi($transaction->created_at, 'Y/m/d H:i')); ?></span>
                            </td>
                            <td class="column-type">
                                <span class="sc-badge <?php echo esc_attr($badge_class); ?> sc-wallet-type-badge">
                                    <span class="sc-wallet-type-icon" aria-hidden="true"><?php echo esc_html($type_icon); ?></span>
                                    <?php echo esc_html($type_label); ?>
                                </span>
                            </td>
                            <td class="column-amount">
                                <?php if ($is_credit): ?>
                                    <span class="sc-wallet-amount is-credit">+ <?php echo esc_html(sc_format_amount_display($transaction->amount)); ?></span>
                                <?php else: ?>
                                    <span class="sc-wallet-amount is-debit">− <?php echo esc_html(sc_format_amount_display($transaction->amount)); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-description">
                                <?php if ($description === ''): ?>
                                    <span class="sc-badge sc-badge--muted">—</span>
                                <?php else: ?>
                                    <span class="sc-wallet-desc" title="<?php echo esc_attr($description); ?>"><?php echo esc_html($desc_display); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = ['page' => 'sc-coach-wallet'];
                        if (!empty($filter_date_from)) $pagination_args['filter_date_from'] = $filter_date_from;
                        if (!empty($filter_date_to)) $pagination_args['filter_date_to'] = $filter_date_to;
                        if (!empty($filter_date_from_shamsi)) $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
                        if (!empty($filter_date_to_shamsi)) $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
                        if (!empty($filter_search)) $pagination_args['filter_search'] = $filter_search;
                        if ($filter_type !== 'all') $pagination_args['filter_type'] = $filter_type;
                        $page_links = paginate_links([
                            'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                            'format' => '',
                            'prev_text' => '< قبلی ',
                            'next_text' => ' بعدی >',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'add_args' => $pagination_args
                        ]);
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function ($) {
    var $toggle = $('#sc-coach-wallet-filters-toggle');
    var $panel = $('#sc-coach-wallet-filters-panel');
    var $card = $toggle.closest('.sc-wallet-list-filters-card');
    var $label = $toggle.find('.sc-wallet-list-filters-toggle-label');
    $toggle.on('click', function () {
        var isOpen = $card.hasClass('is-open');
        if (isOpen) {
            $card.removeClass('is-open');
            $panel.attr('hidden', true);
            $toggle.attr('aria-expanded', 'false');
            $label.text($label.data('label-closed'));
        } else {
            $card.addClass('is-open');
            $panel.removeAttr('hidden');
            $toggle.attr('aria-expanded', 'true');
            $label.text($label.data('label-open'));
        }
    });
});
</script>

