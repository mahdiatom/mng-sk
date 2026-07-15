<?php
/**
 * Recurring Invoices Functions
 */



if (!defined('ABSPATH')) {
    exit;
}


/**
 * ایجاد صورت حساب برای پرداخت در تاریخ مشخص
 * منطق بر اساس تقویم شمسی (جلالی) مشابه دستمزد مربی
 */
 
function sc_is_fixed_invoice_time() {
    if (sc_get_invoice_mode() !== 'fixed_date') {
        return true; // در حالت interval همیشه اجازه اجرا (بر اساس فاصله زمانی)
    }

    return !empty(sc_get_due_fixed_invoice_context());
}


/**
 * Create recurring invoices for active courses
 * این تابع باید توسط cron job فراخوانی شود
 */


function sc_create_recurring_invoices() {

    $invoice_mode = sc_get_invoice_mode();

    if ($invoice_mode === 'sessions_threshold') {
        return sc_create_threshold_invoices();
    }

    if ($invoice_mode === 'fixed_date') {
        if (!sc_is_fixed_invoice_time()) {
            return;
        }
        return sc_create_fixed_date_monthly_invoices();
    }

    // حالت interval — اجرای دوره‌ای بر اساس فاصلهٔ زمانی
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

    // فقط اگر برای این ثبت‌نام هنوز هیچ فاکتوری نباشد.
    // قبلاً با گذشت interval دوباره فاکتور می‌ساخت حتی اگر فاکتور قبلی «تایید پرداخت» شده بود.
    $active_courses = $wpdb->get_results(
        "SELECT mc.*, c.price, c.title as course_title, m.user_id, m.disable_auto_invoice
         FROM $member_courses_table mc
         INNER JOIN $courses_table c ON mc.course_id = c.id
         INNER JOIN $members_table m ON mc.member_id = m.id
         WHERE mc.status = 'active'
         AND c.deleted_at IS NULL
         AND c.is_active = 1
         AND m.is_active = 1
         AND (
             mc.course_status_flags IS NULL
             OR mc.course_status_flags = ''
             OR (
                 mc.course_status_flags NOT LIKE '%%paused%%'
                 AND mc.course_status_flags NOT LIKE '%%completed%%'
                 AND mc.course_status_flags NOT LIKE '%%canceled%%'
             )
         )
         AND NOT EXISTS (
             SELECT 1 FROM $invoices_table i
             WHERE i.member_course_id = mc.id
                OR (i.member_id = mc.member_id AND i.course_id = mc.course_id)
         )"
    );

    error_log('SC Recurring Invoices: Found ' . count($active_courses) . ' courses that need invoices');

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
            continue;
        }
        if (function_exists('sc_is_member_team') && sc_is_member_team($member_course->member_id)) {
            error_log("SC Recurring Invoices: Member ID {$member_course->member_id} is team player. Skipping invoice.");
            continue;
        }
        $amount_for_invoice = function_exists('sc_get_course_billing_amount_for_member_course')
            ? sc_get_course_billing_amount_for_member_course((object) ['id' => $member_course->course_id, 'price' => $member_course->price, 'title' => $member_course->course_title], $member_course)
            : (float) $member_course->price;
        $fee_label = function_exists('sc_course_enrollment_fee_label')
            ? sc_course_enrollment_fee_label($member_course->course_title, !empty($member_course->enrollment_sessions) ? (int) $member_course->enrollment_sessions : null)
            : ('ثبت نام دوره: ' . $member_course->course_title);
        $invoice_result = sc_create_course_invoice(
            $member_course->member_id,
            $member_course->course_id,
            $member_course->id,
            $amount_for_invoice,
            'system defalt',
            $fee_label
        );

        if ($invoice_result && isset($invoice_result['success']) && $invoice_result['success']) {
            $success_count++;
            error_log("SC Recurring Invoices: Invoice created successfully - Invoice ID: {$invoice_result['invoice_id']}, Order ID: {$invoice_result['order_id']}");
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
 * Build the configured invoice timestamp for a Jalali year/month.
 */
function sc_get_fixed_invoice_target_timestamp($jalali_year, $jalali_month) {
    if (!function_exists('jalali_to_gregorian')) {
        return 0;
    }

    $jalali_year = (int) $jalali_year;
    $jalali_month = (int) $jalali_month;
    $last_day = function_exists('jalali_days_in_month')
        ? jalali_days_in_month($jalali_month, $jalali_year)
        : ($jalali_month <= 6 ? 31 : ($jalali_month <= 11 ? 30 : 29));
    $configured_day = (int) sc_get_invoice_day_of_month();
    $target_day = $configured_day > 0 ? min($configured_day, $last_day) : $last_day;
    $gregorian = jalali_to_gregorian($jalali_year, $jalali_month, $target_day);
    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(date_default_timezone_get());
    $target = new DateTimeImmutable(
        sprintf(
            '%04d-%02d-%02d %02d:%02d:00',
            $gregorian[0],
            $gregorian[1],
            $gregorian[2],
            sc_get_invoice_hour(),
            sc_get_invoice_minute()
        ),
        $timezone
    );

    return $target->getTimestamp();
}

/**
 * Return the latest billing period whose configured due time has passed.
 *
 * Before this month's due time, only the immediately previous missed period is
 * eligible and only when fixed-date billing has a successful older run. This
 * prevents a fresh installation from creating a retroactive invoice.
 *
 * @return array{period:string,due_timestamp:int}|array{}
 */
function sc_get_due_fixed_invoice_context() {
    if (!function_exists('gregorian_to_jalali') || !function_exists('jalali_to_gregorian')) {
        return [];
    }

    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(date_default_timezone_get());
    $now = new DateTimeImmutable('now', $timezone);
    $today_j = gregorian_to_jalali(
        (int) $now->format('Y'),
        (int) $now->format('m'),
        (int) $now->format('d')
    );
    $current_year = (int) $today_j[0];
    $current_month = (int) $today_j[1];
    $current_target = sc_get_fixed_invoice_target_timestamp($current_year, $current_month);
    $current_period = sprintf('%04d-%02d', $current_year, $current_month);

    if ($current_target > 0 && $now->getTimestamp() >= $current_target) {
        return [
            'period' => $current_period,
            'due_timestamp' => $current_target,
        ];
    }

    $previous_year = $current_year;
    $previous_month = $current_month - 1;
    if ($previous_month < 1) {
        $previous_month = 12;
        $previous_year--;
    }
    $previous_period = sprintf('%04d-%02d', $previous_year, $previous_month);
    $last_run_period = function_exists('sc_get_invoice_last_run_period')
        ? sc_get_invoice_last_run_period()
        : '';

    if ($last_run_period !== '' && strcmp($last_run_period, $previous_period) < 0) {
        return [
            'period' => $previous_period,
            'due_timestamp' => sc_get_fixed_invoice_target_timestamp($previous_year, $previous_month),
        ];
    }

    return [];
}

/**
 * صورتحساب ماهانهٔ دوره در حالت تاریخ ثابت (تقویم شمسی)
 */
function sc_create_fixed_date_monthly_invoices() {
    error_log('SC Fixed-Date Invoices: Cron started at ' . current_time('mysql'));

    if (!class_exists('WooCommerce')) {
        error_log('SC Fixed-Date Invoices: WooCommerce is not active');
        return;
    }

    global $wpdb;
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $members_table = $wpdb->prefix . 'sc_members';

    $due_context = sc_get_due_fixed_invoice_context();
    $billing_period = isset($due_context['period']) ? (string) $due_context['period'] : '';
    if ($billing_period === '') {
        error_log('SC Fixed-Date Invoices: No due Jalali billing period');
        return;
    }
    if (!function_exists('sc_invoices_support_billing_columns') || !sc_invoices_support_billing_columns()) {
        error_log('SC Fixed-Date Invoices: Billing period columns are unavailable; retrying on a later cron run');
        return;
    }

    $lock_name = 'sc_fixed_invoice_' . get_current_blog_id() . '_' . $billing_period;
    $lock_acquired = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock_name));
    if ($lock_acquired !== 1) {
        error_log("SC Fixed-Date Invoices: Another process is handling period {$billing_period}");
        return;
    }

    $status_where = "mc.status = 'active'";
    if (function_exists('sc_member_courses_support_billing_deferred') && sc_member_courses_support_billing_deferred()) {
        $status_where = "(mc.status = 'active' OR (mc.status = 'inactive' AND mc.billing_deferred = 1))";
    }

    $active_courses = $wpdb->get_results(
        "SELECT mc.*, c.price, c.title AS course_title, c.sessions_count, c.price_per_session, m.user_id, m.disable_auto_invoice
         FROM {$member_courses_table} mc
         INNER JOIN {$courses_table} c ON mc.course_id = c.id
         INNER JOIN {$members_table} m ON mc.member_id = m.id
         WHERE {$status_where}
         AND c.deleted_at IS NULL
         AND c.is_active = 1
         AND m.is_active = 1
         AND (
             mc.course_status_flags IS NULL
             OR mc.course_status_flags = ''
             OR (
                 mc.course_status_flags NOT LIKE '%paused%'
                 AND mc.course_status_flags NOT LIKE '%completed%'
                 AND mc.course_status_flags NOT LIKE '%canceled%'
             )
         )"
    );

    if ($wpdb->last_error !== '') {
        error_log('SC Fixed-Date Invoices: Candidate query failed: ' . $wpdb->last_error);
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        return;
    }

    error_log('SC Fixed-Date Invoices: Found ' . count($active_courses) . ' candidate enrollments for period ' . $billing_period);

    $success_count = 0;
    $error_count = 0;
    $month_names = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    $period_label = $billing_period;
    $pp = explode('-', $billing_period);
    if (count($pp) === 2) {
        $mi = (int) $pp[1];
        if (isset($month_names[$mi])) {
            $period_label = $month_names[$mi] . ' ' . $pp[0];
        }
    }

    foreach ($active_courses as $member_course) {
        if (function_exists('sc_should_create_monthly_invoice_for_member_course')
            && !sc_should_create_monthly_invoice_for_member_course($member_course, $billing_period)) {
            continue;
        }

        if (isset($member_course->disable_auto_invoice) && (int) $member_course->disable_auto_invoice === 1) {
            continue;
        }
        if (function_exists('sc_is_member_team') && sc_is_member_team($member_course->member_id)) {
            continue;
        }

        $course_obj = (object) [
            'id' => (int) $member_course->course_id,
            'price' => (float) $member_course->price,
            'title' => (string) $member_course->course_title,
            'sessions_count' => isset($member_course->sessions_count) ? (int) $member_course->sessions_count : 0,
            'price_per_session' => isset($member_course->price_per_session) ? (float) $member_course->price_per_session : 0,
        ];
        $amount_for_invoice = function_exists('sc_get_course_billing_amount_for_member_course')
            ? sc_get_course_billing_amount_for_member_course($course_obj, $member_course)
            : (float) $member_course->price;
        $fee_label = sprintf('صورت‌حساب ماهانه %s — %s', (string) $member_course->course_title, $period_label);

        $billing_meta = [
            'billing_period_shamsi' => $billing_period,
            'billing_sessions_count' => !empty($member_course->enrollment_sessions)
                ? (int) $member_course->enrollment_sessions
                : (isset($member_course->sessions_count) ? (int) $member_course->sessions_count : 0),
        ];

        $invoice_result = sc_create_course_invoice(
            $member_course->member_id,
            $member_course->course_id,
            $member_course->id,
            $amount_for_invoice,
            'monthly_regular',
            $fee_label,
            null,
            $billing_meta
        );

        if ($invoice_result && !empty($invoice_result['success'])) {
            $success_count++;
            if (!empty($invoice_result['invoice_id'])) {
                do_action('sc_invoice_created', (int) $invoice_result['invoice_id']);
            }
        } else {
            $error_count++;
            $error_message = isset($invoice_result['message']) ? $invoice_result['message'] : 'Unknown error';
            error_log("SC Fixed-Date Invoices: Failed MC {$member_course->id}: {$error_message}");
        }
    }

    if ($error_count === 0) {
        sc_set_invoice_last_run($billing_period);
    } else {
        error_log("SC Fixed-Date Invoices: Period {$billing_period} remains open for retry");
    }
    $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
    error_log("SC Fixed-Date Invoices: Completed period {$billing_period} — Success: {$success_count}, Errors: {$error_count}");
}

