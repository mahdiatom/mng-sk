<?php
/**
 * محاسبه مبلغ ثبت‌نام و صورتحساب دوره‌ای (حالت تاریخ ثابت + تناسب جلسات)
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return bool
 */
function sc_invoices_support_billing_columns() {
    global $wpdb;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $t = $wpdb->prefix . 'sc_invoices';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) !== $t) {
        $ok = false;
        return $ok;
    }
    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$t` LIKE %s", 'billing_period_shamsi'));
    $ok = !empty($col);
    return $ok;
}

/**
 * @return bool
 */
function sc_member_courses_support_billing_deferred() {
    global $wpdb;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $t = $wpdb->prefix . 'sc_member_courses';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) !== $t) {
        $ok = false;
        return $ok;
    }
    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$t` LIKE %s", 'billing_deferred'));
    $ok = !empty($col);
    return $ok;
}

/**
 * @return string YYYY-MM
 */
function sc_get_current_jalali_billing_period_key() {
    return sc_get_jalali_billing_period_key(null);
}

/**
 * @param string|null $datetime_mysql
 * @return string YYYY-MM
 */
function sc_get_jalali_billing_period_key($datetime_mysql = null) {
    if (!function_exists('gregorian_to_jalali')) {
        return '';
    }
    $ts = $datetime_mysql ? strtotime($datetime_mysql) : current_time('timestamp');
    if (!$ts) {
        return '';
    }
    $j = gregorian_to_jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
    return sprintf('%04d-%02d', (int) $j[0], (int) $j[1]);
}

/**
 * @param int $jy
 * @param int $jm
 * @return string Y-m-d
 */
function sc_jalali_month_last_gregorian_ymd($jy, $jm) {
    if (!function_exists('jalali_to_gregorian') || !function_exists('jalali_days_in_month')) {
        return '';
    }
    $last_day = jalali_days_in_month((int) $jm, (int) $jy);
    $g = jalali_to_gregorian((int) $jy, (int) $jm, $last_day);
    return sprintf('%04d-%02d-%02d', (int) $g[0], (int) $g[1], (int) $g[2]);
}

/**
 * @param string|null $from_ymd
 * @return string Y-m-d
 */
function sc_get_current_jalali_month_end_ymd($from_ymd = null) {
    $period = sc_get_jalali_billing_period_key($from_ymd ? ($from_ymd . ' 12:00:00') : null);
    if ($period === '') {
        return '';
    }
    $parts = explode('-', $period);
    if (count($parts) !== 2) {
        return '';
    }
    return sc_jalali_month_last_gregorian_ymd((int) $parts[0], (int) $parts[1]);
}

/**
 * @return bool
 */
function sc_invoice_mode_uses_fixed_date_proration() {
    return function_exists('sc_get_invoice_mode') && sc_get_invoice_mode() === 'fixed_date';
}

/**
 * @param object $course
 * @param object|null $member_course
 * @return float
 */
function sc_get_course_unit_session_price($course, $member_course = null) {
    if (!$course) {
        return 0.0;
    }
    $course_id = (int) $course->id;
    $price_per = isset($course->price_per_session) ? (float) $course->price_per_session : 0.0;

    if (
        $member_course
        && !empty($course->private_variable_coach_pricing)
        && function_exists('sc_get_course_coach_branch_meta')
    ) {
        $chapter = isset($member_course->chapter) ? (string) $member_course->chapter : '';
        $coach_id = isset($member_course->coach_id) ? (int) $member_course->coach_id : 0;
        if ($chapter !== '' && $coach_id > 0) {
            $meta = sc_get_course_coach_branch_meta($course_id, $chapter, $coach_id);
            if ($meta && (float) $meta['price_per_session'] > 0) {
                return (float) $meta['price_per_session'];
            }
        }
    }

    if ($price_per > 0) {
        return $price_per;
    }

    $sessions = 0;
    if ($member_course && isset($member_course->enrollment_sessions) && $member_course->enrollment_sessions !== null && $member_course->enrollment_sessions !== '') {
        $sessions = (int) $member_course->enrollment_sessions;
    }
    if ($sessions <= 0 && !empty($course->sessions_count)) {
        $sessions = (int) $course->sessions_count;
    }
    $course_price = isset($course->price) ? (float) $course->price : 0.0;
    if ($sessions > 0 && $course_price > 0) {
        return round($course_price / $sessions, 2);
    }

    return $course_price > 0 ? $course_price : 0.0;
}

