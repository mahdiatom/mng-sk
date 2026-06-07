<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_honors($chat_id) {
    $buttons = [
        [bale_make_link_button('مشاهده لیست افتخارات من', '/my-account/sc-my-honors#list_honors')],
        [bale_make_link_button('افزودن افتخار جدید', '/my-account/sc-my-honors')],
    ];

    $text = "*بخش افتخارات*\n\n"
        . "در این بخش می‌توانید لیست افتخارات خود را مشاهده و افتخار جدید ثبت کنید.\n\n"
        . "_پ.ن: دسته‌بندی افتخارات توسط مدیریت تعیین می‌شود._";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
