<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_certificates($chat_id) {
    $buttons = [
        [bale_make_link_button('گواهینامه‌های من', '/my-account/sc-my-certificates')],
    ];

    $text = "*گواهینامه‌ها*\n\n"
        . "گواهینامه‌های صادرشده برای شما را مشاهده و دانلود کنید.";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
