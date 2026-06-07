<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_private_notes($chat_id) {
    $buttons = [
        [bale_make_link_button('یادداشت‌های من', '/my-account/sc-private-notes')],
    ];

    $text = "*یادداشت‌های من*\n\n"
        . "یادداشت‌های شخصی ثبت‌شده توسط مربی یا مجموعه را مشاهده کنید.";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
