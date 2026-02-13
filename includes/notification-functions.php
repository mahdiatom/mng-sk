<?php
/**
 * Notification Functions for SportClub Manager
 * اطلاعیه‌ها و ارسال پیامک
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * آیا کاربر فعلی مربی است؟ در صورت بله شناسه مربی را برمی‌گرداند وگرنه 0
 */
function sc_current_user_coach_id() {
    global $wpdb;
    $user_id = get_current_user_id();
    if (!$user_id) return 0;
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $coaches_table WHERE user_id = %d AND is_active = 1 LIMIT 1",
        $user_id
    ));
    return $id ? (int)$id : 0;
}

/**
 * برچسب ثبت‌کننده اطلاعیه برای نمایش در لیست (مدیر / مربی: نام)
 */
function sc_notification_creator_label($notification) {
    global $wpdb;
    $type = isset($notification->created_by_type) ? $notification->created_by_type : 'admin';
    $entity_id = isset($notification->created_by_entity_id) ? (int)$notification->created_by_entity_id : 0;
    if ($type === 'coach' && $entity_id > 0) {
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        $coach = $wpdb->get_row($wpdb->prepare(
            "SELECT first_name, last_name FROM $coaches_table WHERE id = %d",
            $entity_id
        ));
        if ($coach) {
            return 'مربی: ' . trim($coach->first_name . ' ' . $coach->last_name);
        }
    }
    return 'مدیر';
}

/**
 * Get recipients based on target_type and target_config
 * @param string $target_type all|specific|course
 * @param array $target_config JSON decoded config
 * @return array Array of user_ids
 */
function sc_get_notification_recipients($target_type, $target_config) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    $courses_table = $wpdb->prefix . 'sc_courses';

    $user_ids = [];

    if ($target_type === 'specific') {
        // recipient_ids: array of "member_123" or "coach_456"
        $recipient_ids = isset($target_config['recipient_ids']) ? (array)$target_config['recipient_ids'] : [];
        foreach ($recipient_ids as $rid) {
            if (preg_match('/^member_(\d+)$/', $rid, $m)) {
                $uid = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM $members_table WHERE id = %d AND user_id IS NOT NULL",
                    $m[1]
                ));
                if ($uid) $user_ids[] = (int)$uid;
            } elseif (preg_match('/^coach_(\d+)$/', $rid, $m)) {
                $uid = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM $coaches_table WHERE id = %d AND user_id IS NOT NULL",
                    $m[1]
                ));
                if ($uid) $user_ids[] = (int)$uid;
            }
        }
    } elseif ($target_type === 'course') {
        // course_ids: array of course ids, send to active enrolled members
        $course_ids = isset($target_config['course_ids']) ? array_map('absint', (array)$target_config['course_ids']) : [];
        if (empty($course_ids)) return [];
        $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT m.user_id 
             FROM $member_courses_table mc
             INNER JOIN $members_table m ON mc.member_id = m.id
             WHERE mc.course_id IN ($placeholders) 
             AND mc.status = 'active'
             AND m.user_id IS NOT NULL",
            ...$course_ids
        ));
        $user_ids = array_map('intval', (array)$user_ids);
    } else {
        // all - with user_type and course_scope
        $user_type = isset($target_config['user_type']) ? $target_config['user_type'] : 'all'; // all|player|coach
        $course_scope = isset($target_config['course_scope']) ? $target_config['course_scope'] : 'all'; // all|specific
        $course_ids = isset($target_config['course_ids']) ? array_map('absint', (array)$target_config['course_ids']) : [];

        if ($course_scope === 'specific' && !empty($course_ids)) {
            $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
            if ($user_type === 'player') {
                $user_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT m.user_id 
                     FROM $member_courses_table mc
                     INNER JOIN $members_table m ON mc.member_id = m.id
                     WHERE mc.course_id IN ($placeholders) AND mc.status = 'active' AND m.user_id IS NOT NULL",
                    ...$course_ids
                ));
            } elseif ($user_type === 'coach') {
                $user_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT c.user_id 
                     FROM $course_coaches_table cc
                     INNER JOIN $coaches_table c ON cc.coach_id = c.id
                     WHERE cc.course_id IN ($placeholders) AND c.user_id IS NOT NULL",
                    ...$course_ids
                ));
            } else {
                $member_uids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT m.user_id FROM $member_courses_table mc INNER JOIN $members_table m ON mc.member_id = m.id 
                     WHERE mc.course_id IN ($placeholders) AND mc.status = 'active' AND m.user_id IS NOT NULL",
                    ...$course_ids
                ));
                $coach_uids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT c.user_id FROM $course_coaches_table cc INNER JOIN $coaches_table c ON cc.coach_id = c.id 
                     WHERE cc.course_id IN ($placeholders) AND c.user_id IS NOT NULL",
                    ...$course_ids
                ));
                $user_ids = array_unique(array_merge((array)$member_uids, (array)$coach_uids));
            }
        } else {
            if ($user_type === 'player') {
                $user_ids = $wpdb->get_col("SELECT user_id FROM $members_table WHERE user_id IS NOT NULL");
            } elseif ($user_type === 'coach') {
                $user_ids = $wpdb->get_col("SELECT user_id FROM $coaches_table WHERE user_id IS NOT NULL");
            } else {
                $member_uids = $wpdb->get_col("SELECT user_id FROM $members_table WHERE user_id IS NOT NULL");
                $coach_uids = $wpdb->get_col("SELECT user_id FROM $coaches_table WHERE user_id IS NOT NULL");
                $user_ids = array_unique(array_merge((array)$member_uids, (array)$coach_uids));
            }
        }
        $user_ids = array_map('intval', (array)$user_ids);
    }

    return array_unique(array_filter($user_ids));
}

