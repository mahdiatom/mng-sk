<?php
/*
Plugin Name: SportClub Manager
Plugin URI:  https://example.com
Description: Sport club management plugin (members, courses, payments, attendance, etc.)
Version:     1.3.6
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
require_once SC_INCLUDES_DIR . 'course-packages-functions.php'; // پکیج‌های قیمت دوره
require_once SC_INCLUDES_DIR . 'course-schedule-functions.php'; // برنامه هفتگی کلاس دوره
require_once SC_INCLUDES_DIR . 'discount-codes-functions.php'; // کدهای تخفیف صورت‌حساب
require_once SC_INCLUDES_DIR . 'settings-functions.php';   // Settings functions
require_once SC_INCLUDES_DIR . 'recurring-invoices-functions.php'; // Recurring invoices functions
require_once SC_INCLUDES_DIR . 'excel-export-functions.php'; // Excel export functions
require_once SC_INCLUDES_DIR . 'users-info-export-functions.php'; // Users info export (PDF/Excel)
require_once SC_INCLUDES_DIR . 'bulk-actions-functions.php'; // Bulk actions on filtered members
require_once SC_INCLUDES_DIR . 'certificates-functions.php'; // Certificates templates and issue
require_once SC_INCLUDES_DIR . 'expense-export.php'; // Expense export functions
require_once SC_INCLUDES_DIR . 'debtors-export.php'; // Debtors export functions
require_once SC_INCLUDES_DIR . 'active-users-export.php'; // Active users export functions
require_once SC_INCLUDES_DIR . 'payments-export.php'; // Payments export functions
require_once SC_INCLUDES_DIR . 'course-users-export.php'; // Course users export functions
require_once SC_INCLUDES_DIR . 'woocommerce-settings.php'; // WooCommerce settings
require_once SC_INCLUDES_DIR . 'user-registration.php'; // User registration handler
require_once SC_INCLUDES_DIR . 'sms-functions.php'; // SMS functions
require_once SC_INCLUDES_DIR . 'notification-functions.php'; // Notification & SMS broadcast
require_once SC_INCLUDES_DIR . 'roles.php'; // Roles functions
require_once SC_INCLUDES_DIR . 'wallet-functions.php'; // Wallet functions
require_once SC_INCLUDES_DIR . 'coach-wallet-functions.php'; // Coach wallet functions
require_once SC_INCLUDES_DIR . 'coach-salary-cron.php'; // Coach salary cron jobs
require_once SC_INCLUDES_DIR . 'birthday-sms-cron.php'; // Birthday SMS daily cron
require_once SC_INCLUDES_DIR . 'insurance-expiry-sms-cron.php'; // Insurance expiry SMS daily cron
require_once SC_INCLUDES_DIR . 'support-ticket-functions.php'; // Support ticket CRUD, SMS, attachments
require_once SC_INCLUDES_DIR . 'private-notes-functions.php'; // Private notes CRUD, attachments
require_once SC_INCLUDES_DIR . 'activity-log-functions.php';   // Activity log (admin actions)
require_once SC_INCLUDES_DIR . 'login-register-functions.php'; // ورود و عضویت با پیامک و رمز
require_once SC_INCLUDES_DIR . 'redirect.php'; // ورود و عضویت با پیامک و رمز‌
require_once SC_INCLUDES_DIR . 'cleanup.php'; // حدف درخواست های خارجی  برای عملکرد بهتر‌
require_once SC_INCLUDES_DIR . 'attendance_logs.php'; // ارتباط با api حضور غیاب برای لاگ دستگاه
require_once SC_INCLUDES_DIR . 'attendance-auto.php'; // تطبیق لاگ دستگاه با حضور و غیاب (کرون)


include(SC_ADMIN_DIR . 'admin-menu.php');
// Include WooCommerce My Account integration
require_once SC_PUBLIC_DIR . 'my-account.php';
// Include WooCommerce Thank You Page customization
require_once SC_PUBLIC_DIR . 'woocommerce-thankyou.php';
//header footer
require_once SC_PUBLIC_DIR . 'header.php';
require_once SC_PUBLIC_DIR . 'footer.php';



/**
 * ============================
 * Activation & Deactivation Hooks
 * ============================
 */
register_activation_hook(__FILE__, 'sc_activate_plugin');
register_activation_hook( __FILE__, 'club_create_club_coach_role' );
register_activation_hook(__FILE__, 'sc_create_coach_role');
register_activation_hook(__FILE__, 'sc_update_database');
register_deactivation_hook(__FILE__, 'sc_clear_recurring_invoices_cron');


