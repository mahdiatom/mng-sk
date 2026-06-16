<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/private-booking-admin-functions.php';

function sc_is_private_course($course) {
    return is_object($course) && isset($course->course_type) && $course->course_type === 'private';
}

function sc_get_private_class_booking_limits() {
    return [
        'cancel_minutes_before' => max(0, (int) sc_get_setting('private_class_cancel_minutes_before', '1440')),
        'reschedule_minutes_before' => max(0, (int) sc_get_setting('private_class_reschedule_minutes_before', '1440')),
    ];
}

function sc_private_session_status_label($status) {
    $map = [
        'pending_payment' => 'در انتظار پرداخت',
        'scheduled' => 'برنامه‌ریزی‌شده',
        'cancelled' => 'لغو شده',
        'absent' => 'غایب',
        'excused' => 'غیبت مجاز',
        'rescheduled' => 'جابجا شده',
        'done' => 'برگزار شده',
        'active' => 'فعال',
        'paused' => 'متوقف',
        'completed' => 'تکمیل‌شده',
    ];
    return isset($map[$status]) ? $map[$status] : $status;
}

function sc_get_private_course_coaches($course_id, $chapter_name = '') {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id) {
        return [];
    }
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $course_coaches = $wpdb->prefix . 'sc_course_coaches';
    $coaches = $wpdb->prefix . 'sc_coaches';

    $sql = "SELECT c.id, c.first_name, c.last_name
         FROM {$course_coaches} cc
         INNER JOIN {$coaches} c ON c.id = cc.coach_id
         WHERE cc.course_id = %d
           AND c.is_active = 1
           AND (c.is_private_enabled IS NULL OR c.is_private_enabled = 1)";
    $params = [$course_id];
    if ($chapter_name !== '') {
        $sql .= " AND cc.chapter_name = %s";
        $params[] = $chapter_name;
    }
    $sql .= " ORDER BY c.first_name, c.last_name";

    return $wpdb->get_results($wpdb->prepare($sql, $params));
}

function sc_private_schedule_row_matches($row, $chapter_name, $coach_id) {
    $row_chapter = isset($row->chapter_name) ? trim((string) $row->chapter_name) : '';
    $row_coach = isset($row->coach_id) ? (int) $row->coach_id : 0;
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $coach_id = absint($coach_id);

    if ($chapter_name !== '' && $row_chapter !== '' && $row_chapter !== $chapter_name) {
        return false;
    }
    if ($coach_id > 0 && $row_coach > 0 && $row_coach !== $coach_id) {
        return false;
    }
    // ردیف عمومی (بدون شعبه/مربی) برای انتخاب مشخص نمایش داده نشود
    if ($coach_id > 0 && $chapter_name !== '' && $row_chapter === '' && $row_coach === 0) {
        return false;
    }
    return true;
}

function sc_private_get_branch_capacity($course_id, $chapter_name, $coach_id, $course = null) {
    $capacity = null;
    if (function_exists('sc_get_course_coach_branch_meta')) {
        $meta = sc_get_course_coach_branch_meta($course_id, $chapter_name, $coach_id);
        if ($meta && $meta['capacity'] !== null && (int) $meta['capacity'] > 0) {
            return (int) $meta['capacity'];
        }
    }
    if (!$course) {
        global $wpdb;
        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT capacity FROM {$wpdb->prefix}sc_courses WHERE id = %d",
            absint($course_id)
        ));
    }
    if ($course && !empty($course->capacity)) {
        return max(1, (int) $course->capacity);
    }
    return 1;
}

