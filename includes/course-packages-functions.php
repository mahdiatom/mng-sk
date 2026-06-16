<?php
/**
 * پکیج‌های قیمت دوره (جلسه + مبلغ)
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return bool
 */
function sc_course_has_packages($course_id) {
    $course_id = absint($course_id);
    if (!$course_id) {
        return false;
    }
    global $wpdb;
    $t = $wpdb->prefix . 'sc_course_packages';
    $n = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t WHERE course_id = %d",
        $course_id
    ));
    return $n > 0;
}

/**
 * @return array<int, object>
 */
function sc_get_course_packages($course_id, $order_by_sessions = true) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_course_packages';
    $course_id = absint($course_id);
    if (!$course_id) {
        return [];
    }
    $order_sql = $order_by_sessions ? 'sessions_count ASC, sort_order ASC, id ASC' : 'sort_order ASC, id ASC';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE course_id = %d ORDER BY " . $order_sql, $course_id));
    return is_array($rows) ? $rows : [];
}

/**
 * @return object|null
 */
function sc_get_course_package_by_sessions($course_id, $sessions_count) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_course_packages';
    $course_id = absint($course_id);
    $sessions_count = absint($sessions_count);
    if (!$course_id || !$sessions_count) {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $t WHERE course_id = %d AND sessions_count = %d LIMIT 1",
        $course_id,
        $sessions_count
    ));
}

/**
 * حذف و ذخیرهٔ مجدد پکیج‌های یک دوره
 *
 * @param array<int, array{sessions:int, price:float}> $rows
 */
function sc_replace_course_packages($course_id, array $rows, $allow_zero_price = false) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_course_packages';
    $course_id = absint($course_id);
    if (!$course_id) {
        return false;
    }
    $wpdb->delete($t, ['course_id' => $course_id], ['%d']);
    $now = current_time('mysql');
    $sort = 0;
    foreach ($rows as $row) {
        $sessions = isset($row['sessions']) ? absint($row['sessions']) : 0;
        $price = isset($row['price']) ? floatval($row['price']) : 0;
        if ($sessions < 1 || (!$allow_zero_price && $price <= 0)) {
            continue;
        }
        $wpdb->insert(
            $t,
            [
                'course_id' => $course_id,
                'sessions_count' => $sessions,
                'price' => $price,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%f', '%d', '%s', '%s']
        );
        $sort++;
    }
    return true;
}

/**
 * پارس آرایه POST برای ذخیره پکیج‌ها (هم‌تراز بودن اندیس‌ها)
 *
 * @return array{sessions:int, price:float}|WP_Error
 */
function sc_parse_course_packages_from_post() {
    $sessions_raw = isset($_POST['pkg_sessions']) ? (array) $_POST['pkg_sessions'] : [];
    $prices_raw = isset($_POST['pkg_price_raw']) ? (array) $_POST['pkg_price_raw'] : [];
    $rows = [];
    $seen = [];

    $max = max(count($sessions_raw), count($prices_raw));
    for ($i = 0; $i < $max; $i++) {
        $s = isset($sessions_raw[$i]) ? absint($sessions_raw[$i]) : 0;
        $price_clean = '';
        if (isset($prices_raw[$i])) {
            $price_clean = preg_replace('/[^\d.]/', '', str_replace(',', '', sanitize_text_field($prices_raw[$i])));
        }
        $p = floatval($price_clean);
        if ($s < 1 && $p <= 0) {
            continue;
        }
        if ($s < 1 || $p <= 0) {
            continue;
        }
        if (isset($seen[$s])) {
            return new WP_Error('pkg_dup', 'تعداد جلسه در پکیج‌ها تکراری است.');
        }
        $seen[$s] = true;
        $rows[] = ['sessions' => $s, 'price' => $p];
    }
    return $rows;
}

/**
 * پارس گزینه‌های تعداد جلسه (بدون قیمت) برای کلاس خصوصی با قیمت متغیر مربی
 *
 * @return array<int, array{sessions:int, price:float}>|WP_Error
 */
