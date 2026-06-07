<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_notifications_inbox($chat_id) {
    bale_present_notifications($chat_id, 1);
}
