<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_metric_get_field_types() {
    return [
        'number' => 'عدد (قابل نمایش در نمودار)',
        'text'   => 'متن',
        'select' => 'انتخابی',
    ];
}

function sc_metric_decode_options($raw) {
    if (is_array($raw)) {
        return $raw;
    }
    if ($raw === null || $raw === '') {
        return [];
    }
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sc_metric_get_fields($active_only = false) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_metric_fields';
    $sql = "SELECT * FROM $table";
    if ($active_only) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return $wpdb->get_results($sql);
}

function sc_metric_get_field($field_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_metric_fields';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", absint($field_id)));
}

function sc_metric_save_fields($fields) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_metric_fields';
    $now = current_time('mysql');
    $existing_ids = $wpdb->get_col("SELECT id FROM $table");
    $keep_ids = [];
    $order = 0;

    foreach ((array) $fields as $field) {
        $order++;
        $field_id = isset($field['id']) ? absint($field['id']) : 0;
        $options = [];
        if (!empty($field['options']) && is_array($field['options'])) {
            foreach ($field['options'] as $opt) {
                $opt = trim((string) $opt);
                if ($opt !== '') {
                    $options[] = $opt;
                }
            }
        } elseif (!empty($field['options_text'])) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $field['options_text']);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $options[] = $line;
                }
            }
        }

        $row = [
            'title'        => isset($field['title']) ? sanitize_text_field($field['title']) : '',
            'field_type'   => isset($field['field_type']) ? sanitize_key($field['field_type']) : 'number',
            'unit'         => isset($field['unit']) ? sanitize_text_field($field['unit']) : '',
            'options_json' => wp_json_encode($options, JSON_UNESCAPED_UNICODE),
            'sort_order'   => $order,
            'is_active'    => !empty($field['is_active']) ? 1 : 0,
            'updated_at'   => $now,
        ];

        if (!array_key_exists($row['field_type'], sc_metric_get_field_types())) {
            $row['field_type'] = 'number';
        }

        if ($field_id && in_array((string) $field_id, array_map('strval', $existing_ids), true)) {
            $wpdb->update($table, $row, ['id' => $field_id]);
            $keep_ids[] = $field_id;
        } else {
            $row['created_at'] = $now;
            $wpdb->insert($table, $row);
            $keep_ids[] = (int) $wpdb->insert_id;
        }
    }

    foreach ($existing_ids as $existing_id) {
        if (!in_array((int) $existing_id, $keep_ids, true)) {
            sc_metric_delete_field(absint($existing_id));
        }
    }

    return ['success' => true];
}

function sc_metric_delete_field($field_id) {
    global $wpdb;
    $field_id = absint($field_id);
    if (!$field_id) {
        return false;
    }
    $entries_table = $wpdb->prefix . 'sc_member_metric_entries';
    $fields_table = $wpdb->prefix . 'sc_metric_fields';
    $wpdb->delete($entries_table, ['field_id' => $field_id], ['%d']);
    return (bool) $wpdb->delete($fields_table, ['id' => $field_id], ['%d']);
}

function sc_metric_field_is_chartable($field) {
    if (!$field) {
        return false;
    }
    return isset($field->field_type) && $field->field_type === 'number';
}