function sc_private_build_booking_config(array $courses) {
    $map = [];
    $weekday_labels = function_exists('sc_course_weekday_labels_ir') ? sc_course_weekday_labels_ir() : [];

    foreach ($courses as $course) {
        $course_id = (int) $course->id;
        $branch_cfg = function_exists('sc_get_course_enrollment_branch_config')
            ? sc_get_course_enrollment_branch_config($course_id)
            : ['chapters' => [], 'schedule' => []];
        $branch_meta = function_exists('sc_get_course_coach_branch_meta_map')
            ? sc_get_course_coach_branch_meta_map($course_id)
            : [];
        $session_options = function_exists('sc_get_course_private_session_count_options')
            ? sc_get_course_private_session_count_options($course_id)
            : [];

        $priced_packages = [];
        if (empty($course->private_variable_coach_pricing) && function_exists('sc_get_course_packages')) {
            foreach (sc_get_course_packages($course_id) as $pkg) {
                if ((float) $pkg->price > 0) {
                    $priced_packages[] = [
                        'sessions' => (int) $pkg->sessions_count,
                        'price' => (float) $pkg->price,
                    ];
                }
            }
        }

        $chapters = [];
        foreach ((array) ($branch_cfg['chapters'] ?? []) as $ch) {
            $ch_name = (string) ($ch['name'] ?? '');
            if ($ch_name === '') {
                continue;
            }
            $coaches = [];
            foreach ((array) ($ch['coaches'] ?? []) as $co) {
                $cid = (int) ($co['id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                $meta = isset($branch_meta[$ch_name][$cid]) ? $branch_meta[$ch_name][$cid] : null;
                $coaches[] = [
                    'id' => $cid,
                    'name' => (string) ($co['label'] ?? ('مربی #' . $cid)),
                    'price_per_session' => $meta ? (float) $meta['price_per_session'] : 0,
                    'capacity' => ($meta && $meta['capacity'] !== null) ? (int) $meta['capacity'] : null,
                ];
            }
            $chapters[] = [
                'name' => $ch_name,
                'coaches' => $coaches,
            ];
        }

        $coach_names = [];
        foreach ($chapters as $ch) {
            foreach ((array) ($ch['coaches'] ?? []) as $co) {
                $coach_names[(int) $co['id']] = (string) $co['name'];
            }
        }

        $slots = [];
        if (function_exists('sc_get_course_weekly_schedule_rows')) {
            foreach (sc_get_course_weekly_schedule_rows($course_id) as $row) {
                $wd = isset($row->weekday) ? (int) $row->weekday : 0;
                $slot_coach_id = isset($row->coach_id) ? (int) $row->coach_id : 0;
                $slot_chapter = isset($row->chapter_name) ? (string) $row->chapter_name : '';
                $slot_label = (isset($weekday_labels[$wd]) ? $weekday_labels[$wd] : '-') . ' | '
                    . substr((string) $row->time_start, 0, 5) . ' تا ' . substr((string) $row->time_end, 0, 5);
                if ($slot_chapter !== '') {
                    $slot_label .= ' | ' . $slot_chapter;
                }
                if ($slot_coach_id > 0 && isset($coach_names[$slot_coach_id])) {
                    $slot_label .= ' | ' . $coach_names[$slot_coach_id];
                }
                $slots[] = [
                    'id' => (int) $row->id,
                    'weekday' => $wd,
                    'chapter' => $slot_chapter,
                    'coach_id' => $slot_coach_id,
                    'coach_name' => ($slot_coach_id > 0 && isset($coach_names[$slot_coach_id])) ? $coach_names[$slot_coach_id] : '',
                    'label' => $slot_label,
                ];
            }
        }

        $map[$course_id] = [
            'title' => (string) $course->title,
            'variable_coach_pricing' => !empty($course->private_variable_coach_pricing),
            'price_per_session' => isset($course->price_per_session) ? (float) $course->price_per_session : 0,
            'price' => isset($course->price) ? (float) $course->price : 0,
            'chapters' => $chapters,
            'requires_chapter_choice' => count($chapters) > 1,
            'slots' => $slots,
            'session_options' => array_values(array_map('intval', $session_options)),
            'packages' => $priced_packages,
        ];
    }

    return $map;
}

/**
 * حداقل تعداد جلسه برای پیش‌نمایش ظرفیت اسلات‌ها
 */
function sc_private_guess_preview_sessions($course_id) {
    $course_id = absint($course_id);
    if ($course_id > 0 && function_exists('sc_get_course_private_session_count_options')) {
        $options = sc_get_course_private_session_count_options($course_id);
        if (!empty($options)) {
            $ints = array_map('intval', $options);
            return max(1, min($ints));
        }
    }
    return 1;
}

function sc_private_session_occurrence_is_past($session_date, $time_start = '') {
    $session_date = sanitize_text_field((string) $session_date);
    if ($session_date === '') {
        return false;
    }
    $now_ts = (int) current_time('timestamp');
    $today = function_exists('wp_date') ? wp_date('Y-m-d', $now_ts) : current_time('Y-m-d');
    if ($session_date < $today) {
        return true;
    }
    if ($session_date > $today) {
        return false;
    }
    $time_start = trim((string) $time_start);
    if ($time_start === '') {
        return false;
    }
    $session_ts = strtotime($session_date . ' ' . $time_start);
    if (!$session_ts) {
        return false;
    }
    return $session_ts <= $now_ts;
}

function sc_private_normalize_booking_start_date($start_date) {
    $start_date = sanitize_text_field((string) $start_date);
    $today = current_time('Y-m-d');
    if ($start_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        return $today;
    }
    return $start_date < $today ? $today : $start_date;
}

function sc_private_format_time_range_fa($time_start, $time_end) {
    $time_start = trim((string) $time_start);
    $time_end = trim((string) $time_end);
    $start = strlen($time_start) >= 5 ? substr($time_start, 0, 5) : $time_start;
    $end = strlen($time_end) >= 5 ? substr($time_end, 0, 5) : $time_end;
    if ($start === '' && $end === '') {
        return '';
    }
    return trim($start . ' تا ' . $end);
}

function sc_private_format_session_occurrence_label($session_date, $time_start, $time_end) {
    $session_date = sanitize_text_field((string) $session_date);
    if ($session_date === '') {
        return '';
    }
    $weekday_labels = function_exists('sc_course_weekday_labels_ir') ? sc_course_weekday_labels_ir() : [];
    $wd = function_exists('sc_course_ir_weekday_from_gregorian_ymd')
        ? (int) sc_course_ir_weekday_from_gregorian_ymd($session_date)
        : 0;
    $weekday = isset($weekday_labels[$wd]) ? $weekday_labels[$wd] : '';
    $shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($session_date) : $session_date;
    $time_label = sc_private_format_time_range_fa($time_start, $time_end);
    $parts = array_filter([$weekday, $shamsi, $time_label !== '' ? ('ساعت ' . $time_label) : '']);
    return implode(' ', $parts);
}

function sc_private_get_slot_session_conflicts($coach_id, $schedule_slot_id, $session_date) {
    global $wpdb;
    $sessions_t = $wpdb->prefix . 'sc_private_booking_sessions';
    $members_t = $wpdb->prefix . 'sc_members';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT s.member_id, s.time_start, s.time_end, s.status,
                m.first_name, m.last_name
         FROM {$sessions_t} s
         LEFT JOIN {$members_t} m ON m.id = s.member_id
         WHERE s.coach_id = %d
           AND s.schedule_slot_id = %d
           AND s.session_date = %s
           AND s.status IN ('scheduled','absent','excused','done')
         ORDER BY s.time_start ASC",
        (int) $coach_id,
        (int) $schedule_slot_id,
        (string) $session_date
    ));
    $member_names = [];
    foreach ((array) $rows as $row) {
        $name = trim((string) ($row->first_name ?? '') . ' ' . (string) ($row->last_name ?? ''));
        if ($name !== '') {
            $member_names[] = $name;
        }
    }
    $member_names = array_values(array_unique($member_names));
    return [
        'count' => count($rows),
        'member_names' => $member_names,
    ];
}

