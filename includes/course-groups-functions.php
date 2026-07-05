<?php
/**
 * گروه‌بندی داخل دوره (بخش ۱، بخش ۲، …) — جدا از شعبه فیزیکی
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return bool
 */
function sc_course_groups_table_ready() {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_course_groups';
    return ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t);
}

function sc_create_course_groups_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $t = $wpdb->prefix . 'sc_course_groups';
    $sql = "CREATE TABLE `$t` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `course_id` bigint(20) unsigned NOT NULL,
        `group_name` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `chapter_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'شعبه مرتبط',
        `coach_id` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'مربی مرتبط',
        `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_course_group` (`course_id`, `group_name`),
        KEY `idx_course_id` (`course_id`),
        KEY `idx_chapter_coach` (`course_id`, `chapter_name`, `coach_id`)
    ) ENGINE=InnoDB $charset_collate";
    dbDelta($sql);

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) !== $t) {
        $wpdb->query($sql);
    }
}

/**
 * اطمینان از وجود جدول/ستون‌های گروه‌بندی (حتی اگر migration قبلاً خطا داده باشد)
 */
function sc_ensure_course_groups_schema() {
    global $wpdb;

    if (function_exists('sc_create_course_groups_table')) {
        sc_create_course_groups_table();
    }

    $courses_table = $wpdb->prefix . 'sc_courses';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $courses_table)) !== $courses_table) {
        return;
    }

    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$courses_table` LIKE %s", 'has_grouping'));
    if (empty($col)) {
        $wpdb->query("ALTER TABLE `$courses_table` ADD COLUMN `has_grouping` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'دوره دارای گروه‌بندی داخلی'");
    }

    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$courses_table` LIKE %s", 'player_can_select_group'));
    if (empty($col)) {
        $wpdb->query("ALTER TABLE `$courses_table` ADD COLUMN `player_can_select_group` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'بازیکن هنگام ثبت‌نام گروه را انتخاب کند'");
    }

    $mc = $wpdb->prefix . 'sc_member_courses';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $mc)) === $mc) {
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$mc` LIKE %s", 'group_name'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$mc` ADD COLUMN `group_name` varchar(255) DEFAULT NULL COMMENT 'گروه/بخش داخل دوره' AFTER `chapter`");
        }
    }

    $sched = $wpdb->prefix . 'sc_course_weekly_schedule';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sched)) === $sched) {
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$sched` LIKE %s", 'schedule_uses_group'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$sched` ADD COLUMN `schedule_uses_group` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'این ردیف مخصوص یک گروه است'");
        }
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$sched` LIKE %s", 'group_name'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$sched` ADD COLUMN `group_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'گروه'");
        }
    }

    $groups_table = $wpdb->prefix . 'sc_course_groups';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $groups_table)) === $groups_table) {
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$groups_table` LIKE %s", 'chapter_name'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$groups_table` ADD COLUMN `chapter_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'شعبه مرتبط' AFTER `description`");
        }
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$groups_table` LIKE %s", 'coach_id'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$groups_table` ADD COLUMN `coach_id` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'مربی مرتبط' AFTER `chapter_name`");
        }
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$groups_table` LIKE %s", 'capacity'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$groups_table` ADD COLUMN `capacity` int(11) DEFAULT NULL COMMENT 'ظرفیت این گروه' AFTER `coach_id`");
        }
    }

    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$courses_table` LIKE %s", 'use_granular_capacity'));
    if (empty($col)) {
        $wpdb->query("ALTER TABLE `$courses_table` ADD COLUMN `use_granular_capacity` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ظرفیت جداگانه برای شعبه/مربی/گروه'");
    }
}

/**
 * @return bool
 */
function sc_course_groups_has_branch_columns() {
    global $wpdb;
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    if (!sc_course_groups_table_ready()) {
        $has = false;
        return $has;
    }
    $t = $wpdb->prefix . 'sc_course_groups';
    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$t` LIKE %s", 'chapter_name'));
    $has = !empty($col);
    return $has;
}

/**
 * @return bool
 */
function sc_course_groups_has_names_in_post() {
    return !empty(sc_parse_course_groups_from_post());
}

/**
 * @return array<int,array{name:string,description:string}>
 */
