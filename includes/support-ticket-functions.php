<?php
/**
 * Support Ticket Functions - تیکت پشتیبانی
 * CRUD, permissions, SMS notifications, file attachments
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Allowed MIME types for ticket attachments (images, PDF, Word, Excel) */
function sc_support_allowed_mime_types() {
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
    ];
}

/** Get member_id from WordPress user_id */
function sc_support_get_member_id_by_user_id($user_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_members';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $t WHERE user_id = %d LIMIT 1",
        $user_id
    ));
}

/** Get coach_id from WordPress user_id (for coach role) */
function sc_support_get_coach_id_by_user_id($user_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_coaches';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $t WHERE user_id = %d LIMIT 1",
        $user_id
    ));
}

/**
 * Get list of coaches that the member can send ticket to (from their courses)
 * Returns array of [ 'coach_id' => int, 'name' => string, 'mobile' => string ]
 */
function sc_support_get_coaches_for_member($member_id) {
    global $wpdb;
    $mc = $wpdb->prefix . 'sc_member_courses';
    $cc = $wpdb->prefix . 'sc_course_coaches';
    $c = $wpdb->prefix . 'sc_coaches';
    $courses = $wpdb->prefix . 'sc_courses';

    $list = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT co.id AS coach_id,
                TRIM(CONCAT(COALESCE(co.first_name,''), ' ', COALESCE(co.last_name,''))) AS name,
                co.mobile_phone AS mobile
         FROM $mc mc
         INNER JOIN $cc cc ON cc.course_id = mc.course_id
         INNER JOIN $c co ON co.id = cc.coach_id AND co.is_active = 1
         INNER JOIN $courses cr ON cr.id = mc.course_id AND cr.deleted_at IS NULL AND cr.is_active = 1
         WHERE mc.member_id = %d AND mc.status = 'active'
         ORDER BY name",
        $member_id
    ), ARRAY_A);

    return is_array($list) ? $list : [];
}

/**
 * Check if current user can view this ticket (owner, admin, or assigned coach)
 */
function sc_support_can_view_ticket($ticket, $user_id) {
    if (!$ticket || !$user_id) {
        return false;
    }
    if ((int) $ticket->user_id === (int) $user_id) {
        return true;
    }
    if (current_user_can('manage_options')) {
        return true;
    }
    $coach_id = sc_support_get_coach_id_by_user_id($user_id);
    if ($coach_id && $ticket->department === 'coach' && (int) $ticket->coach_id === $coach_id) {
        return true;
    }
    return false;
}

/**
 * Check if user can reply to ticket (same as view + ticket not closed, or closed and replying reopens)
 */
function sc_support_can_reply_ticket($ticket, $user_id) {
    return sc_support_can_view_ticket($ticket, $user_id);
}

/**
 * Check if user can close this ticket (owner or admin/coach)
 */
function sc_support_can_close_ticket($ticket, $user_id) {
    if (!sc_support_can_view_ticket($ticket, $user_id)) {
        return false;
    }
    return true;
}

/** Get single ticket by ID */
function sc_support_get_ticket($ticket_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_support_tickets';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $t WHERE id = %d",
        $ticket_id
    ));
}

/** Get tickets for front user (created by this user) */
function sc_support_get_tickets_for_user($user_id, $args = []) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_support_tickets';
    $where = "user_id = %d";
    $params = [$user_id];
    if (!empty($args['status'])) {
        $where .= " AND status = %s";
        $params[] = $args['status'];
    }
    $order = isset($args['order']) ? strtoupper($args['order']) : 'DESC';
    $orderby = isset($args['orderby']) ? $args['orderby'] : 'updated_at';
    $allowed_orderby = ['id', 'created_at', 'updated_at', 'subject', 'status'];
    if (!in_array($orderby, $allowed_orderby, true)) {
        $orderby = 'updated_at';
    }
    $limit = isset($args['per_page']) ? absint($args['per_page']) : 20;
    $offset = isset($args['offset']) ? absint($args['offset']) : 0;
    $sql = "SELECT * FROM $t WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
    $params[] = $limit;
    $params[] = $offset;
    return $wpdb->get_results($wpdb->prepare($sql, $params));
}

