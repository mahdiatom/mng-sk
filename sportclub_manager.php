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
require_once SC_INCLUDES_DIR . 'jdf.php';                  // JDF library for Persian date conversion
require_once SC_INCLUDES_DIR . 'persian-datepicker-helper.php'; // Persian datepicker helper
require_once SC_INCLUDES_DIR . 'db-functions.php';          // Database table creation functions
require_once SC_INCLUDES_DIR . 'settings-functions.php';   // Settings functions
require_once SC_INCLUDES_DIR . 'recurring-invoices-functions.php'; // Recurring invoices functions
require_once SC_INCLUDES_DIR . 'excel-export-functions.php'; // Excel export functions
require_once SC_INCLUDES_DIR . 'expense-export.php'; // Expense export functions
require_once SC_INCLUDES_DIR . 'debtors-export.php'; // Debtors export functions
require_once SC_INCLUDES_DIR . 'active-users-export.php'; // Active users export functions
require_once SC_INCLUDES_DIR . 'payments-export.php'; // Payments export functions
require_once SC_INCLUDES_DIR . 'course-users-export.php'; // Course users export functions
include(SC_ADMIN_DIR . 'admin-menu.php');
// Include WooCommerce My Account integration
require_once SC_PUBLIC_DIR . 'my-account.php';

/**
 * ============================
 * Activation & Deactivation Hooks
 * ============================
 */
register_activation_hook(__FILE__, 'sc_activate_plugin');
register_deactivation_hook(__FILE__, 'sc_clear_recurring_invoices_cron');

function sc_activate_plugin() {
    // اطمینان از اینکه همه فایل‌های مورد نیاز لود شده‌اند
    if (!function_exists('sc_check_and_create_tables')) {
        // در صورت عدم وجود تابع، خطا را لاگ کن
        error_log('SportClub Manager: sc_check_and_create_tables function not found during activation');
        return;
    }
    
    try {
        // بررسی و ایجاد جداول در صورت عدم وجود
        sc_check_and_create_tables();
        
        // ثبت endpoint های My Account
        add_rewrite_endpoint('sc-submit-documents', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('sc-enroll-course', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('sc-my-courses', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('sc-invoices', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('sc-events', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('sc-event-detail', EP_ROOT | EP_PAGES);
        
        // Flush rewrite rules for WooCommerce endpoint
        flush_rewrite_rules();
    } catch (Exception $e) {
        error_log('SportClub Manager Activation Error: ' . $e->getMessage());
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('خطا در فعال‌سازی افزونه: ' . esc_html($e->getMessage()));
    }
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
 * Add expense_name column to invoices table if not exists
 */
add_action('admin_init', 'sc_add_expense_name_column');
function sc_add_expense_name_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_invoices';
    
    // بررسی وجود ستون expense_name
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'expense_name'
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `expense_name` varchar(255) DEFAULT NULL AFTER `amount`");
    }
}

/**
 * Add event_id column to invoices table if not exists
 */
add_action('admin_init', 'sc_add_event_id_column');
function sc_add_event_id_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_invoices';
    
    // بررسی وجود ستون event_id
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'event_id'
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `event_id` bigint(20) unsigned DEFAULT NULL AFTER `course_id`");
        $wpdb->query("ALTER TABLE $table_name ADD KEY `idx_event_id` (`event_id`)");
    }
}

/**
 * Add insurance_expiry_date column to members table if not exists
 */
add_action('admin_init', 'sc_add_insurance_expiry_date_column');
function sc_add_insurance_expiry_date_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    
    // بررسی وجود ستون insurance_expiry_date_shamsi
    $column_exists_shamsi = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'insurance_expiry_date_shamsi'
    ));
    
    if (empty($column_exists_shamsi)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `insurance_expiry_date_shamsi` varchar(10) DEFAULT NULL AFTER `sport_insurance_photo`");
    }
    
    // بررسی وجود ستون insurance_expiry_date_gregorian
    $column_exists_gregorian = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'insurance_expiry_date_gregorian'
    ));
    
    if (empty($column_exists_gregorian)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `insurance_expiry_date_gregorian` date DEFAULT NULL AFTER `insurance_expiry_date_shamsi`");
    }
}

