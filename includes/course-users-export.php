<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Active members enrolled in a course (shared by modal + Excel export).
 *
 * @return array<int,array<string,mixed>>
 */
function sc_get_course_active_member_rows($course_id) {
    global $wpdb;

    $course_id = absint($course_id);
    if (!$course_id) {
        return [];
    }

    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $group_col = function_exists('sc_member_courses_has_group_column') && sc_member_courses_has_group_column()
        ? 'mc.group_name'
        : "'' AS group_name";

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.first_name, m.last_name, m.national_id, m.player_phone,
                m.father_name, m.father_phone, m.created_at, mc.enrollment_date, mc.chapter,
                {$group_col},
                ch.first_name AS coach_first_name, ch.last_name AS coach_last_name
         FROM $member_courses_table mc
         INNER JOIN $members_table m ON mc.member_id = m.id
         LEFT JOIN $coaches_table ch ON ch.id = mc.coach_id
         WHERE mc.course_id = %d
         AND mc.status = 'active'
         AND (
             mc.course_status_flags IS NULL
             OR mc.course_status_flags = ''
             OR (
                 mc.course_status_flags NOT LIKE %s
                 AND mc.course_status_flags NOT LIKE %s
                 AND mc.course_status_flags NOT LIKE %s
             )
         )
         ORDER BY COALESCE(mc.group_name, ''), mc.chapter ASC, m.last_name ASC, m.first_name ASC",
        $course_id,
        '%paused%',
        '%completed%',
        '%canceled%'
    ), ARRAY_A);

    if (empty($rows) || !is_array($rows)) {
        return [];
    }

    foreach ($rows as &$user) {
        sc_format_course_active_user_row($user);
    }
    unset($user);

    return $rows;
}

/**
 * @param array<string,mixed> $user
 */
function sc_format_course_active_user_row(array &$user) {
    if (!empty($user['enrollment_date'])) {
        $user['enrollment_date_shamsi'] = sc_date_shamsi_date_only($user['enrollment_date']);
    } else {
        $user['enrollment_date_shamsi'] = '-';
    }

    if (!empty($user['created_at'])) {
        $user['created_at_shamsi'] = sc_date_shamsi_date_only($user['created_at']);
    } else {
        $user['created_at_shamsi'] = '-';
    }

    $coach_name = trim((string) ($user['coach_first_name'] ?? '') . ' ' . (string) ($user['coach_last_name'] ?? ''));
    $user['coach_name'] = $coach_name !== '' ? $coach_name : '-';

    $group_name = isset($user['group_name']) ? trim((string) $user['group_name']) : '';
    $user['group_name'] = $group_name;

    $chapter = isset($user['chapter']) ? trim((string) $user['chapter']) : '';
    $user['chapter'] = $chapter;
}

/**
 * HTML for course users modal (styled like invoices list).
 *
 * @param array<int,array<string,mixed>> $users
 */
