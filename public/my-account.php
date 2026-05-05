<?php
// start dashbord - info user summery
if ( ! defined('ABSPATH') ) exit;
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
    
    // موجودی کیف پول بر اساس member_id (بعد از وجود player)
    $wallet_balance = function_exists('sc_get_wallet_balance') ? sc_get_wallet_balance($player->id) : 0;
    
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
         AND status IN ('pending', 'under_review')  AND (course_id > 0 OR invoice_description IS NOT NULL)",
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
                    $date1=date_create($today_shamsi);
        $date2=date_create($insurance_expiry);
        $diff=date_diff($date1,$date2 ,false);
        $diff =  $diff->days ;
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
            </div>

            <div class="sc-user-stats">
                <h4 class="sc-section-title">اطلاعات حساب کاربری</h4>
                <a class="details_info_user_pannel" href="" > جزئیات بیشتر کاربر</a>

                <div class="sc-info-grid">

                    <div class="sc-info-card sc-card-blue">
                        <span class="sc-card-icon">💳</span>
                        <span class="sc-card-title">موجودی کیف پول</span>
                        <strong class="sc-card-value"><?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> تومان </strong>
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
                   <!-- وضعیت پروفایل -->
                <div class="sc-info-card sc-card-green">
                    <span class="sc-card-icon"><?php echo $profile_completed ? '✅' : '⚠️'; ?></span>
                    <span class="sc-card-title">وضعیت پروفایل:</span>
                    <strong class="sc-card-value">
                         <span style="font-size: 14px; color: <?php echo $profile_completed ? '#155724' : '#856404'; ?>; font-weight: 600;">
                                    <?php echo esc_html($profile_status); ?>
                                </span>
                    </strong>
                </div>

             
                      

                </div>
            </div>

        </div>
    </div>
    <div>
        <?php
        if (!empty($player->insurance_expiry_date_shamsi)) {
        $insurance_expiry = $player->insurance_expiry_date_shamsi;
        $today_shamsi = sc_get_today_shamsi();
        $expiry_compare = sc_compare_shamsi_dates($today_shamsi, $insurance_expiry);
        if ($expiry_compare >= 0) {
?>
<div class="sc-insurance-expiry-message">

<p> ⚠️اطلاعیه :  اعتبار بیمه شما به پایان رسیده لطفا نسبت به تمدید آن اقدام کنید و بعد از تمدید در بخش اطلاعات بازیکن تاریخ انقضا  بیمه خود را بروزرسانی کنید همچنین عکس بیمه ورزشی جدید خود را جایگزین عکس قبلی کنید . </p>

<?php 
        }elseif($expiry_compare < 0 && $diff < 30 ){
            ?>
            <div class="sc-insurance-expiry-message">
            <p>هشدار :  کمتر از <?php echo $diff; ?> روز به پایان انقضاء بیمه شما مهلت باقی است لطفا در اسرع وقت نسبت به تمدید آن اقدام کنید.</p>
            </div>
            <?php


        }
    }

        ?>
    </div>

<div id="scRegistrationModal" class="sc-modal" aria-hidden="true">
        <div class="sc-modal-inner">
        <div class="sc-modal-header">
            <span class="sc-modal-close" aria-label="بستن">×</span>
        </div>
      <div class="sc-modal-content-body">
            
                <!-- بخش قابل مشاهده (4 کارت) -->
                <div class="sc-visible-section" style="display: block;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <!-- دوره‌های فعال -->
                        <div class="sc-info-card" style="background: #ede9fe; padding: 14px; border-radius: 10px; border-right: 4px solid #6d34ff;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <span style="font-size: 22px;">📚</span>
                                <strong style="font-size: 13px; color: #444;">دوره‌های فعال</strong>
                            </div>
                            <div style="font-size: 24px; font-weight: bold; color: #4a1fb8;">
                                <?php echo esc_html($active_courses_count); ?>
                            </div>
                        </div>
                        
                        <!-- رویدادهای ثبت‌نام شده -->
                        <div class="sc-info-card" style="background: #fce4f5; padding: 14px; border-radius: 10px; border-right: 4px solid #ff0c81;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <span style="font-size: 22px;">🎯</span>
                                <strong style="font-size: 13px; color: #444;">رویدادهای ثبت‌نام شده</strong>
                            </div>
                            <div style="font-size: 24px; font-weight: bold; color: #a80654;">
                                <?php echo esc_html($event_registrations_count); ?>
                            </div>
                        </div>
                        
                        <!-- صورت حساب‌های پرداخت شده -->
                        <div class="sc-info-card" style="background: #fff4ed; padding: 14px; border-radius: 10px; border-right: 4px solid #ff7d51;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <span style="font-size: 22px;">✅</span>
                                <strong style="font-size: 13px; color: #444;">پرداخت‌های موفق</strong>
                            </div>
                            <div style="font-size: 24px; font-weight: bold; color: #c44d28;">
                                <?php echo esc_html($paid_invoices_count); ?>
                            </div>
                            <?php if ($paid_invoices_total > 0) : ?>
                                <div style="font-size: 12px; color: #c44d28; margin-top: 4px;">
                                    مجموع: <?php echo number_format($paid_invoices_total, 0, '.', ','); ?> تومان
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- سطح شما -->
                        <div class="sc-info-card" style="background: #fef9e7; padding: 14px; border-radius: 10px; border-right: 4px solid #ffc243;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                <span style="font-size: 22px;">⭐</span>
                                <strong style="font-size: 13px; color: #444;">سطح شما</strong>
                            </div>
                            <div style="font-size: 18px; font-weight: bold; color: #b8860b;">
                                <?php echo esc_html($skill_level); ?>
                            </div>
                        </div> 
                </div> 
                    </div>
                 
 
                
                    <!-- اطلاعات تکمیلی -->

                    
                    <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e5e5;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                        <!-- تاریخ عضویت -->
                        <?php if ($membership_date) : ?>
                            <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #fef9e7; border-radius: 8px; border-right: 3px solid #ffc243;">
                                <span style="font-size: 18px;">📅</span>
                                <div>
                                    <strong style="font-size: 12px; color: #555; display: block;">تاریخ عضویت:</strong>
                                    <span style="font-size: 14px; color: #333; font-weight: 600;"><?php echo esc_html($membership_date); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- وضعیت بیمه -->
                        <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #ede9fe; border-radius: 8px; border-right: 3px solid #6d34ff;">
                            <span style="font-size: 18px;">🛡️</span>
                            <div>
                                <strong style="font-size: 12px; color: #555; display: block;">وضعیت بیمه:</strong>
                                <span style="font-size: 14px; color: #333; font-weight: 600;">
                                    <?php echo esc_html($insurance_status);
                                                                                             
                                                                                                            
                                    
                                    ?>
                                    

                                    
                                    <?php if ($insurance_expiry && $insurance_status !== 'ثبت نشده') : ?>
                                        <small style="display: block; color: #999; font-weight: normal; margin-top: 3px;">
                                            (انقضا: <?php echo esc_html($insurance_expiry); ?>)
                                        </small>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="sc-info-card sc-card-green" style="display: flex;">
                    <span class="sc-card-icon" style="margin-top: 11px;">📋</span>
                        <div style="padding-right: 5px;">
                                <span class="sc-card-title" >حضور و غیاب</span>
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
                        
                        <!-- آخرین صورت حساب پرداخت شده -->
                        <?php if ($last_invoice) : ?>
                            <?php
                            // تعیین نام دوره یا رویداد
                            $invoice_item_name = '';
                            if (!empty($last_invoice->course_title)) {
                                $invoice_item_name = $last_invoice->course_title;
                            } elseif (!empty($last_invoice->event_name)) {
                                $invoice_item_name = $last_invoice->event_name;
                            } else {
                                $invoice_item_name = 'سایر(مثل شارژ حساب و ...)';
                            }
                            ?>
                            <div style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #fff4ed; border-radius: 8px; border-right: 3px solid #ff7d51;">
                                <span style="font-size: 18px;">💳</span>
                                <div style="flex: 1; min-width: 0;">
                                    <strong style="font-size: 12px; color: #555; display: block; margin-bottom: 4px;">آخرین صورت حساب پرداخت شده:</strong>
                                    <div style="font-size: 13px; color: #333; font-weight: 600;">
                                        <div style="margin-bottom: 2px;">
                                            <span style="color: #6d34ff; font-weight: bold;"><?php echo esc_html($invoice_item_name); ?></span>
                                        </div>
                                        <div style="margin-top: 4px;">
                                           مبلغ: <span style="color: #6d34ff;"><?php echo number_format(floatval($last_invoice->amount), 0, '.', ','); ?> تومان</span>
                                            - <?php echo esc_html(sc_date_shamsi_date_only($last_invoice->payment_date)); ?>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
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
add_filter('woocommerce_account_menu_items', 'sc_add_my_account_menu_item', 20, 1);
function sc_add_my_account_menu_item($items) {
    // مخفی کردن تب برای مدیران
    if (current_user_can('manage_options')) {
        return $items;
    }
    //انتخاب اسم 
    // Insert before logout
    $logout = $items['customer-logout'];
    unset($items['customer-logout']); 
    $items['sc-submit-documents'] = 'اطلاعات بازیکن' ;
    $items['sc-enroll-course'] = 'ثبت نام در دوره';
    $items['sc-my-courses'] = 'دوره‌های من';
    $items['sc-my-attendances'] = 'حضور و غیاب های من ';
    $items['sc-events'] = 'رویدادها / مسابقات';
    $items['sc-my-events'] = ' رویداد های من ';
    $items['sc-invoices'] = 'صورت حساب‌ها';
    if(sc_get_setting('pro_feature_shop')){ 
    $items['shop'] = 'فروشگاه ' ;
    $items['my-orders'] = 'سفارش های فروشگاه';
    }
    $items['sc-my-honors'] = 'افتخارات من';
    if (function_exists('sc_is_pro_feature_notifications_enabled') && sc_is_pro_feature_notifications_enabled()) {
        $unread = function_exists('sc_count_unread_notifications') ? sc_count_unread_notifications(get_current_user_id()) : 0;
        $items['sc-notifications'] = $unread > 0 ? sprintf('اطلاعیه‌ها (%d)', $unread) : 'اطلاعیه‌ها';
    }
    // تب کیف پول: فقط امکانات پرو (هم‌سطح با پنل ادمین). محتوا تنظیم کیف پول را چک می‌کند.
    if (function_exists('sc_is_pro_feature_players_wallet_enabled') && sc_is_pro_feature_players_wallet_enabled()) {
        $items['sc-wallet'] = 'کیف پول';
    }
    $unread_ticket = sc_count_user_tickets(get_current_user_id(), 'pending_reply');
    $items['sc-support-tickets'] = $unread_ticket > 0 ? sprintf('تیکت پشتیبانی  (%d)', $unread_ticket) : 'تیکت پشتیبانی ';


    $items['sc-faq'] = ' سوالات متداول ';

    $player = sc_get_current_member_for_account_user();
    if (sc_is_member_verification_gate_enabled_for_user($player)) {
        $allowed_when_unverified = ['sc-submit-documents', 'my-orders', 'shop', 'customer-logout'];
        foreach (array_keys($items) as $item_key) {
            if (!in_array($item_key, $allowed_when_unverified, true)) {
                unset($items[$item_key]);
            }
        }
    }

    $items['customer-logout'] = 'خروج از حساب کاربری';
    
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
    add_rewrite_endpoint('sc-my-attendances', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-events', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-event-detail', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-my-events', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-faq', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-invoices', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('shop', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('my-orders', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-event-success', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-wallet', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-my-honors', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-notifications', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('sc-support-tickets', EP_ROOT | EP_PAGES);

}

/**
 * Add query vars
 */
add_filter('query_vars', 'sc_add_my_account_query_vars', 0);
function sc_add_my_account_query_vars($vars) {
    $vars[] = 'sc-submit-documents';
    $vars[] = 'sc-my-honors';
    $vars[] = 'sc-enroll-course';
    $vars[] = 'sc-my-courses';
    $vars[] = 'sc-events';
    $vars[] = 'sc-my-events';
    $vars[] = 'sc-faq';
    $vars[] = 'sc-event-detail';
    $vars[] = 'sc-my-attendances';
    $vars[] = 'sc-invoices';
    $vars[] = 'sc-wallet';
    $vars[] = 'sc-notifications';
    $vars[] = 'sc-support-tickets';
    $vars[] = 'my-orders';
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
add_filter('woocommerce_endpoint_sc-my-attendances_title', function() { 
    return 'حضور و غیاب های من '; 
});

add_filter('woocommerce_endpoint_sc-events_title', function() { 
    return 'رویدادها / مسابقات'; 
});
add_filter('woocommerce_endpoint_sc-my-events_title', function() { 
    return 'رویداد های من'; 
});
add_filter('woocommerce_endpoint_sc-faq_title', function() { 
    return 'پرسش و پاسخ '; 
});

add_filter('woocommerce_endpoint_sc-event-detail_title', function() { 
    return 'جزئیات رویداد'; 
});
add_filter('woocommerce_endpoint_my-orders_title', function() { 
    return 'سفارش های فروشگاه'; 
});

add_filter('woocommerce_endpoint_sc-invoices_title', 'sc_invoices_endpoint_title');

add_filter('woocommerce_endpoint_sc-notifications_title', function() { return 'اطلاعیه‌ها'; });
add_filter('woocommerce_endpoint_sc-support-tickets_title', function() { return 'تیکت پشتیبانی'; });
function sc_invoices_endpoint_title($title) {
    return 'صورت حساب‌ها';
}

/**
 * Get current logged in member row for account checks
 * @return object|null
 */
function sc_get_current_member_for_account_user() {
    if (!is_user_logged_in()) {
        return null;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    $current_user_id = get_current_user_id();
    $billing_phone = get_user_meta($current_user_id, 'billing_phone', true);

    $player = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));

    if (!$player && $billing_phone) {
        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE player_phone = %s LIMIT 1",
            $billing_phone
        ));
    }

    return $player ?: null;
}

