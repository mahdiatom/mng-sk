<?php
/**
 * Export Payments to Excel
 */
if (!defined('ABSPATH')) {
    exit;
}
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

/**
 * Build Gregorian date range from finance filters.
 *
 * @return array{0:string,1:string}
 */
function sc_finance_export_date_range() {
    $from = isset($_GET['filter_date_from']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from'])) : '';
    $to = isset($_GET['filter_date_to']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to'])) : '';
    if ($from === '' && !empty($_GET['filter_date_from_shamsi'])) {
        $from = sc_shamsi_to_gregorian_date(sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])));
    }
    if ($to === '' && !empty($_GET['filter_date_to_shamsi'])) {
        $to = sc_shamsi_to_gregorian_date(sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])));
    }
    if ($from === '' || $to === '') {
        $today = new DateTime();
        $six = clone $today;
        $six->modify('-6 months');
        if ($from === '') {
            $from = $six->format('Y-m-d');
        }
        if ($to === '') {
            $to = $today->format('Y-m-d');
        }
    }
    return [$from, $to];
}

/**
 * Export finance course income tab.
 */
function sc_export_finance_course_income_to_excel() {
    sc_check_phpspreadsheet();
    global $wpdb;
    [$from, $to] = sc_finance_export_date_range();
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field(wp_unslash($_GET['filter_chapter'])) : '';

    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $where = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s", "i.course_id > 0"];
    $args = [$from, $to];
    if ($filter_course > 0) {
        $where[] = "i.course_id = %d";
        $args[] = $filter_course;
    }
    if ($filter_chapter !== '') {
        $where[] = "c.chapter = %s";
        $args[] = $filter_chapter;
    }
    $sql = "SELECT c.title, c.chapter, COUNT(i.id) AS paid_count, SUM(i.amount) AS income_total
            FROM $invoices_table i
            INNER JOIN $courses_table c ON c.id = i.course_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY c.id, c.title, c.chapter
            ORDER BY income_total DESC";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args));

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('درآمد دوره‌ها');
    $sheet->setRightToLeft(true);
    $headers = ['ردیف', 'دوره', 'شعبه', 'تعداد پرداخت', 'درآمد (تومان)'];
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }
    $sheet->getStyle('A1:E1')->applyFromArray(sc_get_excel_header_style());
    $r = 2;
    $i = 1;
    foreach ($rows as $row) {
        $sheet->setCellValueByColumnAndRow(1, $r, $i++);
        $sheet->setCellValueByColumnAndRow(2, $r, $row->title);
        $sheet->setCellValueByColumnAndRow(3, $r, $row->chapter ?: '-');
        $sheet->setCellValueByColumnAndRow(4, $r, (int) $row->paid_count);
        $sheet->setCellValueByColumnAndRow(5, $r, number_format((float) $row->income_total, 0, '.', ','));
        $sheet->getStyle("A{$r}:E{$r}")->applyFromArray(sc_get_excel_data_style());
        $r++;
    }
    sc_auto_size_columns($sheet, 5);
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="finance_course_income_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export finance event income tab.
 */
function sc_export_finance_event_income_to_excel() {
    sc_check_phpspreadsheet();
    global $wpdb;
    [$from, $to] = sc_finance_export_date_range();
    $filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field(wp_unslash($_GET['filter_chapter'])) : '';
    $filter_event_type = isset($_GET['filter_event_type']) ? sanitize_text_field(wp_unslash($_GET['filter_event_type'])) : '';

    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $events_table = $wpdb->prefix . 'sc_events';
    $where = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s", "i.event_id IS NOT NULL", "i.event_id > 0"];
    $args = [$from, $to];
    if ($filter_chapter !== '') {
        $where[] = "e.chapter = %s";
        $args[] = $filter_chapter;
    }
    if ($filter_event_type !== '') {
        $where[] = "e.event_type = %s";
        $args[] = $filter_event_type;
    }
    $sql = "SELECT e.name, e.event_type, e.chapter, COUNT(i.id) AS paid_count, SUM(i.amount) AS income_total
            FROM $invoices_table i
            INNER JOIN $events_table e ON e.id = i.event_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY e.id, e.name, e.event_type, e.chapter
            ORDER BY income_total DESC";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args));

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('درآمد رویدادها');
    $sheet->setRightToLeft(true);
    $headers = ['ردیف', 'عنوان', 'نوع', 'شعبه', 'تعداد پرداخت', 'درآمد (تومان)'];
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }
    $sheet->getStyle('A1:F1')->applyFromArray(sc_get_excel_header_style());
    $r = 2;
    $i = 1;
    foreach ($rows as $row) {
        $sheet->setCellValueByColumnAndRow(1, $r, $i++);
        $sheet->setCellValueByColumnAndRow(2, $r, $row->name);
        $sheet->setCellValueByColumnAndRow(3, $r, $row->event_type === 'competition' ? 'مسابقه' : 'رویداد');
        $sheet->setCellValueByColumnAndRow(4, $r, $row->chapter ?: '-');
        $sheet->setCellValueByColumnAndRow(5, $r, (int) $row->paid_count);
        $sheet->setCellValueByColumnAndRow(6, $r, number_format((float) $row->income_total, 0, '.', ','));
        $sheet->getStyle("A{$r}:F{$r}")->applyFromArray(sc_get_excel_data_style());
        $r++;
    }
    sc_auto_size_columns($sheet, 6);
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="finance_event_income_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export finance coach income tab.
 */
