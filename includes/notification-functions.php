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
    // مدیر: نمایش نام کاربر وردپرس ثبت‌کننده
    $user_id = isset($notification->created_by) ? (int)$notification->created_by : 0;
    if ($user_id > 0) {
        $user = get_user_by('id', $user_id);
        if ($user) {
            $name = trim($user->display_name);
            if (empty($name) && !empty($user->user_login)) {
                $name = $user->user_login;
            }
            return $name ? ('مدیر: ' . $name) : 'مدیر';
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
    if ($target_type === 'phone') {
        return [];
    }
        global $wpdb;
        $members_table = $wpdb->prefix . 'sc_members';
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        $member_courses_table = $wpdb->prefix . 'sc_member_courses';
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
        $courses_table = $wpdb->prefix . 'sc_courses';

        $user_ids = [];

    if ($target_type === 'admin_users') {
        $admin_roles = isset($target_config['roles']) && is_array($target_config['roles']) ? $target_config['roles'] : array_merge(['administrator'], function_exists('sc_get_club_manager_role_slugs') ? sc_get_club_manager_role_slugs() : ['club_coach']);
        $admin_roles = array_values(array_filter(array_map('sanitize_key', $admin_roles)));
        if (empty($admin_roles)) {
            $admin_roles = ['administrator'];
        }
        $admins = function_exists('get_users') ? get_users([
            'role__in' => $admin_roles,
            'fields' => 'ID',
            'number' => 500,
        ]) : [];
        $user_ids = array_map('intval', (array) $admins);
        $user_ids = array_values(array_filter(array_unique($user_ids)));
    } elseif ($target_type === 'specific') {
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
    } elseif ($target_type === 'free_users') {
        $user_ids = $wpdb->get_col(
            "SELECT DISTINCT m.user_id
             FROM $members_table m
             WHERE m.is_active = 1
             AND m.user_id IS NOT NULL
             AND NOT EXISTS (
                 SELECT 1 FROM $member_courses_table mc
                 WHERE mc.member_id = m.id
             )"
        );
        $user_ids = array_map('intval', (array)$user_ids);
    } elseif ($target_type === 'course') {
        $course_values = function_exists('sc_audience_normalize_course_ids_from_request')
            ? sc_audience_normalize_course_ids_from_request($target_config['course_ids'] ?? [])
            : array_filter(array_map('absint', (array) ($target_config['course_ids'] ?? [])));
        if (empty($course_values)) {
            return [];
        }
        $user_ids = function_exists('sc_audience_get_active_member_user_ids_by_course_selections')
            ? sc_audience_get_active_member_user_ids_by_course_selections($course_values)
            : [];
        $user_ids = array_map('intval', (array) $user_ids);
    } elseif ($target_type === 'debtors') {
        $course_values = function_exists('sc_audience_normalize_course_ids_from_request')
            ? sc_audience_normalize_course_ids_from_request($target_config['course_ids'] ?? [])
            : array_filter(array_map('absint', (array) ($target_config['course_ids'] ?? [])));
        $user_ids = function_exists('sc_audience_get_debtor_user_ids_by_course_selections')
            ? sc_audience_get_debtor_user_ids_by_course_selections($course_values)
            : [];
        $user_ids = array_map('intval', (array) $user_ids);
    } elseif ($target_type === 'event') {
        // رویداد: شرکت‌کنندگان یک یا چند رویداد؛ اختیاری: فقط اشخاص انتخاب‌شده
        $events_table = $wpdb->prefix . 'sc_events';
        $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';
        $event_ids = isset($target_config['event_ids']) ? array_map('absint', (array)$target_config['event_ids']) : [];
        $recipient_ids = isset($target_config['recipient_ids']) ? (array)$target_config['recipient_ids'] : [];
        if (empty($event_ids)) return [];
        $placeholders = implode(',', array_fill(0, count($event_ids), '%d'));
        if (!empty($recipient_ids)) {
            $member_ids = [];
            foreach ($recipient_ids as $rid) {
                if (preg_match('/^member_(\d+)$/', $rid, $m)) {
                    $member_ids[] = (int)$m[1];
                }
            }
            if (empty($member_ids)) return [];
            $ph_m = implode(',', array_fill(0, count($member_ids), '%d'));
            $user_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT m.user_id 
                 FROM $event_registrations_table r
                 INNER JOIN $members_table m ON r.member_id = m.id
                 WHERE r.event_id IN ($placeholders) AND r.member_id IN ($ph_m) AND m.user_id IS NOT NULL",
                array_merge($event_ids, $member_ids)
            ));
        } else {
            $user_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT m.user_id 
                 FROM $event_registrations_table r
                 INNER JOIN $members_table m ON r.member_id = m.id
                 WHERE r.event_id IN ($placeholders) AND m.user_id IS NOT NULL",
                ...$event_ids
            ));
        }
        $user_ids = array_map('intval', (array)$user_ids);
    } elseif ($target_type === 'wallet_negative') {
        if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) return [];
        $transactions_table = $wpdb->prefix . 'sc_wallet_transactions';
        $user_ids = $wpdb->get_col(
            "SELECT m.user_id FROM $members_table m
             WHERE m.user_id IS NOT NULL AND m.is_active = 1
             AND (SELECT COALESCE(SUM(CASE WHEN wt.transaction_type IN ('charge','refund') THEN wt.amount WHEN wt.transaction_type IN ('payment','deduct','session_fee') THEN -wt.amount ELSE 0 END), 0) FROM $transactions_table wt WHERE wt.member_id = m.id AND wt.status = 'completed') < 0"
        );
        $user_ids = array_map('intval', array_filter((array)$user_ids));
    } elseif ($target_type === 'team') {
       // team_names: array of team names
        $team_names = isset($target_config['team_names']) 
            ? array_map('sanitize_text_field', (array)$target_config['team_names']) 
            : [];

        if (empty($team_names)) return [];

        global $wpdb;
        $members_table = $wpdb->prefix . 'sc_members';

        // تعداد placeholder ها مثل: %s, %s, %s
        $placeholders = implode(',', array_fill(0, count($team_names), '%s'));

        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id
            FROM $members_table
            WHERE team_player IN ($placeholders)
            AND is_active = 1
            AND user_id IS NOT NULL",
            ...$team_names
        ));

        return array_map('intval', (array)$user_ids);
    } elseif ($target_type === 'level') {

    // level_names: array of level names
    $level_names = isset($target_config['level_names']) 
        ? array_map('sanitize_text_field', (array)$target_config['level_names']) 
        : [];

    if (empty($level_names)) return [];

    $placeholders = implode(',', array_fill(0, count($level_names), '%s'));

    $user_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT user_id
         FROM $members_table
         WHERE skill_level IN ($placeholders)
         AND is_active = 1
         AND user_id IS NOT NULL",
        ...$level_names
    ));

    $user_ids = array_map('intval', (array)$user_ids);
    } elseif ($target_type === 'team_level') {
        // team_names + level_names both required
        $team_names = isset($target_config['team_names'])
            ? array_map('sanitize_text_field', (array)$target_config['team_names'])
            : [];

        $level_names = isset($target_config['level_names'])
            ? array_map('sanitize_text_field', (array)$target_config['level_names'])
            : [];

        if (empty($team_names) || empty($level_names)) {
            return [];
        }

        // placeholders
        $team_ph = implode(',', array_fill(0, count($team_names), '%s'));
        $level_ph = implode(',', array_fill(0, count($level_names), '%s'));

        // merge params
        $params = array_merge($team_names, $level_names);

        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id
            FROM $members_table
            WHERE team_player IN ($team_ph)
            AND skill_level IN ($level_ph)
            AND is_active = 1
            AND user_id IS NOT NULL",
            ...$params
        ));

        return array_map('intval', (array)$user_ids);

    } else {
        // all - with user_type and course_scope
        $user_type = isset($target_config['user_type']) ? $target_config['user_type'] : 'all'; // all|player|coach
        $course_scope = isset($target_config['course_scope']) ? $target_config['course_scope'] : 'all'; // all|specific
        $course_values = function_exists('sc_audience_normalize_course_ids_from_request')
            ? sc_audience_normalize_course_ids_from_request($target_config['course_ids'] ?? [])
            : array_filter(array_map('absint', (array) ($target_config['course_ids'] ?? [])));

        if ($course_scope === 'specific' && !empty($course_values)) {
            $course_ids = function_exists('sc_audience_extract_course_ids')
                ? sc_audience_extract_course_ids($course_values)
                : array_filter(array_map('absint', $course_values));
            if ($user_type === 'player') {
                $user_ids = function_exists('sc_audience_get_active_member_user_ids_by_course_selections')
                    ? sc_audience_get_active_member_user_ids_by_course_selections($course_values)
                    : [];
            } elseif ($user_type === 'coach') {
                if (empty($course_ids)) {
                    $user_ids = [];
                } else {
                    $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
                    $user_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT c.user_id 
                         FROM $course_coaches_table cc
                         INNER JOIN $coaches_table c ON cc.coach_id = c.id
                         WHERE cc.course_id IN ($placeholders) AND c.user_id IS NOT NULL",
                        ...$course_ids
                    ));
                }
            } else {
                $member_uids = function_exists('sc_audience_get_active_member_user_ids_by_course_selections')
                    ? sc_audience_get_active_member_user_ids_by_course_selections($course_values)
                    : [];
                if (!empty($course_ids)) {
                    $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
                    $coach_uids = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT c.user_id FROM $course_coaches_table cc INNER JOIN $coaches_table c ON cc.coach_id = c.id 
                         WHERE cc.course_id IN ($placeholders) AND c.user_id IS NOT NULL",
                        ...$course_ids
                    ));
                } else {
                    $coach_uids = [];
                }
                $user_ids = array_unique(array_merge((array) $member_uids, (array) $coach_uids));
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

    if (function_exists('sc_audience_merge_included_member_user_ids')) {
        $user_ids = sc_audience_merge_included_member_user_ids((array) $user_ids, $target_config);
    }

    $exclude_recipient_ids = isset($target_config['exclude_recipient_ids']) ? (array)$target_config['exclude_recipient_ids'] : [];
    if (!empty($exclude_recipient_ids)) {
        $excluded_user_ids = [];
        foreach ($exclude_recipient_ids as $rid) {
            if (preg_match('/^member_(\d+)$/', $rid, $m)) {
                $uid = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM $members_table WHERE id = %d AND user_id IS NOT NULL",
                    (int)$m[1]
                ));
                if ($uid) $excluded_user_ids[] = (int)$uid;
            } elseif (preg_match('/^coach_(\d+)$/', $rid, $m)) {
                $uid = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM $coaches_table WHERE id = %d AND user_id IS NOT NULL",
                    (int)$m[1]
                ));
                if ($uid) $excluded_user_ids[] = (int)$uid;
            }
        }
        if (!empty($excluded_user_ids)) {
            $user_ids = array_values(array_diff((array)$user_ids, array_unique($excluded_user_ids)));
        }
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
    $notification_type = isset($data['notification_type']) ? sanitize_key($data['notification_type']) : 'admin';
    if (!in_array($notification_type, ['admin', 'system'], true)) {
        $notification_type = 'admin';
    }
    $target_config = isset($data['target_config']) ? $data['target_config'] : [];
    $coach_id = function_exists('sc_current_user_coach_id') ? sc_current_user_coach_id() : 0;
    $is_coach = $coach_id > 0;
    $send_sms = $is_coach ? 0 : (isset($data['send_sms']) ? (int)$data['send_sms'] : 0);
    $send_bale = $is_coach ? 0 : (isset($data['send_bale']) ? (int)$data['send_bale'] : 0);
    $notification_id = isset($data['id']) ? absint($data['id']) : 0;
    $attachment_ids = isset($data['attachment_ids']) ? sc_notification_validate_attachment_ids($data['attachment_ids'], 5) : [];

    if (empty($title)) return ['success' => false, 'message' => 'عنوان الزامی است.'];
    if (empty($content)) return ['success' => false, 'message' => 'متن اطلاعیه الزامی است.'];

    $user_ids = sc_get_notification_recipients($target_type, $target_config);
    $phone_numbers = [];
    if ($target_type === 'phone') {
        $phone_numbers = isset($target_config['phone_numbers']) ? (array)$target_config['phone_numbers'] : [];
        if (function_exists('sc_clean_mobile_number')) {
            $phone_numbers = array_filter(array_map('sc_clean_mobile_number', $phone_numbers));
        } else {
            $phone_numbers = array_filter(array_map('trim', $phone_numbers));
        }
        if (empty($phone_numbers)) return ['success' => false, 'message' => 'هیچ شماره موبایل معتبری وارد نشده است.'];
    } elseif (empty($user_ids)) {
        return ['success' => false, 'message' => 'هیچ مخاطبی برای ارسال انتخاب نشده است.'];
    }

    $now = current_time('mysql');
    $created_by = get_current_user_id();
    $created_by_type = $is_coach ? 'coach' : 'admin';
    $created_by_entity_id = $is_coach ? $coach_id : 0;

    if ($notification_id > 0) {
        $old_row = $wpdb->get_row($wpdb->prepare("SELECT title, content, target_type, target_config, notification_type, send_sms, send_bale FROM $notifications_table WHERE id = %d", $notification_id), ARRAY_A);
        $attachment_json = !empty($attachment_ids) ? wp_json_encode(array_map('absint', $attachment_ids)) : null;
        $wpdb->update(
            $notifications_table,
            [
                'title' => $title,
                'content' => $content,
                'target_type' => $target_type,
                'target_config' => wp_json_encode($target_config),
                'notification_type' => $notification_type,
                'attachment_ids' => $attachment_json,
                'send_sms' => $send_sms,
                'send_bale' => $send_bale,
                'created_by_type' => $created_by_type,
                'created_by_entity_id' => $created_by_entity_id,
                'updated_at' => $now
            ],
            ['id' => $notification_id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s'],
            ['%d']
        );
        $wpdb->delete($recipients_table, ['notification_id' => $notification_id], ['%d']);
        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'notification', $notification_id, 'اطلاعیه «' . $title . '» ویرایش شد', $old_row, ['title' => $title, 'target_type' => $target_type, 'notification_type' => $notification_type, 'send_sms' => $send_sms]);
        }
    } else {
        $attachment_json = !empty($attachment_ids) ? wp_json_encode(array_map('absint', $attachment_ids)) : null;
        $wpdb->insert(
            $notifications_table,
            [
                'title' => $title,
                'content' => $content,
                'target_type' => $target_type,
                'target_config' => wp_json_encode($target_config),
                'notification_type' => $notification_type,
                'attachment_ids' => $attachment_json,
                'send_sms' => $send_sms,
                'send_bale' => $send_bale,
                'created_by' => $created_by,
                'created_by_type' => $created_by_type,
                'created_by_entity_id' => $created_by_entity_id,
                'created_at' => $now,
                'updated_at' => $now
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s']
        );
        $notification_id = $wpdb->insert_id;
        if (function_exists('sc_log_activity') && $notification_id) {
            sc_log_activity('created', 'notification', $notification_id, 'اطلاعیه «' . $title . '» ایجاد شد', null, ['title' => $title, 'target_type' => $target_type, 'notification_type' => $notification_type, 'send_sms' => $send_sms]);
        }
    }

    if ($target_type !== 'phone') {
        foreach ($user_ids as $uid) {
            $wpdb->insert(
                $recipients_table,
                ['notification_id' => $notification_id, 'user_id' => $uid, 'created_at' => $now],
                ['%d', '%d', '%s']
            );
        }
    }

    $sms_text = $content;
    $sms_sent = 0;
    $recipients_with_phone = 0;
    $sms_fail_reason = '';
    
    if ($send_sms && function_exists('sc_send_sms')) {
        if ($target_type === 'phone') {
            foreach ($phone_numbers as $phone) {
                $r = sc_send_sms($phone, $sms_text, false, null, [], 'notification');

                if (!empty($r['success'])) {
                    $sms_sent++;
                } elseif (empty($sms_fail_reason) && !empty($r['message'])) {
                    $sms_fail_reason = $r['message'];
                }
                if (function_exists('sc_log_sms')) {
                    sc_log_sms($r['success'] ? 'INFO' : 'ERROR', 'Notification SMS (phone) ' . ($r['success'] ? 'sent' : 'failed'), [
                        'phone' => $phone,
                        'success' => !empty($r['success']),
                        'message' => $r['message'] ?? ''
                    ]);
                }
                
            }
            $recipients_with_phone = count($phone_numbers);
        } else {
        foreach ($user_ids as $uid) {
            $phone = sc_get_user_phone($uid);
            if ($phone) {
                $recipients_with_phone++;
                $r = sc_send_sms($phone, $sms_text, false, null, [], 'notification');
                if (!empty($r['success'])) {
                    $sms_sent++;
                } elseif (empty($sms_fail_reason) && !empty($r['message'])) {
                    $sms_fail_reason = $r['message'];
                }
                if (function_exists('sc_log_sms')) {
                    sc_log_sms($r['success'] ? 'INFO' : 'ERROR', 'Notification SMS ' . ($r['success'] ? 'sent' : 'failed'), [
                        'user_id' => $uid,
                        'phone' => $phone,
                        'success' => !empty($r['success']),
                        'message' => $r['message'] ?? ''
                    ]);
                }
            }
        }
        }
    }

    $bale_sent = 0;
    if ($send_bale && $target_type !== 'phone' && function_exists('sc_bale_send_notification_to_users')) {
        $bale_sent = sc_bale_send_notification_to_users($user_ids, $title, $content);
    }

    $recipients_count = ($target_type === 'phone') ? count($phone_numbers) : count($user_ids);

    return [
        'success' => true,
        'notification_id' => $notification_id,
        'recipients_count' => $recipients_count,
        'sms_sent' => $sms_sent,
        'bale_sent' => $bale_sent,
        'recipients_with_phone' => $recipients_with_phone,
        'sms_fail_reason' => $sms_fail_reason
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
        "SELECT n.id, n.title, n.content, n.attachment_ids, n.created_at
         FROM $recipients_table nr
         INNER JOIN $notifications_table n ON nr.notification_id = n.id
         WHERE nr.notification_id = %d AND nr.user_id = %d",
        $notification_id, $user_id
    ));
}

