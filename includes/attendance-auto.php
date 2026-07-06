<?php
/**
 * تطبیق لاگ دستگاه با برنامهٔ هفتگی دوره و ثبت حضور/غیبت خودکار
 */
if (!defined('ABSPATH')) {
    exit;
}

add_filter('cron_schedules', 'sc_add_every_five_minutes_schedule_for_attendance');
function sc_add_every_five_minutes_schedule_for_attendance($schedules) {
    if (!isset($schedules['every_five_minutes'])) {
        $schedules['every_five_minutes'] = [
            'interval' => 300,
            'display' => 'هر ۵ دقیقه',
        ];
    }
    return $schedules;
}

add_action('init', 'sc_schedule_attendance_automation_cron');
function sc_schedule_attendance_automation_cron() {
    if (!wp_next_scheduled('sc_attendance_automation_cron_event')) {
        wp_schedule_event(time(), 'every_five_minutes', 'sc_attendance_automation_cron_event');
    }
}

add_action('sc_attendance_automation_cron_event', 'sc_run_attendance_automation_cron');
function sc_run_attendance_automation_cron() {
    if ((int) sc_get_setting('attendance_api_auto_enabled', '1') !== 1) {
        return;
    }
    sc_attendance_auto_process_api_logs(100);
    sc_attendance_auto_mark_absents();
}

/**
 * @return int
 */
function sc_attendance_auto_grace_before_minutes() {
    return max(0, (int) sc_get_setting('attendance_grace_before_minutes', '15'));
}

/**
 * @return int
 */
function sc_attendance_auto_grace_after_minutes() {
    return max(0, (int) sc_get_setting('attendance_grace_after_minutes', '30'));
}

/**
 * دقیقه بعد از پایان کلاس برای ثبت غیبت
 *
 * @return int
 */
function sc_attendance_auto_absent_after_end_minutes() {
    return max(0, (int) sc_get_setting('attendance_absent_after_end_minutes', '15'));
}

/**
 * @param string $hhmmss H:i:s
 * @return int
 */
function sc_attendance_time_to_seconds($hhmmss) {
    $hhmmss = is_string($hhmmss) ? trim($hhmmss) : '';
    if ($hhmmss === '') {
        return 0;
    }
    $p = explode(':', $hhmmss);
    $h = isset($p[0]) ? (int) $p[0] : 0;
    $m = isset($p[1]) ? (int) $p[1] : 0;
    $s = isset($p[2]) ? (int) $p[2] : 0;
    return $h * 3600 + $m * 60 + $s;
}

/**
 * @param string $t1s
 * @param string $t1e
 * @param string $t2s
 * @param string $t2e
 */
function sc_attendance_time_ranges_overlap($t1s, $t1e, $t2s, $t2e) {
    $a1 = sc_attendance_time_to_seconds($t1s);
    $b1 = sc_attendance_time_to_seconds($t1e);
    $a2 = sc_attendance_time_to_seconds($t2s);
    $b2 = sc_attendance_time_to_seconds($t2e);
    return !($b1 <= $a2 || $a1 >= $b2);
}

/**
 * لاگ در بازهٔ لغوِ همان دوره و تاریخ باشد
 *
 * @param int    $course_id
 * @param string $log_datetime Y-m-d H:i:s
 */
