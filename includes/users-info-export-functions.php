<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_users_export_get_default_templates() {
    return [
        'id_card' => [
            'key' => 'id_card',
            'title' => 'کارت شناسایی بازیکن',
            'description' => 'قالب کارت شناسایی با اطلاعات اصلی بازیکن',
            'page_size' => 'A4',
            'cards_per_page' => 2,
            'fields' => [
                'full_name',
                'father_name',
                'player_phone',
                'skill_level',
                'personal_photo',
                'insurance_expiry_date_shamsi',
            ],
            'layout' => [
                'photo_position' => 'left',
                'right_fields' => ['full_name', 'father_name', 'player_phone', 'skill_level', 'insurance_expiry_date_shamsi'],
                'left_fields' => [],
            ],
            'background_color' => '#ffffff',
            'background_image' => '',
            'background_opacity' => 0.2,
            'columns_count' => 2,
            'layout_columns_count' => 2,
            'image_only_mode' => 0,
            'card_footer_text' => '',
            'content_font_family' => 'IRANYekanXFaNum',
            'card_size_preset' => 'id_card',
            'card_width_cm' => 8.5,
            'card_height_cm' => 5.4,
            'card_padding_top' => 3,
            'card_padding_right' => 3,
            'card_padding_bottom' => 3,
            'card_padding_left' => 3,
            'column_paddings' => [],
            'column_widths' => [],
            'image_style' => 'rounded',
            'content_font_size' => 13,
            'image_sizes' => [],
            'custom_css' => '',
            'show_field_labels' => 1,
        ],
    ];
}

/**
 * @return array<string, array{width: int, height: int}>
 */