function sc_render_course_active_users_modal_html($course_id, array $users, $course_title = '', $export_url = '') {
    $course_id = absint($course_id);
    $course_title = $course_title !== '' ? (string) $course_title : ('دوره #' . $course_id);
    $count = count($users);
    $has_grouping = function_exists('sc_course_has_grouping_enabled') && sc_course_has_grouping_enabled($course_id);

    $show_chapter = false;
    foreach ($users as $user) {
        if (!empty($user['chapter'])) {
            $show_chapter = true;
            break;
        }
    }

    if ($export_url === '') {
        $export_url = wp_nonce_url(
            add_query_arg(
                [
                    'page' => 'sc-courses',
                    'sc_export' => 'excel',
                    'export_type' => 'course_users',
                    'course_id' => $course_id,
                ],
                admin_url('admin.php')
            ),
            'sc_export_excel'
        );
    }

    ob_start();
    ?>
    <div class="sc-course-users-modal-inner">
        <div class="sc-course-users-modal-toolbar">
            <div class="sc-course-users-modal-toolbar-text">
                <p class="sc-reports-list-desc">
                    <?php echo esc_html(sprintf('دوره «%s» — %d بازیکن فعال', $course_title, $count)); ?>
                </p>
            </div>
            <div class="sc-reports-list-header-actions">
                <a href="<?php echo esc_url($export_url); ?>" class="sc-reports-list-export-btn sc-course-users-export-btn">خروجی Excel</a>
            </div>
        </div>

        <?php if ($count === 0) : ?>
            <div class="sc-course-users-empty">
                <p>هیچ کاربر فعالی در این دوره یافت نشد.</p>
            </div>
        <?php else : ?>
            <div class="sc-reports-list-table-card sc-course-users-table-card">
                <div class="sc-course-users-table-scroll">
                    <table class="wp-list-table widefat fixed striped sc-course-users-table">
                        <thead>
                            <tr>
                                <th class="column-row">ردیف</th>
                                <th>نام</th>
                                <th>نام خانوادگی</th>
                                <th>کد ملی</th>
                                <?php if ($has_grouping) : ?>
                                    <th>گروه</th>
                                <?php endif; ?>
                                <?php if ($show_chapter) : ?>
                                    <th>شعبه</th>
                                <?php endif; ?>
                                <th>شماره تماس</th>
                                <th>نام پدر</th>
                                <th>تماس پدر</th>
                                <th>مربی</th>
                                <th>تاریخ ثبت‌نام</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $user) : ?>
                                <tr>
                                    <td class="column-row"><?php echo (int) ($index + 1); ?></td>
                                    <td><?php echo esc_html($user['first_name'] ?: '-'); ?></td>
                                    <td><?php echo esc_html($user['last_name'] ?: '-'); ?></td>
                                    <td><?php echo esc_html($user['national_id'] ?: '-'); ?></td>
                                    <?php if ($has_grouping) : ?>
                                        <td>
                                            <?php if (!empty($user['group_name'])) : ?>
                                                <span class="sc-course-users-group-badge"><?php echo esc_html($user['group_name']); ?></span>
                                            <?php else : ?>
                                                <span class="sc-course-users-group-empty">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <?php if ($show_chapter) : ?>
                                        <td><?php echo esc_html($user['chapter'] !== '' ? $user['chapter'] : '—'); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo esc_html($user['player_phone'] ?: '-'); ?></td>
                                    <td><?php echo esc_html($user['father_name'] ?: '-'); ?></td>
                                    <td><?php echo esc_html($user['father_phone'] ?: '-'); ?></td>
                                    <td><?php echo esc_html($user['coach_name'] ?: '-'); ?></td>
                                    <td><?php echo esc_html($user['enrollment_date_shamsi'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function sc_export_course_users_to_excel() {
    sc_check_phpspreadsheet();

    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';

    $course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;

    if (!$course_id) {
        wp_die('شناسه دوره معتبر نیست.');
    }

    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT title FROM $courses_table WHERE id = %d",
        $course_id
    ));

    $course_title = $course ? $course->title : 'دوره ناشناخته';
    $has_grouping = function_exists('sc_course_has_grouping_enabled') && sc_course_has_grouping_enabled($course_id);
    $users = sc_get_course_active_member_rows($course_id);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('کاربران دوره');

    $headers = ['ردیف', 'نام', 'نام خانوادگی', 'کد ملی'];
    if ($has_grouping) {
        $headers[] = 'گروه';
    }
    $headers = array_merge($headers, ['شعبه', 'شماره تماس', 'نام پدر', 'شماره تماس پدر', 'مربی', 'تاریخ ثبت‌نام']);

    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }

    $last_col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $headerStyle = sc_get_excel_header_style();
    $sheet->getStyle('A1:' . $last_col_letter . '1')->applyFromArray($headerStyle);

    $row = 2;
    foreach ($users as $index => $user) {
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $index + 1);
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['first_name'] ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['last_name'] ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['national_id'] ?: '-');
        if ($has_grouping) {
            $sheet->setCellValueByColumnAndRow($col++, $row, !empty($user['group_name']) ? $user['group_name'] : '-');
        }
        $sheet->setCellValueByColumnAndRow($col++, $row, !empty($user['chapter']) ? $user['chapter'] : '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['player_phone'] ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['father_name'] ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['father_phone'] ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['coach_name'] ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['enrollment_date_shamsi'] ?? '-');

        $dataStyle = ($row % 2 === 0) ? sc_get_excel_data_style() : sc_get_excel_alternate_row_style();
        $sheet->getStyle('A' . $row . ':' . $last_col_letter . $row)->applyFromArray($dataStyle);

        $row++;
    }

    sc_auto_size_columns($sheet, count($headers));

    $filename = 'کاربران-دوره-' . sanitize_file_name($course_title) . '-' . date('Y-m-d') . '.xlsx';

    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
