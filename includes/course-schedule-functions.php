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
        foreach ($wd as $d) {
            $sort++;
            $wpdb->insert(
                $table,
                [
                    'course_id' => $course_id,
                    'weekday' => $d,
                    'time_start' => $ts,
                    'time_end' => $te,
                    'sort_order' => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%s', '%s', '%d', '%s', '%s']
            );
        }
    }
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
 * برای فرم ادمین: ادغام ردیف‌های هم‌زمان به یک بلوک با چند روز
 *
 * @param array<int,object> $rows
 * @return array<int,array{start:string,end:string,wd:array<int,int>}>
 */
function sc_group_course_schedule_for_form($rows) {
    $groups = [];
    foreach ($rows as $r) {
        if (!isset($r->time_start, $r->time_end, $r->weekday)) {
            continue;
        }
        $k = $r->time_start . '|' . $r->time_end;
        if (!isset($groups[$k])) {
            $groups[$k] = [
                'start' => $r->time_start,
                'end' => $r->time_end,
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

    $sql = "SELECT s.id AS schedule_id, s.weekday, s.time_start, s.time_end, c.id AS course_id, c.title AS course_title
        FROM {$mc} mc
        INNER JOIN {$c} c ON c.id = mc.course_id AND c.deleted_at IS NULL
        INNER JOIN {$sch} s ON s.course_id = c.id
        WHERE mc.member_id = %d
          AND mc.status IN ('active','inactive')
          AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '' OR TRIM(mc.course_status_flags) = '')
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
        ];
    }

    return ['days' => $labels, 'cells' => $cells];
}
