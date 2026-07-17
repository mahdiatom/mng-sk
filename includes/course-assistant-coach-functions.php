<?php
/**
 * کمک‌مربی دوره: تعریف، ذخیره و محاسبه سهم از دستمزد مربی اصلی
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return string
 */
function sc_course_assistant_coaches_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_course_assistant_coaches';
}

/**
 * ایجاد جدول کمک‌مربی‌های دوره
 */
function sc_create_course_assistant_coaches_table() {
    global $wpdb;
    $table_name = sc_course_assistant_coaches_table();
    $collation = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `course_id` bigint(20) unsigned NOT NULL,
        `chapter_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'شعبه',
        `group_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'گروه (اجباری در صورت گروه‌بندی؛ هر گروه کمک‌مربی/درصد جدا)',
        `primary_coach_id` bigint(20) unsigned NOT NULL COMMENT 'مربی اصلی',
        `assistant_coach_id` bigint(20) unsigned NOT NULL COMMENT 'کمک‌مربی',
        `share_percentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'درصد از سهم مربی اصلی',
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_course_assistant_unique` (`course_id`,`chapter_name`,`group_name`,`primary_coach_id`,`assistant_coach_id`),
        KEY `idx_course_id` (`course_id`),
        KEY `idx_primary_coach` (`primary_coach_id`),
        KEY `idx_assistant_coach` (`assistant_coach_id`),
        KEY `idx_chapter_name` (`chapter_name`)
    ) ENGINE=InnoDB $collation";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * @return bool
 */
function sc_course_assistant_coaches_table_ready() {
    global $wpdb;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $t = sc_course_assistant_coaches_table();
    $ok = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t);
    return $ok;
}

/**
 * حالت واریز سهم کمک‌مربی: direct | via_primary
 *
 * @return string
 */
function sc_get_assistant_salary_payout_mode() {
    $mode = function_exists('sc_get_setting')
        ? (string) sc_get_setting('assistant_salary_payout_mode', 'direct')
        : 'direct';
    return in_array($mode, ['direct', 'via_primary'], true) ? $mode : 'direct';
}

/**
 * @param int $course_id
 * @return array<int, object>
 */
function sc_get_course_assistant_coaches($course_id) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !sc_course_assistant_coaches_table_ready()) {
        return [];
    }

    $t = sc_course_assistant_coaches_table();
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `$t` WHERE course_id = %d ORDER BY chapter_name ASC, primary_coach_id ASC, group_name ASC, id ASC",
        $course_id
    ));

    return is_array($rows) ? $rows : [];
}

/**
 * کمک‌مربی‌های یک مربی اصلی در یک شعبه (و در صورت نیاز گروه)
 *
 * @param int    $course_id
 * @param int    $primary_coach_id
 * @param string $chapter_name
 * @param string $group_name خالی = فقط ردیف‌های بدون گروه؛ مقداردار = فقط همان گروه
 * @return array<int, object>
 */
function sc_get_assistants_for_primary_coach($course_id, $primary_coach_id, $chapter_name, $group_name = '') {
    global $wpdb;
    $course_id = absint($course_id);
    $primary_coach_id = absint($primary_coach_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $group_name = sanitize_text_field((string) $group_name);

    if (
        function_exists('sc_is_pro_feature_assistant_coach_salary_enabled')
        && !sc_is_pro_feature_assistant_coach_salary_enabled()
    ) {
        return [];
    }

    if (!$course_id || !$primary_coach_id || $chapter_name === '' || !sc_course_assistant_coaches_table_ready()) {
        return [];
    }

    $t = sc_course_assistant_coaches_table();
    $coaches = $wpdb->prefix . 'sc_coaches';

    // تطبیق دقیق گروه: هر گروه کمک‌مربی و درصد جدا دارد
    if ($group_name !== '') {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, c.first_name, c.last_name, c.settlement_type, c.is_active
             FROM `$t` a
             INNER JOIN `$coaches` c ON c.id = a.assistant_coach_id
             WHERE a.course_id = %d
               AND a.primary_coach_id = %d
               AND a.chapter_name = %s
               AND a.group_name = %s
               AND a.share_percentage > 0
               AND c.is_active = 1
             ORDER BY a.id ASC",
            $course_id,
            $primary_coach_id,
            $chapter_name,
            $group_name
        ));
    } else {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, c.first_name, c.last_name, c.settlement_type, c.is_active
             FROM `$t` a
             INNER JOIN `$coaches` c ON c.id = a.assistant_coach_id
             WHERE a.course_id = %d
               AND a.primary_coach_id = %d
               AND a.chapter_name = %s
               AND a.group_name = ''
               AND a.share_percentage > 0
               AND c.is_active = 1
             ORDER BY a.id ASC",
            $course_id,
            $primary_coach_id,
            $chapter_name
        ));
    }

    return is_array($rows) ? $rows : [];
}

