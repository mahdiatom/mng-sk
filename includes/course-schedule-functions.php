<?php
/**
 * زمان‌بندی هفتگی دوره‌ها (برنامه کلاس)
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array<int,string> 1=شنبه … 7=جمعه
 */
function sc_course_weekday_labels_ir() {
    return [
        1 => 'شنبه',
        2 => 'یکشنبه',
        3 => 'دوشنبه',
        4 => 'سه‌شنبه',
        5 => 'چهارشنبه',
        6 => 'پنج‌شنبه',
        7 => 'جمعه',
    ];
}

/**
 * از تاریخ میلادی (Y-m-d) به شمارهٔ روز هفتهٔ جدول برنامه (۱=شنبه … ۷=جمعه)
 *
 * @param string $ymd
 * @return int|null ۱ تا ۷
 */
function sc_course_ir_weekday_from_gregorian_ymd($ymd) {
    $ymd = is_string($ymd) ? trim($ymd) : '';
    if ($ymd === '') {
        return null;
    }
    $ts = strtotime($ymd . ' 12:00:00');
    if (!$ts) {
        return null;
    }
    $w = (int) date('w', $ts); // PHP: 0=Sunday … 6=Saturday
    // شنبه=۶ → ۱، یکشنبه=۰ → ۲، … جمعه=۵ → ۷
    return (($w + 1) % 7) + 1;
}

/**
 * @param string $input
 * @return string|null H:i:s
 */
function sc_normalize_time_his($input) {
    $input = is_string($input) ? trim($input) : '';
    if ($input === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $input, $m)) {
        $h = min(23, max(0, (int) $m[1]));
        $i = min(59, max(0, (int) $m[2]));
        $s = isset($m[3]) ? min(59, max(0, (int) $m[3])) : 0;
        return sprintf('%02d:%02d:%02d', $h, $i, $s);
    }
    return null;
}

/**
 * @return bool
 */
function sc_course_weekly_schedule_table_ready() {
    global $wpdb;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $t = $wpdb->prefix . 'sc_course_weekly_schedule';
    $ok = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t);
    return $ok;
}

/**
 * @param int $course_id
 */
function sc_delete_course_weekly_schedule($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !sc_course_weekly_schedule_table_ready()) {
        return;
    }
    $wpdb->delete($wpdb->prefix . 'sc_course_weekly_schedule', ['course_id' => $course_id], ['%d']);
}

/**
 * ذخیره از POST: آرایه csched_row[i][wd][], start, end
 *
 * @param int $course_id
 */
function sc_save_course_weekly_schedule_from_post($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !sc_course_weekly_schedule_table_ready()) {
        return;
    }

    sc_delete_course_weekly_schedule($course_id);

    if (empty($_POST['csched_row']) || !is_array($_POST['csched_row'])) {
        return;
    }

    $table = $wpdb->prefix . 'sc_course_weekly_schedule';
    $now = current_time('mysql');
    $sort = 0;

    $has_chapter_coach_cols = sc_course_schedule_has_chapter_coach_columns();

    foreach (wp_unslash($_POST['csched_row']) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $wd = isset($row['wd']) && is_array($row['wd']) ? array_map('absint', $row['wd']) : [];
        $wd = array_values(array_unique(array_filter($wd, static function ($d) {
            return $d >= 1 && $d <= 7;
        })));
        $ts = isset($row['start']) ? sc_normalize_time_his(sanitize_text_field($row['start'])) : null;
        $te = isset($row['end']) ? sc_normalize_time_his(sanitize_text_field($row['end'])) : null;
        if (!$ts || !$te || empty($wd)) {
            continue;
        }
        if (strcmp($ts, $te) >= 0) {
            continue;
        }
        $row_chapter = isset($row['chapter']) ? sanitize_text_field((string) $row['chapter']) : '';
        $row_coach = isset($row['coach']) ? absint($row['coach']) : 0;
        $row_uses_group = !empty($row['uses_group']) ? 1 : 0;
        $row_group = isset($row['group']) ? sanitize_text_field((string) $row['group']) : '';
        if (!$row_uses_group) {
            $row_group = '';
        }
        foreach ($wd as $d) {
            $sort++;
            $data = [
                'course_id' => $course_id,
                'weekday' => $d,
                'time_start' => $ts,
                'time_end' => $te,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $formats = ['%d', '%d', '%s', '%s', '%d', '%s', '%s'];
            if ($has_chapter_coach_cols) {
                $data['chapter_name'] = $row_chapter;
                $data['coach_id'] = $row_coach;
                $formats[] = '%s';
                $formats[] = '%d';
            }
            if (function_exists('sc_course_schedule_has_group_columns') && sc_course_schedule_has_group_columns()) {
                $data['schedule_uses_group'] = $row_uses_group;
                $data['group_name'] = $row_group;
                $formats[] = '%d';
                $formats[] = '%s';
            }
            $wpdb->insert($table, $data, $formats);
        }
    }
}

