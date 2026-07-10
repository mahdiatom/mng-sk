<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

sc_check_and_create_tables();

global $wpdb;
$invoices_table = $wpdb->prefix . 'sc_invoices';
$expenses_table = $wpdb->prefix . 'sc_expenses';

// دریافت فیلتر بازه تاریخی
$filter_date_from = '';
$filter_date_to = '';
$filter_date_from_shamsi = '';
$filter_date_to_shamsi = '';

if (isset($_GET['filter_date_from_shamsi']) && !empty($_GET['filter_date_from_shamsi'])) {
    $filter_date_from_shamsi = sanitize_text_field($_GET['filter_date_from_shamsi']);
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
} elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
    $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
    $filter_date_from_shamsi = sc_date_shamsi_date_only($filter_date_from);
}

if (isset($_GET['filter_date_to_shamsi']) && !empty($_GET['filter_date_to_shamsi'])) {
    $filter_date_to_shamsi = sanitize_text_field($_GET['filter_date_to_shamsi']);
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
} elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
    $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
    $filter_date_to_shamsi = sc_date_shamsi_date_only($filter_date_to);
}

// اگر بازه تاریخی انتخاب نشده باشد، بازه 6 ماه گذشته را در نظر می‌گیریم
if (empty($filter_date_from) || empty($filter_date_to)) {
    $today = new DateTime();
    $six_months_ago = clone $today;
    $six_months_ago->modify('-6 months');
    
    $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
    $six_months_ago_jalali = gregorian_to_jalali((int)$six_months_ago->format('Y'), (int)$six_months_ago->format('m'), (int)$six_months_ago->format('d'));
    
    if (empty($filter_date_from)) {
        $filter_date_from = $six_months_ago->format('Y-m-d');
        $filter_date_from_shamsi = $six_months_ago_jalali[0] . '/' . 
                                   str_pad($six_months_ago_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                   str_pad($six_months_ago_jalali[2], 2, '0', STR_PAD_LEFT);
    }
    
    if (empty($filter_date_to)) {
        $filter_date_to = $today->format('Y-m-d');
        $filter_date_to_shamsi = $today_jalali[0] . '/' . 
                                 str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                 str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
    }
}

// کل درآمد = آکادمی (دوره + رویداد، پرداخت‌شده / تأیید پرداخت) + فروشگاه (WC processing/completed)
$academy_income_where = [
    "i.status IN ('paid','completed','processing')",
    'i.payment_date IS NOT NULL',
    '(i.course_id > 0 OR (i.event_id IS NOT NULL AND i.event_id > 0))',
];
$academy_income_values = [];
if ($filter_date_from) {
    $academy_income_where[] = 'DATE(i.payment_date) >= %s';
    $academy_income_values[] = $filter_date_from;
}
if ($filter_date_to) {
    $academy_income_where[] = 'DATE(i.payment_date) <= %s';
    $academy_income_values[] = $filter_date_to;
}
if (function_exists('sc_secretary_merge_invoice_where')) {
    sc_secretary_merge_invoice_where($academy_income_where, $academy_income_values, 'i');
}
$academy_where_sql = implode(' AND ', $academy_income_where);
$total_academy_income = $academy_income_values
    ? (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(i.amount), 0) FROM $invoices_table i WHERE $academy_where_sql", $academy_income_values))
    : (float) $wpdb->get_var("SELECT COALESCE(SUM(i.amount), 0) FROM $invoices_table i WHERE $academy_where_sql");

$store_agg = ['total' => 0.0, 'order_count' => 0, 'by_day' => []];
if (!function_exists('sc_secretary_finance_include_store_revenue') || sc_secretary_finance_include_store_revenue()) {
    $store_agg = function_exists('sc_finance_aggregate_store_orders_items')
        ? sc_finance_aggregate_store_orders_items($filter_date_from, $filter_date_to, 0, 0)
        : ['total' => 0.0, 'order_count' => 0, 'by_day' => []];
}
$total_store_income_period = (float) ($store_agg['total'] ?? 0);

$overview_chapter = isset($filter_chapter) ? (string) $filter_chapter : '';
$total_manual_income = function_exists('sc_finance_sum_manual_incomes')
    ? sc_finance_sum_manual_incomes($filter_date_from, $filter_date_to, $overview_chapter)
    : 0.0;

$total_income = $total_academy_income + $total_store_income_period + $total_manual_income;

$paid_academy_count = $academy_income_values
    ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $invoices_table i WHERE $academy_where_sql", $academy_income_values))
    : (int) $wpdb->get_var("SELECT COUNT(*) FROM $invoices_table i WHERE $academy_where_sql");
$paid_invoices_count = $paid_academy_count + (int) ($store_agg['order_count'] ?? 0);

// محاسبه کل هزینه‌ها
$expenses_where_conditions = ['1=1'];
$expenses_where_values = [];
if ($filter_date_from) {
    $expenses_where_conditions[] = 'e.expense_date_gregorian >= %s';
    $expenses_where_values[] = $filter_date_from;
}
if ($filter_date_to) {
    $expenses_where_conditions[] = 'e.expense_date_gregorian <= %s';
    $expenses_where_values[] = $filter_date_to;
}
if (function_exists('sc_secretary_merge_expense_where')) {
    sc_secretary_merge_expense_where($expenses_where_conditions, $expenses_where_values, 'e');
}

$expenses_where_clause = implode(' AND ', $expenses_where_conditions);
if (!empty($expenses_where_values)) {
    $total_expenses_query = $wpdb->prepare(
        "SELECT SUM(e.amount) as total FROM $expenses_table e WHERE $expenses_where_clause",
        $expenses_where_values
    );
} else {
    $total_expenses_query = "SELECT SUM(e.amount) as total FROM $expenses_table e WHERE $expenses_where_clause";
}
$total_expenses_result = $wpdb->get_var($total_expenses_query);
$total_expenses = $total_expenses_result ? floatval($total_expenses_result) : 0;

// محاسبه سود
$profit = $total_income - $total_expenses;

// محاسبه داده‌های دوره قبل (برای مقایسه)
$date_from_obj = new DateTime($filter_date_from);
$date_to_obj = new DateTime($filter_date_to);
$period_days = $date_from_obj->diff($date_to_obj)->days + 1;

$prev_date_to = clone $date_from_obj;
$prev_date_to->modify('-1 day');
$prev_date_from = clone $prev_date_to;
$prev_date_from->modify('-' . ($period_days - 1) . ' days');

// درآمد دوره قبل (آکادمی + فروشگاه، همان منطق بازهٔ جاری)
$prev_from_s = $prev_date_from->format('Y-m-d');
$prev_to_s = $prev_date_to->format('Y-m-d');
$prev_academy_where = [
    "i.status IN ('paid','completed','processing')",
    'i.payment_date IS NOT NULL',
    '(i.course_id > 0 OR (i.event_id IS NOT NULL AND i.event_id > 0))',
    'DATE(i.payment_date) >= %s',
    'DATE(i.payment_date) <= %s',
];
$prev_academy_args = [$prev_from_s, $prev_to_s];
if (function_exists('sc_secretary_merge_invoice_where')) {
    sc_secretary_merge_invoice_where($prev_academy_where, $prev_academy_args, 'i');
}
$prev_academy_income = (float) $wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(i.amount), 0) FROM $invoices_table i WHERE " . implode(' AND ', $prev_academy_where),
    $prev_academy_args
));
$prev_store_agg = ['total' => 0.0];
if (!function_exists('sc_secretary_finance_include_store_revenue') || sc_secretary_finance_include_store_revenue()) {
    $prev_store_agg = function_exists('sc_finance_aggregate_store_orders_items')
        ? sc_finance_aggregate_store_orders_items($prev_from_s, $prev_to_s, 0, 0)
        : ['total' => 0.0];
}
$prev_total_income = $prev_academy_income + (float) ($prev_store_agg['total'] ?? 0);
$prev_manual_income = function_exists('sc_finance_sum_manual_incomes')
    ? sc_finance_sum_manual_incomes($prev_from_s, $prev_to_s, $overview_chapter)
    : 0.0;