function sc_parse_private_session_options_from_post() {
    $sessions_raw = isset($_POST['private_sess_counts']) ? (array) $_POST['private_sess_counts'] : [];
    $rows = [];
    $seen = [];

    foreach ($sessions_raw as $raw) {
        $s = absint($raw);
        if ($s < 1) {
            continue;
        }
        if (isset($seen[$s])) {
            return new WP_Error('private_sess_dup', 'تعداد جلسه تکراری است.');
        }
        $seen[$s] = true;
        $rows[] = ['sessions' => $s, 'price' => 0.0];
    }

    return $rows;
}

/**
 * گزینه‌های تعداد جلسه برای UI کلاس خصوصی
 *
 * @return int[]
 */
function sc_get_course_private_session_count_options($course_id) {
    $course_id = absint($course_id);
    if (!$course_id) {
        return [];
    }

    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    $course = $wpdb->get_row($wpdb->prepare("SELECT sessions_count, private_variable_coach_pricing FROM `$courses_table` WHERE id = %d", $course_id));
    $variable = $course && !empty($course->private_variable_coach_pricing);

    $counts = [];
    if ($variable) {
        $pkgs = sc_get_course_packages($course_id);
        foreach ($pkgs as $pkg) {
            $n = (int) $pkg->sessions_count;
            if ($n > 0) {
                $counts[$n] = $n;
            }
        }
    } elseif (sc_course_has_packages($course_id)) {
        foreach (sc_get_course_packages($course_id) as $pkg) {
            $n = (int) $pkg->sessions_count;
            if ($n > 0) {
                $counts[$n] = $n;
            }
        }
    }

    if (empty($counts) && $course && !empty($course->sessions_count)) {
        $counts[(int) $course->sessions_count] = (int) $course->sessions_count;
    }

    $out = array_values($counts);
    sort($out, SORT_NUMERIC);
    return $out;
}

/**
 * محاسبه مبلغ کلاس خصوصی
 */
function sc_calculate_private_class_invoice_amount($course, $enrollment_sessions, $chapter_name = '', $coach_id = 0) {
    if (!$course) {
        return 0.0;
    }
    $course_id = (int) $course->id;
    $sessions = max(0, absint($enrollment_sessions));
    if ($sessions <= 0) {
        return 0.0;
    }

    $variable = !empty($course->private_variable_coach_pricing);
    if ($variable && $chapter_name !== '' && $coach_id > 0 && function_exists('sc_get_course_coach_branch_meta')) {
        $meta = sc_get_course_coach_branch_meta($course_id, $chapter_name, $coach_id);
        if ($meta && (float) $meta['price_per_session'] > 0) {
            return (float) $meta['price_per_session'] * $sessions;
        }
    }

    if (sc_course_has_packages($course_id)) {
        $pkg = sc_get_course_package_by_sessions($course_id, $sessions);
        if ($pkg) {
            return (float) $pkg->price;
        }
    }

    if (!empty($course->price_per_session) && (float) $course->price_per_session > 0) {
        return (float) $course->price_per_session * $sessions;
    }

    return (float) $course->price;
}

/**
 * جلسات و رکورد ثبت‌نام برای member_courses
 *
 * @param int|null $selected_package_sessions از فرم ادمین یا کاربر
 * @return array{enrollment_sessions:?int,total_sessions:int,remaining_sessions:int}
 */
function sc_member_course_session_fields_for_course($course_id, $selected_package_sessions = null) {
    $course_id = absint($course_id);
    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM $courses_table WHERE id = %d", $course_id));

    if (sc_course_has_packages($course_id)) {
        $sel = absint($selected_package_sessions);
        $pkg = $sel ? sc_get_course_package_by_sessions($course_id, $sel) : null;
        if (!$pkg) {
            return ['enrollment_sessions' => 0, 'total_sessions' => 0, 'remaining_sessions' => 0];
        }
        $n = (int) $pkg->sessions_count;
        return [
            'enrollment_sessions' => $n,
            'total_sessions' => $n,
            'remaining_sessions' => $n,
        ];
    }

    $ts = ($course && !empty($course->sessions_count)) ? (int) $course->sessions_count : 0;
    return [
        'enrollment_sessions' => null,
        'total_sessions' => $ts,
        'remaining_sessions' => $ts,
    ];
}

/**
 * مبلغ صورت‌حساب دوره (ثبت‌نام / دوره‌های تکراری)
 *
 * @param object      $course ردیف sc_courses
 * @param object|null $member_course ردیف sc_member_courses یا شیء با enrollment_sessions
 */