/**
 * @param int $course_id
 * @param object|null $member_course
 * @return array<int,object>
 */
function sc_get_member_course_matching_schedule_rows($course_id, $member_course = null) {
    if (!function_exists('sc_get_course_weekly_schedule_rows')) {
        return [];
    }
    $course_id = absint($course_id);
    if (!$course_id) {
        return [];
    }

    $chapter = $member_course && isset($member_course->chapter) ? trim((string) $member_course->chapter) : '';
    $coach_id = $member_course && isset($member_course->coach_id) ? (int) $member_course->coach_id : 0;
    $group_name = $member_course && isset($member_course->group_name) ? trim((string) $member_course->group_name) : '';

    $rows = sc_get_course_weekly_schedule_rows($course_id);
    $out = [];
    foreach ($rows as $row) {
        $row_chapter = isset($row->chapter_name) ? trim((string) $row->chapter_name) : '';
        $row_coach = isset($row->coach_id) ? (int) $row->coach_id : 0;
        $uses_group = !empty($row->schedule_uses_group);
        $row_group = isset($row->group_name) ? trim((string) $row->group_name) : '';

        if ($row_chapter !== '' && $chapter !== '' && $row_chapter !== $chapter) {
            continue;
        }
        if ($row_coach > 0 && $coach_id > 0 && $row_coach !== $coach_id) {
            continue;
        }
        if ($uses_group && $row_group !== '') {
            if ($group_name === '' || $row_group !== $group_name) {
                continue;
            }
        }

        $out[] = $row;
    }

    return $out;
}

/**
 * @param object $course
 * @param object|null $member_course
 * @return bool
 */
function sc_course_has_billable_weekly_schedule($course, $member_course = null) {
    if (!$course) {
        return false;
    }
    return !empty(sc_get_member_course_matching_schedule_rows((int) $course->id, $member_course));
}

/**
 * @param int    $course_id
 * @param object|null $member_course
 * @param string $from_ymd Y-m-d
 * @param string $to_ymd   Y-m-d
 * @return array<int,array<string,mixed>>
 */