function sc_users_export_get_default_image_sizes() {
    return [
        'personal_photo' => ['width' => 150, 'height' => 190],
        'id_card_photo' => ['width' => 260, 'height' => 150],
        'sport_insurance_photo' => ['width' => 260, 'height' => 150],
        'attendance_qr' => ['width' => 180, 'height' => 180],
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, array{width: int, height: int}>
 */
function sc_users_export_normalize_image_sizes($raw) {
    $defaults = sc_users_export_get_default_image_sizes();
    $raw = is_array($raw) ? $raw : [];
    $out = [];
    foreach ($defaults as $field_key => $default_size) {
        $entry = isset($raw[$field_key]) && is_array($raw[$field_key]) ? $raw[$field_key] : [];
        $out[$field_key] = [
            'width' => isset($entry['width']) ? max(20, min(600, (int) wp_unslash($entry['width']))) : (int) $default_size['width'],
            'height' => isset($entry['height']) ? max(20, min(600, (int) wp_unslash($entry['height']))) : (int) $default_size['height'],
        ];
    }
    return $out;
}

function sc_users_export_sanitize_custom_css($css) {
    $css = (string) wp_unslash($css);
    $css = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $css);
    $css = preg_replace('/@import\b[^;]+;/i', '', $css);
    $css = str_replace(['</style>', '<style'], '', $css);
    return trim($css);
}

/**
 * @return array<string, array{label: string, width: float|null, height: float|null}>
 */
function sc_users_export_get_card_size_presets() {
    return [
        'id_card' => ['label' => 'کارت شناسایی (۸.۵ × ۵.۴ سانتی‌متر)', 'width' => 8.5, 'height' => 5.4],
        'id_card_small' => ['label' => 'کارت کوچک (۸.۵ × ۴.۵ سانتی‌متر)', 'width' => 8.5, 'height' => 4.5],
        'business_card' => ['label' => 'کارت ویزیت (۹ × ۵ سانتی‌متر)', 'width' => 9.0, 'height' => 5.0],
        'credit_card' => ['label' => 'کارت اعتباری ISO (۸.۶ × ۵.۴ سانتی‌متر)', 'width' => 8.56, 'height' => 5.398],
        'a7' => ['label' => 'A7 (۷.۴ × ۱۰.۵ سانتی‌متر)', 'width' => 7.4, 'height' => 10.5],
        'custom' => ['label' => 'سفارشی', 'width' => null, 'height' => null],
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array{top: float, right: float, bottom: float, left: float}
 */
function sc_users_export_normalize_padding_box($raw, $defaults = []) {
    $defaults = wp_parse_args($defaults, ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0]);
    if (!is_array($raw)) {
        $raw = [];
    }
    $out = [];
    foreach (['top', 'right', 'bottom', 'left'] as $side) {
        $value = isset($raw[$side]) ? (float) wp_unslash($raw[$side]) : (float) $defaults[$side];
        $out[$side] = max(0, min(50, $value));
    }
    return $out;
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, array{top: float, right: float, bottom: float, left: float}>
 */
function sc_users_export_normalize_column_paddings($raw, $columns_count = 2) {
    $columns_count = max(1, min(4, (int) $columns_count));
    $raw = is_array($raw) ? $raw : [];
    $out = [];
    for ($i = 1; $i <= $columns_count; $i++) {
        $col_key = 'column_' . $i;
        $out[$col_key] = sc_users_export_normalize_padding_box($raw[$col_key] ?? []);
    }
    return $out;
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, float>
 */
function sc_users_export_normalize_column_widths($raw, $columns_count = 2) {
    $columns_count = max(1, min(4, (int) $columns_count));
    $raw = is_array($raw) ? $raw : [];
    $default_each = round(100 / $columns_count, 2);
    $out = [];
    for ($i = 1; $i <= $columns_count; $i++) {
        $col_key = 'column_' . $i;
        $value = isset($raw[$col_key]) ? (float) wp_unslash($raw[$col_key]) : $default_each;
        $out[$col_key] = max(5, min(95, $value));
    }
    return $out;
}

/**
 * @param array<string, float> $column_widths
 */
function sc_users_export_build_layout_grid_template_columns($column_widths, $columns_count = 2) {
    $columns_count = max(1, min(4, (int) $columns_count));
    $parts = [];
    $total = 0.0;
    for ($i = 1; $i <= $columns_count; $i++) {
        $col_key = 'column_' . $i;
        $weight = isset($column_widths[$col_key]) ? (float) $column_widths[$col_key] : round(100 / $columns_count, 2);
        if ($weight <= 0) {
            $weight = round(100 / $columns_count, 2);
        }
        $parts[] = $weight;
        $total += $weight;
    }
    if ($total <= 0) {
        return 'repeat(' . $columns_count . ', minmax(0, 1fr))';
    }
    $css_parts = [];
    foreach ($parts as $weight) {
        $css_parts[] = 'minmax(0, ' . $weight . 'fr)';
    }
    return implode(' ', $css_parts);
}

/**
 * @param array<string, mixed> $template
 * @return array{width: float, height: float}
 */
function sc_users_export_resolve_card_dimensions($template) {
    $presets = sc_users_export_get_card_size_presets();
    $preset = isset($template['card_size_preset']) ? sanitize_key($template['card_size_preset']) : 'id_card';
    if ($preset !== 'custom' && isset($presets[$preset]) && $presets[$preset]['width'] !== null) {
        return [
            'width' => (float) $presets[$preset]['width'],
            'height' => (float) $presets[$preset]['height'],
        ];
    }
    return [
        'width' => isset($template['card_width_cm']) ? max(1, min(30, (float) $template['card_width_cm'])) : 8.5,
        'height' => isset($template['card_height_cm']) ? max(1, min(30, (float) $template['card_height_cm'])) : 5.4,
    ];
}

function sc_users_export_get_saved_templates() {
    $saved = get_option('sc_users_export_templates', null);
    if ($saved === null) {
        return sc_users_export_get_default_templates();
    }
    if (!is_array($saved)) {
        return sc_users_export_get_default_templates();
    }
    $normalized = [];
    foreach ($saved as $template_key => $template) {
        if (!is_array($template)) {
            continue;
        }
        $normalized_template = sc_users_export_normalize_template($template, is_string($template_key) ? $template_key : '');
        $normalized[$normalized_template['key']] = $normalized_template;
    }
    return !empty($normalized) ? $normalized : sc_users_export_get_default_templates();
}

function sc_users_export_save_templates($templates) {
    if (!is_array($templates)) {
        return;
    }
    update_option('sc_users_export_templates', $templates, false);
}

function sc_users_export_event_field_key($field_id) {
    return 'event_field_' . absint($field_id);
}

/**
 * @param int[] $event_ids
 * @return array<string, string>
 */
function sc_users_export_get_event_field_labels($event_ids = []) {
    global $wpdb;
    $event_ids = array_values(array_filter(array_map('absint', (array) $event_ids)));
    if (empty($event_ids)) {
        return [];
    }
    $event_fields_table = $wpdb->prefix . 'sc_event_fields';
    $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));
    $fields = $wpdb->get_results($wpdb->prepare(
        "SELECT id, field_name, event_id FROM $event_fields_table WHERE event_id IN ($placeholders) ORDER BY field_order ASC, id ASC",
        $event_ids
    ));
    $labels = [];
    foreach ((array) $fields as $field) {
        $labels[sc_users_export_event_field_key($field->id)] = $field->field_name;
    }
    return $labels;
}

/**
 * @param int[] $event_ids
 * @return array<string, string>
 */
function sc_users_export_merge_field_labels($event_ids = []) {
    $labels = sc_users_export_get_field_labels();
    if (!empty($event_ids)) {
        $labels['registration_type'] = 'نوع ثبت‌نام';
        $labels = array_merge($labels, sc_users_export_get_event_field_labels($event_ids));
    }
    return $labels;
}

/**
 * @param array<string, mixed> $entry
 */
function sc_users_export_format_event_field_value($entry) {
    if (!is_array($entry)) {
        return '-';
    }
    $field_type = isset($entry['field_type']) ? (string) $entry['field_type'] : '';
    if ($field_type === 'file') {
        $files = [];
        if (isset($entry['files']) && is_array($entry['files'])) {
            foreach ($entry['files'] as $file) {
                if (is_array($file) && !empty($file['url'])) {
                    $files[] = (string) $file['url'];
                } elseif (is_array($file) && !empty($file['name'])) {
                    $files[] = (string) $file['name'];
                }
            }
        }
        return !empty($files) ? implode('، ', $files) : '-';
    }
    $value = $entry['value'] ?? '';
    if (is_array($value)) {
        $value = implode('، ', array_map('strval', $value));
    }
    $value = trim((string) $value);
    return $value !== '' ? $value : '-';
}

function sc_users_export_normalize_template($template, $fallback_key = '') {
    $event_id = isset($template['event_id']) ? absint($template['event_id']) : 0;
    $is_event_template = !empty($template['is_event_template']) && $event_id > 0;
    $event_ids = $is_event_template ? [$event_id] : [];
    $labels = sc_users_export_merge_field_labels($event_ids);
    $allowed_fields = array_keys($labels);
    $key = isset($template['key']) ? sanitize_key($template['key']) : sanitize_key($fallback_key);
    if ($key === '') {
        $key = 'template_' . wp_generate_password(6, false, false);
    }

    $fields = isset($template['fields']) && is_array($template['fields']) ? array_map('sanitize_text_field', $template['fields']) : [];
    $fields = array_values(array_intersect($fields, $allowed_fields));
    if (empty($fields)) {
        $fields = ['full_name', 'player_phone'];
    }

    $layout = isset($template['layout']) && is_array($template['layout']) ? $template['layout'] : [];
    $columns_count = isset($template['columns_count']) ? max(1, min(4, (int) $template['columns_count'])) : 2;
    $layout_columns_count = isset($template['layout_columns_count']) ? max(1, min(4, (int) $template['layout_columns_count'])) : $columns_count;
    $columns = [];
    if (isset($layout['columns']) && is_array($layout['columns'])) {
        for ($i = 1; $i <= $layout_columns_count; $i++) {
            $col_key = 'column_' . $i;
            $col_fields = isset($layout['columns'][$col_key]) && is_array($layout['columns'][$col_key]) ? array_map('sanitize_text_field', $layout['columns'][$col_key]) : [];
            $columns[$col_key] = array_values(array_intersect($col_fields, $fields));
        }
    } else {
        $right_fields = isset($layout['right_fields']) && is_array($layout['right_fields']) ? array_map('sanitize_text_field', $layout['right_fields']) : [];
        $left_fields = isset($layout['left_fields']) && is_array($layout['left_fields']) ? array_map('sanitize_text_field', $layout['left_fields']) : [];
        $columns['column_1'] = array_values(array_intersect($right_fields, $fields));
        $columns['column_2'] = array_values(array_intersect($left_fields, $fields));
        for ($i = 3; $i <= $layout_columns_count; $i++) {
            $columns['column_' . $i] = [];
        }
    }
    $assigned = [];
    foreach ($columns as $col_fields) {
        foreach ($col_fields as $f) {
            $assigned[$f] = true;
        }
    }
    foreach ($fields as $field_key) {
        if (!isset($assigned[$field_key]) && !in_array($field_key, ['personal_photo', 'attendance_qr'], true)) {
            $columns['column_1'][] = $field_key;
        }
    }

    return [
        'key' => $key,
        'title' => isset($template['title']) ? sanitize_text_field(wp_unslash($template['title'])) : 'قالب جدید',
        'description' => isset($template['description']) ? sanitize_textarea_field(wp_unslash($template['description'])) : '',
        'is_event_template' => $is_event_template ? 1 : 0,
        'event_id' => $is_event_template ? $event_id : 0,
        'page_size' => (isset($template['page_size']) && in_array($template['page_size'], ['A4', 'A5'], true)) ? $template['page_size'] : 'A4',
        'cards_per_page' => isset($template['cards_per_page']) ? max(1, min(6, (int) $template['cards_per_page'])) : 2,
        'fields' => $fields,
        'layout' => [
            'columns' => $columns,
        ],
        'background_color' => isset($template['background_color']) ? sanitize_text_field(wp_unslash($template['background_color'])) : '#ffffff',
        'background_image' => isset($template['background_image']) ? esc_url_raw(wp_unslash($template['background_image'])) : '',
        'background_opacity' => isset($template['background_opacity']) ? max(0, min(1, (float) wp_unslash($template['background_opacity']))) : 0.2,
        'columns_count' => $columns_count,
        'layout_columns_count' => $layout_columns_count,
        'image_only_mode' => !empty($template['image_only_mode']) ? 1 : 0,
        'card_footer_text' => isset($template['card_footer_text']) ? sanitize_textarea_field(wp_unslash($template['card_footer_text'])) : '',
        'content_font_family' => (isset($template['content_font_family']) && in_array(wp_unslash($template['content_font_family']), ['IRANYekanXFaNum', 'Vazir', 'Shabnam', 'Morabba', 'Tahoma', 'Arial'], true))
            ? wp_unslash($template['content_font_family'])
            : 'IRANYekanXFaNum',
        'card_size_preset' => (isset($template['card_size_preset']) && array_key_exists(sanitize_key($template['card_size_preset']), sc_users_export_get_card_size_presets()))
            ? sanitize_key($template['card_size_preset'])
            : 'id_card',
        'card_width_cm' => isset($template['card_width_cm']) ? max(1, min(30, (float) wp_unslash($template['card_width_cm']))) : 8.5,
        'card_height_cm' => isset($template['card_height_cm']) ? max(1, min(30, (float) wp_unslash($template['card_height_cm']))) : 5.4,
        'card_padding_top' => isset($template['card_padding_top']) ? max(0, min(50, (float) wp_unslash($template['card_padding_top']))) : 3,
        'card_padding_right' => isset($template['card_padding_right']) ? max(0, min(50, (float) wp_unslash($template['card_padding_right']))) : 3,
        'card_padding_bottom' => isset($template['card_padding_bottom']) ? max(0, min(50, (float) wp_unslash($template['card_padding_bottom']))) : 3,
        'card_padding_left' => isset($template['card_padding_left']) ? max(0, min(50, (float) wp_unslash($template['card_padding_left']))) : 3,
        'column_paddings' => sc_users_export_normalize_column_paddings($template['column_paddings'] ?? [], $layout_columns_count),
        'column_widths' => sc_users_export_normalize_column_widths($template['column_widths'] ?? [], $layout_columns_count),
        'image_style' => (isset($template['image_style']) && in_array($template['image_style'], ['circle', 'rounded'], true))
            ? $template['image_style']
            : 'rounded',
        'content_font_size' => isset($template['content_font_size']) ? max(8, min(32, (int) wp_unslash($template['content_font_size']))) : 13,
        'image_sizes' => sc_users_export_normalize_image_sizes($template['image_sizes'] ?? []),
        'custom_css' => isset($template['custom_css']) ? sc_users_export_sanitize_custom_css($template['custom_css']) : '',
        'show_field_labels' => array_key_exists('show_field_labels', $template) ? (!empty($template['show_field_labels']) ? 1 : 0) : 1,
    ];
}

/**
 * وضعیت بیمه به‌صورت متن ساده (هم‌تراز با ستون «بیمه» در لیست بازیکنان).
 *
 * @param object $member ردیف sc_members
 */
function sc_users_export_plain_insurance_status($member) {
    $insurance_expiry_date = isset($member->insurance_expiry_date_shamsi) ? trim((string) $member->insurance_expiry_date_shamsi) : '';
    if ($insurance_expiry_date === '') {
        return '-';
    }
    $today = new DateTime(current_time('mysql'));
    if (!function_exists('gregorian_to_jalali')) {
        return '-';
    }
    $today_jalali = gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('m'), (int) $today->format('d'));
    $today_shamsi = $today_jalali[0] . '/'
        . str_pad((string) $today_jalali[1], 2, '0', STR_PAD_LEFT) . '/'
        . str_pad((string) $today_jalali[2], 2, '0', STR_PAD_LEFT);

    $expiry_parts = explode('/', $insurance_expiry_date);
    $today_parts = explode('/', $today_shamsi);
    if (count($expiry_parts) !== 3 || count($today_parts) !== 3) {
        return '-';
    }
    $expiry_year = (int) $expiry_parts[0];
    $expiry_month = (int) $expiry_parts[1];
    $expiry_day = (int) $expiry_parts[2];
    $today_year = (int) $today_parts[0];
    $today_month = (int) $today_parts[1];
    $today_day = (int) $today_parts[2];

    $is_expired = false;
    if ($expiry_year < $today_year) {
        $is_expired = true;
    } elseif ($expiry_year === $today_year) {
        if ($expiry_month < $today_month) {
            $is_expired = true;
        } elseif ($expiry_month === $today_month && $expiry_day < $today_day) {
            $is_expired = true;
        }
    }

    return $is_expired ? 'منقضی' : 'فعال';
}

function sc_users_export_get_field_labels() {
    $labels = [
        'member_id' => 'شناسه بازیکن',
        'full_name' => 'نام و نام خانوادگی',
        'father_name' => 'نام پدر',
        'national_id' => 'کد ملی',
        'birthday_place' => 'محل تولد',
        'player_phone' => 'شماره همراه',
        'father_phone' => 'موبایل پدر',
        'mother_phone' => 'موبایل مادر',
        'landline_phone' => 'تلفن ثابت',
        'address' => 'آدرس',
        'birth_date_shamsi' => 'تاریخ تولد',
        'health_verified' => 'تایید سلامت',
        'info_verified' => 'تایید اطلاعات',
        'skill_level' => 'سطح بازیکن',
        'member_type' => 'نوع بازیکن',
        'team_player' => 'تیم بازیکن',
        'team_level' => 'تیم و سطح',
        'profile_completed' => 'تکمیل پروفایل',
        'identity_verified' => 'احراز هویت',
        'is_active' => 'وضعیت',
        'insurance_status' => 'بیمه',
        'active_events' => 'رویدادهای ثبت نام شده',
        'insurance_expiry_date_shamsi' => 'تاریخ بیمه',
        'active_courses' => 'دوره‌های فعال',
        'medical_condition' => 'وضعیت پزشکی',
        'sports_history' => 'سابقه ورزشی',
        'additional_info' => 'اطلاعات تکمیلی',
        'personal_photo' => 'عکس پرسنلی',
        'id_card_photo' => 'عکس کارت ملی',
        'sport_insurance_photo' => 'عکس بیمه ورزشی',
        'attendance_qr' => 'QR حضور و غیاب',
        'attendance_qr_short_code' => 'کد QR (۷ حرف)',
        'attendance_qr_all_codes' => 'همه کدهای QR',
    ];
    if (function_exists('sc_get_player_info_custom_fields')) {
        $custom_fields = sc_get_player_info_custom_fields();
        foreach ($custom_fields as $field) {
            if (empty($field['key']) || empty($field['visible'])) {
                continue;
            }
            $labels['custom_' . $field['key']] = $field['label'] ?? $field['key'];
        }
    }
    return $labels;
}

function sc_users_export_get_allowed_target_types() {
    $types = ['all', 'free_users', 'specific', 'course', 'event', 'team', 'level', 'team_level', 'identity_verified', 'identity_unverified'];
    if (function_exists('sc_is_registration_fee_enabled') && sc_is_registration_fee_enabled()) {
        $types[] = 'registration_fee_unpaid';
    }
    return $types;
}

/**
 * رکوردهای خروجی: برای رویداد شامل مهمان‌ها؛ در سایر حالت‌ها همان اعضا.
 *
 * @return object[]
 */
function sc_users_export_get_subjects($target_type, $config = []) {
    if ($target_type === 'event') {
        return sc_users_export_get_event_registrants($config);
    }
    return sc_users_export_get_members($target_type, $config);
}

/**
 * @param array $config
 * @return object[]
 */
function sc_users_export_get_event_registrants($config = []) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';
    $events_table = $wpdb->prefix . 'sc_events';

    $event_ids = isset($config['event_ids']) && is_array($config['event_ids']) ? array_filter(array_map('absint', $config['event_ids'])) : [];
    if (empty($event_ids)) {
        return [];
    }

    $member_type_filter = isset($config['member_type']) ? sanitize_text_field($config['member_type']) : 'all';
    $excluded_ids = isset($config['excluded_member_ids']) && is_array($config['excluded_member_ids'])
        ? array_filter(array_map('absint', $config['excluded_member_ids']))
        : [];

    $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));
    $query = "
        SELECT r.id AS registration_id,
               r.event_id,
               r.member_id,
               r.invoice_id,
               r.field_data,
               r.files,
               r.registration_source,
               r.guest_first_name,
               r.guest_last_name,
               r.guest_phone,
               r.guest_national_id,
               r.created_at AS registration_created_at,
               m.*
        FROM $event_registrations_table r
        LEFT JOIN $members_table m ON m.id = r.member_id
        WHERE r.event_id IN ($placeholders)
        ORDER BY r.created_at ASC, r.id ASC
    ";
    $registrations = $wpdb->get_results($wpdb->prepare($query, $event_ids));
    if (empty($registrations)) {
        return [];
    }

    $subjects = [];
    foreach ($registrations as $registration) {
        $is_guest = isset($registration->registration_source) && $registration->registration_source === 'guest';
        if (!$is_guest && empty($registration->member_id)) {
            $is_guest = !empty($registration->guest_national_id) || !empty($registration->guest_phone);
        }

        if (!$is_guest) {
            if (empty($registration->member_id)) {
                continue;
            }
            if (!empty($registration->is_active) && (int) $registration->is_active !== 1) {
                // inactive member — still export if registered
            }
            if ($member_type_filter === 'normal' && isset($registration->member_type) && $registration->member_type === 'team') {
                continue;
            }
            if ($member_type_filter === 'team' && (!isset($registration->member_type) || $registration->member_type !== 'team')) {
                continue;
            }
            if (!empty($excluded_ids) && in_array((int) $registration->member_id, $excluded_ids, true)) {
                continue;
            }
        } elseif ($member_type_filter !== 'all') {
            continue;
        }

        $subject = sc_users_export_build_subject_from_registration($registration, $is_guest);
        if (!$subject) {
            continue;
        }

        if (!empty($subject->is_guest_export)) {
            $subject->active_courses = '-';
        } else {
            $courses = $wpdb->get_col($wpdb->prepare(
                "SELECT c.title
                 FROM $member_courses_table mc
                 INNER JOIN $courses_table c ON c.id = mc.course_id
                 WHERE mc.member_id = %d
                   AND mc.status = 'active'
                   AND (mc.course_status_flags IS NULL OR TRIM(mc.course_status_flags) = '')
                   AND c.deleted_at IS NULL
                 ORDER BY c.title ASC",
                (int) $subject->id
            ));
            $subject->active_courses = !empty($courses) ? implode('، ', $courses) : '-';
        }

        if (!$is_guest && (int) $subject->id > 0) {
            $events = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT e.name
                 FROM $event_registrations_table er
                 INNER JOIN $events_table e ON e.id = er.event_id
                 WHERE er.member_id = %d
                   AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
                 ORDER BY e.name ASC",
                (int) $subject->id
            ));
            $subject->active_events = !empty($events) ? implode('، ', $events) : '-';
        } else {
            $event_name = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM $events_table WHERE id = %d",
                (int) $registration->event_id
            ));
            $subject->active_events = $event_name ? (string) $event_name : '-';
        }

        $subjects[] = $subject;
    }

    return $subjects;
}

