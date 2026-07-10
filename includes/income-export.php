<?php
/**
 * Export Incomes to Excel + finance helpers for manual incomes
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ensure incomes tables exist.
 */
function sc_finance_ensure_incomes_tables() {
    global $wpdb;
    $incomes = $wpdb->prefix . 'sc_incomes';
    $cats = $wpdb->prefix . 'sc_income_categories';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $incomes)) !== $incomes) {
        if (function_exists('sc_create_incomes_table')) {
            sc_create_incomes_table();
        }
    }
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $cats)) !== $cats) {
        if (function_exists('sc_create_income_categories_table')) {
            sc_create_income_categories_table();
        }
    }
}

/**
 * Sum of manually registered club incomes in date range.
 *
 * @param string $date_from Y-m-d
 * @param string $date_to   Y-m-d
 * @param string $chapter   Optional chapter filter
 * @return float
 */
function sc_finance_sum_manual_incomes($date_from, $date_to, $chapter = '') {
    global $wpdb;
    sc_finance_ensure_incomes_tables();
    $table = $wpdb->prefix . 'sc_incomes';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return 0.0;
    }

    $where = ['mi.income_date_gregorian IS NOT NULL'];
    $args = [];
    if ($date_from !== '') {
        $where[] = 'DATE(mi.income_date_gregorian) >= %s';
        $args[] = $date_from;
    }
    if ($date_to !== '') {
        $where[] = 'DATE(mi.income_date_gregorian) <= %s';
        $args[] = $date_to;
    }
    if ($chapter !== '') {
        $where[] = 'mi.chapter = %s';
        $args[] = $chapter;
    }
    if (function_exists('sc_secretary_merge_income_where')) {
        sc_secretary_merge_income_where($where, $args, 'mi');
    }

    $sql = 'SELECT COALESCE(SUM(mi.amount), 0) FROM `' . $table . '` mi WHERE ' . implode(' AND ', $where);
    if (!empty($args)) {
        return (float) $wpdb->get_var($wpdb->prepare($sql, $args));
    }
    return (float) $wpdb->get_var($sql);
}

/**
 * Ledger rows for manually registered incomes (tx_type = income).
 *
 * @param string $date_from
 * @param string $date_to
 * @param string $chapter
 * @return object[]
 */
function sc_finance_manual_income_ledger_rows($date_from, $date_to, $chapter = '') {
    global $wpdb;
    sc_finance_ensure_incomes_tables();
    $table = $wpdb->prefix . 'sc_incomes';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return [];
    }

    $where = ['mi.income_date_gregorian IS NOT NULL'];
    $args = [];
    if ($date_from !== '') {
        $where[] = 'DATE(mi.income_date_gregorian) >= %s';
        $args[] = $date_from;
    }
    if ($date_to !== '') {
        $where[] = 'DATE(mi.income_date_gregorian) <= %s';
        $args[] = $date_to;
    }
    if ($chapter !== '') {
        $where[] = 'mi.chapter = %s';
        $args[] = $chapter;
    }
    if (function_exists('sc_secretary_merge_income_where')) {
        sc_secretary_merge_income_where($where, $args, 'mi');
    }

    $sql = "SELECT DATE(mi.income_date_gregorian) AS tx_date,
                   'income' AS tx_type,
                   mi.amount,
                   '' AS person_name,
                   CONCAT('ثبت درآمد: ', mi.name) AS ref_title,
                   mi.chapter,
                   'manual_income' AS income_source
            FROM `{$table}` mi
            WHERE " . implode(' AND ', $where) . '
            ORDER BY mi.income_date_gregorian DESC, mi.id DESC';

    if (!empty($args)) {
        $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
    } else {
        $rows = $wpdb->get_results($sql);
    }
    return is_array($rows) ? $rows : [];
}

function sc_export_incomes_to_excel() {
    sc_check_phpspreadsheet();

    global $wpdb;
    $incomes_table = $wpdb->prefix . 'sc_incomes';
    $income_categories_table = $wpdb->prefix . 'sc_income_categories';

    $filter_category = isset($_GET['filter_category']) ? absint($_GET['filter_category']) : 0;
    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    $where_conditions = ['1=1'];
    $where_values = [];

    if ($filter_category > 0) {
        $where_conditions[] = 'i.category_id = %d';
        $where_values[] = $filter_category;
    }

    if ($filter_date_from) {
        $where_conditions[] = 'i.income_date_gregorian >= %s';
        $where_values[] = $filter_date_from;
    }

    if ($filter_date_to) {
        $where_conditions[] = 'i.income_date_gregorian <= %s';
        $where_values[] = $filter_date_to;
    }

    if ($search) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where_conditions[] = '(i.name LIKE %s OR i.description LIKE %s)';
        $where_values[] = $search_like;
        $where_values[] = $search_like;
    }

    if (function_exists('sc_secretary_merge_income_where')) {
        sc_secretary_merge_income_where($where_conditions, $where_values, 'i');
    }

    $where_clause = implode(' AND ', $where_conditions);

    $query = "SELECT i.*,
                     ic.name as category_name
              FROM $incomes_table i
              LEFT JOIN $income_categories_table ic ON i.category_id = ic.id
              WHERE $where_clause
              ORDER BY i.income_date_gregorian DESC, i.created_at DESC";

    if (!empty($where_values)) {
        $incomes = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $incomes = $wpdb->get_results($query);
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('درآمدها');
    $sheet->setRightToLeft(true);

    $headers = [
        'ردیف',
        'نام درآمد',
        'شعبه',
        'دسته‌بندی',
        'تاریخ (شمسی)',
        'مبلغ',
        'توضیحات',
        'تاریخ ثبت',
    ];

    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }

    $headerStyle = sc_get_excel_header_style();
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

    $row = 2;
    $row_number = 1;

    foreach ($incomes as $income) {
        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $row_number++);
        $sheet->setCellValueByColumnAndRow($col++, $row, $income->name);
        $sheet->setCellValueByColumnAndRow($col++, $row, $income->chapter ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $income->category_name ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, $income->income_date_shamsi);
        $sheet->setCellValueByColumnAndRow($col++, $row, number_format($income->amount, 0, '.', ',') . ' تومان');
        $sheet->setCellValueByColumnAndRow($col++, $row, $income->description ?: '-');
        $sheet->setCellValueByColumnAndRow($col++, $row, sc_date_shamsi($income->created_at, 'Y/m/d H:i'));

        $dataStyle = sc_get_excel_data_style();
        if ($row % 2 == 0) {
            $alternateStyle = sc_get_excel_alternate_row_style();
            $sheet->getStyle("A$row:H$row")->applyFromArray(array_merge($dataStyle, $alternateStyle));
        } else {
            $sheet->getStyle("A$row:H$row")->applyFromArray($dataStyle);
        }

        $row++;
    }

    sc_auto_size_columns($sheet, 8);

    $filters = [
        'date_from' => $filter_date_from,
        'date_to' => $filter_date_to,
    ];
    $filename = sc_generate_export_filename('incomes', $filters);

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