function sc_metric_save_entry($member_id, $field_id, $entry_date, $value) {
    global $wpdb;
    $member_id = absint($member_id);
    $field_id = absint($field_id);
    $entry_date = sanitize_text_field($entry_date);

    if (!$member_id || !$field_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry_date)) {
        return ['success' => false, 'message' => 'اطلاعات ورودی نامعتبر است.'];
    }

    $field = sc_metric_get_field($field_id);
    if (!$field || !(int) $field->is_active) {
        return ['success' => false, 'message' => 'فیلد انتخاب‌شده معتبر نیست.'];
    }

    $value_numeric = null;
    $value_text = null;

    if ($field->field_type === 'number') {
        if ($value === '' || $value === null) {
            return ['success' => false, 'message' => 'لطفاً مقدار عددی را وارد کنید.'];
        }
        $value_numeric = floatval(str_replace(',', '', (string) $value));
    } elseif ($field->field_type === 'select') {
        $value_text = sanitize_text_field((string) $value);
        $options = sc_metric_decode_options($field->options_json);
        if ($value_text === '' || (!empty($options) && !in_array($value_text, $options, true))) {
            return ['success' => false, 'message' => 'لطفاً یکی از گزینه‌ها را انتخاب کنید.'];
        }
    } else {
        $value_text = sanitize_text_field((string) $value);
        if ($value_text === '') {
            return ['success' => false, 'message' => 'لطفاً مقدار را وارد کنید.'];
        }
    }

    $table = $wpdb->prefix . 'sc_member_metric_entries';
    $now = current_time('mysql');
    $existing_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE member_id = %d AND field_id = %d AND entry_date = %s",
        $member_id,
        $field_id,
        $entry_date
    ));

    $row = [
        'member_id'     => $member_id,
        'field_id'      => $field_id,
        'entry_date'    => $entry_date,
        'value_numeric' => $value_numeric,
        'value_text'    => $value_text,
        'updated_at'    => $now,
    ];

    if ($existing_id) {
        $wpdb->update($table, $row, ['id' => $existing_id]);
    } else {
        $row['created_at'] = $now;
        $wpdb->insert($table, $row);
    }

    return ['success' => true, 'message' => 'اطلاعات با موفقیت ثبت شد.'];
}

function sc_metric_get_member_entries($member_id, $args = []) {
    global $wpdb;
    $member_id = absint($member_id);
    if (!$member_id) {
        return [];
    }

    $entries_table = $wpdb->prefix . 'sc_member_metric_entries';
    $fields_table = $wpdb->prefix . 'sc_metric_fields';

    $where = ['e.member_id = %d'];
    $params = [$member_id];

    if (!empty($args['field_id'])) {
        $where[] = 'e.field_id = %d';
        $params[] = absint($args['field_id']);
    } elseif (!empty($args['field_ids'])) {
        $field_ids = array_values(array_filter(array_map('absint', (array) $args['field_ids'])));
        if (!empty($field_ids)) {
            $where[] = 'e.field_id IN (' . implode(', ', array_fill(0, count($field_ids), '%d')) . ')';
            $params = array_merge($params, $field_ids);
        }
    }
    if (!empty($args['date_from'])) {
        $where[] = 'e.entry_date >= %s';
        $params[] = sanitize_text_field($args['date_from']);
    }
    if (!empty($args['date_to'])) {
        $where[] = 'e.entry_date <= %s';
        $params[] = sanitize_text_field($args['date_to']);
    }

    $sql = "SELECT e.*, f.title AS field_title, f.field_type, f.unit
            FROM $entries_table e
            INNER JOIN $fields_table f ON f.id = e.field_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.entry_date ASC, f.sort_order ASC, f.id ASC";

    return $wpdb->get_results($wpdb->prepare($sql, ...$params));
}

