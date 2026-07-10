<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_bale_is_enabled() {
    return (int) sc_get_setting('sc_bale_bot_enabled', 1) === 1;
}

function sc_bale_safir_is_configured() {
    $api_key = sc_get_setting('sc_bale_safir_api_key', '');
    $bot_id  = sc_get_setting('sc_bale_safir_bot_id', '');
    return $api_key !== '' && $bot_id !== '';
}

function sc_bale_use_miniapp() {
    return (int) sc_get_setting('sc_bale_use_miniapp', 1) === 1;
}

function bale_make_link_button($text, $path) {
    $url = (strpos($path, 'http') === 0) ? $path : home_url($path);

    if (sc_bale_use_miniapp()) {
        return ['text' => $text, 'web_app' => ['url' => $url]];
    }

    return ['text' => $text, 'url' => $url];
}

/**
 * ارسال خودکار در ربات — فقط کاربران دارای Chat ID (بدون سفیر)
 *
 * @param int    $member_or_user_id شناسه عضو یا user_id
 * @param string $phone             نادیده گرفته می‌شود (سازگاری با فراخوانی‌های قدیمی)
 * @param string $message
 * @return string|false 'bot'|false
 */
function sc_bale_notify_user($member_or_user_id, $phone = '', $message = '') {
    if (!sc_bale_is_enabled() || !bale_is_configured() || $message === '') {
        return false;
    }

    $chat_id = (int) sc_get_user_bale_chat_id($member_or_user_id);
    if ($chat_id <= 0) {
        return false;
    }

    $result = bale_parse_api_response(bale_send_message($chat_id, $message));
    return $result['ok'] ? 'bot' : false;
}

/**
 * ارسال اطلاعیه به مخاطبین دارای Chat ID (فرم اطلاعیه / پیامک)
 */
function sc_bale_send_notification_to_users($user_ids, $title, $content) {
    $message = '📢 <b>' . esc_html($title) . "</b>\n\n" . $content;
    $sent = 0;

    foreach ((array) $user_ids as $uid) {
        $uid = (int) $uid;
        if ($uid <= 0) {
            continue;
        }
        if (sc_bale_notify_user($uid, '', $message)) {
            $sent++;
        }
    }

    return $sent;
}

/**
 * آینه‌سازی پیامک تنظیمات به ربات — فقط دارای Chat ID، بدون سفیر
 */
function sc_bale_mirror_sms_from_mobile($mobile, $message, $context = '') {
    $message = trim((string) $message);
    if ($message === '' || !function_exists('sc_bale_notify_user') || !sc_bale_is_enabled()) {
        return;
    }

    $skip_contexts = ['private_cancel_to_admin', 'identity_verified', 'identity_rejected'];
    if (in_array($context, $skip_contexts, true)) {
        return;
    }

    if (function_exists('sc_get_setting') && function_exists('sc_clean_mobile_number')) {
        $admin_phone = sc_get_setting('sms_admin_phone', '');
        if ($admin_phone !== '' && sc_clean_mobile_number($mobile) === sc_clean_mobile_number($admin_phone)) {
            sc_bale_notify_all_connected_staff($message);
            return;
        }
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $member_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $members_table WHERE player_phone = %s LIMIT 1",
        $mobile
    ));
    if ($member_id <= 0 && function_exists('sc_clean_mobile_number')) {
        $clean = sc_clean_mobile_number($mobile);
        if ($clean) {
            $member_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $members_table WHERE player_phone = %s LIMIT 1",
                $clean
            ));
        }
    }
    if ($member_id > 0) {
        sc_bale_notify_user($member_id, '', $message);
        return;
    }

    if (function_exists('sc_clean_mobile_number')) {
        $clean = sc_clean_mobile_number($mobile);
        if ($clean) {
            $wp_user_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'billing_phone' AND meta_value = %s LIMIT 1",
                $clean
            ));
            if ($wp_user_id > 0) {
                $linked = function_exists('sc_bot_get_member_for_wp_user')
                    ? sc_bot_get_member_for_wp_user($wp_user_id)
                    : null;
                if ($linked && !empty($linked->id)) {
                    sc_bale_notify_user((int) $linked->id, '', $message);
                    return;
                }
                sc_bale_notify_user($wp_user_id, '', $message);
                return;
            }
        }
    }

    $coach_user_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $coaches_table WHERE mobile_phone = %s LIMIT 1",
        $mobile
    ));
    if ($coach_user_id > 0) {
        sc_bale_notify_user($coach_user_id, '', $message);
    }
}

/**
 * ساخت target_config از POST — همان منطق اطلاعیه
 */