function sc_private_evaluate_slot_plan($row, $slot_coach_id, $start_date, $sessions_count, $capacity, $slot_id) {
    $slot_id = (int) $slot_id;
    $generated = sc_private_generate_sessions([$row], $start_date, $sessions_count);
    $full = false;
    $blocked_dates = 0;
    $future_checks = 0;
    $conflicts = [];

    foreach ($generated as $session) {
        if ((int) ($session['schedule_slot_id'] ?? 0) !== $slot_id) {
            continue;
        }
        $session_date = (string) ($session['session_date'] ?? '');
        $time_start = (string) ($session['time_start'] ?? '');
        $time_end = (string) ($session['time_end'] ?? '');
        if (sc_private_session_occurrence_is_past($session_date, $time_start)) {
            continue;
        }
        $future_checks++;
        if (!sc_private_can_reserve_slot($slot_coach_id, $slot_id, $session_date, $capacity)) {
            $full = true;
            $blocked_dates++;
            if (count($conflicts) < 4) {
                $occupants = sc_private_get_slot_session_conflicts($slot_coach_id, $slot_id, $session_date);
                $names = $occupants['member_names'];
                $conflicts[] = [
                    'session_date' => $session_date,
                    'session_date_shamsi' => function_exists('sc_date_shamsi_date_only')
                        ? sc_date_shamsi_date_only($session_date)
                        : $session_date,
                    'label' => sc_private_format_session_occurrence_label($session_date, $time_start, $time_end),
                    'member_names' => $names,
                    'reserved_count' => (int) ($occupants['count'] ?? 0),
                    'capacity' => max(1, (int) $capacity),
                    'message' => !empty($names)
                        ? sprintf(
                            'در %s برای %s کلاس ثبت شده است.',
                            sc_private_format_session_occurrence_label($session_date, $time_start, $time_end),
                            implode('، ', $names)
                        )
                        : sprintf(
                            'در %s ظرفیت این بازه تکمیل شده است.',
                            sc_private_format_session_occurrence_label($session_date, $time_start, $time_end)
                        ),
                ];
            }
        }
    }

    return [
        'available' => $future_checks > 0 && !$full,
        'full' => $full && $future_checks > 0,
        'unknown' => $future_checks === 0 && empty($generated),
        'blocked_dates' => $blocked_dates,
        'future_checks' => $future_checks,
        'conflicts' => $conflicts,
    ];
}

function sc_private_find_earliest_available_start_date($row, $slot_coach_id, $slot_chapter, $slot_course_id, $sessions_count, $course, $max_days = 366) {
    $slot_id = isset($row->id) ? (int) $row->id : 0;
    if ($slot_id <= 0 || $sessions_count <= 0) {
        return null;
    }
    $capacity = sc_private_get_branch_capacity($slot_course_id, $slot_chapter, $slot_coach_id, $course);
    $today = current_time('Y-m-d');
    $base_ts = strtotime($today . ' 12:00:00');
    if (!$base_ts) {
        $base_ts = (int) current_time('timestamp');
    }

    for ($day = 0; $day < $max_days; $day++) {
        $candidate_ts = strtotime('+' . $day . ' days', $base_ts);
        if (!$candidate_ts) {
            continue;
        }
        $candidate = function_exists('wp_date') ? wp_date('Y-m-d', $candidate_ts) : date('Y-m-d', $candidate_ts);
        $eval = sc_private_evaluate_slot_plan($row, $slot_coach_id, $candidate, $sessions_count, $capacity, $slot_id);
        if (!empty($eval['available'])) {
            $wd = function_exists('sc_course_ir_weekday_from_gregorian_ymd')
                ? (int) sc_course_ir_weekday_from_gregorian_ymd($candidate)
                : 0;
            $weekday_labels = function_exists('sc_course_weekday_labels_ir') ? sc_course_weekday_labels_ir() : [];
            $shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($candidate) : '';
            return [
                'start_date' => $candidate,
                'start_date_shamsi' => $shamsi,
                'weekday_label' => isset($weekday_labels[$wd]) ? $weekday_labels[$wd] : '',
                'label' => trim(
                    (isset($weekday_labels[$wd]) ? $weekday_labels[$wd] . ' ' : '')
                    . $shamsi
                ),
            ];
        }
    }

    return null;
}

function sc_private_build_session_schedule_preview($selected_slot_ids, $course_id, $chapter_name, $coach_id, $start_date, $sessions_count, $is_admin = false) {
    global $wpdb;
    $selected_slot_ids = array_values(array_unique(array_filter(array_map('absint', (array) $selected_slot_ids))));
    $sessions_count = absint($sessions_count);
    if (empty($selected_slot_ids) || $sessions_count <= 0) {
        return null;
    }

    $sch_table = $wpdb->prefix . 'sc_course_weekly_schedule';
    $ph = implode(',', array_fill(0, count($selected_slot_ids), '%d'));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$sch_table} WHERE id IN ($ph) ORDER BY weekday ASC, time_start ASC",
        ...$selected_slot_ids
    ));
    if (empty($rows)) {
        return null;
    }

    $start_date = sc_private_normalize_booking_start_date($start_date);
    $sessions = sc_private_generate_sessions($rows, $start_date, $sessions_count);
    if (empty($sessions)) {
        return null;
    }

    $items = [];
    foreach ($sessions as $session) {
        $session_date = (string) ($session['session_date'] ?? '');
        $items[] = [
            'session_date' => $session_date,
            'session_date_shamsi' => function_exists('sc_date_shamsi_date_only')
                ? sc_date_shamsi_date_only($session_date)
                : $session_date,
            'label' => sc_private_format_session_occurrence_label(
                $session_date,
                (string) ($session['time_start'] ?? ''),
                (string) ($session['time_end'] ?? '')
            ),
            'schedule_slot_id' => (int) ($session['schedule_slot_id'] ?? 0),
        ];
    }

    return [
        'title' => $is_admin
            ? 'برنامه جلسات انتخاب‌شده'
            : 'کلاس‌های شما در این تاریخ‌ها برگزار می‌شود',
        'items' => $items,
        'sessions_count' => count($items),
        'start_date' => $start_date,
    ];
}