function sc_export_finance_coach_income_to_excel() {
    sc_check_phpspreadsheet();
    global $wpdb;
    [$from, $to] = sc_finance_export_date_range();
    $filter_coach = isset($_GET['filter_coach']) ? absint($_GET['filter_coach']) : 0;
    $filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field(wp_unslash($_GET['filter_chapter'])) : '';

    $wallet_table = $wpdb->prefix . 'sc_coach_wallet_transactions';
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $where = ["w.status = 'completed'", "w.transaction_type IN ('salary_percentage','salary_fixed')", "DATE(w.created_at) BETWEEN %s AND %s"];
    $args = [$from, $to];
    if ($filter_coach > 0) {
        $where[] = "w.coach_id = %d";
        $args[] = $filter_coach;
    }
    if ($filter_chapter !== '') {
        $where[] = "co.chapter = %s";
        $args[] = $filter_chapter;
    }
    $sql = "SELECT c.first_name, c.last_name, COUNT(w.id) AS tx_count, SUM(w.amount) AS coach_income
            FROM $wallet_table w
            INNER JOIN $coaches_table c ON c.id = w.coach_id
            LEFT JOIN $courses_table co ON co.id = w.related_course_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY c.id, c.first_name, c.last_name
            ORDER BY coach_income DESC";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args));

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('درآمد مربیان');
    $sheet->setRightToLeft(true);
    $headers = ['ردیف', 'مربی', 'تعداد تراکنش', 'درآمد مربی (تومان)'];
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }
    $sheet->getStyle('A1:D1')->applyFromArray(sc_get_excel_header_style());
    $r = 2;
    $i = 1;
    foreach ($rows as $row) {
        $sheet->setCellValueByColumnAndRow(1, $r, $i++);
        $sheet->setCellValueByColumnAndRow(2, $r, trim($row->first_name . ' ' . $row->last_name));
        $sheet->setCellValueByColumnAndRow(3, $r, (int) $row->tx_count);
        $sheet->setCellValueByColumnAndRow(4, $r, number_format((float) $row->coach_income, 0, '.', ','));
        $sheet->getStyle("A{$r}:D{$r}")->applyFromArray(sc_get_excel_data_style());
        $r++;
    }
    sc_auto_size_columns($sheet, 4);
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="finance_coach_income_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export finance club share tab.
 */
