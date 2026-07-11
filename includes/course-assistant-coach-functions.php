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
