<?php
/**
 * Settings Functions
 */
if ( ! defined('ABSPATH') ) exit;
/**
 * Format amount for display - use "(منفی)" instead of "-" for negative amounts
 * فرمت نمایش مبلغ - برای اعداد منفی از "(منفی)" به جای "-" استفاده می‌شود
 */
function sc_format_amount_display($amount) {
    $amount = floatval($amount);
    $formatted = number_format(abs($amount), 0, '.', ',');
    return $amount < 0 ? '(منفی) ' . $formatted : $formatted;
}

/**
 * Get setting value
 */
function sc_get_setting($key, $default = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_settings';
    
    $value = $wpdb->get_var($wpdb->prepare(
        "SELECT setting_value FROM $table_name WHERE setting_key = %s",
        $key
    ));
    
    return $value !== null ? $value : $default;
}

/**
 * Update setting value
 */
function sc_update_setting($key, $value, $group = 'general') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_settings';
    
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE setting_key = %s",
        $key
    ));
    
    if ($existing) {
        return $wpdb->update(
            $table_name,
            [
                'setting_value' => $value,
                'updated_at' => current_time('mysql')
            ],
            ['setting_key' => $key],
            ['%s', '%s'],
            ['%s']
        );
    } else {
        return $wpdb->insert(
            $table_name,
            [
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_group' => $group,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }
}

/**
 * Get all settings by group
 */
function sc_get_settings_by_group($group) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_settings';
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT setting_key, setting_value FROM $table_name WHERE setting_group = %s",
        $group
    ), ARRAY_A);
    
    $settings = [];
    foreach ($results as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    return $settings;
}

/**
 * Check if penalty is enabled
 */
function sc_is_penalty_enabled() {
    return (int)sc_get_setting('penalty_enabled', '0') === 1;
}

/**
 * Get penalty minutes
 */
function sc_get_penalty_minutes() {
    return (int)sc_get_setting('penalty_minutes', '7');
}

/**
 * Get penalty amount
 */
function sc_get_penalty_amount() {
    return (float)sc_get_setting('penalty_amount', '500');
}

/**
 * Get invoice interval minutes
 */
function sc_get_invoice_interval_minutes() {
    return (int)sc_get_setting('invoice_interval_minutes', '60');
}

/**
 * Calculate penalty for an invoice
 */
function sc_calculate_penalty($invoice_id) {
    if (!sc_is_penalty_enabled()) {
        return 0;
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE id = %d",
        $invoice_id
    ));
    
    // <-- اینجا شرط جدید اضافه می‌کنیم
    if ($invoice && isset($invoice->disable_penalty) && (int)$invoice->disable_penalty === 1) {
        return 0; // جریمه غیرفعال است
    }

    if (!$invoice || $invoice->status !== 'pending') {
        return 0;
    }
    
    $created_date = strtotime($invoice->created_at);
    $current_date = current_time('timestamp');
    $minutes_passed = floor(($current_date - $created_date) / 60);
    $penalty_minutes = sc_get_penalty_minutes();
    
    if (isset($invoice->penalty_applied) && $invoice->penalty_applied && isset($invoice->penalty_amount) && $invoice->penalty_amount > 0) {
        return (float)$invoice->penalty_amount;
    }
    
    if ($minutes_passed >= $penalty_minutes) {
        return sc_get_penalty_amount();
    }
    
    return 0;
}


/**
 * Apply penalty to an invoice and update WooCommerce order
 */
