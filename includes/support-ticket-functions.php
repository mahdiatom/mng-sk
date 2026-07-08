<?php
/**
 * Support Ticket Functions - تیکت پشتیبانی
 * CRUD, permissions, SMS notifications, file attachments
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Capability for باشگاه admin ticket screens (administrator, club_coach, system_manager, accountant). */
if (!defined('SC_CAP_CLUB_SUPPORT_TICKETS')) {
    define('SC_CAP_CLUB_SUPPORT_TICKETS', 'sc_club_support_tickets');
}

/**
 * Grant ticket admin capability to club staff roles (idempotent).
 */
function sc_support_register_club_ticket_admin_cap() {
    $cap = SC_CAP_CLUB_SUPPORT_TICKETS;
    $club_staff_roles = array_merge(['administrator', 'accountantt'], function_exists('sc_get_club_manager_role_slugs') ? sc_get_club_manager_role_slugs() : ['club_coach']);
    foreach ($club_staff_roles as $role_name) {
        $role = get_role($role_name);
        if ($role && !$role->has_cap($cap)) {
            $role->add_cap($cap);
        }
    }
}
add_action('admin_init', 'sc_support_register_club_ticket_admin_cap', 5);

/** True if WordPress user is نقش حسابدار باشگاه. */
function sc_support_user_is_accountant($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    $u = get_userdata($user_id);
    if (!$u || empty($u->roles)) {
        return false;
    }
    return in_array('accountantt', (array) $u->roles, true);
}

/**
 * Users with role accountantt for ticket recipient dropdowns.
 * @return array<int, array{user_id:int, name:string}>
 */
function sc_support_get_accountant_users() {
    $users = get_users([
        'role' => 'accountantt',
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => ['ID', 'display_name'],
    ]);
    if (!is_array($users)) {
        return [];
    }
    $out = [];
    foreach ($users as $u) {
        if (is_object($u) && !empty($u->ID)) {
            $out[] = [
                'user_id' => (int) $u->ID,
                'name' => $u->display_name !== '' ? $u->display_name : ('کاربر #' . (int) $u->ID),
            ];
        }
    }
    return $out;
}

/**
 * حسابداری که فقط تیکت‌های بخش «حسابدار» اختصاص‌یافته به خودش را می‌بیند.
 * بر اساس نقش accountantt (نه capabilityهای کپی‌شده از مدیر باشگاه).
 *
 * @param int $user_id شناسه کاربر وردپرس؛ ۰ = کاربر جاری.
 */
function sc_support_is_accountant_ticket_scope_only($user_id = 0) {
    $user_id = (int) ($user_id ?: get_current_user_id());
    if ($user_id <= 0 || !sc_support_user_is_accountant($user_id)) {
        return false;
    }
    $u = get_userdata($user_id);
    if (!$u || empty($u->roles)) {
        return false;
    }
    // مدیر کل همچنان به همه تیکت‌ها دسترسی دارد.
    if (in_array('administrator', (array) $u->roles, true)) {
        return false;
    }
    return true;
}

/**
 * شرط SQL لیست/شمارش تیکت برای حسابدار محدود.
 *
 * @param array  $where  آرایه شرط‌های WHERE.
 * @param array  $params پارامترهای prepare.
 * @param string $alias  پیشوند جدول (مثلاً t. یا خالی).
 */
function sc_support_apply_accountant_ticket_list_scope(array &$where, array &$params, $alias = 't') {
    if (!sc_support_is_accountant_ticket_scope_only()) {
        return;
    }
    $uid = get_current_user_id();
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    $where[] = "( ({$prefix}department = %s AND {$prefix}coach_id = %d) OR ({$prefix}created_by_type = %s AND {$prefix}created_by_user_id = %d) )";
    $params[] = 'accountant';
    $params[] = $uid;
    $params[] = 'accountant';
    $params[] = $uid;
}

