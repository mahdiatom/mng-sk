<?php
/**
 * Coach Wallet Functions
 * توابع کیف پول مربی
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * آیا نوع تسویه مربی شامل دستمزد درصدی (بر اساس جلسه/دوره) است.
 *
 * @param string|null $settlement_type
 * @return bool
 */
function sc_coach_settlement_includes_percentage($settlement_type) {
    return $settlement_type === 'percentage' || $settlement_type === 'both';
}

/**
 * آیا نوع تسویه مربی شامل حقوق ثابت ماهانه است.
 *
 * @param string|null $settlement_type
 * @return bool
 */
function sc_coach_settlement_includes_fixed($settlement_type) {
    return $settlement_type === 'fixed' || $settlement_type === 'both';
}

/**
 * Get coach wallet balance
 * دریافت موجودی کیف پول مربی
 */
function sc_get_coach_wallet_balance($coach_id) {
    if (!$coach_id) {
        return 0.00;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_coach_wallet_transactions';
    
    $balance = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(balance_after, 0.00) 
         FROM $table_name 
         WHERE coach_id = %d AND status = 'completed'
         ORDER BY created_at DESC, id DESC 
         LIMIT 1",
        $coach_id
    ));
    
    return floatval($balance ? $balance : 0.00);
}

/**
 * Add transaction to coach wallet
 * افزودن تراکنش به کیف پول مربی
 */
function sc_add_coach_wallet_transaction($coach_id, $transaction_type, $amount, $description = '', $related_data = []) {
    if (!$coach_id || !$amount || $amount <= 0) {
        return ['success' => false, 'message' => 'پارامترهای نامعتبر'];
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_coach_wallet_transactions';
    
    $balance_before = sc_get_coach_wallet_balance($coach_id);
    $balance_after = $balance_before + floatval($amount);
    
    $data = [
        'coach_id' => $coach_id,
        'transaction_type' => $transaction_type,
        'amount' => floatval($amount),
        'balance_before' => $balance_before,
        'balance_after' => $balance_after,
        'description' => $description,
        'created_by' => get_current_user_id(),
        'status' => 'completed',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    // اضافه کردن داده‌های مرتبط
    if (isset($related_data['course_id'])) {
        $data['related_course_id'] = absint($related_data['course_id']);
    }
    if (isset($related_data['attendance_date'])) {
        $data['related_attendance_date'] = sanitize_text_field($related_data['attendance_date']);
    }
    if (isset($related_data['salary_record_id'])) {
        $data['related_salary_record_id'] = absint($related_data['salary_record_id']);
    }
    
    $format = ['%d', '%s', '%f', '%f', '%f', '%s', '%d', '%s', '%s', '%s'];
    if (isset($data['related_course_id'])) {
        $format[] = '%d';
    }
    if (isset($data['related_attendance_date'])) {
        $format[] = '%s';
    }
    if (isset($data['related_salary_record_id'])) {
        $format[] = '%d';
    }
    
    $result = $wpdb->insert($table_name, $data, $format);
    
    if ($result) {
        return [
            'success' => true,
            'transaction_id' => $wpdb->insert_id,
            'balance_after' => $balance_after
        ];
    }
    
    return ['success' => false, 'message' => 'خطا در ثبت تراکنش'];
}

/**
 * Deduct from coach wallet
 * کسر از کیف پول مربی
 */
function sc_deduct_coach_wallet($coach_id, $amount, $description = '') {
    if (!$coach_id || !$amount || $amount <= 0) {
        return ['success' => false, 'message' => 'پارامترهای نامعتبر'];
    }
    
    $balance = sc_get_coach_wallet_balance($coach_id);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_coach_wallet_transactions';
    
    $balance_after = $balance - floatval($amount);
    
    $data = [
        'coach_id' => $coach_id,
        'transaction_type' => 'deduct',
        'amount' => floatval($amount),
        'balance_before' => $balance,
        'balance_after' => $balance_after,
        'description' => $description,
        'created_by' => get_current_user_id(),
        'status' => 'completed',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    $result = $wpdb->insert($table_name, $data, ['%d', '%s', '%f', '%f', '%f', '%s', '%d', '%s', '%s', '%s']);
    
    if ($result) {
        return [
            'success' => true,
            'transaction_id' => $wpdb->insert_id,
            'balance_after' => $balance_after
        ];
    }
    
    return ['success' => false, 'message' => 'خطا در کسر مبلغ'];
}

/**
 * Count attendance records belonging to a specific coach/branch in a course/date.
 * شمارش حضور/غیاب بازیکن‌های اختصاص‌یافته به یک مربی در یک شعبه/دوره/تاریخ
 *
 * @param string      $chapter_name شعبه (خالی = کل دوره، برای سازگاری قدیمی)
 * @param string|null $group_name   null = بدون فیلتر گروه؛ '' = فقط بدون گروه؛ نام = همان گروه
 */
function sc_get_coach_attendance_count_for_salary($coach_id, $course_id, $attendance_date, $present_only = true, $chapter_name = '', $group_name = null) {
    $coach_id = absint($coach_id);
    $course_id = absint($course_id);
    $attendance_date = sanitize_text_field($attendance_date);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $filter_by_group = ($group_name !== null);
    $group_name = $filter_by_group ? sanitize_text_field((string) $group_name) : null;

    if (!$coach_id || !$course_id || $attendance_date === '') {
        return 0;
    }

    global $wpdb;
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $status_where = $present_only ? " AND a.status = 'present' " : '';
    $coach_scope_where = 'mc.coach_id = %d';
    $chapter_where = '';
    $group_where = '';
    $prepare_args = [$course_id, $attendance_date];

    if ($chapter_name !== '') {
        $single_coach_for_chapter = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT CASE WHEN COUNT(DISTINCT cc.coach_id) = 1 THEN MIN(cc.coach_id) ELSE 0 END
             FROM $course_coaches_table cc
             INNER JOIN $coaches_table c ON c.id = cc.coach_id
             WHERE cc.course_id = %d AND cc.chapter_name = %s AND c.is_active = 1",
            $course_id,
            $chapter_name
        ));

        if ($single_coach_for_chapter > 0 && $single_coach_for_chapter === $coach_id) {
            $coach_scope_where = '(mc.coach_id = %d OR mc.coach_id IS NULL OR mc.coach_id = 0)';
            $chapter_where = " AND (mc.chapter = %s OR mc.chapter IS NULL OR mc.chapter = '')";
        } else {
            $coach_scope_where = 'mc.coach_id = %d';
            $chapter_where = ' AND mc.chapter = %s';
        }

        $prepare_args[] = $coach_id;
        $prepare_args[] = $chapter_name;
    } else {
        $single_active_coach_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT CASE WHEN COUNT(DISTINCT cc.coach_id) = 1 THEN MIN(cc.coach_id) ELSE 0 END
             FROM $course_coaches_table cc
             INNER JOIN $coaches_table c ON c.id = cc.coach_id
             WHERE cc.course_id = %d AND c.is_active = 1 AND cc.chapter_name != ''",
            $course_id
        ));

        if ($single_active_coach_id > 0 && $single_active_coach_id === $coach_id) {
            $coach_scope_where = '(mc.coach_id = %d OR mc.coach_id IS NULL OR mc.coach_id = 0)';
        }

        $prepare_args[] = $coach_id;
    }

    if ($filter_by_group) {
        if ($group_name === '') {
            $group_where = " AND (mc.group_name IS NULL OR mc.group_name = '')";
        } else {
            $group_where = ' AND mc.group_name = %s';
            $prepare_args[] = $group_name;
        }
    }

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT a.member_id)
         FROM $attendances_table a
         INNER JOIN $member_courses_table mc
             ON mc.member_id = a.member_id
            AND mc.course_id = a.course_id
         WHERE a.course_id = %d
           AND a.attendance_date = %s
           AND $coach_scope_where
           $chapter_where
           $group_where
           AND mc.status = 'active'
           AND (
                mc.course_status_flags IS NULL
                OR mc.course_status_flags = ''
                OR (
                    mc.course_status_flags NOT LIKE '%%paused%%'
                    AND mc.course_status_flags NOT LIKE '%%completed%%'
                    AND mc.course_status_flags NOT LIKE '%%canceled%%'
                )
           )
           $status_where",
        ...$prepare_args
    ));

    return (int) $count;
}

