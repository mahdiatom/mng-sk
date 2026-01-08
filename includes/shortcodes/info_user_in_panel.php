<?php 
/**
 * Shortcode: نمایش اطلاعات کاربر در پنل
 * [sc_user_info_panel]
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ثبت shortcode
 */






add_shortcode('sc_user_info_panel', 'sc_user_info_panel_shortcode');

function sc_user_info_panel_shortcode($atts) {
    // فقط برای کاربران لاگین شده
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
    
    // شروع خروجی HTML
    ob_start();
    ?>
    
    <div class="sc-user-info-panel" style="background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin: 20px 0; font-family: IRANYekanXFaNum, sans-serif;">
        <div style="display: flex; gap: 25px; flex-wrap: wrap;">
            <!-- بخش عکس و اطلاعات پایه -->
            <div style="flex: 0 0 auto; text-align: center;">
                <div style="width: 150px; height: 150px; border-radius: 50%; overflow: hidden; border: 4px solid #2271b1; margin: 0 auto 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                    <img src="<?php echo esc_url($profile_image); ?>" 
                         alt="<?php echo esc_attr($full_name); ?>" 
                         style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 600; color: #1a1a1a;">
                    <?php echo esc_html($full_name); ?>
                </h3>
                <?php if ($phone) : ?>
                    <p style="margin: 0; color: #666; font-size: 14px; direction: ltr; text-align: center;">
                        📞 <?php echo esc_html($phone); ?>
                    </p>
                <?php endif; ?>
                <?php if ($user_email) : ?>
                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                        ✉️ <?php echo esc_html($user_email); ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- بخش اطلاعات آماری -->
            <div style="flex: 1; min-width: 300px;">
                <h4 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 600; color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">
                    اطلاعات حساب کاربری
                </h4>
                
                <!-- بخش قابل مشاهده (4 کارت) -->
                <div class="sc-visible-section" style="display: block;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <!-- دوره‌های فعال -->
                        <div class="sc-info-card" style="background: linear-gradient(135deg, #e7f3ff 0%, #d0e7ff 100%); padding: 15px; border-radius: 8px; border-right: 4px solid #2271b1;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 24px;">📚</span>
                                <strong style="font-size: 14px; color: #666;">دوره‌های فعال</strong>
                            </div>
                            <div style="font-size: 28px; font-weight: bold; color: #2271b1;">
                                <?php echo esc_html($active_courses_count); ?>
                            </div>
                        </div>
                        
                        <!-- بدهکاری -->
                        <div class="sc-info-card" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 15px; border-radius: 8px; border-right: 4px solid #f0a000;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 24px;">💰</span>
                                <strong style="font-size: 14px; color: #666;">بدهکاری شما</strong>
                            </div>
                            <div style="font-size: 28px; font-weight: bold; color: #856404;">
                                <?php echo number_format($total_debt, 0, '.', ','); ?> تومان
                            </div>
                            <?php if ($debt_count > 0) : ?>
                                <div style="font-size: 12px; color: #856404; margin-top: 5px;">
                                    (<?php echo esc_html($debt_count); ?> صورت حساب)
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- رویدادهای ثبت‌نام شده -->
                        <div class="sc-info-card" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); padding: 15px; border-radius: 8px; border-right: 4px solid #00a32a;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 24px;">🎯</span>
                                <strong style="font-size: 14px; color: #666;">رویدادهای ثبت‌نام شده</strong>
                            </div>
                            <div style="font-size: 28px; font-weight: bold; color: #155724;">
                                <?php echo esc_html($event_registrations_count); ?>
                            </div>
                        </div>
                        
                        <!-- صورت حساب‌های پرداخت شده -->
                        <div class="sc-info-card" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); padding: 15px; border-radius: 8px; border-right: 4px solid #17a2b8;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 24px;">✅</span>
                                <strong style="font-size: 14px; color: #666;">پرداخت‌های موفق</strong>
                            </div>
                            <div style="font-size: 28px; font-weight: bold; color: #0c5460;">
                                <?php echo esc_html($paid_invoices_count); ?>
                            </div>
                            <?php if ($paid_invoices_total > 0) : ?>
                                <div style="font-size: 12px; color: #0c5460; margin-top: 5px;">
                                    مجموع: <?php echo number_format($paid_invoices_total, 0, '.', ','); ?> تومان
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- دکمه باز/بسته کردن -->
                <div style="text-align: center; margin: 20px 0;">
                    <button type="button" class="sc-toggle-button" onclick="scToggleUserPanel(this)" style="
                        background: #2271b1;
                        color: #fff;
                        border: none;
                        padding: 12px 30px;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        display: inline-flex;
                        align-items: center;
                        gap: 10px;
                        transition: all 0.3s ease;
                        box-shadow: 0 2px 8px rgba(34, 113, 177, 0.3);
                    " onmouseover="this.style.background='#135e96'" onmouseout="this.style.background='#2271b1'">
                        <span class="sc-toggle-text">نمایش بیشتر</span>
                        <span class="sc-toggle-arrow" style="font-size: 18px; transition: transform 0.3s ease;">▼</span>
                    </button>
                </div>
                
                <!-- بخش پنهان -->
                <div class="sc-hidden-section" style="display: none; overflow: hidden;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <!-- سن کاربر -->
                        <?php if ($user_age) : ?>
                            <div class="sc-info-card" style="background: linear-gradient(135deg, #f0e6ff 0%, #e6d5ff 100%); padding: 15px; border-radius: 8px; border-right: 4px solid #8b5cf6;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <span style="font-size: 24px;">🎂</span>
                                    <strong style="font-size: 14px; color: #666;">سن شما</strong>
                                </div>
                                <div style="font-size: 28px; font-weight: bold; color: #6b21a8;">
                                    <?php echo esc_html($user_age); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- سطح شما -->
                        <div class="sc-info-card" style="background: linear-gradient(135deg, #fff5e6 0%, #ffe8cc 100%); padding: 15px; border-radius: 8px; border-right: 4px solid #ff9800;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 24px;">⭐</span>
                                <strong style="font-size: 14px; color: #666;">سطح شما</strong>
                            </div>
                            <div style="font-size: 20px; font-weight: bold; color: #e65100;">
                                <?php echo esc_html($skill_level); ?>
                            </div>
                        </div>
                        
                        <!-- تعداد حضور و غیاب -->
                        <div class="sc-info-card" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 15px; border-radius: 8px; border-right: 4px solid #4caf50;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 24px;">📋</span>
                                <strong style="font-size: 14px; color: #666;">حضور و غیاب</strong>
                            </div>
                            <div style="display: flex; gap: 15px; align-items: center; justify-content: space-between;">
                                <div style="text-align: center; flex: 1;">
                                    <div style="font-size: 24px; font-weight: bold; color: #2e7d32;">
                                        <?php echo esc_html($present_count); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #2e7d32; margin-top: 3px;">
                                        حضور
                                    </div>
                                </div>
                                <div style="width: 1px; height: 30px; background: #c8e6c9;"></div>
                                <div style="text-align: center; flex: 1;">
                                    <div style="font-size: 24px; font-weight: bold; color: #d32f2f;">
                                        <?php echo esc_html($absent_count); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #d32f2f; margin-top: 3px;">
                                        غیاب
                                    </div>
                                </div>
                            </div>
                            <?php if ($total_attendances > 0) : ?>
                                <div style="font-size: 11px; color: #666; margin-top: 8px; text-align: center; padding-top: 8px; border-top: 1px solid #c8e6c9;">
                                    مجموع: <?php echo esc_html($total_attendances); ?> رکورد
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- نام کاربری -->
                        <div class="sc-info-card" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); padding: 15px; border-radius: 8px; border-right: 4px solid #9c27b0;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                <span style="font-size: 24px;">👤</span>
                                <strong style="font-size: 14px; color: #666;">نام کاربری</strong>
                            </div>
                            <div style="font-size: 18px; font-weight: bold; color: #6a1b9a; word-break: break-all; direction: ltr; text-align: center;">
                                <?php echo esc_html($user_login); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- اطلاعات تکمیلی -->
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <!-- تاریخ عضویت -->
                        <?php if ($membership_date) : ?>
                            <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f9f9f9; border-radius: 6px;">
                                <span style="font-size: 20px;">📅</span>
                                <div>
                                    <strong style="font-size: 13px; color: #666; display: block;">تاریخ عضویت:</strong>
                                    <span style="font-size: 14px; color: #333; font-weight: 600;"><?php echo esc_html($membership_date); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- وضعیت بیمه -->
                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f9f9f9; border-radius: 6px;">
                            <span style="font-size: 20px;">🛡️</span>
                            <div>
                                <strong style="font-size: 13px; color: #666; display: block;">وضعیت بیمه:</strong>
                                <span style="font-size: 14px; color: #333; font-weight: 600;">
                                    <?php echo esc_html($insurance_status); ?>
                                    <?php if ($insurance_expiry && $insurance_status !== 'ثبت نشده') : ?>
                                        <small style="display: block; color: #999; font-weight: normal; margin-top: 3px;">
                                            (انقضا: <?php echo esc_html($insurance_expiry); ?>)
                                        </small>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- وضعیت پروفایل -->
                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f9f9f9; border-radius: 6px;">
                            <span style="font-size: 20px;"><?php echo $profile_completed ? '✅' : '⚠️'; ?></span>
                            <div>
                                <strong style="font-size: 13px; color: #666; display: block;">وضعیت پروفایل:</strong>
                                <span style="font-size: 14px; color: <?php echo $profile_completed ? '#155724' : '#856404'; ?>; font-weight: 600;">
                                    <?php echo esc_html($profile_status); ?>
                                </span>
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
                                $invoice_item_name = 'سایر';
                            }
                            ?>
                            <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f9f9f9; border-radius: 6px;">
                                <span style="font-size: 20px;">💳</span>
                                <div style="flex: 1;">
                                    <strong style="font-size: 13px; color: #666; display: block; margin-bottom: 5px;">آخرین صورت حساب پرداخت شده:</strong>
                                    <div style="font-size: 14px; color: #333; font-weight: 600;">
                                        <div style="margin-bottom: 3px;">
                                            <span style="color: #2271b1; font-weight: bold;"><?php echo esc_html($invoice_item_name); ?></span>
                                        </div>
                                        <div style="margin-bottom: 3px; margin-top: 5px;">
                                           مبلغ: <span style="color: #2271b1;"><?php echo number_format(floatval($last_invoice->amount), 0, '.', ','); ?> تومان</span>
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
    
    <script>
    function scToggleUserPanel(button) {
        var hiddenSection = button.closest('.sc-user-info-panel').querySelector('.sc-hidden-section');
        var toggleText = button.querySelector('.sc-toggle-text');
        var toggleArrow = button.querySelector('.sc-toggle-arrow');
        
        if (hiddenSection.style.display === 'none' || hiddenSection.style.display === '') {
            // باز کردن
            hiddenSection.style.display = 'block';
            toggleText.textContent = 'نمایش کمتر';
            toggleArrow.style.transform = 'rotate(180deg)';
            toggleArrow.textContent = '▲';
            
            // انیمیشن باز شدن
            hiddenSection.style.maxHeight = '0';
            hiddenSection.style.opacity = '0';
            setTimeout(function() {
                hiddenSection.style.transition = 'max-height 0.5s ease, opacity 0.5s ease';
                hiddenSection.style.maxHeight = hiddenSection.scrollHeight + 'px';
                hiddenSection.style.opacity = '1';
            }, 10);
        } else {
            // بستن
            hiddenSection.style.transition = 'max-height 0.5s ease, opacity 0.5s ease';
            hiddenSection.style.maxHeight = '0';
            hiddenSection.style.opacity = '0';
            
            setTimeout(function() {
                hiddenSection.style.display = 'none';
                toggleText.textContent = 'نمایش بیشتر';
                toggleArrow.style.transform = 'rotate(0deg)';
                toggleArrow.textContent = '▼';
            }, 500);
        }
    }
    </script>
    <?php
    return ob_get_clean();
}