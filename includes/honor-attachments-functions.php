<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_honor_allowed_mimes() {
    return [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
}

function sc_honor_validate_attachment_ids($ids, $max = 1) {
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
        if ((int) get_post_meta($aid, '_sc_honor_attachment', true) !== 1) {
            continue;
        }
        if ((int) get_post_meta($aid, '_sc_honor_uploaded_by', true) !== (int) $user_id) {
            continue;
        }
        $valid[] = $aid;
    }

    return $valid;
}

add_action('wp_ajax_sc_upload_honor_attachment', 'sc_ajax_upload_honor_attachment');
function sc_ajax_upload_honor_attachment() {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    if (!isset($_POST['sc_honor_upload_nonce']) || !wp_verify_nonce($_POST['sc_honor_upload_nonce'], 'sc_honor_upload_attachment')) {
        wp_send_json_error(['message' => 'خطای امنیتی.']);
    }

    $key = isset($_FILES['file']) ? 'file' : (isset($_FILES['honor_attachment']) ? 'honor_attachment' : null);
    if (!$key || empty($_FILES[$key]['name']) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => 'فایل معتبر انتخاب نشده است.']);
    }

    $file = $_FILES[$key];
    $allowed = sc_honor_allowed_mimes();
    if (!empty($file['size']) && (int) $file['size'] > (1 * 1024 * 1024)) {
        wp_send_json_error(['message' => 'حداکثر حجم فایل ۱ مگابایت است.']);
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

    update_post_meta($attach_id, '_sc_honor_attachment', 1);
    update_post_meta($attach_id, '_sc_honor_uploaded_by', get_current_user_id());
    wp_send_json_success(['id' => $attach_id, 'name' => basename($upload['file'])]);
}