function sc_list_scheduled_sessions_for_member_course($course_id, $member_course, $from_ymd, $to_ymd) {
    $course_id = absint($course_id);
    $from_ymd = is_string($from_ymd) ? trim($from_ymd) : '';
    $to_ymd = is_string($to_ymd) ? trim($to_ymd) : '';
    if (!$course_id || $from_ymd === '' || $to_ymd === '' || !function_exists('sc_course_ir_weekday_from_gregorian_ymd')) {
        return [];
    }

    if (strcmp($from_ymd, $to_ymd) > 0) {
        return [];
    }

    global $wpdb;
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT start_date, end_date FROM {$wpdb->prefix}sc_courses WHERE id = %d LIMIT 1",
        $course_id
    ));

    $schedule_rows = sc_get_member_course_matching_schedule_rows($course_id, $member_course);
    if (empty($schedule_rows)) {
        return [];
    }

    $weekday_slots = [];
    foreach ($schedule_rows as $row) {
        $wd = isset($row->weekday) ? (int) $row->weekday : 0;
        if ($wd < 1 || $wd > 7) {
            continue;
        }
        if (!isset($weekday_slots[$wd])) {
            $weekday_slots[$wd] = [];
        }
        $weekday_slots[$wd][] = $row;
    }
    if (empty($weekday_slots)) {
        return [];
    }

    $labels = function_exists('sc_course_weekday_labels_ir') ? sc_course_weekday_labels_ir() : [];
    $sessions = [];
    $cursor = strtotime($from_ymd . ' 12:00:00');
    $end_ts = strtotime($to_ymd . ' 12:00:00');
    if (!$cursor || !$end_ts) {
        return [];
    }

    while ($cursor <= $end_ts) {
        $ymd = date('Y-m-d', $cursor);
        $wd = sc_course_ir_weekday_from_gregorian_ymd($ymd);
        if ($wd && !empty($weekday_slots[$wd])) {
            if ($course && !empty($course->start_date) && strcmp($ymd, substr((string) $course->start_date, 0, 10)) < 0) {
                $cursor = strtotime('+1 day', $cursor);
                continue;
            }
            if ($course && !empty($course->end_date) && strcmp($ymd, substr((string) $course->end_date, 0, 10)) > 0) {
                $cursor = strtotime('+1 day', $cursor);
                continue;
            }

            foreach ($weekday_slots[$wd] as $slot) {
                $slot_start = isset($slot->time_start) ? (string) $slot->time_start : '';
                $slot_end = isset($slot->time_end) ? (string) $slot->time_end : '';
                if ($slot_start === '' || $slot_end === '') {
                    continue;
                }
                if (function_exists('sc_attendance_slot_overlaps_cancellation')
                    && sc_attendance_slot_overlaps_cancellation($course_id, $ymd, $slot_start, $slot_end)) {
                    continue;
                }

                $date_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($ymd) : $ymd;
                $time_start = strlen($slot_start) >= 5 ? substr($slot_start, 0, 5) : $slot_start;
                $time_end = strlen($slot_end) >= 5 ? substr($slot_end, 0, 5) : $slot_end;

                $sessions[] = [
                    'date_ymd' => $ymd,
                    'date_shamsi' => $date_shamsi,
                    'weekday' => (int) $wd,
                    'weekday_label' => isset($labels[$wd]) ? (string) $labels[$wd] : '',
                    'time_start' => $time_start,
                    'time_end' => $time_end,
                    'label' => trim(
                        (isset($labels[$wd]) ? $labels[$wd] : '') . ' ' . $date_shamsi . ' — ' . $time_start . ' تا ' . $time_end
                    ),
                ];
            }
        }
        $cursor = strtotime('+1 day', $cursor);
    }

    return $sessions;
}

/**
 * @param int    $course_id
 * @param object|null $member_course
 * @param string $from_ymd Y-m-d
 * @param string $to_ymd   Y-m-d
 * @return int
 */
function sc_count_scheduled_sessions_for_member_course($course_id, $member_course, $from_ymd, $to_ymd) {
    return count(sc_list_scheduled_sessions_for_member_course($course_id, $member_course, $from_ymd, $to_ymd));
}

/**
 * @param object|null $course
 * @param string|null $shamsi_date  YYYY/MM/DD
 * @param string|null $gregorian_ymd Y-m-d
 * @return array{ymd:string,shamsi:string,error:string}
 */
function sc_resolve_enrollment_start_ymd($course = null, $shamsi_date = null, $gregorian_ymd = null) {
    $today = current_time('Y-m-d');
    $today_shamsi = function_exists('sc_date_shamsi_date_only')
        ? sc_date_shamsi_date_only($today)
        : (function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '');

    $ymd = '';
    $shamsi = '';

    if (is_string($gregorian_ymd) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($gregorian_ymd))) {
        $ymd = trim($gregorian_ymd);
    } elseif (is_string($shamsi_date) && trim($shamsi_date) !== '' && function_exists('sc_shamsi_to_gregorian_date')) {
        $shamsi = trim(str_replace('-', '/', sanitize_text_field($shamsi_date)));
        $ymd = sc_shamsi_to_gregorian_date($shamsi);
    }

    if ($ymd === '') {
        return [
            'ymd' => $today,
            'shamsi' => $today_shamsi,
            'error' => '',
        ];
    }

    if ($shamsi === '' && function_exists('sc_date_shamsi_date_only')) {
        $shamsi = sc_date_shamsi_date_only($ymd);
    }

    if (strcmp($ymd, $today) < 0) {
        return [
            'ymd' => $today,
            'shamsi' => $today_shamsi,
            'error' => 'تاریخ شروع نمی‌تواند قبل از امروز باشد.',
        ];
    }

    if ($course) {
        $course_start = !empty($course->start_date) ? substr((string) $course->start_date, 0, 10) : '';
        $course_end = !empty($course->end_date) ? substr((string) $course->end_date, 0, 10) : '';
        if ($course_start !== '' && strcmp($ymd, $course_start) < 0) {
            return ['ymd' => '', 'shamsi' => '', 'error' => 'تاریخ انتخابی قبل از شروع دوره است.'];
        }
        if ($course_end !== '' && strcmp($ymd, $course_end) > 0) {
            return ['ymd' => '', 'shamsi' => '', 'error' => 'تاریخ انتخابی بعد از پایان دوره است.'];
        }
    }

    return [
        'ymd' => $ymd,
        'shamsi' => $shamsi,
        'error' => '',
    ];
}

