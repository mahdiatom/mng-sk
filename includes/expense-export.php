<?php
/**
 * Export Expenses to Excel
 */
function sc_export_expenses_to_excel() {
    sc_check_phpspreadsheet();
    
    global $wpdb;
    $expenses_table = $wpdb->prefix . 'sc_expenses';
    $expense_categories_table = $wpdb->prefix . 'sc_expense_categories';
    
    // دریافت فیلترها
    $filter_category = isset($_GET['filter_category']) ? absint($_GET['filter_category']) : 0;
    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // ساخت WHERE clause
    $where_conditions = ['1=1'];
    $where_values = [];
    
    if ($filter_category > 0) {
        $where_conditions[] = "e.category_id = %d";
        $where_values[] = $filter_category;
    }
    
    if ($filter_date_from) {
        $where_conditions[] = "e.expense_date_gregorian >= %s";
        $where_values[] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where_conditions[] = "e.expense_date_gregorian <= %s";
        $where_values[] = $filter_date_to;
    }
    
    if ($search) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where_conditions[] = "(e.name LIKE %s OR e.description LIKE %s)";
        $where_values[] = $search_like;
        $where_values[] = $search_like;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت داده‌ها
    $query = "SELECT e.*, 
                     ec.name as category_name
              FROM $expenses_table e
              LEFT JOIN $expense_categories_table ec ON e.category_id = ec.id
              WHERE $where_clause
              ORDER BY e.expense_date_gregorian DESC, e.created_at DESC";
    
    if (!empty($where_values)) {
        $expenses = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $expenses = $wpdb->get_results($query);
    }
    
    // ایجاد Excel
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('هزینه‌ها');
    
    // تنظیم جهت راست به چپ
    $sheet->setRightToLeft(true);
    
    // Header
    $headers = [
        'ردیف',
        'نام هزینه',
        'دسته‌بندی',
        'تاریخ (شمسی)',
        'مبلغ',
        'توضیحات',
        'تاریخ ثبت'
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
    
    foreach ($expenses as $expense) {
        $col = 1;
        
        // ردیف
        $sheet->setCellValueByColumnAndRow($col++, $row, $row_number++);
        
        // نام هزینه
        $sheet->setCellValueByColumnAndRow($col++, $row, $expense->name);
        
        // دسته‌بندی
        $sheet->setCellValueByColumnAndRow($col++, $row, $expense->category_name ?: '-');
        
        // تاریخ (شمسی)
        $sheet->setCellValueByColumnAndRow($col++, $row, $expense->expense_date_shamsi);
        
        // مبلغ
        $sheet->setCellValueByColumnAndRow($col++, $row, number_format($expense->amount, 0, '.', ',') . ' تومان');
        
        // توضیحات
        $sheet->setCellValueByColumnAndRow($col++, $row, $expense->description ?: '-');
        
        // تاریخ ثبت
        $sheet->setCellValueByColumnAndRow($col++, $row, sc_date_shamsi($expense->created_at, 'Y/m/d H:i'));
        
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
    $filters = [
        'date_from' => $filter_date_from,
        'date_to' => $filter_date_to
    ];
    $filename = sc_generate_export_filename('expenses', $filters);
    
    // ارسال فایل
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}