/** Count tickets for user */
function sc_support_count_tickets_for_user($user_id, $status = '') {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_support_tickets';
    $where = "user_id = %d";
    $params = [$user_id];
    if ($status !== '') {
        $where .= " AND status = %s";
        $params[] = $status;
    }
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t WHERE $where",
        $params
    ));
}

/** Get tickets for coach (department=coach and coach_id = this coach) */
function sc_support_get_tickets_for_coach($coach_id, $args = []) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_support_tickets';
    $where = "department = 'coach' AND coach_id = %d";
    $params = [$coach_id];
    if (!empty($args['status'])) {
        $where .= " AND status = %s";
        $params[] = $args['status'];
    }
    $order = isset($args['order']) ? strtoupper($args['order']) : 'DESC';
    $orderby = isset($args['orderby']) ? $args['orderby'] : 'updated_at';
    $allowed_orderby = ['id', 'created_at', 'updated_at', 'subject', 'status'];
    if (!in_array($orderby, $allowed_orderby, true)) {
        $orderby = 'updated_at';
    }
    $limit = isset($args['per_page']) ? absint($args['per_page']) : 20;
    $offset = isset($args['offset']) ? absint($args['offset']) : 0;
    $sql = "SELECT * FROM $t WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
    $params[] = $limit;
    $params[] = $offset;
    return $wpdb->get_results($wpdb->prepare($sql, $params));
}

/** Count tickets pending reply (for admin badge) */
function sc_support_count_pending_reply_for_admin() {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_support_tickets';
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM $t WHERE status = 'pending_reply'");
}

/** Count tickets pending reply for coach */
function sc_support_count_pending_reply_for_coach($coach_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_support_tickets';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t WHERE department = 'coach' AND coach_id = %d AND status = 'pending_reply'",
        $coach_id
    ));
}

/** Get messages for a ticket */
function sc_support_get_messages($ticket_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_support_ticket_messages';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $t WHERE ticket_id = %d ORDER BY created_at ASC",
        $ticket_id
    ));
}

/**
 * Create new ticket and first message. Returns ticket id or WP_Error.
 */
function sc_support_create_ticket($user_id, $department, $coach_id, $subject, $first_message, $attachment_ids = []) {
    global $wpdb;
    $subject = sanitize_text_field($subject);
    $first_message = wp_kses_post($first_message);
    if (empty($subject) || empty($first_message)) {
        return new WP_Error('invalid_data', 'موضوع و متن پیام الزامی است.');
    }
    if ($department === 'coach' && empty($coach_id)) {
        return new WP_Error('invalid_data', 'برای ارسال به مربی، باید مربی را انتخاب کنید.');
    }

    $tickets_table = $wpdb->prefix . 'sc_support_tickets';
    $messages_table = $wpdb->prefix . 'sc_support_ticket_messages';
    $now = current_time('mysql');
    $dept_value = 'manager';
    if ($department === 'coach') {
        $dept_value = 'coach';
    } elseif ($department === 'site_support') {
        $dept_value = 'site_support';
    }
    $coach_id = ($department === 'coach') ? absint($coach_id) : null;
    $attachment_json = !empty($attachment_ids) ? wp_json_encode(array_map('absint', $attachment_ids)) : null;

    $wpdb->insert($tickets_table, [
        'user_id' => $user_id,
        'department' => $dept_value,
        'coach_id' => $coach_id,
        'subject' => $subject,
        'status' => 'pending_reply',
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%s', '%d', '%s', '%s', '%s', '%s']);

    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'خطا در ثبت تیکت.');
    }
    $ticket_id = (int) $wpdb->insert_id;

    $wpdb->insert($messages_table, [
        'ticket_id' => $ticket_id,
        'sender_type' => 'user',
        'sender_id' => $user_id,
        'message' => $first_message,
        'attachment_ids' => $attachment_json,
        'created_at' => $now,
    ], ['%d', '%s', '%d', '%s', '%s', '%s']);

    if ($wpdb->last_error) {
        $wpdb->delete($tickets_table, ['id' => $ticket_id], ['%d']);
        return new WP_Error('db_error', 'خطا در ثبت پیام.');
    }

    $ticket = sc_support_get_ticket($ticket_id);
    if ($ticket && function_exists('sc_support_send_sms_on_new_ticket')) {
        sc_support_send_sms_on_new_ticket($ticket);
    }
    return $ticket_id;
}

