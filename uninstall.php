<?php
// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * ============================
 * Delete all plugin tables and options
 * ============================
 */
global $wpdb;

// Prefix for plugin tables
$prefix = $wpdb->prefix . 'sc_';

// List of all plugin tables
$tables = [
    'members',              // جدول اعضا
    // 'courses',              // جدول دوره‌ها
    // 'member_courses',       // رابطه اعضا و دوره‌ها
    // 'invoices',             // فاکتورها / شهریه‌ها
    // 'attendances',          // حضور و غیاب
    // 'events',               // مسابقات و رویدادها
    // 'event_registrations',  // ثبت‌نام اعضا در مسابقات
    // 'tickets',              // تیکت‌ها
    // 'messages',             // پیام‌های تیکت
    // 'notifications'         // اطلاعیه‌ها
];

// Drop all plugin tables
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$prefix}{$table}");
}

// Delete plugin options in wp_options
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sc_%'");
