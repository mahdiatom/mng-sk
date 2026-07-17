<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('sc_finance_reports_access') && !current_user_can('manage_options') && !(function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only())) {
    wp_die('دسترسی غیرمجاز.');
}

sc_check_and_create_tables();

global $wpdb;

$coaches_table    = $wpdb->prefix . 'sc_coaches';
$courses_table    = $wpdb->prefix . 'sc_courses';
$cc_table         = $wpdb->prefix . 'sc_course_coaches';
$mc_table         = $wpdb->prefix . 'sc_member_courses';
$invoices_table   = $wpdb->prefix . 'sc_invoices';
$wallet_table     = $wpdb->prefix . 'sc_coach_wallet_transactions';
$salary_table     = $wpdb->prefix . 'sc_coach_salary_records';
$att_table        = $wpdb->prefix . 'sc_attendances';
$bookings_table   = $wpdb->prefix . 'sc_private_course_bookings';
$sessions_table   = $wpdb->prefix . 'sc_private_booking_sessions';

$coaches = $wpdb->get_results("SELECT id, first_name, last_name FROM $coaches_table WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");
if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only() && function_exists('sc_secretary_get_branch_coach_ids')) {
    $branch_coach_ids = sc_secretary_get_branch_coach_ids();
    if (empty($branch_coach_ids)) {
        $coaches = [];
    } else {
        $holders = implode(',', array_fill(0, count($branch_coach_ids), '%d'));
        $coaches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name FROM $coaches_table
             WHERE is_active = 1 AND id IN ($holders)
             ORDER BY first_name ASC, last_name ASC",
            ...$branch_coach_ids
        ));
    }
}

$filter_coach_id = isset($_GET['filter_coach_id']) ? absint($_GET['filter_coach_id']) : 0;
if ($filter_coach_id > 0 && function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only() && function_exists('sc_secretary_get_branch_coach_ids')) {
    if (!in_array($filter_coach_id, sc_secretary_get_branch_coach_ids(), true)) {
        $filter_coach_id = 0;
    }
}

$filter_date_from        = '';
$filter_date_to          = '';
$filter_date_from_shamsi = '';
$filter_date_to_shamsi   = '';

if (!empty($_GET['filter_date_from_shamsi'])) {
    $filter_date_from_shamsi = sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi']));
    $filter_date_from        = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($filter_date_from_shamsi) : '';
}
if (!empty($_GET['filter_date_to_shamsi'])) {
    $filter_date_to_shamsi = sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi']));
    $filter_date_to        = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($filter_date_to_shamsi) : '';
}
if ($filter_date_from === '' && !empty($_GET['filter_date_from'])) {
    $filter_date_from = sanitize_text_field(wp_unslash($_GET['filter_date_from']));
}
if ($filter_date_to === '' && !empty($_GET['filter_date_to'])) {
    $filter_date_to = sanitize_text_field(wp_unslash($_GET['filter_date_to']));
}

