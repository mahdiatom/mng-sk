<?php 
if (!defined('SC_PLUGIN_VERSION')) {
    define('SC_PLUGIN_VERSION', '1.25.0'); // همان نسخه افزونه هدر
}

    /**
 * create sc_settings
 */

function sc_create_settings_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_settings';
    $table_collation = $wpdb->get_charset_collate();


$sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text DEFAULT NULL,
        `setting_group` varchar(50) DEFAULT 'general',
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_setting_key` (`setting_key`),
        KEY `idx_setting_group` (`setting_group`)
    ) ENGINE=InnoDB $table_collation";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
    }
    /**
 * create sc_invoices
 */

function sc_create_invoices_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_invoices';
    $table_collation = $wpdb->get_charset_collate();


$sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `member_id` bigint(20) unsigned NOT NULL,
        `course_id` bigint(20) unsigned NOT NULL,
        `event_id` bigint(20) unsigned DEFAULT NULL,
        `member_course_id` bigint(20) unsigned DEFAULT NULL,
        `woocommerce_order_id` bigint(20) unsigned DEFAULT NULL,
        `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `expense_name` varchar(255) DEFAULT NULL,
        `invoice_description` text DEFAULT NULL COMMENT 'توضیحات صورت حساب (ایجاد دستی)',
        `penalty_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `penalty_applied` tinyint(1) DEFAULT 0,
        `disable_penalty` tinyint(1) NOT NULL DEFAULT 0,
        `status` varchar(20) DEFAULT 'pending',
        `payment_date` datetime DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_member_id` (`member_id`),
        KEY `idx_course_id` (`course_id`),
        KEY `idx_member_course_id` (`member_course_id`),
        KEY `idx_woocommerce_order_id` (`woocommerce_order_id`),
        KEY `idx_status` (`status`),
        KEY `idx_event_id` (`event_id`)
    ) ENGINE=InnoDB $table_collation";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
    }





    
    /**
 * create_create_courses_table
 */

function sc_create_courses_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_courses';
    $table_collation = $wpdb->get_charset_collate();


$sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `price` decimal(10,2) NOT NULL DEFAULT 0.00,
        `price_per_session` decimal(10,2) NOT NULL DEFAULT 0.00,
        `capacity` int(11) DEFAULT NULL,
        `sessions_count` int(11) DEFAULT NULL,
        `start_date` date DEFAULT NULL,
        `end_date` date DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `deleted_at` datetime DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_is_active` (`is_active`),
        KEY `idx_deleted_at` (`deleted_at`)
    ) ENGINE=InnoDB $table_collation";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
    }

    /**
 * create_sc_member_courses
 */

function sc_create_member_courses_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_member_courses';
    $table_collation = $wpdb->get_charset_collate();


$sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `member_id` bigint(20) unsigned NOT NULL,
        `course_id` bigint(20) unsigned NOT NULL,
        `enrollment_date` date DEFAULT NULL,
        `status` varchar(20) DEFAULT 'active',
        `course_status_flags` varchar(255) DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_member_course` (`member_id`,`course_id`),
        KEY `idx_member_id` (`member_id`),
        KEY `idx_course_id` (`course_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB $table_collation";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
    }

    /**
 * create_attendances
 */

function sc_create_attendances_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_attendances';
    $table_collation = $wpdb->get_charset_collate();


$sql = "CREATE TABLE `$table_name` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `member_id` bigint(20) unsigned NOT NULL,
    `course_id` bigint(20) unsigned NOT NULL,
    `attendance_date` date NOT NULL,
    `status` enum('present','absent') NOT NULL DEFAULT 'present',
    `user_id` bigint(20) unsigned DEFAULT NULL,
    `absence_sms_sent` tinyint(1) DEFAULT 0,
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_member_course_date` (`member_id`,`course_id`,`attendance_date`),
    KEY `idx_member_id` (`member_id`),
    KEY `idx_course_id` (`course_id`),
    KEY `idx_attendance_date` (`attendance_date`),
    KEY `idx_status` (`status`),
    KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB $table_collation";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
    }
    /**
 * Create members_table
 */
