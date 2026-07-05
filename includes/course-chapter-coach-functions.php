<?php
/**
 * Course chapters, coach assignments per branch, and enrollment helpers.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return string[]
 */
function sc_get_course_chapters($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id) {
        return [];
    }

    $table = $wpdb->prefix . 'sc_course_chapters';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT chapter_name FROM `$table` WHERE course_id = %d ORDER BY chapter_name ASC",
            $course_id
        ));
        if (!empty($rows)) {
            return array_values(array_filter(array_map(static function ($n) {
                return trim((string) $n);
            }, $rows)));
        }
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    $legacy = $wpdb->get_var($wpdb->prepare(
        "SELECT chapter FROM `$courses_table` WHERE id = %d",
        $course_id
    ));
    if ($legacy !== null && trim((string) $legacy) !== '') {
        return [trim((string) $legacy)];
    }

    return [];
}

/**
 * @param string[] $chapter_names
 */
function sc_save_course_chapters($course_id, array $chapter_names) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id) {
        return;
    }

    $table = $wpdb->prefix . 'sc_course_chapters';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return;
    }

    $clean = [];
    foreach ($chapter_names as $name) {
        $name = sanitize_text_field((string) $name);
        if ($name !== '') {
            $clean[$name] = $name;
        }
    }
    $clean = array_values($clean);

    $wpdb->delete($table, ['course_id' => $course_id], ['%d']);

    $now = current_time('mysql');
    foreach ($clean as $name) {
        $wpdb->insert(
            $table,
            [
                'course_id' => $course_id,
                'chapter_name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%s']
        );
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    $primary_chapter = !empty($clean) ? $clean[0] : null;
    $wpdb->update(
        $courses_table,
        [
            'chapter' => $primary_chapter,
            'updated_at' => $now,
        ],
        ['id' => $course_id],
        ['%s', '%s'],
        ['%d']
    );
}

/**
 * Active coaches for a course at a specific branch.
 *
 * @return array<int, array{id:int,label:string}>
 */
function sc_get_course_chapter_coaches($course_id, $chapter_name) {
    global $wpdb;
    $course_id = absint($course_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    if (!$course_id || $chapter_name === '') {
        return [];
    }

    $cc = $wpdb->prefix . 'sc_course_coaches';
    $coaches = $wpdb->prefix . 'sc_coaches';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT cc.coach_id, c.first_name, c.last_name
         FROM `$cc` cc
         INNER JOIN `$coaches` c ON c.id = cc.coach_id AND c.is_active = 1
         WHERE cc.course_id = %d AND cc.chapter_name = %s
         ORDER BY c.last_name ASC, c.first_name ASC, cc.coach_id ASC",
        $course_id,
        $chapter_name
    ));

    $out = [];
    foreach ((array) $rows as $row) {
        $coach_id = (int) $row->coach_id;
        $label = trim((string) $row->first_name . ' ' . (string) $row->last_name);
        if ($label === '') {
            $label = 'مربی #' . $coach_id;
        }
        $out[] = [
            'id' => $coach_id,
            'label' => $label,
        ];
    }

    return $out;
}

/**
 * Course-level enrollment capacity (from sc_courses.capacity).
 *
 * @return array{show:bool,remaining:?int,total_capacity:?int,is_full:bool,unlimited:bool}
 */
function sc_get_course_enrollment_capacity_info($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    $info = [
        'show' => false,
        'remaining' => null,
        'total_capacity' => null,
        'is_full' => false,
        'unlimited' => false,
        'granular' => false,
    ];

    if (!$course_id) {
        $info['is_full'] = true;
        return $info;
    }

    if (function_exists('sc_course_uses_granular_capacity') && sc_course_uses_granular_capacity($course_id)) {
        $info['granular'] = true;
        $summary = function_exists('sc_get_course_granular_enrollment_capacity_summary')
            ? sc_get_course_granular_enrollment_capacity_summary($course_id)
            : ['show' => false, 'unlimited' => true, 'is_full' => false, 'remaining' => null, 'total_capacity' => null];
        return array_merge($info, $summary);
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT capacity FROM `$courses_table` WHERE id = %d",
        $course_id
    ));
    if (!$course || empty($course->capacity) || (int) $course->capacity <= 0) {
        $info['unlimited'] = true;
        $info['is_full'] = false;
        return $info;
    }

    $used = function_exists('sc_count_course_capacity_slots_used')
        ? sc_count_course_capacity_slots_used($course_id)
        : 0;
    $remaining = max(0, (int) $course->capacity - (int) $used);
    $info['show'] = true;
    $info['remaining'] = $remaining;
    $info['total_capacity'] = (int) $course->capacity;
    $info['is_full'] = ($remaining <= 0);
    return $info;
}

function sc_course_has_any_enrollment_slot($course_id) {
    $info = sc_get_course_enrollment_capacity_info($course_id);
    return !$info['is_full'];
}

/**
 * Validate coach is assigned to course+branch.
 */
function sc_is_valid_course_chapter_coach($course_id, $chapter_name, $coach_id) {
    global $wpdb;
    $course_id = absint($course_id);
    $coach_id = absint($coach_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);

    if (!$course_id || !$coach_id || $chapter_name === '') {
        return false;
    }

    $cc = $wpdb->prefix . 'sc_course_coaches';
    $coaches = $wpdb->prefix . 'sc_coaches';

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM `$cc` cc
         INNER JOIN `$coaches` c ON c.id = cc.coach_id AND c.is_active = 1
         WHERE cc.course_id = %d AND cc.coach_id = %d AND cc.chapter_name = %s",
        $course_id,
        $coach_id,
        $chapter_name
    )) > 0;
}