function sc_private_check_slots_availability($course_id, $chapter_name, $coach_id, array $schedule_ids, $start_date, $enrollment_sessions, $course = null, $is_admin = false) {
    global $wpdb;
    $course_id = absint($course_id);
    $coach_id = absint($coach_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $schedule_ids = array_values(array_unique(array_filter(array_map('absint', $schedule_ids))));
    $enrollment_sessions = absint($enrollment_sessions);
    $start_date = sc_private_normalize_booking_start_date($start_date);

    if (empty($schedule_ids)) {
        return [];
    }

    $sch_table = $wpdb->prefix . 'sc_course_weekly_schedule';
    $courses_cache = [];
    if ($course && $course_id > 0) {
        $courses_cache[$course_id] = $course;
    }

    $result = [];
    foreach ($schedule_ids as $slot_id) {
        $slot_id = (int) $slot_id;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$sch_table} WHERE id = %d",
            $slot_id
        ));
        if (!$row) {
            $result[$slot_id] = ['available' => false, 'full' => true, 'unknown' => false];
            continue;
        }

        $slot_course_id = $course_id > 0 ? $course_id : (int) $row->course_id;
        $row_chapter = sanitize_text_field((string) ($row->chapter_name ?? ''));
        $row_coach_id = (int) ($row->coach_id ?? 0);
        $slot_chapter = $row_chapter !== '' ? $row_chapter : $chapter_name;
        $slot_coach_id = $row_coach_id > 0 ? $row_coach_id : $coach_id;

        if ($slot_chapter === '' || $slot_coach_id <= 0) {
            $result[$slot_id] = ['available' => true, 'full' => false, 'unknown' => true];
            continue;
        }

        if (!isset($courses_cache[$slot_course_id])) {
            $courses_cache[$slot_course_id] = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sc_courses WHERE id = %d",
                $slot_course_id
            ));
        }
        $slot_course = $courses_cache[$slot_course_id];
        if (!$slot_course) {
            $result[$slot_id] = ['available' => false, 'full' => true, 'unknown' => false];
            continue;
        }

        $sessions_count = $enrollment_sessions > 0
            ? $enrollment_sessions
            : sc_private_guess_preview_sessions($slot_course_id);
        $capacity = sc_private_get_branch_capacity($slot_course_id, $slot_chapter, $slot_coach_id, $slot_course);
        $eval = sc_private_evaluate_slot_plan($row, $slot_coach_id, $start_date, $sessions_count, $capacity, $slot_id);

        $slot_result = [
            'available' => !empty($eval['available']),
            'full' => !empty($eval['full']),
            'unknown' => !empty($eval['unknown']),
            'blocked_dates' => (int) ($eval['blocked_dates'] ?? 0),
            'preview_sessions' => $sessions_count,
            'start_date_used' => $start_date,
            'conflicts' => $eval['conflicts'] ?? [],
        ];

        if (!empty($eval['full'])) {
            $conflict_messages = [];
            foreach ((array) ($eval['conflicts'] ?? []) as $conflict) {
                if (!empty($conflict['message'])) {
                    $conflict_messages[] = (string) $conflict['message'];
                }
            }
            if ($is_admin) {
                $slot_result['admin_message'] = !empty($conflict_messages)
                    ? implode(' ', $conflict_messages)
                    : 'این بازه در تاریخ شروع انتخابی پر است.';
                $slot_result['admin_suggestion'] = 'ظرفیت دوره یا شعبه را افزایش دهید، یا تاریخ شروع دیگری انتخاب کنید.';
                $earliest = sc_private_find_earliest_available_start_date(
                    $row,
                    $slot_coach_id,
                    $slot_chapter,
                    $slot_course_id,
                    $sessions_count,
                    $slot_course
                );
                if ($earliest && !empty($earliest['label'])) {
                    $slot_result['admin_suggestion'] .= ' اولین تاریخ آزاد پیشنهادی: ' . (string) $earliest['label'] . '.';
                    $slot_result['suggested_start'] = $earliest;
                }
            } else {
                $earliest = sc_private_find_earliest_available_start_date(
                    $row,
                    $slot_coach_id,
                    $slot_chapter,
                    $slot_course_id,
                    $sessions_count,
                    $slot_course
                );
                if ($earliest) {
                    $slot_result['suggested_start'] = $earliest;
                    $slot_result['user_suggestion'] = sprintf(
                        'اولین تاریخی که این بازه برای %d جلسه قابل رزرو است: %s',
                        $sessions_count,
                        (string) ($earliest['label'] ?? $earliest['start_date_shamsi'] ?? '')
                    );
                } else {
                    $slot_result['user_suggestion'] = 'در یک سال آینده تاریخ آزادی برای این بازه یافت نشد. لطفاً بازه یا تعداد جلسات دیگری انتخاب کنید.';
                }
            }
        }

        $result[$slot_id] = $slot_result;
    }

    return $result;
}