function sc_parse_course_groups_from_post() {
    $rows = [];

    if (!empty($_POST['course_groups_json'])) {
        $decoded = json_decode(wp_unslash((string) $_POST['course_groups_json']), true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = isset($item['name']) ? trim((string) $item['name']) : '';
                if ($name === '') {
                    continue;
                }
                $rows[] = [
                    'name' => $name,
                    'description' => isset($item['description']) ? (string) $item['description'] : '',
                    'chapter_name' => isset($item['chapter_name']) ? (string) $item['chapter_name'] : '',
                    'coach_id' => isset($item['coach_id']) ? absint($item['coach_id']) : 0,
                ];
            }
        }
    }

    if (!empty($rows)) {
        return $rows;
    }

    if (empty($_POST['course_group_row']) || !is_array($_POST['course_group_row'])) {
        return [];
    }

    foreach (wp_unslash($_POST['course_group_row']) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = isset($row['name']) ? trim((string) $row['name']) : '';
        if ($name === '') {
            continue;
        }
        $rows[] = [
            'name' => $name,
            'description' => isset($row['description']) ? (string) $row['description'] : '',
            'chapter_name' => isset($row['chapter_name']) ? (string) $row['chapter_name'] : '',
            'coach_id' => isset($row['coach_id']) ? absint($row['coach_id']) : 0,
        ];
    }

    return $rows;
}

/**
 * @return bool
 */
function sc_courses_has_grouping_column() {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_courses';
    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$t` LIKE %s", 'has_grouping'));
    return !empty($col);
}

/**
 * @return bool
 */
function sc_member_courses_has_group_column() {
    global $wpdb;
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    $t = $wpdb->prefix . 'sc_member_courses';
    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$t` LIKE %s", 'group_name'));
    $has = !empty($col);
    return $has;
}

/**
 * @return bool
 */
function sc_course_schedule_has_group_columns() {
    global $wpdb;
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    if (!function_exists('sc_course_weekly_schedule_table_ready') || !sc_course_weekly_schedule_table_ready()) {
        $has = false;
        return $has;
    }
    $t = $wpdb->prefix . 'sc_course_weekly_schedule';
    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$t` LIKE %s", 'group_name'));
    $has = !empty($col);
    return $has;
}

/**
 * @param int $course_id
 * @return array<int,object>
 */
function sc_get_course_groups($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !sc_course_groups_table_ready()) {
        return [];
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_course_groups WHERE course_id = %d ORDER BY sort_order ASC, group_name ASC, id ASC",
        $course_id
    ));
}

/**
 * @param int $course_id
 * @return string[]
 */
function sc_get_course_group_names($course_id) {
    $names = [];
    foreach (sc_get_course_groups($course_id) as $row) {
        $name = isset($row->group_name) ? trim((string) $row->group_name) : '';
        if ($name !== '') {
            $names[] = $name;
        }
    }
    return $names;
}

/**
 * @param int $course_id
 */
function sc_delete_course_groups($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !sc_course_groups_table_ready()) {
        return;
    }
    $wpdb->delete($wpdb->prefix . 'sc_course_groups', ['course_id' => $course_id], ['%d']);
}

/**
 * ذخیره گروه‌ها از POST: course_group_row[i][name], [description]
 *
 * @param int  $course_id
 * @param bool $has_grouping
 */
function sc_save_course_groups_from_post($course_id, $has_grouping = false) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id) {
        return;
    }

    if (function_exists('sc_ensure_course_groups_schema')) {
        sc_ensure_course_groups_schema();
    }

    if (!sc_course_groups_table_ready()) {
        return;
    }

    if (!$has_grouping && function_exists('sc_course_groups_has_names_in_post') && sc_course_groups_has_names_in_post()) {
        $has_grouping = true;
    }

    $group_rows = sc_parse_course_groups_from_post();

    if (!$has_grouping) {
        sc_delete_course_groups($course_id);
        if (sc_courses_has_grouping_column()) {
            $wpdb->update(
                $wpdb->prefix . 'sc_courses',
                [
                    'has_grouping' => 0,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $course_id],
                ['%d', '%s'],
                ['%d']
            );
        }
        return;
    }

    if (empty($group_rows)) {
        sc_delete_course_groups($course_id);
        if (sc_courses_has_grouping_column()) {
            $wpdb->update(
                $wpdb->prefix . 'sc_courses',
                [
                    'has_grouping' => 0,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $course_id],
                ['%d', '%s'],
                ['%d']
            );
        }
        return;
    }

    sc_delete_course_groups($course_id);

    $table = $wpdb->prefix . 'sc_course_groups';
    $now = current_time('mysql');
    $sort = 0;
    $seen = [];

    foreach ($group_rows as $row) {
        $name = isset($row['name']) ? sanitize_text_field((string) $row['name']) : '';
        if ($name === '' || isset($seen[$name])) {
            continue;
        }
        $seen[$name] = true;
        $sort++;
        $desc = isset($row['description']) ? sanitize_textarea_field((string) $row['description']) : '';
        $chapter_name = isset($row['chapter_name']) ? sanitize_text_field((string) $row['chapter_name']) : '';
        $coach_id = isset($row['coach_id']) ? absint($row['coach_id']) : 0;
        $insert_data = [
            'course_id' => $course_id,
            'group_name' => $name,
            'description' => $desc,
            'sort_order' => $sort,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $insert_fmt = ['%d', '%s', '%s', '%d', '%s', '%s'];
        if (sc_course_groups_has_branch_columns()) {
            $insert_data['chapter_name'] = $chapter_name;
            $insert_data['coach_id'] = $coach_id;
            $insert_fmt[] = '%s';
            $insert_fmt[] = '%d';
        }
        $inserted = $wpdb->insert($table, $insert_data, $insert_fmt);
        if ($inserted === false && $wpdb->last_error) {
            error_log('SC Course Groups Insert Error: ' . $wpdb->last_error);
            error_log('SC Course Groups Insert Query: ' . $wpdb->last_query);
        }
    }

    if (sc_courses_has_grouping_column()) {
        $courses_table = $wpdb->prefix . 'sc_courses';
        $wpdb->update(
            $courses_table,
            [
                'has_grouping' => $sort > 0 ? 1 : 0,
                'updated_at' => $now,
            ],
            ['id' => $course_id],
            ['%d', '%s'],
            ['%d']
        );
    }
}

/**
 * @return bool
 */
function sc_courses_player_can_select_group_column() {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_courses';
    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$t` LIKE %s", 'player_can_select_group'));
    return !empty($col);
}