/**
 * @param object      $course
 * @param object|null $member_course
 * @param string|null $registration_ymd
 * @return array<string,mixed>
 */
function sc_calculate_enrollment_billing_preview($course, $member_course = null, $registration_ymd = null) {
    $start = sc_resolve_enrollment_start_ymd($course, null, $registration_ymd);
    $registration_ymd = $start['ymd'] !== '' ? $start['ymd'] : current_time('Y-m-d');
    $registration_shamsi = $start['shamsi'];
    $start_date_error = $start['error'];
    $period_key = sc_get_jalali_billing_period_key($registration_ymd . ' 12:00:00');
    $month_end = sc_get_current_jalali_month_end_ymd($registration_ymd);

    $has_pkg = function_exists('sc_course_has_packages') && sc_course_has_packages((int) $course->id);
    $use_proration = sc_invoice_mode_uses_fixed_date_proration() && !$has_pkg;

    if (!$use_proration || !sc_course_has_billable_weekly_schedule($course, $member_course)) {
        $amount = function_exists('sc_get_course_billing_amount_for_member_course')
            ? sc_get_course_billing_amount_for_member_course($course, $member_course)
            : (float) $course->price;
        return [
            'mode' => 'full',
            'amount' => round((float) $amount, 2),
            'sessions_count' => !empty($course->sessions_count) ? (int) $course->sessions_count : 0,
            'needs_short_session_choice' => false,
            'show_start_date_picker' => false,
            'registration_ymd' => $registration_ymd,
            'registration_shamsi' => $registration_shamsi,
            'start_date_error' => $start_date_error,
            'billing_period_shamsi' => $period_key,
            'period_end_ymd' => $month_end,
            'fee_label' => function_exists('sc_course_enrollment_fee_label')
                ? sc_course_enrollment_fee_label($course->title, $member_course && !empty($member_course->enrollment_sessions) ? (int) $member_course->enrollment_sessions : null)
                : ('ثبت نام دوره: ' . $course->title),
        ];
    }

    $sessions_count = sc_count_scheduled_sessions_for_member_course((int) $course->id, $member_course, $registration_ymd, $month_end);
    $billing_sessions = sc_list_scheduled_sessions_for_member_course((int) $course->id, $member_course, $registration_ymd, $month_end);
    $unit_price = sc_get_course_unit_session_price($course, $member_course);
    $amount = round($sessions_count * $unit_price, 2);
    $needs_choice = ($sessions_count < 2);

    $month_names = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    $period_label = $period_key;
    if ($period_key !== '') {
        $pp = explode('-', $period_key);
        if (count($pp) === 2) {
            $mi = (int) $pp[1];
            $period_label = isset($month_names[$mi]) ? ($month_names[$mi] . ' ' . $pp[0]) : $period_key;
        }
    }

    $fee_label = sprintf(
        'ثبت نام دوره %s — %d جلسه از %s تا پایان %s',
        (string) $course->title,
        $sessions_count,
        $registration_shamsi !== '' ? $registration_shamsi : $registration_ymd,
        $period_label
    );

    return [
        'mode' => 'prorated',
        'amount' => $amount,
        'sessions_count' => $sessions_count,
        'billing_sessions' => $billing_sessions,
        'unit_price' => $unit_price,
        'needs_short_session_choice' => $needs_choice,
        'show_start_date_picker' => true,
        'registration_ymd' => $registration_ymd,
        'registration_shamsi' => $registration_shamsi,
        'start_date_error' => $start_date_error,
        'billing_period_shamsi' => $period_key,
        'period_end_ymd' => $month_end,
        'fee_label' => $fee_label,
    ];
}

