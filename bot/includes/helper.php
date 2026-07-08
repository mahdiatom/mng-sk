<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_get_member_by_chatid($chat_id) {
    $user_id = sc_bot_get_user_id_by_chat_id($chat_id);
    if ($user_id <= 0) {
        return null;
    }

    $player = sc_bot_get_member_for_wp_user($user_id);
    if ($player) {
        if (!isset($player->full_name)) {
            $player->full_name = trim((string) (($player->first_name ?? '') . ' ' . ($player->last_name ?? '')));
        }
        return $player;
    }

    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id,
                CONCAT(first_name, ' ', last_name) AS full_name,
                national_id, mobile_phone AS player_phone,
                '' AS birth_date_shamsi, NULL AS birth_date_gregorian,
                NULL AS insurance_expiry_date_gregorian, is_active,
                1 AS profile_completed, updated_at, '' AS member_type
         FROM {$wpdb->prefix}sc_coaches WHERE user_id = %d LIMIT 1",
        $user_id
    ));
}

function sc_require_connected_user($chat_id) {
    $ctx = sc_bot_require_connected_context($chat_id);
    if (!$ctx) {
        return false;
    }
    return (object) ['user_id' => (int) $ctx['user_id']];
}

function sc_unlink_bot_account($chat_id) {
    return sc_bot_unlink_chat($chat_id);
}

add_action('init', 'sc_handle_bot_reset');

function sc_handle_bot_reset() {
    if (!isset($_POST['sc_reset_bot']) || !is_user_logged_in()) {
        return;
    }
    if (!wp_verify_nonce($_POST['_wpnonce'], 'sc_reset_bot_connection')) {
        return;
    }

    $user_id = get_current_user_id();
    delete_user_meta($user_id, SC_BALE_CHAT_META);
    delete_user_meta($user_id, SC_BALE_TOKEN_META);
    delete_user_meta($user_id, SC_BALE_ACTIVE_ROLE_META);

    global $wpdb;
    $wpdb->update($wpdb->prefix . 'sc_members', ['bot_id' => null, 'bot_token' => null], ['user_id' => $user_id]);
    $wpdb->update($wpdb->prefix . 'sc_coaches', ['bot_id' => null, 'bot_token' => null], ['user_id' => $user_id]);

    if (function_exists('wc_add_notice')) {
        wc_add_notice('اتصال ربات با موفقیت بازنشانی شد.', 'success');
    }
}
