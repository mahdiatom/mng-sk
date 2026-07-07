<?php
/**
 * Wallet Functions
 * توابع مربوط به کیف پول
 */
if ( ! defined('ABSPATH') ) exit;
/**
 * Get wallet balance for a member
 * دریافت موجودی کیف پول یک بازیکن
 */
function sc_get_wallet_balance($member_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_wallet_transactions';
    
    // محاسبه موجودی از مجموع تراکنش‌های completed
    $balance = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(
            CASE 
                WHEN transaction_type = 'charge' THEN amount
                WHEN transaction_type = 'payment' THEN -amount
                WHEN transaction_type = 'deduct' THEN -amount
                WHEN transaction_type = 'session_fee' THEN -amount
                WHEN transaction_type = 'refund' THEN amount
                ELSE 0
            END
        ), 0) as balance
        FROM $table_name
        WHERE member_id = %d AND status = 'completed'",
        $member_id
    ));
    
    return floatval($balance);
}

/**
 * Get wallet balance by user_id
 * دریافت موجودی کیف پول بر اساس user_id
 */
function sc_get_wallet_balance_by_user_id($user_id) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    
    // پیدا کردن member_id از user_id
    $member_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $members_table WHERE user_id = %d LIMIT 1",
        $user_id
    ));
    
    if (!$member_id) {
        return 0;
    }
    
    return sc_get_wallet_balance($member_id);
}

/**
 * Check if wallet is enabled (setting from wallet tab only)
 * بررسی فعال بودن تنظیم کیف پول در تب کیف پول
 */
function sc_is_wallet_enabled() {
    return (int)sc_get_setting('wallet_enabled', '0') === 1;
}

/**
 * Get minimum charge amount
 * دریافت حداقل مبلغ شارژ
 */
function sc_get_wallet_min_charge() {
    return floatval(sc_get_setting('wallet_min_charge', '10000'));
}

/**
 * Get maximum charge amount (0 = unlimited)
 * دریافت حداکثر مبلغ شارژ
 */
function sc_get_wallet_max_charge() {
    return floatval(sc_get_setting('wallet_max_charge', '0'));
}

/**
 * Get maximum negative balance allowed
 * دریافت حداکثر موجودی منفی مجاز
 */
function sc_get_wallet_max_negative_balance() {
    return floatval(sc_get_setting('wallet_max_negative_balance', '0'));
}

/**
 * Get minimum balance alert threshold
 * دریافت حداقل موجودی برای هشدار
 */
function sc_get_wallet_min_balance_alert() {
    return floatval(sc_get_setting('wallet_min_balance_alert', '50000'));
}

/**
 * Check if partial payment from wallet is allowed
 * بررسی امکان پرداخت جزئی از کیف پول
 */
function sc_is_wallet_partial_payment_allowed() {
    return (int)sc_get_setting('wallet_allow_partial_payment', '1') === 1;
}

/**
 * Check if a member can pay an amount from wallet (full or partial).
 */
function sc_can_pay_amount_from_wallet($member_id, $amount) {
    if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) {
        return false;
    }

    $amount = floatval($amount);
    if ($amount <= 0) {
        return false;
    }

    $wallet_balance = sc_get_wallet_balance($member_id);
    if ($wallet_balance >= $amount) {
        return true;
    }

    return sc_is_wallet_partial_payment_allowed() && $wallet_balance > 0;
}

/**
 * Wallet payment button label based on balance vs amount.
 */
function sc_get_wallet_payment_button_label($member_id, $amount) {
    $wallet_balance = sc_get_wallet_balance($member_id);
    $amount = floatval($amount);

    return $wallet_balance >= $amount
        ? 'پرداخت از کیف پول'
        : 'پرداخت از کیف پول + بقیه اش از درگاه';
}

/**
 * Add wallet transaction
 * افزودن تراکنش کیف پول
 */
