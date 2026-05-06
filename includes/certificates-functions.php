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
        'title' => isset($template['title']) ? sanitize_text_field($template['title']) : 'قالب جدید',
        'certificate_title' => isset($template['certificate_title']) ? sanitize_text_field($template['certificate_title']) : (isset($template['title']) ? sanitize_text_field($template['title']) : 'گواهینامه'),
        'description' => isset($template['description']) ? sanitize_textarea_field($template['description']) : '',
        'page_size' => (isset($template['page_size']) && in_array($template['page_size'], ['A4', 'A5'], true)) ? $template['page_size'] : 'A4',
        'orientation' => (isset($template['orientation']) && in_array($template['orientation'], ['portrait', 'landscape'], true)) ? $template['orientation'] : 'portrait',
        'cards_per_page' => 1,
        'background_image' => isset($template['background_image']) ? esc_url_raw($template['background_image']) : '',
        'message_text' => isset($template['message_text']) ? sanitize_textarea_field($template['message_text']) : '',
        'signature_one_text' => isset($template['signature_one_text']) ? sanitize_textarea_field($template['signature_one_text']) : '',
        'signature_one_image' => isset($template['signature_one_image']) ? esc_url_raw($template['signature_one_image']) : '',
        'signature_two_text' => isset($template['signature_two_text']) ? sanitize_textarea_field($template['signature_two_text']) : '',
        'signature_two_image' => isset($template['signature_two_image']) ? esc_url_raw($template['signature_two_image']) : '',
    ];
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
    $message_text = nl2br(esc_html((string) $certificate_row->message_text));
    $signature_one_text = nl2br(esc_html((string) $certificate_row->signature_one_text));
    $signature_two_text = nl2br(esc_html((string) $certificate_row->signature_two_text));
    $signature_one_image = !empty($certificate_row->signature_one_image) ? esc_url($certificate_row->signature_one_image) : '';
    $signature_two_image = !empty($certificate_row->signature_two_image) ? esc_url($certificate_row->signature_two_image) : '';
    $title = esc_html((string) $certificate_row->title);
    $orientation = (isset($certificate_row->orientation) && $certificate_row->orientation === 'landscape') ? 'landscape' : 'portrait';
    $sheet_width = $orientation === 'landscape' ? '297mm' : '210mm';
    $sheet_height = $orientation === 'landscape' ? '210mm' : '297mm';
    $page_size = $orientation === 'landscape' ? 'A4 landscape' : 'A4 portrait';
    $has_bg_image = !empty($background_image);
    $font_regular_woff2 = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff2/IRANYekanXFaNum-Regular.woff2' : '';
    $font_regular_woff = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff/IRANYekanXFaNum-Regular.woff' : '';
    $font_bold_woff2 = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff2/IRANYekanXFaNum-Bold.woff2' : '';
    $font_bold_woff = defined('SC_ASSETS_URL') ? SC_ASSETS_URL . 'fonts/Woff/IRANYekanXFaNum-Bold.woff' : '';

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
            @page { size: <?php echo esc_html($page_size); ?>; margin: 0; }
            body { font-family: IRANYekanXFaNum, Tahoma, Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .sc-certificate-actions { max-width: <?php echo esc_html($sheet_width); ?>; margin: 0 auto 12px; display: flex; gap: 8px; justify-content: flex-start; }
            .sc-certificate-actions button { border: 0; border-radius: 8px; padding: 8px 14px; cursor: pointer; font-family: inherit; }
            .sc-certificate-print-btn { background: #2271b1; color: #fff; }
            .sc-certificate-pdf-btn { background: #008a20; color: #fff; }
            .sc-certificate-sheet { width: <?php echo esc_html($sheet_width); ?>; min-height: <?php echo esc_html($sheet_height); ?>; margin: 0 auto; background: <?php echo $has_bg_image ? 'transparent' : '#fff'; ?>; position: relative; box-sizing: border-box; border: <?php echo $has_bg_image ? '0' : '1px solid #ddd'; ?>; }
            .sc-certificate-bg { position: absolute; inset: 0; background-size: cover; background-position: center; opacity: .35; }
            .sc-certificate-content { position: relative; z-index: 2; padding: 40mm 18mm 30mm; }
            .sc-certificate-title { text-align: center; margin: 0 0 25px; font-size: 28px; font-weight: 700; }
            .sc-certificate-message { font-size: 20px; line-height: 2.2; text-align: center; min-height: 220px; }
            .sc-certificate-signatures { display: flex; justify-content: space-between; gap: 20px; margin-top: 35mm; }
            .sc-signature-box { width: 45%; text-align: center; }
            .sc-signature-image { max-height: 70px; max-width: 100%; object-fit: contain; display: inline-block; margin-bottom: 8px; }
            .sc-signature-text { font-size: 14px; font-weight: 600; }
            @media print {
                body { background: #fff; padding: 0; }
                .sc-certificate-actions { display: none; }
                .sc-certificate-sheet { border: 0; margin: 0; width: auto; min-height: 100vh; }
            }
        </style>
    </head>
    <body>
        <div class="sc-certificate-actions">
            <button type="button" class="sc-certificate-print-btn" onclick="window.print()">پرینت</button>
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
        $message_text = sc_certificates_replace_vars($template['message_text'], $vars);
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
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
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
    if (!$certificate_id || !wp_verify_nonce($nonce, 'sc_download_certificate_' . $certificate_id)) {
        wp_die('درخواست نامعتبر است.');
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $cert_table = $wpdb->prefix . 'sc_certificates';
    $current_user_id = get_current_user_id();
    $member_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $members_table WHERE user_id = %d LIMIT 1", $current_user_id));
    if ($member_id <= 0) {
        wp_die('اطلاعات عضو یافت نشد.');
    }

    $certificate = $wpdb->get_row($wpdb->prepare("SELECT * FROM $cert_table WHERE id = %d AND member_id = %d", $certificate_id, $member_id));
    if (!$certificate) {
        wp_die('گواهینامه یافت نشد.');
    }

    echo sc_certificates_render_html($certificate);
    exit;
}