/**
 * آیا این مربی فقط کمک‌مربی این دوره/شعبه است (نه مربی اصلی)؟
 *
 * @param int    $course_id
 * @param int    $coach_id
 * @param string $chapter_name
 * @return bool
 */
function sc_coach_is_assistant_only_for_course_chapter($course_id, $coach_id, $chapter_name = '') {
    global $wpdb;
    $course_id = absint($course_id);
    $coach_id = absint($coach_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    if (!$course_id || !$coach_id || !sc_course_assistant_coaches_table_ready()) {
        return false;
    }

    $cc = $wpdb->prefix . 'sc_course_coaches';
    if ($chapter_name !== '') {
        $is_primary = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$cc` WHERE course_id = %d AND coach_id = %d AND chapter_name = %s",
            $course_id,
            $coach_id,
            $chapter_name
        ));
    } else {
        $is_primary = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$cc` WHERE course_id = %d AND coach_id = %d AND chapter_name != ''",
            $course_id,
            $coach_id
        ));
    }
    if ($is_primary > 0) {
        return false;
    }

    $t = sc_course_assistant_coaches_table();
    if ($chapter_name !== '') {
        $is_assistant = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$t` WHERE course_id = %d AND assistant_coach_id = %d AND chapter_name = %s",
            $course_id,
            $coach_id,
            $chapter_name
        ));
    } else {
        $is_assistant = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$t` WHERE course_id = %d AND assistant_coach_id = %d",
            $course_id,
            $coach_id
        ));
    }

    return $is_assistant > 0;
}

/**
 * نقش رکورد دستمزد: مربی اصلی یا کمک‌مربی (برای نمایش در گزارش مدیریت).
 *
 * @param int    $coach_id
 * @param int    $course_id
 * @param string $chapter_name
 * @param string $salary_type percentage|fixed
 * @return array{role:string,label:string,primary_coach_id:int,primary_coach_name:string}
 */
function sc_resolve_coach_salary_record_role($coach_id, $course_id, $chapter_name = '', $salary_type = 'percentage') {
    $out = [
        'role' => 'primary',
        'label' => 'مربی اصلی',
        'primary_coach_id' => 0,
        'primary_coach_name' => '',
    ];

    $coach_id = absint($coach_id);
    $course_id = absint($course_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $salary_type = sanitize_text_field((string) $salary_type);

    if (!$coach_id || $salary_type === 'fixed' || $course_id < 1 || !sc_course_assistant_coaches_table_ready()) {
        if ($salary_type === 'fixed') {
            $out['label'] = 'مربی';
        }
        return $out;
    }

    global $wpdb;
    $cc = $wpdb->prefix . 'sc_course_coaches';
    $is_primary = 0;
    if ($chapter_name !== '') {
        $is_primary = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$cc` WHERE course_id = %d AND coach_id = %d AND chapter_name = %s",
            $course_id,
            $coach_id,
            $chapter_name
        ));
    } else {
        $is_primary = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$cc` WHERE course_id = %d AND coach_id = %d",
            $course_id,
            $coach_id
        ));
    }
    if ($is_primary > 0) {
        return $out;
    }

    $t = sc_course_assistant_coaches_table();
    $coaches = $wpdb->prefix . 'sc_coaches';
    if ($chapter_name !== '') {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT a.primary_coach_id, c.first_name, c.last_name
             FROM `$t` a
             LEFT JOIN `$coaches` c ON c.id = a.primary_coach_id
             WHERE a.course_id = %d AND a.assistant_coach_id = %d AND a.chapter_name = %s
             ORDER BY a.id ASC
             LIMIT 1",
            $course_id,
            $coach_id,
            $chapter_name
        ));
    } else {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT a.primary_coach_id, c.first_name, c.last_name
             FROM `$t` a
             LEFT JOIN `$coaches` c ON c.id = a.primary_coach_id
             WHERE a.course_id = %d AND a.assistant_coach_id = %d
             ORDER BY a.id ASC
             LIMIT 1",
            $course_id,
            $coach_id
        ));
    }

    if (!$row) {
        return $out;
    }

    $primary_name = trim((string) ($row->first_name ?? '') . ' ' . (string) ($row->last_name ?? ''));
    return [
        'role' => 'assistant',
        'label' => 'کمک‌مربی',
        'primary_coach_id' => (int) ($row->primary_coach_id ?? 0),
        'primary_coach_name' => $primary_name,
    ];
}

