<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Max upload size in bytes (50 MB). */
if (!defined('SC_PROGRAM_MAX_UPLOAD_BYTES')) {
    define('SC_PROGRAM_MAX_UPLOAD_BYTES', 50 * 1024 * 1024);
}

function sc_program_library_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_program_library_items';
}
function sc_program_templates_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_program_templates';
}
function sc_program_template_days_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_program_template_days';
}
function sc_program_template_day_items_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_program_template_day_items';
}
function sc_member_programs_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_member_programs';
}
function sc_member_program_days_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_member_program_days';
}
function sc_member_program_day_items_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_member_program_day_items';
}
function sc_member_program_completions_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_member_program_completions';
}

function sc_program_today_ymd() {
    return current_time('Y-m-d');
}

function sc_program_upload_dir() {
    $upload = wp_upload_dir();
    $dir = trailingslashit($upload['basedir']) . 'sportclub-programs';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
        if (!file_exists($dir . '/index.php')) {
            file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
        }
    }
    return [
        'path' => $dir,
        'url'  => trailingslashit($upload['baseurl']) . 'sportclub-programs',
    ];
}

function sc_program_file_public_url($relative_or_path) {
    if ($relative_or_path === '' || $relative_or_path === null) {
        return '';
    }
    if (preg_match('#^https?://#i', $relative_or_path)) {
        return $relative_or_path;
    }
    $dir = sc_program_upload_dir();
    $base = basename($relative_or_path);
    return trailingslashit($dir['url']) . $base;
}

function sc_program_allowed_upload_mimes() {
    return [
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'mov'  => 'video/quicktime',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
    ];
}

function sc_program_normalize_media_type($type) {
    $type = sanitize_key((string) $type);
    return in_array($type, ['none', 'aparat', 'url', 'file'], true) ? $type : 'none';
}

function sc_program_normalize_schedule_mode($mode) {
    $mode = sanitize_key((string) $mode);
    return in_array($mode, ['fixed', 'manual', 'weekly'], true) ? $mode : 'fixed';
}

function sc_program_normalize_weekly_mask($mask) {
    if (is_string($mask)) {
        $decoded = json_decode($mask, true);
        $mask = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($mask)) {
        return [];
    }
    $out = [];
    $legacy_php_w = true;
    foreach ($mask as $d) {
        $d = (int) $d;
        // IR week: 1=شنبه … 7=جمعه (preferred)
        if ($d >= 1 && $d <= 7) {
            $out[] = $d;
            if ($d === 7) {
                $legacy_php_w = false;
            }
        } elseif ($d === 0) {
            // Legacy PHP date('w') Sunday — keep for old data until resaved
            $out[] = 0;
        }
    }
    // If values look like IR (has 7 or all in 1-7 without 0), keep as IR
    $has_zero = in_array(0, $out, true);
    $has_seven = in_array(7, $out, true);
    if ($has_zero && !$has_seven) {
        // Convert legacy PHP w (0-6) → IR (1-7)
        $converted = [];
        foreach ($out as $w) {
            // PHP w → IR: Sat(6)→1, Sun(0)→2, … Fri(5)→7
            $converted[] = (($w + 1) % 7) + 1;
        }
        $out = $converted;
    }
    $out = array_values(array_unique(array_filter($out, static function ($d) {
        return $d >= 1 && $d <= 7;
    })));
    sort($out);
    return $out;
}

function sc_program_weekday_labels_ir() {
    if (function_exists('sc_course_weekday_labels_ir')) {
        return sc_course_weekday_labels_ir();
    }
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

function sc_program_ir_weekday_from_ymd($ymd) {
    if (function_exists('sc_course_ir_weekday_from_gregorian_ymd')) {
        return (int) sc_course_ir_weekday_from_gregorian_ymd($ymd);
    }
    $ts = strtotime($ymd . ' 12:00:00');
    if (!$ts) {
        return 0;
    }
    $w = (int) date('w', $ts);
    return (($w + 1) % 7) + 1;
}

function sc_program_get_member_user_id($member_id) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        'SELECT user_id FROM ' . $wpdb->prefix . 'sc_members WHERE id = %d LIMIT 1',
        absint($member_id)
    ));
}

function sc_program_staff_context($user_id = 0) {
    if ($user_id <= 0) {
        $user_id = get_current_user_id();
    }
    $ctx = [
        'user_id' => (int) $user_id,
        'role' => 'none',
        'coach_id' => 0,
        'is_admin' => false,
        'is_coach' => false,
        'is_secretary' => false,
    ];
    if ($user_id <= 0) {
        return $ctx;
    }
    if (user_can($user_id, 'manage_options')) {
        $ctx['role'] = 'admin';
        $ctx['is_admin'] = true;
        return $ctx;
    }
    if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only($user_id)) {
        $ctx['role'] = 'secretary';
        $ctx['is_secretary'] = true;
        return $ctx;
    }
    if (function_exists('sc_support_get_coach_id_by_user_id')) {
        $coach_id = (int) sc_support_get_coach_id_by_user_id($user_id);
        if ($coach_id > 0) {
            $ctx['role'] = 'coach';
            $ctx['is_coach'] = true;
            $ctx['coach_id'] = $coach_id;
            return $ctx;
        }
    }
    return $ctx;
}

function sc_program_can_manage_staff($user_id = 0) {
    $ctx = sc_program_staff_context($user_id);
    return $ctx['is_admin'] || $ctx['is_coach'] || $ctx['is_secretary'];
}

function sc_program_can_access_member($member_id, $user_id = 0) {
    $member_id = absint($member_id);
    if ($member_id <= 0) {
        return false;
    }
    $ctx = sc_program_staff_context($user_id);
    if ($ctx['is_admin']) {
        return true;
    }
    if ($ctx['is_secretary'] && function_exists('sc_secretary_can_access_member')) {
        return sc_secretary_can_access_member($member_id);
    }
    if ($ctx['is_coach'] && function_exists('sc_private_notes_get_member_ids_for_coach')) {
        $allowed = sc_private_notes_get_member_ids_for_coach($ctx['coach_id']);
        return in_array($member_id, $allowed, true);
    }
    return false;
}

function sc_program_filter_member_ids(array $member_ids, $user_id = 0) {
    $member_ids = array_values(array_unique(array_filter(array_map('absint', $member_ids))));
    $ctx = sc_program_staff_context($user_id);
    if ($ctx['is_admin'] || empty($member_ids)) {
        return $member_ids;
    }
    $out = [];
    foreach ($member_ids as $mid) {
        if (sc_program_can_access_member($mid, $user_id)) {
            $out[] = $mid;
        }
    }
    return $out;
}

function sc_program_player_owns_program($program, $user_id = 0) {
    if (!$program) {
        return false;
    }
    if ($user_id <= 0) {
        $user_id = get_current_user_id();
    }
    return $user_id > 0 && (int) $program->user_id === (int) $user_id;
}

/* ===================== Library ===================== */

function sc_program_library_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_program_library_table() . ' WHERE id = %d LIMIT 1',
        absint($id)
    ));
}

function sc_program_library_query($args = []) {
    global $wpdb;
    $table = sc_program_library_table();
    $search = isset($args['search']) ? sanitize_text_field($args['search']) : '';
    $per_page = isset($args['per_page']) ? max(1, (int) $args['per_page']) : 50;
    $page = isset($args['page']) ? max(1, (int) $args['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $where = ['1=1'];
    $values = [];
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(title LIKE %s OR description LIKE %s)';
        $values[] = $like;
        $values[] = $like;
    }
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
    $total = (int) ($values ? $wpdb->get_var($wpdb->prepare($count_sql, ...$values)) : $wpdb->get_var($count_sql));
    $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY updated_at DESC LIMIT %d OFFSET %d";
    $values[] = $per_page;
    $values[] = $offset;
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values));
    return ['rows' => $rows ?: [], 'total' => $total, 'page' => $page, 'per_page' => $per_page];
}

function sc_program_library_save($data, $id = 0) {
    global $wpdb;
    $now = current_time('mysql');
    $row = [
        'title' => sanitize_text_field($data['title'] ?? ''),
        'description' => wp_kses_post($data['description'] ?? ''),
        'media_type' => sc_program_normalize_media_type($data['media_type'] ?? 'none'),
        'media_url' => esc_url_raw($data['media_url'] ?? ''),
        'file_path' => sanitize_text_field($data['file_path'] ?? ''),
        'file_name' => sanitize_file_name($data['file_name'] ?? ''),
        'updated_at' => $now,
    ];
    if ($row['title'] === '') {
        return new WP_Error('invalid', 'عنوان الزامی است.');
    }
    if ($row['media_type'] === 'file') {
        $row['media_url'] = '';
    } elseif (in_array($row['media_type'], ['aparat', 'url'], true)) {
        $row['file_path'] = '';
        $row['file_name'] = '';
    } else {
        $row['media_url'] = '';
        $row['file_path'] = '';
        $row['file_name'] = '';
    }
    $id = absint($id);
    if ($id > 0) {
        $ok = $wpdb->update(sc_program_library_table(), $row, ['id' => $id]);
        return ($ok === false) ? new WP_Error('db', 'خطا در ذخیره.') : $id;
    }
    $row['created_by_user_id'] = get_current_user_id();
    $row['created_at'] = $now;
    $ok = $wpdb->insert(sc_program_library_table(), $row);
    return $ok ? (int) $wpdb->insert_id : new WP_Error('db', 'خطا در ذخیره.');
}

function sc_program_library_delete($id) {
    global $wpdb;
    $item = sc_program_library_get($id);
    if (!$item) {
        return false;
    }
    if (!empty($item->file_path)) {
        $dir = sc_program_upload_dir();
        $full = trailingslashit($dir['path']) . basename($item->file_path);
        if (file_exists($full)) {
            @unlink($full);
        }
    }
    return false !== $wpdb->delete(sc_program_library_table(), ['id' => absint($id)], ['%d']);
}

