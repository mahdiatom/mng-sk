<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

// دریافت تب فعلی (باید قبل از پردازش فرم باشد)
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'penalty';

// پردازش فرم
if (isset($_POST['sc_save_settings']) && check_admin_referer('sc_settings_nonce', 'sc_settings_nonce')) {
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
       

    sc_update_setting('invoice_mode', $invoice_mode, 'invoice');

    if ($invoice_mode === 'interval') {
        $invoice_interval_minutes = absint($_POST['invoice_interval_minutes']);
        sc_update_setting('invoice_interval_minutes', $invoice_interval_minutes, 'invoice');
    } elseif($invoice_mode === 'sessions_threshold'){
         $sessions_count_threshold = isset($_POST['sessions_count_threshold']) ? $_POST['sessions_count_threshold'] : 1;
         sc_update_setting('sessions_count_threshold' , $sessions_count_threshold , 'invoice' ); 
    }
    else {
        $day_val = isset($_POST['invoice_day_of_month']) && $_POST['invoice_day_of_month'] !== '' ? absint($_POST['invoice_day_of_month']) : 0;
        sc_update_setting('invoice_day_of_month', min(31, max(0, $day_val)), 'invoice');
        sc_update_setting('invoice_hour', min(23, max(0, absint($_POST['invoice_hour'] ?? 0))), 'invoice');
        sc_update_setting('invoice_minute', min(59, max(0, absint($_POST['invoice_minute'] ?? 0))), 'invoice');
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

        // Certificate issued SMS settings
        $sms_certificate_user_enabled = isset($_POST['sms_certificate_user_enabled']) ? 1 : 0;
        $sms_certificate_user_template = isset($_POST['sms_certificate_user_template']) ? wp_kses($_POST['sms_certificate_user_template'], array()) : '';
        $sms_certificate_user_pattern = isset($_POST['sms_certificate_user_pattern']) ? absint($_POST['sms_certificate_user_pattern']) : '';
        sc_update_setting('sms_certificate_user_enabled', $sms_certificate_user_enabled, 'sms');
        sc_update_setting('sms_certificate_user_template', $sms_certificate_user_template, 'sms');
        sc_update_setting('sms_certificate_user_pattern', $sms_certificate_user_pattern, 'sms');

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
        $private_class_sms_user_cancel_to_coach_enabled = isset($_POST['private_class_sms_user_cancel_to_coach_enabled']) ? 1 : 0;
        $private_class_sms_coach_cancel_to_user_enabled = isset($_POST['private_class_sms_coach_cancel_to_user_enabled']) ? 1 : 0;
        sc_update_setting('private_class_cancel_minutes_before', (string) $private_class_cancel_minutes_before, 'classes');
        sc_update_setting('private_class_reschedule_minutes_before', (string) $private_class_reschedule_minutes_before, 'classes');
        sc_update_setting('private_class_sms_user_cancel_to_coach_enabled', (string) $private_class_sms_user_cancel_to_coach_enabled, 'classes');
        sc_update_setting('private_class_sms_coach_cancel_to_user_enabled', (string) $private_class_sms_coach_cancel_to_user_enabled, 'classes');
        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب کلاس‌ها ذخیره شد', null, ['tab' => 'classes']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات کلاس‌ها با موفقیت ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'pro_features') {
    $pro_feature_notifications = isset($_POST['pro_feature_notifications']) ? 1 : 0;
    $pro_feature_coaches = isset($_POST['pro_feature_coaches']) ? 1 : 0;
    $pro_feature_players_wallet = isset($_POST['pro_feature_players_wallet']) ? 1 : 0;
    $pro_feature_coaches_wallet_salary = isset($_POST['pro_feature_coaches_wallet_salary']) ? 1 : 0;
    $pro_feature_sms = isset($_POST['pro_feature_sms']) ? 1 : 0;
    $pro_feature_shop = isset($_POST['pro_feature_shop']) ? 1 : 0;

    sc_update_setting('pro_feature_notifications', $pro_feature_notifications, 'pro_features');
    sc_update_setting('pro_feature_coaches', $pro_feature_coaches, 'pro_features');
    sc_update_setting('pro_feature_players_wallet', $pro_feature_players_wallet, 'pro_features');
    sc_update_setting('pro_feature_coaches_wallet_salary', $pro_feature_coaches_wallet_salary, 'pro_features');
    sc_update_setting('pro_feature_sms', $pro_feature_sms, 'pro_features');
    sc_update_setting('pro_feature_shop', $pro_feature_shop, 'pro_features');
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
        sc_update_setting('sc_login_redirect_path', $sc_login_redirect_path, 'login_register');
        sc_update_setting('sc_login_otp_pattern', $sc_login_otp_pattern, 'login_register');
        sc_update_setting('sc_login_logo_url', $sc_login_logo_url, 'login_register');
        sc_update_setting('sc_login_bg_color', $sc_login_bg_color, 'login_register');
        sc_update_setting('sc_login_bg_image', $sc_login_bg_image, 'login_register');
        sc_update_setting('sc_login_btn_bg', $sc_login_btn_bg, 'login_register');
        sc_update_setting('sc_login_btn_color', $sc_login_btn_color, 'login_register');
        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب ورود و عضویت ذخیره شد', null, ['tab' => 'login_register']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ورود و عضویت ذخیره شد.</p></div>';
    }
    elseif ($current_tab === 'about') {

        $sc_name_club   = isset($_POST['sc_name_club']) ? sanitize_text_field($_POST['sc_name_club']) : 'باشگاه اتم';
        $sc_club_logo_url        = isset($_POST['sc_club_logo_url']) ? esc_url_raw($_POST['sc_club_logo_url']) : '';
        $sc_phone_club        = isset($_POST['sc_phone_club']) ? sanitize_text_field($_POST['sc_phone_club']) : '';
        $sc_token_club        = isset($_POST['sc_token_club']) ? sanitize_text_field($_POST['sc_token_club']) : '';
        $sc_botname_club        = isset($_POST['sc_botname_club']) ? sanitize_text_field($_POST['sc_botname_club']) : '';

        sc_update_setting('sc_name_club', $sc_name_club, 'abaut_club');
        sc_update_setting('sc_club_logo_url', $sc_club_logo_url, 'abaut_club');
        sc_update_setting('sc_phone_club', $sc_phone_club, 'abaut_club');
        sc_update_setting('sc_token_club', $sc_token_club, 'bot');
        sc_update_setting('sc_botname_club', $sc_botname_club, 'bot');

        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات تب درباره مجموعه ذخیره شد', null, ['tab' => 'about']);
        }
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات درباره مجموعه شد.</p></div>';
    }
}