/**
 * Get phone number for user_id (from member or coach)
 */
function sc_get_user_phone($user_id) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $phone = $wpdb->get_var($wpdb->prepare(
        "SELECT player_phone FROM $members_table WHERE user_id = %d AND player_phone IS NOT NULL AND player_phone != '' LIMIT 1",
        $user_id
    ));
    if ($phone) return $phone;
    $phone = $wpdb->get_var($wpdb->prepare(
        "SELECT mobile_phone FROM $coaches_table WHERE user_id = %d AND mobile_phone IS NOT NULL AND mobile_phone != '' LIMIT 1",
        $user_id
    ));
    if ($phone) return $phone;
    return get_user_meta($user_id, 'billing_phone', true) ?: '';
}

/**
 * Save notification and add recipients, optionally send SMS
 */
function sc_save_notification($data) {
    global $wpdb;
    $notifications_table = $wpdb->prefix . 'sc_notifications';
    $recipients_table = $wpdb->prefix . 'sc_notification_recipients';

    $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
    $content = isset($data['content']) ? sanitize_textarea_field($data['content']) : '';
    $target_type = isset($data['target_type']) ? sanitize_text_field($data['target_type']) : 'all';
    $target_config = isset($data['target_config']) ? $data['target_config'] : [];
    $coach_id = function_exists('sc_current_user_coach_id') ? sc_current_user_coach_id() : 0;
    $is_coach = $coach_id > 0;
    $send_sms = $is_coach ? 0 : (isset($data['send_sms']) ? (int)$data['send_sms'] : 0);
    $notification_id = isset($data['id']) ? absint($data['id']) : 0;

    if (empty($title)) return ['success' => false, 'message' => 'عنوان الزامی است.'];
    if (empty($content)) return ['success' => false, 'message' => 'متن اطلاعیه الزامی است.'];

    $user_ids = sc_get_notification_recipients($target_type, $target_config);
    if (empty($user_ids)) return ['success' => false, 'message' => 'هیچ مخاطبی برای ارسال انتخاب نشده است.'];

    $now = current_time('mysql');
    $created_by = get_current_user_id();
    $created_by_type = $is_coach ? 'coach' : 'admin';
    $created_by_entity_id = $is_coach ? $coach_id : 0;

    if ($notification_id > 0) {
        $wpdb->update(
            $notifications_table,
            [
                'title' => $title,
                'content' => $content,
                'target_type' => $target_type,
                'target_config' => wp_json_encode($target_config),
                'send_sms' => $send_sms,
                'created_by_type' => $created_by_type,
                'created_by_entity_id' => $created_by_entity_id,
                'updated_at' => $now
            ],
            ['id' => $notification_id],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s'],
            ['%d']
        );
        $wpdb->delete($recipients_table, ['notification_id' => $notification_id], ['%d']);
    } else {
        $wpdb->insert(
            $notifications_table,
            [
                'title' => $title,
                'content' => $content,
                'target_type' => $target_type,
                'target_config' => wp_json_encode($target_config),
                'send_sms' => $send_sms,
                'created_by' => $created_by,
                'created_by_type' => $created_by_type,
                'created_by_entity_id' => $created_by_entity_id,
                'created_at' => $now,
                'updated_at' => $now
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s']
        );
        $notification_id = $wpdb->insert_id;
    }

    foreach ($user_ids as $uid) {
        $wpdb->insert(
            $recipients_table,
            ['notification_id' => $notification_id, 'user_id' => $uid, 'created_at' => $now],
            ['%d', '%d', '%s']
        );
    }

    $sms_text = $title . "\n" . $content;
    $sms_sent = 0;
    if ($send_sms && function_exists('sc_send_sms')) {
        foreach ($user_ids as $uid) {
            $phone = sc_get_user_phone($uid);
            if ($phone) {
                $r = sc_send_sms($phone, $sms_text, false);
                if (!empty($r['success'])) $sms_sent++;
            }
        }
    }

    return [
        'success' => true,
        'notification_id' => $notification_id,
        'recipients_count' => count($user_ids),
        'sms_sent' => $sms_sent
    ];
}

/**
 * Get user notifications (for panel)
 * @param int    $user_id
 * @param int    $limit
 * @param int    $offset
 * @param bool   $unread_only فقط خوانده‌نشده‌ها
 * @param bool   $read_only   فقط خوانده‌شده‌ها
 * @param string $search      جستجو در عنوان و متن
 */
function sc_get_user_notifications($user_id, $limit = 50, $offset = 0, $unread_only = false, $read_only = false, $search = '') {
    global $wpdb;
    $notifications_table = $wpdb->prefix . 'sc_notifications';
    $recipients_table = $wpdb->prefix . 'sc_notification_recipients';
    $reads_table = $wpdb->prefix . 'sc_notification_reads';

    $where = ' WHERE nr.user_id = %d ';
    $prepare_args = [$user_id, $user_id];

    if ($read_only) {
        $where .= ' AND r.id IS NOT NULL ';
    } elseif ($unread_only) {
        $where .= ' AND r.id IS NULL ';
    }

    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where .= ' AND (n.title LIKE %s OR n.content LIKE %s) ';
        $prepare_args[] = $like;
        $prepare_args[] = $like;
    }

    $prepare_args[] = $limit;
    $prepare_args[] = $offset;

    $sql = "SELECT n.id, n.title, n.content, n.created_at,
                CASE WHEN r.read_at IS NOT NULL THEN 1 ELSE 0 END as is_read
         FROM $recipients_table nr
         INNER JOIN $notifications_table n ON nr.notification_id = n.id
         LEFT JOIN $reads_table r ON r.notification_id = nr.notification_id AND r.user_id = %d
         $where
         ORDER BY n.created_at DESC
         LIMIT %d OFFSET %d";

    return $wpdb->get_results($wpdb->prepare($sql, $prepare_args));
}