function sc_program_snapshot_from_library($library_item_id) {
    $item = sc_program_library_get($library_item_id);
    if (!$item) {
        return null;
    }
    return [
        'source_library_item_id' => (int) $item->id,
        'library_item_id' => (int) $item->id,
        'title' => (string) $item->title,
        'description' => (string) $item->description,
        'media_type' => sc_program_normalize_media_type($item->media_type),
        'media_url' => (string) $item->media_url,
        'file_path' => (string) $item->file_path,
        'file_name' => (string) $item->file_name,
    ];
}

/* ===================== Templates ===================== */

function sc_program_template_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_program_templates_table() . ' WHERE id = %d LIMIT 1',
        absint($id)
    ));
}

function sc_program_template_query($args = []) {
    global $wpdb;
    $table = sc_program_templates_table();
    $search = isset($args['search']) ? sanitize_text_field($args['search']) : '';
    $status = isset($args['status']) ? sanitize_key($args['status']) : '';
    $per_page = isset($args['per_page']) ? max(1, (int) $args['per_page']) : 50;
    $page = isset($args['page']) ? max(1, (int) $args['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $where = ['1=1'];
    $values = [];
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(title LIKE %s OR description LIKE %s)';
        $values[] = $like;
        $values[] = $like;
    }
    if ($status !== '') {
        $where[] = 'status = %s';
        $values[] = $status;
    }
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
    $total = (int) ($values ? $wpdb->get_var($wpdb->prepare($count_sql, ...$values)) : $wpdb->get_var($count_sql));
    $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY updated_at DESC LIMIT %d OFFSET %d";
    $values[] = $per_page;
    $values[] = $offset;
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values));
    return ['rows' => $rows ?: [], 'total' => $total];
}

function sc_program_template_save($data, $id = 0) {
    global $wpdb;
    $now = current_time('mysql');
    $mask = sc_program_normalize_weekly_mask($data['weekly_mask'] ?? []);
    $row = [
        'title' => sanitize_text_field($data['title'] ?? ''),
        'description' => sanitize_textarea_field($data['description'] ?? ''),
        'schedule_mode' => sc_program_normalize_schedule_mode($data['schedule_mode'] ?? 'fixed'),
        'duration_days' => max(1, (int) ($data['duration_days'] ?? 7)),
        'weekly_mask' => wp_json_encode($mask),
        'status' => in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active',
        'updated_at' => $now,
    ];
    if ($row['title'] === '') {
        return new WP_Error('invalid', 'عنوان قالب الزامی است.');
    }
    $id = absint($id);
    if ($id > 0) {
        $ok = $wpdb->update(sc_program_templates_table(), $row, ['id' => $id]);
        return ($ok === false) ? new WP_Error('db', 'خطا در ذخیره قالب.') : $id;
    }
    $row['created_by_user_id'] = get_current_user_id();
    $row['created_at'] = $now;
    $ok = $wpdb->insert(sc_program_templates_table(), $row);
    return $ok ? (int) $wpdb->insert_id : new WP_Error('db', 'خطا در ذخیره قالب.');
}

function sc_program_template_get_days($template_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . sc_program_template_days_table() . ' WHERE template_id = %d ORDER BY day_offset ASC',
        absint($template_id)
    )) ?: [];
}

function sc_program_template_get_day_items($template_day_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . sc_program_template_day_items_table() . ' WHERE template_day_id = %d ORDER BY sort_order ASC, id ASC',
        absint($template_day_id)
    )) ?: [];
}

function sc_program_template_ensure_day($template_id, $day_offset, $title = '') {
    global $wpdb;
    $template_id = absint($template_id);
    $day_offset = max(0, (int) $day_offset);
    $existing = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_program_template_days_table() . ' WHERE template_id = %d AND day_offset = %d LIMIT 1',
        $template_id,
        $day_offset
    ));
    if ($existing) {
        if ($title !== '' && (string) $existing->title !== $title) {
            $wpdb->update(sc_program_template_days_table(), ['title' => sanitize_text_field($title)], ['id' => (int) $existing->id]);
            $existing->title = sanitize_text_field($title);
        }
        return $existing;
    }
    $wpdb->insert(sc_program_template_days_table(), [
        'template_id' => $template_id,
        'day_offset' => $day_offset,
        'title' => sanitize_text_field($title !== '' ? $title : ('روز ' . ($day_offset + 1))),
        'created_at' => current_time('mysql'),
    ]);
    return $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_program_template_days_table() . ' WHERE id = %d',
        (int) $wpdb->insert_id
    ));
}

function sc_program_template_add_item($template_day_id, $item_data) {
    global $wpdb;
    $template_day_id = absint($template_day_id);
    if ($template_day_id <= 0) {
        return new WP_Error('invalid', 'روز قالب نامعتبر است.');
    }
    $snap = null;
    $lib_id = absint($item_data['library_item_id'] ?? 0);
    if ($lib_id > 0) {
        $snap = sc_program_snapshot_from_library($lib_id);
        if (!$snap) {
            return new WP_Error('not_found', 'آیتم کتابخانه یافت نشد.');
        }
    }
    $max_sort = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT MAX(sort_order) FROM ' . sc_program_template_day_items_table() . ' WHERE template_day_id = %d',
        $template_day_id
    ));
    $row = [
        'template_day_id' => $template_day_id,
        'library_item_id' => $snap ? $snap['library_item_id'] : null,
        'title' => sanitize_text_field($snap ? $snap['title'] : ($item_data['title'] ?? '')),
        'description' => wp_kses_post($snap ? $snap['description'] : ($item_data['description'] ?? '')),
        'media_type' => sc_program_normalize_media_type($snap ? $snap['media_type'] : ($item_data['media_type'] ?? 'none')),
        'media_url' => esc_url_raw($snap ? $snap['media_url'] : ($item_data['media_url'] ?? '')),
        'file_path' => sanitize_text_field($snap ? $snap['file_path'] : ($item_data['file_path'] ?? '')),
        'file_name' => sanitize_file_name($snap ? $snap['file_name'] : ($item_data['file_name'] ?? '')),
        'sort_order' => isset($item_data['sort_order']) ? (int) $item_data['sort_order'] : ($max_sort + 1),
        'created_at' => current_time('mysql'),
    ];
    if ($row['title'] === '') {
        return new WP_Error('invalid', 'عنوان آیتم الزامی است.');
    }
    $ok = $wpdb->insert(sc_program_template_day_items_table(), $row);
    return $ok ? (int) $wpdb->insert_id : new WP_Error('db', 'خطا در افزودن آیتم.');
}

function sc_program_template_delete_item($item_id) {
    global $wpdb;
    return false !== $wpdb->delete(sc_program_template_day_items_table(), ['id' => absint($item_id)], ['%d']);
}

function sc_program_template_reorder_items($template_day_id, array $item_ids) {
    global $wpdb;
    $template_day_id = absint($template_day_id);
    $order = 0;
    foreach ($item_ids as $iid) {
        $iid = absint($iid);
        if ($iid <= 0) {
            continue;
        }
        $wpdb->update(
            sc_program_template_day_items_table(),
            ['sort_order' => $order],
            ['id' => $iid, 'template_day_id' => $template_day_id],
            ['%d'],
            ['%d', '%d']
        );
        $order++;
    }
    return true;
}

function sc_program_template_delete($id) {
    global $wpdb;
    $id = absint($id);
    $days = sc_program_template_get_days($id);
    foreach ($days as $day) {
        $wpdb->delete(sc_program_template_day_items_table(), ['template_day_id' => (int) $day->id], ['%d']);
    }
    $wpdb->delete(sc_program_template_days_table(), ['template_id' => $id], ['%d']);
    return false !== $wpdb->delete(sc_program_templates_table(), ['id' => $id], ['%d']);
}

function sc_program_template_build_day_structure($template) {
    $mode = sc_program_normalize_schedule_mode($template->schedule_mode);
    $days = sc_program_template_get_days((int) $template->id);
    $by_offset = [];
    foreach ($days as $day) {
        $items = sc_program_template_get_day_items((int) $day->id);
        $by_offset[(int) $day->day_offset] = [
            'day_offset' => (int) $day->day_offset,
            'title' => (string) $day->title,
            'items' => $items,
        ];
    }
    if ($mode === 'fixed') {
        $duration = max(1, (int) $template->duration_days);
        for ($i = 0; $i < $duration; $i++) {
            if (!isset($by_offset[$i])) {
                $by_offset[$i] = ['day_offset' => $i, 'title' => 'روز ' . ($i + 1), 'items' => []];
            }
        }
        ksort($by_offset);
        return array_values($by_offset);
    }
    if ($mode === 'weekly') {
        $mask = sc_program_normalize_weekly_mask($template->weekly_mask);
        if (empty($mask)) {
            $mask = [0, 1, 2, 3, 4, 5, 6];
        }
        // Relative slots for weekly templates: one slot per masked weekday in order (0..count-1),
        // plus any extra custom offsets already stored.
        $slots = count($mask);
        for ($i = 0; $i < $slots; $i++) {
            if (!isset($by_offset[$i])) {
                $by_offset[$i] = ['day_offset' => $i, 'title' => 'الگوی روز ' . ($i + 1), 'items' => []];
            }
        }
        ksort($by_offset);
        return array_values($by_offset);
    }
    // manual: only existing days
    ksort($by_offset);
    return array_values($by_offset);
}

/* ===================== Member programs ===================== */

function sc_program_get($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_member_programs_table() . ' WHERE id = %d LIMIT 1',
        absint($id)
    ));
}

function sc_program_get_days($program_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . sc_member_program_days_table() . ' WHERE program_id = %d ORDER BY program_date ASC',
        absint($program_id)
    )) ?: [];
}