function sc_bale_parse_target_config_from_post($post) {
    $target_type = isset($post['target_type']) ? sanitize_text_field(wp_unslash($post['target_type'])) : 'all';
    $target_config = [];

    if ($target_type === 'phone') {
        $phones_str = isset($post['phone_numbers_str']) ? sanitize_text_field(wp_unslash($post['phone_numbers_str'])) : '';
        $target_config['phone_numbers'] = $phones_str ? array_filter(array_map('trim', explode(',', $phones_str))) : [];
        if (function_exists('sc_normalize_phone_numbers_list')) {
            $target_config['phone_numbers'] = sc_normalize_phone_numbers_list($target_config['phone_numbers']);
        }
        if (!empty($_FILES['phone_excel_file']['name'])) {
            $excel_phones = sc_import_phone_numbers_from_uploaded_excel($_FILES['phone_excel_file']);
            if (!is_wp_error($excel_phones)) {
                $target_config['phone_numbers'] = array_values(array_unique(array_merge(
                    (array) $target_config['phone_numbers'],
                    (array) $excel_phones
                )));
            }
        }
    } elseif ($target_type === 'all') {
        $target_config['user_type'] = isset($post['user_type']) ? sanitize_text_field(wp_unslash($post['user_type'])) : 'all';
        $target_config['course_scope'] = isset($post['course_scope']) ? sanitize_text_field(wp_unslash($post['course_scope'])) : 'all';
        if (!empty($post['course_ids']) && is_array($post['course_ids'])) {
            $target_config['course_ids'] = function_exists('sc_audience_normalize_course_ids_from_request')
                ? sc_audience_normalize_course_ids_from_request($post['course_ids'])
                : array_filter(array_map('absint', $post['course_ids']));
        }
    } elseif ($target_type === 'specific') {
        $rids = isset($post['recipient_ids_str']) ? sanitize_text_field(wp_unslash($post['recipient_ids_str'])) : '';
        $target_config['recipient_ids'] = $rids ? array_filter(array_map('trim', explode(',', $rids))) : [];
    } elseif ($target_type === 'free_users') {
        $target_config['user_type'] = 'player';
    } elseif ($target_type === 'identity_verified' || $target_type === 'identity_unverified' || $target_type === 'registration_fee_unpaid') {
        $target_config['user_type'] = 'player';
    } elseif ($target_type === 'team') {
        $target_config['team_names'] = isset($post['team_names']) && is_array($post['team_names'])
            ? array_map('sanitize_text_field', wp_unslash($post['team_names'])) : [];
    } elseif ($target_type === 'level') {
        $target_config['level_names'] = isset($post['level_names']) && is_array($post['level_names'])
            ? array_map('sanitize_text_field', wp_unslash($post['level_names'])) : [];
    } elseif ($target_type === 'team_level') {
        $target_config['team_names'] = isset($post['team_names']) && is_array($post['team_names'])
            ? array_map('sanitize_text_field', wp_unslash($post['team_names'])) : [];
        $target_config['level_names'] = isset($post['level_names']) && is_array($post['level_names'])
            ? array_map('sanitize_text_field', wp_unslash($post['level_names'])) : [];
    } elseif ($target_type === 'course') {
        $target_config['course_ids'] = function_exists('sc_audience_normalize_course_ids_from_request')
            ? sc_audience_normalize_course_ids_from_request($post['course_ids'] ?? [])
            : array_filter(array_map('absint', (array) ($post['course_ids'] ?? [])));
    } elseif ($target_type === 'debtors') {
        $target_config['course_ids'] = function_exists('sc_audience_normalize_course_ids_from_request')
            ? sc_audience_normalize_course_ids_from_request($post['debtors_course_ids'] ?? [])
            : array_filter(array_map('absint', (array) ($post['debtors_course_ids'] ?? [])));
    } elseif ($target_type === 'event') {
        $target_config['event_ids'] = isset($post['event_ids']) && is_array($post['event_ids'])
            ? array_map('absint', $post['event_ids']) : [];
        $rids = isset($post['event_recipient_ids_str']) ? sanitize_text_field(wp_unslash($post['event_recipient_ids_str'])) : '';
        $target_config['recipient_ids'] = $rids ? array_filter(array_map('trim', explode(',', $rids))) : [];
    }

    if ($target_type !== 'specific') {
        $excluded = isset($post['exclude_recipient_ids_str']) ? sanitize_text_field(wp_unslash($post['exclude_recipient_ids_str'])) : '';
        $target_config['exclude_recipient_ids'] = $excluded ? array_filter(array_map('trim', explode(',', $excluded))) : [];
    }

    $target_config['included_member_ids'] = isset($post['included_member_ids'])
        ? array_filter(array_map('absint', (array) $post['included_member_ids']))
        : [];

    return [$target_type, $target_config];
}

