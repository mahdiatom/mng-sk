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
?>

<div class="wrap">
    <h1 class="wp-heading-inline">کیف پول</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-coach-withdrawals'); ?>" class="page-title-action">💸 درخواست برداشت</a>
    <a href="<?php echo admin_url('admin.php?page=sc-coach-salary'); ?>" class="page-title-action">📊 لیست دستمزد</a>
    <hr class="wp-header-end">
    
    <?php if ($withdrawal_message): ?>
        <div class="notice notice-<?php echo $withdrawal_message_type; ?> is-dismissible">
            <p><?php echo esc_html($withdrawal_message); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- نمایش موجودی -->
    <div class="notice notice-info" style="padding: 20px; margin: 20px 0;">
        <h2 style="margin-top: 0;">💰 موجودی کیف پول: 
            <strong style="font-size: 24px; color: <?php echo $wallet_balance < 0 ? '#d63638' : '#2271b1'; ?>;">
                <?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> تومان
            </strong>
        </h2>
        <?php if ($wallet_debt > 0): ?>
            <p style="margin-top: 8px; color: #d63638;">
                بدهی کیف پول: <strong><?php echo esc_html(sc_format_amount_display($wallet_debt)); ?> تومان</strong>
            </p>
        <?php endif; ?>
        <?php if ($min_withdrawal > 0): ?>
            <p>حداقل مبلغ برداشت: <strong><?php echo esc_html(sc_format_amount_display($min_withdrawal)); ?> تومان</strong></p>
        <?php endif; ?>
    </div>
    
    <!-- آمار و اطلاعات مفید -->
    <div class="card" style="margin: 20px 0; max-width: 100%; padding: 20px;">
        <h2 style="margin-top: 0;">📊 خلاصه وضعیت</h2>
        <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 15px;">
            <div style="flex: 1; min-width: 200px; padding: 15px; background: #f0f9ff; border-radius: 8px; border-right: 4px solid #0ea5e9;">
                <strong>مجموع درآمد شما</strong><br>
                <span style="font-size: 20px; color: #0ea5e9; font-weight: bold;"><?php echo esc_html(sc_format_amount_display($total_income)); ?> تومان</span><br>
                <small style="color: #666;">موجودی + پرداختی + منتظر پرداخت + در انتظار تایید</small>
            </div>
            <div style="flex: 1; min-width: 200px; padding: 15px; background: #f0fdf4; border-radius: 8px; border-right: 4px solid #22c55e;">
                <strong>مجموع پرداختی به شما</strong><br>
                <span style="font-size: 20px; color: #22c55e; font-weight: bold;"><?php echo esc_html(sc_format_amount_display($total_withdrawn)); ?> تومان</span><br>
                <small style="color: #666;">درخواست‌های پرداخت شده</small>
            </div>
            <?php if ($wallet_debt > 0): ?>
            <div style="flex: 1; min-width: 200px; padding: 15px; background: #fef2f2; border-radius: 8px; border-right: 4px solid #d63638;">
                <strong>بدهی کیف پول</strong><br>
                <span style="font-size: 20px; color: #d63638; font-weight: bold;"><?php echo esc_html(sc_format_amount_display($wallet_debt)); ?> تومان</span><br>
                <small style="color: #666;">موجودی منفی</small>
            </div>
            <?php endif; ?>
            <div style="flex: 1; min-width: 200px; padding: 15px; background: #fff7ed; border-radius: 8px; border-right: 4px solid #f97316;">
                <strong>در انتظار تایید</strong><br>
                <span style="font-size: 20px; color: #f97316; font-weight: bold;"><?php echo $count_pending; ?></span> درخواست<br>
                <small style="color: #666;">منتظر بررسی مدیر</small>
            </div>
            <div style="flex: 1; min-width: 200px; padding: 15px; background: #eff6ff; border-radius: 8px; border-right: 4px solid #3b82f6;">
                <strong>تایید شده (منتظر پرداخت)</strong><br>
                <span style="font-size: 20px; color: #3b82f6; font-weight: bold;"><?php echo $count_approved; ?></span> درخواست<br>
                <?php if ($sum_approved > 0): ?>
                <small style="color: #666;">مبلغ: <?php echo esc_html(sc_format_amount_display($sum_approved)); ?> تومان</small>
                <?php endif; ?>
            </div>
            <div style="flex: 1; min-width: 200px; padding: 15px; background: #f0fdf4; border-radius: 8px; border-right: 4px solid #22c55e;">
                <strong>پرداخت شده</strong><br>
                <span style="font-size: 20px; color: #22c55e; font-weight: bold;"><?php echo $count_paid; ?></span> درخواست<br>
                <small style="color: #666;">تسویه شده</small>
            </div>
            <div style="flex: 1; min-width: 200px; padding: 15px; background: #fef2f2; border-radius: 8px; border-right: 4px solid #ef4444;">
                <strong>رد شده</strong><br>
                <span style="font-size: 20px; color: #ef4444; font-weight: bold;"><?php echo $count_rejected; ?></span> درخواست<br>
                <small style="color: #666;">توسط مدیر رد شده</small>
            </div>
        </div>
    </div>
    
    <!-- تراکنش‌های کیف پول -->
    <div class="card" style="margin: 20px 0; max-width: 100%;">
        <h2>تراکنش‌های کیف پول</h2>
        
        <!-- فیلترها -->
        <div class="sc-filter-wrapper" style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 8px;">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                <input type="hidden" name="page" value="sc-coach-wallet">

                <div style="min-width: 180px;">
                    <label for="filter_date_from_shamsi">از تاریخ:</label><br>
                    <input type="text"
                           name="filter_date_from_shamsi"
                           id="filter_date_from_shamsi"
                           value="<?php echo esc_attr($filter_date_from_shamsi); ?>"
                           class="regular-text persian-date-input"
                           placeholder="از تاریخ (شمسی)"
                           readonly
                           style="width: 100%;">
                    <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                </div>

                <div style="min-width: 180px;">
                    <label for="filter_date_to_shamsi">تا تاریخ:</label><br>
                    <input type="text"
                           name="filter_date_to_shamsi"
                           id="filter_date_to_shamsi"
                           value="<?php echo esc_attr($filter_date_to_shamsi); ?>"
                           class="regular-text persian-date-input"
                           placeholder="تا تاریخ (شمسی)"
                           readonly
                           style="width: 100%;">
                    <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                </div>

                <div style="min-width: 180px;">
                    <label for="filter_type">نوع تراکنش:</label><br>
                    <select name="filter_type" id="filter_type" style="width: 100%;">
                        <option value="all" <?php selected($filter_type, 'all'); ?>>همه انواع</option>
                        <option value="salary_percentage" <?php selected($filter_type, 'salary_percentage'); ?>>دستمزد درصدی</option>
                        <option value="salary_fixed" <?php selected($filter_type, 'salary_fixed'); ?>>دستمزد ثابت</option>
                        <option value="charge" <?php selected($filter_type, 'charge'); ?>>شارژ</option>
                        <option value="deduct" <?php selected($filter_type, 'deduct'); ?>>کسر</option>
                        <option value="withdrawal" <?php selected($filter_type, 'withdrawal'); ?>>برداشت</option>
                    </select>
                </div>

                <div style="min-width: 200px;">
                    <label for="filter_search">جستجو:</label><br>
                    <input type="text"
                           name="filter_search"
                           id="filter_search"
                           value="<?php echo esc_attr($filter_search); ?>"
                           class="regular-text"
                           placeholder="جستجو در توضیحات یا مبلغ..."
                           style="width: 100%;">
                </div>

                <div style="min-width: 140px;">
                    <button type="submit" class="button button-primary">اعمال فیلتر</button>
                    <a href="<?php echo admin_url('admin.php?page=sc-coach-wallet'); ?>" class="button">پاک کردن</a>
                </div>
            </form>
        </div>
        
        <?php if (empty($transactions)): ?>
            <p>هیچ تراکنشی ثبت نشده است.</p>
        <?php else: ?>
            <div class="sc-wallet-transactions-table-wrapper">
            <table class="wp-list-table widefat fixed striped sc-wallet-transactions-table">
                <thead>
                    <tr>
                        <th class="column-index">ردیف</th>
                        <th class="column-date">تاریخ</th>
                        <th class="column-type">نوع</th>
                        <th class="column-amount">مبلغ</th>
                        <th class="column-balance-before">موجودی قبل</th>
                        <th class="column-balance-after">موجودی بعد</th>
                        <th class="column-description">توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $start_number = ($current_page - 1) * $per_page;
                    $row = 1;
                    $type_labels = [
                        'salary_percentage' => 'دستمزد درصدی',
                        'salary_fixed' => 'دستمزد ثابت',
                        'charge' => 'شارژ',
                        'deduct' => 'کسر',
                        'withdrawal' => 'برداشت'
                    ];
                    ?>
                    <?php foreach ($transactions as $index => $transaction): ?>
                        <?php $row_number = $start_number + $index + 1; ?>
                        <tr>
                            <td class="column-index"><?php echo $row_number; ?></td>
                            <td class="column-date"><?php echo sc_date_shamsi($transaction->created_at, 'Y/m/d H:i'); ?></td>
                            <td class="column-type"><?php echo $type_labels[$transaction->transaction_type] ?? $transaction->transaction_type; ?></td>
                            <td class="column-amount">
                                <?php if (in_array($transaction->transaction_type, ['charge', 'salary_percentage', 'salary_fixed'])): ?>
                                    <span style="color: #00a32a;">+<?php echo esc_html(sc_format_amount_display($transaction->amount)); ?></span>
                                <?php else: ?>
                                    <span style="color: #d63638;"><?php echo esc_html(sc_format_amount_display(-$transaction->amount)); ?></span>
                                <?php endif; ?>
                                <small>تومان</small>
                            </td>
                            <td class="column-balance-before"><?php echo esc_html(sc_format_amount_display($transaction->balance_before)); ?> تومان</td>
                            <td class="column-balance-after"><strong><?php echo esc_html(sc_format_amount_display($transaction->balance_after)); ?> تومان</strong></td>
                            <td class="column-description"><?php echo esc_html($transaction->description ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate" style="margin-top: 20px;">
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
    
    <style>
        /* استایل اختصاصی جدول تراکنش‌های کیف پول */
        .sc-wallet-transactions-table-wrapper {
            overflow-x: auto;
            margin-top: 10px;
        }

        .sc-wallet-transactions-table th,
        .sc-wallet-transactions-table td {
            vertical-align: middle;
        }

        .sc-wallet-transactions-table .column-index {
            width: 60px;
            text-align: center;
        }

        .sc-wallet-transactions-table .column-date {
            width: 150px;
        }

        .sc-wallet-transactions-table .column-type {
            width: 130px;
        }

        .sc-wallet-transactions-table .column-amount,
        .sc-wallet-transactions-table .column-balance-before,
        .sc-wallet-transactions-table .column-balance-after {
            width: 140px;
            text-align: right;
        }

        .sc-wallet-transactions-table .column-description {
            min-width: 240px;
            white-space: normal;
        }

        @media (max-width: 960px) {
            .sc-wallet-transactions-table th,
            .sc-wallet-transactions-table td {
                padding: 6px 8px;
                font-size: 12px;
            }
        }

        @media (max-width: 782px) {
            .sc-wallet-transactions-table-wrapper {
                margin: 0 -10px;
            }

            .sc-wallet-transactions-table th,
            .sc-wallet-transactions-table td {
                padding: 6px 6px;
                font-size: 11px;
            }

            .sc-wallet-transactions-table .column-date,
            .sc-wallet-transactions-table .column-type,
            .sc-wallet-transactions-table .column-amount,
            .sc-wallet-transactions-table .column-balance-before,
            .sc-wallet-transactions-table .column-balance-after {
                font-size: 11px;
            }

            .sc-wallet-transactions-table .column-description {
                min-width: 260px;
            }
        }
    </style>
</div>

