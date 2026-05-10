<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('sc_view_coach_salary')) {
    wp_die('دسترسی غیرمجاز.');
}
$coach_id = function_exists('sc_current_user_coach_id') ? sc_current_user_coach_id() : 0;
if ($coach_id <= 0) {
    wp_die('اطلاعات مربی یافت نشد.');
}
global $wpdb;
$coaches_table_mc = $wpdb->prefix . 'sc_coaches';
$course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
$courses_table = $wpdb->prefix . 'sc_courses';
$coach_settlement_type_row = $wpdb->get_row($wpdb->prepare(
    "SELECT settlement_type FROM $coaches_table_mc WHERE id = %d LIMIT 1",
    $coach_id
));
$coach_settlement_type_g = $coach_settlement_type_row ? $coach_settlement_type_row->settlement_type : '';
$courses = $wpdb->get_results($wpdb->prepare(
    "SELECT c.id, c.title, cc.salary_percentage , c.price, c.sessions_count, c.start_date, c.end_date, c.is_active
     FROM $courses_table c
     INNER JOIN $course_coaches_table cc ON cc.course_id = c.id AND cc.coach_id = %d
     WHERE c.deleted_at IS NULL
     ORDER BY c.title",
    $coach_id
));
?>
<div class="wrap sc-coach-panel-wrap">
    <div class="sc-coach-panel-header">
        <h1 class="sc-coach-panel-title">دوره‌های من</h1>
        <p class="sc-coach-panel-desc">فقط دوره‌هایی که به شما اختصاص داده شده است  را میتوانید مشاهده کنید .</p>
    </div>
    <?php if (empty($courses)) : ?>
        <div class="sc-coach-panel-empty">
            <span class="sc-coach-panel-empty-icon dashicons dashicons-welcome-learn-more"></span>
            <p class="sc-coach-panel-empty-text">شما به هیچ دوره‌ای اختصاص داده نشده‌اید.</p>
        </div>
    <?php else : ?>
        <div class="sc-coach-panel-card">
            <table class="wp-list-table widefat fixed striped sc-coach-my-courses-table">
                <thead>
                    <tr>
                        <th class="column-title">عنوان</th>
                        <th class="column-price">قیمت</th>
                        <th class="column-price">نوع همکاری</th>
                        <th class="column-salary_percentage">درصد همکاری</th>
                        <th class="column-status">وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $shamsi_start = function_exists('sc_date_shamsi_date_only');
                    foreach ($courses as $c) :
                        $start_display = $c->start_date && $shamsi_start ? sc_date_shamsi_date_only($c->start_date) : ($c->start_date ? esc_html($c->start_date) : '-');
                        $end_display   = $c->end_date && $shamsi_start ? sc_date_shamsi_date_only($c->end_date) : ($c->end_date ? esc_html($c->end_date) : '-');
                    ?>
                        <tr>
                            <td class="column-title"><strong><?php echo esc_html($c->title); ?></strong></td>
                            <td class="column-price"><?php echo $c->price ? number_format((float)$c->price, 0) : '-'; ?></td>
                            <td class="column-salary_percentage"><?php
                                if ($coach_settlement_type_g === 'both') {
                                    echo $c->salary_percentage > 0 ? 'ثابت + درصدی (جلسه)' : 'ثابت + درصدی';
                                } elseif ($coach_settlement_type_g === 'percentage') {
                                    echo $c->salary_percentage > 0 ? 'درصدی' : '-';
                                } else {
                                    echo 'ثابت';
                                }
                            ?></td>
                            <td class="column-salary_percentage"><?php echo $c->salary_percentage >0 ? number_format((float)$c->salary_percentage, 0) : '__'; ?></td>
                            <td class="column-status"><?php echo $c->is_active ? 'فعال' : 'غیرفعال'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