function sc_metric_get_chart_data($member_id, $field_ids, $date_from, $date_to) {
    $member_id = absint($member_id);
    $field_ids = array_values(array_filter(array_map('absint', (array) $field_ids)));
    if (!$member_id || empty($field_ids)) {
        return ['labels' => [], 'datasets' => []];
    }

    $entries = sc_metric_get_member_entries($member_id, [
        'date_from' => $date_from,
        'date_to'   => $date_to,
    ]);

    $fields = sc_metric_get_fields(true);
    $fields_by_id = [];
    foreach ($fields as $field) {
        $fields_by_id[(int) $field->id] = $field;
    }

    $dates = [];
    $series = [];
    foreach ($field_ids as $field_id) {
        if (!isset($fields_by_id[$field_id]) || !sc_metric_field_is_chartable($fields_by_id[$field_id])) {
            continue;
        }
        $series[$field_id] = [];
    }

    foreach ($entries as $entry) {
        $field_id = (int) $entry->field_id;
        if (!isset($series[$field_id])) {
            continue;
        }
        $dates[$entry->entry_date] = true;
        $series[$field_id][$entry->entry_date] = $entry->value_numeric !== null ? (float) $entry->value_numeric : null;
    }

    $sorted_dates = array_keys($dates);
    sort($sorted_dates);

    $labels = [];
    foreach ($sorted_dates as $date) {
        $labels[] = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($date) : $date;
    }

    $colors = [
        ['border' => 'rgb(102, 126, 234)', 'bg' => 'rgba(102, 126, 234, 0.1)'],
        ['border' => 'rgb(34, 197, 94)', 'bg' => 'rgba(34, 197, 94, 0.1)'],
        ['border' => 'rgb(249, 115, 22)', 'bg' => 'rgba(249, 115, 22, 0.1)'],
        ['border' => 'rgb(236, 72, 153)', 'bg' => 'rgba(236, 72, 153, 0.1)'],
        ['border' => 'rgb(14, 165, 233)', 'bg' => 'rgba(14, 165, 233, 0.1)'],
    ];

    $datasets = [];
    $color_index = 0;
    foreach ($series as $field_id => $values_by_date) {
        $field = $fields_by_id[$field_id];
        $color = $colors[$color_index % count($colors)];
        $color_index++;

        $data = [];
        foreach ($sorted_dates as $date) {
            $data[] = array_key_exists($date, $values_by_date) ? $values_by_date[$date] : null;
        }

        $label = $field->title;
        if (!empty($field->unit)) {
            $label .= ' (' . $field->unit . ')';
        }

        $datasets[] = [
            'label'           => $label,
            'data'            => $data,
            'borderColor'     => $color['border'],
            'backgroundColor' => $color['bg'],
            'tension'         => 0.4,
            'fill'            => false,
            'spanGaps'        => true,
        ];
    }

    return [
        'labels'   => $labels,
        'datasets' => $datasets,
    ];
}

function sc_metric_parse_date_filters_from_request() {
    $today_shamsi = function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '';
    $default_from_shamsi = function_exists('sc_shamsi_add_days') ? sc_shamsi_add_days($today_shamsi, -30) : $today_shamsi;

    $from_shamsi = isset($_REQUEST['filter_date_from_shamsi'])
        ? sanitize_text_field(wp_unslash($_REQUEST['filter_date_from_shamsi']))
        : $default_from_shamsi;
    $to_shamsi = isset($_REQUEST['filter_date_to_shamsi'])
        ? sanitize_text_field(wp_unslash($_REQUEST['filter_date_to_shamsi']))
        : $today_shamsi;

    $from = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($from_shamsi) : '';
    $to = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($to_shamsi) : '';

    if ($from && $to && $from > $to) {
        $tmp = $from;
        $from = $to;
        $to = $tmp;
        $tmp_s = $from_shamsi;
        $from_shamsi = $to_shamsi;
        $to_shamsi = $tmp_s;
    }

    return [
        'from_shamsi' => $from_shamsi,
        'to_shamsi'   => $to_shamsi,
        'from'        => $from,
        'to'          => $to,
    ];
}

function sc_metric_fields_for_js($fields) {
    $out = [];
    foreach ((array) $fields as $field) {
        $out[] = [
            'id'         => (int) $field->id,
            'title'      => $field->title,
            'field_type' => $field->field_type,
            'unit'       => $field->unit,
            'options'    => sc_metric_decode_options($field->options_json),
            'chartable'  => sc_metric_field_is_chartable($field),
        ];
    }
    return $out;
}

/**
 * Format a metric entry value for display/export.
 */
function sc_metric_format_entry_value($entry) {
    if (!$entry) {
        return '';
    }
    if (isset($entry->field_type) && $entry->field_type === 'number' && $entry->value_numeric !== null) {
        $formatted = rtrim(rtrim(number_format((float) $entry->value_numeric, 4, '.', ''), '0'), '.');
        if (!empty($entry->unit)) {
            $formatted .= ' ' . $entry->unit;
        }
        return $formatted;
    }
    return isset($entry->value_text) ? (string) $entry->value_text : '';
}