/**
 * لیست مخاطبین فیلترشده با وضعیت Chat ID
 */
function sc_bale_resolve_recipients($target_type, $target_config) {
    if (!function_exists('sc_get_notification_recipients')) {
        return [];
    }

    if ($target_type === 'phone') {
        $phones = isset($target_config['phone_numbers']) ? (array) $target_config['phone_numbers'] : [];
        $recipients = [];
        foreach ($phones as $phone) {
            $phone = trim((string) $phone);
            if ($phone === '') {
                continue;
            }
            $recipients[] = [
                'user_id'       => 0,
                'member_id'     => 0,
                'full_name'     => $phone,
                'phone'         => $phone,
                'bot_id'        => null,
                'has_chat_id'   => false,
                'national_id'   => '-',
                'recipient_id'  => 'phone_' . $phone,
                'type'          => 'شماره',
            ];
        }
        return $recipients;
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $user_ids = sc_get_notification_recipients($target_type, $target_config);
    $recipients = [];

    foreach ($user_ids as $user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            continue;
        }

        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, first_name, last_name, national_id, player_phone, bot_id
             FROM $members_table WHERE user_id = %d LIMIT 1",
            $user_id
        ), ARRAY_A);

        if ($member) {
            $chat_id = !empty($member['bot_id']) ? (int) $member['bot_id'] : (int) sc_get_user_bale_chat_id($member['id']);
            $recipients[] = [
                'user_id'      => $user_id,
                'member_id'    => (int) $member['id'],
                'full_name'    => trim($member['first_name'] . ' ' . $member['last_name']),
                'phone'        => $member['player_phone'] ?? '',
                'bot_id'       => $chat_id > 0 ? $chat_id : null,
                'has_chat_id'  => $chat_id > 0,
                'national_id'  => $member['national_id'] ?: '-',
                'recipient_id' => 'member_' . (int) $member['id'],
                'type'         => 'بازیکن',
            ];
            continue;
        }

        $coach = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, first_name, last_name, national_id, mobile_phone
             FROM $coaches_table WHERE user_id = %d LIMIT 1",
            $user_id
        ), ARRAY_A);

        if ($coach) {
            $chat_id = (int) sc_get_user_bale_chat_id($user_id);
            $phone = $coach['mobile_phone'] ?? '';
            if ($phone === '' && function_exists('sc_get_user_phone')) {
                $phone = sc_get_user_phone($user_id);
            }
            $recipients[] = [
                'user_id'      => $user_id,
                'member_id'    => 0,
                'full_name'    => trim($coach['first_name'] . ' ' . $coach['last_name']),
                'phone'        => $phone,
                'bot_id'       => $chat_id > 0 ? $chat_id : null,
                'has_chat_id'  => $chat_id > 0,
                'national_id'  => $coach['national_id'] ?: '-',
                'recipient_id' => 'coach_' . (int) $coach['id'],
                'type'         => 'مربی',
            ];
        }
    }

    return $recipients;
}

function sc_bale_count_recipients_by_chat($recipients) {
    $with = 0;
    $without = 0;
    foreach ($recipients as $r) {
        if (!empty($r['has_chat_id'])) {
            $with++;
        } else {
            $without++;
        }
    }
    return [
        'total'            => count($recipients),
        'with_chat_id'     => $with,
        'without_chat_id'  => $without,
    ];
}

function sc_bale_delivery_mode_label($mode) {
    $labels = [
        'bot_only'   => 'فقط دارای Chat ID (رایگان)',
        'safir_only' => 'فقط بدون Chat ID (سفیر — هزینه‌دار)',
        'both'       => 'هر دو (ربات رایگان + سفیر هزینه‌دار)',
    ];
    return $labels[$mode] ?? $mode;
}

/**
 * حذف مخاطبین از پیش‌نمایش (تیک برداشته‌شده) — شامل phone_
 */
function sc_bale_apply_excluded_recipients($recipients, $target_config) {
    $exclude = isset($target_config['exclude_recipient_ids']) ? (array) $target_config['exclude_recipient_ids'] : [];
    if (empty($exclude)) {
        return $recipients;
    }
    return array_values(array_filter($recipients, static function ($r) use ($exclude) {
        $rid = isset($r['recipient_id']) ? (string) $r['recipient_id'] : '';
        return $rid === '' || !in_array($rid, $exclude, true);
    }));
}

