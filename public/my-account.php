<?php

/**
 * WooCommerce My Account - اطلاعات بازیکن Tab
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add custom tab to WooCommerce My Account
 */
add_filter('woocommerce_account_menu_items', 'sc_add_my_account_menu_item');
function sc_add_my_account_menu_item($items) {
    // مخفی کردن تب برای مدیران
    if (current_user_can('manage_options')) {
        return $items;
    }
    
    // Insert before logout
    $logout = $items['customer-logout'];
    unset($items['customer-logout']);
    
    $items['sc-submit-documents'] = 'اطلاعات بازیکن';
    $items['sc-enroll-course'] = 'ثبت نام در دوره';
    $items['sc-my-courses'] = 'دوره‌های من';
    $items['sc-events'] = 'رویدادها / مسابقات';
    $items['sc-invoices'] = 'صورت حساب‌ها';
    $items['customer-logout'] = $logout;
    
    return $items;
}

/**
 * Register endpoint for custom tab
 */
add_action('init', 'sc_add_my_account_endpoint');
function sc_add_my_account_endpoint() {
    add_rewrite_endpoint('sc-submit-documents', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-enroll-course', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-my-courses', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-events', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-event-detail', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-invoices', EP_ROOT | EP_PAGES);
}

/**
 * Add query vars
 */
add_filter('query_vars', 'sc_add_my_account_query_vars', 0);
function sc_add_my_account_query_vars($vars) {
    $vars[] = 'sc-submit-documents';
    $vars[] = 'sc-enroll-course';
    $vars[] = 'sc-my-courses';
    $vars[] = 'sc-events';
    $vars[] = 'sc-event-detail';
    $vars[] = 'sc-invoices';
    return $vars;
}

/**
 * Set endpoint title
 */
add_filter('woocommerce_endpoint_sc-submit-documents_title', 'sc_my_account_endpoint_title');
function sc_my_account_endpoint_title($title) {
    return 'اطلاعات بازیکن';
}

add_filter('woocommerce_endpoint_sc-enroll-course_title', 'sc_enroll_course_endpoint_title');
function sc_enroll_course_endpoint_title($title) {
    return 'ثبت نام در دوره';
}

add_filter('woocommerce_endpoint_sc-my-courses_title', function() { 
    return 'دوره‌های من'; 
});

add_filter('woocommerce_endpoint_sc-events_title', function() { 
    return 'رویدادها / مسابقات'; 
});

add_filter('woocommerce_endpoint_sc-event-detail_title', function() { 
    return 'جزئیات رویداد'; 
});

add_filter('woocommerce_endpoint_sc-invoices_title', 'sc_invoices_endpoint_title');
function sc_invoices_endpoint_title($title) {
    return 'صورت حساب‌ها';
}

/**
 * نمایش پیام در بالای صفحه My Account برای کاربرانی که پروفایل ناقص دارند
 */
add_action('woocommerce_account_content', 'sc_display_incomplete_profile_message', 5);
function sc_display_incomplete_profile_message() {
    // بررسی اینکه آیا در یک endpoint خاص هستیم یا نه
    global $wp;
    if (isset($wp->query_vars['sc-submit-documents']) || 
        isset($wp->query_vars['sc-enroll-course']) || 
        isset($wp->query_vars['sc-my-courses']) ||
        isset($wp->query_vars['sc-events']) ||
        isset($wp->query_vars['sc-event-detail']) ||
        isset($wp->query_vars['sc-invoices'])) {
        return; // در صفحات خاص پیام نمایش داده نمی‌شود
    }
    
    // بررسی لاگین بودن کاربر
    if (!is_user_logged_in()) {
        return;
    }
    
    // مخفی کردن پیام برای مدیران
    if (current_user_can('manage_options')) {
        return;
    }
    
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    $current_user_id = get_current_user_id();
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    $billing_phone = get_user_meta($current_user_id, 'billing_phone', true);
    
    // بررسی وجود اطلاعات بازیکن بر اساس user_id
    $player = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    
    // اگر پیدا نشد، بر اساس شماره تماس بررسی می‌کنیم
    if (!$player && $billing_phone) {
        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE player_phone = %s LIMIT 1",
            $billing_phone
        ));
    }
    
    // بررسی تکمیل بودن پروفایل و نمایش پیام
    $should_show_message = false;
    if ($player) {
        $is_completed = sc_check_profile_completed($player->id);
        // به‌روزرسانی وضعیت در دیتابیس
        sc_update_profile_completed_status($player->id);
        
        if (!$is_completed) {
            $should_show_message = true;
        }
    } else {
        // اگر کاربر اصلاً در جدول اعضا وجود ندارد، هم پیام نمایش بده
        $should_show_message = true;
    }
    
    if ($should_show_message) {
        $profile_url = wc_get_account_endpoint_url('sc-submit-documents');
        ?>
        <div class="sc-incomplete-profile-message" style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #856404;">
            <strong style="display: block; margin-bottom: 8px;">⚠️ اطلاعات شما تکمیل نیست</strong>
            <p style="margin: 0;">
                لطفاً <a href="<?php echo esc_url($profile_url); ?>" style="color: #856404; text-decoration: underline; font-weight: bold;">اطلاعات پروفایل</a> را تکمیل کنید.
            </p>
        </div>
        <?php
    }
}

/**
 * بررسی وضعیت فعال بودن کاربر
 * این تابع وضعیت فعال بودن کاربر را بررسی می‌کند و در صورت غیرفعال بودن، پیام مناسب را نمایش می‌دهد
 * @return array|false آرایه شامل player object در صورت فعال بودن، false در غیر این صورت
 */
function sc_check_user_active_status() {
    // بررسی لاگین بودن کاربر
    if (!is_user_logged_in()) {
        return false;
    }
    
    // مخفی کردن برای مدیران
    if (current_user_can('manage_options')) {
        return false; // مدیران همیشه فعال در نظر گرفته می‌شوند
    }
    
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    $current_user_id = get_current_user_id();
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    $billing_phone = get_user_meta($current_user_id, 'billing_phone', true);
    
    // بررسی وجود اطلاعات بازیکن بر اساس user_id
    $player = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    
    // اگر پیدا نشد، بر اساس شماره تماس بررسی می‌کنیم
    if (!$player && $billing_phone) {
        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE player_phone = %s LIMIT 1",
            $billing_phone
        ));
    }
    
    // اگر کاربر در جدول اعضا وجود نداشت
    if (!$player) {
        // در این حالت، false برمی‌گردانیم تا endpoint های مربوطه پیام تکمیل اطلاعات را نمایش دهند
        return false;
    }
    
    // اگر کاربر غیرفعال بود
    if (isset($player->is_active) && $player->is_active == 0) {
        // نمایش پیام غیرفعال بودن
        ?>
        <div class="sc-inactive-user-message" style="background-color: #f8d7da; border: 1px solid #dc3545; border-radius: 4px; padding: 20px; margin: 20px 0; color: #721c24;">
            <strong style="display: block; margin-bottom: 10px; font-size: 16px;">⚠️ حساب شما غیر فعال است</strong>
            <p style="margin: 0; font-size: 14px;">
                حساب کاربری شما غیر فعال شده است. در صورتی که نیاز به فعال شدن دارید با مدیریت باشگاه ارتباط بگیرید.
            </p>
        </div>
        <?php
        return false;
    }
    
    return $player;
}

/**
 * Display content for custom tab
 */
