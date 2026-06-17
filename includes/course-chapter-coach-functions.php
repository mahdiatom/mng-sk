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
    ];

    if (!$course_id) {
        $info['is_full'] = true;
        return $info;
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
            ];
        }
    }

    return [
        'chapters' => $chapter_items,
        'chapter_count' => count($chapter_items),
        'requires_chapter_choice' => count($chapter_items) > 1,
        'schedule' => $schedule_items,
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
            ];
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
    $out = [];

    foreach ($chapters as $course_id => $chapter_name) {
        $course_id = absint($course_id);
        if (!$course_id) {
            continue;
        }
        $out[$course_id] = [
            'chapter' => sanitize_text_field((string) $chapter_name),
            'coach_id' => isset($coaches[$course_id]) ? absint($coaches[$course_id]) : 0,
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
            ];
        } else {
            $out[$course_id]['coach_id'] = absint($coach_id);
        }
    }

    return $out;
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