/**
 * @param int $course_id
 */
function sc_course_player_can_select_group($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !sc_course_has_grouping_enabled($course_id)) {
        return false;
    }
    if (!sc_courses_player_can_select_group_column()) {
        return false;
    }
    $courses_table = $wpdb->prefix . 'sc_courses';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT player_can_select_group FROM `$courses_table` WHERE id = %d",
        $course_id
    )) === 1;
}

/**
 * @param int $course_id
 */
function sc_course_has_grouping_enabled($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id) {
        return false;
    }
    if (!sc_courses_has_grouping_column()) {
        return !empty(sc_get_course_groups($course_id));
    }
    $courses_table = $wpdb->prefix . 'sc_courses';
    $val = $wpdb->get_var($wpdb->prepare(
        "SELECT has_grouping FROM `$courses_table` WHERE id = %d",
        $course_id
    ));
    return (int) $val === 1;
}

/**
 * @param int    $course_id
 * @param string $group_name
 */
function sc_is_valid_course_group_name($course_id, $group_name) {
    $group_name = sanitize_text_field((string) $group_name);
    if ($group_name === '') {
        return false;
    }
    return in_array($group_name, sc_get_course_group_names($course_id), true);
}

/**
 * @param int    $course_id
 * @param string $selected_group
 * @param string $existing_group
 * @return string
 */
function sc_resolve_member_course_group($course_id, $selected_group = '', $existing_group = '') {
    $course_id = absint($course_id);
    $selected_group = sanitize_text_field((string) $selected_group);
    $existing_group = sanitize_text_field((string) $existing_group);

    if (!sc_course_has_grouping_enabled($course_id)) {
        return '';
    }

    if ($selected_group !== '' && sc_is_valid_course_group_name($course_id, $selected_group)) {
        return $selected_group;
    }
    if ($existing_group !== '' && sc_is_valid_course_group_name($course_id, $existing_group)) {
        return $existing_group;
    }

    return '';
}

/**
 * @param int $course_id
 * @return array<string,mixed>
 */
function sc_get_course_groups_config($course_id) {
    $course_id = absint($course_id);
    $enabled = sc_course_has_grouping_enabled($course_id);
    $items = [];

    foreach (sc_get_course_groups($course_id) as $row) {
        $item = sc_format_course_group_item($row);
        if (function_exists('sc_course_uses_granular_capacity') && sc_course_uses_granular_capacity($course_id)) {
            $cap_info = sc_get_enrollment_slot_capacity_info(
                $course_id,
                $item['chapter_name'],
                $item['coach_id'],
                $item['name']
            );
            $item['capacity'] = $cap_info['total_capacity'];
            $item['remaining'] = $cap_info['remaining'];
            $item['is_full'] = !empty($cap_info['is_full']);
        }
        $items[] = $item;
    }

    $player_can_select = sc_course_player_can_select_group($course_id);

    return [
        'has_grouping' => $enabled,
        'player_can_select_group' => $player_can_select,
        'requires_group_choice' => $player_can_select && count($items) > 1,
        'groups' => $items,
        'group_count' => count($items),
    ];
}

