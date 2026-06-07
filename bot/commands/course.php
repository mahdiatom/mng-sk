<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_course($chat_id) {
    $buttons = [
        [bale_make_link_button('مشاهده و ثبت‌نام در دوره', '/my-account/sc-enroll-course')],
        [bale_make_link_button('دوره‌های فعال من', '/my-account/sc-my-courses')],
    ];

    $text = "*بخش دوره‌ها*\n\n"
        . "در این بخش می‌توانید دوره‌های قابل ثبت‌نام و دوره‌های فعال خود را مشاهده کنید.\n\n"
        . "برای ثبت‌نام دوره‌ها به بخش *مشاهده و ثبت‌نام* بروید.\n\n"
        . "_پ.ن: دوره پس از پرداخت هزینه برای شما فعال خواهد شد._";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
