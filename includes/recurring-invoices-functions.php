<?php
/**
 * Recurring Invoices Functions
 */

/**
 * Create recurring invoices for active courses
 * این تابع باید توسط cron job فراخوانی شود
 */
function sc_create_recurring_invoices() {
    // لاگ شروع اجرای cron
    error_log('SC Recurring Invoices: Cron job started at ' . current_time('mysql'));
    
    if (!class_exists('WooCommerce')) {
        error_log('SC Recurring Invoices: WooCommerce is not active');
        return;
    }
    
    global $wpdb;
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $members_table = $wpdb->prefix . 'sc_members';
    
    $interval_days = sc_get_invoice_interval_days();
    
    error_log("SC Recurring Invoices: Using DAY interval: $interval_days days");
    
    // دریافت تمام دوره‌های active که باید برای آن‌ها صورت حساب ایجاد شود
    // فقط دوره‌هایی که آخرین صورت حساب آن‌ها (چه pending چه paid) بیشتر از interval_days روز از ایجاد آن گذشته باشد
    // و flags (paused, completed, canceled) نداشته باشند
    $active_courses = $wpdb->get_results($wpdb->prepare(
        "SELECT mc.*, c.price, c.title as course_title, m.user_id
         FROM $member_courses_table mc
         INNER JOIN $courses_table c ON mc.course_id = c.id
         INNER JOIN $members_table m ON mc.member_id = m.id
         WHERE mc.status = 'active'
         AND c.deleted_at IS NULL
         AND c.is_active = 1
         AND (
             -- دوره‌هایی که flags ندارند یا flags آن‌ها paused, completed, canceled نیست
             mc.course_status_flags IS NULL
             OR mc.course_status_flags = ''
             OR (
                 mc.course_status_flags NOT LIKE '%%paused%%'
                 AND mc.course_status_flags NOT LIKE '%%completed%%'
                 AND mc.course_status_flags NOT LIKE '%%canceled%%'
             )
         )
         AND (
             -- دوره‌هایی که هیچ صورت حسابی ندارند (اولین صورت حساب)
             NOT EXISTS (
                 SELECT 1 FROM $invoices_table i 
                 WHERE i.member_course_id = mc.id
             )
             OR
             -- دوره‌هایی که آخرین صورت حساب آن‌ها (چه pending چه paid) بیشتر از interval_days روز از ایجاد آن گذشته است
             EXISTS (
                 SELECT 1 FROM $invoices_table i 
                 WHERE i.member_course_id = mc.id 
                 AND TIMESTAMPDIFF(DAY, i.created_at, NOW()) >= %d
                 AND i.created_at = (
                     SELECT MAX(i2.created_at) 
                     FROM $invoices_table i2 
                     WHERE i2.member_course_id = mc.id
                 )
             )
         )",
        $interval_days
    ));
    
    error_log("SC Recurring Invoices: Found " . count($active_courses) . " courses that need invoices");
    
    if (empty($active_courses)) {
        error_log('SC Recurring Invoices: No courses found that need invoices');
        return;
    }
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($active_courses as $member_course) {
        error_log("SC Recurring Invoices: Processing course - Member ID: {$member_course->member_id}, Course ID: {$member_course->course_id}, Course Title: {$member_course->course_title}");
        
        // ایجاد صورت حساب جدید (بدون چک کردن pending)
        $invoice_result = sc_create_course_invoice(
            $member_course->member_id,
            $member_course->course_id,
            $member_course->id,
            $member_course->price
        );
        
        // بررسی نتیجه
        if ($invoice_result && isset($invoice_result['success']) && $invoice_result['success']) {
            $success_count++;
            error_log("SC Recurring Invoices: Invoice created successfully - Invoice ID: {$invoice_result['invoice_id']}, Order ID: {$invoice_result['order_id']}");
        } else {
            $error_count++;
            $error_message = isset($invoice_result['message']) ? $invoice_result['message'] : 'Unknown error';
            error_log("SC Recurring Invoices: Failed to create invoice - Member ID: {$member_course->member_id}, Course ID: {$member_course->course_id}, Error: $error_message");
        }
    }
    
    error_log("SC Recurring Invoices: Cron job completed - Success: $success_count, Errors: $error_count");
}

/**
 * Register cron job for recurring invoices
 */
add_action('init', 'sc_register_recurring_invoices_cron');
function sc_register_recurring_invoices_cron() {
    $cron_interval = 'daily'; // هر 24 ساعت
    
    if (!wp_next_scheduled('sc_daily_recurring_invoices_check')) {
        wp_schedule_event(time(), $cron_interval, 'sc_daily_recurring_invoices_check');
    }
}

/**
 * Hook for cron job
 */
add_action('sc_daily_recurring_invoices_check', 'sc_create_recurring_invoices');

/**
 * Cleanup cron job on deactivation
 * توجه: این تابع باید در فایل اصلی افزونه register شود
 */
function sc_clear_recurring_invoices_cron() {
    $timestamp = wp_next_scheduled('sc_daily_recurring_invoices_check');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'sc_daily_recurring_invoices_check');
    }
}

