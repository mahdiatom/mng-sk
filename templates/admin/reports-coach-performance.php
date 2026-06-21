<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('sc_finance_reports_access') && !current_user_can('manage_options')) {
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

$filter_coach_id = isset($_GET['filter_coach_id']) ? absint($_GET['filter_coach_id']) : 0;

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

        // بازیکنان فعال: ثبت‌نام فعال در دوره‌هایی که این مربی روی آن‌ها است.
        if (!empty($course_ids)) {
            $holders = implode(',', array_fill(0, count($course_ids), '%d'));
            $sql       = "SELECT COUNT(DISTINCT mc.member_id) FROM $mc_table mc
                INNER JOIN $courses_table c ON c.id = mc.course_id AND c.deleted_at IS NULL
                WHERE mc.status = 'active' AND mc.course_id IN ($holders)";
            $active_players_now = (int) $wpdb->get_var($wpdb->prepare($sql, ...$course_ids));
        }

        // درآمد مربی از کیف پول (دستمزد) در بازه.
        $coach_income_period = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM $wallet_table
             WHERE coach_id = %d AND status = 'completed'
               AND transaction_type IN ('salary_percentage','salary_fixed')
               AND DATE(created_at) >= %s AND DATE(created_at) <= %s",
            $filter_coach_id,
            $filter_date_from,
            $filter_date_to
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
        $private_revenue_period = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(i.amount), 0)
             FROM $bookings_table b
             INNER JOIN $invoices_table i ON i.id = b.invoice_id
             INNER JOIN $courses_table c ON c.id = b.course_id AND c.course_type = 'private'
             WHERE b.coach_id = %d
               AND i.status IN ('paid','completed','processing')
               AND i.payment_date IS NOT NULL
               AND DATE(i.payment_date) >= %s AND DATE(i.payment_date) <= %s",
            $filter_coach_id,
            $filter_date_from,
            $filter_date_to
        ));

        $private_sessions_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table ps
             INNER JOIN $courses_table c ON c.id = ps.course_id AND c.course_type = 'private'
             WHERE ps.coach_id = %d
               AND ps.session_date >= %s AND ps.session_date <= %s
               AND ps.status <> 'cancelled'",
            $filter_coach_id,
            $filter_date_from,
            $filter_date_to
        ));

        // عملکرد از رکوردهای دستمزد (تعداد روزهای ثبت‌شده، مجموع و میانگین نفرات جلسه).
        $perf_row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS days_cnt,
                    COALESCE(SUM(attendance_count), 0) AS sum_heads,
                    COALESCE(AVG(attendance_count), 0) AS avg_heads
             FROM $salary_table
             WHERE coach_id = %d
               AND attendance_date >= %s AND attendance_date <= %s",
            $filter_coach_id,
            $filter_date_from,
            $filter_date_to
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
<div class="wrap sc_setting_section">
    <h1 class="wp-heading-inline">گزارشات باشگاه — عملکرد مربی</h1>
    <hr class="wp-header-end">

    <form method="get" action="" class="form_fillter_attendance form_fillter_attendance_tab1">
        <input type="hidden" name="page" value="sc-reports-coach-performance">

        <div class="sc-filter-grid">

            <!-- مربی -->
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

            <!-- بازه تاریخ (در انتها) -->
            <div class="sc-filter-field sc-filter-date">
                <label class="sc-filter-label">بازه تاریخ (شمسی)</label>
                <div class="sc-date-range">
                    <input type="text"
                           name="filter_date_from_shamsi"
                           value="<?php echo esc_attr($filter_date_from_shamsi); ?>"
                           class="persian-date-input sc-filter-control"
                           readonly>
                    <input type="hidden" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                    <input type="text"
                           name="filter_date_to_shamsi"
                           value="<?php echo esc_attr($filter_date_to_shamsi); ?>"
                           class="persian-date-input sc-filter-control"
                           readonly>
                    <input type="hidden" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                </div>
            </div>

        </div>

        <p class="submit">
            <input type="submit" class="button button-primary" value="نمایش گزارش">
            <a href="<?php echo esc_url($page_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
        </p>
    </form>

    <?php if (!$filter_coach_id) : ?>
        <div class="notice notice-info"><p>یک مربی و بازه تاریخ را انتخاب کنید.</p></div>
    <?php elseif (!$coach_row) : ?>
        <div class="notice notice-error"><p>مربی یافت نشد.</p></div>
    <?php else : ?>
        <p style="margin:12px 0;">
            <strong><?php echo esc_html(trim($coach_row->first_name . ' ' . $coach_row->last_name)); ?></strong>
            — بازه:
            <?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($filter_date_from) : $filter_date_from); ?>
            تا
            <?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($filter_date_to) : $filter_date_to); ?>
        </p>

        <div class="sc-dashboard-stats">
            <div class="sc-stat-box">
                <h3>بازیکنان فعال فعلی</h3>
                <div style="font-size: 24px; font-weight: bold;"><?php echo (int) $active_players_now; ?></div>
                <p class="description" style="margin-top:8px;">ثبت‌نام فعال در دوره‌هایی که این مربی روی آن‌ها تعریف شده است.</p>
            </div>
            <div class="sc-stat-box">
                <h3>درآمد مربی (دستمزد در بازه)</h3>
                <div style="font-size: 22px; font-weight: bold; color: #2271b1;">
                    <?php echo esc_html(number_format($coach_income_period, 0, '.', ',')); ?> تومان
                </div>
            </div>
            <div class="sc-stat-box">
                <h3>درآمد کل دوره‌ها (فاکتور در بازه)</h3>
                <div style="font-size: 22px; font-weight: bold;">
                    <?php echo esc_html(number_format($total_class_revenue_period, 0, '.', ',')); ?> تومان
                </div>
                <p class="description" style="margin-top:8px;">جمع مبالغ پرداخت‌شده صورت‌حساب دوره‌های این مربی.</p>
            </div>
            <div class="sc-stat-box">
                <h3>سهم مجموعه (تخمینی)</h3>
                <div style="font-size: 22px; font-weight: bold; color: <?php echo $club_share_period >= 0 ? '#00a32a' : '#d63638'; ?>;">
                    <?php echo esc_html(number_format($club_share_period, 0, '.', ',')); ?> تومان
                </div>
                <p class="description" style="margin-top:8px;">درآمد کل دوره منهای دستمزد ثبت‌شده برای مربی در همین بازه.</p>
            </div>
        </div>

        <div class="sc-dashboard-stats">
            <div class="sc-stat-box">
                <h3>کلاس خصوصی — درآمد</h3>
                <div style="font-size: 22px; font-weight: bold; color: #00a32a;">
                    <?php echo esc_html(number_format($private_revenue_period, 0, '.', ',')); ?> تومان
                </div>
            </div>
            <div class="sc-stat-box">
                <h3>کلاس خصوصی — تعداد جلسات</h3>
                <div style="font-size: 24px; font-weight: bold;"><?php echo (int) $private_sessions_count; ?></div>
                <p class="description" style="margin-top:8px;">جلسات ثبت‌شده در بازه (غیر لغو).</p>
            </div>
            <div class="sc-stat-box">
                <h3>عملکرد مربی (دستمزد جلسه‌ای)</h3>
                <div style="font-size: 14px; line-height: 1.7;">
                    روزهای دارای رکورد دستمزد: <strong><?php echo (int) $salary_records_days; ?></strong><br>
                    مجموع نفرات جلسه (سرشمار): <strong><?php echo (int) $salary_total_participants; ?></strong><br>
                    میانگین نفر در جلسه: <strong><?php echo esc_html(number_format($salary_avg_participants, 1, '.', ',')); ?></strong>
                </div>
            </div>
            <div class="sc-stat-box">
                <h3>ثبت حضور و غیاب</h3>
                <div style="font-size: 24px; font-weight: bold;"><?php echo (int) $attendance_rows_period; ?></div>
                <p class="description" style="margin-top:8px;">تعداد ردیف‌های حضور ثبت‌شده برای دوره‌های این مربی در بازه.</p>
            </div>
        </div>

        <div class="sc-stat-box" style="margin-top: 20px;">
            <h2>نمودار ماهانه — تعداد دانشجویان فعال (منحنی)</h2>
            <p class="description">تعداد بازیکنان فعال در پایان هر ماه در دوره‌های این مربی.</p>
            <div style="max-width: 960px; margin-top: 16px;">
                <canvas id="coachActiveMonthlyChart" style="max-height: 380px;"></canvas>
            </div>
        </div>

        <div class="sc-stat-box" style="margin-top: 20px;">
            <h2>نمودار ماهانه — عضو جدید و ریزش</h2>
            <p class="description">ثبت‌نام جدید و بازیکنانی که از ابتدا تا پایان ماه دیگر فعال نیستند.</p>
            <div style="max-width: 960px; margin-top: 16px;">
                <canvas id="coachNewChurnChart" style="max-height: 380px;"></canvas>
            </div>
        </div>

        <div class="chart_dashboard" style="display:flex;flex-wrap:wrap;gap:20px;margin-top:20px;">
            <div class="sc-stat-box" style="flex:1;min-width:300px;">
                <h2>درآمد مربی (ماهانه)</h2>
                <canvas id="coachIncomeChart" style="max-height:340px;"></canvas>
            </div>
            <div class="sc-stat-box" style="flex:1;min-width:300px;">
                <h2>میانگین نفر جلسه (ماهانه)</h2>
                <canvas id="coachAvgAttendanceChart" style="max-height:340px;"></canvas>
            </div>
        </div>

        <div class="sc-stat-box" style="margin-top: 20px;">
            <h2>جدول ماهانه</h2>
            <table class="wp-list-table widefat fixed striped" style="max-width:900px;">
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

        <div class="sc-stat-box" style="margin-top: 20px;">
            <h2>خلاصه مالی دوره‌ها</h2>
            <table class="wp-list-table widefat fixed striped" style="max-width: 720px;">
                <tbody>
                    <tr>
                        <th scope="row">درآمد کل (فاکتورهای دوره در بازه)</th>
                        <td><?php echo esc_html(number_format($total_class_revenue_period, 0, '.', ',')); ?> تومان</td>
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
                        <td><?php echo esc_html(number_format($private_revenue_period, 0, '.', ',')); ?> تومان</td>
                    </tr>
                </tbody>
            </table>
        </div>

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
                            borderColor: 'rgba(34, 113, 177, 1)',
                            backgroundColor: 'rgba(34, 113, 177, 0.12)',
                            tension: 0.45,
                            fill: true,
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
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
                                backgroundColor: 'rgba(0, 163, 42, 0.7)'
                            },
                            {
                                label: 'ریزش',
                                data: monthly.map(function (r) { return parseInt(r.churn, 10) || 0; }),
                                backgroundColor: 'rgba(214, 54, 56, 0.7)'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
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
                            borderColor: 'rgba(0, 163, 42, 1)',
                            backgroundColor: 'rgba(0, 163, 42, 0.1)',
                            tension: 0.35,
                            fill: true
                        }]
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: true } } }
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
                            borderColor: 'rgba(240, 160, 0, 1)',
                            backgroundColor: 'rgba(240, 160, 0, 0.1)',
                            tension: 0.35,
                            fill: true
                        }]
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: true } } }
                });
            }
        });
        </script>
    <?php endif; ?>
</div>