function sc_apply_penalty_to_invoice($invoice_id) {

    
    if (!sc_is_penalty_enabled()) {
        return false;
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE id = %d",
        $invoice_id
    ));
    // اگر جریمه برای این فاکتور غیرفعال شده
    if (isset($invoice->disable_penalty) && (int)$invoice->disable_penalty === 1) {
        return false;
    }

    if (!$invoice || $invoice->status !== 'pending') {
        return false;
    }
    
    // بررسی اینکه آیا جریمه قبلاً اعمال شده یا نه
    $penalty_applied = isset($invoice->penalty_applied) ? (int)$invoice->penalty_applied : 0;
    if ($penalty_applied) {
        return false; // جریمه قبلاً اعمال شده
    }
    
    $penalty_amount = sc_calculate_penalty($invoice_id);
    
    if ($penalty_amount > 0) {
        // به‌روزرسانی جدول invoices
        $update_data = [
            'penalty_amount' => $penalty_amount,
            'penalty_applied' => 1,
            'updated_at' => current_time('mysql')
        ];
        
        $wpdb->update(
            $invoices_table,
            $update_data,
            ['id' => $invoice_id],
            ['%f', '%d', '%s'],
            ['%d']
        );
        
        // به‌روزرسانی سفارش WooCommerce
        if ($invoice->woocommerce_order_id && class_exists('WooCommerce')) {
            $order = wc_get_order($invoice->woocommerce_order_id);
            if ($order && !$order->is_paid()) {
                // بررسی اینکه آیا جریمه قبلاً اضافه شده یا نه
                $has_penalty_fee = false;
                $penalty_item_id = null;
                
                foreach ($order->get_items('fee') as $item_id => $item) {
                    $item_name = $item->get_name();
                    if (strpos($item_name, 'جریمه') !== false || strpos($item_name, 'Penalty') !== false || strpos($item_name, 'تأخیر') !== false) {
                        $has_penalty_fee = true;
                        $penalty_item_id = $item_id;
                        break;
                    }
                }
                
                // اگر جریمه وجود داشت، به‌روزرسانی کن
                if ($has_penalty_fee && $penalty_item_id) {
                    $item = $order->get_item($penalty_item_id);
                    if ($item) {
                        $item->set_total($penalty_amount);
                        $item->save();
                    }
                } else {
                    // اگر جریمه وجود نداشت، اضافه کن
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name('جریمه تأخیر در پرداخت');
                    $fee->set_amount($penalty_amount);
                    $fee->set_tax_class('');
                    $fee->set_tax_status('none');
                    $fee->set_total($penalty_amount);
                    $order->add_item($fee);
                }
                
                // محاسبه مجدد مجموع
                $order->calculate_totals();
                $order->save();
            }
        }
        
        return true;
    }
    
    return false;
}

/**
 * Check and apply penalties for all pending invoices
 */
function sc_check_and_apply_penalties() {
    if (!sc_is_penalty_enabled()) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_invoices';
    $penalty_minutes = sc_get_penalty_minutes();

    $invoices = $wpdb->get_results(
        "SELECT * FROM $table
         WHERE status = 'pending'
         AND (penalty_applied = 0 OR penalty_applied IS NULL)"
    );

    foreach ($invoices as $invoice) {
        // اگر جریمه غیرفعال شده
        if (isset($invoice->disable_penalty) && (int)$invoice->disable_penalty === 1) {
            continue;
        }

        $created = strtotime($invoice->created_at);
        $now = current_time('timestamp');

        if (($now - $created) >= ($penalty_minutes * 60)) {
            sc_apply_penalty_to_invoice($invoice->id);
        }
    }
}


//اضافه کردن صورت حساب در تاریخ مشخص در ماه

function sc_get_invoice_mode() {
    return sc_get_setting('invoice_mode', 'interval');
}

function sc_get_invoice_day_of_month() {
    return (int) sc_get_setting('invoice_day_of_month', 1);
}

function sc_get_invoice_hour() {
    return (int) sc_get_setting('invoice_hour', 0);
}

function sc_get_invoice_minute() {
    return (int) sc_get_setting('invoice_minute', 0);
}

function sc_get_invoice_last_run() {
    return sc_get_setting('invoice_last_run', null);
}

function sc_set_invoice_last_run() {
    sc_update_setting('invoice_last_run', current_time('mysql'), 'invoice');
}

/**
 * Check if Pro feature: Notifications is enabled
 * بررسی فعال بودن امکانات پرو: اطلاعیه‌ها
 */
function sc_is_pro_feature_notifications_enabled() {
    return (int) sc_get_setting('pro_feature_notifications', '0') === 1;
}

