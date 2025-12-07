<?php
/**
 * User Registration Handler
 * مدیریت ثبت‌نام خودکار کاربران در افزونه
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ایجاد خودکار کاربر در افزونه هنگام ثبت‌نام در WordPress
 */
add_action('user_register', 'sc_auto_create_member_on_registration', 10, 1);
function sc_auto_create_member_on_registration($user_id) {
    // بررسی و ایجاد جداول
    sc_check_and_create_tables();
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    
    // بررسی اینکه آیا این کاربر قبلاً در جدول members وجود دارد
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d LIMIT 1",
        $user_id
    ));
    
    // اگر وجود داشت، نیازی به ایجاد نیست
    if ($existing) {
        return;
    }
    
    // دریافت اطلاعات کاربر
    $user = get_userdata($user_id);
    if (!$user) {
        return;
    }
    
    // دریافت اطلاعات از user meta
    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);
    $billing_phone = get_user_meta($user_id, 'billing_phone', true);
    
    // اگر first_name و last_name وجود نداشت، از display_name استفاده می‌کنیم
    if (empty($first_name) || empty($last_name)) {
        $display_name = $user->display_name;
        $name_parts = explode(' ', $display_name, 2);
        if (count($name_parts) >= 2) {
            $first_name = $first_name ?: $name_parts[0];
            $last_name = $last_name ?: $name_parts[1];
        } else {
            $first_name = $first_name ?: $display_name;
            $last_name = $last_name ?: '';
        }
    }
    
    // اگر هنوز خالی است، از user_login استفاده می‌کنیم
    if (empty($first_name)) {
        $first_name = $user->user_login;
    }
    if (empty($last_name)) {
        $last_name = '';
    }
    
    // ایجاد کد ملی موقت از user_id (اگر کد ملی وجود نداشت)
    $national_id = str_pad($user_id, 10, '0', STR_PAD_LEFT);
    
    // بررسی تکراری بودن کد ملی
    $duplicate = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE national_id = %s LIMIT 1",
        $national_id
    ));
    
    if ($duplicate) {
        // اگر تکراری بود، عدد اضافه می‌کنیم
        $counter = 1;
        while ($duplicate) {
            $national_id = str_pad($user_id . $counter, 10, '0', STR_PAD_LEFT);
            $duplicate = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE national_id = %s LIMIT 1",
                $national_id
            ));
            $counter++;
        }
    }
    
    // آماده‌سازی داده‌ها
    $data = [
        'user_id' => $user_id,
        'first_name' => sanitize_text_field($first_name),
        'last_name' => sanitize_text_field($last_name),
        'national_id' => $national_id,
        'player_phone' => !empty($billing_phone) ? sanitize_text_field($billing_phone) : NULL,
        'health_verified' => 0,
        'info_verified' => 0,
        'is_active' => 1,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ];
    
    // آماده‌سازی format array برای insert
    $format = [];
    foreach ($data as $key => $value) {
        if ($value === NULL) {
            $format[] = '%s'; // NULL
        } elseif (in_array($key, ['health_verified', 'info_verified', 'is_active', 'user_id'])) {
            $format[] = '%d'; // integer
        } else {
            $format[] = '%s'; // string
        }
    }
    
    // درج کاربر در جدول members
    $inserted = $wpdb->insert($table_name, $data, $format);
    
    if ($inserted === false) {
        // اگر خطا در ایجاد کاربر بود، لاگ کن
        if ($wpdb->last_error) {
            error_log('SC Auto Create Member Error: ' . $wpdb->last_error);
            error_log('SC Auto Create Member Query: ' . $wpdb->last_query);
        }
    }
}

