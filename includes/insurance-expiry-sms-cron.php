<?php
/**
 * Insurance Expiry SMS Cron
 * ارسال خودکار پیامک یادآوری انقضای بیمه: روز انقضا و ۱۰ روز قبل از آن (یک متن مشترک)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get expiry date in gregorian (Y-m-d) from member - from gregorian column or by converting shamsi.
 */
function sc_member_insurance_expiry_gregorian($member) {
    if (!empty($member->insurance_expiry_date_gregorian) && $member->insurance_expiry_date_gregorian !== '0000-00-00') {
        return $member->insurance_expiry_date_gregorian;
    }
    if (!empty($member->insurance_expiry_date_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
        return sc_shamsi_to_gregorian_date($member->insurance_expiry_date_shamsi);
    }
    return '';
}

/**
 * Get members who have insurance expiring today or in 10 days.
 * Returns array of [ 'member' => object, 'days_remaining' => 0|10, 'expiry_date_shamsi' => string ]
 */
function sc_get_members_insurance_expiry_today_or_in_10_days() {
    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Asia/Tehran');
    $today = new DateTime('now', $tz);
    $today_str = $today->format('Y-m-d');
    $in_10 = clone $today;
    $in_10->modify('+10 days');
    $in_10_str = $in_10->format('Y-m-d');

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';

    $rows = $wpdb->get_results(
        "SELECT id, first_name, last_name, player_phone, insurance_expiry_date_gregorian, insurance_expiry_date_shamsi
         FROM $members_table
         WHERE user_id IS NOT NULL AND is_active = 1
         AND player_phone IS NOT NULL AND TRIM(player_phone) != ''
         AND (insurance_expiry_date_gregorian IS NOT NULL AND insurance_expiry_date_gregorian != '' AND insurance_expiry_date_gregorian != '0000-00-00'
              OR (insurance_expiry_date_shamsi IS NOT NULL AND insurance_expiry_date_shamsi != ''))"
    );

    $result = [];
    foreach ($rows as $m) {
        $expiry_gregorian = sc_member_insurance_expiry_gregorian($m);
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
        $expiry_shamsi = !empty($m->insurance_expiry_date_shamsi) ? $m->insurance_expiry_date_shamsi : '';
        if ($expiry_shamsi === '' && function_exists('sc_date_shamsi_date_only')) {
            $expiry_shamsi = sc_date_shamsi_date_only($expiry_gregorian);
        } elseif ($expiry_shamsi === '' && function_exists('gregorian_to_jalali')) {
            $p = explode('-', $expiry_gregorian);
            if (count($p) === 3) {
                $j = gregorian_to_jalali((int)$p[0], (int)$p[1], (int)$p[2]);
                if ($j && count($j) === 3) {
                    $expiry_shamsi = $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
                }
            }
        }
        $result[] = [
            'member' => $m,
            'days_remaining' => $days_remaining,
            'expiry_date_shamsi' => $expiry_shamsi ?: $expiry_gregorian,
        ];
    }
    return $result;
}

/**
 * Daily cron: send insurance expiry SMS to members whose insurance expires today or in 10 days.
 */
function sc_send_insurance_expiry_sms_daily() {
    if (!function_exists('sc_is_sms_enabled_for') || !sc_is_sms_enabled_for('insurance_expiry', 'user')) {
        return;
    }
    if (!function_exists('sc_send_sms')) {
        return;
    }

    $list = sc_get_members_insurance_expiry_today_or_in_10_days();
    if (empty($list)) {
        return;
    }

    $template = function_exists('sc_get_sms_template') ? sc_get_sms_template('insurance_expiry', 'user') : '';
    if (empty($template)) {
        $template = 'کاربر گرامی %user_name%، تاریخ انقضای بیمه شما %expiry_date% است. لطفاً نسبت به تمدید اقدام کنید.';
    }
    $pattern_code = function_exists('sc_get_sms_pattern') ? sc_get_sms_pattern('insurance_expiry', 'user') : null;

    foreach ($list as $item) {
        $m = $item['member'];
        $user_name = trim($m->first_name . ' ' . $m->last_name);
        if ($user_name === '') {
            $user_name = 'کاربر';
        }
        $variables = [
            'user_name' => $user_name,
            'expiry_date' => $item['expiry_date_shamsi'],
            'days_remaining' => (string) $item['days_remaining'],
        ];
        $message = function_exists('sc_replace_sms_variables') ? sc_replace_sms_variables($template, $variables) : $template;
        foreach ($variables as $k => $v) {
            $message = str_replace('%' . $k . '%', $v, $message);
        }
        sc_send_sms($m->player_phone, $message, !empty($pattern_code), $pattern_code, $variables);
    }
}

add_action('sc_daily_insurance_expiry_sms', 'sc_send_insurance_expiry_sms_daily');

if (!wp_next_scheduled('sc_daily_insurance_expiry_sms')) {
    wp_schedule_event(time(), 'daily', 'sc_daily_insurance_expiry_sms');
}