function sc_is_pro_feature_surveys_enabled() {
    return (int) sc_get_setting('pro_feature_surveys', '0') === 1;
}
//sms
function sc_is_pro_feature_sms_enabled() {
    return (int) sc_get_setting('pro_feature_sms', '0') === 1;
}
/**
 * Check if Pro feature: Coaches is enabled
 * بررسی فعال بودن امکانات پرو: مربیان
 */
function sc_is_pro_feature_coaches_enabled() {
    return (int) sc_get_setting('pro_feature_coaches', '0') === 1;
}

/**
 * Check if Pro feature: Coaches Wallet and Salary is enabled
 * بررسی فعال بودن امکانات پرو: کیف پول مربیان و دستمزد
 */
function sc_is_pro_feature_coaches_wallet_salary_enabled() {
    return (int) sc_get_setting('pro_feature_coaches_wallet_salary', '0') === 1;
}

/**
 * Check if Pro feature: Players Wallet is enabled
 * بررسی فعال بودن امکانات پرو: کیف پول بازیکنان
 */
function sc_is_pro_feature_players_wallet_enabled() {
    return (int) sc_get_setting('pro_feature_players_wallet', '0') === 1;
}

/**
 * فروشگاه (منوی سفارشات ووکامرس)
 */
function sc_is_pro_feature_shop_enabled() {
    return (int) sc_get_setting('pro_feature_shop', '0') === 1;
}

/**
 * امکانات پرو — مخفی‌سازی منوی مدیریت (پیش‌فرض فعال برای سازگاری با نصب‌های قبلی)
 */
function sc_is_pro_feature_user_alerts_enabled() {
    return (int) sc_get_setting('pro_feature_user_alerts', '1') === 1;
}

function sc_is_pro_feature_users_export_enabled() {
    return (int) sc_get_setting('pro_feature_users_export', '1') === 1;
}

function sc_is_pro_feature_bulk_actions_enabled() {
    return (int) sc_get_setting('pro_feature_bulk_actions', '1') === 1;
}

function sc_is_pro_feature_certificates_enabled() {
    return (int) sc_get_setting('pro_feature_certificates', '1') === 1;
}

function sc_is_pro_feature_attendance_enabled() {
    return (int) sc_get_setting('pro_feature_attendance', '1') === 1;
}

function sc_is_pro_feature_attendance_qr_enabled() {
    return (int) sc_get_setting('pro_feature_attendance_qr', '1') === 1;
}

function sc_is_pro_feature_courses_enabled() {
    return (int) sc_get_setting('pro_feature_courses', '1') === 1;
}

function sc_is_pro_feature_events_enabled() {
    return (int) sc_get_setting('pro_feature_events', '1') === 1;
}

function sc_is_pro_feature_private_notes_enabled() {
    return (int) sc_get_setting('pro_feature_private_notes', '1') === 1;
}

function sc_is_pro_feature_support_tickets_enabled() {
    return (int) sc_get_setting('pro_feature_support_tickets', '1') === 1;
}

function sc_is_pro_feature_invoices_enabled() {
    return (int) sc_get_setting('pro_feature_invoices', '1') === 1;
}

function sc_is_pro_feature_honors_enabled() {
    return (int) sc_get_setting('pro_feature_honors', '1') === 1;
}

function sc_is_pro_feature_reports_enabled() {
    return (int) sc_get_setting('pro_feature_reports', '1') === 1;
}

function sc_is_pro_feature_team_level_enabled() {
    return (int) sc_get_setting('pro_feature_team_level', '1') === 1;
}

function sc_is_pro_feature_chapters_enabled() {
    return (int) sc_get_setting('pro_feature_chapters', '1') === 1;
}

function sc_is_pro_feature_faq_enabled() {
    return (int) sc_get_setting('pro_feature_faq', '1') === 1;
}

function sc_is_pro_feature_nav_menus_enabled() {
    return (int) sc_get_setting('pro_feature_nav_menus', '1') === 1;
}

function sc_is_pro_feature_permalinks_enabled() {
    return (int) sc_get_setting('pro_feature_permalinks', '1') === 1;
}

/**
 * Check if players wallet can be shown (pro feature + wallet setting)
 * بررسی امکان نمایش کیف پول بازیکنان (امکانات پرو + تنظیم کیف پول)
 * برای منوی کاربر و عملیات کیف پول استفاده شود
 */