if ($filter_date_from === '' || $filter_date_to === '') {
    $today = new DateTimeImmutable('today', wp_timezone());
    $from  = $today->modify('-6 months');
    $filter_date_from = $from->format('Y-m-d');
    $filter_date_to   = $today->format('Y-m-d');
    if (function_exists('gregorian_to_jalali')) {
        $fj = gregorian_to_jalali((int) $from->format('Y'), (int) $from->format('m'), (int) $from->format('d'));
        $tj = gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('m'), (int) $today->format('d'));
        $filter_date_from_shamsi = $fj[0] . '/' . str_pad((string) $fj[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $fj[2], 2, '0', STR_PAD_LEFT);
        $filter_date_to_shamsi   = $tj[0] . '/' . str_pad((string) $tj[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $tj[2], 2, '0', STR_PAD_LEFT);
    }
} elseif ($filter_date_from_shamsi === '' && function_exists('sc_date_shamsi_date_only')) {
    $filter_date_from_shamsi = sc_date_shamsi_date_only($filter_date_from);
    $filter_date_to_shamsi   = sc_date_shamsi_date_only($filter_date_to);
}

// Defaults for display.
$active_players_now           = 0;
$coach_income_period          = 0.0;
$club_share_period            = 0.0;
$total_class_revenue_period   = 0.0;
$private_revenue_period       = 0.0;
$private_sessions_count       = 0;
$salary_records_days          = 0;
$salary_total_participants    = 0;
$salary_avg_participants      = 0.0;
$attendance_rows_period       = 0;
$monthly_student_chart        = [];
$monthly_coach_metrics        = [];

$coach_row = null;
$course_ids                   = [];

if ($filter_coach_id > 0) {
    $coach_row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, first_name, last_name FROM $coaches_table WHERE id = %d LIMIT 1",
        $filter_coach_id
    ));

    if ($coach_row) {
        $course_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT course_id FROM $cc_table WHERE coach_id = %d",
            $filter_coach_id
        ));
        $course_ids = array_map('absint', $course_ids ?: []);
        if (function_exists('sc_secretary_filter_course_ids')) {
            $course_ids = sc_secretary_filter_course_ids($course_ids);
        }

        // بازیکنان فعال: ثبت‌نام فعال در دوره‌هایی که این مربی روی آن‌ها است.
        if (!empty($course_ids)) {
            $holders = implode(',', array_fill(0, count($course_ids), '%d'));
            $sql       = "SELECT COUNT(DISTINCT mc.member_id) FROM $mc_table mc
                INNER JOIN $courses_table c ON c.id = mc.course_id AND c.deleted_at IS NULL
                WHERE mc.status = 'active' AND mc.course_id IN ($holders)";
            $active_players_now = (int) $wpdb->get_var($wpdb->prepare($sql, ...$course_ids));
        }

        // درآمد مربی از کیف پول (دستمزد) در بازه.
        $wallet_where = [
            'coach_id = %d',
            "status = 'completed'",
            "transaction_type IN ('salary_percentage','salary_fixed')",
            'DATE(created_at) >= %s',
            'DATE(created_at) <= %s',
        ];
        $wallet_args = [$filter_coach_id, $filter_date_from, $filter_date_to];
        if (function_exists('sc_secretary_merge_coach_wallet_course_scope')) {
            sc_secretary_merge_coach_wallet_course_scope($wallet_where, $wallet_args);
        } elseif (!empty($course_ids)) {
            $wallet_where[] = 'related_course_id IN (' . implode(',', array_fill(0, count($course_ids), '%d')) . ')';
            $wallet_args = array_merge($wallet_args, $course_ids);
        }
        $coach_income_period = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM $wallet_table w
             WHERE " . implode(' AND ', $wallet_where),
            ...$wallet_args
        ));

        // درآمد کل دوره‌ها (فاکتورهای پرداخت‌شده) برای دوره‌های این مربی در بازه.
        if (!empty($course_ids)) {
            $holders_c = implode(',', array_fill(0, count($course_ids), '%d'));
            $args_rev    = array_merge(
                [$filter_date_from, $filter_date_to],
                $course_ids
            );
            $total_class_revenue_period = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(i.amount), 0) FROM $invoices_table i
                 WHERE i.status IN ('paid','completed','processing')
                   AND i.payment_date IS NOT NULL
                   AND DATE(i.payment_date) >= %s AND DATE(i.payment_date) <= %s
                   AND i.course_id IN ($holders_c)",
                ...$args_rev
            ));
        }

        $club_share_period = $total_class_revenue_period - $coach_income_period;

        // کلاس خصوصی: درآمد از رزروها و تعداد جلسات در بازه.
        $private_branch_sql = '';
        $private_branch_args = [];
        if (!empty($course_ids)) {
            $private_branch_sql = ' AND b.course_id IN (' . implode(',', array_fill(0, count($course_ids), '%d')) . ')';
            $private_branch_args = $course_ids;
        } elseif (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()) {
            $private_revenue_period = 0.0;
            $private_sessions_count = 0;
        }
        if ($private_branch_sql !== '' || !(function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only())) {
            $private_revenue_period = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(i.amount), 0)
                 FROM $bookings_table b
                 INNER JOIN $invoices_table i ON i.id = b.invoice_id
                 INNER JOIN $courses_table c ON c.id = b.course_id AND c.course_type = 'private'
                 WHERE b.coach_id = %d
                   AND i.status IN ('paid','completed','processing')
                   AND i.payment_date IS NOT NULL
                   AND DATE(i.payment_date) >= %s AND DATE(i.payment_date) <= %s
                   {$private_branch_sql}",
                array_merge([$filter_coach_id, $filter_date_from, $filter_date_to], $private_branch_args)
            ));

            $private_sessions_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $sessions_table ps
                 INNER JOIN $courses_table c ON c.id = ps.course_id AND c.course_type = 'private'
                 WHERE ps.coach_id = %d
                   AND ps.session_date >= %s AND ps.session_date <= %s
                   AND ps.status <> 'cancelled'
                   {$private_branch_sql}",
                array_merge([$filter_coach_id, $filter_date_from, $filter_date_to], $private_branch_args)
            ));
        }

        // عملکرد از رکوردهای دستمزد (تعداد روزهای ثبت‌شده، مجموع و میانگین نفرات جلسه).
        $salary_where = [
            'coach_id = %d',
            'attendance_date >= %s',
            'attendance_date <= %s',
        ];
        $salary_args = [$filter_coach_id, $filter_date_from, $filter_date_to];
        if (function_exists('sc_secretary_merge_salary_course_scope')) {
            sc_secretary_merge_salary_course_scope($salary_where, $salary_args);
        } elseif (!empty($course_ids)) {
            $salary_where[] = 'course_id IN (' . implode(',', array_fill(0, count($course_ids), '%d')) . ')';
            $salary_args = array_merge($salary_args, $course_ids);
        }
        $perf_row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS days_cnt,
                    COALESCE(SUM(attendance_count), 0) AS sum_heads,
                    COALESCE(AVG(attendance_count), 0) AS avg_heads
             FROM $salary_table sr
             WHERE " . implode(' AND ', $salary_where),
            ...$salary_args
        ));
        if ($perf_row) {
            $salary_records_days       = (int) $perf_row->days_cnt;
            $salary_total_participants = (int) $perf_row->sum_heads;
            $salary_avg_participants   = (float) $perf_row->avg_heads;
        }

        // تعداد ردیف‌های حضور و غیاب ثبت‌شده برای دوره‌های این مربی در بازه.
        if (!empty($course_ids)) {
            $holders_a = implode(',', array_fill(0, count($course_ids), '%d'));
            $args_att  = array_merge($course_ids, [$filter_date_from, $filter_date_to]);
            $attendance_rows_period = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $att_table a
                 WHERE a.course_id IN ($holders_a)
                   AND a.attendance_date >= %s AND a.attendance_date <= %s",
                ...$args_att
            ));
        }

        // نمودارهای ماهانه (فعال، جدید، ریزش، درآمد، میانگین حضور).
        if (function_exists('sc_bi_coach_monthly_metrics')) {
            $monthly_coach_metrics = sc_bi_coach_monthly_metrics(
                $filter_coach_id,
                $course_ids,
                $filter_date_from,
                $filter_date_to
            );
            $monthly_student_chart = array_map(static function ($row) {
                return ['month' => $row['month'], 'count' => $row['new']];
            }, $monthly_coach_metrics);
        }
    }
}

