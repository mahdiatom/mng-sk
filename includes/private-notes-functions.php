<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_private_notes_table() { global $wpdb; return $wpdb->prefix . 'sc_private_notes'; }
function sc_private_note_threads_table() { global $wpdb; return $wpdb->prefix . 'sc_private_note_threads'; }
function sc_private_note_messages_table() { global $wpdb; return $wpdb->prefix . 'sc_private_note_messages'; }

function sc_private_notes_get_member_user_id($member_id) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $members_table WHERE id = %d LIMIT 1",
        absint($member_id)
    ));
}

function sc_private_notes_get_thread($thread_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . sc_private_note_threads_table() . " WHERE id = %d LIMIT 1", absint($thread_id)));
}

function sc_private_notes_set_last_selected_thread($member_id, $thread_id, $user_id = 0) {
    $member_id = absint($member_id);
    $thread_id = absint($thread_id);
    if ($user_id <= 0) $user_id = get_current_user_id();
    if ($member_id <= 0 || $thread_id <= 0 || $user_id <= 0) return;
    update_user_meta($user_id, '_sc_private_notes_last_thread_' . $member_id, $thread_id);
}

function sc_private_notes_get_last_selected_thread($member_id, $user_id = 0) {
    $member_id = absint($member_id);
    if ($user_id <= 0) $user_id = get_current_user_id();
    if ($member_id <= 0 || $user_id <= 0) return 0;
    return absint(get_user_meta($user_id, '_sc_private_notes_last_thread_' . $member_id, true));
}

function sc_private_notes_get_member_ids_for_coach($coach_id) {
    if ($coach_id <= 0 || !function_exists('sc_support_get_members_for_coach')) {
        return [];
    }
    $members = sc_support_get_members_for_coach($coach_id);
    if (!is_array($members)) {
        return [];
    }
    return array_values(array_unique(array_filter(array_map('absint', wp_list_pluck($members, 'member_id')))));
}

/**
 * Restrict member IDs to those a coach is allowed to contact.
 */
function sc_private_notes_filter_member_ids_for_coach($member_ids, $coach_id = 0) {
    $member_ids = array_values(array_unique(array_filter(array_map('absint', (array) $member_ids))));
    if ($coach_id <= 0 && function_exists('sc_support_get_coach_id_by_user_id')) {
        $coach_id = (int) sc_support_get_coach_id_by_user_id(get_current_user_id());
    }
    if ($coach_id <= 0) {
        return [];
    }
    $allowed = sc_private_notes_get_member_ids_for_coach($coach_id);
    if (empty($allowed)) {
        return [];
    }
    return array_values(array_intersect($member_ids, $allowed));
}

function sc_private_notes_can_access_member($member_id, $user_id = 0) {
    $member_id = absint($member_id);
    if ($member_id <= 0) {
        return false;
    }
    if ($user_id <= 0) {
        $user_id = get_current_user_id();
    }
    if ($user_id <= 0) {
        return false;
    }
    if (user_can($user_id, 'manage_options')) {
        return true;
    }
    if (!function_exists('sc_support_get_coach_id_by_user_id')) {
        return false;
    }
    $coach_id = (int) sc_support_get_coach_id_by_user_id($user_id);
    if ($coach_id <= 0) {
        return false;
    }
    $allowed_member_ids = sc_private_notes_get_member_ids_for_coach($coach_id);
    return in_array($member_id, $allowed_member_ids, true);
}

function sc_private_notes_can_view_thread($thread, $user_id = 0) {
    if (!$thread) return false;
    if ($user_id <= 0) $user_id = get_current_user_id();
    if ($user_id <= 0) return false;
    if (user_can($user_id, 'manage_options')) return true;
    if ((int) $thread->user_id === (int) $user_id) return true;
    return sc_private_notes_can_access_member((int) $thread->member_id, $user_id);
}

function sc_private_notes_allowed_mimes() {
    if (function_exists('sc_support_allowed_mime_types')) {
        return sc_support_allowed_mime_types();
    }
    return [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
    ];
}