/**
 * Resolve the next fixed Jalali invoice date as a WordPress-local timestamp.
 */
function sc_get_next_fixed_invoice_timestamp() {
    if (!function_exists('gregorian_to_jalali') || !function_exists('jalali_to_gregorian')) {
        return 0;
    }

    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(date_default_timezone_get());
    $now = new DateTimeImmutable('now', $timezone);
    $today_j = gregorian_to_jalali(
        (int) $now->format('Y'),
        (int) $now->format('m'),
        (int) $now->format('d')
    );
    $jy = (int) $today_j[0];
    $jm = (int) $today_j[1];

    for ($offset = 0; $offset <= 1; $offset++) {
        $target_year = $jy;
        $target_month = $jm + $offset;
        if ($target_month > 12) {
            $target_month -= 12;
            $target_year++;
        }

        $target_timestamp = sc_get_fixed_invoice_target_timestamp($target_year, $target_month);
        if ($target_timestamp > $now->getTimestamp()) {
            return $target_timestamp;
        }
    }

    return 0;
}

/**
 * Store one reminder signature per enrollment without changing plugin tables.
 */
function sc_invoice_renewal_reminder_was_sent($member_course_id, $action, $signature) {
    $option_name = 'sc_renewal_reminder_' . absint($member_course_id) . '_' . sanitize_key($action);
    return (string) get_option($option_name, '') === (string) $signature;
}

