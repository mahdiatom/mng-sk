<?php
/**
 * Wallet Functions
 * توابع مربوط به کیف پول
 */

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
 * Check if wallet is enabled
 * بررسی فعال بودن کیف پول
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
    } elseif ($data['transaction_type'] === 'payment' || $data['transaction_type'] === 'deduct') {
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
    if (!sc_is_wallet_enabled()) {
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
    
    return sc_add_wallet_transaction([
        'user_id' => $user_id,
        'member_id' => $member_id,
        'transaction_type' => 'charge',
        'amount' => $amount,
        'description' => $description,
        'created_by' => $created_by
    ]);
}

/**
 * Deduct from wallet (admin only)
 * کاهش از کیف پول (فقط مدیر)
 */
function sc_deduct_wallet($member_id, $amount, $description = '') {
    if (!sc_is_wallet_enabled()) {
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
    
    return sc_add_wallet_transaction([
        'user_id' => $user_id,
        'member_id' => $member_id,
        'transaction_type' => 'deduct',
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
    if (!sc_is_wallet_enabled()) {
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
    
    // بررسی موجودی کافی
    if ($wallet_balance < $pay_amount) {
        // اگر پرداخت جزئی مجاز است
        if (sc_is_wallet_partial_payment_allowed() && $wallet_balance > 0) {
            $pay_amount = $wallet_balance; // پرداخت تا حد موجودی
        } else {
            return [
                'success' => false,
                'message' => 'موجودی کیف پول کافی نیست. موجودی شما: ' . number_format($wallet_balance, 0, '.', ',') . ' تومان'
            ];
        }
    }
    
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
        'description' => 'پرداخت صورت حساب #' . $invoice_id,
        'related_invoice_id' => $invoice_id,
        'related_order_id' => $invoice->woocommerce_order_id,
        'created_by' => get_current_user_id()
    ]);
    
    if (!$transaction_result['success']) {
        return $transaction_result;
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