function sc_program_get_day_items($program_day_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . sc_member_program_day_items_table() . ' WHERE program_day_id = %d ORDER BY sort_order ASC, id ASC',
        absint($program_day_id)
    )) ?: [];
}

function sc_program_get_day_item($item_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_member_program_day_items_table() . ' WHERE id = %d LIMIT 1',
        absint($item_id)
    ));
}

function sc_program_compute_dates_for_assign($template, $start_date, $end_date = '') {
    $start_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) ? $start_date : sc_program_today_ymd();
    $mode = sc_program_normalize_schedule_mode($template->schedule_mode);
    $structure = sc_program_template_build_day_structure($template);
    $dates = [];

    if ($mode === 'fixed') {
        foreach ($structure as $slot) {
            $offset = (int) $slot['day_offset'];
            $dates[] = [
                'day_offset' => $offset,
                'program_date' => gmdate('Y-m-d', strtotime($start_date . ' +' . $offset . ' days')),
                'title' => $slot['title'],
                'items' => $slot['items'],
            ];
        }
        return $dates;
    }

    if ($mode === 'manual') {
        foreach ($structure as $slot) {
            $offset = (int) $slot['day_offset'];
            $dates[] = [
                'day_offset' => $offset,
                'program_date' => gmdate('Y-m-d', strtotime($start_date . ' +' . $offset . ' days')),
                'title' => $slot['title'],
                'items' => $slot['items'],
            ];
        }
        if (empty($dates)) {
            $dates[] = [
                'day_offset' => 0,
                'program_date' => $start_date,
                'title' => 'روز ۱',
                'items' => [],
            ];
        }
        return $dates;
    }

    // weekly: map relative slots onto matching IR weekdays from start..end
    $mask = sc_program_normalize_weekly_mask($template->weekly_mask);
    if (empty($mask)) {
        $mask = [1, 2, 3, 4, 5, 6]; // شنبه تا پنج‌شنبه
    }
    if ($end_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $weeks = max(1, (int) ceil(max(1, (int) $template->duration_days) / max(1, count($mask))));
        $end_date = gmdate('Y-m-d', strtotime($start_date . ' +' . ($weeks * 7 - 1) . ' days'));
    }
    $slot_count = count($structure);
    $slot_index = 0;
    $cursor = strtotime($start_date . ' 12:00:00');
    $end_ts = strtotime($end_date . ' 12:00:00');
    $day_i = 0;
    while ($cursor <= $end_ts) {
        $ymd = date('Y-m-d', $cursor);
        $ir_w = sc_program_ir_weekday_from_ymd($ymd);
        if (in_array($ir_w, $mask, true)) {
            $slot = $structure[$slot_index % max(1, $slot_count)];
            $dates[] = [
                'day_offset' => $day_i,
                'program_date' => $ymd,
                'title' => $slot['title'],
                'items' => $slot['items'],
            ];
            $slot_index++;
            $day_i++;
        }
        $cursor = strtotime('+1 day', $cursor);
    }
    return $dates;
}

function sc_program_insert_day_with_items($program_id, $day_data) {
    global $wpdb;
    $wpdb->insert(sc_member_program_days_table(), [
        'program_id' => absint($program_id),
        'day_offset' => (int) ($day_data['day_offset'] ?? 0),
        'program_date' => $day_data['program_date'],
        'title' => sanitize_text_field($day_data['title'] ?? ''),
        'created_at' => current_time('mysql'),
    ]);
    $day_id = (int) $wpdb->insert_id;
    if ($day_id <= 0) {
        return 0;
    }
    $sort = 0;
    foreach ((array) ($day_data['items'] ?? []) as $item) {
        $item = (array) $item;
        $lib_id = absint($item['library_item_id'] ?? ($item['source_library_item_id'] ?? 0));
        $wpdb->insert(sc_member_program_day_items_table(), [
            'program_day_id' => $day_id,
            'source_library_item_id' => $lib_id > 0 ? $lib_id : null,
            'title' => sanitize_text_field($item['title'] ?? ''),
            'description' => wp_kses_post($item['description'] ?? ''),
            'media_type' => sc_program_normalize_media_type($item['media_type'] ?? 'none'),
            'media_url' => esc_url_raw($item['media_url'] ?? ''),
            'file_path' => sanitize_text_field($item['file_path'] ?? ''),
            'file_name' => sanitize_file_name($item['file_name'] ?? ''),
            'sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : $sort,
            'created_at' => current_time('mysql'),
        ]);
        $sort++;
    }
    return $day_id;
}

function sc_program_assign_to_members($template_id, array $member_ids, $start_date, $opts = []) {
    $template = sc_program_template_get($template_id);
    if (!$template) {
        return new WP_Error('not_found', 'قالب یافت نشد.');
    }
    $member_ids = sc_program_filter_member_ids($member_ids);
    if (empty($member_ids)) {
        return new WP_Error('empty', 'هیچ کاربری برای اختصاص انتخاب نشد.');
    }
    $start_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $start_date) ? $start_date : sc_program_today_ymd();
    $end_date = isset($opts['end_date']) ? (string) $opts['end_date'] : '';
    $dates = sc_program_compute_dates_for_assign($template, $start_date, $end_date);
    $ctx = sc_program_staff_context();
    $now = current_time('mysql');
    $created_ids = [];
    global $wpdb;

    foreach ($member_ids as $member_id) {
        $user_id = sc_program_get_member_user_id($member_id);
        $last_date = !empty($dates) ? $dates[count($dates) - 1]['program_date'] : $start_date;
        $wpdb->insert(sc_member_programs_table(), [
            'member_id' => $member_id,
            'user_id' => $user_id,
            'template_id' => (int) $template->id,
            'title' => (string) $template->title,
            'description' => (string) $template->description,
            'schedule_mode' => sc_program_normalize_schedule_mode($template->schedule_mode),
            'start_date' => $start_date,
            'end_date' => $last_date,
            'weekly_mask' => (string) $template->weekly_mask,
            'status' => 'active',
            'created_by_user_id' => $ctx['user_id'],
            'created_by_coach_id' => $ctx['coach_id'] > 0 ? $ctx['coach_id'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $program_id = (int) $wpdb->insert_id;
        if ($program_id <= 0) {
            continue;
        }
        foreach ($dates as $day_data) {
            sc_program_insert_day_with_items($program_id, $day_data);
        }
        $created_ids[] = $program_id;
        sc_program_notify_member($member_id, (string) $template->title, 'assigned');
    }
    return $created_ids;
}

function sc_program_sync_from_template($program_id) {
    global $wpdb;
    $program = sc_program_get($program_id);
    if (!$program || !(int) $program->template_id) {
        return new WP_Error('invalid', 'این برنامه به قالبی متصل نیست.');
    }
    if (!sc_program_can_access_member((int) $program->member_id)) {
        return new WP_Error('forbidden', 'دسترسی غیرمجاز.');
    }
    $template = sc_program_template_get((int) $program->template_id);
    if (!$template) {
        return new WP_Error('not_found', 'قالب یافت نشد.');
    }

    // Preserve completions keyed by source_library_item_id + day_offset
    $old_days = sc_program_get_days($program_id);
    $preserved = [];
    foreach ($old_days as $day) {
        $items = sc_program_get_day_items((int) $day->id);
        foreach ($items as $item) {
            $done = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . sc_member_program_completions_table() . ' WHERE day_item_id = %d LIMIT 1',
                (int) $item->id
            ));
            if ($done && !empty($item->source_library_item_id)) {
                $key = (int) $day->day_offset . ':' . (int) $item->source_library_item_id;
                $preserved[$key] = $done;
            }
        }
    }

    foreach ($old_days as $day) {
        $items = sc_program_get_day_items((int) $day->id);
        foreach ($items as $item) {
            $wpdb->delete(sc_member_program_completions_table(), ['day_item_id' => (int) $item->id], ['%d']);
        }
        $wpdb->delete(sc_member_program_day_items_table(), ['program_day_id' => (int) $day->id], ['%d']);
        $wpdb->delete(sc_member_program_days_table(), ['id' => (int) $day->id], ['%d']);
    }

    $dates = sc_program_compute_dates_for_assign($template, $program->start_date, (string) $program->end_date);
    $wpdb->update(sc_member_programs_table(), [
        'title' => (string) $template->title,
        'description' => (string) $template->description,
        'schedule_mode' => sc_program_normalize_schedule_mode($template->schedule_mode),
        'weekly_mask' => (string) $template->weekly_mask,
        'end_date' => !empty($dates) ? $dates[count($dates) - 1]['program_date'] : $program->start_date,
        'updated_at' => current_time('mysql'),
    ], ['id' => (int) $program_id]);

    foreach ($dates as $day_data) {
        $day_id = sc_program_insert_day_with_items($program_id, $day_data);
        if ($day_id <= 0) {
            continue;
        }
        $new_items = sc_program_get_day_items($day_id);
        foreach ($new_items as $item) {
            if (empty($item->source_library_item_id)) {
                continue;
            }
            $key = (int) $day_data['day_offset'] . ':' . (int) $item->source_library_item_id;
            if (!isset($preserved[$key])) {
                continue;
            }
            $old = $preserved[$key];
            $wpdb->insert(sc_member_program_completions_table(), [
                'day_item_id' => (int) $item->id,
                'program_id' => (int) $program_id,
                'member_id' => (int) $program->member_id,
                'completed_by_user_id' => (int) $old->completed_by_user_id,
                'completed_by_role' => (string) $old->completed_by_role,
                'completed_at' => (string) $old->completed_at,
            ]);
        }
    }
    sc_program_notify_member((int) $program->member_id, (string) $template->title, 'synced');
    return true;
}

