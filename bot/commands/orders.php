<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_orders($chat_id) {
    $buttons = [
        [bale_make_link_button('سفارش‌های فروشگاه', '/my-account/my-orders')],
        [bale_make_link_button('فروشگاه', '/shop')],
    ];

    $text = "*سفارش‌های فروشگاه*\n\n"
        . "وضعیت سفارش‌ها و جزئیات خریدهای فروشگاه را پیگیری کنید.";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
