<?php
/**
 * ظرفیت تفکیک‌شده دوره: شعبه / مربی / گروه
 */
if (!defined('ABSPATH')) {
    exit;
}

function sc_ensure_granular_capacity_schema() {
    if (function_exists('sc_ensure_course_groups_schema')) {
        sc_ensure_course_groups_schema();
    }
}

/**
 * @return bool
 */
function sc_courses_has_granular_capacity_column() {
    global $wpdb;
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    $t = $wpdb->prefix . 'sc_courses';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) !== $t) {
        $has = false;
        return $has;
    }
    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$t` LIKE %s", 'use_granular_capacity'));
    $has = !empty($col);
    return $has;
}

/**
 * @return bool
 */
function sc_course_groups_has_capacity_column() {
    global $wpdb;
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    $t = $wpdb->prefix . 'sc_course_groups';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) !== $t) {
        $has = false;
        return $has;
    }
    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$t` LIKE %s", 'capacity'));
    $has = !empty($col);
    return $has;
}

/**
 * @param int $course_id
 * @return bool
 */
function sc_course_uses_granular_capacity($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !sc_courses_has_granular_capacity_column()) {
        return false;
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT use_granular_capacity, course_type FROM `$courses_table` WHERE id = %d",
        $course_id
    ));
    if (!$row) {
        return false;
    }
    if (!empty($row->course_type) && $row->course_type === 'private') {
        return false;
    }

    return (int) $row->use_granular_capacity === 1;
}

/**
 * @param int    $course_id
 * @param string $chapter
 * @param int    $coach_id
 * @param string $group_name
 */
/**
 * @param int    $course_id
 * @param string $chapter
 * @param int    $coach_id
 * @return array{limit:?int,mode:string}
 */
function sc_resolve_granular_slot_capacity_target($course_id, $chapter = '', $coach_id = 0, $group_name = '') {
    $course_id = absint($course_id);
    $chapter = function_exists('sc_normalize_member_course_chapter')
        ? sc_normalize_member_course_chapter($chapter)
        : trim((string) $chapter);
    $coach_id = absint($coach_id);
    $group_name = sanitize_text_field((string) $group_name);

    if (
        $group_name !== ''
        && function_exists('sc_course_has_grouping_enabled')
        && sc_course_has_grouping_enabled($course_id)
    ) {
        return ['limit' => sc_get_enrollment_slot_capacity_limit($course_id, $chapter, $coach_id, $group_name), 'mode' => 'group'];
    }

    if (
        function_exists('sc_course_has_grouping_enabled')
        && sc_course_has_grouping_enabled($course_id)
        && function_exists('sc_get_course_groups')
        && sc_course_groups_has_capacity_column()
    ) {
        $group_caps = [];
        foreach (sc_get_course_groups($course_id) as $grow) {
            $item = function_exists('sc_format_course_group_item')
                ? sc_format_course_group_item($grow)
                : ['name' => '', 'chapter_name' => '', 'coach_id' => 0, 'capacity' => null];
            if (!sc_course_group_matches_branch($item, $chapter, $coach_id)) {
                continue;
            }
            if ($item['capacity'] !== null && (int) $item['capacity'] > 0) {
                $group_caps[] = (int) $item['capacity'];
            }
        }
        if (!empty($group_caps)) {
            return ['limit' => array_sum($group_caps), 'mode' => 'groups_pool'];
        }
    }

    return ['limit' => sc_get_enrollment_slot_capacity_limit($course_id, $chapter, $coach_id, ''), 'mode' => 'branch_coach'];
}

