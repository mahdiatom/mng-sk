<?php
if (!defined('ABSPATH')) {
    exit;
}

function sc_add_bot_columns() {
    global $wpdb;
    $table_members = $wpdb->prefix . 'sc_members';
    $columns = $wpdb->get_col("DESC $table_members", 0);

    if (!in_array('bot_id', $columns, true)) {
        $wpdb->query("ALTER TABLE $table_members ADD bot_id BIGINT(10) NULL");
    }
    if (!in_array('bot_token', $columns, true)) {
        $wpdb->query("ALTER TABLE $table_members ADD bot_token VARCHAR(40) NULL");
    }
}

function sc_create_bot_messages_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_bot_messages';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL DEFAULT '',
        content TEXT NOT NULL,
        target_type VARCHAR(50) NOT NULL DEFAULT 'all',
        target_config LONGTEXT NULL,
        send_safir TINYINT(1) NOT NULL DEFAULT 0,
        recipients_count INT(11) NOT NULL DEFAULT 0,
        bot_sent_count INT(11) NOT NULL DEFAULT 0,
        safir_sent_count INT(11) NOT NULL DEFAULT 0,
        fail_count INT(11) NOT NULL DEFAULT 0,
        created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY created_at (created_at)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function sc_migrate_bale_legacy_options() {
    if (sc_get_setting('sc_bale_safir_api_key', '') === '') {
        $legacy_key = get_option('sc_bale_safir_api_key', '');
        if ($legacy_key !== '') {
            sc_update_setting('sc_bale_safir_api_key', $legacy_key, 'bot');
        }
    }
    if (sc_get_setting('sc_bale_safir_bot_id', '') === '') {
        $legacy_id = get_option('sc_bale_bot_id', '');
        if ($legacy_id !== '') {
            sc_update_setting('sc_bale_safir_bot_id', $legacy_id, 'bot');
        }
    }
}

function sc_add_send_bale_notification_column() {
    global $wpdb;
    $table = $wpdb->prefix . 'sc_notifications';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return;
    }
    $columns = $wpdb->get_col("DESC $table", 0);
    if (!in_array('send_bale', $columns, true)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN send_bale TINYINT(1) NOT NULL DEFAULT 0 AFTER send_sms");
    }
}

add_action('admin_init', 'sc_add_bot_columns');
add_action('admin_init', 'sc_create_bot_messages_table');
add_action('admin_init', 'sc_add_send_bale_notification_column');
add_action('admin_init', 'sc_migrate_bale_legacy_options');
