<?php
/**
 * Helper functions for Persian DatePicker
 */

if (!function_exists('sc_enqueue_persian_datepicker')) {
    /**
     * اضافه کردن فایل‌های CSS و JS تقویم شمسی
     */
    function sc_enqueue_persian_datepicker() {
        // اضافه کردن فایل JS تقویم شمسی خودمان
        wp_enqueue_script(
            'persian-datepicker-js',
            SC_ASSETS_URL . 'js/persian-datepicker.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        // اضافه کردن CSS ساده برای تقویم
        wp_add_inline_style('admin-bar', '
            .persian-calendar-wrapper {
                font-family: Tahoma, Arial !important;
            }
            .persian-calendar .calendar-day:hover {
                background: #e5f5fa !important;
            }
            .persian-date-input {
                cursor: pointer;
            }
        ');
    }
}

add_action('admin_enqueue_scripts', 'sc_enqueue_persian_datepicker');
add_action('wp_enqueue_scripts', 'sc_enqueue_persian_datepicker');