add_action('wp_ajax_sc_private_check_slots', 'sc_private_ajax_check_slots');
function sc_private_ajax_check_slots() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'لطفاً وارد شوید.'], 403);
    }
    check_ajax_referer('sc_private_check_slots', 'nonce');

    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $coach_id = isset($_POST['coach_id']) ? absint($_POST['coach_id']) : 0;
    $chapter = isset($_POST['chapter']) ? sanitize_text_field(wp_unslash($_POST['chapter'])) : '';
    $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : current_time('Y-m-d');
    if (!empty($_POST['start_date_shamsi']) && function_exists('sc_shamsi_to_gregorian_date')) {
        $maybe = sc_shamsi_to_gregorian_date(sanitize_text_field(wp_unslash($_POST['start_date_shamsi'])));
        if ($maybe) {
            $start_date = $maybe;
        }
    }
    $start_date = sc_private_normalize_booking_start_date($start_date);
    $enrollment_sessions = isset($_POST['enrollment_sessions']) ? absint($_POST['enrollment_sessions']) : 0;
    $is_admin = !empty($_POST['is_admin']) && current_user_can('manage_options');
    $schedule_ids = [];
    if (isset($_POST['schedule_slot_ids']) && is_array($_POST['schedule_slot_ids'])) {
        $schedule_ids = array_map('absint', $_POST['schedule_slot_ids']);
    }
    $selected_slot_ids = [];
    if (isset($_POST['selected_slot_ids']) && is_array($_POST['selected_slot_ids'])) {
        $selected_slot_ids = array_map('absint', $_POST['selected_slot_ids']);
    }

    $availability = sc_private_check_slots_availability(
        $course_id,
        $chapter,
        $coach_id,
        $schedule_ids,
        $start_date,
        $enrollment_sessions,
        null,
        $is_admin
    );

    $full_count = 0;
    $available_count = 0;
    foreach ($availability as $slot_info) {
        if (!empty($slot_info['unknown'])) {
            continue;
        }
        if (!empty($slot_info['full'])) {
            $full_count++;
        } else {
            $available_count++;
        }
    }

    $preview_sessions = $enrollment_sessions > 0
        ? $enrollment_sessions
        : ($course_id > 0 ? sc_private_guess_preview_sessions($course_id) : 1);

    $session_schedule = sc_private_build_session_schedule_preview(
        $selected_slot_ids,
        $course_id,
        $chapter,
        $coach_id,
        $start_date,
        $preview_sessions,
        $is_admin
    );

    wp_send_json_success([
        'slots' => $availability,
        'summary' => [
            'full' => $full_count,
            'available' => $available_count,
            'preview_sessions' => $preview_sessions,
            'start_date' => $start_date,
        ],
        'session_schedule' => $session_schedule,
        'is_admin' => $is_admin,
    ]);
}

function sc_private_get_member_id_for_current_user() {
    if (!is_user_logged_in()) {
        return 0;
    }
    $player = function_exists('sc_get_current_member_for_account_user') ? sc_get_current_member_for_account_user() : null;
    return $player ? (int) $player->id : 0;
}

function sc_private_generate_sessions($schedule_rows, $start_date, $sessions_needed) {
    $sessions_needed = max(0, (int) $sessions_needed);
    if ($sessions_needed <= 0 || empty($schedule_rows)) {
        return [];
    }
    $rows_by_weekday = [];
    foreach ($schedule_rows as $row) {
        $wd = isset($row->weekday) ? (int) $row->weekday : 0;
        if ($wd < 1 || $wd > 7) {
            continue;
        }
        if (!isset($rows_by_weekday[$wd])) {
            $rows_by_weekday[$wd] = [];
        }
        $rows_by_weekday[$wd][] = $row;
    }

    $cursor_ts = strtotime($start_date . ' 12:00:00');
    if (!$cursor_ts) {
        $cursor_ts = current_time('timestamp');
    }
    $items = [];
    $guard = 0;
    while (count($items) < $sessions_needed && $guard < 730) {
        $ymd = function_exists('wp_date') ? wp_date('Y-m-d', $cursor_ts) : date('Y-m-d', $cursor_ts);
        $wd = function_exists('sc_course_ir_weekday_from_gregorian_ymd') ? (int) sc_course_ir_weekday_from_gregorian_ymd($ymd) : 0;
        if ($wd >= 1 && $wd <= 7 && !empty($rows_by_weekday[$wd])) {
            foreach ($rows_by_weekday[$wd] as $row) {
                $items[] = [
                    'session_date' => $ymd,
                    'schedule_slot_id' => (int) $row->id,
                    'time_start' => (string) $row->time_start,
                    'time_end' => (string) $row->time_end,
                ];
                if (count($items) >= $sessions_needed) {
                    break;
                }
            }
        }
        $cursor_ts = strtotime('+1 day', $cursor_ts);
        $guard++;
    }
    return $items;
}

function sc_private_slot_reserved_count($coach_id, $schedule_slot_id, $session_date) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_private_booking_sessions';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$t}
         WHERE coach_id = %d
           AND schedule_slot_id = %d
           AND session_date = %s
           AND status IN ('scheduled','absent','excused','done')",
        (int) $coach_id,
        (int) $schedule_slot_id,
        (string) $session_date
    ));
}

function sc_private_can_reserve_slot($coach_id, $schedule_slot_id, $session_date, $capacity) {
    $capacity = max(1, (int) $capacity);
    return sc_private_slot_reserved_count($coach_id, $schedule_slot_id, $session_date) < $capacity;
}

