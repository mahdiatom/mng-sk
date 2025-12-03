<?php
/**
 * Excel Export Functions
 * استفاده از PhpSpreadsheet برای ایجاد فایل Excel
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * بررسی و بارگذاری PhpSpreadsheet
 */
function sc_check_phpspreadsheet() {
    // بررسی اینکه آیا PhpSpreadsheet نصب شده است یا نه
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // تلاش برای بارگذاری از vendor directory
        $vendor_path = SC_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($vendor_path)) {
            require_once $vendor_path;
        } else {
            // تلاش برای بارگذاری مستقیم
            $spreadsheet_path = SC_PLUGIN_DIR . 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php';
            if (file_exists($spreadsheet_path)) {
                // بارگذاری دستی کلاس‌های مورد نیاز
                require_once SC_PLUGIN_DIR . 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php';
                require_once SC_PLUGIN_DIR . 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php';
                require_once SC_PLUGIN_DIR . 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Style/Fill.php';
                require_once SC_PLUGIN_DIR . 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Style/Alignment.php';
                require_once SC_PLUGIN_DIR . 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Style/Border.php';
                require_once SC_PLUGIN_DIR . 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Cell/Coordinate.php';
            } else {
                // اگر نصب نشده، پیام خطا نمایش بده
                $install_url = SC_PLUGIN_URL . 'install-phpspreadsheet-simple.php';
                wp_die(
                    '<div style="padding: 20px; font-family: Tahoma, Arial; direction: rtl;">' .
                    '<h1 style="color: #d63638;">⚠️ PhpSpreadsheet نصب نشده است</h1>' .
                    '<p>برای استفاده از قابلیت خروجی Excel، باید PhpSpreadsheet را نصب کنید.</p>' .
                    '<h2>روش نصب:</h2>' .
                    '<h3>روش 1: استفاده از Composer (پیشنهادی)</h3>' .
                    '<ol style="line-height: 2;">' .
                    '<li>Composer را از <a href="https://getcomposer.org/download/" target="_blank">getcomposer.org</a> دانلود و نصب کنید</li>' .
                    '<li>در Command Prompt به پوشه افزونه بروید:<br>' .
                    '<code style="background: #f0f0f1; padding: 5px; display: inline-block; margin: 5px 0;">cd "C:\\xampp\\htdocs\\ai.com\\wp-content\\plugins\\AI sportclub"</code></li>' .
                    '<li>دستور زیر را اجرا کنید:<br>' .
                    '<code style="background: #f0f0f1; padding: 5px; display: inline-block; margin: 5px 0;">composer install</code></li>' .
                    '</ol>' .
                    '<h3>روش 2: استفاده از Composer.phar (بدون نصب Composer)</h3>' .
                    '<ol style="line-height: 2;">' .
                    '<li>فایل <code>composer.phar</code> را از <a href="https://getcomposer.org/download/" target="_blank">getcomposer.org</a> دانلود کنید</li>' .
                    '<li>فایل را در پوشه افزونه قرار دهید</li>' .
                    '<li>در Command Prompt دستور زیر را اجرا کنید:<br>' .
                    '<code style="background: #f0f0f1; padding: 5px; display: inline-block; margin: 5px 0;">C:\\xampp\\php\\php.exe composer.phar install</code></li>' .
                    '</ol>' .
                    '<h3>روش 3: اجرای خودکار (اگر composer.phar موجود است)</h3>' .
                    '<p>اگر فایل <code>composer.phar</code> در پوشه افزونه موجود است، می‌توانید از طریق مرورگر فایل زیر را باز کنید:</p>' .
                    '<p><a href="' . SC_PLUGIN_URL . 'run-composer-install.php" target="_blank" style="background: #2271b1; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">🔧 اجرای خودکار composer install</a></p>' .
                    '<p style="margin-top: 20px;"><strong>راهنمای کامل:</strong> فایل <code>INSTALL_EXCEL.md</code> در پوشه افزونه را مطالعه کنید.</p>' .
                    '</div>',
                    'خطا در نصب PhpSpreadsheet',
                    ['response' => 200]
                );
            }
        }
    }
}

