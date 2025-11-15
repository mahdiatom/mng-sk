<?php 

/**
 * ============================
 * Admin Menu
 * ============================
 */
add_action('admin_menu', 'sc_register_admin_menu');

function sc_register_admin_menu() {

    // Main menu
    add_menu_page(
        'SportClub Manager',        // Page title
        'SportClub Manager',        // Menu title
        'manage_options',           // Capability
        'sc-dashboard',             // Menu slug
        'sc_admin_dashboard_page',  // Callback
        'dashicons-universal-access-alt', // Icon
        26                          // Position
    );

    // Members list
    add_submenu_page(
        'sc-dashboard',
        'Members',
        'Members',
        'manage_options',
        'sc-members',
        'sc_admin_members_list_page'
    );

    // Add Member
    add_submenu_page(
        'sc-dashboard',
        'Add Member',
        'Add Member',
        'manage_options',
        'sc-add-member',
        'sc_admin_add_member_page'
    );
}

/**
 * Placeholder functions for admin pages
 */
function sc_admin_dashboard_page() {
    echo "<h1>SportClub Manager Dashboard</h1>";
}

function sc_admin_members_list_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'members-list.php';
}

function sc_admin_add_member_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'member-add.php';
}