function sc_private_notes_validate_attachment_ids($ids, $max = 5) {
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return [];
    }
    if (is_string($ids)) {
        $ids = array_map('absint', array_filter(explode(',', $ids)));
    } else {
        $ids = is_array($ids) ? array_map('absint', $ids) : [];
    }
    $ids = array_slice(array_unique(array_filter($ids)), 0, absint($max));
    $valid = [];
    foreach ($ids as $aid) {
        if ($aid <= 0) {
            continue;
        }
        $post = get_post($aid);
        if (!$post || $post->post_type !== 'attachment') {
            continue;
        }
        if ((int) get_post_meta($aid, '_sc_private_note_attachment', true) !== 1) {
            continue;
        }
        if ((int) get_post_meta($aid, '_sc_private_note_uploaded_by', true) !== (int) $user_id) {
            continue;
        }
        $valid[] = $aid;
    }
    return $valid;
}

add_action('wp_ajax_sc_upload_private_note_attachment', 'sc_ajax_upload_private_note_attachment');
function sc_ajax_upload_private_note_attachment() {
    if (!is_user_logged_in() || (!current_user_can('manage_options') && !current_user_can('sc_view_coach_salary'))) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    if (!isset($_POST['sc_private_note_upload_nonce']) || !wp_verify_nonce($_POST['sc_private_note_upload_nonce'], 'sc_private_note_upload_attachment')) {
        wp_send_json_error(['message' => 'خطای امنیتی.']);
    }
    $key = isset($_FILES['file']) ? 'file' : (isset($_FILES['private_note_attachment']) ? 'private_note_attachment' : null);
    if (!$key || empty($_FILES[$key]['name']) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => 'فایل معتبر انتخاب نشده است.']);
    }

    $file = $_FILES[$key];
    $allowed = sc_private_notes_allowed_mimes();
    $max_size = 5 * 1024 * 1024;
    if (!empty($file['size']) && (int) $file['size'] > $max_size) {
        wp_send_json_error(['message' => 'حداکثر حجم هر فایل ۵ مگابایت است.']);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = [];
    foreach (array_keys($allowed) as $k) {
        $allowed_ext = array_merge($allowed_ext, explode('|', $k));
    }
    if (!in_array($ext, array_unique($allowed_ext), true)) {
        wp_send_json_error(['message' => 'فرمت فایل مجاز نیست.']);
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $upload = wp_handle_upload($file, ['test_form' => false, 'test_type' => false, 'mimes' => $allowed]);
    if (isset($upload['error'])) {
        wp_send_json_error(['message' => $upload['error']]);
    }

    $attachment = [
        'post_mime_type' => $upload['type'],
        'post_title' => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
        'post_content' => '',
        'post_status' => 'inherit',
    ];
    $attach_id = wp_insert_attachment($attachment, $upload['file']);
    if (is_wp_error($attach_id)) {
        wp_send_json_error(['message' => 'خطا در ذخیره فایل.']);
    }
    update_post_meta($attach_id, '_sc_private_note_attachment', 1);
    update_post_meta($attach_id, '_sc_private_note_uploaded_by', get_current_user_id());
    wp_send_json_success(['id' => $attach_id, 'name' => basename($upload['file'])]);
}

function sc_private_notes_create_thread_for_member($member_id, $subject = '') {
    global $wpdb;
    $member_id = absint($member_id);
    $current_user_id = get_current_user_id();
    if ($member_id <= 0 || $current_user_id <= 0) return new WP_Error('invalid_data', 'کاربر نامعتبر است.');
    if (!sc_private_notes_can_access_member($member_id, $current_user_id)) return new WP_Error('forbidden', 'دسترسی غیرمجاز.');
    $target_user_id = sc_private_notes_get_member_user_id($member_id);
    if ($target_user_id <= 0) return new WP_Error('invalid_user', 'کاربر وردپرس برای بازیکن یافت نشد.');
    $author_coach_id = null;
    if (!current_user_can('manage_options') && function_exists('sc_support_get_coach_id_by_user_id')) {
        $author_coach_id = (int) sc_support_get_coach_id_by_user_id($current_user_id);
    }
    $now = current_time('mysql');
    $ok = $wpdb->insert(sc_private_note_threads_table(), [
        'member_id' => $member_id,
        'user_id' => $target_user_id,
        'created_by_user_id' => $current_user_id,
        'created_by_coach_id' => $author_coach_id ?: null,
        'subject' => sanitize_text_field($subject),
        'is_open' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d','%d','%d','%d','%s','%d','%s','%s']);
    if ($ok === false) return new WP_Error('db_error', 'خطا در ایجاد پرونده.');
    return (int) $wpdb->insert_id;
}

function sc_private_notes_get_or_create_default_thread($member_id) {
    global $wpdb;
    $member_id = absint($member_id);
    $last_thread_id = sc_private_notes_get_last_selected_thread($member_id, get_current_user_id());
    if ($last_thread_id > 0) {
        $last_thread = sc_private_notes_get_thread($last_thread_id);
        if ($last_thread && (int) $last_thread->member_id === $member_id) return (int) $last_thread_id;
    }
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM " . sc_private_note_threads_table() . " WHERE member_id = %d ORDER BY id DESC LIMIT 1",
        $member_id
    ));
    if ($existing) return (int) $existing;
    return sc_private_notes_create_thread_for_member($member_id, '');
}

function sc_private_notes_append_message_to_thread($thread_id, $content, $attachment_ids = []) {
    global $wpdb;
    $thread = sc_private_notes_get_thread($thread_id);
    if (!$thread) return new WP_Error('not_found', 'پرونده یافت نشد.');
    if (!sc_private_notes_can_view_thread($thread, get_current_user_id()) || (int) $thread->user_id === (int) get_current_user_id()) {
        return new WP_Error('forbidden', 'دسترسی غیرمجاز.');
    }
    $content = wp_kses_post($content);
    if (trim(wp_strip_all_tags($content)) === '') return new WP_Error('empty_message', 'متن پیام الزامی است.');
    $attachment_ids = sc_private_notes_validate_attachment_ids($attachment_ids, 5);
    $author_type = current_user_can('manage_options') ? 'admin' : 'coach';
    $author_coach_id = null;
    if ($author_type === 'coach' && function_exists('sc_support_get_coach_id_by_user_id')) {
        $author_coach_id = (int) sc_support_get_coach_id_by_user_id(get_current_user_id());
    }
    $now = current_time('mysql');
    $ok = $wpdb->insert(sc_private_note_messages_table(), [
        'thread_id' => (int) $thread->id,
        'author_type' => $author_type,
        'author_user_id' => get_current_user_id(),
        'author_coach_id' => $author_coach_id ?: null,
        'content' => $content,
        'attachment_ids' => !empty($attachment_ids) ? wp_json_encode(array_map('absint', $attachment_ids)) : null,
        'created_at' => $now,
    ], ['%d','%s','%d','%d','%s','%s','%s']);
    if ($ok === false) return new WP_Error('db_error', 'خطا در ثبت پیام.');
    $wpdb->update(sc_private_note_threads_table(), ['updated_at' => $now], ['id' => (int) $thread->id], ['%s'], ['%d']);
    return (int) $wpdb->insert_id;
}

function sc_private_notes_create_for_members($member_ids, $title, $content, $attachment_ids = [], $mode = 'append_to_default_thread', $selected_thread_id = 0) {
    global $wpdb;
    $member_ids = array_values(array_unique(array_filter(array_map('absint', (array) $member_ids))));
    $content = wp_kses_post($content);
    if (empty($member_ids) || trim($content) === '') {
        return new WP_Error('invalid_data', 'متن و کاربر الزامی است.');
    }
    $created = 0;
    foreach ($member_ids as $member_id) {
        if ($mode === 'create_new_thread') {
            $thread_id = sc_private_notes_create_thread_for_member($member_id, $title);
        } elseif ($mode === 'append_to_selected_thread' && $selected_thread_id > 0) {
            $candidate = sc_private_notes_get_thread($selected_thread_id);
            $thread_id = ($candidate && (int) $candidate->member_id === (int) $member_id) ? (int) $selected_thread_id : sc_private_notes_get_or_create_default_thread($member_id);
        } else {
            $thread_id = sc_private_notes_get_or_create_default_thread($member_id);
        }
        if (is_wp_error($thread_id) || !$thread_id) continue;
        $msg_id = sc_private_notes_append_message_to_thread((int) $thread_id, $content, $attachment_ids);
        if (!is_wp_error($msg_id)) {
            sc_private_notes_set_last_selected_thread($member_id, (int) $thread_id, get_current_user_id());
            $created++;
        }
    }
    return $created;
}

function sc_private_notes_get($id) {
    global $wpdb;
    $table = sc_private_notes_table();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", absint($id)));
}

function sc_private_notes_can_view($note, $user_id = 0) {
    if (!$note) {
        return false;
    }
    if ($user_id <= 0) {
        $user_id = get_current_user_id();
    }
    if ($user_id <= 0) {
        return false;
    }
    if (user_can($user_id, 'manage_options')) {
        return true;
    }
    if ((int) $note->user_id === (int) $user_id) {
        return true;
    }
    if (!function_exists('sc_support_get_coach_id_by_user_id')) {
        return false;
    }
    $coach_id = (int) sc_support_get_coach_id_by_user_id($user_id);
    if ($coach_id <= 0) {
        return false;
    }
    return sc_private_notes_can_access_member((int) $note->member_id, $user_id);
}

function sc_private_notes_attachment_download_url($attachment_id, $note_id) {
    return add_query_arg([
        'sc_private_note_attachment' => (int) $attachment_id,
        'sc_private_note_ref_id' => (int) $note_id,
        'nonce' => wp_create_nonce('sc_private_note_attachment_' . (int) $attachment_id . '_' . (int) $note_id),
    ], home_url('/'));
}

add_action('template_redirect', 'sc_private_notes_attachment_download_handle');
function sc_private_notes_attachment_download_handle() {
    $aid = isset($_GET['sc_private_note_attachment']) ? absint($_GET['sc_private_note_attachment']) : 0;
    $ref_id = isset($_GET['sc_private_note_ref_id']) ? absint($_GET['sc_private_note_ref_id']) : 0;
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if ($aid <= 0 || $ref_id <= 0 || !wp_verify_nonce($nonce, 'sc_private_note_attachment_' . $aid . '_' . $ref_id)) {
        return;
    }
    if ((int) get_post_meta($aid, '_sc_private_note_attachment', true) !== 1) {
        status_header(404);
        exit;
    }
    $msg = $GLOBALS['wpdb']->get_row($GLOBALS['wpdb']->prepare("SELECT * FROM " . sc_private_note_messages_table() . " WHERE id = %d", $ref_id));
    $legacy_note = null;
    if (!$msg) {
        $legacy_note = sc_private_notes_get($ref_id);
    }
    if ($msg) {
        $thread = sc_private_notes_get_thread((int) $msg->thread_id);
        if (!$thread || !sc_private_notes_can_view_thread($thread, get_current_user_id())) { status_header(403); exit; }
        $ids = !empty($msg->attachment_ids) ? json_decode($msg->attachment_ids, true) : [];
    } elseif ($legacy_note && sc_private_notes_can_view($legacy_note, get_current_user_id())) {
        $ids = !empty($legacy_note->attachment_ids) ? json_decode($legacy_note->attachment_ids, true) : [];
    } else {
        status_header(403);
        exit;
    }
    $ids = is_array($ids) ? array_map('absint', $ids) : [];
    if (!in_array($aid, $ids, true)) {
        status_header(404);
        exit;
    }
    $file = get_attached_file($aid);
    if (!$file || !file_exists($file) || !is_readable($file)) {
        status_header(404);
        exit;
    }
    header('Content-Type: ' . get_post_mime_type($aid));
    header('Content-Disposition: attachment; filename="' . esc_attr(basename($file)) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

function sc_private_notes_get_thread_messages($thread_id, $args = []) {
    global $wpdb;
    $thread_id = absint($thread_id);
    if ($thread_id <= 0) {
        return [];
    }
    $messages = sc_private_note_messages_table();
    $coaches = $wpdb->prefix . 'sc_coaches';
    $where = ['m.thread_id = %d'];
    $values = [$thread_id];
    if (!empty($args['date_from'])) {
        $where[] = 'DATE(m.created_at) >= %s';
        $values[] = sanitize_text_field((string) $args['date_from']);
    }
    if (!empty($args['date_to'])) {
        $where[] = 'DATE(m.created_at) <= %s';
        $values[] = sanitize_text_field((string) $args['date_to']);
    }
    if (!empty($args['search'])) {
        $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
        $where[] = 'm.content LIKE %s';
        $values[] = $like;
    }
    $where_sql = implode(' AND ', $where);
    $sql = "SELECT m.*, TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) AS coach_name
         FROM $messages m
         LEFT JOIN $coaches c ON c.id = m.author_coach_id
         WHERE $where_sql
         ORDER BY m.created_at ASC, m.id ASC";
    return $wpdb->get_results($wpdb->prepare($sql, $values));
}

function sc_private_notes_query_admin($args = []) {
    global $wpdb;
    $table = sc_private_note_threads_table();
    $messages = sc_private_note_messages_table();
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $where = ['1=1'];
    $values = [];

    $current_user_id = get_current_user_id();
    $is_admin = current_user_can('manage_options');
    $coach_id = (!$is_admin && function_exists('sc_support_get_coach_id_by_user_id')) ? (int) sc_support_get_coach_id_by_user_id($current_user_id) : 0;
    if (!$is_admin) {
        if ($coach_id <= 0) {
            $where[] = '1=0';
        } else {
            $allowed = sc_private_notes_get_member_ids_for_coach($coach_id);
            if (empty($allowed)) {
                $where[] = '1=0';
            } else {
                $ph = implode(',', array_fill(0, count($allowed), '%d'));
                $where[] = "n.member_id IN ($ph)";
                $values = array_merge($values, $allowed);
            }
        }
    }

    if (!empty($args['member_id'])) {
        $where[] = 'n.member_id = %d';
        $values[] = absint($args['member_id']);
    }
    if (!empty($args['date_from'])) {
        $where[] = 'DATE(n.updated_at) >= %s';
        $values[] = sanitize_text_field($args['date_from']);
    }
    if (!empty($args['date_to'])) {
        $where[] = 'DATE(n.updated_at) <= %s';
        $values[] = sanitize_text_field($args['date_to']);
    }
    if (!empty($args['search'])) {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = '(n.subject LIKE %s OR EXISTS(SELECT 1 FROM ' . $messages . ' m2 WHERE m2.thread_id = n.id AND m2.content LIKE %s))';
        $values[] = $like;
        $values[] = $like;
    }

    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM $table n WHERE $where_sql";
    $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $values));

    $per_page = !empty($args['per_page']) ? max(1, absint($args['per_page'])) : 20;
    $page = !empty($args['page']) ? max(1, absint($args['page'])) : 1;
    $offset = ($page - 1) * $per_page;

    $sql = "SELECT n.*,
            TRIM(CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,''))) AS member_name,
            m.national_id,
            (SELECT COUNT(*) FROM $messages mm WHERE mm.thread_id = n.id) AS messages_count,
            (SELECT mm.content FROM $messages mm WHERE mm.thread_id = n.id ORDER BY mm.id DESC LIMIT 1) AS last_message_excerpt
            FROM $table n
            LEFT JOIN $members_table m ON m.id = n.member_id
            WHERE $where_sql
            ORDER BY n.updated_at DESC
            LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($values, [$per_page, $offset])));

    return [
        'rows' => is_array($rows) ? $rows : [],
        'total' => $total,
        'per_page' => $per_page,
        'page' => $page,
        'total_pages' => (int) max(1, ceil($total / $per_page)),
    ];
}

