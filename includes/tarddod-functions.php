<?php
/**
 * ثبت تردد — جلسات و اسکن QR
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return string
 */
function sc_tarddod_sessions_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_tarddod_sessions';
}

/**
 * @return string
 */
function sc_tarddod_records_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_tarddod_records';
}

/**
 * @return bool
 */
function sc_tarddod_can_manage() {
    return sc_tarddod_user_can_manage();
}

/**
 * فقط مدیر کل، مدیر باشگاه و مدیر سامانه
 *
 * @param int $user_id
 * @return bool
 */
function sc_tarddod_user_can_manage($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }
    return user_can($user_id, 'manage_options')
        || user_can($user_id, 'club_coach')
        || user_can($user_id, 'system_manager');
}

add_filter('user_has_cap', 'sc_tarddod_manage_cap', 10, 4);
function sc_tarddod_manage_cap($allcaps, $caps, $args, $user) {
    $can = !empty($allcaps['manage_options'])
        || !empty($allcaps['club_coach'])
        || !empty($allcaps['system_manager']);
    if (!$can) {
        return $allcaps;
    }
    foreach ((array) $caps as $cap) {
        if ($cap === 'sc_manage_tarddod') {
            $allcaps['sc_manage_tarddod'] = true;
        }
    }
    return $allcaps;
}

add_action('admin_init', 'sc_tarddod_block_unauthorized_pages');
function sc_tarddod_block_unauthorized_pages() {
    if (!is_admin() || empty($_GET['page'])) {
        return;
    }
    $page = sanitize_text_field(wp_unslash($_GET['page']));
    $tarddod_pages = ['sc-tarddod-register', 'sc-tarddod-records', 'sc-tarddod-sessions', 'sc-tarddod-session-add'];
    if (!in_array($page, $tarddod_pages, true)) {
        return;
    }
    if (sc_tarddod_user_can_manage()) {
        return;
    }
    wp_die(
        '<div style="max-width:560px;margin:40px auto;padding:24px;font-family:Tahoma,sans-serif;direction:rtl;text-align:right;">'
        . '<h2>دسترسی غیرمجاز</h2>'
        . '<p>ثبت تردد فقط برای مدیر کل، مدیر باشگاه و مدیر سامانه فعال است.</p></div>',
        'دسترسی غیرمجاز',
        ['response' => 403, 'back_link' => true]
    );
}

/**
 * @return bool
 */
function sc_tarddod_ensure_db_ready() {
    static $running = false;
    if ($running) {
        return sc_tarddod_table_exists();
    }
    if (sc_tarddod_table_exists()) {
        return true;
    }
    $running = true;
    if (function_exists('sc_create_tarddod_tables')) {
        sc_create_tarddod_tables();
    }
    if (function_exists('sc_update_database')) {
        sc_update_database();
    }
    $running = false;
    return sc_tarddod_table_exists();
}

/**
 * @return bool
 */
function sc_tarddod_table_exists() {
    global $wpdb;
    $sessions = sc_tarddod_sessions_table();
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sessions));
    return is_string($found) && $found === $sessions;
}

/**
 * @param int $session_id
 * @return object|null
 */
function sc_tarddod_get_session($session_id) {
    $session_id = absint($session_id);
    if (!$session_id || !sc_tarddod_ensure_db_ready()) {
        return null;
    }
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `" . sc_tarddod_sessions_table() . "` WHERE id = %d LIMIT 1",
        $session_id
    ));
}

/**
 * @param array $data
 * @return int|false
 */