/**
 * @param object $registration
 */
function sc_users_export_build_subject_from_registration($registration, $is_guest = false) {
    $field_data_raw = isset($registration->field_data) ? (string) $registration->field_data : '';
    $parsed_field_data = [];
    if ($field_data_raw !== '') {
        $decoded = json_decode($field_data_raw, true);
        if (is_array($decoded)) {
            $parsed_field_data = $decoded;
        }
    }

    $files_raw = isset($registration->files) ? (string) $registration->files : '';
    if ($files_raw !== '') {
        $decoded_files = json_decode($files_raw, true);
        if (is_array($decoded_files)) {
            foreach ($decoded_files as $field_id => $file_list) {
                $field_id = (string) $field_id;
                if (!isset($parsed_field_data[$field_id]) || !is_array($parsed_field_data[$field_id])) {
                    continue;
                }
                $parsed_field_data[$field_id]['files'] = $file_list;
            }
        }
    }

    if ($is_guest) {
        $subject = (object) [
            'id' => 0,
            'registration_id' => (int) ($registration->registration_id ?? 0),
            'event_id' => (int) $registration->event_id,
            'is_guest_export' => 1,
            'first_name' => (string) ($registration->guest_first_name ?? ''),
            'last_name' => (string) ($registration->guest_last_name ?? ''),
            'national_id' => (string) ($registration->guest_national_id ?? ''),
            'player_phone' => (string) ($registration->guest_phone ?? ''),
            'member_type' => '',
            'team_player' => '',
            'skill_level' => '',
            'is_active' => 1,
            'profile_completed' => 0,
            'identity_verified' => 0,
            'health_verified' => 0,
            'info_verified' => 0,
            'member_extra_fields' => null,
            '_event_field_data' => $parsed_field_data,
        ];
        return $subject;
    }

    if (empty($registration->member_id)) {
        return null;
    }

    $subject = clone $registration;
    $subject->id = (int) $registration->member_id;
    $subject->registration_id = (int) ($registration->registration_id ?? 0);
    $subject->event_id = (int) $registration->event_id;
    $subject->is_guest_export = 0;
    $subject->_event_field_data = $parsed_field_data;
    return $subject;
}