function sc_attendance_log_in_cancelled_window($course_id, $log_datetime) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || $log_datetime === '') {
        return false;
    }
    $d = substr($log_datetime, 0, 10);
    $t = strlen($log_datetime) >= 19 ? substr($log_datetime, 11, 8) : '00:00:00';
    $tbl = $wpdb->prefix . 'sc_course_session_cancellations';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tbl)) !== $tbl) {
        return false;
    }
    $n = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$tbl}` WHERE course_id = %d AND session_date = %s AND time_start <= %s AND time_end >= %s",
        $course_id,
        $d,
        $t,
        $t
    ));
    return $n > 0;
}

/**
 * اسلات کلاس با بازهٔ لغو روی همان روز هم‌پوشانی دارد
 *
 * @param int    $course_id
 * @param string $session_date Y-m-d
 * @param string $slot_start   H:i:s
 * @param string $slot_end     H:i:s
 */
function sc_attendance_slot_overlaps_cancellation($course_id, $session_date, $slot_start, $slot_end) {
    global $wpdb;
    $course_id = absint($course_id);
    $tbl = $wpdb->prefix . 'sc_course_session_cancellations';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tbl)) !== $tbl) {
        return false;
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT time_start, time_end FROM `{$tbl}` WHERE course_id = %d AND session_date = %s",
        $course_id,
        $session_date
    ));
    foreach ($rows as $r) {
        if (sc_attendance_time_ranges_overlap($slot_start, $slot_end, $r->time_start, $r->time_end)) {
            return true;
        }
    }
    return false;
}

/**
 * بعد از ثبت حضور خودکار: دستمزد درصدی مربی بر اساس شاگردهای خودش
 *
 * @param int    $course_id
 * @param string $attendance_date Y-m-d
 */
function sc_attendance_auto_refresh_coach_percentage_salary($course_id, $attendance_date) {
    $course_id = absint($course_id);
    if (!$course_id || $attendance_date === '') {
        return;
    }
    if (function_exists('sc_refresh_coach_percentage_salary_for_course_date')) {
        sc_refresh_coach_percentage_salary_for_course_date($course_id, $attendance_date);
    }
}

/**
 * @param int    $log_id
 * @param int    $attendance_id
 */
function sc_attendance_mark_log_matched($log_id, $attendance_id) {
    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'sc_api_attendance_logs',
        [
            'matched_to_attendance' => 1,
            'matched_attendance_id' => absint($attendance_id),
        ],
        ['id' => absint($log_id)],
        ['%d', '%d'],
        ['%d']
    );
}

/**
 * پردازش رکوردهای لاگ بدون تطبیق
 *
 * @param int $limit
 */
function sc_attendance_auto_process_api_logs($limit = 100) {
    global $wpdb;
    if (!function_exists('sc_course_ir_weekday_from_gregorian_ymd') || !sc_course_weekly_schedule_table_ready()) {
        return;
    }
    $limit = max(1, min(500, (int) $limit));
    $logs_table = $wpdb->prefix . 'sc_api_attendance_logs';
    $members_table = $wpdb->prefix . 'sc_members';
    $mc_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $sch_table = $wpdb->prefix . 'sc_course_weekly_schedule';
    $att_table = $wpdb->prefix . 'sc_attendances';

    $grace_b = sc_attendance_auto_grace_before_minutes();
    $grace_a = sc_attendance_auto_grace_after_minutes();

    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT l.* FROM `{$logs_table}` l
         INNER JOIN `{$members_table}` m ON m.id = CAST(l.employee_code AS UNSIGNED)
         WHERE l.matched_to_attendance = 0
         ORDER BY l.id ASC
         LIMIT %d",
        $limit
    ));

    foreach ($logs as $log) {
        $member_id = (int) $log->employee_code;
        if ($member_id <= 0) {
            continue;
        }
        $log_dt = $log->log_datetime;
        $log_date = substr($log_dt, 0, 10);
        $log_time = strlen($log_dt) >= 19 ? substr($log_dt, 11, 8) : ($log->log_time ?? '00:00:00');
        $log_secs = sc_attendance_time_to_seconds($log_time);

        $ir_wd = sc_course_ir_weekday_from_gregorian_ymd($log_date);
        if ($ir_wd === null) {
            continue;
        }

        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id AS course_id, c.title, c.price_per_session, c.start_date, c.end_date
             FROM `{$mc_table}` mc
             INNER JOIN `{$courses_table}` c ON c.id = mc.course_id AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')
             WHERE mc.member_id = %d AND mc.status = 'active'
             GROUP BY c.id, c.title, c.price_per_session, c.start_date, c.end_date
             ORDER BY c.id ASC",
            $member_id
        ));

        $best = null;
        foreach ($courses as $crs) {
            if (sc_attendance_log_in_cancelled_window((int) $crs->course_id, $log_dt)) {
                continue;
            }
            if (!empty($crs->start_date) && strcmp($log_date, $crs->start_date) < 0) {
                continue;
            }
            if (!empty($crs->end_date) && $crs->end_date !== '0000-00-00' && strcmp($log_date, $crs->end_date) > 0) {
                continue;
            }
            $slots = $wpdb->get_results($wpdb->prepare(
                "SELECT id, time_start, time_end, sort_order FROM `{$sch_table}`
                 WHERE course_id = %d AND weekday = %d
                 ORDER BY time_start ASC, sort_order ASC, id ASC",
                $crs->course_id,
                $ir_wd
            ));
            foreach ($slots as $sl) {
                $s1 = sc_attendance_time_to_seconds($sl->time_start) - $grace_b * 60;
                $e1 = sc_attendance_time_to_seconds($sl->time_end) + $grace_a * 60;
                if ($log_secs >= $s1 && $log_secs <= $e1) {
                    $best = [
                        'course_id' => (int) $crs->course_id,
                        'course_title' => (string) $crs->title,
                        'price_per_session' => floatval($crs->price_per_session),
                        'schedule_slot_id' => (int) $sl->id,
                        'time_start' => $sl->time_start,
                        'time_end' => $sl->time_end,
                    ];
                    break 2;
                }
            }
        }

        if ($best === null) {
            continue;
        }

        $course_id = $best['course_id'];
        $slot_id = $best['schedule_slot_id'];
        $attendance_date = $log_date;
        $attendance_date_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($attendance_date) : $attendance_date;

        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `{$att_table}` WHERE member_id = %d AND course_id = %d AND attendance_date = %s AND schedule_slot_id = %d LIMIT 1",
            $member_id,
            $course_id,
            $attendance_date,
            $slot_id
        ));

        if ($existing_id > 0) {
            $st = (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM `{$att_table}` WHERE id = %d", $existing_id));
            if ($st === 'present') {
                sc_attendance_mark_log_matched($log->id, $existing_id);
            }
            continue;
        }

        $price_per_session = $best['price_per_session'];
        $course_title = $best['course_title'];

        $need_deduct = sc_is_member_team($member_id) && $price_per_session > 0
            && function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet();
        if ($need_deduct) {
            $deduct = sc_deduct_wallet_session_fee($member_id, $price_per_session, $course_title, $attendance_date_shamsi, 0);
            if (empty($deduct['success'])) {
                continue;
            }
        }

        $ins = $wpdb->insert(
            $att_table,
            [
                'member_id' => $member_id,
                'course_id' => $course_id,
                'schedule_slot_id' => $slot_id,
                'attendance_date' => $attendance_date,
                'status' => 'present',
                'record_method' => 'api',
                'absence_sms_sent' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );
        if (!$ins) {
            continue;
        }
        $new_id = (int) $wpdb->insert_id;
        sc_decrease_member_session($member_id, $course_id);
        sc_attendance_auto_refresh_coach_percentage_salary($course_id, $attendance_date);
        sc_attendance_mark_log_matched($log->id, $new_id);
    }
}

/**
 * ثبت غیبت برای اسلات‌هایی که مهلتشان گذشته و حضور ثبت نشده
 */
function sc_attendance_auto_mark_absents() {
    global $wpdb;
    if (!function_exists('sc_course_ir_weekday_from_gregorian_ymd') || !sc_course_weekly_schedule_table_ready()) {
        return;
    }
    $absent_after = sc_attendance_auto_absent_after_end_minutes();
    $now_ts = current_time('timestamp');
    $att_table = $wpdb->prefix . 'sc_attendances';
    $mc_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $sch_table = $wpdb->prefix . 'sc_course_weekly_schedule';

    for ($day_off = 0; $day_off <= 14; $day_off++) {
        $session_date = function_exists('wp_date')
            ? wp_date('Y-m-d', strtotime('-' . $day_off . ' days', $now_ts))
            : gmdate('Y-m-d', $now_ts - $day_off * DAY_IN_SECONDS);
        $ir_wd = sc_course_ir_weekday_from_gregorian_ymd($session_date);
        if ($ir_wd === null) {
            continue;
        }

        $slots = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id AS schedule_slot_id, s.course_id, s.time_start, s.time_end, c.title, c.price_per_session, c.start_date, c.end_date
             FROM `{$sch_table}` s
             INNER JOIN `{$courses_table}` c ON c.id = s.course_id AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')
             WHERE s.weekday = %d",
            $ir_wd
        ));

        foreach ($slots as $slot) {
            if (sc_attendance_slot_overlaps_cancellation((int) $slot->course_id, $session_date, $slot->time_start, $slot->time_end)) {
                continue;
            }
            $end_ts = strtotime(trim($session_date . ' ' . $slot->time_end));
            if (!$end_ts) {
                continue;
            }
            if ($now_ts < $end_ts + $absent_after * 60) {
                continue;
            }
            if (!empty($slot->start_date) && strcmp($session_date, $slot->start_date) < 0) {
                continue;
            }
            if (!empty($slot->end_date) && $slot->end_date !== '0000-00-00' && strcmp($session_date, $slot->end_date) > 0) {
                continue;
            }

            $members = $wpdb->get_col($wpdb->prepare(
                "SELECT mc.member_id FROM `{$mc_table}` mc
                 WHERE mc.course_id = %d AND mc.status = 'active'",
                $slot->course_id
            ));
            $has_new_absent_attendance = false;

            foreach ($members as $mid) {
                $member_id = (int) $mid;
                if ($member_id <= 0) {
                    continue;
                }
                $exists = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM `{$att_table}` WHERE member_id = %d AND course_id = %d AND attendance_date = %s AND schedule_slot_id = %d LIMIT 1",
                    $member_id,
                    $slot->course_id,
                    $session_date,
                    $slot->schedule_slot_id
                ));
                if ($exists > 0) {
                    continue;
                }

                $course_title = (string) $slot->title;
                $price_per_session = floatval($slot->price_per_session);
                $attendance_date_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($session_date) : $session_date;

                $need_deduct = sc_is_member_team($member_id) && $price_per_session > 0
                    && function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet();
                if ($need_deduct) {
                    $deduct = sc_deduct_wallet_session_fee($member_id, $price_per_session, $course_title, $attendance_date_shamsi, 0);
                    if (empty($deduct['success'])) {
                        continue;
                    }
                }

                $ins = $wpdb->insert(
                    $att_table,
                    [
                        'member_id' => $member_id,
                        'course_id' => (int) $slot->course_id,
                        'schedule_slot_id' => (int) $slot->schedule_slot_id,
                        'attendance_date' => $session_date,
                        'status' => 'absent',
                        'record_method' => 'auto_absent',
                        'absence_sms_sent' => 0,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ],
                    ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
                );
                if ($ins) {
                    $new_id = (int) $wpdb->insert_id;
                    sc_decrease_member_session($member_id, (int) $slot->course_id);
                    do_action('sc_attendance_absent', $new_id);
                    $has_new_absent_attendance = true;
                }
            }
            if ($has_new_absent_attendance && function_exists('sc_refresh_coach_percentage_salary_for_course_date')) {
                // بازمحاسبه دستمزد بعد از ثبت غیبت‌های خودکار.
                // وقتی calc_couch_salary=1 باشد فقط حاضرها شمرده می‌شوند و اثر نمی‌گذارد؛
                // وقتی 0 باشد غیبت‌ها هم در محاسبه لحاظ می‌شوند.
                sc_refresh_coach_percentage_salary_for_course_date((int) $slot->course_id, $session_date);
            }
        }
    }
}
