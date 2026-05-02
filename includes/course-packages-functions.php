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
function sc_replace_course_packages($course_id, array $rows) {
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
        if ($sessions < 1 || $price <= 0) {
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
            return new WP_Error('pkg_invalid', 'هر ردیف پکیج باید تعداد جلسه و قیمت معتبر داشته باشد.');
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