/**
 * ذخیره کمک‌مربی‌ها از فرم دوره
 *
 * @param int      $course_id
 * @param string[] $allowed_chapters
 */
function sc_save_course_assistant_coaches_from_post($course_id, array $allowed_chapters) {
    global $wpdb;
    $course_id = absint($course_id);
    if (!$course_id || !sc_course_assistant_coaches_table_ready()) {
        return;
    }

    if (
        function_exists('sc_is_pro_feature_assistant_coach_salary_enabled')
        && !sc_is_pro_feature_assistant_coach_salary_enabled()
    ) {
        return;
    }
    if (empty($_POST['course_assistant_assign_present'])) {
        return;
    }

    $table = sc_course_assistant_coaches_table();
    $wpdb->delete($table, ['course_id' => $course_id], ['%d']);

    $raw = isset($_POST['course_assistant_row']) && is_array($_POST['course_assistant_row'])
        ? wp_unslash($_POST['course_assistant_row'])
        : [];
    if (empty($raw)) {
        return;
    }

    $primary_map = function_exists('sc_get_course_coach_assignments_map')
        ? sc_get_course_coach_assignments_map($course_id)
        : [];

    $has_grouping = function_exists('sc_course_has_grouping_enabled') && sc_course_has_grouping_enabled($course_id);
    $valid_group_names = [];
    if ($has_grouping && function_exists('sc_get_course_group_names')) {
        $valid_group_names = sc_get_course_group_names($course_id);
        if (empty($valid_group_names)) {
            $has_grouping = false;
        }
    }

    $now = current_time('mysql');
    $seen = [];

    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $chapter_name = isset($row['chapter_name']) ? sanitize_text_field((string) $row['chapter_name']) : '';
        $group_name = isset($row['group_name']) ? sanitize_text_field((string) $row['group_name']) : '';
        $primary_coach_id = isset($row['primary_coach_id']) ? absint($row['primary_coach_id']) : 0;
        $assistant_coach_id = isset($row['assistant_coach_id']) ? absint($row['assistant_coach_id']) : 0;
        $share = isset($row['share_percentage']) ? floatval($row['share_percentage']) : 0.0;

        if ($chapter_name === '' || !in_array($chapter_name, $allowed_chapters, true)) {
            continue;
        }
        if ($primary_coach_id < 1 || $assistant_coach_id < 1 || $primary_coach_id === $assistant_coach_id) {
            continue;
        }
        if ($share <= 0) {
            continue;
        }
        if ($share > 100) {
            $share = 100;
        }

        // اگر دوره گروه‌بندی دارد، گروه برای کمک‌مربی اجباری است
        if ($has_grouping) {
            if ($group_name === '' || !in_array($group_name, $valid_group_names, true)) {
                continue;
            }
        } else {
            $group_name = '';
        }

        // کمک‌مربی فقط برای مربی اصلی تعریف‌شده در همان شعبه
        if (empty($primary_map[$chapter_name][$primary_coach_id])) {
            continue;
        }
        // کمک‌مربی نباید خودش مربی اصلی همان شعبه باشد
        if (!empty($primary_map[$chapter_name][$assistant_coach_id])) {
            continue;
        }

        $uniq = $chapter_name . '|' . $group_name . '|' . $primary_coach_id . '|' . $assistant_coach_id;
        if (isset($seen[$uniq])) {
            continue;
        }
        $seen[$uniq] = true;

        $wpdb->insert(
            $table,
            [
                'course_id' => $course_id,
                'chapter_name' => $chapter_name,
                'group_name' => $group_name,
                'primary_coach_id' => $primary_coach_id,
                'assistant_coach_id' => $assistant_coach_id,
                'share_percentage' => $share,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%d', '%d', '%f', '%s', '%s']
        );
    }
}

/**
 * تقسیم مبلغ ناخالص مربی اصلی بین خودش و کمک‌مربی‌ها
 *
 * @param float $gross_amount
 * @param array<int, object> $assistants
 * @return array{primary_net:float, assistants:array<int, array{coach_id:int, share_percentage:float, amount:float, name:string}>}
 */
