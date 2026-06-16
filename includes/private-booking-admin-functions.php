<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_get_private_booking_mode() {
    $mode = (string) sc_get_setting('private_booking_mode', 'direct_payment');
    return $mode === 'admin_approval' ? 'admin_approval' : 'direct_payment';
}

function sc_is_private_booking_admin_approval_mode() {
    return sc_get_private_booking_mode() === 'admin_approval';
}

function sc_get_private_booking_user_field_keys() {
    return ['course', 'chapter', 'coach', 'slots', 'sessions', 'start_date'];
}

function sc_get_private_booking_user_field_settings() {
    $keys = sc_get_private_booking_user_field_keys();
    if (!sc_is_private_booking_admin_approval_mode()) {
        return array_fill_keys($keys, 1);
    }
    $defaults = [
        'course' => 1,
        'chapter' => 1,
        'coach' => 1,
        'slots' => 0,
        'sessions' => 0,
        'start_date' => 0,
    ];
    $out = [];
    foreach ($defaults as $key => $default) {
        $out[$key] = (int) sc_get_setting('private_booking_user_show_' . $key, (string) $default) === 1 ? 1 : 0;
    }
    return $out;
}

function sc_private_booking_status_label($status) {
    $map = [
        'pending_admin' => 'در انتظار بررسی مدیر',
        'pending_payment' => 'منتظر پرداخت',
        'active' => 'فعال',
        'paused' => 'متوقف (ظرفیت)',
        'rejected' => 'رد شده',
        'cancelled' => 'لغو شده',
        'completed' => 'تکمیل‌شده',
    ];
    return isset($map[$status]) ? $map[$status] : (function_exists('sc_private_session_status_label') ? sc_private_session_status_label($status) : $status);
}

function sc_private_booking_status_badge_class($status) {
    $key = preg_replace('/[^a-z_]/', '', strtolower((string) $status));
    return 'sc-pb-status sc-pb-status-' . ($key !== '' ? $key : 'unknown');
}

function sc_private_booking_pending_placeholder_date() {
    return '1970-01-01';
}

function sc_private_can_manage_booking_requests() {
    return current_user_can('manage_options') || current_user_can('club_coach');
}

function sc_private_get_booking_row($booking_id) {
    global $wpdb;
    $booking_id = absint($booking_id);
    if (!$booking_id) {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_private_course_bookings WHERE id = %d LIMIT 1",
        $booking_id
    ));
}

function sc_private_parse_booking_form_input($source = 'POST') {
    $raw = ($source === 'POST') ? $_POST : $_GET;
    $schedule_ids = isset($raw['schedule_slot_ids']) && is_array($raw['schedule_slot_ids'])
        ? array_values(array_filter(array_map('absint', $raw['schedule_slot_ids'])))
        : [];
    $start_date = current_time('Y-m-d');
    if (!empty($raw['start_date_shamsi'])) {
        $start_date_shamsi = sanitize_text_field(wp_unslash($raw['start_date_shamsi']));
        if (function_exists('sc_shamsi_to_gregorian_date')) {
            $maybe = sc_shamsi_to_gregorian_date($start_date_shamsi);
            if (!empty($maybe)) {
                $start_date = $maybe;
            }
        }
    } elseif (!empty($raw['start_date'])) {
        $start_date = sanitize_text_field(wp_unslash($raw['start_date']));
    }

    return [
        'member_id' => isset($raw['member_id']) ? absint($raw['member_id']) : 0,
        'course_id' => isset($raw['course_id']) ? absint($raw['course_id']) : 0,
        'chapter' => isset($raw['chapter']) ? sanitize_text_field(wp_unslash($raw['chapter'])) : '',
        'coach_id' => isset($raw['coach_id']) ? absint($raw['coach_id']) : 0,
        'schedule_ids' => $schedule_ids,
        'start_date' => $start_date,
        'enrollment_sessions' => isset($raw['enrollment_sessions']) ? absint($raw['enrollment_sessions']) : 0,
    ];
}