function sc_add_wallet_transaction($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_wallet_transactions';
    
    // دریافت موجودی فعلی
    $current_balance = sc_get_wallet_balance($data['member_id']);
    
    // محاسبه موجودی جدید
    $new_balance = $current_balance;
    if ($data['transaction_type'] === 'charge' || $data['transaction_type'] === 'refund') {
        $new_balance += $data['amount'];
    } elseif ($data['transaction_type'] === 'payment' || $data['transaction_type'] === 'deduct' || $data['transaction_type'] === 'session_fee') {
        $new_balance -= $data['amount'];
    }
    
    // بررسی محدودیت موجودی منفی
    $max_negative = sc_get_wallet_max_negative_balance();
    if ($new_balance < -$max_negative) {
        return [
            'success' => false,
            'message' => 'موجودی کیف پول نمی‌تواند کمتر از ' . number_format($max_negative, 0, '.', ',') . ' تومان باشد.'
        ];
    }
    
    // افزودن تراکنش
    $inserted = $wpdb->insert(
        $table_name,
        [
            'user_id' => $data['user_id'],
            'member_id' => $data['member_id'],
            'transaction_type' => $data['transaction_type'],
            'amount' => $data['amount'],
            'balance_before' => $current_balance,
            'balance_after' => $new_balance,
            'description' => isset($data['description']) ? $data['description'] : '',
            'related_invoice_id' => isset($data['related_invoice_id']) ? $data['related_invoice_id'] : null,
            'related_order_id' => isset($data['related_order_id']) ? $data['related_order_id'] : null,
            'created_by' => isset($data['created_by']) ? $data['created_by'] : get_current_user_id(),
            'status' => isset($data['status']) ? $data['status'] : 'completed',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%f', '%f', '%f', '%s', '%d', '%d', '%d', '%s', '%s', '%s']
    );
    
    if ($inserted === false) {
        return [
            'success' => false,
            'message' => 'خطا در ثبت تراکنش کیف پول.'
        ];
    }
    
    // بررسی و ارسال هشدارهای موجودی
    $status_for_alerts = isset($data['status']) ? $data['status'] : 'completed';
    if ($status_for_alerts === 'completed') {
        sc_check_wallet_balance_alerts($data['member_id'], $new_balance);
    }
    
    return [
        'success' => true,
        'transaction_id' => $wpdb->insert_id,
        'balance_after' => $new_balance,
        'message' => 'تراکنش با موفقیت ثبت شد.'
    ];
}

/**
 * Charge wallet (user or admin)
 * شارژ کیف پول (کاربر یا مدیر)
 */
function sc_charge_wallet($member_id, $amount, $description = '', $created_by = null) {
    if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) {
        return [
            'success' => false,
            'message' => 'سیستم کیف پول فعال نیست.'
        ];
    }
    
    // بررسی حداقل مبلغ شارژ
    $min_charge = sc_get_wallet_min_charge();
    if ($amount < $min_charge) {
        return [
            'success' => false,
            'message' => 'حداقل مبلغ شارژ ' . number_format($min_charge, 0, '.', ',') . ' تومان است.'
        ];
    }
    
    // بررسی حداکثر مبلغ شارژ
    $max_charge = sc_get_wallet_max_charge();
    if ($max_charge > 0 && $amount > $max_charge) {
        return [
            'success' => false,
            'message' => 'حداکثر مبلغ شارژ ' . number_format($max_charge, 0, '.', ',') . ' تومان است.'
        ];
    }
    
    // دریافت user_id از member_id
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $members_table WHERE id = %d LIMIT 1",
        $member_id
    ));
    
    if (!$user_id) {
        return [
            'success' => false,
            'message' => 'کاربر یافت نشد.'
        ];
    }
    
    if ($created_by === null) {
        $created_by = get_current_user_id();
    }
    
    $result = sc_add_wallet_transaction([
        'user_id' => $user_id,
        'member_id' => $member_id,
        'transaction_type' => 'charge',
        'amount' => $amount,
        'description' => $description,
        'created_by' => $created_by
    ]);
    
    // ارسال پیامک برای شارژ موفق
    if ($result['success'] && isset($result['balance_after'])) {
        sc_send_wallet_charge_success_sms($member_id, $amount, $result['balance_after']);
    }
    if (is_admin() && $result['success'] && function_exists('sc_log_activity')) {
        sc_log_activity('updated', 'wallet', $member_id, 'کیف پول عضو ' . $member_id . ' به مبلغ ' . number_format($amount, 0, '.', ',') . ' تومان شارژ شد', null, ['amount' => $amount, 'balance_after' => $result['balance_after'] ?? null]);
    }
    return $result;
}

/**
 * Deduct from wallet (admin only)
 * کاهش از کیف پول (فقط مدیر)
 */