/**
 * Resolve chapter + coach for a member course row.
 *
 * @return array{chapter:string,coach_id:int}
 */
function sc_resolve_member_course_assignment($course_id, $selected_chapter = '', $selected_coach_id = 0, $existing_chapter = '', $existing_coach_id = 0) {
    $course_id = absint($course_id);
    $selected_coach_id = absint($selected_coach_id);
    $existing_coach_id = absint($existing_coach_id);
    $selected_chapter = sanitize_text_field((string) $selected_chapter);
    $existing_chapter = sanitize_text_field((string) $existing_chapter);

    $chapters = sc_get_course_chapters($course_id);
    $chapter = '';

    if (count($chapters) === 1) {
        $chapter = $chapters[0];
    } elseif ($selected_chapter !== '' && in_array($selected_chapter, $chapters, true)) {
        $chapter = $selected_chapter;
    } elseif ($existing_chapter !== '' && in_array($existing_chapter, $chapters, true)) {
        $chapter = $existing_chapter;
    } elseif (count($chapters) === 0) {
        $chapter = $selected_chapter !== '' ? $selected_chapter : $existing_chapter;
    }

    $coach_id = 0;

    if ($selected_coach_id > 0 && $chapter !== '' && sc_is_valid_course_chapter_coach($course_id, $chapter, $selected_coach_id)) {
        $coach_id = $selected_coach_id;
    } elseif (
        $existing_coach_id > 0
        && $chapter !== ''
        && sc_is_valid_course_chapter_coach($course_id, $chapter, $existing_coach_id)
    ) {
        $coach_id = $existing_coach_id;
    } elseif ($chapter !== '') {
        $coaches = sc_get_course_chapter_coaches($course_id, $chapter);
        if (count($coaches) === 1) {
            $coach_id = (int) $coaches[0]['id'];
        }
    }

    return [
        'chapter' => $chapter,
        'coach_id' => $coach_id,
    ];
}

/**
 * Build enrollment UI config for a course (chapters + coaches).
 *
 * @return array<string,mixed>
 */
function sc_get_course_enrollment_branch_config($course_id) {
    $course_id = absint($course_id);
    $chapters = sc_get_course_chapters($course_id);
    $chapter_items = [];

    foreach ($chapters as $chapter_name) {
        $coaches = sc_get_course_chapter_coaches($course_id, $chapter_name);
        $chapter_items[] = [
            'name' => $chapter_name,
            'coaches' => $coaches,
            'coach_count' => count($coaches),
        ];
    }

    // برنامه هفتگی دوره (با شعبه/مربی هر ردیف) برای نمایش هنگام ثبت‌نام
    $schedule_items = [];
    if (function_exists('sc_get_course_weekly_schedule_rows')) {
        $wd_labels = function_exists('sc_course_weekday_labels_ir') ? sc_course_weekday_labels_ir() : [];
        foreach (sc_get_course_weekly_schedule_rows($course_id) as $srow) {
            $wd = isset($srow->weekday) ? (int) $srow->weekday : 0;
            $schedule_items[] = [
                'weekday' => $wd,
                'day' => isset($wd_labels[$wd]) ? $wd_labels[$wd] : '',
                'start' => substr((string) $srow->time_start, 0, 5),
                'end' => substr((string) $srow->time_end, 0, 5),
                'chapter' => isset($srow->chapter_name) ? (string) $srow->chapter_name : '',
                'coach_id' => isset($srow->coach_id) ? (int) $srow->coach_id : 0,
                'uses_group' => !empty($srow->schedule_uses_group) ? 1 : 0,
                'group_name' => (!empty($srow->schedule_uses_group) && !empty($srow->group_name)) ? (string) $srow->group_name : '',
            ];
        }
    }

    return [
        'chapters' => $chapter_items,
        'chapter_count' => count($chapter_items),
        'requires_chapter_choice' => count($chapter_items) > 1,
        'schedule' => $schedule_items,
        'groups' => function_exists('sc_get_course_groups_config') ? sc_get_course_groups_config($course_id) : ['has_grouping' => false, 'groups' => [], 'group_count' => 0],
        'granular_capacity' => function_exists('sc_course_uses_granular_capacity') && sc_course_uses_granular_capacity($course_id),
        'capacity_slots' => function_exists('sc_get_course_granular_capacity_slots_with_usage')
            ? sc_get_course_granular_capacity_slots_with_usage($course_id)
            : [],
    ];
}

/**
 * @param array<int, array{course_id:int,chapter_name:string,salary_percentage:float}> $assignments
 */