/**
 * ایجاد استایل برای header در Excel
 */
function sc_get_excel_header_style() {
    return [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 11,
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4472C4'], // آبی
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ];
}

/**
 * ایجاد استایل برای داده‌ها در Excel
 */
function sc_get_excel_data_style() {
    return [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC'],
            ],
        ],
        'alignment' => [
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
    ];
}

/**
 * ایجاد استایل برای ردیف‌های زوج (alternate row color)
 */
function sc_get_excel_alternate_row_style() {
    return [
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F2F2F2'], // خاکستری روشن
        ],
    ];
}

/**
 * تنظیم عرض ستون‌ها به صورت خودکار
 */
function sc_auto_size_columns($sheet, $columnCount) {
    for ($col = 1; $col <= $columnCount; $col++) {
        $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
}

/**
 * ایجاد نام فایل بر اساس فیلترها
 */
function sc_generate_export_filename($type, $filters = []) {
    $filename = $type . '_';
    
    // اضافه کردن اطلاعات فیلتر به نام فایل
    $filter_parts = [];
    
    if (isset($filters['status']) && $filters['status'] !== 'all') {
        $status_labels = [
            'pending' => 'pending',
            'on-hold' => 'on-hold',
            'under_review' => 'on-hold', // برای سازگاری با داده‌های قدیمی
            'completed' => 'completed',
            'paid' => 'completed', // برای سازگاری با داده‌های قدیمی
            'processing' => 'processing',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'failed'
        ];
        if (isset($status_labels[$filters['status']])) {
            $filter_parts[] = $status_labels[$filters['status']];
        }
    }
    
    if (isset($filters['date_from']) && !empty($filters['date_from'])) {
        $filter_parts[] = date('Ymd', strtotime($filters['date_from']));
    }
    
    if (isset($filters['date_to']) && !empty($filters['date_to'])) {
        $filter_parts[] = date('Ymd', strtotime($filters['date_to']));
    }
    
    if (!empty($filter_parts)) {
        $filename .= implode('_', $filter_parts) . '_';
    }
    
    $filename .= date('Ymd_His') . '.xlsx';
    
    return $filename;
}

/**
 * Export Invoices to Excel
 */
function sc_export_invoices_to_excel() {
    sc_check_phpspreadsheet();
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    
    // دریافت فیلترها
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // ساخت WHERE clause (مثل prepare_items)
    $where_conditions = ['1=1'];
    $where_values = [];
    
    if ($filter_status !== 'all') {
        // برای completed، باید paid و completed و processing را هم در نظر بگیریم
        if ($filter_status === 'completed') {
            $where_conditions[] = "(i.status = %s OR i.status = %s OR i.status = %s)";
            $where_values[] = 'completed';
            $where_values[] = 'paid';
            $where_values[] = 'processing';
        } elseif ($filter_status === 'on-hold') {
            $where_conditions[] = "(i.status = %s OR i.status = %s)";
            $where_values[] = 'on-hold';
            $where_values[] = 'under_review';
        } else {
            $where_conditions[] = "i.status = %s";
            $where_values[] = $filter_status;
        }
    }
    
    if ($filter_course > 0) {
        $where_conditions[] = "i.course_id = %d";
        $where_values[] = $filter_course;
    }
    
    if ($filter_member > 0) {
        $where_conditions[] = "i.member_id = %d";
        $where_values[] = $filter_member;
    }
    
    if ($filter_date_from) {
        $where_conditions[] = "DATE(i.created_at) >= %s";
        $where_values[] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where_conditions[] = "DATE(i.created_at) <= %s";
        $where_values[] = $filter_date_to;
    }
    
    if ($search) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        if (is_numeric($search)) {
            $where_conditions[] = "(i.id = %d OR m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)";
            $where_values[] = intval($search);
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
        } else {
            $where_conditions[] = "(m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)";
            $where_values[] = $search_like;
            $where_values[] = $search_like;
            $where_values[] = $search_like;
        }
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت داده‌ها
    $query = "SELECT i.id,
                    i.woocommerce_order_id,
                    i.amount,
                    i.expense_name,
                    i.penalty_amount,
                    i.status,
                    i.payment_date,
                    i.created_at,
                    m.first_name,
                    m.last_name,
                    m.player_phone,
                    c.title as course_title,
                    c.price as course_price
              FROM $invoices_table i
              INNER JOIN $members_table m ON i.member_id = m.id
              LEFT JOIN $courses_table c ON i.course_id = c.id AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')
              WHERE $where_clause
              ORDER BY i.created_at DESC";
    
    if (!empty($where_values)) {
        $invoices = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $invoices = $wpdb->get_results($query);
    }
    
    // ایجاد Excel
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('صورت حساب‌ها');
    
    // تنظیم جهت راست به چپ
    $sheet->setRightToLeft(true);
    
    // Header
    $headers = [
        'ردیف',
        'شماره سفارش',
        'نام و نام خانوادگی',
        'شماره تماس',
        'وضعیت',
        'تاریخ ثبت',
        'دوره',
        'هزینه اضافی',
        'مبلغ دوره',
        'مجموع قیمت',
        'جریمه',
        'تاریخ پرداخت'
    ];
    
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }
    
    // اعمال استایل به header
    $headerStyle = sc_get_excel_header_style();
    $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
    
    // داده‌ها
    $row = 2;
    $row_number = 1;
    $status_labels = [
        'pending' => 'در انتظار پرداخت',
        'on-hold' => 'در حال بررسی',
        'under_review' => 'در حال بررسی', // برای سازگاری با داده‌های قدیمی
        'processing' => 'در حال پردازش',
        'completed' => 'پرداخت شده',
        'paid' => 'پرداخت شده', // برای سازگاری با داده‌های قدیمی
        'cancelled' => 'لغو شده',
        'refunded' => 'بازگشت شده',
        'failed' => 'ناموفق'
    ];
    
    foreach ($invoices as $invoice) {
        $col = 1;
        
        // ردیف
        $sheet->setCellValueByColumnAndRow($col++, $row, $row_number++);
        
        // شماره سفارش
        $order_number = '#' . $invoice->id;
        if (!empty($invoice->woocommerce_order_id)) {
            if (function_exists('wc_get_order')) {
                $order = wc_get_order($invoice->woocommerce_order_id);
                if ($order) {
                    $order_number = $order->get_order_number();
                } else {
                    $order_number = '#' . $invoice->woocommerce_order_id;
                }
            } else {
                $order_number = '#' . $invoice->woocommerce_order_id;
            }
        }
        $sheet->setCellValueByColumnAndRow($col++, $row, $order_number);
        
        // نام و نام خانوادگی
        $sheet->setCellValueByColumnAndRow($col++, $row, $invoice->first_name . ' ' . $invoice->last_name);
        
        // شماره تماس
        $sheet->setCellValueByColumnAndRow($col++, $row, $invoice->player_phone ?: '-');
        
        // وضعیت
        $status_label = isset($status_labels[$invoice->status]) ? $status_labels[$invoice->status] : $invoice->status;
        $sheet->setCellValueByColumnAndRow($col++, $row, $status_label);
        
        // تاریخ ثبت
        $sheet->setCellValueByColumnAndRow($col++, $row, sc_date_shamsi($invoice->created_at, 'Y/m/d H:i'));
        
        // دوره
        $course_display = $invoice->course_title ?: 'بدون دوره';
        $sheet->setCellValueByColumnAndRow($col++, $row, $course_display);
        
        // هزینه اضافی
        $expense_display = $invoice->expense_name ?: '-';
        $sheet->setCellValueByColumnAndRow($col++, $row, $expense_display);
        
        // مبلغ دوره
        $course_price = $invoice->course_price ? number_format($invoice->course_price, 0) . ' تومان' : '-';
        $sheet->setCellValueByColumnAndRow($col++, $row, $course_price);
        
        // مجموع قیمت
        $total = floatval($invoice->amount) + floatval($invoice->penalty_amount);
        $sheet->setCellValueByColumnAndRow($col++, $row, number_format($total, 0) . ' تومان');
        
        // جریمه
        $penalty = floatval($invoice->penalty_amount) > 0 ? number_format($invoice->penalty_amount, 0) . ' تومان' : '-';
        $sheet->setCellValueByColumnAndRow($col++, $row, $penalty);
        
        // تاریخ پرداخت
        $payment_date = $invoice->payment_date ? sc_date_shamsi($invoice->payment_date, 'Y/m/d H:i') : '-';
        $sheet->setCellValueByColumnAndRow($col++, $row, $payment_date);
        
        // اعمال استایل به ردیف
        $dataStyle = sc_get_excel_data_style();
        if ($row % 2 == 0) {
            $alternateStyle = sc_get_excel_alternate_row_style();
            $sheet->getStyle("A$row:L$row")->applyFromArray(array_merge($dataStyle, $alternateStyle));
        } else {
            $sheet->getStyle("A$row:L$row")->applyFromArray($dataStyle);
        }
        
        $row++;
    }
    
    // تنظیم عرض ستون‌ها
    sc_auto_size_columns($sheet, 12);
    
    // ایجاد نام فایل
    $filters = [
        'status' => $filter_status,
        'date_from' => $filter_date_from,
        'date_to' => $filter_date_to
    ];
    $filename = sc_generate_export_filename('invoices', $filters);
    
    // ارسال فایل
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export Attendance to Excel
 */
function sc_export_attendance_to_excel() {
    sc_check_phpspreadsheet();
    
    global $wpdb;
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    
    // دریافت فیلترها
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
    
    // ساخت WHERE clause
    $where_conditions = ['1=1'];
    $where_values = [];
    
    if ($filter_course > 0) {
        $where_conditions[] = "a.course_id = %d";
        $where_values[] = $filter_course;
    }
    
    if ($filter_member > 0) {
        $where_conditions[] = "a.member_id = %d";
        $where_values[] = $filter_member;
    }
    
    if ($filter_date_from) {
        $where_conditions[] = "a.attendance_date >= %s";
        $where_values[] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where_conditions[] = "a.attendance_date <= %s";
        $where_values[] = $filter_date_to;
    }
    
    if ($filter_status !== 'all') {
        $where_conditions[] = "a.status = %s";
        $where_values[] = $filter_status;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت داده‌ها
    $query = "SELECT a.*, 
                     m.first_name, m.last_name, m.national_id,
                     c.title as course_title
              FROM $attendances_table a
              INNER JOIN $members_table m ON a.member_id = m.id
              INNER JOIN $courses_table c ON a.course_id = c.id
              WHERE $where_clause
              ORDER BY a.attendance_date DESC, a.created_at DESC";
    
    if (!empty($where_values)) {
        $attendances = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $attendances = $wpdb->get_results($query);
    }
    
    // ایجاد Excel
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('حضور و غیاب');
    
    // تنظیم جهت راست به چپ
    $sheet->setRightToLeft(true);
    
    // Header
    $headers = [
        'ردیف',
        'تاریخ',
        'دوره',
        'نام',
        'نام خانوادگی',
        'کد ملی',
        'وضعیت',
        'تاریخ ثبت'
    ];
    
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }
    
    // اعمال استایل به header
    $headerStyle = sc_get_excel_header_style();
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
    
    // داده‌ها
    $row = 2;
    $row_number = 1;
    
    foreach ($attendances as $attendance) {
        $col = 1;
        
        // ردیف
        $sheet->setCellValueByColumnAndRow($col++, $row, $row_number++);
        
        // تاریخ
        $sheet->setCellValueByColumnAndRow($col++, $row, sc_date_shamsi_date_only($attendance->attendance_date));
        
        // دوره
        $sheet->setCellValueByColumnAndRow($col++, $row, $attendance->course_title);
        
        // نام
        $sheet->setCellValueByColumnAndRow($col++, $row, $attendance->first_name);
        
        // نام خانوادگی
        $sheet->setCellValueByColumnAndRow($col++, $row, $attendance->last_name);
        
        // کد ملی
        $sheet->setCellValueByColumnAndRow($col++, $row, $attendance->national_id);
        
        // وضعیت
        $status_label = $attendance->status === 'present' ? 'حاضر' : 'غایب';
        $sheet->setCellValueByColumnAndRow($col++, $row, $status_label);
        
        // تاریخ ثبت
        $sheet->setCellValueByColumnAndRow($col++, $row, sc_date_shamsi($attendance->created_at, 'Y/m/d H:i'));
        
        // اعمال استایل به ردیف
        $dataStyle = sc_get_excel_data_style();
        if ($row % 2 == 0) {
            $alternateStyle = sc_get_excel_alternate_row_style();
            $sheet->getStyle("A$row:H$row")->applyFromArray(array_merge($dataStyle, $alternateStyle));
        } else {
            $sheet->getStyle("A$row:H$row")->applyFromArray($dataStyle);
        }
        
        $row++;
    }
    
    // تنظیم عرض ستون‌ها
    sc_auto_size_columns($sheet, 8);
    
    // ایجاد نام فایل
    $filters = [
        'date_from' => $filter_date_from,
        'date_to' => $filter_date_to,
        'status' => $filter_status
    ];
    $filename = sc_generate_export_filename('attendance', $filters);
    
    // ارسال فایل
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export Members to Excel
 */
function sc_export_members_to_excel() {
    sc_check_phpspreadsheet();
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    
    // دریافت فیلترها
    $filter_status = isset($_GET['player_status']) ? sanitize_text_field($_GET['player_status']) : (isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all');
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // ساخت WHERE clause
    $where_conditions = ['1=1'];
    $where_values = [];
    
    if ($filter_status === 'active') {
        $where_conditions[] = "m.is_active = 1";
    } elseif ($filter_status === 'inactive') {
        $where_conditions[] = "m.is_active = 0";
    }
    
    if ($filter_course > 0) {
        $where_conditions[] = "m.id IN (SELECT member_id FROM $member_courses_table WHERE course_id = %d AND status = 'active')";
        $where_values[] = $filter_course;
    }
    
    if ($search) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where_conditions[] = "(m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s OR m.player_phone LIKE %s)";
        $where_values[] = $search_like;
        $where_values[] = $search_like;
        $where_values[] = $search_like;
        $where_values[] = $search_like;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت داده‌ها
    $query = "SELECT m.* FROM $members_table m WHERE $where_clause ORDER BY m.last_name ASC, m.first_name ASC";
    
    if (!empty($where_values)) {
        $members = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $members = $wpdb->get_results($query);
    }
    
    // دریافت دوره‌های هر عضو
    foreach ($members as $member) {
        $member_courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.title 
             FROM $courses_table c 
             INNER JOIN $member_courses_table mc ON c.id = mc.course_id 
             WHERE mc.member_id = %d AND mc.status = 'active' AND c.deleted_at IS NULL 
             ORDER BY c.title ASC",
            $member->id
        ));
        $member->courses = $member_courses;
    }
    
    // ایجاد Excel
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('اعضا');
    
    // تنظیم جهت راست به چپ
    $sheet->setRightToLeft(true);
    
    // Header
    $headers = [
        'ردیف',
        'شناسه',
        'نام',
        'نام خانوادگی',
        'کد ملی',
        'شماره تماس',
        'تاریخ تولد',
        'وضعیت',
        'تکمیل پروفایل',
        'دوره‌ها'
    ];
    
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }
    
    // اعمال استایل به header
    $headerStyle = sc_get_excel_header_style();
    $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
    
    // داده‌ها
    $row = 2;
    $row_number = 1;
    
    foreach ($members as $member) {
        $col = 1;
        
        // ردیف
        $sheet->setCellValueByColumnAndRow($col++, $row, $row_number++);
        
        // شناسه
        $sheet->setCellValueByColumnAndRow($col++, $row, $member->id);
        
        // نام
        $sheet->setCellValueByColumnAndRow($col++, $row, $member->first_name);
        
        // نام خانوادگی
        $sheet->setCellValueByColumnAndRow($col++, $row, $member->last_name);
        
        // کد ملی
        $sheet->setCellValueByColumnAndRow($col++, $row, $member->national_id);
        
        // شماره تماس
        $sheet->setCellValueByColumnAndRow($col++, $row, $member->player_phone ?: '-');
        
        // تاریخ تولد
        $sheet->setCellValueByColumnAndRow($col++, $row, $member->birth_date_shamsi ?: '-');
        
        // وضعیت
        $status_label = $member->is_active ? 'فعال' : 'غیرفعال';
        $sheet->setCellValueByColumnAndRow($col++, $row, $status_label);
        
        // تکمیل پروفایل
        $is_completed = sc_check_profile_completed($member->id);
        $profile_status = $is_completed ? 'تکمیل شده' : 'ناقص';
        $sheet->setCellValueByColumnAndRow($col++, $row, $profile_status);
        
        // دوره‌ها
        $course_names = [];
        if (!empty($member->courses)) {
            foreach ($member->courses as $course) {
                $course_names[] = $course->title;
            }
        }
        $courses_text = !empty($course_names) ? implode('، ', $course_names) : '-';
        $sheet->setCellValueByColumnAndRow($col++, $row, $courses_text);
        
        // اعمال استایل به ردیف
        $dataStyle = sc_get_excel_data_style();
        if ($row % 2 == 0) {
            $alternateStyle = sc_get_excel_alternate_row_style();
            $sheet->getStyle("A$row:J$row")->applyFromArray(array_merge($dataStyle, $alternateStyle));
        } else {
            $sheet->getStyle("A$row:J$row")->applyFromArray($dataStyle);
        }
        
        $row++;
    }
    
    // تنظیم عرض ستون‌ها
    sc_auto_size_columns($sheet, 10);
    
    // ایجاد نام فایل
    $filters = [
        'status' => $filter_status
    ];
    $filename = sc_generate_export_filename('members', $filters);
    
    // ارسال فایل
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export Attendance Overall (School-style attendance sheet) to Excel
 */
function sc_export_attendance_overall_to_excel() {
    sc_check_phpspreadsheet();
    
    global $wpdb;
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    
    // دریافت فیلترها
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
    
    // ساخت WHERE clause
    $where_conditions = ['1=1'];
    $where_values = [];
    
    if ($filter_course > 0) {
        $where_conditions[] = "a.course_id = %d";
        $where_values[] = $filter_course;
    }
    
    if ($filter_date_from) {
        $where_conditions[] = "a.attendance_date >= %s";
        $where_values[] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where_conditions[] = "a.attendance_date <= %s";
        $where_values[] = $filter_date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت لیست حضور و غياب‌ها
    $query = "SELECT 
                a.member_id,
                a.attendance_date,
                a.status,
                m.first_name,
                m.last_name
              FROM $attendances_table a
              INNER JOIN $members_table m ON a.member_id = m.id
              WHERE $where_clause
              ORDER BY m.last_name ASC, m.first_name ASC, a.attendance_date ASC";
    
    if (!empty($where_values)) {
        $all_attendances = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $all_attendances = $wpdb->get_results($query);
    }
    
    // ساخت ساختار داده برای نمایش
    $overall_data = [];
    $dates_list = [];
    
    // گروه‌بندی بر اساس member_id و تاریخ
    foreach ($all_attendances as $attendance) {
        $member_id = $attendance->member_id;
        $date_key = $attendance->attendance_date;
        
        if (!isset($overall_data[$member_id])) {
            $overall_data[$member_id] = [
                'name' => $attendance->first_name . ' ' . $attendance->last_name,
                'attendances' => []
            ];
        }
        
        $overall_data[$member_id]['attendances'][$date_key] = $attendance->status;
        
        // اضافه کردن تاریخ به لیست تاریخ‌ها (اگر قبلاً اضافه نشده)
        if (!in_array($date_key, $dates_list)) {
            $dates_list[] = $date_key;
        }
    }
    
    // مرتب‌سازی تاریخ‌ها
    sort($dates_list);
    
    // ایجاد Excel
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('لیست کلی حضور و غیاب');
    
    // تنظیم جهت راست به چپ
    $sheet->setRightToLeft(true);
    
    // Header - ستون اول: نام و نام خانوادگی
    $sheet->setCellValueByColumnAndRow(1, 1, 'نام و نام خانوادگی');
    
    // Header - ستون‌های تاریخ
    $col = 2;
    foreach ($dates_list as $date) {
        $sheet->setCellValueByColumnAndRow($col, 1, sc_date_shamsi_date_only($date));
        $col++;
    }
    
    // اعمال استایل به header
    $headerStyle = sc_get_excel_header_style();
    $last_col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
    $sheet->getStyle("A1:{$last_col_letter}1")->applyFromArray($headerStyle);
    
    // داده‌ها
    $row = 2;
    
    foreach ($overall_data as $member_id => $member_data) {
        $col = 1;
        
        // ستون اول: نام و نام خانوادگی
        $sheet->setCellValueByColumnAndRow($col++, $row, $member_data['name']);
        
        // ستون‌های تاریخ
        foreach ($dates_list as $date) {
            if (isset($member_data['attendances'][$date])) {
                $status = $member_data['attendances'][$date];
                if ($status === 'present') {
                    $sheet->setCellValueByColumnAndRow($col, $row, '✓');
                } else {
                    $sheet->setCellValueByColumnAndRow($col, $row, '✗');
                }
            } else {
                $sheet->setCellValueByColumnAndRow($col, $row, '-');
            }
            $col++;
        }
        
        // اعمال استایل به ردیف
        $dataStyle = sc_get_excel_data_style();
        $last_col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
        if ($row % 2 == 0) {
            $alternateStyle = sc_get_excel_alternate_row_style();
            $sheet->getStyle("A$row:{$last_col_letter}$row")->applyFromArray(array_merge($dataStyle, $alternateStyle));
        } else {
            $sheet->getStyle("A$row:{$last_col_letter}$row")->applyFromArray($dataStyle);
        }
        
        $row++;
    }
    
    // تنظیم عرض ستون‌ها
    $sheet->getColumnDimension('A')->setWidth(25); // ستون نام
    for ($c = 2; $c < $col; $c++) {
        $col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        $sheet->getColumnDimension($col_letter)->setWidth(15); // ستون‌های تاریخ
    }
    
    // ایجاد نام فایل
    $course_title = '';
    if ($filter_course > 0) {
        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT title FROM $courses_table WHERE id = %d",
            $filter_course
        ));
        if ($course) {
            $course_title = $course->title;
        }
    }
    
    $filters = [
        'course' => $course_title,
        'date_from' => $filter_date_from,
        'date_to' => $filter_date_to
    ];
    $filename = sc_generate_export_filename('attendance_overall', $filters);
    
    // ارسال فایل
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