add_action('woocommerce_account_sc-submit-documents_endpoint', 'sc_my_account_documents_content');
function sc_my_account_documents_content() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // بررسی وضعیت فعال بودن کاربر
    $player = sc_check_user_active_status();
    if ($player === false) {
        // اگر کاربر در جدول اعضا وجود نداشت یا غیرفعال بود
        // اگر غیرفعال بود، پیام در تابع sc_check_user_active_status نمایش داده شده است
        // اگر در جدول اعضا وجود نداشت، باید بررسی کنیم
        $current_user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_members';
        $billing_phone = get_user_meta($current_user_id, 'billing_phone', true);
        
        // بررسی وجود اطلاعات بازیکن بر اساس user_id
        $player_check = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d LIMIT 1",
            $current_user_id
        ));
        
        // اگر پیدا نشد، بر اساس شماره تماس بررسی می‌کنیم
        if (!$player_check && $billing_phone) {
            $player_check = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE player_phone = %s LIMIT 1",
                $billing_phone
            ));
        }
        
        // اگر کاربر در جدول اعضا وجود نداشت، اجازه می‌دهیم صفحه اطلاعات بازیکن را ببیند
        // (چون باید بتواند اطلاعاتش را تکمیل کند)
        if (!$player_check) {
            $player = null; // برای استفاده در template
        } else {
            // اگر کاربر وجود داشت اما غیرفعال بود، خروج می‌کنیم
            return;
        }
    }
    
    include SC_TEMPLATES_PUBLIC_DIR . 'submit-documents.php';
}

/**
 * Display content for course enrollment tab
 */
add_action('woocommerce_account_sc-enroll-course_endpoint', 'sc_my_account_enroll_course_content');
function sc_my_account_enroll_course_content() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // بررسی وضعیت فعال بودن کاربر
    $player = sc_check_user_active_status();
    if (!$player) {
        return; // اگر غیرفعال بود، پیام نمایش داده شده و خروج می‌کنیم
    }
    
    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    
    // دریافت تمام دوره‌های فعال
    $courses = $wpdb->get_results(
        "SELECT * FROM $courses_table 
         WHERE deleted_at IS NULL AND is_active = 1 
         ORDER BY created_at DESC"
    );
    
    if (empty($courses)) {
        echo '<div class="woocommerce-message woocommerce-message--info woocommerce-info">';
        echo 'در حال حاضر دوره‌ای برای ثبت نام موجود نیست.';
        echo '</div>';
        return;
    }
    
    // بررسی دوره‌های ثبت‌نام شده کاربر (با flags)
    // دوره‌هایی که status = 'active' دارند را می‌گیریم (حتی اگر flags داشته باشند)
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $member_courses = $wpdb->get_results($wpdb->prepare(
        "SELECT course_id, course_status_flags FROM $member_courses_table 
         WHERE member_id = %d AND status = 'active'",
        $player->id
    ));
    
    // تبدیل به آرایه برای استفاده راحت‌تر
    $enrolled_courses_data = [];
    foreach ($member_courses as $mc) {
        $flags = [];
        if (!empty($mc->course_status_flags)) {
            $flags = explode(',', $mc->course_status_flags);
            $flags = array_map('trim', $flags);
        }
        $enrolled_courses_data[$mc->course_id] = [
            'flags' => $flags,
            'is_canceled' => in_array('canceled', $flags),
            'is_completed' => in_array('completed', $flags),
            'is_paused' => in_array('paused', $flags)
        ];
    }
    
    include SC_TEMPLATES_PUBLIC_DIR . 'enroll-course.php';
}

/**
 * Handle course enrollment form submission
 */