/** نوع فرستنده پیام برای کارکنان باشگاه (مدیر / حسابدار) در پاسخ تیکت. */
function sc_support_staff_sender_type_for_user($user_id = 0) {
    $user_id = (int) ($user_id ?: get_current_user_id());
    if (function_exists('sc_support_is_accountant_ticket_scope_only') && sc_support_is_accountant_ticket_scope_only($user_id)) {
        return 'accountant';
    }
    return 'admin';
}

/** شمار تیکت‌های در انتظار پاسخ که گیرندهٔ آن‌ها این حسابدار است. */
function sc_support_count_pending_assigned_accountant_tickets($user_id) {
    global $wpdb;
    $uid = (int) $user_id;
    if ($uid <= 0) {
        return 0;
    }
    $t = $wpdb->prefix . 'sc_support_tickets';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t WHERE status = 'pending_reply' AND (
            (department = 'accountant' AND coach_id = %d)
            OR (created_by_type = 'accountant' AND created_by_user_id = %d)
        )",
        $uid,
        $uid
    ));
}

/** Allowed MIME types for ticket attachments (all image formats, PDF, Word, Excel) */
function sc_support_allowed_mime_types() {
    return [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'jpg' => 'image/jpg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'tiff|tif' => 'image/tiff',
        'heic' => 'image/heic',
        'heif' => 'image/heif',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
    ];
}

/** لیست پسوندهای مجاز برای نمایش در پیام خطا */
function sc_support_allowed_extensions_list() {
    $mimes = sc_support_allowed_mime_types();
    $exts = [];
    foreach (array_keys($mimes) as $key) {
        $exts = array_merge($exts, explode('|', $key));
    }
    return array_unique($exts);
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

/** Get WordPress user_id from member_id (جدول اعضا) */
function sc_support_get_user_id_by_member_id($member_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_members';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $t WHERE id = %d LIMIT 1",
        $member_id
    ));
}

/**
 * Get list of members that this coach can send ticket to (members in coach's courses)
 * Returns array of [ 'member_id' => int, 'name' => string, 'user_id' => int ]
 */
function sc_support_get_members_for_coach($coach_id) {
    global $wpdb;
    $coach_id = absint($coach_id);
    if ($coach_id <= 0) {
        return [];
    }

    $mc = $wpdb->prefix . 'sc_member_courses';
    $cc = $wpdb->prefix . 'sc_course_coaches';
    $m = $wpdb->prefix . 'sc_members';
    $courses = $wpdb->prefix . 'sc_courses';

    $list = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT mem.id AS member_id,
                TRIM(CONCAT(COALESCE(mem.first_name,''), ' ', COALESCE(mem.last_name,''))) AS name,
                mem.user_id,
                mem.national_id
         FROM $m mem
         INNER JOIN $mc mc ON mc.member_id = mem.id AND mc.status = 'active'
         WHERE (
            mc.coach_id = %d
            OR EXISTS (
                SELECT 1
                FROM $cc cc
                INNER JOIN $courses cr ON cr.id = cc.course_id AND cr.deleted_at IS NULL AND cr.is_active = 1
                WHERE cc.course_id = mc.course_id AND cc.coach_id = %d
            )
         )
         ORDER BY name",
        $coach_id,
        $coach_id
    ), ARRAY_A);

    return is_array($list) ? $list : [];
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
 * Check if current user can view this ticket (owner, admin, or assigned coach / creator coach)
 */