/**
 * @param int   $member_course_id
 * @param string $period_key
 * @param string $type
 * @return bool
 */
function sc_member_course_has_invoice_for_period($member_course_id, $period_key, $type = '') {
    global $wpdb;
    $member_course_id = absint($member_course_id);
    $period_key = sanitize_text_field((string) $period_key);
    if ($member_course_id < 1 || $period_key === '') {
        return false;
    }

    $table = $wpdb->prefix . 'sc_invoices';
    if (sc_invoices_support_billing_columns()) {
        if ($type !== '') {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE member_course_id = %d AND billing_period_shamsi = %s AND type = %s",
                $member_course_id,
                $period_key,
                $type
            )) > 0;
        }
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE member_course_id = %d AND billing_period_shamsi = %s",
            $member_course_id,
            $period_key
        )) > 0;
    }

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE member_course_id = %d" . ($type !== '' ? " AND type = %s" : ''),
        $type !== '' ? [$member_course_id, $type] : [$member_course_id]
    )) > 0;
}

/**
 * @param object $member_course
 * @param string $billing_period
 * @return bool
 */
function sc_should_create_monthly_invoice_for_member_course($member_course, $billing_period) {
    $member_course_id = isset($member_course->id) ? (int) $member_course->id : 0;
    if ($member_course_id < 1 || $billing_period === '') {
        return false;
    }

    if (sc_member_course_has_invoice_for_period($member_course_id, $billing_period, 'monthly_regular')
        || sc_member_course_has_invoice_for_period($member_course_id, $billing_period, 'system defalt')) {
        return false;
    }

    if (sc_member_course_has_invoice_for_period($member_course_id, $billing_period, 'initial_prorated')) {
        return false;
    }

    $enroll_period = sc_get_jalali_billing_period_key(isset($member_course->created_at) ? (string) $member_course->created_at : null);
    $deferred = sc_member_courses_support_billing_deferred()
        && isset($member_course->billing_deferred)
        && (int) $member_course->billing_deferred === 1;

    if ($deferred && $enroll_period === $billing_period) {
        return false;
    }

    return true;
}

/**
 * @param int   $member_id
 * @param int   $member_course_id
 * @param array<string,mixed> $options
 * @return array<string,mixed>
 */
