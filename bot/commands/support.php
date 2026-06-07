<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_support($chat_id) {
    $buttons = [
        [bale_make_link_button('ارسال تیکت پشتیبانی', '/my-account/sc-support-tickets')],
    ];

    $text = "*بخش تیکت و پشتیبانی*\n\n"
        . "در این بخش می‌توانید سوالات خود را برای مدیریت یا مربی ارسال کنید.\n\n"
        . "حداکثر حجم فایل پیوست: ۵ مگابایت.";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