/**
 * Resolve session price for coach salary calculation.
 *
 * @param object|null $course_row row from sc_courses
 */
function sc_resolve_coach_salary_price_per_session($course_id, $chapter_name, $coach_id, $course_row = null, $branch_price = 0.0) {
    $course_id = absint($course_id);
    $coach_id = absint($coach_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $branch_price = (float) $branch_price;

    if ($branch_price > 0) {
        return $branch_price;
    }

    if ($chapter_name !== '' && $coach_id > 0 && function_exists('sc_get_course_coach_branch_meta')) {
        $meta = sc_get_course_coach_branch_meta($course_id, $chapter_name, $coach_id);
        if ($meta && (float) ($meta['price_per_session'] ?? 0) > 0) {
            return (float) $meta['price_per_session'];
        }
    }

    if ($course_row === null) {
        global $wpdb;
        $courses_table = $wpdb->prefix . 'sc_courses';
        $course_row = $wpdb->get_row($wpdb->prepare(
            "SELECT price_per_session, private_variable_coach_pricing FROM $courses_table WHERE id = %d LIMIT 1",
            $course_id
        ));
    }

    if ($course_row && !empty($course_row->private_variable_coach_pricing)) {
        return 0.0;
    }

    return $course_row ? (float) ($course_row->price_per_session ?? 0) : 0.0;
}

/**
 * بازمحاسبه دستمزد درصدی همه مربیان یک دوره برای یک تاریخ (پس از ثبت/ویرایش حضور).
 *
 * @param int    $course_id
 * @param string $attendance_date Y-m-d
 * @return array{price_per_session: float, coaches: array<int, array>}
 */
function sc_refresh_coach_percentage_salary_for_course_date($course_id, $attendance_date) {
    $course_id = absint($course_id);
    $attendance_date = sanitize_text_field((string) $attendance_date);
    $results = [
        'price_per_session' => 0,
        'coaches' => [],
    ];

    if (!$course_id || $attendance_date === '') {
        return $results;
    }

    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    $course_row = $wpdb->get_row($wpdb->prepare(
        "SELECT price_per_session, private_variable_coach_pricing FROM $courses_table WHERE id = %d LIMIT 1",
        $course_id
    ));
    $default_price_per_session = $course_row ? floatval($course_row->price_per_session) : 0;
    $results['price_per_session'] = $default_price_per_session;

    $calc_couch_salary = function_exists('sc_get_setting') ? sc_get_setting('calc_couch_salary') : '';
    $present_only_for_salary = !empty($calc_couch_salary);

    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    // همه انتساب‌های فعال دوره (حتی غیرواجد شرایط) تا علت عدم محاسبه مشخص شود
    $assignments = $wpdb->get_results($wpdb->prepare(
        "SELECT cc.coach_id, cc.chapter_name, cc.salary_percentage, cc.price_per_session AS branch_price,
                c.settlement_type, c.first_name, c.last_name
         FROM $course_coaches_table cc
         INNER JOIN $coaches_table c ON cc.coach_id = c.id
         WHERE cc.course_id = %d
           AND c.is_active = 1
         ORDER BY c.last_name ASC, c.first_name ASC, cc.chapter_name ASC",
        $course_id
    ));

    if (empty($assignments)) {
        $results['coaches'][] = [
            'coach_id' => 0,
            'chapter_name' => '',
            'coach_name' => '',
            'status' => 'no_coaches',
            'salary_amount' => 0,
            'deposited_amount' => 0,
            'attendance_count' => 0,
            'message' => 'هیچ مربی فعالی به این دوره انتساب داده نشده است',
        ];
        return $results;
    }

    foreach ($assignments as $assignment) {
        $coach_id = (int) $assignment->coach_id;
        $chapter_name = sanitize_text_field((string) $assignment->chapter_name);
        $coach_name = trim((string) $assignment->first_name . ' ' . (string) $assignment->last_name);
        $settlement_type = (string) ($assignment->settlement_type ?? '');
        $salary_percentage = floatval($assignment->salary_percentage);

        $base_result = [
            'coach_id' => $coach_id,
            'chapter_name' => $chapter_name,
            'coach_name' => $coach_name,
            'salary_amount' => 0,
            'deposited_amount' => 0,
            'attendance_count' => 0,
            'salary_percentage' => $salary_percentage,
            'settlement_type' => $settlement_type,
        ];

        if ($chapter_name === '') {
            $results['coaches'][] = array_merge($base_result, [
                'status' => 'missing_chapter',
                'message' => 'شعبه برای انتساب مربی به دوره مشخص نشده است',
            ]);
            continue;
        }

        if (!sc_coach_settlement_includes_percentage($settlement_type)) {
            $allowed_settlement = ['fixed', 'percentage', 'both'];
            if ($settlement_type === 'fixed') {
                $settlement_label = 'ثابت';
            } elseif (in_array($settlement_type, $allowed_settlement, true)) {
                $settlement_label = $settlement_type;
            } elseif ($settlement_type === '' || $settlement_type === '0' || is_numeric($settlement_type)) {
                $settlement_label = 'نامعتبر/خراب';
            } else {
                $settlement_label = $settlement_type;
            }
            $results['coaches'][] = array_merge($base_result, [
                'status' => 'skipped_settlement',
                'message' => $settlement_label === 'نامعتبر/خراب'
                    ? 'نوع تسویه مربی در دیتابیس خراب است؛ مربی را ویرایش و دوباره روی «درصدی» ذخیره کنید'
                    : ('نوع تسویه مربی «' . $settlement_label . '» است و شامل دستمزد درصدی نمی‌شود'),
            ]);
            continue;
        }

        if ($salary_percentage <= 0) {
            $results['coaches'][] = array_merge($base_result, [
                'status' => 'missing_percentage',
                'message' => 'درصد دستمزد برای این مربی در دوره/شعبه ثبت نشده است',
            ]);
            continue;
        }

        $price_per_session = sc_resolve_coach_salary_price_per_session(
            $course_id,
            $chapter_name,
            $coach_id,
            $course_row,
            (float) ($assignment->branch_price ?? 0)
        );

        if ($price_per_session <= 0) {
            $price_message = (!empty($course_row->private_variable_coach_pricing))
                ? 'قیمت هر جلسه برای این مربی/شعبه تنظیم نشده است (دوره با قیمت متغیر مربی)'
                : 'قیمت هر جلسه دوره تنظیم نشده است';
            $results['coaches'][] = array_merge($base_result, [
                'status' => 'missing_price',
                'message' => $price_message,
            ]);
            continue;
        }

        $coach_attendance_count = sc_get_coach_attendance_count_for_salary(
            $coach_id,
            $course_id,
            $attendance_date,
            $present_only_for_salary,
            $chapter_name
        );
        $calc_result = sc_calculate_coach_percentage_salary(
            $coach_id,
            $course_id,
            $attendance_date,
            $coach_attendance_count,
            $price_per_session,
            $chapter_name
        );

        $deposited_amount = 0;
        $status = 'error';
        $calc_message = (string) ($calc_result['message'] ?? '');

        if (empty($calc_result['success'])) {
            if ($calc_message === 'درصد دستمزد تنظیم نشده است') {
                $status = 'missing_percentage';
            } elseif ($calc_message === 'پارامترهای نامعتبر') {
                $status = 'invalid_params';
            } else {
                $status = 'error';
            }
            if ($calc_message === '') {
                $calc_message = 'خطا در محاسبه دستمزد';
            }
        } elseif (isset($calc_result['deposited_amount']) && (float) $calc_result['deposited_amount'] > 0) {
            $deposited_amount = (float) $calc_result['deposited_amount'];
            $status = 'calculated';
        } elseif (isset($calc_result['difference']) && abs((float) $calc_result['difference']) >= 0.01) {
            $difference = (float) $calc_result['difference'];
            if ($difference > 0) {
                $deposited_amount = $difference;
                $status = 'calculated';
            } else {
                $status = 'updated';
                $calc_message = $calc_message !== '' ? $calc_message : ('دستمزد به‌روزرسانی شد و مبلغ ' . number_format(abs($difference), 0, '.', ',') . ' تومان از کیف پول کسر شد');
            }
        } elseif (!empty($calc_result['transaction_id'])) {
            $deposited_amount = (float) ($calc_result['salary_amount'] ?? 0);
            $status = $deposited_amount > 0 ? 'calculated' : 'zero_amount';
        } elseif ($calc_message === 'مبلغ تغییر نکرده') {
            $status = 'no_change';
        } elseif ($calc_message === 'دستمزد قابل پرداختی وجود ندارد' || $coach_attendance_count <= 0) {
            $status = 'zero_amount';
            if ($coach_attendance_count <= 0) {
                $calc_message = $present_only_for_salary
                    ? 'هیچ حضور واجد شرایطی برای محاسبه دستمزد ثبت نشده است'
                    : 'هیچ رکورد حضور و غیابی برای محاسبه دستمزد یافت نشد';
            }
        } else {
            $status = 'no_change';
        }

        $results['coaches'][] = [
            'coach_id' => $coach_id,
            'chapter_name' => $chapter_name,
            'coach_name' => $coach_name,
            'status' => $status,
            'salary_amount' => (float) ($calc_result['salary_amount'] ?? 0),
            'gross_salary_amount' => (float) ($calc_result['gross_salary_amount'] ?? ($calc_result['salary_amount'] ?? 0)),
            'assistant_total' => (float) ($calc_result['assistant_total'] ?? 0),
            'deposited_amount' => $deposited_amount,
            'attendance_count' => $coach_attendance_count,
            'salary_percentage' => $salary_percentage,
            'price_per_session' => $price_per_session,
            'message' => $calc_message,
        ];

        // نوتیف/نتیجه کمک‌مربی‌ها
        if (!empty($calc_result['assistants']) && is_array($calc_result['assistants'])) {
            foreach ($calc_result['assistants'] as $ar) {
                $a_id = (int) ($ar['coach_id'] ?? 0);
                $a_res = isset($ar['result']) && is_array($ar['result']) ? $ar['result'] : [];
                $a_dep = (float) ($a_res['deposited_amount'] ?? 0);
                $a_name = '';
                if ($a_id > 0) {
                    $a_row = $wpdb->get_row($wpdb->prepare(
                        "SELECT first_name, last_name FROM $coaches_table WHERE id = %d LIMIT 1",
                        $a_id
                    ));
                    if ($a_row) {
                        $a_name = trim((string) $a_row->first_name . ' ' . (string) $a_row->last_name);
                    }
                }
                $a_status = 'zero_amount';
                $a_message = (string) ($a_res['message'] ?? '');
                if ($a_dep > 0) {
                    $a_status = 'calculated';
                } elseif (!empty($a_res['success']) && $a_message === 'مبلغ تغییر نکرده') {
                    $a_status = 'no_change';
                } elseif (empty($a_res['success']) && $a_message !== '') {
                    $a_status = 'error';
                }
                $results['coaches'][] = [
                    'coach_id' => $a_id,
                    'chapter_name' => $chapter_name,
                    'coach_name' => $a_name !== '' ? ($a_name . ' (کمک‌مربی)') : 'کمک‌مربی',
                    'status' => $a_status,
                    'salary_amount' => (float) ($ar['amount'] ?? ($a_res['salary_amount'] ?? 0)),
                    'deposited_amount' => $a_dep,
                    'attendance_count' => $coach_attendance_count,
                    'message' => $a_message,
                    'is_assistant' => true,
                ];
            }
        }
    }

    return $results;
}

/**
 * ارسال اطلاعیه دستمزد مربی پس از ثبت حضور و غیاب.
 *
 * @param int    $coach_id
 * @param string $type missing_price|calculated
 * @param string $title
 * @param string $content
 * @param array  $meta
 */
function sc_send_coach_salary_attendance_notification($coach_id, $type, $title, $content, $meta = []) {
    if (!function_exists('sc_save_notification') || !function_exists('sc_is_pro_feature_notifications_enabled') || !sc_is_pro_feature_notifications_enabled()) {
        return false;
    }

    $coach_id = absint($coach_id);
    if (!$coach_id) {
        return false;
    }

    $course_id = isset($meta['course_id']) ? absint($meta['course_id']) : 0;
    $attendance_date = isset($meta['attendance_date']) ? sanitize_text_field((string) $meta['attendance_date']) : '';
    $chapter_name = isset($meta['chapter_name']) ? sanitize_text_field((string) $meta['chapter_name']) : '';
    $alert_key = 'coach_salary_' . sanitize_key($type) . '_' . $coach_id . '_' . $course_id . '_' . sanitize_key($chapter_name) . '_' . str_replace('-', '', $attendance_date);

    $result = sc_save_notification([
        'title' => $title,
        'content' => $content,
        'target_type' => 'specific',
        'target_config' => [
            'recipient_ids' => ['coach_' . $coach_id],
            'alert_kind' => 'coach_salary_attendance',
            'alert_key' => $alert_key,
            'alert_meta' => $meta,
        ],
        'notification_type' => 'system',
        'send_sms' => 0,
    ]);

    return !empty($result['success']);
}

/**
 * پردازش نوتیف دستمزد مربی پس از ثبت حضور و غیاب.
 *
 * @param int    $course_id
 * @param string $attendance_date Y-m-d
 * @param string $course_title
 * @return array<int, array{type: string, message: string, coach_id: int, status?: string}>
 */
function sc_process_coach_salary_attendance_notifications($course_id, $attendance_date, $course_title = '') {
    $course_id = absint($course_id);
    $attendance_date = sanitize_text_field((string) $attendance_date);
    if (!$course_id || $attendance_date === '') {
        return [[
            'type' => 'warning',
            'coach_id' => 0,
            'status' => 'invalid_params',
            'message' => 'حقوق و دستمزد محاسبه نشد: دوره یا تاریخ حضور نامعتبر است.',
        ]];
    }

    if (!function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') || !sc_is_pro_feature_coaches_wallet_salary_enabled()) {
        return [[
            'type' => 'warning',
            'coach_id' => 0,
            'status' => 'feature_disabled',
            'message' => 'حقوق و دستمزد محاسبه نشد: قابلیت کیف پول و حقوق مربیان فعال نیست.',
        ]];
    }

    $salary_results = sc_refresh_coach_percentage_salary_for_course_date($course_id, $attendance_date);
    if (empty($salary_results['coaches']) || !is_array($salary_results['coaches'])) {
        return [[
            'type' => 'warning',
            'coach_id' => 0,
            'status' => 'no_coaches',
            'message' => 'حقوق و دستمزد محاسبه نشد: مربی واجد شرایطی برای این دوره یافت نشد.',
        ]];
    }

    $course_title = $course_title !== '' ? $course_title : ('دوره #' . $course_id);
    $attendance_date_shamsi = function_exists('sc_date_shamsi_date_only')
        ? sc_date_shamsi_date_only($attendance_date)
        : $attendance_date;

    $current_coach_id = function_exists('sc_current_user_coach_id') ? (int) sc_current_user_coach_id() : 0;
    $is_pure_coach = current_user_can('coach')
        && !current_user_can('administrator')
        && !current_user_can('club_coach');

    $notices = [];

    foreach ($salary_results['coaches'] as $coach_result) {
        $status = (string) ($coach_result['status'] ?? '');
        $coach_id = (int) ($coach_result['coach_id'] ?? 0);

        // پیام کلی بدون مربی مشخص (مثلاً no_coaches)
        if ($coach_id <= 0) {
            $raw_message = trim((string) ($coach_result['message'] ?? ''));
            $notices[] = [
                'type' => 'warning',
                'coach_id' => 0,
                'status' => $status !== '' ? $status : 'error',
                'message' => 'حقوق و دستمزد محاسبه نشد' . ($raw_message !== '' ? ': ' . $raw_message : '.') ,
            ];
            continue;
        }

        if ($is_pure_coach && $current_coach_id > 0 && $coach_id !== $current_coach_id) {
            continue;
        }

        $coach_name = trim((string) ($coach_result['coach_name'] ?? ''));
        $chapter_name = sanitize_text_field((string) ($coach_result['chapter_name'] ?? ''));
        $chapter_label = $chapter_name !== '' ? (' (شعبه: ' . $chapter_name . ')') : '';
        $name_prefix = ($is_pure_coach || $current_coach_id === $coach_id)
            ? ''
            : ($coach_name !== '' ? 'مربی ' . $coach_name . ': ' : '');
        $salary_amount = (float) ($coach_result['salary_amount'] ?? 0);
        $deposited_amount = (float) ($coach_result['deposited_amount'] ?? 0);
        $attendance_count = (int) ($coach_result['attendance_count'] ?? 0);
        $raw_message = trim((string) ($coach_result['message'] ?? ''));

        $meta = [
            'course_id' => $course_id,
            'attendance_date' => $attendance_date,
            'chapter_name' => $chapter_name,
            'course_title' => $course_title,
            'status' => $status,
        ];

        switch ($status) {
            case 'calculated':
                $amount_formatted = number_format($deposited_amount > 0 ? $deposited_amount : $salary_amount, 0, '.', ',');
                $title = 'دستمزد محاسبه شد';
                $content = sprintf(
                    'دستمزد شما برای دوره «%s»%s در تاریخ %s محاسبه شد و مبلغ %s تومان به کیف پول شما واریز شد.',
                    $course_title,
                    $chapter_label,
                    $attendance_date_shamsi,
                    $amount_formatted
                );
                sc_send_coach_salary_attendance_notification($coach_id, 'calculated', $title, $content, array_merge($meta, [
                    'deposited_amount' => $deposited_amount,
                ]));
                $notices[] = [
                    'type' => 'success',
                    'coach_id' => $coach_id,
                    'status' => $status,
                    'message' => $name_prefix . 'حقوق و دستمزد محاسبه شد' . $chapter_label . ' و مبلغ ' . $amount_formatted . ' تومان به کیف پول واریز شد.',
                ];
                break;

            case 'updated':
                $notices[] = [
                    'type' => 'success',
                    'coach_id' => $coach_id,
                    'status' => $status,
                    'message' => $name_prefix . 'حقوق و دستمزد به‌روزرسانی شد' . $chapter_label . ($raw_message !== '' ? ': ' . $raw_message : '.'),
                ];
                break;

            case 'no_change':
                $amount_formatted = number_format($salary_amount, 0, '.', ',');
                $notices[] = [
                    'type' => 'info',
                    'coach_id' => $coach_id,
                    'status' => $status,
                    'message' => $name_prefix . 'حقوق و دستمزد قبلاً محاسبه شده بود' . $chapter_label
                        . ($salary_amount > 0 ? ' (مبلغ فعلی: ' . $amount_formatted . ' تومان)' : '')
                        . ' و تغییری نداشت.',
                ];
                break;

            case 'missing_price':
                $reason = $raw_message !== '' ? $raw_message : 'قیمت هر جلسه دوره تنظیم نشده است';
                $title = 'دستمزد محاسبه نشد';
                $content = sprintf(
                    'دستمزد شما برای دوره «%s»%s در تاریخ %s محاسبه نشد. علت: %s. از طریق مدیریت این موضوع را پیگیری کنید.',
                    $course_title,
                    $chapter_label,
                    $attendance_date_shamsi,
                    $reason
                );
                sc_send_coach_salary_attendance_notification($coach_id, 'missing_price', $title, $content, $meta);
                $notices[] = [
                    'type' => 'warning',
                    'coach_id' => $coach_id,
                    'status' => $status,
                    'message' => $name_prefix . 'حقوق و دستمزد محاسبه نشد' . $chapter_label . '. علت: ' . $reason,
                ];
                break;

            case 'missing_percentage':
                $reason = $raw_message !== '' ? $raw_message : 'درصد دستمزد برای این مربی در دوره/شعبه ثبت نشده است';
                $title = 'دستمزد محاسبه نشد';
                $content = sprintf(
                    'دستمزد شما برای دوره «%s»%s در تاریخ %s محاسبه نشد. علت: %s. از طریق مدیریت این موضوع را پیگیری کنید.',
                    $course_title,
                    $chapter_label,
                    $attendance_date_shamsi,
                    $reason
                );
                sc_send_coach_salary_attendance_notification($coach_id, 'missing_percentage', $title, $content, $meta);
                $notices[] = [
                    'type' => 'warning',
                    'coach_id' => $coach_id,
                    'status' => $status,
                    'message' => $name_prefix . 'حقوق و دستمزد محاسبه نشد' . $chapter_label . '. علت: ' . $reason,
                ];
                break;

            case 'missing_chapter':
                $reason = $raw_message !== '' ? $raw_message : 'شعبه برای انتساب مربی به دوره مشخص نشده است';
                $notices[] = [
                    'type' => 'warning',
                    'coach_id' => $coach_id,
                    'status' => $status,
                    'message' => $name_prefix . 'حقوق و دستمزد محاسبه نشد. علت: ' . $reason,
                ];
                break;

            case 'skipped_settlement':
                $reason = $raw_message !== '' ? $raw_message : 'نوع تسویه مربی شامل دستمزد درصدی نیست';
                $notices[] = [
                    'type' => 'info',
                    'coach_id' => $coach_id,
                    'status' => $status,
                    'message' => $name_prefix . 'حقوق و دستمزد درصدی محاسبه نشد' . $chapter_label . '. علت: ' . $reason,
                ];
                break;

            case 'zero_amount':
                if ($raw_message !== '') {
                    $reason_text = $raw_message;
                } elseif ($attendance_count <= 0) {
                    $reason_text = 'هیچ حضور واجد شرایطی برای محاسبه دستمزد ثبت نشده است';
                } else {
                    $reason_text = 'مبلغ دستمزد صفر است';
                }
                $notices[] = [
                    'type' => 'warning',
                    'coach_id' => $coach_id,
                    'status' => $status,
                    'message' => $name_prefix . 'حقوق و دستمزد محاسبه نشد' . $chapter_label . '. علت: ' . $reason_text
                        . ($attendance_count > 0 ? ' (تعداد حضور: ' . $attendance_count . ')' : ''),
                ];
                break;

            case 'invalid_params':
            case 'error':
            default:
                $reason = $raw_message !== '' ? $raw_message : 'خطای نامشخص در محاسبه دستمزد';
                $notices[] = [
                    'type' => 'error',
                    'coach_id' => $coach_id,
                    'status' => $status !== '' ? $status : 'error',
                    'message' => $name_prefix . 'حقوق و دستمزد محاسبه نشد' . $chapter_label . '. علت: ' . $reason,
                ];
                break;
        }
    }

    if (empty($notices)) {
        $notices[] = [
            'type' => 'warning',
            'coach_id' => 0,
            'status' => 'no_visible_result',
            'message' => 'حقوق و دستمزد برای مربی فعلی نتیجه‌ای جهت نمایش نداشت.',
        ];
    }

    return $notices;
}

/**
 * Upsert یک رکورد دستمزد درصدی و همگام‌سازی اختلاف با کیف پول.
 *
 * @param int    $coach_id
 * @param int    $course_id
 * @param string $attendance_date
 * @param string $chapter_name
 * @param int    $attendance_count
 * @param float  $price_per_session
 * @param float  $total_revenue
 * @param float  $salary_percentage
 * @param float  $salary_amount
 * @param string $description_prefix
 * @return array{success:bool,message:string,salary_amount:float,difference?:float,transaction_id?:int,deposited_amount?:float}
 */
function sc_upsert_coach_percentage_salary_record(
    $coach_id,
    $course_id,
    $attendance_date,
    $chapter_name,
    $attendance_count,
    $price_per_session,
    $total_revenue,
    $salary_percentage,
    $salary_amount,
    $description_prefix = 'دستمزد درصدی'
) {
    global $wpdb;

    $coach_id = absint($coach_id);
    $course_id = absint($course_id);
    $attendance_date = sanitize_text_field((string) $attendance_date);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $attendance_count = (int) $attendance_count;
    $price_per_session = (float) $price_per_session;
    $total_revenue = (float) $total_revenue;
    $salary_percentage = (float) $salary_percentage;
    $salary_amount = max(0, (float) $salary_amount);

    if (!$coach_id || !$course_id || $attendance_date === '') {
        return ['success' => false, 'message' => 'پارامترهای نامعتبر', 'salary_amount' => 0];
    }

    $salary_records_table = $wpdb->prefix . 'sc_coach_salary_records';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $course_title = (string) $wpdb->get_var($wpdb->prepare("SELECT title FROM $courses_table WHERE id = %d", $course_id));
    $branch_label = $chapter_name !== '' ? (' - شعبه: ' . $chapter_name) : '';

    $existing_record = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $salary_records_table
         WHERE coach_id = %d AND course_id = %d AND chapter_name = %s AND attendance_date = %s LIMIT 1",
        $coach_id,
        $course_id,
        $chapter_name,
        $attendance_date
    ));

    if ($existing_record) {
        $old_record = $wpdb->get_row($wpdb->prepare(
            "SELECT salary_amount FROM $salary_records_table WHERE id = %d",
            $existing_record
        ));
        $old_amount = floatval($old_record ? $old_record->salary_amount : 0);
        $difference = $salary_amount - $old_amount;

        $wpdb->update(
            $salary_records_table,
            [
                'attendance_count' => $attendance_count,
                'price_per_session' => $price_per_session,
                'total_revenue' => $total_revenue,
                'salary_percentage' => $salary_percentage,
                'salary_amount' => $salary_amount,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $existing_record],
            ['%d', '%f', '%f', '%f', '%f', '%s'],
            ['%d']
        );

        if (abs($difference) < 0.01) {
            return [
                'success' => true,
                'message' => 'مبلغ تغییر نکرده',
                'salary_amount' => $salary_amount,
                'difference' => 0,
                'deposited_amount' => 0,
            ];
        }

        if ($difference > 0) {
            $description = sprintf(
                'بروزرسانی %s - دوره: %s%s - تاریخ: %s - افزایش: %s تومان',
                $description_prefix,
                $course_title,
                $branch_label,
                sc_date_shamsi_date_only($attendance_date),
                number_format($difference, 0, '.', ',')
            );
            sc_add_coach_wallet_transaction(
                $coach_id,
                'salary_percentage',
                $difference,
                $description,
                [
                    'course_id' => $course_id,
                    'attendance_date' => $attendance_date,
                    'salary_record_id' => (int) $existing_record,
                ]
            );
        } else {
            $description = sprintf(
                'بروزرسانی %s - دوره: %s%s - تاریخ: %s - کاهش: %s تومان',
                $description_prefix,
                $course_title,
                $branch_label,
                sc_date_shamsi_date_only($attendance_date),
                number_format(abs($difference), 0, '.', ',')
            );
            sc_deduct_coach_wallet($coach_id, abs($difference), $description);
        }

        return [
            'success' => true,
            'message' => 'دستمزد بروزرسانی شد',
            'salary_amount' => $salary_amount,
            'difference' => $difference,
            'deposited_amount' => max(0, $difference),
        ];
    }

    if ($salary_amount <= 0) {
        return [
            'success' => true,
            'message' => 'دستمزد قابل پرداختی وجود ندارد',
            'salary_amount' => 0,
            'deposited_amount' => 0,
        ];
    }

    $description = sprintf(
        '%s - دوره: %s%s - تاریخ: %s - تعداد شرکت‌کنندگان: %d',
        $description_prefix,
        $course_title,
        $branch_label,
        sc_date_shamsi_date_only($attendance_date),
        $attendance_count
    );

    $transaction_result = sc_add_coach_wallet_transaction(
        $coach_id,
        'salary_percentage',
        $salary_amount,
        $description,
        [
            'course_id' => $course_id,
            'attendance_date' => $attendance_date,
        ]
    );

    if (empty($transaction_result['success'])) {
        return ['success' => false, 'message' => 'خطا در افزودن به کیف پول', 'salary_amount' => 0];
    }

    $salary_insert = $wpdb->insert(
        $salary_records_table,
        [
            'coach_id' => $coach_id,
            'course_id' => $course_id,
            'chapter_name' => $chapter_name,
            'attendance_date' => $attendance_date,
            'attendance_count' => $attendance_count,
            'price_per_session' => $price_per_session,
            'total_revenue' => $total_revenue,
            'salary_percentage' => $salary_percentage,
            'salary_amount' => $salary_amount,
            'salary_type' => 'percentage',
            'wallet_transaction_id' => $transaction_result['transaction_id'],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        ['%d', '%d', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%s', '%d', '%s', '%s']
    );

    if ($salary_insert) {
        $wallet_table = $wpdb->prefix . 'sc_coach_wallet_transactions';
        $wpdb->update(
            $wallet_table,
            ['related_salary_record_id' => (int) $wpdb->insert_id],
            ['id' => $transaction_result['transaction_id']],
            ['%d'],
            ['%d']
        );
    }

    return [
        'success' => true,
        'message' => 'دستمزد با موفقیت محاسبه و واریز شد',
        'salary_amount' => $salary_amount,
        'transaction_id' => $transaction_result['transaction_id'],
        'deposited_amount' => $salary_amount,
        'difference' => $salary_amount,
    ];
}

/**
 * Calculate and add percentage salary for coach (per course + branch).
 * محاسبه و افزودن دستمزد درصدی مربی (+ سهم کمک‌مربی در صورت تعریف)
 *
 * @param string $chapter_name شعبه (برای دوره‌های چندشعبه)
 * @param string $group_name   گروه (اختیاری؛ برای فیلتر کمک‌مربی)
 */
function sc_calculate_coach_percentage_salary($coach_id, $course_id, $attendance_date, $attendance_count, $price_per_session, $chapter_name = '', $group_name = '') {
    $coach_id = absint($coach_id);
    $course_id = absint($course_id);
    $attendance_date = sanitize_text_field((string) $attendance_date);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $group_name = sanitize_text_field((string) $group_name);
    $attendance_count = (int) $attendance_count;
    $price_per_session = (float) $price_per_session;

    if (!$coach_id || !$course_id || $attendance_date === '' || $attendance_count < 0 || $price_per_session <= 0) {
        return ['success' => false, 'message' => 'پارامترهای نامعتبر'];
    }

    global $wpdb;
    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';

    if ($chapter_name !== '') {
        $course_coach = $wpdb->get_row($wpdb->prepare(
            "SELECT salary_percentage FROM $course_coaches_table
             WHERE coach_id = %d AND course_id = %d AND chapter_name = %s LIMIT 1",
            $coach_id,
            $course_id,
            $chapter_name
        ));
    } else {
        $course_coach = $wpdb->get_row($wpdb->prepare(
            "SELECT salary_percentage FROM $course_coaches_table
             WHERE coach_id = %d AND course_id = %d AND chapter_name = '' LIMIT 1",
            $coach_id,
            $course_id
        ));
    }

    if (!$course_coach || floatval($course_coach->salary_percentage) <= 0) {
        return ['success' => false, 'message' => 'درصد دستمزد تنظیم نشده است'];
    }

    $salary_percentage = floatval($course_coach->salary_percentage);

    // اگر دوره گروه‌بندی دارد و گروه مشخص نشده، سهم کمک‌مربی را به‌ازای هر گروه جدا حساب کن
    $use_group_split = (
        $group_name === ''
        && $chapter_name !== ''
        && function_exists('sc_course_has_grouping_enabled')
        && sc_course_has_grouping_enabled($course_id)
        && function_exists('sc_get_course_groups')
        && function_exists('sc_get_assistants_for_primary_coach')
        && function_exists('sc_split_primary_salary_with_assistants')
    );

    if ($use_group_split) {
        $calc_couch_salary = function_exists('sc_get_setting') ? sc_get_setting('calc_couch_salary') : '';
        $present_only_for_salary = !empty($calc_couch_salary);

        $group_keys = [];
        foreach ((array) sc_get_course_groups($course_id) as $grow) {
            $gname = isset($grow->group_name) ? trim((string) $grow->group_name) : '';
            if ($gname === '') {
                continue;
            }
            $g_ch = isset($grow->chapter_name) ? (string) $grow->chapter_name : '';
            $g_coach = isset($grow->coach_id) ? (int) $grow->coach_id : 0;
            if ($g_ch !== '' && $g_ch !== $chapter_name) {
                continue;
            }
            if ($g_coach > 0 && $g_coach !== $coach_id) {
                continue;
            }
            $group_keys[$gname] = true;
        }
        // اعضای بدون گروه هم در صورت وجود حضور محاسبه شوند
        $group_keys[''] = true;

        $attendance_count = 0;
        $gross_salary = 0.0;
        $primary_net_total = 0.0;
        $assistant_agg = [];

        foreach (array_keys($group_keys) as $gkey) {
            $g_count = sc_get_coach_attendance_count_for_salary(
                $coach_id,
                $course_id,
                $attendance_date,
                $present_only_for_salary,
                $chapter_name,
                $gkey
            );
            if ($g_count <= 0) {
                continue;
            }
            $g_gross = ($g_count * $price_per_session * $salary_percentage) / 100;
            $g_assistants = sc_get_assistants_for_primary_coach($course_id, $coach_id, $chapter_name, $gkey);
            $g_split = sc_split_primary_salary_with_assistants($g_gross, $g_assistants);

            $attendance_count += $g_count;
            $gross_salary += $g_gross;
            $primary_net_total += (float) $g_split['primary_net'];

            foreach ($g_split['assistants'] as $ga) {
                $aid = (int) $ga['coach_id'];
                if ($aid < 1) {
                    continue;
                }
                if (!isset($assistant_agg[$aid])) {
                    $assistant_agg[$aid] = [
                        'coach_id' => $aid,
                        'share_percentage' => 0.0,
                        'amount' => 0.0,
                        'name' => (string) ($ga['name'] ?? ''),
                        '_gross_base' => 0.0,
                    ];
                }
                $assistant_agg[$aid]['amount'] += (float) $ga['amount'];
                $assistant_agg[$aid]['_gross_base'] += $g_gross;
                if ($assistant_agg[$aid]['name'] === '' && !empty($ga['name'])) {
                    $assistant_agg[$aid]['name'] = (string) $ga['name'];
                }
            }
        }

        foreach ($assistant_agg as $aid => $row) {
            $base = (float) $row['_gross_base'];
            $assistant_agg[$aid]['share_percentage'] = $base > 0
                ? round(((float) $row['amount'] / $base) * 100, 2)
                : 0.0;
            unset($assistant_agg[$aid]['_gross_base']);
        }

        $total_revenue = $attendance_count * $price_per_session;
        $split = [
            'primary_net' => $primary_net_total,
            'assistants' => array_values($assistant_agg),
        ];
    } else {
        $total_revenue = $attendance_count * $price_per_session;
        $gross_salary = ($total_revenue * $salary_percentage) / 100;

        $assistants = [];
        if ($chapter_name !== '' && function_exists('sc_get_assistants_for_primary_coach')) {
            $assistants = sc_get_assistants_for_primary_coach($course_id, $coach_id, $chapter_name, $group_name);
        }

        $split = function_exists('sc_split_primary_salary_with_assistants')
            ? sc_split_primary_salary_with_assistants($gross_salary, $assistants)
            : ['primary_net' => $gross_salary, 'assistants' => []];
    }

    $payout_mode = function_exists('sc_get_assistant_salary_payout_mode')
        ? sc_get_assistant_salary_payout_mode()
        : 'direct';

    $assistant_total = 0.0;
    foreach ($split['assistants'] as $a) {
        $assistant_total += (float) $a['amount'];
    }

    // مبلغی که در رکورد مربی اصلی ذخیره می‌شود
    $primary_record_amount = ($payout_mode === 'via_primary')
        ? $gross_salary
        : (float) $split['primary_net'];

    $primary_desc = empty($split['assistants'])
        ? 'دستمزد درصدی'
        : ($payout_mode === 'via_primary'
            ? 'دستمزد درصدی (ناخالص قبل از سهم کمک‌مربی)'
            : 'دستمزد درصدی (خالص پس از سهم کمک‌مربی)');

    $primary_result = sc_upsert_coach_percentage_salary_record(
        $coach_id,
        $course_id,
        $attendance_date,
        $chapter_name,
        $attendance_count,
        $price_per_session,
        $total_revenue,
        $salary_percentage,
        $primary_record_amount,
        $primary_desc
    );

    if (empty($primary_result['success'])) {
        return $primary_result;
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    $course_title = (string) $wpdb->get_var($wpdb->prepare("SELECT title FROM $courses_table WHERE id = %d", $course_id));
    $branch_label = $chapter_name !== '' ? (' - شعبه: ' . $chapter_name) : '';
    $assistant_results = [];
    $assistant_deposited = 0.0;

    foreach ($split['assistants'] as $a) {
        $assistant_id = (int) $a['coach_id'];
        $assistant_amount = (float) $a['amount'];
        $assistant_pct = (float) $a['share_percentage'];
        $assistant_name = (string) ($a['name'] ?? '');

        // درصد ذخیره‌شده برای کمک‌مربی = درصد از سهم مربی اصلی (نه از کل درآمد کلاس)
        $assistant_result = sc_upsert_coach_percentage_salary_record(
            $assistant_id,
            $course_id,
            $attendance_date,
            $chapter_name,
            $attendance_count,
            $price_per_session,
            $gross_salary,
            $assistant_pct,
            $assistant_amount,
            'دستمزد کمک‌مربی (سهم از مربی اصلی' . ($assistant_name !== '' ? ': ' . $assistant_name : '') . ')'
        );

        if ($payout_mode === 'via_primary' && $assistant_amount > 0 && !empty($assistant_result['success'])) {
            // در حالت via_primary کل ناخالص قبلاً به مربی اصلی رفته؛ سهم کمک‌مربی را از او کم کن
            // فقط وقتی مبلغ کمک‌مربی افزایش یافته / اولین واریز است، کسر متناسب انجام می‌شود
            $assistant_diff = isset($assistant_result['difference'])
                ? (float) $assistant_result['difference']
                : (float) ($assistant_result['deposited_amount'] ?? 0);

            if (abs($assistant_diff) >= 0.01) {
                if ($assistant_diff > 0) {
                    $deduct_desc = sprintf(
                        'انتقال سهم کمک‌مربی به %s - دوره: %s%s - تاریخ: %s - مبلغ: %s تومان',
                        $assistant_name !== '' ? $assistant_name : ('#' . $assistant_id),
                        $course_title,
                        $branch_label,
                        sc_date_shamsi_date_only($attendance_date),
                        number_format($assistant_diff, 0, '.', ',')
                    );
                    sc_deduct_coach_wallet($coach_id, $assistant_diff, $deduct_desc);
                } else {
                    // کاهش سهم کمک‌مربی → برگشت به مربی اصلی
                    $refund_desc = sprintf(
                        'برگشت کاهش سهم کمک‌مربی %s - دوره: %s%s - تاریخ: %s - مبلغ: %s تومان',
                        $assistant_name !== '' ? $assistant_name : ('#' . $assistant_id),
                        $course_title,
                        $branch_label,
                        sc_date_shamsi_date_only($attendance_date),
                        number_format(abs($assistant_diff), 0, '.', ',')
                    );
                    sc_add_coach_wallet_transaction(
                        $coach_id,
                        'salary_percentage',
                        abs($assistant_diff),
                        $refund_desc,
                        [
                            'course_id' => $course_id,
                            'attendance_date' => $attendance_date,
                        ]
                    );
                }
            }
        }

        $assistant_results[] = [
            'coach_id' => $assistant_id,
            'amount' => $assistant_amount,
            'result' => $assistant_result,
        ];
        $assistant_deposited += (float) ($assistant_result['deposited_amount'] ?? 0);
    }

    // صفر کردن رکورد کمک‌مربی‌هایی که دیگر در لیست نیستند (حذف شده‌اند)
    if (function_exists('sc_course_assistant_coaches_table_ready') && sc_course_assistant_coaches_table_ready() && $chapter_name !== '') {
        $salary_records_table = $wpdb->prefix . 'sc_coach_salary_records';
        $active_assistant_ids = array_map(static function ($a) {
            return (int) $a['coach_id'];
        }, $split['assistants']);

        $prev_assistant_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, coach_id, salary_amount FROM $salary_records_table
             WHERE course_id = %d AND chapter_name = %s AND attendance_date = %s
               AND coach_id != %d AND salary_type = 'percentage' AND salary_amount > 0",
            $course_id,
            $chapter_name,
            $attendance_date,
            $coach_id
        ));

        foreach ((array) $prev_assistant_rows as $prev) {
            $prev_coach_id = (int) $prev->coach_id;
            if (in_array($prev_coach_id, $active_assistant_ids, true)) {
                continue;
            }
            // فقط اگر این مربی واقعاً کمک‌مربی این کلاس بوده (نه مربی اصلی شعبه دیگر)
            if (function_exists('sc_coach_is_assistant_only_for_course_chapter')
                && !sc_coach_is_assistant_only_for_course_chapter($course_id, $prev_coach_id, $chapter_name)) {
                continue;
            }
            sc_upsert_coach_percentage_salary_record(
                $prev_coach_id,
                $course_id,
                $attendance_date,
                $chapter_name,
                $attendance_count,
                $price_per_session,
                0,
                0,
                0,
                'حذف سهم کمک‌مربی'
            );
        }
    }

    $primary_deposited = (float) ($primary_result['deposited_amount'] ?? 0);
    if ($payout_mode === 'via_primary') {
        // در via_primary، deposited مربی اصلی = ناخالص منهای سهم‌های منتقل‌شده
        $primary_net_deposited = max(0, $primary_deposited - $assistant_deposited);
    } else {
        $primary_net_deposited = $primary_deposited;
    }

    return [
        'success' => true,
        'message' => empty($split['assistants'])
            ? (string) ($primary_result['message'] ?? 'دستمزد محاسبه شد')
            : 'دستمزد مربی اصلی و کمک‌مربی‌ها محاسبه شد',
        'salary_amount' => (float) $split['primary_net'],
        'gross_salary_amount' => $gross_salary,
        'assistant_total' => $assistant_total,
        'payout_mode' => $payout_mode,
        'difference' => isset($primary_result['difference']) ? (float) $primary_result['difference'] : null,
        'transaction_id' => $primary_result['transaction_id'] ?? null,
        'deposited_amount' => $primary_net_deposited,
        'assistants' => $assistant_results,
    ];
}