function sc_program_query_admin($args = []) {
    global $wpdb;
    $p = sc_member_programs_table();
    $m = $wpdb->prefix . 'sc_members';
    $search = isset($args['search']) ? sanitize_text_field($args['search']) : '';
    $member_id = isset($args['member_id']) ? absint($args['member_id']) : 0;
    $status = isset($args['status']) ? sanitize_key($args['status']) : '';
    $per_page = isset($args['per_page']) ? max(1, (int) $args['per_page']) : 20;
    $page = isset($args['page']) ? max(1, (int) $args['page']) : 1;
    $offset = ($page - 1) * $per_page;
    $where = ['1=1'];
    $values = [];
    $ctx = sc_program_staff_context();
    if ($ctx['is_coach'] && function_exists('sc_private_notes_get_member_ids_for_coach')) {
        $allowed = sc_private_notes_get_member_ids_for_coach($ctx['coach_id']);
        if (empty($allowed)) {
            return ['rows' => [], 'total' => 0];
        }
        $ph = implode(',', array_fill(0, count($allowed), '%d'));
        $where[] = "p.member_id IN ($ph)";
        $values = array_merge($values, $allowed);
    } elseif ($ctx['is_secretary'] && function_exists('sc_secretary_append_member_where')) {
        // Restrict via subquery of accessible members
        $members = function_exists('sc_secretary_get_branch_members_for_picker')
            ? sc_secretary_get_branch_members_for_picker()
            : [];
        $allowed = array_map('absint', wp_list_pluck($members, 'id'));
        if (empty($allowed)) {
            return ['rows' => [], 'total' => 0];
        }
        $ph = implode(',', array_fill(0, count($allowed), '%d'));
        $where[] = "p.member_id IN ($ph)";
        $values = array_merge($values, $allowed);
    }
    if ($member_id > 0) {
        $where[] = 'p.member_id = %d';
        $values[] = $member_id;
    }
    if ($status !== '') {
        $where[] = 'p.status = %s';
        $values[] = $status;
    }
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(p.title LIKE %s OR m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)';
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
    }
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM $p p LEFT JOIN $m m ON m.id = p.member_id WHERE $where_sql";
    $total = (int) ($values ? $wpdb->get_var($wpdb->prepare($count_sql, ...$values)) : $wpdb->get_var($count_sql));
    $sql = "SELECT p.*, TRIM(CONCAT(COALESCE(m.first_name,''),' ',COALESCE(m.last_name,''))) AS member_name, m.national_id
            FROM $p p LEFT JOIN $m m ON m.id = p.member_id
            WHERE $where_sql ORDER BY p.updated_at DESC LIMIT %d OFFSET %d";
    $values[] = $per_page;
    $values[] = $offset;
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values));
    return ['rows' => $rows ?: [], 'total' => $total, 'page' => $page, 'per_page' => $per_page];
}

function sc_program_query_for_user($user_id, $args = []) {
    global $wpdb;
    $user_id = absint($user_id);
    if ($user_id <= 0) {
        return [];
    }
    $include_inactive = !empty($args['include_inactive']);
    if ($include_inactive) {
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . sc_member_programs_table() . ' WHERE user_id = %d ORDER BY FIELD(status, \'active\', \'inactive\'), start_date DESC, id DESC',
            $user_id
        )) ?: [];
    }
    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . sc_member_programs_table() . ' WHERE user_id = %d AND status = %s ORDER BY start_date DESC, id DESC',
        $user_id,
        'active'
    )) ?: [];
}

function sc_program_add_member_day($program_id, $program_date, $title = '') {
    global $wpdb;
    $program = sc_program_get($program_id);
    if (!$program) {
        return new WP_Error('not_found', 'برنامه یافت نشد.');
    }
    if (!sc_program_can_access_member((int) $program->member_id)) {
        return new WP_Error('forbidden', 'دسترسی غیرمجاز.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $program_date)) {
        return new WP_Error('invalid', 'تاریخ نامعتبر است.');
    }
    $exists = $wpdb->get_var($wpdb->prepare(
        'SELECT id FROM ' . sc_member_program_days_table() . ' WHERE program_id = %d AND program_date = %s LIMIT 1',
        absint($program_id),
        $program_date
    ));
    if ($exists) {
        return (int) $exists;
    }
    $start = strtotime($program->start_date . ' 12:00:00');
    $cur = strtotime($program_date . ' 12:00:00');
    $offset = max(0, (int) round(($cur - $start) / DAY_IN_SECONDS));
    $wpdb->insert(sc_member_program_days_table(), [
        'program_id' => absint($program_id),
        'day_offset' => $offset,
        'program_date' => $program_date,
        'title' => sanitize_text_field($title !== '' ? $title : ('روز ' . ($offset + 1))),
        'created_at' => current_time('mysql'),
    ]);
    $wpdb->update(sc_member_programs_table(), ['updated_at' => current_time('mysql')], ['id' => absint($program_id)]);
    return (int) $wpdb->insert_id;
}

function sc_program_add_member_day_item($program_day_id, $item_data) {
    global $wpdb;
    $day = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_member_program_days_table() . ' WHERE id = %d LIMIT 1',
        absint($program_day_id)
    ));
    if (!$day) {
        return new WP_Error('not_found', 'روز یافت نشد.');
    }
    $program = sc_program_get((int) $day->program_id);
    if (!$program || !sc_program_can_access_member((int) $program->member_id)) {
        return new WP_Error('forbidden', 'دسترسی غیرمجاز.');
    }
    $snap = null;
    $lib_id = absint($item_data['library_item_id'] ?? 0);
    if ($lib_id > 0) {
        $snap = sc_program_snapshot_from_library($lib_id);
        if (!$snap) {
            return new WP_Error('not_found', 'آیتم کتابخانه یافت نشد.');
        }
    }
    $max_sort = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT MAX(sort_order) FROM ' . sc_member_program_day_items_table() . ' WHERE program_day_id = %d',
        absint($program_day_id)
    ));
    $row = [
        'program_day_id' => absint($program_day_id),
        'source_library_item_id' => $snap ? $snap['source_library_item_id'] : (absint($item_data['source_library_item_id'] ?? 0) ?: null),
        'title' => sanitize_text_field($snap ? $snap['title'] : ($item_data['title'] ?? '')),
        'description' => wp_kses_post($snap ? $snap['description'] : ($item_data['description'] ?? '')),
        'media_type' => sc_program_normalize_media_type($snap ? $snap['media_type'] : ($item_data['media_type'] ?? 'none')),
        'media_url' => esc_url_raw($snap ? $snap['media_url'] : ($item_data['media_url'] ?? '')),
        'file_path' => sanitize_text_field($snap ? $snap['file_path'] : ($item_data['file_path'] ?? '')),
        'file_name' => sanitize_file_name($snap ? $snap['file_name'] : ($item_data['file_name'] ?? '')),
        'sort_order' => isset($item_data['sort_order']) ? (int) $item_data['sort_order'] : ($max_sort + 1),
        'created_at' => current_time('mysql'),
    ];
    if ($row['title'] === '') {
        return new WP_Error('invalid', 'عنوان الزامی است.');
    }
    $ok = $wpdb->insert(sc_member_program_day_items_table(), $row);
    $wpdb->update(sc_member_programs_table(), ['updated_at' => current_time('mysql')], ['id' => (int) $program->id]);
    return $ok ? (int) $wpdb->insert_id : new WP_Error('db', 'خطا در افزودن آیتم.');
}

function sc_program_update_member_day_item($item_id, $data) {
    global $wpdb;
    $item = sc_program_get_day_item($item_id);
    if (!$item) {
        return new WP_Error('not_found', 'آیتم یافت نشد.');
    }
    $day = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_member_program_days_table() . ' WHERE id = %d',
        (int) $item->program_day_id
    ));
    $program = $day ? sc_program_get((int) $day->program_id) : null;
    if (!$program || !sc_program_can_access_member((int) $program->member_id)) {
        return new WP_Error('forbidden', 'دسترسی غیرمجاز.');
    }
    $row = [
        'title' => sanitize_text_field($data['title'] ?? $item->title),
        'description' => wp_kses_post($data['description'] ?? $item->description),
        'media_type' => sc_program_normalize_media_type($data['media_type'] ?? $item->media_type),
        'media_url' => esc_url_raw($data['media_url'] ?? $item->media_url),
        'file_path' => sanitize_text_field($data['file_path'] ?? $item->file_path),
        'file_name' => sanitize_file_name($data['file_name'] ?? $item->file_name),
    ];
    $ok = $wpdb->update(sc_member_program_day_items_table(), $row, ['id' => absint($item_id)]);
    return ($ok === false) ? new WP_Error('db', 'خطا در ذخیره.') : true;
}

function sc_program_delete_member_day_item($item_id) {
    global $wpdb;
    $item = sc_program_get_day_item($item_id);
    if (!$item) {
        return false;
    }
    $day = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_member_program_days_table() . ' WHERE id = %d',
        (int) $item->program_day_id
    ));
    $program = $day ? sc_program_get((int) $day->program_id) : null;
    if (!$program || !sc_program_can_access_member((int) $program->member_id)) {
        return false;
    }
    $wpdb->delete(sc_member_program_completions_table(), ['day_item_id' => absint($item_id)], ['%d']);
    return false !== $wpdb->delete(sc_member_program_day_items_table(), ['id' => absint($item_id)], ['%d']);
}

function sc_program_reorder_member_day_items($program_day_id, array $item_ids) {
    global $wpdb;
    $day = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_member_program_days_table() . ' WHERE id = %d',
        absint($program_day_id)
    ));
    if (!$day) {
        return false;
    }
    $program = sc_program_get((int) $day->program_id);
    if (!$program || !sc_program_can_access_member((int) $program->member_id)) {
        return false;
    }
    $order = 0;
    foreach ($item_ids as $iid) {
        $iid = absint($iid);
        if ($iid <= 0) {
            continue;
        }
        $wpdb->update(
            sc_member_program_day_items_table(),
            ['sort_order' => $order],
            ['id' => $iid, 'program_day_id' => absint($program_day_id)],
            ['%d'],
            ['%d', '%d']
        );
        $order++;
    }
    return true;
}