add_action('template_redirect', 'sc_handle_course_enrollment');
function sc_handle_course_enrollment() {
    if (!is_user_logged_in() || !isset($_POST['sc_enroll_course'])) {
        return;
    }
    
    // بررسی nonce
    if (!isset($_POST['sc_enroll_course_nonce']) || !wp_verify_nonce($_POST['sc_enroll_course_nonce'], 'sc_enroll_course')) {
        wc_add_notice('خطای امنیتی. لطفاً دوباره تلاش کنید.', 'error');
        return;
    }
    
    // بررسی و ایجاد جداول
    sc_check_and_create_tables();
    
    $current_user_id = get_current_user_id();
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    
    // بررسی وجود اطلاعات بازیکن
    $player = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $members_table WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    
    if (!$player) {
        wc_add_notice('لطفاً ابتدا اطلاعات بازیکن را تکمیل کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
        exit;
    }
    
    // بررسی انتخاب دوره
    if (empty($_POST['course_id'])) {
        wc_add_notice('لطفاً یک دوره را انتخاب کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
        exit;
    }
    
    $course_id = absint($_POST['course_id']);
    
    // بررسی وجود دوره
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $courses_table WHERE id = %d AND deleted_at IS NULL AND is_active = 1",
        $course_id
    ));
    
    if (!$course) {
        wc_add_notice('دوره انتخاب شده معتبر نیست.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
        exit;
    }
    
    // بررسی ظرفیت دوره (فقط دوره‌های active را در نظر می‌گیریم)
    if ($course->capacity) {
        $enrolled_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $member_courses_table WHERE course_id = %d AND status = 'active'",
            $course_id
        ));
        
        if ($enrolled_count >= $course->capacity) {
            wc_add_notice('ظرفیت این دوره تکمیل شده است.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
            exit;
        }
    }
    
    // بررسی ثبت‌نام قبلی
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $member_courses_table WHERE member_id = %d AND course_id = %d",
        $player->id,
        $course_id
    ));
    
    $member_course_id = null;
    
    if ($existing) {
        // اگر کاربر قبلاً در این دوره ثبت‌نام کرده
        if ($existing->status === 'active') {
            wc_add_notice('شما قبلاً در این دوره ثبت‌نام کرده‌اید.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
            exit;
        } elseif (in_array($existing->status, ['canceled', 'completed', 'paused', 'inactive'])) {
            // اگر دوره قبلاً cancel، complete، paused یا inactive بود، می‌تواند دوباره ثبت‌نام کند
            // رکورد موجود را به inactive تغییر می‌دهیم (بعد از پرداخت فعال می‌شود)
            $updated = $wpdb->update(
                $member_courses_table,
                [
                    'status' => 'inactive',
                    'enrollment_date' => NULL, // بعد از پرداخت تنظیم می‌شود
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $existing->id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            
            if ($updated !== false) {
                $member_course_id = $existing->id;
            } else {
                error_log('SC Course Enrollment Update Error: ' . $wpdb->last_error);
                error_log('SC Course Enrollment Update Query: ' . $wpdb->last_query);
                wc_add_notice('خطا در ثبت‌نام. لطفاً دوباره تلاش کنید.', 'error');
                wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
                exit;
            }
        }
    } else {
        // اگر رکورد وجود ندارد، insert می‌کنیم با status = inactive (بعد از پرداخت فعال می‌شود)
        $inserted = $wpdb->insert(
            $member_courses_table,
            [
                'member_id' => $player->id,
                'course_id' => $course_id,
                'enrollment_date' => NULL, // بعد از پرداخت تنظیم می‌شود
                'status' => 'inactive',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s']
        );
        
        // اگر خطا در insert بود، لاگ کن
        if ($inserted === false) {
            error_log('SC Course Enrollment Error: ' . $wpdb->last_error);
            error_log('SC Course Enrollment Query: ' . $wpdb->last_query);
            wc_add_notice('خطا در ثبت‌نام. لطفاً دوباره تلاش کنید.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
            exit;
        }
        
        $member_course_id = $wpdb->insert_id;
    }
    
    if (isset($member_course_id) && $member_course_id) {
        // ایجاد صورت حساب و سفارش WooCommerce
        $invoice_result = sc_create_course_invoice($player->id, $course_id, $member_course_id, $course->price);
        
        if ($invoice_result && isset($invoice_result['success']) && $invoice_result['success']) {
            // ریدایرکت به تب صورت حساب‌ها
            wc_add_notice('ثبت‌نام شما با موفقیت انجام شد. لطفاً صورت حساب خود را پرداخت کنید.', 'success');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
            exit;
        } else {
            $error_message = isset($invoice_result['message']) ? $invoice_result['message'] : 'خطا در ایجاد صورت حساب';
            error_log('SC Invoice Creation Error: ' . $error_message);
            error_log('SC Invoice Result: ' . print_r($invoice_result, true));
            wc_add_notice('ثبت‌نام انجام شد اما ' . $error_message . '. لطفاً با پشتیبانی تماس بگیرید.', 'warning');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
            exit;
        }
    } else {
        error_log('SC Course Enrollment: member_course_id is not set or invalid');
        wc_add_notice('خطا در ثبت‌نام. لطفاً دوباره تلاش کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
        exit;
    }
}

/**
 * Create invoice and WooCommerce order for course enrollment
 */
function sc_create_course_invoice($member_id, $course_id, $member_course_id, $amount) {
    // بررسی فعال بودن WooCommerce
    if (!class_exists('WooCommerce')) {
        return ['success' => false, 'message' => 'WooCommerce فعال نیست.'];
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $courses_table = $wpdb->prefix . 'sc_courses';
    
    // دریافت اطلاعات دوره
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $courses_table WHERE id = %d",
        $course_id
    ));
    
    if (!$course) {
        return ['success' => false, 'message' => 'دوره یافت نشد.'];
    }
    
    // دریافت اطلاعات کاربر
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_members WHERE id = %d",
        $member_id
    ));
    
    if (!$member || !$member->user_id) {
        return ['success' => false, 'message' => 'اطلاعات کاربر یافت نشد.'];
    }
    
    $user_id = $member->user_id;
    
    // دریافت اطلاعات کاربر از WordPress
    $user = get_userdata($user_id);
    if (!$user) {
        return ['success' => false, 'message' => 'کاربر یافت نشد.'];
    }
    
    // دریافت اطلاعات billing از user meta
    $billing_first_name = get_user_meta($user_id, 'billing_first_name', true);
    $billing_last_name = get_user_meta($user_id, 'billing_last_name', true);
    $billing_email = get_user_meta($user_id, 'billing_email', true);
    $billing_phone = get_user_meta($user_id, 'billing_phone', true);
    $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
    $billing_city = get_user_meta($user_id, 'billing_city', true);
    $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
    $billing_country = get_user_meta($user_id, 'billing_country', true);
    $billing_state = get_user_meta($user_id, 'billing_state', true);
    
    // اگر اطلاعات billing وجود نداشت، از اطلاعات کاربر استفاده کن
    if (empty($billing_first_name)) {
        $billing_first_name = $member->first_name ? $member->first_name : '';
    }
    if (empty($billing_last_name)) {
        $billing_last_name = $member->last_name ? $member->last_name : '';
    }
    if (empty($billing_email)) {
        $billing_email = $user->user_email ? $user->user_email : '';
    }
    if (empty($billing_phone)) {
        $billing_phone = $member->player_phone ? $member->player_phone : '';
    }
    
    // اطمینان از اینکه حداقل اطلاعات ضروری وجود دارد
    if (empty($billing_first_name) || empty($billing_last_name) || empty($billing_email)) {
        return ['success' => false, 'message' => 'اطلاعات کاربر ناقص است. لطفاً ابتدا اطلاعات خود را تکمیل کنید.'];
    }
    
    // ایجاد سفارش WooCommerce
    $order = wc_create_order();
    
    if (is_wp_error($order)) {
        return ['success' => false, 'message' => 'خطا در ایجاد سفارش: ' . $order->get_error_message()];
    }
    
    // تنظیم customer برای سفارش - این باید قبل از تنظیم billing باشد
    $order->set_customer_id($user_id);
    
    // تنظیم اطلاعات billing - این باید حتماً پر شود
    $order->set_billing_first_name($billing_first_name);
    $order->set_billing_last_name($billing_last_name);
    $order->set_billing_email($billing_email);
    if (!empty($billing_phone)) {
        $order->set_billing_phone($billing_phone);
    }
    
    if (!empty($billing_address_1)) {
        $order->set_billing_address_1($billing_address_1);
    }
    if (!empty($billing_city)) {
        $order->set_billing_city($billing_city);
    }
    if (!empty($billing_postcode)) {
        $order->set_billing_postcode($billing_postcode);
    }
    if (!empty($billing_country)) {
        $order->set_billing_country($billing_country);
    } else {
        $order->set_billing_country('IR'); // پیش‌فرض ایران
    }
    if (!empty($billing_state)) {
        $order->set_billing_state($billing_state);
    }
    
    // تنظیم اطلاعات shipping (کپی از billing)
    $order->set_shipping_first_name($billing_first_name);
    $order->set_shipping_last_name($billing_last_name);
    // توجه: set_shipping_email و set_shipping_phone در WooCommerce وجود ندارد
    if (!empty($billing_address_1)) {
        $order->set_shipping_address_1($billing_address_1);
    }
    if (!empty($billing_city)) {
        $order->set_shipping_city($billing_city);
    }
    if (!empty($billing_postcode)) {
        $order->set_shipping_postcode($billing_postcode);
    }
    if (!empty($billing_country)) {
        $order->set_shipping_country($billing_country);
    } else {
        $order->set_shipping_country('IR'); // پیش‌فرض ایران
    }
    if (!empty($billing_state)) {
        $order->set_shipping_state($billing_state);
    }
    
    // ذخیره اولیه برای اطمینان از تنظیمات
    $order->save();
    
    // اضافه کردن Fee به سفارش با استفاده از WC_Order_Item_Fee
    $fee = new WC_Order_Item_Fee();
    $fee->set_name('هزینه دوره: ' . $course->title);
    $fee->set_amount($amount);
    $fee->set_tax_class('');
    $fee->set_tax_status('none');
    $fee->set_total($amount);
    $order->add_item($fee);
    
    // تنظیم وضعیت سفارش به pending
    $order->set_status('pending', 'سفارش ایجاد شده از طریق ثبت‌نام در دوره');
    
    // محاسبه مجدد مجموع
    $order->calculate_totals();
    
    // ذخیره سفارش
    $order_id = $order->save();
    
    if (!$order_id) {
        return ['success' => false, 'message' => 'خطا در ذخیره سفارش.'];
    }
    
    // بررسی مجدد سفارش
    $order = wc_get_order($order_id);
    if (!$order) {
        return ['success' => false, 'message' => 'خطا در دریافت سفارش.'];
    }
    
    // اطمینان از اینکه customer_id درست تنظیم شده است
    if ($order->get_customer_id() != $user_id) {
        $order->set_customer_id($user_id);
    }
    
    // اطمینان از اینکه اطلاعات billing درست است
    if (empty($order->get_billing_first_name()) || empty($order->get_billing_last_name()) || empty($order->get_billing_email())) {
        $order->set_billing_first_name($billing_first_name);
        $order->set_billing_last_name($billing_last_name);
        $order->set_billing_email($billing_email);
        if (!empty($billing_phone)) {
            $order->set_billing_phone($billing_phone);
        }
    }
    
    // ذخیره نهایی برای اطمینان از تمام تنظیمات
    $order->save();
    
    // ایجاد رکورد صورت حساب در دیتابیس
    $invoice_inserted = $wpdb->insert(
        $invoices_table,
        [
            'member_id' => $member_id,
            'course_id' => $course_id,
            'member_course_id' => $member_course_id,
            'woocommerce_order_id' => $order_id,
            'amount' => $amount,
            'penalty_amount' => 0.00,
            'penalty_applied' => 0,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ],
        ['%d', '%d', '%d', '%d', '%f', '%f', '%d', '%s', '%s', '%s']
    );
    
    if ($invoice_inserted === false) {
        // در صورت خطا، سفارش را حذف می‌کنیم
        wp_delete_post($order_id, true);
        return ['success' => false, 'message' => 'خطا در ایجاد صورت حساب.'];
    }
    
    // بررسی و اعمال جریمه در صورت نیاز
    $invoice_id = $wpdb->insert_id;
    if ($invoice_id) {
        sc_apply_penalty_to_invoice($invoice_id);
    }
    
    // دریافت لینک پرداخت
    $checkout_url = $order->get_checkout_payment_url();
    
    return [
        'success' => true,
        'order_id' => $order_id,
        'invoice_id' => $invoice_id,
        'checkout_url' => $checkout_url,
        'order' => $order
    ];
}

/**
 * Display content for my courses tab
 */
/**
 * Handle course cancellation
 */
add_action('template_redirect', 'sc_handle_course_cancellation');
function sc_handle_course_cancellation() {
    if (!is_user_logged_in() || !isset($_POST['sc_cancel_course'])) {
        return;
    }
    
    // بررسی nonce
    if (!isset($_POST['sc_cancel_course_nonce']) || !wp_verify_nonce($_POST['sc_cancel_course_nonce'], 'sc_cancel_course')) {
        wc_add_notice('خطای امنیتی. لطفاً دوباره تلاش کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-my-courses'));
        exit;
    }
    
    // بررسی و ایجاد جداول
    sc_check_and_create_tables();
    
    $current_user_id = get_current_user_id();
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    
    // بررسی وجود اطلاعات بازیکن
    $player = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $members_table WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    
    if (!$player) {
        wc_add_notice('اطلاعات بازیکن یافت نشد.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-my-courses'));
        exit;
    }
    
    // دریافت ID دوره برای لغو
    if (!isset($_POST['cancel_course_id']) || empty($_POST['cancel_course_id'])) {
        wc_add_notice('شناسه دوره معتبر نیست.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-my-courses'));
        exit;
    }
    
    $member_course_id = absint($_POST['cancel_course_id']);
    
    // بررسی اینکه دوره متعلق به کاربر فعلی است
    $member_course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $member_courses_table WHERE id = %d AND member_id = %d LIMIT 1",
        $member_course_id,
        $player->id
    ));
    
    if (!$member_course) {
        wc_add_notice('دوره یافت نشد یا شما دسترسی به این دوره ندارید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-my-courses'));
        exit;
    }
    
    // بررسی اینکه دوره قبلاً لغو نشده باشد
    $flags = [];
    if (!empty($member_course->course_status_flags)) {
        $flags = explode(',', $member_course->course_status_flags);
        $flags = array_map('trim', $flags);
    }
    
    if (in_array('canceled', $flags)) {
        wc_add_notice('این دوره قبلاً لغو شده است.', 'warning');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-my-courses'));
        exit;
    }
    
    // اضافه کردن flag "canceled"
    if (!in_array('canceled', $flags)) {
        $flags[] = 'canceled';
    }
    
    $flags_string = implode(',', $flags);
    
    // به‌روزرسانی دوره
    $updated = $wpdb->update(
        $member_courses_table,
        [
            'course_status_flags' => $flags_string,
            'updated_at' => current_time('mysql')
        ],
        ['id' => $member_course_id],
        ['%s', '%s'],
        ['%d']
    );
    
    if ($updated !== false) {
        wc_add_notice('دوره با موفقیت لغو شد.', 'success');
    } else {
        error_log('SC Course Cancellation Error: ' . $wpdb->last_error);
        wc_add_notice('خطا در لغو دوره. لطفاً دوباره تلاش کنید.', 'error');
    }
    
    wp_safe_redirect(wc_get_account_endpoint_url('sc-my-courses'));
    exit;
}

