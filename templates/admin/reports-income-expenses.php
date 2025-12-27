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

// محاسبه کل درآمد (صورت حساب‌های پرداخت شده)
$income_where_conditions = ["status IN ('completed', 'paid')"];
$income_where_values = [];
if ($filter_date_from) {
    $income_where_conditions[] = "DATE(created_at) >= %s";
    $income_where_values[] = $filter_date_from;
}
if ($filter_date_to) {
    $income_where_conditions[] = "DATE(created_at) <= %s";
    $income_where_values[] = $filter_date_to;
}

$income_where_clause = implode(' AND ', $income_where_conditions);
if (!empty($income_where_values)) {
    $total_income_query = $wpdb->prepare(
        "SELECT SUM(amount) as total FROM $invoices_table WHERE $income_where_clause",
        $income_where_values
    );
} else {
    $total_income_query = "SELECT SUM(amount) as total FROM $invoices_table WHERE $income_where_clause";
}
$total_income_result = $wpdb->get_var($total_income_query);
$total_income = $total_income_result ? floatval($total_income_result) : 0;

// محاسبه تعداد صورت حساب‌های پرداخت شده
if (!empty($income_where_values)) {
    $paid_invoices_count_query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $invoices_table WHERE $income_where_clause",
        $income_where_values
    );
} else {
    $paid_invoices_count_query = "SELECT COUNT(*) FROM $invoices_table WHERE $income_where_clause";
}
$paid_invoices_count = $wpdb->get_var($paid_invoices_count_query) ?: 0;

// محاسبه کل هزینه‌ها
$expenses_where_conditions = ["1=1"];
$expenses_where_values = [];
if ($filter_date_from) {
    $expenses_where_conditions[] = "expense_date_gregorian >= %s";
    $expenses_where_values[] = $filter_date_from;
}
if ($filter_date_to) {
    $expenses_where_conditions[] = "expense_date_gregorian <= %s";
    $expenses_where_values[] = $filter_date_to;
}

