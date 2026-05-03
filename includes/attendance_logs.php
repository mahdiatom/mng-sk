<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('cron_schedules', 'sc_add_every_minute_schedule');
function sc_add_every_minute_schedule($schedules) {
    if (!isset($schedules['every_minute'])) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => 'هر یک دقیقه',
        ];
    }
    return $schedules;
}

add_action('init', 'sc_schedule_attendance_sync');
function sc_schedule_attendance_sync() {
    if (!wp_next_scheduled('sc_sync_attendance_cron_event')) {
        wp_schedule_event(time(), 'every_minute', 'sc_sync_attendance_cron_event');
    }
}

add_action('sc_sync_attendance_cron_event', 'sc_fetch_and_store_api_attendance');

function sc_get_attendance_api_base_url() {
    $default = 'https://api.hozoran.ir';
    $raw = trim((string) sc_get_setting('attendance_api_base_url', $default));
    if ($raw === '') {
        $raw = $default;
    }
    return untrailingslashit($raw);
}

function sc_get_attendance_api_key() {
    return trim((string) sc_get_setting('attendance_api_key', ''));
}

function sc_get_attendance_api_bearer_token() {
    return trim((string) sc_get_setting('attendance_api_bearer_token', ''));
}

function sc_attendance_build_api_url($path, $params = []) {
    $base = sc_get_attendance_api_base_url();
    return add_query_arg($params, $base . $path);
}

function sc_attendance_api_request_headers() {
    $headers = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
    $token = sc_get_attendance_api_bearer_token();
    if ($token !== '') {
        $headers['Authorization'] = 'Bearer ' . $token;
    }
    return $headers;
}

function sc_attendance_store_records($records) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_api_attendance_logs';
    $inserted_count = 0;
    $latest_dt = null;
    $now = current_time('mysql');

    foreach ($records as $record) {
        $employee_code = isset($record['employee_code']) ? trim((string) $record['employee_code']) : '';
        $log_date = isset($record['log_date']) ? trim((string) $record['log_date']) : '';
        $log_time = isset($record['log_time']) ? trim((string) $record['log_time']) : '';
        $datetime = isset($record['datetime']) ? trim((string) $record['datetime']) : '';
        if ($employee_code === '' || $log_date === '' || $log_time === '' || $datetime === '') {
            continue;
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO `$table_name` (`employee_code`, `log_date`, `log_time`, `log_datetime`, `created_at`) VALUES (%s, %s, %s, %s, %s)",
                $employee_code,
                $log_date,
                $log_time,
                $datetime,
                $now
            )
        );
        if ($result === 1) {
            $inserted_count++;
        }
        if ($latest_dt === null || strcmp($datetime, $latest_dt) > 0) {
            $latest_dt = $datetime;
        }
    }

    if ($latest_dt !== null) {
        update_option('sc_last_attendance_sync', $latest_dt);
    }
    return $inserted_count;
}

function sc_fetch_and_store_api_attendance() {
    $api_key = sc_get_attendance_api_key();
    if ($api_key === '') {
        return;
    }

    $last_sync = get_option('sc_last_attendance_sync', gmdate('Y-m-d H:i:s', strtotime('-365 days')));
    $request_url = sc_attendance_build_api_url('/attendance/sync', [
        'api_key' => $api_key,
        'since'   => $last_sync,
    ]);

    $response = wp_remote_get($request_url, [
        'timeout' => 60,
        'sslverify' => false,
        'headers' => sc_attendance_api_request_headers(),
    ]);
    if (is_wp_error($response)) {
        return;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || empty($data['records']) || !is_array($data['records'])) {
        return;
    }

    sc_attendance_store_records($data['records']);

    if (function_exists('sc_attendance_auto_process_api_logs') && (int) sc_get_setting('attendance_api_auto_enabled', '1') === 1) {
        sc_attendance_auto_process_api_logs(80);
    }
}

add_action('admin_init', 'sc_manual_fetch_historical_attendance_data');
function sc_manual_fetch_historical_attendance_data() {
    if (!isset($_GET['sc_sync_history']) || $_GET['sc_sync_history'] !== '1') {
        return;
    }
    if (!current_user_can('manage_options') && !current_user_can('sc_manage_attendance')) {
        return;
    }

    $api_key = sc_get_attendance_api_key();
    if ($api_key === '') {
        wp_die('ابتدا API Key را از تنظیمات حضور و غیاب ثبت کنید.');
    }

    $start = isset($_GET['start']) ? sanitize_text_field(wp_unslash($_GET['start'])) : '';
    $end = isset($_GET['end']) ? sanitize_text_field(wp_unslash($_GET['end'])) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        wp_die('پارامترهای start و end با فرمت YYYY-MM-DD الزامی هستند.');
    }

    $request_url = sc_attendance_build_api_url('/attendance/by-date', [
        'api_key' => $api_key,
        'start' => $start,
        'end' => $end,
    ]);

    $response = wp_remote_get($request_url, [
        'timeout' => 60,
        'sslverify' => false,
        'headers' => sc_attendance_api_request_headers(),
    ]);
    if (is_wp_error($response)) {
        wp_die('خطا در ارتباط با سرور: ' . $response->get_error_message());
    }
    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        wp_die('خطای API: HTTP ' . $code);
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || !isset($data['records']) || !is_array($data['records'])) {
        wp_die('داده‌ای یافت نشد یا ساختار پاسخ معتبر نیست.');
    }

    $inserted_count = sc_attendance_store_records($data['records']);
    wp_die('عملیات با موفقیت انجام شد. تعداد رکورد جدید: ' . (int) $inserted_count);
}