function sc_tarddod_create_session(array $data) {
    if (!sc_tarddod_ensure_db_ready()) {
        return false;
    }
    global $wpdb;
    $table = sc_tarddod_sessions_table();
    $title = sanitize_text_field((string) ($data['title'] ?? ''));
    if ($title === '') {
        return false;
    }
    $inserted = $wpdb->insert(
        $table,
        [
            'title'        => $title,
            'description'  => sanitize_textarea_field((string) ($data['description'] ?? '')),
            'session_date' => sanitize_text_field((string) ($data['session_date'] ?? '')),
            'session_time' => sanitize_text_field((string) ($data['session_time'] ?? '')),
            'location'     => sanitize_text_field((string) ($data['location'] ?? '')),
            'notes'        => sanitize_textarea_field((string) ($data['notes'] ?? '')),
            'status'       => in_array(($data['status'] ?? 'open'), ['draft', 'open', 'closed'], true) ? $data['status'] : 'open',
            'created_by'   => get_current_user_id(),
            'created_at'   => current_time('mysql'),
            'updated_at'   => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
    );
    return $inserted ? (int) $wpdb->insert_id : false;
}

/**
 * @param int   $session_id
 * @param array $data
 * @return bool
 */
function sc_tarddod_update_session($session_id, array $data) {
    $session_id = absint($session_id);
    if (!$session_id || !sc_tarddod_ensure_db_ready()) {
        return false;
    }
    global $wpdb;
    $table = sc_tarddod_sessions_table();
    $fields = [
        'title'        => sanitize_text_field((string) ($data['title'] ?? '')),
        'description'  => sanitize_textarea_field((string) ($data['description'] ?? '')),
        'session_date' => sanitize_text_field((string) ($data['session_date'] ?? '')),
        'session_time' => sanitize_text_field((string) ($data['session_time'] ?? '')),
        'location'     => sanitize_text_field((string) ($data['location'] ?? '')),
        'notes'        => sanitize_textarea_field((string) ($data['notes'] ?? '')),
        'status'       => in_array(($data['status'] ?? 'open'), ['draft', 'open', 'closed'], true) ? $data['status'] : 'open',
        'updated_at'   => current_time('mysql'),
    ];
    $updated = $wpdb->update($table, $fields, ['id' => $session_id], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'], ['%d']);
    return $updated !== false;
}

/**
 * @param array $args
 * @return array{items:object[],total:int}
 */
function sc_tarddod_query_sessions(array $args = []) {
    sc_tarddod_ensure_db_ready();
    global $wpdb;
    $table = sc_tarddod_sessions_table();
    $search = trim((string) ($args['search'] ?? ''));
    $status = sanitize_text_field((string) ($args['status'] ?? ''));
    $paged = max(1, absint($args['paged'] ?? 1));
    $per_page = max(10, min(100, absint($args['per_page'] ?? 20)));
    $offset = ($paged - 1) * $per_page;
    $where = ['1=1'];
    $prepare = [];
    if ($status !== '' && in_array($status, ['draft', 'open', 'closed'], true)) {
        $where[] = 'status = %s';
        $prepare[] = $status;
    }
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(title LIKE %s OR description LIKE %s OR location LIKE %s)';
        $prepare[] = $like;
        $prepare[] = $like;
        $prepare[] = $like;
    }
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM `$table` WHERE $where_sql";
    $list_sql = "SELECT * FROM `$table` WHERE $where_sql ORDER BY session_date DESC, session_time DESC, id DESC LIMIT %d OFFSET %d";
    if ($prepare) {
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $prepare));
        $items = $wpdb->get_results($wpdb->prepare($list_sql, array_merge($prepare, [$per_page, $offset])));
    } else {
        $total = (int) $wpdb->get_var($count_sql);
        $items = $wpdb->get_results($wpdb->prepare($list_sql, $per_page, $offset));
    }
    return ['items' => is_array($items) ? $items : [], 'total' => $total];
}

/**
 * @param array $args
 * @return array{items:object[],total:int}
 */
