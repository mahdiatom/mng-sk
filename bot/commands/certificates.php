<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_certificates($chat_id) {
    bale_present_certificates($chat_id, 1);
}