/**
 * Validate notification attachment IDs (uploaded via AJAX for this context).
 *
 * @param array|string $ids Array or comma-separated string of attachment IDs.
 * @param int          $max Maximum number of attachments.
 * @return int[]
 */
function sc_notification_validate_attachment_ids($ids, $max = 5) {
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
    $ids = array_slice($ids, 0, $max);
    $valid = [];
    foreach ($ids as $aid) {
        if (!$aid) continue;
        $post = get_post($aid);
        if (!$post || $post->post_type !== 'attachment') continue;
        if ((int) get_post_meta($aid, '_sc_notification_attachment', true) !== 1) continue;
        if ((int) get_post_meta($aid, '_sc_notification_uploaded_by', true) !== $user_id) continue;
        $valid[] = $aid;
    }
    return $valid;
}

/**
 * AJAX: Upload a single notification attachment (admin or coach).
 */
add_action('wp_ajax_sc_upload_notification_attachment', 'sc_ajax_upload_notification_attachment');
function sc_ajax_upload_notification_attachment() {
    if (!is_user_logged_in() || (!current_user_can('manage_options') && !current_user_can('sc_view_coach_salary'))) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    if (!isset($_POST['sc_notification_upload_nonce']) || !wp_verify_nonce($_POST['sc_notification_upload_nonce'], 'sc_notification_upload_attachment')) {
        wp_send_json_error(['message' => 'خطای امنیتی. لطفاً صفحه را رفرش کنید.']);
    }
    $key = isset($_FILES['file']) ? 'file' : (isset($_FILES['notification_attachment']) ? 'notification_attachment' : null);
    if (!$key || empty($_FILES[$key]['name']) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => 'فایلی انتخاب نشده یا خطا در آپلود.']);
    }
    $allowed = function_exists('sc_support_allowed_mime_types') ? sc_support_allowed_mime_types() : [
        'jpg' => 'image/jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    $allowed_ext = array_keys($allowed);
    $max_size = 5 * 1024 * 1024; // 5MB
    $file = $_FILES[$key];
    if (isset($file['size']) && $file['size'] > $max_size) {
        wp_send_json_error(['message' => 'حداکثر حجم هر فایل ۵ مگابایت است.']);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        wp_send_json_error(['message' => 'فرمت فایل مجاز نیست. مجاز: تصویر، PDF، ورد، اکسل، ZIP و RAR.']);
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
    update_post_meta($attach_id, '_sc_notification_attachment', 1);
    update_post_meta($attach_id, '_sc_notification_uploaded_by', get_current_user_id());
    wp_send_json_success(['id' => $attach_id, 'name' => basename($upload['file'])]);
}

/**
 * Download URL for notification attachment (only for recipient).
 */
function sc_notification_attachment_download_url($attachment_id, $notification_id, $user_id) {
    return add_query_arg([
        'sc_notif_attachment' => (int) $attachment_id,
        'sc_notif_id' => (int) $notification_id,
        'sc_notif_user' => (int) $user_id,
        'nonce' => wp_create_nonce('sc_notif_attach_' . $attachment_id . '_' . $notification_id . '_' . $user_id),
    ], home_url('/'));
}

/**
 * Serve notification attachment download (only if user is recipient).
 */
add_action('template_redirect', 'sc_notification_attachment_download_handle');
function sc_notification_attachment_download_handle() {
    $attach_id = isset($_GET['sc_notif_attachment']) ? absint($_GET['sc_notif_attachment']) : 0;
    $notification_id = isset($_GET['sc_notif_id']) ? absint($_GET['sc_notif_id']) : 0;
    $user_id = isset($_GET['sc_notif_user']) ? absint($_GET['sc_notif_user']) : 0;
    $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
    if (!$attach_id || !$notification_id || !$user_id || !wp_verify_nonce($nonce, 'sc_notif_attach_' . $attach_id . '_' . $notification_id . '_' . $user_id)) {
        return;
    }
    if (get_current_user_id() !== $user_id) {
        status_header(403);
        exit;
    }
    global $wpdb;
    $recipients_table = $wpdb->prefix . 'sc_notification_recipients';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $recipients_table WHERE notification_id = %d AND user_id = %d",
        $notification_id, $user_id
    ));
    if (!$exists) {
        status_header(403);
        exit;
    }
    $notifications_table = $wpdb->prefix . 'sc_notifications';
    $row = $wpdb->get_row($wpdb->prepare("SELECT attachment_ids FROM $notifications_table WHERE id = %d", $notification_id));
    if (!$row || !$row->attachment_ids) {
        status_header(404);
        exit;
    }
    $ids = json_decode($row->attachment_ids, true);
    if (!is_array($ids) || !in_array($attach_id, array_map('intval', $ids), true)) {
        status_header(404);
        exit;
    }
    if (get_post_meta($attach_id, '_sc_notification_attachment', true) != '1') {
        status_header(404);
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
    $count_all = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($user_id, false, false, '') : 0;
    $count_unread = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($user_id, true, false, '') : 0;
    $count_read = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($user_id, false, true, '') : 0;

    wp_send_json_success([
        'items' => $items,
        'total' => (int) $total,
        'total_pages' => $total_pages,
        'page' => $page,
        'empty_message' => sc_notifications_empty_message($filter, $search),
        'base_url_with_filter' => $base_url_with_filter,
        'count_all' => (int) $count_all,
        'count_unread' => (int) $count_unread,
        'count_read' => (int) $count_read,
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

/**
 * AJAX: فیلتر و جستجوی اطلاعیه‌های مربی (پنل ادمین - بدون ریدایرکت)
 */
add_action('wp_ajax_sc_coach_notifications_filter', 'sc_ajax_coach_notifications_filter');
function sc_ajax_coach_notifications_filter() {
    if (!is_user_logged_in() || !current_user_can('sc_view_coach_salary')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
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
    $base_url = admin_url('admin.php?page=sc-coach-notifications');
    $base_url_with_filter = $base_url;
    if ($filter !== 'all') {
        $base_url_with_filter = add_query_arg('filter', $filter, $base_url_with_filter);
    }
    if ($search !== '') {
        $base_url_with_filter = add_query_arg('s', $search, $base_url_with_filter);
    }
    $items = [];
    foreach ($notifications as $n) {
        $view_url = add_query_arg('view', $n->id, $base_url);
        $items[] = [
            'id' => (int) $n->id,
            'title' => $n->title,
            'created_at' => function_exists('sc_date_shamsi') ? sc_date_shamsi($n->created_at, 'Y/m/d - H:i') : $n->created_at,
            'is_read' => !empty($n->is_read),
            'view_url' => $view_url,
        ];
    }
    $count_all = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($user_id, false, false, '') : 0;
    $count_unread = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($user_id, true, false, '') : 0;
    $count_read = function_exists('sc_count_user_notifications') ? sc_count_user_notifications($user_id, false, true, '') : 0;

    wp_send_json_success([
        'items' => $items,
        'total' => (int) $total,
        'total_pages' => $total_pages,
        'page' => $page,
        'empty_message' => sc_notifications_empty_message($filter, $search),
        'base_url_with_filter' => $base_url_with_filter,
        'count_all' => (int) $count_all,
        'count_unread' => (int) $count_unread,
        'count_read' => (int) $count_read,
    ]);
}

/**
 * AJAX: تعداد مخاطبین اطلاعیه بر اساس target_type و target_config
 */
add_action('wp_ajax_sc_notification_recipients_count', 'sc_ajax_notification_recipients_count');
function sc_ajax_notification_recipients_count() {
    if (!current_user_can('manage_options') && !current_user_can('sc_view_coach_salary')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
    }
    $target_type = isset($_POST['target_type']) ? sanitize_text_field($_POST['target_type']) : 'all';
    $target_config = isset($_POST['target_config']) ? (array) $_POST['target_config'] : [];
    if (isset($target_config['recipient_ids']) && is_string($target_config['recipient_ids'])) {
        $target_config['recipient_ids'] = array_filter(array_map('trim', explode(',', $target_config['recipient_ids'])));
    }
    if (isset($target_config['course_ids']) && is_string($target_config['course_ids'])) {
        $target_config['course_ids'] = function_exists('sc_audience_normalize_course_ids_from_request')
            ? sc_audience_normalize_course_ids_from_request(explode(',', $target_config['course_ids']))
            : array_map('absint', array_filter(explode(',', $target_config['course_ids'])));
    }
    if (isset($target_config['event_ids'])) {
        $target_config['event_ids'] = is_array($target_config['event_ids']) ? array_map('absint', $target_config['event_ids']) : array_map('absint', array_filter(explode(',', $target_config['event_ids'])));
    }
    if (isset($target_config['exclude_recipient_ids']) && is_string($target_config['exclude_recipient_ids'])) {
        $target_config['exclude_recipient_ids'] = array_filter(array_map('trim', explode(',', $target_config['exclude_recipient_ids'])));
    }
    if ($target_type === 'phone') {
        $phones = isset($target_config['phone_numbers']) ? $target_config['phone_numbers'] : [];
        if (is_string($phones)) {
            $phones = array_filter(array_map('trim', explode(',', $phones)));
        } else {
            $phones = array_values((array)$phones);
        }
        wp_send_json_success(['count' => count($phones)]);
        return;
    }
    $user_ids = sc_get_notification_recipients($target_type, $target_config);
    wp_send_json_success(['count' => count($user_ids)]);
}

add_action('wp_ajax_sc_notification_recipients_preview', 'sc_ajax_notification_recipients_preview');
function sc_ajax_notification_recipients_preview() {
    check_ajax_referer('sc_notification_recipients_preview', 'nonce');
    if (!current_user_can('manage_options') && !current_user_can('sc_view_coach_salary')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $target_type = isset($_POST['target_type']) ? sanitize_text_field($_POST['target_type']) : 'all';
    $target_config = isset($_POST['target_config']) ? (array) $_POST['target_config'] : [];
    if (isset($target_config['recipient_ids']) && is_string($target_config['recipient_ids'])) {
        $target_config['recipient_ids'] = array_filter(array_map('trim', explode(',', $target_config['recipient_ids'])));
    }
    if (isset($target_config['course_ids']) && is_string($target_config['course_ids'])) {
        $target_config['course_ids'] = function_exists('sc_audience_normalize_course_ids_from_request')
            ? sc_audience_normalize_course_ids_from_request(explode(',', $target_config['course_ids']))
            : array_map('absint', array_filter(explode(',', $target_config['course_ids'])));
    }
    if (isset($target_config['event_ids']) && is_string($target_config['event_ids'])) {
        $target_config['event_ids'] = array_map('absint', array_filter(explode(',', $target_config['event_ids'])));
    }
    if (isset($target_config['exclude_recipient_ids']) && is_string($target_config['exclude_recipient_ids'])) {
        $target_config['exclude_recipient_ids'] = array_filter(array_map('trim', explode(',', $target_config['exclude_recipient_ids'])));
    }

    $user_ids = sc_get_notification_recipients($target_type, $target_config);
    $total = count($user_ids);

    $items = [];
    $max_rows = 200;
    foreach (array_slice($user_ids, 0, $max_rows) as $user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            continue;
        }
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT id, first_name, last_name, national_id FROM $members_table WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        if ($member) {
            $full_name = trim((string) $member->first_name . ' ' . (string) $member->last_name);
            $items[] = [
                'recipient_id' => 'member_' . (int) $member->id,
                'name' => $full_name !== '' ? $full_name : ('بازیکن #' . (int) $member->id),
                'national_id' => (string) ($member->national_id ?: '-'),
                'type' => 'بازیکن',
            ];
            continue;
        }
        $coach = $wpdb->get_row($wpdb->prepare(
            "SELECT id, first_name, last_name, national_id FROM $coaches_table WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        if ($coach) {
            $full_name = trim((string) $coach->first_name . ' ' . (string) $coach->last_name);
            $items[] = [
                'recipient_id' => 'coach_' . (int) $coach->id,
                'name' => $full_name !== '' ? $full_name : ('مربی #' . (int) $coach->id),
                'national_id' => (string) ($coach->national_id ?: '-'),
                'type' => 'مربی',
            ];
        }
    }

    ob_start();
    if (empty($items)) {
        echo '<p class="description">هیچ مخاطبی با این فیلترها پیدا نشد.</p>';
    } else {
        echo '<div class="sc-bulk-preview-meta">تعداد مخاطبین فیلتر شده: <strong>' . esc_html((string) $total) . '</strong></div>';
        echo '<table class="wp-list-table widefat striped sc-bulk-preview-table">';
        echo '<thead><tr><th style="width:64px;"><label><input type="checkbox" id="sc-notif-preview-select-all" checked> انتخاب</label></th><th>نام</th><th>کد ملی</th><th>نوع</th></tr></thead><tbody>';
        foreach ($items as $item) {
            echo '<tr>';
            echo '<td><input type="checkbox" class="sc-notif-preview-member-check" data-recipient-id="' . esc_attr($item['recipient_id']) . '" checked></td>';
            echo '<td>' . esc_html($item['name']) . '</td>';
            echo '<td>' . esc_html($item['national_id']) . '</td>';
            echo '<td>' . esc_html($item['type']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        if ($total > $max_rows) {
            echo '<p class="description">فقط ' . esc_html((string) $max_rows) . ' مورد اول نمایش داده شد.</p>';
        }
    }
    $html = ob_get_clean();

    wp_send_json_success([
        'total' => $total,
        'html' => $html,
    ]);
}



// ارسال نوتیفیکشن خودکار به کاربران
add_action('sc_invoice_created', 'sc_send_invoice_notification_delayed');
function sc_send_invoice_notification_delayed($invoice_id) {
    wp_schedule_single_event(time() + 10, 'sc_delayed_wc_invoice_notification', [$invoice_id]);
}

add_action('sc_delayed_wc_invoice_notification', 'sc_send_invoice_notification_real');

function sc_send_invoice_notification_real($invoice_id) {
    global $wpdb;

    $invoice_table = $wpdb->prefix . 'sc_invoices';
    $members_table = $wpdb->prefix . 'sc_members';
    $invoice = $wpdb->get_row(
        $wpdb->prepare("
         SELECT * , i.woocommerce_order_id
         FROM $invoice_table i
         INNER JOIN $members_table m on i.member_id = m.id
         WHERE i.id = %d
         ",
         $invoice_id)
    );

    if (!$invoice) {
        return;
    }

    // ---------- دریافت اطلاعات کاربر ----------
    
    $member_name = $invoice ? $invoice->first_name : 'کاربر عزیز';

    // ---------- محاسبه و فرمت مبلغ ----------
    $amount = number_format($invoice->amount);

    // ---------- ساخت لینک ورود کاربر ----------
   

    // ---------- ساخت متن نوتیف شخصی‌سازی‌شده ----------
    $content  = $member_name . " عزیز،\n\n";
    $content .= "یک فاکتور جدید برای شما صادر شد.\n";
    $content .= "شماره فاکتور: " . $invoice->woocommerce_order_id . "\n";
    $content .= "مبلغ: " . $amount . " تومان\n\n";
    $content .= "توضیحات : " . $invoice->invoice_description . " \n\n";
    
    // ---------- ساخت نوتیف ----------
    sc_save_notification([
        'title' => " صدور فاکتور جدید - شماره " . $invoice->woocommerce_order_id ,
        'content' => $content,
        'target_type' => 'specific',
        'target_config' => [
            'recipient_ids' => ['member_' . $invoice->member_id]
        ],
        'notification_type' => 'system', // نوع نوتیف خودکار سیستمی
        'send_sms' => 0 // پیامک نمی‌فرستیم
    ]);

    if (function_exists('sc_bale_notify_user')) {
        sc_bale_notify_user((int) $invoice->member_id, '', $content);
    }
}



//افزودن دکمه خوانده همه 
add_action('wp_ajax_sc_mark_all_notifications_read', 'sc_mark_all_notifications_read_callback');
function sc_mark_all_notifications_read_callback() {

    check_ajax_referer('sc_mark_all_read_nonce', 'security');


    if (!is_user_logged_in()) {
        wp_send_json_error('دسترسی غیرمجاز');
    }

    global $wpdb;
    $user_id = get_current_user_id();

    $recipients_table = $wpdb->prefix . 'sc_notification_recipients';
    $reads_table = $wpdb->prefix . 'sc_notification_reads';

    $unread_ids = $wpdb->get_col($wpdb->prepare("
        SELECT nr.notification_id
        FROM $recipients_table nr
        LEFT JOIN $reads_table r 
            ON r.notification_id = nr.notification_id 
            AND r.user_id = %d
        WHERE nr.user_id = %d 
        AND r.id IS NULL
    ", $user_id, $user_id));

    if (empty($unread_ids)) {
        wp_send_json_success('هیچ اطلاعیه خوانده‌نشده‌ای وجود ندارد.');
    }

    foreach ($unread_ids as $nid) {
        $wpdb->insert(
            $reads_table,
            [
                'notification_id' => $nid,
                'user_id' => $user_id,
                'read_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s']  // اصلاح شد
        );
    }

    wp_send_json_success('تمام اعلان‌ها خوانده شدند.');
}

/**
 * AJAX: preview phone numbers from uploaded Excel (column A).
 */
add_action('wp_ajax_sc_preview_phone_excel', 'sc_ajax_preview_phone_excel');
function sc_ajax_preview_phone_excel() {
    try {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sc_preview_phone_excel')) {
            wp_send_json_error(['message' => 'خطای امنیتی. لطفاً صفحه را رفرش کنید.']);
        }

        if (empty($_FILES['phone_excel_file']['name'])) {
            if (empty($_POST['action'])) {
                wp_send_json_error(['message' => 'درخواست ناقص است. احتمالاً حجم فایل از upload_max_filesize یا post_max_size سرور بیشتر است.']);
            }
            wp_send_json_error(['message' => 'فایلی انتخاب نشده است.']);
        }

        $phones = sc_import_phone_numbers_from_uploaded_excel($_FILES['phone_excel_file']);
        if (is_wp_error($phones)) {
            wp_send_json_error(['message' => $phones->get_error_message()]);
        }

        wp_send_json_success([
            'count' => count($phones),
            'sample' => array_slice($phones, 0, 5),
        ]);
    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'خطای سرور: ' . $e->getMessage()]);
    }
}
