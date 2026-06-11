<?php
/**
 * اطلاع‌رسانی هنگام خالی شدن ظرفیت دوره (اعلان داخل سایت + پیامک قابل تنظیم)
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * تعداد ثبت‌نام‌های فعال که در محاسبهٔ ظرفیت دوره می‌آیند (هم‌منطق با enroll-course.php)
 */
function sc_count_course_capacity_slots_used($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if ($course_id < 1) {
        return 0;
    }
    $t = $wpdb->prefix . 'sc_member_courses';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t
         WHERE course_id = %d
           AND status = 'active'
           AND (course_status_flags IS NULL OR TRIM(course_status_flags) = '')",
        $course_id
    ));
}

/**
 * آیا برای این دوره هنوز ظرفیت خالی وجود دارد؟
 */
function sc_course_has_remaining_capacity_slots($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if ($course_id < 1) {
        return false;
    }
    $courses = $wpdb->prefix . 'sc_courses';
    $cap = $wpdb->get_var($wpdb->prepare("SELECT capacity FROM $courses WHERE id = %d LIMIT 1", $course_id));
    if (!$cap || (int) $cap < 1) {
        return true;
    }
    $used = sc_count_course_capacity_slots_used($course_id);
    return $used < (int) $cap;
}

/**
 * شناسهٔ دوره‌هایی که این عضو درخواست اطلاع‌رسانی معلق دارد
 *
 * @return int[]
 */
function sc_get_member_pending_waitlist_course_ids($member_id) {
    global $wpdb;
    $member_id = absint($member_id);
    if ($member_id < 1) {
        return [];
    }
    $wt = $wpdb->prefix . 'sc_course_capacity_waitlist';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wt)) !== $wt) {
        return [];
    }
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT course_id FROM $wt WHERE member_id = %d AND notified_at IS NULL",
        $member_id
    ));
    return array_values(array_filter(array_map('absint', (array) $rows)));
}

/**
 * اعتبارسنجی ثبت درخواست اطلاع‌رسانی
 *
 * @return true|WP_Error
 */
function sc_course_capacity_waitlist_validate_subscribe($member_id, $course_id) {
    global $wpdb;
    $member_id = absint($member_id);
    $course_id = absint($course_id);
    if ($member_id < 1 || $course_id < 1) {
        return new WP_Error('sc_wl_bad', 'درخواست نامعتبر است.');
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $courses_table WHERE id = %d AND deleted_at IS NULL AND is_active = 1 AND (course_type IS NULL OR course_type = '' OR course_type = 'group') LIMIT 1",
        $course_id
    ));
    if (!$course) {
        return new WP_Error('sc_wl_course', 'این دوره در دسترس نیست.');
    }
    if (empty($course->capacity) || (int) $course->capacity < 1) {
        return new WP_Error('sc_wl_cap', 'این دوره محدودیت ظرفیت ندارد.');
    }

    $used = sc_count_course_capacity_slots_used($course_id);
    if ($used < (int) $course->capacity) {
        return new WP_Error('sc_wl_open', 'ظرفیت این دوره هنوز پر نشده است.');
    }

    $members_table = $wpdb->prefix . 'sc_members';
    $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $members_table WHERE id = %d LIMIT 1", $member_id));
    if (!$member) {
        return new WP_Error('sc_wl_member', 'پروفایل بازیکن یافت نشد.');
    }
    if (!function_exists('sc_member_matches_item_restrictions') || !sc_member_matches_item_restrictions($course, $member)) {
        return new WP_Error('sc_wl_restrict', 'شما مجاز به این درخواست برای این دوره نیستید.');
    }

    $today_shamsi = function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '';
    $is_date_bad = false;
    if (!empty($course->start_date) || !empty($course->end_date)) {
        if (!empty($course->end_date) && function_exists('sc_date_shamsi_date_only') && function_exists('sc_compare_shamsi_dates') && $today_shamsi) {
            $end_date_shamsi = sc_date_shamsi_date_only($course->end_date);
            if (!empty($end_date_shamsi) && sc_compare_shamsi_dates($today_shamsi, $end_date_shamsi) > 0) {
                $is_date_bad = true;
            }
        }
        if (!$is_date_bad && !empty($course->start_date) && function_exists('sc_date_shamsi_date_only') && function_exists('sc_compare_shamsi_dates') && $today_shamsi) {
            $start_date_shamsi = sc_date_shamsi_date_only($course->start_date);
            if (!empty($start_date_shamsi) && sc_compare_shamsi_dates($today_shamsi, $start_date_shamsi) < 0) {
                $is_date_bad = true;
            }
        }
    }
    if ($is_date_bad) {
        return new WP_Error('sc_wl_date', 'زمان ثبت‌نام این دوره مناسب نیست یا به پایان رسیده است.');
    }

    return true;
}

/**
 * ثبت یا تمدید درخواست اطلاع‌رسانی
 *
 * @return true|WP_Error
 */
