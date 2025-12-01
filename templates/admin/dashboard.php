<?php
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
     LEFT JOIN $member_courses_table mc ON c.id = mc.course_id AND mc.status = 'active'
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
<div class="wrap">
    <h1>داشبورد SportClub Manager</h1>
    
    <div class="sc-dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        <div class="sc-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666;">کل بازیکنان</h3>
            <div style="font-size: 36px; font-weight: bold; color: #2271b1;"><?php echo $total_members; ?></div>
        </div>
        
        <div class="sc-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666;">بازیکنان فعال</h3>
            <div style="font-size: 36px; font-weight: bold; color: #00a32a;"><?php echo $active_members; ?></div>
        </div>
        
        <div class="sc-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666;">بازیکنان غیرفعال</h3>
            <div style="font-size: 36px; font-weight: bold; color: #d63638;"><?php echo $inactive_members; ?></div>
        </div>
        
        <div class="sc-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666;">دوره‌های فعال</h3>
            <div style="font-size: 36px; font-weight: bold; color: #2271b1;"><?php echo $active_courses; ?></div>
        </div>
        
        <div class="sc-stat-box" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #666;">ثبت‌نام‌های فعال</h3>
            <div style="font-size: 36px; font-weight: bold; color: #2271b1;"><?php echo $total_enrollments; ?></div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>بازیکنان بر اساس دوره</h2>
            <canvas id="courseChart" style="max-height: 300px;"></canvas>
        </div>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>بازیکنان جدید (6 ماه گذشته)</h2>
            <canvas id="monthlyChart" style="max-height: 300px;"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // نمودار دوره‌ها
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
                    backgroundColor: 'rgba(34, 113, 177, 0.6)',
                    borderColor: 'rgba(34, 113, 177, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    // نمودار ماهانه
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
                    borderColor: 'rgba(0, 163, 42, 1)',
                    backgroundColor: 'rgba(0, 163, 42, 0.1)',
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
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
});
</script>