function sc_activate_plugin() {

    // بررسی و ایجاد جداول در صورت وجود تابع
    if (function_exists('sc_check_and_create_tables')) {
        sc_check_and_create_tables();
    } else {
        error_log('SportClub Manager: sc_check_and_create_tables function not found during activation');
    }

    // مقداردهی اولیه تنظیمات SMS
    if (function_exists('sc_initialize_sms_settings')) {
        sc_initialize_sms_settings();
    }

    // Flush rewrite rules فقط یک بار هنگام فعال‌سازی
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
 * Add invoice_description column to invoices table if not exists
 */
add_action('admin_init', 'sc_add_invoice_description_column');
function sc_add_invoice_description_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_invoices';
    $column_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'invoice_description'));
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `invoice_description` text DEFAULT NULL COMMENT 'توضیحات صورت حساب (ایجاد دستی)' AFTER `expense_name`");
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
 * Add last_reminder_sent column to invoices table if not exists
 */
add_action('admin_init', 'sc_add_last_reminder_sent_column');
function sc_add_last_reminder_sent_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_invoices';

    // بررسی وجود ستون last_reminder_sent
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'last_reminder_sent'
    ));

    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `last_reminder_sent` datetime DEFAULT NULL AFTER `payment_date`");
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
 * Add skill_level column to members table if not exists
 */
add_action('admin_init', 'sc_add_skill_level_column');
function sc_add_skill_level_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    
    // بررسی وجود ستون skill_level
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'skill_level'
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `skill_level` varchar(100) DEFAULT NULL AFTER `additional_info`");
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
 * Add identity_verified column to members table if not exists
 */
add_action('admin_init', 'sc_add_identity_verified_column');
function sc_add_identity_verified_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';

    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'identity_verified'
    ));

    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `identity_verified` tinyint(1) DEFAULT 0 AFTER `info_verified`");
    }
}

/**
 * Add province/city/gender columns to members table if not exists
 */
add_action('admin_init', 'sc_add_member_location_gender_columns');
function sc_add_member_location_gender_columns() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';

    $province_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'province'
    ));
    if (empty($province_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `province` varchar(100) DEFAULT NULL AFTER `landline_phone`");
    }

    $city_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'city'
    ));
    if (empty($city_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `city` varchar(100) DEFAULT NULL AFTER `province`");
    }

    $gender_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'gender'
    ));
    if (empty($gender_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `gender` varchar(10) DEFAULT NULL AFTER `city`");
    }
}

/**
 * Add restriction columns to courses/events tables if not exists
 */
add_action('admin_init', 'sc_add_course_event_restriction_columns');
function sc_add_course_event_restriction_columns() {
    global $wpdb;

    $courses_table = $wpdb->prefix . 'sc_courses';
    $events_table = $wpdb->prefix . 'sc_events';

    $targets = [
        $courses_table => ['chapter'],
        $events_table => ['event_location_lng'],
    ];

    foreach ($targets as $table_name => $after_map) {
        $restriction_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'restriction_enabled'));
        if (empty($restriction_exists)) {
            $after_col = $after_map[0];
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `restriction_enabled` tinyint(1) DEFAULT 0 AFTER `$after_col`");
        }

        $teams_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'allowed_teams'));
        if (empty($teams_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `allowed_teams` text DEFAULT NULL AFTER `restriction_enabled`");
        }

        $levels_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'allowed_levels'));
        if (empty($levels_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `allowed_levels` text DEFAULT NULL AFTER `allowed_teams`");
        }

        $gender_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'allowed_gender'));
        if (empty($gender_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN `allowed_gender` varchar(10) DEFAULT 'both' AFTER `allowed_levels`");
        }
    }
}

/**
 * Add holding_date columns to events table if not exists
 */
add_action('admin_init', 'sc_add_holding_date_columns');
function sc_add_holding_date_columns() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_events';
    
    // بررسی وجود ستون holding_date_shamsi
    $holding_date_shamsi_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'holding_date_shamsi'
    ));
    
    if (empty($holding_date_shamsi_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `holding_date_shamsi` varchar(10) DEFAULT NULL AFTER `end_date_gregorian`");
    }
    
    // بررسی وجود ستون holding_date_gregorian
    $holding_date_gregorian_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'holding_date_gregorian'
    ));
    
    if (empty($holding_date_gregorian_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `holding_date_gregorian` date DEFAULT NULL AFTER `holding_date_shamsi`");
    }
}

/**
 * Add event_type column to events table if not exists
 */
add_action('admin_init', 'sc_add_event_type_column');
function sc_add_event_type_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_events';
    
    // بررسی وجود ستون event_type
    $event_type_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'event_type'
    ));
    
    if (empty($event_type_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `event_type` varchar(20) DEFAULT 'event' AFTER `name`");
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
 * Add coach_id column to member_courses table if not exists
 */
add_action('admin_init', 'sc_add_coach_id_column_to_member_courses');
function sc_add_coach_id_column_to_member_courses() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_member_courses';
    
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'coach_id'
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `coach_id` bigint(20) unsigned DEFAULT NULL AFTER `course_id`");
        $wpdb->query("ALTER TABLE $table_name ADD KEY `idx_coach_id` (`coach_id`)");
    }
}

/**
 * ============================
 * Add price_per_session column to courses table if not exists
 * ============================
 */