function sc_users_export_get_members($target_type, $config = []) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';
    $events_table = $wpdb->prefix . 'sc_events';

    $where = ["m.is_active = 1"];
    $params = [];

    if (!empty($config['member_type']) && in_array($config['member_type'], ['normal', 'team'], true)) {
        $where[] = "(COALESCE(m.member_type, 'normal') = %s)";
        $params[] = $config['member_type'];
    }

    $excluded_ids = isset($config['excluded_member_ids']) && is_array($config['excluded_member_ids'])
        ? array_filter(array_map('absint', $config['excluded_member_ids']))
        : [];

    if ($target_type === 'free_users') {
        $where[] = "NOT EXISTS (
            SELECT 1
            FROM $member_courses_table mc
            WHERE mc.member_id = m.id
        )";
    } elseif ($target_type === 'specific') {
        $ids = isset($config['member_ids']) && is_array($config['member_ids']) ? array_filter(array_map('absint', $config['member_ids'])) : [];
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $where[] = "m.id IN ($placeholders)";
        $params = array_merge($params, $ids);
    } elseif ($target_type === 'course') {
        $course_values = function_exists('sc_audience_normalize_course_ids_from_request')
            ? sc_audience_normalize_course_ids_from_request($config['course_ids'] ?? [])
            : array_filter(array_map('absint', (array) ($config['course_ids'] ?? [])));
        if (empty($course_values)) {
            return [];
        }
        $subquery = function_exists('sc_audience_member_ids_by_course_subquery_sql')
            ? sc_audience_member_ids_by_course_subquery_sql($course_values)
            : null;
        if ($subquery) {
            $where[] = $subquery['sql'];
            $params = array_merge($params, $subquery['args']);
        }
    } elseif ($target_type === 'event') {
        $event_ids = isset($config['event_ids']) && is_array($config['event_ids']) ? array_filter(array_map('absint', $config['event_ids'])) : [];
        if (empty($event_ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));
        $where[] = "m.id IN (
            SELECT DISTINCT er.member_id
            FROM $event_registrations_table er
            WHERE er.event_id IN ($placeholders)
        )";
        $params = array_merge($params, $event_ids);
    } elseif ($target_type === 'team') {
        $team_names = isset($config['team_names']) && is_array($config['team_names']) ? array_filter(array_map('sanitize_text_field', $config['team_names'])) : [];
        if (empty($team_names)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($team_names), '%s'));
        $where[] = "m.team_player IN ($placeholders)";
        $params = array_merge($params, $team_names);
    } elseif ($target_type === 'level') {
        $level_names = isset($config['level_names']) && is_array($config['level_names']) ? array_filter(array_map('sanitize_text_field', $config['level_names'])) : [];
        if (empty($level_names)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($level_names), '%s'));
        $where[] = "m.skill_level IN ($placeholders)";
        $params = array_merge($params, $level_names);
    } elseif ($target_type === 'team_level') {
        $team_names = isset($config['team_names']) && is_array($config['team_names']) ? array_filter(array_map('sanitize_text_field', $config['team_names'])) : [];
        $level_names = isset($config['level_names']) && is_array($config['level_names']) ? array_filter(array_map('sanitize_text_field', $config['level_names'])) : [];
        if (empty($team_names) || empty($level_names)) {
            return [];
        }
        $team_placeholders = implode(',', array_fill(0, count($team_names), '%s'));
        $level_placeholders = implode(',', array_fill(0, count($level_names), '%s'));
        $where[] = "m.team_player IN ($team_placeholders)";
        $where[] = "m.skill_level IN ($level_placeholders)";
        $params = array_merge($params, $team_names, $level_names);
    } elseif ($target_type === 'identity_verified') {
        $where[] = 'm.identity_verified = 1';
    } elseif ($target_type === 'identity_unverified') {
        $where[] = '(m.identity_verified = 0 OR m.identity_verified IS NULL)';
    } elseif ($target_type === 'registration_fee_unpaid') {
        if (!function_exists('sc_is_registration_fee_enabled') || !sc_is_registration_fee_enabled()) {
            return [];
        }
        $where[] = '(m.registration_fee_paid = 0 OR m.registration_fee_paid IS NULL)';
    } else {
        // all: no extra where
    }

    if (!empty($excluded_ids)) {
        $exclude_placeholders = implode(',', array_fill(0, count($excluded_ids), '%d'));
        $where[] = "m.id NOT IN ($exclude_placeholders)";
        $params = array_merge($params, $excluded_ids);
    }

    if (function_exists('sc_secretary_merge_member_where_parts')) {
        sc_secretary_merge_member_where_parts($where, $params, 'm');
    }

    $where_sql = implode(' AND ', $where);
    $query = "
        SELECT m.*
        FROM $members_table m
        WHERE $where_sql
        ORDER BY m.last_name ASC, m.first_name ASC
    ";

    $members = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);
    if (empty($members)) {
        return [];
    }

    foreach ($members as $member) {
        $courses = $wpdb->get_col($wpdb->prepare(
            "SELECT c.title
             FROM $member_courses_table mc
             INNER JOIN $courses_table c ON c.id = mc.course_id
             WHERE mc.member_id = %d
               AND mc.status = 'active'
               AND (mc.course_status_flags IS NULL OR TRIM(mc.course_status_flags) = '')
               AND c.deleted_at IS NULL
             ORDER BY c.title ASC",
            $member->id
        ));
        $member->active_courses = !empty($courses) ? implode('، ', $courses) : '-';

        $events = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT e.name
             FROM $event_registrations_table er
             INNER JOIN $events_table e ON e.id = er.event_id
             WHERE er.member_id = %d
               AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
             ORDER BY e.name ASC",
            $member->id
        ));
        $member->active_events = !empty($events) ? implode('، ', $events) : '-';
    }

    return $members;
}

function sc_users_export_prepare_rows($members, $fields) {
    $rows = [];
    foreach ($members as $member) {
        $row = [];
        foreach ($fields as $field) {
            if (strpos($field, 'event_field_') === 0) {
                $field_id = absint(substr($field, strlen('event_field_')));
                $event_data = isset($member->_event_field_data) && is_array($member->_event_field_data) ? $member->_event_field_data : [];
                $entry = null;
                if (isset($event_data[$field_id])) {
                    $entry = $event_data[$field_id];
                } elseif (isset($event_data[(string) $field_id])) {
                    $entry = $event_data[(string) $field_id];
                }
                $row[$field] = sc_users_export_format_event_field_value($entry);
                continue;
            }

            switch ($field) {
                case 'member_id':
                    $row[$field] = !empty($member->is_guest_export) ? '-' : (int) $member->id;
                    break;
                case 'registration_type':
                    $row[$field] = !empty($member->is_guest_export) ? 'مهمان' : 'عضو باشگاه';
                    break;
                case 'full_name':
                    $row[$field] = trim(($member->first_name ?: '') . ' ' . ($member->last_name ?: ''));
                    break;
                case 'active_courses':
                    $row[$field] = isset($member->active_courses) ? $member->active_courses : '-';
                    break;
                case 'active_events':
                    $row[$field] = isset($member->active_events) ? $member->active_events : '-';
                    break;
                case 'member_type':
                    if (!empty($member->member_type) && $member->member_type === 'team') {
                        $row[$field] = 'بازیکن تیم';
                    } else {
                        $row[$field] = 'بازیکن عادی';
                    }
                    break;
                case 'team_level':
                    $team = !empty($member->team_player) ? trim((string) $member->team_player) : '';
                    $level = !empty($member->skill_level) ? trim((string) $member->skill_level) : '';
                    if ($team === '' && $level === '') {
                        $row[$field] = '-';
                    } elseif ($team !== '' && $level !== '') {
                        $row[$field] = $team . ' — سطح: ' . $level;
                    } elseif ($team !== '') {
                        $row[$field] = $team;
                    } else {
                        $row[$field] = 'سطح: ' . $level;
                    }
                    break;
                case 'insurance_status':
                    $row[$field] = sc_users_export_plain_insurance_status($member);
                    break;
                case 'identity_verified':
                    $row[$field] = !empty($member->identity_verified) ? 'تأیید شده' : 'در انتظار بررسی';
                    break;
                case 'is_active':
                    $row[$field] = !empty($member->is_active) ? 'فعال' : 'غیرفعال';
                    break;
                case 'profile_completed':
                    $is_completed = function_exists('sc_check_profile_completed')
                        ? sc_check_profile_completed((int) $member->id, $member)
                        : !empty($member->profile_completed);
                    $row[$field] = $is_completed ? 'تکمیل شده' : 'ناقص';
                    break;
                case 'health_verified':
                case 'info_verified':
                    $row[$field] = !empty($member->{$field}) ? 'بله' : 'خیر';
                    break;
                case 'attendance_qr':
                    $row[$field] = '-';
                    if (empty($member->is_guest_export) && function_exists('sc_attendance_qr_get_export_data_uri')) {
                        $qr_data_uri = sc_attendance_qr_get_export_data_uri((int) $member->id, 320);
                        if ($qr_data_uri !== '') {
                            $row[$field] = $qr_data_uri;
                        }
                    }
                    break;
                case 'attendance_qr_short_code':
                    $row[$field] = function_exists('sc_attendance_qr_get_member_short_code')
                        ? sc_attendance_qr_get_member_short_code((int) $member->id)
                        : '-';
                    if ($row[$field] === '') {
                        $row[$field] = '-';
                    }
                    break;
                case 'attendance_qr_all_codes':
                    $row[$field] = function_exists('sc_attendance_qr_get_member_all_codes_text')
                        ? sc_attendance_qr_get_member_all_codes_text((int) $member->id)
                        : '-';
                    if ($row[$field] === '') {
                        $row[$field] = '-';
                    }
                    break;
                default:
                    if (strpos($field, 'custom_') === 0) {
                        $custom_key = substr($field, 7);
                        $extra = !empty($member->member_extra_fields) ? json_decode((string) $member->member_extra_fields, true) : [];
                        if (!is_array($extra)) {
                            $extra = [];
                        }
                        $val = $extra[$custom_key] ?? '';
                        if (is_array($val)) {
                            $val = implode('، ', array_map('strval', $val));
                        } elseif (function_exists('sc_get_player_info_custom_fields')) {
                            foreach (sc_get_player_info_custom_fields() as $cf) {
                                if (($cf['key'] ?? '') === $custom_key && ($cf['type'] ?? '') === 'checkbox') {
                                    $val = (int) $val === 1 ? 'بله' : 'خیر';
                                    break;
                                }
                            }
                        }
                        $row[$field] = $val !== '' ? $val : '-';
                    } else {
                        $row[$field] = isset($member->{$field}) && $member->{$field} !== '' ? $member->{$field} : '-';
                    }
                    break;
            }
        }
        $rows[] = $row;
    }
    return $rows;
}

function sc_users_export_filename() {
    return 'INFO_USERS_' . date_i18n('Ymd_His');
}

/**
 * خروجی PDF و Excel — مدیر کل، مدیر باشگاه، مدیر سامانه، منشی.
 */
function sc_user_can_users_export_basic($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }
    if (function_exists('sc_user_can_staff_admin_panel') && sc_user_can_staff_admin_panel($user_id)) {
        return true;
    }
    return user_can($user_id, 'administrator')
        || (function_exists('sc_user_has_club_manager_role') && sc_user_has_club_manager_role($user_id))
        || (function_exists('sc_user_is_secretary') && sc_user_is_secretary($user_id));
}

/**
 * خروجی‌های ویژه (ZIP تصاویر، PVC، ترکیب اکسل و فایل) — فقط مدیر کل.
 */
function sc_user_can_users_export_pvc($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }
    return user_can($user_id, 'administrator');
}

/**
 * @return array<string, string>
 */
function sc_users_export_get_image_field_folder_labels($labels = null) {
    if (!is_array($labels)) {
        $labels = sc_users_export_get_field_labels();
    }
    $folders = [];
    foreach (sc_users_export_get_image_field_keys() as $field_key) {
        $label = isset($labels[$field_key]) ? (string) $labels[$field_key] : $field_key;
        $folders[$field_key] = sc_users_export_sanitize_zip_folder_name($label);
    }
    return $folders;
}