$page_url = admin_url('admin.php?page=sc-reports-coach-performance');
?>
<div class="wrap sc-reports-list-wrap">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">عملکرد مربی</h1>
            <p class="sc-reports-list-desc">درآمد، بازیکنان فعال، کلاس خصوصی و روند ماهانه هر مربی.</p>
        </div>
    </div>

    <div class="sc-reports-list-filters-card">
    <form method="get" action="">
        <input type="hidden" name="page" value="sc-reports-coach-performance">
        <div class="sc-filter-grid">
            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_coach_id">مربی</label>
                <select name="filter_coach_id" id="filter_coach_id" class="sc-filter-control">
                    <option value="">— انتخاب کنید —</option>
                    <?php foreach ($coaches as $c) : ?>
                        <option value="<?php echo esc_attr($c->id); ?>" <?php selected($filter_coach_id, (int) $c->id); ?>>
                            <?php echo esc_html(trim($c->first_name . ' ' . $c->last_name)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sc-filter-field">
                <label class="sc-filter-label">از تاریخ</label>
                <input type="text" name="filter_date_from_shamsi" value="<?php echo esc_attr($filter_date_from_shamsi); ?>" class="persian-date-input sc-filter-control" readonly>
                <input type="hidden" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
            </div>
            <div class="sc-filter-field">
                <label class="sc-filter-label">تا تاریخ</label>
                <input type="text" name="filter_date_to_shamsi" value="<?php echo esc_attr($filter_date_to_shamsi); ?>" class="persian-date-input sc-filter-control" readonly>
                <input type="hidden" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
            </div>
        </div>
        <div class="sc-reports-list-filters-actions">
            <input type="submit" class="button button-primary" value="نمایش گزارش">
            <a href="<?php echo esc_url($page_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
        </div>
    </form>
    </div>

    <?php if (!$filter_coach_id) : ?>
        <div class="sc-reports-list-panel"><div class="sc-reports-empty">یک مربی و بازه تاریخ را انتخاب کنید.</div></div>
    <?php elseif (!$coach_row) : ?>
        <div class="sc-reports-list-panel"><div class="sc-reports-empty">مربی یافت نشد.</div></div>
    <?php else : ?>
        <div class="sc-reports-list-panel" style="margin-bottom:16px;">
            <span class="sc-member-identity">
                <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html(mb_substr(trim($coach_row->first_name . ' ' . $coach_row->last_name), 0, 1)); ?></span>
                <span class="sc-member-identity-text">
                    <span class="sc-member-name"><?php echo esc_html(trim($coach_row->first_name . ' ' . $coach_row->last_name)); ?></span>
                    <span class="sc-member-meta">
                        <span class="sc-member-meta-item">
                            <?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($filter_date_from) : $filter_date_from); ?>
                            تا
                            <?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($filter_date_to) : $filter_date_to); ?>
                        </span>
                    </span>
                </span>
            </span>
        </div>

        <div class="sc-reports-list-stats">
            <div class="sc-reports-list-stat-card">
                <div class="sc-reports-list-stat-label">بازیکنان فعال فعلی</div>
                <div class="sc-reports-list-stat-value is-purple"><?php echo (int) $active_players_now; ?></div>
                <p class="description">ثبت‌نام فعال در دوره‌هایی که این مربی روی آن‌ها تعریف شده است.</p>
            </div>
            <?php if (function_exists('sc_is_pro_feature_coach_rating_enabled') && sc_is_pro_feature_coach_rating_enabled()) :
                $coach_rating_stats = sc_coach_rating_get_coach_average($filter_coach_id);
                $coach_ratings_rows = sc_coach_rating_get_coach_ratings($filter_coach_id, [
                    'date_from' => $filter_date_from,
                    'date_to'   => $filter_date_to,
                ]);
                ?>
            <div class="sc-reports-list-stat-card">
                <div class="sc-reports-list-stat-label">میانگین امتیاز بازیکنان</div>
                <div class="sc-reports-list-stat-value is-blue">
                    <?php echo $coach_rating_stats['count'] > 0 ? esc_html(number_format_i18n($coach_rating_stats['average'], 1)) : '—'; ?>
                </div>
                <p class="description">
                    <?php echo $coach_rating_stats['count'] > 0
                        ? esc_html(number_format_i18n($coach_rating_stats['count'])) . ' امتیاز ثبت‌شده'
                        : 'هنوز امتیازی ثبت نشده است.'; ?>
                </p>
            </div>
            <?php endif; ?>
            <div class="sc-reports-list-stat-card">
                <div class="sc-reports-list-stat-label">درآمد مربی (دستمزد در بازه)</div>
                <div class="sc-reports-list-stat-value is-blue"><?php echo esc_html(number_format($coach_income_period, 0, '.', ',')); ?> تومان</div>
            </div>
            <div class="sc-reports-list-stat-card">
                <div class="sc-reports-list-stat-label">درآمد کل دوره‌ها (فاکتور در بازه)</div>
                <div class="sc-reports-list-stat-value"><?php echo esc_html(number_format($total_class_revenue_period, 0, '.', ',')); ?> تومان</div>
                <p class="description">جمع مبالغ پرداخت‌شده صورت‌حساب دوره‌های این مربی.</p>
            </div>
            <div class="sc-reports-list-stat-card">
                <div class="sc-reports-list-stat-label">سهم مجموعه (تخمینی)</div>
                <div class="sc-reports-list-stat-value <?php echo $club_share_period >= 0 ? 'is-credit' : 'is-debit'; ?>"><?php echo esc_html(number_format($club_share_period, 0, '.', ',')); ?> تومان</div>
                <p class="description">درآمد کل دوره منهای دستمزد ثبت‌شده برای مربی در همین بازه.</p>
            </div>
            <div class="sc-reports-list-stat-card">
                <div class="sc-reports-list-stat-label">کلاس خصوصی — درآمد</div>
                <div class="sc-reports-list-stat-value is-credit"><?php echo esc_html(number_format($private_revenue_period, 0, '.', ',')); ?> تومان</div>
            </div>
            <div class="sc-reports-list-stat-card">
                <div class="sc-reports-list-stat-label">کلاس خصوصی — تعداد جلسات</div>
                <div class="sc-reports-list-stat-value"><?php echo (int) $private_sessions_count; ?></div>
                <p class="description">جلسات ثبت‌شده در بازه (غیر لغو).</p>
            </div>
            <div class="sc-reports-list-stat-card">
                <div class="sc-reports-list-stat-label">عملکرد مربی (دستمزد جلسه‌ای)</div>
                <div class="sc-reports-list-stat-value" style="font-size:13px;font-weight:600;line-height:1.7;">
                    روزهای دارای رکورد: <strong><?php echo (int) $salary_records_days; ?></strong><br>
                    مجموع نفرات جلسه: <strong><?php echo (int) $salary_total_participants; ?></strong><br>
                    میانگین نفر در جلسه: <strong><?php echo esc_html(number_format($salary_avg_participants, 1, '.', ',')); ?></strong>
                </div>
            </div>
            <div class="sc-reports-list-stat-card">
                <div class="sc-reports-list-stat-label">ثبت حضور و غیاب</div>
                <div class="sc-reports-list-stat-value"><?php echo (int) $attendance_rows_period; ?></div>
                <p class="description">تعداد ردیف‌های حضور ثبت‌شده برای دوره‌های این مربی در بازه.</p>
            </div>
        </div>

        <div class="sc-reports-chart-grid">
            <div class="sc-reports-chart-card">
                <h2>نمودار ماهانه — تعداد دانشجویان فعال</h2>
                <p class="description">تعداد بازیکنان فعال در پایان هر ماه در دوره‌های این مربی.</p>
                <div class="sc-bi-chart-canvas"><canvas id="coachActiveMonthlyChart"></canvas></div>
            </div>
            <div class="sc-reports-chart-card">
                <h2>نمودار ماهانه — عضو جدید و ریزش</h2>
                <p class="description">ثبت‌نام جدید و بازیکنانی که از ابتدا تا پایان ماه دیگر فعال نیستند.</p>
                <div class="sc-bi-chart-canvas"><canvas id="coachNewChurnChart"></canvas></div>
            </div>
            <div class="sc-reports-chart-card">
                <h2>درآمد مربی (ماهانه)</h2>
                <div class="sc-bi-chart-canvas"><canvas id="coachIncomeChart"></canvas></div>
            </div>
            <div class="sc-reports-chart-card">
                <h2>میانگین نفر جلسه (ماهانه)</h2>
                <div class="sc-bi-chart-canvas"><canvas id="coachAvgAttendanceChart"></canvas></div>
            </div>
        </div>

        <div class="sc-reports-list-panel" style="margin-top:16px;">
            <div class="sc-reports-list-panel-header"><h2>جدول ماهانه</h2></div>
            <div class="sc-reports-list-table-card" style="margin:0;box-shadow:none;border:none;padding:0;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ماه</th>
                        <th>فعال (پایان ماه)</th>
                        <th>جدید</th>
                        <th>ریزش</th>
                        <th>درآمد مربی</th>
                        <th>میانگین نفر جلسه</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($monthly_coach_metrics)) : ?>
                    <tr><td colspan="6">داده‌ای یافت نشد.</td></tr>
                <?php else : foreach ($monthly_coach_metrics as $mrow) : ?>
                    <tr>
                        <td><?php echo esc_html($mrow['month']); ?></td>
                        <td><?php echo (int) $mrow['active']; ?></td>
                        <td><?php echo (int) $mrow['new']; ?></td>
                        <td><?php echo (int) $mrow['churn']; ?></td>
                        <td><?php echo esc_html(number_format((float) $mrow['income'], 0, '.', ',')); ?></td>
                        <td><?php echo esc_html(number_format((float) $mrow['avg_attendance'], 1, '.', ',')); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <div class="sc-reports-list-panel" style="margin-top:16px;">
            <div class="sc-reports-list-panel-header"><h2>خلاصه مالی دوره‌ها</h2></div>
            <div class="sc-reports-list-table-card" style="margin:0;box-shadow:none;border:none;padding:0;">
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <th scope="row">درآمد کل (فاکتورهای دوره در بازه)</th>
                        <td><span class="sc-reports-amount-credit"><?php echo esc_html(number_format($total_class_revenue_period, 0, '.', ',')); ?> تومان</span></td>
                    </tr>
                    <tr>
                        <th scope="row">درآمد مربی (دستمزد کیف پول در بازه)</th>
                        <td><?php echo esc_html(number_format($coach_income_period, 0, '.', ',')); ?> تومان</td>
                    </tr>
                    <tr>
                        <th scope="row">سهم مجموعه (تخمینی)</th>
                        <td><?php echo esc_html(number_format($club_share_period, 0, '.', ',')); ?> تومان</td>
                    </tr>
                    <tr>
                        <th scope="row">درآمد کلاس‌های خصوصی (جدا)</th>
                        <td><span class="sc-reports-amount-credit"><?php echo esc_html(number_format($private_revenue_period, 0, '.', ',')); ?> تومان</span></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <?php if (function_exists('sc_is_pro_feature_coach_rating_enabled') && sc_is_pro_feature_coach_rating_enabled()) : ?>
        <div class="sc-reports-list-panel" style="margin-top:16px;">
            <div class="sc-reports-list-panel-header"><h2>امتیازهای ثبت‌شده توسط بازیکنان</h2></div>
            <div class="sc-reports-list-table-card sc-coach-rating-admin-table" style="margin:0;box-shadow:none;border:none;padding:0;">
                <?php if (empty($coach_ratings_rows)) : ?>
                    <div class="sc-reports-empty">در بازه انتخاب‌شده امتیازی ثبت نشده است.</div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>بازیکن</th>
                                <th>دوره</th>
                                <th>نقش</th>
                                <th>امتیاز</th>
                                <th>توضیحات</th>
                                <th>تاریخ ثبت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coach_ratings_rows as $rating_row) :
                                $member_name = trim(($rating_row->first_name ?? '') . ' ' . ($rating_row->last_name ?? ''));
                                if ($member_name === '') {
                                    $member_name = sc_coach_rating_get_member_display_name((int) $rating_row->member_id);
                                }
                                ?>
                                <tr>
                                    <td><?php echo esc_html($member_name); ?></td>
                                    <td><?php echo esc_html($rating_row->course_title ?: ('#' . (int) $rating_row->course_id)); ?></td>
                                    <td><?php echo esc_html(sc_coach_rating_role_label($rating_row->coach_role)); ?></td>
                                    <td>
                                        <?php echo sc_coach_rating_render_stars_html((int) $rating_row->rating); ?>
                                        <strong><?php echo esc_html(number_format_i18n((int) $rating_row->rating)); ?></strong>
                                    </td>
                                    <td class="sc-coach-rating-comment-cell"><?php echo $rating_row->comment ? esc_html($rating_row->comment) : '—'; ?></td>
                                    <td><?php echo esc_html(function_exists('sc_date_shamsi') ? sc_date_shamsi($rating_row->created_at, 'Y/m/d - H:i') : $rating_row->created_at); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <script src="<?php echo esc_url(SC_ASSETS_URL . 'js/vendor/chart.min.js'); ?>"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') {
                return;
            }
            var monthly = <?php echo wp_json_encode($monthly_coach_metrics); ?>;
            if (!monthly || !monthly.length) {
                return;
            }
            var labels = monthly.map(function (r) { return r.month; });

            var activeCanvas = document.getElementById('coachActiveMonthlyChart');
            if (activeCanvas) {
                new Chart(activeCanvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'دانشجویان فعال',
                            data: monthly.map(function (r) { return parseInt(r.active, 10) || 0; }),
                            borderColor: 'rgba(109, 52, 255, 1)',
                            backgroundColor: 'rgba(109, 52, 255, 0.12)',
                            tension: 0.45,
                            fill: true,
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                        plugins: { legend: { position: 'top' } }
                    }
                });
            }

            var ncCanvas = document.getElementById('coachNewChurnChart');
            if (ncCanvas) {
                new Chart(ncCanvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'عضو جدید',
                                data: monthly.map(function (r) { return parseInt(r.new, 10) || 0; }),
                                backgroundColor: 'rgba(22, 163, 74, 0.7)'
                            },
                            {
                                label: 'ریزش',
                                data: monthly.map(function (r) { return parseInt(r.churn, 10) || 0; }),
                                backgroundColor: 'rgba(220, 38, 38, 0.7)'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                        plugins: { legend: { position: 'top' } }
                    }
                });
            }

            var incomeCanvas = document.getElementById('coachIncomeChart');
            if (incomeCanvas) {
                new Chart(incomeCanvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'درآمد مربی (تومان)',
                            data: monthly.map(function (r) { return parseFloat(r.income) || 0; }),
                            borderColor: 'rgba(109, 52, 255, 1)',
                            backgroundColor: 'rgba(109, 52, 255, 0.1)',
                            tension: 0.35,
                            fill: true
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                });
            }

            var attCanvas = document.getElementById('coachAvgAttendanceChart');
            if (attCanvas) {
                new Chart(attCanvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'میانگین نفر جلسه',
                            data: monthly.map(function (r) { return parseFloat(r.avg_attendance) || 0; }),
                            borderColor: 'rgba(217, 119, 6, 1)',
                            backgroundColor: 'rgba(217, 119, 6, 0.1)',
                            tension: 0.35,
                            fill: true
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
                });
            }
        });
        </script>
    <?php endif; ?>
</div>