function sc_private_send_cancel_sms($session_id, $cancelled_by = 'user') {
    if (!function_exists('sc_send_sms')) {
        return;
    }
    global $wpdb;
    $session_id = absint($session_id);
    if (!$session_id) {
        return;
    }
    $sessions_table = $wpdb->prefix . 'sc_private_booking_sessions';
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $courses_table = $wpdb->prefix . 'sc_courses';

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT ps.id, ps.member_id, ps.coach_id, ps.session_date, ps.time_start, ps.time_end,
                m.first_name AS member_first_name, m.last_name AS member_last_name, m.player_phone,
                co.first_name AS coach_first_name, co.last_name AS coach_last_name, co.mobile_phone,
                c.title AS course_title
         FROM {$sessions_table} ps
         LEFT JOIN {$members_table} m ON m.id = ps.member_id
         LEFT JOIN {$coaches_table} co ON co.id = ps.coach_id
         LEFT JOIN {$courses_table} c ON c.id = ps.course_id
         WHERE ps.id = %d
         LIMIT 1",
        $session_id
    ));
    if (!$row) {
        return;
    }

    $member_name = trim((string) $row->member_first_name . ' ' . (string) $row->member_last_name);
    $coach_name = trim((string) $row->coach_first_name . ' ' . (string) $row->coach_last_name);
    $date_label = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only((string) $row->session_date) : (string) $row->session_date;
    $time_label = substr((string) $row->time_start, 0, 5) . ' تا ' . substr((string) $row->time_end, 0, 5);

    if ($cancelled_by === 'user') {
        $enabled = (int) sc_get_setting('private_class_sms_user_cancel_to_coach_enabled', '0') === 1;
        if ($enabled && !empty($row->mobile_phone)) {
            $variables = [
                'user_name' => $member_name !== '' ? $member_name : ('#' . (int) $row->member_id),
                'coach_name' => $coach_name !== '' ? $coach_name : 'گرامی',
                'item_name' => (string) $row->course_title,
                'date' => $date_label,
                'time' => $time_label,
            ];
            $template = (string) sc_get_setting('private_class_sms_user_cancel_to_coach_template', 'مربی گرامی %coach_name%، بازیکن %user_name% جلسه خصوصی دوره %item_name% در تاریخ %date% ساعت %time% را لغو کرد.');
            $message = function_exists('sc_replace_sms_variables') ? sc_replace_sms_variables($template, $variables) : $template;
            $pattern_code = (int) sc_get_setting('private_class_sms_user_cancel_to_coach_pattern', '0');
            sc_send_sms((string) $row->mobile_phone, $message, $pattern_code > 0, $pattern_code > 0 ? $pattern_code : null, $variables, 'private_cancel_to_coach');
        }

        $admin_enabled = (int) sc_get_setting('private_class_sms_user_cancel_to_admin_enabled', '0') === 1;
        $admin_phone = (string) sc_get_setting('sms_admin_phone', '');
        if ($admin_enabled && $admin_phone !== '') {
            $variables = [
                'user_name' => $member_name !== '' ? $member_name : ('#' . (int) $row->member_id),
                'coach_name' => $coach_name !== '' ? $coach_name : 'مربی',
                'item_name' => (string) $row->course_title,
                'date' => $date_label,
                'time' => $time_label,
            ];
            $template = (string) sc_get_setting('private_class_sms_user_cancel_to_admin_template', 'مدیر گرامی، بازیکن %user_name% جلسه خصوصی دوره %item_name% با مربی %coach_name% در تاریخ %date% ساعت %time% را لغو کرد.');
            $message = function_exists('sc_replace_sms_variables') ? sc_replace_sms_variables($template, $variables) : $template;
            $pattern_code = (int) sc_get_setting('private_class_sms_user_cancel_to_admin_pattern', '0');
            sc_send_sms($admin_phone, $message, $pattern_code > 0, $pattern_code > 0 ? $pattern_code : null, $variables, 'private_cancel_to_admin');
        }
        return;
    }

    $enabled = (int) sc_get_setting('private_class_sms_coach_cancel_to_user_enabled', '0') === 1;
    if ($enabled && !empty($row->player_phone)) {
        $variables = [
            'user_name' => $member_name !== '' ? $member_name : 'گرامی',
            'coach_name' => $coach_name !== '' ? $coach_name : 'مربی',
            'item_name' => (string) $row->course_title,
            'date' => $date_label,
            'time' => $time_label,
        ];
        $template = (string) sc_get_setting('private_class_sms_coach_cancel_to_user_template', 'بازیکن گرامی %user_name%، جلسه خصوصی دوره %item_name% در تاریخ %date% ساعت %time% توسط مربی/مدیر لغو شد.');
        $message = function_exists('sc_replace_sms_variables') ? sc_replace_sms_variables($template, $variables) : $template;
        $pattern_code = (int) sc_get_setting('private_class_sms_coach_cancel_to_user_pattern', '0');
        sc_send_sms((string) $row->player_phone, $message, $pattern_code > 0, $pattern_code > 0 ? $pattern_code : null, $variables, 'private_cancel_to_user');
    }
}

function sc_private_pending_booking_option_key($booking_id) {
    return 'sc_private_booking_pending_' . absint($booking_id);
}

function sc_private_store_pending_booking_payload($booking_id, array $payload) {
    update_option(sc_private_pending_booking_option_key($booking_id), wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), false);
}

