<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * امتیازدهی مربیان توسط بازیکنان
 */

function sc_coach_rating_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_coach_ratings';
}

function sc_coach_rating_table_ready() {
    global $wpdb;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $t = sc_coach_rating_table();
    $ok = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t);
    return $ok;
}

function sc_coach_rating_get_member_display_name($member_id) {
    global $wpdb;
    $member_id = absint($member_id);
    if (!$member_id) {
        return '';
    }
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT first_name, last_name FROM {$wpdb->prefix}sc_members WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!$row) {
        return 'بازیکن #' . $member_id;
    }
    $name = trim((string) $row->first_name . ' ' . (string) $row->last_name);
    return $name !== '' ? $name : ('بازیکن #' . $member_id);
}

/**
 * مربیان قابل امتیازدهی برای یک بازیکن (مربی اصلی + کمک‌مربی‌ها)
 *
 * @return array<int, array<string, mixed>>
 */
function sc_coach_rating_get_rateable_coaches_for_member($member_id) {
    global $wpdb;
    $member_id = absint($member_id);
    if (!$member_id) {
        return [];
    }

    $mc_table = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $enrollments = $wpdb->get_results($wpdb->prepare(
        "SELECT mc.course_id, mc.chapter, mc.group_name, mc.coach_id, c.title AS course_title
         FROM $mc_table mc
         INNER JOIN $courses_table c ON c.id = mc.course_id
         WHERE mc.member_id = %d
           AND mc.status = 'active'
           AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '' OR mc.course_status_flags = ' ')
           AND c.deleted_at IS NULL
           AND c.is_active = 1
           AND mc.coach_id > 0",
        $member_id
    ));

    if (empty($enrollments)) {
        return [];
    }

    $targets = [];
    $seen = [];

    foreach ($enrollments as $enrollment) {
        $course_id = (int) $enrollment->course_id;
        $primary_coach_id = (int) $enrollment->coach_id;
        $chapter = sanitize_text_field((string) ($enrollment->chapter ?? ''));
        $group_name = sanitize_text_field((string) ($enrollment->group_name ?? ''));

        if ($primary_coach_id > 0) {
            $key = $course_id . '|' . $primary_coach_id . '|primary|' . $chapter . '|' . $group_name;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $coach = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, first_name, last_name, personal_photo, is_active
                     FROM $coaches_table WHERE id = %d LIMIT 1",
                    $primary_coach_id
                ));
                if ($coach && (int) $coach->is_active === 1) {
                    $targets[] = [
                        'coach_id'     => (int) $coach->id,
                        'coach_name'   => trim($coach->first_name . ' ' . $coach->last_name),
                        'coach_photo'  => (string) ($coach->personal_photo ?? ''),
                        'course_id'    => $course_id,
                        'course_title' => (string) $enrollment->course_title,
                        'chapter'      => $chapter,
                        'group_name'   => $group_name,
                        'coach_role'   => 'primary',
                        'role_label'   => 'مربی',
                    ];
                }
            }
        }

        if ($primary_coach_id > 0 && $chapter !== '' && function_exists('sc_get_assistants_for_primary_coach')) {
            $assistants = sc_get_assistants_for_primary_coach($course_id, $primary_coach_id, $chapter, $group_name);
            if (empty($assistants) && function_exists('sc_course_assistant_coaches_table_ready') && sc_course_assistant_coaches_table_ready()) {
                $assistant_table = $wpdb->prefix . 'sc_course_assistant_coaches';
                $assistants = $wpdb->get_results($wpdb->prepare(
                    "SELECT a.*, c.first_name, c.last_name
                     FROM `$assistant_table` a
                     INNER JOIN $coaches_table c ON c.id = a.assistant_coach_id
                     WHERE a.course_id = %d
                       AND a.primary_coach_id = %d
                       AND a.chapter_name = %s
                       AND (%s = '' OR a.group_name = %s)
                       AND c.is_active = 1
                     ORDER BY a.id ASC",
                    $course_id,
                    $primary_coach_id,
                    $chapter,
                    $group_name,
                    $group_name
                ));
            }
            foreach ((array) $assistants as $assistant) {
                $assistant_id = (int) ($assistant->assistant_coach_id ?? 0);
                if ($assistant_id <= 0) {
                    continue;
                }
                $key = $course_id . '|' . $assistant_id . '|assistant|' . $chapter . '|' . $group_name;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $assistant_name = trim(($assistant->first_name ?? '') . ' ' . ($assistant->last_name ?? ''));
                $targets[] = [
                    'coach_id'     => $assistant_id,
                    'coach_name'   => $assistant_name !== '' ? $assistant_name : ('مربی #' . $assistant_id),
                    'coach_photo'  => '',
                    'course_id'    => $course_id,
                    'course_title' => (string) $enrollment->course_title,
                    'chapter'      => $chapter,
                    'group_name'   => $group_name,
                    'coach_role'   => 'assistant',
                    'role_label'   => 'کمک‌مربی',
                ];
            }
        }
    }

    return $targets;
}