function sc_can_show_players_wallet() {
    if (!sc_is_pro_feature_players_wallet_enabled()) {
        return false;
    }
    return (int) sc_get_setting('wallet_enabled', '0') === 1;
}

/**
 * Check if player verification is required before accessing account sections
 */
function sc_is_player_verification_required() {
    return (int) sc_get_setting('player_verification_required', '0') === 1;
}

/**
 * Player info: section labels used in settings and forms.
 */
function sc_get_player_info_sections() {
    return [
        'personal' => 'اطلاعات شخصی',
        'contact' => 'اطلاعات تماس',
        'documents' => 'مدارک و تصاویر',
        'additional' => 'اطلاعات تکمیلی',
    ];
}

/**
 * Player info: builtin fields (non-custom).
 */
function sc_get_player_info_builtin_fields() {
    return [
        'first_name' => ['label' => 'نام', 'section' => 'personal', 'type' => 'text'],
        'last_name' => ['label' => 'نام خانوادگی', 'section' => 'personal', 'type' => 'text'],
        'father_name' => ['label' => 'نام پدر', 'section' => 'personal', 'type' => 'text'],
        'national_id' => ['label' => 'کد ملی', 'section' => 'personal', 'type' => 'text', 'always_visible' => 1, 'always_required' => 1],
        'birth_date_shamsi' => ['label' => 'تاریخ تولد (شمسی)', 'section' => 'personal', 'type' => 'text'],
        'birth_date_gregorian' => ['label' => 'تاریخ تولد (میلادی)', 'section' => 'personal', 'type' => 'text'],
        'insurance_expiry_date_shamsi' => ['label' => 'تاریخ انقضا بیمه (شمسی)', 'section' => 'personal', 'type' => 'text'],
        'player_phone' => ['label' => 'شماره موبایل بازیکن', 'section' => 'contact', 'type' => 'text', 'always_visible' => 1, 'always_required' => 1],
        'father_phone' => ['label' => 'شماره موبایل پدر', 'section' => 'contact', 'type' => 'text'],
        'mother_phone' => ['label' => 'شماره موبایل مادر', 'section' => 'contact', 'type' => 'text'],
        'landline_phone' => ['label' => 'تلفن ثابت', 'section' => 'contact', 'type' => 'text'],
        'province' => ['label' => 'استان', 'section' => 'contact', 'type' => 'text'],
        'city' => ['label' => 'شهر', 'section' => 'contact', 'type' => 'text'],
        'gender' => ['label' => 'جنسیت', 'section' => 'personal', 'type' => 'text'],
        'personal_photo' => ['label' => 'عکس پرسنلی', 'section' => 'documents', 'type' => 'image'],
        'id_card_photo' => ['label' => 'عکس کارت ملی', 'section' => 'documents', 'type' => 'image'],
        'sport_insurance_photo' => ['label' => 'عکس بیمه ورزشی', 'section' => 'documents', 'type' => 'image'],
        'medical_condition' => ['label' => 'مشکلات پزشکی', 'section' => 'additional', 'type' => 'text'],
        'sports_history' => ['label' => 'سوابق ورزشی', 'section' => 'additional', 'type' => 'text'],
        'additional_info' => ['label' => 'توضیحات اضافی', 'section' => 'additional', 'type' => 'text'],
        'health_verified' => ['label' => 'تایید سلامت', 'section' => 'additional', 'type' => 'checkbox'],
        'info_verified' => ['label' => 'تایید صحت اطلاعات', 'section' => 'additional', 'type' => 'checkbox'],
    ];
}

/**
 * Player info: visibility/required rules for builtin fields.
 */
function sc_get_player_info_field_rules() {
    $builtin = sc_get_player_info_builtin_fields();
    $raw = sc_get_setting('player_info_field_rules', '');
    $saved = json_decode((string) $raw, true);
    if (!is_array($saved)) {
        $saved = [];
    }

    $rules = [];
    foreach ($builtin as $key => $meta) {
        $visible = isset($saved[$key]['visible']) ? (int) $saved[$key]['visible'] : 1;
        $required = isset($saved[$key]['required']) ? (int) $saved[$key]['required'] : 0;
        if (!empty($meta['always_visible'])) {
            $visible = 1;
        }
        if (!empty($meta['always_required'])) {
            $required = 1;
        }
        $rules[$key] = [
            'visible' => $visible ? 1 : 0,
            'required' => $required ? 1 : 0,
        ];
    }

    return $rules;
}

