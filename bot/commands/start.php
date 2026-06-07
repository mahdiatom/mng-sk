<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_start($chat_id) {
    bale_send_message_with_keyboard($chat_id, 'یکی از گزینه‌ها را انتخاب کنید:', bale_get_main_keyboard());
}