$prev_total_income += $prev_manual_income;

// هزینه دوره قبل
$prev_exp_where = ['e.expense_date_gregorian >= %s', 'e.expense_date_gregorian <= %s'];
$prev_exp_args = [$prev_from_s, $prev_to_s];
if (function_exists('sc_secretary_merge_expense_where')) {
    sc_secretary_merge_expense_where($prev_exp_where, $prev_exp_args, 'e');
}
$prev_expenses_query = $wpdb->prepare(
    "SELECT SUM(e.amount) as total FROM $expenses_table e WHERE " . implode(' AND ', $prev_exp_where),
    $prev_exp_args
);
$prev_total_expenses_raw = $wpdb->get_var($prev_expenses_query);
$prev_total_expenses = $prev_total_expenses_raw ? floatval($prev_total_expenses_raw) : 0;

// سود دوره قبل
$prev_profit = $prev_total_income - $prev_total_expenses;

// محاسبه تغییرات نسبت به دوره قبل
$income_change = $prev_total_income > 0 ? (($total_income - $prev_total_income) / $prev_total_income) * 100 : 0;
$expenses_change = $prev_total_expenses > 0 ? (($total_expenses - $prev_total_expenses) / $prev_total_expenses) * 100 : 0;
$profit_change = $prev_profit != 0 ? (($profit - $prev_profit) / abs($prev_profit)) * 100 : 0;