add_action('admin_init', 'sc_add_price_per_session_column_to_courses');
function sc_add_price_per_session_column_to_courses() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_courses';
    
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'price_per_session'
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `price_per_session` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `price`");
    }
}

/**
 * ============================
 * Add user_id column to attendances table if not exists
 * ============================
 */
add_action('admin_init', 'sc_add_user_id_column_to_attendances');
function sc_add_user_id_column_to_attendances() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_attendances';
    
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        'user_id'
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN `user_id` bigint(20) unsigned DEFAULT NULL AFTER `status`");
        $wpdb->query("ALTER TABLE $table_name ADD KEY `idx_user_id` (`user_id`)");
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
 * Add absence_sms_sent column to attendances table if not exists
 */
if (!function_exists('sc_add_absence_sms_sent_column')) {
    add_action('admin_init', 'sc_add_absence_sms_sent_column');
    function sc_add_absence_sms_sent_column() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_attendances';

        // Check if column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $table_name LIKE %s",
            'absence_sms_sent'
        ));

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN absence_sms_sent TINYINT(1) DEFAULT 0");
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
        'additional_info',
        
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



//برای تکمیل ستون تعیین وضعیت تکمیل یا نافص بودن پروفایل
function sc_is_profile_completed($member_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';

    $member = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT 
                first_name,
                last_name,
                national_id,
                player_phone,
                birth_date_shamsi
             FROM $table
             WHERE id = %d",
            $member_id
        ),
        ARRAY_A
    );

    if (!$member) {
        return false;
    }

    foreach ($member as $value) {
        if (empty($value)) {
            return false;
        }
    }

    return true;
}

/**
 * ============================
 * Check and create tables if not exist
 * ============================
 */