function sc_get_course_billing_amount_for_member_course($course, $member_course = null) {
    if (!$course) {
        return 0.0;
    }
    $course_id = (int) $course->id;
    if (sc_course_has_packages($course_id)) {
        $ens = 0;
        if ($member_course && isset($member_course->enrollment_sessions) && $member_course->enrollment_sessions !== null && $member_course->enrollment_sessions !== '') {
            $ens = (int) $member_course->enrollment_sessions;
        }
        if ($ens > 0) {
            $pkg = sc_get_course_package_by_sessions($course_id, $ens);
            if ($pkg) {
                return floatval($pkg->price);
            }
        }
        $pkgs = sc_get_course_packages($course_id);
        if (!empty($pkgs)) {
            $prices = array_map(function ($p) {
                return floatval($p->price);
            }, $pkgs);
            return (float) min($prices);
        }
        return floatval($course->price);
    }
    return floatval($course->price);
}

/**
 * برچسب آیتم فاکتور / سفارش
 *
 * @param int|null $package_sessions برای پکیج؛ null یعنی سناریوی legacy
 */
function sc_course_enrollment_fee_label($course_title, $package_sessions = null) {
    $course_title = sanitize_text_field($course_title);
    if ($package_sessions !== null && $package_sessions > 0) {
        return sprintf(
            'ثبت نام دوره %s - پکیج %d جلسه',
            $course_title,
            absint($package_sessions)
        );
    }
    return 'ثبت نام دوره: ' . $course_title;
}

/**
 * @return string[]
 */
function sc_member_course_parse_flags($member_course) {
    if (!$member_course || empty($member_course->course_status_flags)) {
        return [];
    }
    $flags = explode(',', (string) $member_course->course_status_flags);
    return array_values(array_filter(array_map('trim', $flags)));
}

function sc_normalize_member_course_chapter($chapter) {
    return trim((string) $chapter);
}

function sc_member_course_assignments_match($row, $chapter, $coach_id) {
    if (!$row) {
        return false;
    }
    $row_ch = sc_normalize_member_course_chapter($row->chapter ?? '');
    $want_ch = sc_normalize_member_course_chapter($chapter);
    if ($row_ch !== $want_ch) {
        return false;
    }
    return (int) ($row->coach_id ?? 0) === (int) $coach_id;
}

function sc_member_course_is_terminal($member_course) {
    $flags = sc_member_course_parse_flags($member_course);
    return in_array('completed', $flags, true) || in_array('canceled', $flags, true);
}

function sc_member_course_is_paused($member_course) {
    return in_array('paused', sc_member_course_parse_flags($member_course), true);
}

/**
 * @param int[] $pending_member_course_ids
 */
function sc_member_course_blocks_new_enrollment($member_course, array $pending_member_course_ids = []) {
    if (!$member_course) {
        return false;
    }
    if (sc_member_course_is_terminal($member_course)) {
        return false;
    }
    if (sc_member_course_is_paused($member_course)) {
        return true;
    }
    $flags = sc_member_course_parse_flags($member_course);
    if (!empty($flags)) {
        return true;
    }
    if ((string) $member_course->status === 'active') {
        return true;
    }
    if ((string) $member_course->status === 'inactive') {
        $mc_id = (int) $member_course->id;
        if ($mc_id > 0 && in_array($mc_id, $pending_member_course_ids, true)) {
            return true;
        }
    }
    return false;
}

/**
 * @return int[]
 */
function sc_get_pending_invoice_member_course_ids($member_id) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $member_id = (int) $member_id;
    if ($member_id <= 0) {
        return [];
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT member_course_id FROM $invoices_table
         WHERE member_id = %d AND member_course_id IS NOT NULL AND member_course_id > 0
           AND status IN ('pending', 'under_review')",
        $member_id
    ));
    $ids = [];
    foreach ((array) $rows as $row) {
        $ids[] = (int) $row->member_course_id;
    }
    return $ids;
}

/**
 * @return object|null
 */
