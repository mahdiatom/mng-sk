<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_surveys($chat_id) {
    $buttons = [
        [bale_make_link_button('نظرسنجی‌ها', '/my-account/sc-surveys')],
    ];

    $text = "*نظرسنجی‌ها*\n\n"
        . "در نظرسنجی‌های فعال مجموعه شرکت کنید.";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
