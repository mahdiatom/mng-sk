<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_export_course_users_to_excel() {
    sc_check_phpspreadsheet();
    
    global $wpdb;
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    
    $course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;
    
    if (!$course_id) {
        wp_die('شناسه دوره معتبر نیست.');
    }
    
    // دریافت اطلاعات دوره
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT title FROM $courses_table WHERE id = %d",
        $course_id
    ));
    
    $course_title = $course ? $course->title : 'دوره ناشناخته';
    
    // دریافت کاربران فعال دوره
    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT m.id, m.first_name, m.last_name, m.national_id, m.player_phone, 
                m.father_name, m.father_phone, m.created_at, mc.enrollment_date
         FROM $member_courses_table mc
         INNER JOIN $members_table m ON mc.member_id = m.id
         WHERE mc.course_id = %d
         AND mc.status = 'active'
         AND (
             mc.course_status_flags IS NULL
             OR mc.course_status_flags = ''
             OR (
                 mc.course_status_flags NOT LIKE '%%paused%%'
                 AND mc.course_status_flags NOT LIKE '%%completed%%'
                 AND mc.course_status_flags NOT LIKE '%%canceled%%'
             )
         )
         ORDER BY m.last_name ASC, m.first_name ASC",
        $course_id
    ), ARRAY_A);
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // تنظیم عنوان
    $sheet->setTitle('کاربران دوره');
    
    // هدرها
    $headers = ['ردیف', 'نام', 'نام خانوادگی', 'کد ملی', 'شماره تماس', 'نام پدر', 'شماره تماس پدر', 'تاریخ ثبت‌نام'];
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }
    
    // استایل هدر
    $headerStyle = sc_get_excel_header_style();
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
    
    // داده‌ها
    $row = 2;
    foreach ($users as $index => $user) {
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $index + 1);
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['first_name'] ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['last_name'] ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['national_id'] ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['player_phone'] ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['father_name'] ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $user['father_phone'] ?: '-');
        
        // تبدیل تاریخ به شمسی
        $enrollment_date = '-';
        if (!empty($user['enrollment_date'])) {
            $enrollment_date = sc_date_shamsi_date_only($user['enrollment_date']);
        }
        $sheet->setCellValueByColumnAndRow($col++, $row, $enrollment_date);
        
        // استایل داده
        $dataStyle = ($row % 2 == 0) ? sc_get_excel_data_style() : sc_get_excel_alternate_row_style();
        $sheet->getStyle("A$row:H$row")->applyFromArray($dataStyle);
        
        $row++;
    }
    
    // تنظیم عرض ستون‌ها
    sc_auto_size_columns($sheet, 1, 8);
    
    // نام فایل
    $filename = 'کاربران-دوره-' . sanitize_file_name($course_title) . '-' . date('Y-m-d') . '.xlsx';
    
    // ارسال فایل
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