function sc_split_primary_salary_with_assistants($gross_amount, array $assistants) {
    $gross_amount = max(0, (float) $gross_amount);
    $out = [
        'primary_net' => $gross_amount,
        'assistants' => [],
    ];
    if ($gross_amount <= 0 || empty($assistants)) {
        return $out;
    }

    $total_share_pct = 0.0;
    foreach ($assistants as $a) {
        $pct = isset($a->share_percentage) ? (float) $a->share_percentage : 0.0;
        if ($pct <= 0) {
            continue;
        }
        $total_share_pct += $pct;
    }
    if ($total_share_pct <= 0) {
        return $out;
    }
    // اگر مجموع درصدها از ۱۰۰ بیشتر شد، نرمال‌سازی نسبی
    $scale = $total_share_pct > 100 ? (100 / $total_share_pct) : 1.0;

    $assistant_total = 0.0;
    foreach ($assistants as $a) {
        $pct = isset($a->share_percentage) ? (float) $a->share_percentage : 0.0;
        if ($pct <= 0) {
            continue;
        }
        $effective_pct = $pct * $scale;
        $amount = round(($gross_amount * $effective_pct) / 100, 2);
        if ($amount <= 0) {
            continue;
        }
        $name = trim((string) ($a->first_name ?? '') . ' ' . (string) ($a->last_name ?? ''));
        $out['assistants'][] = [
            'coach_id' => (int) $a->assistant_coach_id,
            'share_percentage' => $effective_pct,
            'amount' => $amount,
            'name' => $name,
        ];
        $assistant_total += $amount;
    }

    $out['primary_net'] = max(0, round($gross_amount - $assistant_total, 2));
    return $out;
}

/**
 * آیا دسترسی کمک‌مربی به ثبت حضور و غیاب فعال است؟ (تنظیمات مربی)
 * پیش‌فرض غیرفعال = رفتار قبلی (فقط مربی اصلی).
 *
 * @return bool
 */
function sc_assistant_coach_attendance_enabled() {
    if (!function_exists('sc_get_setting')) {
        return false;
    }
    return (int) sc_get_setting('assistant_coach_attendance_enabled', '0') === 1;
}

/**
 * شناسه مربی اصلی مؤثر برای محدوده حضور و غیاب.
 * اگر مربی فقط کمک‌مربی این دوره/شعبه باشد و دسترسی کمک‌مربی فعال باشد،
 * شناسه مربی اصلی برمی‌گردد (برای برنامه هفتگی و محدوده بازیکنان)؛ وگرنه همان شناسه ورودی.
 *
 * @param int    $course_id
 * @param int    $coach_id
 * @param string $chapter_name
 * @param string $group_name
 * @return int
 */
function sc_attendance_assistant_effective_primary_coach_id($course_id, $coach_id, $chapter_name = '', $group_name = '') {
    global $wpdb;
    $course_id = absint($course_id);
    $coach_id = absint($coach_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $group_name = sanitize_text_field((string) $group_name);

    if (!$course_id || !$coach_id || !sc_assistant_coach_attendance_enabled()) {
        return $coach_id;
    }
    if (!sc_coach_is_assistant_only_for_course_chapter($course_id, $coach_id, $chapter_name)) {
        return $coach_id;
    }

    $t = sc_course_assistant_coaches_table();
    $sql = "SELECT primary_coach_id FROM `$t` WHERE course_id = %d AND assistant_coach_id = %d";
    $args = [$course_id, $coach_id];
    if ($chapter_name !== '') {
        $sql .= ' AND chapter_name = %s';
        $args[] = $chapter_name;
    }
    if ($group_name !== '') {
        $sql .= ' ORDER BY (group_name = %s) DESC, id ASC';
        $args[] = $group_name;
    } else {
        $sql .= ' ORDER BY id ASC';
    }
    $sql .= ' LIMIT 1';

    $primary = (int) $wpdb->get_var($wpdb->prepare($sql, $args));
    return $primary > 0 ? $primary : $coach_id;
}

/**
 * SQL برای نمایش نام ثبت‌کننده حضور و غیاب (با برچسب کمک‌مربی در صورت نیاز).
 *
 * @param string $attendance_alias
 * @param string $coach_alias
 * @param string $user_alias
 * @return string
 */
function sc_attendance_recorded_by_name_sql($attendance_alias = 'a', $coach_alias = 'rec_coach', $user_alias = 'rec_user') {
    $attendance_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $attendance_alias);
    $coach_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $coach_alias);
    $user_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $user_alias);
    $base = "COALESCE(CONCAT({$coach_alias}.first_name, ' ', {$coach_alias}.last_name), {$user_alias}.display_name, '-')";
    return "CASE WHEN {$attendance_alias}.recorded_by_assistant = 1 THEN CONCAT({$base}, ' (کمک‌مربی)') ELSE {$base} END";
}