function sc_private_validate_booking_input(array $input, $require_all = true, array $visible_fields = null) {
    global $wpdb;
    $errors = [];
    $visible = is_array($visible_fields) ? $visible_fields : sc_get_private_booking_user_field_settings();

    $need = static function ($field) use ($require_all, $visible) {
        if ($require_all) {
            return true;
        }
        return !empty($visible[$field]);
    };

    if ($require_all || $need('course')) {
        if (empty($input['course_id'])) {
            $errors[] = 'دوره را انتخاب کنید.';
        }
    }
    if ($require_all || $need('chapter')) {
        if ($input['chapter'] === '' && ($require_all || $need('chapter'))) {
            $errors[] = 'شعبه را انتخاب کنید.';
        }
    }
    if ($require_all || $need('coach')) {
        if (empty($input['coach_id'])) {
            $errors[] = 'مربی را انتخاب کنید.';
        }
    }
    if ($require_all || $need('slots')) {
        if (empty($input['schedule_ids'])) {
            $errors[] = 'حداقل یک اسلات زمانی انتخاب کنید.';
        }
    }
    if ($require_all || $need('sessions')) {
        if (empty($input['enrollment_sessions'])) {
            $errors[] = 'تعداد جلسات را انتخاب کنید.';
        }
    }

    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors, 'course' => null, 'sessions' => []];
    }

    if (empty($input['course_id'])) {
        return ['valid' => true, 'errors' => [], 'course' => null, 'sessions' => [], 'partial' => true];
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$courses_table} WHERE id = %d AND deleted_at IS NULL AND is_active = 1 AND course_type = 'private'",
        (int) $input['course_id']
    ));
    if (!$course) {
        return ['valid' => false, 'errors' => ['دوره خصوصی معتبر نیست.'], 'course' => null, 'sessions' => []];
    }

    if ($input['chapter'] !== '') {
        $chapters = function_exists('sc_get_course_chapters') ? sc_get_course_chapters((int) $input['course_id']) : [];
        if (!empty($chapters) && !in_array($input['chapter'], $chapters, true)) {
            return ['valid' => false, 'errors' => ['شعبه انتخاب شده معتبر نیست.'], 'course' => $course, 'sessions' => []];
        }
    }

    if (!empty($input['coach_id']) && $input['chapter'] !== '') {
        if (!function_exists('sc_is_valid_course_chapter_coach') || !sc_is_valid_course_chapter_coach((int) $input['course_id'], $input['chapter'], (int) $input['coach_id'])) {
            return ['valid' => false, 'errors' => ['مربی انتخاب شده برای این شعبه مجاز نیست.'], 'course' => $course, 'sessions' => []];
        }
    }

    if (!empty($input['enrollment_sessions'])) {
        $session_options = function_exists('sc_get_course_private_session_count_options')
            ? sc_get_course_private_session_count_options((int) $input['course_id'])
            : [];
        if (!empty($session_options) && !in_array((int) $input['enrollment_sessions'], $session_options, true)) {
            return ['valid' => false, 'errors' => ['تعداد جلسات انتخابی معتبر نیست.'], 'course' => $course, 'sessions' => []];
        }
    }

    $sessions = [];
    if ($require_all && !empty($input['schedule_ids']) && !empty($input['enrollment_sessions'])) {
        $sch_table = $wpdb->prefix . 'sc_course_weekly_schedule';
        $ph = implode(',', array_fill(0, count($input['schedule_ids']), '%d'));
        $qv = array_merge([(int) $input['course_id']], $input['schedule_ids']);
        $schedule_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$sch_table} WHERE course_id = %d AND id IN ($ph) ORDER BY weekday ASC, time_start ASC",
            $qv
        ));
        if (count($schedule_rows) !== count($input['schedule_ids'])) {
            return ['valid' => false, 'errors' => ['اسلات زمانی انتخابی معتبر نیست.'], 'course' => $course, 'sessions' => []];
        }
        $filtered_rows = [];
        foreach ($schedule_rows as $row) {
            if (!sc_private_schedule_row_matches($row, $input['chapter'], (int) $input['coach_id'])) {
                return ['valid' => false, 'errors' => ['اسلات انتخابی با شعبه/مربی همخوانی ندارد.'], 'course' => $course, 'sessions' => []];
            }
            $filtered_rows[] = $row;
        }

        $invoice_amount = function_exists('sc_calculate_private_class_invoice_amount')
            ? sc_calculate_private_class_invoice_amount($course, (int) $input['enrollment_sessions'], $input['chapter'], (int) $input['coach_id'])
            : 0.0;
        if ($invoice_amount <= 0) {
            return ['valid' => false, 'errors' => ['قیمت این انتخاب تعریف نشده است.'], 'course' => $course, 'sessions' => []];
        }

        $sessions = sc_private_generate_sessions($filtered_rows, $input['start_date'], (int) $input['enrollment_sessions']);
        if (count($sessions) < (int) $input['enrollment_sessions']) {
            return ['valid' => false, 'errors' => ['با برنامه هفتگی انتخابی، تعداد جلسه کافی تولید نشد.'], 'course' => $course, 'sessions' => []];
        }

        $capacity = sc_private_get_branch_capacity((int) $input['course_id'], $input['chapter'], (int) $input['coach_id'], $course);
        foreach ($sessions as $session) {
            if (!sc_private_can_reserve_slot((int) $input['coach_id'], (int) $session['schedule_slot_id'], (string) $session['session_date'], $capacity)) {
                return ['valid' => false, 'errors' => ['بخشی از زمان‌های انتخابی تکمیل ظرفیت شده است.'], 'course' => $course, 'sessions' => []];
            }
        }
    }

    return ['valid' => true, 'errors' => [], 'course' => $course, 'sessions' => $sessions];
}