function sc_check_and_create_tables() {
    // جلوگیری از اجرای مکرر در یک درخواست
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
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    $honor_categories_table = $wpdb->prefix . 'sc_honor_categories';
    $team_categories_table = $wpdb->prefix . 'sc_team_categories';
    $honors_table = $wpdb->prefix . 'sc_honors';
    $certificates_table = $wpdb->prefix . 'sc_certificates';
    $notifications_table = $wpdb->prefix . 'sc_notifications';
    $notification_recipients_table = $wpdb->prefix . 'sc_notification_recipients';
    $notification_reads_table = $wpdb->prefix . 'sc_notification_reads';
    $support_tickets_table = $wpdb->prefix . 'sc_support_tickets';
    $support_ticket_messages_table = $wpdb->prefix . 'sc_support_ticket_messages';
    $sms_log_table = $wpdb->prefix . 'sc_sms_log';
    $sms_log_entries_table = $wpdb->prefix . 'sc_sms_log_entries';
    $course_packages_table = $wpdb->prefix . 'sc_course_packages';
    $discount_codes_table = $wpdb->prefix . 'sc_discount_codes';
    $course_weekly_schedule_table = $wpdb->prefix . 'sc_course_weekly_schedule';
    
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
    $coaches_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $coaches_table)) == $coaches_table;
    $course_coaches_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $course_coaches_table)) == $course_coaches_table;
    $honor_categories_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $honor_categories_table)) == $honor_categories_table;
    $team_categories_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $team_categories_table)) == $team_categories_table;
    $honors_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $honors_table)) == $honors_table;
    $certificates_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $certificates_table)) == $certificates_table;
    $notifications_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $notifications_table)) == $notifications_table;
    $notification_recipients_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $notification_recipients_table)) == $notification_recipients_table;
    $notification_reads_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $notification_reads_table)) == $notification_reads_table;
    $support_tickets_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $support_tickets_table)) == $support_tickets_table;
    $support_ticket_messages_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $support_ticket_messages_table)) == $support_ticket_messages_table;
    $sms_log_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sms_log_table)) == $sms_log_table;
    $sms_log_entries_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $sms_log_entries_table)) == $sms_log_entries_table;
    $course_packages_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $course_packages_table)) == $course_packages_table;
    $discount_codes_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $discount_codes_table)) == $discount_codes_table;
    $course_weekly_schedule_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $course_weekly_schedule_table)) == $course_weekly_schedule_table;
    
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
    if (!$coaches_exists && function_exists('sc_create_coaches_table')) {
        sc_create_coaches_table();
    }
    if (!$course_coaches_exists && function_exists('sc_create_course_coaches_table')) {
        sc_create_course_coaches_table();
    }
    if (!$team_categories_exists && function_exists('sc_create_team_categories_table')) {
        sc_create_team_categories_table();
    }
    if (!$honor_categories_exists && function_exists('sc_create_honor_categories_table')) {
        sc_create_honor_categories_table();
    }
    if (!$honors_exists && function_exists('sc_create_honors_table')) {
        sc_create_honors_table();
    }
    if (!$certificates_exists && function_exists('sc_create_certificates_table')) {
        sc_create_certificates_table();
    }
    if (!$notifications_exists && function_exists('sc_create_notifications_table')) {
        sc_create_notifications_table();
    }
    if (!$notification_recipients_exists && function_exists('sc_create_notification_recipients_table')) {
        sc_create_notification_recipients_table();
    }
    if (!$notification_reads_exists && function_exists('sc_create_notification_reads_table')) {
        sc_create_notification_reads_table();
    }
    if (!$support_tickets_exists && function_exists('sc_create_support_tickets_table')) {
        sc_create_support_tickets_table();
    }
    if (!$support_ticket_messages_exists && function_exists('sc_create_support_ticket_messages_table')) {
        sc_create_support_ticket_messages_table();
    }
    if (!$sms_log_exists && function_exists('sc_create_sms_log_table')) {
        sc_create_sms_log_table();
    }
    if (!$sms_log_entries_exists && function_exists('sc_create_sms_log_entries_table')) {
        sc_create_sms_log_entries_table();
    }
    if (!$course_packages_exists && function_exists('sc_create_course_packages_table')) {
        sc_create_course_packages_table();
    }
    if (!$discount_codes_exists && function_exists('sc_create_discount_codes_tables')) {
        sc_create_discount_codes_tables();
    }
    if (!$course_weekly_schedule_exists && function_exists('sc_create_course_weekly_schedule_table')) {
        sc_create_course_weekly_schedule_table();
    }
    
    // اجرای به‌روزرسانی‌های دیتابیس
    if (function_exists('sc_update_database')) {
        sc_update_database();
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
        'sc_course_session_cancellations',
        'sc_course_weekly_schedule', // برنامه هفتگی کلاس (وابسته به دوره)
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
 * Auto-create member or coach when user registers
 * ============================
 */
// استفاده از set_user_role hook چون نقش در user_register ممکن است هنوز تنظیم نشده باشد
// همچنین از user_register با priority پایین استفاده می‌کنیم برای مواردی که نقش از قبل تنظیم شده
add_action('set_user_role', 'sc_auto_create_member_or_coach_on_role_set', 10, 3);
add_action('user_register', 'sc_auto_create_member_or_coach_on_user_register', 20, 1);

/**
 * وقتی نقش کاربر تنظیم می‌شود (hook اصلی)
 */
function sc_auto_create_member_or_coach_on_role_set($user_id, $role, $old_roles) {
    sc_process_user_registration($user_id);
}

/**
 * وقتی کاربر ثبت می‌شود (hook پشتیبان - برای مواردی که نقش از قبل تنظیم شده)
 */
function sc_auto_create_member_or_coach_on_user_register($user_id) {
    // کمی تاخیر برای اطمینان از تنظیم نقش
    // اما بهتر است از set_user_role استفاده کنیم
    sc_process_user_registration($user_id);
}

/**
 * پردازش ثبت کاربر بر اساس نقش
 */
function sc_process_user_registration($user_id) {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    
    // بررسی اینکه آیا این کاربر قبلاً در هر یک از جداول وجود دارد
    $existing_member = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $members_table WHERE user_id = %d LIMIT 1",
        $user_id
    ));
    
    $existing_coach = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
        $user_id
    ));
    
    // اگر در هر یک از جداول وجود داشت، خروج
    if ($existing_member || $existing_coach) {
        return;
    }
    
    // دریافت اطلاعات کاربر
    $user = get_userdata($user_id);
    if (!$user) {
        return;
    }
    
    // بررسی نقش کاربر
    // اگر نقش مربی است، به جدول مربیان اضافه می‌شود
    if (in_array('coach', $user->roles)) {
        sc_auto_create_coach_on_user_register($user_id);
        return;
    }
    
    // اگر نقش بازیکن (subscriber یا customer) است، به جدول اعضا اضافه می‌شود
    if (in_array('subscriber', $user->roles)) {
        sc_auto_create_member_on_user_register($user_id);
        return;
    }
    if (in_array('customer', $user->roles)) {
        sc_auto_create_member_on_user_register($user_id);
        return;
    }
}

/**
 * ============================
 * Auto-create member when user registers
 * ============================
 */
