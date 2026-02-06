<?php
/**
 * Recurring Invoices Functions
 */



/**
 * ایجاد صورت حساب برای پرداخت در تاریخ مشخص 
 */
function sc_is_fixed_invoice_time() {

    if (sc_get_invoice_mode() !== 'fixed_date') {
        return true; // interval mode
    }
    $now = current_time('timestamp');
    $days_in_month = (int) date('t', $now); 
    
    
    $day_shamsi  = sc_get_invoice_day_of_month();
    $shift = 19;
    $day = $shift + $day_shamsi;
if ($day > $days_in_month) {
    $day -= $days_in_month;
}

    $hour = sc_get_invoice_hour();
    $last = sc_get_invoice_last_run();

    $now = current_time('timestamp');

    if ((int)date('j', $now) !== $day) return false;
    if ((int)date('G', $now) < $hour) return false;

    if ($last && date('Y-m', strtotime($last)) === date('Y-m', $now)) {
        return false;
    }

    return true;
}


/**
 * Create recurring invoices for active courses
 * این تابع باید توسط cron job فراخوانی شود
 */


function sc_create_recurring_invoices() {

    if (!sc_is_fixed_invoice_time()) {
    return;
}

sc_set_invoice_last_run();


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
    
   
 


    $interval_minutes = sc_get_invoice_interval_minutes();
    
    error_log("SC Recurring Invoices: Using MINUTE interval: $interval_minutes minutes");
    //برای اعمال شرط برای ثبت  صورت حساب در تاریخ مشخص
    $fixed_date_mode = (sc_get_invoice_mode() === 'fixed_date');
    $where_month_lock = '';

    if ($fixed_date_mode) {
        $where_month_lock = "
            AND NOT EXISTS (
                SELECT 1 FROM $invoices_table i2
                WHERE i2.member_course_id = mc.id
                AND DATE_FORMAT(i2.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
            )
        ";
    }
    // دریافت تمام دوره‌های active که باید برای آن‌ها صورت حساب ایجاد شود
    // فقط دوره‌هایی که آخرین صورت حساب آن‌ها (چه pending چه paid) بیشتر از interval_days روز از ایجاد آن گذشته باشد
    // و flags (paused, completed, canceled) نداشته باشند


    
    $active_courses = $wpdb->get_results($wpdb->prepare(
        "SELECT mc.*, c.price, c.title as course_title, m.user_id, m.disable_auto_invoice
         FROM $member_courses_table mc
         INNER JOIN $courses_table c ON mc.course_id = c.id
         INNER JOIN $members_table m ON mc.member_id = m.id
         WHERE mc.status = 'active'
         AND c.deleted_at IS NULL
         AND c.is_active = 1
         AND m.is_active = 1
         $where_month_lock
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
             -- دوره‌هایی که آخرین صورت حساب آن‌ها (چه pending چه paid) بیشتر از interval_minutes دقیقه از ایجاد آن گذشته است
             EXISTS (
                 SELECT 1 FROM $invoices_table i 
                 WHERE i.member_course_id = mc.id 
                 AND TIMESTAMPDIFF(MINUTE, i.created_at, NOW()) >= %d
                 AND i.created_at = (
                     SELECT MAX(i2.created_at) 
                     FROM $invoices_table i2 
                     WHERE i2.member_course_id = mc.id
                 )
             )
         )",
        $interval_minutes
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
        if (isset($member_course->disable_auto_invoice) && $member_course->disable_auto_invoice == 1) {
        error_log("SC Recurring Invoices: Auto invoice disabled for Member ID: {$member_course->member_id}. Skipping.");
        continue; // این کاربر نادیده گرفته می‌شود
    }
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

            // ارسال SMS صورت حساب
            do_action('sc_invoice_created', $invoice_result['invoice_id']);
        } else {
            $error_count++;
            $error_message = isset($invoice_result['message']) ? $invoice_result['message'] : 'Unknown error';
            error_log("SC Recurring Invoices: Failed to create invoice - Member ID: {$member_course->member_id}, Course ID: {$member_course->course_id}, Error: $error_message");
        }
    }
    
    error_log("SC Recurring Invoices: Cron job completed - Success: $success_count, Errors: $error_count");
}

/**
 * Add custom cron interval for every minute
 */
add_filter('cron_schedules', 'sc_add_every_minute_cron_schedule');
function sc_add_every_minute_cron_schedule($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60, // 60 seconds
        'display'  => __('Every Minute')
    );
    return $schedules;
}

/**
 * Register cron job for recurring invoices
 */
add_action('init', 'sc_register_recurring_invoices_cron');
function sc_register_recurring_invoices_cron() {
    $cron_interval = 'every_minute'; // هر دقیقه
    
    if (!wp_next_scheduled('sc_every_minute_recurring_invoices_check')) {
        wp_schedule_event(time(), $cron_interval, 'sc_every_minute_recurring_invoices_check');
    }
}

/**
 * Hook for cron job
 */
add_action('sc_every_minute_recurring_invoices_check', 'sc_create_recurring_invoices');

/**
 * بررسی و اعمال flag paused برای دوره‌هایی با 3 یا بیشتر صورت حساب pending
 * این تابع باید توسط cron job روزانه فراخوانی شود
 */