function sc_export_finance_club_share_to_excel() {
    sc_check_phpspreadsheet();
    global $wpdb;
    [$from, $to] = sc_finance_export_date_range();
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_coach = isset($_GET['filter_coach']) ? absint($_GET['filter_coach']) : 0;
    $filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field(wp_unslash($_GET['filter_chapter'])) : '';

    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $wallet_table = $wpdb->prefix . 'sc_coach_wallet_transactions';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $where_income = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s", "i.course_id > 0"];
    $where_salary = ["w.status = 'completed'", "w.transaction_type IN ('salary_percentage','salary_fixed')", "DATE(w.created_at) BETWEEN %s AND %s"];
    $args_income = [$from, $to];
    $args_salary = [$from, $to];
    if ($filter_course > 0) {
        $where_income[] = "i.course_id = %d";
        $args_income[] = $filter_course;
        $where_salary[] = "w.related_course_id = %d";
        $args_salary[] = $filter_course;
    }
    if ($filter_coach > 0) {
        $where_salary[] = "w.coach_id = %d";
        $args_salary[] = $filter_coach;
    }
    if ($filter_chapter !== '') {
        $where_income[] = "c.chapter = %s";
        $args_income[] = $filter_chapter;
        $where_salary[] = "co.chapter = %s";
        $args_salary[] = $filter_chapter;
    }
    $total_income = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(i.amount),0) FROM $invoices_table i INNER JOIN $courses_table c ON c.id = i.course_id WHERE " . implode(' AND ', $where_income), $args_income));
    $coach_income = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(w.amount),0) FROM $wallet_table w LEFT JOIN $courses_table co ON co.id = w.related_course_id WHERE " . implode(' AND ', $where_salary), $args_salary));
    $club_share = $total_income - $coach_income;

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('سهم مجموعه');
    $sheet->setRightToLeft(true);
    $headers = ['شاخص', 'مبلغ (تومان)'];
    $sheet->setCellValue('A1', $headers[0]);
    $sheet->setCellValue('B1', $headers[1]);
    $sheet->getStyle('A1:B1')->applyFromArray(sc_get_excel_header_style());
    $sheet->setCellValue('A2', 'درآمد کل دوره‌ها');
    $sheet->setCellValue('B2', number_format($total_income, 0, '.', ','));
    $sheet->setCellValue('A3', 'درآمد مربیان');
    $sheet->setCellValue('B3', number_format($coach_income, 0, '.', ','));
    $sheet->setCellValue('A4', 'درآمد مجموعه');
    $sheet->setCellValue('B4', number_format($club_share, 0, '.', ','));
    $sheet->getStyle('A2:B4')->applyFromArray(sc_get_excel_data_style());
    sc_auto_size_columns($sheet, 2);
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="finance_club_share_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export finance receivables tab.
 */
function sc_export_finance_receivables_to_excel() {
    sc_check_phpspreadsheet();
    global $wpdb;
    [$from, $to] = sc_finance_export_date_range();
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field(wp_unslash($_GET['filter_chapter'])) : '';
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $where = ["i.status = 'pending'", "DATE(i.created_at) BETWEEN %s AND %s"];
    $args = [$from, $to];
    if ($filter_course > 0) {
        $where[] = "i.course_id = %d";
        $args[] = $filter_course;
    }
    if ($filter_chapter !== '') {
        $where[] = "c.chapter = %s";
        $args[] = $filter_chapter;
    }
    $sql = "SELECT i.created_at, i.amount, m.first_name, m.last_name, c.title AS course_title, c.chapter
            FROM $invoices_table i
            LEFT JOIN $members_table m ON m.id = i.member_id
            LEFT JOIN $courses_table c ON c.id = i.course_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.created_at DESC";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args));

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('مطالبات');
    $sheet->setRightToLeft(true);
    $headers = ['ردیف', 'تاریخ', 'بازیکن', 'دوره', 'شعبه', 'مبلغ مطالبه (تومان)'];
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }
    $sheet->getStyle('A1:F1')->applyFromArray(sc_get_excel_header_style());
    $r = 2;
    $idx = 1;
    foreach ($rows as $row) {
        $sheet->setCellValueByColumnAndRow(1, $r, $idx++);
        $sheet->setCellValueByColumnAndRow(2, $r, substr((string) $row->created_at, 0, 10));
        $sheet->setCellValueByColumnAndRow(3, $r, trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')));
        $sheet->setCellValueByColumnAndRow(4, $r, $row->course_title ?: '-');
        $sheet->setCellValueByColumnAndRow(5, $r, $row->chapter ?: '-');
        $sheet->setCellValueByColumnAndRow(6, $r, number_format((float) $row->amount, 0, '.', ','));
        $sheet->getStyle("A{$r}:F{$r}")->applyFromArray(sc_get_excel_data_style());
        $r++;
    }
    sc_auto_size_columns($sheet, 6);
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="finance_receivables_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export finance cashflow tab.
 */
function sc_export_finance_cashflow_to_excel() {
    sc_check_phpspreadsheet();
    global $wpdb;
    [$from, $to] = sc_finance_export_date_range();
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field(wp_unslash($_GET['filter_chapter'])) : '';
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $expenses_table = $wpdb->prefix . 'sc_expenses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $where_in = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s"];
    $where_out = ["e.expense_date_gregorian IS NOT NULL", "DATE(e.expense_date_gregorian) BETWEEN %s AND %s"];
    $args_in = [$from, $to];
    $args_out = [$from, $to];
    if ($filter_chapter !== '') {
        $where_in[] = "c.chapter = %s";
        $args_in[] = $filter_chapter;
        $where_out[] = "e.chapter = %s";
        $args_out[] = $filter_chapter;
    }
    if ($filter_course > 0) {
        $where_in[] = "i.course_id = %d";
        $args_in[] = $filter_course;
    }
    $cash_in = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(i.amount),0) FROM $invoices_table i LEFT JOIN $courses_table c ON c.id = i.course_id WHERE " . implode(' AND ', $where_in), $args_in));
    $cash_out = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(e.amount),0) FROM $expenses_table e WHERE " . implode(' AND ', $where_out), $args_out));
    $net = $cash_in - $cash_out;

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('جریان نقدی');
    $sheet->setRightToLeft(true);
    $sheet->setCellValue('A1', 'شاخص');
    $sheet->setCellValue('B1', 'مبلغ (تومان)');
    $sheet->getStyle('A1:B1')->applyFromArray(sc_get_excel_header_style());
    $sheet->setCellValue('A2', 'ورودی نقدی');
    $sheet->setCellValue('B2', number_format($cash_in, 0, '.', ','));
    $sheet->setCellValue('A3', 'خروجی نقدی');
    $sheet->setCellValue('B3', number_format($cash_out, 0, '.', ','));
    $sheet->setCellValue('A4', 'خالص جریان نقدی');
    $sheet->setCellValue('B4', number_format($net, 0, '.', ','));
    $sheet->getStyle('A2:B4')->applyFromArray(sc_get_excel_data_style());
    sc_auto_size_columns($sheet, 2);
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="finance_cashflow_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export finance ledger tab.
 */