function sc_save_coach_course_assignments($coach_id, array $assignments) {
    global $wpdb;
    $coach_id = absint($coach_id);
    if (!$coach_id) {
        return;
    }

    $table = $wpdb->prefix . 'sc_course_coaches';
    $wpdb->delete($table, ['coach_id' => $coach_id], ['%d']);

    $now = current_time('mysql');
    foreach ($assignments as $row) {
        $course_id = isset($row['course_id']) ? absint($row['course_id']) : 0;
        $chapter_name = isset($row['chapter_name']) ? sanitize_text_field((string) $row['chapter_name']) : '';
        if (!$course_id || $chapter_name === '') {
            continue;
        }
        $salary = isset($row['salary_percentage']) ? (float) $row['salary_percentage'] : 0.0;
        if ($salary < 0) {
            $salary = 0;
        }
        if ($salary > 100) {
            $salary = 100;
        }

        $wpdb->insert(
            $table,
            [
                'course_id' => $course_id,
                'coach_id' => $coach_id,
                'chapter_name' => $chapter_name,
                'capacity' => null,
                'salary_percentage' => $salary,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%s', '%s', '%f', '%s', '%s']
        );
    }
}

/**
 * @return array{price_per_session:float,capacity:?int,salary_percentage:float}|null
 */
function sc_get_course_coach_branch_meta($course_id, $chapter_name, $coach_id) {
    global $wpdb;
    $course_id = absint($course_id);
    $coach_id = absint($coach_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    if (!$course_id || !$coach_id || $chapter_name === '') {
        return null;
    }

    $table = $wpdb->prefix . 'sc_course_coaches';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT price_per_session, capacity, salary_percentage FROM `$table`
         WHERE course_id = %d AND coach_id = %d AND chapter_name = %s LIMIT 1",
        $course_id,
        $coach_id,
        $chapter_name
    ));
    if (!$row) {
        return null;
    }

    return [
        'price_per_session' => isset($row->price_per_session) ? (float) $row->price_per_session : 0.0,
        'capacity' => ($row->capacity !== null && $row->capacity !== '') ? (int) $row->capacity : null,
        'salary_percentage' => isset($row->salary_percentage) ? (float) $row->salary_percentage : 0.0,
    ];
}

/**
 * @return array<string, array<int, array{price_per_session:float,capacity:?int,salary_percentage:float}>>
 */
function sc_get_course_coach_branch_meta_map($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id) {
        return [];
    }

    $table = $wpdb->prefix . 'sc_course_coaches';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT coach_id, chapter_name, price_per_session, capacity, salary_percentage FROM `$table` WHERE course_id = %d",
        $course_id
    ));

    $map = [];
    foreach ((array) $rows as $row) {
        $map[(string) $row->chapter_name][(int) $row->coach_id] = [
            'price_per_session' => isset($row->price_per_session) ? (float) $row->price_per_session : 0.0,
            'capacity' => ($row->capacity !== null && $row->capacity !== '') ? (int) $row->capacity : null,
            'salary_percentage' => isset($row->salary_percentage) ? (float) $row->salary_percentage : 0.0,
        ];
    }

    return $map;
}

/**
 * Existing coach assignments of a course: [chapter_name][coach_id] => salary_percentage.
 *
 * @return array<string, array<int, float>>
 */
function sc_get_course_coach_assignments_map($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id) {
        return [];
    }

    $table = $wpdb->prefix . 'sc_course_coaches';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT coach_id, chapter_name, salary_percentage FROM `$table` WHERE course_id = %d",
        $course_id
    ));

    $map = [];
    foreach ((array) $rows as $row) {
        $map[(string) $row->chapter_name][(int) $row->coach_id] = (float) $row->salary_percentage;
    }

    return $map;
}

/**
 * Save coach assignments from the course form (course_coach_assign[chapter][coach_id]).
 * Deletes all rows of this course and re-inserts the selection, preserving salary percentages.
 * Coach-side form stays in sync automatically because both read/write sc_course_coaches.
 *
 * @param string[] $allowed_chapters chapters selected for the course
 */
