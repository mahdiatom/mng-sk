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
        ],
    ];
}

function sc_users_export_get_saved_templates() {
    $saved = get_option('sc_users_export_templates', []);
    if (!is_array($saved)) {
        $saved = [];
    }
    return array_replace(sc_users_export_get_default_templates(), $saved);
}

function sc_users_export_save_templates($templates) {
    if (!is_array($templates)) {
        return;
    }
    update_option('sc_users_export_templates', $templates, false);
}

function sc_users_export_get_field_labels() {
    return [
        'member_id' => 'شناسه بازیکن',
        'full_name' => 'نام و نام خانوادگی',
        'father_name' => 'نام پدر',
        'national_id' => 'کد ملی',
        'player_phone' => 'شماره همراه',
        'birth_date_shamsi' => 'تاریخ تولد',
        'skill_level' => 'سطح بازیکن',
        'team_player' => 'تیم بازیکن',
        'insurance_expiry_date_shamsi' => 'تاریخ بیمه',
        'active_courses' => 'دوره‌های فعال',
        'personal_photo' => 'عکس پرسنلی',
    ];
}

function sc_users_export_get_allowed_target_types() {
    return ['all', 'specific', 'course', 'event', 'team', 'level', 'team_level'];
}

function sc_users_export_get_members($target_type, $config = []) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';

    $where = ["m.is_active = 1"];
    $params = [];

    if (!empty($config['member_type']) && in_array($config['member_type'], ['normal', 'team'], true)) {
        $where[] = "(COALESCE(m.member_type, 'normal') = %s)";
        $params[] = $config['member_type'];
    }

    if ($target_type === 'specific') {
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
              AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '')
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
               AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '')
               AND c.deleted_at IS NULL
             ORDER BY c.title ASC",
            $member->id
        ));
        $member->active_courses = !empty($courses) ? implode('، ', $courses) : '-';
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
                default:
                    $row[$field] = isset($member->{$field}) && $member->{$field} !== '' ? $member->{$field} : '-';
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

function sc_users_export_render_pdf_page($rows, $fields, $layout = [], $title = '') {
    $labels = sc_users_export_get_field_labels();
    $page_size = isset($layout['page_size']) && in_array($layout['page_size'], ['A4', 'A5'], true) ? $layout['page_size'] : 'A4';
    $cards_per_page = isset($layout['cards_per_page']) ? max(1, min(6, (int) $layout['cards_per_page'])) : 2;
    $export_title = $title !== '' ? $title : 'خروجی اطلاعات کاربران';

    $print_data = [
        'title' => $export_title,
        'page_size' => $page_size,
        'cards_per_page' => $cards_per_page,
        'rows' => $rows,
        'fields' => $fields,
        'labels' => $labels,
    ];

    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($export_title); ?></title>
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

    if (in_array('personal_photo', $fields, true)) {
        $format = 'pdf';
    }

    if ($format === 'excel') {
        sc_users_export_to_excel($rows, $fields);
        return;
    }

    $title = $template && !empty($template['title']) ? $template['title'] : 'خروجی اطلاعات کاربران';
    sc_users_export_render_pdf_page($rows, $fields, $layout, $title);
}