/**
 * Validate and normalize custom player info fields.
 */
function sc_sanitize_player_info_custom_fields_input($raw_fields) {
    $normalized = [];
    if (!is_array($raw_fields)) {
        return $normalized;
    }

    foreach ($raw_fields as $row) {
        if (!is_array($row)) {
            continue;
        }
        $label = isset($row['label']) ? sanitize_text_field(wp_unslash($row['label'])) : '';
        $field_key = isset($row['key']) ? sanitize_key(wp_unslash($row['key'])) : '';
        $type = isset($row['type']) ? sanitize_key(wp_unslash($row['type'])) : 'text';
        $section = isset($row['section']) ? sanitize_key(wp_unslash($row['section'])) : 'additional';
        $required = !empty($row['required']) ? 1 : 0;
        $visible = isset($row['visible']) ? (int) !empty($row['visible']) : 0;
        $options_raw = isset($row['options']) ? wp_unslash($row['options']) : '';

        if ($label === '') {
            continue;
        }
        if ($field_key === '') {
            $field_key = sanitize_key('field_' . substr(md5($label . wp_rand()), 0, 10));
        }
        if (!in_array($type, ['text', 'image', 'multiselect'], true)) {
            $type = 'text';
        }
        if (!in_array($section, ['personal', 'contact', 'documents', 'additional'], true)) {
            $section = 'additional';
        }

        $options = [];
        if ($type === 'multiselect') {
            $parts = array_filter(array_map('trim', explode(',', (string) $options_raw)));
            foreach ($parts as $part) {
                $options[] = sanitize_text_field($part);
            }
            $options = array_values(array_unique($options));
        }

        $normalized[] = [
            'key' => $field_key,
            'label' => $label,
            'type' => $type,
            'section' => $section,
            'required' => $required,
            'visible' => $visible,
            'options' => $options,
        ];
    }

    return $normalized;
}

/**
 * Get custom player info fields from settings.
 */
function sc_get_player_info_custom_fields() {
    $raw = sc_get_setting('player_info_custom_fields', '');
    $saved = json_decode((string) $raw, true);
    return sc_sanitize_player_info_custom_fields_input($saved);
}

/**
 * Whether a builtin player info field should be shown in forms/views.
 */
function sc_player_info_is_field_visible($field_key, $rules = null) {
    $rules = $rules ?? sc_get_player_info_field_rules();
    return empty($rules[$field_key]) || !empty($rules[$field_key]['visible']);
}

/**
 * HTML required attribute for player info fields.
 */
function sc_player_info_field_required_attr($field_key, $rules = null) {
    $rules = $rules ?? sc_get_player_info_field_rules();
    return !empty($rules[$field_key]['required']) ? ' required' : '';
}

/**
 * Whether a player info field value is considered empty for profile completion.
 */
function sc_player_info_value_is_empty($value, $type = 'text') {
    if ($type === 'checkbox') {
        return (int) $value !== 1;
    }
    if ($type === 'multiselect') {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        return !is_array($value) || count(array_filter($value, static function ($item) {
            return $item !== null && $item !== '';
        })) === 0;
    }
    if ($type === 'image') {
        return !is_string($value) || trim($value) === '';
    }
    if ($value === null) {
        return true;
    }
    if (is_string($value)) {
        $trimmed = trim($value);
        return $trimmed === '' || $trimmed === '0000-00-00';
    }
    if (is_numeric($value)) {
        return false;
    }
    if (is_array($value)) {
        return empty($value);
    }
    return empty($value);
}

/**
 * Whether a builtin player info field is empty on a member record.
 */
