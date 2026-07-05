<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalize course_ids[] from POST/AJAX (supports course_id|chapter|group).
 *
 * @param mixed $raw
 * @return string[]
 */
function sc_audience_normalize_course_ids_from_request($raw) {
    if ($raw === null || $raw === '') {
        return [];
    }
    if (!is_array($raw)) {
        $raw = [$raw];
    }

    $out = [];
    foreach ($raw as $value) {
        $value = sanitize_text_field(wp_unslash((string) $value));
        if ($value === '') {
            continue;
        }
        if (strpos($value, '|') !== false) {
            $parts = function_exists('sc_attendance_course_selection_parts')
                ? sc_attendance_course_selection_parts($value)
                : ['course_id' => absint($value), 'chapter_name' => '', 'group_name' => ''];
            if ((int) $parts['course_id'] > 0) {
                $out[] = function_exists('sc_attendance_course_option_value')
                    ? sc_attendance_course_option_value((int) $parts['course_id'], (string) $parts['chapter_name'], (string) $parts['group_name'])
                    : (string) (int) $parts['course_id'];
            }
            continue;
        }
        $course_id = absint($value);
        if ($course_id > 0) {
            $out[] = (string) $course_id;
        }
    }

    return array_values(array_unique($out));
}

/**
 * @param string[] $raw_values
 * @return array<int,array{course_id:int,chapter_name:string,group_name:string}>
 */
function sc_audience_parse_course_selections(array $raw_values) {
    $selections = [];
    foreach ($raw_values as $raw) {
        $parts = function_exists('sc_attendance_course_selection_parts')
            ? sc_attendance_course_selection_parts((string) $raw)
            : ['course_id' => absint($raw), 'chapter_name' => '', 'group_name' => ''];
        if ((int) $parts['course_id'] > 0) {
            $selections[] = $parts;
        }
    }

    return $selections;
}

/**
 * @param string[]|array<int,array{course_id:int,chapter_name:string,group_name:string}> $raw_or_parsed
 * @return int[]
 */
function sc_audience_extract_course_ids($raw_or_parsed) {
    $parsed = is_array($raw_or_parsed) && isset($raw_or_parsed[0]['course_id'])
        ? $raw_or_parsed
        : sc_audience_parse_course_selections((array) $raw_or_parsed);

    $ids = [];
    foreach ($parsed as $item) {
        $cid = isset($item['course_id']) ? (int) $item['course_id'] : 0;
        if ($cid > 0) {
            $ids[] = $cid;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Build OR conditions for member_courses audience filtering.
 *
 * @param string[] $raw_values
 * @param string   $alias
 * @return array{sql:string,args:array<int,mixed>}
 */
function sc_audience_member_course_filter_sql(array $raw_values, $alias = 'mc') {
    $selections = sc_audience_parse_course_selections($raw_values);
    if (empty($selections)) {
        return ['sql' => ' AND 1=0', 'args' => []];
    }

    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
    if ($alias === '') {
        $alias = 'mc';
    }

    $or_parts = [];
    $args = [];
    foreach ($selections as $sel) {
        $course_id = (int) $sel['course_id'];
        $chapter = sanitize_text_field((string) $sel['chapter_name']);
        $group = sanitize_text_field((string) $sel['group_name']);

        $cond = "{$alias}.course_id = %d
            AND {$alias}.status = 'active'
            AND ({$alias}.course_status_flags IS NULL OR TRIM({$alias}.course_status_flags) = '')";
        $cond_args = [$course_id];

        if ($chapter !== '') {
            $cond .= " AND {$alias}.chapter = %s";
            $cond_args[] = $chapter;
        }
        if ($group !== '') {
            $cond .= " AND {$alias}.group_name = %s";
            $cond_args[] = $group;
        }

        $or_parts[] = '(' . $cond . ')';
        $args = array_merge($args, $cond_args);
    }

    return [
        'sql' => ' AND (' . implode(' OR ', $or_parts) . ')',
        'args' => $args,
    ];
}

/**
 * Subquery: member_id IN (SELECT ... FROM member_courses WHERE selections match).
 *
 * @param string[] $raw_values
 * @return array{sql:string,args:array<int,mixed>}
 */
function sc_audience_member_ids_by_course_subquery_sql(array $raw_values) {
    global $wpdb;
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $filter = sc_audience_member_course_filter_sql($raw_values, 'mc');

    return [
        'sql' => "m.id IN (
            SELECT DISTINCT mc.member_id
            FROM {$member_courses_table} mc
            WHERE 1=1{$filter['sql']}
        )",
        'args' => $filter['args'],
    ];
}

/**
 * Active courses for audience pickers.
 *
 * @param int $coach_id 0 = all
 * @return array<int,object>
 */
function sc_audience_get_courses_for_picker($coach_id = 0) {
    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    $coach_id = absint($coach_id);

    if ($coach_id > 0) {
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT c.id, c.title, c.course_type, c.chapter AS chapter_name
             FROM {$courses_table} c
             INNER JOIN {$course_coaches_table} cc ON cc.course_id = c.id
             WHERE c.deleted_at IS NULL AND c.is_active = 1 AND cc.coach_id = %d
             ORDER BY c.title ASC",
            $coach_id
        ));
    }

    return $wpdb->get_results(
        "SELECT id, title, course_type, chapter AS chapter_name
         FROM {$courses_table}
         WHERE deleted_at IS NULL AND is_active = 1
         ORDER BY title ASC"
    );
}