function sc_export_finance_ledger_to_excel() {
    sc_check_phpspreadsheet();
    global $wpdb;
    [$from, $to] = sc_finance_export_date_range();
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_chapter = isset($_GET['filter_chapter']) ? sanitize_text_field(wp_unslash($_GET['filter_chapter'])) : '';
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $expenses_table = $wpdb->prefix . 'sc_expenses';

    $where_in = ["i.status IN ('paid','completed','processing')", "i.payment_date IS NOT NULL", "DATE(i.payment_date) BETWEEN %s AND %s"];
    $args_in = [$from, $to];
    if ($filter_chapter !== '') {
        $where_in[] = "c.chapter = %s";
        $args_in[] = $filter_chapter;
    }
    if ($filter_course > 0) {
        $where_in[] = "i.course_id = %d";
        $args_in[] = $filter_course;
    }
    $income_rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(i.payment_date) AS tx_date, 'income' AS tx_type, i.amount, CONCAT(m.first_name, ' ', m.last_name) AS person_name, c.title AS ref_title, c.chapter
        FROM $invoices_table i
        LEFT JOIN $members_table m ON m.id = i.member_id
        LEFT JOIN $courses_table c ON c.id = i.course_id
        WHERE " . implode(' AND ', $where_in), $args_in));

    $where_out = ["e.expense_date_gregorian IS NOT NULL", "DATE(e.expense_date_gregorian) BETWEEN %s AND %s"];
    $args_out = [$from, $to];
    if ($filter_chapter !== '') {
        $where_out[] = "e.chapter = %s";
        $args_out[] = $filter_chapter;
    }
    $expense_rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(e.expense_date_gregorian) AS tx_date, 'expense' AS tx_type, e.amount, '' AS person_name, e.name AS ref_title, e.chapter
        FROM $expenses_table e
        WHERE " . implode(' AND ', $where_out), $args_out));

    $rows = array_merge($income_rows ?: [], $expense_rows ?: []);
    usort($rows, static function($a, $b) {
        return strcmp((string) $b->tx_date, (string) $a->tx_date);
    });

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('دفتر تراکنش‌ها');
    $sheet->setRightToLeft(true);
    $headers = ['ردیف', 'تاریخ', 'نوع', 'شرح', 'شخص', 'شعبه', 'مبلغ (تومان)'];
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }
    $sheet->getStyle('A1:G1')->applyFromArray(sc_get_excel_header_style());
    $r = 2;
    $idx = 1;
    foreach ($rows as $row) {
        $sheet->setCellValueByColumnAndRow(1, $r, $idx++);
        $sheet->setCellValueByColumnAndRow(2, $r, $row->tx_date);
        $sheet->setCellValueByColumnAndRow(3, $r, $row->tx_type === 'income' ? 'ورودی' : 'خروجی');
        $sheet->setCellValueByColumnAndRow(4, $r, $row->ref_title ?: '-');
        $sheet->setCellValueByColumnAndRow(5, $r, $row->person_name ?: '-');
        $sheet->setCellValueByColumnAndRow(6, $r, $row->chapter ?: '-');
        $sheet->setCellValueByColumnAndRow(7, $r, number_format((float) $row->amount, 0, '.', ','));
        $sheet->getStyle("A{$r}:G{$r}")->applyFromArray(sc_get_excel_data_style());
        $r++;
    }
    sc_auto_size_columns($sheet, 7);
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="finance_ledger_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}