function sc_users_export_sanitize_zip_folder_name($name) {
    $name = trim((string) $name);
    $name = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $name);
    $name = preg_replace('/\s+/u', ' ', $name);
    $name = trim($name, " \t\n\r\0\x0B.");
    if ($name === '') {
        return 'images';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 80);
    }
    return substr($name, 0, 80);
}

/**
 * @return string[]
 */
function sc_users_export_get_image_field_keys() {
    return ['personal_photo', 'id_card_photo', 'sport_insurance_photo', 'attendance_qr'];
}

/**
 * @return string[]
 */
function sc_users_export_get_phone_field_keys() {
    return ['player_phone', 'father_phone', 'mother_phone', 'landline_phone'];
}

/**
 * @return string[]
 */
function sc_users_export_get_pvc_image_name_field_options($fields, $labels = null) {
    if (!is_array($labels)) {
        $labels = sc_users_export_get_field_labels();
    }
    $image_fields = sc_users_export_get_image_field_keys();
    $phone_fields = sc_users_export_get_phone_field_keys();
    $options = [];
    foreach ((array) $fields as $field) {
        if (!isset($labels[$field]) || in_array($field, $image_fields, true)) {
            continue;
        }
        if (in_array($field, $phone_fields, true)) {
            continue;
        }
        $options[$field] = $labels[$field];
    }
    foreach ((array) $fields as $field) {
        if (!isset($labels[$field]) || in_array($field, $image_fields, true)) {
            continue;
        }
        if (!in_array($field, $phone_fields, true)) {
            continue;
        }
        $options[$field] = $labels[$field];
    }
    return $options;
}

function sc_users_export_normalize_phone_digits($value) {
    $digits = preg_replace('/\D+/', '', (string) $value);
    return $digits !== '' ? $digits : '';
}

function sc_users_export_sanitize_pvc_filename($name) {
    $name = trim((string) $name);
    $name = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $name);
    $name = preg_replace('/\s+/u', ' ', $name);
    $name = trim($name, " \t\n\r\0\x0B.");
    if ($name === '') {
        $name = 'user';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 180);
    }
    return substr($name, 0, 180);
}

/**
 * همان مقداری که در سلول اکسل نوشته می‌شود.
 *
 * @param array<string, mixed> $row
 */
function sc_users_export_get_excel_cell_display_value($row, $field) {
    $field = sanitize_key((string) $field);
    if ($field === '' || !isset($row[$field])) {
        return '-';
    }
    return (string) $row[$field];
}

/**
 * نام فایل از مقدار اکسل — فقط کاراکترهای غیرمجاز سیستم‌فایل حذف می‌شوند.
 */
function sc_users_export_sanitize_excel_filename($name) {
    $name = (string) $name;
    $name = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', $name);
    $name = rtrim($name, " \t\n\r\0\x0B.");
    if ($name === '') {
        return 'user';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 180);
    }
    return substr($name, 0, 180);
}

/**
 * @param array<string, mixed> $row
 */
function sc_users_export_build_excel_image_basename($row, $name_field) {
    $value = sc_users_export_get_excel_cell_display_value($row, $name_field);
    return sc_users_export_sanitize_excel_filename($value);
}

/**
 * @param array<string, mixed> $row
 * @param string[] $fields
 */
function sc_users_export_build_pvc_image_basename($row, $name_field, $fields = []) {
    $phone_fields = sc_users_export_get_phone_field_keys();
    $name_field = sanitize_key((string) $name_field);

    if ($name_field === 'full_name') {
        $full_name = trim((string) ($row['full_name'] ?? ''));
        if ($full_name === '' || $full_name === '-') {
            $full_name = '';
        }
        $phone = '';
        if (in_array('player_phone', $fields, true)) {
            $phone = sc_users_export_normalize_phone_digits($row['player_phone'] ?? '');
        }
        if ($full_name !== '' && $phone !== '') {
            return sc_users_export_sanitize_pvc_filename($full_name . ' ' . $phone);
        }
        if ($full_name !== '') {
            return sc_users_export_sanitize_pvc_filename($full_name);
        }
        if ($phone !== '') {
            return sc_users_export_sanitize_pvc_filename($phone);
        }
        return 'user';
    }

    if (in_array($name_field, $phone_fields, true)) {
        $digits = sc_users_export_normalize_phone_digits($row[$name_field] ?? '');
        return $digits !== '' ? sc_users_export_sanitize_pvc_filename($digits) : 'user';
    }

    $value = isset($row[$name_field]) ? trim((string) $row[$name_field]) : '';
    if ($value === '' || $value === '-') {
        if (!empty($row['member_id']) && $row['member_id'] !== '-') {
            return sc_users_export_sanitize_pvc_filename((string) $row['member_id']);
        }
        return 'user';
    }
    return sc_users_export_sanitize_pvc_filename($value);
}

/**
 * @return array{content: string, extension: string}|null
 */