function sc_private_notes_get_user_notes($user_id, $args = []) {
    $q = is_array($args) ? $args : [];
    if (isset($q['limit']) && !isset($q['per_page'])) {
        $q['per_page'] = max(1, absint($q['limit']));
    }
    if (isset($q['offset']) && isset($q['per_page']) && !isset($q['page'])) {
        $off = max(0, absint($q['offset']));
        $pp = max(1, absint($q['per_page']));
        $q['page'] = (int) floor($off / $pp) + 1;
    }
    $res = sc_private_notes_query_user_threads($user_id, $q);
    return isset($res['rows']) ? $res['rows'] : [];
}

function sc_private_notes_count_user_notes($user_id, $search = '') {
    $res = sc_private_notes_query_user_threads($user_id, [
        'search' => $search,
        'per_page' => 1,
        'page' => 1,
    ]);
    return isset($res['total']) ? (int) $res['total'] : 0;
}

/**
 * لیست پرونده‌های یادداشت خصوصی یک کاربر وردپرس (حساب کاربری) با جستجو و صفحه‌بندی.
 *
 * @param int   $user_id
 * @param array $args search, per_page, page
 * @return array{rows: array, total: int, per_page: int, page: int, total_pages: int}
 */
function sc_private_notes_query_user_threads($user_id, $args = []) {
    global $wpdb;
    $table = sc_private_note_threads_table();
    $messages = sc_private_note_messages_table();
    $user_id = absint($user_id);
    $where = ['n.user_id = %d'];
    $values = [$user_id];
    $search = isset($args['search']) ? sanitize_text_field((string) $args['search']) : '';
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(n.subject LIKE %s OR EXISTS (SELECT 1 FROM ' . $messages . ' mm WHERE mm.thread_id = n.id AND mm.content LIKE %s))';
        $values[] = $like;
        $values[] = $like;
    }
    $where_sql = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM $table n WHERE $where_sql";
    $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $values));

    $per_page = !empty($args['per_page']) ? max(1, absint($args['per_page'])) : 20;
    $page = !empty($args['page']) ? max(1, absint($args['page'])) : 1;
    $offset = ($page - 1) * $per_page;

    $sql = "SELECT n.*,
            (SELECT COUNT(*) FROM $messages mm WHERE mm.thread_id = n.id) AS messages_count,
            (SELECT mm.content FROM $messages mm WHERE mm.thread_id = n.id ORDER BY mm.id DESC LIMIT 1) AS last_message
            FROM $table n
            WHERE $where_sql
            ORDER BY n.updated_at DESC
            LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($values, [$per_page, $offset])));

    return [
        'rows' => is_array($rows) ? $rows : [],
        'total' => $total,
        'per_page' => $per_page,
        'page' => $page,
        'total_pages' => (int) max(1, ceil($total / $per_page)),
    ];
}