/**
 * Check if verification gate is active for current user
 */
function sc_is_member_verification_gate_enabled_for_user($player = null) {
    if (current_user_can('manage_options')) {
        return false;
    }
    if (!function_exists('sc_is_player_verification_required') || !sc_is_player_verification_required()) {
        return false;
    }
    if (!$player) {
        $player = sc_get_current_member_for_account_user();
    }
    return $player && (int)($player->identity_verified ?? 0) !== 1;
}

/**
 * Check if endpoint should be blocked until verification
 */
function sc_is_member_verification_locked_endpoint($endpoint = '') {
    $allowed = ['sc-submit-documents', 'my-orders', 'shop', 'customer-logout'];
    return !in_array($endpoint, $allowed, true);
}

/**
 * Render message for restricted endpoints
 */
function sc_render_member_verification_required_message() {
    $profile_url = wc_get_account_endpoint_url('sc-submit-documents');
    ?>
    <div class="sc-inactive-user-message" style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 20px; margin: 20px 0; color: #856404;">
        <strong style="display: block; margin-bottom: 10px; font-size: 16px;">⚠️ برای ادامه لازم است احراز هویت انجام شود</strong>
        <p style="margin: 0; font-size: 14px;">
            لطفا ابتدا اطلاعات بازیکن خود را تکمیل/به‌روزرسانی کنید تا توسط مدیر بررسی و تایید شود.
            <a href="<?php echo esc_url($profile_url); ?>">رفتن به اطلاعات بازیکن</a>
        </p>
    </div>
    <?php
}

/**
 * Check whether a member can access a restricted item (course/event).
 * Restriction applies only when restriction_enabled = 1.
 */
function sc_member_matches_item_restrictions($item, $player = null) {
    if (!$item) {
        return false;
    }
    if (!$player) {
        $player = sc_get_current_member_for_account_user();
    }
    if (!$player) {
        return false;
    }

    $enabled = isset($item->restriction_enabled) ? (int) $item->restriction_enabled : 0;
    if ($enabled !== 1) {
        return true;
    }

    $player_gender = isset($player->gender) ? (string) $player->gender : '';
    $player_team = isset($player->team_player) ? trim((string) $player->team_player) : '';
    $player_level = isset($player->skill_level) ? trim((string) $player->skill_level) : '';

    $allowed_gender = isset($item->allowed_gender) && $item->allowed_gender !== '' ? (string) $item->allowed_gender : 'both';
    if (in_array($allowed_gender, ['male', 'female'], true) && $player_gender !== $allowed_gender) {
        return false;
    }

    $allowed_teams = [];
    if (!empty($item->allowed_teams)) {
        $decoded = json_decode((string) $item->allowed_teams, true);
        if (is_array($decoded)) {
            $allowed_teams = array_values(array_filter(array_map('trim', $decoded)));
        }
    }
    if (!empty($allowed_teams) && ($player_team === '' || !in_array($player_team, $allowed_teams, true))) {
        return false;
    }

    $allowed_levels = [];
    if (!empty($item->allowed_levels)) {
        $decoded = json_decode((string) $item->allowed_levels, true);
        if (is_array($decoded)) {
            $allowed_levels = array_values(array_filter(array_map('trim', $decoded)));
        }
    }
    if (!empty($allowed_levels) && ($player_level === '' || !in_array($player_level, $allowed_levels, true))) {
        return false;
    }

    return true;
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
        <div class="sc-incomplete-profile-message" >
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
    $player = sc_get_current_member_for_account_user();
    
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

    // اگر احراز هویت اجباری است و کاربر تایید نشده، فقط تب اطلاعات بازیکن در دسترس است
    if (sc_is_member_verification_gate_enabled_for_user($player)) {
        $current_endpoint = '';
        if (function_exists('WC') && WC() && isset(WC()->query)) {
            $current_endpoint = (string) WC()->query->get_current_endpoint();
        }
        // endpoint خالی یعنی داشبورد ووکامرس
        if ($current_endpoint === '' || sc_is_member_verification_locked_endpoint($current_endpoint)) {
            sc_render_member_verification_required_message();
            return false;
        }
    }
    
    return $player;
}