/**
 * Calculate and add fixed salary for coach (monthly)
 * محاسبه و افزودن دستمزد ثابت مربی (ماهیانه)
 */
function sc_calculate_coach_fixed_salary($coach_id, $month_year_shamsi) {
    if (!$coach_id || !$month_year_shamsi) {
        return ['success' => false, 'message' => 'پارامترهای نامعتبر'];
    }
    
    global $wpdb;
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $salary_records_table = $wpdb->prefix . 'sc_coach_salary_records';
    
    // دریافت اطلاعات مربی
    $coach = $wpdb->get_row($wpdb->prepare(
        "SELECT settlement_type, settlement_amount FROM $coaches_table WHERE id = %d LIMIT 1",
        $coach_id
    ));
    
    if (!$coach || !sc_coach_settlement_includes_fixed($coach->settlement_type) || floatval($coach->settlement_amount) <= 0) {
        return ['success' => false, 'message' => 'دستمزد ثابت تنظیم نشده است'];
    }
    
    $salary_amount = floatval($coach->settlement_amount);
    
    // تبدیل ماه شمسی به میلادی برای جستجو
    // month_year_shamsi به فرمت YYYY/MM است
    $parts = explode('/', $month_year_shamsi);
    if (count($parts) !== 2) {
        return ['success' => false, 'message' => 'فرمت تاریخ نامعتبر است'];
    }
    $year_shamsi = intval($parts[0]);
    $month_shamsi = intval($parts[1]);
    
    // تبدیل اول ماه شمسی به میلادی
    $first_day_shamsi = sprintf('%04d/%02d/01', $year_shamsi, $month_shamsi);
    $first_day_gregorian = sc_shamsi_to_gregorian_date($first_day_shamsi);
    
    if (!$first_day_gregorian) {
        return ['success' => false, 'message' => 'خطا در تبدیل تاریخ'];
    }
    
    // بررسی وجود رکورد قبلی برای این ماه (بر اساس سال و ماه میلادی)
    $month_start = date('Y-m-01', strtotime($first_day_gregorian));
    $month_end = date('Y-m-t', strtotime($first_day_gregorian));
    
    $existing_record = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $salary_records_table 
         WHERE coach_id = %d AND salary_type = 'fixed' 
         AND attendance_date >= %s AND attendance_date <= %s LIMIT 1",
        $coach_id,
        $month_start,
        $month_end
    ));
    
    if ($existing_record) {
        return ['success' => false, 'message' => 'دستمزد این ماه قبلاً محاسبه شده است'];
    }
    
    // افزودن به کیف پول
    $description = sprintf('دستمزد ثابت - ماه: %s', $month_year_shamsi);
    $transaction_result = sc_add_coach_wallet_transaction(
        $coach_id,
        'salary_fixed',
        $salary_amount,
        $description
    );
    
    if (!$transaction_result['success']) {
        return ['success' => false, 'message' => 'خطا در افزودن به کیف پول'];
    }
    
    // ایجاد رکورد دستمزد (attendance_date را اول ماه میلادی قرار می‌دهیم)
    $salary_data = [
        'coach_id' => $coach_id,
        'course_id' => 0,
        'chapter_name' => '',
        'attendance_date' => $first_day_gregorian,
        'attendance_count' => 0,
        'price_per_session' => 0,
        'total_revenue' => 0,
        'salary_percentage' => 0,
        'salary_amount' => $salary_amount,
        'salary_type' => 'fixed',
        'wallet_transaction_id' => $transaction_result['transaction_id'],
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    $salary_insert = $wpdb->insert(
        $salary_records_table,
        $salary_data,
        ['%d', '%d', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%s', '%d', '%s', '%s']
    );
    
    if ($salary_insert) {
        $salary_record_id = $wpdb->insert_id;
        // بروزرسانی تراکنش کیف پول با شناسه رکورد دستمزد
        $wallet_table = $wpdb->prefix . 'sc_coach_wallet_transactions';
        $wpdb->update(
            $wallet_table,
            ['related_salary_record_id' => $salary_record_id],
            ['id' => $transaction_result['transaction_id']],
            ['%d'],
            ['%d']
        );
    }
    
    return [
        'success' => true,
        'message' => 'دستمزد ثابت با موفقیت محاسبه و واریز شد',
        'salary_amount' => $salary_amount,
        'transaction_id' => $transaction_result['transaction_id']
    ];
}

/**
 * Create withdrawal request
 * ایجاد درخواست برداشت
 */
function sc_create_coach_withdrawal_request($coach_id, $amount, $notes = '') {
    if (!$coach_id || !$amount || $amount <= 0) {
        return ['success' => false, 'message' => 'پارامترهای نامعتبر'];
    }
    
    $balance = sc_get_coach_wallet_balance($coach_id);
    
    // بررسی حداقل مبلغ برداشت
    $min_withdrawal = floatval(sc_get_setting('coach_min_withdrawal_amount', '0'));
    if ($amount < $min_withdrawal) {
        return [
            'success' => false,
            'message' => sprintf('حداقل مبلغ برداشت %s تومان است', number_format($min_withdrawal, 0, '.', ','))
        ];
    }
    
    global $wpdb;
    
    // کسر فوری از کیف پول هنگام ثبت درخواست
    $deduct_result = sc_deduct_coach_wallet(
        $coach_id,
        floatval($amount),
        'درخواست برداشت'
    );
    
    if (!$deduct_result['success']) {
        return ['success' => false, 'message' => $deduct_result['message'] ?? 'خطا در کسر از کیف پول'];
    }
    
    $table_name = $wpdb->prefix . 'sc_coach_withdrawal_requests';
    
    $data = [
        'coach_id' => $coach_id,
        'amount' => floatval($amount),
        'balance_before' => $balance,
        'status' => 'pending',
        'notes' => $notes,
        'wallet_transaction_id' => $deduct_result['transaction_id'],
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    $result = $wpdb->insert($table_name, $data, ['%d', '%f', '%f', '%s', '%s', '%d', '%s', '%s']);
    
    if ($result) {
        return [
            'success' => true,
            'request_id' => $wpdb->insert_id,
            'message' => 'درخواست برداشت ثبت شد و مبلغ از کیف پول کسر شد'
        ];
    }
    
    // در صورت خطا در ثبت، مبلغ را به کیف پول برگردان
    if (function_exists('sc_add_coach_wallet_transaction')) {
        sc_add_coach_wallet_transaction($coach_id, 'charge', floatval($amount), 'برگشت مبلغ - خطا در ثبت درخواست برداشت');
    }
    return ['success' => false, 'message' => 'خطا در ثبت درخواست'];
}

/**
 * Approve withdrawal request
 * تایید درخواست برداشت (وضعیت: منتظر پرداخت)
 *
 * - اگر وضعیت فعلی pending باشد: فقط به approved تغییر می‌کند (بدون تغییر کیف پول).
 * - اگر وضعیت فعلی rejected باشد: مبلغ دوباره از کیف پول مربی کسر می‌شود و سپس وضعیت approved می‌شود.
 */
function sc_approve_coach_withdrawal_request($request_id) {
    if (!$request_id) {
        return ['success' => false, 'message' => 'شناسه درخواست نامعتبر'];
    }
    
    global $wpdb;
    $requests_table = $wpdb->prefix . 'sc_coach_withdrawal_requests';
    
    // اجازه تایید برای وضعیت‌های pending و rejected
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $requests_table WHERE id = %d AND status IN ('pending','rejected') LIMIT 1",
        $request_id
    ));
    
    if (!$request) {
        return ['success' => false, 'message' => 'درخواست یافت نشد یا قبلاً پردازش شده است'];
    }
    
    // اگر قبلاً رد شده بود، مبلغ را دوباره از کیف پول کسر کن
    if ($request->status === 'rejected') {
        $deduct = sc_deduct_coach_wallet(
            $request->coach_id,
            floatval($request->amount),
            sprintf('کسر مجدد برای تایید دوباره درخواست برداشت #%d', $request_id)
        );
        
        if (!$deduct['success']) {
            return ['success' => false, 'message' => $deduct['message'] ?? 'خطا در کسر مبلغ از کیف پول هنگام تایید مجدد'];
        }
    }
    
    // بروزرسانی وضعیت به approved
    $wpdb->update(
        $requests_table,
        [
            'status' => 'approved',
            'approved_by' => get_current_user_id(),
            'updated_at' => current_time('mysql')
        ],
        ['id' => $request_id],
        ['%s', '%d', '%s'],
        ['%d']
    );
    if (function_exists('sc_log_activity')) {
        sc_log_activity('updated', 'withdrawal', $request_id, 'درخواست برداشت #' . $request_id . ' تایید شد', ['status' => $request->status], ['status' => 'approved']);
    }
    return [
        'success' => true,
        'message' => 'درخواست تایید شد و منتظر پرداخت است'
    ];
}