function sc_bale_recipient_sendable_in_mode($recipient, $delivery_mode) {
    $has_chat = !empty($recipient['has_chat_id']);
    $has_phone = !empty($recipient['phone']);

    if ($delivery_mode === 'bot_only') {
        return $has_chat;
    }
    if ($delivery_mode === 'safir_only') {
        return !$has_chat && $has_phone;
    }
    if ($delivery_mode === 'both') {
        return $has_chat || $has_phone;
    }

    return false;
}

function sc_bale_filter_recipients_for_send($recipients, $delivery_mode) {
    $filtered = [];

    foreach ($recipients as $r) {
        if (sc_bale_recipient_sendable_in_mode($r, $delivery_mode)) {
            $filtered[] = $r;
        }
    }

    return $filtered;
}

function sc_bale_expected_send_counts($recipients, $delivery_mode) {
    $bot = 0;
    $safir = 0;
    foreach ($recipients as $r) {
        $has_chat = !empty($r['has_chat_id']);
        $has_phone = !empty($r['phone']);
        if ($delivery_mode === 'bot_only' && $has_chat) {
            $bot++;
        } elseif ($delivery_mode === 'safir_only' && !$has_chat && $has_phone) {
            $safir++;
        } elseif ($delivery_mode === 'both') {
            if ($has_chat) {
                $bot++;
            } elseif ($has_phone) {
                $safir++;
            }
        }
    }
    return ['bot' => $bot, 'safir' => $safir, 'total' => $bot + $safir];
}

/**
 * @param string $delivery_mode bot_only|safir_only|both
 */
function sc_bale_send_bulk_message($title, $content, $target_type, $target_config, $delivery_mode = 'bot_only') {
    global $wpdb;

    $delivery_mode = in_array($delivery_mode, ['bot_only', 'safir_only', 'both'], true) ? $delivery_mode : 'bot_only';
    $all_recipients = sc_bale_apply_excluded_recipients(
        sc_bale_resolve_recipients($target_type, $target_config),
        $target_config
    );
    $recipients = sc_bale_filter_recipients_for_send($all_recipients, $delivery_mode);

    $bot_sent = 0;
    $safir_sent = 0;
    $fail_count = 0;

    $prefix = "📢 <b>" . esc_html($title) . "</b>\n\n";
    $message = $prefix . $content;

    foreach ($recipients as $r) {
        $has_chat = !empty($r['has_chat_id']);
        $sent = false;

        if (($delivery_mode === 'bot_only' || $delivery_mode === 'both') && $has_chat && !empty($r['bot_id'])) {
            $result = bale_parse_api_response(bale_send_message($r['bot_id'], $message));
            if ($result['ok']) {
                $bot_sent++;
                $sent = true;
            } else {
                $fail_count++;
            }
        }

        if (!$sent && ($delivery_mode === 'safir_only' || $delivery_mode === 'both') && !$has_chat && !empty($r['phone'])) {
            if (!sc_bale_safir_is_configured()) {
                $fail_count++;
                continue;
            }
            $result = bale_parse_api_response(sc_bale_send_by_phone(sc_convert_phone_to_98($r['phone']), $message));
            if ($result['ok']) {
                $safir_sent++;
            } else {
                $fail_count++;
            }
        }
    }

    $target_config['delivery_mode'] = $delivery_mode;
    $stats = sc_bale_count_recipients_by_chat($all_recipients);

    $table = $wpdb->prefix . 'sc_bot_messages';
    $wpdb->insert($table, [
        'title'             => $title,
        'content'           => $content,
        'target_type'       => $target_type,
        'target_config'     => wp_json_encode($target_config, JSON_UNESCAPED_UNICODE),
        'send_safir'        => $delivery_mode === 'safir_only' ? 1 : ($delivery_mode === 'both' ? 2 : 0),
        'recipients_count'  => count($recipients),
        'bot_sent_count'    => $bot_sent,
        'safir_sent_count'  => $safir_sent,
        'fail_count'        => $fail_count,
        'created_by'        => get_current_user_id(),
        'created_at'        => current_time('mysql'),
    ], ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s']);

    return [
        'success'           => true,
        'recipients_count'  => count($recipients),
        'bot_sent'          => $bot_sent,
        'safir_sent'        => $safir_sent,
        'fail_count'        => $fail_count,
        'with_chat_id'      => $stats['with_chat_id'],
        'without_chat_id'   => $stats['without_chat_id'],
        'message_id'        => (int) $wpdb->insert_id,
    ];
}

/** @deprecated */
function sc_bale_get_target_members($target_type, $target_config, $only_connected = true) {
    $all = sc_bale_resolve_recipients($target_type, $target_config);
    if (!$only_connected) {
        return $all;
    }
    return array_values(array_filter($all, static function ($r) {
        return !empty($r['has_chat_id']);
    }));
}