function sc_tarddod_query_records(array $args = []) {
    sc_tarddod_ensure_db_ready();
    global $wpdb;
    $records = sc_tarddod_records_table();
    $sessions = sc_tarddod_sessions_table();
    $session_id = absint($args['session_id'] ?? 0);
    $search = trim((string) ($args['search'] ?? ''));
    $paged = max(1, absint($args['paged'] ?? 1));
    $per_page = max(10, min(100, absint($args['per_page'] ?? 25)));
    $offset = ($paged - 1) * $per_page;
    $where = ['1=1'];
    $prepare = [];
    if ($session_id > 0) {
        $where[] = 'r.session_id = %d';
        $prepare[] = $session_id;
    }
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = 'r.subject_name LIKE %s';
        $prepare[] = $like;
    }
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM `$records` r WHERE $where_sql";
    $list_sql = "SELECT r.*, s.title AS session_title, s.session_date, s.session_time
        FROM `$records` r
        LEFT JOIN `$sessions` s ON s.id = r.session_id
        WHERE $where_sql ORDER BY r.id DESC LIMIT %d OFFSET %d";
    if ($prepare) {
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $prepare));
        $items = $wpdb->get_results($wpdb->prepare($list_sql, array_merge($prepare, [$per_page, $offset])));
    } else {
        $total = (int) $wpdb->get_var($count_sql);
        $items = $wpdb->get_results($wpdb->prepare($list_sql, $per_page, $offset));
    }
    return ['items' => is_array($items) ? $items : [], 'total' => $total];
}

/**
 * @param int $session_id
 * @return object[]
 */
function sc_tarddod_get_session_records($session_id) {
    $result = sc_tarddod_query_records(['session_id' => absint($session_id), 'per_page' => 500, 'paged' => 1]);
    return $result['items'];
}

/**
 * @param string $code
 * @param string $subject_type member|staff
 * @return string
 */
function sc_tarddod_qr_error_message($code, $subject_type = 'member') {
    $is_staff = $subject_type === 'staff';
    $map = [
        'invalid_qr'   => 'کد QR نامعتبر است. QR را کامل و واضح جلوی دوربین بگیرید.',
        'unknown_qr'   => $is_staff ? 'QR پرسنل شناسایی نشد.' : 'بازیکن مرتبط با این QR یافت نشد.',
        'qr_disabled'  => $is_staff ? 'QR پرسنل موقتاً غیرفعال است.' : 'این QR موقتاً غیرفعال است.',
        'qr_inactive'  => $is_staff ? 'این QR پرسنل فعال نیست.' : 'این QR دیگر فعال نیست.',
    ];
    return $map[$code] ?? 'QR شناسایی نشد.';
}

/**
 * @param string $payload
 * @return array{success:bool,code:string,message:string,subject_name?:string,subject_type?:string,subject_id?:int}
 */