function sc_auto_create_member_on_user_register($user_id) {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    
    // بررسی اینکه آیا این کاربر قبلاً در جدول اعضا وجود دارد یا نه
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d",
        $user_id
    ));
    
    // اگر وجود داشت، خروج
    if ($existing) {
        return;
    }
    
    // بررسی اینکه آیا این کاربر در جدول مربیان وجود دارد یا نه
    // اگر وجود داشت، نباید به اعضا اضافه شود (user_id منحصر به فرد است)
    $existing_coach = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $coaches_table WHERE user_id = %d",
        $user_id
    ));
    
    if ($existing_coach) {
        return; // کاربر قبلاً به عنوان مربی ثبت شده است
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
        'identity_verified'    => 0,
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
 * Auto-create coach when user registers with coach role
 * ============================
 */
function sc_auto_create_coach_on_user_register($user_id) {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    global $wpdb;
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $members_table = $wpdb->prefix . 'sc_members';
    
    // بررسی اینکه آیا این کاربر قبلاً در جدول مربیان وجود دارد یا نه
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $coaches_table WHERE user_id = %d",
        $user_id
    ));
    
    // اگر وجود داشت، خروج
    if ($existing) {
        return;
    }
    
    // بررسی اینکه آیا این کاربر در جدول اعضا وجود دارد یا نه
    // اگر وجود داشت، نباید به مربیان اضافه شود (user_id منحصر به فرد است)
    $existing_member = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $members_table WHERE user_id = %d",
        $user_id
    ));
    
    if ($existing_member) {
        return; // کاربر قبلاً به عنوان بازیکن ثبت شده است
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
    $mobile_phone = get_user_meta($user_id, 'billing_phone', true);
    if (empty($mobile_phone)) {
        $mobile_phone = get_user_meta($user_id, 'phone', true);
    }
    
    // ایجاد کد ملی موقت (می‌تواند بعداً توسط کاربر یا مدیر تغییر کند)
    // استفاده از user_id به عنوان کد ملی موقت (محدود به 10 رقم)
    // فرمت: 8 + user_id (حداکثر 9 رقم) = 10 رقم (متفاوت از بازیکن‌ها که 9 استفاده می‌کنند)
    $temp_national_id = '8' . str_pad($user_id, 9, '0', STR_PAD_LEFT);
    
    // بررسی اینکه آیا این کد ملی موقت قبلاً استفاده شده یا نه
    $duplicate = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $coaches_table WHERE national_id = %s",
        $temp_national_id
    ));
    
    // اگر تکراری بود، از user_id + timestamp استفاده کن (آخرین 9 رقم)
    if ($duplicate) {
        $timestamp = time();
        $temp_national_id = '8' . str_pad(substr($timestamp, -9), 9, '0', STR_PAD_LEFT);
        
        // بررسی مجدد تکراری بودن
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $coaches_table WHERE national_id = %s",
            $temp_national_id
        ));
        
        // اگر باز هم تکراری بود، از ترکیب user_id و timestamp استفاده کن
        if ($duplicate) {
            $combined = ($user_id * 1000) + (substr($timestamp, -3));
            $temp_national_id = '8' . str_pad(substr($combined, -9), 9, '0', STR_PAD_LEFT);
        }
    }
    
    // آماده‌سازی داده‌ها
    $data = [
        'user_id'              => $user_id,
        'first_name'           => sanitize_text_field($first_name),
        'last_name'            => sanitize_text_field($last_name),
        'national_id'          => $temp_national_id,
        'mobile_phone'         => !empty($mobile_phone) ? sanitize_text_field($mobile_phone) : NULL,
        'is_active'            => 1, // به صورت پیش‌فرض فعال
        'created_at'           => current_time('mysql'),
        'updated_at'           => current_time('mysql'),
    ];
    
    // آماده‌سازی format array برای insert
    $format = [];
    foreach ($data as $key => $value) {
        if ($value === NULL) {
            $format[] = '%s'; // NULL
        } elseif (in_array($key, ['is_active', 'user_id'])) {
            $format[] = '%d'; // integer
        } else {
            $format[] = '%s'; // string
        }
    }
    
    // افزودن به جدول
    $inserted = $wpdb->insert($coaches_table, $data, $format);
    
    if ($inserted === false) {
        // لاگ خطا در صورت مشکل
        if ($wpdb->last_error) {
            error_log('SC Auto-create Coach Error: ' . $wpdb->last_error);
            error_log('SC Last Query: ' . $wpdb->last_query);
        }
    }
}

/**
 * ============================
 * حذف کاربر WordPress هنگام حذف مربی یا بازیکن
 * ============================
 */
