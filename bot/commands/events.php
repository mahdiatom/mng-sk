<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_events($chat_id) {
    $buttons = [
        [bale_make_link_button('مشاهده و ثبت‌نام در رویداد', '/my-account/sc-events')],
        [bale_make_link_button('رویدادهای من', '/my-account/sc-my-events')],
    ];

    $text = "*بخش رویدادها و مسابقات*\n\n"
        . "در این بخش می‌توانید رویدادهای قابل ثبت‌نام و رویدادهای ثبت‌نامی خود را مشاهده کنید.\n\n"
        . "_پ.ن: رویداد پس از پرداخت هزینه فعال خواهد شد._";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