/**
 * Admin: list metric entries across members with filters.
 *
 * @param array $args {
 *     @type int   $member_id
 *     @type array $field_ids
 *     @type string $date_from Y-m-d
 *     @type string $date_to   Y-m-d
 *     @type int   $limit
 *     @type int   $offset
 *     @type bool  $count_only
 * }
 */
function sc_metric_get_admin_entries($args = []) {
    global $wpdb;

    $entries_table = $wpdb->prefix . 'sc_member_metric_entries';
    $fields_table  = $wpdb->prefix . 'sc_metric_fields';
    $members_table = $wpdb->prefix . 'sc_members';

    $where  = ['1=1'];
    $params = [];

    if (!empty($args['member_id'])) {
        $where[]  = 'e.member_id = %d';
        $params[] = absint($args['member_id']);
    }
    if (!empty($args['field_ids'])) {
        $field_ids = array_values(array_filter(array_map('absint', (array) $args['field_ids'])));
        if (!empty($field_ids)) {
            $where[] = 'e.field_id IN (' . implode(', ', array_fill(0, count($field_ids), '%d')) . ')';
            $params  = array_merge($params, $field_ids);
        }
    } elseif (!empty($args['field_id'])) {
        $where[]  = 'e.field_id = %d';
        $params[] = absint($args['field_id']);
    }
    if (!empty($args['date_from'])) {
        $where[]  = 'e.entry_date >= %s';
        $params[] = sanitize_text_field($args['date_from']);
    }
    if (!empty($args['date_to'])) {
        $where[]  = 'e.entry_date <= %s';
        $params[] = sanitize_text_field($args['date_to']);
    }

    $where_sql = implode(' AND ', $where);

    if (!empty($args['count_only'])) {
        $sql = "SELECT COUNT(*)
                FROM $entries_table e
                INNER JOIN $fields_table f ON f.id = e.field_id
                INNER JOIN $members_table m ON m.id = e.member_id
                WHERE $where_sql";
        if (!empty($params)) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
        }
        return (int) $wpdb->get_var($sql);
    }

    $sql = "SELECT e.*,
                   f.title AS field_title,
                   f.field_type,
                   f.unit,
                   m.first_name,
                   m.last_name,
                   m.national_id,
                   m.player_phone,
                   m.personal_photo
            FROM $entries_table e
            INNER JOIN $fields_table f ON f.id = e.field_id
            INNER JOIN $members_table m ON m.id = e.member_id
            WHERE $where_sql
            ORDER BY e.entry_date DESC, e.id DESC";

    if (isset($args['limit'])) {
        $limit  = max(1, absint($args['limit']));
        $offset = isset($args['offset']) ? max(0, absint($args['offset'])) : 0;
        $sql   .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, $offset);
    }

    if (!empty($params)) {
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
    return $wpdb->get_results($sql);
}

/**
 * Admin summary stats for filtered metric entries.
 */
