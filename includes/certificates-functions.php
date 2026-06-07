<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_certificates_get_placeholders() {
    return [
        'name' => 'نام و نام خانوادگی',
        'national_id' => 'کد ملی',
        'phone' => 'شماره تماس',
        'team' => 'تیم',
        'level' => 'سطح',
        'province' => 'استان',
        'city' => 'شهر',
        'birth_date' => 'تاریخ تولد',
    ];
}

function sc_certificates_get_default_templates() {
    return [
        'default_certificate' => [
            'key' => 'default_certificate',
            'title' => 'گواهینامه پیش فرض',
            'certificate_title' => 'گواهینامه پیش فرض',
            'description' => '',
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'cards_per_page' => 1,
            'background_image' => '',
            'message_text' => 'بازیکن عزیز %name%، یک گواهینامه برای شما صادر شد.',
            'signature_one_text' => '',
            'signature_one_image' => '',
            'signature_two_text' => '',
            'signature_two_image' => '',
            'padding_top' => 200,
            'padding_right' => 56,
            'padding_bottom' => 28,
            'padding_left' => 56,
            'signature_bottom_offset' => 80,
            'background_opacity' => 1,
            'content_font_family' => 'IRANYekanXFaNum',
            'content_font_size' => 17,
            'content_line_height' => 1.85,
            'content_paragraph_spacing' => 0.6,
            'content_text_align' => 'center',
            'tracking_code_prefix' => 'SC-',
            'physical_copy_price' => 0,
        ],
    ];
}

function sc_certificates_get_saved_templates() {
    $saved = get_option('sc_certificate_templates', null);
    if ($saved === null || !is_array($saved) || empty($saved)) {
        return sc_certificates_get_default_templates();
    }
    return $saved;
}

function sc_certificates_save_templates($templates) {
    if (!is_array($templates)) {
        return;
    }
    update_option('sc_certificate_templates', $templates, false);
}

function sc_certificates_normalize_template($template, $fallback_key = '') {
    $key = isset($template['key']) ? sanitize_key($template['key']) : sanitize_key($fallback_key);
    if ($key === '') {
        $key = 'certificate_' . wp_generate_password(6, false, false);
    }
    return [
        'key' => $key,
        'title' => isset($template['title']) ? sanitize_text_field(wp_unslash($template['title'])) : 'قالب جدید',
        'certificate_title' => isset($template['certificate_title']) ? sanitize_text_field(wp_unslash($template['certificate_title'])) : (isset($template['title']) ? sanitize_text_field(wp_unslash($template['title'])) : 'گواهینامه'),
        'description' => isset($template['description']) ? sanitize_textarea_field(wp_unslash($template['description'])) : '',
        'page_size' => (isset($template['page_size']) && in_array($template['page_size'], ['A4', 'A5'], true)) ? $template['page_size'] : 'A4',
        'orientation' => (isset($template['orientation']) && in_array($template['orientation'], ['portrait', 'landscape'], true)) ? $template['orientation'] : 'portrait',
        'cards_per_page' => 1,
        'background_image' => isset($template['background_image']) ? esc_url_raw(wp_unslash($template['background_image'])) : '',
        'message_text' => isset($template['message_text']) ? wp_kses_post(wp_unslash($template['message_text'])) : '',
        'signature_one_text' => isset($template['signature_one_text']) ? wp_kses_post(wp_unslash($template['signature_one_text'])) : '',
        'signature_one_image' => isset($template['signature_one_image']) ? esc_url_raw(wp_unslash($template['signature_one_image'])) : '',
        'signature_two_text' => isset($template['signature_two_text']) ? wp_kses_post(wp_unslash($template['signature_two_text'])) : '',
        'signature_two_image' => isset($template['signature_two_image']) ? esc_url_raw(wp_unslash($template['signature_two_image'])) : '',
        'padding_top' => isset($template['padding_top']) ? max(0, (int) wp_unslash($template['padding_top'])) : 200,
        'padding_right' => isset($template['padding_right']) ? max(0, (int) wp_unslash($template['padding_right'])) : 56,
        'padding_bottom' => isset($template['padding_bottom']) ? max(0, (int) wp_unslash($template['padding_bottom'])) : 28,
        'padding_left' => isset($template['padding_left']) ? max(0, (int) wp_unslash($template['padding_left'])) : 56,
        'signature_bottom_offset' => isset($template['signature_bottom_offset']) ? max(0, (int) wp_unslash($template['signature_bottom_offset'])) : 80,
        'background_opacity' => isset($template['background_opacity']) ? max(0, min(1, (float) wp_unslash($template['background_opacity']))) : 1,
        'content_font_family' => isset($template['content_font_family']) ? sanitize_text_field(wp_unslash($template['content_font_family'])) : 'IRANYekanXFaNum',
        'content_font_size' => isset($template['content_font_size']) ? max(10, (int) wp_unslash($template['content_font_size'])) : 17,
        'content_line_height' => isset($template['content_line_height']) ? max(1, min(3, (float) wp_unslash($template['content_line_height']))) : 1.85,
        'content_paragraph_spacing' => isset($template['content_paragraph_spacing']) ? max(0, min(3, (float) wp_unslash($template['content_paragraph_spacing']))) : 0.6,
        'content_text_align' => (isset($template['content_text_align']) && in_array(wp_unslash($template['content_text_align']), ['right', 'center', 'justify', 'left'], true)) ? wp_unslash($template['content_text_align']) : 'center',
        'tracking_code_prefix' => isset($template['tracking_code_prefix']) ? sanitize_text_field(wp_unslash($template['tracking_code_prefix'])) : 'SC-',
        'physical_copy_price' => isset($template['physical_copy_price']) ? max(0, (float) wp_unslash($template['physical_copy_price'])) : 0,
    ];
}

