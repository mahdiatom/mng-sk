<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_notifications_inbox($chat_id) {
    $buttons = [
        [bale_make_link_button('اطلاعیه‌های من', '/my-account/sc-notifications')],
    ];

    $text = "*اطلاعیه‌ها*\n\n"
        . "پیام‌ها و اطلاعیه‌های مجموعه را در پنل کاربری مشاهده کنید.";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