/**
 * Render multi-select course picker with chapter/group labels.
 *
 * @param array<int,object> $courses
 * @param string[]          $selected_raw
 * @param array<string,mixed> $args id, name, class, description, placeholder
 */
function sc_render_audience_course_picker(array $courses, array $selected_raw = [], array $args = []) {
    $picker_id = isset($args['id']) ? sanitize_html_class((string) $args['id']) : 'sc-audience-course-picker';
    $input_name = isset($args['name']) ? (string) $args['name'] : 'course_ids[]';
    $wrapper_class = isset($args['class']) ? (string) $args['class'] : '';
    $description = isset($args['description']) ? (string) $args['description'] : 'جستجو کنید و دوره‌ها را اضافه کنید. برای دوره‌های دارای گروه، هر گروه جداگانه نمایش داده می‌شود.';
    $placeholder = isset($args['placeholder']) ? (string) $args['placeholder'] : 'جستجو و افزودن دوره...';
    $store_course_id_only = !empty($args['store_course_id_only']);

    $options = function_exists('sc_attendance_build_course_dropdown_options')
        ? sc_attendance_build_course_dropdown_options($courses)
        : [];

    $selected_map = [];
    foreach (sc_audience_normalize_course_ids_from_request($selected_raw) as $sel_value) {
        $selected_map[$sel_value] = true;
    }

    $selected_items = [];
    foreach ($options as $opt) {
        $value = (string) ($opt['value'] ?? '');
        if ($value === '' || empty($selected_map[$value])) {
            continue;
        }
        $store_value = $store_course_id_only ? (string) (int) ($opt['course_id'] ?? absint($value)) : $value;
        $selected_items[] = [
            'value' => $store_value,
            'label' => (string) ($opt['label'] ?? $value),
            'picker_value' => $value,
        ];
        unset($selected_map[$value]);
    }

    foreach (array_keys($selected_map) as $leftover) {
        $parts = function_exists('sc_attendance_course_selection_parts')
            ? sc_attendance_course_selection_parts($leftover)
            : ['course_id' => absint($leftover), 'chapter_name' => '', 'group_name' => ''];
        if ((int) $parts['course_id'] <= 0) {
            continue;
        }
        $label = '#' . (int) $parts['course_id'];
        foreach ($courses as $course) {
            if ((int) $course->id === (int) $parts['course_id']) {
                $label = function_exists('sc_attendance_course_option_label')
                    ? sc_attendance_course_option_label((string) $course->title, (string) $parts['chapter_name'], (string) $parts['group_name'])
                    : (string) $course->title;
                break;
            }
        }
        $store_value = $store_course_id_only ? (string) (int) $parts['course_id'] : $leftover;
        $selected_items[] = [
            'value' => $store_value,
            'label' => $label,
            'picker_value' => $leftover,
        ];
    }

    $partial = defined('SC_TEMPLATES_ADMIN_DIR')
        ? SC_TEMPLATES_ADMIN_DIR . 'partials/audience-course-picker.php'
        : dirname(__DIR__) . '/templates/admin/partials/audience-course-picker.php';

    if (!is_readable($partial)) {
        return '';
    }

    ob_start();
    include $partial;
    return ob_get_clean();
}

/**
 * Active member user_ids for course audience selections.
 *
 * @param string[] $raw_values
 * @return int[]
 */
function sc_audience_get_active_member_user_ids_by_course_selections(array $raw_values) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $course_values = sc_audience_normalize_course_ids_from_request($raw_values);
    if (empty($course_values)) {
        return [];
    }

    $filter = sc_audience_member_course_filter_sql($course_values, 'mc');
    $sql = "SELECT DISTINCT m.user_id
            FROM {$member_courses_table} mc
            INNER JOIN {$members_table} m ON m.id = mc.member_id
            WHERE m.user_id IS NOT NULL{$filter['sql']}";
    $user_ids = !empty($filter['args'])
        ? $wpdb->get_col($wpdb->prepare($sql, $filter['args']))
        : $wpdb->get_col($sql);

    return array_values(array_filter(array_map('intval', (array) $user_ids)));
}

/**
 * Debtor member user_ids optionally filtered by course selections (with group).
 *
 * @param string[] $raw_values
 * @return int[]
 */
function sc_audience_get_debtor_user_ids_by_course_selections(array $raw_values = []) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $members_table = $wpdb->prefix . 'sc_members';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';

    $course_values = sc_audience_normalize_course_ids_from_request($raw_values);
    if (empty($course_values)) {
        $user_ids = $wpdb->get_col(
            "SELECT DISTINCT m.user_id
             FROM {$invoices_table} i
             INNER JOIN {$members_table} m ON i.member_id = m.id
             WHERE i.status = 'pending' AND m.user_id IS NOT NULL AND m.is_active = 1"
        );
        return array_values(array_filter(array_map('intval', (array) $user_ids)));
    }

    $filter = sc_audience_member_course_filter_sql($course_values, 'mc');
    $sql = "SELECT DISTINCT m.user_id
            FROM {$invoices_table} i
            INNER JOIN {$members_table} m ON i.member_id = m.id
            INNER JOIN {$member_courses_table} mc ON mc.member_id = m.id AND mc.course_id = i.course_id
            WHERE i.status = 'pending'
              AND m.user_id IS NOT NULL
              AND m.is_active = 1{$filter['sql']}";
    $user_ids = !empty($filter['args'])
        ? $wpdb->get_col($wpdb->prepare($sql, $filter['args']))
        : $wpdb->get_col($sql);

    return array_values(array_filter(array_map('intval', (array) $user_ids)));
}
