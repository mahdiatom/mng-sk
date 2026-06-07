<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cmd_invoices($chat_id) {
    bale_present_invoices($chat_id, 1);
}