function sc_generate_unique_certificate_tracking_code($prefix = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_certificates';
    $safe_prefix = sanitize_text_field((string) $prefix);
    $safe_prefix = preg_replace('/\s+/', '', $safe_prefix);
    $safe_prefix = substr($safe_prefix, 0, 20);

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $random_part = strtoupper(wp_generate_password(8, false, false));
        $tracking_code = $safe_prefix . $random_part;
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE tracking_code = %s LIMIT 1", $tracking_code));
        if (empty($exists)) {
            return $tracking_code;
        }
    }

    return $safe_prefix . strtoupper(uniqid('', false));
}

function sc_certificates_member_variables($member) {
    return [
        'name' => trim(($member->first_name ?: '') . ' ' . ($member->last_name ?: '')),
        'national_id' => (string) ($member->national_id ?: '-'),
        'phone' => (string) ($member->player_phone ?: '-'),
        'team' => (string) ($member->team_player ?: '-'),
        'level' => (string) ($member->skill_level ?: '-'),
        'province' => (string) ($member->province ?: '-'),
        'city' => (string) ($member->city ?: '-'),
        'birth_date' => (string) ($member->birth_date_shamsi ?: '-'),
    ];
}

function sc_certificates_replace_vars($text, $variables) {
    $out = (string) $text;
    foreach ($variables as $key => $value) {
        $out = str_replace('%' . $key . '%', (string) $value, $out);
    }
    return $out;
}

