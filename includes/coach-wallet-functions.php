<?php
/**
 * Coach Wallet Functions
 * توابع کیف پول مربی
 */

if (!defined('ABSPATH')) {
    exit;
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
        "SELECT COUNT(*)
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
        
        // اگر تراکنش کیف پول وجود دارد و مبلغ تغییر کرده، تراکنش جدید با تفاوت ایجاد کن
        if ($old_record->wallet_transaction_id && abs($difference) >= 0.01) {
            $wallet_table = $wpdb->prefix . 'sc_coach_wallet_transactions';
            
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
        $wpdb->update(
            $salary_records_table,
            ['related_salary_record_id' => $wpdb->insert_id],
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
    
    if (!$coach || $coach->settlement_type !== 'fixed' || floatval($coach->settlement_amount) <= 0) {
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