function sc_private_get_pending_booking_payload($booking_id) {
    $raw = get_option(sc_private_pending_booking_option_key($booking_id), '');
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sc_private_delete_pending_booking_payload($booking_id) {
    delete_option(sc_private_pending_booking_option_key($booking_id));
}

add_filter('woocommerce_account_menu_items', 'sc_add_private_class_my_account_menu', 25, 1);
function sc_add_private_class_my_account_menu($items) {
    if (current_user_can('manage_options')) {
        return $items;
    }
    if (function_exists('sc_is_member_verification_gate_enabled_for_user') && sc_is_member_verification_gate_enabled_for_user()) {
        return $items;
    }
    if (!isset($items['sc-enroll-course'])) {
        $items['sc-private-classes'] = 'کلاس خصوصی';
        return $items;
    }
    $new_items = [];
    foreach ($items as $key => $label) {
        $new_items[$key] = $label;
        if ($key === 'sc-enroll-course') {
            $new_items['sc-private-classes'] = 'کلاس خصوصی';
        }
    }
    return $new_items;
}

add_action('init', function () {
    add_rewrite_endpoint('sc-private-classes', EP_ROOT | EP_PAGES);
});
add_filter('query_vars', function ($vars) {
    $vars[] = 'sc-private-classes';
    return $vars;
}, 0);
add_filter('woocommerce_endpoint_sc-private-classes_title', function () {
    return 'کلاس خصوصی';
});

add_action('woocommerce_account_sc-private-classes_endpoint', 'sc_private_classes_endpoint_content');
function sc_private_classes_endpoint_content() {
    if (!function_exists('sc_check_user_active_status')) {
        return;
    }
    $player = sc_check_user_active_status();
    if (!$player) {
        return;
    }

    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    $courses = $wpdb->get_results(
        "SELECT * FROM {$courses_table}
         WHERE deleted_at IS NULL
           AND is_active = 1
           AND course_type = 'private'
         ORDER BY created_at DESC"
    );
    if (!empty($courses) && function_exists('sc_member_matches_item_restrictions')) {
        $courses = array_values(array_filter($courses, function ($course) use ($player) {
            return sc_member_matches_item_restrictions($course, $player);
        }));
    }
    $private_booking_config = function_exists('sc_private_build_booking_config')
        ? sc_private_build_booking_config($courses)
        : [];
    $private_booking_mode = function_exists('sc_get_private_booking_mode') ? sc_get_private_booking_mode() : 'direct_payment';
    $private_booking_user_fields = function_exists('sc_get_private_booking_user_field_settings')
        ? sc_get_private_booking_user_field_settings()
        : array_fill_keys(['course', 'chapter', 'coach', 'slots', 'sessions', 'start_date'], 1);
    $private_member_bookings = [];
    if ($player) {
        $bookings_table = $wpdb->prefix . 'sc_private_course_bookings';
        $status_filter = function_exists('sc_is_private_booking_admin_approval_mode') && sc_is_private_booking_admin_approval_mode()
            ? "('pending_admin','pending_payment','rejected','paused')"
            : "('pending_payment','active','paused')";
        $private_member_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, c.title AS course_title
             FROM {$bookings_table} b
             LEFT JOIN {$courses_table} c ON c.id = b.course_id
             WHERE b.member_id = %d
               AND b.status IN {$status_filter}
             ORDER BY b.created_at DESC
             LIMIT 20",
            (int) $player->id
        ));
    }
    include SC_TEMPLATES_PUBLIC_DIR . 'private-classes.php';
}

add_action('template_redirect', 'sc_handle_private_class_booking');
function sc_handle_private_class_booking() {
    if (!is_user_logged_in() || !isset($_POST['sc_book_private_class'])) {
        return;
    }
    if (function_exists('sc_is_private_booking_admin_approval_mode') && sc_is_private_booking_admin_approval_mode()) {
        return;
    }
    if (!isset($_POST['sc_private_class_nonce']) || !wp_verify_nonce($_POST['sc_private_class_nonce'], 'sc_book_private_class')) {
        wc_add_notice('خطای امنیتی. لطفا دوباره تلاش کنید.', 'error');
        return;
    }
    $member_id = sc_private_get_member_id_for_current_user();
    if (!$member_id) {
        wc_add_notice('ابتدا اطلاعات بازیکن را تکمیل کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }

    $input = sc_private_parse_booking_form_input('POST');
    $input['member_id'] = $member_id;
    $result = sc_private_finalize_booking_with_invoice($input, ['booking_source' => 'user_direct']);

    if (!empty($result['success'])) {
        wc_add_notice('رزرو کلاس خصوصی ثبت شد. برای فعال‌سازی، صورت‌حساب را پرداخت کنید.', 'success');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
        exit;
    }

    wc_add_notice($result['message'] ?? 'خطا در ثبت رزرو.', 'error');
    wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
    exit;
}

add_action('sc_invoice_paid', 'sc_private_activate_sessions_after_payment', 20, 1);
function sc_private_activate_sessions_after_payment($invoice_id) {
    global $wpdb;
    $invoice_id = absint($invoice_id);
    if (!$invoice_id) {
        return;
    }

    $bookings_table = $wpdb->prefix . 'sc_private_course_bookings';
    $sessions_table = $wpdb->prefix . 'sc_private_booking_sessions';
    $courses_table = $wpdb->prefix . 'sc_courses';

    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT *
         FROM {$bookings_table}
         WHERE invoice_id = %d
           AND status = %s",
        $invoice_id,
        'pending_payment'
    ));
    if (empty($bookings)) {
        return;
    }

    $now = current_time('mysql');
    foreach ($bookings as $booking) {
        $booking_id = (int) $booking->id;
        $payload = sc_private_get_pending_booking_payload($booking_id);
        $sessions = isset($payload['sessions']) && is_array($payload['sessions']) ? $payload['sessions'] : [];
        if (empty($sessions)) {
            continue;
        }

        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$courses_table} WHERE id = %d",
            (int) $booking->course_id
        ));
        $chapter = isset($booking->chapter) ? (string) $booking->chapter : '';
        $capacity = sc_private_get_branch_capacity((int) $booking->course_id, $chapter, (int) $booking->coach_id, $course);

        $can_activate = true;
        foreach ($sessions as $session) {
            $slot_id = isset($session['schedule_slot_id']) ? (int) $session['schedule_slot_id'] : 0;
            $session_date = isset($session['session_date']) ? (string) $session['session_date'] : '';
            if (!$slot_id || $session_date === '' || !sc_private_can_reserve_slot((int) $booking->coach_id, $slot_id, $session_date, $capacity)) {
                $can_activate = false;
                break;
            }
        }
        if (!$can_activate) {
            $wpdb->update(
                $bookings_table,
                ['status' => 'paused', 'updated_at' => $now],
                ['id' => $booking_id],
                ['%s', '%s'],
                ['%d']
            );
            continue;
        }

        foreach ($sessions as $session) {
            $wpdb->insert(
                $sessions_table,
                [
                    'booking_id' => $booking_id,
                    'member_id' => (int) $booking->member_id,
                    'course_id' => (int) $booking->course_id,
                    'coach_id' => (int) $booking->coach_id,
                    'chapter' => $chapter,
                    'schedule_slot_id' => (int) $session['schedule_slot_id'],
                    'session_date' => (string) $session['session_date'],
                    'time_start' => (string) $session['time_start'],
                    'time_end' => (string) $session['time_end'],
                    'status' => 'scheduled',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        }

        $wpdb->update(
            $bookings_table,
            ['status' => 'active', 'updated_at' => $now],
            ['id' => $booking_id],
            ['%s', '%s'],
            ['%d']
        );
        sc_private_delete_pending_booking_payload($booking_id);
    }
}

