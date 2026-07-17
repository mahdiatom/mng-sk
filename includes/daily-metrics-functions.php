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
