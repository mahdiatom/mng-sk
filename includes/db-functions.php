<?php 

    /**
 * create sc_settings
 */

function sc_create_settings_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_settings';
    $table_collation = $wpdb->collate;


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
    $table_collation = $wpdb->collate;


$sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `member_id` bigint(20) unsigned NOT NULL,
        `course_id` bigint(20) unsigned NOT NULL,
        `event_id` bigint(20) unsigned DEFAULT NULL,
        `member_course_id` bigint(20) unsigned DEFAULT NULL,
        `woocommerce_order_id` bigint(20) unsigned DEFAULT NULL,
        `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `expense_name` varchar(255) DEFAULT NULL,
        `penalty_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `penalty_applied` tinyint(1) DEFAULT 0,
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
    $table_collation = $wpdb->collate;


$sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `price` decimal(10,2) NOT NULL DEFAULT 0.00,
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
    $table_collation = $wpdb->collate;


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
    $table_collation = $wpdb->collate;


$sql = "CREATE TABLE `$table_name` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `member_id` bigint(20) unsigned NOT NULL,
    `course_id` bigint(20) unsigned NOT NULL,
    `attendance_date` date NOT NULL,
    `status` enum('present','absent') NOT NULL DEFAULT 'present',
    `created_at` datetime NOT NULL,
    `updated_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_member_course_date` (`member_id`,`course_id`,`attendance_date`),
    KEY `idx_member_id` (`member_id`),
    KEY `idx_course_id` (`course_id`),
    KEY `idx_attendance_date` (`attendance_date`),
    KEY `idx_status` (`status`)
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
    $table_collation = $wpdb->collate;


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
