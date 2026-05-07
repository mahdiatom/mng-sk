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

$ws_days = [];
foreach (range(1, 7) as $day_num) {
    $ws_days[$day_num] = isset($weekday_labels[$day_num]) ? $weekday_labels[$day_num] : (string) $day_num;
}
$ws_cells = [];
foreach (range(1, 7) as $day_num) {
    $ws_cells[$day_num] = [];
}
foreach ($weekly_rows as $row) {
    $weekday = (int) $row->weekday;
    if ($weekday < 1 || $weekday > 7) {
        continue;
    }
    $ws_cells[$weekday][] = [
        'start' => substr((string) $row->time_start, 0, 5),
        'end' => substr((string) $row->time_end, 0, 5),
        'title' => (string) $row->course_title,
        'type' => ((string) $row->course_type === 'private') ? 'خصوصی/نیمه‌خصوصی' : 'گروهی',
    ];
}
$ws_has_any = false;
foreach (range(1, 7) as $day_num) {
    if (!empty($ws_cells[$day_num])) {
        $ws_has_any = true;
        break;
    }
}
?>
<div class="wrap sc-coach-panel-wrap">
    <div class="sc-coach-panel-header">
        <h1 class="sc-coach-panel-title">برنامه هفتگی من</h1>
        <p class="sc-coach-panel-desc">نمای کلی از برنامه دوره‌های عادی و خصوصی شما.</p>
    </div>

    <div class="sc-weekly-schedule-card" style="margin-bottom: 28px; padding: 20px; background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.04);">
        <h2 style="margin: 0 0 14px; font-size: 18px; font-weight: 700; color: #1a1a1a;">📅 برنامه هفتگی دوره‌ها</h2>
        <?php if (!$ws_has_any) : ?>
            <p style="margin:0;color:#666;font-size:14px;">برای شما برنامه هفتگی دوره‌ای ثبت نشده است.</p>
        <?php else : ?>
            <div style="overflow-x:auto;">
                <table class="sc-weekly-schedule-table" style="width:100%; min-width:740px; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr>
                            <?php foreach ($ws_days as $lab) : ?>
                                <th style="padding:10px 8px; background:#2271b1; color:#fff; text-align:center; border:1px solid #1e5a96; font-weight:600;">
                                    <?php echo esc_html($lab); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach (range(1, 7) as $d) : ?>
                                <td style="vertical-align:top; padding:10px 8px; border:1px solid #ddd; background:#fafafa; min-height:100px;">
                                    <?php if (empty($ws_cells[$d])) : ?>
                                        <span style="color:#bbb;">—</span>
                                    <?php else : ?>
                                        <?php foreach ($ws_cells[$d] as $slot) : ?>
                                            <div style="margin-bottom:10px; padding:10px; background:#eef6ff; border-radius:8px; border-right:3px solid #2271b1;">
                                                <div style="font-weight:700; color:#2271b1; margin-bottom:4px;">
                                                    <?php echo esc_html($slot['start']); ?> – <?php echo esc_html($slot['end']); ?>
                                                </div>
                                                <div style="color:#333; line-height:1.4; margin-bottom:4px;"><?php echo esc_html($slot['title']); ?></div>
                                                <span style="display:inline-block; font-size:11px; color:#0a4b78; background:#dbeeff; padding:2px 8px; border-radius:999px;">
                                                    <?php echo esc_html($slot['type']); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
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
