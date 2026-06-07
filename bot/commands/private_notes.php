<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_private_notes($chat_id) {
    bale_present_private_notes($chat_id, 1);
}
