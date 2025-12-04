<?php
/**
 * Export Payments to Excel
 */
function sc_export_payments_to_excel() {
    sc_check_phpspreadsheet();
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    
    // دریافت فیلترها
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
    
    // ساخت WHERE clause
    $where_conditions = ["i.status IN ('completed', 'paid')"];
    $where_values = [];
    
    if ($filter_member > 0) {
        $where_conditions[] = "i.member_id = %d";
        $where_values[] = $filter_member;
    }
    
    if ($filter_course > 0) {
        $where_conditions[] = "i.course_id = %d";
        $where_values[] = $filter_course;
    }
    
    if ($filter_date_from) {
        $where_conditions[] = "DATE(i.created_at) >= %s";
        $where_values[] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where_conditions[] = "DATE(i.created_at) <= %s";
        $where_values[] = $filter_date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت داده‌ها
    $query = "SELECT i.*,
                     m.first_name,
                     m.last_name,
                     m.player_phone,
                     c.title as course_title,
                     c.price as course_price
              FROM $invoices_table i
              INNER JOIN $members_table m ON i.member_id = m.id
              LEFT JOIN $courses_table c ON i.course_id = c.id
              WHERE $where_clause
              ORDER BY i.created_at DESC";
    
    if (!empty($where_values)) {
        $payments = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $payments = $wpdb->get_results($query);
    }
    
    // ایجاد Excel
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('پرداختی‌ها');
    
    // تنظیم جهت راست به چپ
    $sheet->setRightToLeft(true);
    
    // Header
    $headers = [
        'ردیف',
        'سفارش',
        'نام و نام خانوادگی کاربر',
        'تاریخ ثبت سفارش',
        'جزئیات سفارش',
        'مجموع قیمت',
        'شماره تماس'
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
    
    foreach ($payments as $payment) {
        $col = 1;
        
        // ردیف
        $sheet->setCellValueByColumnAndRow($col++, $row, $row_number++);
        
        // شماره سفارش
        $order_number = '#' . $payment->id;
        if (!empty($payment->woocommerce_order_id)) {
            if (function_exists('wc_get_order')) {
                $order = wc_get_order($payment->woocommerce_order_id);
                if ($order) {
                    $order_number = $order->get_order_number();
                } else {
                    $order_number = '#' . $payment->woocommerce_order_id;
                }
            } else {
                $order_number = '#' . $payment->woocommerce_order_id;
            }
        }
        $sheet->setCellValueByColumnAndRow($col++, $row, $order_number);
        
        // نام و نام خانوادگی
        $sheet->setCellValueByColumnAndRow($col++, $row, $payment->first_name . ' ' . $payment->last_name);
        
        // تاریخ ثبت
        $sheet->setCellValueByColumnAndRow($col++, $row, sc_date_shamsi($payment->created_at, 'Y/m/d H:i'));
        
        // جزئیات سفارش
        $course_title = $payment->course_title ?? '';
        $course_price = isset($payment->course_price) ? floatval($payment->course_price) : 0;
        $expense_name = $payment->expense_name ?? '';
        $total_amount = isset($payment->amount) ? floatval($payment->amount) : 0;
        
        $details_parts = [];
        if (!empty($course_title) && trim($course_title) !== '') {
            $course_display = $course_title;
            if ($course_price > 0) {
                $course_display .= ' (' . number_format($course_price, 0, '.', ',') . ' تومان)';
            }
            $details_parts[] = 'دوره: ' . $course_display;
        }
        
        if (!empty($expense_name) && trim($expense_name) !== '') {
            $expense_display = $expense_name;
            $expense_amount = $total_amount - $course_price;
            if ($expense_amount > 0) {
                $expense_display .= ' (' . number_format($expense_amount, 0, '.', ',') . ' تومان)';
            }
            $details_parts[] = 'هزینه اضافی: ' . $expense_display;
        }
        
        $details_text = !empty($details_parts) ? implode(' - ', $details_parts) : 'بدون دوره';
        $sheet->setCellValueByColumnAndRow($col++, $row, $details_text);
        
        // مجموع قیمت
        $total_with_penalty = $total_amount + (float)($payment->penalty_amount ?? 0);
        $sheet->setCellValueByColumnAndRow($col++, $row, number_format($total_with_penalty, 0, '.', ',') . ' تومان');
        
        // شماره تماس
        $sheet->setCellValueByColumnAndRow($col++, $row, $payment->player_phone ?: '-');
        
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
    $filename = 'payments_' . date('Ymd_His') . '.xlsx';
    
    // ارسال فایل
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}