function sc_create_members_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    $table_collation = $wpdb->get_charset_collate();


        $sql = "CREATE TABLE `$table_name` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `first_name` varchar(50) NOT NULL,
            `last_name` varchar(50) NOT NULL,
            `father_name` varchar(50) DEFAULT NULL,
            `national_id` char(10) NOT NULL,
            `player_phone` varchar(15) DEFAULT NULL,
            `father_phone` varchar(15) DEFAULT NULL,
            `mother_phone` varchar(15) DEFAULT NULL,
            `landline_phone` varchar(15) DEFAULT NULL,
            `birth_date_shamsi` varchar(10) DEFAULT NULL,
            `birth_date_gregorian` date DEFAULT NULL,
            `personal_photo` varchar(255) DEFAULT NULL,
            `id_card_photo` varchar(255) DEFAULT NULL,
            `sport_insurance_photo` varchar(255) DEFAULT NULL,
            `medical_condition` text,
            `sports_history` text,
            `health_verified` tinyint(1) DEFAULT 0,
            `info_verified` tinyint(1) DEFAULT 0,
            `is_active` tinyint(1) DEFAULT 1,
            `disable_auto_invoice` tinyint(1) DEFAULT 0,
            `member_type` varchar(20) NOT NULL DEFAULT 'normal' COMMENT 'normal=بازیکن عادی, team=بازیکن تیم',
            `profile_completed` TINYINT(1) NOT NULL DEFAULT 0,
            `additional_info` text,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_national_id` (`national_id`),
            KEY `idx_last_name` (`last_name`),
            KEY `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB $table_collation";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

/**
 * Check if member is team player (کسر از کیف پول، بدون صورت حساب)
 */