/**
 * Add penalty columns to invoices table if not exists
 */
add_action('admin_init', 'sc_add_penalty_columns');
function sc_add_penalty_columns() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_invoices';
    
    // بررسی وجود ستون penalty_amount
    $penalty_amount_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'penalty_amount'
    ));
    
    if (empty($penalty_amount_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `penalty_amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `amount`");
    }
    
    // بررسی وجود ستون penalty_applied
    $penalty_applied_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'penalty_applied'
    ));
    
    if (empty($penalty_applied_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `penalty_applied` tinyint(1) DEFAULT 0 AFTER `penalty_amount`");
    }
}

/**
 * Add profile_completed column to members table if not exists
 */
add_action('admin_init', 'sc_add_profile_completed_column');
function sc_add_profile_completed_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    
    // بررسی وجود ستون profile_completed
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'profile_completed'
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `profile_completed` tinyint(1) DEFAULT 0 AFTER `is_active`");
    }
}

/**
 * Add course_status_flags column to member_courses table if not exists
 */
add_action('admin_init', 'sc_add_course_status_flags_column');
function sc_add_course_status_flags_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_member_courses';
    
    // بررسی وجود ستون course_status_flags
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'course_status_flags'
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `course_status_flags` varchar(255) DEFAULT NULL AFTER `status`");
    }
}

/**
 * Check and create attendances table if not exists
 */
if (!function_exists('sc_check_attendances_table')) {
    add_action('admin_init', 'sc_check_attendances_table');
    function sc_check_attendances_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_attendances';
        
        // بررسی وجود جدول
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if (!$table_exists) {
            sc_create_attendances_table();
        }
    }
}

/**
 * Check if member profile is completed
 * بررسی تمام فیلدها (به جز is_active) - همه باید پر باشند و boolean ها باید true باشند
 */
function sc_check_profile_completed($member_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $member_id
    ));
    
    if (!$member) {
        return false;
    }
    
    // فیلدهایی که باید بررسی شوند (به جز is_active و profile_completed و created_at و updated_at)
    $fields_to_check = [
        'first_name',
        'last_name',
        'father_name',
        'national_id',
        'player_phone',
        'father_phone',
        'mother_phone',
        'landline_phone',
        'birth_date_shamsi',
        'birth_date_gregorian',
        'personal_photo',
        'id_card_photo',
        'sport_insurance_photo',
        'medical_condition',
        'sports_history',
        'health_verified',
        'info_verified',
        'additional_info'
    ];
    
    // بررسی تمام فیلدها
    foreach ($fields_to_check as $field) {
        $value = $member->$field;
        
        // برای فیلدهای boolean (health_verified, info_verified) باید true باشند
        if ($field == 'health_verified' || $field == 'info_verified') {
            if ($value != 1 && $value !== '1' && $value !== true) {
                return false;
            }
        }
        // برای فیلدهای متنی و دیگر فیلدها باید خالی نباشند
        else {
            // بررسی اینکه آیا فیلد خالی است یا نه
            if (empty($value) || trim($value) === '') {
                return false;
            }
        }
    }
    
    // اگر همه فیلدها پر باشند و boolean ها true باشند
    return true;
}

/**
 * Update profile_completed status for a member
 */
function sc_update_profile_completed_status($member_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    
    $is_completed = sc_check_profile_completed($member_id) ? 1 : 0;
    
    $wpdb->update(
        $table_name,
        ['profile_completed' => $is_completed],
        ['id' => $member_id],
        ['%d'],
        ['%d']
    );
    
    return $is_completed;
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
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $settings_table = $wpdb->prefix . 'sc_settings';
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $expense_categories_table = $wpdb->prefix . 'sc_expense_categories';
    $expenses_table = $wpdb->prefix . 'sc_expenses';
    $events_table = $wpdb->prefix . 'sc_events';
    $event_fields_table = $wpdb->prefix . 'sc_event_fields';
    $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';
    
    // بررسی وجود جداول
    $members_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $members_table)) == $members_table;
    $courses_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $courses_table)) == $courses_table;
    $member_courses_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $member_courses_table)) == $member_courses_table;
    $invoices_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $invoices_table)) == $invoices_table;
    $settings_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $settings_table)) == $settings_table;
    $attendances_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $attendances_table)) == $attendances_table;
    $expense_categories_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $expense_categories_table)) == $expense_categories_table;
    $expenses_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $expenses_table)) == $expenses_table;
    $events_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $events_table)) == $events_table;
    $event_fields_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $event_fields_table)) == $event_fields_table;
    $event_registrations_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $event_registrations_table)) == $event_registrations_table;
    
    // ایجاد جداول در صورت عدم وجود
    if (!$members_exists && function_exists('sc_create_members_table')) {
        sc_create_members_table();
    }
    if (!$courses_exists && function_exists('sc_create_courses_table')) {
        sc_create_courses_table();
    }
    if (!$member_courses_exists && function_exists('sc_create_member_courses_table')) {
        sc_create_member_courses_table();
    }
    if (!$invoices_exists && function_exists('sc_create_invoices_table')) {
        sc_create_invoices_table();
    }
    if (!$settings_exists && function_exists('sc_create_settings_table')) {
        sc_create_settings_table();
    }
    if (!$attendances_exists && function_exists('sc_create_attendances_table')) {
        sc_create_attendances_table();
    }
    if (!$expense_categories_exists && function_exists('sc_create_expense_categories_table')) {
        sc_create_expense_categories_table();
    }
    if (!$expenses_exists && function_exists('sc_create_expenses_table')) {
        sc_create_expenses_table();
    }
    if (!$events_exists && function_exists('sc_create_events_table')) {
        sc_create_events_table();
    }
    if (!$event_fields_exists && function_exists('sc_create_event_fields_table')) {
        sc_create_event_fields_table();
    }
    if (!$event_registrations_exists && function_exists('sc_create_event_registrations_table')) {
        sc_create_event_registrations_table();
    }
}

// بررسی و ایجاد جداول در هر بار بارگذاری افزونه (فقط در پنل ادمین)
add_action('admin_init', 'sc_check_and_create_tables');

/**
 * ============================
 * Reset Factory Data Function
 * ============================
 */
function sc_reset_factory_data() {
    // بررسی دسترسی مدیر
    if (!current_user_can('manage_options')) {
        return ['success' => false, 'message' => 'شما دسترسی لازم را ندارید.'];
    }
    
    global $wpdb;
    
    $deleted_counts = [];
    $errors = [];
    
    // حذف داده‌های جداول (نه خود جداول)
    // ترتیب حذف: ابتدا جداول وابسته، سپس جداول اصلی
    $delete_order = [
        'sc_attendances',      // وابسته به members و courses
        'sc_member_courses',   // وابسته به members و courses
        'sc_invoices',         // وابسته به members و courses
        'sc_members',          // جدول اصلی
        'sc_courses',          // جدول اصلی
        'sc_settings'          // مستقل
    ];
    
    // حذف داده‌های جداول (نه خود جداول)
    foreach ($delete_order as $table_suffix) {
        $table_name = $wpdb->prefix . $table_suffix;
        
        // بررسی وجود جدول
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if ($table_exists == $table_name) {
            // شمارش رکوردها قبل از حذف
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            
            // حذف تمام داده‌های جدول
            $result = $wpdb->query("DELETE FROM $table_name");
            
            if ($result !== false) {
                $deleted_counts[$table_suffix] = $count;
                
                // بازنشانی AUTO_INCREMENT برای شروع مجدد از 1
                if ($count > 0) {
                    $wpdb->query("ALTER TABLE $table_name AUTO_INCREMENT = 1");
                }
            } else {
                $errors[] = "خطا در حذف داده‌های جدول: $table_suffix";
            }
        }
    }
    
    // پیام نتیجه
    if (empty($errors)) {
        $message = 'تمام اطلاعات با موفقیت حذف شد.';
        $details = [];
        
        foreach ($deleted_counts as $table => $count) {
            $table_label = [
                'sc_members' => 'عضو',
                'sc_courses' => 'دوره',
                'sc_member_courses' => 'ثبت‌نام',
                'sc_invoices' => 'صورت حساب',
                'sc_attendances' => 'حضور و غیاب',
                'sc_settings' => 'تنظیم'
            ];
            $label = isset($table_label[$table]) ? $table_label[$table] : $table;
            $details[] = "$count مورد از $label";
        }
        
        if (!empty($details)) {
            $message .= ' (' . implode('، ', $details) . ')';
        }
        
        return ['success' => true, 'message' => $message, 'counts' => $deleted_counts];
    } else {
        return ['success' => false, 'message' => 'برخی خطاها رخ داد: ' . implode('، ', $errors)];
    }
}

/**
 * ============================
 * Auto-create member when user registers
 * ============================
 */
add_action('user_register', 'sc_auto_create_member_on_user_register');
function sc_auto_create_member_on_user_register($user_id) {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    
    // بررسی اینکه آیا این کاربر قبلاً در جدول اعضا وجود دارد یا نه
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d",
        $user_id
    ));
    
    // اگر وجود داشت، خروج
    if ($existing) {
        return;
    }
    
    // دریافت اطلاعات کاربر
    $user = get_userdata($user_id);
    if (!$user) {
        return;
    }
    
    // تقسیم نام نمایشی به نام و نام خانوادگی
    $display_name = $user->display_name;
    $name_parts = explode(' ', $display_name, 2);
    $first_name = !empty($name_parts[0]) ? $name_parts[0] : $user->user_login;
    $last_name = !empty($name_parts[1]) ? $name_parts[1] : '';
    
    // اگر نام خانوادگی خالی بود، از user_login استفاده کن
    if (empty($last_name)) {
        $last_name = $user->user_login;
    }
    
    // دریافت شماره تماس از user meta (اگر وجود داشته باشد)
    $player_phone = get_user_meta($user_id, 'billing_phone', true);
    if (empty($player_phone)) {
        $player_phone = get_user_meta($user_id, 'phone', true);
    }
    
    // ایجاد کد ملی موقت (می‌تواند بعداً توسط کاربر یا مدیر تغییر کند)
    // استفاده از user_id به عنوان کد ملی موقت (محدود به 10 رقم)
    // فرمت: 9 + user_id (حداکثر 9 رقم) = 10 رقم
    $temp_national_id = '9' . str_pad($user_id, 9, '0', STR_PAD_LEFT);
    
    // بررسی اینکه آیا این کد ملی موقت قبلاً استفاده شده یا نه
    $duplicate = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE national_id = %s",
        $temp_national_id
    ));
    
    // اگر تکراری بود، از user_id + timestamp استفاده کن (آخرین 9 رقم)
    if ($duplicate) {
        $timestamp = time();
        $temp_national_id = '9' . str_pad(substr($timestamp, -9), 9, '0', STR_PAD_LEFT);
        
        // بررسی مجدد تکراری بودن
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE national_id = %s",
            $temp_national_id
        ));
        
        // اگر باز هم تکراری بود، از ترکیب user_id و timestamp استفاده کن
        if ($duplicate) {
            $combined = ($user_id * 1000) + (substr($timestamp, -3));
            $temp_national_id = '9' . str_pad(substr($combined, -9), 9, '0', STR_PAD_LEFT);
        }
    }
    
    // آماده‌سازی داده‌ها
    $data = [
        'user_id'              => $user_id,
        'first_name'           => sanitize_text_field($first_name),
        'last_name'            => sanitize_text_field($last_name),
        'national_id'          => $temp_national_id,
        'player_phone'         => !empty($player_phone) ? sanitize_text_field($player_phone) : NULL,
        'health_verified'      => 0,
        'info_verified'        => 0,
        'is_active'            => 1, // به صورت پیش‌فرض فعال
        'created_at'           => current_time('mysql'),
        'updated_at'           => current_time('mysql'),
    ];
    
    // افزودن به جدول
    $inserted = $wpdb->insert($table_name, $data);
    
    if ($inserted === false) {
        // لاگ خطا در صورت مشکل
        if ($wpdb->last_error) {
            error_log('SC Auto-create Member Error: ' . $wpdb->last_error);
            error_log('SC Last Query: ' . $wpdb->last_query);
        }
    }
}

/**
 * ============================
 * Auto-update member when user profile is updated
 * ============================
 */
add_action('profile_update', 'sc_auto_update_member_on_profile_update', 10, 2);
function sc_auto_update_member_on_profile_update($user_id, $old_user_data = null) {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    
    // دریافت اطلاعات کاربر
    $user = get_userdata($user_id);
    if (!$user) {
        return;
    }
    
    // بررسی اینکه آیا این کاربر در جدول اعضا وجود دارد یا نه
    $existing_member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d",
        $user_id
    ));
    
    // اگر وجود نداشت، ایجاد کن (ممکن است کاربر قبلاً حذف شده باشد)
    if (!$existing_member) {
        sc_auto_create_member_on_user_register($user_id);
        return;
    }
    
    // دریافت اطلاعات جدید از user meta
    $first_name_meta = get_user_meta($user_id, 'first_name', true);
    $last_name_meta = get_user_meta($user_id, 'last_name', true);
    $billing_phone = get_user_meta($user_id, 'billing_phone', true);
    $phone = get_user_meta($user_id, 'phone', true);
    
    // تقسیم نام نمایشی به نام و نام خانوادگی (اگر user meta وجود نداشت)
    $display_name = $user->display_name;
    $name_parts = explode(' ', $display_name, 2);
    $first_name = !empty($first_name_meta) ? $first_name_meta : (!empty($name_parts[0]) ? $name_parts[0] : $user->user_login);
    $last_name = !empty($last_name_meta) ? $last_name_meta : (!empty($name_parts[1]) ? $name_parts[1] : $user->user_login);
    
    // دریافت شماره تماس
    $player_phone = !empty($billing_phone) ? $billing_phone : $phone;
    
    // آماده‌سازی داده‌های به‌روزرسانی
    $update_data = [
        'first_name'   => sanitize_text_field($first_name),
        'last_name'    => sanitize_text_field($last_name),
        'updated_at'   => current_time('mysql'),
    ];
    
    // آماده‌سازی format برای update
    $format = ['%s', '%s', '%s'];
    
    // به‌روزرسانی شماره تماس فقط اگر تغییر کرده باشد
    if (!empty($player_phone)) {
        $update_data['player_phone'] = sanitize_text_field($player_phone);
        $format[] = '%s';
    }
    
    // به‌روزرسانی کد ملی موقت فقط اگر هنوز موقت است (شروع با 9)
    // اگر کاربر قبلاً کد ملی واقعی وارد کرده، تغییر نمی‌کنیم
    if (preg_match('/^9\d{9}$/', $existing_member->national_id)) {
        // کد ملی هنوز موقت است، می‌توانیم به‌روزرسانی کنیم (اما فعلاً همان را نگه می‌داریم)
        // اگر می‌خواهید کد ملی موقت را هم به‌روزرسانی کنید، این بخش را فعال کنید
        // $update_data['national_id'] = '9' . str_pad($user_id, 9, '0', STR_PAD_LEFT);
        // $format[] = '%s';
    }
    
    // به‌روزرسانی در جدول
    $updated = $wpdb->update(
        $table_name,
        $update_data,
        ['user_id' => $user_id],
        $format,
        ['%d']
    );
    
    if ($updated === false && $wpdb->last_error) {
        // لاگ خطا در صورت مشکل
        error_log('SC Auto-update Member Error: ' . $wpdb->last_error);
        error_log('SC Last Query: ' . $wpdb->last_query);
    }
}

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
    
    // Enqueue media uploader (must be before admin.js)
    // همیشه media uploader را لود کن چون ممکن است در صفحات مختلف نیاز باشد
    wp_enqueue_media();
    
    // Enqueue admin.js with dependencies
    wp_enqueue_script('sc-admin-js', SC_ASSETS_URL . 'js/admin.js', array('jquery', 'media-upload', 'media-views'), '1.0', true);
    
    // Localize script for AJAX
    wp_localize_script('sc-admin-js', 'scAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sc_admin_nonce')
    ));
}

/**
 * Enqueue public CSS and JS
 */
function sc_public_enqueue_assets() {
    wp_enqueue_style('sc-public-css', SC_ASSETS_URL . 'css/public.css', array(), '1.0');
    wp_enqueue_script('sc-public-js', SC_ASSETS_URL . 'js/public.js', array('jquery'), '1.0', true);
}
