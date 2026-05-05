<?php
/**
 * Settings Functions
 */
if ( ! defined('ABSPATH') ) exit;
/**
 * Format amount for display - use "(منفی)" instead of "-" for negative amounts
 * فرمت نمایش مبلغ - برای اعداد منفی از "(منفی)" به جای "-" استفاده می‌شود
 */
function sc_format_amount_display($amount) {
    $amount = floatval($amount);
    $formatted = number_format(abs($amount), 0, '.', ',');
    return $amount < 0 ? '(منفی) ' . $formatted : $formatted;
}

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

function sc_get_invoice_minute() {
    return (int) sc_get_setting('invoice_minute', 0);
}

function sc_get_invoice_last_run() {
    return sc_get_setting('invoice_last_run', null);
}

function sc_set_invoice_last_run() {
    sc_update_setting('invoice_last_run', current_time('mysql'), 'invoice');
}

/**
 * Check if Pro feature: Notifications is enabled
 * بررسی فعال بودن امکانات پرو: اطلاعیه‌ها
 */
function sc_is_pro_feature_notifications_enabled() {
    return (int) sc_get_setting('pro_feature_notifications', '0') === 1;
}
//sms
function sc_is_pro_feature_sms_enabled() {
    return (int) sc_get_setting('pro_feature_sms', '0') === 1;
}
/**
 * Check if Pro feature: Coaches is enabled
 * بررسی فعال بودن امکانات پرو: مربیان
 */
function sc_is_pro_feature_coaches_enabled() {
    return (int) sc_get_setting('pro_feature_coaches', '0') === 1;
}

/**
 * Check if Pro feature: Coaches Wallet and Salary is enabled
 * بررسی فعال بودن امکانات پرو: کیف پول مربیان و دستمزد
 */
function sc_is_pro_feature_coaches_wallet_salary_enabled() {
    return (int) sc_get_setting('pro_feature_coaches_wallet_salary', '0') === 1;
}

/**
 * Check if Pro feature: Players Wallet is enabled
 * بررسی فعال بودن امکانات پرو: کیف پول بازیکنان
 */
function sc_is_pro_feature_players_wallet_enabled() {
    return (int) sc_get_setting('pro_feature_players_wallet', '0') === 1;
}

/**
 * Check if players wallet can be shown (pro feature + wallet setting)
 * بررسی امکان نمایش کیف پول بازیکنان (امکانات پرو + تنظیم کیف پول)
 * برای منوی کاربر و عملیات کیف پول استفاده شود
 */
function sc_can_show_players_wallet() {
    if (!sc_is_pro_feature_players_wallet_enabled()) {
        return false;
    }
    return (int) sc_get_setting('wallet_enabled', '0') === 1;
}

/**
 * Check if player verification is required before accessing account sections
 */
function sc_is_player_verification_required() {
    return (int) sc_get_setting('player_verification_required', '0') === 1;
}
function debt_user($id){
     global $wpdb;
    // محاسبه بدهکاری (صورت حساب‌های pending و under_review)
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $wallet_balance = function_exists('sc_get_wallet_balance') ? sc_get_wallet_balance($id) : 0;
    $debt_wallet = 0;
    if($wallet_balance < 0){
        $debt_wallet = abs($wallet_balance);
    }
    
    $debt_info = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as count,
            SUM(amount + COALESCE(penalty_amount, 0)) as total_debt
         FROM $invoices_table
         WHERE member_id = %d 
         AND status IN ('pending', 'under_review') AND (course_id > 0 OR invoice_description IS NOT NULL)",
        $id
    ));
    $debt_count = $debt_info->count ?? 0;
    $total_debt = floatval($debt_info->total_debt ?? 0);
    $debt = $total_debt + $debt_wallet;
    return [$debt,$debt_count];
}


// تغییر فرمت درست شماره تماس ها 

// تبدیل اعداد فارسی و عربی به انگلیسی
function fa_to_en_digits( $string ) {
    $western = ['0','1','2','3','4','5','6','7','8','9'];
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];

    $string = str_replace( $persian, $western, $string );
    return str_replace( $arabic, $western, $string );
}