function sc_deduct_wallet($member_id, $amount, $description = '') {
    if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) {
        return [
            'success' => false,
            'message' => 'سیستم کیف پول فعال نیست.'
        ];
    }
    
    // بررسی موجودی کافی
    $current_balance = sc_get_wallet_balance($member_id);
    $max_negative = sc_get_wallet_max_negative_balance();
    
    if ($current_balance - $amount < -$max_negative) {
        return [
            'success' => false,
            'message' => 'موجودی کیف پول نمی‌تواند کمتر از ' . number_format($max_negative, 0, '.', ',') . ' تومان باشد.'
        ];
    }
    
    // دریافت user_id از member_id
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $members_table WHERE id = %d LIMIT 1",
        $member_id
    ));
    
    if (!$user_id) {
        return [
            'success' => false,
            'message' => 'کاربر یافت نشد.'
        ];
    }
    
    $result = sc_add_wallet_transaction([
        'user_id' => $user_id,
        'member_id' => $member_id,
        'transaction_type' => 'deduct',
        'amount' => $amount,
        'description' => $description,
        'created_by' => get_current_user_id()
    ]);
    if (is_admin() && $result['success'] && function_exists('sc_log_activity')) {
        sc_log_activity('updated', 'wallet', $member_id, 'از کیف پول عضو ' . $member_id . ' مبلغ ' . number_format($amount, 0, '.', ',') . ' تومان کسر شد', null, ['amount' => $amount, 'balance_after' => $result['balance_after'] ?? null]);
    }
    return $result;
}

/**
 * Deduct session fee from wallet (on attendance present)
 * کسر مبلغ جلسه از کیف پول (هنگام ثبت حضور)
 */
function sc_deduct_wallet_session_fee($member_id, $amount, $course_title, $attendance_date_shamsi, $created_by_user_id = null) {
    if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) {
        return ['success' => true, 'message' => '']; // کیف پول غیرفعال = فقط حضور ذخیره شود
    }
    $amount = floatval($amount);
    if ($amount <= 0) {
        return ['success' => true, 'message' => ''];
    }
    $current_balance = sc_get_wallet_balance($member_id);
    $max_negative = sc_get_wallet_max_negative_balance();
    if ($current_balance - $amount < -$max_negative) {
        return [
            'success' => false,
            'message' => 'موجودی کیف پول نمی‌تواند کمتر از ' . number_format($max_negative, 0, '.', ',') . ' تومان باشد.'
        ];
    }
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $members_table WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!$user_id) {
        return ['success' => false, 'message' => 'کاربر یافت نشد.'];
    }
    if (function_exists('sc_attendance_member_debt_blocked') && sc_attendance_member_debt_blocked($member_id)) {
        return [
            'success' => false,
            'message' => 'عدم ثبت به علت بدهی بیشتر از سقف بدهی' 
        ];
    }
  



    $description = $course_title . ' - ' . $attendance_date_shamsi;
    $created_by = $created_by_user_id !== null ? $created_by_user_id : get_current_user_id();
    return sc_add_wallet_transaction([
        'user_id' => $user_id,
        'member_id' => $member_id,
        'transaction_type' => 'session_fee',
        'amount' => $amount,
        'description' => $description,
        'created_by' => $created_by,
    ]);
}

/**
 * Refund session fee to wallet (on attendance change to absent or delete)
 * 
 */
function sc_refund_wallet_session_fee($member_id, $amount, $course_title, $attendance_date_shamsi) {
    if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) {
        return ['success' => true];
    }
    $amount = floatval($amount);
    if ($amount <= 0) {
        return ['success' => true];
    }
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $members_table WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!$user_id) {
        return ['success' => false];
    }
    $description = 'برگشت مبلغ جلسه - ' . $course_title . ' - ' . $attendance_date_shamsi;
    return sc_add_wallet_transaction([
        'user_id' => $user_id,
        'member_id' => $member_id,
        'transaction_type' => 'refund',
        'amount' => $amount,
        'description' => $description,
        'created_by' => get_current_user_id()
    ]);
}

/**
 * Pay invoice from wallet
 * پرداخت صورت حساب از کیف پول
 */