function sc_private_upsert_member_course($member_id, $course_id, $chapter, $coach_id, $enrollment_sessions) {
    global $wpdb;
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $chapter = function_exists('sc_normalize_member_course_chapter')
        ? sc_normalize_member_course_chapter($chapter)
        : trim((string) $chapter);
    $coach_id = (int) $coach_id;

    $blocking = function_exists('sc_find_blocking_member_course_enrollment')
        ? sc_find_blocking_member_course_enrollment($member_id, $course_id, $chapter, $coach_id)
        : null;
    if ($blocking) {
        $member_course_id = (int) $blocking->id;
        $wpdb->update(
            $member_courses_table,
            [
                'status' => 'inactive',
                'chapter' => $chapter !== '' ? $chapter : null,
                'coach_id' => $coach_id,
                'enrollment_sessions' => $enrollment_sessions,
                'total_sessions' => $enrollment_sessions,
                'remaining_sessions' => $enrollment_sessions,
                'course_status_flags' => '',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $member_course_id],
            ['%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s'],
            ['%d']
        );
        return $member_course_id;
    }

    $now = current_time('mysql');
    $wpdb->insert(
        $member_courses_table,
        [
            'member_id' => (int) $member_id,
            'course_id' => (int) $course_id,
            'chapter' => $chapter !== '' ? $chapter : null,
            'coach_id' => $coach_id,
            'enrollment_date' => null,
            'total_sessions' => $enrollment_sessions,
            'remaining_sessions' => $enrollment_sessions,
            'enrollment_sessions' => $enrollment_sessions,
            'status' => 'inactive',
            'course_status_flags' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%d', '%d', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
    );
    return (int) $wpdb->insert_id;
}

function sc_private_finalize_booking_with_invoice(array $input, array $options = []) {
    global $wpdb;

    $member_id = (int) ($input['member_id'] ?? 0);
    $booking_id = isset($options['booking_id']) ? absint($options['booking_id']) : 0;
    $booking_source = isset($options['booking_source']) ? sanitize_text_field($options['booking_source']) : 'user_direct';
    $allowed_sources = ['user_direct', 'user_request', 'admin'];
    if (!in_array($booking_source, $allowed_sources, true)) {
        $booking_source = 'user_direct';
    }

    $validation = sc_private_validate_booking_input($input, true);
    if (!$validation['valid']) {
        return ['success' => false, 'message' => implode(' ', $validation['errors'])];
    }

    /** @var object $course */
    $course = $validation['course'];
    $sessions = $validation['sessions'];
    $course_id = (int) $input['course_id'];
    $chapter = (string) $input['chapter'];
    $coach_id = (int) $input['coach_id'];
    $enrollment_sessions = (int) $input['enrollment_sessions'];
    $start_date = (string) $input['start_date'];

    if (function_exists('sc_check_and_create_tables')) {
        sc_check_and_create_tables();
    }

    $member_course_id = sc_private_upsert_member_course($member_id, $course_id, $chapter, $coach_id, $enrollment_sessions);
    if (!$member_course_id) {
        return ['success' => false, 'message' => 'خطا در ذخیره ثبت‌نام کلاس خصوصی.'];
    }

    $bookings_table = $wpdb->prefix . 'sc_private_course_bookings';
    $now = current_time('mysql');
    $end_date = end($sessions);
    $end_date = is_array($end_date) && isset($end_date['session_date']) ? $end_date['session_date'] : $start_date;

    $invoice_amount = function_exists('sc_calculate_private_class_invoice_amount')
        ? sc_calculate_private_class_invoice_amount($course, $enrollment_sessions, $chapter, $coach_id)
        : 0.0;

    if ($booking_id > 0) {
        $existing = sc_private_get_booking_row($booking_id);
        if (!$existing || (int) $existing->member_id !== $member_id) {
            return ['success' => false, 'message' => 'رزرو یافت نشد.'];
        }
        if (!in_array((string) $existing->status, ['pending_admin'], true)) {
            return ['success' => false, 'message' => 'این رزرو قابل تکمیل نیست.'];
        }
        $wpdb->update(
            $bookings_table,
            [
                'course_id' => $course_id,
                'coach_id' => $coach_id,
                'chapter' => $chapter,
                'member_course_id' => $member_course_id,
                'package_sessions' => $enrollment_sessions,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => 'pending_payment',
                'booking_source' => $booking_source,
                'updated_at' => $now,
            ],
            ['id' => $booking_id],
            ['%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    } else {
        $inserted = $wpdb->insert(
            $bookings_table,
            [
                'member_id' => $member_id,
                'course_id' => $course_id,
                'coach_id' => $coach_id,
                'chapter' => $chapter,
                'member_course_id' => $member_course_id,
                'package_sessions' => $enrollment_sessions,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => 'pending_payment',
                'booking_source' => $booking_source,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        $booking_id = (int) $wpdb->insert_id;
        if (!$inserted || !$booking_id) {
            return ['success' => false, 'message' => 'خطا در ایجاد رزرو خصوصی.'];
        }
    }

    $fee_label = function_exists('sc_course_enrollment_fee_label')
        ? sc_course_enrollment_fee_label($course->title, $enrollment_sessions)
        : ('ثبت نام کلاس خصوصی: ' . $course->title);
    $invoice_result = function_exists('sc_create_course_invoice')
        ? sc_create_course_invoice($member_id, $course_id, $member_course_id, $invoice_amount, '', $fee_label, null)
        : ['success' => false, 'message' => 'تابع صورت حساب در دسترس نیست'];

    if (empty($invoice_result['success'])) {
        if ($booking_id && empty($options['booking_id'])) {
            $wpdb->delete($bookings_table, ['id' => $booking_id], ['%d']);
        }
        return ['success' => false, 'message' => !empty($invoice_result['message']) ? $invoice_result['message'] : 'ایجاد صورت‌حساب با خطا مواجه شد.'];
    }

    if (!empty($invoice_result['invoice_id'])) {
        $wpdb->update(
            $bookings_table,
            ['invoice_id' => (int) $invoice_result['invoice_id'], 'updated_at' => $now],
            ['id' => $booking_id],
            ['%d', '%s'],
            ['%d']
        );
    }

    sc_private_store_pending_booking_payload($booking_id, [
        'member_id' => $member_id,
        'course_id' => $course_id,
        'coach_id' => $coach_id,
        'chapter' => $chapter,
        'sessions' => $sessions,
    ]);

    return [
        'success' => true,
        'booking_id' => $booking_id,
        'invoice_id' => !empty($invoice_result['invoice_id']) ? (int) $invoice_result['invoice_id'] : 0,
        'message' => 'رزرو ثبت شد. برای فعال‌سازی جلسات، صورت‌حساب باید پرداخت شود.',
    ];
}

function sc_private_create_booking_request($member_id, array $input) {
    global $wpdb;

    $visible = sc_get_private_booking_user_field_settings();
    $validation = sc_private_validate_booking_input($input, false, $visible);
    if (!$validation['valid']) {
        return ['success' => false, 'message' => implode(' ', $validation['errors'])];
    }

    $has_any = !empty($input['course_id']) || $input['chapter'] !== '' || !empty($input['coach_id'])
        || !empty($input['schedule_ids']) || !empty($input['enrollment_sessions']);
    if (!$has_any) {
        return ['success' => false, 'message' => 'حداقل یک مورد را انتخاب کنید.'];
    }

    if (function_exists('sc_check_and_create_tables')) {
        sc_check_and_create_tables();
    }

    $bookings_table = $wpdb->prefix . 'sc_private_course_bookings';
    $now = current_time('mysql');
    $placeholder = sc_private_booking_pending_placeholder_date();
    $member_course_id = 0;

    if (!empty($input['course_id'])) {
        $member_course_id = sc_private_upsert_member_course(
            (int) $member_id,
            (int) $input['course_id'],
            (string) $input['chapter'],
            (int) $input['coach_id'],
            max(0, (int) $input['enrollment_sessions'])
        );
    }

    $inserted = $wpdb->insert(
        $bookings_table,
        [
            'member_id' => (int) $member_id,
            'course_id' => (int) $input['course_id'],
            'coach_id' => (int) $input['coach_id'],
            'chapter' => (string) $input['chapter'],
            'member_course_id' => $member_course_id,
            'package_sessions' => max(0, (int) $input['enrollment_sessions']),
            'start_date' => !empty($input['start_date']) && !empty($visible['start_date']) ? (string) $input['start_date'] : $placeholder,
            'end_date' => $placeholder,
            'status' => 'pending_admin',
            'booking_source' => 'user_request',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
    );
    $booking_id = (int) $wpdb->insert_id;
    if (!$inserted || !$booking_id) {
        return ['success' => false, 'message' => 'خطا در ثبت درخواست.'];
    }

    if (!empty($input['schedule_ids']) && !empty($visible['slots'])) {
        sc_private_store_pending_booking_payload($booking_id, [
            'member_id' => (int) $member_id,
            'course_id' => (int) $input['course_id'],
            'coach_id' => (int) $input['coach_id'],
            'chapter' => (string) $input['chapter'],
            'schedule_slot_ids' => $input['schedule_ids'],
            'enrollment_sessions' => (int) $input['enrollment_sessions'],
            'start_date' => (string) $input['start_date'],
            'partial_request' => 1,
        ]);
    }

    sc_private_send_booking_event_notification('request_to_admin', $booking_id);

    return [
        'success' => true,
        'booking_id' => $booking_id,
        'message' => 'درخواست رزرو ثبت شد و در انتظار بررسی مدیر است.',
    ];
}

function sc_private_reject_booking_request($booking_id, $reason = '') {
    global $wpdb;
    $booking_id = absint($booking_id);
    $booking = sc_private_get_booking_row($booking_id);
    if (!$booking || (string) $booking->status !== 'pending_admin') {
        return ['success' => false, 'message' => 'درخواست قابل رد نیست.'];
    }

    $wpdb->update(
        $wpdb->prefix . 'sc_private_course_bookings',
        [
            'status' => 'rejected',
            'rejected_reason' => sanitize_text_field((string) $reason),
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $booking_id],
        ['%s', '%s', '%s'],
        ['%d']
    );
    sc_private_delete_pending_booking_payload($booking_id);
    sc_private_send_booking_event_notification('rejected_to_user', $booking_id, ['reason' => $reason]);

    return ['success' => true, 'message' => 'درخواست رد شد.'];
}

function sc_private_get_booking_notification_context($booking_id) {
    global $wpdb;
    $booking = sc_private_get_booking_row($booking_id);
    if (!$booking) {
        return [];
    }
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $courses_table = $wpdb->prefix . 'sc_courses';

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, m.first_name AS member_first_name, m.last_name AS member_last_name, m.player_phone,
                c.title AS course_title,
                co.first_name AS coach_first_name, co.last_name AS coach_last_name
         FROM {$wpdb->prefix}sc_private_course_bookings b
         LEFT JOIN {$members_table} m ON m.id = b.member_id
         LEFT JOIN {$courses_table} c ON c.id = b.course_id
         LEFT JOIN {$coaches_table} co ON co.id = b.coach_id
         WHERE b.id = %d
         LIMIT 1",
        (int) $booking_id
    ));
    if (!$row) {
        return [];
    }

    $member_name = trim((string) $row->member_first_name . ' ' . (string) $row->member_last_name);
    $coach_name = trim((string) $row->coach_first_name . ' ' . (string) $row->coach_last_name);

    return [
        'booking_id' => (int) $booking_id,
        'member_id' => (int) $row->member_id,
        'member_name' => $member_name,
        'user_name' => $member_name,
        'coach_name' => $coach_name !== '' ? $coach_name : '-',
        'item_name' => (string) ($row->course_title ?: '-'),
        'course_title' => (string) ($row->course_title ?: '-'),
        'chapter' => (string) $row->chapter,
        'invoice_id' => (int) ($row->invoice_id ?? 0),
        'status_label' => sc_private_booking_status_label((string) $row->status),
        'player_phone' => (string) ($row->player_phone ?? ''),
    ];
}

function sc_private_load_bale_api_if_needed() {
    if (function_exists('bale_send_message') && function_exists('sc_get_user_bale_chat_id')) {
        return;
    }
    $api_file = SC_PLUGIN_DIR . 'bot/includes/api.php';
    if (file_exists($api_file)) {
        require_once $api_file;
    }
}

function sc_private_send_booking_event_notification($event, $booking_id, array $extra = []) {
    $ctx = sc_private_get_booking_notification_context($booking_id);
    if (empty($ctx)) {
        return;
    }
    $ctx = array_merge($ctx, $extra);

    $setting_map = [
        'request_to_admin' => [
            'enabled' => 'private_class_sms_request_to_admin_enabled',
            'template' => 'private_class_sms_request_to_admin_template',
            'pattern' => 'private_class_sms_request_to_admin_pattern',
            'target' => 'admin',
            'bale' => 'private_class_bale_request_to_admin_enabled',
        ],
        'approved_to_user' => [
            'enabled' => 'private_class_sms_approved_to_user_enabled',
            'template' => 'private_class_sms_approved_to_user_template',
            'pattern' => 'private_class_sms_approved_to_user_pattern',
            'target' => 'user',
            'bale' => 'private_class_bale_approved_to_user_enabled',
        ],
        'rejected_to_user' => [
            'enabled' => 'private_class_sms_rejected_to_user_enabled',
            'template' => 'private_class_sms_rejected_to_user_template',
            'pattern' => 'private_class_sms_rejected_to_user_pattern',
            'target' => 'user',
            'bale' => 'private_class_bale_rejected_to_user_enabled',
        ],
        'activated_to_user' => [
            'enabled' => 'private_class_sms_activated_to_user_enabled',
            'template' => 'private_class_sms_activated_to_user_template',
            'pattern' => 'private_class_sms_activated_to_user_pattern',
            'target' => 'user',
            'bale' => 'private_class_bale_activated_to_user_enabled',
        ],
    ];

    if (!isset($setting_map[$event])) {
        return;
    }
    $cfg = $setting_map[$event];

    if ((int) sc_get_setting($cfg['enabled'], '0') === 1 && function_exists('sc_send_sms')) {
        $template = (string) sc_get_setting($cfg['template'], '');
        if ($template === '') {
            $template = '%user_name% درخواست رزرو کلاس خصوصی دوره %item_name% ثبت کرد.';
        }
        $variables = [
            'user_name' => $ctx['user_name'],
            'coach_name' => $ctx['coach_name'],
            'item_name' => $ctx['item_name'],
            'booking_id' => (string) $ctx['booking_id'],
            'invoice_id' => (string) $ctx['invoice_id'],
            'status' => $ctx['status_label'],
            'chapter' => $ctx['chapter'],
            'reason' => isset($ctx['reason']) ? (string) $ctx['reason'] : '',
        ];
        $message = function_exists('sc_replace_sms_variables') ? sc_replace_sms_variables($template, $variables) : $template;
        $pattern_code = (int) sc_get_setting($cfg['pattern'], '0');

        if ($cfg['target'] === 'admin') {
            $admin_phone = (string) sc_get_setting('sms_admin_phone', '');
            if ($admin_phone !== '') {
                sc_send_sms($admin_phone, $message, $pattern_code > 0, $pattern_code > 0 ? $pattern_code : null, $variables, 'private_request_to_admin');
            }
        } else {
            if ($ctx['player_phone'] !== '') {
                sc_send_sms($ctx['player_phone'], $message, $pattern_code > 0, $pattern_code > 0 ? $pattern_code : null, $variables, 'private_' . $event);
            }
        }
    }

    if ((int) sc_get_setting($cfg['bale'], '1') === 1) {
        sc_private_load_bale_api_if_needed();
        $bale_text = sprintf(
            "رزرو کلاس خصوصی\nبازیکن: %s\nدوره: %s\nمربی: %s\nوضعیت: %s",
            $ctx['user_name'],
            $ctx['item_name'],
            $ctx['coach_name'],
            $ctx['status_label']
        );
        if ($event === 'approved_to_user' && !empty($ctx['invoice_id'])) {
            $bale_text .= "\nصورت‌حساب: #" . (int) $ctx['invoice_id'];
        }
        if ($event === 'rejected_to_user' && !empty($ctx['reason'])) {
            $bale_text .= "\nدلیل: " . $ctx['reason'];
        }

        if ($cfg['target'] === 'user' && function_exists('sc_get_user_bale_chat_id') && function_exists('bale_send_message')) {
            $chat_id = sc_get_user_bale_chat_id((int) $ctx['member_id']);
            if ($chat_id) {
                bale_send_message($chat_id, $bale_text);
            }
        }
    }
}

add_action('sc_invoice_paid', 'sc_private_notify_after_activation', 25, 1);
function sc_private_notify_after_activation($invoice_id) {
    global $wpdb;
    $invoice_id = absint($invoice_id);
    if (!$invoice_id) {
        return;
    }
    $bookings = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}sc_private_course_bookings WHERE invoice_id = %d AND status = %s",
        $invoice_id,
        'active'
    ));
    foreach ((array) $bookings as $booking_id) {
        sc_private_send_booking_event_notification('activated_to_user', (int) $booking_id);
    }
}

add_action('admin_init', 'sc_private_handle_admin_booking_actions');
function sc_private_handle_admin_booking_actions() {
    if (!is_admin() || !sc_private_can_manage_booking_requests()) {
        return;
    }

    if (isset($_POST['sc_admin_finalize_private_booking'])) {
        if (!isset($_POST['sc_admin_private_booking_nonce']) || !wp_verify_nonce($_POST['sc_admin_private_booking_nonce'], 'sc_admin_private_booking')) {
            wp_die('خطای امنیتی.');
        }
        $input = sc_private_parse_booking_form_input('POST');
        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $booking_source = $booking_id > 0 ? 'user_request' : 'admin';

        $existing = $booking_id > 0 ? sc_private_get_booking_row($booking_id) : null;
        if ($existing) {
            $input['member_id'] = (int) $existing->member_id;
            if ((string) $existing->booking_source === 'user_request') {
                $booking_source = 'user_request';
            }
        }

        if ($input['member_id'] <= 0) {
            wp_safe_redirect(add_query_arg(['page' => 'sc-private-booking-form', 'error' => urlencode('بازیکن را انتخاب کنید.')], admin_url('admin.php')));
            exit;
        }

        $result = sc_private_finalize_booking_with_invoice($input, [
            'booking_id' => $booking_id,
            'booking_source' => $booking_source,
        ]);

        if (!empty($result['success'])) {
            sc_private_send_booking_event_notification('approved_to_user', (int) $result['booking_id']);
            $redirect = add_query_arg([
                'page' => 'sc-private-booking-requests',
                'updated' => 1,
                'booking_id' => (int) $result['booking_id'],
            ], admin_url('admin.php'));
            wp_safe_redirect($redirect);
            exit;
        }

        $redirect_page = $booking_id > 0 ? 'sc-private-booking-form' : 'sc-private-booking-form';
        wp_safe_redirect(add_query_arg([
            'page' => $redirect_page,
            'booking_id' => $booking_id,
            'error' => urlencode($result['message'] ?? 'خطا'),
        ], admin_url('admin.php')));
        exit;
    }

    if (isset($_POST['sc_reject_private_booking']) && isset($_POST['booking_id'])) {
        if (!isset($_POST['sc_admin_private_booking_nonce']) || !wp_verify_nonce($_POST['sc_admin_private_booking_nonce'], 'sc_admin_private_booking')) {
            wp_die('خطای امنیتی.');
        }
        $booking_id = absint($_POST['booking_id']);
        $reason = isset($_POST['rejected_reason']) ? sanitize_text_field(wp_unslash($_POST['rejected_reason'])) : '';
        sc_private_reject_booking_request($booking_id, $reason);
        wp_safe_redirect(add_query_arg(['page' => 'sc-private-booking-requests', 'rejected' => 1], admin_url('admin.php')));
        exit;
    }
}

add_action('template_redirect', 'sc_handle_private_class_booking_request', 9);
function sc_handle_private_class_booking_request() {
    if (!is_user_logged_in() || !isset($_POST['sc_book_private_class_request'])) {
        return;
    }
    if (!sc_is_private_booking_admin_approval_mode()) {
        return;
    }
    if (!isset($_POST['sc_private_class_nonce']) || !wp_verify_nonce($_POST['sc_private_class_nonce'], 'sc_book_private_class')) {
        wc_add_notice('خطای امنیتی. لطفا دوباره تلاش کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }
    $member_id = sc_private_get_member_id_for_current_user();
    if (!$member_id) {
        wc_add_notice('ابتدا اطلاعات بازیکن را تکمیل کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }

    $input = sc_private_parse_booking_form_input('POST');
    $input['member_id'] = $member_id;
    $result = sc_private_create_booking_request($member_id, $input);
    if (!empty($result['success'])) {
        wc_add_notice($result['message'], 'success');
    } else {
        wc_add_notice($result['message'] ?? 'خطا در ثبت درخواست.', 'error');
    }
    wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
    exit;
}

function sc_render_private_booking_requests_page() {
    if (!sc_private_can_manage_booking_requests()) {
        wp_die('دسترسی غیرمجاز.');
    }
    if (!sc_is_private_booking_admin_approval_mode()) {
        echo '<div class="wrap"><h1>رزرو کلاس خصوصی</h1><div class="notice notice-warning"><p>این بخش فقط در حالت «رزرو با تایید مدیر» فعال است. از تنظیمات باشگاه → تب کلاس‌ها، حالت رزرو را تغییر دهید.</p></div></div>';
        return;
    }
    include SC_TEMPLATES_ADMIN_DIR . 'private-booking-requests-list.php';
}

function sc_render_private_booking_form_page() {
    if (!sc_private_can_manage_booking_requests()) {
        wp_die('دسترسی غیرمجاز.');
    }
    if (!sc_is_private_booking_admin_approval_mode()) {
        echo '<div class="wrap"><h1>ثبت‌نام کلاس خصوصی</h1><div class="notice notice-warning"><p>این بخش فقط در حالت «رزرو با تایید مدیر» فعال است.</p></div></div>';
        return;
    }
    include SC_TEMPLATES_ADMIN_DIR . 'private-booking-admin-form.php';
}