/**
 * Count user notifications with same filters (for pagination)
 */
function sc_count_user_notifications($user_id, $unread_only = false, $read_only = false, $search = '') {
    global $wpdb;
    $notifications_table = $wpdb->prefix . 'sc_notifications';
    $recipients_table = $wpdb->prefix . 'sc_notification_recipients';
    $reads_table = $wpdb->prefix . 'sc_notification_reads';

    $where = ' WHERE nr.user_id = %d ';
    $prepare_args = [$user_id, $user_id];

    if ($read_only) {
        $where .= ' AND r.id IS NOT NULL ';
    } elseif ($unread_only) {
        $where .= ' AND r.id IS NULL ';
    }

    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where .= ' AND (n.title LIKE %s OR n.content LIKE %s) ';
        $prepare_args[] = $like;
        $prepare_args[] = $like;
    }

    $sql = "SELECT COUNT(*)
         FROM $recipients_table nr
         INNER JOIN $notifications_table n ON nr.notification_id = n.id
         LEFT JOIN $reads_table r ON r.notification_id = nr.notification_id AND r.user_id = %d
         $where";

    return (int) $wpdb->get_var($wpdb->prepare($sql, $prepare_args));
}

/**
 * Count unread notifications for user
 */
function sc_count_unread_notifications($user_id) {
    global $wpdb;
    $recipients_table = $wpdb->prefix . 'sc_notification_recipients';
    $reads_table = $wpdb->prefix . 'sc_notification_reads';

    return (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM $recipients_table nr
         LEFT JOIN $reads_table r ON r.notification_id = nr.notification_id AND r.user_id = %d
         WHERE nr.user_id = %d AND r.id IS NULL",
        $user_id, $user_id
    ));
}

/**
 * Get single notification for user (if they are recipient)
 */
