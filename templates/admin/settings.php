<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

// دریافت تب فعلی (باید قبل از پردازش فرم باشد)
$sc_settings_default_tab = (function_exists('sc_is_license_active') && !sc_is_license_active()) ? 'license' : 'penalty';
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $sc_settings_default_tab;

if (function_exists('sc_is_license_active') && !sc_is_license_active()) {
    if (!function_exists('sc_can_manage_license') || !sc_can_manage_license()) {
        wp_die(
            esc_html__('افزونه SportClub به‌دلیل غیرفعال بودن لایسنس در دسترس نیست. لطفاً با مدیر کل سایت تماس بگیرید.', 'sportclub-manager'),
            esc_html__('لایسنس غیرفعال', 'sportclub-manager'),
            ['response' => 403]
        );
    }
    if ($current_tab !== 'license') {
        wp_safe_redirect(admin_url('admin.php?page=sc_setting&tab=license'));
        exit;
    }
}

/**
 * فقط تب لایسنس — بدون بارگذاری تنظیمات SMS و سایر ماژول‌ها
 */
if ($current_tab === 'license' && function_exists('sc_is_license_active') && !sc_is_license_active()) {
    ?>
    <div class="wrap sc-settings-page-header sc-finance-page-header sc_setting_section">
        <h1 class="wp-heading-inline">تنظیمات باشگاه</h1>
        <hr class="wp-header-end">
        <p class="sc-settings-subtitle">پیکربندی جریمه، صورت‌حساب، پیامک، کیف پول و سایر امکانات باشگاه.</p>
    </div>
    <div class="wrap sc-settings-page-body sc-finance-page-body sc_setting_section">
        <nav class="nav-tab-wrapper sc-settings-nav-tabs">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc_setting&tab=license')); ?>"
               class="nav-tab nav-tab-active">لایسنس</a>
        </nav>
        <div class="tab-content sc-settings-tab-content">
            <?php include SC_TEMPLATES_ADMIN_DIR . 'settings-tab-license.php'; ?>
        </div>
    </div>
    <?php
    return;
}

