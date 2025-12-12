<?php
/**
 * Settings Functions
 */

/**
 * Get setting value
 */
function sc_get_setting($key, $default = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_settings';
    
    $value = $wpdb->get_var($wpdb->prepare(
        "SELECT setting_value FROM $table_name WHERE setting_key = %s",
        $key
    ));
    
    return $value !== null ? $value : $default;
}

/**
 * Update setting value
 */
function sc_update_setting($key, $value, $group = 'general') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_settings';
    
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE setting_key = %s",
        $key
    ));
    
    if ($existing) {
        return $wpdb->update(
            $table_name,
            [
                'setting_value' => $value,
                'updated_at' => current_time('mysql')
            ],
            ['setting_key' => $key],
            ['%s', '%s'],
            ['%s']
        );
    } else {
        return $wpdb->insert(
            $table_name,
            [
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_group' => $group,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }
}

/**
 * Get all settings by group
 */
function sc_get_settings_by_group($group) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_settings';
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT setting_key, setting_value FROM $table_name WHERE setting_group = %s",
        $group
    ), ARRAY_A);
    
    $settings = [];
    foreach ($results as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    return $settings;
}

/**
 * Check if penalty is enabled
 */
function sc_is_penalty_enabled() {
    return (int)sc_get_setting('penalty_enabled', '0') === 1;
}

/**
 * Get penalty days
 */
function sc_get_penalty_days() {
    return (int)sc_get_setting('penalty_days', '7');
}

/**
 * Get penalty amount
 */
function sc_get_penalty_amount() {
    return (float)sc_get_setting('penalty_amount', '500');
}

/**
 * Get invoice interval days
 */
function sc_get_invoice_interval_days() {
    return (int)sc_get_setting('invoice_interval_days', '30');
}

/**
 * Calculate penalty for an invoice
 */
function sc_calculate_penalty($invoice_id) {
    if (!sc_is_penalty_enabled()) {
        return 0;
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE id = %d",
        $invoice_id
    ));
    
    if (!$invoice || $invoice->status !== 'pending') {
        return 0;
    }
    
    $created_date = strtotime($invoice->created_at);
    $current_date = current_time('timestamp');
    $days_passed = floor(($current_date - $created_date) / (60 * 60 * 24));
    $penalty_days = sc_get_penalty_days();
    
    // اگر جریمه قبلاً اعمال شده، همان مقدار را برگردان
    if (isset($invoice->penalty_applied) && $invoice->penalty_applied && isset($invoice->penalty_amount) && $invoice->penalty_amount > 0) {
        return (float)$invoice->penalty_amount;
    }
    
    if ($days_passed >= $penalty_days) {
        return sc_get_penalty_amount();
    }
    
    return 0;
}

/**
 * Apply penalty to an invoice and update WooCommerce order
 */
function sc_apply_penalty_to_invoice($invoice_id) {
    if (!sc_is_penalty_enabled()) {
        return false;
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE id = %d",
        $invoice_id
    ));
    
    if (!$invoice || $invoice->status !== 'pending') {
        return false;
    }
    
    // بررسی اینکه آیا جریمه قبلاً اعمال شده یا نه
    $penalty_applied = isset($invoice->penalty_applied) ? (int)$invoice->penalty_applied : 0;
    if ($penalty_applied) {
        return false; // جریمه قبلاً اعمال شده
    }
    
    $penalty_amount = sc_calculate_penalty($invoice_id);
    
    if ($penalty_amount > 0) {
        // به‌روزرسانی جدول invoices
        $update_data = [
            'penalty_amount' => $penalty_amount,
            'penalty_applied' => 1,
            'updated_at' => current_time('mysql')
        ];
        
        $wpdb->update(
            $invoices_table,
            $update_data,
            ['id' => $invoice_id],
            ['%f', '%d', '%s'],
            ['%d']
        );
        
        // به‌روزرسانی سفارش WooCommerce
        if ($invoice->woocommerce_order_id && class_exists('WooCommerce')) {
            $order = wc_get_order($invoice->woocommerce_order_id);
            if ($order && !$order->is_paid()) {
                // بررسی اینکه آیا جریمه قبلاً اضافه شده یا نه
                $has_penalty_fee = false;
                $penalty_item_id = null;
                
                foreach ($order->get_items('fee') as $item_id => $item) {
                    $item_name = $item->get_name();
                    if (strpos($item_name, 'جریمه') !== false || strpos($item_name, 'Penalty') !== false || strpos($item_name, 'تأخیر') !== false) {
                        $has_penalty_fee = true;
                        $penalty_item_id = $item_id;
                        break;
                    }
                }
                
                // اگر جریمه وجود داشت، به‌روزرسانی کن
                if ($has_penalty_fee && $penalty_item_id) {
                    $item = $order->get_item($penalty_item_id);
                    if ($item) {
                        $item->set_total($penalty_amount);
                        $item->save();
                    }
                } else {
                    // اگر جریمه وجود نداشت، اضافه کن
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name('جریمه تأخیر در پرداخت');
                    $fee->set_amount($penalty_amount);
                    $fee->set_tax_class('');
                    $fee->set_tax_status('none');
                    $fee->set_total($penalty_amount);
                    $order->add_item($fee);
                }
                
                // محاسبه مجدد مجموع
                $order->calculate_totals();
                $order->save();
            }
        }
        
        return true;
    }
    
    return false;
}

/**
 * Check and apply penalties for all pending invoices
 */
function sc_check_and_apply_penalties() {
    if (!sc_is_penalty_enabled()) {
        return;
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $penalty_days = sc_get_penalty_days();
    
    // دریافت تمام صورت حساب‌های pending که جریمه اعمال نشده
    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $invoices_table 
         WHERE status = 'pending' 
         AND (penalty_applied = 0 OR penalty_applied IS NULL)
         AND TIMESTAMPDIFF(DAY, created_at, NOW()) >= %d",
        $penalty_days
    ));
    
    foreach ($invoices as $invoice) {
        sc_apply_penalty_to_invoice($invoice->id);
    }
}

