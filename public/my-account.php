<?php
// start dashbord - info user summery

add_action( 'woocommerce_before_account_navigation', 'add_html_before_account_nav' );
function add_html_before_account_nav() {

     if (!is_user_logged_in()) {
        return '<div class="sc-user-info-notice">لطفاً ابتدا وارد حساب کاربری خود شوید.</div>';
    }
    
    // بررسی و ایجاد جداول
    sc_check_and_create_tables();
    
    $current_user_id = get_current_user_id();
    global $wpdb;
    
    // دریافت اطلاعات کاربر WordPress
    $wp_user = wp_get_current_user();
    $user_display_name = $wp_user->display_name;
    $user_email = $wp_user->user_email;
    $user_login = $wp_user->user_login;
    $billing_phone = get_user_meta($current_user_id, 'billing_phone', true);
    
    // دریافت اطلاعات بازیکن از جدول members
    $members_table = $wpdb->prefix . 'sc_members';
    $player = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $members_table WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    
    // اگر پیدا نشد، بر اساس شماره تماس بررسی می‌کنیم
    if (!$player && $billing_phone) {
        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $members_table WHERE player_phone = %s LIMIT 1",
            $billing_phone
        ));
    }
    
    // اگر بازیکن پیدا نشد
    if (!$player) {
        return '<div class="sc-user-info-notice">اطلاعات بازیکن یافت نشد. لطفاً پروفایل خود را تکمیل کنید.</div>';
    }
    
    // دریافت عکس پروفایل
    $profile_image = '';
    if (!empty($player->personal_photo)) {
        $profile_image = esc_url($player->personal_photo);
    } else {
        // استفاده از WordPress avatar
        $profile_image = get_avatar_url($current_user_id, ['size' => 150]);
    }
    
    // دریافت نام و شماره تماس
    $full_name = trim($player->first_name . ' ' . $player->last_name);
    if (empty($full_name)) {
        $full_name = $user_display_name;
    }
    $phone = !empty($player->player_phone) ? $player->player_phone : $billing_phone;
    
    // محاسبه تعداد دوره‌های فعال (فقط دوره‌های فعال و بدون flag)
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $active_courses_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) 
         FROM $member_courses_table mc
         INNER JOIN $courses_table c ON mc.course_id = c.id
         WHERE mc.member_id = %d 
         AND mc.status = 'active'
         AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '')
         AND c.deleted_at IS NULL
         AND c.is_active = 1",
        $player->id
    ));
    
    // محاسبه بدهکاری (صورت حساب‌های pending و under_review)
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $debt_info = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as count,
            SUM(amount + COALESCE(penalty_amount, 0)) as total_debt
         FROM $invoices_table
         WHERE member_id = %d 
         AND status IN ('pending', 'under_review')",
        $player->id
    ));
    $debt_count = $debt_info->count ?? 0;
    $total_debt = floatval($debt_info->total_debt ?? 0);
    
    // تعداد رویدادهای ثبت‌نام شده
    $events_table = $wpdb->prefix . 'sc_events';
    $event_registrations_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) 
         FROM $invoices_table i
         INNER JOIN $events_table e ON i.event_id = e.id
         WHERE i.member_id = %d 
         AND i.status IN ('paid', 'completed', 'processing')
         AND e.deleted_at IS NULL",
        $player->id
    ));
    
    // آخرین صورت حساب پرداخت شده (با نام دوره یا رویداد)
    $courses_table = $wpdb->prefix . 'sc_courses';
    $events_table = $wpdb->prefix . 'sc_events';
    $last_invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            i.id, 
            i.amount, 
            i.payment_date, 
            i.created_at,
            i.course_id,
            i.event_id,
            c.title as course_title,
            e.name as event_name
         FROM $invoices_table i
         LEFT JOIN $courses_table c ON i.course_id = c.id AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')
         LEFT JOIN $events_table e ON i.event_id = e.id AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
         WHERE i.member_id = %d 
         AND i.status IN ('paid', 'completed', 'processing')
         AND i.payment_date IS NOT NULL
         ORDER BY i.payment_date DESC, i.created_at DESC
         LIMIT 1",
        $player->id
    ));
    
    // تعداد و مجموع صورت حساب‌های پرداخت شده
    $paid_invoices_info = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as count,
            SUM(amount) as total_amount
         FROM $invoices_table
         WHERE member_id = %d 
         AND status IN ('paid', 'completed', 'processing')",
        $player->id
    ));
    $paid_invoices_count = $paid_invoices_info->count ?? 0;
    $paid_invoices_total = floatval($paid_invoices_info->total_amount ?? 0);
    
    // محاسبه سن کاربر - استفاده از همان تابع لیست اعضا
    $user_age = '';
    if (!empty($player->birth_date_shamsi)) {
        $user_age = sc_calculate_age($player->birth_date_shamsi);
    } elseif (!empty($player->birth_date_gregorian)) {
        // اگر فقط تاریخ میلادی موجود باشد، ابتدا به شمسی تبدیل می‌کنیم
        $birth_date = new DateTime($player->birth_date_gregorian);
        $birth_year = (int)$birth_date->format('Y');
        $birth_month = (int)$birth_date->format('m');
        $birth_day = (int)$birth_date->format('d');
        
        if (function_exists('gregorian_to_jalali')) {
            $birth_jalali = gregorian_to_jalali($birth_year, $birth_month, $birth_day);
            if ($birth_jalali && count($birth_jalali) === 3) {
                $birth_shamsi = $birth_jalali[0] . '/' . 
                               str_pad($birth_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                               str_pad($birth_jalali[2], 2, '0', STR_PAD_LEFT);
                $user_age = sc_calculate_age($birth_shamsi);
            }
        }
    }
    
    // تعداد حضور و غیاب (جداگانه)
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $attendances_info = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
            COUNT(*) as total_count
         FROM $attendances_table
         WHERE member_id = %d",
        $player->id
    ));
    $present_count = intval($attendances_info->present_count ?? 0);
    $absent_count = intval($attendances_info->absent_count ?? 0);
    $total_attendances = intval($attendances_info->total_count ?? 0);
    
    // سطح کاربر
    $skill_level = !empty($player->skill_level) ? $player->skill_level : 'تعیین نشده';
    
    // تاریخ عضویت
    $membership_date = '';
    if (!empty($player->created_at)) {
        $membership_date = sc_date_shamsi_date_only($player->created_at);
    }
    
    // وضعیت بیمه
    $insurance_status = '';
    $insurance_expiry = '';
    if (!empty($player->insurance_expiry_date_shamsi)) {
        $insurance_expiry = $player->insurance_expiry_date_shamsi;
        $today_shamsi = sc_get_today_shamsi();
        $expiry_compare = sc_compare_shamsi_dates($today_shamsi, $insurance_expiry);
        if ($expiry_compare > 0) {
            $insurance_status = 'منقضی شده';
        } else {
            $insurance_status = 'معتبر';
        }
    } else {
        $insurance_status = 'ثبت نشده';
    }
    
    // وضعیت پروفایل
    $profile_completed = sc_check_profile_completed($player->id);
    $profile_status = $profile_completed ? 'تکمیل شده' : 'ناقص';
    $profile_status_class = $profile_completed ? 'completed' : 'incomplete';
    
  
    ?>
    <div class="sc-user-info-panel">
        <div class="sc-user-info-wrapper">

            <div class="sc-user-profile">
                <div class="sc-user-avatar">
                    <img src="<?php echo esc_url($profile_image); ?>" alt="<?php echo esc_attr($full_name); ?>">
                  
                </div>
                <h3 class="sc-user-name"><?php echo esc_html($full_name); ?></h3>
                  <p class="info_level" ><?php echo $skill_level ; ?></p>

            </div>

            <div class="sc-user-stats">
                <h4 class="sc-section-title">اطلاعات حساب کاربری</h4>

                <div class="sc-info-grid">

                    <div class="sc-info-card sc-card-blue">
                        <span class="sc-card-icon">📚</span>
                        <span class="sc-card-title">دوره‌های فعال</span>
                        <strong class="sc-card-value"><?php echo $active_courses_count; ?></strong>
                    </div>

                    <div class="sc-info-card sc-card-yellow">
                        <span class="sc-card-icon">💰</span>
                        <span class="sc-card-title">بدهکاری</span>
                        <strong class="sc-card-value">
                            <?php echo number_format($debt_info->total_debt ?? 0); ?> تومان
                        </strong>
                        <?php if ($debt_info->count): ?>
                            <small>(<?php echo $debt_info->count; ?> فاکتور)</small>
                        <?php endif; ?>
                    </div>

                 
                <div class="sc-info-card sc-card-purple">
                    <span class="sc-card-icon">🎂</span>
                    <span class="sc-card-title">سن شما</span>
                    <strong class="sc-card-value">
                        <?php echo $user_age?? ''; ?>
                    </strong>
                </div>

                <div class="sc-info-card sc-card-green">
                    <span class="sc-card-icon">📋</span>
                    <span class="sc-card-title">حضور و غیاب</span>

                    <div class="sc-attendance-row">
                        <div class="sc-attendance-item present">
                            <strong><?php echo esc_html($present_count); ?></strong>
                            <small>حضور</small>
                        </div>
                                |
                        <div class="sc-attendance-item absent">
                            <strong><?php echo esc_html($absent_count); ?></strong>
                            <small>غیاب</small>
                        </div>
                    </div>
                </div>

                </div>
            </div>

        </div>
    </div>
    <?php

}




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
    add_rewrite_endpoint('sc-event-success', EP_ROOT | EP_PAGES);

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

    /** @var stdClass|null $player */
    $player = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    if ( empty($player) ) {
    return;
}

    
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
            <strong style="display: block; margin-bottom: 8px;">⚠️ اطلاعات پروفایل شما کامل نیست</strong>
            <p >برای تکمیل پروفایل خود
             <a href="<?php echo esc_url($profile_url); ?>"> اینجا کلیک کنید. </a>
                
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
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    
    // دریافت فیلتر وضعیت - پیش‌فرض: آخرین دوره‌ها (دوره‌های فعال که کاربر می‌تواند ثبت نام کند)
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'latest';
    
    // ساخت شرط WHERE
    $where_conditions = ["c.deleted_at IS NULL", "c.is_active = 1"];
    $where_values = [];
    
    // بررسی دوره‌های ثبت‌نام شده کاربر (با flags) - شامل active و inactive (pending invoice)
    /** @var stdClass|null $player */
    $member_courses = $wpdb->get_results($wpdb->prepare(
        "SELECT course_id, course_status_flags, status FROM $member_courses_table 
         WHERE member_id = %d AND status IN ('active', 'inactive')",
        $player->id
    ));
    
    // بررسی دوره‌هایی که صورت حساب pending یا under_review دارند
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $pending_invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT course_id, status FROM $invoices_table 
         WHERE member_id = %d AND course_id IS NOT NULL AND status IN ('pending', 'under_review')",
        $player->id
    ));
    
    $pending_course_ids = [];
    $under_review_course_ids = [];
    foreach ($pending_invoices as $invoice) {
        if ($invoice->course_id) {
            $pending_course_ids[] = $invoice->course_id;
            if ($invoice->status === 'under_review') {
                $under_review_course_ids[] = $invoice->course_id;
            }
        }
    }
    
    // تبدیل به آرایه برای استفاده راحت‌تر
    $enrolled_courses_data = [];
    foreach ($member_courses as $mc) {
        $flags = [];
        if (!empty($mc->course_status_flags)) {
            $flags = explode(',', $mc->course_status_flags);
            $flags = array_map('trim', $flags);
        }
        
        // بررسی اینکه آیا invoice pending یا under_review دارد یا نه
        $has_pending_invoice = in_array($mc->course_id, $pending_course_ids);
        $is_under_review = in_array($mc->course_id, $under_review_course_ids);
        
        // اگر status = 'inactive' است و invoice pending یا under_review ندارد، این دوره را نادیده بگیر (اجازه ثبت نام دوباره)
        if ($mc->status === 'inactive' && !$has_pending_invoice) {
            continue; // این دوره را در enrolled_courses_data قرار نده
        }
        
        $enrolled_courses_data[$mc->course_id] = [
            'flags' => $flags,
            'is_canceled' => in_array('canceled', $flags),
            'is_completed' => in_array('completed', $flags),
            'is_paused' => in_array('paused', $flags),
            'is_pending_payment' => ($mc->status === 'inactive' && $has_pending_invoice && !$is_under_review), // فقط اگر status = inactive باشد و invoice pending داشته باشد و under_review نباشد
            'is_under_review' => ($mc->status === 'inactive' && $is_under_review) // در انتظار بررسی
        ];
    }
    
    // اضافه کردن دوره‌هایی که صورت حساب pending یا under_review دارند اما در member_courses نیستند
    foreach ($pending_course_ids as $course_id) {
        if (!isset($enrolled_courses_data[$course_id])) {
            $is_under_review = in_array($course_id, $under_review_course_ids);
            $enrolled_courses_data[$course_id] = [
                'flags' => [],
                'is_canceled' => false,
                'is_completed' => false,
                'is_paused' => false,
                'is_pending_payment' => !$is_under_review, // فقط اگر under_review نباشد
                'is_under_review' => $is_under_review
            ];
        }
    }
    
    $enrolled_course_ids = array_keys($enrolled_courses_data);
    
    // اضافه کردن دوره‌هایی که صورت حساب pending دارند به لیست دوره‌های ثبت‌نام شده
    $all_enrolled_course_ids = array_unique(array_merge($enrolled_course_ids, $pending_course_ids));
    
    // فیلتر بر اساس وضعیت
    if ($filter_status === 'latest') {
        // آخرین دوره‌ها: دوره‌های فعال که کاربر در آن‌ها ثبت نام نکرده (می‌تواند ثبت نام کند)
        // شامل دوره‌هایی که صورت حساب pending دارند نمی‌شود
        if (!empty($all_enrolled_course_ids)) {
            $placeholders = implode(',', array_fill(0, count($all_enrolled_course_ids), '%d'));
            $where_conditions[] = "c.id NOT IN ($placeholders)";
            $where_values = $all_enrolled_course_ids;
        }
        // اگر کاربر در هیچ دوره‌ای ثبت نام نکرده، همه دوره‌های فعال نمایش داده می‌شوند
    } elseif ($filter_status === 'active') {
        // دوره‌های ثبت نام شده و فعال (بدون flag)
        $active_course_ids = [];
        foreach ($enrolled_courses_data as $course_id => $data) {
            if (empty($data['flags']) || (empty($data['is_canceled']) && empty($data['is_completed']) && empty($data['is_paused']))) {
                $active_course_ids[] = $course_id;
            }
        }
        if (!empty($active_course_ids)) {
            $placeholders = implode(',', array_fill(0, count($active_course_ids), '%d'));
            $where_conditions[] = "c.id IN ($placeholders)";
            $where_values = $active_course_ids;
        } else {
            $where_conditions[] = "1 = 0";
        }
    } elseif ($filter_status === 'paused') {
        // دوره‌های متوقف شده
        $paused_course_ids = [];
        foreach ($enrolled_courses_data as $course_id => $data) {
            if ($data['is_paused']) {
                $paused_course_ids[] = $course_id;
            }
        }
        if (!empty($paused_course_ids)) {
            $placeholders = implode(',', array_fill(0, count($paused_course_ids), '%d'));
            $where_conditions[] = "c.id IN ($placeholders)";
            $where_values = $paused_course_ids;
        } else {
            $where_conditions[] = "1 = 0";
        }
    } elseif ($filter_status === 'completed') {
        // دوره‌های به اتمام رسیده
        $completed_course_ids = [];
        foreach ($enrolled_courses_data as $course_id => $data) {
            if ($data['is_completed']) {
                $completed_course_ids[] = $course_id;
            }
        }
        if (!empty($completed_course_ids)) {
            $placeholders = implode(',', array_fill(0, count($completed_course_ids), '%d'));
            $where_conditions[] = "c.id IN ($placeholders)";
            $where_values = $completed_course_ids;
        } else {
            $where_conditions[] = "1 = 0";
        }
    } elseif ($filter_status === 'canceled') {
        // دوره‌های لغو شده
        $canceled_course_ids = [];
        foreach ($enrolled_courses_data as $course_id => $data) {
            if ($data['is_canceled']) {
                $canceled_course_ids[] = $course_id;
            }
        }
        if (!empty($canceled_course_ids)) {
            $placeholders = implode(',', array_fill(0, count($canceled_course_ids), '%d'));
            $where_conditions[] = "c.id IN ($placeholders)";
            $where_values = $canceled_course_ids;
        } else {
            $where_conditions[] = "1 = 0";
        }
    } elseif ($filter_status === 'expired') {
        // دوره‌هایی که مهلت ثبت نام آن‌ها تمام شده یا گذشته
        $today_shamsi = sc_get_today_shamsi();
        $today_gregorian = sc_shamsi_to_gregorian_date($today_shamsi);
        
        // دوره‌هایی که تاریخ پایان آن‌ها گذشته است
        $where_conditions[] = "c.end_date IS NOT NULL AND c.end_date < %s";
        $where_values[] = $today_gregorian;
    } elseif ($filter_status === 'all') {
        // همه دوره‌ها (بدون فیلتر اضافی)
        // فقط شرط‌های پایه (deleted_at IS NULL و is_active = 1) اعمال می‌شود
        // هیچ شرط اضافی اضافه نمی‌کنیم
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // محاسبه تعداد کل
    $count_query = "SELECT COUNT(*) FROM $courses_table c WHERE $where_clause";
    if (!empty($where_values)) {
        $total_courses = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
    } else {
        $total_courses = $wpdb->get_var($count_query);
    }
    
    // صفحه‌بندی
    $per_page = 10;
    $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $offset = ($current_page - 1) * $per_page;
    $total_pages = ceil($total_courses / $per_page);
    
    // دریافت دوره‌های کاربر با صفحه‌بندی
    // ترتیب: بر اساس تاریخ ایجاد (جدیدترین اول)
    $query = "SELECT c.*
              FROM $courses_table c
              WHERE $where_clause
              ORDER BY c.created_at DESC
              LIMIT %d OFFSET %d";
    
    $query_values = array_merge($where_values, [$per_page, $offset]);
    $courses = $wpdb->get_results($wpdb->prepare($query, $query_values));
    
    // انتقال متغیرهای فیلتر و صفحه‌بندی به template
    // $filter_status = $filter_status;
    // $current_page = $current_page;
    // $total_pages = $total_pages;
    // $total_courses = $total_courses;
    
    // همیشه template را include کن تا فیلتر نمایش داده شود
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
    
    // بررسی اینکه آیا صورت حساب pending برای این دوره وجود دارد
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $pending_invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table 
         WHERE member_id = %d AND course_id = %d AND status IN ('pending', 'under_review')",
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
        } elseif ($existing->status === 'inactive' && $pending_invoice) {
            // اگر status = 'inactive' و صورت حساب pending دارد، نمی‌تواند دوباره ثبت‌نام کند
            wc_add_notice('شما قبلاً در این دوره ثبت‌نام کرده‌اید و صورت حساب شما در حال پرداخت است. لطفاً ابتدا صورت حساب را پرداخت یا لغو کنید.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
            exit;
        } elseif (in_array($existing->status, ['canceled', 'completed', 'paused', 'inactive'])) {
            // اگر دوره قبلاً cancel، complete، paused یا inactive بود (بدون pending invoice)، می‌تواند دوباره ثبت‌نام کند
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
        // بررسی اینکه آیا صورت حساب pending وجود دارد (حتی اگر member_course وجود نداشته باشد)
        if ($pending_invoice) {
            wc_add_notice('شما قبلاً در این دوره ثبت‌نام کرده‌اید و صورت حساب شما در حال پرداخت است. لطفاً ابتدا صورت حساب را پرداخت یا لغو کنید.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
            exit;
        }
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

        // ارسال SMS صورت حساب
        do_action('sc_invoice_created', $invoice_id);
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
    
    global $wpdb;
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    
    // دریافت فیلتر وضعیت - پیش‌فرض: فقط دوره‌های فعال و بدون flag
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'active';
    /** @var stdClass|null $player */
    // ساخت شرط WHERE
    $where_conditions = ["mc.member_id = %d"];
    $where_values = [$player->id];
    
    // فیلتر بر اساس وضعیت
    // مهم: فقط دوره‌هایی که کاربر در آن‌ها ثبت‌نام کرده (رکورد در member_courses دارد) نمایش داده می‌شوند
    if ($filter_status === 'active') {
        // فقط دوره‌های فعال (بدون flag) - شامل دوره‌های در حال پرداخت (inactive) هم می‌شود
        $where_conditions[] = "mc.status IN ('active', 'inactive')";
        $where_conditions[] = "(mc.course_status_flags IS NULL OR mc.course_status_flags = '' OR mc.course_status_flags = ' ')";
        $where_conditions[] = "c.deleted_at IS NULL";
    } elseif ($filter_status === 'canceled') {
        // فقط دوره‌های لغو شده - باید فلگ 'canceled' داشته باشند
        $where_conditions[] = "mc.course_status_flags LIKE %s";
        $where_values[] = '%canceled%';
    } elseif ($filter_status === 'paused') {
        // فقط دوره‌های متوقف شده - باید فلگ 'paused' داشته باشند
        $where_conditions[] = "mc.course_status_flags LIKE %s";
        $where_values[] = '%paused%';
    } elseif ($filter_status === 'completed') {
        // فقط دوره‌های تمام شده - باید فلگ 'completed' داشته باشند
        $where_conditions[] = "mc.course_status_flags LIKE %s";
        $where_values[] = '%completed%';
    }
    // اگر 'all' باشد، همه دوره‌هایی که کاربر در آن‌ها ثبت‌نام کرده نمایش داده می‌شوند
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // محاسبه تعداد کل
    $count_query = "SELECT COUNT(*) 
                    FROM $member_courses_table mc
                    INNER JOIN $courses_table c ON mc.course_id = c.id
                    WHERE $where_clause";
    $total_courses = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
    
    // صفحه‌بندی
    $per_page = 10;
    $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    $offset = ($current_page - 1) * $per_page;
    $total_pages = ceil($total_courses / $per_page);
    
    // دریافت دوره‌های کاربر با صفحه‌بندی
    // ترتیب: اول دوره‌های فعال و بدون flag، سپس بقیه
    $order_by = "CASE 
                    WHEN (mc.status = 'active' AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '') AND c.is_active = 1 AND c.deleted_at IS NULL) THEN 0 
                    ELSE 1 
                 END ASC, 
                 mc.created_at DESC";
    
    $query = "SELECT mc.*, c.title as course_title, c.is_active as course_is_active, c.deleted_at as course_deleted_at
              FROM $member_courses_table mc
              INNER JOIN $courses_table c ON mc.course_id = c.id
              WHERE $where_clause
              ORDER BY $order_by
              LIMIT %d OFFSET %d";
    
    $query_values = array_merge($where_values, [$per_page, $offset]);
    $user_courses = $wpdb->get_results($wpdb->prepare($query, $query_values));
    
    // دریافت invoice‌های pending برای دوره‌ها
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $pending_invoices = [];
    if (!empty($user_courses)) {
        $course_ids = array_map(function($course) {
            return $course->course_id;
        }, $user_courses);
        
        if (!empty($course_ids)) {
            $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
            $pending_invoices_query = $wpdb->prepare(
                "SELECT course_id, status FROM $invoices_table 
                 WHERE member_id = %d AND course_id IN ($placeholders) AND status IN ('pending', 'under_review')",
                array_merge([$player->id], $course_ids)
            );
            $pending_invoice_results = $wpdb->get_results($pending_invoices_query);
            
            $under_review_invoices = [];
            foreach ($pending_invoice_results as $invoice) {
                if ($invoice->course_id) {
                    $pending_invoices[$invoice->course_id] = true;
                    if ($invoice->status === 'under_review') {
                        $under_review_invoices[$invoice->course_id] = true;
                    }
                }
            }
        }
    }
    
    // // انتقال متغیرهای فیلتر و صفحه‌بندی به template
    // $filter_status = $filter_status;
    // $current_page = $current_page;
    // $total_pages = $total_pages;
    // $total_courses = $total_courses;
    // $pending_invoices = isset($pending_invoices) ? $pending_invoices : [];
    // $under_review_invoices = isset($under_review_invoices) ? $under_review_invoices : [];
    // $player = $player; // پاس دادن player به template
    
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
    
    if (!$member) {
        return ['success' => false, 'message' => 'اطلاعات کاربر یافت نشد.', 'order_id' => null];
    }
    
    // پیدا کردن آخرین order ID برای اطمینان از توالی (قبل از ایجاد order)
    global $wpdb;
    
    // پیدا کردن آخرین order ID از تمام order ها (حتی حذف شده)
    // این مهم است چون AUTO_INCREMENT باید از آخرین ID استفاده کند
    $last_order_id = $wpdb->get_var(
        "SELECT ID FROM {$wpdb->posts} 
         WHERE post_type = 'shop_order' 
         ORDER BY ID DESC 
         LIMIT 1"
    );
    
    // اگر order وجود داشت، مطمئن شویم که AUTO_INCREMENT درست تنظیم شده است
    if ($last_order_id) {
        // پیدا کردن AUTO_INCREMENT فعلی
        $table_name = $wpdb->posts;
        $auto_increment = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AUTO_INCREMENT 
                 FROM INFORMATION_SCHEMA.TABLES 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = %s",
                $table_name
            )
        );
        
        // اگر AUTO_INCREMENT کمتر یا مساوی آخرین order ID بود، آن را تنظیم کن
        if ($auto_increment && $auto_increment <= $last_order_id) {
            $next_id = $last_order_id + 1;
            $wpdb->query(
                "ALTER TABLE {$table_name} AUTO_INCREMENT = {$next_id}"
            );
        }
    }
    
    // ایجاد سفارش WooCommerce (قبل از تنظیم customer)
    // این مهم است چون AUTO_INCREMENT باید قبل از ایجاد order تنظیم شود
    $order = wc_create_order();
    
    if (is_wp_error($order)) {
        return ['success' => false, 'message' => 'خطا در ایجاد سفارش: ' . $order->get_error_message(), 'order_id' => null];
    }
    
    // اگر user_id وجود دارد، از آن استفاده کن
    $user_id = null;
    $user = null;
    
    if (!empty($member->user_id)) {
        $user_id = $member->user_id;
        $user = get_userdata($user_id);
        
        if ($user) {
            // تنظیم customer برای سفارش
            $order->set_customer_id($user_id);
            
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
        } else {
            // اگر user پیدا نشد، از اطلاعات member استفاده کن
            $billing_first_name = $member->first_name ? $member->first_name : '';
            $billing_last_name = $member->last_name ? $member->last_name : '';
            $billing_email = '';
            $billing_phone = $member->player_phone ? $member->player_phone : '';
            $billing_address_1 = '';
            $billing_city = '';
            $billing_postcode = '';
            $billing_country = 'IR';
            $billing_state = '';
        }
    } else {
        // اگر user_id وجود نداشت، از اطلاعات member استفاده کن
        $billing_first_name = $member->first_name ? $member->first_name : '';
        $billing_last_name = $member->last_name ? $member->last_name : '';
        $billing_email = '';
        $billing_phone = $member->player_phone ? $member->player_phone : '';
        $billing_address_1 = '';
        $billing_city = '';
        $billing_postcode = '';
        $billing_country = 'IR';
        $billing_state = '';
    }
    
    // اطمینان از اینکه حداقل اطلاعات ضروری وجود دارد
    if (empty($billing_first_name) || empty($billing_last_name)) {
        return ['success' => false, 'message' => 'اطلاعات کاربر ناقص است (نام و نام خانوادگی الزامی است).', 'order_id' => null];
    }
    
    // اگر email وجود نداشت، یک email موقت ایجاد کن
    if (empty($billing_email)) {
        $billing_email = 'member_' . $member_id . '@sportclub.local';
    }
    
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
    // توجه: در اینجا نیازی به بررسی deleted_at و is_active نیست چون قبلاً در sc_handle_event_enrollment بررسی شده است
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $events_table WHERE id = %d",
        $event_id
    ));
    
    if (!$event) {
        error_log('SC Event Invoice: Event not found - event_id: ' . $event_id);
        return ['success' => false, 'message' => 'رویداد یافت نشد. (Event ID: ' . $event_id . ')'];
    }
    
    // اگر amount صفر یا خالی است، از قیمت رویداد استفاده کن
    if (empty($amount) || $amount == 0) {
        $amount = floatval($event->price);
    }
    
    // ایجاد صورت حساب
    $invoice_data = [
        'member_id' => $member_id,
        'event_id' => $event_id,
        'course_id' => 0, // برای رویداد، course_id باید 0 باشد نه NULL
        'member_course_id' => NULL,
        'woocommerce_order_id' => NULL,
        'amount' => $amount,
        'expense_name' => NULL,
        'penalty_amount' => 0.00,
        'penalty_applied' => 0,
        'status' => 'pending',
        'payment_date' => NULL,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    // آماده‌سازی format array برای insert
    // ترتیب: member_id, event_id, course_id, member_course_id, woocommerce_order_id, amount, expense_name, penalty_amount, penalty_applied, status, payment_date, created_at, updated_at
    $format_array = ['%d', '%d', '%d', '%s', '%s', '%f', '%s', '%f', '%d', '%s', '%s', '%s', '%s'];
    
    // تنظیم format برای فیلدهای NULL (index از 0 شروع می‌شود)
    // member_course_id (index 3) = NULL
    // woocommerce_order_id (index 4) = NULL  
    // expense_name (index 6) = NULL
    // payment_date (index 10) = NULL
    
    $inserted = $wpdb->insert(
        $invoices_table,
        $invoice_data,
        $format_array
    );
    
    if ($inserted === false) {
        error_log('SC Event Invoice: Insert failed - ' . $wpdb->last_error);
        error_log('SC Event Invoice: Insert query - ' . $wpdb->last_query);
        error_log('SC Event Invoice: Insert data - ' . print_r($invoice_data, true));
        error_log('SC Event Invoice: Insert format - ' . print_r($format_array, true));
        return ['success' => false, 'message' => 'خطا در ایجاد صورت حساب: ' . $wpdb->last_error];
    }
    
    $invoice_id = $wpdb->insert_id;

    // Trigger SMS hook for event invoices
    do_action('sc_invoice_created', $invoice_id);

    // ایجاد سفارش WooCommerce
    // توجه: course_id را 0 می‌فرستیم چون این یک رویداد است، نه دوره
    $order_result = sc_create_woocommerce_order_for_invoice($invoice_id, $member_id, 0, $amount, $event->name);
    
    if ($order_result && isset($order_result['success']) && $order_result['success'] && !empty($order_result['order_id'])) {
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
            'payment_url' => $payment_url,
            'message' => 'صورت حساب با موفقیت ایجاد شد.'
        ];
    } else {
        // اگر order ایجاد نشد، خطا را برمی‌گردانیم
        $error_message = isset($order_result['message']) ? $order_result['message'] : 'خطا در ایجاد سفارش WooCommerce';
        error_log('SC Event Order Creation Error: ' . $error_message);
        error_log('SC Event Order Result: ' . print_r($order_result, true));
        
        return [
            'success' => false,
            'invoice_id' => $invoice_id,
            'order_id' => NULL,
            'payment_url' => '',
            'message' => $error_message
        ];
    }
}
}