/**
 * @return bool
 */
function sc_course_schedule_has_chapter_coach_columns() {
    global $wpdb;
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    if (!sc_course_weekly_schedule_table_ready()) {
        $has = false;
        return $has;
    }
    $t = $wpdb->prefix . 'sc_course_weekly_schedule';
    $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$t` LIKE %s", 'chapter_name'));
    $has = !empty($col);
    return $has;
}

/**
 * @param int $course_id
 * @return array<int,object>
 */
function sc_get_course_weekly_schedule_rows($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !sc_course_weekly_schedule_table_ready()) {
        return [];
    }
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_course_weekly_schedule WHERE course_id = %d ORDER BY weekday ASC, time_start ASC, id ASC",
        $course_id
    ));
}

/**
 * برای فرم ادمین: ادغام ردیف‌های هم‌زمان (و هم‌شعبه/هم‌مربی) به یک بلوک با چند روز
 *
 * @param array<int,object> $rows
 * @return array<int,array{start:string,end:string,wd:array<int,int>,chapter:string,coach_id:int}>
 */
function sc_group_course_schedule_for_form($rows) {
    $groups = [];
    foreach ($rows as $r) {
        if (!isset($r->time_start, $r->time_end, $r->weekday)) {
            continue;
        }
        $chapter = isset($r->chapter_name) ? (string) $r->chapter_name : '';
        $coach_id = isset($r->coach_id) ? (int) $r->coach_id : 0;
        $uses_group = isset($r->schedule_uses_group) ? (int) $r->schedule_uses_group : 0;
        $group_name = isset($r->group_name) ? (string) $r->group_name : '';
        $k = $r->time_start . '|' . $r->time_end . '|' . $chapter . '|' . $coach_id . '|' . $uses_group . '|' . $group_name;
        if (!isset($groups[$k])) {
            $groups[$k] = [
                'start' => $r->time_start,
                'end' => $r->time_end,
                'chapter' => $chapter,
                'coach_id' => $coach_id,
                'uses_group' => $uses_group,
                'group_name' => $group_name,
                'wd' => [],
            ];
        }
        $groups[$k]['wd'][(int) $r->weekday] = (int) $r->weekday;
    }
    $out = [];
    foreach ($groups as $g) {
        $g['wd'] = array_values($g['wd']);
        sort($g['wd']);
        $out[] = $g;
    }
    usort($out, static function ($a, $b) {
        return strcmp((string) $a['start'], (string) $b['start']);
    });
    return $out;
}

/**
 * برنامهٔ هفتگی بازیکن از روی دوره‌های فعال ثبت‌نام + جدول زمان‌بندی
 *
 * @param int $member_id
 * @return array{days:array<int,string>,cells:array<int,array<int,array<string,mixed>>>}
 */
function sc_get_member_weekly_schedule_matrix($member_id) {
    global $wpdb;
    $member_id = absint($member_id);
    $labels = sc_course_weekday_labels_ir();
    $cells = [];
    foreach (range(1, 7) as $d) {
        $cells[$d] = [];
    }
    if (!$member_id || !sc_course_weekly_schedule_table_ready()) {
        return ['days' => $labels, 'cells' => $cells];
    }

    $mc = $wpdb->prefix . 'sc_member_courses';
    $c = $wpdb->prefix . 'sc_courses';
    $sch = $wpdb->prefix . 'sc_course_weekly_schedule';

    // فقط ردیف‌های برنامه که با شعبه/مربی انتخابی بازیکن هنگام ثبت‌نام مطابقت دارند
    $chapter_coach_filter = '';
    if (function_exists('sc_course_schedule_has_chapter_coach_columns') && sc_course_schedule_has_chapter_coach_columns()) {
        $chapter_coach_filter = "
          AND (s.chapter_name IS NULL OR s.chapter_name = '' OR mc.chapter IS NULL OR mc.chapter = '' OR s.chapter_name = mc.chapter)
          AND (s.coach_id IS NULL OR s.coach_id = 0 OR mc.coach_id IS NULL OR mc.coach_id = 0 OR s.coach_id = mc.coach_id)";
    }

    if (function_exists('sc_course_schedule_has_group_columns') && sc_course_schedule_has_group_columns()) {
        $chapter_coach_filter .= "
          AND (
            COALESCE(s.schedule_uses_group, 0) = 0
            OR COALESCE(s.group_name, '') = ''
            OR (mc.group_name IS NOT NULL AND mc.group_name != '' AND s.group_name = mc.group_name)
          )";
    }

    $group_select = '';
    if (function_exists('sc_course_schedule_has_group_columns') && sc_course_schedule_has_group_columns()) {
        $group_select = ', s.schedule_uses_group, s.group_name';
    }

    $sql = "SELECT s.id AS schedule_id, s.weekday, s.time_start, s.time_end{$group_select},
                   c.id AS course_id, c.title AS course_title
        FROM {$mc} mc
        INNER JOIN {$c} c ON c.id = mc.course_id AND c.deleted_at IS NULL
        INNER JOIN {$sch} s ON s.course_id = c.id
        WHERE mc.member_id = %d
          AND mc.status IN ('active','inactive')
          AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '' OR TRIM(mc.course_status_flags) = '')
          {$chapter_coach_filter}
        ORDER BY s.weekday ASC, s.time_start ASC";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $member_id));
    foreach ($rows as $r) {
        $d = (int) $r->weekday;
        if ($d < 1 || $d > 7) {
            continue;
        }
        $cells[$d][] = [
            'schedule_id' => isset($r->schedule_id) ? (int) $r->schedule_id : 0,
            'course_id' => (int) $r->course_id,
            'title' => (string) $r->course_title,
            'start' => substr((string) $r->time_start, 0, 5),
            'end' => substr((string) $r->time_end, 0, 5),
            'group_name' => (
                function_exists('sc_course_schedule_has_group_columns') && sc_course_schedule_has_group_columns()
                && !empty($r->schedule_uses_group) && !empty($r->group_name)
            ) ? (string) $r->group_name : '',
        ];
    }

    return ['days' => $labels, 'cells' => $cells];
}

/**
 * برنامهٔ هفتگی مربی: فقط اسلات‌هایی که با انتساب شعبه/مربی او در دوره مطابقت دارند.
 *
 * @param int $coach_id
 * @return array{days:array<int,string>,cells:array<int,array<int,array<string,mixed>>>}
 */
function sc_get_coach_weekly_schedule_matrix($coach_id) {
    global $wpdb;
    $coach_id = absint($coach_id);
    $labels = sc_course_weekday_labels_ir();
    $cells = [];
    foreach (range(1, 7) as $d) {
        $cells[$d] = [];
    }
    if (!$coach_id || !sc_course_weekly_schedule_table_ready()) {
        return ['days' => $labels, 'cells' => $cells];
    }

    $cc = $wpdb->prefix . 'sc_course_coaches';
    $c = $wpdb->prefix . 'sc_courses';
    $sch = $wpdb->prefix . 'sc_course_weekly_schedule';

    // فقط ردیف‌هایی که برای همین مربی (یا عمومی) و شعبهٔ انتساب اوست — نه همهٔ اسلات‌های دوره
    $has_chapter_coach = function_exists('sc_course_schedule_has_chapter_coach_columns')
        && sc_course_schedule_has_chapter_coach_columns();
    $has_group_cols = function_exists('sc_course_schedule_has_group_columns')
        && sc_course_schedule_has_group_columns();

    $slot_filter = '';
    if ($has_chapter_coach) {
        $slot_filter = "
          AND (s.coach_id IS NULL OR s.coach_id = 0 OR s.coach_id = %d)
          AND (
            s.chapter_name IS NULL OR s.chapter_name = ''
            OR cc.chapter_name IS NULL OR cc.chapter_name = ''
            OR s.chapter_name = cc.chapter_name
          )";
    }

    $extra_select = '';
    if ($has_chapter_coach) {
        $extra_select .= ', s.chapter_name, s.coach_id AS slot_coach_id';
    }
    if ($has_group_cols) {
        $extra_select .= ', s.schedule_uses_group, s.group_name';
    }

    $sql = "SELECT DISTINCT s.id AS schedule_id, s.weekday, s.time_start, s.time_end{$extra_select},
                   c.id AS course_id, c.title AS course_title, c.course_type
        FROM {$cc} cc
        INNER JOIN {$c} c ON c.id = cc.course_id AND c.deleted_at IS NULL
        INNER JOIN {$sch} s ON s.course_id = c.id
        WHERE cc.coach_id = %d
          {$slot_filter}
        ORDER BY s.weekday ASC, s.time_start ASC, s.id ASC";

    $rows = $has_chapter_coach
        ? $wpdb->get_results($wpdb->prepare($sql, $coach_id, $coach_id))
        : $wpdb->get_results($wpdb->prepare($sql, $coach_id));
    $seen = [];
    foreach ($rows as $r) {
        $d = (int) $r->weekday;
        if ($d < 1 || $d > 7) {
            continue;
        }
        $sid = isset($r->schedule_id) ? (int) $r->schedule_id : 0;
        $dedupe_key = $sid > 0 ? (string) $sid : ($d . '|' . $r->time_start . '|' . $r->time_end . '|' . $r->course_id);
        if (isset($seen[$dedupe_key])) {
            continue;
        }
        $seen[$dedupe_key] = true;

        $chapter = (isset($r->chapter_name) && trim((string) $r->chapter_name) !== '')
            ? trim((string) $r->chapter_name)
            : '';
        $group_name = (
            function_exists('sc_course_schedule_has_group_columns') && sc_course_schedule_has_group_columns()
            && !empty($r->schedule_uses_group) && !empty($r->group_name)
        ) ? (string) $r->group_name : '';

        $cells[$d][] = [
            'schedule_id' => $sid,
            'course_id' => (int) $r->course_id,
            'title' => (string) $r->course_title,
            'type' => ((string) $r->course_type === 'private') ? 'خصوصی/نیمه‌خصوصی' : 'گروهی',
            'start' => substr((string) $r->time_start, 0, 5),
            'end' => substr((string) $r->time_end, 0, 5),
            'chapter' => $chapter,
            'group_name' => $group_name,
        ];
    }

    return ['days' => $labels, 'cells' => $cells];
}

/**
 * فیلترهای گزارش برنامه هفتگی ادمین
 *
 * @param array|null $source
 * @return array{filter_course:int,filter_chapter:string,filter_coach:int,filter_group:string}
 */
function sc_weekly_schedule_report_parse_filters($source = null) {
    $source = is_array($source) ? $source : $_GET;
    $filter_course = isset($source['filter_course']) ? absint($source['filter_course']) : 0;
    $filter_chapter = isset($source['filter_chapter']) ? sanitize_text_field(wp_unslash((string) $source['filter_chapter'])) : '';
    $filter_coach = isset($source['filter_coach']) ? absint($source['filter_coach']) : 0;
    $filter_group_raw = isset($source['filter_group']) ? sanitize_text_field(wp_unslash((string) $source['filter_group'])) : '';
    $filter_group = function_exists('sc_finance_normalize_group_filter')
        ? sc_finance_normalize_group_filter($filter_course, $filter_group_raw)
        : $filter_group_raw;

    return [
        'filter_course' => $filter_course,
        'filter_chapter' => $filter_chapter,
        'filter_coach' => $filter_coach,
        'filter_group' => $filter_group,
    ];
}

/**
 * @param array{filter_course?:int,filter_chapter?:string,filter_coach?:int,filter_group?:string} $filters
 * @return array{days:array<int,string>,sections:array<int,array<string,mixed>>}
 */
function sc_get_admin_weekly_schedule_report(array $filters) {
    global $wpdb;

    $labels = sc_course_weekday_labels_ir();
    if (!sc_course_weekly_schedule_table_ready()) {
        return ['days' => $labels, 'sections' => []];
    }

    $filter_course = isset($filters['filter_course']) ? absint($filters['filter_course']) : 0;
    $filter_chapter = isset($filters['filter_chapter']) ? sanitize_text_field((string) $filters['filter_chapter']) : '';
    $filter_coach = isset($filters['filter_coach']) ? absint($filters['filter_coach']) : 0;
    $filter_group = isset($filters['filter_group']) ? sanitize_text_field((string) $filters['filter_group']) : '';

    $sch = $wpdb->prefix . 'sc_course_weekly_schedule';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $where = ['c.deleted_at IS NULL'];
    $args = [];

    if ($filter_course > 0) {
        $where[] = 's.course_id = %d';
        $args[] = $filter_course;
    }

    if ($filter_chapter !== '' && sc_course_schedule_has_chapter_coach_columns()) {
        $where[] = "(s.chapter_name IS NULL OR s.chapter_name = '' OR s.chapter_name = %s)";
        $args[] = $filter_chapter;
    }

    if ($filter_coach > 0 && sc_course_schedule_has_chapter_coach_columns()) {
        $where[] = '(s.coach_id IS NULL OR s.coach_id = 0 OR s.coach_id = %d)';
        $args[] = $filter_coach;
    }

    if ($filter_group !== '' && sc_course_schedule_has_group_columns()) {
        if ($filter_group === '__none__') {
            $where[] = "(COALESCE(s.schedule_uses_group, 0) = 0 OR COALESCE(s.group_name, '') = '')";
        } else {
            $where[] = "(
                COALESCE(s.schedule_uses_group, 0) = 0
                OR COALESCE(s.group_name, '') = ''
                OR s.group_name = %s
            )";
            $args[] = $filter_group;
        }
    }

    $extra_select = ', c.title AS course_title, c.course_type';
    if (sc_course_schedule_has_chapter_coach_columns()) {
        $extra_select .= ', s.chapter_name, s.coach_id';
    }
    if (sc_course_schedule_has_group_columns()) {
        $extra_select .= ', s.schedule_uses_group, s.group_name';
    }

    $sql = "SELECT s.id, s.course_id, s.weekday, s.time_start, s.time_end{$extra_select}
            FROM {$sch} s
            INNER JOIN {$courses_table} c ON c.id = s.course_id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY c.title ASC, s.weekday ASC, s.time_start ASC, s.id ASC';

    $rows = !empty($args) ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);
    if (empty($rows)) {
        return ['days' => $labels, 'sections' => []];
    }

    $coach_ids = [];
    foreach ($rows as $row) {
        if (!empty($row->coach_id)) {
            $coach_ids[(int) $row->coach_id] = true;
        }
    }

    $coach_names = [];
    if (!empty($coach_ids)) {
        $ids = array_keys($coach_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $coach_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name FROM {$coaches_table} WHERE id IN ($placeholders)",
            ...$ids
        ));
        foreach ($coach_rows as $coach_row) {
            $coach_names[(int) $coach_row->id] = trim((string) $coach_row->first_name . ' ' . (string) $coach_row->last_name);
        }
    }

    $sections = [];
    foreach ($rows as $row) {
        $course_id = (int) $row->course_id;
        if (!isset($sections[$course_id])) {
            $cells = [];
            foreach (range(1, 7) as $day_num) {
                $cells[$day_num] = [];
            }
            $sections[$course_id] = [
                'course_id' => $course_id,
                'course_title' => (string) $row->course_title,
                'course_type' => (string) $row->course_type,
                'cells' => $cells,
            ];
        }

        $weekday = (int) $row->weekday;
        if ($weekday < 1 || $weekday > 7) {
            continue;
        }

        $chapter = (isset($row->chapter_name) && trim((string) $row->chapter_name) !== '')
            ? trim((string) $row->chapter_name)
            : '';
        $coach_id = isset($row->coach_id) ? (int) $row->coach_id : 0;
        $coach_name = ($coach_id > 0 && isset($coach_names[$coach_id])) ? $coach_names[$coach_id] : '';
        if ($coach_id > 0 && $coach_name === '') {
            $coach_name = 'مربی #' . $coach_id;
        }
        $group_name = (
            sc_course_schedule_has_group_columns()
            && !empty($row->schedule_uses_group)
            && !empty($row->group_name)
        ) ? (string) $row->group_name : '';

        $sections[$course_id]['cells'][$weekday][] = [
            'schedule_id' => (int) $row->id,
            'start' => substr((string) $row->time_start, 0, 5),
            'end' => substr((string) $row->time_end, 0, 5),
            'chapter' => $chapter,
            'coach_name' => $coach_name,
            'coach_id' => $coach_id,
            'group_name' => $group_name,
            'type' => ((string) $row->course_type === 'private') ? 'خصوصی/نیمه‌خصوصی' : 'گروهی',
        ];
    }

    return [
        'days' => $labels,
        'sections' => array_values($sections),
    ];
}

/**
 * @param array{filter_course?:int,filter_chapter?:string,filter_coach?:int,filter_group?:string} $filters
 * @return string
 */
function sc_weekly_schedule_report_pdf_export_url(array $filters) {
    $args = [
        'page' => 'sc-reports-weekly-schedule',
        'sc_export_weekly_schedule_pdf' => 1,
    ];
    if (!empty($filters['filter_course'])) {
        $args['filter_course'] = (int) $filters['filter_course'];
    }
    if (!empty($filters['filter_chapter'])) {
        $args['filter_chapter'] = $filters['filter_chapter'];
    }
    if (!empty($filters['filter_coach'])) {
        $args['filter_coach'] = (int) $filters['filter_coach'];
    }
    if (!empty($filters['filter_group'])) {
        $args['filter_group'] = $filters['filter_group'];
    }

    return wp_nonce_url(add_query_arg($args, admin_url('admin.php')), 'sc_export_weekly_schedule_pdf');
}

/**
 * @param array{filter_course?:int,filter_chapter?:string,filter_coach?:int,filter_group?:string} $filters
 * @return int
 */
function sc_weekly_schedule_report_active_filters_count(array $filters) {
    $count = 0;
    if (!empty($filters['filter_course'])) {
        $count++;
    }
    if (!empty($filters['filter_chapter'])) {
        $count++;
    }
    if (!empty($filters['filter_coach'])) {
        $count++;
    }
    if (!empty($filters['filter_group'])) {
        $count++;
    }
    return $count;
}
