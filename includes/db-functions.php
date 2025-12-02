<?php 
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


