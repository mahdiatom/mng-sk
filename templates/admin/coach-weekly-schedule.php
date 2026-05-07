<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('sc_view_coach_salary') && !current_user_can('coach')) {
    wp_die('دسترسی غیرمجاز.');
}
$coach_id = function_exists('sc_current_user_coach_id') ? sc_current_user_coach_id() : 0;
if ($coach_id <= 0) {
    wp_die('اطلاعات مربی یافت نشد.');
}
global $wpdb;
$course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
$courses_table = $wpdb->prefix . 'sc_courses';
$slots_table = $wpdb->prefix . 'sc_course_weekly_schedule';
$private_sessions_table = $wpdb->prefix . 'sc_private_booking_sessions';
$weekday_labels = function_exists('sc_course_weekday_labels_ir') ? sc_course_weekday_labels_ir() : [];

$weekly_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT c.id AS course_id, c.title AS course_title, c.course_type, s.weekday, s.time_start, s.time_end
     FROM {$course_coaches_table} cc
     INNER JOIN {$courses_table} c ON c.id = cc.course_id
     INNER JOIN {$slots_table} s ON s.course_id = c.id
     WHERE cc.coach_id = %d
       AND c.deleted_at IS NULL
     ORDER BY s.weekday ASC, s.time_start ASC",
    $coach_id
));

$upcoming_private = $wpdb->get_results($wpdb->prepare(
    "SELECT ps.id, ps.course_id, ps.member_id, ps.session_date, ps.time_start, ps.time_end, ps.status, c.title AS course_title
     FROM {$private_sessions_table} ps
     INNER JOIN {$courses_table} c ON c.id = ps.course_id
     WHERE ps.coach_id = %d
       AND ps.session_date >= %s
     ORDER BY ps.session_date ASC, ps.time_start ASC
     LIMIT 200",
    $coach_id,
    current_time('Y-m-d')
));
?>
<div class="wrap sc-coach-panel-wrap">
    <div class="sc-coach-panel-header">
        <h1 class="sc-coach-panel-title">برنامه هفتگی من</h1>
        <p class="sc-coach-panel-desc">نمای کلی از برنامه دوره‌های عادی و خصوصی شما.</p>
    </div>

    <h2>برنامه هفتگی دوره‌ها</h2>
    <div class="sc-coach-panel-card">
        <?php if (empty($weekly_rows)) : ?>
            <p>برای شما برنامه هفتگی دوره‌ای ثبت نشده است.</p>
        <?php else : ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>روز</th>
                        <th>ساعت</th>
                        <th>دوره</th>
                        <th>نوع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weekly_rows as $row) : ?>
                        <tr>
                            <td><?php echo esc_html(isset($weekday_labels[(int) $row->weekday]) ? $weekday_labels[(int) $row->weekday] : '-'); ?></td>
                            <td><?php echo esc_html(substr((string) $row->time_start, 0, 5) . ' تا ' . substr((string) $row->time_end, 0, 5)); ?></td>
                            <td><?php echo esc_html($row->course_title); ?></td>
                            <td><?php echo esc_html($row->course_type === 'private' ? 'خصوصی/نیمه‌خصوصی' : 'گروهی'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <h2 style="margin-top:24px;">جلسات خصوصی پیش رو</h2>
    <div class="sc-coach-panel-card">
        <?php if (empty($upcoming_private)) : ?>
            <p>جلسه خصوصی برنامه‌ریزی شده‌ای وجود ندارد.</p>
        <?php else : ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>ساعت</th>
                        <th>دوره</th>
                        <th>بازیکن</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_private as $row) : ?>
                        <tr>
                            <td><?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($row->session_date) : $row->session_date); ?></td>
                            <td><?php echo esc_html(substr((string) $row->time_start, 0, 5) . ' تا ' . substr((string) $row->time_end, 0, 5)); ?></td>
                            <td><?php echo esc_html($row->course_title); ?></td>
                            <td>#<?php echo esc_html((string) $row->member_id); ?></td>
                            <td><?php echo esc_html($row->status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
