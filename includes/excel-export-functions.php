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
function sc_export_event_registrations_to_excel() {

    sc_check_phpspreadsheet();

    global $wpdb;

    $registrations_table = $wpdb->prefix . 'sc_event_registrations';
    $members_table       = $wpdb->prefix . 'sc_members';
    $events_table        = $wpdb->prefix . 'sc_events';
    $invoices_table      = $wpdb->prefix . 'sc_invoices';

    /**
     * فیلترها
     */
    $filter_status    = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
    $filter_event     = isset($_GET['filter_event']) ? absint($_GET['filter_event']) : 0;
    $filter_member    = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
    $filter_date_to   = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
    $search           = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    /**
     * WHERE clause (دقیقاً مثل invoices)
     */
    $where_conditions = ['1=1'];
    $where_values     = [];

    // وضعیت پرداخت (از invoices)
    if ($filter_status !== 'all') {
        $where_conditions[] = "i.status = %s";
        $where_values[] = $filter_status;
    }

    if ($filter_event > 0) {
        $where_conditions[] = "r.event_id = %d";
        $where_values[] = $filter_event;
    }

    if ($filter_member > 0) {
        $where_conditions[] = "r.member_id = %d";
        $where_values[] = $filter_member;
    }

    if ($filter_date_from) {
        $where_conditions[] = "DATE(r.created_at) >= %s";
        $where_values[] = $filter_date_from;
    }

    if ($filter_date_to) {
        $where_conditions[] = "DATE(r.created_at) <= %s";
        $where_values[] = $filter_date_to;
    }

    if ($search) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';

        if (is_numeric($search)) {
            $where_conditions[] = "(r.id = %d OR m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)";
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

    /**
     * کوئری صحیح (پرداخت از invoices)
     */
    $query = "
    SELECT
        r.id              AS registration_id,
        r.created_at      AS registration_date,

        m.first_name,
        m.last_name,
        m.player_phone,

        e.name            AS event_title,
        e.price           AS event_price,

        i.id              AS invoice_id,
        i.woocommerce_order_id,
        i.amount          AS paid_amount,
        i.status          AS payment_status,
        i.payment_date

    FROM {$registrations_table} r
    INNER JOIN {$members_table} m ON r.member_id = m.id
    LEFT JOIN {$events_table} e ON r.event_id = e.id
    LEFT JOIN {$invoices_table} i ON r.invoice_id = i.id
    WHERE {$where_clause}
    ORDER BY r.created_at DESC
";


    $registrations = !empty($where_values)
        ? $wpdb->get_results($wpdb->prepare($query, $where_values))
        : $wpdb->get_results($query);

    /**
     * ساخت Excel
     */
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ثبت‌نام‌های رویداد');
    $sheet->setRightToLeft(true);

    // Header
    $headers = [
        'ردیف',
        'شماره سفارش',
        'نام و نام خانوادگی',
        'شماره تماس',
        'رویداد',
        'مبلغ رویداد',
        'مبلغ پرداختی',
        'وضعیت پرداخت',
        'تاریخ ثبت',
        'تاریخ پرداخت'
    ];

    foreach ($headers as $col => $header) {
        $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
    }

    $sheet->getStyle('A1:J1')->applyFromArray(sc_get_excel_header_style());

    /**
     * داده‌ها
     */
    $row = 2;
    $index = 1;

    $status_labels = [
        'pending'     => 'در انتظار پرداخت',
        'on-hold'     => 'در حال بررسی',
        'processing'  => 'پرداخت شده',
        'completed'   => 'پرداخت شده',
        'paid'        => 'پرداخت شده',
        'cancelled'   => 'لغو شده',
        'failed'      => 'ناموفق',
        'refunded'    => 'بازگشت شده'
    ];

    foreach ($registrations as $reg) {

        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $index++);
                // شماره سفارش
        $order_number = '#' . $reg->registration_id;

        // اگر سفارش ووکامرس دارد
        if (!empty($reg->woocommerce_order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($reg->woocommerce_order_id);
            if ($order) {
                $order_number = $order->get_order_number();
            } else {
                $order_number = '#' . $reg->woocommerce_order_id;
            }
        // اگر فاکتور دارد ولی ووکامرس ندارد
        } elseif (!empty($reg->invoice_id)) {
            $order_number = '#' . $reg->invoice_id;
        }

        $sheet->setCellValueByColumnAndRow($col++, $row, $order_number);
        $sheet->setCellValueByColumnAndRow($col++, $row, trim($reg->first_name . ' ' . $reg->last_name));
        $sheet->setCellValueByColumnAndRow($col++, $row, $reg->player_phone ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $reg->event_title ?: '-');

        $sheet->setCellValueByColumnAndRow(
            $col++,
            $row,
            $reg->event_price ? number_format($reg->event_price, 0) . ' تومان' : '-'
        );

        $sheet->setCellValueByColumnAndRow(
            $col++,
            $row,
            $reg->paid_amount ? number_format($reg->paid_amount, 0) . ' تومان' : '-'
        );

        $sheet->setCellValueByColumnAndRow(
            $col++,
            $row,
            $status_labels[$reg->payment_status] ?? '-'
        );

        $sheet->setCellValueByColumnAndRow(
            $col++,
            $row,
            sc_date_shamsi($reg->registration_date, 'Y/m/d H:i')
        );

        $sheet->setCellValueByColumnAndRow(
            $col++,
            $row,
            $reg->payment_date ? sc_date_shamsi($reg->payment_date, 'Y/m/d H:i') : '-'
        );

        // استایل
        $style = ($row % 2 === 0)
            ? array_merge(sc_get_excel_data_style(), sc_get_excel_alternate_row_style())
            : sc_get_excel_data_style();

        $sheet->getStyle("A{$row}:J{$row}")->applyFromArray($style);

        $row++;
    }

    sc_auto_size_columns($sheet, 10);

    /**
     * خروجی فایل
     */
    $filename = sc_generate_export_filename('event_registrations', [
        'status'    => $filter_status,
        'date_from' => $filter_date_from,
        'date_to'   => $filter_date_to
    ]);

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
 * Export Event Registrations to Excel
 */
// function sc_export_event_registrations_to_excel() {
//     sc_check_phpspreadsheet();
    
//     global $wpdb;
//     $registrations_table = $wpdb->prefix . 'sc_event_registrations';
//     $members_table = $wpdb->prefix . 'sc_members';
//     $events_table = $wpdb->prefix . 'sc_events';
    
//     // دریافت فیلترها
//     $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
//     $filter_event = isset($_GET['filter_event']) ? absint($_GET['filter_event']) : 0;
//     $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
//     $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
//     $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
//     $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
//     // ساخت WHERE clause
//     $where_conditions = ['1=1'];
//     $where_values = [];
    
//     if ($filter_status !== 'all') {
//         $where_conditions[] = "r.status = %s";
//         $where_values[] = $filter_status;
//     }
    
//     if ($filter_event > 0) {
//         $where_conditions[] = "r.event_id = %d";
//         $where_values[] = $filter_event;
//     }
    
//     if ($filter_member > 0) {
//         $where_conditions[] = "r.member_id = %d";
//         $where_values[] = $filter_member;
//     }
    
//     if ($filter_date_from) {
//         $where_conditions[] = "r.created_at >= %s";
//         $where_values[] = $filter_date_from . ' 00:00:00';
//     }
    
//     if ($filter_date_to) {
//         $where_conditions[] = "r.created_at <= %s";
//         $where_values[] = $filter_date_to . ' 23:59:59';
//     }
    
//     if ($search) {
//         $search_like = '%' . $wpdb->esc_like($search) . '%';
//         if (is_numeric($search)) {
//             $where_conditions[] = "(r.id = %d OR m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)";
//             $where_values[] = intval($search);
//             $where_values[] = $search_like;
//             $where_values[] = $search_like;
//             $where_values[] = $search_like;
//         } else {
//             $where_conditions[] = "(m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)";
//             $where_values[] = $search_like;
//             $where_values[] = $search_like;
//             $where_values[] = $search_like;
//         }
//     }
    
//     $where_clause = implode(' AND ', $where_conditions);
    
//     // دریافت داده‌ها
//     $query = "SELECT r.id,
//                      r.amount,
//                      r.status,
//                      r.payment_date,
//                      r.created_at,
//                      m.first_name,
//                      m.last_name,
//                      m.player_phone,
//                      e.title as event_title,
//                      e.price as event_price
//               FROM $registrations_table r
//               INNER JOIN $members_table m ON r.member_id = m.id
//               LEFT JOIN $events_table e ON r.event_id = e.id
//               WHERE $where_clause
//               ORDER BY r.created_at DESC";
    
//     if (!empty($where_values)) {
//         $registrations = $wpdb->get_results($wpdb->prepare($query, $where_values));
//     } else {
//         $registrations = $wpdb->get_results($query);
//     }
    
//     // ایجاد Excel
//     $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
//     $sheet = $spreadsheet->getActiveSheet();
//     $sheet->setTitle('ثبت‌نام‌های رویداد');
//     $sheet->setRightToLeft(true);
    
//     // Header
//     $headers = [
//         'ردیف',
//         'شناسه ثبت‌نام',
//         'نام و نام خانوادگی',
//         'شماره تماس',
//         'وضعیت',
//         'تاریخ ثبت',
//         'رویداد',
//         'مبلغ رویداد',
//         'مبلغ پرداختی',
//         'تاریخ پرداخت'
//     ];
    
//     $col = 1;
//     foreach ($headers as $header) {
//         $sheet->setCellValueByColumnAndRow($col, 1, $header);
//         $col++;
//     }
    
//     $sheet->getStyle('A1:J1')->applyFromArray(sc_get_excel_header_style());
    
//     // داده‌ها
//     $row = 2;
//     $row_number = 1;
//     $status_labels = [
//         'pending' => 'در انتظار پرداخت',
//         'completed' => 'پرداخت شده',
//         'cancelled' => 'لغو شده',
//         'failed' => 'ناموفق'
//     ];
    
//     foreach ($registrations as $reg) {
//         $col = 1;
//         $sheet->setCellValueByColumnAndRow($col++, $row, $row_number++);
//         $sheet->setCellValueByColumnAndRow($col++, $row, $reg->id);
//         $sheet->setCellValueByColumnAndRow($col++, $row, $reg->first_name . ' ' . $reg->last_name);
//         $sheet->setCellValueByColumnAndRow($col++, $row, $reg->player_phone ?: '-');
//         $sheet->setCellValueByColumnAndRow($col++, $row, $status_labels[$reg->status] ?? $reg->status);
//         $sheet->setCellValueByColumnAndRow($col++, $row, sc_date_shamsi($reg->created_at, 'Y/m/d H:i'));
//         $sheet->setCellValueByColumnAndRow($col++, $row, $reg->event_title ?: '-');
//         $sheet->setCellValueByColumnAndRow($col++, $row, $reg->event_price ? number_format($reg->event_price, 0) . ' تومان' : '-');
//         $sheet->setCellValueByColumnAndRow($col++, $row, $reg->amount ? number_format($reg->amount, 0) . ' تومان' : '-');
//         $sheet->setCellValueByColumnAndRow($col++, $row, $reg->payment_date ? sc_date_shamsi($reg->payment_date, 'Y/m/d H:i') : '-');
        
//         $dataStyle = sc_get_excel_data_style();
//         if ($row % 2 == 0) {
//             $alternateStyle = sc_get_excel_alternate_row_style();
//             $sheet->getStyle("A$row:J$row")->applyFromArray(array_merge($dataStyle, $alternateStyle));
//         } else {
//             $sheet->getStyle("A$row:J$row")->applyFromArray($dataStyle);
//         }
//         $row++;
//     }
    
//     sc_auto_size_columns($sheet, 10);
    
//     $filters = [
//         'status' => $filter_status,
//         'date_from' => $filter_date_from,
//         'date_to' => $filter_date_to
//     ];
//     $filename = sc_generate_export_filename('event_registrations', $filters);
    
//     if (ob_get_level()) {
//         ob_end_clean();
//     }
    
//     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
//     header('Content-Disposition: attachment;filename="' . $filename . '"');
//     header('Cache-Control: max-age=0');
//     header('Pragma: public');
    
//     $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
//     $writer->save('php://output');
//     exit;
// }