function sc_get_user_notification_detail($notification_id, $user_id) {
    global $wpdb;
    $notifications_table = $wpdb->prefix . 'sc_notifications';
    $recipients_table = $wpdb->prefix . 'sc_notification_recipients';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT n.id, n.title, n.content, n.created_at
         FROM $recipients_table nr
         INNER JOIN $notifications_table n ON nr.notification_id = n.id
         WHERE nr.notification_id = %d AND nr.user_id = %d",
        $notification_id, $user_id
    ));
}

/**
 * Mark notification as read
 */
function sc_mark_notification_read($notification_id, $user_id) {
    global $wpdb;
    $recipients_table = $wpdb->prefix . 'sc_notification_recipients';
    $reads_table = $wpdb->prefix . 'sc_notification_reads';

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $recipients_table WHERE notification_id = %d AND user_id = %d",
        $notification_id, $user_id
    ));
    if (!$exists) return false;

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $reads_table WHERE notification_id = %d AND user_id = %d",
        $notification_id, $user_id
    ));
    if ($existing) return true;

    $wpdb->insert(
        $reads_table,
        ['notification_id' => $notification_id, 'user_id' => $user_id, 'read_at' => current_time('mysql')],
        ['%d', '%d', '%s']
    );
    return true;
}

add_action('wp_ajax_sc_mark_notification_read', 'sc_ajax_mark_notification_read');
function sc_ajax_mark_notification_read() {
    check_ajax_referer('sc_mark_notification_read', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'لطفاً وارد شوید.']);
    }
    $notification_id = isset($_POST['notification_id']) ? absint($_POST['notification_id']) : 0;
    if (!$notification_id) {
        wp_send_json_error(['message' => 'شناسه نامعتبر.']);
    }
    $user_id = get_current_user_id();
    $ok = sc_mark_notification_read($notification_id, $user_id);
    if ($ok) {
        wp_send_json_success();
    }
    wp_send_json_error(['message' => 'خطا در ثبت.']);
}

/**
 * AJAX: فیلتر و جستجوی اطلاعیه‌ها (بدون ریدایرکت)
 */
add_action('wp_ajax_sc_notifications_filter', 'sc_ajax_notifications_filter');
function sc_ajax_notifications_filter() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'لطفاً وارد شوید.']);
    }
    $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
    if (!in_array($filter, ['all', 'unread', 'read'], true)) {
        $filter = 'all';
    }
    $search = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
    $page = isset($_POST['notif_page']) ? max(1, absint($_POST['notif_page'])) : 1;
    $per_page = 15;
    $user_id = get_current_user_id();
    $unread_only = ($filter === 'unread');
    $read_only = ($filter === 'read');
    $notifications = sc_get_user_notifications($user_id, $per_page, ($page - 1) * $per_page, $unread_only, $read_only, $search);
    $total = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($user_id, $unread_only, $read_only, $search) : 0;
    $total_pages = max(1, ceil($total / $per_page));
    $base_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-notifications') : '';
    $base_url_with_filter = $base_url;
    if ($filter !== 'all') {
        $base_url_with_filter = add_query_arg('filter', $filter, $base_url_with_filter);
    }
    if ($search !== '') {
        $base_url_with_filter = add_query_arg('s', $search, $base_url_with_filter);
    }
    $items = [];
    foreach ($notifications as $n) {
        // view_url فقط با base_url (بدون پارامترهای جستجو)
        $view_url = add_query_arg('view', $n->id, $base_url);
        $items[] = [
            'id' => (int) $n->id,
            'title' => $n->title,
            'created_at' => function_exists('sc_date_shamsi') ? sc_date_shamsi($n->created_at, 'Y/m/d') : $n->created_at,
            'is_read' => !empty($n->is_read),
            'view_url' => $view_url,
        ];
    }
    wp_send_json_success([
        'items' => $items,
        'total' => (int) $total,
        'total_pages' => $total_pages,
        'page' => $page,
        'empty_message' => sc_notifications_empty_message($filter, $search),
        'base_url_with_filter' => $base_url_with_filter,
    ]);
}

function sc_notifications_empty_message($filter, $search) {
    if ($search !== '') {
        return 'نتیجه‌ای برای جستجو یافت نشد.';
    }
    if ($filter === 'unread') {
        return 'همه اطلاعیه‌ها خوانده شده‌اند.';
    }
    if ($filter === 'read') {
        return 'هنوز اطلاعیه‌ای به عنوان خوانده شده ندارید.';
    }
    return 'هنوز اطلاعیه‌ای دریافت نکرده‌اید.';
}
