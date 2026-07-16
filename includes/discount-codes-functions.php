<?php
/**
 * کدهای تخفیف مخصوص صورت‌حساب ثبت‌نام (دوره / رویداد)
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $code
 */
function sc_normalize_sc_discount_code($code) {
    $code = is_string($code) ? trim($code) : '';
    $code = preg_replace('/\s+/u', '', $code);
    return strtoupper($code);
}

/**
 * @return bool
 */
function sc_sc_discount_tables_ready() {
    global $wpdb;
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $t = $wpdb->prefix . 'sc_discount_codes';
    $ready = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t);
    return $ready;
}

/**
 * آیا جدول صورت‌حساب ستون‌های تخفیف را دارد؟
 *
 * @return bool
 */
function sc_invoices_support_discount_columns() {
    global $wpdb;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $t = $wpdb->prefix . 'sc_invoices';
    $row = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$t` LIKE %s", 'subtotal_amount'));
    $ok = !empty($row);
    return $ok;
}

/**
 * تعداد استفادهٔ کل از یک کد
 */
function sc_discount_code_usage_total($discount_code_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_discount_code_usages';
    $discount_code_id = absint($discount_code_id);
    if (!$discount_code_id || !sc_sc_discount_tables_ready()) {
        return 0;
    }
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t WHERE discount_code_id = %d",
        $discount_code_id
    ));
}

/**
 * تعداد استفادهٔ یک عضو از کد
 */
function sc_discount_code_usage_for_member($discount_code_id, $member_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_discount_code_usages';
    $discount_code_id = absint($discount_code_id);
    $member_id = absint($member_id);
    if (!$discount_code_id || !$member_id || !sc_sc_discount_tables_ready()) {
        return 0;
    }
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t WHERE discount_code_id = %d AND member_id = %d",
        $discount_code_id,
        $member_id
    ));
}

/**
 * محاسبهٔ مبلغ تخفیف از روی رکورد کد و مبلغ پایه
 *
 * @param object $row ردیف sc_discount_codes
 * @param float  $subtotal
 * @return float تخفیف (حداکثر تا subtotal)
 */
function sc_calculate_sc_discount_amount($row, $subtotal) {
    $subtotal = max(0, round((float) $subtotal, 2));
    if ($subtotal <= 0 || !$row) {
        return 0.0;
    }
    $type = isset($row->discount_type) ? $row->discount_type : 'percent';
    $discount = 0.0;

    if ($type === 'fixed') {
        $discount = floatval($row->discount_value);
    } else {
        $pct = floatval($row->discount_value);
        if ($pct <= 0) {
            return 0.0;
        }
        $discount = round($subtotal * ($pct / 100), 2);
        $cap = isset($row->max_discount_amount) && $row->max_discount_amount !== null && $row->max_discount_amount !== ''
            ? floatval($row->max_discount_amount)
            : null;
        if ($cap !== null && $cap > 0 && $discount > $cap) {
            $discount = $cap;
        }
    }

    if ($discount < 0) {
        $discount = 0;
    }
    if ($discount > $subtotal) {
        $discount = $subtotal;
    }
    return round($discount, 2);
}

/**
 * اعتبارسنجی کد تخفیف برای ثبت‌نام کاربر
 *
 * @param string $raw_code
 * @param array  $ctx {
 *   @type string $context             'course' یا 'event'
 *   @type int    $member_id
 *   @type float  $subtotal            مبلغ قبل از تخفیف
 *   @type int|null $course_id
 *   @type int|null $event_id
 *   @type object|null $course        ردیف دوره (اختیاری)
 *   @type object|null $event         ردیف رویداد (اختیاری)
 * }
 * @return WP_Error|array{discount_code_id:int,code:string,discount_amount:float,net_amount:float,subtotal:float}
 */
function sc_validate_sc_discount_code($raw_code, array $ctx) {
    if (!sc_sc_discount_tables_ready()) {
        return new WP_Error('sc_no_discount', 'سیستم کد تخفیف آماده نیست.');
    }

    $code = sc_normalize_sc_discount_code($raw_code);
    if ($code === '') {
        return new WP_Error('sc_discount_empty', 'کد تخفیف را وارد کنید.');
    }

    $context = isset($ctx['context']) ? sanitize_key($ctx['context']) : '';
    $member_id = isset($ctx['member_id']) ? absint($ctx['member_id']) : 0;
    $subtotal = isset($ctx['subtotal']) ? round(floatval($ctx['subtotal']), 2) : 0.0;

    if (!$member_id || $subtotal <= 0) {
        return new WP_Error('sc_discount_bad_ctx', 'اطلاعات صورت‌حساب برای اعمال تخفیف ناقص است.');
    }

    if (!in_array($context, ['course', 'event'], true)) {
        return new WP_Error('sc_discount_bad_ctx', 'نوع صورت‌حساب نامعتبر است.');
    }

    global $wpdb;
    $codes_t = $wpdb->prefix . 'sc_discount_codes';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $codes_t WHERE code = %s LIMIT 1",
        $code
    ));

    if (!$row) {
        return new WP_Error('sc_discount_invalid', 'کد تخفیف معتبر نیست.');
    }

    if (!(int) $row->is_active) {
        return new WP_Error('sc_discount_inactive', 'این کد تخفیف غیرفعال است.');
    }

    $now = current_time('mysql');
    if (!empty($row->starts_at) && strcmp($now, $row->starts_at) < 0) {
        return new WP_Error('sc_discount_not_started', 'زمان استفاده از این کد هنوز فرا نرسیده است.');
    }
    if (!empty($row->ends_at) && strcmp($now, $row->ends_at) > 0) {
        return new WP_Error('sc_discount_expired', 'اعتبار این کد تخفیف به پایان رسیده است.');
    }

    if ($context === 'course' && !(int) $row->allow_course) {
        return new WP_Error('sc_discount_no_course', 'این کد برای ثبت‌نام دوره قابل استفاده نیست.');
    }
    if ($context === 'event' && !(int) $row->allow_event) {
        return new WP_Error('sc_discount_no_event', 'این کد برای رویداد قابل استفاده نیست.');
    }

    $lim_total = isset($row->usage_limit_total) && $row->usage_limit_total !== null && $row->usage_limit_total !== ''
        ? absint($row->usage_limit_total)
        : null;
    if ($lim_total !== null && $lim_total > 0) {
        $used = sc_discount_code_usage_total((int) $row->id);
        if ($used >= $lim_total) {
            return new WP_Error('sc_discount_limit', 'سقف استفادهٔ این کد تخفیف تکمیل شده است.');
        }
    }

    $lim_member = isset($row->usage_limit_per_member) && $row->usage_limit_per_member !== null && $row->usage_limit_per_member !== ''
        ? absint($row->usage_limit_per_member)
        : null;
    if ($lim_member !== null && $lim_member > 0) {
        $used_m = sc_discount_code_usage_for_member((int) $row->id, $member_id);
        if ($used_m >= $lim_member) {
            return new WP_Error('sc_discount_member_limit', 'شما قبلاً از این کد استفاده کرده‌اید.');
        }
    }

    $dc_id = (int) $row->id;

    $course_id = isset($ctx['course_id']) ? absint($ctx['course_id']) : 0;
    $event_id = isset($ctx['event_id']) ? absint($ctx['event_id']) : 0;

    $course = isset($ctx['course']) && is_object($ctx['course']) ? $ctx['course'] : null;
    $event = isset($ctx['event']) && is_object($ctx['event']) ? $ctx['event'] : null;

    if ($context === 'course' && $course_id) {
        if (!$course) {
            $course = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sc_courses WHERE id = %d",
                $course_id
            ));
        }
        $n_courses = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_courses WHERE discount_code_id = %d",
            $dc_id
        ));
        if ($n_courses > 0) {
            $ok = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_courses WHERE discount_code_id = %d AND course_id = %d",
                $dc_id,
                $course_id
            ));
            if (!$ok) {
                return new WP_Error('sc_discount_course_restrict', 'این کد برای این دوره مجاز نیست.');
            }
        }

        $n_ch = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_chapters WHERE discount_code_id = %d",
            $dc_id
        ));
        if ($n_ch > 0) {
            $ch_name = '';
            if (!empty($args['enrollment_chapter'])) {
                $ch_name = trim((string) $args['enrollment_chapter']);
            } elseif ($course && isset($course->chapter)) {
                $ch_name = trim((string) $course->chapter);
            }
            if ($ch_name === '') {
                return new WP_Error('sc_discount_chapter', 'این کد فقط برای شعبه‌های مشخص‌شده مجاز است.');
            }
            $ok_ch = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_chapters WHERE discount_code_id = %d AND chapter_name = %s",
                $dc_id,
                $ch_name
            ));
            if (!$ok_ch) {
                return new WP_Error('sc_discount_chapter', 'این کد برای شعبهٔ این دوره مجاز نیست.');
            }
        }
    }

    if ($context === 'event' && $event_id) {
        if (!$event) {
            $event = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sc_events WHERE id = %d",
                $event_id
            ));
        }
        $n_events = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_events WHERE discount_code_id = %d",
            $dc_id
        ));
        if ($n_events > 0) {
            $ok = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_events WHERE discount_code_id = %d AND event_id = %d",
                $dc_id,
                $event_id
            ));
            if (!$ok) {
                return new WP_Error('sc_discount_event_restrict', 'این کد برای این رویداد مجاز نیست.');
            }
        }

        $n_ch_ev = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_chapters WHERE discount_code_id = %d",
            $dc_id
        ));
        if ($n_ch_ev > 0) {
            $event_ch = $event && isset($event->chapter) ? trim((string) $event->chapter) : '';
            if ($event_ch === '') {
                return new WP_Error('sc_discount_event_chapter', 'این کد فقط برای شعبه‌های مشخص‌شده مجاز است.');
            }
            $ok_ch_ev = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_chapters WHERE discount_code_id = %d AND chapter_name = %s",
                $dc_id,
                $event_ch
            ));
            if (!$ok_ch_ev) {
                return new WP_Error('sc_discount_event_chapter', 'این کد برای شعبهٔ این رویداد مجاز نیست.');
            }
        }
    }

    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_members WHERE id = %d",
        $member_id
    ));
    if (!$member) {
        return new WP_Error('sc_discount_member', 'عضو یافت نشد.');
    }

    $deny = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_members_deny WHERE discount_code_id = %d AND member_id = %d",
        $dc_id,
        $member_id
    ));
    if ($deny > 0) {
        return new WP_Error('sc_discount_denied', 'شما مجاز به استفاده از این کد نیستید.');
    }

    $n_allow = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_members_allow WHERE discount_code_id = %d",
        $dc_id
    ));
    if ($n_allow > 0) {
        $in_allow = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_members_allow WHERE discount_code_id = %d AND member_id = %d",
            $dc_id,
            $member_id
        ));
        if (!$in_allow) {
            return new WP_Error('sc_discount_allowlist', 'این کد فقط برای اعضای مشخص‌شده مجاز است.');
        }
    }

    $n_teams = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_teams WHERE discount_code_id = %d",
        $dc_id
    ));
    if ($n_teams > 0) {
        $tp = isset($member->team_player) ? trim((string) $member->team_player) : '';
        if ($tp === '') {
            return new WP_Error('sc_discount_team', 'این کد فقط برای بازیکنان تیم‌های مشخص‌شده است.');
        }
        $ok_t = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_teams WHERE discount_code_id = %d AND team_name = %s",
            $dc_id,
            $tp
        ));
        if (!$ok_t) {
            return new WP_Error('sc_discount_team', 'این کد برای تیم شما مجاز نیست.');
        }
    }

    $n_lv = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_levels WHERE discount_code_id = %d",
        $dc_id
    ));
    if ($n_lv > 0) {
        $lv = isset($member->skill_level) ? trim((string) $member->skill_level) : '';
        if ($lv === '') {
            return new WP_Error('sc_discount_level', 'این کد فقط برای سطح‌های مشخص‌شده است.');
        }
        $ok_l = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_levels WHERE discount_code_id = %d AND level_name = %s",
            $dc_id,
            $lv
        ));
        if (!$ok_l) {
            return new WP_Error('sc_discount_level', 'این کد برای سطح شما مجاز نیست.');
        }
    }

    $n_gender = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_genders WHERE discount_code_id = %d",
        $dc_id
    ));
    if ($n_gender > 0) {
        $member_gender = isset($member->gender) ? trim((string) $member->gender) : '';
        if ($member_gender === '') {
            return new WP_Error('sc_discount_gender', 'این کد فقط برای جنسیت‌های مشخص‌شده است.');
        }
        $ok_g = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sc_discount_code_genders WHERE discount_code_id = %d AND gender = %s",
            $dc_id,
            $member_gender
        ));
        if (!$ok_g) {
            return new WP_Error('sc_discount_gender', 'این کد برای جنسیت شما مجاز نیست.');
        }
    }

    $min_sub = isset($row->min_subtotal) ? floatval($row->min_subtotal) : 0;
    if ($min_sub > 0 && $subtotal + 0.00001 < $min_sub) {
        return new WP_Error('sc_discount_min', 'حداقل مبلغ صورت‌حساب برای این کد ' . number_format($min_sub, 0, '.', ',') . ' تومان است.');
    }

    $discount_amount = sc_calculate_sc_discount_amount($row, $subtotal);
    $net = round(max(0, $subtotal - $discount_amount), 2);

    return [
        'discount_code_id' => $dc_id,
        'code' => $code,
        'discount_amount' => $discount_amount,
        'net_amount' => $net,
        'subtotal' => $subtotal,
    ];
}

/**
 * ثبت استفاده از کد پس از ایجاد صورت‌حساب
 */
function sc_record_sc_discount_usage($discount_code_id, $invoice_id, $member_id, $amount_saved) {
    global $wpdb;
    if (!sc_sc_discount_tables_ready()) {
        return false;
    }
    $discount_code_id = absint($discount_code_id);
    $invoice_id = absint($invoice_id);
    $member_id = absint($member_id);
    if (!$discount_code_id || !$invoice_id || !$member_id) {
        return false;
    }
    $t = $wpdb->prefix . 'sc_discount_code_usages';
    return false !== $wpdb->insert(
        $t,
        [
            'discount_code_id' => $discount_code_id,
            'invoice_id' => $invoice_id,
            'member_id' => $member_id,
            'amount_saved' => round(floatval($amount_saved), 2),
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%d', '%d', '%f', '%s']
    );
}

/**
 * AJAX: پیش‌نمایش تخفیف (ثبت‌نام دوره)
 */
add_action('wp_ajax_sc_preview_sc_discount_course', 'sc_ajax_preview_sc_discount_course');
function sc_ajax_preview_sc_discount_course() {
    check_ajax_referer('sc_discount_preview', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'ورود لازم است.']);
    }
    $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $enrollment_sessions = isset($_POST['enrollment_sessions']) ? absint($_POST['enrollment_sessions']) : 0;
    $enrollment_chapter = isset($_POST['enrollment_chapter']) ? sanitize_text_field(wp_unslash($_POST['enrollment_chapter'])) : '';

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $uid = get_current_user_id();
    $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $members_table WHERE user_id = %d LIMIT 1", $uid));
    if (!$member) {
        wp_send_json_error(['message' => 'پروفایل بازیکن یافت نشد.']);
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $courses_table WHERE id = %d AND deleted_at IS NULL AND is_active = 1",
        $course_id
    ));
    if (!$course) {
        wp_send_json_error(['message' => 'دوره معتبر نیست.']);
    }

    $subtotal = floatval($course->price);
    if (function_exists('sc_course_has_packages') && sc_course_has_packages($course_id)) {
        // انتخاب پکیج اختیاری است؛ اگر انتخاب شد باید معتبر باشد، وگرنه قیمت پایه دوره استفاده می‌شود
        if ($enrollment_sessions > 0) {
            if (!function_exists('sc_get_course_package_by_sessions')) {
                wp_send_json_error(['message' => 'امکان بررسی پکیج در دسترس نیست.']);
            }
            $pkg = sc_get_course_package_by_sessions($course_id, $enrollment_sessions);
            if (!$pkg) {
                wp_send_json_error(['message' => 'پکیج نامعتبر است.']);
            }
            $subtotal = floatval($pkg->price);
        }
    }

    if (trim($code) === '') {
        $html = function_exists('wc_price') ? wp_kses_post(wc_price($subtotal)) : esc_html(number_format($subtotal, 0, '.', ','));
        wp_send_json_success([
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'net_amount' => $subtotal,
            'summary_html' => '<strong>مبلغ:</strong> ' . $html,
        ]);
    }

    $r = sc_validate_sc_discount_code($code, [
        'context' => 'course',
        'member_id' => (int) $member->id,
        'subtotal' => $subtotal,
        'course_id' => $course_id,
        'course' => $course,
        'enrollment_chapter' => $enrollment_chapter,
    ]);

    if (is_wp_error($r)) {
        wp_send_json_error(['message' => $r->get_error_message()]);
    }

    $net_html = function_exists('wc_price') ? wp_kses_post(wc_price($r['net_amount'])) : esc_html(number_format($r['net_amount'], 0, '.', ','));
    $disc_html = function_exists('wc_price') ? wp_kses_post(wc_price($r['discount_amount'])) : esc_html(number_format($r['discount_amount'], 0, '.', ','));

    $summary = '<strong>جمع قبل از تخفیف:</strong> ' . (function_exists('wc_price') ? wp_kses_post(wc_price($r['subtotal'])) : esc_html(number_format($r['subtotal'], 0, '.', ',')));
    $summary .= '<br><strong>تخفیف:</strong> ' . $disc_html;
    $summary .= '<br><strong>مبلغ قابل پرداخت:</strong> ' . $net_html;

    wp_send_json_success([
        'subtotal' => $r['subtotal'],
        'discount_amount' => $r['discount_amount'],
        'net_amount' => $r['net_amount'],
        'summary_html' => $summary,
    ]);
}

/**
 * AJAX: پیش‌نمایش تخفیف (رویداد)
 */
add_action('wp_ajax_sc_preview_sc_discount_event', 'sc_ajax_preview_sc_discount_event');
function sc_ajax_preview_sc_discount_event() {
    check_ajax_referer('sc_discount_preview', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'ورود لازم است.']);
    }
    $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $uid = get_current_user_id();
    $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $members_table WHERE user_id = %d LIMIT 1", $uid));
    if (!$member) {
        wp_send_json_error(['message' => 'پروفایل بازیکن یافت نشد.']);
    }

    $events_table = $wpdb->prefix . 'sc_events';
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $events_table WHERE id = %d AND deleted_at IS NULL AND is_active = 1",
        $event_id
    ));
    if (!$event) {
        wp_send_json_error(['message' => 'رویداد معتبر نیست.']);
    }

    $subtotal = floatval($event->price);
    if ($subtotal <= 0) {
        wp_send_json_error(['message' => 'این رویداد رایگان است.']);
    }

    if (trim($code) === '') {
        $html = function_exists('wc_price') ? wp_kses_post(wc_price($subtotal)) : esc_html(number_format($subtotal, 0, '.', ','));
        wp_send_json_success([
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'net_amount' => $subtotal,
            'summary_html' => '<strong>مبلغ:</strong> ' . $html,
        ]);
    }

    $r = sc_validate_sc_discount_code($code, [
        'context' => 'event',
        'member_id' => (int) $member->id,
        'subtotal' => $subtotal,
        'event_id' => $event_id,
        'event' => $event,
    ]);

    if (is_wp_error($r)) {
        wp_send_json_error(['message' => $r->get_error_message()]);
    }

    $net_html = function_exists('wc_price') ? wp_kses_post(wc_price($r['net_amount'])) : esc_html(number_format($r['net_amount'], 0, '.', ','));
    $disc_html = function_exists('wc_price') ? wp_kses_post(wc_price($r['discount_amount'])) : esc_html(number_format($r['discount_amount'], 0, '.', ','));

    $summary = '<strong>جمع قبل از تخفیف:</strong> ' . (function_exists('wc_price') ? wp_kses_post(wc_price($r['subtotal'])) : esc_html(number_format($r['subtotal'], 0, '.', ',')));
    $summary .= '<br><strong>تخفیف:</strong> ' . $disc_html;
    $summary .= '<br><strong>مبلغ قابل پرداخت:</strong> ' . $net_html;

    wp_send_json_success([
        'subtotal' => $r['subtotal'],
        'discount_amount' => $r['discount_amount'],
        'net_amount' => $r['net_amount'],
        'summary_html' => $summary,
    ]);
}

/**
 * حذف ردیف‌های محدودیت (بدون حذف سوابق استفاده)
 */
function sc_clear_discount_code_restrictions($discount_code_id) {
    global $wpdb;
    $discount_code_id = absint($discount_code_id);
    if (!$discount_code_id || !sc_sc_discount_tables_ready()) {
        return;
    }
    $suffixes = [
        'sc_discount_code_courses',
        'sc_discount_code_events',
        'sc_discount_code_chapters',
        'sc_discount_code_teams',
        'sc_discount_code_levels',
        'sc_discount_code_genders',
        'sc_discount_code_members_allow',
        'sc_discount_code_members_deny',
    ];
    foreach ($suffixes as $s) {
        $wpdb->delete($wpdb->prefix . $s, ['discount_code_id' => $discount_code_id], ['%d']);
    }
}

/**
 * حذف کامل کد تخفیف به‌همراه سوابق استفاده
 */
function sc_delete_discount_code_admin($id) {
    if (!current_user_can('manage_options')) {
        return false;
    }
    global $wpdb;
    $id = absint($id);
    if (!$id || !sc_sc_discount_tables_ready()) {
        return false;
    }
    sc_clear_discount_code_restrictions($id);
    $wpdb->delete($wpdb->prefix . 'sc_discount_code_usages', ['discount_code_id' => $id], ['%d']);
    return (false !== $wpdb->delete($wpdb->prefix . 'sc_discount_codes', ['id' => $id], ['%d']));
}

/**
 * @param string $str
 * @return int[]
 */
function sc_parse_member_ids_csv($str) {
    $str = is_string($str) ? $str : '';
    $out = [];
    foreach (preg_split('/[,;\s]+/', $str, -1, PREG_SPLIT_NO_EMPTY) as $p) {
        $i = absint($p);
        if ($i > 0) {
            $out[] = $i;
        }
    }
    return array_values(array_unique($out));
}

/**
 * @param string $str
 * @return int[]
 */
function sc_parse_member_tokens_csv($str) {
    $str = is_string($str) ? $str : '';
    $out = [];
    foreach (preg_split('/[,;\s]+/', $str, -1, PREG_SPLIT_NO_EMPTY) as $token) {
        $token = trim($token);
        if ($token === '') {
            continue;
        }
        if (preg_match('/^member_(\d+)$/', $token, $m)) {
            $out[] = absint($m[1]);
            continue;
        }
        $out[] = absint($token);
    }
    return array_values(array_filter(array_unique($out)));
}

/**
 * ذخیره کد تخفیف از فرم ادمین
 *
 * @return string|false URL ریدایرکت یا false
 */
function sc_save_discount_code_from_post() {
    if (!current_user_can('manage_options')) {
        return false;
    }
    sc_check_and_create_tables();
    if (!sc_sc_discount_tables_ready()) {
        return admin_url('admin.php?page=sc-discount-codes&sc_err=no_tables');
    }

    global $wpdb;
    $codes_t = $wpdb->prefix . 'sc_discount_codes';

    $edit_id = isset($_POST['discount_id']) ? absint($_POST['discount_id']) : 0;
    $code_raw = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
    $code_norm = sc_normalize_sc_discount_code($code_raw);
    if ($code_norm === '') {
        return admin_url('admin.php?page=sc-add-discount-code&discount_id=' . $edit_id . '&sc_err=code');
    }

    $dup = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $codes_t WHERE code = %s AND id != %d LIMIT 1",
        $code_norm,
        $edit_id
    ));
    if ($dup > 0) {
        return admin_url('admin.php?page=sc-add-discount-code&discount_id=' . $edit_id . '&sc_err=duplicate');
    }

    $discount_type = isset($_POST['discount_type']) && $_POST['discount_type'] === 'fixed' ? 'fixed' : 'percent';
    $dv_raw = isset($_POST['discount_value_raw']) ? sanitize_text_field(wp_unslash($_POST['discount_value_raw'])) : '';
    $discount_value = floatval(str_replace([',', ' '], ['', ''], $dv_raw));
    $max_raw = isset($_POST['max_discount_amount_raw']) ? sanitize_text_field(wp_unslash($_POST['max_discount_amount_raw'])) : '';
    $max_cap = trim($max_raw) === '' ? null : floatval(str_replace([',', ' '], ['', ''], $max_raw));
    $min_raw = isset($_POST['min_subtotal_raw']) ? sanitize_text_field(wp_unslash($_POST['min_subtotal_raw'])) : '';
    $min_sub = floatval(str_replace([',', ' '], ['', ''], $min_raw));

    $starts_at = null;
    $starts_date_shamsi = isset($_POST['starts_at_date_shamsi']) ? sanitize_text_field(wp_unslash($_POST['starts_at_date_shamsi'])) : '';
    $starts_time = isset($_POST['starts_at_time']) ? sanitize_text_field(wp_unslash($_POST['starts_at_time'])) : '';
    if ($starts_date_shamsi !== '') {
        $starts_gregorian = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($starts_date_shamsi) : '';
        if ($starts_gregorian) {
            if (!preg_match('/^\d{2}:\d{2}$/', $starts_time)) {
                $starts_time = '00:00';
            }
            $starts_at = $starts_gregorian . ' ' . $starts_time . ':00';
        }
    }

    $ends_at = null;
    $ends_date_shamsi = isset($_POST['ends_at_date_shamsi']) ? sanitize_text_field(wp_unslash($_POST['ends_at_date_shamsi'])) : '';
    $ends_time = isset($_POST['ends_at_time']) ? sanitize_text_field(wp_unslash($_POST['ends_at_time'])) : '';
    if ($ends_date_shamsi !== '') {
        $ends_gregorian = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($ends_date_shamsi) : '';
        if ($ends_gregorian) {
            if (!preg_match('/^\d{2}:\d{2}$/', $ends_time)) {
                $ends_time = '23:59';
            }
            $ends_at = $ends_gregorian . ' ' . $ends_time . ':00';
        }
    }

    $usage_total = isset($_POST['usage_limit_total']) && $_POST['usage_limit_total'] !== ''
        ? absint($_POST['usage_limit_total'])
        : null;
    $usage_member = isset($_POST['usage_limit_per_member']) && $_POST['usage_limit_per_member'] !== ''
        ? absint($_POST['usage_limit_per_member'])
        : null;

    $allow_course = isset($_POST['allow_course']) ? 1 : 0;
    $allow_event = isset($_POST['allow_event']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $now = current_time('mysql');

    $row = [
        'code' => $code_norm,
        'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : null,
        'discount_type' => $discount_type,
        'discount_value' => $discount_value,
        'max_discount_amount' => $max_cap,
        'min_subtotal' => $min_sub,
        'starts_at' => $starts_at,
        'ends_at' => $ends_at,
        'usage_limit_total' => $usage_total,
        'usage_limit_per_member' => $usage_member,
        'allow_course' => $allow_course,
        'allow_event' => $allow_event,
        'is_active' => $is_active,
        'updated_at' => $now,
    ];

    // max_discount_amount و سقف‌های استفاده می‌توانند NULL باشند
    $fmt = ['%s', '%s', '%s', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s'];

    if ($edit_id) {
        $wpdb->update($codes_t, $row, ['id' => $edit_id], $fmt, ['%d']);
        $dc_id = $edit_id;
    } else {
        $row['created_at'] = $now;
        $fmt_ins = array_merge($fmt, ['%s']);
        $wpdb->insert($codes_t, $row, $fmt_ins);
        $dc_id = (int) $wpdb->insert_id;
    }

    if (!$dc_id) {
        return admin_url('admin.php?page=sc-discount-codes&sc_err=save');
    }

    sc_clear_discount_code_restrictions($dc_id);

    $course_ids = isset($_POST['course_ids']) && is_array($_POST['course_ids']) ? array_map('absint', $_POST['course_ids']) : [];
    $course_ids = array_filter(array_unique($course_ids));
    foreach ($course_ids as $cid) {
        $wpdb->insert(
            $wpdb->prefix . 'sc_discount_code_courses',
            ['discount_code_id' => $dc_id, 'course_id' => $cid],
            ['%d', '%d']
        );
    }

    $event_ids = isset($_POST['event_ids']) && is_array($_POST['event_ids']) ? array_map('absint', $_POST['event_ids']) : [];
    $event_ids = array_filter(array_unique($event_ids));
    foreach ($event_ids as $eid) {
        $wpdb->insert(
            $wpdb->prefix . 'sc_discount_code_events',
            ['discount_code_id' => $dc_id, 'event_id' => $eid],
            ['%d', '%d']
        );
    }

    $chapters = isset($_POST['chapter_names']) && is_array($_POST['chapter_names']) ? $_POST['chapter_names'] : [];
    foreach ($chapters as $ch) {
        $ch = sanitize_text_field($ch);
        if ($ch !== '') {
            $wpdb->insert(
                $wpdb->prefix . 'sc_discount_code_chapters',
                ['discount_code_id' => $dc_id, 'chapter_name' => function_exists('mb_substr') ? mb_substr($ch, 0, 191) : substr($ch, 0, 191)],
                ['%d', '%s']
            );
        }
    }

    $teams = isset($_POST['team_names']) && is_array($_POST['team_names']) ? $_POST['team_names'] : [];
    foreach ($teams as $tm) {
        $tm = sanitize_text_field($tm);
        if ($tm !== '') {
            $wpdb->insert(
                $wpdb->prefix . 'sc_discount_code_teams',
                ['discount_code_id' => $dc_id, 'team_name' => function_exists('mb_substr') ? mb_substr($tm, 0, 191) : substr($tm, 0, 191)],
                ['%d', '%s']
            );
        }
    }

    $levels = isset($_POST['level_names']) && is_array($_POST['level_names']) ? $_POST['level_names'] : [];
    foreach ($levels as $lv) {
        $lv = sanitize_text_field($lv);
        if ($lv !== '') {
            $wpdb->insert(
                $wpdb->prefix . 'sc_discount_code_levels',
                ['discount_code_id' => $dc_id, 'level_name' => function_exists('mb_substr') ? mb_substr($lv, 0, 191) : substr($lv, 0, 191)],
                ['%d', '%s']
            );
        }
    }

    $genders = isset($_POST['gender_values']) && is_array($_POST['gender_values']) ? $_POST['gender_values'] : [];
    foreach ($genders as $g) {
        $g = sanitize_key($g);
        if (!in_array($g, ['male', 'female'], true)) {
            continue;
        }
        $wpdb->insert(
            $wpdb->prefix . 'sc_discount_code_genders',
            ['discount_code_id' => $dc_id, 'gender' => $g],
            ['%d', '%s']
        );
    }

    $allow_m = [];
    if (isset($_POST['member_allow_ids']) && is_array($_POST['member_allow_ids'])) {
        $allow_m = array_map('absint', $_POST['member_allow_ids']);
        $allow_m = array_values(array_filter(array_unique($allow_m)));
    } elseif (!empty($_POST['member_allow_ids_str'])) {
        $allow_m = sc_parse_member_tokens_csv(sanitize_text_field(wp_unslash($_POST['member_allow_ids_str'])));
    } else {
        $allow_m = sc_parse_member_ids_csv(isset($_POST['members_allow']) ? wp_unslash($_POST['members_allow']) : '');
    }
    foreach ($allow_m as $mid) {
        $wpdb->insert(
            $wpdb->prefix . 'sc_discount_code_members_allow',
            ['discount_code_id' => $dc_id, 'member_id' => $mid],
            ['%d', '%d']
        );
    }

    $deny_m = [];
    if (isset($_POST['member_deny_ids']) && is_array($_POST['member_deny_ids'])) {
        $deny_m = array_map('absint', $_POST['member_deny_ids']);
        $deny_m = array_values(array_filter(array_unique($deny_m)));
    } elseif (!empty($_POST['member_deny_ids_str'])) {
        $deny_m = sc_parse_member_tokens_csv(sanitize_text_field(wp_unslash($_POST['member_deny_ids_str'])));
    } else {
        $deny_m = sc_parse_member_ids_csv(isset($_POST['members_deny']) ? wp_unslash($_POST['members_deny']) : '');
    }
    foreach ($deny_m as $mid) {
        $wpdb->insert(
            $wpdb->prefix . 'sc_discount_code_members_deny',
            ['discount_code_id' => $dc_id, 'member_id' => $mid],
            ['%d', '%d']
        );
    }

    return admin_url('admin.php?page=sc-discount-codes&saved=1');
}

function sc_discount_code_edit_page_load() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'sc-add-discount-code') {
        return;
    }
    if (!empty($_POST['sc_save_discount_code']) && check_admin_referer('sc_save_discount_code')) {
        $url = sc_save_discount_code_from_post();
        if ($url) {
            wp_safe_redirect($url);
            exit;
        }
    }
}

function sc_discount_codes_list_page_load() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'sc-discount-codes') {
        return;
    }
    if (isset($_GET['action'], $_GET['discount_id']) && $_GET['action'] === 'delete' && current_user_can('manage_options')) {
        $id = absint($_GET['discount_id']);
        check_admin_referer('sc_delete_discount_' . $id);
        sc_delete_discount_code_admin($id);
        wp_safe_redirect(admin_url('admin.php?page=sc-discount-codes&deleted=1'));
        exit;
    }
}

function sc_admin_discount_codes_list_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'discount-codes-list.php';
}

function sc_admin_discount_code_edit_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'discount-code-edit.php';
}

/**
 * آمار استفاده کدهای تخفیف (تعداد / نفرات یکتا / مجموع مبلغ).
 * مجموع تخفیف فقط برای صورت‌حساب‌های پرداخت‌شده / تایید پرداخت حساب می‌شود.
 *
 * @param int[] $discount_code_ids
 * @return array<int,array{usage_count:int,member_count:int,total_saved:float}>
 */
function sc_get_discount_codes_usage_stats(array $discount_code_ids) {
    global $wpdb;
    $discount_code_ids = array_values(array_filter(array_map('absint', $discount_code_ids)));
    $out = [];
    foreach ($discount_code_ids as $id) {
        $out[$id] = [
            'usage_count' => 0,
            'member_count' => 0,
            'total_saved' => 0.0,
        ];
    }
    if (empty($discount_code_ids) || !sc_sc_discount_tables_ready()) {
        return $out;
    }

    $placeholders = implode(',', array_fill(0, count($discount_code_ids), '%d'));
    $usages_t = $wpdb->prefix . 'sc_discount_code_usages';
    $invoices_t = $wpdb->prefix . 'sc_invoices';
    $paid_statuses = sc_discount_paid_invoice_statuses();
    $status_placeholders = implode(',', array_fill(0, count($paid_statuses), '%s'));

    $sql = "SELECT u.discount_code_id,
                   COUNT(*) AS usage_count,
                   COUNT(DISTINCT u.member_id) AS member_count,
                   COALESCE(SUM(
                       CASE
                           WHEN i.status IN ($status_placeholders) THEN u.amount_saved
                           ELSE 0
                       END
                   ), 0) AS total_saved
            FROM $usages_t u
            LEFT JOIN $invoices_t i ON i.id = u.invoice_id
            WHERE u.discount_code_id IN ($placeholders)
            GROUP BY u.discount_code_id";

    $args = array_merge($paid_statuses, $discount_code_ids);
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);

    if (!empty($rows)) {
        foreach ($rows as $row) {
            $id = (int) $row['discount_code_id'];
            $out[$id] = [
                'usage_count' => (int) $row['usage_count'],
                'member_count' => (int) $row['member_count'],
                'total_saved' => round((float) $row['total_saved'], 2),
            ];
        }
    }

    return $out;
}

/**
 * وضعیت‌های صورت‌حساب که در جمع تخفیف لحاظ می‌شوند.
 *
 * @return string[]
 */
function sc_discount_paid_invoice_statuses() {
    return ['processing', 'paid', 'completed'];
}

/**
 * آیا وضعیت صورت‌حساب برای جمع تخفیف معتبر است؟
 *
 * @param string|null $status
 */
function sc_discount_invoice_status_counts_toward_total($status) {
    $status = is_string($status) ? trim($status) : '';
    return $status !== '' && in_array($status, sc_discount_paid_invoice_statuses(), true);
}

/**
 * برچسب فارسی وضعیت صورت‌حساب برای مودال کد تخفیف.
 *
 * @param string|null $status
 * @param bool        $invoice_found
 */
function sc_discount_invoice_status_label($status, $invoice_found = true) {
    if (!$invoice_found) {
        return 'صورت‌حساب یافت نشد';
    }
    $status = is_string($status) ? trim($status) : '';
    if ($status === '') {
        return 'نامشخص';
    }
    if (function_exists('sc_get_invoice_status_display')) {
        $info = sc_get_invoice_status_display($status);
        if (!empty($info['label'])) {
            return (string) $info['label'];
        }
    }
    return $status;
}

/**
 * ردیف‌های استفاده از یک کد تخفیف برای مودال کاربران.
 *
 * @return array<int,array<string,mixed>>
 */
function sc_get_discount_code_usage_rows($discount_code_id) {
    global $wpdb;
    $discount_code_id = absint($discount_code_id);
    if (!$discount_code_id || !sc_sc_discount_tables_ready()) {
        return [];
    }

    $usages_t = $wpdb->prefix . 'sc_discount_code_usages';
    $members_t = $wpdb->prefix . 'sc_members';
    $invoices_t = $wpdb->prefix . 'sc_invoices';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT u.id AS usage_id, u.invoice_id, u.member_id, u.amount_saved, u.created_at,
                    m.first_name, m.last_name, m.national_id, m.player_phone,
                    i.id AS invoice_exists_id, i.amount AS invoice_amount, i.status AS invoice_status
             FROM $usages_t u
             LEFT JOIN $members_t m ON m.id = u.member_id
             LEFT JOIN $invoices_t i ON i.id = u.invoice_id
             WHERE u.discount_code_id = %d
             ORDER BY u.created_at DESC, u.id DESC",
            $discount_code_id
        ),
        ARRAY_A
    );

    if (empty($rows) || !is_array($rows)) {
        return [];
    }

    foreach ($rows as &$row) {
        $row['full_name'] = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        if ($row['full_name'] === '') {
            $row['full_name'] = 'کاربر #' . (int) ($row['member_id'] ?? 0);
        }
        $row['amount_saved'] = round((float) ($row['amount_saved'] ?? 0), 2);
        $row['created_at_shamsi'] = function_exists('sc_date_shamsi')
            ? sc_date_shamsi((string) ($row['created_at'] ?? ''), 'Y/m/d H:i')
            : (string) ($row['created_at'] ?? '-');
        $row['invoice_found'] = !empty($row['invoice_exists_id']);
        $row['invoice_amount'] = isset($row['invoice_amount']) && $row['invoice_amount'] !== null
            ? round((float) $row['invoice_amount'], 2)
            : null;
        $row['invoice_status'] = isset($row['invoice_status']) ? trim((string) $row['invoice_status']) : '';
        $row['invoice_status_label'] = sc_discount_invoice_status_label(
            $row['invoice_status'],
            (bool) $row['invoice_found']
        );
        $row['counts_toward_total'] = $row['invoice_found']
            && sc_discount_invoice_status_counts_toward_total($row['invoice_status']);
    }
    unset($row);

    return $rows;
}

/**
 * HTML مودال کاربران استفاده‌کننده از کد تخفیف.
 *
 * @param array<int,array<string,mixed>> $rows
 */
function sc_render_discount_code_users_modal_html($discount_code_id, array $rows, $code_label = '') {
    $discount_code_id = absint($discount_code_id);
    $code_label = $code_label !== '' ? (string) $code_label : ('کد #' . $discount_code_id);
    $usage_count = count($rows);
    $member_ids = [];
    $total_saved = 0.0;
    foreach ($rows as $row) {
        $mid = (int) ($row['member_id'] ?? 0);
        if ($mid > 0) {
            $member_ids[$mid] = true;
        }
        if (!empty($row['counts_toward_total'])) {
            $total_saved += (float) ($row['amount_saved'] ?? 0);
        }
    }
    $member_count = count($member_ids);
    $total_saved = round($total_saved, 2);

    ob_start();
    ?>
    <div class="sc-discount-users-modal-inner">
        <div class="sc-discount-users-modal-toolbar">
            <div class="sc-discount-users-modal-toolbar-text">
                <p class="sc-reports-list-desc">
                    <?php
                    echo esc_html(sprintf(
                        'کد «%s» — %d استفاده توسط %d نفر | مجموع تخفیف: %s تومان',
                        $code_label,
                        $usage_count,
                        $member_count,
                        number_format($total_saved, 0, '.', ',')
                    ));
                    ?>
                </p>
            </div>
        </div>

        <div class="sc-discount-users-stats">
            <div class="sc-discount-users-stat">
                <span class="sc-discount-users-stat-label">تعداد استفاده</span>
                <span class="sc-discount-users-stat-value"><?php echo (int) $usage_count; ?></span>
            </div>
            <div class="sc-discount-users-stat">
                <span class="sc-discount-users-stat-label">تعداد نفرات</span>
                <span class="sc-discount-users-stat-value"><?php echo (int) $member_count; ?></span>
            </div>
            <div class="sc-discount-users-stat">
                <span class="sc-discount-users-stat-label">مجموع تخفیف</span>
                <span class="sc-discount-users-stat-value"><?php echo esc_html(number_format($total_saved, 0, '.', ',')); ?> <small>تومان</small></span>
                <span class="description" style="display:block;margin-top:4px;font-size:11px;">فقط پرداخت‌شده / تایید پرداخت</span>
            </div>
        </div>

        <?php if ($usage_count === 0) : ?>
            <div class="sc-discount-users-empty">
                <p>هنوز کسی از این کد تخفیف استفاده نکرده است.</p>
            </div>
        <?php else : ?>
            <div class="sc-reports-list-table-card sc-discount-users-table-card">
                <div class="sc-discount-users-table-scroll">
                    <table class="wp-list-table widefat fixed striped sc-discount-users-table">
                        <thead>
                            <tr>
                                <th class="column-row">ردیف</th>
                                <th>کاربر</th>
                                <th>کد ملی</th>
                                <th>تماس</th>
                                <th>مبلغ تخفیف</th>
                                <th>صورت‌حساب</th>
                                <th>تاریخ استفاده</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $index => $row) :
                                $invoice_id = (int) ($row['invoice_id'] ?? 0);
                                $member_id = (int) ($row['member_id'] ?? 0);
                                $member_url = $member_id
                                    ? admin_url('admin.php?page=sc-view-member&player_id=' . $member_id)
                                    : '';
                                $invoice_url = $invoice_id
                                    ? admin_url('admin.php?page=sc-invoices&s=' . $invoice_id)
                                    : '';
                                ?>
                                <tr>
                                    <td class="column-row"><?php echo (int) ($index + 1); ?></td>
                                    <td>
                                        <?php if ($member_url) : ?>
                                            <a href="<?php echo esc_url($member_url); ?>"><?php echo esc_html($row['full_name']); ?></a>
                                        <?php else : ?>
                                            <?php echo esc_html($row['full_name']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($row['national_id'] ?: '—'); ?></td>
                                    <td><?php echo esc_html($row['player_phone'] ?: '—'); ?></td>
                                    <td><strong><?php echo esc_html(number_format((float) $row['amount_saved'], 0, '.', ',')); ?></strong></td>
                                    <td>
                                        <?php if ($invoice_id) : ?>
                                            <?php if ($invoice_url) : ?>
                                                <a href="<?php echo esc_url($invoice_url); ?>">#<?php echo (int) $invoice_id; ?></a>
                                            <?php else : ?>
                                                #<?php echo (int) $invoice_id; ?>
                                            <?php endif; ?>
                                            <span class="sc-discount-users-invoice-status"><?php echo esc_html($row['invoice_status_label'] ?? 'نامشخص'); ?></span>
                                        <?php else : ?>
                                            —
                                            <span class="sc-discount-users-invoice-status">بدون صورت‌حساب</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($row['created_at_shamsi'] ?? '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

add_action('wp_ajax_sc_get_discount_code_users', 'sc_ajax_get_discount_code_users');
function sc_ajax_get_discount_code_users() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $discount_code_id = isset($_POST['discount_code_id']) ? absint($_POST['discount_code_id']) : 0;
    if (!$discount_code_id) {
        wp_send_json_error(['message' => 'شناسه کد تخفیف معتبر نیست.']);
    }

    if (!sc_sc_discount_tables_ready()) {
        wp_send_json_error(['message' => 'جداول کد تخفیف آماده نیست.']);
    }

    global $wpdb;
    $codes_t = $wpdb->prefix . 'sc_discount_codes';
    $code_row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, code FROM $codes_t WHERE id = %d LIMIT 1",
        $discount_code_id
    ));
    if (!$code_row) {
        wp_send_json_error(['message' => 'کد تخفیف یافت نشد.']);
    }

    $rows = sc_get_discount_code_usage_rows($discount_code_id);
    $html = sc_render_discount_code_users_modal_html($discount_code_id, $rows, (string) $code_row->code);

    $member_ids = [];
    $total_saved = 0.0;
    foreach ($rows as $row) {
        $mid = (int) ($row['member_id'] ?? 0);
        if ($mid > 0) {
            $member_ids[$mid] = true;
        }
        if (!empty($row['counts_toward_total'])) {
            $total_saved += (float) ($row['amount_saved'] ?? 0);
        }
    }

    wp_send_json_success([
        'html' => $html,
        'count' => count($rows),
        'member_count' => count($member_ids),
        'total_saved' => round($total_saved, 2),
        'discount_code_id' => $discount_code_id,
        'code' => (string) $code_row->code,
    ]);
}

add_action(
    'admin_init',
    static function () {
        if (!is_admin()) {
            return;
        }
        sc_discount_codes_list_page_load();
        sc_discount_code_edit_page_load();
    },
    5
);