/**
 * نرمال‌سازی شماره موبایل ایران
 */
function sanitize_iran_phone( $phone ) {

    $phone = fa_to_en_digits( trim( $phone ) );

    // حذف فاصله و نقطه
    $phone = str_replace( [' ', '.'], '', $phone );

    // .915xxxxxxx
    if ( strpos( $phone, '.' ) === 0 ) {
        $phone = '0' . substr( $phone, 1 );
    }

    // 0098xxxxxxxxxx
    if ( strpos( $phone, '0098' ) === 0 ) {
        $phone = substr( $phone, 4 );
    }

    // +98xxxxxxxxxx
    if ( strpos( $phone, '+98' ) === 0 ) {
        $phone = substr( $phone, 3 );
    }

    // 98xxxxxxxxxx
    if ( strpos( $phone, '98' ) === 0 ) {
        $phone = substr( $phone, 2 );
    }

    // اضافه کردن صفر در صورت نبود
    if ( strpos( $phone, '0' ) !== 0 ) {
        $phone = '0' . $phone;
    }

    // فقط عدد باشد
    if ( ! ctype_digit( $phone ) ) {
        return false;
    }

    // طول موبایل ایران
    if ( strlen( $phone ) !== 11 ) {
        return false;
    }

    // شروع با 09
    if ( strpos( $phone, '09' ) !== 0 ) {
        return false;
    }

    return $phone;
}
add_filter( 'woocommerce_checkout_posted_data', function ( $data ) {

    if ( ! empty( $data['billing_phone'] ) ) {
        $phone = sanitize_iran_phone( $data['billing_phone'] );

        if ( $phone !== false ) {
            $data['billing_phone'] = $phone;
        }
    }

    return $data;
});
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {

    if ( empty( $data['billing_phone'] ) ) {
        return;
    }

    $phone = sanitize_iran_phone( $data['billing_phone'] );

    if ( $phone === false ) {
        $errors->add(
            'billing_phone_error',
            'شماره موبایل وارد شده معتبر نیست.'
        );
    }
}, 100, 2 );


//پایان فرمت صحیح شماره تماس ها 



function get_cart_item_count() {
    $cart = WC()->cart;

    if ( $cart ) {
        return ['count' => $cart->get_cart_contents_count() , 'sum' => number_format( $cart->get_total( true ) )]; // تعداد کل اقلام (با توجه به تعداد هر محصول)
       
    }
    return 0;
}

//کم کردن یک جلسه برای کاربر
function sc_decrease_member_session($member_id, $course_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'sc_member_courses';

    $member_course = $wpdb->get_row($wpdb->prepare(
        "SELECT id, remaining_sessions 
         FROM $table 
         WHERE member_id = %d AND course_id = %d AND status = 'active'
         LIMIT 1",
        $member_id,
        $course_id
    ));

    if (!$member_course) {
        return false;
    }

    if ($member_course->remaining_sessions <= 0) {
        return false;
    }

    $wpdb->query($wpdb->prepare(
        "UPDATE $table
         SET remaining_sessions = remaining_sessions - 1,
             updated_at = %s
         WHERE id = %d",
        current_time('mysql'),
        $member_course->id
    ));

    return true;
}
//حذف سوخت جلسه غیبت - بازگرداندن یک جلسه  افزایش یک جلسه

function sc_increase_member_session($member_id, $course_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_member_courses';

    // پیدا کردن رکورد فعال کاربر در دوره
    $member_course = $wpdb->get_row($wpdb->prepare("
        SELECT id FROM $table 
        WHERE member_id = %d AND course_id = %d AND status = 'active'
        LIMIT 1
    ", $member_id, $course_id));

    if (!$member_course) return false;

    // افزایش یک جلسه
    $wpdb->query($wpdb->prepare("
        UPDATE $table 
        SET remaining_sessions = remaining_sessions + 1, updated_at = %s 
        WHERE id = %d
    ", current_time('mysql'), $member_course->id));

    return true;
}