function sc_is_member_team($member_id) {
    if (!$member_id) {
        return false;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $type = $wpdb->get_var($wpdb->prepare(
        "SELECT member_type FROM $table WHERE id = %d LIMIT 1",
        $member_id
    ));
    return ($type === 'team');
}

/**
 * Create expense categories table
 */
function sc_create_expense_categories_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_expense_categories';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `description` text DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_name` (`name`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create expenses table
 */
function sc_create_expenses_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_expenses';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `category_id` bigint(20) unsigned DEFAULT NULL,
        `expense_date_shamsi` varchar(10) DEFAULT NULL,
        `expense_date_gregorian` date DEFAULT NULL,
        `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `description` text DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_category_id` (`category_id`),
        KEY `idx_expense_date_gregorian` (`expense_date_gregorian`),
        KEY `idx_created_at` (`created_at`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create events table
 */
function sc_create_events_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_events';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `event_type` varchar(20) DEFAULT 'event',
        `description` text DEFAULT NULL,
        `price` decimal(10,2) NOT NULL DEFAULT 0.00,
        `start_date_shamsi` varchar(10) DEFAULT NULL,
        `start_date_gregorian` date DEFAULT NULL,
        `end_date_shamsi` varchar(10) DEFAULT NULL,
        `end_date_gregorian` date DEFAULT NULL,
        `holding_date_shamsi` varchar(10) DEFAULT NULL,
        `holding_date_gregorian` date DEFAULT NULL,
        `image` varchar(255) DEFAULT NULL,
        `has_age_limit` tinyint(1) DEFAULT 0,
        `min_age` int(11) DEFAULT NULL,
        `max_age` int(11) DEFAULT NULL,
        `capacity` int(11) DEFAULT NULL,
        `event_time` text DEFAULT NULL,
        `event_location` varchar(255) DEFAULT NULL,
        `event_location_address` text DEFAULT NULL,
        `event_location_lat` decimal(10,8) DEFAULT NULL,
        `event_location_lng` decimal(11,8) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `deleted_at` datetime DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_is_active` (`is_active`),
        KEY `idx_deleted_at` (`deleted_at`),
        KEY `idx_start_date_gregorian` (`start_date_gregorian`),
        KEY `idx_end_date_gregorian` (`end_date_gregorian`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create event fields table
 * این جدول فیلدهای سفارشی هر رویداد را ذخیره می‌کند
 */
function sc_create_event_fields_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_event_fields';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `event_id` bigint(20) unsigned NOT NULL,
        `field_name` varchar(255) NOT NULL,
        `field_type` varchar(50) NOT NULL,
        `field_options` text DEFAULT NULL,
        `is_required` tinyint(1) DEFAULT 0,
        `field_order` int(11) DEFAULT 0,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_event_id` (`event_id`),
        KEY `idx_field_order` (`field_order`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create event registrations table
 * این جدول اطلاعات ثبت‌نام کاربران در رویدادها را ذخیره می‌کند
 */
function sc_create_event_registrations_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_event_registrations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `event_id` bigint(20) unsigned NOT NULL,
        `member_id` bigint(20) unsigned NOT NULL,
        `invoice_id` bigint(20) unsigned DEFAULT NULL,
        `field_data` longtext DEFAULT NULL,
        `files` longtext DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_event_id` (`event_id`),
        KEY `idx_member_id` (`member_id`),
        KEY `idx_invoice_id` (`invoice_id`),
        UNIQUE KEY `idx_event_member` (`event_id`, `member_id`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create coaches table
 */
function sc_create_coaches_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_coaches';
    $table_collation = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) unsigned DEFAULT NULL,
        `first_name` varchar(50) NOT NULL,
        `last_name` varchar(50) NOT NULL,
        `national_id` char(10) NOT NULL,
        `mobile_phone` varchar(15) DEFAULT NULL,
        `gender` varchar(10) DEFAULT NULL,
        `specialization` varchar(255) DEFAULT NULL,
        `coaching_level` varchar(100) DEFAULT NULL,
        `coaching_experience` int(11) DEFAULT NULL,
        `sports_history` text DEFAULT NULL,
        `settlement_type` varchar(20) DEFAULT 'fixed',
        `settlement_amount` decimal(10,2) DEFAULT 0.00,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_national_id` (`national_id`),
        UNIQUE KEY `idx_user_id` (`user_id`),
        KEY `idx_is_active` (`is_active`),
        KEY `idx_last_name` (`last_name`)
    ) ENGINE=InnoDB $table_collation";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create course coaches table (ارتباط دوره و مربی)
 */
function sc_create_course_coaches_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_course_coaches';
    $table_collation = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `course_id` bigint(20) unsigned NOT NULL,
        `coach_id` bigint(20) unsigned NOT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_course_coach` (`course_id`, `coach_id`),
        KEY `idx_course_id` (`course_id`),
        KEY `idx_coach_id` (`coach_id`)
    ) ENGINE=InnoDB $table_collation";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create wallet_transactions table
 */
function sc_create_wallet_transactions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_wallet_transactions';
    $table_collation = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) unsigned NOT NULL COMMENT 'شناسه کاربر WordPress',
        `member_id` bigint(20) unsigned NOT NULL COMMENT 'شناسه بازیکن در sc_members',
        `transaction_type` enum('charge','deduct','payment','refund','session_fee') NOT NULL COMMENT 'نوع تراکنش: charge=شارژ, deduct=کاهش دستی, payment=پرداخت, refund=بازگشت, session_fee=کسر جلسه حضور',
        `amount` decimal(10,2) NOT NULL COMMENT 'مبلغ تراکنش (همیشه مثبت)',
        `balance_before` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'موجودی قبل از تراکنش',
        `balance_after` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'موجودی بعد از تراکنش',
        `description` text DEFAULT NULL COMMENT 'توضیحات تراکنش',
        `related_invoice_id` bigint(20) unsigned DEFAULT NULL COMMENT 'شناسه صورت حساب مرتبط (اگر مربوط به پرداخت باشد)',
        `related_order_id` bigint(20) unsigned DEFAULT NULL COMMENT 'شناسه سفارش WooCommerce مرتبط',
        `created_by` bigint(20) unsigned NOT NULL COMMENT 'شناسه کاربری که تراکنش را ایجاد کرده',
        `status` enum('completed','pending','failed','cancelled') NOT NULL DEFAULT 'completed' COMMENT 'وضعیت تراکنش',
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_member_id` (`member_id`),
        KEY `idx_transaction_type` (`transaction_type`),
        KEY `idx_status` (`status`),
        KEY `idx_related_invoice` (`related_invoice_id`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB $table_collation";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create coach wallet transactions table
 */
function sc_create_coach_wallet_transactions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_coach_wallet_transactions';
    $table_collation = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `coach_id` bigint(20) unsigned NOT NULL COMMENT 'شناسه مربی در sc_coaches',
        `transaction_type` enum('salary_percentage','salary_fixed','charge','deduct','withdrawal') NOT NULL COMMENT 'نوع تراکنش: salary_percentage=دستمزد درصدی, salary_fixed=دستمزد ثابت, charge=شارژ, deduct=کاهش دستی, withdrawal=برداشت',
        `amount` decimal(10,2) NOT NULL COMMENT 'مبلغ تراکنش (همیشه مثبت)',
        `balance_before` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'موجودی قبل از تراکنش',
        `balance_after` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'موجودی بعد از تراکنش',
        `description` text DEFAULT NULL COMMENT 'توضیحات تراکنش',
        `related_course_id` bigint(20) unsigned DEFAULT NULL COMMENT 'شناسه دوره مرتبط (برای دستمزد درصدی)',
        `related_attendance_date` date DEFAULT NULL COMMENT 'تاریخ حضور مرتبط (برای دستمزد درصدی)',
        `related_salary_record_id` bigint(20) unsigned DEFAULT NULL COMMENT 'شناسه رکورد دستمزد مرتبط',
        `created_by` bigint(20) unsigned NOT NULL COMMENT 'شناسه کاربری که تراکنش را ایجاد کرده',
        `status` enum('completed','pending','failed','cancelled') NOT NULL DEFAULT 'completed' COMMENT 'وضعیت تراکنش',
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_coach_id` (`coach_id`),
        KEY `idx_transaction_type` (`transaction_type`),
        KEY `idx_status` (`status`),
        KEY `idx_related_course` (`related_course_id`),
        KEY `idx_related_attendance_date` (`related_attendance_date`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB $table_collation";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create coach salary records table
 */
function sc_create_coach_salary_records_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_coach_salary_records';
    $table_collation = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `coach_id` bigint(20) unsigned NOT NULL COMMENT 'شناسه مربی',
        `course_id` bigint(20) unsigned NOT NULL COMMENT 'شناسه دوره',
        `attendance_date` date NOT NULL COMMENT 'تاریخ حضور',
        `attendance_count` int(11) NOT NULL DEFAULT 0 COMMENT 'تعداد شرکت کنندگان',
        `price_per_session` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'قیمت هر جلسه',
        `total_revenue` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'کل درآمد (تعداد × قیمت)',
        `salary_percentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'درصد دستمزد',
        `salary_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'مبلغ دستمزد',
        `salary_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage' COMMENT 'نوع دستمزد',
        `wallet_transaction_id` bigint(20) unsigned DEFAULT NULL COMMENT 'شناسه تراکنش کیف پول',
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_coach_course_date` (`coach_id`, `course_id`, `attendance_date`),
        KEY `idx_coach_id` (`coach_id`),
        KEY `idx_course_id` (`course_id`),
        KEY `idx_attendance_date` (`attendance_date`),
        KEY `idx_salary_type` (`salary_type`)
    ) ENGINE=InnoDB $table_collation";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create coach withdrawal requests table
 */
function sc_create_coach_withdrawal_requests_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_coach_withdrawal_requests';
    $table_collation = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `coach_id` bigint(20) unsigned NOT NULL COMMENT 'شناسه مربی',
        `amount` decimal(10,2) NOT NULL COMMENT 'مبلغ درخواست',
        `balance_before` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'موجودی قبل از درخواست',
        `status` enum('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending' COMMENT 'وضعیت: pending=در انتظار تایید, approved=تایید شده, rejected=رد شده, paid=پرداخت شده',
        `rejection_reason` text DEFAULT NULL COMMENT 'دلیل رد (در صورت رد)',
        `approved_by` bigint(20) unsigned DEFAULT NULL COMMENT 'شناسه کاربری که تایید کرده',
        `paid_by` bigint(20) unsigned DEFAULT NULL COMMENT 'شناسه کاربری که پرداخت کرده',
        `paid_at` datetime DEFAULT NULL COMMENT 'تاریخ پرداخت',
        `wallet_transaction_id` bigint(20) unsigned DEFAULT NULL COMMENT 'شناسه تراکنش کیف پول (بعد از تایید)',
        `notes` text DEFAULT NULL COMMENT 'یادداشت‌ها',
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_coach_id` (`coach_id`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB $table_collation";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create honor categories table
 */
function sc_create_honor_categories_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_honor_categories';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_name` (`name`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create notifications table
 */
function sc_create_notifications_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_notifications';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `content` text NOT NULL,
        `target_type` varchar(20) NOT NULL COMMENT 'all/specific/course',
        `target_config` longtext DEFAULT NULL COMMENT 'JSON: user_type, course_ids, recipient_ids, etc.',
        `send_sms` tinyint(1) NOT NULL DEFAULT 0,
        `created_by` bigint(20) unsigned DEFAULT NULL,
        `created_by_type` varchar(20) NOT NULL DEFAULT 'admin' COMMENT 'admin=مدیر, coach=مربی',
        `created_by_entity_id` bigint(20) unsigned DEFAULT 0 COMMENT 'coach_id if coach, 0 if admin',
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_target_type` (`target_type`),
        KEY `idx_created_at` (`created_at`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create notification recipients table
 */
function sc_create_notification_recipients_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_notification_recipients';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `notification_id` bigint(20) unsigned NOT NULL,
        `user_id` bigint(20) unsigned NOT NULL COMMENT 'WordPress user_id',
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_notification_user` (`notification_id`, `user_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_notification_id` (`notification_id`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create notification reads table
 */
function sc_create_notification_reads_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_notification_reads';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `notification_id` bigint(20) unsigned NOT NULL,
        `user_id` bigint(20) unsigned NOT NULL,
        `read_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_notification_user` (`notification_id`, `user_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_notification_id` (`notification_id`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create honors table
 */
function sc_create_honors_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_honors';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `member_id` bigint(20) unsigned DEFAULT NULL,
        `coach_id` bigint(20) unsigned DEFAULT NULL,
        `name` varchar(255) NOT NULL,
        `category_id` bigint(20) unsigned NOT NULL,
        `description` text DEFAULT NULL,
        `file_url` varchar(500) DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_member_id` (`member_id`),
        KEY `idx_coach_id` (`coach_id`),
        KEY `idx_category_id` (`category_id`),
        KEY `idx_created_at` (`created_at`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create support tickets table (تیکت پشتیبانی)
 */
function sc_create_support_tickets_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_support_tickets';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) unsigned DEFAULT 0 COMMENT 'کاربر عضو مرتبط (گیرنده یا فرستنده)؛ 0 وقتی تیکت فقط بین مربی/مدیر است',
        `department` varchar(20) NOT NULL DEFAULT 'manager' COMMENT 'manager=مدیر باشگاه, coach=مربی, site_support',
        `coach_id` bigint(20) unsigned DEFAULT NULL COMMENT 'مربی مرتبط (گیرنده یا فرستنده)',
        `subject` varchar(255) NOT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'pending_reply' COMMENT 'pending_reply, answered, closed',
        `created_by_type` varchar(20) NOT NULL DEFAULT 'user' COMMENT 'user=کاربر, coach=مربی, admin=مدیر',
        `created_by_user_id` bigint(20) unsigned DEFAULT NULL COMMENT 'user_id وقتی created_by_type=user یا admin',
        `created_by_coach_id` bigint(20) unsigned DEFAULT NULL COMMENT 'coach_id وقتی created_by_type=coach',
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_department` (`department`),
        KEY `idx_coach_id` (`coach_id`),
        KEY `idx_status` (`status`),
        KEY `idx_created_by_coach_id` (`created_by_coach_id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_updated_at` (`updated_at`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create support ticket messages table
 */
function sc_create_support_ticket_messages_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_support_ticket_messages';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `ticket_id` bigint(20) unsigned NOT NULL,
        `sender_type` varchar(20) NOT NULL COMMENT 'user, admin, coach',
        `sender_id` bigint(20) unsigned DEFAULT 0,
        `message` text NOT NULL,
        `attachment_ids` text DEFAULT NULL COMMENT 'JSON array of attachment post IDs',
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_ticket_id` (`ticket_id`),
        KEY `idx_created_at` (`created_at`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * جدول لاگ ارسال پیامک (همهٔ ارسال‌ها از هر بخش)
 */
function sc_create_sms_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_sms_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `created_at` datetime NOT NULL,
        `mobile` varchar(20) NOT NULL,
        `message_text` text DEFAULT NULL COMMENT 'متن ارسالی یا توضیح پترن',
        `context` varchar(100) DEFAULT NULL COMMENT 'بخش مبدا: ticket_new, invoice, enrollment, ...',
        `success` tinyint(1) NOT NULL DEFAULT 0,
        `error_message` text DEFAULT NULL,
        `response_message` varchar(500) DEFAULT NULL,
        `message_id` varchar(100) DEFAULT NULL COMMENT 'شناسه پیام از API',
        `extra` text DEFAULT NULL COMMENT 'JSON',
        `delivery_state` varchar(100) DEFAULT NULL COMMENT 'وضعیت تحویل از API: رسیده به گوشی، لیست سیاه، ...',
        `delivery_checked_at` datetime DEFAULT NULL COMMENT 'زمان آخرین بررسی وضعیت تحویل',
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_context` (`context`),
        KEY `idx_success` (`success`),
        KEY `idx_mobile` (`mobile`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * جدول لاگ تفصیلی پیامک (هر فراخوانی sc_log_sms: DEBUG, INFO, SUCCESS, ERROR)
 */
function sc_create_sms_log_entries_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_sms_log_entries';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `created_at` datetime NOT NULL,
        `level` varchar(20) NOT NULL COMMENT 'DEBUG, INFO, SUCCESS, ERROR',
        `message` varchar(500) NOT NULL,
        `data` longtext DEFAULT NULL COMMENT 'JSON',
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_level` (`level`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * جدول لاگ فعالیت ادمین (چه کسی چه عملی روی چه موجودیتی انجام داده)
 */
function sc_create_activity_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_activity_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `created_at` datetime NOT NULL,
        `user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
        `user_display_name` varchar(255) DEFAULT NULL,
        `action` varchar(50) NOT NULL COMMENT 'created, updated, deleted, ...',
        `entity_type` varchar(80) NOT NULL COMMENT 'member, course, coach, notification, ...',
        `entity_id` bigint(20) unsigned DEFAULT NULL,
        `summary` varchar(500) DEFAULT NULL COMMENT 'خلاصه متن: احمد رضایی ایجاد شد',
        `old_value` longtext DEFAULT NULL COMMENT 'JSON قبل از تغییر',
        `new_value` longtext DEFAULT NULL COMMENT 'JSON بعد از تغییر',
        `ip` varchar(45) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_entity` (`entity_type`, `entity_id`),
        KEY `idx_action` (`action`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function sc_update_database() {
    global $wpdb;

    // نسخه نصب شده دیتابیس
    $installed_version = get_option('sc_plugin_db_version');

    // اگر نسخه دیتابیس کمتر از نسخه فعلی افزونه است
    if (version_compare($installed_version, SC_PLUGIN_VERSION, '<')) {

        // --- جدول‌ها ---
        sc_create_settings_table();
        sc_create_invoices_table();
        sc_create_courses_table();
        sc_create_member_courses_table();
        sc_create_attendances_table();
        sc_create_members_table();
        sc_create_expense_categories_table();
        sc_create_expenses_table();
        sc_create_events_table();
        sc_create_event_fields_table();
        sc_create_event_registrations_table();
        sc_create_coaches_table();
        sc_create_course_coaches_table();
        sc_create_wallet_transactions_table();
        sc_create_coach_wallet_transactions_table();
        sc_create_coach_salary_records_table();
        sc_create_coach_withdrawal_requests_table();
        sc_create_honor_categories_table();
        sc_create_honors_table();
        sc_create_notifications_table();
        sc_create_notification_recipients_table();
        sc_create_notification_reads_table();
        sc_create_support_tickets_table();
        sc_create_support_ticket_messages_table();
        sc_create_sms_log_table();
        sc_create_sms_log_entries_table();
        sc_create_activity_log_table();

        // --- ستون‌های جدید (در صورت اضافه شدن بعد از نسخه قبل) ---
        // $table_name = $wpdb->prefix . 'sc_invoices';
        // $column_exists = $wpdb->get_results(
        //     $wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'disable_penalty')
        // );

        // if (empty($column_exists)) {
        //     $wpdb->query("ALTER TABLE $table_name ADD COLUMN `disable_penalty` TINYINT(1) NOT NULL DEFAULT 0 AFTER `penalty_applied`");
        // }
        
        // // بررسی و اضافه کردن ستون price_per_session به جدول courses
        // $courses_table = $wpdb->prefix . 'sc_courses';
        // $price_per_session_exists = $wpdb->get_results(
        //     $wpdb->prepare("SHOW COLUMNS FROM $courses_table LIKE %s", 'price_per_session')
        // );
        
        // if (empty($price_per_session_exists)) {
        //     $wpdb->query("ALTER TABLE $courses_table ADD COLUMN `price_per_session` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `price`");
        // }
        
        // // بررسی و اضافه کردن ستون user_id به جدول attendances
        // $attendances_table = $wpdb->prefix . 'sc_attendances';
        // $user_id_exists = $wpdb->get_results(
        //     $wpdb->prepare("SHOW COLUMNS FROM $attendances_table LIKE %s", 'user_id')
        // );
        
        // if (empty($user_id_exists)) {
        //     $wpdb->query("ALTER TABLE $attendances_table ADD COLUMN `user_id` bigint(20) unsigned DEFAULT NULL AFTER `status`");
        //     $wpdb->query("ALTER TABLE $attendances_table ADD KEY `idx_user_id` (`user_id`)");
        // }
      
        // --- به روز رسانی نسخه دیتابیس ---
        update_option('sc_plugin_db_version', SC_PLUGIN_VERSION);
    }

    // اضافه کردن ستون‌های وضعیت تحویل به جدول لاگ پیامک (برای لیست سیاه / تحویل واقعی)
    $sms_log_table = $wpdb->prefix . 'sc_sms_log';
    $delivery_state_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$sms_log_table` LIKE %s", 'delivery_state'));
    if (empty($delivery_state_exists)) {
        $wpdb->query("ALTER TABLE `$sms_log_table` ADD COLUMN `delivery_state` varchar(100) DEFAULT NULL COMMENT 'وضعیت تحویل از API' AFTER `extra`");
    }
    $delivery_checked_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$sms_log_table` LIKE %s", 'delivery_checked_at'));
    if (empty($delivery_checked_exists)) {
        $wpdb->query("ALTER TABLE `$sms_log_table` ADD COLUMN `delivery_checked_at` datetime DEFAULT NULL COMMENT 'زمان آخرین بررسی وضعیت تحویل' AFTER `delivery_state`");
    }

    // اضافه کردن نوع تراکنش session_fee به جدول کیف پول (یک بار برای نصب‌های قبلی)
    if (get_option('sc_wallet_session_fee_enum_added', '0') !== '1') {
        $wt_table = $wpdb->prefix . 'sc_wallet_transactions';
        $wpdb->query("ALTER TABLE `$wt_table` MODIFY COLUMN `transaction_type` enum('charge','deduct','payment','refund','session_fee') NOT NULL COMMENT 'نوع تراکنش'");
        update_option('sc_wallet_session_fee_enum_added', '1');
    }

    // اضافه کردن ستون member_type به جدول اعضا (یک بار برای نصب‌های قبلی)
    if (get_option('sc_member_type_column_added', '0') !== '1') {
        $members_table = $wpdb->prefix . 'sc_members';
        $col_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$members_table` LIKE %s", 'member_type'));
        if (empty($col_exists)) {
            $wpdb->query("ALTER TABLE `$members_table` ADD COLUMN `member_type` varchar(20) NOT NULL DEFAULT 'normal' COMMENT 'normal=بازیکن عادی, team=بازیکن تیم' AFTER `disable_auto_invoice`");
        }
        update_option('sc_member_type_column_added', '1');
    }

    // اضافه کردن ستون salary_percentage به جدول course_coaches (یک بار برای نصب‌های قبلی)
    if (get_option('sc_coach_salary_percentage_column_added', '0') !== '1') {
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
        $col_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$course_coaches_table` LIKE %s", 'salary_percentage'));
        if (empty($col_exists)) {
            $wpdb->query("ALTER TABLE `$course_coaches_table` ADD COLUMN `salary_percentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'درصد دستمزد مربی برای این دوره' AFTER `coach_id`");
        }
        update_option('sc_coach_salary_percentage_column_added', '1');
    }

    // اضافه کردن ستون file_url به جدول honors (یک بار برای نصب‌های قبلی)
    if (get_option('sc_honors_file_url_column_added', '0') !== '1') {
        $honors_table = $wpdb->prefix . 'sc_honors';
        $col_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$honors_table` LIKE %s", 'file_url'));
        if (empty($col_exists)) {
            $wpdb->query("ALTER TABLE `$honors_table` ADD COLUMN `file_url` varchar(500) DEFAULT NULL AFTER `description`");
        }
        update_option('sc_honors_file_url_column_added', '1');
    }

    // اضافه کردن ستون coach_id به جدول honors (یک بار برای نصب‌های قبلی)
    if (get_option('sc_honors_coach_id_column_added', '0') !== '1') {
        $honors_table = $wpdb->prefix . 'sc_honors';
        $col_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$honors_table` LIKE %s", 'coach_id'));
        if (empty($col_exists)) {
            $wpdb->query("ALTER TABLE `$honors_table` ADD COLUMN `coach_id` bigint(20) unsigned DEFAULT NULL AFTER `member_id`");
            $wpdb->query("ALTER TABLE `$honors_table` ADD KEY `idx_coach_id` (`coach_id`)");
        }
        update_option('sc_honors_coach_id_column_added', '1');
    }

    // تغییر member_id به nullable برای نصب‌های قبلی (یک بار)
    if (get_option('sc_honors_member_id_nullable', '0') !== '1') {
        $honors_table = $wpdb->prefix . 'sc_honors';
        // بررسی اینکه آیا member_id nullable است یا نه
        $column_info = $wpdb->get_row($wpdb->prepare(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'member_id'",
            $wpdb->dbname,
            $honors_table
        ));
        
        if ($column_info && $column_info->IS_NULLABLE === 'NO') {
            // تغییر member_id به nullable
            $wpdb->query("ALTER TABLE `$honors_table` MODIFY COLUMN `member_id` bigint(20) unsigned DEFAULT NULL");
        }
        update_option('sc_honors_member_id_nullable', '1');
    }

    // ستون‌های ایجادکننده تیکت (user/coach/admin) برای ارسال تیکت توسط مربی و مدیر
    if (get_option('sc_support_tickets_created_by_columns_added', '0') !== '1') {
        $tickets_table = $wpdb->prefix . 'sc_support_tickets';
        $col1 = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$tickets_table` LIKE %s", 'created_by_type'));
        if (empty($col1)) {
            $wpdb->query("ALTER TABLE `$tickets_table` ADD COLUMN `created_by_type` varchar(20) NOT NULL DEFAULT 'user' COMMENT 'user, coach, admin' AFTER `status`");
        }
        $col2 = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$tickets_table` LIKE %s", 'created_by_user_id'));
        if (empty($col2)) {
            $wpdb->query("ALTER TABLE `$tickets_table` ADD COLUMN `created_by_user_id` bigint(20) unsigned DEFAULT NULL AFTER `created_by_type`");
        }
        $col3 = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$tickets_table` LIKE %s", 'created_by_coach_id'));
        if (empty($col3)) {
            $wpdb->query("ALTER TABLE `$tickets_table` ADD COLUMN `created_by_coach_id` bigint(20) unsigned DEFAULT NULL AFTER `created_by_user_id`");
            $wpdb->query("ALTER TABLE `$tickets_table` ADD KEY `idx_created_by_coach_id` (`created_by_coach_id`)");
        }
        $wpdb->query("UPDATE `$tickets_table` SET created_by_type = 'user', created_by_user_id = user_id WHERE created_by_user_id IS NULL");
        $wpdb->query("ALTER TABLE `$tickets_table` MODIFY COLUMN `user_id` bigint(20) unsigned DEFAULT 0 COMMENT 'کاربر عضو مرتبط؛ 0 وقتی تیکت فقط بین مربی/مدیر است'");
        update_option('sc_support_tickets_created_by_columns_added', '1');
    }

    // ستون‌های ثبت‌کننده اطلاعیه (مدیر / مربی)
    if (get_option('sc_notifications_creator_columns_added', '0') !== '1') {
        $notifications_table = $wpdb->prefix . 'sc_notifications';
        $col1 = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$notifications_table` LIKE %s", 'created_by_type'));
        if (empty($col1)) {
            $wpdb->query("ALTER TABLE `$notifications_table` ADD COLUMN `created_by_type` varchar(20) NOT NULL DEFAULT 'admin' COMMENT 'admin=مدیر, coach=مربی' AFTER `created_by`");
        }
        $col2 = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$notifications_table` LIKE %s", 'created_by_entity_id'));
        if (empty($col2)) {
            $wpdb->query("ALTER TABLE `$notifications_table` ADD COLUMN `created_by_entity_id` bigint(20) unsigned DEFAULT 0 COMMENT 'coach_id if coach' AFTER `created_by_type`");
        }
        update_option('sc_notifications_creator_columns_added', '1');
    }

    // ستون پیوست‌های اطلاعیه
    if (get_option('sc_notifications_attachment_ids_added', '0') !== '1') {
        $notifications_table = $wpdb->prefix . 'sc_notifications';
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `$notifications_table` LIKE %s", 'attachment_ids'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE `$notifications_table` ADD COLUMN `attachment_ids` text DEFAULT NULL COMMENT 'JSON array of attachment post IDs' AFTER `target_config`");
        }
        update_option('sc_notifications_attachment_ids_added', '1');
    }
}