/* ===================== Completions & progress ===================== */

function sc_program_is_item_completed($day_item_id) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . sc_member_program_completions_table() . ' WHERE day_item_id = %d',
        absint($day_item_id)
    )) > 0;
}

function sc_program_get_completed_item_ids($program_id) {
    global $wpdb;
    $ids = $wpdb->get_col($wpdb->prepare(
        'SELECT day_item_id FROM ' . sc_member_program_completions_table() . ' WHERE program_id = %d',
        absint($program_id)
    ));
    return array_map('absint', $ids ?: []);
}

function sc_program_can_toggle_completion($program, $day, $as_user_id = 0) {
    if (!$program || !$day) {
        return false;
    }
    if ($as_user_id <= 0) {
        $as_user_id = get_current_user_id();
    }
    if (sc_program_player_owns_program($program, $as_user_id)) {
        return (string) $day->program_date === sc_program_today_ymd();
    }
    return sc_program_can_access_member((int) $program->member_id, $as_user_id);
}

function sc_program_toggle_completion($day_item_id, $done = true, $user_id = 0) {
    global $wpdb;
    if ($user_id <= 0) {
        $user_id = get_current_user_id();
    }
    $item = sc_program_get_day_item($day_item_id);
    if (!$item) {
        return new WP_Error('not_found', 'آیتم یافت نشد.');
    }
    $day = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_member_program_days_table() . ' WHERE id = %d',
        (int) $item->program_day_id
    ));
    $program = $day ? sc_program_get((int) $day->program_id) : null;
    if (!$program || !sc_program_can_toggle_completion($program, $day, $user_id)) {
        return new WP_Error('forbidden', 'امکان ثبت تیک برای این روز وجود ندارد.');
    }
    $exists = sc_program_is_item_completed((int) $item->id);
    if ($done) {
        if ($exists) {
            return true;
        }
        $ctx = sc_program_staff_context($user_id);
        $role = 'player';
        if ($ctx['is_admin']) {
            $role = 'admin';
        } elseif ($ctx['is_coach']) {
            $role = 'coach';
        } elseif ($ctx['is_secretary']) {
            $role = 'secretary';
        } elseif (sc_program_player_owns_program($program, $user_id)) {
            $role = 'player';
        }
        $wpdb->insert(sc_member_program_completions_table(), [
            'day_item_id' => (int) $item->id,
            'program_id' => (int) $program->id,
            'member_id' => (int) $program->member_id,
            'completed_by_user_id' => $user_id,
            'completed_by_role' => $role,
            'completed_at' => current_time('mysql'),
        ]);
        return true;
    }
    if ($exists) {
        $wpdb->delete(sc_member_program_completions_table(), ['day_item_id' => (int) $item->id], ['%d']);
    }
    return true;
}

function sc_program_progress($program_id, $until_today = true) {
    global $wpdb;
    $program_id = absint($program_id);
    $days_table = sc_member_program_days_table();
    $items_table = sc_member_program_day_items_table();
    $comp_table = sc_member_program_completions_table();
    $today = sc_program_today_ymd();
    $date_sql = $until_today ? $wpdb->prepare(' AND d.program_date <= %s', $today) : '';
    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $items_table i
         INNER JOIN $days_table d ON d.id = i.program_day_id
         WHERE d.program_id = " . (int) $program_id . $date_sql
    );
    $done = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $comp_table c
         INNER JOIN $items_table i ON i.id = c.day_item_id
         INNER JOIN $days_table d ON d.id = i.program_day_id
         WHERE c.program_id = " . (int) $program_id . $date_sql
    );
    $percent = $total > 0 ? round(($done / $total) * 100) : 0;
    return ['total' => $total, 'done' => $done, 'percent' => $percent];
}

/* ===================== Notifications ===================== */

function sc_program_notify_member($member_id, $program_title, $event = 'assigned') {
    global $wpdb;
    $member = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . $wpdb->prefix . 'sc_members WHERE id = %d LIMIT 1',
        absint($member_id)
    ));
    if (!$member) {
        return;
    }
    $name = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
    $labels = [
        'assigned' => 'یک برنامه تخصصی جدید برای شما ثبت شد',
        'synced' => 'برنامه تخصصی شما از قالب به‌روز شد',
        'updated' => 'برنامه تخصصی شما به‌روز شد',
    ];
    $label = $labels[$event] ?? $labels['assigned'];
    $msg = $label . "\n«" . $program_title . "»\n" . $name;

    if (function_exists('sc_bale_notify_user') && function_exists('sc_bale_is_enabled') && sc_bale_is_enabled()) {
        sc_bale_notify_user((int) $member->id, (string) ($member->player_phone ?? ''), $msg);
    }

    $sms_enabled = (int) (function_exists('sc_get_sms_setting')
        ? sc_get_sms_setting('sms_specialized_program_enabled')
        : sc_get_setting('sms_specialized_program_enabled', '0'));
    if ($sms_enabled === 1 && !empty($member->player_phone) && function_exists('sc_send_sms')) {
        $template = function_exists('sc_get_sms_setting')
            ? (string) sc_get_sms_setting('sms_specialized_program_template')
            : (string) sc_get_setting('sms_specialized_program_template', '');
        $pattern = function_exists('sc_get_sms_setting')
            ? absint(sc_get_sms_setting('sms_specialized_program_pattern'))
            : absint(sc_get_setting('sms_specialized_program_pattern', 0));
        if ($template === '') {
            $template = '%name% عزیز، %label%: %program%';
        }
        $text = str_replace(
            ['%name%', '%label%', '%program%'],
            [$name, $label, $program_title],
            $template
        );
        $vars = ['name' => $name, 'label' => $label, 'program' => $program_title];
        sc_send_sms((string) $member->player_phone, $text, $pattern > 0, $pattern > 0 ? $pattern : null, $vars, 'specialized_program_' . $event);
    }
}

/* ===================== Reports ===================== */

function sc_program_report_summary($date_from = '', $date_to = '') {
    global $wpdb;
    $p = sc_member_programs_table();
    $c = sc_member_program_completions_table();
    $today = sc_program_today_ymd();
    $active = (int) $wpdb->get_var("SELECT COUNT(*) FROM $p WHERE status = 'active'");
    $done_today = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $c WHERE DATE(completed_at) = %s",
        $today
    ));
    $programs = $wpdb->get_col("SELECT id FROM $p WHERE status = 'active'") ?: [];
    $sum_pct = 0;
    $cnt = 0;
    foreach ($programs as $pid) {
        $prog = sc_program_progress((int) $pid, true);
        $sum_pct += $prog['percent'];
        $cnt++;
    }
    $avg = $cnt > 0 ? round($sum_pct / $cnt, 1) : 0;

    $trend = [];
    if ($date_from === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $date_from = gmdate('Y-m-d', strtotime($today . ' -13 days'));
    }
    if ($date_to === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $date_to = $today;
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(completed_at) AS d, COUNT(*) AS cnt FROM $c
         WHERE DATE(completed_at) BETWEEN %s AND %s GROUP BY DATE(completed_at) ORDER BY d ASC",
        $date_from,
        $date_to
    ));
    $map = [];
    foreach ($rows as $r) {
        $map[$r->d] = (int) $r->cnt;
    }
    $cur = strtotime($date_from . ' 12:00:00');
    $end = strtotime($date_to . ' 12:00:00');
    while ($cur <= $end) {
        $d = date('Y-m-d', $cur);
        $trend[] = ['date' => $d, 'count' => isset($map[$d]) ? $map[$d] : 0];
        $cur = strtotime('+1 day', $cur);
    }

    return [
        'active_programs' => $active,
        'avg_percent' => $avg,
        'done_today' => $done_today,
        'trend' => $trend,
    ];
}

function sc_program_report_players($args = []) {
    global $wpdb;
    $p = sc_member_programs_table();
    $m = $wpdb->prefix . 'sc_members';
    $search = isset($args['search']) ? sanitize_text_field($args['search']) : '';
    $where = ["p.status = 'active'"];
    $values = [];
    $ctx = sc_program_staff_context();
    if ($ctx['is_coach'] && function_exists('sc_private_notes_get_member_ids_for_coach')) {
        $allowed = sc_private_notes_get_member_ids_for_coach($ctx['coach_id']);
        if (empty($allowed)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($allowed), '%d'));
        $where[] = "p.member_id IN ($ph)";
        $values = array_merge($values, $allowed);
    } elseif ($ctx['is_secretary'] && function_exists('sc_secretary_get_branch_members_for_picker')) {
        $members = sc_secretary_get_branch_members_for_picker();
        $allowed = array_map('absint', wp_list_pluck($members, 'id'));
        if (empty($allowed)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($allowed), '%d'));
        $where[] = "p.member_id IN ($ph)";
        $values = array_merge($values, $allowed);
    }
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(p.title LIKE %s OR m.first_name LIKE %s OR m.last_name LIKE %s OR m.national_id LIKE %s)';
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
    }
    $where_sql = implode(' AND ', $where);
    $sql = "SELECT p.*, TRIM(CONCAT(COALESCE(m.first_name,''),' ',COALESCE(m.last_name,''))) AS member_name, m.national_id
            FROM $p p LEFT JOIN $m m ON m.id = p.member_id WHERE $where_sql ORDER BY p.updated_at DESC LIMIT 200";
    $rows = $values ? $wpdb->get_results($wpdb->prepare($sql, ...$values)) : $wpdb->get_results($sql);
    $out = [];
    foreach ($rows ?: [] as $row) {
        $prog = sc_program_progress((int) $row->id, true);
        $row->progress_percent = $prog['percent'];
        $row->progress_done = $prog['done'];
        $row->progress_total = $prog['total'];
        $out[] = $row;
    }
    return $out;
}