function sc_find_blocking_member_course_enrollment($member_id, $course_id, $chapter = '', $coach_id = 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_member_courses';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE member_id = %d AND course_id = %d ORDER BY created_at DESC",
        (int) $member_id,
        (int) $course_id
    ));
    $pending_ids = sc_get_pending_invoice_member_course_ids($member_id);
    foreach ((array) $rows as $row) {
        if (!sc_member_course_assignments_match($row, $chapter, $coach_id)) {
            continue;
        }
        if (sc_member_course_blocks_new_enrollment($row, $pending_ids)) {
            return $row;
        }
    }
    return null;
}

function sc_course_has_blocking_enrollment($member_id, $course_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_member_courses';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE member_id = %d AND course_id = %d",
        (int) $member_id,
        (int) $course_id
    ));
    $pending_ids = sc_get_pending_invoice_member_course_ids($member_id);
    foreach ((array) $rows as $row) {
        if (sc_member_course_blocks_new_enrollment($row, $pending_ids)) {
            return true;
        }
    }
    return false;
}

function sc_get_coach_display_name($coach_id) {
    $coach_id = (int) $coach_id;
    if ($coach_id <= 0) {
        return '';
    }
    global $wpdb;
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT first_name, last_name FROM $coaches_table WHERE id = %d",
        $coach_id
    ));
    if (!$row) {
        return '';
    }
    return trim((string) $row->first_name . ' ' . (string) $row->last_name);
}

/**
 * خلاصه وضعیت ثبت‌نام کاربر در یک دوره (برای صفحه ثبت‌نام)
 *
 * @return array{blocking:bool,is_under_review:bool,is_pending_payment:bool,is_canceled:bool,is_completed:bool,is_paused:bool,is_active:bool}
 */
function sc_summarize_member_course_enrollment_status($member_course, array $pending_member_course_ids, array $under_review_member_course_ids) {
    $mc_id = (int) $member_course->id;
    $flags = sc_member_course_parse_flags($member_course);
    $summary = [
        'blocking' => sc_member_course_blocks_new_enrollment($member_course, $pending_member_course_ids),
        'is_under_review' => in_array($mc_id, $under_review_member_course_ids, true),
        'is_pending_payment' => in_array($mc_id, $pending_member_course_ids, true) && !in_array($mc_id, $under_review_member_course_ids, true),
        'is_canceled' => in_array('canceled', $flags, true),
        'is_completed' => in_array('completed', $flags, true),
        'is_paused' => in_array('paused', $flags, true),
        'is_active' => ((string) $member_course->status === 'active' && empty($flags)),
    ];
    return $summary;
}

/**
 * AJAX: پیش‌نمایش قیمت پکیج در صفحه ثبت‌نام
 */
add_action('wp_ajax_sc_enroll_course_package_preview', 'sc_ajax_enroll_course_package_preview');
function sc_ajax_enroll_course_package_preview() {
    check_ajax_referer('sc_enroll_package', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'ورود لازم است.']);
    }
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $sessions = isset($_POST['sessions']) ? absint($_POST['sessions']) : 0;
    if (!$course_id || !$sessions) {
        wp_send_json_error(['message' => 'پارامتر نامعتبر است.']);
    }
    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $courses_table WHERE id = %d AND deleted_at IS NULL AND is_active = 1",
        $course_id
    ));
    if (!$course) {
        wp_send_json_error(['message' => 'دوره یافت نشد.']);
    }
    if (!sc_course_has_packages($course_id)) {
        $amt = floatval($course->price);
        $label = sc_course_enrollment_fee_label($course->title, null);
        $html = function_exists('wc_price') ? wc_price($amt) : number_format($amt, 0, '.', ',') . ' تومان';
        wp_send_json_success([
            'amount' => $amt,
            'amount_html' => $html,
            'fee_label' => $label,
            'sessions' => !empty($course->sessions_count) ? (int) $course->sessions_count : 0,
        ]);
    }
    $pkg = sc_get_course_package_by_sessions($course_id, $sessions);
    if (!$pkg) {
        wp_send_json_error(['message' => 'پکیج نامعتبر است.']);
    }
    $amt = floatval($pkg->price);
    $html = function_exists('wc_price') ? wc_price($amt) : number_format($amt, 0, '.', ',') . ' تومان';
    wp_send_json_success([
        'amount' => $amt,
        'amount_html' => $html,
        'fee_label' => sc_course_enrollment_fee_label($course->title, (int) $pkg->sessions_count),
        'sessions' => (int) $pkg->sessions_count,
    ]);
}