function sc_create_enrollment_invoice_for_member_course($member_id, $member_course_id, array $options = []) {
    if (!class_exists('WooCommerce') || !function_exists('sc_create_course_invoice')) {
        return ['success' => false, 'message' => 'WooCommerce یا تابع صورت‌حساب در دسترس نیست.'];
    }

    global $wpdb;
    $member_id = absint($member_id);
    $member_course_id = absint($member_course_id);
    if ($member_id < 1 || $member_course_id < 1) {
        return ['success' => false, 'message' => 'شناسه نامعتبر است.'];
    }

    $mc_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $inv_table = $wpdb->prefix . 'sc_invoices';

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT mc.*, c.*, c.title AS course_title FROM {$mc_table} mc
         INNER JOIN {$courses_table} c ON c.id = mc.course_id
         WHERE mc.id = %d AND mc.member_id = %d LIMIT 1",
        $member_course_id,
        $member_id
    ));
    if (!$row) {
        return ['success' => false, 'message' => 'ثبت‌نام دوره یافت نشد.'];
    }

    $inv_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$inv_table} WHERE member_course_id = %d",
        $member_course_id
    ));
    if ($inv_count > 0) {
        return ['success' => false, 'message' => 'برای این ثبت‌نام قبلاً صورت‌حساب صادر شده است.'];
    }

    $short_mode = isset($options['short_sessions_mode']) ? sanitize_text_field((string) $options['short_sessions_mode']) : 'charge_remaining';
    if (!in_array($short_mode, ['charge_remaining', 'defer_to_next_month'], true)) {
        $short_mode = 'charge_remaining';
    }

    $course = (object) [
        'id' => (int) $row->course_id,
        'title' => (string) $row->course_title,
        'price' => (float) $row->price,
        'price_per_session' => isset($row->price_per_session) ? (float) $row->price_per_session : 0,
        'sessions_count' => isset($row->sessions_count) ? (int) $row->sessions_count : 0,
        'private_variable_coach_pricing' => isset($row->private_variable_coach_pricing) ? $row->private_variable_coach_pricing : 0,
        'start_date' => isset($row->start_date) ? $row->start_date : null,
        'end_date' => isset($row->end_date) ? $row->end_date : null,
    ];

    $registration_ymd = current_time('Y-m-d');
    if (!empty($options['enrollment_start_ymd'])) {
        $registration_ymd = (string) $options['enrollment_start_ymd'];
    } elseif (!empty($options['enrollment_start_shamsi'])) {
        $resolved = sc_resolve_enrollment_start_ymd($course, (string) $options['enrollment_start_shamsi']);
        if ($resolved['ymd'] !== '') {
            $registration_ymd = $resolved['ymd'];
        }
    }
    $preview = sc_calculate_enrollment_billing_preview($course, $row, $registration_ymd);

    if (!empty($preview['needs_short_session_choice']) && $short_mode === 'defer_to_next_month') {
        if (sc_member_courses_support_billing_deferred()) {
            $wpdb->update(
                $mc_table,
                [
                    'billing_deferred' => 1,
                    'status' => 'inactive',
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $member_course_id],
                ['%d', '%s', '%s'],
                ['%d']
            );
        }
        return [
            'success' => true,
            'deferred' => true,
            'message' => 'ثبت‌نام انجام شد. صورت‌حساب اولیه در تاریخ صدور ماه بعد ایجاد می‌شود.',
        ];
    }

    if (!empty($preview['needs_short_session_choice']) && $short_mode === 'charge_remaining' && (int) $preview['sessions_count'] < 1) {
        if (sc_member_courses_support_billing_deferred()) {
            $wpdb->update(
                $mc_table,
                ['billing_deferred' => 1, 'status' => 'inactive', 'updated_at' => current_time('mysql')],
                ['id' => $member_course_id],
                ['%d', '%s', '%s'],
                ['%d']
            );
        }
        return [
            'success' => true,
            'deferred' => true,
            'message' => 'جلسه‌ای تا پایان ماه باقی نمانده؛ صورت‌حساب در ماه بعد صادر می‌شود.',
        ];
    }

    $amount = (float) $preview['amount'];
    if ($amount <= 0 && $preview['mode'] === 'prorated') {
        if (sc_member_courses_support_billing_deferred()) {
            $wpdb->update(
                $mc_table,
                ['billing_deferred' => 1, 'status' => 'inactive', 'updated_at' => current_time('mysql')],
                ['id' => $member_course_id],
                ['%d', '%s', '%s'],
                ['%d']
            );
        }
        return [
            'success' => true,
            'deferred' => true,
            'message' => 'مبلغ قابل محاسبه نیست؛ صورت‌حساب در ماه بعد صادر می‌شود.',
        ];
    }

    $invoice_type = ($preview['mode'] === 'prorated') ? 'initial_prorated' : '';
    $fee_label = (string) $preview['fee_label'];
    $billing_meta = [
        'billing_period_shamsi' => (string) $preview['billing_period_shamsi'],
        'billing_sessions_count' => (int) $preview['sessions_count'],
    ];

    $discount_meta = isset($options['discount_meta']) && is_array($options['discount_meta']) ? $options['discount_meta'] : null;

    $result = sc_create_course_invoice(
        $member_id,
        (int) $row->course_id,
        $member_course_id,
        $amount,
        $invoice_type,
        $fee_label,
        $discount_meta,
        $billing_meta
    );

    if (is_array($result) && !empty($result['success']) && sc_member_courses_support_billing_deferred()) {
        $wpdb->update(
            $mc_table,
            ['billing_deferred' => 0, 'updated_at' => current_time('mysql')],
            ['id' => $member_course_id],
            ['%d', '%s'],
            ['%d']
        );
    }

    return is_array($result) ? $result : ['success' => false, 'message' => 'خطای نامشخص در ایجاد صورت‌حساب.'];
}