// پردازش فرم (بدون لایسنس فعال ذخیره سایر تب‌ها مجاز نیست)
if (isset($_POST['sc_save_settings']) && check_admin_referer('sc_settings_nonce', 'sc_settings_nonce')
    && (!function_exists('sc_is_license_active') || sc_is_license_active())) {
    if ($current_tab === 'penalty') {
        $penalty_enabled = isset($_POST['penalty_enabled']) ? 1 : 0;
        $penalty_minutes = isset($_POST['penalty_minutes']) ? absint($_POST['penalty_minutes']) : 7;
        $penalty_amount_raw = isset($_POST['penalty_amount_raw']) && $_POST['penalty_amount_raw'] !== '' ? $_POST['penalty_amount_raw'] : (isset($_POST['penalty_amount']) ? $_POST['penalty_amount'] : '');
        $penalty_amount = $penalty_amount_raw !== '' ? floatval(str_replace(',', '', $penalty_amount_raw)) : 500;

        sc_update_setting('penalty_enabled', $penalty_enabled, 'penalty');
        sc_update_setting('penalty_minutes', $penalty_minutes, 'penalty');
        sc_update_setting('penalty_amount', $penalty_amount, 'penalty');
        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب جریمه ذخیره شد', null, ['tab' => 'penalty']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات جریمه با موفقیت ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'invoice') {
    $pro_create_invoice_player_team = isset($_POST['pro_create_invoice_player_team']) ? 1 : 0;
     sc_update_setting('pro_create_invoice_player_team' , $pro_create_invoice_player_team , 'invoice' );   
    $invoice_mode = isset($_POST['invoice_mode']) ? sanitize_text_field($_POST['invoice_mode']) : 'interval';
    $normalize_digits = static function ($value) {
        $value = is_scalar($value) ? (string) $value : '';
        if (function_exists('fa_to_en_digits')) {
            $value = fa_to_en_digits($value);
        }
        return trim($value);
    };

    sc_update_setting('invoice_mode', $invoice_mode, 'invoice');

    if ($invoice_mode === 'interval') {
        $invoice_interval_minutes = absint($_POST['invoice_interval_minutes']);
        sc_update_setting('invoice_interval_minutes', $invoice_interval_minutes, 'invoice');
    } elseif($invoice_mode === 'sessions_threshold'){
         $sessions_count_threshold = isset($_POST['sessions_count_threshold']) ? $_POST['sessions_count_threshold'] : 1;
         sc_update_setting('sessions_count_threshold' , $sessions_count_threshold , 'invoice' ); 
    }
    else {
        $day_raw = isset($_POST['invoice_day_of_month']) ? $normalize_digits(wp_unslash($_POST['invoice_day_of_month'])) : '';
        $hour_raw = isset($_POST['invoice_hour']) ? $normalize_digits(wp_unslash($_POST['invoice_hour'])) : '0';
        $minute_raw = isset($_POST['invoice_minute']) ? $normalize_digits(wp_unslash($_POST['invoice_minute'])) : '0';
        $day_val = $day_raw !== '' ? absint($day_raw) : 0;
        sc_update_setting('invoice_day_of_month', min(31, max(0, $day_val)), 'invoice');
        sc_update_setting('invoice_hour', min(23, max(0, absint($hour_raw))), 'invoice');
        sc_update_setting('invoice_minute', min(59, max(0, absint($minute_raw))), 'invoice');
    }

    if (function_exists('sc_log_activity')) {
        sc_log_activity('updated', 'settings', 0, 'تنظیمات تب صورتحساب ذخیره شد', null, ['tab' => 'invoice']);
    }
    echo '<div class="notice notice-success is-dismissible"><p>تنظیمات صورتحساب ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'sms') {
        // API Settings
        $sms_api_key = isset($_POST['sms_api_key']) ? sanitize_text_field($_POST['sms_api_key']) : '';
        $sms_sender = isset($_POST['sms_sender']) ? sanitize_text_field($_POST['sms_sender']) : '';
        $sms_admin_phone = isset($_POST['sms_admin_phone']) ? sanitize_text_field($_POST['sms_admin_phone']) : '';
        $sms_reminder_delay_minutes = isset($_POST['sms_reminder_delay_minutes']) ? absint($_POST['sms_reminder_delay_minutes']) : 4320;
        $sms_cost_per_message = isset($_POST['sms_cost_per_message']) ? floatval(str_replace(',', '', $_POST['sms_cost_per_message'])) : 200;

        sc_update_setting('sms_api_key', $sms_api_key, 'sms');
        sc_update_setting('sms_sender', $sms_sender, 'sms');
        sc_update_setting('sms_admin_phone', $sms_admin_phone, 'sms');
        sc_update_setting('sms_reminder_delay_minutes', $sms_reminder_delay_minutes, 'sms');
        sc_update_setting('sms_cost_per_message', $sms_cost_per_message, 'sms');

        // Master SMS switch
        $sms_master_enabled = isset($_POST['sms_master_enabled']) ? 1 : 0;
        sc_update_setting('sms_master_enabled', $sms_master_enabled, 'sms');

        // Invoice SMS Settings
        $sms_invoice_user_enabled = isset($_POST['sms_invoice_user_enabled']) ? 1 : 0;
        $sms_invoice_user_template = isset($_POST['sms_invoice_user_template']) ? wp_kses($_POST['sms_invoice_user_template'], array()) : '';
        $sms_invoice_user_pattern = isset($_POST['sms_invoice_user_pattern']) ? absint($_POST['sms_invoice_user_pattern']) : '';
        $sms_invoice_admin_enabled = isset($_POST['sms_invoice_admin_enabled']) ? 1 : 0;
        $sms_invoice_admin_template = isset($_POST['sms_invoice_admin_template']) ? wp_kses($_POST['sms_invoice_admin_template'], array()) : '';
        $sms_invoice_admin_pattern = isset($_POST['sms_invoice_admin_pattern']) ? absint($_POST['sms_invoice_admin_pattern']) : '';

        sc_update_setting('sms_invoice_user_enabled', $sms_invoice_user_enabled, 'sms');
        sc_update_setting('sms_invoice_user_template', $sms_invoice_user_template, 'sms');
        sc_update_setting('sms_invoice_user_pattern', $sms_invoice_user_pattern, 'sms');
        sc_update_setting('sms_invoice_admin_enabled', $sms_invoice_admin_enabled, 'sms');
        sc_update_setting('sms_invoice_admin_template', $sms_invoice_admin_template, 'sms');
        sc_update_setting('sms_invoice_admin_pattern', $sms_invoice_admin_pattern, 'sms');

        // Additional Invoice states (cancelled, onhold)
        $sms_invoice_cancelled_user_enabled = isset($_POST['sms_invoice_cancelled_user_enabled']) ? 1 : 0;
        $sms_invoice_cancelled_user_template = isset($_POST['sms_invoice_cancelled_user_template']) ? wp_kses($_POST['sms_invoice_cancelled_user_template'], array()) : '';
        $sms_invoice_cancelled_user_pattern = isset($_POST['sms_invoice_cancelled_user_pattern']) ? absint($_POST['sms_invoice_cancelled_user_pattern']) : '';
        $sms_invoice_cancelled_admin_enabled = isset($_POST['sms_invoice_cancelled_admin_enabled']) ? 1 : 0;
        $sms_invoice_cancelled_admin_template = isset($_POST['sms_invoice_cancelled_admin_template']) ? wp_kses($_POST['sms_invoice_cancelled_admin_template'], array()) : '';
        $sms_invoice_cancelled_admin_pattern = isset($_POST['sms_invoice_cancelled_admin_pattern']) ? absint($_POST['sms_invoice_cancelled_admin_pattern']) : '';
        $sms_invoice_onhold_user_enabled = isset($_POST['sms_invoice_onhold_user_enabled']) ? 1 : 0;
        $sms_invoice_onhold_user_template = isset($_POST['sms_invoice_onhold_user_template']) ? wp_kses($_POST['sms_invoice_onhold_user_template'], array()) : '';
        $sms_invoice_onhold_user_pattern = isset($_POST['sms_invoice_onhold_user_pattern']) ? absint($_POST['sms_invoice_onhold_user_pattern']) : '';
        $sms_invoice_onhold_admin_enabled = isset($_POST['sms_invoice_onhold_admin_enabled']) ? 1 : 0;
        $sms_invoice_onhold_admin_template = isset($_POST['sms_invoice_onhold_admin_template']) ? wp_kses($_POST['sms_invoice_onhold_admin_template'], array()) : '';
        $sms_invoice_onhold_admin_pattern = isset($_POST['sms_invoice_onhold_admin_pattern']) ? absint($_POST['sms_invoice_onhold_admin_pattern']) : '';

        sc_update_setting('sms_invoice_cancelled_user_enabled', $sms_invoice_cancelled_user_enabled, 'sms');
        sc_update_setting('sms_invoice_cancelled_user_template', $sms_invoice_cancelled_user_template, 'sms');
        sc_update_setting('sms_invoice_cancelled_user_pattern', $sms_invoice_cancelled_user_pattern, 'sms');
        sc_update_setting('sms_invoice_cancelled_admin_enabled', $sms_invoice_cancelled_admin_enabled, 'sms');
        sc_update_setting('sms_invoice_cancelled_admin_template', $sms_invoice_cancelled_admin_template, 'sms');
        sc_update_setting('sms_invoice_cancelled_admin_pattern', $sms_invoice_cancelled_admin_pattern, 'sms');
        sc_update_setting('sms_invoice_onhold_user_enabled', $sms_invoice_onhold_user_enabled, 'sms');
        sc_update_setting('sms_invoice_onhold_user_template', $sms_invoice_onhold_user_template, 'sms');
        sc_update_setting('sms_invoice_onhold_user_pattern', $sms_invoice_onhold_user_pattern, 'sms');
        sc_update_setting('sms_invoice_onhold_admin_enabled', $sms_invoice_onhold_admin_enabled, 'sms');
        sc_update_setting('sms_invoice_onhold_admin_template', $sms_invoice_onhold_admin_template, 'sms');
        sc_update_setting('sms_invoice_onhold_admin_pattern', $sms_invoice_onhold_admin_pattern, 'sms');

        // Invoice paid SMS
        $sms_invoice_paid_user_enabled = isset($_POST['sms_invoice_paid_user_enabled']) ? 1 : 0;
        $sms_invoice_paid_user_template = isset($_POST['sms_invoice_paid_user_template']) ? wp_kses($_POST['sms_invoice_paid_user_template'], array()) : '';
        $sms_invoice_paid_user_pattern = isset($_POST['sms_invoice_paid_user_pattern']) ? absint($_POST['sms_invoice_paid_user_pattern']) : '';
        $sms_invoice_paid_admin_enabled = isset($_POST['sms_invoice_paid_admin_enabled']) ? 1 : 0;
        $sms_invoice_paid_admin_template = isset($_POST['sms_invoice_paid_admin_template']) ? wp_kses($_POST['sms_invoice_paid_admin_template'], array()) : '';
        $sms_invoice_paid_admin_pattern = isset($_POST['sms_invoice_paid_admin_pattern']) ? absint($_POST['sms_invoice_paid_admin_pattern']) : '';

        sc_update_setting('sms_invoice_paid_user_enabled', $sms_invoice_paid_user_enabled, 'sms');
        sc_update_setting('sms_invoice_paid_user_template', $sms_invoice_paid_user_template, 'sms');
        sc_update_setting('sms_invoice_paid_user_pattern', $sms_invoice_paid_user_pattern, 'sms');
        sc_update_setting('sms_invoice_paid_admin_enabled', $sms_invoice_paid_admin_enabled, 'sms');
        sc_update_setting('sms_invoice_paid_admin_template', $sms_invoice_paid_admin_template, 'sms');
        sc_update_setting('sms_invoice_paid_admin_pattern', $sms_invoice_paid_admin_pattern, 'sms');

        // WooCommerce Product Order SMS (پیامک محصول)
        $sms_wc_order_completed_user_enabled = isset($_POST['sms_wc_order_completed_user_enabled']) ? 1 : 0;
        $sms_wc_order_completed_user_template = isset($_POST['sms_wc_order_completed_user_template']) ? wp_kses($_POST['sms_wc_order_completed_user_template'], array()) : '';
        $sms_wc_order_completed_user_pattern = isset($_POST['sms_wc_order_completed_user_pattern']) ? absint($_POST['sms_wc_order_completed_user_pattern']) : '';
        $sms_wc_order_completed_admin_enabled = isset($_POST['sms_wc_order_completed_admin_enabled']) ? 1 : 0;
        $sms_wc_order_completed_admin_template = isset($_POST['sms_wc_order_completed_admin_template']) ? wp_kses($_POST['sms_wc_order_completed_admin_template'], array()) : '';
        $sms_wc_order_completed_admin_pattern = isset($_POST['sms_wc_order_completed_admin_pattern']) ? absint($_POST['sms_wc_order_completed_admin_pattern']) : '';

        $sms_wc_order_cancelled_user_enabled = isset($_POST['sms_wc_order_cancelled_user_enabled']) ? 1 : 0;
        $sms_wc_order_cancelled_user_template = isset($_POST['sms_wc_order_cancelled_user_template']) ? wp_kses($_POST['sms_wc_order_cancelled_user_template'], array()) : '';
        $sms_wc_order_cancelled_user_pattern = isset($_POST['sms_wc_order_cancelled_user_pattern']) ? absint($_POST['sms_wc_order_cancelled_user_pattern']) : '';
        $sms_wc_order_cancelled_admin_enabled = isset($_POST['sms_wc_order_cancelled_admin_enabled']) ? 1 : 0;
        $sms_wc_order_cancelled_admin_template = isset($_POST['sms_wc_order_cancelled_admin_template']) ? wp_kses($_POST['sms_wc_order_cancelled_admin_template'], array()) : '';
        $sms_wc_order_cancelled_admin_pattern = isset($_POST['sms_wc_order_cancelled_admin_pattern']) ? absint($_POST['sms_wc_order_cancelled_admin_pattern']) : '';

        $sms_wc_order_onhold_user_enabled = isset($_POST['sms_wc_order_onhold_user_enabled']) ? 1 : 0;
        $sms_wc_order_onhold_user_template = isset($_POST['sms_wc_order_onhold_user_template']) ? wp_kses($_POST['sms_wc_order_onhold_user_template'], array()) : '';
        $sms_wc_order_onhold_user_pattern = isset($_POST['sms_wc_order_onhold_user_pattern']) ? absint($_POST['sms_wc_order_onhold_user_pattern']) : '';
        $sms_wc_order_onhold_admin_enabled = isset($_POST['sms_wc_order_onhold_admin_enabled']) ? 1 : 0;
        $sms_wc_order_onhold_admin_template = isset($_POST['sms_wc_order_onhold_admin_template']) ? wp_kses($_POST['sms_wc_order_onhold_admin_template'], array()) : '';
        $sms_wc_order_onhold_admin_pattern = isset($_POST['sms_wc_order_onhold_admin_pattern']) ? absint($_POST['sms_wc_order_onhold_admin_pattern']) : '';

        $sms_wc_order_failed_user_enabled = isset($_POST['sms_wc_order_failed_user_enabled']) ? 1 : 0;
        $sms_wc_order_failed_user_template = isset($_POST['sms_wc_order_failed_user_template']) ? wp_kses($_POST['sms_wc_order_failed_user_template'], array()) : '';
        $sms_wc_order_failed_user_pattern = isset($_POST['sms_wc_order_failed_user_pattern']) ? absint($_POST['sms_wc_order_failed_user_pattern']) : '';
        $sms_wc_order_failed_admin_enabled = isset($_POST['sms_wc_order_failed_admin_enabled']) ? 1 : 0;
        $sms_wc_order_failed_admin_template = isset($_POST['sms_wc_order_failed_admin_template']) ? wp_kses($_POST['sms_wc_order_failed_admin_template'], array()) : '';
        $sms_wc_order_failed_admin_pattern = isset($_POST['sms_wc_order_failed_admin_pattern']) ? absint($_POST['sms_wc_order_failed_admin_pattern']) : '';

        sc_update_setting('sms_wc_order_completed_user_enabled', $sms_wc_order_completed_user_enabled, 'sms');
        sc_update_setting('sms_wc_order_completed_user_template', $sms_wc_order_completed_user_template, 'sms');
        sc_update_setting('sms_wc_order_completed_user_pattern', $sms_wc_order_completed_user_pattern, 'sms');
        sc_update_setting('sms_wc_order_completed_admin_enabled', $sms_wc_order_completed_admin_enabled, 'sms');
        sc_update_setting('sms_wc_order_completed_admin_template', $sms_wc_order_completed_admin_template, 'sms');
        sc_update_setting('sms_wc_order_completed_admin_pattern', $sms_wc_order_completed_admin_pattern, 'sms');

        sc_update_setting('sms_wc_order_cancelled_user_enabled', $sms_wc_order_cancelled_user_enabled, 'sms');
        sc_update_setting('sms_wc_order_cancelled_user_template', $sms_wc_order_cancelled_user_template, 'sms');
        sc_update_setting('sms_wc_order_cancelled_user_pattern', $sms_wc_order_cancelled_user_pattern, 'sms');
        sc_update_setting('sms_wc_order_cancelled_admin_enabled', $sms_wc_order_cancelled_admin_enabled, 'sms');
        sc_update_setting('sms_wc_order_cancelled_admin_template', $sms_wc_order_cancelled_admin_template, 'sms');
        sc_update_setting('sms_wc_order_cancelled_admin_pattern', $sms_wc_order_cancelled_admin_pattern, 'sms');

        sc_update_setting('sms_wc_order_onhold_user_enabled', $sms_wc_order_onhold_user_enabled, 'sms');
        sc_update_setting('sms_wc_order_onhold_user_template', $sms_wc_order_onhold_user_template, 'sms');
        sc_update_setting('sms_wc_order_onhold_user_pattern', $sms_wc_order_onhold_user_pattern, 'sms');
        sc_update_setting('sms_wc_order_onhold_admin_enabled', $sms_wc_order_onhold_admin_enabled, 'sms');
        sc_update_setting('sms_wc_order_onhold_admin_template', $sms_wc_order_onhold_admin_template, 'sms');
        sc_update_setting('sms_wc_order_onhold_admin_pattern', $sms_wc_order_onhold_admin_pattern, 'sms');

        sc_update_setting('sms_wc_order_failed_user_enabled', $sms_wc_order_failed_user_enabled, 'sms');
        sc_update_setting('sms_wc_order_failed_user_template', $sms_wc_order_failed_user_template, 'sms');
        sc_update_setting('sms_wc_order_failed_user_pattern', $sms_wc_order_failed_user_pattern, 'sms');
        sc_update_setting('sms_wc_order_failed_admin_enabled', $sms_wc_order_failed_admin_enabled, 'sms');
        sc_update_setting('sms_wc_order_failed_admin_template', $sms_wc_order_failed_admin_template, 'sms');
        sc_update_setting('sms_wc_order_failed_admin_pattern', $sms_wc_order_failed_admin_pattern, 'sms');

        // WooCommerce Virtual/Downloadable Product Order SMS
        $wc_virtual_sms_statuses = ['completed', 'cancelled', 'onhold', 'failed'];
        foreach ($wc_virtual_sms_statuses as $wc_virtual_status) {
            foreach (['user', 'admin'] as $wc_virtual_recipient) {
                $wc_virtual_prefix = 'sms_wc_order_virtual_' . $wc_virtual_status . '_' . $wc_virtual_recipient;
                $wc_virtual_enabled = isset($_POST[$wc_virtual_prefix . '_enabled']) ? 1 : 0;
                $wc_virtual_template = isset($_POST[$wc_virtual_prefix . '_template']) ? wp_kses($_POST[$wc_virtual_prefix . '_template'], array()) : '';
                $wc_virtual_pattern = isset($_POST[$wc_virtual_prefix . '_pattern']) ? absint($_POST[$wc_virtual_prefix . '_pattern']) : '';
                sc_update_setting($wc_virtual_prefix . '_enabled', $wc_virtual_enabled, 'sms');
                sc_update_setting($wc_virtual_prefix . '_template', $wc_virtual_template, 'sms');
                sc_update_setting($wc_virtual_prefix . '_pattern', $wc_virtual_pattern, 'sms');
            }
        }

        // Enrollment SMS Settings
        $sms_enrollment_user_enabled = isset($_POST['sms_enrollment_user_enabled']) ? 1 : 0;
        $sms_enrollment_user_template = isset($_POST['sms_enrollment_user_template']) ? wp_kses($_POST['sms_enrollment_user_template'], array()) : '';
        $sms_enrollment_user_pattern = isset($_POST['sms_enrollment_user_pattern']) ? absint($_POST['sms_enrollment_user_pattern']) : '';
        $sms_enrollment_admin_enabled = isset($_POST['sms_enrollment_admin_enabled']) ? 1 : 0;
        $sms_enrollment_admin_template = isset($_POST['sms_enrollment_admin_template']) ? wp_kses($_POST['sms_enrollment_admin_template'], array()) : '';
        $sms_enrollment_admin_pattern = isset($_POST['sms_enrollment_admin_pattern']) ? absint($_POST['sms_enrollment_admin_pattern']) : '';

        sc_update_setting('sms_enrollment_user_enabled', $sms_enrollment_user_enabled, 'sms');
        sc_update_setting('sms_enrollment_user_template', $sms_enrollment_user_template, 'sms');
        sc_update_setting('sms_enrollment_user_pattern', $sms_enrollment_user_pattern, 'sms');
        sc_update_setting('sms_enrollment_admin_enabled', $sms_enrollment_admin_enabled, 'sms');
        sc_update_setting('sms_enrollment_admin_template', $sms_enrollment_admin_template, 'sms');
        sc_update_setting('sms_enrollment_admin_pattern', $sms_enrollment_admin_pattern, 'sms');

        // Course capacity waitlist (notify when spot opens)
        $sms_course_capacity_waitlist_user_enabled = isset($_POST['sms_course_capacity_waitlist_user_enabled']) ? 1 : 0;
        $sms_course_capacity_waitlist_user_template = isset($_POST['sms_course_capacity_waitlist_user_template']) ? wp_kses($_POST['sms_course_capacity_waitlist_user_template'], array()) : '';
        $sms_course_capacity_waitlist_user_pattern = isset($_POST['sms_course_capacity_waitlist_user_pattern']) ? absint($_POST['sms_course_capacity_waitlist_user_pattern']) : '';
        sc_update_setting('sms_course_capacity_waitlist_user_enabled', $sms_course_capacity_waitlist_user_enabled, 'sms');
        sc_update_setting('sms_course_capacity_waitlist_user_template', $sms_course_capacity_waitlist_user_template, 'sms');
        sc_update_setting('sms_course_capacity_waitlist_user_pattern', $sms_course_capacity_waitlist_user_pattern, 'sms');

        // Reminder SMS Settings
        $sms_reminder_user_enabled = isset($_POST['sms_reminder_user_enabled']) ? 1 : 0;
        $sms_reminder_user_template = isset($_POST['sms_reminder_user_template']) ? wp_kses($_POST['sms_reminder_user_template'], array()) : '';
        $sms_reminder_user_pattern = isset($_POST['sms_reminder_user_pattern']) ? absint($_POST['sms_reminder_user_pattern']) : '';
        $sms_reminder_admin_enabled = isset($_POST['sms_reminder_admin_enabled']) ? 1 : 0;
        $sms_reminder_admin_template = isset($_POST['sms_reminder_admin_template']) ? wp_kses($_POST['sms_reminder_admin_template'], array()) : '';
        $sms_reminder_admin_pattern = isset($_POST['sms_reminder_admin_pattern']) ? absint($_POST['sms_reminder_admin_pattern']) : '';

        sc_update_setting('sms_reminder_user_enabled', $sms_reminder_user_enabled, 'sms');
        sc_update_setting('sms_reminder_user_template', $sms_reminder_user_template, 'sms');
        sc_update_setting('sms_reminder_user_pattern', $sms_reminder_user_pattern, 'sms');
        sc_update_setting('sms_reminder_admin_enabled', $sms_reminder_admin_enabled, 'sms');
        sc_update_setting('sms_reminder_admin_template', $sms_reminder_admin_template, 'sms');
        sc_update_setting('sms_reminder_admin_pattern', $sms_reminder_admin_pattern, 'sms');

        // Absence SMS Settings
        $sms_absence_user_enabled = isset($_POST['sms_absence_user_enabled']) ? 1 : 0;
        $sms_absence_user_template = isset($_POST['sms_absence_user_template']) ? wp_kses($_POST['sms_absence_user_template'], array()) : '';
        $sms_absence_user_pattern = isset($_POST['sms_absence_user_pattern']) ? absint($_POST['sms_absence_user_pattern']) : '';
        $sms_absence_admin_enabled = isset($_POST['sms_absence_admin_enabled']) ? 1 : 0;
        $sms_absence_admin_template = isset($_POST['sms_absence_admin_template']) ? wp_kses($_POST['sms_absence_admin_template'], array()) : '';
        $sms_absence_admin_pattern = isset($_POST['sms_absence_admin_pattern']) ? absint($_POST['sms_absence_admin_pattern']) : '';
        $sms_absence_alert_user_enabled = isset($_POST['sms_absence_alert_user_enabled']) ? 1 : 0;
        $sms_absence_alert_user_template = isset($_POST['sms_absence_alert_user_template']) ? wp_kses($_POST['sms_absence_alert_user_template'], array()) : '';
        $sms_absence_alert_user_pattern = isset($_POST['sms_absence_alert_user_pattern']) ? absint($_POST['sms_absence_alert_user_pattern']) : '';
        $sms_absence_alert_admin_enabled = isset($_POST['sms_absence_alert_admin_enabled']) ? 1 : 0;
        $sms_absence_alert_admin_template = isset($_POST['sms_absence_alert_admin_template']) ? wp_kses($_POST['sms_absence_alert_admin_template'], array()) : '';
        $sms_absence_alert_admin_pattern = isset($_POST['sms_absence_alert_admin_pattern']) ? absint($_POST['sms_absence_alert_admin_pattern']) : '';

        sc_update_setting('sms_absence_user_enabled', $sms_absence_user_enabled, 'sms');
        sc_update_setting('sms_absence_user_template', $sms_absence_user_template, 'sms');
        sc_update_setting('sms_absence_user_pattern', $sms_absence_user_pattern, 'sms');
        sc_update_setting('sms_absence_admin_enabled', $sms_absence_admin_enabled, 'sms');
        sc_update_setting('sms_absence_admin_template', $sms_absence_admin_template, 'sms');
        sc_update_setting('sms_absence_admin_pattern', $sms_absence_admin_pattern, 'sms');
        sc_update_setting('sms_absence_alert_user_enabled', $sms_absence_alert_user_enabled, 'sms');
        sc_update_setting('sms_absence_alert_user_template', $sms_absence_alert_user_template, 'sms');
        sc_update_setting('sms_absence_alert_user_pattern', $sms_absence_alert_user_pattern, 'sms');
        sc_update_setting('sms_absence_alert_admin_enabled', $sms_absence_alert_admin_enabled, 'sms');
        sc_update_setting('sms_absence_alert_admin_template', $sms_absence_alert_admin_template, 'sms');
        sc_update_setting('sms_absence_alert_admin_pattern', $sms_absence_alert_admin_pattern, 'sms');

        // Birthday SMS Settings
        $sms_birthday_user_enabled = isset($_POST['sms_birthday_user_enabled']) ? 1 : 0;
        $sms_birthday_user_template = isset($_POST['sms_birthday_user_template']) ? wp_kses($_POST['sms_birthday_user_template'], array()) : '';
        $sms_birthday_user_pattern = isset($_POST['sms_birthday_user_pattern']) ? absint($_POST['sms_birthday_user_pattern']) : '';
        sc_update_setting('sms_birthday_user_enabled', $sms_birthday_user_enabled, 'sms');
        sc_update_setting('sms_birthday_user_template', $sms_birthday_user_template, 'sms');
        sc_update_setting('sms_birthday_user_pattern', $sms_birthday_user_pattern, 'sms');

        // Insurance expiry SMS Settings
        $sms_insurance_expiry_user_enabled = isset($_POST['sms_insurance_expiry_user_enabled']) ? 1 : 0;
        $sms_insurance_expiry_user_template = isset($_POST['sms_insurance_expiry_user_template']) ? wp_kses($_POST['sms_insurance_expiry_user_template'], array()) : '';
        $sms_insurance_expiry_user_pattern = isset($_POST['sms_insurance_expiry_user_pattern']) ? absint($_POST['sms_insurance_expiry_user_pattern']) : '';
        sc_update_setting('sms_insurance_expiry_user_enabled', $sms_insurance_expiry_user_enabled, 'sms');
        sc_update_setting('sms_insurance_expiry_user_template', $sms_insurance_expiry_user_template, 'sms');
        sc_update_setting('sms_insurance_expiry_user_pattern', $sms_insurance_expiry_user_pattern, 'sms');

        // Identity verification approved SMS settings
        $sms_identity_verified_user_enabled = isset($_POST['sms_identity_verified_user_enabled']) ? 1 : 0;
        $sms_identity_verified_user_template = isset($_POST['sms_identity_verified_user_template']) ? wp_kses($_POST['sms_identity_verified_user_template'], array()) : '';
        $sms_identity_verified_user_pattern = isset($_POST['sms_identity_verified_user_pattern']) ? absint($_POST['sms_identity_verified_user_pattern']) : '';
        sc_update_setting('sms_identity_verified_user_enabled', $sms_identity_verified_user_enabled, 'sms');
        sc_update_setting('sms_identity_verified_user_template', $sms_identity_verified_user_template, 'sms');
        sc_update_setting('sms_identity_verified_user_pattern', $sms_identity_verified_user_pattern, 'sms');

        // Identity rejection SMS settings
        $sms_identity_rejected_user_enabled = isset($_POST['sms_identity_rejected_user_enabled']) ? 1 : 0;
        $sms_identity_rejected_user_template = isset($_POST['sms_identity_rejected_user_template']) ? wp_kses($_POST['sms_identity_rejected_user_template'], array()) : '';
        $sms_identity_rejected_user_pattern = isset($_POST['sms_identity_rejected_user_pattern']) ? absint($_POST['sms_identity_rejected_user_pattern']) : '';
        sc_update_setting('sms_identity_rejected_user_enabled', $sms_identity_rejected_user_enabled, 'sms');
        sc_update_setting('sms_identity_rejected_user_template', $sms_identity_rejected_user_template, 'sms');
        sc_update_setting('sms_identity_rejected_user_pattern', $sms_identity_rejected_user_pattern, 'sms');

        // Certificate issued SMS settings
        $sms_certificate_user_enabled = isset($_POST['sms_certificate_user_enabled']) ? 1 : 0;
        $sms_certificate_user_template = isset($_POST['sms_certificate_user_template']) ? wp_kses($_POST['sms_certificate_user_template'], array()) : '';
        $sms_certificate_user_pattern = isset($_POST['sms_certificate_user_pattern']) ? absint($_POST['sms_certificate_user_pattern']) : '';
        sc_update_setting('sms_certificate_user_enabled', $sms_certificate_user_enabled, 'sms');
        sc_update_setting('sms_certificate_user_template', $sms_certificate_user_template, 'sms');
        sc_update_setting('sms_certificate_user_pattern', $sms_certificate_user_pattern, 'sms');

        $sms_survey_submission_user_enabled = isset($_POST['sms_survey_submission_user_enabled']) ? 1 : 0;
        $sms_survey_submission_user_template = isset($_POST['sms_survey_submission_user_template']) ? wp_kses($_POST['sms_survey_submission_user_template'], array()) : '';
        $sms_survey_submission_user_pattern = isset($_POST['sms_survey_submission_user_pattern']) ? absint($_POST['sms_survey_submission_user_pattern']) : '';
        $sms_survey_submission_admin_enabled = isset($_POST['sms_survey_submission_admin_enabled']) ? 1 : 0;
        $sms_survey_submission_admin_template = isset($_POST['sms_survey_submission_admin_template']) ? wp_kses($_POST['sms_survey_submission_admin_template'], array()) : '';
        $sms_survey_submission_admin_pattern = isset($_POST['sms_survey_submission_admin_pattern']) ? absint($_POST['sms_survey_submission_admin_pattern']) : '';
        sc_update_setting('sms_survey_submission_user_enabled', $sms_survey_submission_user_enabled, 'sms');
        sc_update_setting('sms_survey_submission_user_template', $sms_survey_submission_user_template, 'sms');
        sc_update_setting('sms_survey_submission_user_pattern', $sms_survey_submission_user_pattern, 'sms');
        sc_update_setting('sms_survey_submission_admin_enabled', $sms_survey_submission_admin_enabled, 'sms');
        sc_update_setting('sms_survey_submission_admin_template', $sms_survey_submission_admin_template, 'sms');
        sc_update_setting('sms_survey_submission_admin_pattern', $sms_survey_submission_admin_pattern, 'sms');

        // Wallet SMS Settings
        $sms_wallet_low_balance_user_enabled = isset($_POST['sms_wallet_low_balance_user_enabled']) ? 1 : 0;
        $sms_wallet_low_balance_user_template = isset($_POST['sms_wallet_low_balance_user_template']) ? wp_kses($_POST['sms_wallet_low_balance_user_template'], array()) : '';
        $sms_wallet_low_balance_user_pattern = isset($_POST['sms_wallet_low_balance_user_pattern']) ? absint($_POST['sms_wallet_low_balance_user_pattern']) : '';
        
        $sms_wallet_negative_balance_user_enabled = isset($_POST['sms_wallet_negative_balance_user_enabled']) ? 1 : 0;
        $sms_wallet_negative_balance_user_template = isset($_POST['sms_wallet_negative_balance_user_template']) ? wp_kses($_POST['sms_wallet_negative_balance_user_template'], array()) : '';
        $sms_wallet_negative_balance_user_pattern = isset($_POST['sms_wallet_negative_balance_user_pattern']) ? absint($_POST['sms_wallet_negative_balance_user_pattern']) : '';
        
        $sms_wallet_charge_success_user_enabled = isset($_POST['sms_wallet_charge_success_user_enabled']) ? 1 : 0;
        $sms_wallet_charge_success_user_template = isset($_POST['sms_wallet_charge_success_user_template']) ? wp_kses($_POST['sms_wallet_charge_success_user_template'], array()) : '';
        $sms_wallet_charge_success_user_pattern = isset($_POST['sms_wallet_charge_success_user_pattern']) ? absint($_POST['sms_wallet_charge_success_user_pattern']) : '';
        
        $sms_wallet_payment_user_enabled = isset($_POST['sms_wallet_payment_user_enabled']) ? 1 : 0;
        $sms_wallet_payment_user_template = isset($_POST['sms_wallet_payment_user_template']) ? wp_kses($_POST['sms_wallet_payment_user_template'], array()) : '';
        $sms_wallet_payment_user_pattern = isset($_POST['sms_wallet_payment_user_pattern']) ? absint($_POST['sms_wallet_payment_user_pattern']) : '';

        sc_update_setting('sms_wallet_low_balance_user_enabled', $sms_wallet_low_balance_user_enabled, 'sms');
        sc_update_setting('sms_wallet_low_balance_user_template', $sms_wallet_low_balance_user_template, 'sms');
        sc_update_setting('sms_wallet_low_balance_user_pattern', $sms_wallet_low_balance_user_pattern, 'sms');
        
        sc_update_setting('sms_wallet_negative_balance_user_enabled', $sms_wallet_negative_balance_user_enabled, 'sms');
        sc_update_setting('sms_wallet_negative_balance_user_template', $sms_wallet_negative_balance_user_template, 'sms');
        sc_update_setting('sms_wallet_negative_balance_user_pattern', $sms_wallet_negative_balance_user_pattern, 'sms');
        
        sc_update_setting('sms_wallet_charge_success_user_enabled', $sms_wallet_charge_success_user_enabled, 'sms');
        sc_update_setting('sms_wallet_charge_success_user_template', $sms_wallet_charge_success_user_template, 'sms');
        sc_update_setting('sms_wallet_charge_success_user_pattern', $sms_wallet_charge_success_user_pattern, 'sms');
        
        sc_update_setting('sms_wallet_payment_user_enabled', $sms_wallet_payment_user_enabled, 'sms');
        sc_update_setting('sms_wallet_payment_user_template', $sms_wallet_payment_user_template, 'sms');
        sc_update_setting('sms_wallet_payment_user_pattern', $sms_wallet_payment_user_pattern, 'sms');

        // Support ticket SMS
        $sms_ticket_new_recipient_enabled = isset($_POST['sms_ticket_new_recipient_enabled']) ? 1 : 0;
        $sms_ticket_new_recipient_template = isset($_POST['sms_ticket_new_recipient_template']) ? wp_kses_post($_POST['sms_ticket_new_recipient_template']) : '';
        $sms_ticket_reply_enabled = isset($_POST['sms_ticket_reply_enabled']) ? 1 : 0;
        $sms_ticket_reply_template = isset($_POST['sms_ticket_reply_template']) ? wp_kses_post($_POST['sms_ticket_reply_template']) : '';
        sc_update_setting('sms_ticket_new_recipient_enabled', $sms_ticket_new_recipient_enabled, 'sms');
        sc_update_setting('sms_ticket_new_recipient_template', $sms_ticket_new_recipient_template, 'sms');
        sc_update_setting('sms_ticket_reply_enabled', $sms_ticket_reply_enabled, 'sms');
        sc_update_setting('sms_ticket_reply_template', $sms_ticket_reply_template, 'sms');

        // Private classes cancellation SMS settings (stored in classes group)
        $private_class_sms_user_cancel_to_coach_enabled = isset($_POST['private_class_sms_user_cancel_to_coach_enabled']) ? 1 : 0;
        $private_class_sms_user_cancel_to_admin_enabled = isset($_POST['private_class_sms_user_cancel_to_admin_enabled']) ? 1 : 0;
        $private_class_sms_coach_cancel_to_user_enabled = isset($_POST['private_class_sms_coach_cancel_to_user_enabled']) ? 1 : 0;
        $private_class_sms_user_cancel_to_coach_template = isset($_POST['private_class_sms_user_cancel_to_coach_template']) ? wp_kses($_POST['private_class_sms_user_cancel_to_coach_template'], array()) : '';
        $private_class_sms_user_cancel_to_coach_pattern = isset($_POST['private_class_sms_user_cancel_to_coach_pattern']) ? absint($_POST['private_class_sms_user_cancel_to_coach_pattern']) : 0;
        $private_class_sms_user_cancel_to_admin_template = isset($_POST['private_class_sms_user_cancel_to_admin_template']) ? wp_kses($_POST['private_class_sms_user_cancel_to_admin_template'], array()) : '';
        $private_class_sms_user_cancel_to_admin_pattern = isset($_POST['private_class_sms_user_cancel_to_admin_pattern']) ? absint($_POST['private_class_sms_user_cancel_to_admin_pattern']) : 0;
        $private_class_sms_coach_cancel_to_user_template = isset($_POST['private_class_sms_coach_cancel_to_user_template']) ? wp_kses($_POST['private_class_sms_coach_cancel_to_user_template'], array()) : '';
        $private_class_sms_coach_cancel_to_user_pattern = isset($_POST['private_class_sms_coach_cancel_to_user_pattern']) ? absint($_POST['private_class_sms_coach_cancel_to_user_pattern']) : 0;

        sc_update_setting('private_class_sms_user_cancel_to_coach_enabled', (string) $private_class_sms_user_cancel_to_coach_enabled, 'classes');
        sc_update_setting('private_class_sms_user_cancel_to_admin_enabled', (string) $private_class_sms_user_cancel_to_admin_enabled, 'classes');
        sc_update_setting('private_class_sms_coach_cancel_to_user_enabled', (string) $private_class_sms_coach_cancel_to_user_enabled, 'classes');
        sc_update_setting('private_class_sms_user_cancel_to_coach_template', $private_class_sms_user_cancel_to_coach_template, 'classes');
        sc_update_setting('private_class_sms_user_cancel_to_coach_pattern', (string) $private_class_sms_user_cancel_to_coach_pattern, 'classes');
        sc_update_setting('private_class_sms_user_cancel_to_admin_template', $private_class_sms_user_cancel_to_admin_template, 'classes');
        sc_update_setting('private_class_sms_user_cancel_to_admin_pattern', (string) $private_class_sms_user_cancel_to_admin_pattern, 'classes');
        sc_update_setting('private_class_sms_coach_cancel_to_user_template', $private_class_sms_coach_cancel_to_user_template, 'classes');
        sc_update_setting('private_class_sms_coach_cancel_to_user_pattern', (string) $private_class_sms_coach_cancel_to_user_pattern, 'classes');

        $private_class_sms_request_to_admin_enabled = isset($_POST['private_class_sms_request_to_admin_enabled']) ? 1 : 0;
        $private_class_sms_approved_to_user_enabled = isset($_POST['private_class_sms_approved_to_user_enabled']) ? 1 : 0;
        $private_class_sms_rejected_to_user_enabled = isset($_POST['private_class_sms_rejected_to_user_enabled']) ? 1 : 0;
        $private_class_sms_activated_to_user_enabled = isset($_POST['private_class_sms_activated_to_user_enabled']) ? 1 : 0;
        $private_class_sms_request_to_admin_template = isset($_POST['private_class_sms_request_to_admin_template']) ? wp_kses($_POST['private_class_sms_request_to_admin_template'], array()) : '';
        $private_class_sms_approved_to_user_template = isset($_POST['private_class_sms_approved_to_user_template']) ? wp_kses($_POST['private_class_sms_approved_to_user_template'], array()) : '';
        $private_class_sms_rejected_to_user_template = isset($_POST['private_class_sms_rejected_to_user_template']) ? wp_kses($_POST['private_class_sms_rejected_to_user_template'], array()) : '';
        $private_class_sms_activated_to_user_template = isset($_POST['private_class_sms_activated_to_user_template']) ? wp_kses($_POST['private_class_sms_activated_to_user_template'], array()) : '';
        $private_class_sms_request_to_admin_pattern = isset($_POST['private_class_sms_request_to_admin_pattern']) ? absint($_POST['private_class_sms_request_to_admin_pattern']) : 0;
        $private_class_sms_approved_to_user_pattern = isset($_POST['private_class_sms_approved_to_user_pattern']) ? absint($_POST['private_class_sms_approved_to_user_pattern']) : 0;
        $private_class_sms_rejected_to_user_pattern = isset($_POST['private_class_sms_rejected_to_user_pattern']) ? absint($_POST['private_class_sms_rejected_to_user_pattern']) : 0;
        $private_class_sms_activated_to_user_pattern = isset($_POST['private_class_sms_activated_to_user_pattern']) ? absint($_POST['private_class_sms_activated_to_user_pattern']) : 0;

        sc_update_setting('private_class_sms_request_to_admin_enabled', (string) $private_class_sms_request_to_admin_enabled, 'classes');
        sc_update_setting('private_class_sms_approved_to_user_enabled', (string) $private_class_sms_approved_to_user_enabled, 'classes');
        sc_update_setting('private_class_sms_rejected_to_user_enabled', (string) $private_class_sms_rejected_to_user_enabled, 'classes');
        sc_update_setting('private_class_sms_activated_to_user_enabled', (string) $private_class_sms_activated_to_user_enabled, 'classes');
        sc_update_setting('private_class_sms_request_to_admin_template', $private_class_sms_request_to_admin_template, 'classes');
        sc_update_setting('private_class_sms_approved_to_user_template', $private_class_sms_approved_to_user_template, 'classes');
        sc_update_setting('private_class_sms_rejected_to_user_template', $private_class_sms_rejected_to_user_template, 'classes');
        sc_update_setting('private_class_sms_activated_to_user_template', $private_class_sms_activated_to_user_template, 'classes');
        sc_update_setting('private_class_sms_request_to_admin_pattern', (string) $private_class_sms_request_to_admin_pattern, 'classes');
        sc_update_setting('private_class_sms_approved_to_user_pattern', (string) $private_class_sms_approved_to_user_pattern, 'classes');
        sc_update_setting('private_class_sms_rejected_to_user_pattern', (string) $private_class_sms_rejected_to_user_pattern, 'classes');
        sc_update_setting('private_class_sms_activated_to_user_pattern', (string) $private_class_sms_activated_to_user_pattern, 'classes');

        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب پیامک ذخیره شد', null, ['tab' => 'sms']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات پیامک با موفقیت ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'wallet') {
        $wallet_enabled = isset($_POST['wallet_enabled']) ? 1 : 0;
        $raw_min = isset($_POST['wallet_min_charge_raw']) && $_POST['wallet_min_charge_raw'] !== '' ? str_replace(',', '', $_POST['wallet_min_charge_raw']) : (isset($_POST['wallet_min_charge']) ? $_POST['wallet_min_charge'] : '');
        $wallet_min_charge = $raw_min !== '' ? floatval($raw_min) : 10000;
        $raw_max = isset($_POST['wallet_max_charge_raw']) && $_POST['wallet_max_charge_raw'] !== '' ? str_replace(',', '', $_POST['wallet_max_charge_raw']) : (isset($_POST['wallet_max_charge']) ? $_POST['wallet_max_charge'] : '');
        $wallet_max_charge = $raw_max !== '' ? floatval($raw_max) : 0;
        $raw_neg = isset($_POST['wallet_max_negative_balance_raw']) && $_POST['wallet_max_negative_balance_raw'] !== '' ? str_replace(',', '', $_POST['wallet_max_negative_balance_raw']) : (isset($_POST['wallet_max_negative_balance']) ? $_POST['wallet_max_negative_balance'] : '');
        $wallet_max_negative_balance = $raw_neg !== '' ? floatval($raw_neg) : 0;
        $raw_alert = isset($_POST['wallet_min_balance_alert_raw']) && $_POST['wallet_min_balance_alert_raw'] !== '' ? str_replace(',', '', $_POST['wallet_min_balance_alert_raw']) : (isset($_POST['wallet_min_balance_alert']) ? $_POST['wallet_min_balance_alert'] : '');
        $wallet_min_balance_alert = $raw_alert !== '' ? floatval($raw_alert) : 50000;
        $wallet_allow_partial_payment = isset($_POST['wallet_allow_partial_payment']) ? 1 : 0;

        sc_update_setting('wallet_enabled', $wallet_enabled, 'wallet');
        sc_update_setting('wallet_min_charge', $wallet_min_charge, 'wallet');
        sc_update_setting('wallet_max_charge', $wallet_max_charge, 'wallet');
        sc_update_setting('wallet_max_negative_balance', $wallet_max_negative_balance, 'wallet');
        sc_update_setting('wallet_min_balance_alert', $wallet_min_balance_alert, 'wallet');
        sc_update_setting('wallet_allow_partial_payment', $wallet_allow_partial_payment, 'wallet');
        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب کیف پول ذخیره شد', null, ['tab' => 'wallet']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات کیف پول با موفقیت ذخیره شد.</p></div>';
    }
    elseif($current_tab === 'attendance'){
        $deduction_wallet_enabled = isset($_POST['deduction_wallet']) ? 1 : 0;
   
        $raw_neg_debt = isset($_POST['max_debt_for_attendance']) && $_POST['max_debt_for_attendance'] !== '' ? str_replace(',', '', $_POST['max_debt_for_attendance']) : (isset($_POST['max_debt_for_attendance']) ? $_POST['max_debt_for_attendance'] : '');
        $max_debt_for_attendance = $raw_neg_debt !== '' ? floatval($raw_neg_debt) : 0;
        $user_alert_absence_limit = isset($_POST['user_alert_absence_limit']) ? max(1, absint($_POST['user_alert_absence_limit'])) : 3;
        $attendance_api_auto_enabled = isset($_POST['attendance_api_auto_enabled']) ? 1 : 0;
        $attendance_api_base_url = isset($_POST['attendance_api_base_url']) ? esc_url_raw(trim((string) wp_unslash($_POST['attendance_api_base_url']))) : '';
        $attendance_api_key = isset($_POST['attendance_api_key']) ? sanitize_text_field(wp_unslash($_POST['attendance_api_key'])) : '';
        $attendance_api_bearer_token = isset($_POST['attendance_api_bearer_token']) ? sanitize_text_field(wp_unslash($_POST['attendance_api_bearer_token'])) : '';
        $attendance_grace_before_minutes = isset($_POST['attendance_grace_before_minutes']) ? max(0, absint($_POST['attendance_grace_before_minutes'])) : 15;
        $attendance_grace_after_minutes = isset($_POST['attendance_grace_after_minutes']) ? max(0, absint($_POST['attendance_grace_after_minutes'])) : 30;
        $attendance_absent_after_end_minutes = isset($_POST['attendance_absent_after_end_minutes']) ? max(0, absint($_POST['attendance_absent_after_end_minutes'])) : 15;
        $user_alert_absence_limit = isset($_POST['user_alert_absence_limit']) ? max(1, absint($_POST['user_alert_absence_limit'])) : 3;
        sc_update_setting('deduction_wallet_enabled' , $deduction_wallet_enabled , 'attendance');
        sc_update_setting('max_debt_for_attendance' , $max_debt_for_attendance , 'attendance');
        sc_update_setting('user_alert_absence_limit', (string) $user_alert_absence_limit, 'attendance');
        sc_update_setting('attendance_api_auto_enabled', $attendance_api_auto_enabled, 'attendance');
        sc_update_setting('attendance_api_base_url', $attendance_api_base_url, 'attendance');
        sc_update_setting('attendance_api_key', $attendance_api_key, 'attendance');
        sc_update_setting('attendance_api_bearer_token', $attendance_api_bearer_token, 'attendance');
        sc_update_setting('attendance_grace_before_minutes', (string) $attendance_grace_before_minutes, 'attendance');
        sc_update_setting('attendance_grace_after_minutes', (string) $attendance_grace_after_minutes, 'attendance');
        sc_update_setting('attendance_absent_after_end_minutes', (string) $attendance_absent_after_end_minutes, 'attendance');
        sc_update_setting('user_alert_absence_limit', (string) $user_alert_absence_limit, 'attendance');

        $attendance_qr_enabled = isset($_POST['attendance_qr_enabled']) ? 1 : 0;
        $attendance_qr_logo_url = isset($_POST['attendance_qr_logo_url']) ? esc_url_raw(trim((string) wp_unslash($_POST['attendance_qr_logo_url']))) : '';
        $attendance_qr_logo_size_percent = isset($_POST['attendance_qr_logo_size_percent']) ? max(12, min(30, absint($_POST['attendance_qr_logo_size_percent']))) : 22;
        $attendance_qr_scan_cooldown_ms = isset($_POST['attendance_qr_scan_cooldown_ms']) ? max(300, min(5000, absint($_POST['attendance_qr_scan_cooldown_ms']))) : 300;
        $attendance_qr_show_dashboard = isset($_POST['attendance_qr_show_dashboard']) ? 1 : 0;
        $attendance_qr_sound_success_url = isset($_POST['attendance_qr_sound_success_url']) ? esc_url_raw(trim((string) wp_unslash($_POST['attendance_qr_sound_success_url']))) : '';
        $attendance_qr_sound_error_url = isset($_POST['attendance_qr_sound_error_url']) ? esc_url_raw(trim((string) wp_unslash($_POST['attendance_qr_sound_error_url']))) : '';
        $attendance_qr_sound_duplicate_url = isset($_POST['attendance_qr_sound_duplicate_url']) ? esc_url_raw(trim((string) wp_unslash($_POST['attendance_qr_sound_duplicate_url']))) : '';
        $attendance_qr_sound_not_in_course_url = isset($_POST['attendance_qr_sound_not_in_course_url']) ? esc_url_raw(trim((string) wp_unslash($_POST['attendance_qr_sound_not_in_course_url']))) : '';
        if (function_exists('sc_attendance_qr_is_valid_sound_url')) {
            foreach ([
                'attendance_qr_sound_success_url'       => &$attendance_qr_sound_success_url,
                'attendance_qr_sound_error_url'         => &$attendance_qr_sound_error_url,
                'attendance_qr_sound_duplicate_url'     => &$attendance_qr_sound_duplicate_url,
                'attendance_qr_sound_not_in_course_url' => &$attendance_qr_sound_not_in_course_url,
            ] as $label => &$sound_url) {
                if ($sound_url !== '' && !sc_attendance_qr_is_valid_sound_url($sound_url)) {
                    $sound_url = '';
                }
            }
            unset($sound_url);
        }
        sc_update_setting('attendance_qr_enabled', $attendance_qr_enabled, 'attendance');
        sc_update_setting('attendance_qr_logo_url', $attendance_qr_logo_url, 'attendance');
        sc_update_setting('attendance_qr_logo_size_percent', (string) $attendance_qr_logo_size_percent, 'attendance');
        sc_update_setting('attendance_qr_scan_cooldown_ms', (string) $attendance_qr_scan_cooldown_ms, 'attendance');
        sc_update_setting('attendance_qr_show_dashboard', $attendance_qr_show_dashboard, 'attendance');
        sc_update_setting('attendance_qr_sound_success_url', $attendance_qr_sound_success_url, 'attendance');
        sc_update_setting('attendance_qr_sound_error_url', $attendance_qr_sound_error_url, 'attendance');
        sc_update_setting('attendance_qr_sound_duplicate_url', $attendance_qr_sound_duplicate_url, 'attendance');
        sc_update_setting('attendance_qr_sound_not_in_course_url', $attendance_qr_sound_not_in_course_url, 'attendance');

        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب حضور و غیاب ذخیره شد', null, ['tab' => 'attendance']);
        }
                echo '<div class="notice notice-success is-dismissible"><p>تنظیمات حضور و غیاب با موفقیت ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'player_info') {
        $player_verification_required = isset($_POST['player_verification_required']) ? 1 : 0;
        sc_update_setting('player_verification_required', $player_verification_required, 'player_info');
        $builtin_fields = function_exists('sc_get_player_info_builtin_fields') ? sc_get_player_info_builtin_fields() : [];
        $incoming_rules = isset($_POST['player_field_rules']) && is_array($_POST['player_field_rules']) ? $_POST['player_field_rules'] : [];
        $saved_rules = [];
        foreach ($builtin_fields as $field_key => $meta) {
            $visible = isset($incoming_rules[$field_key]['visible']) ? 1 : 0;
            $required = isset($incoming_rules[$field_key]['required']) ? 1 : 0;
            if (!empty($meta['always_visible'])) {
                $visible = 1;
            }
            if (!empty($meta['always_required'])) {
                $required = 1;
            }
            $saved_rules[$field_key] = [
                'visible' => $visible,
                'required' => $required,
            ];
        }
        sc_update_setting('player_info_field_rules', wp_json_encode($saved_rules, JSON_UNESCAPED_UNICODE), 'player_info');

        $custom_fields = function_exists('sc_sanitize_player_info_custom_fields_input') && isset($_POST['player_custom_fields'])
            ? sc_sanitize_player_info_custom_fields_input($_POST['player_custom_fields'])
            : [];
        sc_update_setting('player_info_custom_fields', wp_json_encode($custom_fields, JSON_UNESCAPED_UNICODE), 'player_info');
        if (function_exists('sc_sync_all_profile_completed_statuses')) {
            sc_sync_all_profile_completed_statuses();
        }
        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب اطلاعات بازیکن ذخیره شد', null, ['tab' => 'player_info']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات اطلاعات بازیکن با موفقیت ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'coach_salary') {
        $raw_min = isset($_POST['coach_min_withdrawal_amount_raw']) && $_POST['coach_min_withdrawal_amount_raw'] !== '' ? str_replace(',', '', $_POST['coach_min_withdrawal_amount_raw']) : (isset($_POST['coach_min_withdrawal_amount']) ? $_POST['coach_min_withdrawal_amount'] : '');
        $coach_min_withdrawal_amount = $raw_min !== '' ? floatval($raw_min) : 0;
        $raw_neg = isset($_POST['coach_max_negative_balance_raw']) && $_POST['coach_max_negative_balance_raw'] !== '' ? str_replace(',', '', $_POST['coach_max_negative_balance_raw']) : (isset($_POST['coach_max_negative_balance']) ? $_POST['coach_max_negative_balance'] : '');
        $coach_max_negative_balance = $raw_neg !== '' ? floatval($raw_neg) : 0;
        $calc_couch_salary = isset($_POST['calc_couch_salary']) ? 1 : 0;
        $coach_fixed_salary_settlement_day = isset($_POST['coach_fixed_salary_settlement_day']) ? absint($_POST['coach_fixed_salary_settlement_day']) : 0;
        if ($coach_fixed_salary_settlement_day > 31) {
            $coach_fixed_salary_settlement_day = 0;
        }
        
        sc_update_setting('coach_min_withdrawal_amount', $coach_min_withdrawal_amount, 'coach_salary');
        sc_update_setting('coach_max_negative_balance', $coach_max_negative_balance, 'coach_salary');
        sc_update_setting('coach_fixed_salary_settlement_day', $coach_fixed_salary_settlement_day, 'coach_salary');
        sc_update_setting('calc_couch_salary', $calc_couch_salary, 'coach_salary');
        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب دستمزد مربی ذخیره شد', null, ['tab' => 'coach_salary']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات دستمزد مربی با موفقیت ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'classes') {
        $private_class_cancel_minutes_before = isset($_POST['private_class_cancel_minutes_before']) ? max(0, absint($_POST['private_class_cancel_minutes_before'])) : 1440;
        $private_class_reschedule_minutes_before = isset($_POST['private_class_reschedule_minutes_before']) ? max(0, absint($_POST['private_class_reschedule_minutes_before'])) : 1440;
        sc_update_setting('private_class_cancel_minutes_before', (string) $private_class_cancel_minutes_before, 'classes');
        sc_update_setting('private_class_reschedule_minutes_before', (string) $private_class_reschedule_minutes_before, 'classes');

        $private_booking_mode = isset($_POST['private_booking_mode']) && $_POST['private_booking_mode'] === 'admin_approval'
            ? 'admin_approval'
            : 'direct_payment';
        sc_update_setting('private_booking_mode', $private_booking_mode, 'classes');
        $private_class_page_description = isset($_POST['private_class_page_description'])
            ? wp_kses_post(wp_unslash($_POST['private_class_page_description']))
            : '';
        sc_update_setting('private_class_page_description', $private_class_page_description, 'classes');
        foreach (['course', 'chapter', 'coach', 'slots', 'sessions', 'start_date'] as $field_key) {
            $val = isset($_POST['private_booking_user_show_' . $field_key]) ? 1 : 0;
            sc_update_setting('private_booking_user_show_' . $field_key, (string) $val, 'classes');
        }

        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب کلاس‌ها ذخیره شد', null, ['tab' => 'classes']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات کلاس‌ها با موفقیت ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'honors') {
        $honors_api_key = isset($_POST['honors_api_key']) ? sanitize_text_field(wp_unslash($_POST['honors_api_key'])) : '';
        sc_update_setting('honors_api_key', $honors_api_key, 'honors_api');
        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب افتخارات ذخیره شد', null, ['tab' => 'honors']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات افتخارات با موفقیت ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'pro_features') {
    $pro_feature_notifications = isset($_POST['pro_feature_notifications']) ? (int) $_POST['pro_feature_notifications'] : 0;
    $pro_feature_coaches = isset($_POST['pro_feature_coaches']) ? (int) $_POST['pro_feature_coaches'] : 0;
    $pro_feature_players_wallet = isset($_POST['pro_feature_players_wallet']) ? (int) $_POST['pro_feature_players_wallet'] : 0;
    $pro_feature_coaches_wallet_salary = isset($_POST['pro_feature_coaches_wallet_salary']) ? (int) $_POST['pro_feature_coaches_wallet_salary'] : 0;
    $pro_feature_sms = isset($_POST['pro_feature_sms']) ? (int) $_POST['pro_feature_sms'] : 0;
    $pro_feature_shop = isset($_POST['pro_feature_shop']) ? (int) $_POST['pro_feature_shop'] : 0;
    $pro_feature_user_alerts = isset($_POST['pro_feature_user_alerts']) ? (int) $_POST['pro_feature_user_alerts'] : 0;
    $pro_feature_users_export = isset($_POST['pro_feature_users_export']) ? (int) $_POST['pro_feature_users_export'] : 0;
    $pro_feature_bulk_actions = isset($_POST['pro_feature_bulk_actions']) ? (int) $_POST['pro_feature_bulk_actions'] : 0;
    $pro_feature_certificates = isset($_POST['pro_feature_certificates']) ? (int) $_POST['pro_feature_certificates'] : 0;
    $pro_feature_attendance = isset($_POST['pro_feature_attendance']) ? (int) $_POST['pro_feature_attendance'] : 0;
    $pro_feature_attendance_qr = isset($_POST['pro_feature_attendance_qr']) ? (int) $_POST['pro_feature_attendance_qr'] : 0;
    $pro_feature_courses = isset($_POST['pro_feature_courses']) ? (int) $_POST['pro_feature_courses'] : 0;
    $pro_feature_events = isset($_POST['pro_feature_events']) ? (int) $_POST['pro_feature_events'] : 0;
    $pro_feature_private_notes = isset($_POST['pro_feature_private_notes']) ? (int) $_POST['pro_feature_private_notes'] : 0;
    $pro_feature_support_tickets = isset($_POST['pro_feature_support_tickets']) ? (int) $_POST['pro_feature_support_tickets'] : 0;
    $pro_feature_invoices = isset($_POST['pro_feature_invoices']) ? (int) $_POST['pro_feature_invoices'] : 0;
    $pro_feature_honors = isset($_POST['pro_feature_honors']) ? (int) $_POST['pro_feature_honors'] : 0;
    $pro_feature_reports = isset($_POST['pro_feature_reports']) ? (int) $_POST['pro_feature_reports'] : 0;
    $pro_feature_team_level = isset($_POST['pro_feature_team_level']) ? (int) $_POST['pro_feature_team_level'] : 0;
    $pro_feature_chapters = isset($_POST['pro_feature_chapters']) ? (int) $_POST['pro_feature_chapters'] : 0;
    $pro_feature_faq = isset($_POST['pro_feature_faq']) ? (int) $_POST['pro_feature_faq'] : 0;
    $pro_feature_nav_menus = isset($_POST['pro_feature_nav_menus']) ? (int) $_POST['pro_feature_nav_menus'] : 0;
    $pro_feature_permalinks = isset($_POST['pro_feature_permalinks']) ? (int) $_POST['pro_feature_permalinks'] : 0;
    $pro_feature_surveys = isset($_POST['pro_feature_surveys']) ? (int) $_POST['pro_feature_surveys'] : 0;

    sc_update_setting('pro_feature_notifications', $pro_feature_notifications, 'pro_features');
    sc_update_setting('pro_feature_coaches', $pro_feature_coaches, 'pro_features');
    sc_update_setting('pro_feature_players_wallet', $pro_feature_players_wallet, 'pro_features');
    sc_update_setting('pro_feature_coaches_wallet_salary', $pro_feature_coaches_wallet_salary, 'pro_features');
    sc_update_setting('pro_feature_sms', $pro_feature_sms, 'pro_features');
    sc_update_setting('pro_feature_shop', $pro_feature_shop, 'pro_features');
    sc_update_setting('pro_feature_user_alerts', $pro_feature_user_alerts, 'pro_features');
    sc_update_setting('pro_feature_users_export', $pro_feature_users_export, 'pro_features');
    sc_update_setting('pro_feature_bulk_actions', $pro_feature_bulk_actions, 'pro_features');
    sc_update_setting('pro_feature_certificates', $pro_feature_certificates, 'pro_features');
    sc_update_setting('pro_feature_attendance', $pro_feature_attendance, 'pro_features');
    sc_update_setting('pro_feature_attendance_qr', $pro_feature_attendance_qr, 'pro_features');
    sc_update_setting('pro_feature_courses', $pro_feature_courses, 'pro_features');
    sc_update_setting('pro_feature_events', $pro_feature_events, 'pro_features');
    sc_update_setting('pro_feature_private_notes', $pro_feature_private_notes, 'pro_features');
    sc_update_setting('pro_feature_support_tickets', $pro_feature_support_tickets, 'pro_features');
    sc_update_setting('pro_feature_invoices', $pro_feature_invoices, 'pro_features');
    sc_update_setting('pro_feature_honors', $pro_feature_honors, 'pro_features');
    sc_update_setting('pro_feature_reports', $pro_feature_reports, 'pro_features');
    sc_update_setting('pro_feature_team_level', $pro_feature_team_level, 'pro_features');
    sc_update_setting('pro_feature_chapters', $pro_feature_chapters, 'pro_features');
    sc_update_setting('pro_feature_faq', $pro_feature_faq, 'pro_features');
    sc_update_setting('pro_feature_nav_menus', $pro_feature_nav_menus, 'pro_features');
    sc_update_setting('pro_feature_permalinks', $pro_feature_permalinks, 'pro_features');
    sc_update_setting('pro_feature_surveys', $pro_feature_surveys, 'pro_features');
    if (function_exists('sc_log_activity')) {
        sc_log_activity('updated', 'settings', 0, 'تنظیمات تب امکانات پرو ذخیره شد', null, ['tab' => 'pro_features']);
    }
    echo '<div class="notice notice-success is-dismissible"><p>تنظیمات امکانات پرو با موفقیت ذخیره شد.</p></div>';
}
    elseif ($current_tab === 'log') {
        $log_day = isset($_POST['activity_log_cleanup_day']) ? absint($_POST['activity_log_cleanup_day']) : 1;
        $log_day = max(1, min(28, $log_day));
        sc_update_setting('activity_log_cleanup_day', $log_day, 'activity_log');
        if (function_exists('sc_activity_log_schedule_cron')) {
            sc_activity_log_schedule_cron();
        }
        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب لاگ ذخیره شد', null, ['tab' => 'log', 'activity_log_cleanup_day' => $log_day]);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات لاگ با موفقیت ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'login_register') {
        $sc_login_redirect_path   = isset($_POST['sc_login_redirect_path']) ? sanitize_text_field($_POST['sc_login_redirect_path']) : 'my-account/sc-submit-documents/';
        $sc_login_otp_pattern     = isset($_POST['sc_login_otp_pattern']) ? absint($_POST['sc_login_otp_pattern']) : 0;
        $sc_login_logo_url        = isset($_POST['sc_login_logo_url']) ? esc_url_raw($_POST['sc_login_logo_url']) : '';
        $sc_login_bg_color        = isset($_POST['sc_login_bg_color']) ? sanitize_hex_color($_POST['sc_login_bg_color']) : '#ffffff';
        $sc_login_bg_image        = isset($_POST['sc_login_bg_image']) ? esc_url_raw($_POST['sc_login_bg_image']) : '';
        $sc_login_btn_bg         = isset($_POST['sc_login_btn_bg']) ? sanitize_hex_color($_POST['sc_login_btn_bg']) : '#e60012';
        $sc_login_btn_color      = isset($_POST['sc_login_btn_color']) ? sanitize_hex_color($_POST['sc_login_btn_color']) : '#ffffff';
        $sc_login_page_id        = isset($_POST['sc_login_page_id']) ? absint($_POST['sc_login_page_id']) : 0;
        $sc_login_display_mode   = isset($_POST['sc_login_display_mode']) ? sanitize_text_field($_POST['sc_login_display_mode']) : 'mode1';
        $sc_login_card_align     = isset($_POST['sc_login_card_align']) ? sanitize_text_field($_POST['sc_login_card_align']) : 'center';
        if (!in_array($sc_login_display_mode, ['mode1', 'mode2', 'mode3'], true)) {
            $sc_login_display_mode = 'mode1';
        }
        if (!in_array($sc_login_card_align, ['left', 'center', 'right'], true)) {
            $sc_login_card_align = 'center';
        }
        sc_update_setting('sc_login_redirect_path', $sc_login_redirect_path, 'login_register');
        sc_update_setting('sc_login_otp_pattern', $sc_login_otp_pattern, 'login_register');
        sc_update_setting('sc_login_logo_url', $sc_login_logo_url, 'login_register');
        sc_update_setting('sc_login_bg_color', $sc_login_bg_color, 'login_register');
        sc_update_setting('sc_login_bg_image', $sc_login_bg_image, 'login_register');
        sc_update_setting('sc_login_btn_bg', $sc_login_btn_bg, 'login_register');
        sc_update_setting('sc_login_btn_color', $sc_login_btn_color, 'login_register');
        sc_update_setting('sc_login_page_id', $sc_login_page_id, 'login_register');
        sc_update_setting('sc_login_display_mode', $sc_login_display_mode, 'login_register');
        sc_update_setting('sc_login_card_align', $sc_login_card_align, 'login_register');
        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب ورود و عضویت ذخیره شد', null, ['tab' => 'login_register']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ورود و عضویت ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'about') {

        $sc_org_bg_color =   isset($_POST['sc_org_bg_color']) ? sanitize_hex_color($_POST['sc_org_bg_color']) : '#6D34FF';
        $sc_txt_bg_color =   isset($_POST['sc_txt_bg_color']) ? sanitize_hex_color($_POST['sc_txt_bg_color']) : '#6D34FF';

        $sc_name_club   = isset($_POST['sc_name_club']) ? sanitize_text_field($_POST['sc_name_club']) : 'باشگاه اتم';
        $sc_club_logo_url        = isset($_POST['sc_club_logo_url']) ? esc_url_raw($_POST['sc_club_logo_url']) : '';
        $sc_phone_club        = isset($_POST['sc_phone_club']) ? sanitize_text_field($_POST['sc_phone_club']) : '';

        sc_update_setting('sc_name_club', $sc_name_club, 'abaut_club');
        sc_update_setting('sc_club_logo_url', $sc_club_logo_url, 'abaut_club');
        sc_update_setting('sc_phone_club', $sc_phone_club, 'abaut_club');
        sc_update_setting('sc_org_bg_color', $sc_org_bg_color, 'abaut_club');
        sc_update_setting('sc_txt_bg_color', $sc_txt_bg_color, 'abaut_club');

        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب درباره مجموعه ذخیره شد', null, ['tab' => 'about']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات درباره مجموعه شد.</p></div>';
    }
    elseif ($current_tab === 'bale_bot') {
        $sc_token_club          = isset($_POST['sc_token_club']) ? sanitize_text_field($_POST['sc_token_club']) : '';
        $sc_botname_club        = isset($_POST['sc_botname_club']) ? sanitize_text_field($_POST['sc_botname_club']) : '';
        $sc_bale_safir_api_key  = isset($_POST['sc_bale_safir_api_key']) ? sanitize_text_field($_POST['sc_bale_safir_api_key']) : '';
        $sc_bale_safir_bot_id   = isset($_POST['sc_bale_safir_bot_id']) ? absint($_POST['sc_bale_safir_bot_id']) : 0;
        $sc_bale_bot_enabled    = isset($_POST['sc_bale_bot_enabled']) ? 1 : 0;
        $sc_bale_use_miniapp    = isset($_POST['sc_bale_use_miniapp']) ? 1 : 0;
        $sc_bale_webhook_secret = isset($_POST['sc_bale_webhook_secret']) ? sanitize_text_field($_POST['sc_bale_webhook_secret']) : '';

        sc_update_setting('sc_token_club', $sc_token_club, 'bot');
        sc_update_setting('sc_botname_club', $sc_botname_club, 'bot');
        sc_update_setting('sc_bale_safir_api_key', $sc_bale_safir_api_key, 'bot');
        sc_update_setting('sc_bale_safir_bot_id', $sc_bale_safir_bot_id, 'bot');
        sc_update_setting('sc_bale_bot_enabled', $sc_bale_bot_enabled, 'bot');
        sc_update_setting('sc_bale_use_miniapp', $sc_bale_use_miniapp, 'bot');
        sc_update_setting('sc_bale_webhook_secret', $sc_bale_webhook_secret, 'bot');

        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب ربات بله ذخیره شد', null, ['tab' => 'bale_bot']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ربات بله ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'header_footer') {
    


        $sc_header_search_placeholder = isset($_POST['sc_header_search_placeholder']) ? sanitize_text_field($_POST['sc_header_search_placeholder']) : '';
        sc_update_setting('sc_header_search_placeholder', $sc_header_search_placeholder, 'header_footer');



        $lines_raw = isset($_POST['sc_header_search_suggestions']) ? wp_unslash($_POST['sc_header_search_suggestions']) : '';
        $lines = preg_split('/\r\n|\r|\n/', $lines_raw);
        $items = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line, 2);
            if (count($parts) < 2) {
                continue;
            }
            $t = sanitize_text_field(trim($parts[0]));
            $u = esc_url_raw(trim($parts[1]));
            if ($t === '' || $u === '') {
                continue;
            }
            $items[] = array('title' => $t, 'url' => $u);
        }
        sc_update_setting('sc_header_search_suggestions_json', wp_json_encode($items, JSON_UNESCAPED_UNICODE), 'header_footer');

        $kw_in = isset($_POST['sc_search_keywords']) && is_array($_POST['sc_search_keywords']) ? wp_unslash($_POST['sc_search_keywords']) : array();
        $kw_clean = array();
        foreach ($kw_in as $key => $blob) {
            $key = preg_replace('/[^A-Za-z0-9_]/', '', (string) $key);
            if ($key === '') {
                continue;
            }
            $kw_clean[$key] = sanitize_textarea_field($blob);
        }
        sc_update_setting('sc_header_search_keywords_json', wp_json_encode($kw_clean, JSON_UNESCAPED_UNICODE), 'header_footer');

        if (function_exists('sc_can_manage_license') ? sc_can_manage_license() : in_array('administrator', (array) wp_get_current_user()->roles, true)) {
            $sc_footer_text_line1 = isset($_POST['sc_footer_text_line1']) ? sanitize_textarea_field(wp_unslash($_POST['sc_footer_text_line1'])) : '';
            $sc_footer_text_line2 = isset($_POST['sc_footer_text_line2']) ? sanitize_textarea_field(wp_unslash($_POST['sc_footer_text_line2'])) : '';
            sc_update_setting('sc_footer_text_line1', $sc_footer_text_line1, 'header_footer');
            sc_update_setting('sc_footer_text_line2', $sc_footer_text_line2, 'header_footer');
        }

        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات هدر و فوتر ذخیره شد', null, ['tab' => 'header_footer']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات هدر و فوتر ذخیره شد.</p></div>';
    }
}