function sc_save_course_coach_assignments_from_post($course_id, array $allowed_chapters) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id) {
        return;
    }

    // فقط وقتی فرم دوره بخش «مربی‌های هر شعبه» را نمایش داده، تخصیص‌ها را بازنویسی کن
    if (empty($_POST['course_coach_assign_present'])) {
        return;
    }

    $table = $wpdb->prefix . 'sc_course_coaches';
    $salary_map = sc_get_course_coach_assignments_map($course_id);
    $branch_meta_map = sc_get_course_coach_branch_meta_map($course_id);
    $branch_prices = isset($_POST['coach_branch_price_raw']) && is_array($_POST['coach_branch_price_raw'])
        ? wp_unslash($_POST['coach_branch_price_raw'])
        : [];
    $branch_capacities = isset($_POST['coach_branch_capacity']) && is_array($_POST['coach_branch_capacity'])
        ? wp_unslash($_POST['coach_branch_capacity'])
        : [];

    $raw = isset($_POST['course_coach_assign']) && is_array($_POST['course_coach_assign'])
        ? wp_unslash($_POST['course_coach_assign'])
        : [];

    $wpdb->delete($table, ['course_id' => $course_id], ['%d']);

    $now = current_time('mysql');
    foreach ($raw as $chapter_name => $coach_ids) {
        $chapter_name = sanitize_text_field((string) $chapter_name);
        if ($chapter_name === '' || !is_array($coach_ids) || !in_array($chapter_name, $allowed_chapters, true)) {
            continue;
        }
        foreach ($coach_ids as $coach_id => $enabled) {
            if ((string) $enabled !== '1') {
                continue;
            }
            $coach_id = absint($coach_id);
            if (!$coach_id) {
                continue;
            }
            $salary = isset($salary_map[$chapter_name][$coach_id]) ? (float) $salary_map[$chapter_name][$coach_id] : 0.0;

            $price_per_session = 0.0;
            $posted_price = null;
            if (isset($branch_prices[$chapter_name][$coach_id])) {
                $posted_price = $branch_prices[$chapter_name][$coach_id];
            } elseif (isset($branch_prices[$chapter_name][(string) $coach_id])) {
                $posted_price = $branch_prices[$chapter_name][(string) $coach_id];
            }
            if ($posted_price !== null && $posted_price !== '') {
                $price_raw = preg_replace('/[^\d.]/', '', str_replace(',', '', sanitize_text_field((string) $posted_price)));
                $price_per_session = (float) $price_raw;
            } elseif (isset($branch_meta_map[$chapter_name][$coach_id]['price_per_session'])) {
                $price_per_session = (float) $branch_meta_map[$chapter_name][$coach_id]['price_per_session'];
            }

            $capacity = null;
            $posted_capacity = null;
            if (isset($branch_capacities[$chapter_name][$coach_id])) {
                $posted_capacity = $branch_capacities[$chapter_name][$coach_id];
            } elseif (isset($branch_capacities[$chapter_name][(string) $coach_id])) {
                $posted_capacity = $branch_capacities[$chapter_name][(string) $coach_id];
            }
            if ($posted_capacity !== null && $posted_capacity !== '') {
                $capacity = max(1, absint($posted_capacity));
            } elseif (isset($branch_meta_map[$chapter_name][$coach_id]['capacity']) && $branch_meta_map[$chapter_name][$coach_id]['capacity'] !== null) {
                $capacity = max(1, (int) $branch_meta_map[$chapter_name][$coach_id]['capacity']);
            }

            if (function_exists('sc_private_cap_branch_capacity_value')) {
                $capacity = sc_private_cap_branch_capacity_value($course_id, $capacity);
            }

            $row_data = [
                'course_id' => $course_id,
                'coach_id' => $coach_id,
                'chapter_name' => $chapter_name,
                'capacity' => $capacity,
                'price_per_session' => $price_per_session,
                'salary_percentage' => $salary,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $row_format = [
                '%d',
                '%d',
                '%s',
                ($capacity !== null ? '%d' : '%s'),
                '%f',
                '%f',
                '%s',
                '%s',
            ];

            $wpdb->insert($table, $row_data, $row_format);
        }
    }
}

/**
 * Parse coach course assignments from POST (coach_course_assign[course_id][chapter]).
 *
 * @return array<int, array{course_id:int,chapter_name:string,salary_percentage:float}>
 */
function sc_parse_coach_course_assignments_from_post() {
    $raw = isset($_POST['coach_course_assign']) && is_array($_POST['coach_course_assign'])
        ? $_POST['coach_course_assign']
        : [];
    $percentages = isset($_POST['coach_course_percentage']) && is_array($_POST['coach_course_percentage'])
        ? $_POST['coach_course_percentage']
        : [];

    $assignments = [];
    foreach ($raw as $course_id => $chapters) {
        $course_id = absint($course_id);
        if (!$course_id || !is_array($chapters)) {
            continue;
        }
        foreach ($chapters as $chapter_name => $enabled) {
            if ((string) $enabled !== '1') {
                continue;
            }
            $chapter_name = sanitize_text_field((string) $chapter_name);
            if ($chapter_name === '') {
                continue;
            }
            $pct = isset($percentages[$course_id][$chapter_name]) ? (float) $percentages[$course_id][$chapter_name] : 0.0;

            $assignments[] = [
                'course_id' => $course_id,
                'chapter_name' => $chapter_name,
                'salary_percentage' => $pct,
            ];
        }
    }

    return $assignments;
}

/**
 * Map for bulk actions: course_id => chapters => coaches.
 *
 * @return array<string, mixed>
 */