// display register event user
add_action('woocommerce_account_sc-my-events_endpoint', 'sc_my_account_my_events_content');
function sc_my_account_my_events_content(){
          // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // بررسی وضعیت فعال بودن کاربر
    $player = sc_check_user_active_status();
    if (!$player) {
        return; // اگر غیرفعال بود، پیام نمایش داده شده و خروج می‌کنیم
    }

    
    
    $user_id_wp = get_current_user_id();

    global $wpdb;
    
    $member_event_register_table = $wpdb->prefix . 'sc_event_registrations';
    $event_table = $wpdb->prefix . 'sc_events';
	$wp_user_table = $wpdb->prefix . 'users';
	$member_table = $wpdb->prefix . 'sc_members';

$results = $wpdb->get_results(
    "SELECT m.id
     FROM {$wp_user_table} wp
     INNER JOIN {$member_table} m
     ON wp.ID = m.user_id
     WHERE wp.ID = {$user_id_wp}" , ARRAY_A
);

$user_id =  $results[0]['id'];



    $count_events = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$member_event_register_table} WHERE member_id = %d",
        $user_id
    )
); 

    $per_page = 10;
    $limit = $per_page;
    $current_page = isset($_GET['pag']) ? absint($_GET['pag']) : 1;
    $offset = ($current_page - 1) * $per_page;
	$total_pages = ceil($count_events / $per_page);


    $query = $wpdb->prepare(
    "SELECT r.id, r.event_id, e.event_time, e.name, e.holding_date_shamsi
     FROM {$member_event_register_table} r
     INNER JOIN {$event_table} e ON r.event_id = e.id
     WHERE r.member_id = %d
     LIMIT %d OFFSET %d",
    $user_id,
    $limit,
    $offset
);

$user_events = $wpdb->get_results($query, ARRAY_A);
    
    
    include SC_TEMPLATES_PUBLIC_DIR . 'my-events.php';
}
// display register event user
add_action('woocommerce_account_sc-faq_endpoint', 'sc_my_account_faq_content');
function sc_my_account_faq_content(){
          // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    $player = sc_check_user_active_status();
    if (!$player) {
        return;
    }
    
 
    include SC_TEMPLATES_PUBLIC_DIR . 'faq.php';
}
/**
 * Display content for custom tab
 */