// پردازش فرم بازگشت به کارخانه
if (isset($_POST['sc_reset_factory']) && check_admin_referer('sc_reset_factory', 'sc_reset_factory_nonce')
    && function_exists('sc_reset_factory_data')) {
    if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] == '1') {
        $reset_result = sc_reset_factory_data();
        if ($reset_result['success']) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($reset_result['message']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($reset_result['message']) . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>لطفاً تأیید را علامت بزنید.</p></div>';
    }
}

// دریافت تنظیمات سایر تب‌ها — تب لایسنس به این داده‌ها نیاز ندارد
if ($current_tab !== 'license') :

// دریافت تنظیمات فعلی
$penalty_enabled = sc_is_penalty_enabled();
$penalty_minutes = sc_get_penalty_minutes();
$penalty_amount = sc_get_penalty_amount();
$invoice_interval_minutes = sc_get_invoice_interval_minutes();

// تنظیمات SMS
$sms_api_key = sc_get_setting('sms_api_key', '');
$sms_sender = sc_get_setting('sms_sender', '');
$sms_admin_phone = sc_get_setting('sms_admin_phone', '');
$sms_reminder_delay_minutes = sc_get_setting('sms_reminder_delay_minutes', '4320');
$sms_cost_per_message = floatval(sc_get_setting('sms_cost_per_message', '200'));
$sms_master_enabled = (int)sc_get_setting('sms_master_enabled', '1');

// Invoice SMS Settings
$sms_invoice_user_enabled = (int) sc_get_sms_setting('sms_invoice_user_enabled');
$sms_invoice_user_template = sc_get_sms_setting('sms_invoice_user_template');
$sms_invoice_user_pattern = sc_get_sms_setting('sms_invoice_user_pattern');
$sms_invoice_admin_enabled = (int) sc_get_sms_setting('sms_invoice_admin_enabled');
$sms_invoice_admin_template = sc_get_sms_setting('sms_invoice_admin_template');
$sms_invoice_admin_pattern = sc_get_sms_setting('sms_invoice_admin_pattern');

// Additional Invoice states (cancelled, onhold)
$sms_invoice_cancelled_user_enabled = (int) sc_get_sms_setting('sms_invoice_cancelled_user_enabled');
$sms_invoice_cancelled_user_template = sc_get_sms_setting('sms_invoice_cancelled_user_template');
$sms_invoice_cancelled_user_pattern = sc_get_sms_setting('sms_invoice_cancelled_user_pattern');
$sms_invoice_cancelled_admin_enabled = (int) sc_get_sms_setting('sms_invoice_cancelled_admin_enabled');
$sms_invoice_cancelled_admin_template = sc_get_sms_setting('sms_invoice_cancelled_admin_template');
$sms_invoice_cancelled_admin_pattern = sc_get_sms_setting('sms_invoice_cancelled_admin_pattern');

$sms_invoice_onhold_user_enabled = (int) sc_get_sms_setting('sms_invoice_onhold_user_enabled');
$sms_invoice_onhold_user_template = sc_get_sms_setting('sms_invoice_onhold_user_template');
$sms_invoice_onhold_user_pattern = sc_get_sms_setting('sms_invoice_onhold_user_pattern');
$sms_invoice_onhold_admin_enabled = (int) sc_get_sms_setting('sms_invoice_onhold_admin_enabled');
$sms_invoice_onhold_admin_template = sc_get_sms_setting('sms_invoice_onhold_admin_template');
$sms_invoice_onhold_admin_pattern = sc_get_sms_setting('sms_invoice_onhold_admin_pattern');

// Invoice paid
$sms_invoice_paid_user_enabled = (int) sc_get_sms_setting('sms_invoice_paid_user_enabled');
$sms_invoice_paid_user_template = sc_get_sms_setting('sms_invoice_paid_user_template');
$sms_invoice_paid_user_pattern = sc_get_sms_setting('sms_invoice_paid_user_pattern');
$sms_invoice_paid_admin_enabled = (int) sc_get_sms_setting('sms_invoice_paid_admin_enabled');
$sms_invoice_paid_admin_template = sc_get_sms_setting('sms_invoice_paid_admin_template');
$sms_invoice_paid_admin_pattern = sc_get_sms_setting('sms_invoice_paid_admin_pattern');

// WooCommerce Product Order SMS (پیامک محصول)
$sms_wc_order_completed_user_enabled = (int) sc_get_sms_setting('sms_wc_order_completed_user_enabled');
$sms_wc_order_completed_user_template = sc_get_sms_setting('sms_wc_order_completed_user_template');
$sms_wc_order_completed_user_pattern = sc_get_sms_setting('sms_wc_order_completed_user_pattern');
$sms_wc_order_completed_admin_enabled = (int) sc_get_sms_setting('sms_wc_order_completed_admin_enabled');
$sms_wc_order_completed_admin_template = sc_get_sms_setting('sms_wc_order_completed_admin_template');
$sms_wc_order_completed_admin_pattern = sc_get_sms_setting('sms_wc_order_completed_admin_pattern');

$sms_wc_order_cancelled_user_enabled = (int) sc_get_sms_setting('sms_wc_order_cancelled_user_enabled');
$sms_wc_order_cancelled_user_template = sc_get_sms_setting('sms_wc_order_cancelled_user_template');
$sms_wc_order_cancelled_user_pattern = sc_get_sms_setting('sms_wc_order_cancelled_user_pattern');
$sms_wc_order_cancelled_admin_enabled = (int) sc_get_sms_setting('sms_wc_order_cancelled_admin_enabled');
$sms_wc_order_cancelled_admin_template = sc_get_sms_setting('sms_wc_order_cancelled_admin_template');
$sms_wc_order_cancelled_admin_pattern = sc_get_sms_setting('sms_wc_order_cancelled_admin_pattern');

$sms_wc_order_onhold_user_enabled = (int) sc_get_sms_setting('sms_wc_order_onhold_user_enabled');
$sms_wc_order_onhold_user_template = sc_get_sms_setting('sms_wc_order_onhold_user_template');
$sms_wc_order_onhold_user_pattern = sc_get_sms_setting('sms_wc_order_onhold_user_pattern');
$sms_wc_order_onhold_admin_enabled = (int) sc_get_sms_setting('sms_wc_order_onhold_admin_enabled');
$sms_wc_order_onhold_admin_template = sc_get_sms_setting('sms_wc_order_onhold_admin_template');
$sms_wc_order_onhold_admin_pattern = sc_get_sms_setting('sms_wc_order_onhold_admin_pattern');

$sms_wc_order_failed_user_enabled = (int) sc_get_sms_setting('sms_wc_order_failed_user_enabled');
$sms_wc_order_failed_user_template = sc_get_sms_setting('sms_wc_order_failed_user_template');
$sms_wc_order_failed_user_pattern = sc_get_sms_setting('sms_wc_order_failed_user_pattern');
$sms_wc_order_failed_admin_enabled = (int) sc_get_sms_setting('sms_wc_order_failed_admin_enabled');
$sms_wc_order_failed_admin_template = sc_get_sms_setting('sms_wc_order_failed_admin_template');
$sms_wc_order_failed_admin_pattern = sc_get_sms_setting('sms_wc_order_failed_admin_pattern');

// WooCommerce Virtual/Downloadable Product Order SMS
$sc_wc_virtual_sms = [];
$sc_wc_virtual_sms_statuses = ['completed', 'cancelled', 'onhold', 'failed'];
foreach ($sc_wc_virtual_sms_statuses as $sc_wc_virtual_status) {
    foreach (['user', 'admin'] as $sc_wc_virtual_recipient) {
        $sc_wc_virtual_prefix = 'sms_wc_order_virtual_' . $sc_wc_virtual_status . '_' . $sc_wc_virtual_recipient;
        $sc_wc_virtual_sms[$sc_wc_virtual_status][$sc_wc_virtual_recipient] = [
            'enabled' => (int) sc_get_sms_setting($sc_wc_virtual_prefix . '_enabled'),
            'template' => sc_get_sms_setting($sc_wc_virtual_prefix . '_template'),
            'pattern' => sc_get_sms_setting($sc_wc_virtual_prefix . '_pattern'),
        ];
    }
}
$sc_wc_virtual_sms_labels = [
    'completed' => 'تکمیل‌شده',
    'cancelled' => 'لغو‌شده',
    'onhold' => 'در انتظار بررسی',
    'failed' => 'ناموفق',
];
$sc_wc_virtual_sms_recipient_labels = [
    'user' => 'کاربر',
    'admin' => 'مدیر',
];

// Enrollment SMS Settings
$sms_enrollment_user_enabled = (int) sc_get_sms_setting('sms_enrollment_user_enabled');
$sms_enrollment_user_template = sc_get_sms_setting('sms_enrollment_user_template');
$sms_enrollment_user_pattern = sc_get_sms_setting('sms_enrollment_user_pattern');
$sms_enrollment_admin_enabled = (int) sc_get_sms_setting('sms_enrollment_admin_enabled');
$sms_enrollment_admin_template = sc_get_sms_setting('sms_enrollment_admin_template');
$sms_enrollment_admin_pattern = sc_get_sms_setting('sms_enrollment_admin_pattern');

$sms_course_capacity_waitlist_user_enabled = (int) sc_get_sms_setting('sms_course_capacity_waitlist_user_enabled');
$sms_course_capacity_waitlist_user_template = sc_get_sms_setting('sms_course_capacity_waitlist_user_template');
$sms_course_capacity_waitlist_user_pattern = sc_get_sms_setting('sms_course_capacity_waitlist_user_pattern');

// Reminder SMS Settings
$sms_reminder_user_enabled = (int) sc_get_sms_setting('sms_reminder_user_enabled');
$sms_reminder_user_template = sc_get_sms_setting('sms_reminder_user_template');
$sms_reminder_user_pattern = sc_get_sms_setting('sms_reminder_user_pattern');
$sms_reminder_admin_enabled = (int) sc_get_sms_setting('sms_reminder_admin_enabled');
$sms_reminder_admin_template = sc_get_sms_setting('sms_reminder_admin_template');
$sms_reminder_admin_pattern = sc_get_sms_setting('sms_reminder_admin_pattern');

// Absence SMS Settings
$sms_absence_user_enabled = (int) sc_get_sms_setting('sms_absence_user_enabled');
$sms_absence_user_template = sc_get_sms_setting('sms_absence_user_template');
$sms_absence_user_pattern = sc_get_sms_setting('sms_absence_user_pattern');
$sms_absence_admin_enabled = (int) sc_get_sms_setting('sms_absence_admin_enabled');
$sms_absence_admin_template = sc_get_sms_setting('sms_absence_admin_template');
$sms_absence_admin_pattern = sc_get_sms_setting('sms_absence_admin_pattern');
$sms_absence_alert_user_enabled = (int) sc_get_sms_setting('sms_absence_alert_user_enabled');
$sms_absence_alert_user_template = sc_get_sms_setting('sms_absence_alert_user_template');
$sms_absence_alert_user_pattern = sc_get_sms_setting('sms_absence_alert_user_pattern');
$sms_absence_alert_admin_enabled = (int) sc_get_sms_setting('sms_absence_alert_admin_enabled');
$sms_absence_alert_admin_template = sc_get_sms_setting('sms_absence_alert_admin_template');
$sms_absence_alert_admin_pattern = sc_get_sms_setting('sms_absence_alert_admin_pattern');

// Wallet Settings
$wallet_enabled = (int)sc_get_setting('wallet_enabled', '0');
$wallet_min_charge = floatval(sc_get_setting('wallet_min_charge', '10000'));
$wallet_max_charge = floatval(sc_get_setting('wallet_max_charge', '0'));
$wallet_max_negative_balance = floatval(sc_get_setting('wallet_max_negative_balance', '0'));
$wallet_min_balance_alert = floatval(sc_get_setting('wallet_min_balance_alert', '50000'));
$wallet_allow_partial_payment = (int)sc_get_setting('wallet_allow_partial_payment', '1');
$user_alert_absence_limit = (int) sc_get_setting('user_alert_absence_limit', '3');

// Birthday SMS Settings
$sms_birthday_user_enabled = (int) sc_get_sms_setting('sms_birthday_user_enabled');
$sms_birthday_user_template = sc_get_sms_setting('sms_birthday_user_template');
$sms_birthday_user_pattern = sc_get_sms_setting('sms_birthday_user_pattern');

// Insurance expiry SMS Settings
$sms_insurance_expiry_user_enabled = (int) sc_get_sms_setting('sms_insurance_expiry_user_enabled');
$sms_insurance_expiry_user_template = sc_get_sms_setting('sms_insurance_expiry_user_template');
$sms_insurance_expiry_user_pattern = sc_get_sms_setting('sms_insurance_expiry_user_pattern');

// Identity verification approved SMS settings
$sms_identity_verified_user_enabled = (int) sc_get_sms_setting('sms_identity_verified_user_enabled');
$sms_identity_verified_user_template = sc_get_sms_setting('sms_identity_verified_user_template');
$sms_identity_verified_user_pattern = sc_get_sms_setting('sms_identity_verified_user_pattern');

