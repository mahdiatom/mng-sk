<?php
/**
 * WooCommerce Settings
 * تنظیمات ووکامرس - غیرفعال کردن منوهای پیش‌فرض حساب کاربری
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * غیرفعال کردن منوهای پیش‌فرض حساب کاربری ووکامرس
 * فقط "خروج" باقی می‌ماند
 * 
 * اولویت 5 برای اجرا قبل از sc_add_my_account_menu_item (اولویت 10)
 */
add_filter('woocommerce_account_menu_items', 'sc_remove_default_account_menu_items', 5, 1);
function sc_remove_default_account_menu_items($items) {
    // اگر کاربر مدیر است، منوهای پیش‌فرض را نگه دار
    if (current_user_can('manage_options')) {
        return $items;
    }
    
    // فقط "خروج" را نگه دار و بقیه را حذف کن
    $logout = isset($items['customer-logout']) ? $items['customer-logout'] : 'خروج';
    
    // حذف تمام منوهای پیش‌فرض
    $items = [];
    
    // فقط "خروج" را اضافه کن
    $items['customer-logout'] = $logout;
    
    return $items;
}