function sc_metric_get_admin_stats($args = []) {
    global $wpdb;

    $entries_table = $wpdb->prefix . 'sc_member_metric_entries';
    $fields_table  = $wpdb->prefix . 'sc_metric_fields';
    $members_table = $wpdb->prefix . 'sc_members';

    $where  = ['1=1'];
    $params = [];

    if (!empty($args['member_id'])) {
        $where[]  = 'e.member_id = %d';
        $params[] = absint($args['member_id']);
    }
    if (!empty($args['field_ids'])) {
        $field_ids = array_values(array_filter(array_map('absint', (array) $args['field_ids'])));
        if (!empty($field_ids)) {
            $where[] = 'e.field_id IN (' . implode(', ', array_fill(0, count($field_ids), '%d')) . ')';
            $params  = array_merge($params, $field_ids);
        }
    }
    if (!empty($args['date_from'])) {
        $where[]  = 'e.entry_date >= %s';
        $params[] = sanitize_text_field($args['date_from']);
    }
    if (!empty($args['date_to'])) {
        $where[]  = 'e.entry_date <= %s';
        $params[] = sanitize_text_field($args['date_to']);
    }

    $where_sql = implode(' AND ', $where);
    $sql = "SELECT
                COUNT(*) AS total_entries,
                COUNT(DISTINCT e.member_id) AS unique_members,
                COUNT(DISTINCT e.entry_date) AS unique_dates,
                COUNT(DISTINCT e.field_id) AS unique_fields
            FROM $entries_table e
            INNER JOIN $fields_table f ON f.id = e.field_id
            INNER JOIN $members_table m ON m.id = e.member_id
            WHERE $where_sql";

    if (!empty($params)) {
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$params));
    } else {
        $row = $wpdb->get_row($sql);
    }

    return [
        'total_entries'  => $row ? (int) $row->total_entries : 0,
        'unique_members' => $row ? (int) $row->unique_members : 0,
        'unique_dates'   => $row ? (int) $row->unique_dates : 0,
        'unique_fields'  => $row ? (int) $row->unique_fields : 0,
    ];
}

/**
 * Admin chart payloads: daily activity + per-field counts + optional member trend.
 */
function sc_metric_get_admin_chart_payloads($args = []) {
    global $wpdb;

    $entries_table = $wpdb->prefix . 'sc_member_metric_entries';
    $fields_table  = $wpdb->prefix . 'sc_metric_fields';
    $members_table = $wpdb->prefix . 'sc_members';

    $where  = ['1=1'];
    $params = [];

    if (!empty($args['member_id'])) {
        $where[]  = 'e.member_id = %d';
        $params[] = absint($args['member_id']);
    }
    if (!empty($args['field_ids'])) {
        $field_ids = array_values(array_filter(array_map('absint', (array) $args['field_ids'])));
        if (!empty($field_ids)) {
            $where[] = 'e.field_id IN (' . implode(', ', array_fill(0, count($field_ids), '%d')) . ')';
            $params  = array_merge($params, $field_ids);
        }
    }
    if (!empty($args['date_from'])) {
        $where[]  = 'e.entry_date >= %s';
        $params[] = sanitize_text_field($args['date_from']);
    }
    if (!empty($args['date_to'])) {
        $where[]  = 'e.entry_date <= %s';
        $params[] = sanitize_text_field($args['date_to']);
    }

    $where_sql = implode(' AND ', $where);

    $daily_sql = "SELECT e.entry_date, COUNT(*) AS cnt
                  FROM $entries_table e
                  INNER JOIN $fields_table f ON f.id = e.field_id
                  INNER JOIN $members_table m ON m.id = e.member_id
                  WHERE $where_sql
                  GROUP BY e.entry_date
                  ORDER BY e.entry_date ASC";

    $field_sql = "SELECT f.id, f.title, f.unit, COUNT(*) AS cnt
                  FROM $entries_table e
                  INNER JOIN $fields_table f ON f.id = e.field_id
                  INNER JOIN $members_table m ON m.id = e.member_id
                  WHERE $where_sql
                  GROUP BY f.id, f.title, f.unit
                  ORDER BY cnt DESC, f.sort_order ASC";

    if (!empty($params)) {
        $daily_rows = $wpdb->get_results($wpdb->prepare($daily_sql, ...$params));
        $field_rows = $wpdb->get_results($wpdb->prepare($field_sql, ...$params));
    } else {
        $daily_rows = $wpdb->get_results($daily_sql);
        $field_rows = $wpdb->get_results($field_sql);
    }

    $daily_labels = [];
    $daily_counts = [];
    foreach ((array) $daily_rows as $row) {
        $daily_labels[] = function_exists('sc_date_shamsi_date_only')
            ? sc_date_shamsi_date_only($row->entry_date)
            : $row->entry_date;
        $daily_counts[] = (int) $row->cnt;
    }

    $field_labels = [];
    $field_counts = [];
    foreach ((array) $field_rows as $row) {
        $label = $row->title;
        if (!empty($row->unit)) {
            $label .= ' (' . $row->unit . ')';
        }
        $field_labels[] = $label;
        $field_counts[] = (int) $row->cnt;
    }

    $member_trend = ['labels' => [], 'datasets' => []];
    if (!empty($args['member_id']) && !empty($args['chart_field_ids'])) {
        $member_trend = sc_metric_get_chart_data(
            absint($args['member_id']),
            $args['chart_field_ids'],
            isset($args['date_from']) ? $args['date_from'] : '',
            isset($args['date_to']) ? $args['date_to'] : ''
        );
    }

    return [
        'daily_activity' => [
            'labels' => $daily_labels,
            'counts' => $daily_counts,
        ],
        'by_field' => [
            'labels' => $field_labels,
            'counts' => $field_counts,
        ],
        'member_trend' => $member_trend,
    ];
}