function sc_private_notes_get_legacy_user_notes($user_id, $limit = 20, $offset = 0) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM " . sc_private_notes_table() . " WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
        absint($user_id),
        absint($limit),
        absint($offset)
    ));
}

add_action('wp_ajax_sc_private_notes_preview_recipients', 'sc_private_notes_preview_recipients_ajax');
function sc_private_notes_preview_recipients_ajax() {
    check_ajax_referer('sc_private_notes_preview', 'nonce');
    if (!current_user_can('manage_options') && !current_user_can('sc_view_coach_salary')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    $target_type = isset($_POST['target_type']) ? sanitize_text_field(wp_unslash($_POST['target_type'])) : 'all';
    $config = [
        'member_ids' => isset($_POST['member_ids']) ? array_map('absint', (array) $_POST['member_ids']) : [],
        'course_ids' => isset($_POST['course_ids']) ? array_map('absint', (array) $_POST['course_ids']) : [],
        'event_ids' => isset($_POST['event_ids']) ? array_map('absint', (array) $_POST['event_ids']) : [],
        'team_names' => isset($_POST['team_names']) ? array_map('sanitize_text_field', (array) $_POST['team_names']) : [],
        'level_names' => isset($_POST['level_names']) ? array_map('sanitize_text_field', (array) $_POST['level_names']) : [],
        'member_type' => isset($_POST['member_type']) ? sanitize_text_field(wp_unslash($_POST['member_type'])) : 'all',
        'member_status' => isset($_POST['member_status']) ? sanitize_text_field(wp_unslash($_POST['member_status'])) : 'all',
    ];
    if (!current_user_can('manage_options') && function_exists('sc_support_get_coach_id_by_user_id')) {
        $coach_id = (int) sc_support_get_coach_id_by_user_id(get_current_user_id());
        if ($target_type === 'all') {
            $allowed = sc_private_notes_get_member_ids_for_coach($coach_id);
            if (empty($allowed)) {
                $members = [];
            } else {
                global $wpdb;
                $members_table = $wpdb->prefix . 'sc_members';
                $placeholders = implode(',', array_fill(0, count($allowed), '%d'));
                $members = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, first_name, last_name, national_id, member_type, team_player, skill_level, is_active
                     FROM $members_table WHERE id IN ($placeholders)
                     ORDER BY last_name ASC, first_name ASC",
                    ...$allowed
                ));
            }
        } else {
            $members = function_exists('sc_bulk_actions_get_members') ? sc_bulk_actions_get_members($target_type, $config) : [];
            $member_ids = array_values(array_unique(array_map('absint', wp_list_pluck((array) $members, 'id'))));
            $member_ids = sc_private_notes_filter_member_ids_for_coach($member_ids, $coach_id);
            $members = array_values(array_filter((array) $members, function ($m) use ($member_ids) {
                return in_array((int) $m->id, $member_ids, true);
            }));
        }
    } else {
        $members = function_exists('sc_bulk_actions_get_members') ? sc_bulk_actions_get_members($target_type, $config) : [];
    }
    ob_start();
    if (empty($members)) {
        echo '<p class="description">هیچ کاربری با این فیلترها پیدا نشد.</p>';
    } else {
        echo '<div class="sc-bulk-preview-meta">تعداد کاربران فیلتر شده: <strong>' . esc_html((string) count($members)) . '</strong></div>';
        echo '<table class="wp-list-table widefat striped sc-bulk-preview-table">';
        echo '<thead><tr><th style="width:64px;"><label><input type="checkbox" id="sc-private-notes-preview-select-all" checked> انتخاب</label></th><th>نام</th><th>کد ملی</th><th>نوع</th><th>تیم</th><th>سطح</th><th>وضعیت</th></tr></thead><tbody>';
        foreach (array_slice($members, 0, 200) as $member) {
            $full_name = trim((string) $member->first_name . ' ' . (string) $member->last_name);
            $row_label = $full_name !== '' ? $full_name : ('کاربر #' . (int) $member->id);
            $type_label = ($member->member_type === 'team') ? 'بازیکن تیم' : 'بازیکن عادی';
            $status_label = !empty($member->is_active) ? 'فعال' : 'غیرفعال';
            echo '<tr>';
            echo '<td><input type="checkbox" class="sc-private-notes-preview-member-check" data-member-id="' . (int) $member->id . '" data-member-label="' . esc_attr($row_label) . '" checked></td>';
            echo '<td>' . esc_html($row_label) . '</td>';
            echo '<td>' . esc_html((string) ($member->national_id ?: '-')) . '</td>';
            echo '<td>' . esc_html($type_label) . '</td>';
            echo '<td>' . esc_html((string) ($member->team_player ?: '-')) . '</td>';
            echo '<td>' . esc_html((string) ($member->skill_level ?: '-')) . '</td>';
            echo '<td>' . esc_html($status_label) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if (count($members) > 200) {
            echo '<p class="description">فقط 200 مورد اول نمایش داده شد.</p>';
        }
    }
    wp_send_json_success(['html' => ob_get_clean(), 'total' => count($members)]);
}

add_action('wp_ajax_sc_private_notes_member_threads', 'sc_private_notes_member_threads_ajax');
function sc_private_notes_member_threads_ajax() {
    check_ajax_referer('sc_private_notes_preview', 'nonce');
    if (!current_user_can('manage_options') && !current_user_can('sc_view_coach_salary')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    if ($member_id <= 0 || !sc_private_notes_can_access_member($member_id, get_current_user_id())) {
        wp_send_json_error(['message' => 'کاربر نامعتبر است.']);
    }
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, subject, updated_at FROM " . sc_private_note_threads_table() . " WHERE member_id = %d ORDER BY updated_at DESC",
        $member_id
    ));
    $last = sc_private_notes_get_last_selected_thread($member_id, get_current_user_id());
    $threads = [];
    foreach ((array) $rows as $r) {
        $threads[] = [
            'id' => (int) $r->id,
            'label' => trim((string) $r->subject) !== '' ? (string) $r->subject : ('پرونده #' . (int) $r->id),
            'updated_at' => (string) $r->updated_at,
        ];
    }
    wp_send_json_success(['threads' => $threads, 'last_thread_id' => $last]);
}