/**
 * @param array{chapter?:string,coach_id?:int,group_name?:string} $assignment
 * @return array<string,mixed>
 */
/**
 * @param object|array<string,mixed> $row
 * @return array{name:string,description:string,chapter_name:string,coach_id:int}
 */
function sc_format_course_group_item($row) {
    if (is_array($row)) {
        return [
            'name' => isset($row['group_name']) ? (string) $row['group_name'] : (isset($row['name']) ? (string) $row['name'] : ''),
            'description' => isset($row['description']) ? (string) $row['description'] : '',
            'chapter_name' => isset($row['chapter_name']) ? (string) $row['chapter_name'] : '',
            'coach_id' => isset($row['coach_id']) ? absint($row['coach_id']) : 0,
            'capacity' => (isset($row['capacity']) && $row['capacity'] !== null && $row['capacity'] !== '') ? (int) $row['capacity'] : null,
        ];
    }

    return [
        'name' => isset($row->group_name) ? (string) $row->group_name : '',
        'description' => isset($row->description) ? (string) $row->description : '',
        'chapter_name' => isset($row->chapter_name) ? (string) $row->chapter_name : '',
        'coach_id' => isset($row->coach_id) ? absint($row->coach_id) : 0,
        'capacity' => (isset($row->capacity) && $row->capacity !== null && $row->capacity !== '') ? (int) $row->capacity : null,
    ];
}

/**
 * @param object|array<string,mixed> $group
 * @param string                   $chapter_name
 * @param int                      $coach_id
 */
function sc_course_group_matches_branch($group, $chapter_name = '', $coach_id = 0) {
    $item = sc_format_course_group_item($group);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $coach_id = absint($coach_id);

    if ($item['chapter_name'] !== '') {
        if ($chapter_name === '' || $item['chapter_name'] !== $chapter_name) {
            return false;
        }
    }
    if ($item['coach_id'] > 0) {
        if ($coach_id <= 0 || $item['coach_id'] !== $coach_id) {
            return false;
        }
    }

    return true;
}

/**
 * Match group for weekly schedule row: both branch and coach must be selected and match the group definition.
 *
 * @param array<string,mixed>|object $group
 * @param string                   $chapter_name
 * @param int                      $coach_id
 */
function sc_course_group_matches_schedule_row($group, $chapter_name = '', $coach_id = 0) {
    $item = sc_format_course_group_item($group);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $coach_id = absint($coach_id);

    if ($chapter_name === '' || $coach_id <= 0) {
        return false;
    }
    if ($item['chapter_name'] === '' || $item['chapter_name'] !== $chapter_name) {
        return false;
    }
    if ($item['coach_id'] <= 0 || $item['coach_id'] !== $coach_id) {
        return false;
    }

    return true;
}

/**
 * @param int    $course_id
 * @param string $chapter_name
 * @param int    $coach_id
 * @return array<int,array<string,mixed>>
 */
function sc_get_course_groups_for_branch($course_id, $chapter_name = '', $coach_id = 0) {
    $items = [];
    foreach (sc_get_course_groups($course_id) as $row) {
        if (sc_course_group_matches_branch($row, $chapter_name, $coach_id)) {
            $items[] = sc_format_course_group_item($row);
        }
    }
    return $items;
}

/**
 * @param int    $course_id
 * @param string $group_name
 * @return object|null
 */
function sc_get_course_group_row($course_id, $group_name) {
    global $wpdb;
    $course_id = absint($course_id);
    $group_name = sanitize_text_field((string) $group_name);
    if (!$course_id || $group_name === '' || !sc_course_groups_table_ready()) {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_course_groups WHERE course_id = %d AND group_name = %s LIMIT 1",
        $course_id,
        $group_name
    ));
}

/**
 * @param int    $course_id
 * @param string $group_name
 * @return array{chapter:string,coach_id:int,group_name:string}
 */
