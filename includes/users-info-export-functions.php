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
        ],
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
    return $saved;
}

function sc_users_export_save_templates($templates) {
    if (!is_array($templates)) {
        return;
    }
    update_option('sc_users_export_templates', $templates, false);
}

function sc_users_export_normalize_template($template, $fallback_key = '') {
    $labels = sc_users_export_get_field_labels();
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
        if (!isset($assigned[$field_key]) && $field_key !== 'personal_photo') {
            $columns['column_1'][] = $field_key;
        }
    }

    return [
        'key' => $key,
        'title' => isset($template['title']) ? sanitize_text_field(wp_unslash($template['title'])) : 'قالب جدید',
        'description' => isset($template['description']) ? sanitize_textarea_field(wp_unslash($template['description'])) : '',
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
    return ['all', 'free_users', 'specific', 'course', 'event', 'team', 'level', 'team_level'];
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
        $course_ids = isset($config['course_ids']) && is_array($config['course_ids']) ? array_filter(array_map('absint', $config['course_ids'])) : [];
        if (empty($course_ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
        $where[] = "m.id IN (
            SELECT DISTINCT mc.member_id
            FROM $member_courses_table mc
            WHERE mc.status = 'active'
              AND (mc.course_status_flags IS NULL OR TRIM(mc.course_status_flags) = '')
              AND mc.course_id IN ($placeholders)
        )";
        $params = array_merge($params, $course_ids);
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
    } else {
        // all: no extra where
    }

    if (!empty($excluded_ids)) {
        $exclude_placeholders = implode(',', array_fill(0, count($excluded_ids), '%d'));
        $where[] = "m.id NOT IN ($exclude_placeholders)";
        $params = array_merge($params, $excluded_ids);
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
            switch ($field) {
                case 'member_id':
                    $row[$field] = (int) $member->id;
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
                    $row[$field] = !empty($member->profile_completed) ? 'تکمیل شده' : 'ناقص';
                    break;
                case 'health_verified':
                case 'info_verified':
                    $row[$field] = !empty($member->{$field}) ? 'بله' : 'خیر';
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

function sc_users_export_to_excel($rows, $fields) {
    if (!function_exists('sc_check_phpspreadsheet')) {
        wp_die('کتابخانه Excel در دسترس نیست.');
    }
    sc_check_phpspreadsheet();

    $labels = sc_users_export_get_field_labels();
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

    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . sc_users_export_filename() . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function sc_users_export_render_pdf_page($rows, $fields, $layout = [], $title = '', $template = null) {
    $labels = sc_users_export_get_field_labels();
    $page_size = isset($layout['page_size']) && in_array($layout['page_size'], ['A4', 'A5'], true) ? $layout['page_size'] : 'A4';
    $cards_per_page = isset($layout['cards_per_page']) ? max(1, min(6, (int) $layout['cards_per_page'])) : 2;
    $export_title = $title !== '' ? $title : 'خروجی اطلاعات کاربران';

    $print_data = [
        'title' => $export_title,
        'page_size' => $page_size,
        'cards_per_page' => $cards_per_page,
        'columns_count' => (int) (($template['columns_count'] ?? $cards_per_page) ?: 2),
        'rows' => $rows,
        'fields' => $fields,
        'labels' => $labels,
        'template' => is_array($template) ? $template : null,
    ];

    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($export_title); ?></title>
        <link rel="stylesheet" href="<?php echo esc_url(SC_ASSETS_URL . 'css/admin.css'); ?>">
        <link rel="stylesheet" href="<?php echo esc_url(SC_ASSETS_URL . 'css/users-export-print.css'); ?>">
    </head>
    <body class="sc-users-export-print-page">
        <div id="sc-users-export-print-root" data-print="<?php echo esc_attr(wp_json_encode($print_data)); ?>"></div>
        <script src="<?php echo esc_url(SC_ASSETS_URL . 'js/users-export-print.js'); ?>"></script>
    </body>
    </html>
    <?php
    exit;
}

add_action('admin_post_sc_users_info_export', 'sc_users_info_export_handler');
add_action('wp_ajax_sc_users_export_preview_members', 'sc_users_export_preview_members_ajax');

function sc_users_export_preview_members_ajax() {
    check_ajax_referer('sc_users_export_preview_members', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $target_type = isset($_POST['target_type']) ? sanitize_text_field(wp_unslash($_POST['target_type'])) : 'all';
    if (!in_array($target_type, sc_users_export_get_allowed_target_types(), true)) {
        $target_type = 'all';
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
        echo '<thead><tr><th style="width:64px;"><label><input type="checkbox" id="sc-users-preview-select-all" checked> انتخاب</label></th><th>نام</th><th>کد ملی</th><th>نوع</th><th>تیم</th><th>سطح</th><th>وضعیت</th></tr></thead><tbody>';
        foreach ($preview_rows as $member) {
            $full_name = trim((string) $member->first_name . ' ' . (string) $member->last_name);
            $row_label = $full_name !== '' ? $full_name : ('کاربر #' . (int) $member->id);
            $type_label = ((string) ($member->member_type ?? '') === 'team') ? 'بازیکن تیم' : 'بازیکن عادی';
            $status_label = !empty($member->is_active) ? 'فعال' : 'غیرفعال';
            echo '<tr>';
            echo '<td><input type="checkbox" class="sc-users-preview-member-check" data-member-id="' . (int) $member->id . '" data-member-label="' . esc_attr($row_label) . '" checked></td>';
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

function sc_users_info_export_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }
    check_admin_referer('sc_users_info_export_action', 'sc_users_info_export_nonce');

    $target_type = isset($_POST['target_type']) ? sanitize_text_field(wp_unslash($_POST['target_type'])) : 'all';
    if (!in_array($target_type, sc_users_export_get_allowed_target_types(), true)) {
        $target_type = 'all';
    }

    $config = [
        'member_ids' => isset($_POST['member_ids']) ? array_map('absint', (array) $_POST['member_ids']) : [],
        'excluded_member_ids' => isset($_POST['excluded_member_ids']) ? array_map('absint', (array) $_POST['excluded_member_ids']) : [],
        'course_ids' => isset($_POST['course_ids']) ? array_map('absint', (array) $_POST['course_ids']) : [],
        'event_ids' => isset($_POST['event_ids']) ? array_map('absint', (array) $_POST['event_ids']) : [],
        'team_names' => isset($_POST['team_names']) ? array_map('sanitize_text_field', (array) $_POST['team_names']) : [],
        'level_names' => isset($_POST['level_names']) ? array_map('sanitize_text_field', (array) $_POST['level_names']) : [],
        'member_type' => isset($_POST['member_type']) ? sanitize_text_field(wp_unslash($_POST['member_type'])) : 'all',
    ];

    $selected_fields = isset($_POST['fields']) ? array_map('sanitize_text_field', (array) $_POST['fields']) : [];
    $field_labels = sc_users_export_get_field_labels();
    $fields = array_values(array_intersect($selected_fields, array_keys($field_labels)));
    if (empty($fields)) {
        wp_die('حداقل یک فیلد برای خروجی انتخاب کنید.');
    }

    $template_key = isset($_POST['template_key']) ? sanitize_text_field(wp_unslash($_POST['template_key'])) : '';
    $templates = sc_users_export_get_saved_templates();
    $template = ($template_key && isset($templates[$template_key])) ? $templates[$template_key] : null;
    if ($template) {
        $template = sc_users_export_normalize_template($template, $template_key);
    }

    if ($template && !empty($template['fields']) && empty($_POST['override_template_fields'])) {
        $fields = array_values(array_intersect((array) $template['fields'], array_keys($field_labels)));
    }

    $members = sc_users_export_get_members($target_type, $config);
    if (empty($members)) {
        wp_die('هیچ کاربری با این فیلترها پیدا نشد.');
    }

    $rows = sc_users_export_prepare_rows($members, $fields);
    $format = isset($_POST['export_format']) ? sanitize_text_field(wp_unslash($_POST['export_format'])) : 'pdf';
    $layout = [
        'page_size' => isset($_POST['page_size']) ? sanitize_text_field(wp_unslash($_POST['page_size'])) : ($template['page_size'] ?? 'A4'),
        'cards_per_page' => isset($_POST['cards_per_page']) ? absint($_POST['cards_per_page']) : (int) ($template['cards_per_page'] ?? 2),
    ];

    $image_fields = ['personal_photo', 'id_card_photo', 'sport_insurance_photo'];
    if (!empty(array_intersect($fields, $image_fields))) {
        $format = 'pdf';
    }

    if ($format === 'excel') {
        sc_users_export_to_excel($rows, $fields);
        return;
    }

    $title = $template && !empty($template['title']) ? $template['title'] : 'خروجی اطلاعات کاربران';
    sc_users_export_render_pdf_page($rows, $fields, $layout, $title, $template);
}