add_action('woocommerce_account_sc-my-courses_endpoint', 'sc_my_account_my_courses_content');
function sc_my_account_my_courses_content() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // بررسی وضعیت فعال بودن کاربر
    $player = sc_check_user_active_status();
    if (!$player) {
        return; // اگر غیرفعال بود، پیام نمایش داده شده و خروج می‌کنیم
    }
    
    include SC_TEMPLATES_PUBLIC_DIR . 'my-courses.php';
}

/**
 * Create WooCommerce order for an existing invoice (when created by admin)
 */
function sc_create_woocommerce_order_for_invoice($invoice_id, $member_id, $course_id, $amount, $expense_name = '') {
    // بررسی فعال بودن WooCommerce
    if (!class_exists('WooCommerce')) {
        return ['success' => false, 'message' => 'WooCommerce فعال نیست.', 'order_id' => null];
    }
    
    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    $members_table = $wpdb->prefix . 'sc_members';
    
    // دریافت اطلاعات دوره (اگر وجود داشته باشد)
    $course = null;
    if ($course_id > 0) {
        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $courses_table WHERE id = %d",
            $course_id
        ));
    }
    
    // دریافت اطلاعات کاربر
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $members_table WHERE id = %d",
        $member_id
    ));
    
    if (!$member || !$member->user_id) {
        return ['success' => false, 'message' => 'اطلاعات کاربر یافت نشد یا user_id تنظیم نشده است.', 'order_id' => null];
    }
    
    $user_id = $member->user_id;
    
    // دریافت اطلاعات کاربر از WordPress
    $user = get_userdata($user_id);
    if (!$user) {
        return ['success' => false, 'message' => 'کاربر یافت نشد.', 'order_id' => null];
    }
    
    // دریافت اطلاعات billing از user meta
    $billing_first_name = get_user_meta($user_id, 'billing_first_name', true);
    $billing_last_name = get_user_meta($user_id, 'billing_last_name', true);
    $billing_email = get_user_meta($user_id, 'billing_email', true);
    $billing_phone = get_user_meta($user_id, 'billing_phone', true);
    $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
    $billing_city = get_user_meta($user_id, 'billing_city', true);
    $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
    $billing_country = get_user_meta($user_id, 'billing_country', true);
    $billing_state = get_user_meta($user_id, 'billing_state', true);
    
    // اگر اطلاعات billing وجود نداشت، از اطلاعات کاربر استفاده کن
    if (empty($billing_first_name)) {
        $billing_first_name = $member->first_name ? $member->first_name : '';
    }
    if (empty($billing_last_name)) {
        $billing_last_name = $member->last_name ? $member->last_name : '';
    }
    if (empty($billing_email)) {
        $billing_email = $user->user_email ? $user->user_email : '';
    }
    if (empty($billing_phone)) {
        $billing_phone = $member->player_phone ? $member->player_phone : '';
    }
    
    // اطمینان از اینکه حداقل اطلاعات ضروری وجود دارد
    if (empty($billing_first_name) || empty($billing_last_name) || empty($billing_email)) {
        return ['success' => false, 'message' => 'اطلاعات کاربر ناقص است.', 'order_id' => null];
    }
    
    // پیدا کردن آخرین order ID برای اطمینان از توالی شماره سفارش
    global $wpdb;
    $last_order_id = $wpdb->get_var(
        "SELECT ID FROM {$wpdb->posts} 
         WHERE post_type = 'shop_order' 
         ORDER BY ID DESC 
         LIMIT 1"
    );
    
    // اگر order وجود نداشت، از 0 شروع کن
    if (!$last_order_id) {
        $last_order_id = 0;
    }
    
    // ایجاد سفارش WooCommerce
    $order = wc_create_order();
    
    if (is_wp_error($order)) {
        return ['success' => false, 'message' => 'خطا در ایجاد سفارش: ' . $order->get_error_message(), 'order_id' => null];
    }
    
    // اطمینان از اینکه order ID درست است (باید بیشتر از آخرین order باشد)
    $new_order_id = $order->get_id();
    if ($new_order_id <= $last_order_id) {
        // اگر order ID درست نیست، یک order جدید ایجاد کن
        wp_delete_post($new_order_id, true);
        $order = wc_create_order();
        if (is_wp_error($order)) {
            return ['success' => false, 'message' => 'خطا در ایجاد سفارش: ' . $order->get_error_message(), 'order_id' => null];
        }
    }
    
    // تنظیم customer برای سفارش
    $order->set_customer_id($user_id);
    
    // تنظیم اطلاعات billing
    $order->set_billing_first_name($billing_first_name);
    $order->set_billing_last_name($billing_last_name);
    $order->set_billing_email($billing_email);
    if (!empty($billing_phone)) {
        $order->set_billing_phone($billing_phone);
    }
    
    if (!empty($billing_address_1)) {
        $order->set_billing_address_1($billing_address_1);
    }
    if (!empty($billing_city)) {
        $order->set_billing_city($billing_city);
    }
    if (!empty($billing_postcode)) {
        $order->set_billing_postcode($billing_postcode);
    }
    if (!empty($billing_country)) {
        $order->set_billing_country($billing_country);
    } else {
        $order->set_billing_country('IR'); // پیش‌فرض ایران
    }
    if (!empty($billing_state)) {
        $order->set_billing_state($billing_state);
    }
    
    // تنظیم اطلاعات shipping (کپی از billing)
    $order->set_shipping_first_name($billing_first_name);
    $order->set_shipping_last_name($billing_last_name);
    if (!empty($billing_address_1)) {
        $order->set_shipping_address_1($billing_address_1);
    }
    if (!empty($billing_city)) {
        $order->set_shipping_city($billing_city);
    }
    if (!empty($billing_postcode)) {
        $order->set_shipping_postcode($billing_postcode);
    }
    if (!empty($billing_country)) {
        $order->set_shipping_country($billing_country);
    } else {
        $order->set_shipping_country('IR');
    }
    if (!empty($billing_state)) {
        $order->set_shipping_state($billing_state);
    }
    
    // ذخیره اولیه
    $order->save();
    
    // محاسبه مبلغ دوره/رویداد و هزینه اضافی
    $course_amount = 0;
    $expense_amount = 0;
    
    // بررسی اینکه آیا این invoice برای رویداد است یا دوره
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE id = %d",
        $invoice_id
    ));
    
    $events_table = $wpdb->prefix . 'sc_events';
    $event = null;
    if ($invoice && !empty($invoice->event_id)) {
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $events_table WHERE id = %d",
            $invoice->event_id
        ));
    }
    
    if ($course && $course->price > 0) {
        $course_amount = floatval($course->price);
        // اضافه کردن هزینه دوره به سفارش
        $fee = new WC_Order_Item_Fee();
        $fee->set_name('دوره: ' . $course->title);
        $fee->set_amount($course_amount);
        $fee->set_tax_class('');
        $fee->set_tax_status('none');
        $fee->set_total($course_amount);
        $order->add_item($fee);
    } elseif ($event && $event->price > 0) {
        $course_amount = floatval($event->price);
        // اضافه کردن هزینه رویداد به سفارش
        $fee = new WC_Order_Item_Fee();
        $fee->set_name('رویداد / مسابقه: ' . $event->name);
        $fee->set_amount($course_amount);
        $fee->set_tax_class('');
        $fee->set_tax_status('none');
        $fee->set_total($course_amount);
        $order->add_item($fee);
    }
    
    // اگر هزینه اضافی وجود دارد
    if ($amount > $course_amount) {
        $expense_amount = $amount - $course_amount;
        $fee = new WC_Order_Item_Fee();
        $fee_name = !empty($expense_name) ? $expense_name : 'هزینه اضافی';
        $fee->set_name($fee_name);
        $fee->set_amount($expense_amount);
        $fee->set_tax_class('');
        $fee->set_tax_status('none');
        $fee->set_total($expense_amount);
        $order->add_item($fee);
    }
    
    // تنظیم وضعیت سفارش به pending
    $order->set_status('pending', 'سفارش ایجاد شده از طریق پنل مدیریت');
    
    // محاسبه مجدد مجموع
    $order->calculate_totals();
    
    // ذخیره سفارش
    $order_id = $order->save();
    
    if (!$order_id) {
        return ['success' => false, 'message' => 'خطا در ذخیره سفارش.', 'order_id' => null];
    }
    
    return ['success' => true, 'order_id' => $order_id, 'message' => 'سفارش با موفقیت ایجاد شد.'];
}