function sc_mark_invoice_renewal_reminder_sent($member_course_id, $action, $signature) {
    $option_name = 'sc_renewal_reminder_' . absint($member_course_id) . '_' . sanitize_key($action);
    if (get_option($option_name, null) === null) {
        add_option($option_name, (string) $signature, '', false);
        return;
    }
    update_option($option_name, (string) $signature, false);
}

/**
 * Send the configured renewal text through SMS, Bale and the user panel.
 */
function sc_dispatch_invoice_renewal_reminder($course, $action, array $variables) {
    if (!function_exists('sc_get_sms_template') || !function_exists('sc_replace_sms_variables')) {
        return;
    }

    $template = sc_get_sms_template($action, 'user');
    if ($template === '') {
        return;
    }

    $message = sc_replace_sms_variables($template, $variables);
    $phone = isset($course->player_phone) ? (string) $course->player_phone : '';
    $clean_phone = function_exists('sc_clean_mobile_number') ? sc_clean_mobile_number($phone) : trim($phone);
    $sms_can_mirror_to_bale = $clean_phone !== ''
        && (int) sc_get_setting('sms_master_enabled', '1') === 1
        && sc_get_setting('sms_api_key', '') !== ''
        && sc_get_setting('sms_sender', '') !== ''
        && function_exists('sc_bale_mirror_sms_from_mobile');

    if ($clean_phone !== '' && function_exists('sc_send_sms')) {
        $pattern_code = function_exists('sc_get_sms_pattern') ? sc_get_sms_pattern($action, 'user') : null;
        sc_send_sms(
            $clean_phone,
            $message,
            !empty($pattern_code),
            $pattern_code,
            $variables,
            $action
        );
    }

    // sc_send_sms mirrors configured SMS messages to Bale. If SMS cannot mirror,
    // send directly so Bale remains controlled by the same renewal switch.
    if (!$sms_can_mirror_to_bale && function_exists('sc_bale_notify_user')) {
        sc_bale_notify_user((int) $course->member_id, '', $message);
    }

    if (function_exists('sc_save_notification')) {
        sc_save_notification([
            'title' => 'یادآوری تمدید دوره',
            'content' => $message,
            'target_type' => 'specific',
            'target_config' => [
                'recipient_ids' => ['member_' . (int) $course->member_id],
            ],
            'notification_type' => 'system',
            'send_sms' => 0,
            'send_bale' => 0,
        ]);
    }
}

