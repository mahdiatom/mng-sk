<?php
if (!defined('ABSPATH')) {
    exit;
}

define('SC_BALE_CHAT_META', 'sc_bale_chat_id');
define('SC_BALE_TOKEN_META', 'sc_bale_bot_token');
define('SC_BALE_ACTIVE_ROLE_META', 'sc_bale_active_role');

/**
 * @return int
 */
function sc_bot_get_user_id_by_chat_id($chat_id) {
    global $wpdb;
    $chat_id = (string) $chat_id;
    if ($chat_id === '') {
        return 0;
    }

    $user_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        SC_BALE_CHAT_META,
        $chat_id
    ));
    if ($user_id > 0) {
        return $user_id;
    }

    $members = $wpdb->prefix . 'sc_members';
    $user_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $members WHERE bot_id = %s AND user_id IS NOT NULL AND user_id > 0 LIMIT 1",
        $chat_id
    ));
    if ($user_id > 0) {
        return $user_id;
    }

    $coaches = $wpdb->prefix . 'sc_coaches';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $coaches WHERE bot_id = %s AND user_id IS NOT NULL AND user_id > 0 LIMIT 1",
        $chat_id
    ));
}

/**
 * @return int
 */
function sc_bot_find_user_by_token($token) {
    $token = trim((string) $token);
    if ($token === '') {
        return 0;
    }

    $users = get_users([
        'meta_key'   => SC_BALE_TOKEN_META,
        'meta_value' => $token,
        'number'     => 1,
        'fields'     => 'ID',
    ]);
    if (!empty($users)) {
        return (int) $users[0];
    }

    global $wpdb;
    $members = $wpdb->prefix . 'sc_members';
    $user_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $members WHERE bot_token = %s LIMIT 1",
        $token
    ));
    if ($user_id > 0) {
        return $user_id;
    }

    $coaches = $wpdb->prefix . 'sc_coaches';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $coaches WHERE bot_token = %s LIMIT 1",
        $token
    ));
}

/**
 * @return string
 */
function sc_bot_get_or_create_connect_token($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }

    $token = get_user_meta($user_id, SC_BALE_TOKEN_META, true);
    if ($token === '' || $token === null) {
        $token = wp_generate_password(20, false);
        update_user_meta($user_id, SC_BALE_TOKEN_META, $token);
    }

    sc_bot_sync_connect_token_to_legacy_tables($user_id, $token);
    return (string) $token;
}

function sc_bot_sync_connect_token_to_legacy_tables($user_id, $token) {
    global $wpdb;
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    $members = $wpdb->prefix . 'sc_members';
    $coaches = $wpdb->prefix . 'sc_coaches';

    $wpdb->update($members, ['bot_token' => $token], ['user_id' => $user_id]);
    $wpdb->update($coaches, ['bot_token' => $token], ['user_id' => $user_id]);
}

function sc_bot_link_chat_to_user($chat_id, $user_id) {
    $chat_id = (string) $chat_id;
    $user_id = (int) $user_id;
    if ($chat_id === '' || $user_id <= 0) {
        return false;
    }

    update_user_meta($user_id, SC_BALE_CHAT_META, $chat_id);
    delete_user_meta($user_id, SC_BALE_TOKEN_META);

    global $wpdb;
    $members = $wpdb->prefix . 'sc_members';
    $coaches = $wpdb->prefix . 'sc_coaches';

    $wpdb->update(
        $members,
        ['bot_id' => $chat_id, 'bot_token' => null],
        ['user_id' => $user_id]
    );
    $wpdb->update(
        $coaches,
        ['bot_id' => $chat_id, 'bot_token' => null],
        ['user_id' => $user_id]
    );

    $roles = sc_bot_get_available_roles($user_id);
    if (!empty($roles) && !get_user_meta($user_id, SC_BALE_ACTIVE_ROLE_META, true)) {
        update_user_meta($user_id, SC_BALE_ACTIVE_ROLE_META, $roles[0]);
    }

    return true;
}