function sc_course_capacity_waitlist_subscribe($member_id, $course_id) {
    global $wpdb;
    $ok = sc_course_capacity_waitlist_validate_subscribe($member_id, $course_id);
    if (is_wp_error($ok)) {
        return $ok;
    }

    $wt = $wpdb->prefix . 'sc_course_capacity_waitlist';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wt)) !== $wt) {
        return new WP_Error('sc_wl_table', 'سیستم آماده نیست. لطفاً بعداً تلاش کنید.');
    }

    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id, notified_at FROM $wt WHERE course_id = %d AND member_id = %d LIMIT 1",
        $course_id,
        $member_id
    ));

    $now = current_time('mysql');
    if ($existing) {
        if ($existing->notified_at === null || $existing->notified_at === '') {
            return new WP_Error('sc_wl_dup', 'شما قبلاً برای این دوره درخواست اطلاع‌رسانی ثبت کرده‌اید.');
        }
        $wpdb->update(
            $wt,
            ['notified_at' => null, 'created_at' => $now],
            ['id' => (int) $existing->id],
            ['%s', '%s'],
            ['%d']
        );
        return true;
    }

    $wpdb->insert(
        $wt,
        [
            'course_id' => absint($course_id),
            'member_id' => absint($member_id),
            'created_at' => $now,
            'notified_at' => null,
        ],
        ['%d', '%d', '%s', '%s']
    );

    if ($wpdb->insert_id) {
        return true;
    }
    return new WP_Error('sc_wl_db', 'ثبت درخواست انجام نشد.');
}

/**
 * ارسال اعلان و پیامک به افراد در صف، وقتی حداقل یک جای خالی وجود دارد
 */
function sc_maybe_notify_course_capacity_waitlist($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if ($course_id < 1) {
        return;
    }

    $wt = $wpdb->prefix . 'sc_course_capacity_waitlist';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wt)) !== $wt) {
        return;
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    $course = $wpdb->get_row($wpdb->prepare("SELECT id, title, capacity FROM $courses_table WHERE id = %d LIMIT 1", $course_id));
    if (!$course || empty($course->capacity) || (int) $course->capacity < 1) {
        return;
    }

    if (!sc_course_has_remaining_capacity_slots($course_id)) {
        return;
    }

    $waiters = $wpdb->get_results($wpdb->prepare(
        "SELECT id, member_id FROM $wt WHERE course_id = %d AND notified_at IS NULL ORDER BY id ASC",
        $course_id
    ));
    if (empty($waiters)) {
        return;
    }

    $members_table = $wpdb->prefix . 'sc_members';
    $course_title = isset($course->title) ? (string) $course->title : ('#' . $course_id);
    $enroll_url = function_exists('wc_get_account_endpoint_url')
        ? wc_get_account_endpoint_url('sc-enroll-course')
        : home_url('/');

    foreach ($waiters as $w) {
        $mid = (int) $w->member_id;
        $wid = (int) $w->id;
        if ($mid < 1 || $wid < 1) {
            continue;
        }

        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, first_name, last_name, player_phone FROM $members_table WHERE id = %d LIMIT 1",
            $mid
        ));
        if (!$member) {
            $wpdb->update($wt, ['notified_at' => current_time('mysql')], ['id' => $wid], ['%s'], ['%d']);
            continue;
        }

        $user_name = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
        if ($user_name === '') {
            $user_name = 'کاربر گرامی';
        }

        $notif_title = 'ظرفیت دوره «' . $course_title . '»';
        $notif_content = sprintf(
            'کاربر گرامی، ظرفیت ثبت‌نام در دوره «%s» اکنون باز است. برای ثبت‌نام به بخش «ثبت‌نام در دوره» در حساب کاربری خود مراجعه کنید.',
            $course_title
        );
        if (!empty($enroll_url)) {
            $notif_content .= "\n" . $enroll_url;
        }

        if (function_exists('sc_save_notification')) {
            sc_save_notification([
                'title' => $notif_title,
                'content' => $notif_content,
                'target_type' => 'specific',
                'target_config' => [
                    'recipient_ids' => ['member_' . $mid],
                ],
                'notification_type' => 'system',
                'send_sms' => 0,
            ]);
        }

        if (
            function_exists('sc_is_sms_enabled_for')
            && sc_is_sms_enabled_for('course_capacity_waitlist', 'user')
            && function_exists('sc_get_sms_template')
            && function_exists('sc_replace_sms_variables')
            && function_exists('sc_send_sms')
            && !empty($member->player_phone)
        ) {
            $template = sc_get_sms_template('course_capacity_waitlist', 'user');
            if (!empty($template)) {
                $variables = [
                    'user_name' => $user_name,
                    'course_name' => $course_title,
                    'item_name' => $course_title,
                    'enroll_url' => $enroll_url,
                ];
                $message = sc_replace_sms_variables($template, $variables);
                $pattern_code = function_exists('sc_get_sms_pattern') ? sc_get_sms_pattern('course_capacity_waitlist', 'user') : null;
                sc_send_sms(
                    (string) $member->player_phone,
                    $message,
                    !empty($pattern_code),
                    $pattern_code,
                    $variables,
                    'course_capacity_waitlist'
                );
            }
        }

        $wpdb->update(
            $wt,
            ['notified_at' => current_time('mysql')],
            ['id' => $wid],
            ['%s'],
            ['%d']
        );
    }
}

add_action('wp_ajax_sc_course_capacity_waitlist_subscribe', 'sc_ajax_course_capacity_waitlist_subscribe');
function sc_ajax_course_capacity_waitlist_subscribe() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'لطفاً وارد شوید.']);
    }
    check_ajax_referer('sc_course_capacity_waitlist', 'nonce');

    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    if ($course_id < 1) {
        wp_send_json_error(['message' => 'دوره نامعتبر است.']);
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $members_table WHERE user_id = %d LIMIT 1",
        get_current_user_id()
    ));
    if ($member_id < 1) {
        wp_send_json_error(['message' => 'پروفایل بازیکن یافت نشد.']);
    }

    $result = sc_course_capacity_waitlist_subscribe($member_id, $course_id);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success(['message' => 'با موفقیت ثبت شد. به محض خالی شدن ظرفیت، از طریق اعلان‌ و پیامک، مطلع می‌شوید.']);
}