/**
 * AJAX: پیش‌نمایش مبلغ ثبت‌نام
 */
add_action('wp_ajax_sc_enroll_course_billing_preview', 'sc_ajax_enroll_course_billing_preview');
function sc_ajax_enroll_course_billing_preview() {
    check_ajax_referer('sc_enroll_billing_preview', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'ورود لازم است.']);
    }

    global $wpdb;
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $sessions = isset($_POST['enrollment_sessions']) ? absint($_POST['enrollment_sessions']) : 0;
    $chapter = isset($_POST['enrollment_chapter']) ? sanitize_text_field(wp_unslash($_POST['enrollment_chapter'])) : '';
    $coach_id = isset($_POST['enrollment_coach_id']) ? absint($_POST['enrollment_coach_id']) : 0;
    $group = isset($_POST['enrollment_group']) ? sanitize_text_field(wp_unslash($_POST['enrollment_group'])) : '';
    $start_shamsi = isset($_POST['enrollment_start_shamsi']) ? sanitize_text_field(wp_unslash($_POST['enrollment_start_shamsi'])) : '';

    if ($course_id < 1) {
        wp_send_json_error(['message' => 'دوره نامعتبر است.']);
    }

    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_courses WHERE id = %d AND deleted_at IS NULL AND is_active = 1",
        $course_id
    ));
    if (!$course) {
        wp_send_json_error(['message' => 'دوره یافت نشد.']);
    }

    $start = function_exists('sc_resolve_enrollment_start_ymd')
        ? sc_resolve_enrollment_start_ymd($course, $start_shamsi !== '' ? $start_shamsi : null)
        : ['ymd' => current_time('Y-m-d'), 'shamsi' => '', 'error' => ''];
    if ($start['error'] !== '' && $start['ymd'] === '') {
        wp_send_json_error(['message' => $start['error']]);
    }

    $member_course = (object) [
        'chapter' => $chapter,
        'coach_id' => $coach_id,
        'group_name' => $group,
        'enrollment_sessions' => $sessions > 0 ? $sessions : null,
    ];

    $preview = sc_calculate_enrollment_billing_preview($course, $member_course, $start['ymd']);
    $amount_html = function_exists('wc_price')
        ? wc_price((float) $preview['amount'])
        : number_format((float) $preview['amount'], 0, '.', ',') . ' تومان';

    wp_send_json_success([
        'amount' => (float) $preview['amount'],
        'amount_html' => $amount_html,
        'sessions_count' => (int) $preview['sessions_count'],
        'billing_sessions' => isset($preview['billing_sessions']) && is_array($preview['billing_sessions']) ? $preview['billing_sessions'] : [],
        'mode' => (string) $preview['mode'],
        'needs_short_session_choice' => !empty($preview['needs_short_session_choice']),
        'show_start_date_picker' => !empty($preview['show_start_date_picker']),
        'registration_shamsi' => isset($preview['registration_shamsi']) ? (string) $preview['registration_shamsi'] : '',
        'registration_ymd' => isset($preview['registration_ymd']) ? (string) $preview['registration_ymd'] : '',
        'start_date_error' => isset($preview['start_date_error']) ? (string) $preview['start_date_error'] : '',
        'fee_label' => (string) $preview['fee_label'],
        'uses_fixed_date_proration' => sc_invoice_mode_uses_fixed_date_proration(),
    ]);
}