function sc_pay_invoice_from_wallet($invoice_id, $amount = null) {
    if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) {
        return [
            'success' => false,
            'message' => 'سیستم کیف پول فعال نیست.'
        ];
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $members_table = $wpdb->prefix . 'sc_members';
    
    // دریافت اطلاعات صورت حساب
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE id = %d",
        $invoice_id
    ));
    
    if (!$invoice) {
        return [
            'success' => false,
            'message' => 'صورت حساب یافت نشد.'
        ];
    }
    
    // بررسی وضعیت صورت حساب
    if (!in_array($invoice->status, ['pending', 'under_review'])) {
        return [
            'success' => false,
            'message' => 'این صورت حساب قابل پرداخت نیست.'
        ];
    }

    if (!empty($invoice->expense_name) && $invoice->expense_name === 'شارژ کیف پول') {
        return [
            'success' => false,
            'message' => 'شارژ کیف پول فقط از درگاه پرداخت امکان‌پذیر است.'
        ];
    }
    
    // محاسبه مبلغ قابل پرداخت
    $total_amount = floatval($invoice->amount) + floatval($invoice->penalty_amount ?? 0);
    $pay_amount = $amount !== null ? floatval($amount) : $total_amount;
    
    if ($pay_amount <= 0 || $pay_amount > $total_amount) {
        return [
            'success' => false,
            'message' => 'مبلغ پرداخت نامعتبر است.'
        ];
    }
    
    // دریافت موجودی کیف پول
    $wallet_balance = sc_get_wallet_balance($invoice->member_id);
    
    // اگر موجودی کافی نیست و پرداخت جزئی مجاز است و موجودی مثبت است، از مبلغ موجود استفاده کن
    if ($wallet_balance < $pay_amount && sc_is_wallet_partial_payment_allowed() && $wallet_balance > 0) {
        $pay_amount = $wallet_balance;
    }
    // در غیر این صورت پرداخت کامل انجام می‌شود (در صورت مجاز بودن موجودی منفی در تنظیمات، sc_add_wallet_transaction اعتبارسنجی می‌کند)
    
    // دریافت user_id
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $members_table WHERE id = %d LIMIT 1",
        $invoice->member_id
    ));
    
    if (!$user_id) {
        return [
            'success' => false,
            'message' => 'کاربر یافت نشد.'
        ];
    }
    
    // ثبت تراکنش پرداخت
    $transaction_result = sc_add_wallet_transaction([
        'user_id' => $user_id,
        'member_id' => $invoice->member_id,
        'transaction_type' => 'payment',
        'amount' => $pay_amount,
        'description' => 'پرداخت صورت حساب #' . $invoice->woocommerce_order_id ?? $invoice->id,
        'related_invoice_id' => $invoice_id,
        'related_order_id' => $invoice->woocommerce_order_id,
        'created_by' => get_current_user_id()
    ]);
    
    if (!$transaction_result['success']) {
        return $transaction_result;
    }
    
    // ارسال پیامک برای پرداخت از کیف پول
    if (isset($transaction_result['balance_after'])) {
        sc_send_wallet_payment_sms($invoice->member_id, $pay_amount, $transaction_result['balance_after'], $invoice_id);
    }
    
    // اگر مبلغ کامل پرداخت شد، بروزرسانی وضعیت صورت حساب
    if ($pay_amount >= $total_amount) {
        $wpdb->update(
            $invoices_table,
            [
                'status' => 'paid',
                'payment_date' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $invoice_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        do_action('sc_invoice_paid', $invoice_id);

        // بروزرسانی وضعیت سفارش WooCommerce
        if (!empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($invoice->woocommerce_order_id);
            if ($order) {
                // حذف fee های قبلی و اضافه کردن fee جدید با مبلغ صفر (یا می‌توانیم status را تغییر دهیم)
                // بهتر است فقط status را تغییر دهیم
                $order->update_status('completed', 'پرداخت از کیف پول');
                $order->save();
            }
        }
    } else {
        // پرداخت جزئی - کاهش مبلغ صورت حساب و بروزرسانی fee سفارش
        $remaining_amount = $total_amount - $pay_amount;
        $wpdb->update(
            $invoices_table,
            [
                'amount' => $remaining_amount,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $invoice_id],
            ['%f', '%s'],
            ['%d']
        );
        
        // بروزرسانی fee سفارش WooCommerce
        if (!empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($invoice->woocommerce_order_id);
            if ($order) {
                // حذف fee های قبلی
                foreach ($order->get_items('fee') as $item_id => $item) {
                    $order->remove_item($item_id);
                }
                
                // اضافه کردن fee جدید با مبلغ باقیمانده
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('مبلغ باقیمانده صورت حساب');
                $fee->set_amount($remaining_amount);
                $fee->set_tax_class('');
                $fee->set_tax_status('none');
                $fee->set_total($remaining_amount);
                $order->add_item($fee);
                
                // محاسبه مجدد و ذخیره
                $order->calculate_totals();
                $order->save();
            }
        }
    }
    
    return [
        'success' => true,
        'transaction_id' => $transaction_result['transaction_id'],
        'paid_amount' => $pay_amount,
        'remaining_amount' => $total_amount - $pay_amount,
        'message' => $pay_amount >= $total_amount ? 'صورت حساب با موفقیت پرداخت شد.' : 'مبلغ ' . number_format($pay_amount, 0, '.', ',') . ' تومان از کیف پول پرداخت شد.'
    ];
}

/**
 * Get wallet transactions for a member
 * دریافت تراکنش‌های کیف پول یک بازیکن
 */
function sc_get_wallet_transactions($member_id, $limit = 50, $offset = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_wallet_transactions';
    
    $transactions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name
        WHERE member_id = %d
        ORDER BY created_at DESC
        LIMIT %d OFFSET %d",
        $member_id,
        $limit,
        $offset
    ));
    
    return $transactions;
}