function sc_bot_unlink_chat($chat_id) {
    $chat_id = (string) $chat_id;
    $user_id = sc_bot_get_user_id_by_chat_id($chat_id);
    if ($user_id <= 0) {
        return false;
    }

    delete_user_meta($user_id, SC_BALE_CHAT_META);
    delete_user_meta($user_id, SC_BALE_TOKEN_META);
    delete_user_meta($user_id, SC_BALE_ACTIVE_ROLE_META);

    global $wpdb;
    $wpdb->update($wpdb->prefix . 'sc_members', ['bot_id' => null, 'bot_token' => null], ['user_id' => $user_id]);
    $wpdb->update($wpdb->prefix . 'sc_coaches', ['bot_id' => null, 'bot_token' => null], ['user_id' => $user_id]);

    return true;
}

/**
 * @return int
 */
function sc_bot_get_coach_id_for_user($user_id) {
    global $wpdb;
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0;
    }
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}sc_coaches WHERE user_id = %d AND is_active = 1 LIMIT 1",
        $user_id
    ));
    return $id ? (int) $id : 0;
}

function sc_bot_user_is_manager($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    return user_can($user_id, 'manage_options')
        || user_can($user_id, 'club_coach')
        || user_can($user_id, 'system_manager');
}

function sc_bot_user_is_secretary_only($user_id) {
    if (!function_exists('sc_user_is_secretary_only')) {
        return false;
    }
    return sc_user_is_secretary_only($user_id);
}

function sc_bot_user_is_coach_only($user_id) {
    $user_id = (int) $user_id;
    return sc_bot_get_coach_id_for_user($user_id) > 0
        && user_can($user_id, 'coach')
        && !sc_bot_user_is_manager($user_id);
}

/**
 * @return string[]
 */
function sc_bot_get_available_roles($user_id) {
    $user_id = (int) $user_id;
    $roles = [];

    if (sc_bot_user_is_manager($user_id)) {
        $roles[] = 'manager';
    }
    if (sc_bot_user_is_secretary_only($user_id)) {
        $roles[] = 'secretary';
    }
    if (sc_bot_get_coach_id_for_user($user_id) > 0 && user_can($user_id, 'coach')) {
        $roles[] = 'coach';
    }
    if (sc_bot_get_member_for_wp_user($user_id)) {
        $roles[] = 'player';
    }

    return $roles;
}

function sc_bot_get_role_label($role) {
    $labels = [
        'player'    => 'بازیکن',
        'coach'     => 'مربی',
        'manager'   => 'مدیر',
        'secretary' => 'منشی شعبه',
    ];
    return $labels[$role] ?? $role;
}

/**
 * @return array<string, mixed>|false
 */
function sc_bot_resolve_user_context($chat_id) {
    $chat_id = (string) $chat_id;
    $user_id = sc_bot_get_user_id_by_chat_id($chat_id);
    if ($user_id <= 0) {
        return [
            'connected'    => false,
            'chat_id'      => $chat_id,
            'connect_url'  => sc_bot_get_connect_url_for_visitor(),
        ];
    }

    $available_roles = sc_bot_get_available_roles($user_id);
    if (empty($available_roles)) {
        return [
            'connected'    => false,
            'chat_id'      => $chat_id,
            'connect_url'  => sc_bot_get_connect_url_for_user($user_id),
        ];
    }

    $active_role = (string) get_user_meta($user_id, SC_BALE_ACTIVE_ROLE_META, true);
    if ($active_role === '' || !in_array($active_role, $available_roles, true)) {
        $active_role = $available_roles[0];
        update_user_meta($user_id, SC_BALE_ACTIVE_ROLE_META, $active_role);
    }

    $player = sc_bot_get_member_for_wp_user($user_id);
    $user = get_userdata($user_id);
    $full_name = '';
    if ($player) {
        $full_name = trim((string) (($player->first_name ?? '') . ' ' . ($player->last_name ?? '')));
    }
    if ($full_name === '' && $user) {
        $full_name = trim((string) $user->display_name);
    }

    return [
        'connected'       => true,
        'chat_id'         => $chat_id,
        'user_id'         => $user_id,
        'member_id'       => $player ? (int) $player->id : 0,
        'coach_id'        => sc_bot_get_coach_id_for_user($user_id),
        'full_name'       => $full_name,
        'available_roles' => $available_roles,
        'active_role'     => $active_role,
        'is_manager'      => sc_bot_user_is_manager($user_id),
        'is_secretary'    => sc_bot_user_is_secretary_only($user_id),
        'connect_url'     => sc_bot_get_connect_url_for_user($user_id),
    ];
}