// محاسبه داده‌های ماهیانه برای نمودار و لیست (6 ماه آخر)
$monthly_data = [];
$start_date = new DateTime($filter_date_from);
$end_date = new DateTime($filter_date_to);

// ایجاد لیست ماه‌ها
$current = clone $start_date;
$months = [];
while ($current <= $end_date) {
    $months[] = clone $current;
    $current->modify('+1 month');
}

// اگر کمتر از 6 ماه باشد، 6 ماه گذشته را نمایش می‌دهیم
if (count($months) < 6) {
    $months = [];
    $current = clone $end_date;
    for ($i = 5; $i >= 0; $i--) {
        $month_start = clone $current;
        $month_start->modify("-$i months");
        $month_start->modify('first day of this month');
        $months[] = $month_start;
    }
}

foreach ($months as $month_start) {
    $month_end = clone $month_start;
    $month_end->modify('last day of this month');
    
    // محدود کردن به بازه انتخابی
    if ($month_start < new DateTime($filter_date_from)) {
        $month_start = new DateTime($filter_date_from);
    }
    if ($month_end > new DateTime($filter_date_to)) {
        $month_end = new DateTime($filter_date_to);
    }
    
    $month_start_str = $month_start->format('Y-m-d');
    $month_end_str = $month_end->format('Y-m-d');
    
    // تبدیل به شمسی برای نمایش
    $month_start_jalali = gregorian_to_jalali(
        (int)$month_start->format('Y'),
        (int)$month_start->format('m'),
        (int)$month_start->format('d')
    );
    $month_name = sc_date_shamsi($month_start_str, 'Y/m');
    
    // درآمد ماه (آکادمی بر اساس تاریخ پرداخت + فروشگاه بر اساس تاریخ سفارش)
    $month_income_where = [
        "i.status IN ('paid','completed','processing')",
        'i.payment_date IS NOT NULL',
        '(i.course_id > 0 OR (i.event_id IS NOT NULL AND i.event_id > 0))',
        'DATE(i.payment_date) >= %s',
        'DATE(i.payment_date) <= %s',
    ];
    $month_income_args = [$month_start_str, $month_end_str];
    if (function_exists('sc_secretary_merge_invoice_where')) {
        sc_secretary_merge_invoice_where($month_income_where, $month_income_args, 'i');
    }
    $month_income_query = $wpdb->prepare(
        "SELECT COALESCE(SUM(i.amount), 0) FROM $invoices_table i WHERE " . implode(' AND ', $month_income_where),
        $month_income_args
    );
    $month_academy_income = (float) $wpdb->get_var($month_income_query);
    $month_store_income = 0.0;
    $store_by_day = $store_agg['by_day'] ?? [];
    foreach ($store_by_day as $day => $amt) {
        if ($day >= $month_start_str && $day <= $month_end_str) {
            $month_store_income += (float) $amt;
        }
    }
    $month_income = $month_academy_income + $month_store_income;

    $month_manual_income = function_exists('sc_finance_sum_manual_incomes')
        ? sc_finance_sum_manual_incomes($month_start_str, $month_end_str, $overview_chapter)
        : 0.0;
    $month_income += $month_manual_income;
    
    // هزینه ماه
    $month_exp_where = ['e.expense_date_gregorian >= %s', 'e.expense_date_gregorian <= %s'];
    $month_exp_args = [$month_start_str, $month_end_str];
    if (function_exists('sc_secretary_merge_expense_where')) {
        sc_secretary_merge_expense_where($month_exp_where, $month_exp_args, 'e');
    }
    $month_expenses_query = $wpdb->prepare(
        "SELECT SUM(e.amount) as total FROM $expenses_table e WHERE " . implode(' AND ', $month_exp_where),
        $month_exp_args
    );
    $month_expenses_result = $wpdb->get_var($month_expenses_query);
    $month_expenses = $month_expenses_result ? floatval($month_expenses_result) : 0;
    
    // سود ماه
    $month_profit = $month_income - $month_expenses;
    
    // فقط اگر حداقل یکی از درآمد، هزینه یا سود غیر صفر باشد، اضافه کن
    if ($month_income != 0 || $month_expenses != 0 || $month_profit != 0) {
        $monthly_data[] = [
            'month' => $month_name,
            'month_gregorian' => $month_start->format('Y-m'),
            'income' => $month_income,
            'expenses' => $month_expenses,
            'profit' => $month_profit
        ];
    }
}
$active_filters_count = 0;
if (!empty($_GET['filter_date_from']) || !empty($_GET['filter_date_from_shamsi'])) {
    $active_filters_count++;
}
if (!empty($_GET['filter_date_to']) || !empty($_GET['filter_date_to_shamsi'])) {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;
$overview_clear_url = admin_url('admin.php?page=sc-reports-income-expenses&tab=overview');
?>

<div class="sc-reports-list-filters-card sc-finance-reports-filter-panel sc-finance-overview-filters<?php echo $filters_open ? ' is-open' : ''; ?>">
    <div class="sc-reports-list-filters-toolbar">
        <button type="button"
                class="sc-reports-list-filters-toggle"
                id="sc-finance-overview-filters-toggle"
                aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                aria-controls="sc-finance-overview-filters-panel">
            <span class="sc-reports-list-filters-toggle-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="sc-reports-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
            </span>
            <?php if ($active_filters_count > 0) : ?>
                <span class="sc-reports-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
            <?php endif; ?>
            <span class="sc-reports-list-filters-chevron" aria-hidden="true"></span>
        </button>
        <?php if ($active_filters_count > 0) : ?>
            <a href="<?php echo esc_url($overview_clear_url); ?>" class="sc-reports-list-filters-clear">پاک کردن فیلترها</a>
        <?php endif; ?>
    </div>
    <form method="GET" action="" class="sc-reports-list-filters-panel sc-finance-reports-filter-form" id="sc-finance-overview-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
        <input type="hidden" name="page" value="sc-reports-income-expenses">
        <input type="hidden" name="tab" value="overview">

        <div class="sc-filter-grid sc-finance-filter-grid">
            <div class="sc-filter-field">
                <label class="sc-filter-label">از تاریخ</label>
                <input type="text"
                       name="filter_date_from_shamsi"
                       id="filter_date_from_shamsi"
                       value="<?php echo esc_attr($filter_date_from_shamsi); ?>"
                       class="persian-date-input sc-filter-control"
                       placeholder="از تاریخ (شمسی)"
                       readonly>
                <input type="hidden" name="filter_date_from" id="filter_date_from"
                       value="<?php echo esc_attr($filter_date_from); ?>">
            </div>
            <div class="sc-filter-field">
                <label class="sc-filter-label">تا تاریخ</label>
                <input type="text"
                       name="filter_date_to_shamsi"
                       id="filter_date_to_shamsi"
                       value="<?php echo esc_attr($filter_date_to_shamsi); ?>"
                       class="persian-date-input sc-filter-control"
                       placeholder="تا تاریخ (شمسی)"
                       readonly>
                <input type="hidden" name="filter_date_to" id="filter_date_to"
                       value="<?php echo esc_attr($filter_date_to); ?>">
            </div>
        </div>

        <div class="sc-reports-list-filters-actions">
            <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
            <a href="<?php echo esc_url($overview_clear_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
        </div>
    </form>
</div>
<script type="text/javascript">
jQuery(function ($) {
    var $toggle = $('#sc-finance-overview-filters-toggle');
    var $panel = $('#sc-finance-overview-filters-panel');
    var $card = $toggle.closest('.sc-reports-list-filters-card');
    var $label = $toggle.find('.sc-reports-list-filters-toggle-label');
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

    <p class="description sc-finance-reports-info-note">
        <strong>کل درآمد</strong> برابر مجموع درآمد <strong>آکادمی</strong> (صورت‌حساب‌های دوره و رویداد پرداخت‌شده)،
        <strong>فروشگاه</strong> (سفارش‌های ووکامرس)،
        و <strong>درآمدهای ثبت‌شده دستی</strong> (منوی صورت‌حساب‌ها ← ثبت درآمد) در بازهٔ انتخابی است.
    </p>
    
    <div class="sc-dashboard-stats sc-finance-reports-stats">
        <div class="sc-stat-box sc-finance-stat-box sc-finance-stat-box--income">
            <h3>کل درآمد</h3>
            <div class="sc-finance-stat-value">
                <?php echo number_format($total_income, 0, '.', ','); ?> تومان
            </div>
            <div class="sc-finance-stat-meta" style="margin-top:8px;font-size:12px;opacity:.85;line-height:1.6;">
                آکادمی: <?php echo esc_html(number_format($total_academy_income, 0, '.', ',')); ?>
                + فروشگاه: <?php echo esc_html(number_format($total_store_income_period, 0, '.', ',')); ?>
                + ثبت دستی: <?php echo esc_html(number_format($total_manual_income, 0, '.', ',')); ?>
            </div>
            <div class="sc-finance-stat-trend <?php echo $income_change >= 0 ? 'is-positive' : 'is-negative'; ?>">
                <?php if ($income_change != 0) : ?>
                    <?php echo $income_change >= 0 ? '↑' : '↓'; ?> 
                    <?php echo number_format(abs($income_change), 1); ?>% 
                    نسبت به دوره قبل
                <?php else : ?>
                    تغییر نکرده
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sc-stat-box sc-finance-stat-box sc-finance-stat-box--expense">
            <h3>کل هزینه‌ها</h3>
            <div class="sc-finance-stat-value is-expense">
                <?php echo number_format($total_expenses, 0, '.', ','); ?> تومان
            </div>
            <div class="sc-finance-stat-trend <?php echo $expenses_change >= 0 ? 'is-negative' : 'is-positive'; ?>">
                <?php if ($expenses_change != 0) : ?>
                    <?php echo $expenses_change >= 0 ? '↑' : '↓'; ?> 
                    <?php echo number_format(abs($expenses_change), 1); ?>% 
                    نسبت به دوره قبل
                <?php else : ?>
                    تغییر نکرده
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sc-stat-box sc-finance-stat-box sc-finance-stat-box--profit">
            <h3>سود نهایی</h3>
            <div class="sc-finance-stat-value <?php echo $profit >= 0 ? 'is-positive' : 'is-negative'; ?>">
                <?php echo number_format($profit, 0, '.', ','); ?> تومان
            </div>
            <div class="sc-finance-stat-trend <?php echo $profit_change >= 0 ? 'is-positive' : 'is-negative'; ?>">
                <?php if ($profit_change != 0) : ?>
                    <?php echo $profit_change >= 0 ? '↑' : '↓'; ?> 
                    <?php echo number_format(abs($profit_change), 1); ?>% 
                    نسبت به دوره قبل
                <?php else : ?>
                    تغییر نکرده
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sc-stat-box sc-finance-stat-box sc-finance-stat-box--count">
            <h3>صورت‌حساب آکادمی + سفارش فروشگاه</h3>
            <div class="sc-finance-stat-value is-accent">
                <?php echo $paid_invoices_count; ?>
            </div>
        </div>
    </div>
    
    <div class="sc-finance-panel postbox sc-finance-chart-panel sc-finance-reports-chart-panel">
        <div class="postbox-header"><h2>نمودار درآمد و هزینه‌ها (۶ ماه آخر)</h2></div>
        <div class="inside">
            <div class="sc-finance-chart-wrap sc-finance-chart-wrap--tall">
                <canvas id="incomeExpensesChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="sc-finance-panel postbox sc-finance-reports-data-panel">
        <div class="postbox-header"><h2>گزارش ماهیانه</h2></div>
        <div class="inside">
        <div class="sc-finance-reports-table-wrap">
            <table class="wp-list-table widefat fixed striped sc-finance-reports-table">
                <thead>
                    <tr>
                        <th>ماه</th>
                        <th>درآمد</th>
                        <th>هزینه</th>
                        <th>سود نهایی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_data as $month) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($month['month']); ?></strong></td>
                            <td class="sc-finance-cell-income">
                                <?php echo number_format($month['income'], 0, '.', ','); ?> تومان
                            </td>
                            <td class="sc-finance-cell-expense">
                                <?php echo number_format($month['expenses'], 0, '.', ','); ?> تومان
                            </td>
                            <td class="<?php echo $month['profit'] >= 0 ? 'sc-finance-cell-income' : 'sc-finance-cell-expense'; ?>">
                                <?php echo number_format($month['profit'], 0, '.', ','); ?> تومان
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>

<script src="<?php echo esc_url(SC_ASSETS_URL . 'js/vendor/chart.min.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('incomeExpensesChart');
    if (!ctx) {
        return;
    }
    
    const monthlyData = <?php echo json_encode($monthly_data); ?>;
    
    if (!monthlyData || monthlyData.length === 0) {
        ctx.parentElement.innerHTML = '<p style="text-align: center; padding: 40px; color: #666;">داده‌ای برای نمایش وجود ندارد.</p>';
        return;
    }
    
    const labels = monthlyData.map(m => m.month);
    const incomeData = monthlyData.map(m => parseFloat(m.income) || 0);
    const expensesData = monthlyData.map(m => parseFloat(m.expenses) || 0);
    const profitData = monthlyData.map(m => parseFloat(m.profit) || 0);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'درآمد',
                    data: incomeData,
                    backgroundColor: 'rgba(109, 52, 255, 0.65)',
                    borderColor: 'rgba(74, 31, 184, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                },
                {
                    label: 'هزینه',
                    data: expensesData,
                    backgroundColor: 'rgba(220, 38, 38, 0.55)',
                    borderColor: 'rgba(220, 38, 38, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                },
                {
                    label: 'سود نهایی',
                    data: profitData,
                    backgroundColor: 'rgba(16, 185, 129, 0.6)',
                    borderColor: 'rgba(5, 150, 105, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('fa-IR').format(value) + ' تومان';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += new Intl.NumberFormat('fa-IR').format(context.parsed.y) + ' تومان';
                            return label;
                        }
                    }
                }
            }
        }
    });
});
</script>

