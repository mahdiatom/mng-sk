<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_orders($chat_id) {
    bale_present_orders($chat_id, 1);
}
