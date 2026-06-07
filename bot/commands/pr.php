<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_pr($chat_id) {
    bale_present_attendances($chat_id);
}