/**
 * Get wallet transactions count for a member
 * دریافت تعداد تراکنش‌های کیف پول یک بازیکن
 */
function sc_get_wallet_transactions_count($member_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_wallet_transactions';
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE member_id = %d",
        $member_id
    ));
    
    return intval($count);
}

/**
 * Send SMS for wallet low balance alert
 * ارسال پیامک برای هشدار موجودی کم
 */
function sc_send_wallet_low_balance_sms($member_id, $balance) {
    if (!function_exists('sc_send_sms')) {
        return false;
    }
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT first_name, last_name, player_phone FROM $members_table WHERE id = %d",
        $member_id
    ));
    
    if (!$member || empty($member->player_phone)) {
        return false;
    }
    
    $min_balance = sc_get_wallet_min_balance_alert();
    $user_name = trim($member->first_name . ' ' . $member->last_name);
    
    // بررسی فعال بودن پیامک
    if (!sc_is_sms_enabled_for('wallet_low_balance', 'user')) {
        return false;
    }
    
    $template = sc_get_sms_template('wallet_low_balance', 'user');
    if (empty($template)) {
        $template = 'هشدار: موجودی کیف پول شما به %balance% تومان رسیده است. لطفاً کیف پول خود را شارژ کنید.';
    }
    
    $variables = [
        'user_name' => $user_name,
        'balance' => number_format($balance, 0, '.', ','),
        'min_balance' => number_format($min_balance, 0, '.', ',')
    ];
    
    $message = sc_replace_sms_variables($template, $variables);
    $pattern_code = sc_get_sms_pattern('wallet_low_balance', 'user');
    
    return sc_send_sms($member->player_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'wallet_low_balance');
}

/**
 * Send SMS for wallet negative balance alert
 * ارسال پیامک برای هشدار موجودی منفی
 */
function sc_send_wallet_negative_balance_sms($member_id, $balance) {
    if (!function_exists('sc_send_sms')) {
        return false;
    }
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT first_name, last_name, player_phone FROM $members_table WHERE id = %d",
        $member_id
    ));
    
    if (!$member || empty($member->player_phone)) {
        return false;
    }
    
    $user_name = trim($member->first_name . ' ' . $member->last_name);
    
    // بررسی فعال بودن پیامک
    if (!sc_is_sms_enabled_for('wallet_negative_balance', 'user')) {
        return false;
    }
    
    $template = sc_get_sms_template('wallet_negative_balance', 'user');
    if (empty($template)) {
        $template = 'هشدار: موجودی کیف پول شما منفی شده است (%balance% تومان). لطفاً فوراً کیف پول خود را شارژ کنید.';
    }
    
    $variables = [
        'user_name' => $user_name,
        'balance' => number_format($balance, 0, '.', ',')
    ];
    
    $message = sc_replace_sms_variables($template, $variables);
    $pattern_code = sc_get_sms_pattern('wallet_negative_balance', 'user');
    
    return sc_send_sms($member->player_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'wallet_negative_balance');
}

/**
 * Send SMS for wallet charge success
 * ارسال پیامک برای شارژ موفق کیف پول
 */
function sc_send_wallet_charge_success_sms($member_id, $amount, $new_balance) {
    if (!function_exists('sc_send_sms')) {
        return false;
    }
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT first_name, last_name, player_phone FROM $members_table WHERE id = %d",
        $member_id
    ));
    
    if (!$member || empty($member->player_phone)) {
        return false;
    }
    
    $user_name = trim($member->first_name . ' ' . $member->last_name);
    
    // بررسی فعال بودن پیامک
    if (!sc_is_sms_enabled_for('wallet_charge_success', 'user')) {
        return false;
    }
    
    $template = sc_get_sms_template('wallet_charge_success', 'user');
    if (empty($template)) {
        $template = 'کیف پول شما به مبلغ %amount% تومان شارژ شد. موجودی فعلی: %balance% تومان.';
    }
    
    $variables = [
        'user_name' => $user_name,
        'amount' => number_format($amount, 0, '.', ','),
        'balance' => number_format($new_balance, 0, '.', ',')
    ];
    
    $message = sc_replace_sms_variables($template, $variables);
    $pattern_code = sc_get_sms_pattern('wallet_charge_success', 'user');
    
    return sc_send_sms($member->player_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'wallet_charge_success');
}

