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
$coaches_table = $wpdb->prefix . 'sc_coaches';
$coach = $wpdb->get_row($wpdb->prepare("SELECT * FROM $coaches_table WHERE id = %d", $coach_id));
if (!$coach) {
    wp_die('اطلاعات مربی یافت نشد.');
}
?>
<div class="wrap">
    <h1>اطلاعات من</h1>
    <p class="description">اطلاعات پروفایل شما (فقط نمایش). برای تغییر با مدیر تماس بگیرید.</p>
    <div class="sc-coach-profile-card">
        <table class="form-table">
            <tr>
                <th>نام</th>
                <td><?php echo esc_html($coach->first_name); ?></td>
            </tr>
            <tr>
                <th>نام خانوادگی</th>
                <td><?php echo esc_html($coach->last_name); ?></td>
            </tr>
            <tr>
                <th>کد ملی</th>
                <td><?php echo esc_html($coach->national_id); ?></td>
            </tr>
            <tr>
                <th>موبایل</th>
                <td><?php echo esc_html($coach->mobile_phone ?: '-'); ?></td>
            </tr>
            <tr>
                <th>جنسیت</th>
                <td><?php echo esc_html($coach->gender ?: '-'); ?></td>
            </tr>
            <tr>
                <th>تخصص</th>
                <td><?php echo esc_html($coach->specialization ?: '-'); ?></td>
            </tr>
            <tr>
                <th>سطح مربی‌گری</th>
                <td><?php echo esc_html($coach->coaching_level ?: '-'); ?></td>
            </tr>
            <tr>
                <th>سابقه مربی‌گری (سال)</th>
                <td><?php echo esc_html($coach->coaching_experience !== null ? $coach->coaching_experience : '-'); ?></td>
            </tr>
            <tr>
                <th>سوابق ورزشی</th>
                <td><?php echo $coach->sports_history ? nl2br(esc_html($coach->sports_history)) : '-'; ?></td>
            </tr>
            <tr>
                <th>وضعیت</th>
                <td><?php echo $coach->is_active ? 'فعال' : 'غیرفعال'; ?></td>
            </tr>
        </table>
    </div>
</div>
<style>.sc-coach-profile-card { background:#fff; border:1px solid #c3c4c7; border-radius:8px; padding:20px; margin-top:15px; max-width:600px; }</style>