// پردازش فرم بازگشت به کارخانه
if (isset($_POST['sc_reset_factory']) && check_admin_referer('sc_reset_factory', 'sc_reset_factory_nonce')) {
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

// Invoice SMS Settings
$sms_invoice_user_enabled = (int)sc_get_setting('sms_invoice_user_enabled', '1');
$sms_invoice_user_template = sc_get_setting('sms_invoice_user_template', '');
$sms_invoice_user_pattern = sc_get_setting('sms_invoice_user_pattern', '');
$sms_invoice_admin_enabled = (int)sc_get_setting('sms_invoice_admin_enabled', '1');
$sms_invoice_admin_template = sc_get_setting('sms_invoice_admin_template', '');
$sms_invoice_admin_pattern = sc_get_setting('sms_invoice_admin_pattern', '');

// Enrollment SMS Settings

$sms_enrollment_user_enabled = (int)sc_get_setting('sms_enrollment_user_enabled', '1');
$sms_enrollment_user_template = sc_get_setting('sms_enrollment_user_template', '');
$sms_enrollment_user_pattern = sc_get_setting('sms_enrollment_user_pattern', '');
$sms_enrollment_admin_enabled = (int)sc_get_setting('sms_enrollment_admin_enabled', '1');
$sms_enrollment_admin_template = sc_get_setting('sms_enrollment_admin_template', '');
$sms_enrollment_admin_pattern = sc_get_setting('sms_enrollment_admin_pattern', '');

// Reminder SMS Settings
$sms_reminder_user_enabled = (int)sc_get_setting('sms_reminder_user_enabled', '1');
$sms_reminder_user_template = sc_get_setting('sms_reminder_user_template', '');
$sms_reminder_user_pattern = sc_get_setting('sms_reminder_user_pattern', '');
$sms_reminder_admin_enabled = (int)sc_get_setting('sms_reminder_admin_enabled', '1');
$sms_reminder_admin_template = sc_get_setting('sms_reminder_admin_template', '');
$sms_reminder_admin_pattern = sc_get_setting('sms_reminder_admin_pattern', '');

// Absence SMS Settings
$sms_absence_user_enabled = (int)sc_get_setting('sms_absence_user_enabled', '1');
$sms_absence_user_template = sc_get_setting('sms_absence_user_template', 'کاربر گرامی %user_name%، غیبت شما در جلسه دوره %course_name% مورخ %date% ثبت شد.');
$sms_absence_user_pattern = sc_get_setting('sms_absence_user_pattern', '');
$sms_absence_admin_enabled = (int)sc_get_setting('sms_absence_admin_enabled', '1');
$sms_absence_alert_user_enabled = (int)sc_get_setting('sms_absence_alert_user_enabled', '0');
$sms_absence_alert_user_template = sc_get_setting('sms_absence_alert_user_template', 'کاربر گرامی %user_name%، تعداد غیبت شما در %item_name% به %absence_count% رسیده است (حد مجاز: %absence_limit%).');
$sms_absence_alert_user_pattern = sc_get_setting('sms_absence_alert_user_pattern', '');
$sms_absence_alert_admin_enabled = (int)sc_get_setting('sms_absence_alert_admin_enabled', '0');
$sms_absence_alert_admin_template = sc_get_setting('sms_absence_alert_admin_template', 'هشدار غیبت: %user_name% در %item_name% دارای %absence_count% غیبت است (حد مجاز: %absence_limit%).');
$sms_absence_alert_admin_pattern = sc_get_setting('sms_absence_alert_admin_pattern', '');

// Wallet Settings
$wallet_enabled = (int)sc_get_setting('wallet_enabled', '0');
$wallet_min_charge = floatval(sc_get_setting('wallet_min_charge', '10000'));
$wallet_max_charge = floatval(sc_get_setting('wallet_max_charge', '0'));
$wallet_max_negative_balance = floatval(sc_get_setting('wallet_max_negative_balance', '0'));
$wallet_min_balance_alert = floatval(sc_get_setting('wallet_min_balance_alert', '50000'));
$wallet_allow_partial_payment = (int)sc_get_setting('wallet_allow_partial_payment', '1');
$user_alert_absence_limit = (int) sc_get_setting('user_alert_absence_limit', '3');
$sms_absence_admin_template = sc_get_setting('sms_absence_admin_template', 'غیبت: %user_name% - دوره %course_name% - تاریخ %date%');
$sms_absence_admin_pattern = sc_get_setting('sms_absence_admin_pattern', '');
$sms_absence_alert_user_enabled = (int)sc_get_setting('sms_absence_alert_user_enabled', '0');
$sms_absence_alert_user_template = sc_get_setting('sms_absence_alert_user_template', 'کاربر گرامی %user_name%، تعداد غیبت شما در %item_name% به %absence_count% رسیده است (حد مجاز: %absence_limit%).');
$sms_absence_alert_user_pattern = sc_get_setting('sms_absence_alert_user_pattern', '');
$sms_absence_alert_admin_enabled = (int)sc_get_setting('sms_absence_alert_admin_enabled', '0');
$sms_absence_alert_admin_template = sc_get_setting('sms_absence_alert_admin_template', 'هشدار غیبت: %user_name% در %item_name% دارای %absence_count% غیبت است (حد مجاز: %absence_limit%).');
$sms_absence_alert_admin_pattern = sc_get_setting('sms_absence_alert_admin_pattern', '');

// Birthday SMS Settings
$sms_birthday_user_enabled = (int)sc_get_setting('sms_birthday_user_enabled', '0');
$sms_birthday_user_template = sc_get_setting('sms_birthday_user_template', '');
$sms_birthday_user_pattern = sc_get_setting('sms_birthday_user_pattern', '');

// Insurance expiry SMS Settings
$sms_insurance_expiry_user_enabled = (int)sc_get_setting('sms_insurance_expiry_user_enabled', '0');
$sms_insurance_expiry_user_template = sc_get_setting('sms_insurance_expiry_user_template', '');
$sms_insurance_expiry_user_pattern = sc_get_setting('sms_insurance_expiry_user_pattern', '');

// Identity verification approved SMS settings
$sms_identity_verified_user_enabled = (int)sc_get_setting('sms_identity_verified_user_enabled', '1');
$sms_identity_verified_user_template = sc_get_setting('sms_identity_verified_user_template', 'کاربر گرامی %user_name%، احراز هویت شما تایید شد.');
$sms_identity_verified_user_pattern = sc_get_setting('sms_identity_verified_user_pattern', '');
$sms_certificate_user_enabled = (int)sc_get_setting('sms_certificate_user_enabled', '1');
$sms_certificate_user_template = sc_get_setting('sms_certificate_user_template', 'کاربر گرامی %user_name%، یک گواهینامه برای شما صادر شد. لطفا به پنل خود مراجعه کنید.');
$sms_certificate_user_pattern = sc_get_setting('sms_certificate_user_pattern', '');

// Wallet SMS Settings
$sms_wallet_low_balance_user_enabled = (int)sc_get_setting('sms_wallet_low_balance_user_enabled', '1');
$sms_wallet_low_balance_user_template = sc_get_setting('sms_wallet_low_balance_user_template', '');
$sms_wallet_low_balance_user_pattern = sc_get_setting('sms_wallet_low_balance_user_pattern', '');

$sms_wallet_negative_balance_user_enabled = (int)sc_get_setting('sms_wallet_negative_balance_user_enabled', '1');
$sms_wallet_negative_balance_user_template = sc_get_setting('sms_wallet_negative_balance_user_template', '');
$sms_wallet_negative_balance_user_pattern = sc_get_setting('sms_wallet_negative_balance_user_pattern', '');

$sms_wallet_charge_success_user_enabled = (int)sc_get_setting('sms_wallet_charge_success_user_enabled', '1');
$sms_wallet_charge_success_user_template = sc_get_setting('sms_wallet_charge_success_user_template', '');
$sms_wallet_charge_success_user_pattern = sc_get_setting('sms_wallet_charge_success_user_pattern', '');

$sms_wallet_payment_user_enabled = (int)sc_get_setting('sms_wallet_payment_user_enabled', '1');
$sms_wallet_payment_user_template = sc_get_setting('sms_wallet_payment_user_template', '');
$sms_wallet_payment_user_pattern = sc_get_setting('sms_wallet_payment_user_pattern', '');

// Support ticket SMS
$sms_ticket_new_recipient_enabled = (int)sc_get_setting('sms_ticket_new_recipient_enabled', '0');
$sms_ticket_new_recipient_template = sc_get_setting('sms_ticket_new_recipient_template', 'تیکت پشتیبانی جدید #{ticket_id} با موضوع: {subject}');
$sms_ticket_reply_enabled = (int)sc_get_setting('sms_ticket_reply_enabled', '0');
$sms_ticket_reply_template = sc_get_setting('sms_ticket_reply_template', 'پاسخ جدید به تیکت #{ticket_id}. لطفا پنل خود را بررسی کنید.');

// تنظیمات افزونه پرو
$pro_feature_notifications = (int) sc_get_setting('pro_feature_notifications', 0);
$pro_feature_coaches = (int) sc_get_setting('pro_feature_coaches', 0);
$pro_feature_players_wallet = (int) sc_get_setting('pro_feature_players_wallet', 0);
$pro_feature_coaches_wallet_salary = (int) sc_get_setting('pro_feature_coaches_wallet_salary', 0);
$pro_feature_sms = (int) sc_get_setting('pro_feature_sms', 0);
$pro_feature_shop = (int) sc_get_setting('pro_feature_shop', 0);
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
// تنطیمات حضور و غیاب 

$deduction_wallet_enabled = (int)sc_get_setting('deduction_wallet',0);
// تنظیمات درباره مجموعه

$sc_name_club      = sc_get_setting('sc_name_club', '');
$sc_club_logo_url      = sc_get_setting('sc_club_logo_url', '');
$sc_phone_club      = sc_get_setting('sc_phone_club', '');
$sc_token_club      = sc_get_setting('sc_token_club', '');
$sc_botname_club      = sc_get_setting('sc_botname_club', '');

// ذخیره سازی صورتحساب
$sessions_count_threshold = sc_get_setting('sessions_count_threshold','1');
?>

<div class="wrap sc_setting_section" >
    <h1>تنظیمات SportClub Manager</h1>

    <nav class="nav-tab-wrapper">
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
    </nav>

    <div class="tab-content" style="margin-top: 20px;">
        <?php if ($current_tab === 'penalty') : ?>
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
                            <input type="number"invoice_day_of_month
                                name=""
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

                <form method="POST" action="" id="sc-reset-factory-form" onsubmit="return confirm('آیا مطمئن هستید؟ این عملیات غیر قابل بازگشت است و تمام اطلاعات حذف خواهد شد!');">
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
                               onclick="return confirm('آیا واقعاً مطمئن هستید؟ این عملیات غیر قابل بازگشت است!');">
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
                        <th scope="row"><label for="sc_login_otp_pattern">کد پترن پیامک (کد یکبارمصرف)</label></th>
                        <td>
                            <input type="number" name="sc_login_otp_pattern" id="sc_login_otp_pattern"
                                   value="<?php echo esc_attr($sc_login_otp_pattern); ?>"
                                   class="small-text" min="0" placeholder="مثال: 123456">
                            <p class="description">کد پترن از پنل sms.ir برای ارسال کد تأیید (پارامتر: Code)</p>
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
                            <p class="description">در صورت پر بودن، این تصویر به جای رنگ استفاده می‌شود</p>
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
                        <th scope="row"><label for="sc_phone_club">شماره تماس پشتیبانی </label></th>
                        <td>
                            <input type="text" name="sc_phone_club" 
                                   value="<?php echo esc_attr($sc_phone_club); ?>"
                                   class="regular-text" placeholder="مثلا : 09944338956  ">
                            <p class="description">شماره تماس در هدر و فوتر قسمت درباره مجموعه نمایش داده خواهد شد</p>
                        </td>
                    </tr>
                
                    <tr>
                        <th scope="row"><label for="sc_token_club">توکن ربات بله</label></th>
                        <td>
                            <input type="password" name="sc_token_club" 
                                   value="<?php echo esc_attr($sc_token_club); ?>"
                                   class="regular-text" placeholder="مثلا : 123456789:xxkjdkjfkjdfiejdekjdf  ">
                            <p class="description">برای ساخت توکن ربات وارد آیدی @botfather در اپ بله شوید سپس احراز هویت شوید و یک ربات بسازید در انتها یک توکن عددی- متنی می دهد.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sc_botname_club">نام کاربری ربات بله</label></th>
                        <td>
                            <input type="text" name="sc_botname_club" 
                                   value="<?php echo esc_attr($sc_botname_club); ?>"
                                   class="regular-text" placeholder="مثلا : mahdi_bot  ">
                            <p class="description">نام رباتی که در @botfather ساختید را وارد کنید.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات ورود و عضویت">
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
                </table>

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

                <h3>پیامک هشدار غیبت (عبور از حد مجاز)</h3>
                <table class="form-table">
                    <tr>
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
                        <th scope="row">پیامک هشدار به کاربر</th>
=======
                        <th scope="row">پیامک به کاربر</th>
>>>>>>> parent of 3e72976 (a)
=======
                        <th scope="row">پیامک به کاربر</th>
>>>>>>> parent of 3e72976 (a)
=======
                        <th scope="row">پیامک به کاربر</th>
>>>>>>> parent of 3e72976 (a)
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_absence_alert_user_enabled"
                                       value="1"
                                       <?php checked($sms_absence_alert_user_enabled, 1); ?>>
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
                                فعال کردن پیامک هشدار غیبت به کاربر
=======
                                فعال کردن پیامک هشدار عبور از حد مجاز غیبت
>>>>>>> parent of 3e72976 (a)
=======
                                فعال کردن پیامک هشدار عبور از حد مجاز غیبت
>>>>>>> parent of 3e72976 (a)
=======
                                فعال کردن پیامک هشدار عبور از حد مجاز غیبت
>>>>>>> parent of 3e72976 (a)
                            </label>
                            <br><br>
                            <textarea name="sms_absence_alert_user_template"
                                      rows="3"
                                      class="large-text"
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
                                      placeholder="متن پیامک هشدار به کاربر"><?php echo esc_textarea($sms_absence_alert_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% -
                                نام دوره/آیتم = %item_name% -
                                تعداد غیبت = %absence_count% -
                                حد مجاز = %absence_limit% -
                                تاریخ = %date%
=======
=======
>>>>>>> parent of 3e72976 (a)
=======
>>>>>>> parent of 3e72976 (a)
                                      placeholder="متن پیامک هشدار غیبت"><?php echo esc_textarea($sms_absence_alert_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% -
                                نام دوره = %course_name% -
                                نام آیتم = %item_name% -
                                تعداد غیبت = %absence_count% -
                                حد مجاز = %absence_limit% -
                                تاریخ آخرین غیبت = %date%
<<<<<<< HEAD
<<<<<<< HEAD
>>>>>>> parent of 3e72976 (a)
=======
>>>>>>> parent of 3e72976 (a)
=======
>>>>>>> parent of 3e72976 (a)
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
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD

                    <tr>
                        <th scope="row">پیامک هشدار به مدیر</th>
=======
                    <tr>
                        <th scope="row">پیامک به مدیر</th>
>>>>>>> parent of 3e72976 (a)
=======
                    <tr>
                        <th scope="row">پیامک به مدیر</th>
>>>>>>> parent of 3e72976 (a)
=======
                    <tr>
                        <th scope="row">پیامک به مدیر</th>
>>>>>>> parent of 3e72976 (a)
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_absence_alert_admin_enabled"
                                       value="1"
                                       <?php checked($sms_absence_alert_admin_enabled, 1); ?>>
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
                                فعال کردن پیامک هشدار غیبت به مدیر
=======
                                فعال کردن پیامک هشدار عبور از حد مجاز غیبت به مدیر
>>>>>>> parent of 3e72976 (a)
=======
                                فعال کردن پیامک هشدار عبور از حد مجاز غیبت به مدیر
>>>>>>> parent of 3e72976 (a)
=======
                                فعال کردن پیامک هشدار عبور از حد مجاز غیبت به مدیر
>>>>>>> parent of 3e72976 (a)
                            </label>
                            <br><br>
                            <textarea name="sms_absence_alert_admin_template"
                                      rows="3"
                                      class="large-text"
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
                                      placeholder="متن پیامک هشدار به مدیر"><?php echo esc_textarea($sms_absence_alert_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% -
                                نام دوره/آیتم = %item_name% -
                                تعداد غیبت = %absence_count% -
                                حد مجاز = %absence_limit% -
                                تاریخ = %date%
=======
=======
>>>>>>> parent of 3e72976 (a)
=======
>>>>>>> parent of 3e72976 (a)
                                      placeholder="متن پیامک هشدار غیبت به مدیر"><?php echo esc_textarea($sms_absence_alert_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% -
                                نام دوره = %course_name% -
                                نام آیتم = %item_name% -
                                تعداد غیبت = %absence_count% -
                                حد مجاز = %absence_limit% -
                                تاریخ آخرین غیبت = %date%
<<<<<<< HEAD
<<<<<<< HEAD
>>>>>>> parent of 3e72976 (a)
=======
>>>>>>> parent of 3e72976 (a)
=======
>>>>>>> parent of 3e72976 (a)
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
                </table>

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
            <style>
                .sc-player-info-settings-wrap{max-width:1100px}
                .sc-player-info-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;margin:14px 0;box-shadow:0 1px 2px rgba(0,0,0,.04)}
                .sc-player-info-card h2{margin:0 0 8px}
                .sc-player-info-card h3{margin:16px 0 10px}
                .sc-player-info-table{border-radius:8px;overflow:hidden}
                .sc-player-custom-field-item{transition:all .2s ease}
                .sc-player-custom-field-item:hover{box-shadow:0 2px 10px rgba(0,0,0,.06)}
            </style>
            <div class="sc-player-info-settings-wrap">
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>
                <div class="sc-player-info-card">
                <table class="form-table">
                    <tr>
                        <th scope="row">اجبار احراز هویت بازیکن</th>
                        <td>
                            <label class="switch">
                                <input type="checkbox" name="player_verification_required" value="1" <?php checked($player_verification_required, 1); ?>>
                                <span class="slider round"></span>
                            </label>
                            <p class="description">در صورت فعال بودن، بازیکن تا زمان تایید احراز هویت فقط به بخش «اطلاعات بازیکن» دسترسی خواهد داشت.</p>
                        </td>
                    </tr>
                </table>
                </div>

                <div class="sc-player-info-card">
                <h2 style="margin-top: 24px;">مدیریت فیلدهای پیش‌فرض اطلاعات بازیکن</h2>
                <p class="description">فقط «کد ملی» و «شماره موبایل بازیکن» همیشه نمایش داده می‌شوند و اجباری هستند.</p>
                <?php foreach ($player_sections as $section_key => $section_label) : ?>
                    <h3 style="margin-top: 18px;"><?php echo esc_html($section_label); ?></h3>
                    <table class="widefat striped sc-player-info-table" style="max-width: 980px;">
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

                <div class="sc-player-info-card">
                <h2 style="margin-top: 24px;">فیلدهای سفارشی اطلاعات بازیکن</h2>
                <p class="description">نوع‌های مجاز: متن، عکس و چندانتخابی</p>
                <div id="sc-player-custom-fields-container">
                    <?php foreach ($player_custom_fields as $idx => $custom_field) : ?>
                        <div class="sc-player-custom-field-item" style="margin:0 0 12px;padding:12px;border:1px solid #dcdcde;border-radius:6px;background:#fff;">
                            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                                <input type="text" name="player_custom_fields[<?php echo (int) $idx; ?>][label]" value="<?php echo esc_attr($custom_field['label'] ?? ''); ?>" placeholder="عنوان فیلد" style="min-width:180px;">
                                <input type="text" name="player_custom_fields[<?php echo (int) $idx; ?>][key]" value="<?php echo esc_attr($custom_field['key'] ?? ''); ?>" placeholder="کلید انگلیسی (اختیاری)" style="min-width:180px;" dir="ltr">
                                <select name="player_custom_fields[<?php echo (int) $idx; ?>][section]">
                                    <?php foreach ($player_sections as $sec_key => $sec_label) : ?>
                                        <option value="<?php echo esc_attr($sec_key); ?>" <?php selected(($custom_field['section'] ?? 'additional'), $sec_key); ?>><?php echo esc_html($sec_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="player_custom_fields[<?php echo (int) $idx; ?>][type]" class="sc-player-custom-type">
                                    <option value="text" <?php selected(($custom_field['type'] ?? 'text'), 'text'); ?>>متن</option>
                                    <option value="image" <?php selected(($custom_field['type'] ?? ''), 'image'); ?>>عکس</option>
                                    <option value="multiselect" <?php selected(($custom_field['type'] ?? ''), 'multiselect'); ?>>چند انتخابی</option>
                                </select>
                                <input type="text" name="player_custom_fields[<?php echo (int) $idx; ?>][options]" value="<?php echo esc_attr(!empty($custom_field['options']) && is_array($custom_field['options']) ? implode(', ', $custom_field['options']) : ''); ?>" placeholder="گزینه‌ها (با , جدا شود)" class="sc-player-custom-options" style="<?php echo (($custom_field['type'] ?? '') === 'multiselect') ? '' : 'display:none;'; ?>min-width:220px;">
                                <label><input type="checkbox" name="player_custom_fields[<?php echo (int) $idx; ?>][required]" value="1" <?php checked(!empty($custom_field['required'])); ?>> اجباری</label>
                                <label><input type="checkbox" name="player_custom_fields[<?php echo (int) $idx; ?>][visible]" value="1" <?php checked(!isset($custom_field['visible']) || !empty($custom_field['visible'])); ?>> نمایش</label>
                                <button type="button" class="button sc-player-custom-remove" style="color:#b32d2e;">حذف</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p>
                    <button type="button" class="button" id="sc-add-player-custom-field-btn">افزودن فیلد سفارشی</button>
                </p>
                </div>
                <p class="submit">
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

                $(document).on('change', '.sc-player-custom-type', function() {
                    var $row = $(this).closest('.sc-player-custom-field-item');
                    if ($(this).val() === 'multiselect') {
                        $row.find('.sc-player-custom-options').show();
                    } else {
                        $row.find('.sc-player-custom-options').hide().val('');
                    }
                });

                $(document).on('click', '#sc-add-player-custom-field-btn', function() {
                    var idx = customIndex++;
                    var html = '' +
                        '<div class="sc-player-custom-field-item" style="margin:0 0 12px;padding:12px;border:1px solid #dcdcde;border-radius:6px;background:#fff;">' +
                        '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">' +
                        '<input type="text" name="player_custom_fields[' + idx + '][label]" placeholder="عنوان فیلد" style="min-width:180px;">' +
                        '<input type="text" name="player_custom_fields[' + idx + '][key]" placeholder="کلید انگلیسی (اختیاری)" style="min-width:180px;" dir="ltr">' +
                        '<select name="player_custom_fields[' + idx + '][section]">' + sectionOptions() + '</select>' +
                        '<select name="player_custom_fields[' + idx + '][type]" class="sc-player-custom-type">' +
                        '<option value="text">متن</option>' +
                        '<option value="image">عکس</option>' +
                        '<option value="multiselect">چند انتخابی</option>' +
                        '</select>' +
                        '<input type="text" name="player_custom_fields[' + idx + '][options]" placeholder="گزینه‌ها (با , جدا شود)" class="sc-player-custom-options" style="display:none;min-width:220px;">' +
                        '<label><input type="checkbox" name="player_custom_fields[' + idx + '][required]" value="1"> اجباری</label>' +
                        '<label><input type="checkbox" name="player_custom_fields[' + idx + '][visible]" value="1" checked> نمایش</label>' +
                        '<button type="button" class="button sc-player-custom-remove" style="color:#b32d2e;">حذف</button>' +
                        '</div></div>';
                    $('#sc-player-custom-fields-container').append(html);
                });

                $(document).on('click', '.sc-player-custom-remove', function() {
                    $(this).closest('.sc-player-custom-field-item').remove();
                });
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
            $private_class_sms_user_cancel_to_coach_enabled = (int) sc_get_setting('private_class_sms_user_cancel_to_coach_enabled', '0');
            $private_class_sms_coach_cancel_to_user_enabled = (int) sc_get_setting('private_class_sms_coach_cancel_to_user_enabled', '0');
        ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>
                <h2>تنظیمات کلاس‌های خصوصی / نیمه‌خصوصی</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="private_class_cancel_minutes_before">حداقل فاصله لغو تا شروع جلسه (دقیقه)</label></th>
                        <td>
                            <input type="number" name="private_class_cancel_minutes_before" id="private_class_cancel_minutes_before" value="<?php echo esc_attr($private_class_cancel_minutes_before); ?>" min="0" class="small-text">
                            <p class="description">اگر مقدار ۰ باشد، بدون محدودیت زمانی لغو می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="private_class_reschedule_minutes_before">حداقل فاصله جابجایی تا شروع جلسه (دقیقه)</label></th>
                        <td>
                            <input type="number" name="private_class_reschedule_minutes_before" id="private_class_reschedule_minutes_before" value="<?php echo esc_attr($private_class_reschedule_minutes_before); ?>" min="0" class="small-text">
                            <p class="description">این محدودیت برای جابجایی جلسه توسط کاربر/مربی اعمال می‌شود.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ارسال پیامک در لغو توسط کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox" name="private_class_sms_user_cancel_to_coach_enabled" value="1" <?php checked($private_class_sms_user_cancel_to_coach_enabled, 1); ?>>
                                هنگام لغو جلسه توسط کاربر، پیامک برای مربی ارسال شود.
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ارسال پیامک در لغو توسط مربی/مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox" name="private_class_sms_coach_cancel_to_user_enabled" value="1" <?php checked($private_class_sms_coach_cancel_to_user_enabled, 1); ?>>
                                هنگام لغو جلسه توسط مربی یا مدیر، پیامک برای بازیکن ارسال شود.
                            </label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات کلاس‌ها">
                </p>
            </form>
        <?php endif;
        if ($current_tab === 'pro_features') : ?>
    <form method="POST" action="">
        <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">اطلاعیه‌ها</th>
                <td>
                    <label class="switch">
                        <input type="checkbox" name="pro_feature_notifications" value="1" <?php checked($pro_feature_notifications, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">مربیان</th>
                <td>
                    <label class="switch">
                        <input type="checkbox" name="pro_feature_coaches" value="1" <?php checked($pro_feature_coaches, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">کیف پول بازیکنان</th>
                <td>
                    <label class="switch">
                        <input type="checkbox" name="pro_feature_players_wallet" value="1" <?php checked($pro_feature_players_wallet, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">کیف پول مربیان و دستمزد</th>
                <td>
                    <label class="switch">
                        <input type="checkbox" name="pro_feature_coaches_wallet_salary" value="1" <?php checked($pro_feature_coaches_wallet_salary, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">سامانه پیامکی</th>
                <td>
                    <label class="switch">
                        <input type="checkbox" name="pro_feature_sms" value="1" <?php checked($pro_feature_sms, 1); ?>>
                        <span class="slider round"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">فروشگاه </th>
                <td>
                    <label class="switch">
                        <input type="checkbox" name="pro_feature_shop" value="1" <?php checked($pro_feature_shop, 1); ?>>
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


