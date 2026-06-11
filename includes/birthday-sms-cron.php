<?php
/**
 * Birthday SMS Cron
 * ارسال خودکار پیامک تبریک تولد هر روز به کاربرانی که امروز تولدشان است
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get members who have birthday today (by gregorian month/day).
 * اعضایی که امروز تولدشان است (فقط بازیکنان با شماره موبایل و user_id)
 */
function sc_get_members_with_birthday_today() {
    $today = new DateTime('now', wp_timezone());
    $today_m = (int) $today->format('n');
    $today_d = (int) $today->format('j');

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';

    // 1) Members with birth_date_gregorian matching today's month and day
    $by_gregorian = $wpdb->get_results($wpdb->prepare(
        "SELECT id, first_name, last_name, player_phone, birth_date_gregorian, birth_date_shamsi , bot_id
         FROM $members_table
         WHERE birth_date_gregorian IS NOT NULL AND birth_date_gregorian != '' AND birth_date_gregorian != '0000-00-00'
         AND MONTH(birth_date_gregorian) = %d AND DAY(birth_date_gregorian) = %d
         AND user_id IS NOT NULL AND is_active = 1
         AND player_phone IS NOT NULL AND TRIM(player_phone) != ''",
        $today_m,
        $today_d
    ));

    // 2) Members with only birth_date_shamsi: fetch and filter in PHP
    $by_shamsi_raw = $wpdb->get_results(
        "SELECT id, first_name, last_name, player_phone, birth_date_gregorian, birth_date_shamsi , bot_id
         FROM $members_table
         WHERE (birth_date_gregorian IS NULL OR birth_date_gregorian = '' OR birth_date_gregorian = '0000-00-00')
         AND birth_date_shamsi IS NOT NULL AND birth_date_shamsi != ''
         AND user_id IS NOT NULL AND is_active = 1
         AND player_phone IS NOT NULL AND TRIM(player_phone) != ''"
    );

    $by_shamsi = [];
    foreach ($by_shamsi_raw as $m) {
        if (empty($m->birth_date_shamsi)) {
            continue;
        }
        $gregorian = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($m->birth_date_shamsi) : '';
        if ($gregorian === '') {
            continue;
        }
        $parts = explode('-', $gregorian);
        if (count($parts) !== 3) {
            continue;
        }
        $m_month = (int) $parts[1];
        $m_day = (int) $parts[2];
        if ($m_month === $today_m && $m_day === $today_d) {
            $by_shamsi[] = $m;
        }
    }

    // Merge; avoid duplicate id (same member might not appear in both, but ensure unique by id)
    $seen = [];
    $result = [];
    foreach (array_merge($by_gregorian ?: [], $by_shamsi) as $row) {
        if (isset($seen[ $row->id ])) {
            continue;
        }
        $seen[ $row->id ] = true;
        $result[] = $row;
    }

    return $result;
}

/**
 * Daily cron: send birthday SMS to members whose birthday is today.
 */
function sc_send_birthday_sms_daily() {
    if (!function_exists('sc_is_sms_enabled_for') || !sc_is_sms_enabled_for('birthday', 'user')) {
        return;
    }
    if (!function_exists('sc_send_sms')) {
        return;
    }

    $members = sc_get_members_with_birthday_today();
    if (empty($members)) {
        return;
    }

    $template = function_exists('sc_get_sms_template') ? sc_get_sms_template('birthday', 'user') : '';
    if (empty($template)) {
        $template = 'کاربر گرامی %user_name%، تولدتان مبارک! باشگاه ورزشی ما این روز را به شما تبریک می‌گوید.';
    }
    $pattern_code = function_exists('sc_get_sms_pattern') ? sc_get_sms_pattern('birthday', 'user') : null;

    foreach ($members as $member) {
        $user_name = trim($member->first_name . ' ' . $member->last_name);
        if ($user_name === '') {
            $user_name = 'کاربر';
        }
        $variables = ['user_name' => $user_name];
        $message = function_exists('sc_replace_sms_variables') ? sc_replace_sms_variables($template, $variables) : str_replace('%user_name%', $user_name, $template);
        sc_send_sms($member->player_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'birthday');

    }
}

add_action('sc_daily_birthday_sms', 'sc_send_birthday_sms_daily');

if (!wp_next_scheduled('sc_daily_birthday_sms')) {
    wp_schedule_event(time(), 'daily', 'sc_daily_birthday_sms');
}