/**
 * Add reply to ticket. If ticket was closed, reopen (status = pending_reply).
 * sender_type: 'user' | 'admin' | 'coach', sender_id: user_id or coach_id as per type.
 * Returns message id or WP_Error.
 */
function sc_support_add_message($ticket_id, $sender_type, $sender_id, $message, $attachment_ids = []) {
    global $wpdb;
    $ticket = sc_support_get_ticket($ticket_id);
    if (!$ticket) {
        return new WP_Error('not_found', 'تیکت یافت نشد.');
    }
    $message = wp_kses_post($message);
    if (trim($message) === '' && empty($attachment_ids)) {
        return new WP_Error('invalid_data', 'متن پیام یا پیوست الزامی است.');
    }

    $messages_table = $wpdb->prefix . 'sc_support_ticket_messages';
    $tickets_table = $wpdb->prefix . 'sc_support_tickets';
    $now = current_time('mysql');
    $attachment_json = !empty($attachment_ids) ? wp_json_encode(array_map('absint', $attachment_ids)) : null;

    $wpdb->insert($messages_table, [
        'ticket_id' => $ticket_id,
        'sender_type' => $sender_type,
        'sender_id' => absint($sender_id),
        'message' => $message,
        'attachment_ids' => $attachment_json,
        'created_at' => $now,
    ], ['%d', '%s', '%d', '%s', '%s', '%s']);

    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'خطا در ثبت پیام.');
    }
    $message_id = (int) $wpdb->insert_id;

    $new_status = ($sender_type === 'user') ? 'pending_reply' : 'answered';
    $wpdb->update(
        $tickets_table,
        ['status' => $new_status, 'updated_at' => $now],
        ['id' => $ticket_id],
        ['%s', '%s'],
        ['%d']
    );

    if ($ticket->status === 'closed') {
        $wpdb->update(
            $tickets_table,
            ['status' => 'pending_reply', 'updated_at' => $now],
            ['id' => $ticket_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    if (function_exists('sc_support_send_sms_on_new_message')) {
        sc_support_send_sms_on_new_message($ticket, $sender_type, $sender_id);
    }
    return $message_id;
}

/**
 * Close ticket. Allowed for owner or admin/coach.
 */
function sc_support_close_ticket($ticket_id, $user_id) {
    $ticket = sc_support_get_ticket($ticket_id);
    if (!$ticket || !sc_support_can_close_ticket($ticket, $user_id)) {
        return new WP_Error('forbidden', 'امکان بستن این تیکت را ندارید.');
    }
    global $wpdb;
    $t = $wpdb->prefix . 'sc_support_tickets';
    $wpdb->update($t, ['status' => 'closed', 'updated_at' => current_time('mysql')], ['id' => $ticket_id], ['%s', '%s'], ['%d']);
    return true;
}

/**
 * Handle file upload for ticket attachment. Returns array of attachment post IDs or WP_Error.
 * Uses wp_handle_upload; files stored in WordPress uploads, with permission check via meta.
 */
function sc_support_handle_attachments($files_key = 'ticket_attachments') {
    if (empty($_FILES[$files_key])) {
        return [];
    }
    $allowed = sc_support_allowed_mime_types();
    $allowed_ext = array_keys($allowed);
    $max_size = 5 * 1024 * 1024; // 5MB per file
    $max_files = 5;
    $ids = [];
    $names = $_FILES[$files_key]['name'];
    $tmp = $_FILES[$files_key]['tmp_name'];
    $error = $_FILES[$files_key]['error'];
    $size = $_FILES[$files_key]['size'];
    if (!is_array($names)) {
        $names = [$names];
        $tmp = [$tmp];
        $error = [$error];
        $size = [$size];
    }
    $count = 0;

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    for ($i = 0; $i < count($names) && $count < $max_files; $i++) {
        if (empty($names[$i]) || $error[$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        if (isset($size[$i]) && $size[$i] > $max_size) {
            continue;
        }
        $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            continue;
        }
        $file = [
            'name' => $names[$i],
            'type' => $_FILES[$files_key]['type'][$i],
            'tmp_name' => $tmp[$i],
            'error' => $error[$i],
            'size' => isset($size[$i]) ? $size[$i] : 0,
        ];
        $upload = wp_handle_upload($file, ['test_form' => false, 'mimes' => $allowed]);
        if (isset($upload['error'])) {
            continue;
        }
        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ];
        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        if (is_wp_error($attach_id)) {
            continue;
        }
        update_post_meta($attach_id, '_sc_support_ticket_attachment', 1);
        $ids[] = $attach_id;
        $count++;
    }
    return $ids;
}

/**
 * Get download URL for ticket attachment (secure: only if user can view ticket).
 */
function sc_support_attachment_download_url($attachment_id, $ticket_id) {
    return add_query_arg([
        'sc_ticket_attachment' => (int) $attachment_id,
        'sc_ticket_id' => (int) $ticket_id,
        'nonce' => wp_create_nonce('sc_ticket_attachment_' . $attachment_id . '_' . $ticket_id),
    ], home_url('/'));
}

/**
 * Send SMS when new ticket is created - to recipient (manager or coach).
 */
function sc_support_send_sms_on_new_ticket($ticket) {
    $enabled = (int) sc_get_setting('sms_ticket_new_recipient_enabled', '0');
    if ($enabled !== 1) {
        return;
    }
    $template = sc_get_setting('sms_ticket_new_recipient_template', 'تیکت پشتیبانی جدید #%s با موضوع: %s');
    $template = str_replace(['{ticket_id}', '{subject}'], [$ticket->id, $ticket->subject], $template);
    if (strpos($template, '%s') !== false) {
        $template = sprintf($template, $ticket->id, $ticket->subject);
    }
    $mobile = null;
    if ($ticket->department === 'manager' || $ticket->department === 'site_support') {
        $mobile = sc_get_setting('sms_admin_phone', '');
    } else {
        global $wpdb;
        $c = $wpdb->prefix . 'sc_coaches';
        $mobile = $wpdb->get_var($wpdb->prepare("SELECT mobile_phone FROM $c WHERE id = %d", $ticket->coach_id));
    }
    if ($mobile && function_exists('sc_send_sms')) {
        sc_send_sms($mobile, $template, false);
    }
}

/**
 * Send SMS when new message is added - to the other party (if user replied -> to manager/coach; if admin/coach replied -> to user).
 */
function sc_support_send_sms_on_new_message($ticket, $sender_type, $sender_id) {
    $enabled = (int) sc_get_setting('sms_ticket_reply_enabled', '0');
    if ($enabled !== 1) {
        return;
    }
    $template = sc_get_setting('sms_ticket_reply_template', 'پاسخ جدید به تیکت #%s. لطفا پنل خود را بررسی کنید.');
    $template = str_replace(['{ticket_id}', '{subject}'], [$ticket->id, $ticket->subject], $template);
    if (strpos($template, '%s') !== false) {
        $template = sprintf($template, $ticket->id);
    }
    $mobile = null;
    if ($sender_type === 'user') {
        if ($ticket->department === 'manager' || $ticket->department === 'site_support') {
            $mobile = sc_get_setting('sms_admin_phone', '');
        } else {
            global $wpdb;
            $c = $wpdb->prefix . 'sc_coaches';
            $mobile = $wpdb->get_var($wpdb->prepare("SELECT mobile_phone FROM $c WHERE id = %d", $ticket->coach_id));
        }
    } else {
        $user_id = (int) $ticket->user_id;
        $member_id = sc_support_get_member_id_by_user_id($user_id);
        if ($member_id) {
            global $wpdb;
            $m = $wpdb->prefix . 'sc_members';
            $mobile = $wpdb->get_var($wpdb->prepare("SELECT player_phone FROM $m WHERE id = %d", $member_id));
        }
        if (empty($mobile)) {
            $mobile = get_user_meta($user_id, 'billing_phone', true);
        }
    }
    if ($mobile && function_exists('sc_send_sms')) {
        sc_send_sms($mobile, $template, false);
    }
}

/** Status label for display */
function sc_support_status_label($status) {
    $labels = [
        'pending_reply' => 'در انتظار پاسخ',
        'answered' => 'پاسخ داده شده',
        'closed' => 'بسته شده',
    ];
    return isset($labels[$status]) ? $labels[$status] : $status;
}

/** Department label */
function sc_support_department_label($department, $coach_id = null) {
    if ($department === 'manager') {
        return 'مدیر باشگاه';
    }
    if ($department === 'site_support') {
        return 'پشتیبانی سایت';
    }
    if ($department === 'coach' && $coach_id) {
        global $wpdb;
        $c = $wpdb->prefix . 'sc_coaches';
        $name = $wpdb->get_var($wpdb->prepare(
            "SELECT TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) FROM $c WHERE id = %d",
            $coach_id
        ));
        return $name ? 'مربی: ' . $name : 'مربی باشگاه';
    }
    return 'مربی باشگاه';
}

/**
 * AJAX: فیلتر و جستجوی تیکت‌های کاربر
 */
add_action('wp_ajax_sc_support_tickets_filter', 'sc_ajax_support_tickets_filter');
function sc_ajax_support_tickets_filter() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'لطفاً وارد شوید.']);
    }
    $filter_status = isset($_POST['filter_status']) ? sanitize_text_field($_POST['filter_status']) : 'all';
    if (!in_array($filter_status, ['all', 'pending_reply', 'answered', 'closed'], true)) {
        $filter_status = 'all';
    }
    $search = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
    $page = isset($_POST['ticket_page']) ? max(1, absint($_POST['ticket_page'])) : 1;
    $per_page = 20;
    $user_id = get_current_user_id();
    $args = ['per_page' => $per_page, 'offset' => ($page - 1) * $per_page];
    if ($filter_status !== 'all') {
        $args['status'] = $filter_status;
    }
    if ($search !== '') {
        $all_tickets = sc_support_get_tickets_for_user($user_id, ['per_page' => 500, 'offset' => 0] + ($filter_status !== 'all' ? ['status' => $filter_status] : []));
        $search_lower = mb_strtolower($search);
        $tickets = array_values(array_filter($all_tickets, function ($t) use ($search_lower) {
            return strpos(mb_strtolower($t->subject), $search_lower) !== false || (is_numeric($search_lower) && (int) $t->id === (int) $search_lower);
        }));
        $total = count($tickets);
        $tickets = array_slice($tickets, ($page - 1) * $per_page, $per_page);
    } else {
        $tickets = sc_support_get_tickets_for_user($user_id, $args);
        $total = sc_support_count_tickets_for_user($user_id, $filter_status === 'all' ? '' : $filter_status);
    }
    $total_pages = max(1, ceil($total / $per_page));
    $base_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-support-tickets') : '';
    $items = [];
    foreach ($tickets as $t) {
        $items[] = [
            'id' => (int) $t->id,
            'subject' => $t->subject,
            'status' => $t->status,
            'status_label' => sc_support_status_label($t->status),
            'department_label' => sc_support_department_label($t->department, $t->coach_id),
            'updated_at' => sc_date_shamsi($t->updated_at, 'Y/m/d'),
            'view_url' => add_query_arg('view_ticket', $t->id, $base_url),
        ];
    }
    $empty_message = ($search !== '') ? 'نتیجه‌ای برای جستجو یافت نشد.' : 'هنوز تیکتی ارسال نکرده‌اید.';
    wp_send_json_success([
        'items' => $items,
        'total' => (int) $total,
        'total_pages' => $total_pages,
        'page' => $page,
        'empty_message' => $empty_message,
        'base_url_with_filter' => $base_url,
    ]);
}

