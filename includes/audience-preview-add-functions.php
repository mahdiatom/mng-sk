<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param mixed $source
 * @return int[]
 */
function sc_audience_parse_included_member_ids($source) {
    if ($source === null || $source === '') {
        return [];
    }
    if (!is_array($source)) {
        $source = [$source];
    }
    return array_values(array_unique(array_filter(array_map('absint', $source))));
}

/**
 * @param int[] $ids
 * @return object[]
 */
function sc_audience_fetch_members_by_ids(array $ids) {
    $ids = sc_audience_parse_included_member_ids($ids);
    if (empty($ids)) {
        return [];
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $members = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $members_table WHERE id IN ($placeholders) ORDER BY last_name ASC, first_name ASC",
        ...$ids
    ));

    return is_array($members) ? $members : [];
}

/**
 * @param object[] $members
 * @param int[]    $included_ids
 * @return object[]
 */
function sc_audience_merge_included_members_into_list(array $members, array $included_ids) {
    $included_ids = sc_audience_parse_included_member_ids($included_ids);
    if (empty($included_ids)) {
        return $members;
    }

    $existing_ids = [];
    foreach ($members as $member) {
        if (is_object($member) && isset($member->id)) {
            $existing_ids[(int) $member->id] = true;
        }
    }

    $missing = array_values(array_filter($included_ids, static function ($id) use ($existing_ids) {
        return !isset($existing_ids[$id]);
    }));
    if (empty($missing)) {
        return $members;
    }

    foreach (sc_audience_fetch_members_by_ids($missing) as $member) {
        $members[] = $member;
    }

    return $members;
}

/**
 * @param int[] $member_ids
 * @param int[] $included_ids
 * @return int[]
 */
function sc_audience_merge_included_member_ids(array $member_ids, array $included_ids) {
    $member_ids = array_values(array_unique(array_filter(array_map('absint', $member_ids))));
    $included_ids = sc_audience_parse_included_member_ids($included_ids);
    if (empty($included_ids)) {
        return $member_ids;
    }
    return array_values(array_unique(array_merge($member_ids, $included_ids)));
}

/**
 * @param object[] $members
 * @param array    $config
 * @return object[]
 */
function sc_audience_apply_included_members_config(array $members, array $config) {
    $included_ids = isset($config['included_member_ids']) ? (array) $config['included_member_ids'] : [];
    $members = sc_audience_merge_included_members_into_list($members, $included_ids);

    $excluded_ids = isset($config['excluded_member_ids']) ? sc_audience_parse_included_member_ids($config['excluded_member_ids']) : [];
    if (empty($excluded_ids)) {
        return $members;
    }

    return array_values(array_filter($members, static function ($member) use ($excluded_ids) {
        return is_object($member) && isset($member->id) && !in_array((int) $member->id, $excluded_ids, true);
    }));
}

/**
 * @param int[] $user_ids
 * @param array $target_config
 * @return int[]
 */
function sc_audience_merge_included_member_user_ids(array $user_ids, array $target_config) {
    $included_ids = isset($target_config['included_member_ids']) ? (array) $target_config['included_member_ids'] : [];
    $included_ids = sc_audience_parse_included_member_ids($included_ids);
    if (empty($included_ids)) {
        return array_values(array_unique(array_filter(array_map('intval', $user_ids))));
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    foreach ($included_ids as $member_id) {
        $uid = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $members_table WHERE id = %d AND user_id IS NOT NULL LIMIT 1",
            $member_id
        ));
        if ($uid > 0) {
            $user_ids[] = $uid;
        }
    }

    return array_values(array_unique(array_filter(array_map('intval', $user_ids))));
}

/**
 * @param array $recipients
 * @param array $target_config
 * @return array
 */
function sc_audience_merge_included_into_bale_recipients(array $recipients, array $target_config) {
    $included_ids = isset($target_config['included_member_ids']) ? (array) $target_config['included_member_ids'] : [];
    $included_ids = sc_audience_parse_included_member_ids($included_ids);
    if (empty($included_ids)) {
        return $recipients;
    }

    $existing = [];
    foreach ($recipients as $recipient) {
        $rid = isset($recipient['recipient_id']) ? (string) $recipient['recipient_id'] : '';
        if ($rid !== '') {
            $existing[$rid] = true;
        }
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $chat_table = $wpdb->prefix . 'sc_bale_user_chats';

    foreach ($included_ids as $member_id) {
        $rid = 'member_' . $member_id;
        if (isset($existing[$rid])) {
            continue;
        }
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT id, first_name, last_name, national_id, player_phone, user_id FROM $members_table WHERE id = %d LIMIT 1",
            $member_id
        ));
        if (!$member) {
            continue;
        }
        $full_name = trim((string) $member->first_name . ' ' . (string) $member->last_name);
        $phone = (string) ($member->player_phone ?: '');
        $has_chat = false;
        if ((int) $member->user_id > 0) {
            $chat_id = $wpdb->get_var($wpdb->prepare(
                "SELECT chat_id FROM $chat_table WHERE wp_user_id = %d AND chat_id IS NOT NULL AND chat_id != '' LIMIT 1",
                (int) $member->user_id
            ));
            $has_chat = !empty($chat_id);
        }
        $recipients[] = [
            'recipient_id' => $rid,
            'name' => $full_name !== '' ? $full_name : ('بازیکن #' . (int) $member->id),
            'national_id' => (string) ($member->national_id ?: '-'),
            'type' => 'بازیکن',
            'phone' => $phone,
            'has_chat_id' => $has_chat,
        ];
        $existing[$rid] = true;
    }

    return $recipients;
}

/**
 * @return object[]
 */
function sc_audience_get_members_for_preview_add_picker() {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    return $wpdb->get_results(
        "SELECT id, first_name, last_name, national_id, member_type, team_player, skill_level, is_active
         FROM $members_table
         WHERE is_active = 1
         ORDER BY last_name ASC, first_name ASC"
    );
}

/**
 * @param object[] $members
 * @param array    $args
 */
function sc_audience_render_preview_add_members_block($members, array $args = []) {
    $defaults = [
        'wrap_id' => 'sc-audience-preview-add-wrap',
        'dropdown_id' => 'sc-audience-preview-add-dropdown',
        'options_id' => 'sc-audience-preview-add-options',
        'hidden_inputs_id' => 'sc-audience-preview-add-inputs',
        'mode' => 'member',
        'label' => 'افزودن کاربر به لیست پیش‌نمایش',
        'description' => 'در صورت نیاز می‌توانید کاربران دیگری را به لیست پیش‌نمایش اضافه کنید.',
    ];
    $args = wp_parse_args($args, $defaults);

    $template = defined('SC_TEMPLATES_ADMIN_DIR')
        ? SC_TEMPLATES_ADMIN_DIR . 'partials/audience-preview-add-members.php'
        : dirname(__DIR__) . '/templates/admin/partials/audience-preview-add-members.php';

    if (!file_exists($template)) {
        return;
    }

    include $template;
}
