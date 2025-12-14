<?php
require_once('wp-load.php');
global $wpdb;
$table_name = $wpdb->prefix . 'sc_attendances';
$columns = $wpdb->get_results('SHOW COLUMNS FROM ' . $table_name);
$found = false;
foreach ($columns as $column) {
    if ($column->Field == 'absence_sms_sent') {
        echo 'Column absence_sms_sent exists';
        $found = true;
        break;
    }
}
if (!$found) {
    echo 'Column absence_sms_sent does NOT exist';
}
?>
