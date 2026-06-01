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
 * Count attendance records belonging to a specific coach in a course/date.
 * شمارش حضور/غیاب بازیکن‌های اختصاص‌یافته به یک مربی در یک دوره/تاریخ
 */
function sc_get_coach_attendance_count_for_salary($coach_id, $course_id, $attendance_date, $present_only = true) {
    $coach_id = absint($coach_id);
    $course_id = absint($course_id);
    $attendance_date = sanitize_text_field($attendance_date);

    if (!$coach_id || !$course_id || $attendance_date === '') {
        return 0;
    }

    global $wpdb;
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $status_where = $present_only ? " AND a.status = 'present' " : '';
    $coach_scope_where = "mc.coach_id = %d";
    $prepare_args = [$course_id, $attendance_date, $coach_id];

    // Fallback: اگر دوره فقط یک مربی فعال دارد، رکوردهای بدون coach_id هم متعلق به همان مربی حساب شود.
    $single_active_coach_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT CASE WHEN COUNT(DISTINCT cc.coach_id) = 1 THEN MIN(cc.coach_id) ELSE 0 END
         FROM $course_coaches_table cc
         INNER JOIN $coaches_table c ON c.id = cc.coach_id
         WHERE cc.course_id = %d AND c.is_active = 1",
        $course_id
    ));

    if ($single_active_coach_id > 0 && $single_active_coach_id === $coach_id) {
        $coach_scope_where = "(mc.coach_id = %d OR mc.coach_id IS NULL OR mc.coach_id = 0)";
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
        "SELECT price_per_session FROM $courses_table WHERE id = %d LIMIT 1",
        $course_id
    ));
    $price_per_session = $course_row ? floatval($course_row->price_per_session) : 0;
    $results['price_per_session'] = $price_per_session;

    $calc_couch_salary = function_exists('sc_get_setting') ? sc_get_setting('calc_couch_salary') : '';
    $present_only_for_salary = !empty($calc_couch_salary);

    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $coaches = $wpdb->get_results($wpdb->prepare(
        "SELECT cc.coach_id, cc.salary_percentage, c.settlement_type, c.first_name, c.last_name
         FROM $course_coaches_table cc
         INNER JOIN $coaches_table c ON cc.coach_id = c.id
         WHERE cc.course_id = %d AND c.settlement_type IN ('percentage', 'both') AND c.is_active = 1",
        $course_id
    ));

    foreach ($coaches as $coach) {
        $coach_id = (int) $coach->coach_id;
        $coach_name = trim((string) $coach->first_name . ' ' . (string) $coach->last_name);

        if (floatval($coach->salary_percentage) <= 0 || !function_exists('sc_get_coach_attendance_count_for_salary')) {
            continue;
        }

        if ($price_per_session <= 0) {
            $results['coaches'][] = [
                'coach_id' => $coach_id,
                'coach_name' => $coach_name,
                'status' => 'missing_price',
                'salary_amount' => 0,
                'deposited_amount' => 0,
                'message' => 'قیمت هر جلسه دوره تنظیم نشده است',
            ];
            continue;
        }

        $coach_attendance_count = sc_get_coach_attendance_count_for_salary(
            $coach_id,
            $course_id,
            $attendance_date,
            $present_only_for_salary
        );
        $calc_result = sc_calculate_coach_percentage_salary(
            $coach_id,
            $course_id,
            $attendance_date,
            $coach_attendance_count,
            $price_per_session
        );

        $deposited_amount = 0;
        $status = 'error';

        if (!empty($calc_result['success'])) {
            if (isset($calc_result['difference']) && abs((float) $calc_result['difference']) >= 0.01) {
                $difference = (float) $calc_result['difference'];
                if ($difference > 0) {
                    $deposited_amount = $difference;
                    $status = 'calculated';
                } else {
                    $status = 'updated';
                }
            } elseif (!empty($calc_result['transaction_id'])) {
                $deposited_amount = (float) ($calc_result['salary_amount'] ?? 0);
                $status = $deposited_amount > 0 ? 'calculated' : 'zero_amount';
            } elseif (($calc_result['message'] ?? '') === 'مبلغ تغییر نکرده') {
                $status = 'no_change';
            } elseif (($calc_result['message'] ?? '') === 'دستمزد قابل پرداختی وجود ندارد') {
                $status = 'zero_amount';
            } else {
                $status = 'no_change';
            }
        }

        $results['coaches'][] = [
            'coach_id' => $coach_id,
            'coach_name' => $coach_name,
            'status' => $status,
            'salary_amount' => (float) ($calc_result['salary_amount'] ?? 0),
            'deposited_amount' => $deposited_amount,
            'message' => (string) ($calc_result['message'] ?? ''),
        ];
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
    $alert_key = 'coach_salary_' . sanitize_key($type) . '_' . $coach_id . '_' . $course_id . '_' . str_replace('-', '', $attendance_date);

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
 * @return array<int, array{type: string, message: string, coach_id: int}>
 */
function sc_process_coach_salary_attendance_notifications($course_id, $attendance_date, $course_title = '') {
    if (!function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') || !sc_is_pro_feature_coaches_wallet_salary_enabled()) {
        return [];
    }

    $course_id = absint($course_id);
    $attendance_date = sanitize_text_field((string) $attendance_date);
    if (!$course_id || $attendance_date === '') {
        return [];
    }

    $salary_results = sc_refresh_coach_percentage_salary_for_course_date($course_id, $attendance_date);
    if (empty($salary_results['coaches']) || !is_array($salary_results['coaches'])) {
        return [];
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
        $coach_id = (int) ($coach_result['coach_id'] ?? 0);
        if (!$coach_id) {
            continue;
        }

        if ($is_pure_coach && $current_coach_id > 0 && $coach_id !== $current_coach_id) {
            continue;
        }

        $coach_name = trim((string) ($coach_result['coach_name'] ?? ''));
        $name_prefix = ($is_pure_coach || $current_coach_id === $coach_id) ? '' : ($coach_name !== '' ? 'مربی ' . $coach_name . ': ' : '');

        if (($coach_result['status'] ?? '') === 'missing_price') {
            $title = 'دستمزد محاسبه نشد';
            $content = sprintf(
                'دستمزد شما برای دوره «%s» در تاریخ %s به علت نداشتن قیمت هر جلسه محاسبه نشد. از طریق مدیریت این موضوع را پیگیری کنید.',
                $course_title,
                $attendance_date_shamsi
            );

            sc_send_coach_salary_attendance_notification($coach_id, 'missing_price', $title, $content, [
                'course_id' => $course_id,
                'attendance_date' => $attendance_date,
                'course_title' => $course_title,
            ]);

            $notices[] = [
                'type' => 'warning',
                'coach_id' => $coach_id,
                'message' => $name_prefix . 'دستمزد شما به علت نداشتن قیمت هر جلسه محاسبه نشد. از طریق مدیریت این موضوع را پیگیری کنید.',
            ];
            continue;
        }

        if (($coach_result['status'] ?? '') === 'calculated' && (float) ($coach_result['deposited_amount'] ?? 0) > 0) {
            $deposited_amount = (float) $coach_result['deposited_amount'];
            $amount_formatted = number_format($deposited_amount, 0, '.', ',');
            $title = 'دستمزد محاسبه شد';
            $content = sprintf(
                'دستمزد شما برای دوره «%s» در تاریخ %s محاسبه شد و مبلغ %s تومان به کیف پول شما واریز شد.',
                $course_title,
                $attendance_date_shamsi,
                $amount_formatted
            );

            sc_send_coach_salary_attendance_notification($coach_id, 'calculated', $title, $content, [
                'course_id' => $course_id,
                'attendance_date' => $attendance_date,
                'course_title' => $course_title,
                'deposited_amount' => $deposited_amount,
            ]);

            $notices[] = [
                'type' => 'success',
                'coach_id' => $coach_id,
                'message' => $name_prefix . 'دستمزد شما محاسبه شد و مبلغ ' . $amount_formatted . ' تومان به کیف پول شما واریز شد.',
            ];
        }
    }

    return $notices;
}

/**
 * Calculate and add percentage salary for coach
 * محاسبه و افزودن دستمزد درصدی مربی
 */
function sc_calculate_coach_percentage_salary($coach_id, $course_id, $attendance_date, $attendance_count, $price_per_session) {
    if (!$coach_id || !$course_id || !$attendance_date || $attendance_count < 0 || $price_per_session <= 0) {
        return ['success' => false, 'message' => 'پارامترهای نامعتبر'];
    }
    
    global $wpdb;
    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    $salary_records_table = $wpdb->prefix . 'sc_coach_salary_records';
    
    // دریافت درصد دستمزد مربی برای این دوره
    $course_coach = $wpdb->get_row($wpdb->prepare(
        "SELECT salary_percentage FROM $course_coaches_table 
         WHERE coach_id = %d AND course_id = %d LIMIT 1",
        $coach_id,
        $course_id
    ));
    
    if (!$course_coach || floatval($course_coach->salary_percentage) <= 0) {
        return ['success' => false, 'message' => 'درصد دستمزد تنظیم نشده است'];
    }
    
    $salary_percentage = floatval($course_coach->salary_percentage);
    $total_revenue = $attendance_count * $price_per_session;
    $salary_amount = ($total_revenue * $salary_percentage) / 100;
    
    // بررسی وجود رکورد قبلی (برای جلوگیری از محاسبه مجدد)
    $existing_record = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $salary_records_table 
         WHERE coach_id = %d AND course_id = %d AND attendance_date = %s LIMIT 1",
        $coach_id,
        $course_id,
        $attendance_date
    ));
    
    if ($existing_record) {
        // بروزرسانی رکورد موجود
        $old_record = $wpdb->get_row($wpdb->prepare(
            "SELECT salary_amount, wallet_transaction_id FROM $salary_records_table WHERE id = %d",
            $existing_record
        ));
        
        $old_amount = floatval($old_record->salary_amount);
        $difference = $salary_amount - $old_amount;
        
        if (abs($difference) < 0.01) {
            // مبلغ تغییر نکرده
            return ['success' => true, 'message' => 'مبلغ تغییر نکرده', 'salary_amount' => $salary_amount];
        }
        
        // بروزرسانی رکورد
        $wpdb->update(
            $salary_records_table,
            [
                'attendance_count' => $attendance_count,
                'price_per_session' => $price_per_session,
                'total_revenue' => $total_revenue,
                'salary_amount' => $salary_amount,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $existing_record],
            ['%d', '%f', '%f', '%f', '%s'],
            ['%d']
        );
        
        // مبلغ تغییر کرده: همیشه تفاوت را در کیف پول اعمال کن (حتی اگر wallet_transaction_id خالی باشد)
        if (abs($difference) >= 0.01) {
            // ایجاد تراکنش جدید با مبلغ تفاوت
            $courses_table = $wpdb->prefix . 'sc_courses';
            $course = $wpdb->get_var($wpdb->prepare("SELECT title FROM $courses_table WHERE id = %d", $course_id));
            
            if ($difference > 0) {
                // مبلغ افزایش یافته - تراکنش مثبت
                $description = sprintf('بروزرسانی دستمزد درصدی - دوره: %s - تاریخ: %s - افزایش: %s تومان', 
                    $course, sc_date_shamsi_date_only($attendance_date), number_format($difference, 0, '.', ','));
                
                $transaction_result = sc_add_coach_wallet_transaction(
                    $coach_id,
                    'salary_percentage',
                    $difference,
                    $description,
                    [
                        'course_id' => $course_id,
                        'attendance_date' => $attendance_date,
                        'salary_record_id' => $existing_record
                    ]
                );
            } else {
                // مبلغ کاهش یافته - تراکنش منفی (کسر)
                $description = sprintf('بروزرسانی دستمزد درصدی - دوره: %s - تاریخ: %s - کاهش: %s تومان', 
                    $course, sc_date_shamsi_date_only($attendance_date), number_format(abs($difference), 0, '.', ','));
                
                $transaction_result = sc_deduct_coach_wallet(
                    $coach_id,
                    abs($difference),
                    $description
                );
            }
            
            // بروزرسانی wallet_transaction_id در رکورد دستمزد (به آخرین تراکنش)
            if (isset($transaction_result['success']) && $transaction_result['success']) {
                // wallet_transaction_id را به آخرین تراکنش مرتبط با این رکورد تنظیم می‌کنیم
                // یا می‌توانیم آن را null بگذاریم چون چند تراکنش داریم
            }
        }
        
        return [
            'success' => true,
            'message' => 'دستمزد بروزرسانی شد',
            'salary_amount' => $salary_amount,
            'difference' => $difference
        ];
    }
    
    // اگر رکوردی وجود ندارد و مبلغ صفر است، چیزی ایجاد نشود.
    if ($salary_amount <= 0) {
        return ['success' => true, 'message' => 'دستمزد قابل پرداختی وجود ندارد', 'salary_amount' => 0];
    }

    // ایجاد رکورد جدید
    $courses_table = $wpdb->prefix . 'sc_courses';
    $course = $wpdb->get_var($wpdb->prepare("SELECT title FROM $courses_table WHERE id = %d", $course_id));
    $description = sprintf('دستمزد درصدی - دوره: %s - تاریخ: %s - تعداد شرکت‌کنندگان: %d', 
        $course, sc_date_shamsi_date_only($attendance_date), $attendance_count);
    
    // افزودن به کیف پول
    $transaction_result = sc_add_coach_wallet_transaction(
        $coach_id,
        'salary_percentage',
        $salary_amount,
        $description,
        [
            'course_id' => $course_id,
            'attendance_date' => $attendance_date
        ]
    );
    
    if (!$transaction_result['success']) {
        return ['success' => false, 'message' => 'خطا در افزودن به کیف پول'];
    }
    
    // ایجاد رکورد دستمزد
    $salary_data = [
        'coach_id' => $coach_id,
        'course_id' => $course_id,
        'attendance_date' => $attendance_date,
        'attendance_count' => $attendance_count,
        'price_per_session' => $price_per_session,
        'total_revenue' => $total_revenue,
        'salary_percentage' => $salary_percentage,
        'salary_amount' => $salary_amount,
        'salary_type' => 'percentage',
        'wallet_transaction_id' => $transaction_result['transaction_id'],
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    $salary_insert = $wpdb->insert(
        $salary_records_table,
        $salary_data,
        ['%d', '%d', '%s', '%d', '%f', '%f', '%f', '%f', '%s', '%d', '%s', '%s']
    );
    
    if ($salary_insert) {
        $salary_record_id = $wpdb->insert_id;
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
        'message' => 'دستمزد با موفقیت محاسبه و واریز شد',
        'salary_amount' => $salary_amount,
        'transaction_id' => $transaction_result['transaction_id']
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
        'course_id' => 0, // برای دستمزد ثابت دوره نداریم
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
        ['%d', '%d', '%s', '%d', '%f', '%f', '%f', '%f', '%s', '%d', '%s', '%s']
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