/**
 * Export daily metrics report to Excel.
 */
function sc_export_daily_metrics_to_excel() {
    if (!function_exists('sc_check_phpspreadsheet')) {
        wp_die('خروجی اکسل در دسترس نیست.');
    }
    sc_check_phpspreadsheet();

    $date_filters = sc_metric_parse_date_filters_from_request();
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    $field_ids = [];
    if (isset($_GET['chart_field_ids'])) {
        $field_ids = array_values(array_filter(array_map('absint', (array) wp_unslash($_GET['chart_field_ids']))));
    }

    $query_args = [
        'date_from' => $date_filters['from'],
        'date_to'   => $date_filters['to'],
    ];
    if ($filter_member > 0) {
        $query_args['member_id'] = $filter_member;
    }
    if (!empty($field_ids)) {
        $query_args['field_ids'] = $field_ids;
    }

    $entries = sc_metric_get_admin_entries($query_args);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft(true);
    $sheet->setTitle('ثبت اطلاعات');

    $headers = ['ردیف', 'تاریخ', 'نام و نام خانوادگی', 'کد ملی', 'شماره تماس', 'فیلد', 'مقدار'];
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col++, 1, $header);
    }
    if (function_exists('sc_get_excel_header_style')) {
        $sheet->getStyle('A1:G1')->applyFromArray(sc_get_excel_header_style());
    }

    $row = 2;
    $index = 1;
    foreach ($entries as $entry) {
        $full_name = trim(($entry->first_name ?? '') . ' ' . ($entry->last_name ?? ''));
        $date_label = function_exists('sc_date_shamsi_date_only')
            ? sc_date_shamsi_date_only($entry->entry_date)
            : $entry->entry_date;

        $col = 1;
        $sheet->setCellValueByColumnAndRow($col++, $row, $index++);
        $sheet->setCellValueByColumnAndRow($col++, $row, $date_label);
        $sheet->setCellValueByColumnAndRow($col++, $row, $full_name);
        $sheet->setCellValueByColumnAndRow($col++, $row, $entry->national_id ?? '');
        $sheet->setCellValueByColumnAndRow($col++, $row, $entry->player_phone ?? '');
        $sheet->setCellValueByColumnAndRow($col++, $row, $entry->field_title ?? '');
        $sheet->setCellValueByColumnAndRow($col++, $row, sc_metric_format_entry_value($entry));

        if (function_exists('sc_get_excel_data_style')) {
            $data_style = sc_get_excel_data_style();
            if ($row % 2 === 0 && function_exists('sc_get_excel_alternate_row_style')) {
                $data_style = array_merge($data_style, sc_get_excel_alternate_row_style());
            }
            $sheet->getStyle("A$row:G$row")->applyFromArray($data_style);
        }
        $row++;
    }

    if (function_exists('sc_auto_size_columns')) {
        sc_auto_size_columns($sheet, 7);
    }

    $filename = 'daily_metrics_' . date('Ymd_His') . '.xlsx';
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