function sc_get_bulk_course_branch_coaches_map() {
    global $wpdb;
    $cc = $wpdb->prefix . 'sc_course_coaches';
    $coaches = $wpdb->prefix . 'sc_coaches';

    $rows = $wpdb->get_results(
        "SELECT cc.course_id, cc.chapter_name, cc.coach_id, c.first_name, c.last_name
         FROM `$cc` cc
         INNER JOIN `$coaches` c ON c.id = cc.coach_id AND c.is_active = 1
         ORDER BY cc.course_id ASC, cc.chapter_name ASC, c.last_name ASC, c.first_name ASC"
    );

    $map = [];
    foreach ((array) $rows as $row) {
        $cid = (int) $row->course_id;
        $chapter = (string) $row->chapter_name;
        if (!isset($map[$cid])) {
            $map[$cid] = [
                'chapters' => sc_get_course_chapters($cid),
                'coaches' => [],
                'groups' => function_exists('sc_get_bulk_course_groups_map_entry')
                    ? sc_get_bulk_course_groups_map_entry($cid)
                    : [],
                'has_grouping' => function_exists('sc_course_has_grouping_enabled') && sc_course_has_grouping_enabled($cid),
            ];
        }
        $label = trim((string) $row->first_name . ' ' . (string) $row->last_name);
        if ($label === '') {
            $label = 'مربی #' . (int) $row->coach_id;
        }
        if (!isset($map[$cid]['coaches'][$chapter])) {
            $map[$cid]['coaches'][$chapter] = [];
        }
        $map[$cid]['coaches'][$chapter][] = [
            'id' => (int) $row->coach_id,
            'label' => $label,
        ];
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    $all_course_ids = $wpdb->get_col(
        "SELECT id FROM `$courses_table` WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'"
    );
    foreach ((array) $all_course_ids as $cid) {
        $cid = (int) $cid;
        if ($cid <= 0 || isset($map[$cid])) {
            continue;
        }
        $chapters = sc_get_course_chapters($cid);
        if (!empty($chapters)) {
            $map[$cid] = [
                'chapters' => $chapters,
                'coaches' => [],
                'groups' => function_exists('sc_get_bulk_course_groups_map_entry')
                    ? sc_get_bulk_course_groups_map_entry($cid)
                    : [],
                'has_grouping' => function_exists('sc_course_has_grouping_enabled') && sc_course_has_grouping_enabled($cid),
            ];
        }
    }

    foreach ($map as $cid => $entry) {
        if (!isset($entry['groups'])) {
            $map[$cid]['groups'] = function_exists('sc_get_bulk_course_groups_map_entry')
                ? sc_get_bulk_course_groups_map_entry((int) $cid)
                : [];
        }
        if (!isset($entry['has_grouping'])) {
            $map[$cid]['has_grouping'] = function_exists('sc_course_has_grouping_enabled') && sc_course_has_grouping_enabled((int) $cid);
        }
    }

    return $map;
}

/**
 * Validate branch/coach selections for bulk course activation.
 *
 * @param int[] $course_ids
 * @return string[] error lines (empty = ok)
 */
function sc_validate_bulk_course_activate_assignments(array $course_ids) {
    global $wpdb;
    $errors = [];
    $courses_table = $wpdb->prefix . 'sc_courses';

    $chapters_post = isset($_POST['course_chapter']) && is_array($_POST['course_chapter'])
        ? wp_unslash($_POST['course_chapter'])
        : [];
    $coaches_post = isset($_POST['course_coach']) && is_array($_POST['course_coach'])
        ? wp_unslash($_POST['course_coach'])
        : [];

    foreach ($course_ids as $course_id) {
        $course_id = absint($course_id);
        if (!$course_id) {
            continue;
        }

        $title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$courses_table} WHERE id = %d", $course_id));
        $label = $title ? (string) $title : ('دوره #' . $course_id);

        $chapters = function_exists('sc_get_course_chapters') ? sc_get_course_chapters($course_id) : [];
        $sel_chapter = isset($chapters_post[$course_id]) ? sanitize_text_field((string) $chapters_post[$course_id]) : '';
        $sel_coach = isset($coaches_post[$course_id]) ? absint($coaches_post[$course_id]) : 0;

        if (count($chapters) > 1) {
            if ($sel_chapter === '' || !in_array($sel_chapter, $chapters, true)) {
                $errors[] = 'برای دوره «' . $label . '» انتخاب شعبه الزامی است.';
                continue;
            }
        } elseif (count($chapters) === 1) {
            $sel_chapter = $chapters[0];
        }

        if ($sel_chapter === '') {
            continue;
        }

        $coaches = function_exists('sc_get_course_chapter_coaches')
            ? sc_get_course_chapter_coaches($course_id, $sel_chapter)
            : [];

        if (count($coaches) > 1) {
            if ($sel_coach <= 0 || !function_exists('sc_is_valid_course_chapter_coach') || !sc_is_valid_course_chapter_coach($course_id, $sel_chapter, $sel_coach)) {
                $errors[] = 'برای دوره «' . $label . '» در شعبه «' . $sel_chapter . '» انتخاب مربی الزامی است.';
            }
        } elseif (count($coaches) === 1) {
            if ($sel_coach > 0 && function_exists('sc_is_valid_course_chapter_coach') && !sc_is_valid_course_chapter_coach($course_id, $sel_chapter, $sel_coach)) {
                $errors[] = 'مربی انتخاب‌شده برای دوره «' . $label . '» در شعبه «' . $sel_chapter . '» معتبر نیست.';
            }
        } elseif ($sel_coach > 0 && function_exists('sc_is_valid_course_chapter_coach') && !sc_is_valid_course_chapter_coach($course_id, $sel_chapter, $sel_coach)) {
            $errors[] = 'مربی انتخاب‌شده برای دوره «' . $label . '» در شعبه «' . $sel_chapter . '» معتبر نیست.';
        }
    }

    return $errors;
}

function sc_parse_member_course_assignments_from_post() {
    $chapters = isset($_POST['course_chapter']) && is_array($_POST['course_chapter']) ? $_POST['course_chapter'] : [];
    $coaches = isset($_POST['course_coach']) && is_array($_POST['course_coach']) ? $_POST['course_coach'] : [];
    $groups = isset($_POST['course_group']) && is_array($_POST['course_group']) ? $_POST['course_group'] : [];
    $out = [];

    foreach ($chapters as $course_id => $chapter_name) {
        $course_id = absint($course_id);
        if (!$course_id) {
            continue;
        }
        $out[$course_id] = [
            'chapter' => sanitize_text_field((string) $chapter_name),
            'coach_id' => isset($coaches[$course_id]) ? absint($coaches[$course_id]) : 0,
            'group_name' => isset($groups[$course_id]) ? sanitize_text_field((string) $groups[$course_id]) : '',
        ];
    }
    foreach ($coaches as $course_id => $coach_id) {
        $course_id = absint($course_id);
        if (!$course_id) {
            continue;
        }
        if (!isset($out[$course_id])) {
            $out[$course_id] = [
                'chapter' => '',
                'coach_id' => absint($coach_id),
                'group_name' => isset($groups[$course_id]) ? sanitize_text_field((string) $groups[$course_id]) : '',
            ];
        } else {
            $out[$course_id]['coach_id'] = absint($coach_id);
        }
    }
    foreach ($groups as $course_id => $group_name) {
        $course_id = absint($course_id);
        if (!$course_id) {
            continue;
        }
        if (!isset($out[$course_id])) {
            $out[$course_id] = [
                'chapter' => '',
                'coach_id' => 0,
                'group_name' => sanitize_text_field((string) $group_name),
            ];
        } else {
            $out[$course_id]['group_name'] = sanitize_text_field((string) $group_name);
        }
    }

    return $out;
}