// Identity rejection
$sms_identity_rejected_user_enabled = (int) sc_get_sms_setting('sms_identity_rejected_user_enabled');
$sms_identity_rejected_user_template = sc_get_sms_setting('sms_identity_rejected_user_template');
$sms_identity_rejected_user_pattern = sc_get_sms_setting('sms_identity_rejected_user_pattern');

$sms_certificate_user_enabled = (int) sc_get_sms_setting('sms_certificate_user_enabled');
$sms_certificate_user_template = sc_get_sms_setting('sms_certificate_user_template');
$sms_certificate_user_pattern = sc_get_sms_setting('sms_certificate_user_pattern');

$sms_survey_submission_user_enabled = (int) sc_get_sms_setting('sms_survey_submission_user_enabled');
$sms_survey_submission_user_template = sc_get_sms_setting('sms_survey_submission_user_template');
$sms_survey_submission_user_pattern = sc_get_sms_setting('sms_survey_submission_user_pattern');
$sms_survey_submission_admin_enabled = (int) sc_get_sms_setting('sms_survey_submission_admin_enabled');
$sms_survey_submission_admin_template = sc_get_sms_setting('sms_survey_submission_admin_template');
$sms_survey_submission_admin_pattern = sc_get_sms_setting('sms_survey_submission_admin_pattern');

// Wallet SMS Settings
$sms_wallet_low_balance_user_enabled = (int) sc_get_sms_setting('sms_wallet_low_balance_user_enabled');
$sms_wallet_low_balance_user_template = sc_get_sms_setting('sms_wallet_low_balance_user_template');
$sms_wallet_low_balance_user_pattern = sc_get_sms_setting('sms_wallet_low_balance_user_pattern');

$sms_wallet_negative_balance_user_enabled = (int) sc_get_sms_setting('sms_wallet_negative_balance_user_enabled');
$sms_wallet_negative_balance_user_template = sc_get_sms_setting('sms_wallet_negative_balance_user_template');
$sms_wallet_negative_balance_user_pattern = sc_get_sms_setting('sms_wallet_negative_balance_user_pattern');

$sms_wallet_charge_success_user_enabled = (int) sc_get_sms_setting('sms_wallet_charge_success_user_enabled');
$sms_wallet_charge_success_user_template = sc_get_sms_setting('sms_wallet_charge_success_user_template');
$sms_wallet_charge_success_user_pattern = sc_get_sms_setting('sms_wallet_charge_success_user_pattern');

$sms_wallet_payment_user_enabled = (int) sc_get_sms_setting('sms_wallet_payment_user_enabled');
$sms_wallet_payment_user_template = sc_get_sms_setting('sms_wallet_payment_user_template');
$sms_wallet_payment_user_pattern = sc_get_sms_setting('sms_wallet_payment_user_pattern');

// Support ticket SMS
$sms_ticket_new_recipient_enabled = (int) sc_get_sms_setting('sms_ticket_new_recipient_enabled');
$sms_ticket_new_recipient_template = sc_get_sms_setting('sms_ticket_new_recipient_template');
$sms_ticket_reply_enabled = (int) sc_get_sms_setting('sms_ticket_reply_enabled');
$sms_ticket_reply_template = sc_get_sms_setting('sms_ticket_reply_template');

// Private classes cancellation SMS (stored in classes settings)
$private_class_sms_user_cancel_to_coach_enabled = (int) sc_get_setting('private_class_sms_user_cancel_to_coach_enabled', '0');
$private_class_sms_user_cancel_to_admin_enabled = (int) sc_get_setting('private_class_sms_user_cancel_to_admin_enabled', '0');
$private_class_sms_coach_cancel_to_user_enabled = (int) sc_get_setting('private_class_sms_coach_cancel_to_user_enabled', '0');
$private_class_sms_user_cancel_to_coach_template = sc_get_setting('private_class_sms_user_cancel_to_coach_template', 'مربی گرامی %coach_name%، بازیکن %user_name% جلسه خصوصی دوره %item_name% در تاریخ %date% ساعت %time% را لغو کرد.');
$private_class_sms_user_cancel_to_coach_pattern = (int) sc_get_setting('private_class_sms_user_cancel_to_coach_pattern', '0');
$private_class_sms_user_cancel_to_admin_template = sc_get_setting('private_class_sms_user_cancel_to_admin_template', 'مدیر گرامی، بازیکن %user_name% جلسه خصوصی دوره %item_name% با مربی %coach_name% در تاریخ %date% ساعت %time% را لغو کرد.');
$private_class_sms_user_cancel_to_admin_pattern = (int) sc_get_setting('private_class_sms_user_cancel_to_admin_pattern', '0');
$private_class_sms_coach_cancel_to_user_template = sc_get_setting('private_class_sms_coach_cancel_to_user_template', 'بازیکن گرامی %user_name%، جلسه خصوصی دوره %item_name% در تاریخ %date% ساعت %time% توسط مربی/مدیر لغو شد.');
$private_class_sms_coach_cancel_to_user_pattern = (int) sc_get_setting('private_class_sms_coach_cancel_to_user_pattern', '0');
$private_class_sms_request_to_admin_enabled = (int) sc_get_setting('private_class_sms_request_to_admin_enabled', '0');
$private_class_sms_approved_to_user_enabled = (int) sc_get_setting('private_class_sms_approved_to_user_enabled', '0');
$private_class_sms_rejected_to_user_enabled = (int) sc_get_setting('private_class_sms_rejected_to_user_enabled', '0');
$private_class_sms_activated_to_user_enabled = (int) sc_get_setting('private_class_sms_activated_to_user_enabled', '0');
$private_class_sms_request_to_admin_template = sc_get_setting('private_class_sms_request_to_admin_template', 'مدیر گرامی، بازیکن %user_name% درخواست رزرو کلاس خصوصی دوره %item_name% ثبت کرد.');
$private_class_sms_approved_to_user_template = sc_get_setting('private_class_sms_approved_to_user_template', 'بازیکن گرامی %user_name%، درخواست رزرو کلاس خصوصی دوره %item_name% تایید شد. لطفاً صورت‌حساب #%invoice_id% را پرداخت کنید.');
$private_class_sms_rejected_to_user_template = sc_get_setting('private_class_sms_rejected_to_user_template', 'بازیکن گرامی %user_name%، درخواست رزرو کلاس خصوصی دوره %item_name% رد شد.');
$private_class_sms_activated_to_user_template = sc_get_setting('private_class_sms_activated_to_user_template', 'بازیکن گرامی %user_name%، جلسات کلاس خصوصی دوره %item_name% فعال شد.');
$private_class_sms_request_to_admin_pattern = (int) sc_get_setting('private_class_sms_request_to_admin_pattern', '0');
$private_class_sms_approved_to_user_pattern = (int) sc_get_setting('private_class_sms_approved_to_user_pattern', '0');
$private_class_sms_rejected_to_user_pattern = (int) sc_get_setting('private_class_sms_rejected_to_user_pattern', '0');
$private_class_sms_activated_to_user_pattern = (int) sc_get_setting('private_class_sms_activated_to_user_pattern', '0');
$private_class_bale_request_to_admin_enabled = (int) sc_get_setting('private_class_bale_request_to_admin_enabled', '1');
$private_class_bale_approved_to_user_enabled = (int) sc_get_setting('private_class_bale_approved_to_user_enabled', '1');
$private_class_bale_rejected_to_user_enabled = (int) sc_get_setting('private_class_bale_rejected_to_user_enabled', '1');
$private_class_bale_activated_to_user_enabled = (int) sc_get_setting('private_class_bale_activated_to_user_enabled', '1');
$private_booking_mode = function_exists('sc_get_private_booking_mode') ? sc_get_private_booking_mode() : 'direct_payment';
$private_booking_user_fields = function_exists('sc_get_private_booking_user_field_settings') ? sc_get_private_booking_user_field_settings() : [];

// تنظیمات افزونه پرو
$pro_feature_notifications = (int) sc_get_setting('pro_feature_notifications', 0);
$pro_feature_coaches = (int) sc_get_setting('pro_feature_coaches', 0);
$pro_feature_players_wallet = (int) sc_get_setting('pro_feature_players_wallet', 0);
$pro_feature_coaches_wallet_salary = (int) sc_get_setting('pro_feature_coaches_wallet_salary', 0);
$pro_feature_sms = (int) sc_get_setting('pro_feature_sms', 0);
$pro_feature_shop = (int) sc_get_setting('pro_feature_shop', 0);
$pro_feature_user_alerts = (int) sc_get_setting('pro_feature_user_alerts', 1);
$pro_feature_users_export = (int) sc_get_setting('pro_feature_users_export', 1);
$pro_feature_bulk_actions = (int) sc_get_setting('pro_feature_bulk_actions', 1);
$pro_feature_certificates = (int) sc_get_setting('pro_feature_certificates', 1);
$pro_feature_attendance = (int) sc_get_setting('pro_feature_attendance', 1);
$pro_feature_attendance_qr = (int) sc_get_setting('pro_feature_attendance_qr', 1);
$pro_feature_courses = (int) sc_get_setting('pro_feature_courses', 1);
$pro_feature_events = (int) sc_get_setting('pro_feature_events', 1);
$pro_feature_private_notes = (int) sc_get_setting('pro_feature_private_notes', 1);
$pro_feature_support_tickets = (int) sc_get_setting('pro_feature_support_tickets', 1);
$pro_feature_invoices = (int) sc_get_setting('pro_feature_invoices', 1);
$pro_feature_honors = (int) sc_get_setting('pro_feature_honors', 1);
$pro_feature_reports = (int) sc_get_setting('pro_feature_reports', 1);
$pro_feature_team_level = (int) sc_get_setting('pro_feature_team_level', 1);
$pro_feature_chapters = (int) sc_get_setting('pro_feature_chapters', 1);
$pro_feature_faq = (int) sc_get_setting('pro_feature_faq', 1);
$pro_feature_nav_menus = (int) sc_get_setting('pro_feature_nav_menus', 1);
$pro_feature_permalinks = (int) sc_get_setting('pro_feature_permalinks', 1);
$pro_feature_surveys = (int) sc_get_setting('pro_feature_surveys', 0);
$wallet_enabled = (int) sc_get_setting('wallet_enabled', 0);

$activity_log_cleanup_day = max(1, min(28, (int) sc_get_setting('activity_log_cleanup_day', '1')));

// تنظیمات ورود و عضویت
$sc_login_redirect_path = sc_get_setting('sc_login_redirect_path', 'my-account/sc-submit-documents/');
$sc_login_otp_pattern   = sc_get_setting('sc_login_otp_pattern', '');
$sc_login_logo_url      = sc_get_setting('sc_login_logo_url', '');
$sc_login_bg_color      = sc_get_setting('sc_login_bg_color', '#ffffff');
$sc_login_bg_image      = sc_get_setting('sc_login_bg_image', '');
$sc_login_btn_bg        = sc_get_setting('sc_login_btn_bg', '#e60012');
$sc_login_btn_color     = sc_get_setting('sc_login_btn_color', '#ffffff');
$sc_login_page_id       = (int) sc_get_setting('sc_login_page_id', 0);
$sc_login_display_mode  = sc_get_setting('sc_login_display_mode', 'mode1');
$sc_login_card_align    = sc_get_setting('sc_login_card_align', 'center');
if (!in_array($sc_login_display_mode, ['mode1', 'mode2', 'mode3'], true)) {
    $sc_login_display_mode = 'mode1';
}
if (!in_array($sc_login_card_align, ['left', 'center', 'right'], true)) {
    $sc_login_card_align = 'center';
}
// تنطیمات حضور و غیاب 

$deduction_wallet_enabled = (int)sc_get_setting('deduction_wallet',0);
// تنظیمات درباره مجموعه

$sc_name_club      = sc_get_setting('sc_name_club', '');
$sc_club_logo_url      = sc_get_setting('sc_club_logo_url', '');
$sc_phone_club      = sc_get_setting('sc_phone_club', '');

$sc_header_search_placeholder = sc_get_setting('sc_header_search_placeholder', 'جستجو در خدمات، صفحات و فروشگاه…');
$sc_footer_text_line1 = sc_get_setting('sc_footer_text_line1', '{year} تمامی حقوق برای سیستم هوشمند باشگاه اتم کلاب محفوظ است.');
$sc_footer_text_line2 = sc_get_setting('sc_footer_text_line2', 'طراحی شده توسط اتم کلاب');
$sc_can_edit_footer_texts = function_exists('sc_can_manage_license')
    ? sc_can_manage_license()
    : in_array('administrator', (array) wp_get_current_user()->roles, true);
$sc_org_bg_color = sc_get_setting('sc_org_bg_color', '#6D34FF');
$sc_txt_bg_color = sc_get_setting('sc_txt_bg_color', '#6D34FF');

$sc_header_search_suggestions_lines = '';
$raw_header_suggestions = sc_get_setting('sc_header_search_suggestions_json', '');
$decoded_header_suggestions = json_decode((string) $raw_header_suggestions, true);
if (is_array($decoded_header_suggestions)) {
    foreach ($decoded_header_suggestions as $row) {
        if (!empty($row['title']) && !empty($row['url'])) {
            $sc_header_search_suggestions_lines .= $row['title'] . '|' . $row['url'] . "\n";
        }
    }
}

// ذخیره سازی صورتحساب
$sessions_count_threshold = sc_get_setting('sessions_count_threshold','1');

endif; // پایان بارگذاری تنظیمات (غیر از تب لایسنس)
?>

<div class="wrap sc-settings-page-header sc-finance-page-header sc_setting_section">
    <h1 class="wp-heading-inline">تنظیمات باشگاه</h1>
    <hr class="wp-header-end">
    <p class="sc-settings-subtitle">پیکربندی جریمه، صورت‌حساب، پیامک، کیف پول و سایر امکانات باشگاه.</p>