function sc_player_info_member_builtin_field_is_empty($member, $field_key, $field_meta = null) {
    $builtin_fields = sc_get_player_info_builtin_fields();
    if ($field_meta === null) {
        $field_meta = $builtin_fields[$field_key] ?? ['type' => 'text'];
    }
    $type = $field_meta['type'] ?? 'text';
    $value = isset($member->{$field_key}) ? $member->{$field_key} : null;
    return sc_player_info_value_is_empty($value, $type);
}

/**
 * Whether a custom player info field is empty on a member record.
 */
function sc_player_info_member_custom_field_is_empty($field, $extra_values) {
    if (!is_array($field) || empty($field['key'])) {
        return true;
    }
    $key = $field['key'];
    $type = $field['type'] ?? 'text';
    $value = $extra_values[$key] ?? null;
    return sc_player_info_value_is_empty($value, $type);
}

/**
 * Render custom player info fields as admin form-table rows.
 */
function sc_render_admin_player_custom_fields_rows($custom_fields, $section, $values = []) {
    if (empty($custom_fields) || !is_array($custom_fields)) {
        return;
    }
    if (!is_array($values)) {
        $values = [];
    }
    foreach ($custom_fields as $field) {
        if (!is_array($field) || ($field['section'] ?? '') !== $section || empty($field['visible'])) {
            continue;
        }
        $key = isset($field['key']) ? sanitize_key($field['key']) : '';
        if ($key === '') {
            continue;
        }
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? $key;
        $is_required = !empty($field['required']);
        $required_attr = $is_required ? ' required' : '';
        $required_mark = $is_required ? ' <span style="color:#d63638;">*</span>' : '';
        $current_val = $values[$key] ?? ($type === 'multiselect' ? [] : '');
        echo '<tr class="sc-admin-custom-player-field sc-custom-type-' . esc_attr($type) . '">';
        echo '<th scope="row"><label for="sc_admin_custom_' . esc_attr($key) . '">' . esc_html($label) . $required_mark . '</label></th>';
        echo '<td>';
        if ($type === 'image') {
            $image_url = is_string($current_val) ? $current_val : '';
            echo '<input type="text" name="player_custom_fields_images[' . esc_attr($key) . ']" id="sc_admin_custom_' . esc_attr($key) . '_txt" class="regular-text" value="' . esc_attr($image_url) . '" placeholder="آدرس تصویر یا آپلود کنید"' . $required_attr . '>';
            echo ' <button type="button" class="button-secondary sc-player-custom-upload-btn" data-target="#sc_admin_custom_' . esc_attr($key) . '_txt">انتخاب تصویر</button>';
            if ($image_url !== '') {
                echo '<div class="img_photo_prev sc-image-preview"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($label) . '"></div>';
            }
        } elseif ($type === 'multiselect') {
            $selected = is_array($current_val) ? $current_val : [];
            $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];
            echo '<select id="sc_admin_custom_' . esc_attr($key) . '" name="player_custom_fields_multi[' . esc_attr($key) . '][]" multiple size="4" class="regular-text"' . $required_attr . '>';
            foreach ($options as $option) {
                $option = (string) $option;
                echo '<option value="' . esc_attr($option) . '" ' . selected(in_array($option, $selected, true), true, false) . '>' . esc_html($option) . '</option>';
            }
            echo '</select>';
        } else {
            $text_value = is_string($current_val) ? $current_val : '';
            echo '<input type="text" name="player_custom_fields[' . esc_attr($key) . ']" id="sc_admin_custom_' . esc_attr($key) . '" value="' . esc_attr($text_value) . '" class="regular-text"' . $required_attr . '>';
        }
        echo '</td></tr>';
    }
}

/**
 * Render custom player info fields as admin view table rows.
 */
