<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_get_member_by_chatid($chat_id) {
    global $wpdb;
    $table_members = $wpdb->prefix . 'sc_members';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id,
                CONCAT(first_name, ' ', last_name) AS full_name,
                national_id, player_phone, birth_date_shamsi, birth_date_gregorian,
                insurance_expiry_date_gregorian, is_active, profile_completed,
                updated_at, member_type
         FROM $table_members WHERE bot_id = %s",
        $chat_id
    ));
}

function sc_require_connected_user($chat_id) {
    global $wpdb;
    $table_members = $wpdb->prefix . 'sc_members';

    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT user_id FROM $table_members WHERE bot_id = %s",
        $chat_id
    ));

    if (!$member) {
        $link = home_url('/my-account/bot-connect');
        $text = "❌ حساب شما به سایت متصل نیست.\n\nبرای استفاده از امکانات ربات ابتدا حساب خود را متصل کنید.";
        $buttons = [[bale_make_link_button('🔗 اتصال به حساب', $link)]];
        bale_send_message_with_buttons($chat_id, $text, $buttons);
        return false;
    }

    return $member;
}

function sc_unlink_bot_account($chat_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';

    return $wpdb->update(
        $table,
        ['bot_id' => null],
        ['bot_id' => $chat_id],
        ['%s'],
        ['%s']
    );
}

add_action('init', 'sc_handle_bot_reset');

function sc_handle_bot_reset() {
    if (!isset($_POST['sc_reset_bot']) || !is_user_logged_in()) {
        return;
    }
    if (!wp_verify_nonce($_POST['_wpnonce'], 'sc_reset_bot_connection')) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';

    $wpdb->update(
        $table,
        ['bot_id' => null, 'bot_token' => null],
        ['user_id' => get_current_user_id()],
        ['%s', '%s'],
        ['%d']
    );

    wc_add_notice('اتصال ربات با موفقیت بازنشانی شد.', 'success');
}