/**
 * Create invoice and WooCommerce order for event enrollment
 */
if (!function_exists('sc_create_event_invoice')) {
function sc_create_event_invoice($member_id, $event_id, $amount) {
    // بررسی فعال بودن WooCommerce
    if (!class_exists('WooCommerce')) {
        return ['success' => false, 'message' => 'WooCommerce فعال نیست.'];
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $events_table = $wpdb->prefix . 'sc_events';
    
    // دریافت اطلاعات رویداد
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $events_table WHERE id = %d",
        $event_id
    ));
    
    if (!$event) {
        return ['success' => false, 'message' => 'رویداد یافت نشد.'];
    }
    
    // ایجاد صورت حساب
    $invoice_data = [
        'member_id' => $member_id,
        'event_id' => $event_id,
        'course_id' => NULL,
        'amount' => $amount,
        'status' => 'pending',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    $inserted = $wpdb->insert(
        $invoices_table,
        $invoice_data,
        ['%d', '%d', '%s', '%f', '%s', '%s', '%s']
    );
    
    if ($inserted === false) {
        return ['success' => false, 'message' => 'خطا در ایجاد صورت حساب.'];
    }
    
    $invoice_id = $wpdb->insert_id;
    
    // ایجاد سفارش WooCommerce
    $order_result = sc_create_woocommerce_order_for_invoice($invoice_id, $member_id, 0, $amount, $event->name);
    
    if ($order_result && isset($order_result['order_id']) && $order_result['order_id']) {
        $order_id = $order_result['order_id'];
        
        // بروزرسانی invoice با order_id
        $wpdb->update(
            $invoices_table,
            ['woocommerce_order_id' => $order_id, 'updated_at' => current_time('mysql')],
            ['id' => $invoice_id],
            ['%d', '%s'],
            ['%d']
        );
        
        // دریافت لینک پرداخت
        $order = wc_get_order($order_id);
        $payment_url = $order ? $order->get_checkout_payment_url() : '';
        
        return [
            'success' => true,
            'invoice_id' => $invoice_id,
            'order_id' => $order_id,
            'payment_url' => $payment_url
        ];
    }
    
    return [
        'success' => true,
        'invoice_id' => $invoice_id,
        'order_id' => NULL,
        'payment_url' => ''
    ];
}
}

/**
 * Display content for invoices tab
 */
