<?php
/**
 * Export Debtors to Excel
 */
if (!defined('ABSPATH')) {
    exit;
}
function sc_export_debtors_to_excel() {
    sc_check_phpspreadsheet();
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    
    // دریافت فیلترها
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field(wp_unslash((string) $_GET['filter_chapter'])) : '';
    $filter_group_raw = isset($_GET['filter_group']) ? sanitize_text_field(wp_unslash((string) $_GET['filter_group'])) : '';
    $filter_group = function_exists('sc_finance_normalize_group_filter')
        ? sc_finance_normalize_group_filter($filter_course, $filter_group_raw)
        : '';
    
    // ساخت WHERE clause
    $where_conditions = ['m.is_active = 1'];
    $where_values = [];
    
    if ($filter_member > 0) {
        $where_conditions[] = "m.id = %d";
        $where_values[] = $filter_member;
    }

    $mc_conditions = ["status = 'active'"];
    $mc_values = [];
    $needs_mc_filter = false;

    if ($filter_course > 0) {
        $mc_conditions[] = 'course_id = %d';
        $mc_values[] = $filter_course;
        $needs_mc_filter = true;
    }
    if ($filter_chapter !== '') {
        $mc_conditions[] = 'chapter = %s';
        $mc_values[] = $filter_chapter;
        $needs_mc_filter = true;
    }
    if ($filter_group !== '') {
        if ($filter_group === '__none__') {
            $mc_conditions[] = "COALESCE(group_name, '') = ''";
        } else {
            $mc_conditions[] = 'group_name = %s';
            $mc_values[] = $filter_group;
        }
        $needs_mc_filter = true;
    }

    if ($needs_mc_filter) {
        $mc_where = implode(' AND ', $mc_conditions);
        $where_conditions[] = "m.id IN (SELECT member_id FROM $member_courses_table WHERE $mc_where)";
        $where_values = array_merge($where_values, $mc_values);
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت اعضا
    $query = "SELECT m.* 
              FROM $members_table m 
              WHERE $where_clause 
              ORDER BY m.last_name ASC, m.first_name ASC";
    
    if (!empty($where_values)) {
        $members = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $members = $wpdb->get_results($query);
    }
    
    // محاسبه بدهی و فیلتر کردن فقط بدهکاران
    $debtors = [];
    foreach ($members as $member) {
        $debt_info = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(amount) as total_debt, COUNT(*) as debt_count 
             FROM $invoices_table 
             WHERE member_id = %d 
             AND status IN ('pending')",
            $member->id
        ));
        
        $debt_amount = $debt_info && $debt_info->total_debt ? floatval($debt_info->total_debt) : 0;
        $debt_count = $debt_info && $debt_info->debt_count ? intval($debt_info->debt_count) : 0;
        
        if ($debt_amount > 0) {
            $member->debt_amount = $debt_amount;
            $member->debt_count = $debt_count;
            
            // دریافت دوره‌های فعال
            $member_courses = $wpdb->get_results($wpdb->prepare(
                "SELECT c.title 
                 FROM $courses_table c 
                 INNER JOIN $member_courses_table mc ON c.id = mc.course_id 
                 WHERE mc.member_id = %d AND mc.status = 'active' AND c.deleted_at IS NULL 
                 ORDER BY c.title ASC",
                $member->id
            ));
            
            $course_names = [];
            foreach ($member_courses as $course) {
                $course_names[] = $course->title;
            }
            $member->active_courses_text = !empty($course_names) ? implode('، ', $course_names) : '-';
            
            $debtors[] = $member;
        }
    }
    
    // ایجاد Excel
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('بدهکاران');
    
    // تنظیم جهت راست به چپ
    $sheet->setRightToLeft(true);
    
    // Header
    $headers = [
        'ردیف',
        'نام و نام خانوادگی',
        'دوره‌های فعال',
        'مبلغ',
        'تعداد بدهی‌ها'
    ];
    
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }
    
    // اعمال استایل به header
    $headerStyle = sc_get_excel_header_style();
    $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
    
    // داده‌ها
    $row = 2;
    $row_number = 1;
    
    foreach ($debtors as $debtor) {
        $col = 1;
        
        // ردیف
        $sheet->setCellValueByColumnAndRow($col++, $row, $row_number++);
        
        // نام و نام خانوادگی
        $sheet->setCellValueByColumnAndRow($col++, $row, $debtor->first_name . ' ' . $debtor->last_name);
        
        // دوره‌های فعال
        $sheet->setCellValueByColumnAndRow($col++, $row, $debtor->active_courses_text);
        
        // مبلغ
        $sheet->setCellValueByColumnAndRow($col++, $row, number_format($debtor->debt_amount, 0, '.', ',') . ' تومان');
        
        // تعداد بدهی‌ها
        $sheet->setCellValueByColumnAndRow($col++, $row, $debtor->debt_count);
        
        // اعمال استایل به ردیف
        $dataStyle = sc_get_excel_data_style();
        if ($row % 2 == 0) {
            $alternateStyle = sc_get_excel_alternate_row_style();
            $sheet->getStyle("A$row:E$row")->applyFromArray(array_merge($dataStyle, $alternateStyle));
        } else {
            $sheet->getStyle("A$row:E$row")->applyFromArray($dataStyle);
        }
        
        $row++;
    }
    
    // تنظیم عرض ستون‌ها
    sc_auto_size_columns($sheet, 5);
    
    // ایجاد نام فایل
    $filename = 'debtors_' . date('Ymd_His') . '.xlsx';
    
    // ارسال فایل
    // پاک کردن تمام خروجی‌های قبلی
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
