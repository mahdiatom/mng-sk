<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_start($chat_id, $ctx = null) {
    if (!is_array($ctx)) {
        $ctx = sc_bot_resolve_user_context($chat_id);
    }
    if (!is_array($ctx)) {
        $ctx = ['connected' => false];
    }
    bale_send_message_with_keyboard(
        $chat_id,
        bale_get_welcome_message($ctx),
        bale_get_main_keyboard($ctx)
    );
}