add_action('woocommerce_account_sc-submit-documents_endpoint', 'sc_my_account_documents_content');
function sc_my_account_documents_content() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // در تب اطلاعات بازیکن، حتی کاربر احرازنشده باید فرم را ببیند.
    // فقط اگر کاربر غیرفعال باشد، دسترسی قطع می‌شود.
    $player = sc_get_current_member_for_account_user();
    if ($player && isset($player->is_active) && (int)$player->is_active === 0) {
        ?>
        <div class="sc-inactive-user-message" style="background-color: #f8d7da; border: 1px solid #dc3545; border-radius: 4px; padding: 20px; margin: 20px 0; color: #721c24;">
            <strong style="display: block; margin-bottom: 10px; font-size: 16px;">⚠️ حساب شما غیر فعال است</strong>
            <p style="margin: 0; font-size: 14px;">
                حساب کاربری شما غیر فعال شده است. در صورتی که نیاز به فعال شدن دارید با مدیریت باشگاه ارتباط بگیرید.
            </p>
        </div>
        <?php
        return;
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
    
    $chapter = isset($chapter) ? $chapter : (isset($_GET['chapter']) ? sanitize_text_field($_GET['chapter']) : 'all');

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
    

    // فیلتر بر اساس شعبه
    if($chapter !== 'all'){
        $where_conditions[] = " chapter LIKE '%$chapter%' ";
        //$where_values = $chapter;
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

    // اعمال محدودیت تیم/سطح/جنسیت روی دوره‌ها
    if (!empty($courses)) {
        $courses = array_values(array_filter($courses, function ($course) use ($player) {
            return sc_member_matches_item_restrictions($course, $player);
        }));
    }
    
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
    if (!sc_member_matches_item_restrictions($course, $player)) {
        wc_add_notice('شما مجاز به مشاهده یا ثبت‌نام در این دوره نیستید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
        exit;
    }

    $has_pkg = function_exists('sc_course_has_packages') && sc_course_has_packages($course_id);
    $enrollment_sessions_sel = isset($_POST['enrollment_sessions']) ? absint($_POST['enrollment_sessions']) : 0;
    $invoice_amount = floatval($course->price);
    $fee_label = function_exists('sc_course_enrollment_fee_label')
        ? sc_course_enrollment_fee_label($course->title, null)
        : ('ثبت نام دوره: ' . $course->title);

    if ($has_pkg) {
        if (!$enrollment_sessions_sel || !function_exists('sc_get_course_package_by_sessions') || !sc_get_course_package_by_sessions($course_id, $enrollment_sessions_sel)) {
            wc_add_notice('لطفاً پکیج (تعداد جلسه) را انتخاب کنید.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
            exit;
        }
        $pkg = sc_get_course_package_by_sessions($course_id, $enrollment_sessions_sel);
        $invoice_amount = floatval($pkg->price);
        $fee_label = sc_course_enrollment_fee_label($course->title, (int) $pkg->sessions_count);
    } elseif ($invoice_amount <= 0) {
        wc_add_notice('قیمت این دوره ثبت نشده است. لطفاً با پشتیبانی تماس بگیرید.', 'error');
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
            $upd_inactive = [
                'status' => 'inactive',
                'enrollment_date' => null,
                'updated_at' => current_time('mysql'),
            ];
            if ($has_pkg && $enrollment_sessions_sel) {
                $upd_inactive['enrollment_sessions'] = $enrollment_sessions_sel;
            } else {
                $upd_inactive['enrollment_sessions'] = null;
            }
            $fmt_inactive = ['%s', '%s', '%s', '%s'];
            $updated = $wpdb->update(
                $member_courses_table,
                $upd_inactive,
                ['id' => $existing->id],
                $fmt_inactive,
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
        $insert_mc = [
            'member_id' => $player->id,
            'course_id' => $course_id,
            'enrollment_date' => null,
            'status' => 'inactive',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'enrollment_sessions' => ($has_pkg && $enrollment_sessions_sel) ? $enrollment_sessions_sel : null,
        ];
        $inserted = $wpdb->insert(
            $member_courses_table,
            $insert_mc,
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
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
        // بازیکن تیم: صورت حساب ایجاد نمی‌شود، دوره فوراً فعال است - فعلا غیرفعال شود تا بررسی شود 
    
        if (function_exists('sc_is_member_team') && sc_is_member_team($player->id) && !sc_get_setting('pro_create_invoice_player_team')) {
            $sf_team = function_exists('sc_member_course_session_fields_for_course')
                ? sc_member_course_session_fields_for_course($course_id, $has_pkg ? $enrollment_sessions_sel : null)
                : ['enrollment_sessions' => null, 'total_sessions' => 0, 'remaining_sessions' => 0];
            $team_upd = [
                'status' => 'active',
                'enrollment_date' => current_time('Y-m-d'),
                'updated_at' => current_time('mysql'),
                'total_sessions' => (int) $sf_team['total_sessions'],
                'remaining_sessions' => (int) $sf_team['remaining_sessions'],
            ];
            if ($sf_team['enrollment_sessions'] === null) {
                $team_upd['enrollment_sessions'] = null;
            } else {
                $team_upd['enrollment_sessions'] = (int) $sf_team['enrollment_sessions'];
            }
            $team_fmt = ['%s', '%s', '%s', '%d', '%d'];
            $team_fmt[] = $sf_team['enrollment_sessions'] === null ? '%s' : '%d';
            $wpdb->update(
                $member_courses_table,
                $team_upd,
                ['id' => $member_course_id],
                $team_fmt,
                ['%d']
            );
            if(sc_get_setting('deduction_wallet_enabled')){
           wc_add_notice('ثبت‌نام شما با موفقیت انجام شد. هزینه هر جلسه از کیف پول کسر می‌شود.', 'success');
            }else{
                           wc_add_notice('ثبت‌نام شما با موفقیت انجام شد.', 'success');

            }
            wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
            exit;
        }

        // بازیکن عادی: ایجاد صورت حساب و سفارش WooCommerce
        $discount_meta = null;
        $raw_discount = isset($_POST['sc_invoice_discount_code']) ? sanitize_text_field(wp_unslash($_POST['sc_invoice_discount_code'])) : '';
        if ($raw_discount !== '' && function_exists('sc_validate_sc_discount_code')) {
            $vd = sc_validate_sc_discount_code($raw_discount, [
                'context' => 'course',
                'member_id' => (int) $player->id,
                'subtotal' => $invoice_amount,
                'course_id' => $course_id,
                'course' => $course,
            ]);
            if (is_wp_error($vd)) {
                wc_add_notice($vd->get_error_message(), 'error');
                wp_safe_redirect(wc_get_account_endpoint_url('sc-enroll-course'));
                exit;
            }
            if ($vd['discount_amount'] > 0) {
                $discount_meta = [
                    'discount_code_id' => $vd['discount_code_id'],
                    'discount_code' => $vd['code'],
                    'discount_amount' => $vd['discount_amount'],
                    'net_amount' => $vd['net_amount'],
                    'subtotal' => $vd['subtotal'],
                ];
            }
        }

        $invoice_result = sc_create_course_invoice($player->id, $course_id, $member_course_id, $invoice_amount, '', $fee_label, $discount_meta);

        if ($invoice_result && isset($invoice_result['success']) && $invoice_result['success']) {
            wc_add_notice('مرحله اول ثبت‌نام شما با موفقیت انجام شد جهت فعال شدن دوره لطفاً صورت حساب خود را پرداخت کنید .', 'success');
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
function sc_create_course_invoice($member_id, $course_id, $member_course_id, $amount, $type = '', $fee_display_name = '', $discount_meta = null) {
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

    $subtotal = round(floatval($amount), 2);
    $discount_amt = 0;
    $discount_code_id = null;
    $discount_code_str = null;
    $net_total = $subtotal;
    if (is_array($discount_meta) && isset($discount_meta['discount_amount']) && floatval($discount_meta['discount_amount']) > 0) {
        $discount_amt = round(floatval($discount_meta['discount_amount']), 2);
        $discount_code_id = isset($discount_meta['discount_code_id']) ? absint($discount_meta['discount_code_id']) : null;
        $discount_code_str = isset($discount_meta['discount_code']) ? sanitize_text_field($discount_meta['discount_code']) : '';
        $net_total = isset($discount_meta['net_amount'])
            ? round(floatval($discount_meta['net_amount']), 2)
            : round(max(0, $subtotal - $discount_amt), 2);
    }
    
    // اضافه کردن Fee به سفارش با استفاده از WC_Order_Item_Fee
    $fee = new WC_Order_Item_Fee();
    $fee_name = ($fee_display_name !== '' && $fee_display_name !== null)
        ? $fee_display_name
        : ('هزینه دوره: ' . $course->title);
    $fee->set_name($fee_name);
    $fee->set_amount($subtotal);
    $fee->set_tax_class('');
    $fee->set_tax_status('none');
    $fee->set_total($subtotal);
    $order->add_item($fee);

    if ($discount_amt > 0) {
        $disc_fee = new WC_Order_Item_Fee();
        $disc_label = $discount_code_str
            ? ('تخفیف (' . $discount_code_str . ')')
            : 'تخفیف';
        $disc_fee->set_name($disc_label);
        $disc_fee->set_amount(-$discount_amt);
        $disc_fee->set_tax_class('');
        $disc_fee->set_tax_status('none');
        $disc_fee->set_total(-$discount_amt);
        $order->add_item($disc_fee);
    }
    
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
    $invoice_row = [
        'member_id' => $member_id,
        'course_id' => $course_id,
        'member_course_id' => $member_course_id,
        'woocommerce_order_id' => $order_id,
        'amount' => $net_total,
        'penalty_amount' => 0.00,
        'penalty_applied' => 0,
        'status' => 'pending',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
        'type' => $type,
    ];
    $invoice_fmt = ['%d', '%d', '%d', '%d', '%f', '%f', '%d', '%s', '%s', '%s', '%s'];

    if (function_exists('sc_invoices_support_discount_columns') && sc_invoices_support_discount_columns()) {
        $invoice_row['subtotal_amount'] = $subtotal;
        $invoice_row['discount_amount'] = $discount_amt;
        $invoice_row['discount_code_id'] = ($discount_amt > 0 && $discount_code_id) ? $discount_code_id : null;
        $invoice_row['discount_code'] = ($discount_amt > 0 && $discount_code_str) ? $discount_code_str : null;
        $invoice_fmt[] = '%f';
        $invoice_fmt[] = '%f';
        $invoice_fmt[] = '%s';
        $invoice_fmt[] = '%s';
    }

    $invoice_inserted = $wpdb->insert($invoices_table, $invoice_row, $invoice_fmt);
    
    if ($invoice_inserted === false) {
        // در صورت خطا، سفارش را حذف می‌کنیم
        wp_delete_post($order_id, true);
        return ['success' => false, 'message' => 'خطا در ایجاد صورت حساب.'];
    }
    
    // بررسی و اعمال جریمه در صورت نیاز
    $invoice_id = $wpdb->insert_id;
    if ($invoice_id) {
        if ($discount_amt > 0 && $discount_code_id && function_exists('sc_record_sc_discount_usage')) {
            sc_record_sc_discount_usage($discount_code_id, $invoice_id, $member_id, $discount_amt);
        }
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

add_action('woocommerce_account_sc-my-attendances_endpoint', 'sc_my_account_attendances_content');
function sc_my_account_attendances_content() {
    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses'; 
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $members_table = $wpdb->prefix . 'sc_members'; 
    $courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");


    include SC_TEMPLATES_PUBLIC_DIR . 'my-attendances.php';

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
    $current_page = isset($_GET['pag']) ? absint($_GET['pag']) : 1;
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

    $sc_weekly_schedule_matrix = ['days' => [], 'cells' => []];
    if (function_exists('sc_get_member_weekly_schedule_matrix')) {
        $sc_weekly_schedule_matrix = sc_get_member_weekly_schedule_matrix((int) $player->id);
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

    $inv_disc = 0.0;
    $inv_sub = null;
    if ($invoice && function_exists('sc_invoices_support_discount_columns') && sc_invoices_support_discount_columns()) {
        $inv_disc = isset($invoice->discount_amount) ? floatval($invoice->discount_amount) : 0.0;
        $inv_sub = isset($invoice->subtotal_amount) ? floatval($invoice->subtotal_amount) : null;
    }
    
    $events_table = $wpdb->prefix . 'sc_events';
    $event = null;
    if ($invoice && !empty($invoice->event_id)) {
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $events_table WHERE id = %d",
            $invoice->event_id
        ));
    }

    $fee_discount_handled = false;
    if ($invoice && $inv_disc > 0 && $inv_sub !== null && $inv_sub > 0) {
        if ($course && $course_id > 0 && floatval($course->price) > 0) {
            $course_amount = $inv_sub;
            $fee = new WC_Order_Item_Fee();
            $fee->set_name('دوره: ' . $course->title);
            $fee->set_amount($inv_sub);
            $fee->set_tax_class('');
            $fee->set_tax_status('none');
            $fee->set_total($inv_sub);
            $order->add_item($fee);
            $disc_fee = new WC_Order_Item_Fee();
            $dl = !empty($invoice->discount_code) ? ('تخفیف (' . $invoice->discount_code . ')') : 'تخفیف';
            $disc_fee->set_name($dl);
            $disc_fee->set_amount(-$inv_disc);
            $disc_fee->set_tax_class('');
            $disc_fee->set_tax_status('none');
            $disc_fee->set_total(-$inv_disc);
            $order->add_item($disc_fee);
            $fee_discount_handled = true;
        } elseif ($event && !empty($invoice->event_id) && floatval($event->price) > 0) {
            $course_amount = $inv_sub;
            $fee = new WC_Order_Item_Fee();
            $fee->set_name('رویداد / مسابقه: ' . $event->name);
            $fee->set_amount($inv_sub);
            $fee->set_tax_class('');
            $fee->set_tax_status('none');
            $fee->set_total($inv_sub);
            $order->add_item($fee);
            $disc_fee = new WC_Order_Item_Fee();
            $dl = !empty($invoice->discount_code) ? ('تخفیف (' . $invoice->discount_code . ')') : 'تخفیف';
            $disc_fee->set_name($dl);
            $disc_fee->set_amount(-$inv_disc);
            $disc_fee->set_tax_class('');
            $disc_fee->set_tax_status('none');
            $disc_fee->set_total(-$inv_disc);
            $order->add_item($disc_fee);
            $fee_discount_handled = true;
        }
    }

    if (!$fee_discount_handled && $course && $course->price > 0) {
        $course_amount = floatval($course->price);
        // اضافه کردن هزینه دوره به سفارش
        $fee = new WC_Order_Item_Fee();
        $fee->set_name('دوره: ' . $course->title);
        $fee->set_amount($course_amount);
        $fee->set_tax_class('');
        $fee->set_tax_status('none');
        $fee->set_total($course_amount);
        $order->add_item($fee);
    } elseif (!$fee_discount_handled && $event && $event->price > 0) {
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
function sc_create_event_invoice($member_id, $event_id, $amount, $discount_meta = null) {
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

    $subtotal = round(floatval($amount), 2);
    $discount_amt = 0;
    $discount_code_id = null;
    $discount_code_str = null;
    $net_total = $subtotal;
    if (is_array($discount_meta) && isset($discount_meta['discount_amount']) && floatval($discount_meta['discount_amount']) > 0) {
        $discount_amt = round(floatval($discount_meta['discount_amount']), 2);
        $discount_code_id = isset($discount_meta['discount_code_id']) ? absint($discount_meta['discount_code_id']) : null;
        $discount_code_str = isset($discount_meta['discount_code']) ? sanitize_text_field($discount_meta['discount_code']) : '';
        $net_total = isset($discount_meta['net_amount'])
            ? round(floatval($discount_meta['net_amount']), 2)
            : round(max(0, $subtotal - $discount_amt), 2);
    }
    
    // ایجاد صورت حساب
    $invoice_data = [
        'member_id' => $member_id,
        'event_id' => $event_id,
        'course_id' => 0, // برای رویداد، course_id باید 0 باشد نه NULL
        'member_course_id' => NULL,
        'woocommerce_order_id' => NULL,
        'amount' => $net_total,
        'expense_name' => NULL,
        'penalty_amount' => 0.00,
        'penalty_applied' => 0,
        'status' => 'pending',
        'payment_date' => NULL,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ];

    $format_array = ['%d', '%d', '%d', '%s', '%s', '%f', '%s', '%f', '%d', '%s', '%s', '%s', '%s'];

    if (function_exists('sc_invoices_support_discount_columns') && sc_invoices_support_discount_columns()) {
        $invoice_data['subtotal_amount'] = $subtotal;
        $invoice_data['discount_amount'] = $discount_amt;
        $invoice_data['discount_code_id'] = ($discount_amt > 0 && $discount_code_id) ? $discount_code_id : null;
        $invoice_data['discount_code'] = ($discount_amt > 0 && $discount_code_str) ? $discount_code_str : null;
        $format_array[] = '%f';
        $format_array[] = '%f';
        $format_array[] = '%s';
        $format_array[] = '%s';
    }
    
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

    if ($invoice_id && $discount_amt > 0 && $discount_code_id && function_exists('sc_record_sc_discount_usage')) {
        sc_record_sc_discount_usage($discount_code_id, $invoice_id, $member_id, $discount_amt);
    }

    // Trigger SMS hook for event invoices
    do_action('sc_invoice_created', $invoice_id);

    // ایجاد سفارش WooCommerce (مبلغ ورودی = خالص پس از تخفیف برای هم‌خوانی با هزینه اضافی)
    $order_result = sc_create_woocommerce_order_for_invoice($invoice_id, $member_id, 0, $net_total, $event->name);
    
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
 * Handle payment from wallet
 */
add_action('template_redirect', 'sc_handle_wallet_payment');
function sc_handle_wallet_payment() {
    if (!is_account_page() || !isset($_GET['pay_from_wallet']) || !isset($_GET['invoice_id'])) {
        return;
    }
    
    $invoice_id = absint($_GET['invoice_id']);
    if (!$invoice_id) {
        return;
    }
    
    // بررسی nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'pay_from_wallet_' . $invoice_id)) {
        wc_add_notice('خطا در تأیید درخواست. لطفاً دوباره تلاش کنید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
        exit;
    }
    
    // بررسی فعال بودن کیف پول (امکانات پرو + تنظیم کیف پول)
    if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) {
        wc_add_notice('سیستم کیف پول فعال نیست.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
        exit;
    }
    
    // بررسی وضعیت کاربر
    $player = sc_check_user_active_status();
    if (!$player) {
        wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
        exit;
    }
    
    // پرداخت از کیف پول
    $result = sc_pay_invoice_from_wallet($invoice_id);
    
    if ($result['success']) {
        if (isset($result['remaining_amount']) && $result['remaining_amount'] > 0) {
            // پرداخت جزئی انجام شد - هدایت به صفحه پرداخت برای مابقی
            global $wpdb;
            $invoices_table = $wpdb->prefix . 'sc_invoices';
            $invoice = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $invoices_table WHERE id = %d",
                $invoice_id
            ));
            
            if ($invoice && !empty($invoice->woocommerce_order_id)) {
                $order = wc_get_order($invoice->woocommerce_order_id);
                if ($order) {
                    // بروزرسانی مبلغ سفارش
                    $order->calculate_totals();
                    $order->save();
                    
                    wc_add_notice('مبلغ ' . number_format($result['paid_amount'], 0, '.', ',') . ' تومان از کیف پول پرداخت شد. مابقی مبلغ: ' . number_format($result['remaining_amount'], 0, '.', ',') . ' تومان', 'success');
                    wp_safe_redirect($order->get_checkout_payment_url());
                    exit;
                }
            }
        } else {
            // پرداخت کامل انجام شد
            wc_add_notice('صورت حساب با موفقیت از کیف پول پرداخت شد.', 'success');
        }
    } else {
        wc_add_notice($result['message'], 'error');
    }
    
    wp_safe_redirect(wc_get_account_endpoint_url('sc-invoices'));
    exit;
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
 * Handle invoice cancellation request
 */
add_action('template_redirect', 'sc_handle_order_cancellation');
function sc_handle_order_cancellation() {
    // بررسی درخواست لغو
    if (!isset($_GET['cancel_order']) || !isset($_GET['order_id']) || !isset($_GET['_wpnonce'])) {
        return;
    }
    
    // بررسی اینکه آیا در صفحه invoices هستیم
    if (!is_account_page()) {
        return;
    }
    
    // بررسی nonce
    if (!wp_verify_nonce($_GET['_wpnonce'], 'cancel_order_' . $_GET['order_id'])) {
        wc_add_notice('درخواست نامعتبر است.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('my-orders'));
        exit;
    }
    
    // بررسی لاگین بودن کاربر
    if (!is_user_logged_in()) {
        wc_add_notice('لطفاً ابتدا وارد حساب کاربری خود شوید.', 'error');
        wp_safe_redirect(wc_get_account_endpoint_url('my-orders'));
        exit;
    }
    
    // بررسی و ایجاد جداول
    sc_check_and_create_tables();
    
   
    
    $order_id = absint($_GET['order_id']);
    

    // بررسی اینکه فقط سفارش‌های با وضعیت pending یا under_review قابل لغو هستند
    
    
    
    global $wpdb;

 $members_table = $wpdb->prefix . 'sc_members';
        $orders_table = $wpdb->prefix . 'wc_orders';
        $woocommerce_order_items_table = $wpdb->prefix . 'woocommerce_order_items';
        $woocommerce_order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

// حذف صورت حساب‌ها
                
                    $invoice = $wpdb->get_row($wpdb->prepare(
                        "SELECT wo.id , wo.status
                    from wp_wc_orders wo
                    INNER JOIN wp_woocommerce_order_items woi ON wo.id = woi.order_id
                    WHERE woi.order_item_type NOT IN ('fee') AND wo.customer_id NOT IN (0) AND wo.id = %d 
                    GROUP BY 
                        wo.id, wo.status, wo.total_amount, wo.payment_method_title;",
                                            $order_id
                    ));
                 
                    if (!in_array($invoice->status, ['pending', 'under_review' , 'wc-checkout-draft'])) {
                        wc_add_notice("'فقط سفارش‌های در انتظار پرداخت یا در حال بررسی قابل لغو هستند.' ", 'error');
                        wp_safe_redirect(wc_get_account_endpoint_url('my-orders'));
                        exit;
                    }
                                    // حذف صورت حساب
                  $orderdelete1 =  $wpdb->delete($orders_table, ['id' => $order_id], ['%d']);
                   $orderdelete2 =  $wpdb->delete($woocommerce_order_items_table, ['order_id' => $order_id], ['%d']);


 
        
       if($orderdelete1 && $orderdelete2 ){
        
      
          wc_add_notice('سفارش با موفقیت حذف شد.', 'success');

    wp_safe_redirect(wc_get_account_endpoint_url('my-orders'));
                  
    
    exit;
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
    $events_table = $wpdb->prefix . 'sc_events';
    
    // دریافت فیلتر وضعیت
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all';
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

    $from_sql = "FROM {$invoices_table} i
              LEFT JOIN {$courses_table} c ON i.course_id = c.id AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')
              LEFT JOIN {$events_table} e ON i.event_id = e.id AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
              WHERE {$where_clause}";

    // ساخت ORDER BY - pending ها اول، سپس بر اساس تاریخ
    $order_by = "ORDER BY
        CASE
            WHEN i.status = 'pending' THEN 1
            WHEN i.status = 'under_review' THEN 2
            ELSE 3
        END,
        i.created_at DESC";

    $count_sql = "SELECT COUNT(*) {$from_sql}";
    $total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $where_values));

    $per_page = 10;
    $total_pages = max(1, (int) ceil($total_items / $per_page));
    $current_page = isset($_GET['pag']) ? max(1, absint($_GET['pag'])) : 1;
    if ($current_page > $total_pages) {
        $current_page = $total_pages;
    }
    $offset = ($current_page - 1) * $per_page;

    $query = "SELECT i.*, c.title as course_title, c.price as course_price, e.name as event_name
              {$from_sql}
              {$order_by}
              LIMIT %d OFFSET %d";

    $invoices = $wpdb->get_results($wpdb->prepare($query, array_merge($where_values, [$per_page, $offset])));













    include SC_TEMPLATES_PUBLIC_DIR . 'invoices-list.php';
}

/**
 * Display content for wallet tab
 */
add_action('woocommerce_account_sc-wallet_endpoint', 'sc_my_account_wallet_content');
function sc_my_account_wallet_content() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // بررسی وضعیت فعال بودن کاربر
    $player = sc_check_user_active_status();
    if (!$player) {
        return; // اگر غیرفعال بود، پیام نمایش داده شده و خروج می‌کنیم
    }
    
    // بررسی فعال بودن کیف پول (امکانات پرو + تنظیم کیف پول)
    if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) {
        if (function_exists('sc_is_pro_feature_players_wallet_enabled') && !sc_is_pro_feature_players_wallet_enabled()) {
            wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
            exit;
        }
        echo '<div class="woocommerce-message woocommerce-message--info woocommerce-info">سیستم کیف پول فعال نیست.</div>';
        return;
    }
    
    include SC_TEMPLATES_PUBLIC_DIR . 'wallet.php';
}

/**
 * Display content for my honors tab
 */
add_action('woocommerce_account_sc-notifications_endpoint', 'sc_my_account_notifications_content');
function sc_my_account_notifications_content() {
    sc_check_and_create_tables();
    if (!is_user_logged_in()) return;
    $player = sc_check_user_active_status();
    if (!$player) return;
    if (function_exists('sc_is_pro_feature_notifications_enabled') && !sc_is_pro_feature_notifications_enabled()) {
        wp_safe_redirect(wc_get_account_endpoint_url('dashboard'));
        exit;
    }
    include SC_TEMPLATES_PUBLIC_DIR . 'my-notifications.php';
}

add_action('woocommerce_account_sc-support-tickets_endpoint', 'sc_my_account_support_tickets_content');
function sc_my_account_support_tickets_content() {
    sc_check_and_create_tables();
    if (!is_user_logged_in()) return;
    $player = sc_check_user_active_status();
    if (!$player) return;
    include SC_TEMPLATES_PUBLIC_DIR . 'support-tickets.php';
}

add_action('woocommerce_account_my-orders_endpoint', 'sc_my_account_my_orders_content');


function sc_my_account_my_orders_content() {
    global $wpdb;
    $player = sc_check_user_active_status();
    if (!$player) {
        return;
    }

    $members_table = $wpdb->prefix . 'sc_members';
    $orders_table = $wpdb->prefix . 'wc_orders';
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $woocommerce_order_items_table = $wpdb->prefix . 'woocommerce_order_items';
    $woocommerce_order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

    $user_id = get_current_user_id();
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all';

    $where_conditions = [
        'woi.order_item_type = %s',
        'sm.user_id = %d',
        'wo.customer_id > 0',
    ];
    $where_values = ['line_item', $user_id];

    $type_col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$orders_table}` LIKE %s", 'type'));
    if ($type_col) {
        $where_conditions[] = 'wo.type = %s';
        $where_values[] = 'shop_order';
    }

    if ($filter_status !== 'all' && $filter_status !== 'penalty') {
        if ($filter_status === 'completed') {
            $where_conditions[] = '(wo.status = %s OR wo.status = %s)';
            $where_values[] = 'wc-completed';
            $where_values[] = 'wc-processing';
        } elseif ($filter_status === 'on-hold') {
            $where_conditions[] = '(wo.status = %s OR wo.status = %s)';
            $where_values[] = 'wc-on-hold';
            $where_values[] = 'wc-under_review';
        } elseif ($filter_status === 'pending') {
            $where_conditions[] = '(wo.status = %s OR wo.status = %s)';
            $where_values[] = 'wc-pending';
            $where_values[] = 'wc-checkout-draft';
        } elseif ($filter_status === 'paid') {
            $where_conditions[] = 'wo.status = %s';
            $where_values[] = 'wc-completed';
        } else {
            $map = [
                'processing' => 'wc-processing',
                'cancelled' => 'wc-cancelled',
                'failed' => 'wc-failed',
                'refunded' => 'wc-refunded',
            ];
            if (isset($map[$filter_status])) {
                $where_conditions[] = 'wo.status = %s';
                $where_values[] = $map[$filter_status];
            }
        }
    } elseif ($filter_status === 'penalty') {
        $where_conditions[] = "EXISTS (SELECT 1 FROM {$invoices_table} invp WHERE invp.woocommerce_order_id = wo.id AND (invp.penalty_amount > 0 OR invp.penalty_applied = 1))";
    }

    $where_sql = implode(' AND ', $where_conditions);

    $from_sql = "
            {$members_table} sm
            INNER JOIN {$orders_table} wo ON wo.customer_id = sm.user_id
            INNER JOIN {$woocommerce_order_items_table} woi ON woi.order_id = wo.id
            INNER JOIN {$woocommerce_order_itemmeta} woi_qty
                ON woi.order_item_id = woi_qty.order_item_id
                AND woi_qty.meta_key = '_qty'
        WHERE
            {$where_sql}";

    $count_sql = "SELECT COUNT(DISTINCT wo.id) FROM {$from_sql}";
    $total_orders = (int) $wpdb->get_var($wpdb->prepare($count_sql, $where_values));

    $per_page = 10;
    $total_pages = max(1, (int) ceil($total_orders / $per_page));
    $current_page = isset($_GET['pag']) ? max(1, absint($_GET['pag'])) : 1;
    if ($current_page > $total_pages) {
        $current_page = $total_pages;
    }
    $offset = ($current_page - 1) * $per_page;

    $query = "SELECT
            wo.id AS id_order,
            sm.user_id,
            wo.status,
            wo.payment_method_title,
            GROUP_CONCAT(
                CONCAT(
                    woi.order_item_name,
                    ' (',
                    woi_qty.meta_value,
                    ')'
                )
                SEPARATOR '<br>'
            ) AS products_with_quantity,
            wo.total_amount,
            wo.date_created_gmt
        FROM
            {$from_sql}
        GROUP BY
            wo.id, sm.user_id, sm.first_name, sm.last_name, sm.player_phone, wo.status, wo.total_amount, wo.payment_method_title, wo.date_created_gmt
        ORDER BY
            wo.id DESC
        LIMIT %d OFFSET %d";

    $orders = $wpdb->get_results($wpdb->prepare($query, array_merge($where_values, [$per_page, $offset])));

    include SC_TEMPLATES_PUBLIC_DIR . 'my_orders.php';






}






add_action('woocommerce_account_sc-my-honors_endpoint', 'sc_my_account_my_honors_content');
function sc_my_account_my_honors_content() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // بررسی وضعیت فعال بودن کاربر
    $player = sc_check_user_active_status();
    if (!$player) {
        return; // اگر غیرفعال بود، پیام نمایش داده شده و خروج می‌کنیم
    }
    
    include SC_TEMPLATES_PUBLIC_DIR . 'my-honors.php';
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
        $where_conditions[] = "holding_date_gregorian < %s";
        $where_values[] = $today_gregorian;
    } elseif ($filter_status === 'is_upcoming') {
 
       // به زودی - در آینده و در بازه ثبت‌نام نیست
        $where_conditions[] = "(
            (start_date_gregorian > %s)
            AND (holding_date_gregorian > %s)
        )";
        $where_values[] = $today_gregorian;
        $where_values[] = $today_gregorian;
    } elseif ($filter_status === 'all') {
    
        // همه - بدون محدودیت تاریخ
        // هیچ شرط اضافی اضافه نمی‌کنیم
    } else {
     
        //پیش‌فرض: آخرین - در بازه ثبت‌نام
        $where_conditions[] = "(
            (start_date_gregorian <= %s)
            AND (end_date_gregorian >= %s)
        )";
        $where_values[] = $today_gregorian;
        $where_values[] = $today_gregorian;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    //صفحه بندی
    
    $per_page = 10;

    $query_count = "SELECT COUNT(*) FROM $events_table 
              WHERE $where_clause
              ";
    $events_count = $wpdb->get_results($wpdb->prepare($query_count, $where_values),ARRAY_N);
    $current_page = isset($_GET['pag']) ? max(1, absint($_GET['pag'])) : 1;
    $total_items = $events_count[0][0];
        $limit = $per_page;
    $offset = ($current_page - 1) * $per_page;
   $total_pages = ceil($total_items / $per_page);






 //"
    // دریافت رویدادها
    $query = "SELECT * FROM $events_table 
              WHERE $where_clause
              ORDER BY holding_date_gregorian ASC, created_at ASC LIMIT $limit OFFSET $offset";
  
  
    if (!empty($where_values)) {
      
        $events = $wpdb->get_results($wpdb->prepare($query, $where_values));

        
    } else {
   
        $events = $wpdb->get_results($query);
    }

    if (!empty($events)) {
        $events = array_values(array_filter($events, function ($event) use ($player) {
            return sc_member_matches_item_restrictions($event, $player);
        }));
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
    if (!sc_member_matches_item_restrictions($event, $player)) {
        wc_add_notice('شما مجاز به مشاهده این رویداد نیستید.', 'error');
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
    if (!sc_member_matches_item_restrictions($event, $player)) {
        wc_add_notice('شما مجاز به ثبت‌نام در این رویداد نیستید.', 'error');
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
    wp_safe_redirect(wc_get_account_endpoint_url("sc-event-success?event_id=$event_id&player_id=$player->id"));
exit;

    exit;
}

    $discount_meta = null;
    $raw_discount = isset($_POST['sc_invoice_discount_code']) ? sanitize_text_field(wp_unslash($_POST['sc_invoice_discount_code'])) : '';
    if ($raw_discount !== '' && function_exists('sc_validate_sc_discount_code')) {
        $vd = sc_validate_sc_discount_code($raw_discount, [
            'context' => 'event',
            'member_id' => (int) $player->id,
            'subtotal' => floatval($event->price),
            'event_id' => $event_id,
            'event' => $event,
        ]);
        if (is_wp_error($vd)) {
            wc_add_notice($vd->get_error_message(), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('sc-event-detail'));
            exit;
        }
        if ($vd['discount_amount'] > 0) {
            $discount_meta = [
                'discount_code_id' => $vd['discount_code_id'],
                'discount_code' => $vd['code'],
                'discount_amount' => $vd['discount_amount'],
                'net_amount' => $vd['net_amount'],
                'subtotal' => $vd['subtotal'],
            ];
        }
    }

    $invoice_result = sc_create_event_invoice($player->id, $event_id, $event->price, $discount_meta);
    
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
        wc_add_notice('مرحله اول ثبت‌نام شما با موفقیت انجام شد جهت فعال شدن دوره لطفاً صورت حساب خود را پرداخت کنید .', 'success');
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
    
<?php
        if (isset($_GET['event_id'])) {
        $event_id =  $_GET['event_id']; 
        $player_id =  $_GET['player_id']; 
        global $wpdb;
        $event_table = $wpdb->prefix . 'sc_events';
        $query = "SELECT * FROM $event_table WHERE id=$event_id";
        $event = $wpdb->get_row($query );

        $name = $event->name ?? '';
        $event_type = $event->event_type ?? 'event';
        if($event_type =="competition"){
           $event_type ="مسابقه";
        }else{
            $event_type = "رویداد"; 
        }
        $description = $event->description ?? '';

        $holding_date = $event->holding_date_gregorian ?? '';
        $holding_date_shamsi = $event->holding_date_shamsi ?? '';
        $image = $event->image ?? './assets/img/no-image.jpg';
        $event_time = $event->event_time ?? '';
        
        $event_location = $event->event_location ?? '';
        $event_location_address = $event->event_location_address ?? '';
        $event_location_lat = $event->event_location_lat ?? '';
        $event_location_lng = $event->event_location_lng ?? '';

            
        ?>
        <div class="sc-event-success-page form-row" >
        <h2>🎉 ثبت‌نام شما در <strong><?php echo $name; ?> </strong> با موفقیت انجام شد.</h2>
      </div>  
        <div class="sc-event-detail-section">
            <h3>اطلاعات رویداد ثبت نامی شما :</h3>
            <p>باتشکر از شرکت در این رویداد اطلاعات زیر را به خاطر داشته باشید متنظر قدم سرسبزتان هستیم.</p>
        </div>
        
        <div class="info_event_register">
             <div class="info_box_1">
            
                <div class="details_event sc-event-detail-section">
                    <div class="head">
                    <h3><?php echo $name; ?></h3>
                    </div>

            <div class="sc-event-detail-meta-grid">
                    <div class="sc-event-detail-meta-item">
                    <span class="sc-event-meta-icon">📅</span>
                    <div>
                        <strong>تاریخ </strong>
                        <p><?php echo $holding_date_shamsi; ?></p>
                    </div>
                </div>
                <div class="sc-event-detail-meta-item">
                    <span class="sc-event-meta-icon">🕐</span>
                    <div>
                        <strong>زمان </strong>
                        <p><?php echo $event_time; ?></p>
                    </div>
                </div>
                <div class="sc-event-detail-meta-item">
                    <span class="sc-event-meta-icon">📍</span>
                    <div>
                        <strong>مکان </strong>
                        <p><?php echo $event_location; ?></p>
                    </div>
                </div>
            </div>
                <div class="info_box_2 sc-event-detail-section">
                    <h3> توضیحات  :</h3>
                    <p>
                    <?php echo $description; ?>
                    </p>
                </div>
            
             <div class="info_box_3 sc-event-detail-section">

                <div class="address_info"><h3>آدرس دقیق :</h3>
                <p><?php echo $event_location_address; ?>
                </p>
            </div>

                <div class="loc_map ">
            <?php if (!empty($event->event_location_lat) && !empty($event->event_location_lng)) : ?>
                <div class="sc-event-detail-section">
                    <h3>لوکیشن محل برگزاری : </h3>
                    <div class="sc-event-map">
                        <iframe
                            width="100%"
                            height="400"
                            frameborder="0"
                            style="border:0; border-radius: 8px;"
                            src="https://www.google.com/maps?q=<?php echo esc_attr($event_location_lat); ?>,<?php echo esc_attr($event_location_lng); ?>&output=embed"
                            allowfullscreen>
                        </iframe>
                    </div>
                </div>
            <?php endif; ?>        
            </div>
             </div>
        </div>
            


    </div>
    <?php


}

    
    

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
            
            // بررسی اینکه آیا این صورت حساب برای شارژ کیف پول است
            if (!empty($invoice->expense_name) && $invoice->expense_name === 'شارژ کیف پول' && $invoice->course_id == 0 && empty($invoice->member_course_id)) {
                // شارژ کیف پول بعد از پرداخت موفق
                if (function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet()) {
                    // بررسی اینکه آیا قبلاً تراکنش شارژ برای این صورت حساب ثبت شده است
                    $transactions_table = $wpdb->prefix . 'sc_wallet_transactions';
                    $existing_transaction = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $transactions_table 
                         WHERE related_invoice_id = %d 
                         AND transaction_type = 'charge' 
                         AND status = 'completed' 
                         LIMIT 1",
                        $invoice->id
                    ));
                    
                    // اگر تراکنش قبلاً ثبت نشده است، آن را ثبت کن
                    if (!$existing_transaction) {
                        $charge_result = sc_charge_wallet(
                            $invoice->member_id,
                            floatval($invoice->amount),
                            'شارژ کیف پول - پرداخت صورت حساب #' . $invoice->id,
                            $order->get_customer_id() // created_by
                        );
                        
                        if ($charge_result['success']) {
                            error_log('SC WALLET: Charge transaction created after payment - Invoice ID: ' . $invoice->id . ', Amount: ' . $invoice->amount);
                        } else {
                            error_log('SC WALLET ERROR: Failed to create charge transaction - Invoice ID: ' . $invoice->id . ', Error: ' . $charge_result['message']);
                        }
                    } else {
                        error_log('SC WALLET: Charge transaction already exists for Invoice ID: ' . $invoice->id);
                    }
                }
            }
            
            // فعال کردن دوره بعد از پرداخت موفق (فقط processing و completed)
            if ($invoice->member_course_id && $invoice->type !== 'session_auto') {
                $member_courses_table = $wpdb->prefix . 'sc_member_courses';
                $courses_table = $wpdb->prefix . 'sc_courses';
                $course = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $courses_table WHERE id = %d",
                $invoice->course_id
                ));
                $mc_pay = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $member_courses_table WHERE id = %d LIMIT 1",
                    $invoice->member_course_id
                ));
                $total_sessions = 0;
                if ($mc_pay && isset($mc_pay->enrollment_sessions) && $mc_pay->enrollment_sessions !== null && $mc_pay->enrollment_sessions !== '') {
                    $total_sessions = (int) $mc_pay->enrollment_sessions;
                } elseif ($course && !empty($course->sessions_count)) {
                    $total_sessions = (int) $course->sessions_count;
                }
                // بروزرسانی وضعیت دوره به active
                $wpdb->update(
                    $member_courses_table,
                    [
                        'status' => 'active',
                        'enrollment_date' => current_time('Y-m-d'),
                        'total_sessions' => $total_sessions,
                        'remaining_sessions' => $total_sessions,
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $invoice->member_course_id],
                    ['%s', '%s', '%d', '%d', '%s'],
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

            // ======== اضافه‌شده (شناسایی پرداخت موفق) ========
    do_action('sc_invoice_paid', $invoice->id);
    // ================================================

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
    $data['province'] = isset($_POST['province']) && !empty(trim($_POST['province'])) ? sanitize_text_field($_POST['province']) : NULL;
    $data['city'] = isset($_POST['city']) && !empty(trim($_POST['city'])) ? sanitize_text_field($_POST['city']) : NULL;
    $data['gender'] = isset($_POST['gender']) && in_array($_POST['gender'], ['male', 'female'], true) ? sanitize_text_field($_POST['gender']) : NULL;
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
    // هر تغییری توسط بازیکن، وضعیت احراز را به انتظار بررسی برمی‌گرداند
    $data['identity_verified'] = 0;
   
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
            } elseif (in_array($key, ['health_verified', 'info_verified', 'identity_verified', 'is_active', 'user_id'])) {
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
                    } elseif (in_array($key, ['health_verified', 'info_verified', 'identity_verified', 'is_active', 'user_id'])) {
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
            } elseif (in_array($key, ['health_verified', 'info_verified', 'identity_verified', 'is_active', 'user_id'])) {
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
 * AJAX: آپلود فوری یک عکس (بلافاصله بعد از انتخاب فایل)
 */
add_action('wp_ajax_sc_upload_player_photo', 'sc_ajax_upload_player_photo');
function sc_ajax_upload_player_photo() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'لطفاً ابتدا وارد شوید.']);
    }
    if (!isset($_POST['sc_documents_nonce']) || !wp_verify_nonce($_POST['sc_documents_nonce'], 'sc_submit_documents')) {
        wp_send_json_error(['message' => 'خطای امنیتی. لطفاً صفحه را رفرش کنید.']);
    }
    $field_name = isset($_POST['field_name']) ? sanitize_text_field($_POST['field_name']) : '';
    $allowed = ['personal_photo', 'id_card_photo', 'sport_insurance_photo'];
    if (!in_array($field_name, $allowed, true)) {
        wp_send_json_error(['message' => 'فیلد نامعتبر.']);
    }
    if (!isset($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => 'فایلی انتخاب نشده یا خطا در آپلود.']);
    }
    $user_id = get_current_user_id();
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    $file = $_FILES[$field_name];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024;
    if (!in_array($file['type'], $allowed_types, true)) {
        wp_send_json_error(['message' => 'فقط تصاویر (JPG, PNG, GIF, WEBP) مجاز است.']);
    }
    if ($file['size'] > $max_size) {
        wp_send_json_error(['message' => 'حداکثر حجم هر فایل ۵ مگابایت است.']);
    }
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        wp_send_json_error(['message' => 'فایل انتخاب‌شده تصویر معتبر نیست.']);
    }
    $upload_overrides = [
        'test_form' => false,
        'mimes' => ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'],
        'unique_filename_callback' => function($dir, $name, $ext) use ($user_id, $field_name) {
            return sanitize_file_name($user_id . '_' . $field_name . '_' . time() . $ext);
        }
    ];
    $movefile = wp_handle_upload($file, $upload_overrides);
    if ($movefile && !isset($movefile['error'])) {
        wp_send_json_success(['url' => $movefile['url']]);
    }
    wp_send_json_error(['message' => isset($movefile['error']) ? $movefile['error'] : 'خطا در ذخیره فایل.']);
}

/**
 * AJAX: ارسال فرم اطلاعات بازیکن (بدون رفرش صفحه)
 */
add_action('wp_ajax_sc_submit_documents_ajax', 'sc_ajax_submit_documents');
function sc_ajax_submit_documents() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'لطفاً ابتدا وارد شوید.']);
    }
    if (!isset($_POST['sc_documents_nonce']) || !wp_verify_nonce($_POST['sc_documents_nonce'], 'sc_submit_documents')) {
        wp_send_json_error(['message' => 'خطای امنیتی. لطفاً صفحه را رفرش کنید.']);
    }
    sc_check_and_create_tables();
    $current_user_id = get_current_user_id();
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';

    if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['national_id'])) {
        wp_send_json_error(['message' => 'لطفاً فیلدهای اجباری (نام، نام خانوادگی، کد ملی) را پر کنید.']);
    }

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

    $data['father_name'] = isset($_POST['father_name']) && trim($_POST['father_name']) !== '' ? sanitize_text_field($_POST['father_name']) : null;
    $data['player_phone'] = isset($_POST['player_phone']) && trim($_POST['player_phone']) !== '' ? sanitize_text_field($_POST['player_phone']) : null;
    $data['father_phone'] = isset($_POST['father_phone']) && trim($_POST['father_phone']) !== '' ? sanitize_text_field($_POST['father_phone']) : null;
    $data['mother_phone'] = isset($_POST['mother_phone']) && trim($_POST['mother_phone']) !== '' ? sanitize_text_field($_POST['mother_phone']) : null;
    $data['landline_phone'] = isset($_POST['landline_phone']) && trim($_POST['landline_phone']) !== '' ? sanitize_text_field($_POST['landline_phone']) : null;
    $data['province'] = isset($_POST['province']) && trim($_POST['province']) !== '' ? sanitize_text_field($_POST['province']) : null;
    $data['city'] = isset($_POST['city']) && trim($_POST['city']) !== '' ? sanitize_text_field($_POST['city']) : null;
    $data['gender'] = isset($_POST['gender']) && in_array($_POST['gender'], ['male', 'female'], true) ? sanitize_text_field($_POST['gender']) : null;
    $data['birth_date_shamsi'] = isset($_POST['birth_date_shamsi']) && trim($_POST['birth_date_shamsi']) !== '' ? sanitize_text_field($_POST['birth_date_shamsi']) : null;
    $data['birth_date_gregorian'] = isset($_POST['birth_date_gregorian']) && trim($_POST['birth_date_gregorian']) !== '' ? sanitize_text_field($_POST['birth_date_gregorian']) : null;
    $insurance_expiry_date_shamsi = isset($_POST['insurance_expiry_date_shamsi']) && trim($_POST['insurance_expiry_date_shamsi']) !== '' ? sanitize_text_field($_POST['insurance_expiry_date_shamsi']) : null;
    $data['insurance_expiry_date_shamsi'] = $insurance_expiry_date_shamsi;
    $insurance_expiry_date_gregorian = null;
    if ($insurance_expiry_date_shamsi && isset($_POST['insurance_expiry_date_gregorian']) && trim($_POST['insurance_expiry_date_gregorian']) !== '') {
        $insurance_expiry_date_gregorian = sanitize_text_field($_POST['insurance_expiry_date_gregorian']);
    } elseif ($insurance_expiry_date_shamsi) {
        $insurance_expiry_date_gregorian = sc_shamsi_to_gregorian_date($insurance_expiry_date_shamsi);
    }
    $data['insurance_expiry_date_gregorian'] = $insurance_expiry_date_gregorian;
    $data['medical_condition'] = isset($_POST['medical_condition']) && trim($_POST['medical_condition']) !== '' ? sanitize_textarea_field($_POST['medical_condition']) : null;
    $data['sports_history'] = isset($_POST['sports_history']) && trim($_POST['sports_history']) !== '' ? sanitize_textarea_field($_POST['sports_history']) : null;
    $data['additional_info'] = isset($_POST['additional_info']) && trim($_POST['additional_info']) !== '' ? sanitize_textarea_field($_POST['additional_info']) : null;
    $data['health_verified'] = (isset($_POST['health_verified']) && $_POST['health_verified']) ? 1 : 0;
    $data['info_verified'] = (isset($_POST['info_verified']) && $_POST['info_verified']) ? 1 : 0;
    $data['identity_verified'] = 0;
    

    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d LIMIT 1", $current_user_id));
    if (!$existing && !empty($data['national_id'])) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE national_id = %s LIMIT 1", $data['national_id']));
    }
    if (!$existing && !empty($data['player_phone'])) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE player_phone = %s LIMIT 1", $data['player_phone']));
    }
    if ($existing && $existing->user_id && (int) $existing->user_id !== $current_user_id) {
        wp_send_json_error(['message' => 'این اطلاعات قبلاً به حساب کاربری دیگری اختصاص داده شده است.']);
    }

    if (current_user_can('manage_options') && isset($_POST['skill_level'])) {
        $data['skill_level'] = trim($_POST['skill_level']) !== '' ? sanitize_text_field($_POST['skill_level']) : null;
    } elseif ($existing && isset($existing->skill_level)) {
        $data['skill_level'] = $existing->skill_level;
    } else {
        $data['skill_level'] = null;
    }

    if (isset($_POST['personal_photo_url']) && trim($_POST['personal_photo_url']) !== '') {
        $data['personal_photo'] = esc_url_raw(trim($_POST['personal_photo_url']));
    } elseif ($existing && !empty($existing->personal_photo)) {
        $data['personal_photo'] = $existing->personal_photo;
    } else {
        $data['personal_photo'] = null;
    }
    if (isset($_POST['id_card_photo_url']) && trim($_POST['id_card_photo_url']) !== '') {
        $data['id_card_photo'] = esc_url_raw(trim($_POST['id_card_photo_url']));
    } elseif ($existing && !empty($existing->id_card_photo)) {
        $data['id_card_photo'] = $existing->id_card_photo;
    } else {
        $data['id_card_photo'] = null;
    }
    if (isset($_POST['sport_insurance_photo_url']) && trim($_POST['sport_insurance_photo_url']) !== '') {
        $data['sport_insurance_photo'] = esc_url_raw(trim($_POST['sport_insurance_photo_url']));
    } elseif ($existing && !empty($existing->sport_insurance_photo)) {
        $data['sport_insurance_photo'] = $existing->sport_insurance_photo;
    } else {
        $data['sport_insurance_photo'] = null;
    }

    if ($existing) {
        $user_id_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND id != %d LIMIT 1",
            $current_user_id,
            $existing->id
        ));
        if ($user_id_exists) {
            wp_send_json_error(['message' => 'این حساب کاربری قبلاً به بازیکن دیگری اختصاص داده شده است.']);
        }
        unset($data['created_at']);
        $data['updated_at'] = current_time('mysql');
        $data['user_id'] = $current_user_id;
        if (!current_user_can('manage_options')) {
            unset($data['skill_level']);
        }
        $format = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                $format[] = '%s';
            } elseif (in_array($key, ['health_verified', 'info_verified', 'identity_verified', 'is_active', 'user_id'], true)) {
                $format[] = '%d';
            } else {
                $format[] = '%s';
            }
        }
        $updated = $wpdb->update($table_name, $data, ['id' => $existing->id], $format, ['%d']);
        if ($updated !== false) {
            sc_update_profile_completed_status($existing->id);
            wp_send_json_success(['message' => 'اطلاعات شما با موفقیت به روز شد.']);
        }
        wp_send_json_error(['message' => 'خطا در بروزرسانی. لطفاً دوباره تلاش کنید.']);
    }

    $duplicate_national_id = !empty($data['national_id']) ? $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE national_id = %s LIMIT 1", $data['national_id'])) : null;
    $duplicate_phone = !empty($data['player_phone']) ? $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE player_phone = %s LIMIT 1", $data['player_phone'])) : null;
    if ($duplicate_national_id || $duplicate_phone) {
        $existing_id = $duplicate_national_id ?: $duplicate_phone;
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d LIMIT 1", $existing_id));
        if ($existing) {
            $user_id_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE user_id = %d AND id != %d LIMIT 1",
                $current_user_id,
                $existing->id
            ));
            if ($user_id_exists) {
                wp_send_json_error(['message' => 'این حساب کاربری قبلاً به بازیکن دیگری اختصاص داده شده است.']);
            }
            unset($data['created_at']);
            $data['updated_at'] = current_time('mysql');
            $data['user_id'] = $current_user_id;
            if (!current_user_can('manage_options') && isset($existing->skill_level)) {
                $data['skill_level'] = $existing->skill_level;
            }
            $format = [];
            foreach ($data as $key => $value) {
                if ($value === null) {
                    $format[] = '%s';
                } elseif (in_array($key, ['health_verified', 'info_verified', 'identity_verified', 'is_active', 'user_id'], true)) {
                    $format[] = '%d';
                } else {
                    $format[] = '%s';
                }
            }
            $updated = $wpdb->update($table_name, $data, ['id' => $existing->id], $format, ['%d']);
            if ($updated !== false) {
                sc_update_profile_completed_status($existing->id);
                wp_send_json_success(['message' => 'اطلاعات شما با موفقیت به روز شد.']);
            }
            wp_send_json_error(['message' => 'خطا در بروزرسانی.']);
        }
    }

    $insert_format = [];
    foreach ($data as $key => $value) {
        if ($value === null) {
            $insert_format[] = '%s';
        } elseif (in_array($key, ['health_verified', 'info_verified', 'identity_verified', 'is_active', 'user_id'], true)) {
            $insert_format[] = '%d';
        } else {
            $insert_format[] = '%s';
        }
    }
    $inserted = $wpdb->insert($table_name, $data, $insert_format);
    if ($inserted !== false) {
        sc_update_profile_completed_status($wpdb->insert_id);
        wp_send_json_success(['message' => 'اطلاعات شما با موفقیت ثبت شد.']);
    }
    wp_send_json_error(['message' => 'خطا در ثبت اطلاعات. لطفاً دوباره تلاش کنید.']);
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