/**
 * Send one pre-invoice renewal reminder per billing/session cycle.
 */
function sc_check_invoice_renewal_reminders() {
    $mode = sc_get_invoice_mode();
    $action = $mode === 'sessions_threshold' ? 'renewal_sessions' : 'renewal_date';
    if (!function_exists('sc_is_sms_enabled_for') || !sc_is_sms_enabled_for($action, 'user')) {
        return;
    }

    global $wpdb;
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $members_table = $wpdb->prefix . 'sc_members';
    $invoices_table = $wpdb->prefix . 'sc_invoices';

    $courses = $wpdb->get_results(
        "SELECT mc.*, c.title AS course_title, m.user_id, m.first_name, m.last_name,
                m.player_phone, m.disable_auto_invoice,
                (SELECT i.id FROM {$invoices_table} i
                 WHERE i.member_course_id = mc.id
                 ORDER BY i.created_at DESC, i.id DESC LIMIT 1) AS latest_invoice_id,
                (SELECT i.created_at FROM {$invoices_table} i
                 WHERE i.member_course_id = mc.id
                 ORDER BY i.created_at DESC, i.id DESC LIMIT 1) AS latest_invoice_created_at
         FROM {$member_courses_table} mc
         INNER JOIN {$courses_table} c ON c.id = mc.course_id
         INNER JOIN {$members_table} m ON m.id = mc.member_id
         WHERE mc.status = 'active'
         AND c.deleted_at IS NULL
         AND c.is_active = 1
         AND m.is_active = 1
         AND (
             mc.course_status_flags IS NULL
             OR mc.course_status_flags = ''
             OR (
                 mc.course_status_flags NOT LIKE '%paused%'
                 AND mc.course_status_flags NOT LIKE '%completed%'
                 AND mc.course_status_flags NOT LIKE '%canceled%'
             )
         )
         AND NOT EXISTS (
             SELECT 1 FROM {$invoices_table} pending_invoice
             WHERE pending_invoice.member_course_id = mc.id
             AND pending_invoice.status = 'pending'
         )"
    );

    if (empty($courses)) {
        return;
    }

    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(date_default_timezone_get());
    $now = (new DateTimeImmutable('now', $timezone))->getTimestamp();
    $fixed_due_timestamp = $mode === 'fixed_date' ? sc_get_next_fixed_invoice_timestamp() : 0;

    foreach ($courses as $course) {
        if ($mode !== 'sessions_threshold') {
            if (!empty($course->disable_auto_invoice)) {
                continue;
            }
            if (function_exists('sc_is_member_team') && sc_is_member_team((int) $course->member_id)) {
                continue;
            }
        }

        $user_name = trim((string) $course->first_name . ' ' . (string) $course->last_name);
        $variables = [
            'user_name' => $user_name !== '' ? $user_name : 'کاربر گرامی',
            'course_name' => (string) $course->course_title,
            'item_name' => (string) $course->course_title,
        ];

        if ($mode === 'sessions_threshold') {
            $remaining = (int) $course->remaining_sessions;
            $reminder_sessions = sc_get_invoice_renewal_reminder_sessions();
            if ($remaining !== $reminder_sessions) {
                continue;
            }
            $signature = 'sessions:' . (int) $course->latest_invoice_id . ':' . $reminder_sessions;
            $variables['remaining_sessions'] = (string) $remaining;
        } else {
            if ($mode === 'interval') {
                if (empty($course->latest_invoice_created_at)) {
                    continue;
                }
                try {
                    $last_invoice = new DateTimeImmutable((string) $course->latest_invoice_created_at, $timezone);
                } catch (Exception $exception) {
                    continue;
                }
                $due_timestamp = $last_invoice->getTimestamp() + (sc_get_invoice_interval_minutes() * MINUTE_IN_SECONDS);
            } else {
                $due_timestamp = $fixed_due_timestamp;
            }

            $reminder_days = sc_get_invoice_renewal_reminder_days();
            if ($due_timestamp <= $now || $now < ($due_timestamp - ($reminder_days * DAY_IN_SECONDS))) {
                continue;
            }
            $days_remaining = max(1, (int) ceil(($due_timestamp - $now) / DAY_IN_SECONDS));
            $renewal_date = (new DateTimeImmutable('@' . $due_timestamp))
                ->setTimezone($timezone)
                ->format('Y-m-d');
            if (function_exists('sc_date_shamsi_date_only')) {
                $renewal_date = sc_date_shamsi_date_only($renewal_date);
            }
            $signature = $mode . ':' . $due_timestamp;
            $variables['days_remaining'] = (string) $days_remaining;
            $variables['renewal_date'] = (string) $renewal_date;
        }

        if (sc_invoice_renewal_reminder_was_sent((int) $course->id, $action, $signature)) {
            continue;
        }

        sc_dispatch_invoice_renewal_reminder($course, $action, $variables);
        sc_mark_invoice_renewal_reminder_sent((int) $course->id, $action, $signature);
    }
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
/**
 * Connect correct invoice generator to cron
 * (must NOT be inside init)
 */
add_action('sc_every_minute_recurring_invoices_check', 'sc_check_invoice_renewal_reminders', 5);

if (sc_get_invoice_mode() === 'sessions_threshold') {

    add_action('sc_every_minute_recurring_invoices_check', 'sc_create_threshold_invoices');

} else {

    add_action('sc_every_minute_recurring_invoices_check', 'sc_create_recurring_invoices');

}



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

// // ساخت صورت حساب فقط در زمانی که بر حسب تعداد جلسات انتخاب شده 
function sc_create_threshold_invoices() {
    
    if (sc_get_invoice_mode() !== 'sessions_threshold') {
         return;
 }

    global $wpdb;

    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table        = $wpdb->prefix . 'sc_courses';
    $members_table        = $wpdb->prefix . 'sc_members';
    $invoices_table       = $wpdb->prefix . 'sc_invoices';

    error_log("SC Threshold Mode: Checking remaining sessions...");

    // دوره‌هایی که:
    // active هستند
    // paused/completed/canceled نیستند
    // threshold_invoiced != 1 (یعنی قبلاً فاکتور threshold نگرفته‌اند)
    // و هنوز هیچ فاکتوری برای این ثبت‌نام ندارند (مثلاً از اقدامات سریع)
    $courses = $wpdb->get_results("
        SELECT mc.*, c.price, c.title AS course_title, m.user_id , mc.remaining_sessions , m.player_phone 
        FROM $member_courses_table mc
        INNER JOIN $courses_table c ON mc.course_id = c.id
        INNER JOIN $members_table m ON mc.member_id = m.id
        WHERE mc.status = 'active'
        AND (mc.course_status_flags IS NULL 
             OR mc.course_status_flags = '' 
             OR (mc.course_status_flags NOT LIKE '%paused%' 
                 AND mc.course_status_flags NOT LIKE '%completed%' 
                 AND mc.course_status_flags NOT LIKE '%canceled%'))
        AND c.deleted_at IS NULL
        AND c.is_active = 1
        AND m.is_active = 1
        AND (mc.threshold_invoiced IS NULL OR mc.threshold_invoiced = 0)
        AND NOT EXISTS (
            SELECT 1 FROM $invoices_table i
            WHERE i.member_course_id = mc.id
               OR (i.member_id = mc.member_id AND i.course_id = mc.course_id)
        )
    ");

    if (empty($courses)) {
        error_log("SC Threshold Mode: No courses found");
        return;
    }

    $success = 0;

    foreach ($courses as $course) {

        // تابع کمکی که تعداد جلسات باقی‌مانده را از جدول جلسات برمی‌گرداند
       $remaining = intval($course->remaining_sessions);
       $sessions_count_threshold = sc_get_setting('sessions_count_threshold','1');

		if ($remaining !== $sessions_count_threshold) {
			continue;
		}

        // ایجاد صورت حساب
        $amount_for_invoice = function_exists('sc_get_course_billing_amount_for_member_course')
            ? sc_get_course_billing_amount_for_member_course((object) ['id' => $course->course_id, 'price' => $course->price, 'title' => $course->course_title], $course)
            : (float) $course->price;
        $fee_label = function_exists('sc_course_enrollment_fee_label')
            ? sc_course_enrollment_fee_label($course->course_title, !empty($course->enrollment_sessions) ? (int) $course->enrollment_sessions : null)
            : ('ثبت نام دوره: ' . $course->course_title);
        $invoice = sc_create_course_invoice(
            $course->member_id,
            $course->course_id,
            $course->id,
            $amount_for_invoice,
            'session_auto',
            $fee_label
        );
       

        if ($invoice && isset($invoice['success']) && $invoice['success']) {

            // جلوگیری از صدور دوباره
            $wpdb->update(
                $member_courses_table,
                ['threshold_invoiced' => 1],
                ['id' => $course->id],
                ['%d'],
                ['%d']
            );

            do_action('sc_invoice_created', $invoice['invoice_id']);

            $success++;
            error_log("SC Threshold Mode: Invoice created for MC {$course->id}");
        }
    }

    error_log("SC Threshold Mode: Completed - $success invoices");

    return;
}

/**
 * پس از ذخیرهٔ دوره‌های عضو توسط مدیر: اگر برای این ثبت‌نام (member_course) هنوز هیچ صورت‌حسابی ثبت نشده باشد
 * و شرایط مشابه کرون برقرار باشد، یک صورت‌حساب در انتظار پرداخت ایجاد می‌شود.
 *
 * حالت آستانهٔ جلسات: کرون فقط وقتی remaining برابر آستانه است فاکتور می‌زند؛ با فعال‌سازی دستی جلسات معمولاً کامل است.
 * حالت تاریخ ثابت: تا روز/ساعت تسویه، بدنهٔ اصلی کرون اجرا نمی‌شود.
 *
 * @param int $member_id
 * @param array<int,int|string> $course_ids شناسهٔ دوره‌های تیک‌خورده در فرم
 */
function sc_maybe_create_initial_invoices_after_member_courses_save($member_id, $course_ids) {
    if (!class_exists('WooCommerce') || !function_exists('sc_create_enrollment_invoice_for_member_course')) {
        return;
    }
    $member_id = absint($member_id);
    if ($member_id < 1 || empty($course_ids) || !is_array($course_ids)) {
        return;
    }

    global $wpdb;
    $mc_table = $wpdb->prefix . 'sc_member_courses';
    $inv_table = $wpdb->prefix . 'sc_invoices';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $members_table = $wpdb->prefix . 'sc_members';

    $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$members_table} WHERE id = %d", $member_id));
    if (!$member || (int) $member->is_active !== 1) {
        return;
    }
    if (isset($member->disable_auto_invoice) && (int) $member->disable_auto_invoice === 1) {
        return;
    }
    if (function_exists('sc_is_member_team') && sc_is_member_team($member_id) && !sc_get_setting('pro_create_invoice_player_team')) {
        return;
    }

    foreach ($course_ids as $course_id) {
        $course_id = absint($course_id);
        if ($course_id < 1) {
            continue;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT mc.*, c.price, c.title AS course_title
             FROM {$mc_table} mc
             INNER JOIN {$courses_table} c ON c.id = mc.course_id
             WHERE mc.member_id = %d AND mc.course_id = %d
             AND c.deleted_at IS NULL AND c.is_active = 1
             LIMIT 1",
            $member_id,
            $course_id
        ));
        if (!$row) {
            continue;
        }

        $fs = isset($row->course_status_flags) ? (string) $row->course_status_flags : '';
        if ($fs !== '') {
            $low = strtolower($fs);
            if (strpos($low, 'paused') !== false || strpos($low, 'completed') !== false || strpos($low, 'canceled') !== false) {
                continue;
            }
        }

        $inv_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$inv_table} WHERE member_course_id = %d",
            (int) $row->id
        ));
        if ($inv_count > 0) {
            continue;
        }

        $mode = function_exists('sc_get_invoice_mode') ? sc_get_invoice_mode() : 'interval';
        if ($mode === 'sessions_threshold') {
            if (!function_exists('sc_create_course_invoice')) {
                continue;
            }
            $amount = function_exists('sc_get_course_billing_amount_for_member_course')
                ? sc_get_course_billing_amount_for_member_course(
                    (object) ['id' => (int) $row->course_id, 'price' => $row->price, 'title' => $row->course_title],
                    $row
                )
                : (float) $row->price;
            $fee_label = function_exists('sc_course_enrollment_fee_label')
                ? sc_course_enrollment_fee_label(
                    $row->course_title,
                    !empty($row->enrollment_sessions) ? (int) $row->enrollment_sessions : null
                )
                : ('ثبت نام دوره: ' . $row->course_title);
            $res = sc_create_course_invoice(
                $member_id,
                $course_id,
                (int) $row->id,
                (float) $amount,
                'session_auto',
                $fee_label
            );
            if (is_array($res) && !empty($res['success'])) {
                $wpdb->update(
                    $mc_table,
                    ['threshold_invoiced' => 1],
                    ['id' => (int) $row->id],
                    ['%d'],
                    ['%d']
                );
                if (!empty($res['invoice_id'])) {
                    do_action('sc_invoice_created', (int) $res['invoice_id']);
                }
            }
            continue;
        }

        $res = sc_create_enrollment_invoice_for_member_course($member_id, (int) $row->id, [
            'short_sessions_mode' => 'charge_remaining',
        ]);

        if (is_array($res) && !empty($res['success']) && !empty($res['invoice_id'])) {
            do_action('sc_invoice_created', (int) $res['invoice_id']);
        }
    }
}

