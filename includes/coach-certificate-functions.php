<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return int
 */
function sc_get_coach_info_page_id() {
    return (int) sc_get_setting('coach_info_page_id', 0);
}

/**
 * @return WP_Post|null
 */
function sc_get_coach_info_page_post() {
    $page_id = sc_get_coach_info_page_id();
    if ($page_id <= 0) {
        return null;
    }

    $page = get_post($page_id);
    if (!$page || $page->post_type !== 'page') {
        return null;
    }

    return $page;
}

/**
 * @return string
 */
function sc_get_coach_info_page_content_html() {
    $page = sc_get_coach_info_page_post();
    if (!$page) {
        return '';
    }

    $content = trim((string) $page->post_content);
    if ($content === '') {
        return '';
    }

    return apply_filters('the_content', $content);
}

/**
 * @param object $coach
 * @return string
 */
function sc_get_coach_certificate_expiry_gregorian($coach) {
    if (!empty($coach->coaching_certificate_expiry_date_gregorian) && $coach->coaching_certificate_expiry_date_gregorian !== '0000-00-00') {
        return (string) $coach->coaching_certificate_expiry_date_gregorian;
    }

    if (!empty($coach->coaching_certificate_expiry_date_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
        return (string) sc_shamsi_to_gregorian_date((string) $coach->coaching_certificate_expiry_date_shamsi);
    }

    return '';
}

/**
 * @param object $coach
 * @return array{status:string,message:string,days_remaining:int,expiry_date:string}|array{}
 */
function sc_get_coach_certificate_expiry_notice_data($coach) {
    if (!$coach) {
        return [];
    }

    $expiry_date_shamsi = !empty($coach->coaching_certificate_expiry_date_shamsi)
        ? trim((string) $coach->coaching_certificate_expiry_date_shamsi)
        : '';
    $expiry_date_gregorian = sc_get_coach_certificate_expiry_gregorian($coach);
    if ($expiry_date_shamsi === '' && $expiry_date_gregorian === '') {
        return [];
    }

    if ($expiry_date_shamsi === '' && $expiry_date_gregorian !== '' && function_exists('sc_date_shamsi_date_only')) {
        $expiry_date_shamsi = sc_date_shamsi_date_only($expiry_date_gregorian);
    }

    if ($expiry_date_gregorian === '') {
        return [];
    }

    try {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Asia/Tehran');
        $today = new DateTime('today', $tz);
        $expiry = new DateTime($expiry_date_gregorian, $tz);
    } catch (Exception $e) {
        return [];
    }

    $interval = (int) $today->diff($expiry)->format('%r%a');
    if ($interval < 0) {
        return [
            'status' => 'expired',
            'message' => sprintf('تاریخ اعتبار مدرک مربی‌گری شما در %s به پایان رسیده است. لطفاً برای تمدید آن اقدام کنید.', $expiry_date_shamsi ?: $expiry_date_gregorian),
            'days_remaining' => $interval,
            'expiry_date' => $expiry_date_shamsi ?: $expiry_date_gregorian,
        ];
    }

    if ($interval === 0) {
        return [
            'status' => 'today',
            'message' => sprintf('امروز آخرین روز اعتبار مدرک مربی‌گری شما است (%s). لطفاً آن را تمدید کنید.', $expiry_date_shamsi ?: $expiry_date_gregorian),
            'days_remaining' => 0,
            'expiry_date' => $expiry_date_shamsi ?: $expiry_date_gregorian,
        ];
    }

    if ($interval <= 10) {
        return [
            'status' => 'soon',
            'message' => sprintf('اعتبار مدرک مربی‌گری شما در %s منقضی می‌شود. %d روز تا پایان اعتبار باقی مانده است.', $expiry_date_shamsi ?: $expiry_date_gregorian, $interval),
            'days_remaining' => $interval,
            'expiry_date' => $expiry_date_shamsi ?: $expiry_date_gregorian,
        ];
    }

    return [];
}

/**
 * Returns array of ['coach' => object, 'days_remaining' => 0|10, 'expiry_date_shamsi' => string].
 *
 * @return array<int, array<string, mixed>>
 */
function sc_get_coaches_certificate_expiry_today_or_in_10_days() {
    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Asia/Tehran');
    $today = new DateTime('now', $tz);
    $today_str = $today->format('Y-m-d');
    $in_10 = clone $today;
    $in_10->modify('+10 days');
    $in_10_str = $in_10->format('Y-m-d');

    global $wpdb;
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $rows = $wpdb->get_results(
        "SELECT id, first_name, last_name, mobile_phone, coaching_certificate_expiry_date_gregorian, coaching_certificate_expiry_date_shamsi
         FROM $coaches_table
         WHERE is_active = 1
           AND mobile_phone IS NOT NULL
           AND TRIM(mobile_phone) <> ''
           AND (
               (coaching_certificate_expiry_date_gregorian IS NOT NULL AND coaching_certificate_expiry_date_gregorian <> '' AND coaching_certificate_expiry_date_gregorian <> '0000-00-00')
               OR (coaching_certificate_expiry_date_shamsi IS NOT NULL AND coaching_certificate_expiry_date_shamsi <> '')
           )"
    );

    $result = [];
    foreach ((array) $rows as $coach) {
        $expiry_gregorian = sc_get_coach_certificate_expiry_gregorian($coach);
        if ($expiry_gregorian === '') {
            continue;
        }

        $days_remaining = null;
        if ($expiry_gregorian === $today_str) {
            $days_remaining = 0;
        } elseif ($expiry_gregorian === $in_10_str) {
            $days_remaining = 10;
        }

        if ($days_remaining === null) {
            continue;
        }

        $expiry_shamsi = !empty($coach->coaching_certificate_expiry_date_shamsi)
            ? (string) $coach->coaching_certificate_expiry_date_shamsi
            : '';
        if ($expiry_shamsi === '' && function_exists('sc_date_shamsi_date_only')) {
            $expiry_shamsi = sc_date_shamsi_date_only($expiry_gregorian);
        }

        $result[] = [
            'coach' => $coach,
            'days_remaining' => $days_remaining,
            'expiry_date_shamsi' => $expiry_shamsi ?: $expiry_gregorian,
        ];
    }

    return $result;
}

/**
 * ارسال پیامک یادآوری انقضای مدرک مربی به خود مربی و مدیر.
 *
 * @return void
 */
function sc_send_coach_certificate_expiry_sms_daily() {
    if (!function_exists('sc_send_sms')) {
        return;
    }

    $items = sc_get_coaches_certificate_expiry_today_or_in_10_days();
    if (empty($items)) {
        return;
    }

    $user_enabled = function_exists('sc_is_sms_enabled_for') && sc_is_sms_enabled_for('coach_certificate_expiry', 'user');
    $admin_enabled = function_exists('sc_is_sms_enabled_for') && sc_is_sms_enabled_for('coach_certificate_expiry', 'admin');
    if (!$user_enabled && !$admin_enabled) {
        return;
    }

    $user_template = function_exists('sc_get_sms_template') ? sc_get_sms_template('coach_certificate_expiry', 'user') : '';
    $admin_template = function_exists('sc_get_sms_template') ? sc_get_sms_template('coach_certificate_expiry', 'admin') : '';
    $user_pattern = function_exists('sc_get_sms_pattern') ? sc_get_sms_pattern('coach_certificate_expiry', 'user') : null;
    $admin_pattern = function_exists('sc_get_sms_pattern') ? sc_get_sms_pattern('coach_certificate_expiry', 'admin') : null;
    $admin_phone = trim((string) sc_get_sms_setting('sms_admin_phone'));

    foreach ($items as $item) {
        $coach = $item['coach'];
        $coach_name = trim((string) $coach->first_name . ' ' . (string) $coach->last_name);
        if ($coach_name === '') {
            $coach_name = 'مربی';
        }

        $variables = [
            'user_name' => $coach_name,
            'coach_name' => $coach_name,
            'expiry_date' => (string) $item['expiry_date_shamsi'],
            'days_remaining' => (string) $item['days_remaining'],
        ];

        if ($user_enabled && !empty($coach->mobile_phone)) {
            $message = function_exists('sc_replace_sms_variables')
                ? sc_replace_sms_variables($user_template, $variables)
                : $user_template;
            sc_send_sms((string) $coach->mobile_phone, $message, !empty($user_pattern), $user_pattern, $variables, 'coach_certificate_expiry');
        }

        if ($admin_enabled && $admin_phone !== '') {
            $message = function_exists('sc_replace_sms_variables')
                ? sc_replace_sms_variables($admin_template, $variables)
                : $admin_template;
            sc_send_sms($admin_phone, $message, !empty($admin_pattern), $admin_pattern, $variables, 'coach_certificate_expiry');
        }
    }
}

add_action('sc_daily_coach_certificate_expiry_sms', 'sc_send_coach_certificate_expiry_sms_daily');

if (!wp_next_scheduled('sc_daily_coach_certificate_expiry_sms')) {
    wp_schedule_event(time(), 'daily', 'sc_daily_coach_certificate_expiry_sms');
}