function sc_program_report_detail($program_id) {
    $program = sc_program_get($program_id);
    if (!$program) {
        return null;
    }
    if (!sc_program_can_access_member((int) $program->member_id) && !sc_program_player_owns_program($program)) {
        return null;
    }
    $days = sc_program_get_days($program_id);
    $completed = sc_program_get_completed_item_ids($program_id);
    $detail = [];
    foreach ($days as $day) {
        $items = sc_program_get_day_items((int) $day->id);
        $list = [];
        foreach ($items as $item) {
            $list[] = [
                'item' => $item,
                'done' => in_array((int) $item->id, $completed, true),
            ];
        }
        $detail[] = ['day' => $day, 'items' => $list];
    }
    return [
        'program' => $program,
        'progress' => sc_program_progress($program_id, true),
        'progress_all' => sc_program_progress($program_id, false),
        'days' => $detail,
    ];
}

/**
 * Replace all template days/items from a JSON payload (AJAX save, no refresh).
 *
 * @param int   $template_id
 * @param array $days_payload [ ['day_offset'=>0,'title'=>'','library_ids'=>[1,2]], ... ]
 */
function sc_program_template_replace_days_from_payload($template_id, array $days_payload) {
    global $wpdb;
    $template_id = absint($template_id);
    $template = sc_program_template_get($template_id);
    if (!$template) {
        return new WP_Error('not_found', 'قالب یافت نشد.');
    }

    $existing_days = sc_program_template_get_days($template_id);
    foreach ($existing_days as $day) {
        $wpdb->delete(sc_program_template_day_items_table(), ['template_day_id' => (int) $day->id], ['%d']);
    }
    $wpdb->delete(sc_program_template_days_table(), ['template_id' => $template_id], ['%d']);

    $created = [];
    foreach ($days_payload as $slot) {
        $offset = max(0, (int) ($slot['day_offset'] ?? 0));
        $title = sanitize_text_field($slot['title'] ?? ('روز ' . ($offset + 1)));
        $day = sc_program_template_ensure_day($template_id, $offset, $title);
        if (!$day) {
            continue;
        }
        $lib_ids = isset($slot['library_ids']) ? array_map('absint', (array) $slot['library_ids']) : [];
        $items_out = [];
        $sort = 0;
        foreach ($lib_ids as $lib_id) {
            if ($lib_id <= 0) {
                continue;
            }
            $item_id = sc_program_template_add_item((int) $day->id, [
                'library_item_id' => $lib_id,
                'sort_order' => $sort,
            ]);
            if (!is_wp_error($item_id)) {
                $items_out[] = [
                    'id' => (int) $item_id,
                    'library_id' => $lib_id,
                    'title' => (string) (sc_program_library_get($lib_id)->title ?? ''),
                ];
            }
            $sort++;
        }
        $created[] = [
            'day_id' => (int) $day->id,
            'day_offset' => $offset,
            'title' => $title,
            'items' => $items_out,
        ];
    }
    $wpdb->update(sc_program_templates_table(), ['updated_at' => current_time('mysql')], ['id' => $template_id]);
    return $created;
}

function sc_program_template_export_structure_for_js($template_id) {
    $template = sc_program_template_get($template_id);
    if (!$template) {
        return [];
    }
    $structure = sc_program_template_build_day_structure($template);
    $out = [];
    foreach ($structure as $slot) {
        $day_row = null;
        foreach (sc_program_template_get_days((int) $template->id) as $d) {
            if ((int) $d->day_offset === (int) $slot['day_offset']) {
                $day_row = $d;
                break;
            }
        }
        if (!$day_row) {
            $day_row = sc_program_template_ensure_day((int) $template->id, (int) $slot['day_offset'], $slot['title']);
        }
        $items = sc_program_template_get_day_items((int) $day_row->id);
        $item_list = [];
        foreach ($items as $it) {
            $item_list[] = [
                'id' => (int) $it->id,
                'library_id' => (int) ($it->library_item_id ?: 0),
                'title' => (string) $it->title,
                'media_type' => (string) $it->media_type,
            ];
        }
        $out[] = [
            'day_id' => (int) $day_row->id,
            'day_offset' => (int) $slot['day_offset'],
            'title' => (string) ($slot['title'] ?: ('روز ' . ((int) $slot['day_offset'] + 1))),
            'items' => $item_list,
        ];
    }
    return $out;
}

/* ===================== Upload AJAX ===================== */

add_action('wp_ajax_sc_program_upload_file', 'sc_program_ajax_upload_file');
function sc_program_ajax_upload_file() {
    if (!sc_program_can_manage_staff()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
    }
    check_ajax_referer('sc_program_upload', 'nonce');
    if (empty($_FILES['file'])) {
        wp_send_json_error(['message' => 'فایلی ارسال نشد.']);
    }
    $file = $_FILES['file'];
    if (!empty($file['error'])) {
        wp_send_json_error(['message' => 'خطا در آپلود فایل.']);
    }
    if ((int) $file['size'] > SC_PROGRAM_MAX_UPLOAD_BYTES) {
        wp_send_json_error(['message' => 'حداکثر حجم مجاز ۵۰ مگابایت است.']);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = sc_program_allowed_upload_mimes();
    if (!isset($allowed[$ext])) {
        wp_send_json_error(['message' => 'فرمت فایل مجاز نیست.']);
    }
    $finfo = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed);
    if (empty($finfo['ext'])) {
        wp_send_json_error(['message' => 'نوع فایل نامعتبر است.']);
    }
    $dir = sc_program_upload_dir();
    $safe_name = wp_unique_filename($dir['path'], time() . '-' . sanitize_file_name($file['name']));
    $dest = trailingslashit($dir['path']) . $safe_name;
    if (!@move_uploaded_file($file['tmp_name'], $dest)) {
        wp_send_json_error(['message' => 'ذخیره فایل ناموفق بود.']);
    }
    wp_send_json_success([
        'file_path' => $safe_name,
        'file_name' => sanitize_file_name($file['name']),
        'url' => trailingslashit($dir['url']) . $safe_name,
    ]);
}

add_action('wp_ajax_sc_program_toggle_completion', 'sc_program_ajax_toggle_completion');
add_action('wp_ajax_nopriv_sc_program_toggle_completion', 'sc_program_ajax_toggle_completion');
function sc_program_ajax_toggle_completion() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'وارد شوید.'], 401);
    }
    check_ajax_referer('sc_program_toggle', 'nonce');
    $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
    $done = !empty($_POST['done']) && (string) $_POST['done'] !== '0';
    $result = sc_program_toggle_completion($item_id, $done);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    $item = sc_program_get_day_item($item_id);
    $day = $item ? $GLOBALS['wpdb']->get_row($GLOBALS['wpdb']->prepare(
        'SELECT * FROM ' . sc_member_program_days_table() . ' WHERE id = %d',
        (int) $item->program_day_id
    )) : null;
    $program_id = $day ? (int) $day->program_id : 0;
    $progress = $program_id ? sc_program_progress($program_id, true) : ['percent' => 0, 'done' => 0, 'total' => 0];
    wp_send_json_success(['done' => $done, 'progress' => $progress]);
}

add_action('wp_ajax_sc_program_library_search', 'sc_program_ajax_library_search');
function sc_program_ajax_library_search() {
    if (!sc_program_can_manage_staff()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
    }
    check_ajax_referer('sc_program_admin', 'nonce');
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $res = sc_program_library_query(['search' => $search, 'per_page' => 40, 'page' => 1]);
    $items = [];
    foreach ($res['rows'] as $row) {
        $items[] = [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'description' => wp_strip_all_tags((string) $row->description),
            'media_type' => (string) $row->media_type,
        ];
    }
    wp_send_json_success(['items' => $items]);
}

add_action('wp_ajax_sc_program_add_library_to_day', 'sc_program_ajax_add_library_to_day');
function sc_program_ajax_add_library_to_day() {
    if (!sc_program_can_manage_staff()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
    }
    check_ajax_referer('sc_program_admin', 'nonce');
    $target = isset($_POST['target']) ? sanitize_key($_POST['target']) : 'member';
    $day_id = isset($_POST['day_id']) ? absint($_POST['day_id']) : 0;
    $library_id = isset($_POST['library_id']) ? absint($_POST['library_id']) : 0;
    if ($target === 'template') {
        $id = sc_program_template_add_item($day_id, ['library_item_id' => $library_id]);
    } else {
        $id = sc_program_add_member_day_item($day_id, ['library_item_id' => $library_id]);
    }
    if (is_wp_error($id)) {
        wp_send_json_error(['message' => $id->get_error_message()]);
    }
    wp_send_json_success(['item_id' => $id]);
}

add_action('wp_ajax_sc_program_reorder_day_items', 'sc_program_ajax_reorder_day_items');
function sc_program_ajax_reorder_day_items() {
    if (!sc_program_can_manage_staff()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
    }
    check_ajax_referer('sc_program_admin', 'nonce');
    $target = isset($_POST['target']) ? sanitize_key($_POST['target']) : 'member';
    $day_id = isset($_POST['day_id']) ? absint($_POST['day_id']) : 0;
    $ids = isset($_POST['item_ids']) ? array_map('absint', (array) $_POST['item_ids']) : [];
    if ($target === 'template') {
        sc_program_template_reorder_items($day_id, $ids);
    } else {
        sc_program_reorder_member_day_items($day_id, $ids);
    }
    wp_send_json_success();
}

add_action('wp_ajax_sc_program_template_save_days', 'sc_program_ajax_template_save_days');
function sc_program_ajax_template_save_days() {
    if (!sc_program_can_manage_staff()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
    }
    check_ajax_referer('sc_program_admin', 'nonce');
    $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
    $raw = isset($_POST['days_json']) ? wp_unslash($_POST['days_json']) : '[]';
    $days = json_decode($raw, true);
    if (!is_array($days)) {
        wp_send_json_error(['message' => 'داده روزها نامعتبر است.']);
    }
    $result = sc_program_template_replace_days_from_payload($template_id, $days);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success([
        'days' => sc_program_template_export_structure_for_js($template_id),
        'message' => 'روزها و تمرین‌ها ذخیره شدند.',
    ]);
}