$expenses_where_clause = implode(' AND ', $expenses_where_conditions);
if (!empty($expenses_where_values)) {
    $total_expenses_query = $wpdb->prepare(
        "SELECT SUM(amount) as total FROM $expenses_table WHERE $expenses_where_clause",
        $expenses_where_values
    );
} else {
    $total_expenses_query = "SELECT SUM(amount) as total FROM $expenses_table WHERE $expenses_where_clause";
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

// درآمد دوره قبل
$prev_income_where = "status IN ('completed', 'paid') AND DATE(created_at) >= %s AND DATE(created_at) <= %s";
$prev_income_query = $wpdb->prepare(
    "SELECT SUM(amount) as total FROM $invoices_table WHERE $prev_income_where",
    $prev_date_from->format('Y-m-d'),
    $prev_date_to->format('Y-m-d')
);
$prev_total_income = $wpdb->get_var($prev_income_query) ? floatval($wpdb->get_var($prev_income_query)) : 0;

// هزینه دوره قبل
$prev_expenses_where = "expense_date_gregorian >= %s AND expense_date_gregorian <= %s";
$prev_expenses_query = $wpdb->prepare(
    "SELECT SUM(amount) as total FROM $expenses_table WHERE $prev_expenses_where",
    $prev_date_from->format('Y-m-d'),
    $prev_date_to->format('Y-m-d')
);
$prev_total_expenses = $wpdb->get_var($prev_expenses_query) ? floatval($wpdb->get_var($prev_expenses_query)) : 0;

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
    
    // درآمد ماه
    $month_income_query = $wpdb->prepare(
        "SELECT SUM(amount) as total FROM $invoices_table 
         WHERE status IN ('completed', 'paid') 
         AND DATE(created_at) >= %s 
         AND DATE(created_at) <= %s",
        $month_start_str,
        $month_end_str
    );
    $month_income_result = $wpdb->get_var($month_income_query);
    $month_income = $month_income_result ? floatval($month_income_result) : 0;
    
    // هزینه ماه
    $month_expenses_query = $wpdb->prepare(
        "SELECT SUM(amount) as total FROM $expenses_table 
         WHERE expense_date_gregorian >= %s 
         AND expense_date_gregorian <= %s",
        $month_start_str,
        $month_end_str
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
?>

<div class="wrap">
    <h1 class="wp-heading-inline">گزارشات باشگاه - درآمد و هزینه‌ها</h1>
    <hr class="wp-header-end">
    
    <!-- فیلتر بازه تاریخی -->
    <form method="GET" action="" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
        <input type="hidden" name="page" value="sc-reports-income-expenses">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label>بازه تاریخ (شمسی)</label>
                </th>
                <td>
                    <input type="text" name="filter_date_from_shamsi" id="filter_date_from_shamsi" 
                           value="<?php echo esc_attr($filter_date_from_shamsi); ?>" 
                           class="regular-text persian-date-input" 
                           placeholder="از تاریخ (شمسی)" 
                           style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                    <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                    <span>تا</span>
                    <input type="text" name="filter_date_to_shamsi" id="filter_date_to_shamsi" 
                           value="<?php echo esc_attr($filter_date_to_shamsi); ?>" 
                           class="regular-text persian-date-input" 
                           placeholder="تا تاریخ (شمسی)" 
                           style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                    <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                    <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
            <a href="<?php echo admin_url('admin.php?page=sc-reports-income-expenses'); ?>" class="button">بازنشانی</a>
        </p>
    </form>
    
    <!-- کارت‌های خلاصه -->
    <div class="sc-dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        <div class="sc-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666;">کل درآمد</h3>
            <div style="font-size: 36px; font-weight: bold; color: #00a32a;">
                <?php echo number_format($total_income, 0, '.', ','); ?> تومان
            </div>
            <div style="margin-top: 10px; font-size: 14px; color: <?php echo $income_change >= 0 ? '#00a32a' : '#d63638'; ?>;">
                <?php if ($income_change != 0) : ?>
                    <?php echo $income_change >= 0 ? '↑' : '↓'; ?> 
                    <?php echo number_format(abs($income_change), 1); ?>% 
                    نسبت به دوره قبل
                <?php else : ?>
                    تغییر نکرده
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sc-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666;">کل هزینه‌ها</h3>
            <div style="font-size: 36px; font-weight: bold; color: #d63638;">
                <?php echo number_format($total_expenses, 0, '.', ','); ?> تومان
            </div>
            <div style="margin-top: 10px; font-size: 14px; color: <?php echo $expenses_change >= 0 ? '#d63638' : '#00a32a'; ?>;">
                <?php if ($expenses_change != 0) : ?>
                    <?php echo $expenses_change >= 0 ? '↑' : '↓'; ?> 
                    <?php echo number_format(abs($expenses_change), 1); ?>% 
                    نسبت به دوره قبل
                <?php else : ?>
                    تغییر نکرده
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sc-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666;">سود نهایی</h3>
            <div style="font-size: 36px; font-weight: bold; color: <?php echo $profit >= 0 ? '#00a32a' : '#d63638'; ?>;">
                <?php echo number_format($profit, 0, '.', ','); ?> تومان
            </div>
            <div style="margin-top: 10px; font-size: 14px; color: <?php echo $profit_change >= 0 ? '#00a32a' : '#d63638'; ?>;">
                <?php if ($profit_change != 0) : ?>
                    <?php echo $profit_change >= 0 ? '↑' : '↓'; ?> 
                    <?php echo number_format(abs($profit_change), 1); ?>% 
                    نسبت به دوره قبل
                <?php else : ?>
                    تغییر نکرده
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sc-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666;">تعداد صورتحساب‌های پرداخت شده</h3>
            <div style="font-size: 36px; font-weight: bold; color: #2271b1;">
                <?php echo $paid_invoices_count; ?>
            </div>
        </div>
    </div>
    
    <!-- نمودار میله‌ای -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2>نمودار درآمد و هزینه‌ها (6 ماه آخر)</h2>
        <canvas id="incomeExpensesChart" style="max-height: 400px;"></canvas>
    </div>
    
    <!-- لیست ماهیانه -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 20px 0;">
        <h2>گزارش ماهیانه</h2>
        <div class="back_attendance_list">
            <table class="wp-list-table widefat fixed striped">
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
                            <td style="color: #00a32a; font-weight: bold;">
                                <?php echo number_format($month['income'], 0, '.', ','); ?> تومان
                            </td>
                            <td style="color: #d63638; font-weight: bold;">
                                <?php echo number_format($month['expenses'], 0, '.', ','); ?> تومان
                            </td>
                            <td style="color: <?php echo $month['profit'] >= 0 ? '#00a32a' : '#d63638'; ?>; font-weight: bold;">
                                <?php echo number_format($month['profit'], 0, '.', ','); ?> تومان
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
                    backgroundColor: 'rgba(0, 163, 42, 0.6)',
                    borderColor: 'rgba(0, 163, 42, 1)',
                    borderWidth: 1
                },
                {
                    label: 'هزینه',
                    data: expensesData,
                    backgroundColor: 'rgba(214, 54, 56, 0.6)',
                    borderColor: 'rgba(214, 54, 56, 1)',
                    borderWidth: 1
                },
                {
                    label: 'سود نهایی',
                    data: profitData,
                    backgroundColor: 'rgba(34, 113, 177, 0.6)',
                    borderColor: 'rgba(34, 113, 177, 1)',
                    borderWidth: 1
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