function sc_coach_rating_target_key($target) {
    return implode('|', [
        (int) ($target['coach_id'] ?? 0),
        (int) ($target['course_id'] ?? 0),
        sanitize_text_field((string) ($target['chapter'] ?? '')),
        sanitize_text_field((string) ($target['group_name'] ?? '')),
        sanitize_key((string) ($target['coach_role'] ?? 'primary')),
    ]);
}

function sc_coach_rating_get_member_ratings_map($member_id) {
    global $wpdb;
    $member_id = absint($member_id);
    if (!$member_id || !sc_coach_rating_table_ready()) {
        return [];
    }
    $table = sc_coach_rating_table();
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE member_id = %d",
        $member_id
    ));
    $map = [];
    foreach ((array) $rows as $row) {
        $key = implode('|', [
            (int) $row->coach_id,
            (int) $row->course_id,
            sanitize_text_field((string) $row->chapter),
            sanitize_text_field((string) $row->group_name),
            sanitize_key((string) $row->coach_role),
        ]);
        $map[$key] = $row;
    }
    return $map;
}

function sc_coach_rating_member_has_pending($member_id) {
    if (!sc_is_pro_feature_coach_rating_enabled()) {
        return false;
    }
    $targets = sc_coach_rating_get_rateable_coaches_for_member($member_id);
    if (empty($targets)) {
        return false;
    }
    $existing = sc_coach_rating_get_member_ratings_map($member_id);
    foreach ($targets as $target) {
        if (!isset($existing[sc_coach_rating_target_key($target)])) {
            return true;
        }
    }
    return false;
}

function sc_coach_rating_member_pending_count($member_id) {
    if (!sc_is_pro_feature_coach_rating_enabled()) {
        return 0;
    }
    $targets = sc_coach_rating_get_rateable_coaches_for_member($member_id);
    if (empty($targets)) {
        return 0;
    }
    $existing = sc_coach_rating_get_member_ratings_map($member_id);
    $count = 0;
    foreach ($targets as $target) {
        if (!isset($existing[sc_coach_rating_target_key($target)])) {
            $count++;
        }
    }
    return $count;
}

function sc_coach_rating_save($member_id, $target, $rating, $comment = '') {
    global $wpdb;
    $member_id = absint($member_id);
    if (!$member_id || !sc_coach_rating_table_ready()) {
        return ['success' => false, 'message' => 'سیستم امتیازدهی در دسترس نیست.'];
    }

    $coach_id = absint($target['coach_id'] ?? 0);
    $course_id = absint($target['course_id'] ?? 0);
    $chapter = sanitize_text_field((string) ($target['chapter'] ?? ''));
    $group_name = sanitize_text_field((string) ($target['group_name'] ?? ''));
    $coach_role = sanitize_key((string) ($target['coach_role'] ?? 'primary'));
    if (!in_array($coach_role, ['primary', 'assistant'], true)) {
        $coach_role = 'primary';
    }

    $rating = (int) $rating;
    if ($rating < 1 || $rating > 5) {
        return ['success' => false, 'message' => 'لطفاً امتیاز بین ۱ تا ۵ را انتخاب کنید.'];
    }

    $comment = sanitize_textarea_field((string) $comment);

    $rateable = sc_coach_rating_get_rateable_coaches_for_member($member_id);
    $valid = false;
    foreach ($rateable as $item) {
        if (sc_coach_rating_target_key($item) === sc_coach_rating_target_key([
            'coach_id'   => $coach_id,
            'course_id'  => $course_id,
            'chapter'    => $chapter,
            'group_name' => $group_name,
            'coach_role' => $coach_role,
        ])) {
            $valid = true;
            break;
        }
    }
    if (!$valid) {
        return ['success' => false, 'message' => 'مربی انتخاب‌شده برای شما معتبر نیست.'];
    }

    $table = sc_coach_rating_table();
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table
         WHERE member_id = %d AND coach_id = %d AND course_id = %d
           AND chapter = %s AND group_name = %s AND coach_role = %s
         LIMIT 1",
        $member_id,
        $coach_id,
        $course_id,
        $chapter,
        $group_name,
        $coach_role
    ));
    if ($existing) {
        return ['success' => false, 'message' => 'امتیاز این مربی قبلاً ثبت شده و قابل ویرایش نیست.'];
    }

    $now = current_time('mysql');
    $inserted = $wpdb->insert($table, [
        'member_id'   => $member_id,
        'coach_id'    => $coach_id,
        'course_id'   => $course_id,
        'chapter'     => $chapter,
        'group_name'  => $group_name,
        'coach_role'  => $coach_role,
        'rating'      => $rating,
        'comment'     => $comment,
        'created_at'  => $now,
        'updated_at'  => $now,
    ], ['%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);

    if (!$inserted) {
        return ['success' => false, 'message' => 'خطا در ثبت امتیاز. لطفاً دوباره تلاش کنید.'];
    }

    return ['success' => true, 'message' => 'امتیاز شما با موفقیت ثبت شد. از مشارکت شما سپاسگزاریم.'];
}

function sc_coach_rating_get_coach_average($coach_id) {
    global $wpdb;
    $coach_id = absint($coach_id);
    if (!$coach_id || !sc_coach_rating_table_ready()) {
        return ['average' => 0, 'count' => 0];
    }
    $table = sc_coach_rating_table();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT AVG(rating) AS avg_rating, COUNT(*) AS rating_count FROM $table WHERE coach_id = %d",
        $coach_id
    ));
    return [
        'average' => $row ? round((float) $row->avg_rating, 1) : 0,
        'count'   => $row ? (int) $row->rating_count : 0,
    ];
}

