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

/**
 * حذف فیلدهای اضافی از صفحه ویرایش کاربر WordPress
 * فقط نام کاربری، رمز عبور و ایمیل نمایش داده می‌شود
 */
add_action('admin_init', 'sc_remove_user_profile_fields');
function sc_remove_user_profile_fields() {
    // حذف بخش‌های اضافی از صفحه ویرایش کاربر
    remove_action('show_user_profile', 'wp_user_contactmethods');
    remove_action('edit_user_profile', 'wp_user_contactmethods');
    
    // حذف فیلدهای WooCommerce از صفحه ویرایش کاربر
    if (class_exists('WooCommerce')) {
        // حذف فیلدهای billing و shipping
        add_filter('woocommerce_customer_meta_fields', '__return_empty_array', 999);
        
        // حذف فیلدهای اضافی WooCommerce
        remove_action('show_user_profile', array('WC_Admin_Profile', 'add_customer_meta_fields'));
        remove_action('edit_user_profile', array('WC_Admin_Profile', 'add_customer_meta_fields'));
        remove_action('personal_options_update', array('WC_Admin_Profile', 'save_customer_meta_fields'));
        remove_action('edit_user_profile_update', array('WC_Admin_Profile', 'save_customer_meta_fields'));
    }
}

/**
 * حذف فیلدهای اضافی از صفحه ویرایش کاربر با استفاده از CSS و JavaScript
 */
add_action('admin_head-user-edit.php', 'sc_hide_user_profile_fields');
add_action('admin_head-profile.php', 'sc_hide_user_profile_fields');
function sc_hide_user_profile_fields() {
    ?>
    <style>
        /* حذف بخش Contact Info */
        #your-profile h2:contains('Contact Info'),
        #your-profile .user-description-wrap,
        #your-profile .user-url-wrap,
        #your-profile .user-first-name-wrap,
        #your-profile .user-last-name-wrap,
        #your-profile .user-nickname-wrap,
        #your-profile .user-display-name-wrap {
            display: none !important;
        }
        
        /* حذف بخش About Yourself */
        #your-profile h2:contains('About Yourself'),
        #your-profile .user-rich-editing-wrap,
        #your-profile .user-syntax-highlighting-wrap,
        #your-profile .user-comment-shortcuts-wrap,
        #your-profile .user-admin-color-wrap,
        #your-profile .user-admin-bar-front-wrap,
        #your-profile .user-language-wrap {
            display: none !important;
        }
        
        /* حذف بخش Account Management */
        #your-profile h2:contains('Account Management'),
        #your-profile .user-sessions-wrap {
            display: none !important;
        }
        
        /* حذف فیلدهای WooCommerce */
        #your-profile .woocommerce-customer-data,
        #your-profile h2:contains('Billing'),
        #your-profile h2:contains('Shipping'),
        #your-profile .form-table:has(th:contains('Billing')),
        #your-profile .form-table:has(th:contains('Shipping')) {
            display: none !important;
        }
        
        /* حذف تمام فیلدها به جز نام کاربری، رمز و ایمیل */
        #your-profile .form-table tr:not(:has(#user_login)):not(:has(#user_pass)):not(:has(#user_email)) {
            display: none !important;
        }
    </style>
    <script>
        jQuery(document).ready(function($) {
            // حذف بخش‌های اضافی با JavaScript
            $('#your-profile h2').each(function() {
                var $h2 = $(this);
                var text = $h2.text().toLowerCase();
                if (text.indexOf('contact') !== -1 || 
                    text.indexOf('about') !== -1 || 
                    text.indexOf('account management') !== -1 ||
                    text.indexOf('billing') !== -1 ||
                    text.indexOf('shipping') !== -1 ||
                    text.indexOf('اطلاعات تماس') !== -1 ||
                    text.indexOf('درباره') !== -1 ||
                    text.indexOf('مدیریت') !== -1 ||
                    text.indexOf('آدرس') !== -1) {
                    // حذف h2 و تمام محتوای بعد از آن تا h2 بعدی
                    var $next = $h2.nextUntil('h2');
                    $h2.hide();
                    $next.hide();
                }
            });
            
            // حذف فیلدهای اضافی
            $('#your-profile .form-table tr').each(function() {
                var $row = $(this);
                var hasLogin = $row.find('#user_login').length > 0;
                var hasPass = $row.find('#user_pass, #pass1').length > 0;
                var hasEmail = $row.find('#user_email').length > 0;
                
                if (!hasLogin && !hasPass && !hasEmail) {
                    $row.hide();
                }
            });
            
            // حذف فیلدهای WooCommerce
            $('#your-profile').find('tr').each(function() {
                var $row = $(this);
                var thText = $row.find('th').text().toLowerCase();
                if (thText.indexOf('billing') !== -1 || 
                    thText.indexOf('shipping') !== -1 ||
                    thText.indexOf('آدرس') !== -1) {
                    $row.hide();
                }
            });
        });
    </script>
    <?php
}

/**
 * حذف فیلدهای اضافی با استفاده از filter
 */
add_filter('user_contactmethods', '__return_empty_array', 999);
add_filter('show_password_fields', '__return_true', 999);
