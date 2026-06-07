<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_private_classes($chat_id) {
    $buttons = [
        [bale_make_link_button('کلاس‌های خصوصی', '/my-account/sc-private-classes')],
    ];

    $text = "*کلاس خصوصی*\n\n"
        . "رزرو، مشاهده و مدیریت کلاس‌های خصوصی از این بخش انجام می‌شود.";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