/**
 * Secure download of ticket attachment (only if user can view ticket).
 */
add_action('template_redirect', 'sc_support_attachment_download_handle');
function sc_support_attachment_download_handle() {
    $attach_id = isset($_GET['sc_ticket_attachment']) ? absint($_GET['sc_ticket_attachment']) : 0;
    $ticket_id = isset($_GET['sc_ticket_id']) ? absint($_GET['sc_ticket_id']) : 0;
    $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
    if (!$attach_id || !$ticket_id || !wp_verify_nonce($nonce, 'sc_ticket_attachment_' . $attach_id . '_' . $ticket_id)) {
        return;
    }
    if (get_post_meta($attach_id, '_sc_support_ticket_attachment', true) != '1') {
        return;
    }
    $ticket = sc_support_get_ticket($ticket_id);
    if (!$ticket) {
        return;
    }
    $user_id = get_current_user_id();
    if (!sc_support_can_view_ticket($ticket, $user_id)) {
        status_header(403);
        exit;
    }
    $file = get_attached_file($attach_id);
    if (!file_exists($file) || !is_readable($file)) {
        status_header(404);
        exit;
    }
    $filename = basename($file);
    header('Content-Type: ' . get_post_mime_type($attach_id));
    header('Content-Disposition: attachment; filename="' . esc_attr($filename) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}
