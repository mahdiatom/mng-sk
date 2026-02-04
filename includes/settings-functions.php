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
 * Get penalty minutes
 */
function sc_get_penalty_minutes() {
    return (int)sc_get_setting('penalty_minutes', '7');
}

/**
 * Get penalty amount
 */
function sc_get_penalty_amount() {
    return (float)sc_get_setting('penalty_amount', '500');
}

/**
 * Get invoice interval minutes
 */
function sc_get_invoice_interval_minutes() {
    return (int)sc_get_setting('invoice_interval_minutes', '60');
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
    
    // <-- اینجا شرط جدید اضافه می‌کنیم
    if ($invoice && isset($invoice->disable_penalty) && (int)$invoice->disable_penalty === 1) {
        return 0; // جریمه غیرفعال است
    }

    if (!$invoice || $invoice->status !== 'pending') {
        return 0;
    }
    
    $created_date = strtotime($invoice->created_at);
    $current_date = current_time('timestamp');
    $minutes_passed = floor(($current_date - $created_date) / 60);
    $penalty_minutes = sc_get_penalty_minutes();
    
    if (isset($invoice->penalty_applied) && $invoice->penalty_applied && isset($invoice->penalty_amount) && $invoice->penalty_amount > 0) {
        return (float)$invoice->penalty_amount;
    }
    
    if ($minutes_passed >= $penalty_minutes) {
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
    // اگر جریمه برای این فاکتور غیرفعال شده
    if (isset($invoice->disable_penalty) && (int)$invoice->disable_penalty === 1) {
        return false;
    }

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
    $table = $wpdb->prefix . 'sc_invoices';
    $penalty_minutes = sc_get_penalty_minutes();

    $invoices = $wpdb->get_results(
        "SELECT * FROM $table
         WHERE status = 'pending'
         AND (penalty_applied = 0 OR penalty_applied IS NULL)"
    );

    foreach ($invoices as $invoice) {
        // اگر جریمه غیرفعال شده
        if (isset($invoice->disable_penalty) && (int)$invoice->disable_penalty === 1) {
            continue;
        }

        $created = strtotime($invoice->created_at);
        $now = current_time('timestamp');

        if (($now - $created) >= ($penalty_minutes * 60)) {
            sc_apply_penalty_to_invoice($invoice->id);
        }
    }
}


//افزودن متن به بخش ویرایش کاربر

add_action('edit_user_profile', 'add_filed_help_for_panel_edit_user');
function add_filed_help_for_panel_edit_user() {
    echo "برای ویرایش کاربر باید بخش اعضا ->لیست اعضا مراجعه کنید.سپس  کاربر خودرا جستجو کرده و روی ویرایش کلیک کنید . <br>";
}
//اضافه کردن صورت حساب در تاریخ مشخص در ماه

function sc_get_invoice_mode() {
    return sc_get_setting('invoice_mode', 'interval');
}

function sc_get_invoice_day_of_month() {
    return (int) sc_get_setting('invoice_day_of_month', 1);
}

function sc_get_invoice_hour() {
    return (int) sc_get_setting('invoice_hour', 0);
}

function sc_get_invoice_last_run() {
    return sc_get_setting('invoice_last_run', null);
}

function sc_set_invoice_last_run() {
    sc_update_setting('invoice_last_run', current_time('mysql'), 'invoice');
}