function sc_certificates_render_html($certificate_row) {
    $background_image = !empty($certificate_row->background_image) ? esc_url($certificate_row->background_image) : '';
    $message_text = wp_kses_post((string) $certificate_row->message_text);
    $signature_one_text = wp_kses_post((string) $certificate_row->signature_one_text);
    $signature_two_text = wp_kses_post((string) $certificate_row->signature_two_text);
    $signature_one_image = !empty($certificate_row->signature_one_image) ? esc_url($certificate_row->signature_one_image) : '';
    $signature_two_image = !empty($certificate_row->signature_two_image) ? esc_url($certificate_row->signature_two_image) : '';
    $title = esc_html((string) $certificate_row->title);
    $orientation = (isset($certificate_row->orientation) && $certificate_row->orientation === 'landscape') ? 'landscape' : 'portrait';
    $padding_top = isset($certificate_row->padding_top) ? max(0, (int) $certificate_row->padding_top) : 200;
    $padding_right = isset($certificate_row->padding_right) ? max(0, (int) $certificate_row->padding_right) : 56;
    $padding_bottom = isset($certificate_row->padding_bottom) ? max(0, (int) $certificate_row->padding_bottom) : 28;
    $padding_left = isset($certificate_row->padding_left) ? max(0, (int) $certificate_row->padding_left) : 56;
    $signature_bottom_offset = isset($certificate_row->signature_bottom_offset) ? max(0, (int) $certificate_row->signature_bottom_offset) : 80;
    $background_opacity = isset($certificate_row->background_opacity) ? max(0, min(1, (float) $certificate_row->background_opacity)) : 1;
    $content_font_family = !empty($certificate_row->content_font_family) ? sanitize_text_field((string) $certificate_row->content_font_family) : 'IRANYekanXFaNum';
    $allowed_font_families = ['IRANYekanXFaNum', 'Vazir', 'Shabnam', 'Morabba', 'Tahoma', 'Arial'];
    if (!in_array($content_font_family, $allowed_font_families, true)) {
        $content_font_family = 'IRANYekanXFaNum';
    }
    $content_font_size = isset($certificate_row->content_font_size) ? max(10, (int) $certificate_row->content_font_size) : 17;
    $content_line_height = isset($certificate_row->content_line_height) ? max(1, min(3, (float) $certificate_row->content_line_height)) : 1.85;
    $content_paragraph_spacing = isset($certificate_row->content_paragraph_spacing) ? max(0, min(3, (float) $certificate_row->content_paragraph_spacing)) : 0.6;
    $content_text_align = (isset($certificate_row->content_text_align) && in_array($certificate_row->content_text_align, ['right', 'center', 'justify', 'left'], true)) ? $certificate_row->content_text_align : 'center';
    $sheet_width = $orientation === 'landscape' ? '297mm' : '210mm';
    $sheet_height = $orientation === 'landscape' ? '210mm' : '297mm';
    $page_size = $orientation === 'landscape' ? 'A4 landscape' : 'A4 portrait';
    $has_bg_image = !empty($background_image);
    $font_regular_woff2 = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff2/IRANYekanXFaNum-Regular.woff2' : '';
    $font_regular_woff = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff/IRANYekanXFaNum-Regular.woff' : '';
    $font_bold_woff2 = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff2/IRANYekanXFaNum-Bold.woff2' : '';
    $font_bold_woff = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff/IRANYekanXFaNum-Bold.woff' : '';
    $vazir_regular_woff2 = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff2/Vazir.woff2' : '';
    $vazir_regular_woff = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff/Vazir.woff' : '';
    $shabnam_regular_woff2 = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff2/Shabnam.woff2' : '';
    $shabnam_regular_woff = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff/Shabnam.woff' : '';
    $morabba_regular_ttf = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/ttf/Morabba-Regular.ttf' : '';

    ob_start();
    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo $title; ?></title>
        <style>
            @font-face {
                font-family: IRANYekanXFaNum;
                font-style: normal;
                font-weight: normal;
                src: url('<?php echo esc_url($font_regular_woff); ?>') format('woff'),
                     url('<?php echo esc_url($font_regular_woff2); ?>') format('woff2');
            }
            @font-face {
                font-family: IRANYekanXFaNum;
                font-style: normal;
                font-weight: bold;
                src: url('<?php echo esc_url($font_bold_woff); ?>') format('woff'),
                     url('<?php echo esc_url($font_bold_woff2); ?>') format('woff2');
            }
            @font-face {
                font-family: Vazir;
                font-style: normal;
                font-weight: normal;
                src: url('<?php echo esc_url($vazir_regular_woff); ?>') format('woff'),
                     url('<?php echo esc_url($vazir_regular_woff2); ?>') format('woff2');
            }
            @font-face {
                font-family: Shabnam;
                font-style: normal;
                font-weight: normal;
                src: url('<?php echo esc_url($shabnam_regular_woff); ?>') format('woff'),
                     url('<?php echo esc_url($shabnam_regular_woff2); ?>') format('woff2');
            }
            @font-face {
                font-family: Morabba;
                font-style: normal;
                font-weight: normal;
                src: url('<?php echo esc_url($morabba_regular_ttf); ?>') format('truetype');
            }
            @page { size: <?php echo esc_html($page_size); ?>; margin: 0; }
            html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body { font-family: IRANYekanXFaNum, Tahoma, Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sc-certificate-actions { max-width: <?php echo esc_html($sheet_width); ?>; margin: 0 auto 12px; display: flex; gap: 8px; justify-content: flex-start; }
            .sc-certificate-actions button { border: 0; border-radius: 8px; padding: 8px 14px; cursor: pointer; font-family: inherit; background: #008a20; color: #fff; }
            .sc-certificate-sheet {
                width: <?php echo esc_html($sheet_width); ?>;
                height: <?php echo esc_html($sheet_height); ?>;
                min-height: <?php echo esc_html($sheet_height); ?>;
                max-height: <?php echo esc_html($sheet_height); ?>;
                margin: 0 auto;
                background: <?php echo $has_bg_image ? 'transparent' : '#fff'; ?>;
                position: relative;
                box-sizing: border-box;
                border: <?php echo $has_bg_image ? '0' : '1px solid #ddd'; ?>;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }
            .sc-certificate-bg {
                position: absolute;
                inset: 0;
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                opacity: <?php echo esc_html($background_opacity); ?>;
                z-index: 1;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .sc-certificate-content {
                position: relative;
                z-index: 2;
                flex: 1;
                display: flex;
                flex-direction: column;
                padding: <?php echo esc_html($padding_top); ?>px <?php echo esc_html($padding_right); ?>px <?php echo esc_html($padding_bottom); ?>px <?php echo esc_html($padding_left); ?>px;
                box-sizing: border-box;
                min-height: 0;
            }
            .sc-certificate-title { text-align: center; margin: 0 0 14px; font-size: 26px; font-weight: 700; flex-shrink: 0; }
            .sc-certificate-message {
                font-family: <?php echo esc_html($content_font_family); ?>, IRANYekanXFaNum, Tahoma, Arial, sans-serif;
                font-size: <?php echo esc_html($content_font_size); ?>px;
                line-height: <?php echo esc_html($content_line_height); ?>;
                text-align: <?php echo esc_html($content_text_align); ?>;
                flex: 1;
                min-height: 0;
                overflow: hidden;
            }
            .sc-certificate-message p { margin: 0 0 <?php echo esc_html($content_paragraph_spacing); ?>em; }
            .sc-certificate-signatures {
                display: flex;
                justify-content: space-between;
                gap: 16px;
                margin-top: auto;
                margin-bottom: <?php echo esc_html($signature_bottom_offset); ?>px;
                flex-shrink: 0;
                padding-top: 30px;
            }
            .sc-signature-box { width: 45%; text-align: center; }
            .sc-signature-image { max-height: 56px; max-width: 100%; object-fit: contain; display: inline-block; margin-bottom: 6px; }
            .sc-signature-text {
                font-size: 14px;
                line-height: 1.8;
                font-weight: normal;
                text-align: inherit;
            }
            .sc-signature-text p,
            .sc-signature-text ul,
            .sc-signature-text ol,
            .sc-signature-text h1,
            .sc-signature-text h2,
            .sc-signature-text h3,
            .sc-signature-text h4,
            .sc-signature-text h5,
            .sc-signature-text h6 { margin: 0.25em 0; }
            @media print {
                body { background: #fff; padding: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                .sc-certificate-actions { display: none !important; }
                .sc-certificate-sheet {
                    margin: 0 !important;
                    border: 0 !important;
                    width: <?php echo esc_html($sheet_width); ?> !important;
                    height: <?php echo esc_html($sheet_height); ?> !important;
                    min-height: <?php echo esc_html($sheet_height); ?> !important;
                    max-height: <?php echo esc_html($sheet_height); ?> !important;
                    box-shadow: none !important;
                    page-break-after: avoid;
                    page-break-inside: avoid;
                    break-inside: avoid;
                }
                .sc-certificate-bg {
                    opacity: <?php echo esc_html($background_opacity); ?> !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
            }
        </style>
    </head>
    <body>
        <div class="sc-certificate-actions">
            <button type="button" class="sc-certificate-pdf-btn" onclick="window.print()">خروجی PDF</button>
        </div>
        <div class="sc-certificate-sheet">
            <?php if ($background_image) : ?>
                <div class="sc-certificate-bg" style="background-image:url('<?php echo $background_image; ?>')"></div>
            <?php endif; ?>
            <div class="sc-certificate-content">
                <h1 class="sc-certificate-title"><?php echo $title; ?></h1>
                <div class="sc-certificate-message"><?php echo $message_text; ?></div>
                <div class="sc-certificate-signatures">
                    <div class="sc-signature-box">
                        <?php if ($signature_one_image) : ?><img class="sc-signature-image" src="<?php echo $signature_one_image; ?>" alt="signature-1"><?php endif; ?>
                        <div class="sc-signature-text"><?php echo $signature_one_text; ?></div>
                    </div>
                    <div class="sc-signature-box">
                        <?php if ($signature_two_image) : ?><img class="sc-signature-image" src="<?php echo $signature_two_image; ?>" alt="signature-2"><?php endif; ?>
                        <div class="sc-signature-text"><?php echo $signature_two_text; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

add_action('admin_post_sc_issue_certificates', 'sc_issue_certificates_handler');
add_action('wp_ajax_sc_certificates_preview_members', 'sc_certificates_preview_members_ajax');
add_action('admin_post_sc_request_certificate_physical_invoice', 'sc_request_certificate_physical_invoice_handler');
add_action('admin_post_sc_admin_create_certificate_physical_invoice', 'sc_admin_create_certificate_physical_invoice_handler');

function sc_certificates_preview_members_ajax() {
    check_ajax_referer('sc_certificates_preview_members', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    if (!function_exists('sc_users_export_get_members')) {
        wp_send_json_error(['message' => 'توابع فیلتر کاربران در دسترس نیست.']);
    }

    $target_type = isset($_POST['target_type']) ? sanitize_text_field(wp_unslash($_POST['target_type'])) : 'all';
    if (function_exists('sc_users_export_get_allowed_target_types')) {
        $allowed_types = sc_users_export_get_allowed_target_types();
        if (!in_array($target_type, $allowed_types, true)) {
            $target_type = 'all';
        }
    }

    $config = [
        'member_ids' => isset($_POST['member_ids']) ? array_map('absint', (array) $_POST['member_ids']) : [],
        'course_ids' => isset($_POST['course_ids']) ? array_map('absint', (array) $_POST['course_ids']) : [],
        'event_ids' => isset($_POST['event_ids']) ? array_map('absint', (array) $_POST['event_ids']) : [],
        'team_names' => isset($_POST['team_names']) ? array_map('sanitize_text_field', (array) $_POST['team_names']) : [],
        'level_names' => isset($_POST['level_names']) ? array_map('sanitize_text_field', (array) $_POST['level_names']) : [],
        'member_type' => isset($_POST['member_type']) ? sanitize_text_field(wp_unslash($_POST['member_type'])) : 'all',
    ];

    $members = sc_users_export_get_members($target_type, $config);
    $total = count($members);
    $preview_rows = array_slice($members, 0, 200);

    ob_start();
    if (empty($members)) {
        echo '<p class="description">هیچ کاربری با این فیلترها پیدا نشد.</p>';
    } else {
        echo '<div class="sc-bulk-preview-meta">تعداد کاربران فیلتر شده: <strong>' . esc_html((string) $total) . '</strong></div>';
        echo '<table class="wp-list-table widefat striped sc-bulk-preview-table">';
        echo '<thead><tr><th style="width:64px;"><label><input type="checkbox" id="sc-cert-preview-select-all" checked> انتخاب</label></th><th>نام</th><th>کد ملی</th><th>نوع</th><th>تیم</th><th>سطح</th><th>وضعیت</th></tr></thead><tbody>';
        foreach ($preview_rows as $member) {
            $full_name = trim((string) $member->first_name . ' ' . (string) $member->last_name);
            $row_label = $full_name !== '' ? $full_name : ('کاربر #' . (int) $member->id);
            $type_label = ((string) ($member->member_type ?? '') === 'team') ? 'بازیکن تیم' : 'بازیکن عادی';
            $status_label = !empty($member->is_active) ? 'فعال' : 'غیرفعال';
            echo '<tr>';
            echo '<td><input type="checkbox" class="sc-cert-preview-member-check" data-member-id="' . (int) $member->id . '" data-member-label="' . esc_attr($row_label) . '" checked></td>';
            echo '<td>' . esc_html($row_label) . '</td>';
            echo '<td>' . esc_html((string) ($member->national_id ?: '-')) . '</td>';
            echo '<td>' . esc_html($type_label) . '</td>';
            echo '<td>' . esc_html((string) ($member->team_player ?: '-')) . '</td>';
            echo '<td>' . esc_html((string) ($member->skill_level ?: '-')) . '</td>';
            echo '<td>' . esc_html($status_label) . '</td>';
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

function sc_issue_certificates_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }
    check_admin_referer('sc_issue_certificates_action', 'sc_issue_certificates_nonce');
    if (!function_exists('sc_users_export_get_members')) {
        wp_die('توابع فیلتر کاربران در دسترس نیست.');
    }

    $target_type = isset($_POST['target_type']) ? sanitize_text_field(wp_unslash($_POST['target_type'])) : 'all';
    $config = [
        'member_ids' => isset($_POST['member_ids']) ? array_map('absint', (array) $_POST['member_ids']) : [],
        'excluded_member_ids' => isset($_POST['excluded_member_ids']) ? array_map('absint', (array) $_POST['excluded_member_ids']) : [],
        'course_ids' => isset($_POST['course_ids']) ? array_map('absint', (array) $_POST['course_ids']) : [],
        'event_ids' => isset($_POST['event_ids']) ? array_map('absint', (array) $_POST['event_ids']) : [],
        'team_names' => isset($_POST['team_names']) ? array_map('sanitize_text_field', (array) $_POST['team_names']) : [],
        'level_names' => isset($_POST['level_names']) ? array_map('sanitize_text_field', (array) $_POST['level_names']) : [],
        'member_type' => isset($_POST['member_type']) ? sanitize_text_field(wp_unslash($_POST['member_type'])) : 'all',
    ];
    $template_key = isset($_POST['template_key']) ? sanitize_key(wp_unslash($_POST['template_key'])) : '';
    $templates = sc_certificates_get_saved_templates();
    if (!$template_key || !isset($templates[$template_key])) {
        wp_die('قالب گواهینامه معتبر نیست.');
    }
    $template = sc_certificates_normalize_template($templates[$template_key], $template_key);
    $members = sc_users_export_get_members($target_type, $config);
    if (empty($members)) {
        wp_die('هیچ کاربری با فیلتر انتخابی پیدا نشد.');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_certificates';
    $issued = 0;
    $sms_sent = 0;
    foreach ($members as $member) {
        $vars = sc_certificates_member_variables($member);
        $message_text = wp_kses_post(sc_certificates_replace_vars($template['message_text'], $vars));
        $tracking_code = sc_generate_unique_certificate_tracking_code($template['tracking_code_prefix'] ?? '');
        $inserted = $wpdb->insert(
            $table,
            [
                'member_id' => (int) $member->id,
                'template_key' => $template['key'],
                'title' => $template['certificate_title'],
                'message_text' => $message_text,
                'orientation' => $template['orientation'],
                'background_image' => $template['background_image'],
                'signature_one_text' => $template['signature_one_text'],
                'signature_one_image' => $template['signature_one_image'],
                'signature_two_text' => $template['signature_two_text'],
                'signature_two_image' => $template['signature_two_image'],
                'padding_top' => $template['padding_top'],
                'padding_right' => $template['padding_right'],
                'padding_bottom' => $template['padding_bottom'],
                'padding_left' => $template['padding_left'],
                'signature_bottom_offset' => $template['signature_bottom_offset'],
                'background_opacity' => $template['background_opacity'],
                'content_font_family' => $template['content_font_family'],
                'content_font_size' => $template['content_font_size'],
                'content_line_height' => $template['content_line_height'],
                'content_paragraph_spacing' => $template['content_paragraph_spacing'],
                'content_text_align' => $template['content_text_align'],
                'tracking_code' => $tracking_code,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%f', '%s', '%d', '%f', '%f', '%s', '%s', '%d', '%s', '%s']
        );
        if (!$inserted) {
            continue;
        }
        $issued++;
        $certificate_id = (int) $wpdb->insert_id;

        if (function_exists('sc_save_notification')) {
            sc_save_notification([
                'title' => 'صدور گواهینامه جدید',
                'content' => 'تبریک یک گواهینامه برای شما صادر شد از بخش گواهینامه ها میتوانید مشاهده و دانلود کنید',
                'target_type' => 'specific',
                'target_config' => ['recipient_ids' => ['member_' . (int) $member->id]],
                'send_sms' => 0,
            ]);
        }

        if (function_exists('sc_is_sms_enabled_for') && function_exists('sc_get_sms_template') && function_exists('sc_send_sms')) {
            if (sc_is_sms_enabled_for('certificate', 'user') && !empty($member->player_phone)) {
                $sms_template = sc_get_sms_template('certificate', 'user');
                $sms_vars = ['user_name' => $vars['name']];
                $sms_text = sc_replace_sms_variables($sms_template, $sms_vars);
                $pattern_code = function_exists('sc_get_sms_pattern') ? sc_get_sms_pattern('certificate', 'user') : null;
                $result = sc_send_sms($member->player_phone, $sms_text, !empty($pattern_code), $pattern_code, $sms_vars, 'certificate');
                if (!empty($result['success'])) {
                    $sms_sent++;
                }
                if (function_exists('sc_bale_notify_user')) {
                    sc_bale_notify_user((int) $member->id, $member->player_phone, $sms_text);
                }
            }
        }

        if (function_exists('sc_log_activity')) {
            sc_log_activity('created', 'certificate', $certificate_id, 'گواهینامه برای عضو صادر شد', null, ['member_id' => (int) $member->id, 'template' => $template['key']]);
        }
    }

    $redirect = add_query_arg(
        [
            'page' => 'sc-certificates-issue',
            'issued' => $issued,
            'sms_sent' => $sms_sent,
        ],
        admin_url('admin.php')
    );
    wp_safe_redirect($redirect);
    exit;
}

function sc_get_member_certificates($member_id, $limit = 10, $offset = 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_certificates';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE member_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $member_id,
        $limit,
        $offset
    ));
}

function sc_count_member_certificates($member_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_certificates';
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE member_id = %d", $member_id));
}

add_action('admin_post_sc_download_certificate', 'sc_download_certificate_handler');
add_action('admin_post_nopriv_sc_download_certificate', 'sc_download_certificate_handler');
function sc_download_certificate_handler() {
    if (!is_user_logged_in()) {
        wp_die('لطفاً وارد شوید.');
    }
    $certificate_id = isset($_GET['certificate_id']) ? absint($_GET['certificate_id']) : 0;
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if (!$certificate_id) {
        wp_die('درخواست نامعتبر است.');
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $cert_table = $wpdb->prefix . 'sc_certificates';

    $admin_mode = isset($_GET['sc_cert_admin']) && (string) wp_unslash($_GET['sc_cert_admin']) === '1';
    if ($admin_mode) {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز.');
        }
        if (!wp_verify_nonce($nonce, 'sc_admin_download_certificate_' . $certificate_id)) {
            wp_die('درخواست نامعتبر است.');
        }
        $certificate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $cert_table WHERE id = %d", $certificate_id));
    } else {
        if (!wp_verify_nonce($nonce, 'sc_download_certificate_' . $certificate_id)) {
            wp_die('درخواست نامعتبر است.');
        }
        $current_user_id = get_current_user_id();
        $member_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $members_table WHERE user_id = %d LIMIT 1", $current_user_id));
        if ($member_id <= 0) {
            wp_die('اطلاعات عضو یافت نشد.');
        }

        $certificate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $cert_table WHERE id = %d AND member_id = %d", $certificate_id, $member_id));
    }

    if (!$certificate) {
        wp_die('گواهینامه یافت نشد.');
    }

    echo sc_certificates_render_html($certificate);
    exit;
}

function sc_create_physical_certificate_invoice($certificate, $member_id, $price) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';

    $member_id = (int) $member_id;
    $price = (float) $price;
    if ($member_id <= 0 || $price <= 0 || empty($certificate) || empty($certificate->id)) {
        return ['success' => false, 'code' => 'invalid'];
    }

    $tracking_code = !empty($certificate->tracking_code) ? (string) $certificate->tracking_code : ('ID-' . (int) $certificate->id);
    $expense_name = 'نسخه فیزیکی گواهینامه';
    $invoice_description = 'درخواست نسخه فیزیکی گواهینامه به کد رهگیری: ' . $tracking_code;

    $existing_invoice_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$invoices_table}
         WHERE member_id = %d AND invoice_description = %s
         ORDER BY id DESC LIMIT 1",
        $member_id,
        $invoice_description
    ));
    if ($existing_invoice_id > 0) {
        return ['success' => false, 'code' => 'exists', 'invoice_id' => $existing_invoice_id];
    }

    $invoice_data = [
        'member_id' => $member_id,
        'course_id' => 0,
        'member_course_id' => null,
        'woocommerce_order_id' => null,
        'amount' => $price,
        'expense_name' => $expense_name,
        'invoice_description' => $invoice_description,
        'penalty_amount' => 0,
        'penalty_applied' => 0,
        'disable_penalty' => 0,
        'status' => 'pending',
        'payment_date' => null,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ];
    $invoice_format = ['%d', '%d', '%s', '%s', '%f', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%s', '%s'];

    $inserted = $wpdb->insert($invoices_table, $invoice_data, $invoice_format);
    if ($inserted === false) {
        return ['success' => false, 'code' => 'db_error'];
    }

    $invoice_id = (int) $wpdb->insert_id;
    do_action('sc_invoice_created', $invoice_id);

    if (function_exists('sc_create_woocommerce_order_for_invoice')) {
        $order_result = sc_create_woocommerce_order_for_invoice($invoice_id, $member_id, 0, $price, $expense_name);
        if (!empty($order_result['success']) && !empty($order_result['order_id'])) {
            $wpdb->update(
                $invoices_table,
                ['woocommerce_order_id' => (int) $order_result['order_id'], 'updated_at' => current_time('mysql')],
                ['id' => $invoice_id],
                ['%d', '%s'],
                ['%d']
            );
        }
    }

    return ['success' => true, 'code' => 'created', 'invoice_id' => $invoice_id];
}

function sc_request_certificate_physical_invoice_handler() {
    if (!is_user_logged_in()) {
        wp_die('لطفا وارد شوید.');
    }

    $certificate_id = isset($_GET['certificate_id']) ? absint($_GET['certificate_id']) : 0;
    if ($certificate_id <= 0 || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'sc_request_certificate_physical_invoice_' . $certificate_id)) {
        wp_die('درخواست نامعتبر است.');
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $cert_table = $wpdb->prefix . 'sc_certificates';
    $current_user_id = get_current_user_id();
    $member_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$members_table} WHERE user_id = %d LIMIT 1", $current_user_id));
    if ($member_id <= 0) {
        wp_die('اطلاعات کاربر یافت نشد.');
    }

    $certificate = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$cert_table} WHERE id = %d AND member_id = %d", $certificate_id, $member_id));
    if (!$certificate) {
        wp_die('گواهینامه یافت نشد.');
    }

    $templates = sc_certificates_get_saved_templates();
    $template_item = isset($templates[$certificate->template_key]) ? $templates[$certificate->template_key] : [];
    $template = sc_certificates_normalize_template($template_item, (string) $certificate->template_key);
    $price = (float) ($template['physical_copy_price'] ?? 0);
    $account_invoices_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-invoices') : home_url('/');
    if ($price <= 0) {
        $redirect = add_query_arg('sc_phys_status', 'price_not_set', $account_invoices_url);
        wp_safe_redirect($redirect);
        exit;
    }

    $result = sc_create_physical_certificate_invoice($certificate, $member_id, $price);
    if ($result['code'] === 'created') {
        $redirect = add_query_arg(
            [
                'sc_phys_status' => $result['code'],
                'sc_phys_cert' => (int) $certificate_id,
            ],
            $account_invoices_url
        );
    } else {
        $account_certificates_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-my-certificates') : home_url('/');
        $status_for_view = $result['code'];
        if ($result['code'] === 'exists' && !empty($result['invoice_id'])) {
            $invoices_table = $wpdb->prefix . 'sc_invoices';
            $invoice_status = (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$invoices_table} WHERE id = %d LIMIT 1", (int) $result['invoice_id']));
            if (in_array($invoice_status, ['processing', 'completed', 'paid'], true)) {
                $status_for_view = 'paid';
            }
        }
        $redirect = add_query_arg(
            [
                'sc_phys_status' => $status_for_view,
                'sc_phys_cert' => (int) $certificate_id,
            ],
            $account_certificates_url
        );
    }
    wp_safe_redirect($redirect);
    exit;
}

function sc_admin_create_certificate_physical_invoice_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }

    $certificate_id = isset($_GET['certificate_id']) ? absint($_GET['certificate_id']) : 0;
    if ($certificate_id <= 0 || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'sc_admin_create_certificate_physical_invoice_' . $certificate_id)) {
        wp_die('درخواست نامعتبر است.');
    }

    global $wpdb;
    $cert_table = $wpdb->prefix . 'sc_certificates';
    $certificate = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$cert_table} WHERE id = %d", $certificate_id));
    if (!$certificate) {
        wp_die('گواهینامه یافت نشد.');
    }

    $templates = sc_certificates_get_saved_templates();
    $template_item = isset($templates[$certificate->template_key]) ? $templates[$certificate->template_key] : [];
    $template = sc_certificates_normalize_template($template_item, (string) $certificate->template_key);
    $price = (float) ($template['physical_copy_price'] ?? 0);
    if ($price <= 0) {
        wp_safe_redirect(add_query_arg(['page' => 'sc-certificates-list', 'sc_phys_status' => 'price_not_set'], admin_url('admin.php')));
        exit;
    }

    $result = sc_create_physical_certificate_invoice($certificate, (int) $certificate->member_id, $price);
    wp_safe_redirect(add_query_arg(['page' => 'sc-certificates-list', 'sc_phys_status' => $result['code']], admin_url('admin.php')));
    exit;
}
