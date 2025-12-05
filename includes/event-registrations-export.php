<?php
/**
 * Export Event Registrations to Excel
 */
function sc_export_event_registrations_to_excel() {
    sc_check_phpspreadsheet();
    
    ob_end_clean();
    
    global $wpdb;
    $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';
    $events_table = $wpdb->prefix . 'sc_events';
    $members_table = $wpdb->prefix . 'sc_members';
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    
    // دریافت فیلترها
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    $filter_event = isset($_GET['filter_event']) ? absint($_GET['filter_event']) : 0;
    $filter_event_type = isset($_GET['filter_event_type']) ? sanitize_text_field($_GET['filter_event_type']) : 'all';
    $filter_order = isset($_GET['filter_order']) ? sanitize_text_field($_GET['filter_order']) : '';
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
    
    // ساخت WHERE clause
    $where_conditions = [];
    $where_values = [];
    
    if ($filter_member > 0) {
        $where_conditions[] = "r.member_id = %d";
        $where_values[] = $filter_member;
    }
    
    if ($filter_event > 0) {
        $where_conditions[] = "r.event_id = %d";
        $where_values[] = $filter_event;
    }
    
    if ($filter_event_type !== 'all') {
        $where_conditions[] = "e.event_type = %s";
        $where_values[] = $filter_event_type;
    }
    
    if (!empty($filter_order)) {
        $filter_order = str_replace('#', '', $filter_order);
        $filter_order = absint($filter_order);
        if ($filter_order > 0) {
            $where_conditions[] = "(i.woocommerce_order_id = %d OR i.id = %d)";
            $where_values[] = $filter_order;
            $where_values[] = $filter_order;
        }
    }
    
    if ($filter_status !== 'all') {
        $where_conditions[] = "i.status = %s";
        $where_values[] = $filter_status;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Query برای دریافت داده‌ها
    $query = "SELECT r.*, 
                     e.name as event_name,
                     e.event_type as event_type,
                     e.holding_date_shamsi as event_holding_date,
                     m.first_name, 
                     m.last_name, 
                     m.player_phone,
                     m.national_id,
                     i.status,
                     i.woocommerce_order_id,
                     i.id as invoice_id,
                     i.amount as invoice_amount
              FROM $event_registrations_table r
              LEFT JOIN $events_table e ON r.event_id = e.id AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
              LEFT JOIN $members_table m ON r.member_id = m.id
              LEFT JOIN $invoices_table i ON r.invoice_id = i.id
              $where_clause
              ORDER BY r.created_at DESC";
    
    if (!empty($where_values)) {
        $registrations = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $registrations = $wpdb->get_results($query);
    }
    
    // آماده‌سازی داده‌ها برای export
    $export_data = [];
    $row_number = 1;
    
    foreach ($registrations as $registration) {
        $event_type_label = ($registration->event_type === 'competition') ? 'مسابقه' : 'رویداد';
        $event_name_display = $registration->event_name;
        if ($registration->event_holding_date) {
            $event_name_display .= ' (' . $registration->event_holding_date . ')';
        }
        
        $member_name = trim($registration->first_name . ' ' . $registration->last_name);
        if (empty($member_name)) {
            $member_name = '-';
        }
        
        $order_number = '-';
        if ($registration->woocommerce_order_id) {
            $order_number = '#' . $registration->woocommerce_order_id;
        } elseif ($registration->invoice_id) {
            $order_number = '#' . $registration->invoice_id;
        }
        
        $status_labels = [
            'pending' => 'در انتظار پرداخت',
            'processing' => 'در حال پردازش',
            'completed' => 'تایید پرداخت',
            'on-hold' => 'در حال بررسی',
            'cancelled' => 'لغو شده',
            'refunded' => 'بازگشت شده',
            'failed' => 'ناموفق'
        ];
        
        $status_label = isset($status_labels[$registration->status]) ? $status_labels[$registration->status] : $registration->status;
        
        $created_date = '-';
        if ($registration->created_at && $registration->created_at !== '0000-00-00 00:00:00') {
            $created_timestamp = strtotime($registration->created_at);
            if ($created_timestamp) {
                $created_date = sc_date_shamsi_date_only(date('Y-m-d', $created_timestamp));
            }
        }
        
        $export_data[] = [
            'row_number' => $row_number++,
            'order_number' => $order_number,
            'event_name' => $event_name_display,
            'event_type' => $event_type_label,
            'member_name' => $member_name,
            'national_id' => $registration->national_id ?: '-',
            'phone' => $registration->player_phone ?: '-',
            'status' => $status_label,
            'amount' => $registration->invoice_amount ? number_format(intval($registration->invoice_amount), 0, '.', ',') . ' تومان' : '-',
            'registration_date' => $created_date
        ];
    }
    
    // ایجاد Excel
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ثبت‌نامی‌های رویداد');
    
    // تنظیم جهت راست به چپ
    $sheet->setRightToLeft(true);
    
    // Header
    $headers = [
        'ردیف',
        'شماره سفارش',
        'نام رویداد / مسابقه',
        'نوع',
        'نام کاربر',
        'کد ملی',
        'شماره تماس',
        'وضعیت',
        'مبلغ',
        'تاریخ ثبت‌نام'
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
    foreach ($export_data as $data) {
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['row_number']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['order_number']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['event_name']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['event_type']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['member_name']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['national_id']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['phone']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['status']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['amount']);
        $sheet->setCellValueByColumnAndRow($col++, $row, $data['registration_date']);
        $row++;
    }
    
    // تنظیم عرض ستون‌ها
    sc_auto_size_columns($sheet, 10);
    
    // ایجاد فایل
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    // نام فایل
    $filename = 'event_registrations_' . date('Y-m-d_His') . '.xlsx';
    
    // ارسال headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // ارسال فایل
    $writer->save('php://output');
    exit;
}



