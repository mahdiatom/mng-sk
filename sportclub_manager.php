<?php
/*
Plugin Name: SportClub Manager
Plugin URI:  https://example.com
Description: Sport club management plugin (members, courses, payments, attendance, etc.)
Version:     1.0
Author:      Mahdi Babashahi
Author URI:  https://example.com
License:     GPL2
Text Domain: sportclub-manager
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================
 * Define constants for paths and URLs
 * ============================
 */
define('SC_PLUGIN_DIR', plugin_dir_path(__FILE__));              // Physical path to the plugin
define('SC_PLUGIN_URL', plugin_dir_url(__FILE__));               // URL to the plugin

define('SC_INCLUDES_DIR', SC_PLUGIN_DIR . 'includes/');          // Includes folder
define('SC_ADMIN_DIR', SC_PLUGIN_DIR . 'admin/');                // Admin pages folder
define('SC_PUBLIC_DIR', SC_PLUGIN_DIR . 'public/');              // Public pages folder
define('SC_TEMPLATES_DIR', SC_PLUGIN_DIR . 'templates/');        // Templates folder
define('SC_TEMPLATES_ADMIN_DIR', SC_TEMPLATES_DIR . 'admin/');  // Admin templates
define('SC_TEMPLATES_PUBLIC_DIR', SC_TEMPLATES_DIR . 'public/');// Public templates
define('SC_ASSETS_DIR', SC_PLUGIN_DIR . 'assets/');              // Assets folder (CSS, JS, images)
define('SC_ASSETS_URL', SC_PLUGIN_URL . 'assets/');              // Assets URL

/**
 * ============================
 * Include core plugin files
 * ============================
 */
require_once SC_INCLUDES_DIR . 'db-functions.php';          // Database table creation functions
include(SC_ADMIN_DIR . 'admin-menu.php');
// Include WooCommerce My Account integration
require_once SC_PUBLIC_DIR . 'my-account.php';

/**
 * ============================
 * Activation & Deactivation Hooks
 * ============================
 */
register_activation_hook(__FILE__, 'sc_activate_plugin');
//register_deactivation_hook(__FILE__, 'sc_deactivate_plugin');

function sc_activate_plugin() {
    sc_create_members_table();
    sc_create_courses_table();
    sc_create_member_courses_table();
    
    // Flush rewrite rules for WooCommerce endpoint
    flush_rewrite_rules();
}

/**
 * Add user_id column to existing table if not exists
 */
add_action('admin_init', 'sc_add_user_id_column');
function sc_add_user_id_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    
    // بررسی وجود ستون user_id
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'user_id'
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `user_id` bigint(20) unsigned DEFAULT NULL AFTER `id`");
        $wpdb->query("ALTER TABLE $table_name ADD UNIQUE KEY `idx_user_id` (`user_id`)");
    }
}

/**
 * Add sessions_count column to courses table if not exists
 */
add_action('admin_init', 'sc_add_sessions_count_column');
function sc_add_sessions_count_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_courses';
    
    // بررسی وجود ستون sessions_count
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'sessions_count'
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `sessions_count` int(11) DEFAULT NULL AFTER `capacity`");
    }
}

/**
 * ============================
 * Check and create tables if not exist
 * ============================
 */
function sc_check_and_create_tables() {
    // جلوگیری از اجرای مکرر در یک درخواست
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    
    global $wpdb;
    
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $member_courses_table = $wpdb->prefix . 'sc_member_courses';
    
    // بررسی وجود جداول
    $members_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $members_table)) == $members_table;
    $courses_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $courses_table)) == $courses_table;
    $member_courses_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $member_courses_table)) == $member_courses_table;
    
    // ایجاد جداول در صورت عدم وجود
    if (!$members_exists) {
        sc_create_members_table();
    }
    if (!$courses_exists) {
        sc_create_courses_table();
    }
    if (!$member_courses_exists) {
        sc_create_member_courses_table();
    }
}

// بررسی و ایجاد جداول در هر بار بارگذاری افزونه (فقط در پنل ادمین)
add_action('admin_init', 'sc_check_and_create_tables');

/**
 * ============================
 * Enqueue scripts and styles
 * ============================
 */
add_action('admin_enqueue_scripts', 'sc_admin_enqueue_assets');
add_action('wp_enqueue_scripts', 'sc_public_enqueue_assets');

/**
 * Enqueue admin CSS and JS
 */
function sc_admin_enqueue_assets() {
    wp_enqueue_style('sc-admin-css', SC_ASSETS_URL . 'css/admin.css', array(), '1.0');
    wp_enqueue_script('sc-admin-js', SC_ASSETS_URL . 'js/admin.js', array('jquery'), '1.0', true);
    // add media wp for photo and ....
        wp_enqueue_media();
}

/**
 * Enqueue public CSS and JS
 */
function sc_public_enqueue_assets() {
    wp_enqueue_style('sc-public-css', SC_ASSETS_URL . 'css/public.css', array(), '1.0');
    wp_enqueue_script('sc-public-js', SC_ASSETS_URL . 'js/public.js', array('jquery'), '1.0', true);
}
