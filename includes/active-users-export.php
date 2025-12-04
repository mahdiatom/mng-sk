<?php
/**
 * Export Active Users to Excel
 */
function sc_export_active_users_to_excel() {
    sc_check_phpspreadsheet();
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    
    // دریافت فیلترها
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_debt_status = isset($_GET['filter_debt_status']) ? sanitize_text_field($_GET['filter_debt_status']) : 'all';
    $filter_insurance_status = isset($_GET['filter_insurance_status']) ? sanitize_text_field($_GET['filter_insurance_status']) : 'all';
    $filter_profile_status = isset($_GET['filter_profile_status']) ? sanitize_text_field($_GET['filter_profile_status']) : 'all';
    
    // ساخت WHERE clause
    $where_conditions = ['m.is_active = 1'];
    $where_values = [];
    
    if ($filter_member > 0) {
        $where_conditions[] = "m.id = %d";
        $where_values[] = $filter_member;
    }
    
    if ($filter_course > 0) {
        $where_conditions[] = "m.id IN (SELECT member_id FROM $member_courses_table WHERE course_id = %d AND status = 'active')";
        $where_values[] = $filter_course;
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
    
    // محاسبه اطلاعات اضافی برای هر کاربر
    $export_data = [];
    foreach ($members as $member) {
        // محاسبه بدهی
        $debt_query = "SELECT SUM(amount) as total_debt 
                       FROM $invoices_table 
                       WHERE member_id = %d 
                       AND status IN ('pending')";
        $debt_result = $wpdb->get_var($wpdb->prepare($debt_query, $member->id));
        $debt_amount = $debt_result ? floatval($debt_result) : 0;
        $has_debt = $debt_amount > 0;
        
        // بررسی وضعیت بیمه
        $insurance_active = false;
        if (!empty($member->insurance_expiry_date_shamsi)) {
            $today = new DateTime();
            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
            $today_shamsi = $today_jalali[0] . '/' . 
                           str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                           str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
            
            $expiry_parts = explode('/', $member->insurance_expiry_date_shamsi);
            $today_parts = explode('/', $today_shamsi);
            
            if (count($expiry_parts) === 3 && count($today_parts) === 3) {
                $expiry_year = (int)$expiry_parts[0];
                $expiry_month = (int)$expiry_parts[1];
                $expiry_day = (int)$expiry_parts[2];
                
                $today_year = (int)$today_parts[0];
                $today_month = (int)$today_parts[1];
                $today_day = (int)$today_parts[2];
                
                $is_expired = false;
                if ($expiry_year < $today_year) {
                    $is_expired = true;
                } elseif ($expiry_year == $today_year) {
                    if ($expiry_month < $today_month) {
                        $is_expired = true;
                    } elseif ($expiry_month == $today_month) {
                        if ($expiry_day < $today_day) {
                            $is_expired = true;
                        }
                    }
                }
                
                $insurance_active = !$is_expired;
            }
        }
        
        // بررسی تکمیل پروفایل
        $profile_completed = sc_check_profile_completed($member->id);
        
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
        $courses_text = !empty($course_names) ? implode('، ', $course_names) : '-';
        
        // اعمال فیلترهای اضافی
        if ($filter_debt_status !== 'all') {
            if ($filter_debt_status === 'has_debt' && !$has_debt) {
                continue;
            }
            if ($filter_debt_status === 'no_debt' && $has_debt) {
                continue;
            }
        }
        
        if ($filter_insurance_status !== 'all') {
            if ($filter_insurance_status === 'active' && !$insurance_active) {
                continue;
            }
            if ($filter_insurance_status === 'expired' && $insurance_active) {
                continue;
            }
        }
        
        if ($filter_profile_status !== 'all') {
            if ($filter_profile_status === 'completed' && !$profile_completed) {
                continue;
            }
            if ($filter_profile_status === 'incomplete' && $profile_completed) {
                continue;
            }
        }
        
        $export_data[] = [
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'courses' => $courses_text,
            'phone' => $member->player_phone ?: '-',
            'debt_amount' => $has_debt ? $debt_amount : 0,
            'profile_status' => $profile_completed ? 'تکمیل' : 'ناقص',
            'insurance_status' => !empty($member->insurance_expiry_date_shamsi) ? ($insurance_active ? 'فعال' : 'منقضی') : '-'
        ];
    }
    
    // ایجاد Excel
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('کاربران فعال');
    
    // تنظیم جهت راست به چپ
    $sheet->setRightToLeft(true);
    
    // Header
    $headers = [
        'ردیف',
        'نام و نام خانوادگی',
        'دوره‌های فعال',
        'شماره تماس',
        'مقدار بدهی',
        'وضعیت پروفایل',
        'بیمه'
    ];
    
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }
    
    // اعمال استایل به header
    $headerStyle = sc_get_excel_header_style();
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
    
    // داده‌ها
    $row = 2;
    $row_number = 1;
    
    foreach ($export_data as $data) {
        $col = 1;
        
        // ردیف
        $sheet->setCellValueByColumnAndRow($col++, $row, $row_number++);
        
        // نام و نام خانوادگی
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['first_name'] . ' ' . $data['last_name']);
        
        // دوره‌های فعال
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['courses']);
        
        // شماره تماس
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['phone']);
        
        // مقدار بدهی
        if ($data['debt_amount'] > 0) {
            $sheet->setCellValueByColumnAndRow($col++, $row, number_format($data['debt_amount'], 0, '.', ',') . ' تومان');
        } else {
            $sheet->setCellValueByColumnAndRow($col++, $row, '-');
        }
        
        // وضعیت پروفایل
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['profile_status']);
        
        // بیمه
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['insurance_status']);
        
        // اعمال استایل به ردیف
        $dataStyle = sc_get_excel_data_style();
        if ($row % 2 == 0) {
            $alternateStyle = sc_get_excel_alternate_row_style();
            $sheet->getStyle("A$row:G$row")->applyFromArray(array_merge($dataStyle, $alternateStyle));
        } else {
            $sheet->getStyle("A$row:G$row")->applyFromArray($dataStyle);
        }
        
        $row++;
    }
    
    // تنظیم عرض ستون‌ها
    sc_auto_size_columns($sheet, 7);
    
    // ایجاد نام فایل
    $filename = 'active_users_' . date('Ymd_His') . '.xlsx';
    
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