</div>
<div class="wrap sc-settings-page-body sc-finance-page-body sc_setting_section">
    <nav class="nav-tab-wrapper sc-settings-nav-tabs">
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=license'); ?>"
           class="nav-tab <?php echo $current_tab === 'license' ? 'nav-tab-active' : ''; ?>">
            لایسنس
        </a>
        <?php if (!function_exists('sc_is_license_active') || sc_is_license_active()) : ?>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=penalty'); ?>"
           class="nav-tab <?php echo $current_tab === 'penalty' ? 'nav-tab-active' : ''; ?>">
            جریمه
        </a>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>"
           class="nav-tab <?php echo $current_tab === 'invoice' ? 'nav-tab-active' : ''; ?>">
            صورتحساب
        </a>
        <?php
        if(sc_is_pro_feature_sms_enabled()){ ?>

        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=sms'); ?>"
           class="nav-tab <?php echo $current_tab === 'sms' ? 'nav-tab-active' : ''; ?>">
            پیامک
        </a>
     <?php } ?>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=login_register'); ?>"
           class="nav-tab <?php echo $current_tab === 'login_register' ? 'nav-tab-active' : ''; ?>">
            ورود و عضویت
        </a>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=about'); ?>"
           class="nav-tab <?php echo $current_tab === 'about' ? 'nav-tab-active' : ''; ?>">
            درباره  مجموعه
        </a>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=honors'); ?>"
           class="nav-tab <?php echo $current_tab === 'honors' ? 'nav-tab-active' : ''; ?>">
            افتخارات
        </a>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=bale_bot'); ?>"
           class="nav-tab <?php echo $current_tab === 'bale_bot' ? 'nav-tab-active' : ''; ?>">
            ربات بله
        </a>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=header_footer'); ?>"
           class="nav-tab <?php echo $current_tab === 'header_footer' ? 'nav-tab-active' : ''; ?>">
            هدر و فوتر
        </a>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=player_info'); ?>"
            class="nav-tab <?php echo $current_tab === 'player_info' ? 'nav-tab-active' : ''; ?>">
               اطلاعات بازیکن
        </a>
        <?php
          if (function_exists('sc_is_pro_feature_players_wallet_enabled') && sc_is_pro_feature_players_wallet_enabled()) {
?>

     
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=wallet'); ?>"
           class="nav-tab <?php echo $current_tab === 'wallet' ? 'nav-tab-active' : ''; ?>">
            کیف پول
        </a>
             <?php }
     
         if (function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') && sc_is_pro_feature_coaches_wallet_salary_enabled()) {
 ?>

        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=attendance'); ?>"
            class="nav-tab <?php echo $current_tab === 'attendance' ? 'nav-tab-active' : ''; ?>">
               حضور و غیاب
        </a>

     
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=coach_salary'); ?>"
           class="nav-tab <?php echo $current_tab === 'coach_salary' ? 'nav-tab-active' : ''; ?>">
            دستمزد مربی
        </a>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=classes'); ?>"
           class="nav-tab <?php echo $current_tab === 'classes' ? 'nav-tab-active' : ''; ?>">
            کلاس‌ها
        </a>
             <?php } 
             
             if ( in_array('administrator', wp_get_current_user()->roles) ) {
?>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=pro_features'); ?>"
            class="nav-tab <?php echo $current_tab === 'pro_features' ? 'nav-tab-active' : ''; ?>">
               امکانات پرو
        </a>

        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=log'); ?>"
            class="nav-tab <?php echo $current_tab === 'log' ? 'nav-tab-active' : ''; ?>">
            لاگ
        </a>

        <!-- <a href="<?php // echo admin_url('admin.php?page=sc_setting&tab=reset'); ?>"
           class="nav-tab <?php // echo $current_tab === 'reset' ? 'nav-tab-active' : ''; ?>">
            بازگشت به کارخانه
        </a> -->
<?php } ?>
        <?php endif; ?>
    </nav>

    <div class="tab-content sc-settings-tab-content">
        <?php if ($current_tab === 'license') :
            include SC_TEMPLATES_ADMIN_DIR . 'settings-tab-license.php';
        elseif ($current_tab === 'penalty') : ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>

                <table class="form-table  ">
                    <tr>
                        <th scope="row">
                            <label for="penalty_enabled">فعال کردن جریمه</label>
                        </th>
                        <td>
                            <input type="checkbox"
                                   name="penalty_enabled"
                                   id="penalty_enabled"
                                   value="1"
                                   <?php checked($penalty_enabled, 1); ?>>
                            <label for="penalty_enabled">فعال کردن سیستم جریمه</label>
                            <p class="description">در صورت فعال بودن، برای صورت حساب‌های پرداخت نشده بعد از مدت مشخص شده جریمه اعمال می‌شود.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="penalty_minutes">مهلت زمانی برای جریمه </label>
                        </th>
                        <td>
                            <input type="number"
                                   name="penalty_minutes"
                                   id="penalty_minutes"
                                   value="<?php echo esc_attr($penalty_minutes); ?>"
                                   min="1"
                                   class="regular-text"
                                   required>
                            <p class="description">بعد از گذشت این مهلت زمانی جریمه برای کاربر اعمال خواهد شد.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="penalty_amount">مبلغ جریمه (تومان)</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="penalty_amount"
                                   id="penalty_amount"
                                   value="<?php echo $penalty_amount > 0 ? number_format($penalty_amount, 0, '.', ',') : ''; ?>"
                                   class="regular-text"
                                   placeholder="0"
                                   dir="ltr"
                                   inputmode="numeric"
                                   required>
                            <input type="hidden"
                                   name="penalty_amount_raw"
                                   id="penalty_amount_raw"
                                   value="<?php echo esc_attr($penalty_amount); ?>">
                            <p class="description">مبلغ جریمه که به صورت حساب اضافه می‌شود.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات">
                </p>
            </form>

    <?php elseif ($current_tab === 'invoice') : 
           
            $invoice_day_of_month = (int) sc_get_invoice_day_of_month();
            $invoice_settlement_gregorian_list = [];
            if (sc_get_invoice_mode() === 'fixed_date' && function_exists('gregorian_to_jalali') && function_exists('jalali_to_gregorian')) {
                $now = new DateTime();
                $today_j = gregorian_to_jalali((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));
                $jy = $today_j[0];
                $jm = (int)$today_j[1];
                $month_names = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                $suffix = ($invoice_day_of_month === 0) ? ' - آخر ماه' : '';
                for ($m = 0; $m <= 2; $m++) {
                    $ty = $jy;
                    $tm = $jm + $m;
                    if ($tm > 12) { $tm -= 12; $ty++; }
                    $last_day = function_exists('jalali_days_in_month') ? jalali_days_in_month($tm, $ty) : (($tm <= 6) ? 31 : (($tm <= 11) ? 30 : 29));
                    $jd = ($invoice_day_of_month > 0) ? min($invoice_day_of_month, $last_day) : $last_day;
                    $g = jalali_to_gregorian($ty, $tm, $jd);
                    $invoice_settlement_gregorian_list[] = ['label' => ($m === 0 ? 'ماه جاری' : ($m === 1 ? 'ماه بعد' : '۲ ماه بعد')) . ' (' . $month_names[$tm] . ' ' . $ty . ')' . $suffix, 'date' => sprintf('%04d/%02d/%02d', $g[0], $g[1], $g[2])];
                }
            }
                ?>
                <form method="POST" action="">
         <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>

                        <table class="form-table">
                    <tr>
                        <th scope="row">ایجاد صورت حساب برای بازیکن تیم</th>
                        <td>
                            <?php  $pro_create_invoice_player_team = sc_get_setting('pro_create_invoice_player_team'); ?>
                            <label class="switch">
                                <input type="checkbox" name="pro_create_invoice_player_team" value="1" <?php checked($pro_create_invoice_player_team, 1); ?>>
                                <span class="slider round"></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>نوع ایجاد صورتحساب</th>
                        <td>
                            <select name="invoice_mode" id="invoice_mode_select" style="width: 200px;">
                                <option value="interval" <?php selected(sc_get_invoice_mode(), 'interval'); ?>>
                                    بر اساس فاصله زمانی
                                </option>
                                <option value="fixed_date" <?php selected(sc_get_invoice_mode(), 'fixed_date'); ?>>
                                    در تاریخ مشخص ماهانه
                                </option>
                                <option value="sessions_threshold" <?php selected(sc_get_invoice_mode(), 'sessions_threshold'); ?>>
                                    بر حسب تعداد جلسات کاربر
                                </option>
                            </select>
                        </td>
                    </tr>


                    <tr class="invoice-row interval-row">
                        <th>فاصله زمانی (دقیقه)</th>
                        <td>
                            <input type="number"
                                name="invoice_interval_minutes"
                                value="<?php echo esc_attr(sc_get_invoice_interval_minutes()); ?>">
                            <p class="description">فقط در حالت فاصله زمانی استفاده می‌شود</p>
                        </td>
                    </tr>

                    <tr class="invoice-row fixed-date-row-day">
                        <th>روز ماه (شمسی)</th>
                        <td>
                            <input type="number"
                                name="invoice_day_of_month"
                                id="invoice_day_of_month"
                                min="0"
                                max="31"
                                value="<?php echo $invoice_day_of_month > 0 ? esc_attr($invoice_day_of_month) : ''; ?>"
                                placeholder="0">
                            <p class="description">روز شمسی هر ماه (۱ تا ۳۱). خالی یا ۰ = آخر ماه شمسی.</p>
                            <?php if (sc_get_invoice_mode() === 'fixed_date' && !empty($invoice_settlement_gregorian_list)): ?>
                                <div class="description" style="margin-top: 8px; padding: 8px; background: #f0f6fc; border-right: 3px solid #2271b1;">
                                    <?php foreach ($invoice_settlement_gregorian_list as $item): ?>
                                        <div><strong><?php echo esc_html($item['label']); ?>:</strong> <?php echo esc_html($item['date']); ?></div>
                                    <?php endforeach; ?>
                                    <div style="margin-top: 6px;"><em>محاسبه دقیق بر اساس تقویم رسمی جلالی</em></div>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr class="invoice-row fixed-date-row-time">
                        <th>ساعت و دقیقه اجرا</th>
                        <td>
                            
                            <input type="number"
                                name="invoice_minute"
                                min="0"
                                max="59"
                                value="<?php echo esc_attr(sc_get_invoice_minute()); ?>"
                                placeholder="دقیقه"
                                style="width: 70px;"> : 
                                <input type="number"
                                name="invoice_hour"
                                min="0"
                                max="23"
                                value="<?php echo esc_attr(sc_get_invoice_hour()); ?>"
                                placeholder="ساعت"
                                style="width: 70px;">
                            <p class="description">مثال: 23:10 یعنی ساعت ۱۱ شب و ۱۰ دقیقه</p>
                        </td>
                    </tr>
                    <tr class="invoice-row sessions-row">
                        <th>تعداد جلسات باقی مانده </th>
                        <td>
                            <input type="number"
                                name="sessions_count_threshold"
                                min="1"
                                max="100"
                                value="<?php echo $sessions_count_threshold > 0 ? esc_attr($sessions_count_threshold) : '1'; ?>"
                                placeholder="0">
                            <p class="description">وقتی به این عدد تعداد جلسات باقی مانده برسد یک فاکتور برای کاربر ساخته می شود.</p>
                                
                                
                            
                        </td>
                    </tr>

                    </table>


                      <p class="submit">
                      <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات">
                                    </p>
                  </form>
        <?php elseif ($current_tab === 'log') : ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>
                <h3>تنظیمات لاگ فعالیت</h3>
                <p class="description">لاگ فعالیت‌های ادمین (چه کسی چه عملی انجام داده) در دیتابیس ذخیره می‌شود. لاگ‌های قدیمی‌تر از یک ماه به‌صورت خودکار پاک می‌شوند.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="activity_log_cleanup_day">روز پاک‌سازی ماهانه</label></th>
                        <td>
                            <select name="activity_log_cleanup_day" id="activity_log_cleanup_day">
                                <?php for ($d = 1; $d <= 28; $d++) : ?>
                                    <option value="<?php echo $d; ?>" <?php selected($activity_log_cleanup_day, $d); ?>><?php echo $d; ?> هر ماه</option>
                                <?php endfor; ?>
                            </select>
                            <p class="description">در این روز از هر ماه، لاگ‌های قدیمی‌تر از ۱ ماه حذف می‌شوند.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات">
                </p>
            </form>
        <?php elseif ($current_tab === 'reset') : ?>
            <div class="sc-reset-factory-section">
                <div class="notice notice-warning" style="margin-bottom: 20px;">
                    <p><strong>هشدار:</strong> این عملیات غیر قابل بازگشت است!</p>
                    <p>با کلیک بر روی دکمه زیر، تمام اطلاعات موجود در جداول افزونه حذف خواهد شد:</p>
                    <ul style="margin-right: 20px; margin-top: 10px;">
                        <li>تمام اعضا (کاربران)</li>
                        <li>تمام دوره‌ها</li>
                        <li>تمام ثبت‌نام‌ها در دوره‌ها</li>
                        <li>تمام صورت حساب‌ها</li>
                        <li>تمام حضور و غیاب‌ها</li>
                    </ul>
                    <p><strong>توجه:</strong> ساختار جداول (ستون‌ها) حفظ می‌شود و فقط داده‌ها حذف می‌شوند.</p>
                </div>

                <form method="POST" action="" id="sc-reset-factory-form" onsubmit="return scConfirmInline(event, { type: 'warning', message: 'آیا مطمئن هستید؟ این عملیات غیر قابل بازگشت است و تمام اطلاعات حذف خواهد شد!' });">
                    <?php wp_nonce_field('sc_reset_factory', 'sc_reset_factory_nonce'); ?>

                    <p>
                        <label>
                            <input type="checkbox" name="confirm_reset" value="1" required>
                            من این عملیات را درک کرده‌ام و می‌خواهم تمام اطلاعات را حذف کنم
                        </label>
                    </p>

                    <p class="submit">
                        <input type="submit"
                               name="sc_reset_factory"
                               class="button button-secondary"
                               value="بازگشت به کارخانه (حذف تمام اطلاعات)"
                               style="background-color: #dc3232; border-color: #dc3232; color: #fff;"
                               onclick="return scConfirmInline(event, { type: 'warning', message: 'آیا واقعاً مطمئن هستید؟ این عملیات غیر قابل بازگشت است!' });">
                    </p>
                </form>
            </div>
        <?php elseif ($current_tab === 'login_register') : ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>
                    <h3>تنظیمات فرم ورود و عضویت - [sc_login_register_form]</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sc_login_redirect_path">ریدایرکت بعد از ورود</label></th>
                        <td>
                            <input type="text" name="sc_login_redirect_path" id="sc_login_redirect_path"
                                   value="<?php echo esc_attr($sc_login_redirect_path); ?>"
                                   class="regular-text" placeholder="my-account/sc-submit-documents/">
                            <p class="description">مسیر نسبی بعد از آدرس سایت (مثال: my-account/sc-submit-documents/)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_login_page_id">صفحه ورود سفارشی</label></th>
                        <td>
                            <?php
                            wp_dropdown_pages(array(
                                'name'              => 'sc_login_page_id',
                                'id'                => 'sc_login_page_id',
                                'selected'          => $sc_login_page_id,
                                'show_option_none'  => '— انتخاب کنید (غیرفعال) —',
                                'option_none_value' => 0,
                            ));
                            ?>
                            <p class="description">
                                صفحه‌ای که حاوی شورتکد <code>[sc_login_register_form]</code> است را انتخاب کنید. در صورت انتخاب، کاربران لاگین‌نکرده هنگام باز کردن <code>/my-account/</code> به جای فرم پیش‌فرض ووکامرس به این صفحه هدایت می‌شوند.
                            </p>
                            <?php if ($sc_login_page_id) :
                                $sc_login_page_url = get_permalink($sc_login_page_id);
                                if ($sc_login_page_url) : ?>
                                    <p class="description">
                                        پیش‌نمایش لینک: <a href="<?php echo esc_url($sc_login_page_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($sc_login_page_url); ?></a>
                                    </p>
                                <?php endif;
                            endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_login_otp_pattern">کد پترن پیامک (کد یکبارمصرف)</label></th>
                        <td>
                            <input type="number" name="sc_login_otp_pattern" id="sc_login_otp_pattern"
                                   value="<?php echo esc_attr($sc_login_otp_pattern); ?>"
                                   class="small-text" min="0" placeholder="مثال: 123456">
                            <p class="description">کد پترن از پنل sms.ir برای ارسال کد تأیید (پارامتر: Code)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_login_display_mode">حالت نمایش</label></th>
                        <td>
                            <select name="sc_login_display_mode" id="sc_login_display_mode">
                                <option value="mode1" <?php selected($sc_login_display_mode, 'mode1'); ?>>حالت یک — کارت وسط صفحه با تصویر پس‌زمینه</option>
                                <option value="mode2" <?php selected($sc_login_display_mode, 'mode2'); ?>>حالت دو — تصویر ۷۰٪ چپ و فرم ۳۰٪ راست</option>
                                <option value="mode3" <?php selected($sc_login_display_mode, 'mode3'); ?>>حالت سه — فرم ۳۰٪ چپ و تصویر ۷۰٪ راست</option>
                            </select>
                            <p class="description">حالت یک: کارت شیشه‌ای روی پس‌زمینه. حالت دو: تصویر چپ، فرم راست. حالت سه: برعکس حالت دو — فرم چپ، تصویر راست.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_login_card_align">تراز</label></th>
                        <td>
                            <select name="sc_login_card_align" id="sc_login_card_align">
                                <option value="right" <?php selected($sc_login_card_align, 'right'); ?>>راست</option>
                                <option value="center" <?php selected($sc_login_card_align, 'center'); ?>>وسط</option>
                                <option value="left" <?php selected($sc_login_card_align, 'left'); ?>>چپ</option>
                            </select>
                            <p class="description">حالت یک: موقعیت کارت روی صفحه. حالت دو و سه: تراز تصویر در بخش تصویر.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_login_logo_url">لوگو</label></th>
                        <td>
                            <div class="sc-lr-media-wrap">
                                <input type="hidden" name="sc_login_logo_url" id="sc_login_logo_url"
                                       value="<?php echo esc_attr($sc_login_logo_url); ?>">
                                <button type="button" class="button" id="sc_login_logo_upload">انتخاب تصویر</button>
                                <button type="button" class="button" id="sc_login_logo_remove" <?php echo empty($sc_login_logo_url) ? ' style="display:none;"' : ''; ?>>حذف</button>
                                <div class="sc-lr-media-preview" id="sc_login_logo_preview" style="margin-top:8px;">
                                    <?php if (!empty($sc_login_logo_url)) : ?>
                                        <img src="<?php echo esc_url($sc_login_logo_url); ?>" alt="" style="max-width:200px;height:auto;border:1px solid #ddd;border-radius:4px;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="description">لوگوی نمایش داده شده بالای فرم</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_login_bg_color">رنگ پس‌زمینه</label></th>
                        <td>
                            <input type="color" name="sc_login_bg_color" id="sc_login_bg_color"
                                   value="<?php echo esc_attr($sc_login_bg_color); ?>"
                                   style="vertical-align:middle;width:40px;height:32px;padding:2px;cursor:pointer;">
                            <span class="sc-lr-color-hex" id="sc_login_bg_color_hex"><?php echo esc_html($sc_login_bg_color); ?></span>
                            <p class="description">رنگ پس‌زمینه صفحه فرم</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_login_bg_image">تصویر پس‌زمینه</label></th>
                        <td>
                            <div class="sc-lr-media-wrap">
                                <input type="hidden" name="sc_login_bg_image" id="sc_login_bg_image"
                                       value="<?php echo esc_attr($sc_login_bg_image); ?>">
                                <button type="button" class="button" id="sc_login_bg_upload">انتخاب تصویر</button>
                                <button type="button" class="button" id="sc_login_bg_remove" <?php echo empty($sc_login_bg_image) ? ' style="display:none;"' : ''; ?>>حذف</button>
                                <div class="sc-lr-media-preview" id="sc_login_bg_preview" style="margin-top:8px;">
                                    <?php if (!empty($sc_login_bg_image)) : ?>
                                        <img src="<?php echo esc_url($sc_login_bg_image); ?>" alt="" style="max-width:200px;max-height:80px;object-fit:cover;border:1px solid #ddd;border-radius:4px;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="description">
                                <strong>حالت یک:</strong> تصویر تمام‌صفحه — اندازه پیشنهادی <strong>1920×1080</strong> پیکسل (نسبت ۱۶:۹).<br>
                                <strong>حالت دو و سه:</strong> تصویر بخش بزرگ — اندازه پیشنهادی <strong>1344×1080</strong> پیکسل (نسبت ۷۰٪ عرض صفحه Full HD).<br>
                                تصویر کل ناحیه را می‌پوشاند؛ اگر نسبت تصویر با صفحه فرق داشته باشد، لبه‌ها کمی برش می‌خورند. در صورت خالی بودن، فقط رنگ پس‌زمینه اعمال می‌شود.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_login_btn_bg">رنگ دکمه</label></th>
                        <td>
                            <input type="color" name="sc_login_btn_bg" id="sc_login_btn_bg"
                                   value="<?php echo esc_attr($sc_login_btn_bg); ?>"
                                   style="vertical-align:middle;width:40px;height:32px;padding:2px;cursor:pointer;">
                            <span class="sc-lr-color-hex" id="sc_login_btn_bg_hex"><?php echo esc_html($sc_login_btn_bg); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_login_btn_color">رنگ متن دکمه</label></th>
                        <td>
                            <input type="color" name="sc_login_btn_color" id="sc_login_btn_color"
                                   value="<?php echo esc_attr($sc_login_btn_color); ?>"
                                   style="vertical-align:middle;width:40px;height:32px;padding:2px;cursor:pointer;">
                            <span class="sc-lr-color-hex" id="sc_login_btn_color_hex"><?php echo esc_html($sc_login_btn_color); ?></span>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات ورود و عضویت">
                </p>
            </form>
         
        <?php elseif ($current_tab === 'about') :
            
            ?>
            <form method="POST" action="">

                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>
                    <h3>اطلاعات مجموعه </h3>
                <table class="form-table">

                    <tr>
                        <th scope="row"><label for="sc_name_club">نام مجموعه </label></th>
                        <td>
                            <input type="text" name="sc_name_club" 
                                   value="<?php echo esc_attr($sc_name_club); ?>"
                                   class="regular-text" placeholder="مثلا اتم کلاب">
                            <p class="description">در قسمت هایی که نام باشگاه وجود دارد از این قسمت خوانده می شود. هدر - فوتر و ...</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="sc_club_logo_url">لوگو</label></th>
                        <td>
                            <div class="sc-lr-media-wrap">
                                <input type="hidden" name="sc_club_logo_url" id="sc_club_logo_url"
                                       value="<?php echo esc_attr($sc_club_logo_url); ?>">
                                <button type="button" class="button" id="sc_club_logo_upload">انتخاب تصویر</button>
                                <button type="button" class="button" id="sc_club_logo_remove" <?php echo empty($sc_club_logo_url) ? ' style="display:none;"' : ''; ?>>حذف</button>
                                <div class="sc-lr-media-preview" id="sc_club_logo_preview" style="margin-top:8px;">
                                    <?php if (!empty($sc_club_logo_url)) : ?>
                                        <img src="<?php echo esc_url($sc_club_logo_url); ?>" alt="" style="max-width:200px;height:auto;border:1px solid #ddd;border-radius:4px;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="description">نمایش در هدر و فوتر</p>
                        </td>
                    </tr>

                             <tr>
                        <th scope="row"><label for="sc_org_bg_color">رنگ سازمانی</label></th>
                        <td>
                            <input type="color" name="sc_org_bg_color" id="sc_org_bg_color"
                                   value="<?php echo esc_attr($sc_org_bg_color); ?>"
                                   style="vertical-align:middle;width:40px;height:32px;padding:2px;cursor:pointer;">
                            <span class="sc-org-bg-color-hex" id="sc_org_bg_color_hex"><?php echo esc_html($sc_org_bg_color); ?></span>
                            <p class="description">این رنگ در بخش های فوتر- هدر و منو به کار می رود .</p>
                        </td>
                    </tr>
                      <tr>
                        <th scope="row"><label for="sc_txt_bg_color">رنگ متن </label></th>
                        <td>
                            <input type="color" name="sc_txt_bg_color" id="sc_txt_bg_color"
                                   value="<?php echo esc_attr($sc_txt_bg_color); ?>"
                                   style="vertical-align:middle;width:40px;height:32px;padding:2px;cursor:pointer;">
                            <span class="sc-txt-bg-color-hex" id="sc_txt_bg_color_hex"><?php echo esc_html($sc_txt_bg_color); ?></span>
                            <p class="description">این رنگ در بخش های فوتر- هدر و منو به کار می رود .</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_phone_club">شماره تماس پشتیبانی </label></th>
                        <td>
                            <input type="text" name="sc_phone_club" 
                                   value="<?php echo esc_attr($sc_phone_club); ?>"
                                   class="regular-text" placeholder="مثلا : 09944338956  ">
                            <p class="description">شماره تماس در هدر و فوتر قسمت درباره مجموعه نمایش داده خواهد شد</p>
                        </td>
                    </tr>
                
                </table>
                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات درباره مجموعه">
                </p>
            </form>
         
        <?php elseif ($current_tab === 'bale_bot') : ?>
            <?php include SC_TEMPLATES_ADMIN_DIR . 'settings-tab-bale-bot.php'; ?>

        <?php elseif ($current_tab === 'header_footer') : ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>
                <h3>جستجوی هدر</h3>
                <table class="form-table">
             
                    <tr>
                        <th scope="row"><label for="sc_header_search_placeholder">متن راهنمای باکس جستجو</label></th>
                        <td>
                            <input type="text" name="sc_header_search_placeholder" id="sc_header_search_placeholder"
                                   value="<?php echo esc_attr($sc_header_search_placeholder); ?>"
                                   class="regular-text" placeholder="جستجو در خدمات، صفحات و فروشگاه…">
                            <p class="description">در وسط هدر (دسکتاپ) و پنجره جستجو (موبایل) نمایش داده می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_header_search_suggestions">پیشنهادهای اولیه</label></th>
                        <td>
                            <textarea name="sc_header_search_suggestions" id="sc_header_search_suggestions" rows="12" class="large-text code" placeholder="عنوان لینک|https://..."><?php echo esc_textarea($sc_header_search_suggestions_lines); ?></textarea>
                            <p class="description">هر خط یک پیشنهاد؛ فرمت: <code>عنوان نمایشی|آدرس کامل</code> (مثال: <code>ثبت‌نام دوره|<?php echo esc_html(home_url('/')); ?></code>). با فوکوس روی جستجو، قبل از تایپ این موارد نشان داده می‌شوند.</p>
                        </td>
                    </tr>
                </table>

                <?php
                $sc_saved_search_kw = function_exists('sc_header_search_get_saved_keywords_map') ? sc_header_search_get_saved_keywords_map() : array();
                $sc_hs_registry = function_exists('sc_header_search_link_targets_map') ? sc_header_search_link_targets_map() : array();
                $sc_hs_site_rows = array();
                $sc_hs_account_rows = array();
                foreach ($sc_hs_registry as $reg_key => $reg_info) {
                    $g = isset($reg_info['group']) ? $reg_info['group'] : 'account';
                    if ($g === 'site') {
                        $sc_hs_site_rows[$reg_key] = $reg_info;
                    } else {
                        $sc_hs_account_rows[$reg_key] = $reg_info;
                    }
                }
                ?>

                <h3 style="margin-top:24px;">کلمات کلیدی جستجو — خدمات، فروشگاه و پنل کاربری</h3>
                <p class="description">جستجوی هدر به‌صورت خودکار دوره‌های گروهی، رویدادها، برگه‌ها و نوشته‌های منتشرشده را بر اساس متنی که کاربر تایپ می‌کند پیدا می‌کند؛ اینجا فقط کلمات تکمیلی بگذارید تا سریع‌تر به مقاصد ثابت (فروشگاه، ثبت‌نام، تیکت، کیف پول و …) برسد — هر کلمه را با ویرگول یا خط جدید جدا کنید.</p>

                <table class="widefat striped sc_table_search_custom" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th scope="col" style="width:32%;">بخش</th>
                            <th scope="col">کلمات کلیدی</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sc_hs_site_rows)) : ?>
                            <tr><td colspan="2"><strong>سایت و فروشگاه عمومی</strong></td></tr>
                            <?php foreach ($sc_hs_site_rows as $rk => $ri) :
                                $kw_val = isset($sc_saved_search_kw[$rk]) ? $sc_saved_search_kw[$rk] : '';
                                ?>
                                <tr>
                                    <td style="vertical-align:top;">
                                        <strong><?php echo esc_html($ri['title']); ?></strong>
                                        <p class="description" style="margin:6px 0 0;word-break:break-all;"><a href="<?php echo esc_url($ri['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($ri['url']); ?></a></p>
                                    </td>
                                    <td>
                                        <textarea name="sc_search_keywords[<?php echo esc_attr($rk); ?>]" rows="2" class="large-text" placeholder="مثال: خرید، محصول، فروشگاه"><?php echo esc_textarea($kw_val); ?></textarea>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($sc_hs_account_rows)) : ?>
                            <tr><td colspan="2"><strong>پنل کاربری و خدمات باشگاه</strong></td></tr>
                            <?php foreach ($sc_hs_account_rows as $rk => $ri) :
                                $kw_val = isset($sc_saved_search_kw[$rk]) ? $sc_saved_search_kw[$rk] : '';
                                ?>
                                <tr>
                                    <td style="vertical-align:top;">
                                        <strong><?php echo esc_html($ri['title']); ?></strong>
                                        <p class="description" style="margin:6px 0 0;word-break:break-all;"><a href="<?php echo esc_url($ri['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($ri['url']); ?></a></p>
                                    </td>
                                    <td>
                                        <textarea name="sc_search_keywords[<?php echo esc_attr($rk); ?>]" rows="2" class="large-text" placeholder="کلمات مرتبط با این بخش"><?php echo esc_textarea($kw_val); ?></textarea>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php
                        $sc_pages_for_search_kw = get_posts(array(
                            'post_type'      => 'page',
                            'post_status'    => 'publish',
                            'posts_per_page' => -1,
                            'orderby'        => 'title',
                            'order'          => 'ASC',
                            'no_found_rows'  => true,
                        ));
                        ?>
                        <?php if (!empty($sc_pages_for_search_kw)) : ?>
                            <tr><td colspan="2"><strong>صفحات وردپرس</strong></td></tr>
                            <?php foreach ($sc_pages_for_search_kw as $sc_pg) :
                                $pk = 'page_' . (int) $sc_pg->ID;
                                $kw_val = isset($sc_saved_search_kw[$pk]) ? $sc_saved_search_kw[$pk] : '';
                                if ($kw_val === '' && isset($sc_saved_search_kw[(string) $sc_pg->ID])) {
                                    $kw_val = $sc_saved_search_kw[(string) $sc_pg->ID];
                                }
                                ?>
                                <tr>
                                    <td style="vertical-align:top;">
                                        <strong><?php echo esc_html(get_the_title($sc_pg)); ?></strong>
                                        <p class="description" style="margin:6px 0 0;">
                                            <a href="<?php echo esc_url(get_permalink($sc_pg)); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(wp_trim_words(get_permalink($sc_pg), 14, '…')); ?></a>
                                        </p>
                                    </td>
                                    <td>
                                        <textarea name="sc_search_keywords[<?php echo esc_attr($pk); ?>]" rows="2" class="large-text" placeholder="کلمات مخصوص این صفحه"><?php echo esc_textarea($kw_val); ?></textarea>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="2"><?php esc_html_e('صفحهٔ منتشرشده‌ای در وردپرس نیست.', 'sportclub-manager'); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($sc_can_edit_footer_texts) : ?>
                <h3 style="margin-top:24px;">متن فوتر</h3>
                <p class="description">این بخش فقط توسط مدیر کل سایت قابل ویرایش است. در خط اول می‌توانید از <code>{year}</code> برای نمایش سال جاری استفاده کنید.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sc_footer_text_line1">متن خط اول فوتر</label></th>
                        <td>
                            <textarea name="sc_footer_text_line1" id="sc_footer_text_line1" rows="2" class="large-text"><?php echo esc_textarea($sc_footer_text_line1); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_footer_text_line2">متن خط دوم فوتر</label></th>
                        <td>
                            <textarea name="sc_footer_text_line2" id="sc_footer_text_line2" rows="2" class="large-text"><?php echo esc_textarea($sc_footer_text_line2); ?></textarea>
                        </td>
                    </tr>
                </table>
                <?php endif; ?>

                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات هدر و فوتر">
                </p>
            </form>

        <?php elseif ($current_tab === 'sms') : ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>

                <!-- API Settings -->
                <h3>تنظیمات API پیامک</h3>
                <table class="form-table  ">
                    <tr>
                        <th scope="row">
                            <label for="sms_api_key">API Key</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="sms_api_key"
                                   id="sms_api_key"
                                   value="<?php echo esc_attr($sms_api_key); ?>"
                                   class="regular-text"
                                   placeholder="API Key پنل sms.ir">
                            <p class="description">API Key که از پنل sms.ir دریافت کرده‌اید.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sms_sender">شماره ارسال کننده</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="sms_sender"
                                   id="sms_sender"
                                   value="<?php echo esc_attr($sms_sender); ?>"
                                   class="regular-text"
                                   placeholder="مثال: 1000123456">
                            <p class="description">شماره اختصاصی شما در پنل sms.ir.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sms_admin_phone">شماره مدیر</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="sms_admin_phone"
                                   id="sms_admin_phone"
                                   value="<?php echo esc_attr($sms_admin_phone); ?>"
                                   class="regular-text"
                                   placeholder="مثال: 09123456789">
                            <p class="description">شماره موبایل مدیر برای دریافت پیامک‌های اطلاع‌رسانی.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">اعتبار پنل</th>
                        <td>
                            <?php
                            $credit_result = sc_get_sms_credit();
                            if ($credit_result['success']) {
                                $sms_count = floor($credit_result['credit']); // گرد کردن به پایین
                                $monetary_value = $sms_count * 219; // محاسبه ارزش ریالی (۲۱۹ تومان هر پیامک)
                                echo '<span style="color: green; font-weight: bold;">' . esc_html($sms_count) . ' پیامک</span>';
                                echo '<br><small style="color: #666;">معادل ' . number_format($monetary_value, 0) . ' تومان</small>';
                            } else {
                                echo '<span style="color: red;">' . esc_html($credit_result['message']) . '</span>';
                            }
                            ?>
                            <p class="description">میزان اعتبار باقی‌مانده در پنل sms.ir</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sms_reminder_delay_minutes">مدت زمان یادآوری پرداخت</label>
                        </th>
                        <td>
                            <input type="number"
                                   name="sms_reminder_delay_minutes"
                                   id="sms_reminder_delay_minutes"
                                   value="<?php echo esc_attr($sms_reminder_delay_minutes); ?>"
                                   min="1"
                                   class="regular-text"
                                   required>
                            <p class="description">مدت زمان به دقیقه که بعد از ایجاد صورت حساب، پیامک یادآوری ارسال شود. (پیش‌فرض: 4320 دقیقه = 3 روز)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sms_cost_per_message">هزینه هر پیامک (تومان)</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="sms_cost_per_message"
                                   id="sms_cost_per_message"
                                   value="<?php echo esc_attr(number_format($sms_cost_per_message, 0)); ?>"
                                   class="regular-text"
                                   placeholder="200">
                            <p class="description">هزینه تقریبی هر پیامک برای نمایش در فرم افزودن اطلاعیه.</p>
                        </td>
                    </tr>
                </table>

                <style>
                    .sc-sms-section-selector {
                        margin: 16px 0 20px;
                        padding: 14px 16px;
                        border: 1px solid #dcdcde;
                        border-radius: 8px;
                        background: #fff;
                    }

                    .sc-sms-section-selector strong {
                        display: block;
                        margin-bottom: 10px;
                    }

                    .sc-sms-checkbox-list {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 10px 18px;
                    }

                    .sc-sms-checkbox-list label {
                        min-width: 210px;
                    }

                    .sc-sms-message-section {
                        display: none;
                        margin: 0 0 18px;
                        padding: 16px 18px;
                        border: 1px solid #dcdcde;
                        border-radius: 10px;
                        background: #fff;
                    }

                    .sc-sms-message-section h3 {
                        margin-top: 0;
                        margin-bottom: 12px;
                        padding-bottom: 8px;
                        border-bottom: 1px solid #f0f0f1;
                    }

                    .sc-sms-message-section .form-table {
                        margin-top: 0;
                    }
                </style>

                <!-- Master SMS Enable/Disable -->
                <table class="form-table" style="margin-bottom:20px; background:#fff3cd; padding:15px; border:1px solid #f0c36d; border-radius:6px;">
                    <tr>
                        <th scope="row" style="width:200px; vertical-align:middle;">
                            <label for="sms_master_enabled"><strong>وضعیت کلی ارسال پیامک</strong></label>
                        </th>
                        <td>
                            <label style="font-size:15px;">
                                <input type="checkbox"
                                       id="sms_master_enabled"
                                       name="sms_master_enabled"
                                       value="1"
                                       <?php checked($sms_master_enabled, 1); ?>>
                                <strong style="color:#d63638;">فعال کردن ارسال پیامک</strong>
                            </label>
                            <p class="description" style="margin-top:8px; color:#b32d2e; font-weight:500;">
                                ⚠️ با غیرفعال کردن این گزینه، <strong>تمامی پیامک‌های خودکار</strong> (از جمله پیامک‌های ورود، عضویت، صورت‌حساب، ثبت‌نام، غیبت، احراز هویت و ...) متوقف خواهند شد.
                                حتی اگر الگوها فعال باشند، هیچ پیامکی ارسال نمی‌شود.
                            </p>
                        </td>
                    </tr>
                </table>

                <div class="sc-sms-section-selector">
                    <strong>نمایش بخش‌های تنظیمات پیامک</strong>
                    <div class="sc-sms-checkbox-list">
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="invoice"> پیامک صورت حساب</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="wc-product"> پیامک محصول (ووکامرس)</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="enrollment"> پیامک ثبت نام</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="course-capacity-waitlist"> پیامک خالی شدن ظرفیت دوره</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="reminder"> پیامک یادآوری پرداخت</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="absence"> پیامک غیبت</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="absence-alert"> پیامک هشدار غیبت</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="birthday"> پیامک تولد</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="insurance"> پیامک انقضای بیمه</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="identity"> پیامک تایید احراز هویت</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="certificate"> پیامک صدور گواهینامه</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="wallet"> پیامک کیف پول</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="private-class-cancel"> پیامک لغو جلسات خصوصی</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="private-class-booking"> پیامک رزرو کلاس خصوصی</label>
                        <label><input type="checkbox" class="sc-sms-section-toggle" data-target="ticket"> پیامک تیکت پشتیبانی</label>
                    </div>
                    <p class="description" style="margin: 10px 0 0;">برای نمایش تنظیمات هر بخش، تیک همان بخش را بزنید.</p>
                </div>

                <div class="sc-sms-message-section" data-section="invoice">
                <!-- Invoice SMS Settings -->
                <h3>پیامک صورت حساب</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">پیامک به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_invoice_user_enabled"
                                       value="1"
                                       <?php checked($sms_invoice_user_enabled, 1); ?>>
                                فعال کردن پیامک صورت حساب به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_invoice_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به کاربر"><?php echo esc_textarea($sms_invoice_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% -
                                نام رویداد = %event_name% -
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount% - 
                                تاریخ سررسید = %due_date%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_invoice_user_pattern"
                                   value="<?php echo esc_attr($sms_invoice_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">پیامک به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_invoice_admin_enabled"
                                       value="1"
                                       <?php checked($sms_invoice_admin_enabled, 1); ?>>
                                فعال کردن پیامک صورت حساب به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_invoice_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به مدیر"><?php echo esc_textarea($sms_invoice_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount% - 
                                تاریخ سررسید = %due_date%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_invoice_admin_pattern"
                                   value="<?php echo esc_attr($sms_invoice_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <!-- Additional Invoice States: Cancelled -->
                    <tr>
                        <th scope="row">پیامک لغو شده به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_invoice_cancelled_user_enabled"
                                       value="1"
                                       <?php checked($sms_invoice_cancelled_user_enabled, 1); ?>>
                                فعال کردن پیامک لغو صورت حساب به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_invoice_cancelled_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک لغو به کاربر"><?php echo esc_textarea($sms_invoice_cancelled_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام آیتم = %item_name% - 
                                مبلغ = %amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_invoice_cancelled_user_pattern"
                                   value="<?php echo esc_attr($sms_invoice_cancelled_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">پیامک لغو شده به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_invoice_cancelled_admin_enabled"
                                       value="1"
                                       <?php checked($sms_invoice_cancelled_admin_enabled, 1); ?>>
                                فعال کردن پیامک لغو صورت حساب به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_invoice_cancelled_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک لغو به مدیر"><?php echo esc_textarea($sms_invoice_cancelled_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام آیتم = %item_name% - 
                                مبلغ = %amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_invoice_cancelled_admin_pattern"
                                   value="<?php echo esc_attr($sms_invoice_cancelled_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir</p>
                        </td>
                    </tr>

                    <!-- Additional Invoice States: On-hold / Pending review -->
                    <tr>
                        <th scope="row">پیامک در انتظار بررسی به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_invoice_onhold_user_enabled"
                                       value="1"
                                       <?php checked($sms_invoice_onhold_user_enabled, 1); ?>>
                                فعال کردن پیامک در انتظار بررسی صورت حساب به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_invoice_onhold_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک در انتظار بررسی به کاربر"><?php echo esc_textarea($sms_invoice_onhold_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام آیتم = %item_name% - 
                                مبلغ = %amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_invoice_onhold_user_pattern"
                                   value="<?php echo esc_attr($sms_invoice_onhold_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">پیامک در انتظار بررسی به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_invoice_onhold_admin_enabled"
                                       value="1"
                                       <?php checked($sms_invoice_onhold_admin_enabled, 1); ?>>
                                فعال کردن پیامک در انتظار بررسی صورت حساب به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_invoice_onhold_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک در انتظار بررسی به مدیر"><?php echo esc_textarea($sms_invoice_onhold_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام آیتم = %item_name% - 
                                مبلغ = %amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_invoice_onhold_admin_pattern"
                                   value="<?php echo esc_attr($sms_invoice_onhold_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir</p>
                        </td>
                    </tr>
                    <!-- Paid row -->
                    <tr>
                        <th scope="row">پیامک پرداخت شده به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_invoice_paid_user_enabled"
                                       value="1"
                                       <?php checked($sms_invoice_paid_user_enabled, 1); ?>>
                                فعال کردن پیامک پرداخت شده صورت حساب به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_invoice_paid_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک پرداخت شده به کاربر"><?php echo esc_textarea($sms_invoice_paid_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام آیتم = %item_name% - 
                                مبلغ = %amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_invoice_paid_user_pattern"
                                   value="<?php echo esc_attr($sms_invoice_paid_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">پیامک پرداخت شده به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_invoice_paid_admin_enabled"
                                       value="1"
                                       <?php checked($sms_invoice_paid_admin_enabled, 1); ?>>
                                فعال کردن پیامک پرداخت شده صورت حساب به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_invoice_paid_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک پرداخت شده به مدیر"><?php echo esc_textarea($sms_invoice_paid_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام آیتم = %item_name% - 
                                مبلغ = %amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_invoice_paid_admin_pattern"
                                   value="<?php echo esc_attr($sms_invoice_paid_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir</p>
                        </td>
                    </tr>

                </table>

                </div>
                <!-- WooCommerce Product Order SMS Section -->
                <div class="sc-sms-message-section" data-section="wc-product">
                <h3>پیامک محصول (سفارشات ووکامرس)</h3>
                <p class="description" style="margin-bottom:15px;">
                    سفارش‌های مربوط به <strong>دوره، رویداد و صورت‌حساب داخلی</strong> از بخش «پیامک صورت حساب» پیام می‌گیرند و اینجا پیامک نمی‌گیرند.
                    بخش زیر فقط برای سفارش‌های مستقیم فروشگاه (محصول فیزیکی، مجازی یا دانلودی) است.
                </p>
                <h4 style="margin:20px 0 10px;">محصول فیزیکی (غیرمجازی)</h4>
                <table class="form-table">
                    <!-- Completed -->
                    <tr>
                        <th scope="row">پیامک تکمیل‌شده به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wc_order_completed_user_enabled"
                                       value="1"
                                       <?php checked($sms_wc_order_completed_user_enabled, 1); ?>>
                                فعال کردن پیامک سفارش تکمیل‌شده به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_wc_order_completed_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wc_order_completed_user_template); ?></textarea>
                            <p class="description">متغیرها: %user_name%، %order_id%، %amount%</p>
                            <br>
                            <input type="number" name="sms_wc_order_completed_user_pattern" value="<?php echo esc_attr($sms_wc_order_completed_user_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">پیامک تکمیل‌شده به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wc_order_completed_admin_enabled"
                                       value="1"
                                       <?php checked($sms_wc_order_completed_admin_enabled, 1); ?>>
                                فعال کردن پیامک سفارش تکمیل‌شده به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_wc_order_completed_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wc_order_completed_admin_template); ?></textarea>
                            <p class="description">متغیرها: %user_name%، %order_id%، %amount%</p>
                            <br>
                            <input type="number" name="sms_wc_order_completed_admin_pattern" value="<?php echo esc_attr($sms_wc_order_completed_admin_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>

                    <!-- Cancelled -->
                    <tr>
                        <th scope="row">پیامک لغو‌شده به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wc_order_cancelled_user_enabled"
                                       value="1"
                                       <?php checked($sms_wc_order_cancelled_user_enabled, 1); ?>>
                                فعال کردن پیامک سفارش لغو‌شده به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_wc_order_cancelled_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wc_order_cancelled_user_template); ?></textarea>
                            <p class="description">متغیرها: %user_name%، %order_id%</p>
                            <br>
                            <input type="number" name="sms_wc_order_cancelled_user_pattern" value="<?php echo esc_attr($sms_wc_order_cancelled_user_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">پیامک لغو‌شده به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wc_order_cancelled_admin_enabled"
                                       value="1"
                                       <?php checked($sms_wc_order_cancelled_admin_enabled, 1); ?>>
                                فعال کردن پیامک سفارش لغو‌شده به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_wc_order_cancelled_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wc_order_cancelled_admin_template); ?></textarea>
                            <p class="description">متغیرها: %user_name%، %order_id%</p>
                            <br>
                            <input type="number" name="sms_wc_order_cancelled_admin_pattern" value="<?php echo esc_attr($sms_wc_order_cancelled_admin_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>

                    <!-- On-hold / Pending -->
                    <tr>
                        <th scope="row">پیامک در انتظار بررسی به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wc_order_onhold_user_enabled"
                                       value="1"
                                       <?php checked($sms_wc_order_onhold_user_enabled, 1); ?>>
                                فعال کردن پیامک سفارش در انتظار بررسی به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_wc_order_onhold_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wc_order_onhold_user_template); ?></textarea>
                            <p class="description">متغیرها: %user_name%، %order_id%</p>
                            <br>
                            <input type="number" name="sms_wc_order_onhold_user_pattern" value="<?php echo esc_attr($sms_wc_order_onhold_user_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">پیامک در انتظار بررسی به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wc_order_onhold_admin_enabled"
                                       value="1"
                                       <?php checked($sms_wc_order_onhold_admin_enabled, 1); ?>>
                                فعال کردن پیامک سفارش در انتظار بررسی به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_wc_order_onhold_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wc_order_onhold_admin_template); ?></textarea>
                            <p class="description">متغیرها: %user_name%، %order_id%</p>
                            <br>
                            <input type="number" name="sms_wc_order_onhold_admin_pattern" value="<?php echo esc_attr($sms_wc_order_onhold_admin_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>

                    <!-- Failed -->
                    <tr>
                        <th scope="row">پیامک ناموفق به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wc_order_failed_user_enabled"
                                       value="1"
                                       <?php checked($sms_wc_order_failed_user_enabled, 1); ?>>
                                فعال کردن پیامک سفارش ناموفق به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_wc_order_failed_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wc_order_failed_user_template); ?></textarea>
                            <p class="description">متغیرها: %user_name%، %order_id%</p>
                            <br>
                            <input type="number" name="sms_wc_order_failed_user_pattern" value="<?php echo esc_attr($sms_wc_order_failed_user_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">پیامک ناموفق به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wc_order_failed_admin_enabled"
                                       value="1"
                                       <?php checked($sms_wc_order_failed_admin_enabled, 1); ?>>
                                فعال کردن پیامک سفارش ناموفق به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_wc_order_failed_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wc_order_failed_admin_template); ?></textarea>
                            <p class="description">متغیرها: %user_name%، %order_id%</p>
                            <br>
                            <input type="number" name="sms_wc_order_failed_admin_pattern" value="<?php echo esc_attr($sms_wc_order_failed_admin_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>
                </table>

                <h4 style="margin:30px 0 10px;">محصول مجازی / دانلودی</h4>
                <p class="description" style="margin-bottom:15px;">
                    اگر سفارش حداقل یک محصول مجازی یا دانلودی داشته باشد، به‌جای الگوی محصول فیزیکی از این بخش استفاده می‌شود.
                    متغیرهای اضافه: %product_names%، %item_name%
                </p>
                <table class="form-table">
                    <?php foreach ($sc_wc_virtual_sms_statuses as $sc_wc_virtual_status) : ?>
                        <?php foreach (['user', 'admin'] as $sc_wc_virtual_recipient) :
                            $sc_wc_virtual_prefix = 'sms_wc_order_virtual_' . $sc_wc_virtual_status . '_' . $sc_wc_virtual_recipient;
                            $sc_wc_virtual_row = $sc_wc_virtual_sms[$sc_wc_virtual_status][$sc_wc_virtual_recipient];
                        ?>
                    <tr>
                        <th scope="row">پیامک <?php echo esc_html($sc_wc_virtual_sms_labels[$sc_wc_virtual_status]); ?> به <?php echo esc_html($sc_wc_virtual_sms_recipient_labels[$sc_wc_virtual_recipient]); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr($sc_wc_virtual_prefix . '_enabled'); ?>"
                                       value="1"
                                       <?php checked($sc_wc_virtual_row['enabled'], 1); ?>>
                                فعال کردن پیامک سفارش <?php echo esc_html($sc_wc_virtual_sms_labels[$sc_wc_virtual_status]); ?> (مجازی/دانلودی) به <?php echo esc_html($sc_wc_virtual_sms_recipient_labels[$sc_wc_virtual_recipient]); ?>
                            </label>
                            <br><br>
                            <textarea name="<?php echo esc_attr($sc_wc_virtual_prefix . '_template'); ?>"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sc_wc_virtual_row['template']); ?></textarea>
                            <p class="description">متغیرها: %user_name%، %order_id%، %amount%، %product_names%، %item_name%</p>
                            <br>
                            <input type="number"
                                   name="<?php echo esc_attr($sc_wc_virtual_prefix . '_pattern'); ?>"
                                   value="<?php echo esc_attr($sc_wc_virtual_row['pattern']); ?>"
                                   class="small-text"
                                   placeholder="کد پترن">
                        </td>
                    </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </table>
                </div>

                <div class="sc-sms-message-section" data-section="enrollment">
                <!-- Enrollment SMS Settings -->
                <h3>پیامک ثبت نام</h3>
                <table class="form-table  ">
                    <tr>
                        <th scope="row">پیامک به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_enrollment_user_enabled"
                                       value="1"
                                       <?php checked($sms_enrollment_user_enabled, 1); ?>>
                                فعال کردن پیامک ثبت نام به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_enrollment_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به کاربر"><?php echo esc_textarea($sms_enrollment_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده: - 
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_enrollment_user_pattern"
                                   value="<?php echo esc_attr($sms_enrollment_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">پیامک به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_enrollment_admin_enabled"
                                       value="1"
                                       <?php checked($sms_enrollment_admin_enabled, 1); ?>>
                                فعال کردن پیامک ثبت نام به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_enrollment_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به مدیر"><?php echo esc_textarea($sms_enrollment_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_enrollment_admin_pattern"
                                   value="<?php echo esc_attr($sms_enrollment_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                </div>
                <div class="sc-sms-message-section" data-section="course-capacity-waitlist">
                <h3>پیامک خالی شدن ظرفیت دوره</h3>
                <p class="description">هنگامی که کاربر روی «اطلاع‌رسانی در صورت خالی شدن ظرفیت» بزند و بعداً ظرفیت دوره باز شود، این متن به او ارسال می‌شود (در کنار اعلان داخل پنل).</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">پیامک به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_course_capacity_waitlist_user_enabled"
                                       value="1"
                                       <?php checked($sms_course_capacity_waitlist_user_enabled, 1); ?>>
                                فعال کردن پیامک اطلاع‌رسانی ظرفیت
                            </label>
                            <br><br>
                            <textarea name="sms_course_capacity_waitlist_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_course_capacity_waitlist_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% — نام دوره = %course_name% یا %item_name% — لینک ثبت‌نام = %enroll_url%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_course_capacity_waitlist_user_pattern"
                                   value="<?php echo esc_attr($sms_course_capacity_waitlist_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                </div>
                <div class="sc-sms-message-section" data-section="reminder">
                <!-- Reminder SMS Settings -->
                <h3>پیامک یادآوری پرداخت</h3>
                <table class="form-table  ">
                    <tr>
                        <th scope="row">پیامک به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_reminder_user_enabled"
                                       value="1"
                                       <?php checked($sms_reminder_user_enabled, 1); ?>>
                                فعال کردن پیامک یادآوری به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_reminder_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به کاربر"><?php echo esc_textarea($sms_reminder_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount% - 
                                مبلغ جریمه = %penalty_amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_reminder_user_pattern"
                                   value="<?php echo esc_attr($sms_reminder_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">پیامک به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_reminder_admin_enabled"
                                       value="1"
                                       <?php checked($sms_reminder_admin_enabled, 1); ?>>
                                فعال کردن پیامک یادآوری به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_reminder_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به مدیر"><?php echo esc_textarea($sms_reminder_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount% - 
                                مبلغ جریمه = %penalty_amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_reminder_user_pattern"
                                   value="<?php echo esc_attr($sms_reminder_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                </div>
                <div class="sc-sms-message-section" data-section="absence">
                <!-- Absence SMS Settings -->
                <h3>پیامک غیبت</h3>
                <table class="form-table  ">
                    <tr>
                        <th scope="row">پیامک به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_absence_user_enabled"
                                       value="1"
                                       <?php checked($sms_absence_user_enabled, 1); ?>>
                                فعال کردن پیامک غیبت به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_absence_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به کاربر"><?php echo esc_textarea($sms_absence_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                تاریخ = %date%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_absence_user_pattern"
                                   value="<?php echo esc_attr($sms_absence_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">پیامک به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_absence_admin_enabled"
                                       value="1"
                                       <?php checked($sms_absence_admin_enabled, 1); ?>>
                                فعال کردن پیامک غیبت به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_absence_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به مدیر"><?php echo esc_textarea($sms_absence_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                تاریخ = %date%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_absence_admin_pattern"
                                   value="<?php echo esc_attr($sms_absence_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                </div>
                <div class="sc-sms-message-section" data-section="absence-alert">
                <h3>پیامک هشدار غیبت (عبور از حد مجاز)</h3>
                <table class="form-table">
                    <tr>

                        <th scope="row">پیامک هشدار به کاربر</th>

                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_absence_alert_user_enabled"
                                       value="1"
                                       <?php checked($sms_absence_alert_user_enabled, 1); ?>>
                    فعال کردن پیامک هشدار غیبت به کاربر


                            </label>
                            <br><br>
                            <textarea name="sms_absence_alert_user_template"
                                      rows="3"
                                      class="large-text"

                                      placeholder="متن پیامک هشدار به کاربر"><?php echo esc_textarea($sms_absence_alert_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% -
                                نام دوره/آیتم = %item_name% -
                                تعداد غیبت = %absence_count% -
                                حد مجاز = %absence_limit% -
                                تاریخ = %date%

                                      placeholder="متن پیامک هشدار غیبت"><?php echo esc_textarea($sms_absence_alert_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% -
                                نام دوره = %course_name% -
                                نام آیتم = %item_name% -
                                تعداد غیبت = %absence_count% -
                                حد مجاز = %absence_limit% -
                                تاریخ آخرین غیبت = %date%

                            </p>
                            <br>
                            <input type="number"
                                   name="sms_absence_alert_user_pattern"
                                   value="<?php echo esc_attr($sms_absence_alert_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>


                    <tr>
                        <th scope="row">پیامک هشدار به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_absence_alert_admin_enabled"
                                       value="1"
                                       <?php checked($sms_absence_alert_admin_enabled, 1); ?>>

                                فعال کردن پیامک هشدار غیبت به مدیر


                            </label>
                            <br><br>
                            <textarea name="sms_absence_alert_admin_template"
                                      rows="3"
                                      class="large-text"

                                      placeholder="متن پیامک هشدار به مدیر"><?php echo esc_textarea($sms_absence_alert_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% -
                                نام دوره/آیتم = %item_name% -
                                تعداد غیبت = %absence_count% -
                                حد مجاز = %absence_limit% -
                                تاریخ = %date%

                                      placeholder="متن پیامک هشدار غیبت به مدیر"><?php echo esc_textarea($sms_absence_alert_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% -
                                نام دوره = %course_name% -
                                نام آیتم = %item_name% -
                                تعداد غیبت = %absence_count% -
                                حد مجاز = %absence_limit% -
                                تاریخ آخرین غیبت = %date%


                            </p>
                            <br>
                            <input type="number"
                                   name="sms_absence_alert_admin_pattern"
                                   value="<?php echo esc_attr($sms_absence_alert_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                </div>
                <div class="sc-sms-message-section" data-section="birthday">
                <!-- Birthday SMS Settings -->
                <h3>پیامک تولد</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">پیامک تبریک تولد</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_birthday_user_enabled"
                                       value="1"
                                       <?php checked($sms_birthday_user_enabled, 1); ?>>
                                فعال کردن ارسال خودکار پیامک تبریک تولد
                            </label>
                            <p class="description">هر روز به صورت خودکار کاربرانی که امروز تاریخ تولدشان است (بر اساس تاریخ تولد ثبت‌شده در پروفایل) پیامک ارسال می‌شود.</p>
                            <br><br>
                            <textarea name="sms_birthday_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک تبریک تولد"><?php echo esc_textarea($sms_birthday_user_template); ?></textarea>
                            <p class="description">
                                متغیر قابل استفاده: نام کاربر = %user_name%<br>
                                در صورت خالی بودن، متن پیش‌فرض استفاده می‌شود: «کاربر گرامی %user_name%، تولدتان مبارک! باشگاه ورزشی ما این روز را به شما تبریک می‌گوید.»
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_birthday_user_pattern"
                                   value="<?php echo esc_attr($sms_birthday_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                </div>
                <div class="sc-sms-message-section" data-section="insurance">
                <!-- Insurance expiry SMS Settings -->
                <h3>پیامک انقضای بیمه</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">پیامک یادآوری انقضای بیمه</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_insurance_expiry_user_enabled"
                                       value="1"
                                       <?php checked($sms_insurance_expiry_user_enabled, 1); ?>>
                                فعال کردن ارسال خودکار پیامک انقضای بیمه
                            </label>
                            <p class="description">هر روز به صورت خودکار به کاربرانی که <strong>امروز</strong> یا <strong>۱۰ روز دیگر</strong> تاریخ انقضای بیمه‌شان است (بر اساس تاریخ انقضای ثبت‌شده در پروفایل) یک پیامک مشترک ارسال می‌شود.</p>
                            <br><br>
                            <textarea name="sms_insurance_expiry_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_insurance_expiry_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده: نام کاربر = %user_name% | تاریخ انقضا (شمسی) = %expiry_date% | تعداد روز مانده = %days_remaining% (۰ = امروز انقضا، ۱۰ = ده روز مانده)<br>
                                در صورت خالی بودن، متن پیش‌فرض استفاده می‌شود: «کاربر گرامی %user_name%، تاریخ انقضای بیمه شما %expiry_date% است. لطفاً نسبت به تمدید اقدام کنید.»
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_insurance_expiry_user_pattern"
                                   value="<?php echo esc_attr($sms_insurance_expiry_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                </div>
                <div class="sc-sms-message-section" data-section="identity">
                <h3>پیامک تایید احراز هویت</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">پیامک بعد از تایید احراز هویت</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_identity_verified_user_enabled"
                                       value="1"
                                       <?php checked($sms_identity_verified_user_enabled, 1); ?>>
                                فعال کردن ارسال پیامک پس از تایید احراز هویت بازیکن
                            </label>
                            <br><br>
                            <textarea name="sms_identity_verified_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_identity_verified_user_template); ?></textarea>
                            <p class="description">
                                متغیر قابل استفاده: نام کاربر = %user_name%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_identity_verified_user_pattern"
                                   value="<?php echo esc_attr($sms_identity_verified_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">پیامک بعد از رد احراز هویت</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_identity_rejected_user_enabled"
                                       value="1"
                                       <?php checked($sms_identity_rejected_user_enabled, 1); ?>>
                                فعال کردن ارسال پیامک پس از رد احراز هویت بازیکن
                            </label>
                            <br><br>
                            <textarea name="sms_identity_rejected_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_identity_rejected_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name%<br>
                                علت رد = %reason%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_identity_rejected_user_pattern"
                                   value="<?php echo esc_attr($sms_identity_rejected_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                </div>
                <div class="sc-sms-message-section" data-section="certificate">
                <h3>پیامک صدور گواهینامه</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">پیامک پس از صدور گواهینامه</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_certificate_user_enabled"
                                       value="1"
                                       <?php checked($sms_certificate_user_enabled, 1); ?>>
                                فعال کردن ارسال پیامک بعد از صدور گواهینامه
                            </label>
                            <br><br>
                            <textarea name="sms_certificate_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_certificate_user_template); ?></textarea>
                            <p class="description">
                                متغیر قابل استفاده: نام کاربر = %user_name%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_certificate_user_pattern"
                                   value="<?php echo esc_attr($sms_certificate_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                <h3>پیامک ثبت نظرسنجی</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">پیامک به کاربر (بعد از ثبت نهایی)</th>
                        <td>
                            <label><input type="checkbox" name="sms_survey_submission_user_enabled" value="1" <?php checked($sms_survey_submission_user_enabled, 1); ?>> فعال</label>
                            <br><br>
                            <textarea name="sms_survey_submission_user_template" rows="3" class="large-text"><?php echo esc_textarea($sms_survey_submission_user_template); ?></textarea>
                            <p class="description">متغیرها: %user_name% ، %survey_title%</p>
                            <input type="number" name="sms_survey_submission_user_pattern" value="<?php echo esc_attr($sms_survey_submission_user_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">پیامک به مدیر</th>
                        <td>
                            <label><input type="checkbox" name="sms_survey_submission_admin_enabled" value="1" <?php checked($sms_survey_submission_admin_enabled, 1); ?>> فعال</label>
                            <br><br>
                            <textarea name="sms_survey_submission_admin_template" rows="3" class="large-text"><?php echo esc_textarea($sms_survey_submission_admin_template); ?></textarea>
                            <input type="number" name="sms_survey_submission_admin_pattern" value="<?php echo esc_attr($sms_survey_submission_admin_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>
                </table>

                </div>
                <div class="sc-sms-message-section" data-section="wallet">
                <!-- Wallet SMS Settings -->
                <h3>پیامک کیف پول</h3>
                <table class="form-table">
                    <!-- هشدار موجودی کم -->
                    <tr>
                        <th scope="row">هشدار موجودی کم</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wallet_low_balance_user_enabled"
                                       value="1"
                                       <?php checked($sms_wallet_low_balance_user_enabled, 1); ?>>
                                فعال کردن پیامک هشدار موجودی کم
                            </label>
                            <br><br>
                            <textarea name="sms_wallet_low_balance_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wallet_low_balance_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                موجودی = %balance% - 
                                حداقل موجودی = %min_balance%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_wallet_low_balance_user_pattern"
                                   value="<?php echo esc_attr($sms_wallet_low_balance_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <!-- هشدار موجودی منفی -->
                    <tr>
                        <th scope="row">هشدار موجودی منفی</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wallet_negative_balance_user_enabled"
                                       value="1"
                                       <?php checked($sms_wallet_negative_balance_user_enabled, 1); ?>>
                                فعال کردن پیامک هشدار موجودی منفی
                            </label>
                            <br><br>
                            <textarea name="sms_wallet_negative_balance_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wallet_negative_balance_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                موجودی = %balance%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_wallet_negative_balance_user_pattern"
                                   value="<?php echo esc_attr($sms_wallet_negative_balance_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <!-- اعلان شارژ موفق -->
                    <tr>
                        <th scope="row">اعلان شارژ موفق</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wallet_charge_success_user_enabled"
                                       value="1"
                                       <?php checked($sms_wallet_charge_success_user_enabled, 1); ?>>
                                فعال کردن پیامک شارژ موفق
                            </label>
                            <br><br>
                            <textarea name="sms_wallet_charge_success_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wallet_charge_success_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                مبلغ شارژ = %amount% - 
                                موجودی فعلی = %balance%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_wallet_charge_success_user_pattern"
                                   value="<?php echo esc_attr($sms_wallet_charge_success_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <!-- اعلان پرداخت از کیف پول -->
                    <tr>
                        <th scope="row">اعلان پرداخت از کیف پول</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_wallet_payment_user_enabled"
                                       value="1"
                                       <?php checked($sms_wallet_payment_user_enabled, 1); ?>>
                                فعال کردن پیامک پرداخت از کیف پول
                            </label>
                            <br><br>
                            <textarea name="sms_wallet_payment_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_wallet_payment_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                مبلغ پرداخت = %amount% - 
                                موجودی فعلی = %balance% - 
                                شناسه صورت حساب = %invoice_id%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_wallet_payment_user_pattern"
                                   value="<?php echo esc_attr($sms_wallet_payment_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                </div>
                <div class="sc-sms-message-section" data-section="private-class-cancel">
                <h3>پیامک لغو جلسات خصوصی</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">لغو توسط کاربر → پیامک به مربی</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="private_class_sms_user_cancel_to_coach_enabled"
                                       value="1"
                                       <?php checked($private_class_sms_user_cancel_to_coach_enabled, 1); ?>>
                                فعال کردن ارسال پیامک به مربی
                            </label>
                            <br><br>
                            <textarea name="private_class_sms_user_cancel_to_coach_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($private_class_sms_user_cancel_to_coach_template); ?></textarea>
                            <br><br>
                            <input type="number"
                                   name="private_class_sms_user_cancel_to_coach_pattern"
                                   value="<?php echo esc_attr($private_class_sms_user_cancel_to_coach_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">لغو توسط کاربر → پیامک به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="private_class_sms_user_cancel_to_admin_enabled"
                                       value="1"
                                       <?php checked($private_class_sms_user_cancel_to_admin_enabled, 1); ?>>
                                فعال کردن ارسال هشدار پیامکی به مدیر
                            </label>
                            <br><br>
                            <textarea name="private_class_sms_user_cancel_to_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($private_class_sms_user_cancel_to_admin_template); ?></textarea>
                            <br><br>
                            <input type="number"
                                   name="private_class_sms_user_cancel_to_admin_pattern"
                                   value="<?php echo esc_attr($private_class_sms_user_cancel_to_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">شماره مقصد از فیلد «شماره مدیر» در تب پیامک خوانده می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">لغو توسط مربی/مدیر → پیامک به بازیکن</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="private_class_sms_coach_cancel_to_user_enabled"
                                       value="1"
                                       <?php checked($private_class_sms_coach_cancel_to_user_enabled, 1); ?>>
                                فعال کردن ارسال پیامک به بازیکن
                            </label>
                            <br><br>
                            <textarea name="private_class_sms_coach_cancel_to_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($private_class_sms_coach_cancel_to_user_template); ?></textarea>
                            <br><br>
                            <input type="number"
                                   name="private_class_sms_coach_cancel_to_user_pattern"
                                   value="<?php echo esc_attr($private_class_sms_coach_cancel_to_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>
                <p class="description" style="margin-top: 8px;">
                    متغیرهای قابل استفاده:
                    <code>%user_name%</code> -
                    <code>%coach_name%</code> -
                    <code>%item_name%</code> -
                    <code>%date%</code> -
                    <code>%time%</code>
                </p>
                </div>
                <div class="sc-sms-message-section" data-section="private-class-booking">
                <h3>پیامک / بله — رزرو کلاس خصوصی (حالت تایید مدیر)</h3>
                <p class="description" style="margin-bottom:12px;">پیام بله برای کاربر به‌صورت خودکار ارسال می‌شود اگر chat id او در سیستم ثبت شده باشد.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">درخواست کاربر → مدیر</th>
                        <td>
                            <label><input type="checkbox" name="private_class_sms_request_to_admin_enabled" value="1" <?php checked($private_class_sms_request_to_admin_enabled, 1); ?>> پیامک به مدیر</label>
                            <br><br>
                            <textarea name="private_class_sms_request_to_admin_template" rows="2" class="large-text"><?php echo esc_textarea($private_class_sms_request_to_admin_template); ?></textarea>
                            <br><input type="number" name="private_class_sms_request_to_admin_pattern" value="<?php echo esc_attr($private_class_sms_request_to_admin_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">تایید مدیر → کاربر</th>
                        <td>
                            <label><input type="checkbox" name="private_class_sms_approved_to_user_enabled" value="1" <?php checked($private_class_sms_approved_to_user_enabled, 1); ?>> پیامک به کاربر</label>
                            <br><br>
                            <textarea name="private_class_sms_approved_to_user_template" rows="2" class="large-text"><?php echo esc_textarea($private_class_sms_approved_to_user_template); ?></textarea>
                            <br><input type="number" name="private_class_sms_approved_to_user_pattern" value="<?php echo esc_attr($private_class_sms_approved_to_user_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">رد درخواست → کاربر</th>
                        <td>
                            <label><input type="checkbox" name="private_class_sms_rejected_to_user_enabled" value="1" <?php checked($private_class_sms_rejected_to_user_enabled, 1); ?>> پیامک</label>
                            <br><br>
                            <textarea name="private_class_sms_rejected_to_user_template" rows="2" class="large-text"><?php echo esc_textarea($private_class_sms_rejected_to_user_template); ?></textarea>
                            <br><input type="number" name="private_class_sms_rejected_to_user_pattern" value="<?php echo esc_attr($private_class_sms_rejected_to_user_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">فعال‌سازی جلسات (پس از پرداخت) → کاربر</th>
                        <td>
                            <label><input type="checkbox" name="private_class_sms_activated_to_user_enabled" value="1" <?php checked($private_class_sms_activated_to_user_enabled, 1); ?>> پیامک</label>
                            <br><br>
                            <textarea name="private_class_sms_activated_to_user_template" rows="2" class="large-text"><?php echo esc_textarea($private_class_sms_activated_to_user_template); ?></textarea>
                            <br><input type="number" name="private_class_sms_activated_to_user_pattern" value="<?php echo esc_attr($private_class_sms_activated_to_user_pattern); ?>" class="small-text" placeholder="کد پترن">
                        </td>
                    </tr>
                </table>
                <p class="description">متغیرها: <code>%user_name%</code> <code>%coach_name%</code> <code>%item_name%</code> <code>%invoice_id%</code> <code>%booking_id%</code> <code>%status%</code> <code>%chapter%</code> <code>%reason%</code></p>
                </div>
                <div class="sc-sms-message-section" data-section="ticket">
                <!-- Support ticket SMS -->
                <h3>پیامک تیکت پشتیبانی</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">اعلان تیکت جدید به گیرنده (مدیر/مربی)</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_ticket_new_recipient_enabled"
                                       value="1"
                                       <?php checked($sms_ticket_new_recipient_enabled, 1); ?>>
                                فعال کردن پیامک هنگام ارسال تیکت جدید به مدیر یا مربی
                            </label>
                            <br><br>
                            <textarea name="sms_ticket_new_recipient_template"
                                      rows="2"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_ticket_new_recipient_template); ?></textarea>
                            <p class="description">متغیرها: {ticket_id} ، {subject}</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">اعلان پاسخ جدید</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_ticket_reply_enabled"
                                       value="1"
                                       <?php checked($sms_ticket_reply_enabled, 1); ?>>
                                فعال کردن پیامک هنگام ارسال هر پاسخ (به کاربر یا به مدیر/مربی)
                            </label>
                            <br><br>
                            <textarea name="sms_ticket_reply_template"
                                      rows="2"
                                      class="large-text"
                                      placeholder="متن پیامک"><?php echo esc_textarea($sms_ticket_reply_template); ?></textarea>
                            <p class="description">متغیرها: {ticket_id} ، {subject}</p>
                        </td>
                    </tr>
                </table>

                </div>
                <script>
                    (function () {
                        var toggles = document.querySelectorAll('.sc-sms-section-toggle');

                        function updateSection(target, show) {
                            var section = document.querySelector('.sc-sms-message-section[data-section="' + target + '"]');
                            if (section) {
                                section.style.display = show ? 'block' : 'none';
                            }
                        }

                        toggles.forEach(function (toggle) {
                            updateSection(toggle.getAttribute('data-target'), toggle.checked);
                            toggle.addEventListener('change', function () {
                                updateSection(this.getAttribute('data-target'), this.checked);
                            });
                        });
                    })();
                </script>

                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات پیامک">
                </p>
            </form>

        <?php endif; 

        if ($current_tab === 'wallet') : ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">فعال کردن کیف پول</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wallet_enabled" value="1" <?php checked($wallet_enabled, 1); ?>>
                                فعال کردن سیستم کیف پول
                            </label>
                            <p class="description">با فعال کردن این گزینه، کاربران می‌توانند کیف پول خود را شارژ کنند و از آن برای پرداخت صورت حساب‌ها استفاده کنند.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">حداقل مبلغ شارژ</th>
                        <td>
                            <input type="text" 
                                   name="wallet_min_charge" 
                                   id="wallet_min_charge"
                                   value="<?php echo $wallet_min_charge > 0 ? number_format($wallet_min_charge, 0, '.', ',') : ''; ?>" 
                                   class="regular-text" 
                                   dir="ltr" 
                                   inputmode="numeric" 
                                   placeholder="0">
                            <input type="hidden" name="wallet_min_charge_raw" id="wallet_min_charge_raw" value="<?php echo esc_attr($wallet_min_charge); ?>">
                            <p class="description">حداقل مبلغی که کاربر می‌تواند برای شارژ کیف پول خود وارد کند (به تومان).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">حداکثر مبلغ شارژ</th>
                        <td>
                            <input type="text" 
                                   name="wallet_max_charge" 
                                   id="wallet_max_charge"
                                   value="<?php echo $wallet_max_charge > 0 ? number_format($wallet_max_charge, 0, '.', ',') : ''; ?>" 
                                   class="regular-text" 
                                   dir="ltr" 
                                   inputmode="numeric" 
                                   placeholder="0">
                            <input type="hidden" name="wallet_max_charge_raw" id="wallet_max_charge_raw" value="<?php echo esc_attr($wallet_max_charge); ?>">
                            <p class="description">حداکثر مبلغی که کاربر می‌تواند برای شارژ کیف پول خود وارد کند (به تومان). برای بدون محدودیت، مقدار 0 وارد کنید.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">حداکثر موجودی منفی مجاز</th>
                        <td>
                            <input type="text" 
                                   name="wallet_max_negative_balance" 
                                   id="wallet_max_negative_balance"
                                   value="<?php echo $wallet_max_negative_balance > 0 ? number_format($wallet_max_negative_balance, 0, '.', ',') : ''; ?>" 
                                   class="regular-text" 
                                   dir="ltr" 
                                   inputmode="numeric" 
                                   placeholder="0">
                            <input type="hidden" name="wallet_max_negative_balance_raw" id="wallet_max_negative_balance_raw" value="<?php echo esc_attr($wallet_max_negative_balance); ?>">
                            <p class="description">حداکثر موجودی منفی که کاربر می‌تواند داشته باشد (به تومان). برای عدم اجازه موجودی منفی، مقدار 0 وارد کنید. هنگام منفی شدن کیف پول، مدیر از طریق ایمیل مطلع می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">حداقل موجودی برای هشدار</th>
                        <td>
                            <input type="text" 
                                   name="wallet_min_balance_alert" 
                                   id="wallet_min_balance_alert"
                                   value="<?php echo $wallet_min_balance_alert > 0 ? number_format($wallet_min_balance_alert, 0, '.', ',') : ''; ?>" 
                                   class="regular-text" 
                                   dir="ltr" 
                                   inputmode="numeric" 
                                   placeholder="0">
                            <input type="hidden" name="wallet_min_balance_alert_raw" id="wallet_min_balance_alert_raw" value="<?php echo esc_attr($wallet_min_balance_alert); ?>">
                            <p class="description">اگر موجودی کیف پول کاربر کمتر از این مقدار باشد، هشدار نمایش داده می‌شود (به تومان).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">پرداخت جزئی از کیف پول</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wallet_allow_partial_payment" value="1" <?php checked($wallet_allow_partial_payment, 1); ?>>
                                اجازه پرداخت جزئی از کیف پول
                            </label>
                            <p class="description">در صورت فعال بودن، اگر موجودی کیف پول کافی نباشد، کاربر می‌تواند مبلغ موجود را از کیف پول پرداخت کند و مابقی را از درگاه پرداخت.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات کیف پول">
                </p>
            </form>

        <?php endif; 
        if ($current_tab === 'attendance') : ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce');
                $deduction_wallet_enabled = sc_get_setting('deduction_wallet_enabled'); 
                $max_debt_for_attendance = floatval(sc_get_setting('max_debt_for_attendance', '0'));
                $user_alert_absence_limit = (int) sc_get_setting('user_alert_absence_limit', '3');
                $attendance_api_auto_enabled = (int) sc_get_setting('attendance_api_auto_enabled', '1');
                $attendance_api_base_url = (string) sc_get_setting('attendance_api_base_url', 'https://api.hozoran.ir');
                $attendance_api_key = (string) sc_get_setting('attendance_api_key', '');
                $attendance_api_bearer_token = (string) sc_get_setting('attendance_api_bearer_token', '');
                $attendance_grace_before_minutes = (int) sc_get_setting('attendance_grace_before_minutes', '15');
                $attendance_grace_after_minutes = (int) sc_get_setting('attendance_grace_after_minutes', '30');
                $attendance_absent_after_end_minutes = (int) sc_get_setting('attendance_absent_after_end_minutes', '15');
                $user_alert_absence_limit = (int) sc_get_setting('user_alert_absence_limit', '3');
                $attendance_qr_enabled = (int) sc_get_setting('attendance_qr_enabled', '1');
                $attendance_qr_logo_url = (string) sc_get_setting('attendance_qr_logo_url', '');
                $attendance_qr_logo_size_percent = (int) sc_get_setting('attendance_qr_logo_size_percent', '22');
                $attendance_qr_scan_cooldown_ms = (int) sc_get_setting('attendance_qr_scan_cooldown_ms', '300');
                $attendance_qr_show_dashboard = (int) sc_get_setting('attendance_qr_show_dashboard', '1');
                $attendance_qr_sound_success_url = (string) sc_get_setting('attendance_qr_sound_success_url', '');
                $attendance_qr_sound_error_url = (string) sc_get_setting('attendance_qr_sound_error_url', '');
                $attendance_qr_sound_duplicate_url = (string) sc_get_setting('attendance_qr_sound_duplicate_url', '');
                $attendance_qr_sound_not_in_course_url = (string) sc_get_setting('attendance_qr_sound_not_in_course_url', '');
                $attendance_qr_default_sound_success = function_exists('sc_attendance_qr_get_sound_url') ? sc_attendance_qr_get_sound_url('success') : '';
                $attendance_qr_default_sound_error = function_exists('sc_attendance_qr_get_sound_url') ? sc_attendance_qr_get_sound_url('error') : '';
                $attendance_qr_default_sound_duplicate = function_exists('sc_attendance_qr_get_sound_url') ? sc_attendance_qr_get_sound_url('duplicate') : '';
                $attendance_qr_default_sound_not_in_course = function_exists('sc_attendance_qr_get_sound_url') ? sc_attendance_qr_get_sound_url('not_in_course') : '';
                ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">حضور خودکار از لاگ دستگاه</th>
                        <td>
                            <label>
                                <input type="checkbox" name="attendance_api_auto_enabled" value="1" <?php checked($attendance_api_auto_enabled, 1); ?>>
                                فعال (کرون هر ۵ دقیقه + بعد از سینک API)
                            </label>
                            <p class="description">کد عضو در دستگاه باید برابر <code>member_id</code> باشد. برنامهٔ هفتگی هر دوره (۱=شنبه تا ۷=جمعه) با تاریخ میلادی لاگ تطبیق داده می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">آدرس پایه API دستگاه</th>
                        <td>
                            <input type="url" name="attendance_api_base_url" value="<?php echo esc_attr($attendance_api_base_url); ?>" class="regular-text" dir="ltr" placeholder="https://api.hozoran.ir">
                            <p class="description">فقط دامنه/آدرس پایه. مسیرهای <code>/attendance/sync</code> و <code>/attendance/by-date</code> به‌صورت خودکار افزوده می‌شوند.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Key دستگاه</th>
                        <td>
                            <input type="text" name="attendance_api_key" value="<?php echo esc_attr($attendance_api_key); ?>" class="regular-text" dir="ltr" autocomplete="off" placeholder="TEST123">
                            <p class="description">این کلید برای دریافت تردد از API جدید استفاده می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Bearer Token (JWT)</th>
                        <td>
                            <input type="text" name="attendance_api_bearer_token" value="<?php echo esc_attr($attendance_api_bearer_token); ?>" class="regular-text" dir="ltr" autocomplete="off" placeholder="eyJ0eXAiOiJKV1QiLCJhbGciOi...">
                            <p class="description">درخواست‌ها با هدر <code>Authorization: Bearer &lt;token&gt;</code> ارسال می‌شوند (مطابق فایل‌های نمونه).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">حاشیهٔ قبل از شروع کلاس (دقیقه)</th>
                        <td>
                            <input type="number" name="attendance_grace_before_minutes" value="<?php echo esc_attr($attendance_grace_before_minutes); ?>" min="0" max="180" class="small-text">
                            <p class="description">مثلاً ۱۵ یعنی از این مدت قبل از ساعت شروع کلاس، تَگ دستگاه برای حضور پذیرفته می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">حاشیهٔ بعد از پایان کلاس (دقیقه)</th>
                        <td>
                            <input type="number" name="attendance_grace_after_minutes" value="<?php echo esc_attr($attendance_grace_after_minutes); ?>" min="0" max="180" class="small-text">
                            <p class="description">مثلاً ۳۰ یعنی تا این مدت بعد از پایان بازهٔ کلاس، تَگ برای حضور پذیرفته می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ثبت غیبت بعد از پایان کلاس (دقیقه)</th>
                        <td>
                            <input type="number" name="attendance_absent_after_end_minutes" value="<?php echo esc_attr($attendance_absent_after_end_minutes); ?>" min="0" max="240" class="small-text">
                            <p class="description">بعد از گذشت این مدت از <strong>پایان ساعت کلاس</strong>، برای اعضای فعال بدون رکورد حضور برای همان اسلات، غیبت ثبت می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">حد مجاز غیبت برای هشدار</th>
                        <td>
                            <input type="number" name="user_alert_absence_limit" value="<?php echo esc_attr($user_alert_absence_limit); ?>" min="1" max="365" class="small-text">
                            <p class="description">اگر تعداد غیبت یک کاربر در هر دوره از این مقدار بیشتر شود، هشدار سیستمی برای مدیر و کاربر تولید می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">کسر از کیف پول برای حضور و غیاب</th>
                        <td>
                            <label>
                                <input type="checkbox" name="deduction_wallet" value="1" <?php checked($deduction_wallet_enabled, 1); ?>>
                                فعال کردن کسر از کیف پول برای حضور و غیاب
                            </label>
                            <p class="description">در این صورت با هر بار حضور و غیاب کاربر به ازای قیمت هر جلسه دوره از کیف پول بازیکن کسر میگردد.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">حداکثر بدهی منفی مجاز برای عدم ثبت حضور و غیاب</th>
                        <td>
                            <input type="text" 
                                   name="max_debt_for_attendance" 
                                   id="max_debt_for_attendance"
                                   value="<?php echo $max_debt_for_attendance > 0 ? number_format($max_debt_for_attendance, 0, '.', ',') : ''; ?>" 
                                   class="regular-text" 
                                   dir="ltr" 
                                   inputmode="numeric" 
                                   placeholder="0">
                            <input type="hidden" name="max_debt_for_attendance_raw" id="max_debt_for_attendance_raw" value="<?php echo esc_attr($max_debt_for_attendance); ?>">
                            <p class="description">در صورتی که بدهی کل کاربر بیشتر از این مبلغ باشد امکان ثبت رکورد حضور و غیاب برای آن کاربر امکان پذیر نمی باشد.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">حد مجاز غیبت برای هشدار</th>
                        <td>
                            <input type="number"
                                   name="user_alert_absence_limit"
                                   value="<?php echo esc_attr(max(1, $user_alert_absence_limit)); ?>"
                                   min="1"
                                   class="small-text">
                            <p class="description">اگر تعداد غیبت یک بازیکن در یک دوره از این عدد بیشتر شود، در منوی «هشدارهای کاربر» نمایش داده می‌شود.</p>
                        </td>
                    </tr>
                </table>

                <?php if (function_exists('sc_is_pro_feature_attendance_qr_enabled') && sc_is_pro_feature_attendance_qr_enabled()) : ?>
                <h2 class="title sc-settings-section-title">QR حضور و غیاب</h2>
                <p class="description sc-settings-section-desc">هر بازیکن یک کد QR اختصاصی (هش ۶۴ کاراکتری) دریافت می‌کند. مربی با اسکن لایو دوربین، حضور را ثبت می‌کند.</p>
                <table class="form-table sc-attendance-qr-settings">
                    <tr>
                        <th scope="row">فعال‌سازی QR</th>
                        <td>
                            <label>
                                <input type="checkbox" name="attendance_qr_enabled" value="1" <?php checked($attendance_qr_enabled, 1); ?>>
                                اسکن QR در صفحه ثبت حضور و غیاب فعال باشد
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">نمایش در پیشخوان بازیکن</th>
                        <td>
                            <label>
                                <input type="checkbox" name="attendance_qr_show_dashboard" value="1" <?php checked($attendance_qr_show_dashboard, 1); ?>>
                                کارت QR در پیشخوان کاربر نمایش داده شود
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="attendance_qr_logo_url">لوگوی وسط QR</label></th>
                        <td>
                            <div class="sc-lr-media-field">
                                <input type="hidden" name="attendance_qr_logo_url" id="attendance_qr_logo_url" value="<?php echo esc_attr($attendance_qr_logo_url); ?>">
                                <button type="button" class="button" id="attendance_qr_logo_upload">انتخاب لوگو</button>
                                <button type="button" class="button" id="attendance_qr_logo_remove" <?php echo $attendance_qr_logo_url === '' ? ' style="display:none;"' : ''; ?>>حذف</button>
                                <div class="sc-lr-media-preview" id="attendance_qr_logo_preview" style="margin-top:8px;">
                                    <?php if ($attendance_qr_logo_url !== '') : ?>
                                        <img src="<?php echo esc_url($attendance_qr_logo_url); ?>" alt="" style="max-width:120px;height:auto;border:1px solid #ddd;border-radius:4px;">
                                    <?php endif; ?>
                                </div>
                                <p class="description">در صورت خالی بودن، از لوگوی باشگاه (تب درباره باشگاه) استفاده می‌شود. سطح تصحیح خطای QR روی High است تا اسکن با لوگو پایدار بماند.</p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="attendance_qr_logo_size_percent">اندازه لوگو در QR (%)</label></th>
                        <td>
                            <input type="number" name="attendance_qr_logo_size_percent" id="attendance_qr_logo_size_percent" value="<?php echo esc_attr($attendance_qr_logo_size_percent); ?>" min="12" max="30" class="small-text">
                            <p class="description">پیشنهاد: ۱۸ تا ۲۴ درصد. بیش از ۲۵٪ ممکن است اسکن را سخت کند.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="attendance_qr_scan_cooldown_ms">فاصله بین اسکن‌ها (میلی‌ثانیه)</label></th>
                        <td>
                            <input type="number" name="attendance_qr_scan_cooldown_ms" id="attendance_qr_scan_cooldown_ms" value="<?php echo esc_attr($attendance_qr_scan_cooldown_ms); ?>" min="300" max="5000" step="100" class="small-text">
                            <p class="description">فقط برای جلوگیری از ثبت تکراری همان QR در دوربین (پیش‌فرض: ۳۰۰). اسکن بازیکن بعدی بلافاصله انجام می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">صدای تأیید اسکن</th>
                        <td>
                            <div class="sc-lr-media-field">
                                <input type="hidden" name="attendance_qr_sound_success_url" id="attendance_qr_sound_success_url" value="<?php echo esc_attr($attendance_qr_sound_success_url); ?>">
                                <button type="button" class="button sc-qr-sound-upload" data-target="attendance_qr_sound_success_url" data-preview="attendance_qr_sound_success_preview">انتخاب فایل صوتی</button>
                                <button type="button" class="button sc-qr-sound-remove" data-target="attendance_qr_sound_success_url" data-preview="attendance_qr_sound_success_preview" <?php echo $attendance_qr_sound_success_url === '' ? ' style="display:none;"' : ''; ?>>حذف</button>
                                <p class="description">فرمت مجاز: MP3 — پس از اسکن موفق پخش می‌شود.</p>
                                <p class="description" id="attendance_qr_sound_success_preview"><?php echo $attendance_qr_sound_success_url !== '' ? esc_html($attendance_qr_sound_success_url) : 'پیش‌فرض: ' . esc_html($attendance_qr_default_sound_success); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">صدای خطا</th>
                        <td>
                            <div class="sc-lr-media-field">
                                <input type="hidden" name="attendance_qr_sound_error_url" id="attendance_qr_sound_error_url" value="<?php echo esc_attr($attendance_qr_sound_error_url); ?>">
                                <button type="button" class="button sc-qr-sound-upload" data-target="attendance_qr_sound_error_url" data-preview="attendance_qr_sound_error_preview">انتخاب فایل صوتی</button>
                                <button type="button" class="button sc-qr-sound-remove" data-target="attendance_qr_sound_error_url" data-preview="attendance_qr_sound_error_preview" <?php echo $attendance_qr_sound_error_url === '' ? ' style="display:none;"' : ''; ?>>حذف</button>
                                <p class="description">فرمت مجاز: MP3 — هنگام خطای اسکن پخش می‌شود.</p>
                                <p class="description" id="attendance_qr_sound_error_preview"><?php echo $attendance_qr_sound_error_url !== '' ? esc_html($attendance_qr_sound_error_url) : 'پیش‌فرض: ' . esc_html($attendance_qr_default_sound_error); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">صدای ثبت تکراری</th>
                        <td>
                            <div class="sc-lr-media-field">
                                <input type="hidden" name="attendance_qr_sound_duplicate_url" id="attendance_qr_sound_duplicate_url" value="<?php echo esc_attr($attendance_qr_sound_duplicate_url); ?>">
                                <button type="button" class="button sc-qr-sound-upload" data-target="attendance_qr_sound_duplicate_url" data-preview="attendance_qr_sound_duplicate_preview">انتخاب فایل صوتی</button>
                                <button type="button" class="button sc-qr-sound-remove" data-target="attendance_qr_sound_duplicate_url" data-preview="attendance_qr_sound_duplicate_preview" <?php echo $attendance_qr_sound_duplicate_url === '' ? ' style="display:none;"' : ''; ?>>حذف</button>
                                <p class="description">فرمت مجاز: MP3 — هنگام ثبت تکراری پخش می‌شود.</p>
                                <p class="description" id="attendance_qr_sound_duplicate_preview"><?php echo $attendance_qr_sound_duplicate_url !== '' ? esc_html($attendance_qr_sound_duplicate_url) : 'پیش‌فرض: ' . esc_html($attendance_qr_default_sound_duplicate); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">صدای عدم ثبت‌نام در دوره</th>
                        <td>
                            <div class="sc-lr-media-field">
                                <input type="hidden" name="attendance_qr_sound_not_in_course_url" id="attendance_qr_sound_not_in_course_url" value="<?php echo esc_attr($attendance_qr_sound_not_in_course_url); ?>">
                                <button type="button" class="button sc-qr-sound-upload" data-target="attendance_qr_sound_not_in_course_url" data-preview="attendance_qr_sound_not_in_course_preview">انتخاب فایل صوتی</button>
                                <button type="button" class="button sc-qr-sound-remove" data-target="attendance_qr_sound_not_in_course_url" data-preview="attendance_qr_sound_not_in_course_preview" <?php echo $attendance_qr_sound_not_in_course_url === '' ? ' style="display:none;"' : ''; ?>>حذف</button>
                                <p class="description">فرمت مجاز: MP3 — وقتی بازیکن در دوره/گروه انتخاب‌شده ثبت‌نام فعال ندارد پخش می‌شود.</p>
                                <p class="description" id="attendance_qr_sound_not_in_course_preview"><?php echo $attendance_qr_sound_not_in_course_url !== '' ? esc_html($attendance_qr_sound_not_in_course_url) : 'پیش‌فرض: ' . esc_html($attendance_qr_default_sound_not_in_course); ?></p>
                            </div>
                        </td>
                    </tr>
                </table>
                <?php else : ?>
                <div class="notice notice-info inline" style="margin:12px 0 18px;"><p> جهت فعال سازی حضور و غیاب توسط qrcode با پشتیبانی سایت تماس بگیرید.</p></div>
                <?php endif; ?>

                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات حضور و غیاب">
                </p>
            </form>

        <?php endif; 
        if ($current_tab === 'player_info') :
            $player_verification_required = (int) sc_get_setting('player_verification_required', '0');
            $player_sections = function_exists('sc_get_player_info_sections') ? sc_get_player_info_sections() : [];
            $player_builtin_fields = function_exists('sc_get_player_info_builtin_fields') ? sc_get_player_info_builtin_fields() : [];
            $player_field_rules = function_exists('sc_get_player_info_field_rules') ? sc_get_player_info_field_rules() : [];
            $player_custom_fields = function_exists('sc_get_player_info_custom_fields') ? sc_get_player_info_custom_fields() : [];
        ?>
            <div class="sc-player-info-settings-wrap">
            <form method="POST" action="" class="sc-player-info-settings-form">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>

                <div class="sc-player-info-panel sc-finance-panel postbox">
                    <div class="postbox-header"><h2>احراز هویت بازیکن</h2></div>
                    <div class="inside">
                <table class="form-table sc-player-info-form-table">
                    <tr>
                        <th scope="row">اجبار احراز هویت بازیکن</th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" name="player_verification_required" value="1" <?php checked($player_verification_required, 1); ?>>
                                <span class="slider round"></span>
                            </label>
                            <p class="description">در صورت فعال بودن، فقط «بازیکن تیم» تا زمان تایید احراز هویت به بخش «اطلاعات بازیکن» محدود می‌شود. «بازیکن عادی» بدون تکمیل احراز هویت به تمام بخش‌ها دسترسی دارد.</p>
                        </td>
                    </tr>
                </table>
                    </div>
                </div>

                <div class="sc-player-info-panel sc-finance-panel postbox">
                    <div class="postbox-header"><h2>مدیریت فیلدهای پیش‌فرض</h2></div>
                    <div class="inside">
                <p class="description sc-player-info-panel-desc">فقط «کد ملی» و «شماره موبایل بازیکن» همیشه نمایش داده می‌شوند و اجباری هستند.</p>
                <?php foreach ($player_sections as $section_key => $section_label) : ?>
                    <h3 class="sc-player-info-section-title"><?php echo esc_html($section_label); ?></h3>
                    <table class="widefat striped sc-player-info-table">
                        <thead>
                            <tr>
                                <th>فیلد</th>
                                <th style="width: 160px;">نمایش/مخفی</th>
                                <th style="width: 160px;">اجباری/اختیاری</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($player_builtin_fields as $field_key => $field_meta) : ?>
                                <?php if (($field_meta['section'] ?? '') !== $section_key) { continue; } ?>
                                <?php
                                    $is_always_visible = !empty($field_meta['always_visible']);
                                    $is_always_required = !empty($field_meta['always_required']);
                                    $is_visible = isset($player_field_rules[$field_key]['visible']) ? (int) $player_field_rules[$field_key]['visible'] : 1;
                                    $is_required = isset($player_field_rules[$field_key]['required']) ? (int) $player_field_rules[$field_key]['required'] : 0;
                                    if ($is_always_visible) {
                                        $is_visible = 1;
                                    }
                                    if ($is_always_required) {
                                        $is_required = 1;
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html($field_meta['label']); ?>
                                        <?php if ($is_always_visible || $is_always_required) : ?>
                                            <small style="display:block;color:#666;">(ثابت)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   name="player_field_rules[<?php echo esc_attr($field_key); ?>][visible]"
                                                   value="1"
                                                   <?php checked($is_visible, 1); ?>
                                                   <?php disabled($is_always_visible, true); ?>>
                                            نمایش
                                        </label>
                                        <?php if ($is_always_visible) : ?>
                                            <input type="hidden" name="player_field_rules[<?php echo esc_attr($field_key); ?>][visible]" value="1">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   name="player_field_rules[<?php echo esc_attr($field_key); ?>][required]"
                                                   value="1"
                                                   <?php checked($is_required, 1); ?>
                                                   <?php disabled($is_always_required, true); ?>>
                                            اجباری
                                        </label>
                                        <?php if ($is_always_required) : ?>
                                            <input type="hidden" name="player_field_rules[<?php echo esc_attr($field_key); ?>][required]" value="1">
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
                    </div>
                </div>

                <div class="sc-player-info-panel sc-player-custom-fields-panel sc-finance-panel postbox">
                    <div class="postbox-header sc-player-custom-fields-header">
                        <h2>فیلدهای سفارشی اطلاعات بازیکن</h2>
                        <button type="button" class="button button-secondary" id="sc-add-player-custom-field-btn">+ افزودن فیلد</button>
                    </div>
                    <div class="inside">
                <p class="description sc-player-info-panel-desc">نوع‌های مجاز: متن، عکس و چندانتخابی — فیلدهای سفارشی در فرم اطلاعات بازیکن نمایش داده می‌شوند.</p>
                <div id="sc-player-custom-fields-container" class="sc-player-custom-fields-container">
                    <?php foreach ($player_custom_fields as $idx => $custom_field) : ?>
                        <div class="sc-player-custom-field-item" data-index="<?php echo (int) $idx; ?>">
                            <div class="sc-player-custom-field-item-header">
                                <span class="sc-player-custom-field-badge">فیلد سفارشی <?php echo (int) $idx + 1; ?></span>
                                <button type="button" class="button button-small sc-player-custom-remove">حذف</button>
                            </div>
                            <div class="sc-player-custom-field-grid">
                                <div class="sc-player-custom-field-row">
                                    <label>عنوان فیلد</label>
                                    <input type="text" class="regular-text" name="player_custom_fields[<?php echo (int) $idx; ?>][label]" value="<?php echo esc_attr($custom_field['label'] ?? ''); ?>" placeholder="مثلاً: شماره بیمه">
                                </div>
                                <div class="sc-player-custom-field-row">
                                    <label>کلید انگلیسی</label>
                                    <input type="text" class="regular-text" name="player_custom_fields[<?php echo (int) $idx; ?>][key]" value="<?php echo esc_attr($custom_field['key'] ?? ''); ?>" placeholder="insurance_no" dir="ltr">
                                    <p class="description">اختیاری — برای ذخیره در دیتابیس</p>
                                </div>
                                <div class="sc-player-custom-field-row">
                                    <label>بخش نمایش</label>
                                    <select name="player_custom_fields[<?php echo (int) $idx; ?>][section]" class="sc-player-custom-section">
                                        <?php foreach ($player_sections as $sec_key => $sec_label) : ?>
                                            <option value="<?php echo esc_attr($sec_key); ?>" <?php selected(($custom_field['section'] ?? 'additional'), $sec_key); ?>><?php echo esc_html($sec_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="sc-player-custom-field-row">
                                    <label>نوع فیلد</label>
                                    <select name="player_custom_fields[<?php echo (int) $idx; ?>][type]" class="sc-player-custom-type">
                                        <option value="text" <?php selected(($custom_field['type'] ?? 'text'), 'text'); ?>>متن</option>
                                        <option value="image" <?php selected(($custom_field['type'] ?? ''), 'image'); ?>>عکس</option>
                                        <option value="multiselect" <?php selected(($custom_field['type'] ?? ''), 'multiselect'); ?>>چند انتخابی</option>
                                    </select>
                                </div>
                                <div class="sc-player-custom-field-row sc-player-custom-field-row--options" style="<?php echo (($custom_field['type'] ?? '') === 'multiselect') ? '' : 'display:none;'; ?>">
                                    <label>گزینه‌ها</label>
                                    <input type="text" class="regular-text sc-player-custom-options" name="player_custom_fields[<?php echo (int) $idx; ?>][options]" value="<?php echo esc_attr(!empty($custom_field['options']) && is_array($custom_field['options']) ? implode(', ', $custom_field['options']) : ''); ?>" placeholder="گزینه۱, گزینه۲, گزینه۳">
                                    <p class="description">برای نوع چندانتخابی — با ویرگول جدا کنید</p>
                                </div>
                                <div class="sc-player-custom-field-row sc-player-custom-field-row--flags">
                                    <label>تنظیمات</label>
                                    <div class="sc-player-custom-flags">
                                        <label class="sc-player-custom-flag"><input type="checkbox" name="player_custom_fields[<?php echo (int) $idx; ?>][required]" value="1" <?php checked(!empty($custom_field['required'])); ?>> اجباری</label>
                                        <label class="sc-player-custom-flag"><input type="checkbox" name="player_custom_fields[<?php echo (int) $idx; ?>][visible]" value="1" <?php checked(!isset($custom_field['visible']) || !empty($custom_field['visible'])); ?>> نمایش</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="sc-player-custom-fields-empty" id="sc-player-custom-fields-empty"<?php echo !empty($player_custom_fields) ? ' style="display:none;"' : ''; ?>>هنوز فیلد سفارشی ثبت نشده است. روی «افزودن فیلد» کلیک کنید.</p>
                    </div>
                </div>
                <p class="submit sc-player-info-submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات اطلاعات بازیکن">
                </p>
            </form>
            </div>

            <script>
            jQuery(function($) {
                var sections = <?php echo wp_json_encode($player_sections); ?>;
                var customIndex = <?php echo (int) count($player_custom_fields); ?>;

                function sectionOptions() {
                    var html = '';
                    $.each(sections, function(key, label) {
                        html += '<option value="' + key + '">' + label + '</option>';
                    });
                    return html;
                }

                function toggleEmptyState() {
                    var hasItems = $('#sc-player-custom-fields-container .sc-player-custom-field-item').length > 0;
                    if (hasItems) {
                        $('#sc-player-custom-fields-empty').hide();
                    } else if ($('#sc-player-custom-fields-empty').length) {
                        $('#sc-player-custom-fields-empty').show();
                    }
                }

                function renumberBadges() {
                    $('#sc-player-custom-fields-container .sc-player-custom-field-item').each(function(i) {
                        $(this).find('.sc-player-custom-field-badge').text('فیلد سفارشی ' + (i + 1));
                    });
                }

                $(document).on('change', '.sc-player-custom-type', function() {
                    var $row = $(this).closest('.sc-player-custom-field-item');
                    var $opts = $row.find('.sc-player-custom-field-row--options');
                    if ($(this).val() === 'multiselect') {
                        $opts.show();
                    } else {
                        $opts.hide();
                        $row.find('.sc-player-custom-options').val('');
                    }
                });

                $(document).on('click', '#sc-add-player-custom-field-btn', function() {
                    var idx = customIndex++;
                    var html = '' +
                        '<div class="sc-player-custom-field-item" data-index="' + idx + '">' +
                        '<div class="sc-player-custom-field-item-header">' +
                        '<span class="sc-player-custom-field-badge">فیلد سفارشی</span>' +
                        '<button type="button" class="button button-small sc-player-custom-remove">حذف</button>' +
                        '</div>' +
                        '<div class="sc-player-custom-field-grid">' +
                        '<div class="sc-player-custom-field-row"><label>عنوان فیلد</label>' +
                        '<input type="text" class="regular-text" name="player_custom_fields[' + idx + '][label]" placeholder="مثلاً: شماره بیمه"></div>' +
                        '<div class="sc-player-custom-field-row"><label>کلید انگلیسی</label>' +
                        '<input type="text" class="regular-text" name="player_custom_fields[' + idx + '][key]" placeholder="insurance_no" dir="ltr">' +
                        '<p class="description">اختیاری — برای ذخیره در دیتابیس</p></div>' +
                        '<div class="sc-player-custom-field-row"><label>بخش نمایش</label>' +
                        '<select name="player_custom_fields[' + idx + '][section]" class="sc-player-custom-section">' + sectionOptions() + '</select></div>' +
                        '<div class="sc-player-custom-field-row"><label>نوع فیلد</label>' +
                        '<select name="player_custom_fields[' + idx + '][type]" class="sc-player-custom-type">' +
                        '<option value="text">متن</option><option value="image">عکس</option><option value="multiselect">چند انتخابی</option>' +
                        '</select></div>' +
                        '<div class="sc-player-custom-field-row sc-player-custom-field-row--options" style="display:none;"><label>گزینه‌ها</label>' +
                        '<input type="text" class="regular-text sc-player-custom-options" name="player_custom_fields[' + idx + '][options]" placeholder="گزینه۱, گزینه۲, گزینه۳">' +
                        '<p class="description">برای نوع چندانتخابی — با ویرگول جدا کنید</p></div>' +
                        '<div class="sc-player-custom-field-row sc-player-custom-field-row--flags"><label>تنظیمات</label>' +
                        '<div class="sc-player-custom-flags">' +
                        '<label class="sc-player-custom-flag"><input type="checkbox" name="player_custom_fields[' + idx + '][required]" value="1"> اجباری</label>' +
                        '<label class="sc-player-custom-flag"><input type="checkbox" name="player_custom_fields[' + idx + '][visible]" value="1" checked> نمایش</label>' +
                        '</div></div></div></div>';
                    $('#sc-player-custom-fields-container').append(html);
                    renumberBadges();
                    toggleEmptyState();
                });

                $(document).on('click', '.sc-player-custom-remove', function() {
                    $(this).closest('.sc-player-custom-field-item').remove();
                    renumberBadges();
                    toggleEmptyState();
                });

                toggleEmptyState();
            });
            </script>

        <?php endif; 
        if ($current_tab === 'coach_salary') : 
            $coach_min_withdrawal_amount = floatval(sc_get_setting('coach_min_withdrawal_amount', '0'));
            $coach_max_negative_balance = floatval(sc_get_setting('coach_max_negative_balance', '0'));
            $coach_fixed_salary_settlement_day = (int) sc_get_setting('coach_fixed_salary_settlement_day', '0');
            // محاسبه تاریخ میلادی معادل برای ماه جاری و ۲ ماه بعد
            $settlement_gregorian_list = [];
            if ($coach_fixed_salary_settlement_day > 0 && function_exists('gregorian_to_jalali') && function_exists('jalali_to_gregorian')) {
                $now = new DateTime();
                $today_j = gregorian_to_jalali((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));
                $jy = $today_j[0];
                $jm = (int)$today_j[1];
                $month_names = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                for ($m = 0; $m <= 2; $m++) {
                    $ty = $jy;
                    $tm = $jm + $m;
                    if ($tm > 12) { $tm -= 12; $ty++; }
                    $last_day = function_exists('jalali_days_in_month') ? jalali_days_in_month($tm, $ty) : (($tm <= 6) ? 31 : (($tm <= 11) ? 30 : 29));
                    $jd = min($coach_fixed_salary_settlement_day, $last_day);
                    $g = jalali_to_gregorian($ty, $tm, $jd);
                    $settlement_gregorian_list[] = ['label' => ($m === 0 ? 'ماه جاری' : ($m === 1 ? 'ماه بعد' : '۲ ماه بعد')) . ' (' . $month_names[$tm] . ' ' . $ty . ')', 'date' => sprintf('%04d/%02d/%02d', $g[0], $g[1], $g[2])];
                }
            }
        ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>
                
                <h2>تنظیمات دستمزد و کیف پول مربی</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="coach_min_withdrawal_amount">حداقل مبلغ برداشت (تومان)</label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="coach_min_withdrawal_amount" 
                                   id="coach_min_withdrawal_amount"
                                   value="<?php echo number_format($coach_min_withdrawal_amount, 0, '.', ','); ?>" 
                                   class="regular-text"
                                   dir="ltr"
                                   inputmode="numeric"
                                   placeholder="0">
                            <input type="hidden" name="coach_min_withdrawal_amount_raw" id="coach_min_withdrawal_amount_raw" value="<?php echo esc_attr($coach_min_withdrawal_amount); ?>">
                            <p class="description">مربی نمی‌تواند کمتر از این مبلغ درخواست برداشت کند. برای غیرفعال کردن، مقدار 0 وارد کنید.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="coach_max_negative_balance">حداکثر موجودی منفی مجاز (تومان)</label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="coach_max_negative_balance" 
                                   id="coach_max_negative_balance"
                                   value="<?php echo number_format($coach_max_negative_balance, 0, '.', ','); ?>" 
                                   class="regular-text"
                                   dir="ltr"
                                   inputmode="numeric"
                                   placeholder="0">
                            <input type="hidden" name="coach_max_negative_balance_raw" id="coach_max_negative_balance_raw" value="<?php echo esc_attr($coach_max_negative_balance); ?>">
                            <p class="description">مربی می‌تواند تا این مقدار موجودی منفی داشته باشد. برای غیرفعال کردن، مقدار 0 وارد کنید.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="coach_fixed_salary_settlement_day">روز پرداخت دستمزد ثابت</label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="coach_fixed_salary_settlement_day" 
                                   id="coach_fixed_salary_settlement_day"
                                   value="<?php echo $coach_fixed_salary_settlement_day > 0 ? $coach_fixed_salary_settlement_day : ''; ?>" 
                                   class="small-text" 
                                   min="1" 
                                   max="31" 
                                   placeholder="0">
                            <p class="description">روز شمسی هر ماه که دستمزد ثابت به کیف پول مربی‌ها واریز می‌شود (۱ تا ۳۱). خالی یا ۰ = آخر ماه شمسی.</p>
                            <?php if ($coach_fixed_salary_settlement_day > 0 && !empty($settlement_gregorian_list)): ?>
                                <div class="description" style="margin-top: 8px; padding: 8px; background: #f0f6fc; border-right: 3px solid #2271b1;">
                                    <?php foreach ($settlement_gregorian_list as $item): ?>
                                        <div><strong><?php echo esc_html($item['label']); ?>:</strong> <?php echo esc_html($item['date']); ?></div>
                                    <?php endforeach; ?>
                                    <div style="margin-top: 6px;"><em>محاسبه دقیق بر اساس تقویم رسمی جلالی</em></div>
                                </div>
                            <?php endif; ?>
                        </td>

                      
                    </tr>
                    <tr>                  
                      <th scope="row">محاسبه دستمزد مربی بر اساس  بازیکنان حاضر در کلاس</th>
                            <td>
                                <?php  $calc_couch_salary = sc_get_setting('calc_couch_salary'); ?>
                                <label class="switch">
                                    <input type="checkbox" name="calc_couch_salary" value="1" <?php checked($calc_couch_salary, 1); ?>>
                                    <span class="slider round"></span>
                                </label>
                                <p class="description">با فعال کردن این بخش دستمزد مربی بر اساس بازیکنان حاضر محاسبه می شود و در  صورت غیرفعال بودن بر اساس تعداد کل شرکت کننده های  کلاس </p>

                            </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات دستمزد مربی">
                </p>
            </form>
            
            <div class="info_coach_salary" style="margin-top: 30px;">
                <h3>اطلاعات</h3>
                <ul>
                    <li><strong>دستمزد درصدی:</strong> در زمان ثبت حضور و غیاب، به صورت خودکار محاسبه و به کیف پول مربی واریز می‌شود.</li>
                    <li><strong>دستمزد ثابت:</strong> در روز مشخص‌شده در تنظیمات (یا آخر ماه در صورت خالی بودن) به صورت خودکار به کیف پول مربی واریز می‌شود.</li>
                    <li><strong>درخواست برداشت:</strong> مربی می‌تواند از کیف پول خود درخواست برداشت کند که نیاز به تایید مدیر دارد.</li>
                    <li><strong>مدیریت کیف پول:</strong> مدیر می‌تواند به صورت دستی کیف پول مربی را شارژ یا برداشت کند.</li>
                </ul>
            </div>
        <?php endif; 
        if ($current_tab === 'classes') :
            $private_class_cancel_minutes_before = (int) sc_get_setting('private_class_cancel_minutes_before', '1440');
            $private_class_reschedule_minutes_before = (int) sc_get_setting('private_class_reschedule_minutes_before', '1440');
            $private_booking_mode = function_exists('sc_get_private_booking_mode') ? sc_get_private_booking_mode() : 'direct_payment';
            $private_booking_field_defaults = ['course' => 1, 'chapter' => 1, 'coach' => 1, 'slots' => 0, 'sessions' => 0, 'start_date' => 0];
            $private_booking_user_fields = [];
            foreach ($private_booking_field_defaults as $fkey => $fdefault) {
                $private_booking_user_fields[$fkey] = (int) sc_get_setting('private_booking_user_show_' . $fkey, (string) $fdefault) === 1;
            }
            $private_class_page_description = function_exists('sc_get_private_class_page_description')
                ? sc_get_private_class_page_description()
                : trim((string) sc_get_setting('private_class_page_description', ''));
            $field_labels = [
                'course' => 'انتخاب دوره',
                'chapter' => 'انتخاب شعبه',
                'coach' => 'انتخاب مربی',
                'slots' => 'انتخاب اسلات زمانی',
                'sessions' => 'تعداد جلسات',
                'start_date' => 'تاریخ شروع',
            ];
        ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>
                <h2>تنظیمات کلاس‌های خصوصی / نیمه‌خصوصی</h2>
                <style>
                    .sc-classes-settings-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:14px}
                    .sc-classes-settings-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
                    .sc-classes-settings-item label{display:block;font-weight:600;margin-bottom:6px}
                    .sc-booking-mode-options{display:grid;gap:10px;margin:12px 0}
                    .sc-booking-mode-option{border:1px solid #dcdcde;border-radius:8px;padding:12px;background:#fff}
                </style>

                <div class="sc-classes-settings-card">
                    <h3 style="margin-top:0;">حالت رزرو کلاس خصوصی</h3>
                    <div class="sc-booking-mode-options">
                        <label class="sc-booking-mode-option">
                            <input type="radio" name="private_booking_mode" value="direct_payment" <?php checked($private_booking_mode, 'direct_payment'); ?>>
                            <strong>حالت ۱ — رزرو با پرداخت کاربر</strong>
                            <p class="description" style="margin:6px 0 0;">کاربر فرم کامل را پر می‌کند، صورت‌حساب صادر می‌شود و پس از پرداخت جلسات فعال می‌شوند.</p>
                        </label>
                        <label class="sc-booking-mode-option">
                            <input type="radio" name="private_booking_mode" value="admin_approval" <?php checked($private_booking_mode, 'admin_approval'); ?>>
                            <strong>حالت ۲ — رزرو با تایید مدیر</strong>
                            <p class="description" style="margin:6px 0 0;">کاربر درخواست می‌دهد، مدیر تکمیل می‌کند و صورت‌حساب صادر می‌شود. تا پرداخت یا تایید پرداخت، جلسه‌ای رزرو نمی‌شود.</p>
                        </label>
                    </div>
                </div>

                <div class="sc-classes-settings-card">
                    <h3 style="margin-top:0;">توضیحات صفحه رزرو کلاس خصوصی (کاربر)</h3>
                    <p class="description">این متن بالای فرم رزرو در پنل کاربر نمایش داده می‌شود و قابل بستن نیست.</p>
                    <textarea name="private_class_page_description" id="private_class_page_description" rows="4" class="large-text" placeholder="مثلاً: برای رزرو کلاس خصوصی، ابتدا دوره و مربی را انتخاب کنید..."><?php echo esc_textarea($private_class_page_description); ?></textarea>
                </div>

                <div class="sc-classes-settings-card sc-booking-mode-2-fields" <?php echo $private_booking_mode !== 'admin_approval' ? 'style="display:none;"' : ''; ?>>
                    <h3 style="margin-top:0;">فیلدهای قابل نمایش به کاربر (حالت ۲)</h3>
                    <p class="description">فیلدهای غیرفعال برای کاربر پنهان می‌مانند و مدیر در بخش «رزرو کلاس خصوصی» آن‌ها را تکمیل می‌کند.</p>
                    <div class="sc-classes-settings-grid">
                        <?php foreach ($field_labels as $fkey => $flabel) :
                            $checked = !empty($private_booking_user_fields[$fkey]);
                        ?>
                            <div class="sc-classes-settings-item">
                                <label>
                                    <input type="checkbox" name="private_booking_user_show_<?php echo esc_attr($fkey); ?>" value="1" <?php checked($checked); ?>>
                                    <?php echo esc_html($flabel); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sc-classes-settings-card">
                    <div class="sc-classes-settings-grid">
                        <div class="sc-classes-settings-item">
                            <label for="private_class_cancel_minutes_before">حداقل فاصله لغو تا شروع جلسه (دقیقه)</label>
                            <input type="number" name="private_class_cancel_minutes_before" id="private_class_cancel_minutes_before" value="<?php echo esc_attr($private_class_cancel_minutes_before); ?>" min="0" class="small-text">
                            <p class="description">اگر مقدار ۰ باشد، بدون محدودیت زمانی لغو می‌شود.</p>
                        </div>
                        <div class="sc-classes-settings-item">
                            <label for="private_class_reschedule_minutes_before">حداقل فاصله جابجایی تا شروع جلسه (دقیقه)</label>
                            <input type="number" name="private_class_reschedule_minutes_before" id="private_class_reschedule_minutes_before" value="<?php echo esc_attr($private_class_reschedule_minutes_before); ?>" min="0" class="small-text">
                            <p class="description">این محدودیت برای جابجایی جلسه توسط کاربر/مربی اعمال می‌شود.</p>
                        </div>
                    </div>
                </div>

                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات کلاس‌ها">
                </p>
            </form>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modeInputs = document.querySelectorAll('input[name="private_booking_mode"]');
                var fieldsCard = document.querySelector('.sc-booking-mode-2-fields');
                if (!modeInputs.length || !fieldsCard) return;
                modeInputs.forEach(function (input) {
                    input.addEventListener('change', function () {
                        fieldsCard.style.display = (document.querySelector('input[name="private_booking_mode"]:checked')?.value === 'admin_approval') ? '' : 'none';
                    });
                });
            });
            </script>
        <?php endif;
        if ($current_tab === 'honors') :
            $honors_api_key_value = function_exists('sc_get_honors_api_key') ? sc_get_honors_api_key() : trim((string) sc_get_setting('honors_api_key', ''));
            $honors_api_list_url = rest_url('sportclub/v1/honors');
            $honors_api_single_url = rest_url('sportclub/v1/honors/{id}');
        ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>
                <h2>API افتخارات (برای سایت‌های خارجی)</h2>
                <p class="description">با این API می‌توانید افتخارات بازیکنان و مربیان را در سایت دیگر نمایش دهید. فیلد <code>owner_type</code> مشخص می‌کند افتخار متعلق به بازیکن است یا مربی.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="honors_api_key">کلید API</label></th>
                        <td>
                            <input type="text" name="honors_api_key" id="honors_api_key" value="<?php echo esc_attr($honors_api_key_value); ?>" class="regular-text" dir="ltr" autocomplete="off" placeholder="یک کلید امن وارد کنید">
                            <p class="description">در هدر <code>X-API-Key</code> یا <code>Authorization: Bearer</code> یا پارامتر <code>api_key</code> ارسال شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">آدرس لیست</th>
                        <td><code dir="ltr"><?php echo esc_html($honors_api_list_url); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">آدرس تکی</th>
                        <td><code dir="ltr"><?php echo esc_html($honors_api_single_url); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">پارامترهای فیلتر</th>
                        <td>
                            <p class="description" style="margin:0;">
                                <code>owner_type=all|member|coach</code>،
                                <code>status=approved|pending|rejected|all</code>،
                                <code>member_id</code>،
                                <code>coach_id</code>،
                                <code>category_id</code>،
                                <code>page</code>،
                                <code>per_page</code>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات افتخارات">
                </p>
            </form>
        <?php endif;
        if ($current_tab === 'pro_features') : ?>
    <form method="POST" action="">
        <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>

        <style>
            .sc-pro-features-grid {
                width: 100%;
                border-collapse: separate;
            }

            .sc-pro-features-grid tbody {
                display: grid;
                grid-template-columns: repeat(3, minmax(220px, 1fr));
                gap: 12px;
            }

            .sc-pro-features-grid tr {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin: 0;
                padding: 12px 14px;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                background: #fff;
            }

            .sc-pro-features-grid th,
            .sc-pro-features-grid td {
                display: block;
                margin: 0;
                padding: 0;
            }

            .sc-pro-features-grid th {
                font-weight: 600;
                text-align: right;
                flex: 1;
            }

            .sc-pro-features-grid td {
                flex-shrink: 0;
            }

            @media (max-width: 1200px) {
                .sc-pro-features-grid tbody {
                    grid-template-columns: repeat(2, minmax(220px, 1fr));
                }
            }

            @media (max-width: 782px) {
                .sc-pro-features-grid tbody {
                    grid-template-columns: minmax(200px, 1fr);
                }
            }
        </style>

        <table class="form-table sc-pro-features-grid">
            <tr>
                <th scope="row">اطلاعیه‌ها</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_notifications" value="0">
                        <input type="checkbox" name="pro_feature_notifications" value="1" <?php checked($pro_feature_notifications, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">نظرسنجی</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_surveys" value="0">
                        <input type="checkbox" name="pro_feature_surveys" value="1" <?php checked($pro_feature_surveys, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">مربیان</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_coaches" value="0">
                        <input type="checkbox" name="pro_feature_coaches" value="1" <?php checked($pro_feature_coaches, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">کیف پول بازیکنان</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_players_wallet" value="0">
                        <input type="checkbox" name="pro_feature_players_wallet" value="1" <?php checked($pro_feature_players_wallet, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">کیف پول مربیان و دستمزد</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_coaches_wallet_salary" value="0">
                        <input type="checkbox" name="pro_feature_coaches_wallet_salary" value="1" <?php checked($pro_feature_coaches_wallet_salary, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">سامانه پیامکی</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_sms" value="0">
                        <input type="checkbox" name="pro_feature_sms" value="1" <?php checked($pro_feature_sms, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">فروشگاه </th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_shop" value="0">
                        <input type="checkbox" name="pro_feature_shop" value="1" <?php checked($pro_feature_shop, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">هشدارهای کاربر</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_user_alerts" value="0">
                        <input type="checkbox" name="pro_feature_user_alerts" value="1" <?php checked($pro_feature_user_alerts, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">خروجی اطلاعات کاربران</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_users_export" value="0">
                        <input type="checkbox" name="pro_feature_users_export" value="1" <?php checked($pro_feature_users_export, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">کارهای دست‌جمعی</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_bulk_actions" value="0">
                        <input type="checkbox" name="pro_feature_bulk_actions" value="1" <?php checked($pro_feature_bulk_actions, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">گواهینامه‌ها</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_certificates" value="0">
                        <input type="checkbox" name="pro_feature_certificates" value="1" <?php checked($pro_feature_certificates, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">حضور و غیاب</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_attendance" value="0">
                        <input type="checkbox" name="pro_feature_attendance" value="1" <?php checked($pro_feature_attendance, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">اسکن QR حضور و غیاب</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_attendance_qr" value="0">
                        <input type="checkbox" name="pro_feature_attendance_qr" value="1" <?php checked($pro_feature_attendance_qr, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                    <p class="description">QR اختصاصی بازیکن، اسکن دوربین در ثبت حضور، کارت QR در پیشخوان و خروجی اطلاعات کاربر</p>
                </td>
            </tr>
            <tr>
                <th scope="row">دوره‌ها و کلاس‌های خصوصی</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_courses" value="0">
                        <input type="checkbox" name="pro_feature_courses" value="1" <?php checked($pro_feature_courses, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">رویدادها</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_events" value="0">
                        <input type="checkbox" name="pro_feature_events" value="1" <?php checked($pro_feature_events, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">یادداشت‌های خصوصی</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_private_notes" value="0">
                        <input type="checkbox" name="pro_feature_private_notes" value="1" <?php checked($pro_feature_private_notes, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">تیکت پشتیبانی</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_support_tickets" value="0">
                        <input type="checkbox" name="pro_feature_support_tickets" value="1" <?php checked($pro_feature_support_tickets, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">صورت‌حساب و مالی</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_invoices" value="0">
                        <input type="checkbox" name="pro_feature_invoices" value="1" <?php checked($pro_feature_invoices, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">افتخارات</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_honors" value="0">
                        <input type="checkbox" name="pro_feature_honors" value="1" <?php checked($pro_feature_honors, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">گزارشات باشگاه</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_reports" value="0">
                        <input type="checkbox" name="pro_feature_reports" value="1" <?php checked($pro_feature_reports, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">تیم و سطح</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_team_level" value="0">
                        <input type="checkbox" name="pro_feature_team_level" value="1" <?php checked($pro_feature_team_level, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">شعبه‌های باشگاه</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_chapters" value="0">
                        <input type="checkbox" name="pro_feature_chapters" value="1" <?php checked($pro_feature_chapters, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">سوالات متداول</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_faq" value="0">
                        <input type="checkbox" name="pro_feature_faq" value="1" <?php checked($pro_feature_faq, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">فهرست‌های منو</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_nav_menus" value="0">
                        <input type="checkbox" name="pro_feature_nav_menus" value="1" <?php checked($pro_feature_nav_menus, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">پیوندهای یکتا</th>
                <td>
                    <label class="switch">
                        <input type="hidden" name="pro_feature_permalinks" value="0">
                        <input type="checkbox" name="pro_feature_permalinks" value="1" <?php checked($pro_feature_permalinks, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات امکانات پرو">
        </p>
    </form>
<?php endif; ?>

    </div>
</div>






   <script name="for login_club">
            jQuery(function($) {
                if (typeof wp === 'undefined' || !wp.media) return;
                var logoUploader, bgUploader;
                $('#sc_login_logo_upload').on('click', function(e) {
                    e.preventDefault();
                    if (logoUploader) { logoUploader.open(); return; }
                    logoUploader = wp.media({ title: 'انتخاب لوگو', button: { text: 'استفاده از این تصویر' }, multiple: false, library: { type: 'image' } });
                    logoUploader.on('select', function() {
                        var att = logoUploader.state().get('selection').first().toJSON();
                        $('#sc_login_logo_url').val(att.url);
                        $('#sc_login_logo_preview').html('<img src="' + att.url + '" alt="" style="max-width:200px;height:auto;border:1px solid #ddd;border-radius:4px;">');
                        $('#sc_login_logo_remove').show();
                    });
                    logoUploader.open();
                });
                $('#sc_login_logo_remove').on('click', function() {
                    $('#sc_login_logo_url').val('');
                    $('#sc_login_logo_preview').empty();
                    $(this).hide();
                });
                $('#sc_login_bg_upload').on('click', function(e) {
                    e.preventDefault();
                    if (bgUploader) { bgUploader.open(); return; }
                    bgUploader = wp.media({ title: 'انتخاب تصویر پس‌زمینه', button: { text: 'استفاده از این تصویر' }, multiple: false, library: { type: 'image' } });
                    bgUploader.on('select', function() {
                        var att = bgUploader.state().get('selection').first().toJSON();
                        $('#sc_login_bg_image').val(att.url);
                        $('#sc_login_bg_preview').html('<img src="' + att.url + '" alt="" style="max-width:200px;max-height:80px;object-fit:cover;border:1px solid #ddd;border-radius:4px;">');
                        $('#sc_login_bg_remove').show();
                    });
                    bgUploader.open();
                });
                $('#sc_login_bg_remove').on('click', function() {
                    $('#sc_login_bg_image').val('');
                    $('#sc_login_bg_preview').empty();
                    $(this).hide();
                });
                $('#sc_login_bg_color, #sc_login_btn_bg, #sc_login_btn_color').on('input change', function() {
                    var id = $(this).attr('id') + '_hex';
                    $('#' + id).text($(this).val());
                });
                $('#sc_org_bg_color, #sc_org_btn_bg, #sc_org_btn_color').on('input change', function() {
                    var id = $(this).attr('id') + '_hex';
                    $('#' + id).text($(this).val());
                });
                $('#sc_txt_bg_color, #sc_org_btn_bg, #sc_org_btn_color').on('input change', function() {
                    var id = $(this).attr('id') + '_hex';
                    $('#' + id).text($(this).val());
                });
            });
            </script>

<script name="for logo_club">
            jQuery(function($) {
                if (typeof wp === 'undefined' || !wp.media) return;
                var logoUploader2;
                $('#sc_club_logo_upload').on('click', function(e) {
                    e.preventDefault();
                    if (logoUploader2) { logoUploader2.open(); return; }
                    logoUploader2 = wp.media({ title: 'انتخاب لوگو', button: { text: 'استفاده از این تصویر' }, multiple: false, library: { type: 'image' } });
                    logoUploader2.on('select', function() {
                        var att = logoUploader2.state().get('selection').first().toJSON();
                        $('#sc_club_logo_url').val(att.url);
                        $('#sc_club_logo_preview').html('<img src="' + att.url + '" alt="" style="max-width:200px;height:auto;border:1px solid #ddd;border-radius:4px;">');
                        $('#sc_club_logo_remove').show();
                    });
                    logoUploader2.open();
                });
                $('#sc_club_logo_remove').on('click', function() {
                    $('#sc_club_logo_url').val('');
                    $('#sc_club_logo_preview').empty();
                    $(this).hide();
                });
                 
                
            });
            </script>

<script name="for attendance_qr_settings">
            jQuery(function($) {
                if (typeof wp === 'undefined' || !wp.media) return;
                var qrLogoUploader;
                $('#attendance_qr_logo_upload').on('click', function(e) {
                    e.preventDefault();
                    if (qrLogoUploader) { qrLogoUploader.open(); return; }
                    qrLogoUploader = wp.media({ title: 'لوگوی QR', button: { text: 'استفاده از این تصویر' }, multiple: false, library: { type: 'image' } });
                    qrLogoUploader.on('select', function() {
                        var att = qrLogoUploader.state().get('selection').first().toJSON();
                        $('#attendance_qr_logo_url').val(att.url);
                        $('#attendance_qr_logo_preview').html('<img src="' + att.url + '" alt="" style="max-width:120px;height:auto;border:1px solid #ddd;border-radius:4px;">');
                        $('#attendance_qr_logo_remove').show();
                    });
                    qrLogoUploader.open();
                });
                $('#attendance_qr_logo_remove').on('click', function() {
                    $('#attendance_qr_logo_url').val('');
                    $('#attendance_qr_logo_preview').empty();
                    $(this).hide();
                });

                var soundUploaders = {};
                $('.sc-qr-sound-upload').on('click', function(e) {
                    e.preventDefault();
                    var target = $(this).data('target');
                    var preview = $(this).data('preview');
                    if (!target) return;
                    if (!soundUploaders[target]) {
                        soundUploaders[target] = wp.media({ title: 'انتخاب فایل MP3', button: { text: 'استفاده' }, multiple: false, library: { type: 'audio/mpeg' } });
                        soundUploaders[target].on('select', function() {
                            var att = soundUploaders[target].state().get('selection').first().toJSON();
                            var isMp3 = (att.mime === 'audio/mpeg' || att.mime === 'audio/mp3' || (att.url && /\.mp3(\?|$)/i.test(att.url)));
                            if (!isMp3) {
                                alert('فقط فایل MP3 مجاز است.');
                                return;
                            }
                            $('#' + target).val(att.url);
                            $('#' + preview).text(att.url);
                            $('.sc-qr-sound-remove[data-target="' + target + '"]').show();
                        });
                    }
                    soundUploaders[target].open();
                });
                $('.sc-qr-sound-remove').on('click', function() {
                    var target = $(this).data('target');
                    var preview = $(this).data('preview');
                    $('#' + target).val('');
                    $('#' + preview).text('پیش‌فرض سیستم');
                    $(this).hide();
                });
            });
            </script>


<script>
jQuery(document).ready(function($) {
    // فرمت کردن مبلغ در تنظیمات دستمزد مربی
    $('#coach_min_withdrawal_amount, #coach_max_negative_balance , #max_debt_for_attendance').on('input', function() {
        var value = $(this).val().replace(/,/g, '');
        if (!isNaN(value) && value !== '') {
            $(this).val(number_format(value, 0, '.', ','));
        }
    });
    
    function number_format(number, decimals, dec_point, thousands_sep) {
        number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+number) ? 0 : +number,
            prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
            sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
            dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
            s = '',
            toFixedFix = function(n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
    }
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {

    const select = document.getElementById("invoice_mode_select");

    function updateInvoiceFields() {
        const mode = select.value;

        // همه مخفی
        document.querySelectorAll(".invoice-row").forEach(el => {
            el.style.display = "none";
        });

        if (mode === "interval") {
            document.querySelector(".interval-row").style.display = "table-row";
        }

        if (mode === "fixed_date") {
            document.querySelector(".fixed-date-row-day").style.display = "table-row";
            document.querySelector(".fixed-date-row-time").style.display = "table-row";
        }

        if (mode === "sessions_threshold") {
            document.querySelector(".sessions-row").style.display = "table-row";
        }
    }

    // وقتی کاربر انتخاب می‌کند
    select.addEventListener("change", updateInvoiceFields);

    // اجرا هنگام بارگذاری
    updateInvoiceFields();

});
</script>


