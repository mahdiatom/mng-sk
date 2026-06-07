<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_dashboard($chat_id) {
    $buttons = [
        [bale_make_link_button('ورود به پیشخوان', '/my-account/sc-dashboard')],
    ];

    $text = "*پیشخوان*\n\n"
        . "خلاصه وضعیت حساب، دوره‌ها و اعلان‌های شما در پیشخوان نمایش داده می‌شود.";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