function sc_delete_wp_user_by_table_id($table_name, $record_id) {
    global $wpdb;
    
    // دریافت user_id از جدول
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $table_name WHERE id = %d LIMIT 1",
        $record_id
    ));
    
    // اگر user_id وجود داشت و کاربر وجود دارد، حذف کن
    if ($user_id && get_userdata($user_id)) {
        // بررسی اینکه آیا این کاربر در جدول دیگر هم وجود دارد یا نه
        // اگر وجود داشت، نباید کاربر WordPress را حذف کنیم
        $members_table = $wpdb->prefix . 'sc_members';
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        
        $exists_in_members = false;
        $exists_in_coaches = false;
        
        if ($table_name == $members_table) {
            // اگر از جدول members حذف می‌شود، بررسی کن که در coaches وجود نداشته باشد
            $exists_in_coaches = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
                $user_id
            ));
        } elseif ($table_name == $coaches_table) {
            // اگر از جدول coaches حذف می‌شود، بررسی کن که در members وجود نداشته باشد
            $exists_in_members = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $members_table WHERE user_id = %d LIMIT 1",
                $user_id
            ));
        }
        
        // اگر در هیچ یک از جداول دیگر وجود نداشت، کاربر WordPress را حذف کن
        if (!$exists_in_members && !$exists_in_coaches) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            wp_delete_user($user_id);
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
    wp_enqueue_style('sc-admin-css', SC_ASSETS_URL . 'css/admin.css', array(),  time());
    $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    $is_ticket_new = is_admin() && isset($_GET['page']) && $_GET['page'] === 'sc-support-ticket-new';
    $is_ticket_view = is_admin() && isset($_GET['page']) && $_GET['page'] === 'sc-support-ticket-view';
    $is_coach_ticket_new = is_admin() && isset($_GET['page']) && $_GET['page'] === 'sc-coach-support-ticket-new';
    $is_coach_ticket_view = is_admin() && isset($_GET['page']) && $_GET['page'] === 'sc-coach-support-ticket-view';
    if (current_user_can('sc_view_coach_salary') || $is_ticket_new) {
        wp_enqueue_style('sc-coach-admin-css', SC_ASSETS_URL . 'css/coach-admin.css', array('sc-admin-css'), time());
    }
    $is_notification_add = is_admin() && isset($_GET['page']) && in_array($_GET['page'], array('sc-add-notification', 'sc-coach-add-notification'), true);
    if ($is_ticket_new || $is_ticket_view || $is_coach_ticket_new || $is_coach_ticket_view || $is_notification_add) {
        wp_enqueue_script('sc-ticket-attachments', SC_ASSETS_URL . 'js/ticket-attachments.js', array('jquery'), time(), true);
        wp_localize_script('sc-ticket-attachments', 'scTicketAttach', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
    // Enqueue media uploader (must be before admin.js)
    // همیشه media uploader را لود کن چون ممکن است در صفحات مختلف نیاز باشد
    wp_enqueue_media();
    
    // Enqueue admin.js with dependencies
    wp_enqueue_script('sc-admin-js', SC_ASSETS_URL . 'js/admin.js', array('jquery', 'media-upload', 'media-views'), time(), true);
    
    // Localize script for AJAX
    wp_localize_script('sc-admin-js', 'scAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sc_admin_nonce')
    ));

    if (in_array($current_page, array('sc-users-info-export', 'sc-users-export-templates', 'sc-certificates-issue', 'sc-certificates-templates', 'sc-certificates-list'), true)) {
        wp_enqueue_style('sc-users-export-admin-css', SC_ASSETS_URL . 'css/admin-users-export.css', array('sc-admin-css'), time());
        wp_enqueue_script('sc-users-export-admin-js', SC_ASSETS_URL . 'js/users-export-admin.js', array('jquery', 'sc-admin-js'), time(), true);
    }
    if ($current_page === 'sc-bulk-actions') {
        wp_enqueue_style('sc-users-export-admin-css', SC_ASSETS_URL . 'css/admin-users-export.css', array('sc-admin-css'), time());
        wp_enqueue_style('sc-bulk-actions-admin-css', SC_ASSETS_URL . 'css/admin-bulk-actions.css', array('sc-admin-css', 'sc-users-export-admin-css'), time());
        wp_enqueue_script('sc-bulk-actions-admin-js', SC_ASSETS_URL . 'js/bulk-actions-admin.js', array('jquery', 'sc-admin-js'), time(), true);
        wp_localize_script('sc-bulk-actions-admin-js', 'scBulkActions', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sc_bulk_actions_preview'),
            'maxPreviewRows' => 200,
        ));
    }
    if (in_array($current_page, array('sc-private-notes', 'sc-add-private-note', 'sc-coach-private-notes', 'sc-coach-add-private-note'), true)) {
        wp_enqueue_style('sc-private-notes-css', SC_ASSETS_URL . 'css/private-notes.css', array('sc-admin-css'), time());
        wp_enqueue_script('sc-ticket-attachments', SC_ASSETS_URL . 'js/ticket-attachments.js', array('jquery'), time(), true);
        wp_localize_script('sc-ticket-attachments', 'scTicketAttach', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
}

/**
 * Enqueue public CSS and JS
 */
function sc_public_enqueue_assets() {
    wp_enqueue_style('sc-public-css', SC_ASSETS_URL . 'css/public.css', array(),   time());
    wp_enqueue_style('my-custom-icons-css',SC_ASSETS_URL . 'css/all.css',array(),'1.0');

    wp_enqueue_script('sc-public-js', SC_ASSETS_URL . 'js/public.js', array('jquery'), '1.0', true);

    if (is_account_page() && get_query_var('sc-submit-documents') !== false) {
        wp_enqueue_script('sc-submit-documents-js', SC_ASSETS_URL . 'js/submit-documents.js', array('jquery'), time(), true);
        wp_localize_script('sc-submit-documents-js', 'scDocuments', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
    if (is_account_page()) {
        wp_enqueue_script('sc-ticket-attachments', SC_ASSETS_URL . 'js/ticket-attachments.js', array('jquery'), time(), true);
        wp_localize_script('sc-ticket-attachments', 'scTicketAttach', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
    if (is_account_page() && function_exists('wc_get_account_endpoint_url')) {
        wp_enqueue_script('sc-notifications-ajax', SC_ASSETS_URL . 'js/notifications-ajax.js', array('jquery'), time(), true);
        wp_localize_script('sc-notifications-ajax', 'scNotificationsAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'baseUrl' => wc_get_account_endpoint_url('sc-notifications'),
            'nonceMarkRead' => wp_create_nonce('sc_mark_notification_read'),
        ));
    }
    if (is_account_page() && get_query_var('sc-private-notes', false) !== false) {
        wp_enqueue_style('sc-private-notes-css', SC_ASSETS_URL . 'css/private-notes.css', array('sc-public-css'), time());
    }
}

//پنهان کردن تاپ منو برای نقش مدیر باشگاه
add_action( 'wp_enqueue_scripts', function () {

if ( ! current_user_can('club_coach') || current_user_can('administrator') ) {
        return;
    }

    wp_add_inline_style(
        'sc-public-css',
        '
         
        #wp-admin-bar-customize ,
        #wp-admin-bar-updates ,
        #wp-admin-bar-comments ,
        #wp-admin-bar-new-content ,
        #wp-admin-bar-elementor_inspector{
            display: none;
    }
          
         '
    );
});

add_action( 'wp_enqueue_scripts', function () {
    wp_add_inline_style(
        'theme-style-handle',
        '.tab-menu { background:red; }'
    );
});

/**
 * AJAX handler for wallet period report
 */
add_action('wp_ajax_sc_get_wallet_period_report', 'sc_ajax_get_wallet_period_report');
function sc_ajax_get_wallet_period_report() {
    check_ajax_referer('sc_wallet_period_report', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'لطفاً ابتدا وارد حساب کاربری خود شوید.']);
    }
    
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'monthly';
    $year = isset($_POST['year']) ? absint($_POST['year']) : date('Y');
    $month = isset($_POST['month']) ? absint($_POST['month']) : date('m');
    
    // بررسی دسترسی کاربر به این member_id
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $current_user_id = get_current_user_id();
    
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $members_table WHERE id = %d AND user_id = %d LIMIT 1",
        $member_id, $current_user_id
    ));
    
    if (!$member) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }
    
    $transactions = sc_get_wallet_transactions_by_period($member_id, $period, $year, $month);
    $report = sc_get_wallet_financial_report($member_id);
    
    ob_start();
    ?>
    <div style="margin-top: 20px;">
        <h4 style="margin-bottom: 15px;">گزارش <?php echo $period === 'monthly' ? 'ماهانه' : 'سالانه'; ?> - <?php echo $year; ?><?php echo $period === 'monthly' ? ' / ' . $month : ''; ?></h4>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 20px;">
            <div style="background: white; padding: 10px; border-radius: 4px; border-left: 3px solid #28a745;">
                <div style="font-size: 12px; color: #666;">تعداد تراکنش</div>
                <div style="font-size: 20px; font-weight: 700; color: #28a745;"><?php echo count($transactions); ?></div>
            </div>
        </div>
        
        <?php if (!empty($transactions)) : ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>نوع</th>
                        <th>مبلغ</th>
                        <th>موجودی بعد</th>
                        <th>توضیحات</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction) : ?>
                        <tr>
                            <td>
                                <?php
                                $type_labels = [
                                    'charge' => 'شارژ',
                                    'payment' => 'پرداخت',
                                    'deduct' => 'کاهش',
                                    'refund' => 'بازگشت'
                                ];
                                echo $type_labels[$transaction->transaction_type] ?? $transaction->transaction_type;
                                ?>
                            </td>
                            <td><?php echo number_format(floatval($transaction->amount), 0, '.', ','); ?> تومان</td>
                            <td><?php echo number_format(floatval($transaction->balance_after), 0, '.', ','); ?> تومان</td>
                            <td><?php echo esc_html($transaction->description ?: '-'); ?></td>
                            <td><?php echo esc_html(sc_date_shamsi($transaction->created_at, 'Y/m/d H:i')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p style="color: #666; margin-top: 15px;">تراکنشی در این دوره یافت نشد.</p>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
}