add_action('woocommerce_account_sc-invoices_endpoint', 'sc_my_account_invoices_content');
function sc_my_account_invoices_content() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // بررسی وضعیت فعال بودن کاربر
    $player = sc_check_user_active_status();
    if (!$player) {
        return; // اگر غیرفعال بود، پیام نمایش داده شده و خروج می‌کنیم
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $courses_table = $wpdb->prefix . 'sc_courses';
    
    // دریافت تمام صورت حساب‌های کاربر
    // توجه: بررسی جریمه در hook sc_check_penalty_on_invoices_page انجام می‌شود
    $events_table = $wpdb->prefix . 'sc_events';
    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT i.*, c.title as course_title, c.price as course_price, e.name as event_name
         FROM $invoices_table i
         LEFT JOIN $courses_table c ON i.course_id = c.id AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')
         LEFT JOIN $events_table e ON i.event_id = e.id AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
         WHERE i.member_id = %d
         ORDER BY i.created_at DESC",
        $player->id
    ));
    
    include SC_TEMPLATES_PUBLIC_DIR . 'invoices-list.php';
}

/**
 * Display content for events list tab
 */
add_action('woocommerce_account_sc-events_endpoint', 'sc_my_account_events_content');
function sc_my_account_events_content() {
    // بررسی و ایجاد جداول
    sc_check_and_create_tables();
    
    // بررسی وضعیت فعال بودن کاربر
    $player = sc_check_user_active_status();
    if (!$player) {
        return;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'sc_events';
    
    // دریافت تمام رویدادهای فعال
    $events = $wpdb->get_results(
        "SELECT * FROM $events_table 
         WHERE deleted_at IS NULL AND is_active = 1 
         ORDER BY created_at DESC"
    );
    
    include SC_TEMPLATES_PUBLIC_DIR . 'events-list.php';
}

/**
 * Display content for event detail tab
 */
add_action('woocommerce_account_sc-event-detail_endpoint', 'sc_my_account_event_detail_content');
function sc_my_account_event_detail_content() {
    // بررسی و ایجاد جداول
    sc_check_and_create_tables();
    
    // بررسی وضعیت فعال بودن کاربر
    $player = sc_check_user_active_status();
    if (!$player) {
        return;
    }
    
    global $wp;
    $event_id = isset($wp->query_vars['sc-event-detail']) ? absint($wp->query_vars['sc-event-detail']) : 0;
    
    if (!$event_id) {
        wc_add_notice('رویداد یافت نشد.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-events'));
        exit;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'sc_events';
    
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $events_table WHERE id = %d AND deleted_at IS NULL AND is_active = 1",
        $event_id
    ));
    
    if (!$event) {
        wc_add_notice('رویداد یافت نشد یا غیرفعال است.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-events'));
        exit;
    }
    
    include SC_TEMPLATES_PUBLIC_DIR . 'event-detail.php';
}

/**
 * Handle event enrollment form submission
 */
add_action('template_redirect', 'sc_handle_event_enrollment');
function sc_handle_event_enrollment() {
    if (!is_user_logged_in() || !isset($_POST['sc_enroll_event'])) {
        return;
    }
    
    // بررسی nonce
    if (!isset($_POST['sc_enroll_event_nonce']) || !wp_verify_nonce($_POST['sc_enroll_event_nonce'], 'sc_enroll_event')) {
        wc_add_notice('خطای امنیتی. لطفاً دوباره تلاش کنید.', 'error');
        return;
    }
    
    // بررسی و ایجاد جداول
    sc_check_and_create_tables();
    
    $player = sc_check_user_active_status();
    if (!$player) {
        wc_add_notice('حساب کاربری شما غیرفعال است.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-events'));
        exit;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'sc_events';
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    
    // بررسی انتخاب رویداد
    if (empty($_POST['event_id'])) {
        wc_add_notice('لطفاً یک رویداد را انتخاب کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-events'));
        exit;
    }
    
    $event_id = absint($_POST['event_id']);
    
    // بررسی وجود رویداد
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $events_table WHERE id = %d AND deleted_at IS NULL AND is_active = 1",
        $event_id
    ));
    
    if (!$event) {
        wc_add_notice('رویداد انتخاب شده معتبر نیست.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-events'));
        exit;
    }
    
    // بررسی محدودیت تاریخ
    $today_shamsi = sc_get_today_shamsi();
    $is_date_expired = false;
    
    if (!empty($event->start_date_gregorian) || !empty($event->end_date_gregorian)) {
        $start_date_shamsi = !empty($event->start_date_gregorian) ? sc_date_shamsi_date_only($event->start_date_gregorian) : '';
        $end_date_shamsi = !empty($event->end_date_gregorian) ? sc_date_shamsi_date_only($event->end_date_gregorian) : '';
        
        if (!empty($end_date_shamsi)) {
            if (sc_compare_shamsi_dates($today_shamsi, $end_date_shamsi) > 0) {
                $is_date_expired = true;
            }
        }
        
        if (!empty($start_date_shamsi) && !$is_date_expired) {
            if (sc_compare_shamsi_dates($today_shamsi, $start_date_shamsi) < 0) {
                $is_date_expired = true;
            }
        }
    }
    
    if ($is_date_expired) {
        wc_add_notice('زمان ثبت نام این رویداد تمام شده است.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail', $event_id));
        exit;
    }
    
    // بررسی شرط سنی
    if ($event->has_age_limit && !empty($player->birth_date_shamsi)) {
        $user_age = sc_calculate_age($player->birth_date_shamsi);
        $age_number = (int)str_replace(' سال', '', $user_age);
        
        if ($event->min_age && $age_number < $event->min_age) {
            wc_add_notice('شما سن لازم برای شرکت در این رویداد را ندارید. حداقل سن: ' . $event->min_age . ' سال', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail', $event_id));
            exit;
        }
        if ($event->max_age && $age_number > $event->max_age) {
            wc_add_notice('شما سن لازم برای شرکت در این رویداد را ندارید. حداکثر سن: ' . $event->max_age . ' سال', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail', $event_id));
            exit;
        }
    } elseif ($event->has_age_limit && empty($player->birth_date_shamsi)) {
        wc_add_notice('لطفاً ابتدا تاریخ تولد خود را در بخش اطلاعات بازیکن تکمیل کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail', $event_id));
        exit;
    }
    
    // بررسی ظرفیت
    if ($event->capacity) {
        $enrolled_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $invoices_table WHERE event_id = %d AND status IN ('paid', 'completed', 'processing')",
            $event_id
        ));
        $remaining = $event->capacity - $enrolled_count;
        
        if ($remaining <= 0) {
            wc_add_notice('ظرفیت این رویداد تکمیل شده است.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail', $event_id));
            exit;
        }
    }
    
    // بررسی ثبت‌نام قبلی
    $existing_invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE member_id = %d AND event_id = %d AND status IN ('paid', 'completed', 'processing')",
        $player->id,
        $event_id
    ));
    
    if ($existing_invoice) {
        wc_add_notice('شما قبلاً در این رویداد ثبت نام کرده‌اید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail', $event_id));
        exit;
    }
    
    // ایجاد صورت حساب و سفارش WooCommerce
    $invoice_result = sc_create_event_invoice($player->id, $event_id, $event->price);
    
    if ($invoice_result['success']) {
        wc_add_notice('ثبت‌نام با موفقیت انجام شد. لطفاً صورت حساب را پرداخت کنید.', 'success');
        wp_safe_redirect($invoice_result['payment_url']);
        exit;
    } else {
        wc_add_notice('خطا در ثبت‌نام. لطفاً دوباره تلاش کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail', $event_id));
        exit;
    }
}

/**
 * Hook برای به‌روزرسانی وضعیت صورت حساب پس از پرداخت سفارش WooCommerce
 */
