<?php 
function sc_create_members_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_members';
    $charset_collate = $wpdb->get_charset_collate();
// user_id => id in wordpress
    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) unsigned DEFAULT NULL, 
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
        UNIQUE KEY `idx_user_id` (`user_id`),
        KEY `idx_last_name` (`last_name`),
        KEY `idx_is_active` (`is_active`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create courses table
 */
function sc_create_courses_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_courses';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `description` text,
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
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Create member_courses table (relationship between members and courses)
 */
function sc_create_member_courses_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_member_courses';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `member_id` bigint(20) unsigned NOT NULL,
        `course_id` bigint(20) unsigned NOT NULL,
        `enrollment_date` date DEFAULT NULL,
        `status` varchar(20) DEFAULT 'active',
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_member_id` (`member_id`),
        KEY `idx_course_id` (`course_id`),
        KEY `idx_status` (`status`),
        UNIQUE KEY `idx_member_course` (`member_id`, `course_id`)
    ) $charset_collate";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}