add_action('wp_ajax_sc_program_template_get_days', 'sc_program_ajax_template_get_days');
function sc_program_ajax_template_get_days() {
    if (!sc_program_can_manage_staff()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
    }
    check_ajax_referer('sc_program_admin', 'nonce');
    $template_id = isset($_GET['template_id']) ? absint($_GET['template_id']) : 0;
    wp_send_json_success(['days' => sc_program_template_export_structure_for_js($template_id)]);
}

add_action('admin_post_sc_issue_specialized_programs', 'sc_program_handle_issue_post');
function sc_program_handle_issue_post() {
    if (!sc_program_can_manage_staff()) {
        wp_die('دسترسی غیرمجاز.');
    }
    check_admin_referer('sc_issue_specialized_programs', 'sc_issue_specialized_programs_nonce');
    $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
    $start_shamsi = isset($_POST['start_date_shamsi']) ? sanitize_text_field(wp_unslash($_POST['start_date_shamsi'])) : '';
    $end_shamsi = isset($_POST['end_date_shamsi']) ? sanitize_text_field(wp_unslash($_POST['end_date_shamsi'])) : '';
    $start = ($start_shamsi && function_exists('sc_shamsi_to_gregorian_date'))
        ? sc_shamsi_to_gregorian_date($start_shamsi)
        : (isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : sc_program_today_ymd());
    $end = ($end_shamsi && function_exists('sc_shamsi_to_gregorian_date'))
        ? sc_shamsi_to_gregorian_date($end_shamsi)
        : (isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '');

    $target_type = isset($_POST['target_type']) ? sanitize_key($_POST['target_type']) : 'specific';
    $config = [
        'member_ids' => isset($_POST['member_ids']) ? array_map('absint', (array) $_POST['member_ids']) : [],
        'course_ids' => isset($_POST['course_ids']) ? (array) $_POST['course_ids'] : [],
        'event_ids' => isset($_POST['event_ids']) ? array_map('absint', (array) $_POST['event_ids']) : [],
        'team_names' => isset($_POST['team_names']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['team_names'])) : [],
        'level_names' => isset($_POST['level_names']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['level_names'])) : [],
        'member_type' => isset($_POST['member_type']) ? sanitize_text_field(wp_unslash($_POST['member_type'])) : 'all',
        'excluded_member_ids' => isset($_POST['excluded_member_ids']) ? array_map('absint', (array) $_POST['excluded_member_ids']) : [],
        'included_member_ids' => isset($_POST['included_member_ids']) ? array_map('absint', (array) $_POST['included_member_ids']) : [],
    ];
    $members = function_exists('sc_users_export_get_members')
        ? sc_users_export_get_members($target_type, $config)
        : [];
    if (!empty($config['included_member_ids']) && function_exists('sc_audience_merge_included_members_into_list')) {
        $members = sc_audience_merge_included_members_into_list($members, $config['included_member_ids']);
    }
    $member_ids = array_map('absint', wp_list_pluck($members, 'id'));
    $member_ids = sc_program_filter_member_ids($member_ids);

    $result = sc_program_assign_to_members($template_id, $member_ids, $start, ['end_date' => $end]);
    $ctx = sc_program_staff_context();
    $is_coach_flow = !empty($_POST['sc_programs_is_coach']) || (!empty($ctx['is_coach']) && empty($ctx['is_admin']));
    $list_page = $is_coach_flow ? 'sc-coach-programs' : 'sc-programs';
    $assign_page = $is_coach_flow ? 'sc-coach-programs-assign' : 'sc-programs-assign';
    if (is_wp_error($result)) {
        wp_safe_redirect(add_query_arg(['page' => $assign_page, 'error' => rawurlencode($result->get_error_message())], admin_url('admin.php')));
        exit;
    }
    wp_safe_redirect(add_query_arg(['page' => $list_page, 'assigned' => count($result)], admin_url('admin.php')));
    exit;
}

/* ===================== Player portal helpers + AJAX ===================== */

/**
 * برچسب روز برای بازیکن: «شنبه ۱۹ تیر» (بدون سال)
 */
function sc_program_player_day_chip_label($ymd) {
    $ymd = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $ymd) ? (string) $ymd : '';
    if ($ymd === '') {
        return '';
    }
    if (function_exists('sc_date_shamsi')) {
        return sc_date_shamsi($ymd . ' 12:00:00', 'l j F');
    }
    return $ymd;
}

function sc_program_player_can_view_program($program_id) {
    $program = sc_program_get(absint($program_id));
    if (!$program) {
        return false;
    }
    return sc_program_player_owns_program($program) || sc_program_can_access_member((int) $program->member_id);
}

function sc_program_player_render_media_html($item) {
    if (!$item) {
        return '<p class="sc-my-media-empty">رسانه‌ای ثبت نشده است.</p>';
    }
    $type = sc_program_normalize_media_type($item->media_type ?? 'none');
    if ($type === 'none') {
        return '<p class="sc-my-media-empty">برای این تمرین رسانه‌ای تعریف نشده است.</p>';
    }
    $html = '';
    if ($type === 'file' && !empty($item->file_path)) {
        $url = sc_program_file_public_url($item->file_path);
        $name = !empty($item->file_name) ? $item->file_name : basename((string) $item->file_path);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4', 'webm', 'mov', 'm4v'], true)) {
            $html .= '<video class="sc-my-media-video" controls preload="metadata" src="' . esc_url($url) . '"></video>';
        } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $html .= '<img class="sc-my-media-img" src="' . esc_url($url) . '" alt="' . esc_attr($item->title) . '">';
        } else {
            $html .= '<a class="sc_button sc_button--primary" href="' . esc_url($url) . '" target="_blank" rel="noopener">دانلود فایل: ' . esc_html($name) . '</a>';
        }
    } elseif ($type === 'aparat' || $type === 'url') {
        $url = (string) ($item->media_url ?? '');
        if ($url === '') {
            return '<p class="sc-my-media-empty">لینک رسانه خالی است.</p>';
        }
        if (preg_match('#aparat\.com/(?:v/|video/video/embed/videohash/)([a-zA-Z0-9]+)#', $url, $m)) {
            $embed = 'https://www.aparat.com/video/video/embed/videohash/' . rawurlencode($m[1]) . '/vt/frame';
            $html .= '<div class="sc-my-media-embed"><iframe src="' . esc_url($embed) . '" allowFullScreen="true" webkitallowfullscreen="true" mozallowfullscreen="true"></iframe></div>';
        } elseif (preg_match('#(youtube\.com|youtu\.be)#i', $url)) {
            $html .= '<p><a class="sc_button sc_button--primary" href="' . esc_url($url) . '" target="_blank" rel="noopener">مشاهده در یوتیوب</a></p>';
        } else {
            $html .= '<p><a class="sc_button sc_button--primary" href="' . esc_url($url) . '" target="_blank" rel="noopener">باز کردن لینک رسانه</a></p>';
        }
        $html .= '<p class="sc-prog-hint"><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($url) . '</a></p>';
    }
    if (!empty($item->description)) {
        $html .= '<div class="sc-my-media-desc">' . wp_kses_post(wpautop($item->description)) . '</div>';
    }
    return $html !== '' ? $html : '<p class="sc-my-media-empty">رسانه‌ای برای نمایش نیست.</p>';
}

