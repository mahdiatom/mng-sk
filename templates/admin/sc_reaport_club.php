<?php
sc_check_and_create_tables();
function sc_get_profile_completion_stats() {
    global $wpdb;

    $table = $wpdb->prefix . 'sc_members';

    // گرفتن همه ID ها
    $member_ids = $wpdb->get_col(
        "SELECT id FROM {$table}"
    );

    $completed = 0;
    $incomplete = 0;

    foreach ($member_ids as $member_id) {
        if (sc_check_profile_completed($member_id)) {
            $completed++;
        } else {
            $incomplete++;
        }
    }

    return [
        'completed'  => $completed,
        'incomplete' => $incomplete,
        'total'      => count($member_ids),
    ];
}

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';

// آمار کلی
//کاربران
$total_members = $wpdb->get_var("SELECT COUNT(*) FROM $members_table");
$active_members = $wpdb->get_var("SELECT COUNT(*) FROM $members_table WHERE is_active = 1");
$inactive_members = $wpdb->get_var("SELECT COUNT(*) FROM $members_table WHERE is_active = 0");
$Incomplete_profile = $wpdb->get_var("SELECT COUNT(*) FROM $members_table WHERE is_active = 0");
$stats = sc_get_profile_completion_stats();


echo "کاربران <br>";
printf("کاربران فعال :%d کاربران غیرفعال :%d کل کاربران :%d",$active_members,$inactive_members , $total_members);
echo "پروفایل <br>";
printf(" کاربران کامل :%d  کاربران ناقص:%d",$stats['completed'] , $stats['incomplete']);
echo "پروفایل <br>";







?>