/**
 * Parse course selection value (course_id|chapter_name|group_name).
 *
 * @return array{course_id:int,chapter_name:string,group_name:string}
 */
function sc_attendance_course_selection_parts($raw) {
    $raw = sanitize_text_field((string) $raw);
    if ($raw === '') {
        return ['course_id' => 0, 'chapter_name' => '', 'group_name' => ''];
    }

    $parts = explode('|', $raw, 3);
    $course_id = absint($parts[0] ?? 0);
    $chapter_name = isset($parts[1]) ? sanitize_text_field((string) $parts[1]) : '';
    $group_name = isset($parts[2]) ? sanitize_text_field((string) $parts[2]) : '';

    if (count($parts) === 1) {
        return ['course_id' => $course_id, 'chapter_name' => '', 'group_name' => ''];
    }
    if (count($parts) === 2 && strpos($raw, '|') !== false) {
        return ['course_id' => $course_id, 'chapter_name' => $chapter_name, 'group_name' => ''];
    }

    return [
        'course_id' => $course_id,
        'chapter_name' => $chapter_name,
        'group_name' => $group_name,
    ];
}

/**
 * Read course/chapter/group from request using separate params (WAF-safe) or legacy pipe format.
 *
 * @param array<string,mixed>|null $source
 * @return array{course_id:int,chapter_name:string,group_name:string}
 */
function sc_attendance_get_selection_from_request($source = null) {
    $src = is_array($source) ? $source : $_REQUEST;

    $course_id = isset($src['attendance_course_id']) ? absint($src['attendance_course_id']) : 0;
    $chapter_name = isset($src['attendance_chapter'])
        ? sanitize_text_field(wp_unslash((string) $src['attendance_chapter']))
        : '';
    $group_name = isset($src['attendance_group'])
        ? sanitize_text_field(wp_unslash((string) $src['attendance_group']))
        : '';

    if ($course_id > 0) {
        return [
            'course_id' => $course_id,
            'chapter_name' => $chapter_name,
            'group_name' => $group_name,
        ];
    }

    $raw = '';
    if (isset($src['course_id']) && $src['course_id'] !== '') {
        $raw = wp_unslash((string) $src['course_id']);
    }

    return sc_attendance_course_selection_parts($raw);
}

/**
 * Admin URL for attendance-add without pipe-delimited course_id.
 *
 * @param array<string,mixed> $extra
 */
function sc_attendance_add_page_url($course_id, $date = '', $chapter_name = '', $group_name = '', $extra = []) {
    $args = array_merge(['page' => 'sc-attendance-add'], is_array($extra) ? $extra : []);

    $course_id = absint($course_id);
    if ($course_id > 0) {
        $args['attendance_course_id'] = $course_id;
        $chapter_name = sanitize_text_field((string) $chapter_name);
        $group_name = sanitize_text_field((string) $group_name);
        if ($chapter_name !== '') {
            $args['attendance_chapter'] = $chapter_name;
        }
        if ($group_name !== '') {
            $args['attendance_group'] = $group_name;
        }
    }

    if ($date !== '') {
        $args['date'] = sanitize_text_field((string) $date);
        if (function_exists('sc_date_shamsi_date_only')) {
            $shamsi = sc_date_shamsi_date_only($date);
            if ($shamsi !== '') {
                $args['date_shamsi'] = $shamsi;
            }
        }
    }

    return add_query_arg($args, admin_url('admin.php'));
}

function sc_attendance_course_option_value($course_id, $chapter_name = '', $group_name = '') {
    $course_id = absint($course_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $group_name = sanitize_text_field((string) $group_name);
    if ($chapter_name === '' && $group_name === '') {
        return (string) $course_id;
    }

    return $course_id . '|' . $chapter_name . '|' . $group_name;
}

function sc_attendance_course_option_label($title, $chapter_name = '', $group_name = '') {
    $title = (string) $title;
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $group_name = sanitize_text_field((string) $group_name);
    $label = $title;
    if ($chapter_name !== '') {
        $label .= ' — ' . $chapter_name;
    }
    if ($group_name !== '') {
        $label .= ' — گروه: ' . $group_name;
    }

    return $label;
}

/**
 * SQL WHERE fragment for attendance member list by selected group.
 *
 * @param string $selected_group_name
 * @param int    $course_id
 * @return array{sql:string,args:array<int,mixed>}
 */
function sc_attendance_member_group_filter_sql($selected_group_name, $course_id = 0) {
    $selected_group_name = sanitize_text_field((string) $selected_group_name);
    $course_id = absint($course_id);

    $has_grouping = $course_id > 0
        && function_exists('sc_course_has_grouping_enabled')
        && sc_course_has_grouping_enabled($course_id);

    if ($has_grouping) {
        if ($selected_group_name === '') {
            return ['sql' => ' AND 1=0', 'args' => []];
        }

        return [
            'sql' => ' AND mc.group_name = %s',
            'args' => [$selected_group_name],
        ];
    }

    if ($selected_group_name !== '') {
        return [
            'sql' => ' AND mc.group_name = %s',
            'args' => [$selected_group_name],
        ];
    }

    return ['sql' => '', 'args' => []];
}

/**
 * @param int $course_id
 * @return int
 */
function sc_attendance_count_members_without_group($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !function_exists('sc_course_has_grouping_enabled') || !sc_course_has_grouping_enabled($course_id)) {
        return 0;
    }

    $mc = $wpdb->prefix . 'sc_member_courses';

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `$mc` mc
         WHERE mc.course_id = %d
           AND mc.status = 'active'
           AND (mc.course_status_flags IS NULL OR mc.course_status_flags = ''
                OR (mc.course_status_flags NOT LIKE %s AND mc.course_status_flags NOT LIKE %s AND mc.course_status_flags NOT LIKE %s))
           AND COALESCE(mc.group_name, '') = ''",
        $course_id,
        '%paused%',
        '%completed%',
        '%canceled%'
    ));
}

