<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_private_notes_table() {
    global $wpdb;
    return $wpdb->prefix . 'sc_private_notes';
}

function sc_private_notes_get_member_user_id($member_id) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $members_table WHERE id = %d LIMIT 1",
        absint($member_id)
    ));
}

function sc_private_notes_get_member_ids_for_coach($coach_id) {
    if ($coach_id <= 0 || !function_exists('sc_support_get_members_for_coach')) {
        return [];
    }
    $members = sc_support_get_members_for_coach($coach_id);
    if (!is_array($members)) {
        return [];
    }
    return array_values(array_unique(array_map('absint', wp_list_pluck($members, 'member_id'))));
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

function sc_private_notes_create_for_members($member_ids, $title, $content, $attachment_ids = []) {
    global $wpdb;
    $table = sc_private_notes_table();
    $member_ids = array_values(array_unique(array_filter(array_map('absint', (array) $member_ids))));
    $title = sanitize_text_field($title);
    $content = wp_kses_post($content);
    if (empty($member_ids) || $title === '' || trim($content) === '') {
        return new WP_Error('invalid_data', 'عنوان، متن و کاربر الزامی است.');
    }

    $current_user_id = get_current_user_id();
    $author_type = current_user_can('manage_options') ? 'admin' : 'coach';
    $author_coach_id = 0;
    if ($author_type === 'coach' && function_exists('sc_support_get_coach_id_by_user_id')) {
        $author_coach_id = (int) sc_support_get_coach_id_by_user_id($current_user_id);
        if ($author_coach_id <= 0) {
            return new WP_Error('forbidden', 'اطلاعات مربی یافت نشد.');
        }
    }

    $attachment_ids = sc_private_notes_validate_attachment_ids($attachment_ids, 5);
    $attachment_json = !empty($attachment_ids) ? wp_json_encode(array_map('absint', $attachment_ids)) : null;
    $now = current_time('mysql');
    $created = 0;

    foreach ($member_ids as $member_id) {
        if (!sc_private_notes_can_access_member($member_id, $current_user_id)) {
            continue;
        }
        $target_user_id = sc_private_notes_get_member_user_id($member_id);
        if ($target_user_id <= 0) {
            continue;
        }
        $res = $wpdb->insert($table, [
            'member_id' => $member_id,
            'user_id' => $target_user_id,
            'author_type' => $author_type,
            'author_user_id' => $current_user_id,
            'author_coach_id' => $author_coach_id ?: null,
            'title' => $title,
            'content' => $content,
            'attachment_ids' => $attachment_json,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']);
        if ($res !== false) {
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
        'sc_private_note_id' => (int) $note_id,
        'nonce' => wp_create_nonce('sc_private_note_attachment_' . (int) $attachment_id . '_' . (int) $note_id),
    ], home_url('/'));
}

add_action('template_redirect', 'sc_private_notes_attachment_download_handle');
function sc_private_notes_attachment_download_handle() {
    $aid = isset($_GET['sc_private_note_attachment']) ? absint($_GET['sc_private_note_attachment']) : 0;
    $note_id = isset($_GET['sc_private_note_id']) ? absint($_GET['sc_private_note_id']) : 0;
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if ($aid <= 0 || $note_id <= 0 || !wp_verify_nonce($nonce, 'sc_private_note_attachment_' . $aid . '_' . $note_id)) {
        return;
    }
    $note = sc_private_notes_get($note_id);
    if (!$note || !sc_private_notes_can_view($note, get_current_user_id())) {
        status_header(403);
        exit;
    }
    if ((int) get_post_meta($aid, '_sc_private_note_attachment', true) !== 1) {
        status_header(404);
        exit;
    }
    $ids = !empty($note->attachment_ids) ? json_decode($note->attachment_ids, true) : [];
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

function sc_private_notes_query_admin($args = []) {
    global $wpdb;
    $table = sc_private_notes_table();
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
        $where[] = 'DATE(n.created_at) >= %s';
        $values[] = sanitize_text_field($args['date_from']);
    }
    if (!empty($args['date_to'])) {
        $where[] = 'DATE(n.created_at) <= %s';
        $values[] = sanitize_text_field($args['date_to']);
    }
    if (!empty($args['search'])) {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = '(n.title LIKE %s OR n.content LIKE %s)';
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
            TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) AS coach_name
            FROM $table n
            LEFT JOIN $members_table m ON m.id = n.member_id
            LEFT JOIN $coaches_table c ON c.id = n.author_coach_id
            WHERE $where_sql
            ORDER BY n.created_at DESC
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
    global $wpdb;
    $table = sc_private_notes_table();
    $limit = isset($args['limit']) ? max(1, absint($args['limit'])) : 20;
    $offset = isset($args['offset']) ? max(0, absint($args['offset'])) : 0;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
        absint($user_id),
        $limit,
        $offset
    ));
}

function sc_private_notes_count_user_notes($user_id) {
    global $wpdb;
    $table = sc_private_notes_table();
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE user_id = %d",
        absint($user_id)
    ));
}