/**
 * Send SMS for wallet payment
 * ارسال پیامک برای پرداخت از کیف پول
 */
function sc_send_wallet_payment_sms($member_id, $amount, $new_balance, $invoice_id = null) {
    if (!function_exists('sc_send_sms')) {
        return false;
    }
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT first_name, last_name, player_phone FROM $members_table WHERE id = %d",
        $member_id
    ));
    
    if (!$member || empty($member->player_phone)) {
        return false;
    }
    
    $user_name = trim($member->first_name . ' ' . $member->last_name);
    
    // بررسی فعال بودن پیامک
    if (!sc_is_sms_enabled_for('wallet_payment', 'user')) {
        return false;
    }
    
    $template = sc_get_sms_template('wallet_payment', 'user');
    if (empty($template)) {
        $template = 'مبلغ %amount% تومان از کیف پول شما کسر شد. موجودی فعلی: %balance% تومان.';
    }
    
    $variables = [
        'user_name' => $user_name,
        'amount' => number_format($amount, 0, '.', ','),
        'balance' => number_format($new_balance, 0, '.', ',')
    ];
    
    if ($invoice_id) {
        $variables['invoice_id'] = $invoice_id;
    }
    
    $message = sc_replace_sms_variables($template, $variables);
    $pattern_code = sc_get_sms_pattern('wallet_payment', 'user');
    
    return sc_send_sms($member->player_phone, $message, !empty($pattern_code), $pattern_code, $variables, 'wallet_payment');
}

/**
 * Notify admin when user wallet goes negative
 * اطلاع به مدیر هنگام منفی شدن کیف پول کاربر
 */
function sc_notify_admin_wallet_negative_balance($member_id, $balance) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT first_name, last_name, national_id, player_phone FROM $members_table WHERE id = %d",
        $member_id
    ));
    if (!$member) {
        return;
    }
    $name = trim($member->first_name . ' ' . $member->last_name);
    $admin_email = get_option('admin_email');
    $subject = 'هشدار: کیف پول منفی - ' . $name;
    $message = sprintf(
        "کیف پول کاربر زیر منفی شده است:\n\nنام: %s\nکد ملی: %s\nتلفن: %s\nموجودی فعلی: %s تومان\n\nلطفاً در پنل مدیریت بررسی کنید.",
        $name,
        $member->national_id ?: '-',
        $member->player_phone ?: '-',
        number_format($balance, 0, '.', ',')
    );
    wp_mail($admin_email, $subject, $message);
}

/**
 * Check and send wallet balance alerts
 * بررسی و ارسال هشدارهای موجودی کیف پول
 */
function sc_check_wallet_balance_alerts($member_id, $new_balance) {
    $min_balance_alert = sc_get_wallet_min_balance_alert();
    $max_negative = sc_get_wallet_max_negative_balance();
    
    // هشدار موجودی منفی
    if ($new_balance < 0) {
        // فقط یک بار در روز ارسال شود
        $last_alert_key = 'wallet_negative_alert_' . $member_id;
        $last_alert_date = get_transient($last_alert_key);
        
        if ($last_alert_date !== date('Y-m-d')) {
            sc_send_wallet_negative_balance_sms($member_id, $new_balance);
            sc_notify_admin_wallet_negative_balance($member_id, $new_balance);
            set_transient($last_alert_key, date('Y-m-d'), DAY_IN_SECONDS);
        }
    }
    // هشدار موجودی کم
    elseif ($min_balance_alert > 0 && $new_balance <= $min_balance_alert && $new_balance > 0) {
        // فقط یک بار در روز ارسال شود
        $last_alert_key = 'wallet_low_balance_alert_' . $member_id;
        $last_alert_date = get_transient($last_alert_key);
        
        if ($last_alert_date !== date('Y-m-d')) {
            sc_send_wallet_low_balance_sms($member_id, $new_balance);
            set_transient($last_alert_key, date('Y-m-d'), DAY_IN_SECONDS);
        }
    }
}

/**
 * Get wallet financial report for a member
 * دریافت گزارش مالی کیف پول یک بازیکن
 */