function sc_resolve_enrollment_from_course_group($course_id, $group_name) {
    $course_id = absint($course_id);
    $group_name = sanitize_text_field((string) $group_name);
    $out = [
        'chapter' => '',
        'coach_id' => 0,
        'group_name' => '',
    ];

    if ($group_name === '') {
        return $out;
    }
    if (!sc_is_valid_course_group_name($course_id, $group_name)) {
        return $out;
    }

    $out['group_name'] = $group_name;
    $row = sc_get_course_group_row($course_id, $group_name);
    if ($row) {
        $item = sc_format_course_group_item($row);
        $out['chapter'] = $item['chapter_name'];
        $out['coach_id'] = (int) $item['coach_id'];
    }

    return $out;
}

/**
 * @param int $course_id
 * @return array<int,array<string,mixed>>
 */
function sc_get_bulk_course_groups_map_entry($course_id) {
    $course_id = absint($course_id);
    if (!$course_id || !sc_course_has_grouping_enabled($course_id)) {
        return [];
    }

    $groups = [];
    foreach (sc_get_course_groups($course_id) as $row) {
        $groups[] = sc_format_course_group_item($row);
    }
    return $groups;
}

function sc_member_course_row_with_group(array $assignment) {
    $row = [];
    if (!function_exists('sc_member_courses_has_group_column') || !sc_member_courses_has_group_column()) {
        return $row;
    }
    $group = isset($assignment['group_name']) ? sanitize_text_field((string) $assignment['group_name']) : '';
    $row['group_name'] = $group !== '' ? $group : null;
    return $row;
}

/**
 * Normalize finance group filter (requires selected course with grouping enabled).
 */
function sc_finance_normalize_group_filter($filter_course, $filter_group) {
    $filter_course = absint($filter_course);
    $filter_group = sanitize_text_field((string) $filter_group);
    if ($filter_group === '' || $filter_course <= 0) {
        return '';
    }
    if (!function_exists('sc_course_has_grouping_enabled') || !sc_course_has_grouping_enabled($filter_course)) {
        return '';
    }
    if ($filter_group === '__none__') {
        return '__none__';
    }
    if (function_exists('sc_is_valid_course_group_name') && sc_is_valid_course_group_name($filter_course, $filter_group)) {
        return $filter_group;
    }

    return '';
}

/**
 * LEFT JOIN invoices to member_courses for finance filters.
 */
function sc_finance_invoice_member_course_join_sql($invoice_alias = 'i', $mc_alias = 'mc') {
    global $wpdb;
    $invoice_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $invoice_alias) ?: 'i';
    $mc_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $mc_alias) ?: 'mc';
    $table = $wpdb->prefix . 'sc_member_courses';

    return " LEFT JOIN `{$table}` `{$mc_alias}` ON (
        `{$mc_alias}`.`id` = `{$invoice_alias}`.`member_course_id`
        OR (
            (`{$invoice_alias}`.`member_course_id` IS NULL OR `{$invoice_alias}`.`member_course_id` = 0)
            AND `{$mc_alias}`.`member_id` = `{$invoice_alias}`.`member_id`
            AND `{$mc_alias}`.`course_id` = `{$invoice_alias}`.`course_id`
        )
    ) ";
}

/**
 * Apply course group filter on invoice queries. Returns JOIN SQL when active.
 *
 * @param array<int,string> $where
 * @param array<int,mixed>  $args
 */
function sc_finance_apply_invoice_group_filter(array &$where, array &$args, $filter_course, $filter_group, $invoice_alias = 'i', $mc_alias = 'mc') {
    $filter_group = sc_finance_normalize_group_filter($filter_course, $filter_group);
    if ($filter_group === '') {
        return '';
    }

    $invoice_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $invoice_alias) ?: 'i';
    $mc_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $mc_alias) ?: 'mc';

    if ($filter_group === '__none__') {
        $where[] = "COALESCE(`{$mc_alias}`.`group_name`, '') = ''";
    } else {
        $where[] = "`{$mc_alias}`.`group_name` = %s";
        $args[] = $filter_group;
    }

    return sc_finance_invoice_member_course_join_sql($invoice_alias, $mc_alias);
}

/**
 * Map course_id => group names for finance filter UI.
 *
 * @param array<int,object> $courses
 * @return array<int,array<int,string>>
 */
function sc_finance_course_groups_map_for_ui(array $courses) {
    $map = [];
    foreach ($courses as $course) {
        $cid = isset($course->id) ? (int) $course->id : 0;
        if (!$cid || !function_exists('sc_course_has_grouping_enabled') || !sc_course_has_grouping_enabled($cid)) {
            continue;
        }
        $names = function_exists('sc_get_course_group_names') ? sc_get_course_group_names($cid) : [];
        if (!empty($names)) {
            $map[$cid] = array_values($names);
        }
    }

    return $map;
}