function sc_check_and_pause_courses_with_unpaid_invoices() {
    error_log('SC Pause Courses: Checking for courses with 3+ pending invoices');
    
    global $wpdb;
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $members_table = $wpdb->prefix . 'sc_members';
    
    // دریافت تمام دوره‌های فعال که 3 یا بیشتر صورت حساب pending دارند
    // فقط صورت حساب‌هایی که مربوط به دوره هستند (دارای course_id و member_course_id)
    $courses_to_pause = $wpdb->get_results(
        "SELECT mc.id, mc.member_id, mc.course_id, mc.course_status_flags, c.title as course_title,
                COUNT(i.id) as pending_count
         FROM $member_courses_table mc
         INNER JOIN $courses_table c ON mc.course_id = c.id
         INNER JOIN $members_table m ON mc.member_id = m.id
         INNER JOIN $invoices_table i ON i.member_course_id = mc.id
         WHERE mc.status = 'active'
         AND c.deleted_at IS NULL
         AND c.is_active = 1
         AND m.is_active = 1
         AND i.status = 'pending'
         AND i.member_course_id IS NOT NULL
         AND i.course_id IS NOT NULL
         AND i.course_id > 0
         GROUP BY mc.id
         HAVING pending_count >= 3"
    );
    
    if (empty($courses_to_pause)) {
        error_log('SC Pause Courses: No courses found with 3+ pending invoices');
        return;
    }
    
    error_log("SC Pause Courses: Found " . count($courses_to_pause) . " courses to pause");
    
    $paused_count = 0;
    
    foreach ($courses_to_pause as $course) {
        // بررسی اینکه آیا قبلاً paused نشده است
        $current_flags = $course->course_status_flags;
        
        // اگر قبلاً paused نشده باشد
        if (empty($current_flags) || 
            (strpos($current_flags, 'paused') === false)) {
            
            // اضافه کردن flag paused
            $new_flags = empty($current_flags) ? 'paused' : $current_flags . ',paused';
            
            // به‌روزرسانی flag
            $updated = $wpdb->update(
                $member_courses_table,
                ['course_status_flags' => $new_flags],
                ['id' => $course->id],
                ['%s'],
                ['%d']
            );
            
            if ($updated !== false) {
                $paused_count++;
                error_log("SC Pause Courses: Course paused - Member Course ID: {$course->id}, Member ID: {$course->member_id}, Course ID: {$course->course_id}, Course Title: {$course->course_title}, Pending Invoices: {$course->pending_count}");
            } else {
                error_log("SC Pause Courses: Failed to pause course - Member Course ID: {$course->id}");
            }
        } else {
            error_log("SC Pause Courses: Course already paused - Member Course ID: {$course->id}");
        }
    }
    
    error_log("SC Pause Courses: Completed - Paused: $paused_count courses");
}

/**
 * اضافه کردن تابع به cron job هر دقیقه
 */
add_action('sc_every_minute_recurring_invoices_check', 'sc_check_and_pause_courses_with_unpaid_invoices');

/**
 * ارسال یادآوری پرداخت برای صورت حساب‌های معوق
 */
function sc_send_payment_reminders() {
    error_log('SC Payment Reminders: Starting payment reminder check');

    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';

    // دریافت صورت حساب‌های pending که مدت زمان مشخص شده از ایجاد آن‌ها گذشته
    $reminder_delay_minutes = sc_get_reminder_delay_minutes();
    $reminder_invoices = $wpdb->get_results(
        "SELECT * FROM $invoices_table
         WHERE status = 'pending'
         AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) >= $reminder_delay_minutes
         AND (last_reminder_sent IS NULL OR TIMESTAMPDIFF(DAY, last_reminder_sent, NOW()) >= 7)"
    );

    if (empty($reminder_invoices)) {
        error_log('SC Payment Reminders: No invoices need reminders');
        return;
    }

    $reminder_count = 0;

    foreach ($reminder_invoices as $invoice) {
        // ارسال SMS یادآوری پرداخت
        do_action('sc_payment_reminder', $invoice->id);

        // بروزرسانی زمان آخرین یادآوری
        $wpdb->update(
            $invoices_table,
            ['last_reminder_sent' => current_time('mysql')],
            ['id' => $invoice->id],
            ['%s'],
            ['%d']
        );

        $reminder_count++;
        error_log("SC Payment Reminders: Reminder sent for invoice ID: {$invoice->id}");
    }

    error_log("SC Payment Reminders: Completed - Sent: $reminder_count reminders");
}

/**
 * اضافه کردن یادآوری پرداخت به cron job روزانه
 */
add_action('sc_every_minute_recurring_invoices_check', 'sc_send_payment_reminders');


add_action('sc_every_minute_recurring_invoices_check', 'sc_check_and_apply_penalties');

/**
 * Cleanup cron job on deactivation
 * توجه: این تابع باید در فایل اصلی افزونه register شود
 */
function sc_clear_recurring_invoices_cron() {
    $timestamp = wp_next_scheduled('sc_every_minute_recurring_invoices_check');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'sc_every_minute_recurring_invoices_check');
    }
}