/**
 * Handle invoice cancellation request
 */
add_action('template_redirect', 'sc_handle_invoice_cancellation');
function sc_handle_invoice_cancellation() {
    // بررسی درخواست لغو
    if (!isset($_GET['cancel_invoice']) || !isset($_GET['invoice_id']) || !isset($_GET['_wpnonce'])) {
        return;
    }
    
    // بررسی اینکه آیا در صفحه invoices هستیم
    if (!is_account_page()) {
        return;
    }
    
    // بررسی nonce
    if (!wp_verify_nonce($_GET['_wpnonce'], 'cancel_invoice_' . $_GET['invoice_id'])) {
        wc_add_notice('درخواست نامعتبر است.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
        exit;
    }
    
    // بررسی لاگین بودن کاربر
    if (!is_user_logged_in()) {
        wc_add_notice('لطفاً ابتدا وارد حساب کاربری خود شوید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
        exit;
    }
    
    // بررسی و ایجاد جداول
    sc_check_and_create_tables();
    
    // بررسی وضعیت فعال بودن کاربر
    $player = sc_check_user_active_status();
    if (!$player) {
        wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
        exit;
    }
    
    $invoice_id = absint($_GET['invoice_id']);
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
        /** @var stdClass|null $player */

    // بررسی اینکه صورت حساب متعلق به کاربر فعلی است
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE id = %d AND member_id = %d",
        $invoice_id,
        $player->id
    ));
    
    if (!$invoice) {
        wc_add_notice('صورت حساب یافت نشد.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
        exit;
    }
    
    // بررسی اینکه فقط سفارش‌های با وضعیت pending یا under_review قابل لغو هستند
    if (!in_array($invoice->status, ['pending', 'under_review'])) {
        wc_add_notice('فقط سفارش‌های در انتظار پرداخت یا در حال بررسی قابل لغو هستند.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
        exit;
    }
    
    // به‌روزرسانی وضعیت به cancelled
    $updated = $wpdb->update(
        $invoices_table,
        [
            'status' => 'cancelled',
            'updated_at' => current_time('mysql')
        ],
        ['id' => $invoice_id],
        ['%s', '%s'],
        ['%d']
    );
    if ($updated !== false) {
        // اگر سفارش WooCommerce وجود دارد، آن را هم لغو کن
        if (!empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($invoice->woocommerce_order_id);
            if ($order && in_array($order->get_status(), ['pending', 'on-hold'])) {
                $order->update_status('cancelled', 'لغو شده توسط کاربر');
            }
        }
        
        // اگر این صورت حساب برای یک دوره است، member_course را حذف کن
        // مهم: لغو invoice هیچ ارتباطی به فلگ 'canceled' دوره ندارد
        // فلگ 'canceled' فقط زمانی تنظیم می‌شود که کاربر یا مدیر دوره را لغو کند
        if (!empty($invoice->course_id)) {
            
            $member_courses_table = $wpdb->prefix . 'sc_member_courses';
            /** @var stdClass|null $player */
            // اگر member_course_id وجود دارد، از آن استفاده کن
            if (!empty($invoice->member_course_id)) {
                // حذف رکورد member_course تا کاربر بتواند دوباره ثبت‌نام کند
                $wpdb->delete(
                    $member_courses_table,
                    ['id' => $invoice->member_course_id],
                    ['%d']
                );
            } else {
                // اگر member_course_id وجود ندارد، از course_id و member_id استفاده کن
                // فقط رکوردهایی که status = 'inactive' دارند را حذف کن (چون این‌ها مربوط به invoice pending هستند)
                $wpdb->delete(
                    $member_courses_table,
                    [
                        'member_id' => $player->id,
                        'course_id' => $invoice->course_id,
                        'status' => 'inactive'
                    ],
                    ['%d', '%d', '%s']
                );
            }
        }
        
        // اگر این صورت حساب برای یک رویداد است، event_registration را حذف کن تا امکان ثبت نام دوباره فراهم شود
        if (!empty($invoice->event_id)) {
            $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';
            // حذف رکورد event_registration مربوط به این invoice
            $wpdb->delete(
                $event_registrations_table,
                ['invoice_id' => $invoice_id],
                ['%d']
            );
        }
        
        wc_add_notice('سفارش با موفقیت لغو شد.', 'success');
    } else {
        wc_add_notice('خطا در لغو سفارش. لطفاً دوباره تلاش کنید.', 'error');
    }
    
    // حفظ فیلتر در redirect
    $redirect_url = wc_get_account_endpoint_url('sc-invoices');
    if (isset($_GET['filter_status']) && $_GET['filter_status'] !== 'all') {
        $redirect_url = add_query_arg('filter_status', sanitize_text_field($_GET['filter_status']), $redirect_url);
    }
    
    wp_safe_redirect($redirect_url);
    exit;
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
    $events_table = $wpdb->prefix . 'sc_events';
    
    // دریافت فیلتر وضعیت
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
    /** @var stdClass|null $player */
    // ساخت شرط WHERE
    $where_conditions = ["i.member_id = %d"];
    $where_values = [$player->id];
    
    // فیلتر بر اساس وضعیت
    if ($filter_status !== 'all') {
        $where_conditions[] = "i.status = %s";
        $where_values[] = $filter_status;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // ساخت ORDER BY - pending ها اول، سپس بر اساس تاریخ
    $order_by = "ORDER BY 
        CASE 
            WHEN i.status = 'pending' THEN 1
            WHEN i.status = 'under_review' THEN 2
            ELSE 3
        END,
        i.created_at DESC";
    
    // دریافت تمام صورت حساب‌های کاربر
    $query = "SELECT i.*, c.title as course_title, c.price as course_price, e.name as event_name
              FROM $invoices_table i
              LEFT JOIN $courses_table c ON i.course_id = c.id AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')
              LEFT JOIN $events_table e ON i.event_id = e.id AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
              WHERE $where_clause
              $order_by";
    
    $invoices = $wpdb->get_results($wpdb->prepare($query, $where_values));
    
    // انتقال متغیر فیلتر به template
   // $filter_status = $filter_status;
    
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
    
    // دریافت تاریخ امروز
    $today_shamsi = sc_get_today_shamsi();
    $today_gregorian = date('Y-m-d');
    
    // دریافت فیلترها
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'latest';
    $filter_event_type = isset($_GET['filter_event_type']) ? sanitize_text_field($_GET['filter_event_type']) : 'all';
    
    // ساخت WHERE clause
    $where_conditions = [
        "deleted_at IS NULL",
        "is_active = 1"
    ];
    $where_values = [];
    
    // فیلتر نوع (رویداد/مسابقه)
    if ($filter_event_type !== 'all') {
        $where_conditions[] = "event_type = %s";
        $where_values[] = $filter_event_type;
    }
    
    // فیلتر وضعیت
    if ($filter_status === 'past') {
        // رویداد/مسابقه برگزار شده - تاریخ برگزاری گذشته
        $where_conditions[] = "holding_date_gregorian IS NOT NULL AND holding_date_gregorian < %s";
        $where_values[] = $today_gregorian;
    } elseif ($filter_status === 'upcoming') {
        // به زودی - در آینده و در بازه ثبت‌نام نیست
        $where_conditions[] = "(
            (start_date_gregorian IS NOT NULL AND start_date_gregorian > %s)
            OR (end_date_gregorian IS NOT NULL AND end_date_gregorian < %s)
        )";
        $where_values[] = $today_gregorian;
        $where_values[] = $today_gregorian;
    } elseif ($filter_status === 'all') {
        // همه - بدون محدودیت تاریخ
        // هیچ شرط اضافی اضافه نمی‌کنیم
    } else {
        // پیش‌فرض: آخرین - در بازه ثبت‌نام
        $where_conditions[] = "(
            (start_date_gregorian IS NULL OR start_date_gregorian <= %s)
            AND (end_date_gregorian IS NULL OR end_date_gregorian >= %s)
        )";
        $where_values[] = $today_gregorian;
        $where_values[] = $today_gregorian;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت رویدادها
    $query = "SELECT * FROM $events_table 
              WHERE $where_clause
              ORDER BY holding_date_gregorian DESC, created_at DESC";
    
    if (!empty($where_values)) {
        $events = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $events = $wpdb->get_results($query);
    }
    
    // انتقال متغیرهای فیلتر به template
    // $filter_status = $filter_status;
    // $filter_event_type = $filter_event_type;
    
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
        wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail'));
        exit;
    }
    /** @var stdClass|null $player */
    // بررسی شرط سنی
    if ($event->has_age_limit && !empty($player->birth_date_shamsi)) {
        $user_age = sc_calculate_age($player->birth_date_shamsi);
        $age_number = (int)str_replace(' سال', '', $user_age);
        
        if ($event->min_age && $age_number < $event->min_age) {
            wc_add_notice('شما سن لازم برای شرکت در این رویداد را ندارید. حداقل سن: ' . $event->min_age . ' سال', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail'));
            exit;
        }
        if ($event->max_age && $age_number > $event->max_age) {
            wc_add_notice('شما سن لازم برای شرکت در این رویداد را ندارید. حداکثر سن: ' . $event->max_age . ' سال', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail'));
            exit;
        }
    } elseif ($event->has_age_limit && empty($player->birth_date_shamsi)) {
        wc_add_notice('لطفاً ابتدا تاریخ تولد خود را در بخش اطلاعات بازیکن تکمیل کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail'));
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
            wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail'));
            exit;
        }
    }
    
    // بررسی ثبت‌نام قبلی - بررسی invoice های pending یا under_review
    $existing_pending_invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT id, status FROM $invoices_table WHERE member_id = %d AND event_id = %d AND status IN ('pending', 'under_review') ORDER BY created_at DESC LIMIT 1",
        $player->id,
        $event_id
    ));
    
    if ($existing_pending_invoice) {
        if ($existing_pending_invoice->status === 'pending') {
            wc_add_notice('شما قبلاً برای این رویداد ثبت‌نام کرده‌اید و صورت حساب آن در انتظار پرداخت است. لطفاً به بخش صورت حساب‌ها مراجعه کنید.', 'error');
        } else {
            wc_add_notice('شما قبلاً برای این رویداد ثبت‌نام کرده‌اید و صورت حساب آن در حال بررسی است. لطفاً به بخش صورت حساب‌ها مراجعه کنید.', 'error');
        }
        wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail'));
        exit;
    }
    
    // بررسی ثبت‌نام قبلی - بررسی event_registrations برای رویدادهای پرداخت شده
    $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';
    $existing_registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $event_registrations_table WHERE member_id = %d AND event_id = %d",
        $player->id,
        $event_id
    ));
    
    if ($existing_registration) {
        // بررسی اینکه آیا invoice مربوط به این registration پرداخت شده است یا نه
        $paid_invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $invoices_table WHERE id = %d AND status IN ('paid', 'completed', 'processing')",
            $existing_registration->invoice_id
        ));
        
        if ($paid_invoice) {
            wc_add_notice('شما قبلاً در این رویداد ثبت نام کرده‌اید.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail'));
            exit;
        }
    }
    
    // بررسی و اعتبارسنجی فیلدهای سفارشی
    $event_fields_table = $wpdb->prefix . 'sc_event_fields';
    $event_fields = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $event_fields_table WHERE event_id = %d ORDER BY field_order ASC, id ASC",
        $event_id
    ));
    
    $field_data = [];
    $uploaded_files = [];
    $errors = [];
    
    if (!empty($event_fields)) {
        foreach ($event_fields as $field) {
            $field_value = null;
            
            // بررسی فیلدهای متنی، عددی، تاریخ و select
            if (in_array($field->field_type, ['text', 'number', 'date', 'select'])) {
                if (isset($_POST['event_fields'][$field->id])) {
                    $field_value = sanitize_text_field($_POST['event_fields'][$field->id]);
                }
                
                // بررسی اجباری بودن
                if ($field->is_required && empty($field_value)) {
                    $errors[] = 'فیلد "' . $field->field_name . '" الزامی است.';
                    continue;
                }
                
                $field_data[$field->id] = [
                    'field_name' => $field->field_name,
                    'field_type' => $field->field_type,
                    'value' => $field_value
                ];
            }
            
            // پردازش فایل‌ها
            if ($field->field_type === 'file') {
                if (isset($_FILES['event_fields']['name'][$field->id][0]) && !empty($_FILES['event_fields']['name'][$field->id][0])) {
                    $file_count = count($_FILES['event_fields']['name'][$field->id]);
                    
                    // بررسی تعداد فایل‌ها
                    if ($file_count > 10) {
                        $errors[] = 'فیلد "' . $field->field_name . '": حداکثر 10 فایل مجاز است.';
                        continue;
                    }
                    
                    $field_files = [];
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['event_fields']['error'][$field->id][$i] !== UPLOAD_ERR_OK) {
                            continue;
                        }
                        
                        $file_name = $_FILES['event_fields']['name'][$field->id][$i];
                        $file_tmp = $_FILES['event_fields']['tmp_name'][$field->id][$i];
                        $file_size = $_FILES['event_fields']['size'][$field->id][$i];
                        $file_type = $_FILES['event_fields']['type'][$field->id][$i];
                        
                        // بررسی حجم فایل (1 مگابایت)
                        if ($file_size > 1048576) { // 1MB in bytes
                            $errors[] = 'فیلد "' . $field->field_name . '": فایل "' . $file_name . '" بیش از 1 مگابایت است.';
                            continue;
                        }
                        
                        // بررسی نوع فایل (فقط تصویر و PDF)
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
                        
                        if (!in_array($file_type, $allowed_types) && !in_array($file_ext, $allowed_exts)) {
                            $errors[] = 'فیلد "' . $field->field_name . '": فایل "' . $file_name . '" باید تصویر یا PDF باشد.';
                            continue;
                        }
                        
                        // آپلود فایل
                        $upload_dir = wp_upload_dir();
                        $sc_upload_dir = $upload_dir['basedir'] . '/sportclub-event-files';
                        if (!file_exists($sc_upload_dir)) {
                            wp_mkdir_p($sc_upload_dir);
                        }
                        
                        $unique_filename = wp_unique_filename($sc_upload_dir, $file_name);
                        $file_path = $sc_upload_dir . '/' . $unique_filename;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $file_url = $upload_dir['baseurl'] . '/sportclub-event-files/' . $unique_filename;
                            $field_files[] = [
                                'name' => $file_name,
                                'url' => $file_url,
                                'path' => $file_path,
                                'size' => $file_size,
                                'type' => $file_type
                            ];
                        }
                    }
                    
                    // بررسی اجباری بودن
                    if ($field->is_required && empty($field_files)) {
                        $errors[] = 'فیلد "' . $field->field_name . '" الزامی است.';
                        continue;
                    }
                    
                    if (!empty($field_files)) {
                        $field_data[$field->id] = [
                            'field_name' => $field->field_name,
                            'field_type' => $field->field_type,
                            'value' => null
                        ];
                        $uploaded_files[$field->id] = $field_files;
                    }
                } elseif ($field->is_required) {
                    $errors[] = 'فیلد "' . $field->field_name . '" الزامی است.';
                }
            }
        }
    }
    
    // اگر خطایی وجود داشت
    if (!empty($errors)) {
        foreach ($errors as $error) {
            wc_add_notice($error, 'error');
        }
        // ذخیره داده‌های فرم در session برای نمایش مجدد (اختیاری)
        wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail'));
        exit;
    }
    
    // ایجاد صورت حساب و سفارش WooCommerce
    error_log('SC Event Enrollment: Creating invoice for event_id: ' . $event_id . ', member_id: ' . $player->id . ', price: ' . $event->price);
    // =======================
