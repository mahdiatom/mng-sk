<?php
if (!defined('ABSPATH')) {
    exit;
}

$sc_private_bookings_page_slug = 'sc-coach-private-classes';
$sc_private_bookings_force_coach_scope = true;
include SC_TEMPLATES_ADMIN_DIR . 'private-bookings-list.php';