function sc_users_export_load_image_binary($source) {
    $source = trim((string) $source);
    if ($source === '' || $source === '-') {
        return null;
    }

    if (strpos($source, 'data:image') === 0) {
        if (preg_match('#^data:image/(\w+);base64,(.+)$#i', $source, $matches)) {
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            $content = base64_decode($matches[2], true);
            if ($content !== false && $content !== '') {
                return ['content' => $content, 'extension' => $ext !== '' ? $ext : 'png'];
            }
        }
        return null;
    }

    if (strpos($source, 'http://') === 0 || strpos($source, 'https://') === 0) {
        $upload = wp_get_upload_dir();
        if (!empty($upload['baseurl']) && !empty($upload['basedir']) && strpos($source, $upload['baseurl']) === 0) {
            $local_path = $upload['basedir'] . substr($source, strlen($upload['baseurl']));
            if (is_readable($local_path)) {
                $content = file_get_contents($local_path);
                if ($content !== false && $content !== '') {
                    $ext = strtolower(pathinfo($local_path, PATHINFO_EXTENSION));
                    return ['content' => $content, 'extension' => $ext !== '' ? $ext : 'jpg'];
                }
            }
        }
        $response = wp_remote_get($source, ['timeout' => 25]);
        if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
            $content = wp_remote_retrieve_body($response);
            if ($content !== '') {
                $ext = strtolower(pathinfo(parse_url($source, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
                return ['content' => $content, 'extension' => $ext !== '' ? $ext : 'jpg'];
            }
        }
    }

    if (is_readable($source)) {
        $content = file_get_contents($source);
        if ($content !== false && $content !== '') {
            $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            return ['content' => $content, 'extension' => $ext !== '' ? $ext : 'jpg'];
        }
    }

    return null;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @param string[] $fields
 * @param array<string, string>|null $labels
 * @param string|null $save_path
 */
function sc_users_export_to_excel($rows, $fields, $labels = null, $save_path = null) {
    if (!function_exists('sc_check_phpspreadsheet')) {
        wp_die('کتابخانه Excel در دسترس نیست.');
    }
    sc_check_phpspreadsheet();

    if (!is_array($labels)) {
        $labels = sc_users_export_get_field_labels();
    }
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('خروجی کاربران');
    $sheet->setRightToLeft(true);

    $sheet->setCellValueByColumnAndRow(1, 1, 'ردیف');
    $col = 2;
    foreach ($fields as $field) {
        $sheet->setCellValueByColumnAndRow($col++, 1, isset($labels[$field]) ? $labels[$field] : $field);
    }

    $headerStyle = function_exists('sc_get_excel_header_style') ? sc_get_excel_header_style() : [];
    $headerEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
    if (!empty($headerStyle)) {
        $sheet->getStyle("A1:{$headerEnd}1")->applyFromArray($headerStyle);
    }

    $dataStyle = function_exists('sc_get_excel_data_style') ? sc_get_excel_data_style() : [];
    $alternateStyle = function_exists('sc_get_excel_alternate_row_style') ? sc_get_excel_alternate_row_style() : [];

    $rowIndex = 2;
    $counter = 1;
    foreach ($rows as $row) {
        $sheet->setCellValueByColumnAndRow(1, $rowIndex, $counter++);
        $col = 2;
        foreach ($fields as $field) {
            $sheet->setCellValueByColumnAndRow($col++, $rowIndex, isset($row[$field]) ? $row[$field] : '-');
        }
        $lineEnd = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
        if (!empty($dataStyle)) {
            $style = ($rowIndex % 2 === 0 && !empty($alternateStyle)) ? array_merge($dataStyle, $alternateStyle) : $dataStyle;
            $sheet->getStyle("A{$rowIndex}:{$lineEnd}{$rowIndex}")->applyFromArray($style);
        }
        $rowIndex++;
    }

    if (function_exists('sc_auto_size_columns')) {
        sc_auto_size_columns($sheet, count($fields) + 1);
    }

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    if ($save_path) {
        $writer->save($save_path);
        return $save_path;
    }

    sc_users_export_discard_output_buffers();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . sc_users_export_filename() . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @param string[] $fields
 * @param array<string, string>|null $labels
 * @param array<string, mixed> $options
 */
function sc_users_export_to_pvc_zip($rows, $fields, $labels = null, $options = []) {
    if (!function_exists('sc_check_phpspreadsheet')) {
        wp_die('کتابخانه Excel در دسترس نیست.');
    }
    if (!class_exists('ZipArchive')) {
        wp_die('افزونه ZipArchive در PHP فعال نیست.');
    }

    $image_fields = sc_users_export_get_image_field_keys();
    $selected_image_fields = array_values(array_intersect($fields, $image_fields));
    if (empty($selected_image_fields)) {
        wp_die('برای خروجی کارت PVC حداقل یک فیلد تصویری انتخاب کنید.');
    }

    $name_field = isset($options['image_name_field']) ? sanitize_key((string) $options['image_name_field']) : 'full_name';
    $excel_fields = array_values(array_diff($fields, $image_fields));
    if (empty($excel_fields)) {
        wp_die('برای خروجی کارت PVC حداقل یک فیلد متنی علاوه بر تصویر انتخاب کنید.');
    }

    $name_options = sc_users_export_get_pvc_image_name_field_options($fields, $labels);
    if (!isset($name_options[$name_field])) {
        $name_field = isset($name_options['full_name']) ? 'full_name' : array_key_first($name_options);
    }

    // نام فایل فقط از فیلد انتخاب‌شده (مثلاً کد ملی) ساخته می‌شود؛ بدون پسوند نوع تصویر.
    $suffix_map = [
        'personal_photo' => '',
        'id_card_photo' => '',
        'sport_insurance_photo' => '',
        'attendance_qr' => '',
    ];

    $temp_dir = trailingslashit(get_temp_dir()) . 'sc_pvc_' . wp_generate_password(12, false, false);
    if (!wp_mkdir_p($temp_dir)) {
        wp_die('امکان ساخت پوشه موقت وجود ندارد.');
    }
    $images_dir = trailingslashit($temp_dir) . 'images';
    if (!wp_mkdir_p($images_dir)) {
        sc_users_export_cleanup_temp_dir($temp_dir);
        wp_die('امکان ساخت پوشه تصاویر وجود ندارد.');
    }

    $excel_filename = sc_users_export_filename() . '.xlsx';
    $excel_path = trailingslashit($temp_dir) . $excel_filename;
    sc_users_export_to_excel($rows, $excel_fields, $labels, $excel_path);

    $used_names = [];
    foreach ($rows as $row) {
        $basename = sc_users_export_build_pvc_image_basename($row, (string) $name_field, $fields);
        foreach ($selected_image_fields as $image_field) {
            $source = $row[$image_field] ?? '';
            $binary = sc_users_export_load_image_binary($source);
            if (!$binary) {
                continue;
            }
            $suffix = $suffix_map[$image_field] ?? ('_' . $image_field);
            $candidate = $basename . $suffix;
            $unique = $candidate;
            $counter = 2;
            while (isset($used_names[$unique])) {
                $unique = $candidate . '_' . $counter;
                $counter++;
            }
            $used_names[$unique] = true;
            $ext = $binary['extension'] !== '' ? $binary['extension'] : 'jpg';
            $file_path = $images_dir . '/' . $unique . '.' . $ext;
            file_put_contents($file_path, $binary['content']);
        }
    }

    $zip_filename = sc_users_export_filename() . '_PVC.zip';
    $zip_path = trailingslashit($temp_dir) . $zip_filename;
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        sc_users_export_cleanup_temp_dir($temp_dir);
        wp_die('امکان ساخت فایل ZIP وجود ندارد.');
    }
    $zip->addFile($excel_path, $excel_filename);
    $image_files = glob($images_dir . '/*');
    if (is_array($image_files)) {
        foreach ($image_files as $image_file) {
            if (!is_file($image_file)) {
                continue;
            }
            $zip->addFile($image_file, 'images/' . basename($image_file));
        }
    }
    $zip->close();

    sc_users_export_discard_output_buffers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment;filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    header('Cache-Control: max-age=0');
    readfile($zip_path);
    sc_users_export_cleanup_temp_dir($temp_dir);
    exit;
}

/**
 * ترکیب اکسل و فایل — اکسل فیلدهای متنی + ZIP با پوشه جدا برای هر نوع تصویر.
 *
 * @param array<int, array<string, mixed>> $rows
 * @param string[] $fields
 * @param array<string, string>|null $labels
 * @param array<string, mixed> $options
 */
function sc_users_export_to_excel_images_zip($rows, $fields, $labels = null, $options = []) {
    if (!function_exists('sc_check_phpspreadsheet')) {
        wp_die('کتابخانه Excel در دسترس نیست.');
    }
    if (!class_exists('ZipArchive')) {
        wp_die('افزونه ZipArchive در PHP فعال نیست.');
    }

    $image_fields = sc_users_export_get_image_field_keys();
    $selected_image_fields = array_values(array_intersect($fields, $image_fields));
    if (empty($selected_image_fields)) {
        wp_die('برای خروجی ترکیب اکسل و فایل حداقل یک فیلد تصویری انتخاب کنید.');
    }

    $name_field = isset($options['image_name_field']) ? sanitize_key((string) $options['image_name_field']) : 'full_name';
    $excel_fields = array_values(array_diff($fields, $image_fields));
    if (empty($excel_fields)) {
        wp_die('برای خروجی ترکیب اکسل و فایل حداقل یک فیلد متنی علاوه بر تصویر انتخاب کنید.');
    }

    if (!is_array($labels)) {
        $labels = sc_users_export_get_field_labels();
    }

    $name_options = sc_users_export_get_pvc_image_name_field_options($fields, $labels);
    if (!isset($name_options[$name_field])) {
        $name_field = isset($name_options['full_name']) ? 'full_name' : array_key_first($name_options);
    }

    $folder_labels = sc_users_export_get_image_field_folder_labels($labels);

    $temp_dir = trailingslashit(get_temp_dir()) . 'sc_excel_img_' . wp_generate_password(12, false, false);
    if (!wp_mkdir_p($temp_dir)) {
        wp_die('امکان ساخت پوشه موقت وجود ندارد.');
    }

    $excel_filename = sc_users_export_filename() . '.xlsx';
    $excel_path = trailingslashit($temp_dir) . $excel_filename;
    sc_users_export_to_excel($rows, $excel_fields, $labels, $excel_path);

    $used_names = [];
    foreach ($selected_image_fields as $image_field) {
        $used_names[$image_field] = [];
    }

    foreach ($rows as $row) {
        $basename = sc_users_export_build_excel_image_basename($row, (string) $name_field);
        foreach ($selected_image_fields as $image_field) {
            $source = $row[$image_field] ?? '';
            $binary = sc_users_export_load_image_binary($source);
            if (!$binary) {
                continue;
            }
            $folder_name = $folder_labels[$image_field] ?? sc_users_export_sanitize_zip_folder_name($image_field);
            $folder_path = trailingslashit($temp_dir) . $folder_name;
            if (!wp_mkdir_p($folder_path)) {
                continue;
            }
            $candidate = $basename;
            $unique = $candidate;
            $counter = 2;
            while (isset($used_names[$image_field][$unique])) {
                $unique = $candidate . '_' . $counter;
                $counter++;
            }
            $used_names[$image_field][$unique] = true;
            $ext = $binary['extension'] !== '' ? $binary['extension'] : 'jpg';
            $file_path = $folder_path . '/' . $unique . '.' . $ext;
            file_put_contents($file_path, $binary['content']);
        }
    }

    $zip_filename = sc_users_export_filename() . '_excel-files.zip';
    $zip_path = trailingslashit($temp_dir) . $zip_filename;
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        sc_users_export_cleanup_temp_dir($temp_dir);
        wp_die('امکان ساخت فایل ZIP وجود ندارد.');
    }

    $zip->addFile($excel_path, $excel_filename);

    foreach ($selected_image_fields as $image_field) {
        $folder_name = $folder_labels[$image_field] ?? sc_users_export_sanitize_zip_folder_name($image_field);
        $folder_path = trailingslashit($temp_dir) . $folder_name;
        if (!is_dir($folder_path)) {
            continue;
        }
        $image_files = glob($folder_path . '/*');
        if (!is_array($image_files)) {
            continue;
        }
        foreach ($image_files as $image_file) {
            if (!is_file($image_file)) {
                continue;
            }
            $zip->addFile($image_file, $folder_name . '/' . basename($image_file));
        }
    }

    $zip->close();

    sc_users_export_discard_output_buffers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment;filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    header('Cache-Control: max-age=0');
    readfile($zip_path);
    sc_users_export_cleanup_temp_dir($temp_dir);
    exit;
}

function sc_users_export_cleanup_temp_dir($dir) {
    $dir = trailingslashit((string) $dir);
    if ($dir === '' || !is_dir($dir)) {
        return;
    }
    $items = glob($dir . '*');
    if (is_array($items)) {
        foreach ($items as $item) {
            if (is_dir($item)) {
                sc_users_export_cleanup_temp_dir($item);
            } elseif (is_file($item)) {
                @unlink($item);
            }
        }
    }
    @rmdir(rtrim($dir, '/\\'));
}

/**
 * پاک‌سازی بافر خروجی قبل از ارسال فایل دانلود (جلوگیری از آلوده شدن Excel/PDF با Warning).
 */
function sc_users_export_discard_output_buffers() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

function sc_users_export_render_pdf_page($rows, $fields, $layout = [], $title = '', $template = null, $labels = null, $options = []) {
    sc_users_export_discard_output_buffers();
    if (!is_array($labels)) {
        $labels = sc_users_export_get_field_labels();
    }
    $page_size = isset($layout['page_size']) && in_array($layout['page_size'], ['A4', 'A5'], true) ? $layout['page_size'] : 'A4';
    $cards_per_page = isset($layout['cards_per_page']) ? max(1, min(6, (int) $layout['cards_per_page'])) : 2;
    $export_title = $title !== '' ? $title : 'خروجی اطلاعات کاربران';
    $export_mode = isset($options['export_mode']) && $options['export_mode'] === 'cards_zip' ? 'cards_zip' : 'pdf';

    $print_data = [
        'title' => $export_title,
        'page_size' => $page_size,
        'cards_per_page' => $cards_per_page,
        'columns_count' => (int) (($template['columns_count'] ?? $cards_per_page) ?: 2),
        'rows' => $rows,
        'fields' => $fields,
        'labels' => $labels,
        'template' => is_array($template) ? $template : null,
        'export_mode' => $export_mode,
        'zip_filename' => $export_mode === 'cards_zip' ? sc_users_export_filename() . '_cards' : '',
    ];

    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($export_title); ?></title>
        <link rel="stylesheet" href="<?php echo esc_url(SC_ASSETS_URL . 'css/users-export-print.css'); ?>">
        <?php if (is_array($template) && !empty($template['custom_css'])) : ?>
            <style id="sc-users-export-custom-css"><?php echo esc_html($template['custom_css']); ?></style>
        <?php endif; ?>
        <?php if ($export_mode === 'cards_zip') : ?>
            <script src="<?php echo esc_url(SC_ASSETS_URL . 'js/vendor/html2canvas.min.js'); ?>"></script>
            <script src="<?php echo esc_url(SC_ASSETS_URL . 'js/vendor/jszip.min.js'); ?>"></script>
        <?php endif; ?>
    </head>
    <body class="sc-users-export-print-page<?php echo $export_mode === 'cards_zip' ? ' sc-users-export-cards-zip-page' : ''; ?>">
        <div id="sc-users-export-print-root" data-print="<?php echo esc_attr(wp_json_encode($print_data, JSON_UNESCAPED_UNICODE)); ?>"></div>
        <script src="<?php echo esc_url(SC_ASSETS_URL . 'js/users-export-print.js'); ?>"></script>
    </body>
    </html>
    <?php
    exit;
}

add_action('admin_post_sc_users_info_export', 'sc_users_info_export_handler');
add_action('wp_ajax_sc_users_export_preview_members', 'sc_users_export_preview_members_ajax');
add_action('wp_ajax_sc_users_export_get_event_fields', 'sc_users_export_get_event_fields_ajax');
add_action('wp_ajax_sc_users_export_template_search_member', 'sc_users_export_template_search_member_ajax');
add_action('wp_ajax_sc_users_export_template_preview_data', 'sc_users_export_template_preview_data_ajax');
add_action('wp_ajax_sc_users_export_save_templates', 'sc_users_export_save_templates_ajax');

function sc_users_export_save_templates_ajax() {
    check_ajax_referer('sc_save_export_templates_nonce', 'nonce');
    if (!function_exists('sc_user_can_staff_admin_panel') || !sc_user_can_staff_admin_panel()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $posted_templates = [];
    if (!empty($_POST['templates_json'])) {
        $decoded = json_decode(wp_unslash((string) $_POST['templates_json']), true);
        if (is_array($decoded)) {
            $posted_templates = $decoded;
        }
    } elseif (isset($_POST['form'])) {
        $parsed = [];
        parse_str(wp_unslash((string) $_POST['form']), $parsed);
        if (isset($parsed['templates']) && is_array($parsed['templates'])) {
            $posted_templates = $parsed['templates'];
        }
    }

    if (empty($posted_templates)) {
        wp_send_json_error(['message' => 'داده‌ای برای ذخیره دریافت نشد.']);
    }

    $new_templates = [];
    foreach ($posted_templates as $template) {
        if (!is_array($template)) {
            continue;
        }
        $normalized = sc_users_export_normalize_template($template, isset($template['key']) ? $template['key'] : '');
        $new_templates[$normalized['key']] = $normalized;
    }

    if (empty($new_templates)) {
        wp_send_json_error(['message' => 'هیچ قالب معتبری پردازش نشد.']);
    }

    sc_users_export_save_templates($new_templates);
    wp_send_json_success([
        'message' => 'تنظیمات قالب با موفقیت ذخیره شد.',
        'templates' => sc_users_export_get_saved_templates(),
    ]);
}

function sc_users_export_get_event_fields_ajax() {
    check_ajax_referer('sc_users_export_get_event_fields', 'nonce');
    if (!function_exists('sc_user_can_staff_admin_panel') || !sc_user_can_staff_admin_panel()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $event_ids = isset($_POST['event_ids']) ? array_filter(array_map('absint', (array) $_POST['event_ids'])) : [];
    $event_labels = sc_users_export_get_event_field_labels($event_ids);
    $html = '';
    if (!empty($event_ids)) {
        $html .= '<div class="sc-event-fields-section"><h3 class="sc-event-fields-heading">فیلدهای اختصاصی رویداد</h3>';
        $html .= '<label class="sc-inline-check sc-event-field-check">';
        $html .= '<input type="checkbox" name="fields[]" value="registration_type"> نوع ثبت‌نام';
        $html .= '</label>';
        foreach ($event_labels as $key => $label) {
            $html .= '<label class="sc-inline-check sc-event-field-check">';
            $html .= '<input type="checkbox" name="fields[]" value="' . esc_attr($key) . '"> ';
            $html .= esc_html($label);
            $html .= '</label>';
        }
        $html .= '</div>';
    }

    $labels_response = array_merge(['registration_type' => 'نوع ثبت‌نام'], $event_labels);

    wp_send_json_success([
        'html' => $html,
        'labels' => $labels_response,
    ]);
}

function sc_users_export_preview_members_ajax() {
    check_ajax_referer('sc_users_export_preview_members', 'nonce');
    if (!function_exists('sc_user_can_staff_admin_panel') || !sc_user_can_staff_admin_panel()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $target_type = isset($_POST['target_type']) ? sanitize_text_field(wp_unslash($_POST['target_type'])) : 'all';
    if (!in_array($target_type, sc_users_export_get_allowed_target_types(), true)) {
        $target_type = 'all';
    }

    $config = [
        'member_ids' => isset($_POST['member_ids']) ? array_map('absint', (array) $_POST['member_ids']) : [],
        'course_ids' => function_exists('sc_audience_normalize_course_ids_from_request')
            ? sc_audience_normalize_course_ids_from_request($_POST['course_ids'] ?? [])
            : array_filter(array_map('absint', (array) ($_POST['course_ids'] ?? []))),
        'event_ids' => isset($_POST['event_ids']) ? array_map('absint', (array) $_POST['event_ids']) : [],
        'team_names' => isset($_POST['team_names']) ? array_map('sanitize_text_field', (array) $_POST['team_names']) : [],
        'level_names' => isset($_POST['level_names']) ? array_map('sanitize_text_field', (array) $_POST['level_names']) : [],
        'member_type' => isset($_POST['member_type']) ? sanitize_text_field(wp_unslash($_POST['member_type'])) : 'all',
    ];

    $subjects = sc_users_export_get_subjects($target_type, $config);
    $total = count($subjects);
    $preview_rows = array_slice($subjects, 0, 200);
    $is_event = ($target_type === 'event');

    ob_start();
    if (empty($subjects)) {
        echo '<p class="description">هیچ کاربری با این فیلترها پیدا نشد.</p>';
    } else {
        $count_label = $is_event ? 'تعداد ثبت‌نام‌ها' : 'تعداد کاربران فیلتر شده';
        echo '<div class="sc-bulk-preview-meta">' . esc_html($count_label) . ': <strong>' . esc_html((string) $total) . '</strong></div>';
        echo '<table class="wp-list-table widefat striped sc-bulk-preview-table">';
        if ($is_event) {
            echo '<thead><tr><th style="width:64px;"><label><input type="checkbox" id="sc-users-preview-select-all" checked> انتخاب</label></th><th>نام</th><th>کد ملی</th><th>شماره همراه</th><th>نوع</th></tr></thead><tbody>';
        } else {
            echo '<thead><tr><th style="width:64px;"><label><input type="checkbox" id="sc-users-preview-select-all" checked> انتخاب</label></th><th>نام</th><th>کد ملی</th><th>نوع</th><th>تیم</th><th>سطح</th><th>وضعیت</th></tr></thead><tbody>';
        }
        foreach ($preview_rows as $member) {
            $full_name = trim((string) $member->first_name . ' ' . (string) $member->last_name);
            $is_guest = !empty($member->is_guest_export);
            if ($is_guest) {
                $row_label = $full_name !== '' ? $full_name : ('مهمان #' . (int) ($member->registration_id ?? 0));
                $type_label = 'مهمان';
            } else {
                $row_label = $full_name !== '' ? $full_name : ('کاربر #' . (int) $member->id);
                $type_label = ((string) ($member->member_type ?? '') === 'team') ? 'بازیکن تیم' : 'بازیکن عادی';
            }
            $member_id_attr = $is_guest ? 0 : (int) $member->id;
            echo '<tr>';
            echo '<td><input type="checkbox" class="sc-users-preview-member-check" data-member-id="' . esc_attr((string) $member_id_attr) . '" data-is-guest="' . ($is_guest ? '1' : '0') . '" data-member-label="' . esc_attr($row_label) . '" checked></td>';
            echo '<td>' . esc_html($row_label) . '</td>';
            echo '<td>' . esc_html((string) ($member->national_id ?: '-')) . '</td>';
            if ($is_event) {
                echo '<td>' . esc_html((string) ($member->player_phone ?: '-')) . '</td>';
                echo '<td>' . esc_html($type_label) . '</td>';
            } else {
                $status_label = !empty($member->is_active) ? 'فعال' : 'غیرفعال';
                echo '<td>' . esc_html($type_label) . '</td>';
                echo '<td>' . esc_html((string) ($member->team_player ?: '-')) . '</td>';
                echo '<td>' . esc_html((string) ($member->skill_level ?: '-')) . '</td>';
                echo '<td>' . esc_html($status_label) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
        if ($total > 200) {
            echo '<p class="description">فقط 200 مورد اول نمایش داده شد.</p>';
        }
    }
    $html = ob_get_clean();

    wp_send_json_success([
        'total' => $total,
        'html' => $html,
    ]);
}

function sc_users_export_template_search_member_ajax() {
    check_ajax_referer('sc_users_export_template_preview', 'nonce');
    if (!function_exists('sc_user_can_staff_admin_panel') || !sc_user_can_staff_admin_panel()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $q = isset($_REQUEST['q']) ? sanitize_text_field(wp_unslash($_REQUEST['q'])) : '';
    if ($q === '') {
        wp_send_json_success(['items' => []]);
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $digits = preg_replace('/\D/', '', $q);
    $like = '%' . $wpdb->esc_like($q) . '%';
    $where = ['(m.deleted_at IS NULL OR m.deleted_at = "0000-00-00 00:00:00")'];
    $where[] = '(m.first_name LIKE %s OR m.last_name LIKE %s OR CONCAT(m.first_name, " ", m.last_name) LIKE %s OR m.player_phone LIKE %s OR m.national_id LIKE %s)';
    $args = [$like, $like, $like, $like, $like];
    if ($digits !== '' && $digits !== $q) {
        $digits_like = '%' . $wpdb->esc_like($digits) . '%';
        $where[] = '(REPLACE(REPLACE(m.player_phone, "-", ""), " ", "") LIKE %s OR m.national_id LIKE %s)';
        $args[] = $digits_like;
        $args[] = $digits_like;
    }
    if (function_exists('sc_secretary_merge_member_where_parts')) {
        sc_secretary_merge_member_where_parts($where, $args, 'm');
    }
    $where_sql = implode(' AND ', $where);
    $sql = "SELECT m.id, m.first_name, m.last_name, m.player_phone, m.national_id
            FROM {$members_table} m
            WHERE {$where_sql}
            ORDER BY m.last_name ASC, m.first_name ASC
            LIMIT 20";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
    $items = [];
    foreach ((array) $rows as $row) {
        $name = trim((string) $row->first_name . ' ' . (string) $row->last_name);
        $label = $name !== '' ? $name : ('کاربر #' . (int) $row->id);
        if (!empty($row->player_phone)) {
            $label .= ' — ' . $row->player_phone;
        }
        if (!empty($row->national_id)) {
            $label .= ' (کد ملی: ' . $row->national_id . ')';
        }
        $items[] = [
            'id' => (int) $row->id,
            'label' => $label,
        ];
    }
    wp_send_json_success(['items' => $items]);
}

function sc_users_export_template_preview_data_ajax() {
    check_ajax_referer('sc_users_export_template_preview', 'nonce');
    if (!function_exists('sc_user_can_staff_admin_panel') || !sc_user_can_staff_admin_panel()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    if ($member_id <= 0) {
        wp_send_json_error(['message' => 'کاربر انتخاب نشده است.']);
    }

    $posted_fields = isset($_POST['fields']) ? array_map('sanitize_text_field', (array) $_POST['fields']) : [];
    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $event_ids = $event_id > 0 ? [$event_id] : [];
    $field_labels = sc_users_export_merge_field_labels($event_ids);
    $fields = array_values(array_intersect($posted_fields, array_keys($field_labels)));
    if (empty($fields)) {
        $fields = ['full_name', 'player_phone'];
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$members_table} WHERE id = %d", $member_id));
    if (!$member) {
        wp_send_json_error(['message' => 'کاربر یافت نشد.']);
    }

    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';
    $events_table = $wpdb->prefix . 'sc_events';

    $courses = $wpdb->get_col($wpdb->prepare(
        "SELECT c.title
         FROM $member_courses_table mc
         INNER JOIN $courses_table c ON c.id = mc.course_id
         WHERE mc.member_id = %d
           AND mc.status = 'active'
           AND (mc.course_status_flags IS NULL OR TRIM(mc.course_status_flags) = '')
           AND c.deleted_at IS NULL
         ORDER BY c.title ASC",
        $member_id
    ));
    $member->active_courses = !empty($courses) ? implode('، ', $courses) : '-';

    $events = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT e.name
         FROM $event_registrations_table er
         INNER JOIN $events_table e ON e.id = er.event_id
         WHERE er.member_id = %d
           AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
         ORDER BY e.name ASC",
        $member_id
    ));
    $member->active_events = !empty($events) ? implode('، ', $events) : '-';

    $rows = sc_users_export_prepare_rows([$member], $fields);
    $row = !empty($rows[0]) ? $rows[0] : [];

    wp_send_json_success([
        'row' => $row,
        'fields' => $fields,
        'labels' => $field_labels,
        'member_label' => trim((string) $member->first_name . ' ' . (string) $member->last_name),
    ]);
}

function sc_users_info_export_handler() {
    if (!function_exists('sc_user_can_staff_admin_panel') || !sc_user_can_staff_admin_panel()) {
        wp_die('دسترسی غیرمجاز.');
    }
    check_admin_referer('sc_users_info_export_action', 'sc_users_info_export_nonce');
    ob_start();

    $target_type = isset($_POST['target_type']) ? sanitize_text_field(wp_unslash($_POST['target_type'])) : 'all';
    if (!in_array($target_type, sc_users_export_get_allowed_target_types(), true)) {
        $target_type = 'all';
    }

    $config = [
        'member_ids' => isset($_POST['member_ids']) ? array_map('absint', (array) $_POST['member_ids']) : [],
        'excluded_member_ids' => isset($_POST['excluded_member_ids']) ? array_map('absint', (array) $_POST['excluded_member_ids']) : [],
        'included_member_ids' => isset($_POST['included_member_ids']) ? array_map('absint', (array) $_POST['included_member_ids']) : [],
        'course_ids' => function_exists('sc_audience_normalize_course_ids_from_request')
            ? sc_audience_normalize_course_ids_from_request($_POST['course_ids'] ?? [])
            : array_filter(array_map('absint', (array) ($_POST['course_ids'] ?? []))),
        'event_ids' => isset($_POST['event_ids']) ? array_map('absint', (array) $_POST['event_ids']) : [],
        'team_names' => isset($_POST['team_names']) ? array_map('sanitize_text_field', (array) $_POST['team_names']) : [],
        'level_names' => isset($_POST['level_names']) ? array_map('sanitize_text_field', (array) $_POST['level_names']) : [],
        'member_type' => isset($_POST['member_type']) ? sanitize_text_field(wp_unslash($_POST['member_type'])) : 'all',
    ];

    $event_ids_for_labels = ($target_type === 'event') ? $config['event_ids'] : [];
    $template_key = isset($_POST['template_key']) ? sanitize_text_field(wp_unslash($_POST['template_key'])) : '';
    $templates = sc_users_export_get_saved_templates();
    $template = ($template_key && isset($templates[$template_key])) ? $templates[$template_key] : null;
    if ($template) {
        $template = sc_users_export_normalize_template($template, $template_key);
        if (!empty($template['is_event_template']) && !empty($template['event_id'])) {
            $target_type = 'event';
            $config['event_ids'] = [(int) $template['event_id']];
            $event_ids_for_labels = [(int) $template['event_id']];
        }
    }

    $field_labels = sc_users_export_merge_field_labels($event_ids_for_labels);
    $selected_fields = isset($_POST['fields']) ? array_map('sanitize_text_field', (array) $_POST['fields']) : [];
    $fields = array_values(array_intersect($selected_fields, array_keys($field_labels)));
    if (empty($fields)) {
        wp_die('حداقل یک فیلد برای خروجی انتخاب کنید.');
    }

    if ($template && !empty($template['fields']) && empty($_POST['override_template_fields'])) {
        $fields = array_values(array_intersect((array) $template['fields'], array_keys($field_labels)));
    }

    $subjects = sc_users_export_get_subjects($target_type, $config);
    if (function_exists('sc_audience_apply_included_members_config')) {
        $subjects = sc_audience_apply_included_members_config($subjects, $config);
    }
    if (empty($subjects)) {
        wp_die('هیچ کاربری با این فیلترها پیدا نشد.');
    }

    $rows = sc_users_export_prepare_rows($subjects, $fields);
    sc_users_export_discard_output_buffers();

    $pvc_export = !empty($_POST['pvc_export']);
    if ($pvc_export) {
        if (!sc_user_can_users_export_pvc()) {
            wp_die('خروجی کارت PVC فقط برای مدیر کل فعال است.');
        }
        $pvc_name_field = isset($_POST['pvc_image_name_field']) ? sanitize_key(wp_unslash($_POST['pvc_image_name_field'])) : 'full_name';
        sc_users_export_to_pvc_zip($rows, $fields, $field_labels, [
            'image_name_field' => $pvc_name_field,
        ]);
        return;
    }

    $excel_images_export = !empty($_POST['excel_images_export']);
    $format = isset($_POST['export_format']) ? sanitize_text_field(wp_unslash($_POST['export_format'])) : 'pdf';
    if ($format === 'excel_images' || $excel_images_export) {
        if (!sc_user_can_users_export_pvc()) {
            wp_die('خروجی ترکیب اکسل و فایل فقط برای مدیر کل فعال است.');
        }
        $excel_images_name_field = isset($_POST['excel_images_name_field']) ? sanitize_key(wp_unslash($_POST['excel_images_name_field'])) : 'full_name';
        sc_users_export_to_excel_images_zip($rows, $fields, $field_labels, [
            'image_name_field' => $excel_images_name_field,
        ]);
        return;
    }

    if (!in_array($format, ['pdf', 'excel', 'cards_zip'], true)) {
        $format = 'pdf';
    }
    if ($format === 'excel' && !sc_user_can_users_export_basic()) {
        wp_die('خروجی Excel فقط برای مدیر کل، مدیر باشگاه، مدیر سامانه و منشی فعال است.');
    }
    if ($format === 'cards_zip' && !sc_user_can_users_export_pvc()) {
        wp_die('خروجی ZIP تصاویر فقط برای مدیر کل فعال است.');
    }
    $layout = [
        'page_size' => isset($_POST['page_size']) ? sanitize_text_field(wp_unslash($_POST['page_size'])) : ($template['page_size'] ?? 'A4'),
        'cards_per_page' => isset($_POST['cards_per_page']) ? absint($_POST['cards_per_page']) : (int) ($template['cards_per_page'] ?? 2),
    ];

    $image_fields = ['personal_photo', 'id_card_photo', 'sport_insurance_photo', 'attendance_qr'];
    if (!empty(array_intersect($fields, $image_fields)) && $format !== 'cards_zip') {
        $format = 'pdf';
    }

    if ($format === 'excel') {
        sc_users_export_to_excel($rows, $fields, $field_labels);
        return;
    }

    $title = $template && !empty($template['title']) ? $template['title'] : 'خروجی اطلاعات کاربران';
    if ($format === 'cards_zip') {
        sc_users_export_render_pdf_page($rows, $fields, $layout, $title, $template, $field_labels, ['export_mode' => 'cards_zip']);
        return;
    }
    sc_users_export_render_pdf_page($rows, $fields, $layout, $title, $template, $field_labels);
}
