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
    if ($balance < $amount) {
        return ['success' => false, 'message' => 'موجودی ناکافی'];
    }
    
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
 * Calculate and add percentage salary for coach
 * محاسبه و افزودن دستمزد درصدی مربی
 */
function sc_calculate_coach_percentage_salary($coach_id, $course_id, $attendance_date, $attendance_count, $price_per_session) {
    if (!$coach_id || !$course_id || !$attendance_date || $attendance_count <= 0 || $price_per_session <= 0) {
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
    
    // بررسی موجودی
    if ($amount > $balance) {
        return ['success' => false, 'message' => 'مبلغ درخواستی بیشتر از موجودی است'];
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_coach_withdrawal_requests';
    
    $data = [
        'coach_id' => $coach_id,
        'amount' => floatval($amount),
        'balance_before' => $balance,
        'status' => 'pending',
        'notes' => $notes,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    $result = $wpdb->insert($table_name, $data, ['%d', '%f', '%f', '%s', '%s', '%s', '%s']);
    
    if ($result) {
        return [
            'success' => true,
            'request_id' => $wpdb->insert_id,
            'message' => 'درخواست برداشت با موفقیت ثبت شد'
        ];
    }
    
    return ['success' => false, 'message' => 'خطا در ثبت درخواست'];
}

/**
 * Approve withdrawal request
 * تایید درخواست برداشت (وضعیت: منتظر پرداخت - بدون کسر از کیف پول)
 */
function sc_approve_coach_withdrawal_request($request_id) {
    if (!$request_id) {
        return ['success' => false, 'message' => 'شناسه درخواست نامعتبر'];
    }
    
    global $wpdb;
    $requests_table = $wpdb->prefix . 'sc_coach_withdrawal_requests';
    
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $requests_table WHERE id = %d AND status = 'pending' LIMIT 1",
        $request_id
    ));
    
    if (!$request) {
        return ['success' => false, 'message' => 'درخواست یافت نشد یا قبلاً پردازش شده است'];
    }
    
    // فقط بروزرسانی وضعیت - بدون کسر از کیف پول (منتظر پرداخت)
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
    
    return [
        'success' => true,
        'message' => 'درخواست تایید شد و منتظر پرداخت است'
    ];
}

/**
 * Reject withdrawal request
 * رد درخواست برداشت
 */
function sc_reject_coach_withdrawal_request($request_id, $rejection_reason = '') {
    if (!$request_id) {
        return ['success' => false, 'message' => 'شناسه درخواست نامعتبر'];
    }
    
    global $wpdb;
    $requests_table = $wpdb->prefix . 'sc_coach_withdrawal_requests';
    
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
    
    return ['success' => true, 'message' => 'درخواست رد شد'];
}

/**
 * Mark withdrawal request as paid
 * علامت‌گذاری درخواست برداشت به عنوان پرداخت شده (کسر از کیف پول + تغییر وضعیت)
 */
function sc_mark_coach_withdrawal_paid($request_id) {
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
    
    // کسر از کیف پول در زمان پرداخت
    $deduct_result = sc_deduct_coach_wallet(
        $request->coach_id,
        $request->amount,
        sprintf('برداشت - درخواست #%d', $request_id)
    );
    
    if (!$deduct_result['success']) {
        return ['success' => false, 'message' => 'خطا در کسر از کیف پول: ' . $deduct_result['message']];
    }
    
    $wpdb->update(
        $requests_table,
        [
            'status' => 'paid',
            'paid_by' => get_current_user_id(),
            'paid_at' => current_time('mysql'),
            'wallet_transaction_id' => $deduct_result['transaction_id'],
            'updated_at' => current_time('mysql')
        ],
        ['id' => $request_id],
        ['%s', '%d', '%s', '%d', '%s'],
        ['%d']
    );
    
    return ['success' => true, 'message' => 'درخواست پرداخت شد و مبلغ از کیف پول کسر شد'];
}