/**
 * Build flat course options for attendance dropdowns.
 *
 * @param array<int,object> $courses
 * @return array<int,array{value:string,label:string,course_type:string,search:string,course_id:int}>
 */
function sc_attendance_build_course_dropdown_options($courses) {
    $options = [];

    foreach ((array) $courses as $course) {
        $course_id = isset($course->id) ? (int) $course->id : 0;
        if (!$course_id) {
            continue;
        }
        $title = isset($course->title) ? (string) $course->title : ('#' . $course_id);
        $course_type = function_exists('sc_normalize_attendance_course_type')
            ? sc_normalize_attendance_course_type($course->course_type ?? 'group')
            : 'group';
        $chapter_from_row = isset($course->chapter_name) ? (string) $course->chapter_name : '';

        if (function_exists('sc_course_has_grouping_enabled') && sc_course_has_grouping_enabled($course_id)) {
            $groups = function_exists('sc_get_course_groups') ? sc_get_course_groups($course_id) : [];
            if (empty($groups)) {
                $value = sc_attendance_course_option_value($course_id, $chapter_from_row, '');
                $label = sc_attendance_course_option_label($title, $chapter_from_row, '');
                $options[] = [
                    'value' => $value,
                    'label' => $label,
                    'course_type' => $course_type,
                    'search' => strtolower($label . ' ' . $course_id),
                    'course_id' => $course_id,
                ];
                continue;
            }
            foreach ($groups as $grow) {
                $gitem = function_exists('sc_format_course_group_item') ? sc_format_course_group_item($grow) : ['name' => (string) $grow->group_name, 'chapter_name' => '', 'coach_id' => 0];
                $chapter = $gitem['chapter_name'] !== '' ? $gitem['chapter_name'] : $chapter_from_row;
                $value = sc_attendance_course_option_value($course_id, $chapter, $gitem['name']);
                $label = sc_attendance_course_option_label($title, $chapter, $gitem['name']);
                $options[] = [
                    'value' => $value,
                    'label' => $label,
                    'course_type' => $course_type,
                    'search' => strtolower($label . ' ' . $course_id . ' ' . $gitem['name']),
                    'course_id' => $course_id,
                ];
            }
            continue;
        }

        $value = sc_attendance_course_option_value($course_id, $chapter_from_row, '');
        $label = sc_attendance_course_option_label($title, $chapter_from_row, '');
        $options[] = [
            'value' => $value,
            'label' => $label,
            'course_type' => $course_type,
            'search' => strtolower($label . ' ' . $course_id),
            'course_id' => $course_id,
        ];
    }

    return $options;
}

/**
 * نرمال‌سازی نوع دوره برای حضور و غیاب.
 */
function sc_normalize_attendance_course_type($course_type) {
    return (isset($course_type) && $course_type === 'private') ? 'private' : 'group';
}

/**
 * برچسب فارسی نوع دوره.
 */
function sc_attendance_course_type_label($course_type) {
    return sc_normalize_attendance_course_type($course_type) === 'private'
        ? 'خصوصی / نیمه‌خصوصی'
        : 'گروهی';
}

/**
 * Dropdown جستجوی دوره برای فیلترهای حضور و غیاب.
 *
 * @param array<int,object> $courses
 * @param int               $selected_course_id
 * @param array<string,mixed> $args
 */