add_action('woocommerce_order_status_changed', 'sc_update_invoice_status_on_payment', 10, 4);
function sc_update_invoice_status_on_payment($order_id, $old_status, $new_status, $order) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    
    // بررسی اینکه آیا این سفارش مربوط به یک صورت حساب است
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE woocommerce_order_id = %d",
        $order_id
    ));
    
    if ($invoice) {
        // به‌روزرسانی وضعیت صورت حساب بر اساس وضعیت سفارش WooCommerce
        $invoice_status = $new_status; // استفاده مستقیم از وضعیت WooCommerce
        $payment_date = NULL;
        
        if (in_array($new_status, ['processing', 'completed'])) {
            $payment_date = current_time('mysql');
            
            // فعال کردن دوره بعد از پرداخت موفق
            if ($invoice->member_course_id) {
                $member_courses_table = $wpdb->prefix . 'sc_member_courses';
                $wpdb->update(
                    $member_courses_table,
                    [
                        'status' => 'active',
                        'enrollment_date' => current_time('Y-m-d'),
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $invoice->member_course_id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );
            }
        }
        
        $wpdb->update(
            $invoices_table,
            [
                'status' => $invoice_status,
                'payment_date' => $payment_date,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $invoice->id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }
}


// حذف auto-submit - کاربر باید خودش اطلاعات را وارد کند و پرداخت کند

// حذف auto-submit - کاربر باید خودش اطلاعات را وارد کند و پرداخت کند
// لینک پرداخت به صفحه checkout ووکامرس می‌رود و کاربر می‌تواند اطلاعات را وارد کند

/**
 * Handle form submission
 */
add_action('template_redirect', 'sc_handle_documents_submission');
function sc_handle_documents_submission() {
    if (!is_user_logged_in() || !isset($_POST['sc_submit_documents'])) {
        return;
    }
    
    // بررسی nonce
    if (!isset($_POST['sc_documents_nonce']) || !wp_verify_nonce($_POST['sc_documents_nonce'], 'sc_submit_documents')) {
        wp_die('خطای امنیتی. لطفاً دوباره تلاش کنید.');
    }
    
    // بررسی و ایجاد جداول
    sc_check_and_create_tables();
    
    $current_user_id = get_current_user_id();
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    
    // Validation
    if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['national_id'])) {
        wc_add_notice('لطفاً فیلدهای اجباری را پر کنید.', 'error');
        return;
    }
    
    // آماده‌سازی داده‌ها
    $data = [
        'user_id'              => $current_user_id,
        'first_name'           => sanitize_text_field($_POST['first_name']),
        'last_name'            => sanitize_text_field($_POST['last_name']),
        'national_id'          => sanitize_text_field($_POST['national_id']),
        'health_verified'      => 0,
        'info_verified'        => 0,
        'created_at'           => current_time('mysql'),
        'updated_at'           => current_time('mysql'),
    ];
    
    // فیلدهای اختیاری - همیشه به‌روزرسانی می‌شوند (حتی اگر خالی باشند)
    // برای فیلدهای متنی: اگر خالی باشند، NULL ذخیره می‌شود
    $data['father_name'] = isset($_POST['father_name']) && !empty(trim($_POST['father_name'])) ? sanitize_text_field($_POST['father_name']) : NULL;
    $data['player_phone'] = isset($_POST['player_phone']) && !empty(trim($_POST['player_phone'])) ? sanitize_text_field($_POST['player_phone']) : NULL;
    $data['father_phone'] = isset($_POST['father_phone']) && !empty(trim($_POST['father_phone'])) ? sanitize_text_field($_POST['father_phone']) : NULL;
    $data['mother_phone'] = isset($_POST['mother_phone']) && !empty(trim($_POST['mother_phone'])) ? sanitize_text_field($_POST['mother_phone']) : NULL;
    $data['landline_phone'] = isset($_POST['landline_phone']) && !empty(trim($_POST['landline_phone'])) ? sanitize_text_field($_POST['landline_phone']) : NULL;
    $data['birth_date_shamsi'] = isset($_POST['birth_date_shamsi']) && !empty(trim($_POST['birth_date_shamsi'])) ? sanitize_text_field($_POST['birth_date_shamsi']) : NULL;
    $data['birth_date_gregorian'] = isset($_POST['birth_date_gregorian']) && !empty(trim($_POST['birth_date_gregorian'])) ? sanitize_text_field($_POST['birth_date_gregorian']) : NULL;
    
    // پردازش تاریخ انقضا بیمه شمسی و تبدیل به میلادی
    $insurance_expiry_date_shamsi = isset($_POST['insurance_expiry_date_shamsi']) && !empty(trim($_POST['insurance_expiry_date_shamsi'])) ? sanitize_text_field($_POST['insurance_expiry_date_shamsi']) : NULL;
    $data['insurance_expiry_date_shamsi'] = $insurance_expiry_date_shamsi;
    
    // تبدیل تاریخ انقضا بیمه شمسی به میلادی
    $insurance_expiry_date_gregorian = NULL;
    if ($insurance_expiry_date_shamsi) {
        // اگر از hidden field ارسال شده باشد، استفاده کن
        if (isset($_POST['insurance_expiry_date_gregorian']) && !empty(trim($_POST['insurance_expiry_date_gregorian']))) {
            $insurance_expiry_date_gregorian = sanitize_text_field($_POST['insurance_expiry_date_gregorian']);
        } else {
            // در غیر این صورت، تبدیل کن
            $insurance_expiry_date_gregorian = sc_shamsi_to_gregorian_date($insurance_expiry_date_shamsi);
        }
    }
    $data['insurance_expiry_date_gregorian'] = $insurance_expiry_date_gregorian;
    $data['medical_condition'] = isset($_POST['medical_condition']) && !empty(trim($_POST['medical_condition'])) ? sanitize_textarea_field($_POST['medical_condition']) : NULL;
    $data['sports_history'] = isset($_POST['sports_history']) && !empty(trim($_POST['sports_history'])) ? sanitize_textarea_field($_POST['sports_history']) : NULL;
    $data['additional_info'] = isset($_POST['additional_info']) && !empty(trim($_POST['additional_info'])) ? sanitize_textarea_field($_POST['additional_info']) : NULL;
    
    // برای checkbox ها: اگر تیک نخورده باشد، 0 ذخیره می‌شود
    $data['health_verified'] = isset($_POST['health_verified']) && !empty($_POST['health_verified']) ? 1 : 0;
    $data['info_verified'] = isset($_POST['info_verified']) && !empty($_POST['info_verified']) ? 1 : 0;
   
    // بررسی وجود اطلاعات قبلی
    // اول بر اساس user_id بررسی می‌کنیم
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    
    // اگر با user_id پیدا نشد، بر اساس national_id بررسی می‌کنیم
    if (!$existing) {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE national_id = %s LIMIT 1",
            $data['national_id']
        ));
        
        // اگر با national_id پیدا شد، بررسی می‌کنیم که user_id نداشته باشد
        if ($existing && $existing->user_id && $existing->user_id != $current_user_id) {
            // این national_id به کاربر دیگری اختصاص داده شده است
            wc_add_notice('این کد ملی قبلاً به حساب کاربری دیگری اختصاص داده شده است. لطفاً با پشتیبانی تماس بگیرید.', 'error');
            return;
        }
    }
    
    // پردازش آپلود عکس‌ها با امنیت
    $uploaded_files = sc_handle_secure_file_upload($current_user_id);
    if ($uploaded_files) {
        if (isset($uploaded_files['personal_photo'])) {
            $data['personal_photo'] = $uploaded_files['personal_photo'];
        }
        if (isset($uploaded_files['id_card_photo'])) {
            $data['id_card_photo'] = $uploaded_files['id_card_photo'];
        }
        if (isset($uploaded_files['sport_insurance_photo'])) {
            $data['sport_insurance_photo'] = $uploaded_files['sport_insurance_photo'];
        }
    }
    // اگر فایلی آپلود نشده و در حالت update هستیم، فیلدهای عکس را در update_data اضافه نمی‌کنیم
    // تا عکس‌های قبلی حفظ شوند
    
    if ($existing) {
        // بررسی اینکه آیا user_id در رکورد دیگری استفاده شده است
        $user_id_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND id != %d LIMIT 1",
        $current_user_id,
            $existing->id
    ));
    
        if ($user_id_exists) {
            // user_id در رکورد دیگری استفاده شده است
            wc_add_notice('این حساب کاربری قبلاً به بازیکن دیگری اختصاص داده شده است. لطفاً با پشتیبانی تماس بگیرید.', 'error');
            return;
        }
        
        // بروزرسانی - تمام فیلدها (حتی اگر خالی باشند)
        $update_data = $data;
        // حذف created_at از update
        unset($update_data['created_at']);
        $update_data['updated_at'] = current_time('mysql');
        
        // اگر user_id وجود نداشت یا با user_id فعلی متفاوت است، به‌روزرسانی می‌کنیم
        if (!$existing->user_id || $existing->user_id != $current_user_id) {
            $update_data['user_id'] = $current_user_id;
        } else {
            // اگر user_id قبلاً درست تنظیم شده، از update_data حذف می‌کنیم تا تغییر نکند
            unset($update_data['user_id']);
        }
        
        // آماده‌سازی format برای update
        $format = [];
        foreach ($update_data as $key => $value) {
            if ($value === NULL) {
                $format[] = '%s'; // NULL
            } elseif (in_array($key, ['health_verified', 'info_verified', 'is_active', 'user_id'])) {
                $format[] = '%d'; // integer
            } else {
                $format[] = '%s'; // string
            }
        }
        
        $updated = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $existing->id],
            $format,
            ['%d']
        );
        
        if ($updated !== false) {
            // به‌روزرسانی وضعیت تکمیل پروفایل
            sc_update_profile_completed_status($existing->id);
            
            wc_add_notice('اطلاعات شما با موفقیت به روز شد.', 'success');
            // ریدایرکت برای جلوگیری از ارسال مجدد فرم
            wp_safe_redirect(wc_get_account_endpoint_url('sc-submit-documents'));
            exit;
        } else {
            if ($wpdb->last_error) {
                error_log('WP Update Error: ' . $wpdb->last_error);
            }
            wc_add_notice('خطا در بروزرسانی اطلاعات. لطفاً دوباره تلاش کنید.', 'error');
        }
    } else {
        // بررسی تکراری بودن کد ملی
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE national_id = %s",
            $data['national_id']
        ));
        
        if ($duplicate) {
            wc_add_notice('این کد ملی قبلاً ثبت شده است. لطفاً با پشتیبانی تماس بگیرید.', 'error');
            return;
        }
        
        // افزودن جدید
        $inserted = $wpdb->insert($table_name, $data);
        
        if ($inserted !== false) {
            $insert_id = $wpdb->insert_id;
            
            // به‌روزرسانی وضعیت تکمیل پروفایل
            sc_update_profile_completed_status($insert_id);
            
            wc_add_notice('اطلاعات شما با موفقیت ثبت شد.', 'success');
            // ریدایرکت برای جلوگیری از ارسال مجدد فرم
            wp_safe_redirect(wc_get_account_endpoint_url('sc-submit-documents'));
            exit;
        } else {
            if ($wpdb->last_error) {
                error_log('WP Insert Error: ' . $wpdb->last_error);
                error_log('WP Last Query: ' . $wpdb->last_query);
            }
            wc_add_notice('خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.', 'error');
        }
    }
}

