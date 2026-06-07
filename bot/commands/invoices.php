<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_invoices($chat_id) {
    $buttons = [
        [bale_make_link_button('مشاهده صورتحساب‌ها', '/my-account/sc-invoices')],
    ];

    $text = "*صورتحساب*\n\n"
        . "لیست صورتحساب‌ها، وضعیت پرداخت و پرداخت آنلاین از بخش صورتحساب در دسترس است.";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
