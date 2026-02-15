<?php
/**
 * لاگ فعالیت ادمین افزونه — چه کسی چه عملی روی چه چیزی انجام داده
 * فقط در پنل ادمین و فقط برای عملیات‌های مهم (ایجاد/ویرایش/حذف)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ثبت یک رکورد در جدول لاگ فعالیت
 *
 * @param string       $action     created | updated | deleted
 * @param string       $entity_type member | course | coach | notification | invoice | ...
 * @param int|null     $entity_id  شناسه رکورد (در حذف می‌توان null باشد)
 * @param string       $summary    متن کوتاه فارسی برای نمایش در لیست
 * @param array|string|null $old_value داده قبل از تغییر (برای updated/deleted). به JSON ذخیره می‌شود.
 * @param array|string|null $new_value داده بعد از تغییر (برای created/updated). به JSON ذخیره می‌شود.
 */
function sc_log_activity($action, $entity_type, $entity_id, $summary, $old_value = null, $new_value = null) {
    if (!is_admin()) {
        return;
    }
    // فقط مدیر کل (administrator) — نه مربی و نه مدیر باشگاه
    if (!current_user_can('manage_options')) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sc_activity_log';

    $user_id = get_current_user_id();
    $user = $user_id ? wp_get_current_user() : null;
    $user_display = $user && $user->display_name ? $user->display_name : ('ID:' . $user_id);

    $old_json = $old_value !== null ? (is_string($old_value) ? $old_value : wp_json_encode($old_value, JSON_UNESCAPED_UNICODE)) : null;
    $new_json = $new_value !== null ? (is_string($new_value) ? $new_value : wp_json_encode($new_value, JSON_UNESCAPED_UNICODE)) : null;

    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : null;
    if (strlen($summary) > 500) {
        $summary = mb_substr($summary, 0, 497) . '…';
    }

    $wpdb->insert(
        $table,
        [
            'created_at'        => current_time('mysql'),
            'user_id'           => $user_id,
            'user_display_name' => $user_display,
            'action'            => $action,
            'entity_type'       => $entity_type,
            'entity_id'         => $entity_id,
            'summary'           => $summary,
            'old_value'         => $old_json,
            'new_value'         => $new_json,
            'ip'                => $ip,
        ],
        ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
    );
}

/**
 * پاک‌سازی لاگ‌های قدیمی‌تر از یک ماه (برای کرون ماهانه)
 */
function sc_activity_log_cleanup_old() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_activity_log';
    $cutoff = gmdate('Y-m-d H:i:s', strtotime('-1 month'));
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM `$table` WHERE created_at < %s",
        $cutoff
    ));
    return $deleted !== false ? $deleted : 0;
}

/**
 * زمان‌بندی کرون روزانه که در روز تنظیم‌شده پاک‌سازی ماهانه را اجرا می‌کند
 */
function sc_activity_log_schedule_cron() {
    $hook = 'sc_activity_log_daily_check';
    if (!wp_next_scheduled($hook)) {
        wp_schedule_event(time(), 'daily', $hook);
    }
}

/**
 * حذف زمان‌بندی کرون پاک‌سازی لاگ
 */
function sc_activity_log_unschedule_cron() {
    wp_clear_scheduled_hook('sc_activity_log_daily_check');
}

/**
 * هر روز اجرا می‌شود؛ اگر روز جاری برابر روز تنظیم‌شده بود، لاگ‌های قدیمی را پاک می‌کند
 */
add_action('sc_activity_log_daily_check', function () {
    $day = (int) sc_get_setting('activity_log_cleanup_day', '1');
    $day = max(1, min(28, $day));
    $today = (int) date('j');
    if ($today === $day) {
        sc_activity_log_cleanup_old();
    }
});