function sc_private_update_session_status($session_id, $status, $restore_session = false) {
    global $wpdb;
    $session_id = absint($session_id);
    if (!$session_id) {
        return false;
    }
    $t = $wpdb->prefix . 'sc_private_booking_sessions';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $session_id));
    if (!$row) {
        return false;
    }
    $allowed = ['scheduled', 'cancelled', 'absent', 'excused', 'rescheduled', 'done'];
    if (!in_array($status, $allowed, true)) {
        return false;
    }
    if ($status === 'cancelled' && (string) $row->status !== 'scheduled') {
        return false;
    }
    $updated = $wpdb->update(
        $t,
        ['status' => $status, 'updated_at' => current_time('mysql')],
        ['id' => $session_id],
        ['%s', '%s'],
        ['%d']
    );
    if ($updated === false) {
        return false;
    }
    if ($restore_session && function_exists('sc_increase_member_session')) {
        sc_increase_member_session((int) $row->member_id, (int) $row->course_id);
    }
    return true;
}

function sc_private_sync_session_with_attendance($member_id, $course_id, $session_date, $attendance_status) {
    global $wpdb;
    $member_id = absint($member_id);
    $course_id = absint($course_id);
    $session_date = sanitize_text_field($session_date);
    $attendance_status = sanitize_text_field($attendance_status);
    if (!$member_id || !$course_id || $session_date === '') {
        return false;
    }
    $status_map = [
        'present' => 'done',
        'absent' => 'absent',
        'excused' => 'excused',
    ];
    if (!isset($status_map[$attendance_status])) {
        return false;
    }
    $target_status = $status_map[$attendance_status];
    $t = $wpdb->prefix . 'sc_private_booking_sessions';

    $session_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT id
         FROM {$t}
         WHERE member_id = %d
           AND course_id = %d
           AND session_date = %s
           AND status IN ('scheduled','rescheduled','absent','excused','done')
         ORDER BY time_start ASC, id ASC",
        $member_id,
        $course_id,
        $session_date
    ));
    if (empty($session_ids)) {
        return false;
    }
    $updated_any = false;
    foreach ($session_ids as $sid) {
        $sid = (int) $sid;
        if ($sid > 0 && sc_private_update_session_status($sid, $target_status, false)) {
            $updated_any = true;
        }
    }
    return $updated_any;
}

function sc_private_session_start_ts($session_row) {
    if (!is_object($session_row) || empty($session_row->session_date) || empty($session_row->time_start)) {
        return 0;
    }
    return strtotime((string) $session_row->session_date . ' ' . substr((string) $session_row->time_start, 0, 8));
}

add_action('template_redirect', 'sc_handle_private_session_user_actions');
function sc_handle_private_session_user_actions() {
    if (!is_user_logged_in() || empty($_POST['sc_private_session_action'])) {
        return;
    }
    if (!isset($_POST['sc_private_session_nonce']) || !wp_verify_nonce($_POST['sc_private_session_nonce'], 'sc_private_session_action')) {
        wc_add_notice('خطای امنیتی.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }
    $action_type = sanitize_text_field(wp_unslash($_POST['sc_private_session_action']));
    $session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
    $member_id = sc_private_get_member_id_for_current_user();
    if (!$member_id || !$session_id) {
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }
    global $wpdb;
    $t = $wpdb->prefix . 'sc_private_booking_sessions';
    $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d AND member_id = %d", $session_id, $member_id));
    if (!$session) {
        wc_add_notice('جلسه یافت نشد.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }
    $limits = sc_get_private_class_booking_limits();
    $now_ts = current_time('timestamp');
    $session_ts = sc_private_session_start_ts($session);
    $today_ymd = current_time('Y-m-d');
    if ((string) $session->session_date < $today_ymd || ($session_ts > 0 && $session_ts <= $now_ts)) {
        wc_add_notice('امکان لغو/تغییر وضعیت برای جلسه گذشته وجود ندارد.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }
    if ($action_type === 'cancel') {
        if ((string) $session->status !== 'scheduled') {
            wc_add_notice('فقط جلسات برنامه‌ریزی‌شده قابل لغو هستند.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
            exit;
        }
        $min_before = (int) $limits['cancel_minutes_before'];
        if ($min_before > 0 && $session_ts > 0 && (($session_ts - $now_ts) < ($min_before * 60))) {
            wc_add_notice('مهلت لغو این جلسه گذشته است.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
            exit;
        }
        sc_private_update_session_status($session_id, 'cancelled', true);
        sc_private_send_cancel_sms($session_id, 'user');
        wc_add_notice('جلسه با موفقیت لغو شد و یک جلسه به پکیج بازگشت.', 'success');
    } elseif ($action_type === 'excuse_absence') {
        wc_add_notice('مجاز کردن غیبت فقط توسط مربی یا مدیر انجام می‌شود.', 'error');
    }
    wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
    exit;
}

add_action('admin_post_sc_private_cancel_session', function () {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }
    check_admin_referer('sc_private_cancel_session');
    $session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
    sc_private_update_session_status($session_id, 'cancelled', true);
    sc_private_send_cancel_sms($session_id, 'coach_admin');
    wp_safe_redirect(wp_get_referer() ?: admin_url());
    exit;
});