function sc_render_searchable_course_filter_dropdown($courses, $selected_course_id = 0, $args = []) {
    $selected_course_id = absint($selected_course_id);
    $input_name = isset($args['name']) ? (string) $args['name'] : 'filter_course';
    $input_id = isset($args['id']) ? (string) $args['id'] : 'filter_course';
    $all_label = isset($args['all_label']) ? (string) $args['all_label'] : 'همه دوره‌ها';
    $placeholder = isset($args['placeholder']) ? (string) $args['placeholder'] : $all_label;
    $wrapper_class = isset($args['wrapper_class']) ? (string) $args['wrapper_class'] : 'sc-searchable-dropdown sc-attendance-course-filter-dropdown';

    $selected_text = $all_label;
    if ($selected_course_id > 0) {
        foreach ($courses as $course) {
            if ((int) $course->id === $selected_course_id) {
                $selected_text = (string) $course->title;
                break;
            }
        }
    }

    ob_start();
    ?>
    <div class="<?php echo esc_attr($wrapper_class); ?>">
        <input type="hidden" name="<?php echo esc_attr($input_name); ?>" id="<?php echo esc_attr($input_id); ?>" value="<?php echo esc_attr((string) $selected_course_id); ?>">

        <div class="sc-dropdown-toggle" tabindex="0" role="button" aria-haspopup="listbox">
            <span class="sc-dropdown-placeholder" <?php echo $selected_course_id > 0 ? 'style="display:none"' : ''; ?>><?php echo esc_html($placeholder); ?></span>
            <span class="sc-dropdown-selected" <?php echo $selected_course_id <= 0 ? 'style="display:none"' : ''; ?>><?php echo esc_html($selected_text); ?></span>
            <span class="sc-dropdown-arrow">▼</span>
        </div>

        <div class="sc-dropdown-menu" role="listbox">
            <div class="sc-dropdown-search">
                <input type="text" class="sc-search-input" placeholder="جستجوی نام دوره..." autocomplete="off">
            </div>
            <div class="sc-dropdown-options">
                <?php
                $display_count = 0;
                $max_display = 10;
                ?>
                <div class="sc-dropdown-option sc-visible"
                     data-value="0"
                     data-search="<?php echo esc_attr(strtolower($all_label)); ?>"
                     data-label="<?php echo esc_attr($all_label); ?>">
                    <?php echo esc_html($all_label); ?>
                </div>
                <?php foreach ($courses as $course) :
                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                    $display_count++;
                    $search_blob = strtolower($course->title . ' ' . $course->id);
                    ?>
                    <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                         data-value="<?php echo esc_attr((string) $course->id); ?>"
                         data-search="<?php echo esc_attr($search_blob); ?>"
                         data-label="<?php echo esc_attr($course->title); ?>">
                        <?php echo esc_html($course->title); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * SQL scope for member_courses rows in attendance by coach and branch.
 *
 * @return array{coach_scope_where:string,chapter_where:string,prepare_args:array<int,mixed>}
 */
function sc_attendance_member_scope_sql($course_id, $coach_id, $chapter_name = '') {
    global $wpdb;

    $course_id = absint($course_id);
    $coach_id = absint($coach_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);

    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $coach_scope_where = 'mc.coach_id = %d';
    $chapter_where = '';
    $prepare_args = [];

    if ($chapter_name !== '') {
        $single_coach_for_chapter = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT CASE WHEN COUNT(DISTINCT cc.coach_id) = 1 THEN MIN(cc.coach_id) ELSE 0 END
             FROM $course_coaches_table cc
             INNER JOIN $coaches_table c ON c.id = cc.coach_id
             WHERE cc.course_id = %d AND cc.chapter_name = %s AND c.is_active = 1",
            $course_id,
            $chapter_name
        ));

        if ($single_coach_for_chapter > 0 && $single_coach_for_chapter === $coach_id) {
            $coach_scope_where = '(mc.coach_id = %d OR mc.coach_id IS NULL OR mc.coach_id = 0)';
            $chapter_where = " AND (mc.chapter = %s OR mc.chapter IS NULL OR mc.chapter = '')";
        } else {
            $chapter_where = ' AND mc.chapter = %s';
        }

        $prepare_args[] = $coach_id;
        $prepare_args[] = $chapter_name;
    } else {
        $single_active_coach_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT CASE WHEN COUNT(DISTINCT cc.coach_id) = 1 THEN MIN(cc.coach_id) ELSE 0 END
             FROM $course_coaches_table cc
             INNER JOIN $coaches_table c ON c.id = cc.coach_id
             WHERE cc.course_id = %d AND c.is_active = 1 AND cc.chapter_name != ''",
            $course_id
        ));

        if ($single_active_coach_id > 0 && $single_active_coach_id === $coach_id) {
            $coach_scope_where = '(mc.coach_id = %d OR mc.coach_id IS NULL OR mc.coach_id = 0)';
        }

        $prepare_args[] = $coach_id;
    }

    return [
        'coach_scope_where' => $coach_scope_where,
        'chapter_where' => $chapter_where,
        'prepare_args' => $prepare_args,
    ];
}

add_action('wp_ajax_sc_get_course_enrollment_options', 'sc_ajax_get_course_enrollment_options');
add_action('wp_ajax_nopriv_sc_get_course_enrollment_options', 'sc_ajax_get_course_enrollment_options');

function sc_ajax_get_course_enrollment_options() {
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    if (!$course_id) {
        wp_send_json_error(['message' => 'دوره نامعتبر است.']);
    }

    wp_send_json_success(sc_get_course_enrollment_branch_config($course_id));
}

/**
 * HTML avatar for course title column (image or initials fallback).
 *
 * @param string $title
 * @param string $image_url
 * @return string
 */
function sc_render_course_avatar_html($title, $image_url = '') {
    $title = trim((string) $title);
    $image_url = trim((string) $image_url);

    if ($image_url !== '') {
        return '<span class="sc-course-avatar" aria-hidden="true">'
            . '<img src="' . esc_url($image_url) . '" alt="">'
            . '</span>';
    }

    $initials = $title !== '' ? mb_substr($title, 0, 1) : 'د';

    return '<span class="sc-course-avatar sc-course-avatar--initials" aria-hidden="true">'
        . esc_html($initials)
        . '</span>';
}
