<?php

if ( ! defined('ABSPATH') ) exit;
// بررسی و ایجاد جداول در صورت عدم وجود


sc_check_and_create_tables();

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';

// آمار کلی
$total_members = $wpdb->get_var("SELECT COUNT(*) FROM $members_table");
$active_members = $wpdb->get_var("SELECT COUNT(*) FROM $members_table WHERE is_active = 1");
$inactive_members = $wpdb->get_var("SELECT COUNT(*) FROM $members_table WHERE is_active = 0");
$total_courses = $wpdb->get_var("SELECT COUNT(*) FROM $courses_table WHERE deleted_at IS NULL");
$active_courses = $wpdb->get_var("SELECT COUNT(*) FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1");
$total_enrollments = $wpdb->get_var("SELECT COUNT(*) FROM $member_courses_table WHERE status = 'active'");

// آمار بازیکنان بر اساس دوره
$course_stats = $wpdb->get_results(
    "SELECT c.id, c.title, COUNT(mc.member_id) as enrolled_count, c.capacity
     FROM $courses_table c
     LEFT JOIN $member_courses_table mc
        ON c.id = mc.course_id
       AND mc.status = 'active'
       AND (mc.course_status_flags IS NULL OR TRIM(mc.course_status_flags) = '')
     WHERE c.deleted_at IS NULL AND c.is_active = 1
     GROUP BY c.id
     ORDER BY enrolled_count DESC
     LIMIT 10"
);

// آمار بازیکنان جدید در 6 ماه گذشته
$monthly_stats = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $members_table WHERE created_at >= %s AND created_at <= %s",
        $month_start . ' 00:00:00',
        $month_end . ' 23:59:59'
    ));
    $monthly_stats[] = [
        'month' => sc_date_shamsi(date('Y-m-01', strtotime("-$i months")), 'Y/m'),
        'count' => $count
    ];
}
?>
<div class="wrap sc-dashboard-page-header sc-club-dashboard-wrap">
    <h1 class="wp-heading-inline">داشبورد مدیریت باشگاه ورزشی</h1>
    <hr class="wp-header-end">
    <p class="sc-dashboard-subtitle">خلاصه وضعیت بازیکنان، دوره‌ها و روند ثبت‌نام در باشگاه.</p>
</div>
<div class="wrap sc-dashboard-page-body sc-club-dashboard-wrap">
    <div class="sc-dashboard-panel postbox">
        <div class="postbox-header"><h2>آمار کلی</h2></div>
        <div class="inside">
            <div class="sc-dashboard-stats">
                <div class="sc-stat-box">
                    <h3>کل بازیکنان</h3>
                    <div class="sc-stat-value"><?php echo (int) $total_members; ?></div>
                </div>
                <div class="sc-stat-box sc-stat-box--success">
                    <h3>بازیکنان فعال</h3>
                    <div class="sc-stat-value"><?php echo (int) $active_members; ?></div>
                </div>
                <div class="sc-stat-box sc-stat-box--muted">
                    <h3>بازیکنان غیرفعال</h3>
                    <div class="sc-stat-value"><?php echo (int) $inactive_members; ?></div>
                </div>
                <div class="sc-stat-box">
                    <h3>دوره‌های فعال</h3>
                    <div class="sc-stat-value"><?php echo (int) $active_courses; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="chart_dashboard sc-dashboard-charts">
        <div class="chart_courses_users_dashboard sc_chart sc-dashboard-chart-panel postbox">
            <div class="postbox-header"><h2>بازیکنان بر اساس دوره</h2></div>
            <div class="inside sc-dashboard-chart-inner">
                <canvas id="courseChart"></canvas>
            </div>
        </div>

        <div class="new_users sc_chart sc-dashboard-chart-panel postbox">
            <div class="postbox-header"><h2>بازیکنان جدید (۶ ماه گذشته)</h2></div>
            <div class="inside sc-dashboard-chart-inner">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo esc_url(SC_ASSETS_URL . 'js/vendor/chart.min.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const courseCtx = document.getElementById('courseChart');
    if (courseCtx) {
        const courseData = <?php echo json_encode($course_stats); ?>;
        new Chart(courseCtx, {
            type: 'bar',
            data: {
                labels: courseData.map(c => c.title),
                datasets: [{
                    label: 'تعداد ثبت‌نام',
                    data: courseData.map(c => parseInt(c.enrolled_count)),
                    backgroundColor: 'rgba(109, 52, 255, 0.55)',
                    borderColor: 'rgba(74, 31, 184, 1)',
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) {
        const monthlyData = <?php echo json_encode($monthly_stats); ?>;
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(m => m.month),
                datasets: [{
                    label: 'بازیکنان جدید',
                    data: monthlyData.map(m => parseInt(m.count)),
                    borderColor: 'rgba(109, 52, 255, 1)',
                    backgroundColor: 'rgba(109, 52, 255, 0.12)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }
});
</script>