add_action('sc_invoice_paid', 'sc_refill_sessions_after_payment');

function sc_refill_sessions_after_payment($invoice_id) {
    global $wpdb;

    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_invoices WHERE id = %d",
        $invoice_id
    ));

    if (!$invoice) return;

    // فقط برای فاکتورهای حالت آستانه جلسات
    if ($invoice->type !== 'session_auto') return;

  

    // اطلاعات دوره مربوطه
    $member_course = $wpdb->get_row($wpdb->prepare(
        "SELECT remaining_sessions, total_sessions ,threshold_invoiced
         FROM {$wpdb->prefix}sc_member_courses 
         WHERE id = %d",
        $invoice->member_course_id
    ));

    if (!$member_course) return;

    // شارژ جلسات
    $new_remaining = (int)$member_course->remaining_sessions + (int)$member_course->total_sessions;
    $new_total     = (int)$member_course->total_sessions ;
	$threshold_invoiced = 0;

    // بروزرسانی تعداد جلسات
    $wpdb->update(
        "{$wpdb->prefix}sc_member_courses",
        [
            'total_sessions'     => $new_total,
            'remaining_sessions' => $new_remaining,
            'threshold_invoiced' => $threshold_invoiced   // ***** مهم‌ترین بخش: ریست قفل *****
        ],
        ['id' => $invoice->member_course_id],
        ['%d', '%d', '%d'],
        ['%d']
    );

    error_log("SC THRESHOLD: Sessions refilled and threshold reset for MC {$invoice->member_course_id}");
}















