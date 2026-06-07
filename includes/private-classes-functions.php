<?php
if (!defined('ABSPATH')) {
    exit;
}

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

function sc_get_private_course_coaches($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id) {
        return [];
    }
    $course_coaches = $wpdb->prefix . 'sc_course_coaches';
    $coaches = $wpdb->prefix . 'sc_coaches';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT c.id, c.first_name, c.last_name
         FROM {$course_coaches} cc
         INNER JOIN {$coaches} c ON c.id = cc.coach_id
         WHERE cc.course_id = %d
           AND c.is_active = 1
           AND (c.is_private_enabled IS NULL OR c.is_private_enabled = 1)
         ORDER BY c.first_name, c.last_name",
        $course_id
    ));
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
        $ymd = gmdate('Y-m-d', $cursor_ts);
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
        if (function_exists('sc_bale_notify_user') && !empty($row->member_id)) {
            sc_bale_notify_user((int) $row->member_id, (string) $row->player_phone, $message);
        }
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
    include SC_TEMPLATES_PUBLIC_DIR . 'private-classes.php';
}

add_action('template_redirect', 'sc_handle_private_class_booking');
function sc_handle_private_class_booking() {
    if (!is_user_logged_in() || !isset($_POST['sc_book_private_class'])) {
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
    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $coach_id = isset($_POST['coach_id']) ? absint($_POST['coach_id']) : 0;
    $schedule_ids = isset($_POST['schedule_slot_ids']) && is_array($_POST['schedule_slot_ids']) ? array_values(array_filter(array_map('absint', $_POST['schedule_slot_ids']))) : [];
    $start_date = current_time('Y-m-d');
    if (!empty($_POST['start_date_shamsi'])) {
        $start_date_shamsi = sanitize_text_field(wp_unslash($_POST['start_date_shamsi']));
        if (function_exists('sc_shamsi_to_gregorian_date')) {
            $maybe_gregorian = sc_shamsi_to_gregorian_date($start_date_shamsi);
            if (!empty($maybe_gregorian)) {
                $start_date = $maybe_gregorian;
            }
        }
    } elseif (!empty($_POST['start_date'])) {
        $start_date = sanitize_text_field(wp_unslash($_POST['start_date']));
    }
    $enrollment_sessions = isset($_POST['enrollment_sessions']) ? absint($_POST['enrollment_sessions']) : 0;
    if (!$course_id || !$coach_id || empty($schedule_ids) || $enrollment_sessions <= 0) {
        wc_add_notice('اطلاعات رزرو کامل نیست.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }

    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$courses_table} WHERE id = %d AND deleted_at IS NULL AND is_active = 1 AND course_type = 'private'",
        $course_id
    ));
    if (!$course) {
        wc_add_notice('دوره خصوصی معتبر نیست.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }
    $valid_coaches = sc_get_private_course_coaches($course_id);
    $valid_coach_ids = array_map('intval', wp_list_pluck($valid_coaches, 'id'));
    if (!in_array($coach_id, $valid_coach_ids, true)) {
        wc_add_notice('مربی انتخاب شده برای این دوره مجاز نیست.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }

    $sch_table = $wpdb->prefix . 'sc_course_weekly_schedule';
    $ph = implode(',', array_fill(0, count($schedule_ids), '%d'));
    $qv = array_merge([$course_id], $schedule_ids);
    $schedule_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$sch_table} WHERE course_id = %d AND id IN ($ph) ORDER BY weekday ASC, time_start ASC",
        $qv
    ));
    if (count($schedule_rows) !== count($schedule_ids)) {
        wc_add_notice('اسلات زمانی انتخابی معتبر نیست.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }

    if (function_exists('sc_course_has_packages') && sc_course_has_packages($course_id)) {
        $pkg = function_exists('sc_get_course_package_by_sessions') ? sc_get_course_package_by_sessions($course_id, $enrollment_sessions) : null;
        if (!$pkg) {
            wc_add_notice('پکیج انتخابی معتبر نیست.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
            exit;
        }
        $invoice_amount = (float) $pkg->price;
    } else {
        wc_add_notice('برای کلاس خصوصی باید پکیج جلسه تعریف شده باشد.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }

    $sessions = sc_private_generate_sessions($schedule_rows, $start_date, $enrollment_sessions);
    if (count($sessions) < $enrollment_sessions) {
        wc_add_notice('با برنامه هفتگی انتخابی، تعداد جلسه کافی برای کل بازه پکیج تولید نشد.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }
    $capacity = !empty($course->capacity) ? (int) $course->capacity : 1;
    foreach ($sessions as $session) {
        if (!sc_private_can_reserve_slot($coach_id, (int) $session['schedule_slot_id'], (string) $session['session_date'], $capacity)) {
            wc_add_notice('بخشی از زمان‌های انتخابی تکمیل ظرفیت شده است. لطفا اسلات دیگری انتخاب کنید.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
            exit;
        }
    }

    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$member_courses_table} WHERE member_id = %d AND course_id = %d LIMIT 1",
        $member_id,
        $course_id
    ));
    $now = current_time('mysql');
    $member_course_id = 0;
    if ($existing) {
        $member_course_id = (int) $existing->id;
        $wpdb->update(
            $member_courses_table,
            [
                'status' => 'inactive',
                'enrollment_sessions' => $enrollment_sessions,
                'updated_at' => $now,
            ],
            ['id' => $member_course_id],
            ['%s', '%d', '%s'],
            ['%d']
        );
    } else {
        $wpdb->insert(
            $member_courses_table,
            [
                'member_id' => $member_id,
                'course_id' => $course_id,
                'enrollment_date' => null,
                'total_sessions' => $enrollment_sessions,
                'remaining_sessions' => $enrollment_sessions,
                'enrollment_sessions' => $enrollment_sessions,
                'status' => 'inactive',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s']
        );
        $member_course_id = (int) $wpdb->insert_id;
    }
    if (!$member_course_id) {
        wc_add_notice('خطا در ذخیره ثبت‌نام کلاس خصوصی.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }

    $bookings_table = $wpdb->prefix . 'sc_private_course_bookings';
    $end_date = end($sessions);
    $end_date = is_array($end_date) && isset($end_date['session_date']) ? $end_date['session_date'] : $start_date;

    $wpdb->insert(
        $bookings_table,
        [
            'member_id' => $member_id,
            'course_id' => $course_id,
            'coach_id' => $coach_id,
            'member_course_id' => $member_course_id,
            'package_sessions' => $enrollment_sessions,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => 'pending_payment',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
    );
    $booking_id = (int) $wpdb->insert_id;
    if (!$booking_id) {
        wc_add_notice('خطا در ایجاد رزرو خصوصی.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-private-classes'));
        exit;
    }
    $fee_label = function_exists('sc_course_enrollment_fee_label')
        ? sc_course_enrollment_fee_label($course->title, $enrollment_sessions)
        : ('ثبت نام کلاس خصوصی: ' . $course->title);
    $invoice_result = function_exists('sc_create_course_invoice')
        ? sc_create_course_invoice($member_id, $course_id, $member_course_id, $invoice_amount, '', $fee_label, null)
        : ['success' => false, 'message' => 'تابع صورت حساب در دسترس نیست'];
    if (!empty($invoice_result['success'])) {
        if (!empty($invoice_result['invoice_id'])) {
            $wpdb->update($bookings_table, ['invoice_id' => (int) $invoice_result['invoice_id'], 'updated_at' => $now], ['id' => $booking_id], ['%d', '%s'], ['%d']);
        }
        sc_private_store_pending_booking_payload($booking_id, [
            'member_id' => $member_id,
            'course_id' => $course_id,
            'coach_id' => $coach_id,
            'sessions' => $sessions,
        ]);
        wc_add_notice('رزرو کلاس خصوصی ثبت شد. برای فعال‌سازی، صورت‌حساب را پرداخت کنید.', 'success');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
        exit;
    }
    wc_add_notice('رزرو ثبت شد اما ایجاد صورت‌حساب با خطا مواجه شد.', 'warning');
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
            "SELECT id, capacity FROM {$courses_table} WHERE id = %d",
            (int) $booking->course_id
        ));
        $capacity = ($course && !empty($course->capacity)) ? (int) $course->capacity : 1;

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
                    'schedule_slot_id' => (int) $session['schedule_slot_id'],
                    'session_date' => (string) $session['session_date'],
                    'time_start' => (string) $session['time_start'],
                    'time_end' => (string) $session['time_end'],
                    'status' => 'scheduled',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
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