function sc_program_player_render_day_panel($program, $day, array $completed_ids, $today) {
    $items = sc_program_get_day_items((int) $day->id);
    $is_today = ((string) $day->program_date === $today);
    $is_past = ((string) $day->program_date < $today);
    $is_future = ((string) $day->program_date > $today);
    ob_start();
    ?>
    <div class="sc-my-program-day-panel is-loaded" data-day="<?php echo esc_attr($day->program_date); ?>" data-day-id="<?php echo (int) $day->id; ?>">
        <?php if ($is_past) : ?>
            <div class="sc-my-day-banner sc-my-day-banner--past" role="status">
                <span class="sc-my-day-banner__icon">📅</span>
                <div>
                    <strong>این روز گذشته است</strong>
                    <p>می‌توانید تمرین‌ها و وضعیت تیک‌ها را ببینید، ولی تغییر تیک فقط برای «امروز» فعال است.</p>
                </div>
            </div>
        <?php elseif ($is_future) : ?>
            <div class="sc-my-day-banner sc-my-day-banner--future" role="status">
                <span class="sc-my-day-banner__icon">⏳</span>
                <div>
                    <strong>هنوز نوبت این روز نرسیده</strong>
                    <p>برنامه را از قبل مرور کنید؛ ثبت انجام تمرین از نیمه‌شب امروز به‌وقت باشگاه فعال می‌شود.</p>
                </div>
            </div>
        <?php else : ?>
            <div class="sc-my-day-banner sc-my-day-banner--today" role="status">
                <span class="sc-my-day-banner__icon">⚡</span>
                <div>
                    <strong>امروز روز تمرین شماست</strong>
                    <p>هر تمرین را بعد از انجام تیک بزنید. می‌توانید تیک را بردارید و دوباره ثبت کنید.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($items)) : ?>
            <div class="sc-my-rest-day" aria-live="polite">
                <div class="sc-my-rest-day__art" aria-hidden="true">
                    <div class="sc-my-coffee">
                        <div class="sc-my-coffee__steam sc-my-coffee__steam--1"></div>
                        <div class="sc-my-coffee__steam sc-my-coffee__steam--2"></div>
                        <div class="sc-my-coffee__steam sc-my-coffee__steam--3"></div>
                        <div class="sc-my-coffee__cup">
                            <div class="sc-my-coffee__rim"></div>
                            <div class="sc-my-coffee__body"></div>
                            <div class="sc-my-coffee__handle"></div>
                        </div>
                        <div class="sc-my-coffee__saucer"></div>
                    </div>
                </div>
                <div class="sc-my-rest-day__copy">
                    <p class="sc-my-rest-day__emoji" aria-hidden="true">🌿☕✨</p>
                    <h3>امروز وقت ریکاوری و نفس‌کشیدن است</h3>
                    <p>
                        مربی برای این روز تمرینی ثبت نکرده؛ یعنی بدنتان فرصت دارد انرژی ذخیره کند، عضله‌ها ترمیم شوند
                        و برای جلسه بعدی آماده‌تر باشید. یک نوشیدنی گرم، خواب کافی و کمی کشش ملایم بهترین برنامهٔ امروز شماست.
                    </p>
                    <p class="sc-my-rest-day__tip">💡 ریکاوری بخشی از پیشرفت است — نه توقف مسیر.</p>
                </div>
            </div>
        <?php else : ?>
            <div class="sc-my-program-items">
                <?php foreach ($items as $item) :
                    $done = in_array((int) $item->id, $completed_ids, true);
                    $has_media = sc_program_normalize_media_type($item->media_type) !== 'none'
                        || (!empty($item->media_url) || !empty($item->file_path) || !empty($item->description));
                    ?>
                    <article class="sc-my-program-item<?php echo $done ? ' is-done' : ''; ?><?php echo !$is_today ? ' is-locked' : ''; ?>"
                             data-item-id="<?php echo (int) $item->id; ?>">
                        <label class="sc-my-tick" title="<?php echo $is_today ? 'ثبت انجام' : 'فقط امروز قابل تیک است'; ?>">
                            <input type="checkbox"
                                   class="sc-my-program-check"
                                   data-item-id="<?php echo (int) $item->id; ?>"
                                   <?php checked($done); ?>
                                   <?php disabled(!$is_today); ?>>
                            <span class="sc-my-tick__box"></span>
                        </label>
                        <div class="sc-my-program-item__body">
                            <h4 class="sc-my-program-item-title"><?php echo esc_html($item->title); ?></h4>
                            <?php if (!empty($item->description)) : ?>
                                <div class="sc-my-program-item-desc"><?php echo wp_kses_post(wpautop(wp_html_excerpt(wp_strip_all_tags($item->description), 160, '…'))); ?></div>
                            <?php endif; ?>
                            <?php if ($has_media) : ?>
                                <button type="button" class="sc_button sc-my-media-toggle" data-item-id="<?php echo (int) $item->id; ?>" aria-expanded="false">
                                    جزئیات و رسانه
                                </button>
                                <div class="sc-my-media-panel" data-item-id="<?php echo (int) $item->id; ?>" hidden>
                                    <div class="sc-my-media-panel__loading">در حال بارگذاری…</div>
                                    <div class="sc-my-media-panel__content"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function sc_program_player_render_program_card($program, $today) {
    $program_id = (int) $program->id;
    $days = sc_program_get_days($program_id);
    $progress = sc_program_progress($program_id, true);
    $completed = sc_program_get_completed_item_ids($program_id);
    $is_complete = ((int) $progress['total'] > 0 && (int) $progress['percent'] >= 100);
    $default_day = $today;
    $has_today = false;
    $today_day = null;
    foreach ($days as $d) {
        if ((string) $d->program_date === $today) {
            $has_today = true;
            $today_day = $d;
            break;
        }
    }
    if (!$has_today && !empty($days)) {
        // نزدیک‌ترین روز به امروز
        $best = $days[0];
        $best_diff = PHP_INT_MAX;
        foreach ($days as $d) {
            $diff = abs(strtotime($d->program_date) - strtotime($today));
            if ($diff < $best_diff) {
                $best_diff = $diff;
                $best = $d;
            }
        }
        $default_day = (string) $best->program_date;
        $today_day = $best;
    }
    ob_start();
    ?>
    <div class="sc-my-program-card" id="sc-my-program-<?php echo $program_id; ?>" data-program-id="<?php echo $program_id; ?>" data-active-day="<?php echo esc_attr($default_day); ?>">
        <?php if ($is_complete) : ?>
            <div class="sc-my-congrats" role="status">
                <span class="sc-my-congrats__burst" aria-hidden="true">🎉</span>
                <div>
                    <strong>آفرین! این برنامه را کامل کردید</strong>
                    <p>همه تمرین‌های تا امروز انجام شده‌اند. همین نظم، مسیر قهرمانی شماست — ادامه بدهید.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="sc-my-progress-block">
            <div class="sc-program-progress sc-program-progress--lg">
                <div class="sc-program-progress-bar"><span style="width:<?php echo (int) $progress['percent']; ?>%"></span></div>
                <div class="sc-my-progress-stats">
                    <strong class="sc-program-progress-pct"><?php echo (int) $progress['percent']; ?>٪</strong>
                    <span class="sc-program-progress-meta"><?php echo esc_html($progress['done'] . ' از ' . $progress['total']); ?></span>
                </div>
            </div>
            <p class="sc-my-progress-encourage<?php echo $is_complete ? ' is-complete' : ''; ?>">
                <?php if ($is_complete) : ?>
                    🌟 عالی بود! نوار پر شد — به خودتان افتخار کنید و برای برنامه بعدی آماده باشید.
                <?php elseif ((int) $progress['percent'] >= 70) : ?>
                    💪 خیلی نزدیکید! چند تیک دیگر تا تکمیل امروز فاصله دارید.
                <?php elseif ((int) $progress['percent'] >= 30) : ?>
                    🔥 خوب پیش می‌روید — هر تیک، یک قدم محکم‌تر.
                <?php else : ?>
                    ✨ شروع کنید؛ همین امروز می‌تواند بهترین روز تمرینتان باشد.
                <?php endif; ?>
            </p>
        </div>

        <div class="sc-my-program-day-tabs" role="tablist">
            <?php foreach ($days as $day) :
                $label = sc_program_player_day_chip_label($day->program_date);
                $is_today_chip = ((string) $day->program_date === $today);
                $is_active_day = ((string) $day->program_date === $default_day);
                ?>
                <button type="button"
                        role="tab"
                        data-day="<?php echo esc_attr($day->program_date); ?>"
                        data-day-id="<?php echo (int) $day->id; ?>"
                        class="sc-my-day-chip<?php echo $is_active_day ? ' is-active' : ''; ?><?php echo $is_today_chip ? ' is-today' : ''; ?>"
                        aria-selected="<?php echo $is_active_day ? 'true' : 'false'; ?>">
                    <span class="sc-my-day-chip__label"><?php echo esc_html($label); ?></span>
                    <?php if ($is_today_chip) : ?><span class="sc-my-day-chip__badge">امروز</span><?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="sc-my-day-panels">
            <?php
            if ($today_day) {
                echo sc_program_player_render_day_panel($program, $today_day, $completed, $today);
            } elseif (empty($days)) {
                echo '<div class="sc-prog-empty-hero sc-prog-empty-hero--soft"><p>روزی در این برنامه ثبت نشده است.</p></div>';
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_action('wp_ajax_sc_program_player_get_program', 'sc_program_ajax_player_get_program');
function sc_program_ajax_player_get_program() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'وارد شوید.'], 401);
    }
    check_ajax_referer('sc_program_toggle', 'nonce');
    $program_id = isset($_POST['program_id']) ? absint($_POST['program_id']) : 0;
    if (!sc_program_player_can_view_program($program_id)) {
        wp_send_json_error(['message' => 'دسترسی به این برنامه ندارید.']);
    }
    $program = sc_program_get($program_id);
    if (!$program || $program->status === 'inactive') {
        wp_send_json_error(['message' => 'این برنامه فعال نیست یا یافت نشد.']);
    }
    $today = sc_program_today_ymd();
    wp_send_json_success([
        'html' => sc_program_player_render_program_card($program, $today),
        'program_id' => $program_id,
    ]);
}

add_action('wp_ajax_sc_program_player_get_day', 'sc_program_ajax_player_get_day');
function sc_program_ajax_player_get_day() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'وارد شوید.'], 401);
    }
    check_ajax_referer('sc_program_toggle', 'nonce');
    $program_id = isset($_POST['program_id']) ? absint($_POST['program_id']) : 0;
    $day_ymd = isset($_POST['day']) ? sanitize_text_field(wp_unslash($_POST['day'])) : '';
    if (!sc_program_player_can_view_program($program_id)) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    $program = sc_program_get($program_id);
    $days = sc_program_get_days($program_id);
    $day = null;
    foreach ($days as $d) {
        if ((string) $d->program_date === $day_ymd) {
            $day = $d;
            break;
        }
    }
    if (!$program || !$day) {
        wp_send_json_error(['message' => 'روز یافت نشد.']);
    }
    $completed = sc_program_get_completed_item_ids($program_id);
    wp_send_json_success([
        'html' => sc_program_player_render_day_panel($program, $day, $completed, sc_program_today_ymd()),
        'day' => $day_ymd,
    ]);
}

add_action('wp_ajax_sc_program_player_get_item_media', 'sc_program_ajax_player_get_item_media');
function sc_program_ajax_player_get_item_media() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'وارد شوید.'], 401);
    }
    check_ajax_referer('sc_program_toggle', 'nonce');
    $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
    $item = sc_program_get_day_item($item_id);
    if (!$item) {
        wp_send_json_error(['message' => 'آیتم یافت نشد.']);
    }
    global $wpdb;
    $day = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . sc_member_program_days_table() . ' WHERE id = %d',
        (int) $item->program_day_id
    ));
    $program = $day ? sc_program_get((int) $day->program_id) : null;
    if (!$program || !sc_program_player_can_view_program((int) $program->id)) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    wp_send_json_success([
        'html' => sc_program_player_render_media_html($item),
        'title' => (string) $item->title,
    ]);
}

