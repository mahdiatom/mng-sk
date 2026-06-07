<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_shop($chat_id) {
    $buttons = [
        [
            bale_make_link_button('مشاهده فروشگاه', '/shop'),
            ['text' => 'دسته‌بندی فروشگاه', 'callback_data' => 'cat_shop'],
        ],
    ];

    $text = "*فروشگاه*\n\n"
        . "برای مشاهده محصولات یکی از گزینه‌های زیر را انتخاب کنید.\n\n"
        . "_پ.ن: تمامی خریدها از سایت باشگاه انجام می‌شود._";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
