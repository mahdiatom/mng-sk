<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_pr($chat_id) {
    $buttons = [
        [bale_make_link_button('مشاهده حضور و غیاب‌های من', '/my-account/sc-my-attendances')],
    ];

    $text = "*بخش حضور و غیاب*\n\n"
        . "در این بخش می‌توانید حضور و غیاب‌های خود را مشاهده کنید.\n\n"
        . "_پ.ن: در صورت مغایرت با مسئول مجموعه تماس بگیرید._";

    bale_send_message_with_buttons($chat_id, $text, $buttons);
}