function sc_count_enrollment_slot_capacity_used($course_id, $chapter = '', $coach_id = 0, $group_name = '') {
    global $wpdb;
    $course_id = absint($course_id);
    if ($course_id < 1) {
        return 0;
    }

    $chapter = function_exists('sc_normalize_member_course_chapter')
        ? sc_normalize_member_course_chapter($chapter)
        : trim((string) $chapter);
    $coach_id = absint($coach_id);
    $group_name = sanitize_text_field((string) $group_name);

    $t = $wpdb->prefix . 'sc_member_courses';
    $base = "course_id = %d AND status = 'active' AND (course_status_flags IS NULL OR TRIM(course_status_flags) = '')";

    if (
        $group_name !== ''
        && function_exists('sc_course_has_grouping_enabled')
        && sc_course_has_grouping_enabled($course_id)
    ) {
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$t` WHERE {$base} AND group_name = %s",
            $course_id,
            $group_name
        ));
    }

    if (
        function_exists('sc_course_has_grouping_enabled')
        && sc_course_has_grouping_enabled($course_id)
        && function_exists('sc_get_course_groups')
        && sc_course_groups_has_capacity_column()
    ) {
        $has_group_caps = false;
        foreach (sc_get_course_groups($course_id) as $grow) {
            $item = function_exists('sc_format_course_group_item')
                ? sc_format_course_group_item($grow)
                : ['name' => '', 'chapter_name' => '', 'coach_id' => 0, 'capacity' => null];
            if (!sc_course_group_matches_branch($item, $chapter, $coach_id)) {
                continue;
            }
            if ($item['capacity'] !== null && (int) $item['capacity'] > 0) {
                $has_group_caps = true;
                break;
            }
        }
        if ($has_group_caps) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `$t` WHERE {$base} AND chapter = %s AND coach_id = %d",
                $course_id,
                $chapter,
                $coach_id
            ));
        }
    }

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `$t`
         WHERE {$base}
           AND chapter = %s
           AND coach_id = %d
           AND (group_name IS NULL OR TRIM(group_name) = '')",
        $course_id,
        $chapter,
        $coach_id
    ));
}

/**
 * @param int    $course_id
 * @param string $chapter
 * @param int    $coach_id
 * @param string $group_name
 * @return int|null null = نامحدود
 */
function sc_get_enrollment_slot_capacity_limit($course_id, $chapter = '', $coach_id = 0, $group_name = '') {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !sc_course_uses_granular_capacity($course_id)) {
        return null;
    }

    $chapter = function_exists('sc_normalize_member_course_chapter')
        ? sc_normalize_member_course_chapter($chapter)
        : trim((string) $chapter);
    $coach_id = absint($coach_id);
    $group_name = sanitize_text_field((string) $group_name);

    if (
        $group_name !== ''
        && function_exists('sc_course_has_grouping_enabled')
        && sc_course_has_grouping_enabled($course_id)
        && sc_course_groups_has_capacity_column()
    ) {
        $cap = $wpdb->get_var($wpdb->prepare(
            "SELECT capacity FROM {$wpdb->prefix}sc_course_groups
             WHERE course_id = %d AND group_name = %s LIMIT 1",
            $course_id,
            $group_name
        ));
        if ($cap !== null && $cap !== '' && (int) $cap > 0) {
            return max(1, (int) $cap);
        }
        return null;
    }

    if ($coach_id > 0 && $chapter !== '' && function_exists('sc_get_course_coach_branch_meta')) {
        $meta = sc_get_course_coach_branch_meta($course_id, $chapter, $coach_id);
        if ($meta && $meta['capacity'] !== null && (int) $meta['capacity'] > 0) {
            return max(1, (int) $meta['capacity']);
        }
    }

    return null;
}

/**
 * @return array{show:bool,remaining:?int,total_capacity:?int,is_full:bool,unlimited:bool}
 */
function sc_get_enrollment_slot_capacity_info($course_id, $chapter = '', $coach_id = 0, $group_name = '') {
    $info = [
        'show' => false,
        'remaining' => null,
        'total_capacity' => null,
        'is_full' => false,
        'unlimited' => false,
    ];

    if (!sc_course_uses_granular_capacity($course_id)) {
        return $info;
    }

    $target = sc_resolve_granular_slot_capacity_target($course_id, $chapter, $coach_id, $group_name);
    $limit = $target['limit'];
    if ($limit === null) {
        $info['unlimited'] = true;
        return $info;
    }

    $used = sc_count_enrollment_slot_capacity_used($course_id, $chapter, $coach_id, $group_name);
    $remaining = max(0, $limit - $used);
    $info['show'] = true;
    $info['remaining'] = $remaining;
    $info['total_capacity'] = $limit;
    $info['is_full'] = ($remaining <= 0);
    return $info;
}

/**
 * @param int $course_id
 * @return array<int,array<string,mixed>>
 */
function sc_get_course_granular_capacity_slot_definitions($course_id) {
    $course_id = absint($course_id);
    if (!$course_id) {
        return [];
    }

    $slots = [];
    $has_groups = function_exists('sc_course_has_grouping_enabled') && sc_course_has_grouping_enabled($course_id);

    if ($has_groups && function_exists('sc_get_course_groups')) {
        foreach (sc_get_course_groups($course_id) as $row) {
            $name = isset($row->group_name) ? trim((string) $row->group_name) : '';
            if ($name === '') {
                continue;
            }
            $cap = null;
            if (sc_course_groups_has_capacity_column() && isset($row->capacity) && $row->capacity !== null && $row->capacity !== '') {
                $cap = max(1, (int) $row->capacity);
            }
            $slots[] = [
                'type' => 'group',
                'group_name' => $name,
                'chapter_name' => isset($row->chapter_name) ? (string) $row->chapter_name : '',
                'coach_id' => isset($row->coach_id) ? (int) $row->coach_id : 0,
                'capacity' => $cap,
            ];
        }
        if (!empty($slots)) {
            return $slots;
        }
    }

    if (function_exists('sc_get_course_coach_assignments_map')) {
        $map = sc_get_course_coach_assignments_map($course_id);
        $meta_map = function_exists('sc_get_course_coach_branch_meta_map')
            ? sc_get_course_coach_branch_meta_map($course_id)
            : [];
        foreach ($map as $chapter_name => $coach_ids) {
            foreach (array_keys((array) $coach_ids) as $coach_id) {
                $coach_id = absint($coach_id);
                if (!$coach_id) {
                    continue;
                }
                $cap = null;
                if (isset($meta_map[$chapter_name][$coach_id]['capacity']) && $meta_map[$chapter_name][$coach_id]['capacity'] !== null) {
                    $cap = max(1, (int) $meta_map[$chapter_name][$coach_id]['capacity']);
                }
                $slots[] = [
                    'type' => 'branch_coach',
                    'group_name' => '',
                    'chapter_name' => (string) $chapter_name,
                    'coach_id' => $coach_id,
                    'capacity' => $cap,
                ];
            }
        }
    }

    if (empty($slots) && function_exists('sc_get_course_chapters')) {
        $chapters = sc_get_course_chapters($course_id);
        if (count($chapters) === 1) {
            $slots[] = [
                'type' => 'branch_coach',
                'group_name' => '',
                'chapter_name' => (string) $chapters[0],
                'coach_id' => 0,
                'capacity' => null,
            ];
        }
    }

    return $slots;
}

/**
 * @param int $course_id
 * @return array<int,array<string,mixed>>
 */
function sc_get_course_granular_capacity_slots_with_usage($course_id) {
    $out = [];
    foreach (sc_get_course_granular_capacity_slot_definitions($course_id) as $slot) {
        $chapter = (string) ($slot['chapter_name'] ?? '');
        $coach_id = (int) ($slot['coach_id'] ?? 0);
        $group_name = (string) ($slot['group_name'] ?? '');
        $limit = isset($slot['capacity']) && $slot['capacity'] !== null ? (int) $slot['capacity'] : null;
        $used = sc_count_enrollment_slot_capacity_used($course_id, $chapter, $coach_id, $group_name);
        $remaining = ($limit !== null) ? max(0, $limit - $used) : null;
        $out[] = array_merge($slot, [
            'used' => $used,
            'remaining' => $remaining,
            'is_full' => ($limit !== null && $used >= $limit),
            'unlimited' => ($limit === null),
        ]);
    }
    return $out;
}

/**
 * @param int $course_id
 */
function sc_course_has_any_granular_enrollment_slot($course_id) {
    $slots = sc_get_course_granular_capacity_slots_with_usage($course_id);
    if (empty($slots)) {
        return true;
    }
    foreach ($slots as $slot) {
        if (!empty($slot['unlimited']) || empty($slot['is_full'])) {
            return true;
        }
    }
    return false;
}

/**
 * @return array{show:bool,remaining:?int,total_capacity:?int,is_full:bool,unlimited:bool}
 */
function sc_get_course_granular_enrollment_capacity_summary($course_id) {
    $info = [
        'show' => false,
        'remaining' => 0,
        'total_capacity' => 0,
        'is_full' => false,
        'unlimited' => false,
    ];

    $slots = sc_get_course_granular_capacity_slots_with_usage($course_id);
    if (empty($slots)) {
        $info['unlimited'] = true;
        return $info;
    }

    $has_limited = false;
    $total_cap = 0;
    $total_remaining = 0;
    $all_full = true;

    foreach ($slots as $slot) {
        if (!empty($slot['unlimited'])) {
            $all_full = false;
            continue;
        }
        $has_limited = true;
        $total_cap += (int) $slot['capacity'];
        $total_remaining += max(0, (int) ($slot['remaining'] ?? 0));
        if (empty($slot['is_full'])) {
            $all_full = false;
        }
    }

    if (!$has_limited) {
        $info['unlimited'] = true;
        return $info;
    }

    $info['show'] = true;
    $info['total_capacity'] = $total_cap;
    $info['remaining'] = $total_remaining;
    $info['is_full'] = $all_full;
    return $info;
}

/**
 * @param int    $course_id
 * @param string $chapter
 * @param int    $coach_id
 * @param string $group_name
 * @return true|WP_Error
 */
function sc_validate_enrollment_slot_capacity($course_id, $chapter = '', $coach_id = 0, $group_name = '') {
    if (!sc_course_uses_granular_capacity($course_id)) {
        return true;
    }

    $info = sc_get_enrollment_slot_capacity_info($course_id, $chapter, $coach_id, $group_name);
    if (!empty($info['unlimited'])) {
        return true;
    }
    if (!empty($info['is_full'])) {
        return new WP_Error('sc_cap_full', 'ظرفیت انتخاب‌شده (شعبه/مربی/گروه) تکمیل شده است.');
    }
    return true;
}

/**
 * @param int $course_id
 */
function sc_save_granular_capacities_from_post($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !sc_courses_has_granular_capacity_column()) {
        return;
    }

    $use_granular = !empty($_POST['use_granular_capacity']);
    $courses_table = $wpdb->prefix . 'sc_courses';
    $wpdb->update(
        $courses_table,
        [
            'use_granular_capacity' => $use_granular ? 1 : 0,
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $course_id],
        ['%d', '%s'],
        ['%d']
    );

    if (!$use_granular || empty($_POST['granular_capacity_present'])) {
        return;
    }

    $group_caps = isset($_POST['granular_group_capacity']) && is_array($_POST['granular_group_capacity'])
        ? wp_unslash($_POST['granular_group_capacity'])
        : [];
    $branch_caps = isset($_POST['granular_branch_capacity']) && is_array($_POST['granular_branch_capacity'])
        ? wp_unslash($_POST['granular_branch_capacity'])
        : [];

    if (sc_course_groups_has_capacity_column() && !empty($group_caps)) {
        $groups_table = $wpdb->prefix . 'sc_course_groups';
        foreach ($group_caps as $group_name => $cap_val) {
            $group_name = sanitize_text_field((string) $group_name);
            if ($group_name === '') {
                continue;
            }
            $capacity = ($cap_val !== null && $cap_val !== '') ? max(1, absint($cap_val)) : null;
            $wpdb->update(
                $groups_table,
                [
                    'capacity' => $capacity,
                    'updated_at' => current_time('mysql'),
                ],
                [
                    'course_id' => $course_id,
                    'group_name' => $group_name,
                ],
                [$capacity !== null ? '%d' : '%s', '%s'],
                ['%d', '%s']
            );
        }
    }

    if (!empty($branch_caps)) {
        $cc = $wpdb->prefix . 'sc_course_coaches';
        foreach ($branch_caps as $chapter_name => $coach_rows) {
            $chapter_name = sanitize_text_field((string) $chapter_name);
            if ($chapter_name === '' || !is_array($coach_rows)) {
                continue;
            }
            foreach ($coach_rows as $coach_id => $cap_val) {
                $coach_id = absint($coach_id);
                if (!$coach_id) {
                    continue;
                }
                $capacity = ($cap_val !== null && $cap_val !== '') ? max(1, absint($cap_val)) : null;
                $wpdb->update(
                    $cc,
                    [
                        'capacity' => $capacity,
                        'updated_at' => current_time('mysql'),
                    ],
                    [
                        'course_id' => $course_id,
                        'coach_id' => $coach_id,
                        'chapter_name' => $chapter_name,
                    ],
                    [$capacity !== null ? '%d' : '%s', '%s'],
                    ['%d', '%d', '%s']
                );
            }
        }
    }
}