function sc_support_can_view_ticket($ticket, $user_id) {
    if (!$ticket || !$user_id) {
        return false;
    }
    if ((int) $ticket->user_id === (int) $user_id) {
        return true;
    }
    if (function_exists('sc_support_is_accountant_ticket_scope_only') && sc_support_is_accountant_ticket_scope_only($user_id)) {
        if ($ticket->department === 'accountant' && !empty($ticket->coach_id) && (int) $ticket->coach_id === (int) $user_id) {
            return true;
        }
        $created_by = isset($ticket->created_by_type) ? $ticket->created_by_type : '';
        if ($created_by === 'accountant' && (int) $ticket->created_by_user_id === (int) $user_id) {
            return true;
        }
        return false;
    }
    if (user_can($user_id, 'manage_options') || user_can($user_id, 'club_coach')) {
        return true;
    }
    $coach_id = sc_support_get_coach_id_by_user_id($user_id);
    if ($coach_id) {
        if ($ticket->department === 'coach' && (int) $ticket->coach_id === $coach_id) {
            return true;
        }
        if (!empty($ticket->created_by_coach_id) && (int) $ticket->created_by_coach_id === $coach_id) {
            return true;
        }
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

/** Get tickets for coach (دریافت‌شده توسط این مربی یا ارسال‌شده توسط این مربی) */
function sc_support_get_tickets_for_coach($coach_id, $args = []) {
    global $wpdb;
    $t = $wpdb->prefix . 'sc_support_tickets';
    $where = "((department = 'coach' AND coach_id = %d) OR (created_by_coach_id = %d) OR (department = 'accountant' AND created_by_coach_id = %d))";
    $params = [$coach_id, $coach_id, $coach_id];
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

/** Count tickets pending reply for coach (تیکت‌های دریافتی که در انتظار پاسخ مربی هستند) */
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
    if ($department === 'accountant') {
        $acc_uid = absint($coach_id);
        if ($acc_uid <= 0 || !sc_support_user_is_accountant($acc_uid)) {
            return new WP_Error('invalid_data', 'برای ارسال به حسابدار، باید یک حسابدار معتبر انتخاب کنید.');
        }
    }

    $tickets_table = $wpdb->prefix . 'sc_support_tickets';
    $messages_table = $wpdb->prefix . 'sc_support_ticket_messages';
    $now = current_time('mysql');
    $dept_value = 'manager';
    if ($department === 'coach') {
        $dept_value = 'coach';
    } elseif ($department === 'site_support') {
        $dept_value = 'site_support';
    } elseif ($department === 'accountant') {
        $dept_value = 'accountant';
    }
    // برای accountant مقدار coach_id در DB = user_id وردپرس حسابدار (مسیریابی گیرنده)
    $coach_id = ($department === 'coach' || $department === 'accountant') ? absint($coach_id) : null;
    $attachment_json = !empty($attachment_ids) ? wp_json_encode(array_map('absint', $attachment_ids)) : null;

    $wpdb->insert($tickets_table, [
        'user_id' => $user_id,
        'department' => $dept_value,
        'coach_id' => $coach_id,
        'subject' => $subject,
        'status' => 'pending_reply',
        'created_by_type' => 'user',
        'created_by_user_id' => $user_id,
        'created_by_coach_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s']);

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
 * Create ticket by coach: به کاربر (عضو)، مدیر باشگاه یا حسابدار.
 * $recipient_type: 'member' | 'manager' | 'accountant'
 * $recipient_id: member_id، یا ۰ برای مدیر، یا user_id وردپرس برای حسابدار
 * Returns ticket id or WP_Error.
 */
function sc_support_create_ticket_by_coach($coach_id, $recipient_type, $recipient_id, $subject, $first_message, $attachment_ids = []) {
    global $wpdb;
    $subject = sanitize_text_field($subject);
    $first_message = wp_kses_post($first_message);
    if (empty($subject) || empty($first_message)) {
        return new WP_Error('invalid_data', 'موضوع و متن پیام الزامی است.');
    }
    if (!in_array($recipient_type, ['member', 'manager', 'accountant'], true)) {
        return new WP_Error('invalid_data', 'نوع گیرنده نامعتبر است.');
    }
    $user_id = 0;
    $department = 'manager';
    $coach_id_val = null;
    if ($recipient_type === 'member') {
        $member_id = absint($recipient_id);
        if ($member_id <= 0) {
            return new WP_Error('invalid_data', 'باید یک کاربر را انتخاب کنید.');
        }
        $user_id = sc_support_get_user_id_by_member_id($member_id);
        if ($user_id <= 0) {
            return new WP_Error('invalid_data', 'کاربر انتخاب‌شده معتبر نیست.');
        }
        $department = 'coach';
        $coach_id_val = absint($coach_id);
    } elseif ($recipient_type === 'accountant') {
        $acc_uid = absint($recipient_id);
        if ($acc_uid <= 0 || !sc_support_user_is_accountant($acc_uid)) {
            return new WP_Error('invalid_data', 'حسابدار انتخاب‌شده معتبر نیست.');
        }
        $department = 'accountant';
        $coach_id_val = $acc_uid;
    }

    $tickets_table = $wpdb->prefix . 'sc_support_tickets';
    $messages_table = $wpdb->prefix . 'sc_support_ticket_messages';
    $now = current_time('mysql');
    $attachment_json = !empty($attachment_ids) ? wp_json_encode(array_map('absint', $attachment_ids)) : null;

    $wpdb->insert($tickets_table, [
        'user_id' => $user_id,
        'department' => $department,
        'coach_id' => $coach_id_val,
        'subject' => $subject,
        'status' => 'pending_reply',
        'created_by_type' => 'coach',
        'created_by_user_id' => null,
        'created_by_coach_id' => absint($coach_id),
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s']);

    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'خطا در ثبت تیکت.');
    }
    $ticket_id = (int) $wpdb->insert_id;

    $wpdb->insert($messages_table, [
        'ticket_id' => $ticket_id,
        'sender_type' => 'coach',
        'sender_id' => absint($coach_id),
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
 * Create ticket by admin: به کاربر (عضو)، مربی یا حسابدار.
 * $recipient_type: 'member' | 'coach' | 'accountant'
 * $recipient_id: member_id، coach_id، یا user_id وردپرس حسابدار
 * Returns ticket id or WP_Error.
 */
function sc_support_create_ticket_by_admin($admin_user_id, $recipient_type, $recipient_id, $subject, $first_message, $attachment_ids = [], $created_by_type = 'admin') {
    global $wpdb;
    $subject = sanitize_text_field($subject);
    $first_message = wp_kses_post($first_message);
    if (empty($subject) || empty($first_message)) {
        return new WP_Error('invalid_data', 'موضوع و متن پیام الزامی است.');
    }
    if (!in_array($recipient_type, ['member', 'coach', 'accountant', 'manager'], true)) {
        return new WP_Error('invalid_data', 'نوع گیرنده نامعتبر است.');
    }
    if (!in_array($created_by_type, ['admin', 'accountant'], true)) {
        $created_by_type = 'admin';
    }
    $user_id = 0;
    $department = 'manager';
    $coach_id_val = null;
    if ($recipient_type === 'manager') {
        // گیرنده: مدیر باشگاه — user_id و coach_id صفر می‌مانند.
    } elseif ($recipient_type === 'member') {
        $member_id = absint($recipient_id);
        if ($member_id <= 0) {
            return new WP_Error('invalid_data', 'باید یک کاربر را انتخاب کنید.');
        }
        $user_id = sc_support_get_user_id_by_member_id($member_id);
        if ($user_id <= 0) {
            return new WP_Error('invalid_data', 'کاربر انتخاب‌شده معتبر نیست.');
        }
    } elseif ($recipient_type === 'coach') {
        $coach_id_val = absint($recipient_id);
        if ($coach_id_val <= 0) {
            return new WP_Error('invalid_data', 'باید یک مربی را انتخاب کنید.');
        }
        $department = 'coach';
    } else {
        $acc_uid = absint($recipient_id);
        if ($acc_uid <= 0 || !sc_support_user_is_accountant($acc_uid)) {
            return new WP_Error('invalid_data', 'باید یک حسابدار را انتخاب کنید.');
        }
        $department = 'accountant';
        $coach_id_val = $acc_uid;
    }

    $tickets_table = $wpdb->prefix . 'sc_support_tickets';
    $messages_table = $wpdb->prefix . 'sc_support_ticket_messages';
    $now = current_time('mysql');
    $attachment_json = !empty($attachment_ids) ? wp_json_encode(array_map('absint', $attachment_ids)) : null;

    $wpdb->insert($tickets_table, [
        'user_id' => $user_id,
        'department' => $department,
        'coach_id' => $coach_id_val,
        'subject' => $subject,
        'status' => 'pending_reply',
        'created_by_type' => $created_by_type,
        'created_by_user_id' => $admin_user_id,
        'created_by_coach_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%d', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s']);

    if ($wpdb->last_error) {
        return new WP_Error('db_error', 'خطا در ثبت تیکت.');
    }
    $ticket_id = (int) $wpdb->insert_id;

    $sender_type = ($created_by_type === 'accountant') ? 'accountant' : 'admin';
    $wpdb->insert($messages_table, [
        'ticket_id' => $ticket_id,
        'sender_type' => $sender_type,
        'sender_id' => $admin_user_id,
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
    if (is_admin() && function_exists('sc_log_activity') && ($sender_type === 'admin' || $sender_type === 'coach')) {
        sc_log_activity('updated', 'support_ticket', $ticket_id, 'پاسخ به تیکت #' . $ticket_id . ' ثبت شد', ['status' => $ticket->status], ['status' => $new_status]);
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
    if (is_admin() && function_exists('sc_log_activity')) {
        sc_log_activity('updated', 'support_ticket', $ticket_id, 'تیکت #' . $ticket_id . ' بسته شد', ['status' => $ticket->status], ['status' => 'closed']);
    }
    return true;
}

/**
 * Handle file upload for ticket attachment. Returns array of attachment post IDs or WP_Error.
 * Uses wp_handle_upload; files stored in WordPress uploads, with permission check via meta.
 */
function sc_support_handle_attachments($files_key = 'ticket_attachments') {
    // در HTML با name="reply_attachments[]" کلید در $_FILES برابر "reply_attachments[]" است نه "reply_attachments"
    if (empty($_FILES[$files_key]) && empty($_FILES[$files_key . '[]'])) {
        return [];
    }
    $actual_key = !empty($_FILES[$files_key]) ? $files_key : $files_key . '[]';
    $allowed = sc_support_allowed_mime_types();
    $allowed_ext = array_keys($allowed);
    $max_size = 5 * 1024 * 1024; // 5MB per file
    $max_files = 5;
    $ids = [];
    $names = $_FILES[$actual_key]['name'];
    $tmp = $_FILES[$actual_key]['tmp_name'];
    $error = $_FILES[$actual_key]['error'];
    $size = $_FILES[$actual_key]['size'];
    $type = isset($_FILES[$actual_key]['type']) ? $_FILES[$actual_key]['type'] : [];
    if (!is_array($names)) {
        $names = [$names];
        $tmp = [$tmp];
        $error = [$error];
        $size = [$size];
        $type = is_array($type) ? $type : [$type];
    }
    if (!is_array($type)) {
        $type = array_pad([], count($names), '');
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
        $ext_ok = false;
        foreach ($allowed_ext as $key_pattern) {
            if (in_array($ext, explode('|', $key_pattern), true)) {
                $ext_ok = true;
                break;
            }
        }
        if (!$ext_ok) {
            continue;
        }
        $file = [
            'name' => $names[$i],
            'type' => isset($type[$i]) ? $type[$i] : '',
            'tmp_name' => $tmp[$i],
            'error' => $error[$i],
            'size' => isset($size[$i]) ? $size[$i] : 0,
        ];
        $upload = wp_handle_upload($file, ['test_form' => false, 'test_type' => false, 'mimes' => $allowed]);
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
 * Validate and return attachment IDs that were uploaded by current user via AJAX.
 * Used when form is submitted with pre-uploaded attachment IDs (background upload).
 *
 * @param array|string $ids Array of attachment IDs or comma-separated string.
 * @param int          $max Maximum number of attachments to accept.
 * @return int[] Sanitized list of attachment post IDs.
 */
function sc_support_validate_attachment_ids($ids, $max = 5) {
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return [];
    }
    if (is_string($ids)) {
        $ids = array_map('absint', array_filter(explode(',', $ids)));
    } else {
        $ids = is_array($ids) ? array_map('absint', $ids) : [];
    }
    $ids = array_unique(array_filter($ids));
    $result = [];
    $n = 0;
    foreach ($ids as $aid) {
        if ($n >= $max) {
            break;
        }
        if ($aid <= 0) {
            continue;
        }
        $post = get_post($aid);
        if (!$post || $post->post_type !== 'attachment') {
            continue;
        }
        if ((int) get_post_meta($aid, '_sc_support_ticket_attachment', true) !== 1) {
            continue;
        }
        $uploaded_by = (int) get_post_meta($aid, '_sc_ticket_uploaded_by', true);
        if ($uploaded_by !== $user_id) {
            continue;
        }
        $result[] = $aid;
        $n++;
    }
    return $result;
}

/**
 * AJAX: Upload a single ticket attachment (background upload). Returns attachment ID.
 * Files are uploaded immediately on select; form later submits attachment IDs.
 */
add_action('wp_ajax_sc_upload_ticket_attachment', 'sc_ajax_upload_ticket_attachment');
function sc_ajax_upload_ticket_attachment() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'لطفاً ابتدا وارد شوید.']);
    }
    if (!isset($_POST['sc_ticket_upload_nonce']) || !wp_verify_nonce($_POST['sc_ticket_upload_nonce'], 'sc_ticket_upload_attachment')) {
        wp_send_json_error(['message' => 'خطای امنیتی. لطفاً صفحه را رفرش کنید.']);
    }
    $key = isset($_FILES['file']) ? 'file' : (isset($_FILES['ticket_attachment']) ? 'ticket_attachment' : null);
    if (!$key || empty($_FILES[$key]['name'])) {
        wp_send_json_error(['message' => 'هیچ فایلی انتخاب نشده است.']);
    }
    $file = $_FILES[$key];
    $upload_err_msg = [
        UPLOAD_ERR_INI_SIZE => 'حجم فایل از حد مجاز سرور بیشتر است.',
        UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیش از حد مجاز است.',
        UPLOAD_ERR_PARTIAL => 'فایل فقط بخشی آپلود شده است. دوباره تلاش کنید.',
        UPLOAD_ERR_NO_FILE => 'هیچ فایلی انتخاب نشده است.',
        UPLOAD_ERR_NO_TMP_DIR => 'خطای سرور: پوشه موقت وجود ندارد.',
        UPLOAD_ERR_CANT_WRITE => 'خطای سرور: ذخیره فایل ممکن نیست.',
        UPLOAD_ERR_EXTENSION => 'آپلود به دلیل یک افزونه متوقف شد.',
    ];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = isset($upload_err_msg[$file['error']]) ? $upload_err_msg[$file['error']] : 'خطا در آپلود فایل (کد: ' . $file['error'] . ').';
        wp_send_json_error(['message' => $msg]);
    }
    $allowed = sc_support_allowed_mime_types();
    $allowed_ext_flat = function_exists('sc_support_allowed_extensions_list') ? sc_support_allowed_extensions_list() : array_keys($allowed);
    $allowed_ext_keys = array_keys($allowed);
    $max_size = 5 * 1024 * 1024; // 5MB
    if (isset($file['size']) && $file['size'] > $max_size) {
        wp_send_json_error(['message' => 'حجم فایل «' . basename($file['name']) . '» بیش از ۵ مگابایت است.']);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $ext_allowed = false;
    foreach ($allowed_ext_keys as $key_pattern) {
        $keys = explode('|', $key_pattern);
        if (in_array($ext, $keys, true)) {
            $ext_allowed = true;
            break;
        }
    }
    if (!$ext_allowed) {
        $list = implode(', ', array_map(function ($e) { return '.' . $e; }, $allowed_ext_flat));
        wp_send_json_error(['message' => 'فرمت فایل «' . $ext . '» مجاز نیست. فرمت‌های مجاز: ' . $list]);
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
        'post_title'     => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
        'post_content'  => '',
        'post_status'   => 'inherit',
    ];
    $attach_id = wp_insert_attachment($attachment, $upload['file']);
    if (is_wp_error($attach_id)) {
        wp_send_json_error(['message' => 'خطا در ذخیره پیوست.']);
    }
    update_post_meta($attach_id, '_sc_support_ticket_attachment', 1);
    update_post_meta($attach_id, '_sc_ticket_uploaded_by', get_current_user_id());
    wp_send_json_success(['id' => $attach_id, 'name' => basename($upload['file'])]);
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
 * Send SMS when new ticket is created - to recipient (مدیر، مربی یا کاربر عضو).
 */
function sc_support_send_sms_on_new_ticket($ticket) {
    $enabled = (int) sc_get_sms_setting('sms_ticket_new_recipient_enabled');
    if ($enabled !== 1) {
        return;
    }
    $template_raw = sc_get_sms_setting('sms_ticket_new_recipient_template');
    $template = str_replace(['{ticket_id}', '{subject}'], [$ticket->id, $ticket->subject], $template_raw);
    if (strpos($template, '%s') !== false) {
        $template = sprintf($template, $ticket->id, $ticket->subject);
    }
    $pattern_code = (int) sc_get_sms_setting('sms_ticket_new_recipient_pattern');
    $pattern_params = [
        'TicketId' => (string) $ticket->id,
        'Subject' => (string) $ticket->subject,
    ];
    $mobile = null;
    $created_by = isset($ticket->created_by_type) ? $ticket->created_by_type : 'user';
    if ($created_by !== 'user' && !empty($ticket->user_id) && (int) $ticket->user_id > 0) {
        $member_id = sc_support_get_member_id_by_user_id($ticket->user_id);
        if ($member_id) {
            global $wpdb;
            $m = $wpdb->prefix . 'sc_members';
            $mobile = $wpdb->get_var($wpdb->prepare("SELECT player_phone FROM $m WHERE id = %d", $member_id));
        }
        if (empty($mobile)) {
            $mobile = get_user_meta($ticket->user_id, 'billing_phone', true);
        }
    }
    if (!$mobile && ($ticket->department === 'manager' || $ticket->department === 'site_support')) {
        $mobile = sc_get_setting('sms_admin_phone', '');
    }
    if (!$mobile && $ticket->department === 'coach' && !empty($ticket->coach_id)) {
        global $wpdb;
        $c = $wpdb->prefix . 'sc_coaches';
        $mobile = $wpdb->get_var($wpdb->prepare("SELECT mobile_phone FROM $c WHERE id = %d", $ticket->coach_id));
    }
    if (!$mobile && $ticket->department === 'accountant' && !empty($ticket->coach_id)) {
        $mobile = get_user_meta((int) $ticket->coach_id, 'billing_phone', true);
    }
    if ($mobile && function_exists('sc_send_sms')) {
        sc_send_sms($mobile, $template, $pattern_code > 0, $pattern_code > 0 ? $pattern_code : null, $pattern_params, 'ticket_new');
    }
}

/**
 * Send SMS when new message is added - to the other party (if user replied -> to manager/coach; if admin/coach replied -> to user).
 */
function sc_support_send_sms_on_new_message($ticket, $sender_type, $sender_id) {
    $enabled = (int) sc_get_sms_setting('sms_ticket_reply_enabled');
    if ($enabled !== 1) {
        return;
    }
    $template_raw = sc_get_sms_setting('sms_ticket_reply_template');
    $template = str_replace(['{ticket_id}', '{subject}'], [$ticket->id, $ticket->subject], $template_raw);
    if (strpos($template, '%s') !== false) {
        $template = sprintf($template, $ticket->id);
    }
    $pattern_code = (int) sc_get_sms_setting('sms_ticket_reply_pattern');
    $pattern_params = [
        'TicketId' => (string) $ticket->id,
        'Subject' => (string) $ticket->subject,
    ];
    $mobile = null;
    if ($sender_type === 'user') {
        if ($ticket->department === 'manager' || $ticket->department === 'site_support') {
            $mobile = sc_get_setting('sms_admin_phone', '');
        } elseif ($ticket->department === 'accountant' && !empty($ticket->coach_id)) {
            $mobile = get_user_meta((int) $ticket->coach_id, 'billing_phone', true);
        } else {
            global $wpdb;
            $c = $wpdb->prefix . 'sc_coaches';
            $mobile = $wpdb->get_var($wpdb->prepare("SELECT mobile_phone FROM $c WHERE id = %d", $ticket->coach_id));
        }
    } else {
        $user_id = (int) $ticket->user_id;
        if ($user_id > 0) {
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
        if (empty($mobile) && !empty($ticket->created_by_coach_id)) {
            global $wpdb;
            $c = $wpdb->prefix . 'sc_coaches';
            $mobile = $wpdb->get_var($wpdb->prepare("SELECT mobile_phone FROM $c WHERE id = %d", $ticket->created_by_coach_id));
        }
        if (empty($mobile) && $ticket->department === 'manager') {
            $mobile = sc_get_setting('sms_admin_phone', '');
        }
        if (empty($mobile) && $ticket->department === 'coach' && !empty($ticket->coach_id)) {
            global $wpdb;
            $c = $wpdb->prefix . 'sc_coaches';
            $mobile = $wpdb->get_var($wpdb->prepare("SELECT mobile_phone FROM $c WHERE id = %d", $ticket->coach_id));
        }
        if (empty($mobile) && $ticket->department === 'accountant' && !empty($ticket->coach_id)) {
            $mobile = get_user_meta((int) $ticket->coach_id, 'billing_phone', true);
        }
    }
    if ($mobile && function_exists('sc_send_sms')) {
        sc_send_sms($mobile, $template, $pattern_code > 0, $pattern_code > 0 ? $pattern_code : null, $pattern_params, 'ticket_reply');
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
    if ($department === 'accountant') {
        if ($coach_id) {
            $u = get_userdata((int) $coach_id);
            return $u ? ('حسابدار: ' . $u->display_name) : 'حسابدار باشگاه';
        }
        return 'حسابدار باشگاه';
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
    $base_url = function_exists('sc_panel_endpoint_url')
        ? sc_panel_endpoint_url('sc-support-tickets')
        : (function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-support-tickets') : '');
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




function sc_count_user_tickets($user_id, $status = 'all') {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_support_tickets';

    // شمارش همه تیکت‌ها
    if ($status === 'all') {
        return (int)$wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM $table 
                 WHERE user_id = %d",
                $user_id
            )
        );
    }

    // شمارش بر اساس وضعیت
    $allowed_statuses = [
        'pending_reply',
        'answered',
        'closed',
    ];

    if (!in_array($status, $allowed_statuses, true)) {
        return 0;
    }

    return (int)$wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) 
             FROM $table 
             WHERE user_id = %d 
               AND status = %s",
            $user_id,
            $status
        )
    );
}