/**
 * آیا ثبت‌کننده فعلی برای این دوره/شعبه کمک‌مربی است؟
 *
 * @param int    $course_id
 * @param int    $coach_id
 * @param string $chapter_name
 * @return bool
 */
function sc_attendance_is_assistant_recorder($course_id, $coach_id, $chapter_name = '') {
    if (!$course_id || !$coach_id || !sc_assistant_coach_attendance_enabled()) {
        return false;
    }
    return sc_coach_is_assistant_only_for_course_chapter($course_id, $coach_id, $chapter_name);
}

/**
 * آیا مربی (اصلی یا کمک‌مربی مجاز) می‌تواند برای این دوره/شعبه/گروه حضور ثبت کند؟
 *
 * @param int    $course_id
 * @param int    $coach_id
 * @param string $chapter_name
 * @param string $group_name
 * @return bool
 */
function sc_coach_can_manage_attendance_for_course_chapter($course_id, $coach_id, $chapter_name = '', $group_name = '') {
    global $wpdb;
    $course_id = absint($course_id);
    $coach_id = absint($coach_id);
    $chapter_name = sanitize_text_field((string) $chapter_name);
    $group_name = sanitize_text_field((string) $group_name);

    if (!$course_id || !$coach_id) {
        return false;
    }

    $cc = $wpdb->prefix . 'sc_course_coaches';
    if ($chapter_name !== '') {
        $is_primary = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$cc` WHERE course_id = %d AND coach_id = %d AND chapter_name = %s",
            $course_id,
            $coach_id,
            $chapter_name
        )) > 0;
    } else {
        $is_primary = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$cc` WHERE course_id = %d AND coach_id = %d AND chapter_name != ''",
            $course_id,
            $coach_id
        )) > 0;
    }
    if ($is_primary) {
        return true;
    }

    if (!sc_assistant_coach_attendance_enabled() || !sc_course_assistant_coaches_table_ready()) {
        return false;
    }

    if (function_exists('sc_course_has_grouping_enabled') && sc_course_has_grouping_enabled($course_id) && $group_name === '') {
        return false;
    }

    $t = sc_course_assistant_coaches_table();
    $sql = "SELECT COUNT(*) FROM `$t` WHERE course_id = %d AND assistant_coach_id = %d";
    $args = [$course_id, $coach_id];
    if ($chapter_name !== '') {
        $sql .= ' AND chapter_name = %s';
        $args[] = $chapter_name;
    }
    if ($group_name !== '') {
        $sql .= ' AND group_name = %s';
        $args[] = $group_name;
    }

    return (int) $wpdb->get_var($wpdb->prepare($sql, $args)) > 0;
}

/**
 * شناسه دوره‌هایی که مربی در آن‌ها کمک‌مربی است (برای دسترسی حضور و غیاب).
 * فقط وقتی دسترسی کمک‌مربی به حضور و غیاب فعال باشد مقدار برمی‌گرداند.
 *
 * @param int $coach_id
 * @return int[]
 */
function sc_coach_assistant_attendance_course_ids($coach_id) {
    global $wpdb;
    $coach_id = absint($coach_id);
    if (!$coach_id || !sc_assistant_coach_attendance_enabled() || !sc_course_assistant_coaches_table_ready()) {
        return [];
    }
    $t = sc_course_assistant_coaches_table();
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT course_id FROM `$t` WHERE assistant_coach_id = %d",
        $coach_id
    ));
    return array_values(array_filter(array_map('absint', (array) $ids)));
}

/**
 * شناسه دوره‌هایی که مربی (اصلی یا کمک‌مربی) به حضور و غیاب آن‌ها دسترسی دارد.
 *
 * @param int $coach_id
 * @return int[]
 */
function sc_coach_attendance_accessible_course_ids($coach_id) {
    global $wpdb;
    $coach_id = absint($coach_id);
    if (!$coach_id) {
        return [];
    }

    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT course_id FROM $course_coaches_table WHERE coach_id = %d",
        $coach_id
    ));
    $ids = array_map('absint', (array) $ids);

    if (function_exists('sc_coach_assistant_attendance_course_ids')) {
        $ids = array_merge($ids, sc_coach_assistant_attendance_course_ids($coach_id));
    }

    return array_values(array_unique(array_filter($ids)));
}