/**
 * Reject withdrawal request
 * رد درخواست برداشت - در صورت کسر قبلی، مبلغ به کیف پول برمی‌گردد
 */
function sc_reject_coach_withdrawal_request($request_id, $rejection_reason = '') {
    if (!$request_id) {
        return ['success' => false, 'message' => 'شناسه درخواست نامعتبر'];
    }
    
    global $wpdb;
    $requests_table = $wpdb->prefix . 'sc_coach_withdrawal_requests';
    
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $requests_table WHERE id = %d AND status IN ('pending', 'approved') LIMIT 1",
        $request_id
    ));
    
    if ($request) {
        // مبلغ هنگام ثبت درخواست کسر شده - برگشت به کیف پول
        $refund = sc_add_coach_wallet_transaction(
            $request->coach_id,
            'charge',
            floatval($request->amount),
            sprintf('برگشت مبلغ - رد درخواست برداشت #%d', $request_id)
        );
    }
    
    $wpdb->update(
        $requests_table,
        [
            'status' => 'rejected',
            'rejection_reason' => $rejection_reason,
            'updated_at' => current_time('mysql')
        ],
        ['id' => $request_id],
        ['%s', '%s', '%s'],
        ['%d']
    );
    if (function_exists('sc_log_activity') && $request) {
        sc_log_activity('updated', 'withdrawal', $request_id, 'درخواست برداشت #' . $request_id . ' رد شد', ['status' => $request->status], ['status' => 'rejected']);
    }
    return ['success' => true, 'message' => 'درخواست رد شد و مبلغ به کیف پول برگشت داده شد'];
}