/**
 * AJAX: گزارش حضور بازیکن (ادمین)
 */
add_action('wp_ajax_sc_attendance_report_player', 'sc_ajax_attendance_report_player');
function sc_ajax_attendance_report_player() {
    check_ajax_referer('sc_attendance_report_player', 'nonce');

    if (!current_user_can('sc_manage_attendance') && !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $date_from_shamsi = isset($_POST['date_from_shamsi']) ? sanitize_text_field($_POST['date_from_shamsi']) : '';
    $date_to_shamsi = isset($_POST['date_to_shamsi']) ? sanitize_text_field($_POST['date_to_shamsi']) : '';

    if (!$member_id || !$date_from_shamsi || !$date_to_shamsi) {
        wp_send_json_error(['message' => 'کاربر و بازه تاریخ را مشخص کنید.']);
    }

    $date_from = sc_shamsi_to_gregorian_date($date_from_shamsi);
    $date_to = sc_shamsi_to_gregorian_date($date_to_shamsi);
    if (!$date_from || !$date_to) {
        wp_send_json_error(['message' => 'فرمت تاریخ شمسی نامعتبر است.']);
    }

    global $wpdb;
    $attendances_table = $wpdb->prefix . 'sc_attendances';
    $members_table = $wpdb->prefix . 'sc_members';
    $courses_table = $wpdb->prefix . 'sc_courses';

    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT id, first_name, last_name, national_id FROM $members_table WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!$member) {
        wp_send_json_error(['message' => 'کاربر یافت نشد.']);
    }

    $member_name = $member->first_name . ' ' . $member->last_name;

    // لیست حضور و غیاب در بازه
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id, a.course_id, a.attendance_date, a.status, c.title AS course_title
         FROM $attendances_table a
         LEFT JOIN $courses_table c ON c.id = a.course_id
         WHERE a.member_id = %d AND a.attendance_date >= %s AND a.attendance_date <= %s
         ORDER BY a.attendance_date DESC, c.title ASC",
        $member_id,
        $date_from,
        $date_to
    ));

    $total_present = 0;
    $total_absent = 0;
    $by_course = [];
    foreach ($rows as $r) {
        if ($r->status === 'present') {
            $total_present++;
        } else {
            $total_absent++;
        }
        $cid = (int) $r->course_id;
        if (!isset($by_course[$cid])) {
            $by_course[$cid] = ['title' => $r->course_title ?: '(بدون نام)', 'present' => 0, 'absent' => 0];
        }
        if ($r->status === 'present') {
            $by_course[$cid]['present']++;
        } else {
            $by_course[$cid]['absent']++;
        }
    }

    ob_start();
    ?>
    <div class="postbox" style="margin-top: 0;">     
        <div class="postbox-header">
            <h2 class=" filter_head_player">نتیجه گزارش</h2>
        </div>
        <div class="inside" style="padding: 20px;">
            <table class="form-table" style="margin-bottom: 20px;">
                <tr>
                    <th scope="row" style="width: 140px;">نام کاربر:</th>
                    <td><strong><?php echo esc_html($member_name); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">بازه تاریخی:</th>
                    <td><?php echo esc_html($date_from_shamsi); ?> تا <?php echo esc_html($date_to_shamsi); ?></td>
                </tr>
            </table>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 24px;">
                <div style="background: #f0f6fc; padding: 15px; border-radius: 6px; border-right: 4px solid #2271b1;">
                    <div style="font-size: 12px; color: #666;">تعداد کل جلسات حضور</div>
                    <div style="font-size: 24px; font-weight: 700; color: #2271b1;"><?php echo (int) $total_present; ?></div>
                </div>
                <div style="background: #fcf0f1; padding: 15px; border-radius: 6px; border-right: 4px solid #d63638;">
                    <div style="font-size: 12px; color: #666;">تعداد کل جلسات غیبت</div>
                    <div style="font-size: 24px; font-weight: 700; color: #d63638;"><?php echo (int) $total_absent; ?></div>
                </div>
            </div>

            <h3 style="margin: 20px 0 10px 0;">تعداد حضور در هر دوره</h3>
            <?php if (!empty($by_course)) : ?>
                <table class="wp-list-table widefat fixed striped" style="margin-bottom: 24px;">
                    <thead>
                        <tr>
                            <th>دوره</th>
                            <th style="width: 120px; text-align: center;">حضور</th>
                            <th style="width: 120px; text-align: center;">غیبت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($by_course as $course_id => $info) : ?>
                            <tr>
                                <td><?php echo esc_html($info['title']); ?></td>
                                <td style="text-align: center;"><?php echo (int) $info['present']; ?></td>
                                <td style="text-align: center;"><?php echo (int) $info['absent']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p style="color: #666;">در این بازه رکوردی ثبت نشده است.</p>
            <?php endif; ?>

            <h3 style="margin: 20px 0 10px 0;">لیست گزارش</h3>
            <?php if (!empty($rows)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 120px;">تاریخ</th>
                            <th>دوره</th>
                            <th style="width: 100px;">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) :
                            $status_label = $row->status === 'present' ? 'حاضر' : 'غایب';
                            $status_color = $row->status === 'present' ? '#00a32a' : '#d63638';
                        ?>
                            <tr>
                                <td><?php echo esc_html(sc_date_shamsi_date_only($row->attendance_date)); ?></td>
                                <td><?php echo esc_html($row->course_title ?: '-'); ?></td>
                                <td>
                                    <span style="color: <?php echo esc_attr($status_color); ?>; font-weight: 600;"><?php echo esc_html($status_label); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p style="color: #666;">رکوردی در این بازه یافت نشد.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);

}

add_action('woocommerce_blocks_enqueue_cart_block_scripts_before' , 'addwoocommerce_cart_is_empty_sc');
function addwoocommerce_cart_is_empty_sc(){
    $count = get_cart_item_count()['count'];
    
    if(is_page('cart') && $count == 0  ){
        include SC_TEMPLATES_PUBLIC_DIR . 'empty_cart.php';
    }
}

