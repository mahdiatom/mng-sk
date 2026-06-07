<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_course($chat_id) {
    bale_present_courses($chat_id);
}