function sc_tarddod_resolve_qr_payload($payload) {
    $payload = trim((string) $payload);
    if ($payload === '') {
        return ['success' => false, 'code' => 'invalid_qr', 'message' => sc_tarddod_qr_error_message('invalid_qr')];
    }

    $is_staff_payload = stripos($payload, SC_STAFF_QR_PREFIX) === 0;

    if ($is_staff_payload && function_exists('sc_staff_qr_resolve_scan_hash')) {
        $resolved = sc_staff_qr_resolve_scan_hash($payload);
        if ($resolved['scan_code'] === 'invalid_qr') {
            return ['success' => false, 'code' => 'invalid_qr', 'message' => sc_tarddod_qr_error_message('invalid_qr', 'staff')];
        }
        if ($resolved['scan_code'] === 'qr_disabled') {
            return ['success' => false, 'code' => 'qr_disabled', 'message' => sc_tarddod_qr_error_message('qr_disabled', 'staff'), 'subject_name' => $resolved['subject_name']];
        }
        if ($resolved['scan_code'] === 'qr_inactive') {
            return ['success' => false, 'code' => 'qr_inactive', 'message' => sc_tarddod_qr_error_message('qr_inactive', 'staff'), 'subject_name' => $resolved['subject_name']];
        }
        if ($resolved['scan_code'] !== 'ok' || !$resolved['user']) {
            return ['success' => false, 'code' => 'unknown_qr', 'message' => sc_tarddod_qr_error_message('unknown_qr', 'staff')];
        }
        return [
            'success'      => true,
            'code'         => 'ok',
            'message'      => 'شناسایی شد.',
            'subject_type' => 'staff',
            'subject_id'   => (int) $resolved['user']->ID,
            'subject_name' => $resolved['subject_name'],
        ];
    }

    if (!$is_staff_payload && function_exists('sc_attendance_qr_resolve_scan_hash')) {
        $hash = function_exists('sc_attendance_qr_parse_payload') ? sc_attendance_qr_parse_payload($payload) : '';
        if ($hash === '') {
            return ['success' => false, 'code' => 'invalid_qr', 'message' => sc_tarddod_qr_error_message('invalid_qr')];
        }

        $resolved = sc_attendance_qr_resolve_scan_hash($hash, [
            'allow_inactive_member' => true,
            'allow_inactive_qr'     => true,
        ]);
        $member_name = $resolved['member']
            ? trim((string) $resolved['member']->first_name . ' ' . (string) $resolved['member']->last_name)
            : '';

        if ($resolved['scan_code'] === 'invalid_qr') {
            return ['success' => false, 'code' => 'invalid_qr', 'message' => sc_tarddod_qr_error_message('invalid_qr'), 'subject_name' => $member_name];
        }
        if ($resolved['scan_code'] === 'qr_disabled') {
            return ['success' => false, 'code' => 'qr_disabled', 'message' => sc_tarddod_qr_error_message('qr_disabled'), 'subject_name' => $member_name];
        }
        if ($resolved['scan_code'] === 'qr_inactive') {
            return ['success' => false, 'code' => 'qr_inactive', 'message' => sc_tarddod_qr_error_message('qr_inactive'), 'subject_name' => $member_name];
        }
        if ($resolved['scan_code'] !== 'ok' || !$resolved['member']) {
            return ['success' => false, 'code' => 'unknown_qr', 'message' => sc_tarddod_qr_error_message('unknown_qr'), 'subject_name' => $member_name];
        }
        return [
            'success'      => true,
            'code'         => 'ok',
            'message'      => 'شناسایی شد.',
            'subject_type' => 'member',
            'subject_id'   => (int) $resolved['member']->id,
            'subject_name' => $member_name,
        ];
    }

    return ['success' => false, 'code' => 'unknown_qr', 'message' => sc_tarddod_qr_error_message('unknown_qr')];
}

/**
 * @param int    $session_id
 * @param string $payload
 * @param int    $scanner_user_id
 * @return array{success:bool,code:string,message:string,subject_name?:string,is_new?:bool}
 */
function sc_tarddod_register_scan($session_id, $payload, $scanner_user_id = 0) {
    $session_id = absint($session_id);
    if (!$session_id) {
        return ['success' => false, 'code' => 'invalid', 'message' => 'جلسه انتخاب نشده است.'];
    }
    $session = sc_tarddod_get_session($session_id);
    if (!$session) {
        return ['success' => false, 'code' => 'invalid', 'message' => 'جلسه یافت نشد.'];
    }
    if ($session->status === 'closed') {
        return ['success' => false, 'code' => 'session_closed', 'message' => 'این جلسه بسته شده است.'];
    }
    if ($session->status === 'draft') {
        return ['success' => false, 'code' => 'session_draft', 'message' => 'جلسه هنوز باز نشده است.'];
    }

    $resolved = sc_tarddod_resolve_qr_payload($payload);
    if (empty($resolved['success'])) {
        return [
            'success'      => false,
            'code'         => $resolved['code'],
            'message'      => $resolved['message'],
            'subject_name' => $resolved['subject_name'] ?? '',
        ];
    }

    global $wpdb;
    $table = sc_tarddod_records_table();
    $exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `$table` WHERE session_id = %d AND subject_type = %s AND subject_id = %d",
        $session_id,
        $resolved['subject_type'],
        $resolved['subject_id']
    ));
    if ($exists > 0) {
        return [
            'success'      => true,
            'code'         => 'duplicate',
            'message'      => 'قبلاً در این جلسه ثبت شده است.',
            'subject_name' => $resolved['subject_name'],
            'is_new'       => false,
            'record_id'    => 0,
            'session_title'=> $session->title ?? '',
        ];
    }

    $inserted = $wpdb->insert(
        $table,
        [
            'session_id'    => $session_id,
            'subject_type'  => $resolved['subject_type'],
            'subject_id'    => $resolved['subject_id'],
            'subject_name'  => sanitize_text_field($resolved['subject_name']),
            'record_method' => 'qr',
            'scanned_by'    => absint($scanner_user_id ?: get_current_user_id()),
            'created_at'    => current_time('mysql'),
        ],
        ['%d', '%s', '%d', '%s', '%s', '%d', '%s']
    );
    if (!$inserted) {
        return ['success' => false, 'code' => 'db_error', 'message' => 'خطا در ثبت تردد.'];
    }

    return [
        'success'      => true,
        'code'         => 'created',
        'message'      => 'تردد با موفقیت ثبت شد.',
        'subject_name' => $resolved['subject_name'],
        'subject_type' => $resolved['subject_type'],
        'subject_id'   => $resolved['subject_id'],
        'is_new'       => true,
        'record_id'    => (int) $wpdb->insert_id,
        'session_title'=> $session->title ?? '',
        'snapshot'     => function_exists('sc_qr_scan_photo_is_enabled') && sc_qr_scan_photo_is_enabled(),
    ];
}

