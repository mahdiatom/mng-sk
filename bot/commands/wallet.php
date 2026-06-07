<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_wallet($chat_id) {
    $buttons = [
        [bale_make_link_button('مدیریت کیف پول', '/my-account/sc-wallet')],
    ];

    $text = "*کیف پول*\n\n"
        . "موجودی، شارژ و تراکنش‌های کیف پول خود را مشاهده و مدیریت کنید.";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