// اگر رویداد رایگان است
// =======================
if ((float) $event->price <= 0) {

    $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';

    // جلوگیری از ثبت‌نام تکراری
    $already_registered = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $event_registrations_table WHERE event_id = %d AND member_id = %d",
            $event_id,
            $player->id
        )
    );

    if (!$already_registered) {
        $wpdb->insert(
            $event_registrations_table,
            [
                'event_id'   => $event_id,
                'member_id'  => $player->id,
                'invoice_id' => null,
                'field_data' => json_encode($field_data, JSON_UNESCAPED_UNICODE),
                'files'      => json_encode($uploaded_files, JSON_UNESCAPED_UNICODE),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );
    }

    // هوک اختیاری (پیامک، ایمیل، لاگ و...)
    do_action('sc_free_event_registered', $event_id, $player->id);

   // wc_add_notice('ثبت‌نام شما در رویداد با موفقیت انجام شد.', 'success');
    wp_safe_redirect(wc_get_account_endpoint_url('sc-event-success'));
exit;

    exit;
}

    $invoice_result = sc_create_event_invoice($player->id, $event_id, $event->price);
    
    error_log('SC Event Enrollment: Invoice result: ' . print_r($invoice_result, true));
    
    if ($invoice_result && isset($invoice_result['success']) && $invoice_result['success']) {
        $invoice_id = isset($invoice_result['invoice_id']) ? $invoice_result['invoice_id'] : null;
        
        // ذخیره اطلاعات ثبت‌نام (فیلدها و فایل‌ها)
        $registration_data = [
            'event_id' => $event_id,
            'member_id' => $player->id,
            'invoice_id' => $invoice_id,
            'field_data' => json_encode($field_data, JSON_UNESCAPED_UNICODE),
            'files' => json_encode($uploaded_files, JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $wpdb->insert(
            $event_registrations_table,
            $registration_data,
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );
        
        // ریدایرکت به تب صورت حساب‌ها
        wc_add_notice('ثبت‌نام شما با موفقیت انجام شد. لطفاً صورت حساب خود را پرداخت کنید.', 'success');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
        exit;
    } else {
        $error_message = isset($invoice_result['message']) ? $invoice_result['message'] : 'خطا در ایجاد صورت حساب';
        error_log('SC Event Invoice Creation Error: ' . $error_message);
        error_log('SC Event Invoice Result: ' . print_r($invoice_result, true));
        wc_add_notice('خطا در ثبت‌نام: ' . $error_message . '. لطفاً دوباره تلاش کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail'));
        exit;
    }
}