add_action('wp_ajax_sc_tarddod_scan', 'sc_ajax_tarddod_scan');
function sc_ajax_tarddod_scan() {
    check_ajax_referer('sc_tarddod_scan', 'nonce');
    if (!sc_tarddod_can_manage()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.', 'code' => 'forbidden']);
    }
    $session_id = absint($_POST['session_id'] ?? 0);
    $payload = wp_unslash($_POST['qr_payload'] ?? '');
    $result = sc_tarddod_register_scan($session_id, $payload, get_current_user_id());
    if (empty($result['success'])) {
        wp_send_json_error([
            'message'      => $result['message'],
            'code'         => $result['code'],
            'subject_name' => $result['subject_name'] ?? '',
        ]);
    }
    $record_id = isset($result['record_id']) ? (int) $result['record_id'] : 0;
    if ($record_id && ($result['code'] ?? '') === 'created'
        && function_exists('sc_qr_scan_photo_handle_post_for_record')) {
        $photos = sc_qr_scan_photo_handle_post_for_record('tarddod', $record_id);
        $result['photo_url'] = $photos['rear_url'];
        $result['photo_front_url'] = $photos['front_url'];
    }
    wp_send_json_success($result);
}

add_action('wp_ajax_sc_tarddod_session_records', 'sc_ajax_tarddod_session_records');
function sc_ajax_tarddod_session_records() {
    check_ajax_referer('sc_tarddod_scan', 'nonce');
    if (!sc_tarddod_can_manage()) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
    }
    $session_id = absint($_POST['session_id'] ?? 0);
    wp_send_json_success([
        'records' => sc_tarddod_get_session_records($session_id),
        'count'   => count(sc_tarddod_get_session_records($session_id)),
    ]);
}

/**
 * @return array
 */
function sc_tarddod_get_open_sessions_for_select() {
    sc_tarddod_ensure_db_ready();
    global $wpdb;
    $table = sc_tarddod_sessions_table();
    $rows = $wpdb->get_results(
        "SELECT id, title, session_date, session_time, location, status FROM `$table`
         WHERE status = 'open' ORDER BY session_date DESC, session_time DESC, id DESC LIMIT 100"
    );
    return is_array($rows) ? $rows : [];
}

function sc_tarddod_status_label($status) {
    $map = ['draft' => 'پیش‌نویس', 'open' => 'باز', 'closed' => 'بسته'];
    return $map[$status] ?? (string) $status;
}

function sc_tarddod_subject_type_label($type) {
    return $type === 'staff' ? 'پرسنل' : 'بازیکن';
}
