<?php 
// --- (تغییر جدید) --- 
// افزودن زمان‌بندی ۱ دقیقه‌ای به وردپرس
add_filter('cron_schedules', 'sc_add_every_minute_schedule');
function sc_add_every_minute_schedule($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60,
        'display'  => 'هر یک دقیقه'
    );
    return $schedules;
}
// --------------------

// 1. ثبت زمان‌بندی (Cron) در وردپرس
add_action('init', 'sc_schedule_attendance_sync');
function sc_schedule_attendance_sync() {
    if (!wp_next_scheduled('sc_sync_attendance_cron_event')) {
        // --- (تغییر جدید: hourly به every_minute تغییر کرد) ---
        wp_schedule_event(time(), 'every_minute', 'sc_sync_attendance_cron_event');
    }
}

// 2. متصل کردن تابع دریافت اطلاعات به هوک Cron
add_action('sc_sync_attendance_cron_event', 'sc_fetch_and_store_api_attendance');

// 3. تابع اصلی برای گرفتن دیتا از API و ذخیره در دیتابیس
function sc_fetch_and_store_api_attendance() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_api_attendance_logs';
    
    $api_key = 'TEST123'; // می‌توانید این را از جدول sc_settings بخوانید
    $api_url = 'https://api.hozoran.ir/attendance/sync';

    // --- (تغییر جدید: 2 days به 365 days تغییر کرد) ---
    // دریافت زمان آخرین سینک از تنظیمات. اگر بار اول بود، از 365 روز پیش شروع کند
    $last_sync = get_option('sc_last_attendance_sync', gmdate('Y-m-d H:i:s', strtotime('-365 days')));

    // ساخت URL به همراه پارامترها
    $request_url = add_query_arg([
        'api_key' => $api_key,
        'since'   => $last_sync
    ], $api_url);

    // ارسال درخواست GET
    $response = wp_remote_get($request_url, [
        'timeout' => 60, // (تغییر جدید: افزایش تایم‌اوت به دلیل حجم بالای دیتای ۱ ساله)
        'sslverify' => false 
    ]);

    if (is_wp_error($response)) {
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // بررسی اینکه آیا رکوردی وجود دارد یا خیر
    if (!empty($data['records']) && is_array($data['records'])) {
        foreach ($data['records'] as $record) {
            // استفاده از INSERT IGNORE برای جلوگیری از ثبت دیتای تکراری
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO `$table_name` 
                    (`employee_code`, `log_date`, `log_time`, `log_datetime`, `created_at`) 
                    VALUES (%s, %s, %s, %s, %s)",
                    $record['employee_code'],
                    $record['log_date'],
                    $record['log_time'],
                    $record['datetime'],
                    current_time('mysql')
                )
            );
        }
    }

    // به‌روزرسانی زمان آخرین سینک برای استفاده در درخواست‌های بعدی
    if (!empty($data['server_time'])) {
        update_option('sc_last_attendance_sync', $data['server_time']);
    }
}





// ==========================================
// تابع دستی تست (بدون تغییر باقی ماند)
add_action('admin_init', 'sc_manual_fetch_historical_attendance_data');

function sc_manual_fetch_historical_attendance_data() {
    if ( ! isset( $_GET['sc_sync_history'] ) || $_GET['sc_sync_history'] != '1' ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_api_attendance_logs';

    $api_url = 'https://api.hozoran.ir/attendance/by-date?api_key=TEST123&start=2026-03-01&end=2026-04-25';

    $response = wp_remote_get( $api_url, array('timeout' => 60) );

    if ( is_wp_error( $response ) ) {
        wp_die( 'خطا در ارتباط با سرور: ' . $response->get_error_message() );
    }

    $body = wp_remote_retrieve_body( $response );
    $data_response = json_decode( $body, true );

    if ( ! isset( $data_response['records'] ) || ! is_array( $data_response['records'] ) ) {
        wp_die( 'داده‌ای یافت نشد یا ساختار پاسخ معتبر نیست.' );
    }

    $inserted_count = 0;
    $current_time = current_time('mysql'); 

    foreach ( $data_response['records'] as $log ) {
        $result = $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO $table_name (employee_code, log_date, log_time, log_datetime, created_at) 
             VALUES (%s, %s, %s, %s, %s)",
            $log['employee_code'],
            $log['log_date'],
            $log['log_time'],
            $log['datetime'],
            $current_time
        ) );

        if ( $result ) {
            $inserted_count++;
        }
    }

    wp_die( 'عملیات با موفقیت انجام شد! تعداد رکوردهای جدید ذخیره‌شده: ' . $inserted_count );
}
?>