function sc_get_wallet_financial_report($member_id, $start_date = null, $end_date = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_wallet_transactions';
    
    $where_conditions = ["member_id = %d", "status = 'completed'"];
    $where_values = [$member_id];
    
    if ($start_date) {
        $where_conditions[] = "created_at >= %s";
        $where_values[] = $start_date;
    }
    
    if ($end_date) {
        $where_conditions[] = "created_at <= %s";
        $where_values[] = $end_date;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $report = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COALESCE(SUM(CASE WHEN transaction_type = 'charge' THEN amount ELSE 0 END), 0) as total_charge,
            COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END), 0) as total_payment,
            COALESCE(SUM(CASE WHEN transaction_type = 'deduct' THEN amount ELSE 0 END), 0) as total_deduct,
            COALESCE(SUM(CASE WHEN transaction_type = 'session_fee' THEN amount ELSE 0 END), 0) as total_session_fee,
            COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) as total_refund,
            COUNT(*) as total_transactions
        FROM $table_name
        $where_clause",
        $where_values
    ));
    
    $current_balance = sc_get_wallet_balance($member_id);
    
    return [
        'total_charge' => floatval($report->total_charge ?? 0),
        'total_payment' => floatval($report->total_payment ?? 0),
        'total_deduct' => floatval($report->total_deduct ?? 0),
        'total_session_fee' => floatval($report->total_session_fee ?? 0),
        'total_refund' => floatval($report->total_refund ?? 0),
        'total_transactions' => intval($report->total_transactions ?? 0),
        'current_balance' => $current_balance,
        'net_balance' => $current_balance
    ];
}

/**
 * Get wallet transactions by period (monthly/yearly)
 * دریافت تراکنش‌های کیف پول بر اساس دوره (ماهانه/سالانه)
 */
function sc_get_wallet_transactions_by_period($member_id, $period = 'monthly', $year = null, $month = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_wallet_transactions';
    
    if ($period === 'monthly') {
        if (!$year) {
            $year = date('Y');
        }
        if (!$month) {
            $month = date('m');
        }
        
        // تشخیص اینکه سال شمسی است یا میلادی (سال‌های شمسی معمولاً کمتر از 1700 هستند)
        $is_jalali = ((int)$year < 1700);
        
        if ($is_jalali) {
            // سال و ماه شمسی → بازه معادل میلادی
            $jy = (int)$year;
            $jm = (int)$month;
            
            // اول ماه
            list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, 1);
            $start_date = sprintf('%04d-%02d-%02d 00:00:00', $gy, $gm, $gd);
            
            // اول ماه بعد
            $next_jy = $jy;
            $next_jm = $jm + 1;
            if ($next_jm > 12) {
                $next_jm = 1;
                $next_jy++;
            }
            list($ngy, $ngm, $ngd) = jalali_to_gregorian($next_jy, $next_jm, 1);
            $next_start = sprintf('%04d-%02d-%02d', $ngy, $ngm, $ngd);
            $end_date   = date('Y-m-d 23:59:59', strtotime($next_start . ' -1 day'));
        } else {
            // حالت قدیمی: سال/ماه میلادی
            $start_date = "$year-$month-01 00:00:00";
            $end_date   = date('Y-m-t 23:59:59', strtotime($start_date));
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
            WHERE member_id = %d
            AND created_at >= %s
            AND created_at <= %s
            ORDER BY created_at DESC",
            $member_id, $start_date, $end_date
        ));
    } elseif ($period === 'yearly') {
        if (!$year) {
            $year = date('Y');
        }
        
        $is_jalali = ((int)$year < 1700);
        
        if ($is_jalali) {
            $jy = (int)$year;
            // اول فروردین سال جاری
            list($gy, $gm, $gd) = jalali_to_gregorian($jy, 1, 1);
            $start_date = sprintf('%04d-%02d-%02d 00:00:00', $gy, $gm, $gd);
            
            // اول فروردین سال بعد
            list($ngy, $ngm, $ngd) = jalali_to_gregorian($jy + 1, 1, 1);
            $next_start = sprintf('%04d-%02d-%02d', $ngy, $ngm, $ngd);
            $end_date   = date('Y-m-d 23:59:59', strtotime($next_start . ' -1 day'));
        } else {
            // حالت قدیمی: سال میلادی
            $start_date = "$year-01-01 00:00:00";
            $end_date   = "$year-12-31 23:59:59";
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
            WHERE member_id = %d
            AND created_at >= %s
            AND created_at <= %s
            ORDER BY created_at DESC",
            $member_id, $start_date, $end_date
        ));
    }
    
    return [];
}