/**
 * Delete withdrawal request
 * حذف درخواست برداشت - در صورت لزوم، مبلغ به کیف پول برگردانده می‌شود
 */
function sc_delete_coach_withdrawal_request($request_id) {
    if (!$request_id) {
        return ['success' => false, 'message' => 'شناسه درخواست نامعتبر'];
    }
    
    global $wpdb;
    $requests_table = $wpdb->prefix . 'sc_coach_withdrawal_requests';
    
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $requests_table WHERE id = %d LIMIT 1",
        $request_id
    ));
    
    if (!$request) {
        return ['success' => false, 'message' => 'درخواست یافت نشد'];
    }
    
    // برگشت مبلغ به کیف پول:
    // - برای وضعیت‌های pending / approved / paid: مبلغ هنوز به طور نهایی در سیستم تسویه نشده، پس برگردان.
    // - برای وضعیت rejected: قبلاً در رد کردن مبلغ برگشت داده شده، پس دوباره برنمی‌گردانیم.
    if (in_array($request->status, ['pending', 'approved', 'paid'], true)) {
        sc_add_coach_wallet_transaction(
            $request->coach_id,
            'charge',
            floatval($request->amount),
            sprintf('برگشت مبلغ - حذف درخواست برداشت #%d', $request_id)
        );
    }
    
    // حذف خود درخواست
    $deleted = $wpdb->delete(
        $requests_table,
        ['id' => $request_id],
        ['%d']
    );
    
    if ($deleted) {
        return ['success' => true, 'message' => 'درخواست با موفقیت حذف شد و مبلغ به کیف پول برگشت داده شد'];
    }
    
    return ['success' => false, 'message' => 'خطا در حذف درخواست'];
}