function sc_coach_rating_render_stars_html($rating, $max = 5) {
    $rating = max(0, min((float) $rating, (float) $max));
    $full = (int) floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = max(0, $max - $full - $half);
    $html = '<span class="sc-coach-rating-stars" aria-label="' . esc_attr($rating . ' از ' . $max) . '">';
    for ($i = 0; $i < $full; $i++) {
        $html .= '<span class="sc-coach-rating-star is-full" aria-hidden="true">★</span>';
    }
    if ($half) {
        $html .= '<span class="sc-coach-rating-star is-half" aria-hidden="true">★</span>';
    }
    for ($i = 0; $i < $empty; $i++) {
        $html .= '<span class="sc-coach-rating-star is-empty" aria-hidden="true">★</span>';
    }
    $html .= '</span>';
    return $html;
}

/**
 * @return array<int, object>
 */
function sc_coach_rating_get_coach_ratings($coach_id, $args = []) {
    global $wpdb;
    $coach_id = absint($coach_id);
    if (!$coach_id || !sc_coach_rating_table_ready()) {
        return [];
    }

    $table = sc_coach_rating_table();
    $courses_table = $wpdb->prefix . 'sc_courses';
    $members_table = $wpdb->prefix . 'sc_members';

    $where = ['r.coach_id = %d'];
    $params = [$coach_id];

    if (!empty($args['date_from'])) {
        $where[] = 'DATE(r.created_at) >= %s';
        $params[] = sanitize_text_field($args['date_from']);
    }
    if (!empty($args['date_to'])) {
        $where[] = 'DATE(r.created_at) <= %s';
        $params[] = sanitize_text_field($args['date_to']);
    }

    $limit = isset($args['limit']) ? absint($args['limit']) : 0;
    $sql = "SELECT r.*,
                   m.first_name, m.last_name,
                   c.title AS course_title
            FROM $table r
            LEFT JOIN $members_table m ON m.id = r.member_id
            LEFT JOIN $courses_table c ON c.id = r.course_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.created_at DESC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }

    return $wpdb->get_results($wpdb->prepare($sql, ...$params));
}

/**
 * @return array<int, object>
 */
function sc_coach_rating_get_all_coaches_summary() {
    global $wpdb;
    if (!sc_coach_rating_table_ready()) {
        return [];
    }

    $table = sc_coach_rating_table();
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    return $wpdb->get_results(
        "SELECT c.id, c.first_name, c.last_name, c.personal_photo, c.is_active,
                ROUND(AVG(r.rating), 1) AS avg_rating,
                COUNT(r.id) AS rating_count
         FROM $coaches_table c
         LEFT JOIN $table r ON r.coach_id = c.id
         WHERE c.is_active = 1
         GROUP BY c.id
         ORDER BY avg_rating DESC, rating_count DESC, c.first_name ASC, c.last_name ASC"
    );
}

function sc_coach_rating_role_label($role) {
    return $role === 'assistant' ? 'کمک‌مربی' : 'مربی';
}