add_action('woocommerce_account_sc-event-success_endpoint', function () {
    ?>
    <div class="sc-event-success-page">
        <h2>🎉 ثبت‌نام شما با موفقیت انجام شد</h2>
        <p>ثبت‌نام شما در رویداد با موفقیت انجام شد.</p>

        <a href="<?php echo esc_url(wc_get_account_endpoint_url('sc-events')); ?>" class="button">
            بازگشت به لیست رویدادها
        </a>
    </div>
    <?php
});


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
        
        // فقط در حالت‌های processing و completed دوره را فعال کن
        if (in_array($new_status, ['processing', 'completed'])) {
            $payment_date = current_time('mysql');
            
            // فعال کردن دوره بعد از پرداخت موفق (فقط processing و completed)
            if ($invoice->member_course_id) {
                $member_courses_table = $wpdb->prefix . 'sc_member_courses';
                $courses_table = $wpdb->prefix . 'sc_courses';

                // بروزرسانی وضعیت دوره به active
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

                // ارسال پیامک ثبت نام موفق (فقط اگر دوره فعال باشد)
                $member_course = $wpdb->get_row($wpdb->prepare(
                    "SELECT mc.*, c.title as course_title, c.is_active as course_is_active
                     FROM $member_courses_table mc
                     LEFT JOIN $courses_table c ON mc.course_id = c.id
                     WHERE mc.id = %d",
                    $invoice->member_course_id
                ));

                if ($member_course && $member_course->course_is_active == 1) {
                    // Debug logging
                    error_log('SC PAYMENT SUCCESS: Enrollment SMS hook triggered for member_course_id: ' . $invoice->member_course_id);

                    // ارسال SMS ثبت نام موفق
                    do_action('sc_course_enrolled_success', $invoice->member_course_id);

                    // ارسال SMS پرداخت موفق
                    do_action('sc_payment_success', $invoice->id);
                } else {
                    error_log('SC PAYMENT SUCCESS: Enrollment SMS skipped - course not active or not found');
                }
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
    // مهم: باید رکورد موجود را پیدا کنیم تا از ایجاد رکورد تکراری جلوگیری کنیم
    $existing = null;
    
    // اول بر اساس user_id بررسی می‌کنیم (اولویت اول)
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    
    // اگر با user_id پیدا نشد، بر اساس national_id بررسی می‌کنیم (اولویت دوم)
    if (!$existing && !empty($data['national_id'])) {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE national_id = %s LIMIT 1",
            $data['national_id']
        ));
    }
    
    // اگر هنوز پیدا نشد، بر اساس player_phone بررسی می‌کنیم (اولویت سوم)
    if (!$existing && !empty($data['player_phone'])) {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE player_phone = %s LIMIT 1",
            $data['player_phone']
        ));
    }
    
    // اگر با national_id یا player_phone پیدا شد، بررسی می‌کنیم که user_id نداشته باشد یا با user_id فعلی متفاوت باشد
    if ($existing && !$existing->user_id) {
        // اگر user_id ندارد، آن را تنظیم می‌کنیم
        // این یعنی رکورد توسط مدیر ایجاد شده و user_id تنظیم نشده
    } elseif ($existing && $existing->user_id && $existing->user_id != $current_user_id) {
        // این national_id یا player_phone به کاربر دیگری اختصاص داده شده است
        wc_add_notice('این اطلاعات قبلاً به حساب کاربری دیگری اختصاص داده شده است. لطفاً با پشتیبانی تماس بگیرید.', 'error');
        return;
    }
    
    // فیلد سطح - فقط مدیر می‌تواند ویرایش کند
    if (current_user_can('manage_options') && isset($_POST['skill_level'])) {
        $data['skill_level'] = !empty(trim($_POST['skill_level'])) ? sanitize_text_field($_POST['skill_level']) : NULL;
    } elseif ($existing && isset($existing->skill_level)) {
        // اگر مدیر نیست، مقدار قبلی را حفظ می‌کنیم
        $data['skill_level'] = $existing->skill_level;
    } else {
        $data['skill_level'] = NULL;
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
        
        // مهم: همیشه user_id را به‌روزرسانی می‌کنیم تا اطمینان حاصل کنیم که رکورد به کاربر فعلی متصل است
        // این باعث می‌شود که اگر مدیر کاربر را اضافه کرده و user_id تنظیم نشده، حالا تنظیم شود
        $update_data['user_id'] = $current_user_id;
        
        // اگر مدیر نیست، skill_level را از update_data حذف می‌کنیم
        if (!current_user_can('manage_options')) {
            unset($update_data['skill_level']);
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
        // اگر رکورد پیدا نشد، بررسی می‌کنیم که آیا کد ملی یا شماره تماس تکراری است
        // این بررسی برای جلوگیری از ایجاد رکورد تکراری است
        $duplicate_national_id = null;
        $duplicate_phone = null;
        
        if (!empty($data['national_id'])) {
            $duplicate_national_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE national_id = %s LIMIT 1",
                $data['national_id']
            ));
        }
        
        if (!empty($data['player_phone'])) {
            $duplicate_phone = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE player_phone = %s LIMIT 1",
                $data['player_phone']
            ));
        }
        
        if ($duplicate_national_id || $duplicate_phone) {
            // اگر کد ملی یا شماره تماس تکراری است، باید همان رکورد را به‌روزرسانی کنیم
            $existing_id = $duplicate_national_id ? $duplicate_national_id : $duplicate_phone;
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d LIMIT 1",
                $existing_id
            ));
            
            if ($existing) {
                // بررسی اینکه آیا user_id در رکورد دیگری استفاده شده است
                $user_id_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE user_id = %d AND id != %d LIMIT 1",
                    $current_user_id,
                    $existing->id
                ));
            
                if ($user_id_exists) {
                    wc_add_notice('این حساب کاربری قبلاً به بازیکن دیگری اختصاص داده شده است. لطفاً با پشتیبانی تماس بگیرید.', 'error');
                    return;
                }
                
                // بروزرسانی رکورد موجود
                $update_data = $data;
                unset($update_data['created_at']);
                $update_data['updated_at'] = current_time('mysql');
                $update_data['user_id'] = $current_user_id;
                
                // حفظ skill_level اگر مدیر نیست
                if (!current_user_can('manage_options') && isset($existing->skill_level)) {
                    $update_data['skill_level'] = $existing->skill_level;
                }
                
                // آماده‌سازی format برای update
                $format = [];
                foreach ($update_data as $key => $value) {
                    if ($value === NULL) {
                        $format[] = '%s';
                    } elseif (in_array($key, ['health_verified', 'info_verified', 'is_active', 'user_id'])) {
                        $format[] = '%d';
                    } else {
                        $format[] = '%s';
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
                    sc_update_profile_completed_status($existing->id);
                    wc_add_notice('اطلاعات شما با موفقیت به روز شد.', 'success');
                    wp_safe_redirect(wc_get_account_endpoint_url('sc-submit-documents'));
                    exit;
                } else {
                    if ($wpdb->last_error) {
                        error_log('WP Update Error: ' . $wpdb->last_error);
                    }
                    wc_add_notice('خطا در بروزرسانی اطلاعات. لطفاً دوباره تلاش کنید.', 'error');
                }
                return;
            }
        }
        
        // افزودن جدید - فقط اگر هیچ رکوردی پیدا نشد
        // آماده‌سازی format برای insert
        $insert_format = [];
        foreach ($data as $key => $value) {
            if ($value === NULL) {
                $insert_format[] = '%s'; // NULL
            } elseif (in_array($key, ['health_verified', 'info_verified', 'is_active', 'user_id'])) {
                $insert_format[] = '%d'; // integer
            } else {
                $insert_format[] = '%s'; // string
            }
        }
        
        $inserted = $wpdb->insert($table_name, $data, $insert_format);
        
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