/**
 * Mark withdrawal request as paid
 * علامت‌گذاری درخواست برداشت به عنوان پرداخت شده (مبلغ قبلاً هنگام ثبت درخواست کسر شده)
 *
 * @param int    $request_id   شناسه درخواست برداشت
 * @param string $payment_note توضیحات/اطلاعات پرداخت (اختیاری)
 */
function sc_mark_coach_withdrawal_paid($request_id, $payment_note = '') {
    if (!$request_id) {
        return ['success' => false, 'message' => 'شناسه درخواست نامعتبر'];
    }
    
    global $wpdb;
    $requests_table = $wpdb->prefix . 'sc_coach_withdrawal_requests';
    
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $requests_table WHERE id = %d AND status = 'approved' LIMIT 1",
        $request_id
    ));
    
    if (!$request) {
        return ['success' => false, 'message' => 'درخواست یافت نشد یا تایید نشده است'];
    }
    
    // آماده‌سازی یادداشت پرداخت (در صورت وجود)
    $update_data = [
        'status'    => 'paid',
        'paid_by'   => get_current_user_id(),
        'paid_at'   => current_time('mysql'),
        'updated_at'=> current_time('mysql'),
    ];

    $update_format = ['%s', '%d', '%s', '%s'];

    if (!empty($payment_note)) {
        $combined_note = trim(($request->notes ? $request->notes . ' | ' : '') . $payment_note);
        $update_data['notes'] = $combined_note;
        $update_format[] = '%s';
    }

    // مبلغ قبلاً هنگام ثبت درخواست کسر شده - فقط وضعیت را به پرداخت شده تغییر می‌دهیم
    $wpdb->update(
        $requests_table,
        $update_data,
        ['id' => $request_id],
        $update_format,
        ['%d']
    );
    if (function_exists('sc_log_activity') && $request) {
        sc_log_activity('updated', 'withdrawal', $request_id, 'درخواست برداشت #' . $request_id . ' پرداخت شده ثبت شد', ['status' => $request->status], ['status' => 'paid']);
    }
    return ['success' => true, 'message' => 'درخواست به عنوان پرداخت شده علامت‌گذاری شد'];
}