/**
 * Get wallet statistics for admin
 * دریافت آمار کلی کیف پول برای مدیر
 */
function sc_get_wallet_admin_statistics() {
    global $wpdb;
    $transactions_table = $wpdb->prefix . 'sc_wallet_transactions';
    $members_table = $wpdb->prefix . 'sc_members';
    
    // تعداد کاربران با کیف پول
    $users_with_wallet = $wpdb->get_var(
        "SELECT COUNT(DISTINCT member_id) FROM $transactions_table WHERE status = 'completed'"
    );
    
    // مجموع موجودی تمام کاربران
    $total_balance = $wpdb->get_var(
        "SELECT COALESCE(SUM(
            CASE 
                WHEN transaction_type = 'charge' THEN amount
                WHEN transaction_type = 'payment' THEN -amount
                WHEN transaction_type = 'deduct' THEN -amount
                WHEN transaction_type = 'session_fee' THEN -amount
                WHEN transaction_type = 'refund' THEN amount
                ELSE 0
            END
        ), 0) as total_balance
        FROM $transactions_table
        WHERE status = 'completed'"
    );
    
    // تعداد کل تراکنش‌ها
    $total_transactions = $wpdb->get_var(
        "SELECT COUNT(*) FROM $transactions_table WHERE status = 'completed'"
    );
    
    // مجموع شارژها
    $total_charges = $wpdb->get_var(
        "SELECT COALESCE(SUM(amount), 0) FROM $transactions_table 
        WHERE transaction_type = 'charge' AND status = 'completed'"
    );
    
    // مجموع پرداخت‌ها
    $total_payments = $wpdb->get_var(
        "SELECT COALESCE(SUM(amount), 0) FROM $transactions_table 
        WHERE transaction_type = 'payment' AND status = 'completed'"
    );
    
    // تعداد کاربران با موجودی منفی
    $users_with_negative = 0;
    $members = $wpdb->get_col("SELECT id FROM $members_table WHERE is_active = 1");
    foreach ($members as $member_id) {
        $balance = sc_get_wallet_balance($member_id);
        if ($balance < 0) {
            $users_with_negative++;
        }
    }
    
    return [
        'users_with_wallet' => intval($users_with_wallet),
        'total_balance' => floatval($total_balance),
        'total_transactions' => intval($total_transactions),
        'total_charges' => floatval($total_charges),
        'total_payments' => floatval($total_payments),
        'users_with_negative' => $users_with_negative
    ];
}

/**
 * Get wallet balance history for chart
 * دریافت تاریخچه موجودی برای نمودار
 */
function sc_get_wallet_balance_history($member_id, $days = 30) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_wallet_transactions';
    
    $start_date = date('Y-m-d 00:00:00', strtotime("-$days days"));
    
    // موجودی قبل از شروع بازه (برای محاسبه موجودی واقعی هر روز)
    $opening_balance = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(
                CASE 
                    WHEN transaction_type = 'charge' THEN amount
                    WHEN transaction_type = 'payment' THEN -amount
                    WHEN transaction_type = 'deduct' THEN -amount
                    WHEN transaction_type = 'session_fee' THEN -amount
                    WHEN transaction_type = 'refund' THEN amount
                    ELSE 0
                END
            ), 0) AS balance
         FROM $table_name
         WHERE member_id = %d
           AND status = 'completed'
           AND created_at < %s",
        $member_id,
        $start_date
    ));
    
    $transactions = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(created_at) as date, 
                SUM(CASE 
                    WHEN transaction_type = 'charge' THEN amount
                    WHEN transaction_type = 'payment' THEN -amount
                    WHEN transaction_type = 'deduct' THEN -amount
                    WHEN transaction_type = 'session_fee' THEN -amount
                    WHEN transaction_type = 'refund' THEN amount
                    ELSE 0
                END) as daily_change
        FROM $table_name
        WHERE member_id = %d
          AND created_at >= %s
          AND status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY date ASC",
        $member_id,
        $start_date
    ));
    
    $history         = [];
    $running_balance = floatval($opening_balance);
    
    foreach ($transactions as $transaction) {
        $running_balance += floatval($transaction->daily_change);
        
        // تبدیل تاریخ میلادی به شمسی برای نمایش بهتر روی نمودار
        $shamsi_date = sc_date_shamsi_date_only($transaction->date);
        
        $history[] = [
            'date'    => $shamsi_date,
            'balance' => $running_balance,
            'change'  => floatval($transaction->daily_change),
        ];
    }
    
    return $history;
}