/**
 * Handle secure file upload
 */
function sc_handle_secure_file_upload($user_id) {
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    $uploaded_files = [];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    
    $file_fields = [
        'personal_photo' => 'عکس پرسنلی',
        'id_card_photo' => 'عکس کارت ملی',
        'sport_insurance_photo' => 'عکس بیمه ورزشی'
    ];
    
    foreach ($file_fields as $field_name => $field_label) {
        if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        $file = $_FILES[$field_name];
        
        // بررسی نوع فایل
        $mime_type = $file['type'];
        
        if (!in_array($mime_type, $allowed_types)) {
            wc_add_notice("نوع فایل $field_label معتبر نیست. فقط تصاویر (JPG, PNG, GIF, WEBP) مجاز است.", 'error');
            continue;
        }
        
        // بررسی اندازه فایل
        if ($file['size'] > $max_file_size) {
            wc_add_notice("حجم فایل $field_label بیش از 5 مگابایت است.", 'error');
            continue;
        }
        
        // بررسی محتوای فایل (امنیت)
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info === false) {
            wc_add_notice("فایل $field_label یک تصویر معتبر نیست.", 'error');
            continue;
        }
        
        // تنظیمات آپلود
        $upload_overrides = [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp'
            ],
            'unique_filename_callback' => function($dir, $name, $ext) use ($user_id, $field_name) {
                // ایجاد نام فایل امن
                $safe_name = sanitize_file_name($user_id . '_' . $field_name . '_' . time() . $ext);
                return $safe_name;
            }
        ];
        
        // آپلود فایل
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            $uploaded_files[$field_name] = $movefile['url'];
        } else {
            wc_add_notice("خطا در آپلود $field_label: " . (isset($movefile['error']) ? $movefile['error'] : 'خطای ناشناخته'), 'error');
        }
    }
    
    return $uploaded_files;
}

/**
 * Hook برای بررسی و اعمال جریمه هنگام مشاهده صفحه پرداخت
 */
add_action('woocommerce_before_checkout_process', 'sc_check_penalty_on_checkout');
add_action('template_redirect', 'sc_check_penalty_on_payment_page');
function sc_check_penalty_on_payment_page() {
    if (!is_checkout()) {
        return;
    }
    
    global $wp;
    if (!isset($wp->query_vars['order-pay'])) {
        return;
    }
    
    $order_id = absint($wp->query_vars['order-pay']);
    if (!$order_id) {
        return;
    }
    
    // بررسی اینکه آیا این سفارش مربوط به یک صورت حساب است
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE woocommerce_order_id = %d",
        $order_id
    ));
    
    if ($invoice && $invoice->status === 'pending') {
        // بررسی و اعمال جریمه
        sc_apply_penalty_to_invoice($invoice->id);
    }
}

/**
 * Hook برای بررسی و اعمال جریمه هنگام مشاهده صفحه صورت حساب‌ها
 */
add_action('woocommerce_account_sc-invoices_endpoint', 'sc_check_penalty_on_invoices_page', 5);
function sc_check_penalty_on_invoices_page() {
    // بررسی و اعمال جریمه برای تمام صورت حساب‌های pending
    sc_check_and_apply_penalties();
}

function sc_check_penalty_on_checkout() {
    // این hook برای checkout معمولی است
    // برای order-pay از sc_check_penalty_on_payment_page استفاده می‌شود
}

/**
 * Hook برای بررسی و اعمال جریمه به صورت دوره‌ای
 */
add_action('wp', 'sc_scheduled_penalty_check');
function sc_scheduled_penalty_check() {
    // فقط یک بار در روز بررسی می‌شود
    $last_check = get_transient('sc_last_penalty_check');
    if ($last_check) {
        return;
    }
    
    sc_check_and_apply_penalties();
    
    // ذخیره زمان آخرین بررسی (24 ساعت)
    set_transient('sc_last_penalty_check', current_time('timestamp'), DAY_IN_SECONDS);
}