function sc_bot_get_connect_url_for_user($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return home_url('/my-account/bot-connect');
    }
    if (sc_bot_user_is_manager($user_id) || sc_bot_get_coach_id_for_user($user_id) > 0 || sc_bot_user_is_secretary_only($user_id)) {
        return admin_url('admin.php?page=sc-bale-bot-connect');
    }
    return home_url('/my-account/bot-connect');
}

function sc_bot_get_connect_url_for_visitor() {
    return home_url('/my-account/bot-connect');
}

function sc_bot_get_bale_start_link($user_id) {
    $token = sc_bot_get_or_create_connect_token($user_id);
    $bot_username = function_exists('bale_get_bot_username') ? bale_get_bot_username() : '';
    if ($bot_username === '' || $token === '') {
        return '';
    }
    return 'https://ble.ir/' . rawurlencode($bot_username) . '?start=' . rawurlencode($token);
}

function sc_bot_set_active_role($user_id, $role) {
    $user_id = (int) $user_id;
    $roles = sc_bot_get_available_roles($user_id);
    if (!in_array($role, $roles, true)) {
        return false;
    }
    update_user_meta($user_id, SC_BALE_ACTIVE_ROLE_META, $role);
    return true;
}

/**
 * @return array<string, mixed>|false
 */
function sc_bot_require_connected_context($chat_id) {
    $ctx = sc_bot_resolve_user_context($chat_id);
    if (!$ctx || empty($ctx['connected'])) {
        $link = $ctx['connect_url'] ?? sc_bot_get_connect_url_for_visitor();
        $text = "❌ حساب شما به سایت متصل نیست.\n\n"
            . "برای استفاده از امکانات ربات، ابتدا از پنل سایت وارد شوید و روی «اتصال به ربات» بزنید.";
        $buttons = [[bale_make_link_button('🔗 اتصال به حساب', $link)]];
        bale_send_message_with_buttons($chat_id, $text, $buttons);
        return false;
    }
    return $ctx;
}

/**
 * @return int[]
 */
function sc_bale_get_connected_staff_user_ids() {
    global $wpdb;

    $ids = [];
    $rows = $wpdb->get_results(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '" . esc_sql(SC_BALE_CHAT_META) . "' AND meta_value != '' AND meta_value IS NOT NULL"
    );
    foreach ((array) $rows as $row) {
        $uid = (int) $row->user_id;
        if ($uid <= 0) {
            continue;
        }
        if (sc_bot_user_is_manager($uid) || sc_bot_get_coach_id_for_user($uid) > 0) {
            $ids[$uid] = $uid;
        }
    }

    return array_values($ids);
}

function sc_bale_notify_all_connected_staff($message) {
    $message = trim((string) $message);
    if ($message === '' || !function_exists('sc_bale_notify_user')) {
        return 0;
    }

    $sent = 0;
    foreach (sc_bale_get_connected_staff_user_ids() as $uid) {
        if (sc_bale_notify_user($uid, '', $message)) {
            $sent++;
        }
    }
    return $sent;
}
