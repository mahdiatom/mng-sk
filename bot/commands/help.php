<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_help($chat_id) {
    $buttons = [
        [bale_make_link_button('ورود به پنل کاربری', '/my-account/sc-dashboard')],
    ];

    bale_send_message_with_buttons(
        $chat_id,
        "راهنمای ربات:\n\nاز منوی کیبورد یکی از بخش‌ها را انتخاب کنید یا از دکمه زیر وارد پنل کاربری شوید.",
        $buttons
    );
}