function sc_render_admin_player_custom_fields_view_rows($custom_fields, $section, $values = [], $exclude_types = []) {
    if (empty($custom_fields) || !is_array($custom_fields)) {
        return;
    }
    if (!is_array($values)) {
        $values = [];
    }
    if (!is_array($exclude_types)) {
        $exclude_types = [];
    }
    foreach ($custom_fields as $field) {
        if (!is_array($field) || ($field['section'] ?? '') !== $section || empty($field['visible'])) {
            continue;
        }
        $key = isset($field['key']) ? sanitize_key($field['key']) : '';
        if ($key === '') {
            continue;
        }
        $type = $field['type'] ?? 'text';
        if (in_array($type, $exclude_types, true)) {
            continue;
        }
        $label = $field['label'] ?? $key;
        $current_val = $values[$key] ?? ($type === 'multiselect' ? [] : '');
        echo '<tr><th>' . esc_html($label) . '</th><td>';
        if ($type === 'image') {
            $image_url = is_string($current_val) ? trim($current_val) : '';
            if ($image_url !== '') {
                echo '<a href="' . esc_url($image_url) . '" target="_blank"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($label) . '" style="max-width:200px;height:auto;border:1px solid #ddd;border-radius:4px;"></a>';
            } else {
                echo '-';
            }
        } elseif ($type === 'multiselect') {
            $selected = is_array($current_val) ? array_filter(array_map('strval', $current_val)) : [];
            echo $selected ? esc_html(implode('، ', $selected)) : '-';
        } else {
            $text_value = is_string($current_val) ? trim($current_val) : '';
            echo $text_value !== '' ? esc_html($text_value) : '-';
        }
        echo '</td></tr>';
    }
}

/**
 * Display value for a builtin player info field in admin view.
 */
function sc_player_info_format_builtin_view_value($player, $field_key) {
    $value = isset($player->{$field_key}) ? $player->{$field_key} : null;
    if ($field_key === 'gender') {
        if ($value === 'male') {
            return 'مرد';
        }
        if ($value === 'female') {
            return 'زن';
        }
        return '-';
    }
    if (in_array($field_key, ['health_verified', 'info_verified'], true)) {
        return (int) $value === 1
            ? '<span style="color:#059669;">✓ بله</span>'
            : '<span style="color:#dc2626;">✗ خیر</span>';
    }
    if (is_string($value)) {
        $value = trim($value);
    }
    if ($value === null || $value === '') {
        return '-';
    }
    if (in_array($field_key, ['medical_condition', 'sports_history', 'additional_info'], true)) {
        return nl2br(esc_html((string) $value));
    }
    return esc_html((string) $value);
}

function debt_user($id){
     global $wpdb;
    // محاسبه بدهکاری (صورت حساب‌های pending و under_review)
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $wallet_balance = function_exists('sc_get_wallet_balance') ? sc_get_wallet_balance($id) : 0;
    $debt_wallet = 0;
    if($wallet_balance < 0){
        $debt_wallet = abs($wallet_balance);
    }
    
    $debt_info = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as count,
            SUM(amount + COALESCE(penalty_amount, 0)) as total_debt
         FROM $invoices_table
         WHERE member_id = %d 
         AND status IN ('pending', 'under_review') AND (course_id > 0 OR invoice_description IS NOT NULL)",
        $id
    ));
    $debt_count = $debt_info->count ?? 0;
    $total_debt = floatval($debt_info->total_debt ?? 0);
    $debt = $total_debt + $debt_wallet;
    return [$debt,$debt_count];
}

function sc_attendance_debt_block_enabled() {
    return (int) sc_get_setting('attendance_debt_block_enabled', '0') === 1;
}

function sc_attendance_get_max_debt_for_attendance() {
    return floatval(sc_get_setting('max_debt_for_attendance', '0'));
}

function sc_attendance_member_debt_blocked($member_id) {
    $member_id = absint($member_id);
    $max_debt = sc_attendance_get_max_debt_for_attendance();
    if (!$member_id || !sc_attendance_debt_block_enabled() || $max_debt <= 0 || !function_exists('debt_user')) {
        return false;
    }

    $debt_data = debt_user($member_id);
    $debt = isset($debt_data[0]) ? floatval($debt_data[0]) : 0;
    return $debt >= $max_debt;
}


// تغییر فرمت درست شماره تماس ها 

// تبدیل اعداد فارسی و عربی به انگلیسی
function fa_to_en_digits( $string ) {
    $western = ['0','1','2','3','4','5','6','7','8','9'];
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];

    $string = str_replace( $persian, $western, $string );
    return str_replace( $arabic, $western, $string );
}

/**
 * نرمال‌سازی شماره موبایل ایران
 */
function sanitize_iran_phone( $phone ) {

    $phone = fa_to_en_digits( trim( $phone ) );

    // حذف فاصله و نقطه
    $phone = str_replace( [' ', '.'], '', $phone );

    // .915xxxxxxx
    if ( strpos( $phone, '.' ) === 0 ) {
        $phone = '0' . substr( $phone, 1 );
    }

    // 0098xxxxxxxxxx
    if ( strpos( $phone, '0098' ) === 0 ) {
        $phone = substr( $phone, 4 );
    }

    // +98xxxxxxxxxx
    if ( strpos( $phone, '+98' ) === 0 ) {
        $phone = substr( $phone, 3 );
    }

    // 98xxxxxxxxxx
    if ( strpos( $phone, '98' ) === 0 ) {
        $phone = substr( $phone, 2 );
    }

    // اضافه کردن صفر در صورت نبود
    if ( strpos( $phone, '0' ) !== 0 ) {
        $phone = '0' . $phone;
    }

    // فقط عدد باشد
    if ( ! ctype_digit( $phone ) ) {
        return false;
    }

    // طول موبایل ایران
    if ( strlen( $phone ) !== 11 ) {
        return false;
    }

    // شروع با 09
    if ( strpos( $phone, '09' ) !== 0 ) {
        return false;
    }

    return $phone;
}
add_filter( 'woocommerce_checkout_posted_data', function ( $data ) {

    if ( ! empty( $data['billing_phone'] ) ) {
        $phone = sanitize_iran_phone( $data['billing_phone'] );

        if ( $phone !== false ) {
            $data['billing_phone'] = $phone;
        }
    }

    return $data;
});
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {

    if ( empty( $data['billing_phone'] ) ) {
        return;
    }

    $phone = sanitize_iran_phone( $data['billing_phone'] );

    if ( $phone === false ) {
        $errors->add(
            'billing_phone_error',
            'شماره موبایل وارد شده معتبر نیست.'
        );
    }
}, 100, 2 );


//پایان فرمت صحیح شماره تماس ها 



function get_cart_item_count() {
    $cart = WC()->cart;

    if ( $cart ) {
        return ['count' => $cart->get_cart_contents_count() , 'sum' => number_format( $cart->get_total( true ) )]; // تعداد کل اقلام (با توجه به تعداد هر محصول)
       
    }
    return 0;
}

//کم کردن یک جلسه برای کاربر
function sc_decrease_member_session($member_id, $course_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'sc_member_courses';

    $member_course = $wpdb->get_row($wpdb->prepare(
        "SELECT id, remaining_sessions 
         FROM $table 
         WHERE member_id = %d AND course_id = %d AND status = 'active'
         LIMIT 1",
        $member_id,
        $course_id
    ));

    if (!$member_course) {
        return false;
    }

    if ($member_course->remaining_sessions <= 0) {
        return false;
    }

    $was_last_session = ((int) $member_course->remaining_sessions === 1);

    $wpdb->query($wpdb->prepare(
        "UPDATE $table
         SET remaining_sessions = remaining_sessions - 1,
             updated_at = %s
         WHERE id = %d",
        current_time('mysql'),
        $member_course->id
    ));

    if ($was_last_session) {
        do_action('sc_member_course_last_session', $member_id, $course_id);
    }

    return true;
}
//حذف سوخت جلسه غیبت - بازگرداندن یک جلسه  افزایش یک جلسه

function sc_increase_member_session($member_id, $course_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_member_courses';

    // پیدا کردن رکورد فعال کاربر در دوره
    $member_course = $wpdb->get_row($wpdb->prepare("
        SELECT id FROM $table 
        WHERE member_id = %d AND course_id = %d AND status = 'active'
        LIMIT 1
    ", $member_id, $course_id));

    if (!$member_course) return false;

    // افزایش یک جلسه
    $wpdb->query($wpdb->prepare("
        UPDATE $table 
        SET remaining_sessions = remaining_sessions + 1, updated_at = %s 
        WHERE id = %d
    ", current_time('mysql'), $member_course->id));

    return true;
}







