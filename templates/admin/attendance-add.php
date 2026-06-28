<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$attendances_table = $wpdb->prefix . 'sc_attendances';

    // پردازش فرم ثبت حضور و غیاب
if (isset($_POST['sc_save_attendance']) && check_admin_referer('sc_attendance_nonce', 'sc_attendance_nonce')) {
    $salary_notices = [];
    $course_selection = isset($_POST['course_id']) ? wp_unslash($_POST['course_id']) : '';
    $selection_parts = function_exists('sc_attendance_course_selection_parts')
        ? sc_attendance_course_selection_parts($course_selection)
        : ['course_id' => absint($course_selection), 'chapter_name' => '', 'group_name' => ''];
    $course_id = (int) $selection_parts['course_id'];
    $chapter_name = (string) $selection_parts['chapter_name'];
    $group_name = (string) ($selection_parts['group_name'] ?? '');
    
    // پردازش تاریخ (شمسی به میلادی)
    $attendance_date = '';
    if (isset($_POST['attendance_date_shamsi']) && !empty($_POST['attendance_date_shamsi'])) {
        $attendance_date = sc_shamsi_to_gregorian_date(sanitize_text_field($_POST['attendance_date_shamsi']));
    } elseif (isset($_POST['attendance_date']) && !empty($_POST['attendance_date'])) {
        $attendance_date = sanitize_text_field($_POST['attendance_date']);
    }
  
    if (!$course_id) {
        $message = 'لطفاً یک دوره را انتخاب کنید.';
        $message_type = 'error';
    } elseif (!$attendance_date) {
        $message = 'لطفاً تاریخ را وارد کنید.';
        $message_type = 'error';
    } else {
        // دریافت لیست حضور/غیاب ارسالی
        $attendances = isset($_POST['attendance']) ? $_POST['attendance'] : [];
        
        if (empty($attendances)) {
            $message = 'هیچ اطلاعات حضور و غیابی ثبت نشد.';
            $message_type = 'error';
        } else {
            $courses_table = $wpdb->prefix . 'sc_courses';
            $course_row = $wpdb->get_row($wpdb->prepare(
                "SELECT title, price_per_session, course_type FROM $courses_table WHERE id = %d LIMIT 1",
                $course_id
            ));
            $course_title = $course_row ? $course_row->title : '';
            $price_per_session = $course_row ? floatval($course_row->price_per_session) : 0;
            $attendance_date_shamsi = $attendance_date ? sc_date_shamsi_date_only($attendance_date) : '';

            $saved_count = 0;
            $updated_count = 0;
            $wallet_failed = array(); // لیست کاربرانی که به دلیل کیف پول ثبت نشدند
            $current_user_id = get_current_user_id();
            $current_is_coach_user = current_user_can('coach') && !current_user_can('administrator') && !current_user_can('club_coach');
            $current_coach_id_for_assignment = 0;

            if ($current_is_coach_user) {
                $coaches_table = $wpdb->prefix . 'sc_coaches';
                $current_coach_id_for_assignment = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
                    $current_user_id
                ));
            }

            foreach ($attendances as $member_id => $status) {
                $member_id = absint($member_id);
                if($status === 'present'){
                    $status ='present';
                }elseif($status === 'absent'){
                    $status = 'absent';
                }else{
                    $status = 'excused';
                }
                if (!$member_id) {
                    continue;
                }

                // مربی فقط بازیکن خودش یا بدون انتساب را می‌تواند ثبت کند؛ غیرمجاز را رد کن.
                if ($current_coach_id_for_assignment > 0) {
                    $member_scope = function_exists('sc_attendance_member_scope_sql')
                        ? sc_attendance_member_scope_sql($course_id, $current_coach_id_for_assignment, $chapter_name)
                        : [
                            'coach_scope_where' => '(coach_id = %d OR coach_id IS NULL OR coach_id = 0)',
                            'chapter_where' => '',
                            'prepare_args' => [$current_coach_id_for_assignment],
                        ];
                    $group_filter = function_exists('sc_attendance_member_group_filter_sql')
                        ? sc_attendance_member_group_filter_sql($group_name)
                        : ['sql' => '', 'args' => []];

                    $can_touch_sql = "SELECT COUNT(*) FROM $member_courses_table mc
                         WHERE mc.member_id = %d AND mc.course_id = %d AND mc.status = 'active'
                           AND {$member_scope['coach_scope_where']}
                           {$member_scope['chapter_where']}
                           {$group_filter['sql']}
                           AND (
                             mc.course_status_flags IS NULL OR mc.course_status_flags = ''
                             OR (
                               mc.course_status_flags NOT LIKE %s
                               AND mc.course_status_flags NOT LIKE %s
                               AND mc.course_status_flags NOT LIKE %s
                             )
                           )";
                    $can_touch_args = array_merge(
                        [$member_id, $course_id],
                        $member_scope['prepare_args'],
                        $group_filter['args'],
                        ['%paused%', '%completed%', '%canceled%']
                    );
                    $can_touch = (int) $wpdb->get_var($wpdb->prepare($can_touch_sql, $can_touch_args));
                    if (!$can_touch) {
                        continue;
                    }
                    // بازیکن بدون مربی را با اولین ثبت حضور توسط این مربی به خودش منتسب کن.
                    if ($chapter_name !== '') {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $member_courses_table
                             SET coach_id = %d, chapter = %s, updated_at = %s
                             WHERE member_id = %d
                               AND course_id = %d
                               AND (coach_id IS NULL OR coach_id = 0)
                               AND (chapter = %s OR chapter IS NULL OR chapter = '')",
                            $current_coach_id_for_assignment,
                            $chapter_name,
                            current_time('mysql'),
                            $member_id,
                            $course_id,
                            $chapter_name
                        ));
                    } else {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $member_courses_table
                             SET coach_id = %d, updated_at = %s
                             WHERE member_id = %d
                               AND course_id = %d
                               AND (coach_id IS NULL OR coach_id = 0)",
                            $current_coach_id_for_assignment,
                            current_time('mysql'),
                            $member_id,
                            $course_id
                        ));
                    }
                }

                // بررسی وجود رکورد قبلی
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $attendances_table 
                     WHERE member_id = %d AND course_id = %d AND attendance_date = %s AND schedule_slot_id = 0",
                    $member_id,
                    $course_id,
                    $attendance_date
                ));

                $current_record = null;
                if ($existing) {

                    $current_record = $wpdb->get_row($wpdb->prepare(
                        "SELECT status, absence_sms_sent , user_id FROM $attendances_table WHERE id = %d",
                        $existing
                    ));
                }
                    // debt_user($member_id)[0] < floatval(sc_get_setting('max_debt_for_attendance', '0'))
                // در صورت «حاضر»: فقط برای بازیکن تیم، اگر کیف پول فعال و قیمت جلسه > 0، ابتدا کسر را انجام بده؛ اگر کسر ناموفق بود این کاربر را ثبت نکن
                $need_deduct = ( ($status === 'present' ||  $status === 'absent' || $status === 'excused' )  &&  sc_is_member_team($member_id) && $price_per_session > 0 && (function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet()));
                $deduct_done = false;
                if ($need_deduct) {
                    $should_deduct = !$existing || ! $current_record ;
                    // before =>  $should_deduct = !$existing || ($current_record && $current_record->status === 'absent');
                    if ($should_deduct) {
                        $deduct_result = sc_deduct_wallet_session_fee($member_id, $price_per_session, $course_title, $attendance_date_shamsi );
                        if (!$deduct_result['success']) {
                            $member_name = $wpdb->get_var($wpdb->prepare(
                                "SELECT CONCAT(first_name, ' ', last_name) FROM $members_table WHERE id = %d LIMIT 1",
                                $member_id
                            ));
                            $wallet_failed[] = ( $member_name ? $member_name : 'شناسه ' . $member_id ) . ' (' . ( isset($deduct_result['message']) ? $deduct_result['message'] : 'موجودی کیف پول ناکافی' ) . ')';
                            continue;
                        }
                        $deduct_done = true;
                    }
                }

                $data = array(
                    'member_id' => $member_id,
                    'course_id' => $course_id,
                    'schedule_slot_id' => 0,
                    'attendance_date' => $attendance_date,
                    'status' => $status,
                    'user_id' => $current_user_id,
                    'updated_at' => current_time('mysql')
                );

                if ($existing) {
                    // برگشت مبلغ جلسه اگر از حاضر به غایب تغییر کند (فقط برای بازیکن تیم)
                    // if ($status === 'absent' && $current_record && $current_record->status === 'present' && sc_is_member_team($member_id) && $price_per_session > 0 && (function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet())) {
                    //     sc_refund_wallet_session_fee($member_id, $price_per_session, $course_title, $attendance_date_shamsi);
                    // }

                // refund اگر غیبت تبدیل به غیبت مجاز شود
                    if (
                        $current_record &&
                        $current_record->status === 'absent' &&
                        $status === 'excused' &&
                        sc_is_member_team($member_id) &&
                        $price_per_session > 0 &&
                        (function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet())
                    ) {
                        sc_refund_wallet_session_fee(
                            $member_id,
                            $price_per_session,
                            $course_title,
                            $attendance_date_shamsi
                        );
                    }


                    $update_data = array(
                            'status' => $status,
                            'updated_at' => current_time('mysql')
                        );

                        // فقط اگر ثبت‌کننده قبلاً مشخص نشده باشد مقدار بده
                        if (empty($current_record->user_id)) {
                            $update_data['user_id'] = $current_user_id;
                        }

                    if ($current_record && $current_record->status == 'absent' && $status == 'present') {
                        $update_data['absence_sms_sent'] = 0;
                    }

                    $wpdb->update(
                        $attendances_table,
                        $update_data,
                        array('id' => $existing),
                        array('%s', '%d', '%s'),
                        array('%d')
                    );
                    $updated_count++;

                    if ($status === 'absent') {
                        do_action('sc_attendance_absent', $existing);
                    }
                } else {
                    $data['created_at'] = current_time('mysql');
                    $inserted_id = $wpdb->insert(
                        $attendances_table,
                        $data,
                        array('%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s')
                    );

                    if ($inserted_id) {
                        $saved_count++;
                        if ($status === 'present' || $status === 'absent' ) {
                            sc_decrease_member_session($member_id, $course_id);
                        }
                        if ($status === 'absent') {
                            do_action('sc_attendance_absent', $wpdb->insert_id);
                        }
                    }
                }

                // اگر این دوره خصوصی باشد، وضعیت جلسه متناظر هم با حضور/غیاب سینک شود
                if (function_exists('sc_is_private_course') && sc_is_private_course($course_row) && function_exists('sc_private_sync_session_with_attendance')) {
                    sc_private_sync_session_with_attendance($member_id, $course_id, $attendance_date, $status);
                }
            }

            // دستمزد درصدی (و بخش درصدی ترکیبی): بلافاصله پس از ذخیره حضور بازمحاسبه، به کیف پول اعمال و نوتیف ارسال می‌شود.
            if (($saved_count > 0 || $updated_count > 0) && function_exists('sc_process_coach_salary_attendance_notifications')) {
                $salary_notices = sc_process_coach_salary_attendance_notifications($course_id, $attendance_date, $course_title);
            }

            if ($saved_count > 0 || $updated_count > 0) {
                if (function_exists('sc_log_activity')) {
                    sc_log_activity('updated', 'attendance', $course_id, 'حضور و غیاب دوره «' . $course_title . '» در تاریخ ' . $attendance_date_shamsi . ' ثبت شد (' . $saved_count . ' جدید، ' . $updated_count . ' به‌روزرسانی)', null, ['course_id' => $course_id, 'attendance_date' => $attendance_date, 'saved_count' => $saved_count, 'updated_count' => $updated_count]);
                }
                $message = sprintf(
                    'حضور و غیاب با موفقیت ثبت شد. (%d مورد جدید، %d مورد بروزرسانی)',
                    $saved_count,
                    $updated_count
                );
                if (!empty($wallet_failed)) {
                    $message .= ' ثبت نشد (موجودی کیف پول ناکافی یا بیش از حد مجاز منفی): ' . implode('؛ ', array_map('esc_html', $wallet_failed));
                }
                $message_type = !empty($wallet_failed) ? 'warning' : 'success';
            } else {
                $message = 'خطا در ثبت حضور و غیاب.';
                if (!empty($wallet_failed)) {
                    $message .= ' ' . implode('؛ ', array_map('esc_html', $wallet_failed));
                }
                $message_type = 'error';
            }
        }
    }
    
    if (isset($message)) {
        echo '<div class="notice notice-' . esc_attr($message_type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
    if (!empty($salary_notices) && is_array($salary_notices)) {
        foreach ($salary_notices as $salary_notice) {
            $notice_type = isset($salary_notice['type']) ? $salary_notice['type'] : 'info';
            $notice_message = isset($salary_notice['message']) ? $salary_notice['message'] : '';
            if ($notice_message === '') {
                continue;
            }
            echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible"><p>' . esc_html($notice_message) . '</p></div>';
        }
    }
}

// دریافت دوره‌های فعال
// اگر کاربر مربی است، فقط دوره‌های مربی را نمایش بده
$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();
$current_coach_id = 0;

if (current_user_can('coach') && !current_user_can('administrator') && !current_user_can('club_coach')) {
    // کاربر مربی است - فقط دوره‌های مربی را نمایش بده
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    
    // دریافت coach_id از user_id
    $coach = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    
    if ($coach) {
        $coach_id = $coach->id;
        $current_coach_id = $coach_id;
        // دریافت دوره‌های مربی به تفکیک شعبه
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.title, c.course_type, cc.chapter_name
             FROM $courses_table c
             INNER JOIN $course_coaches_table cc ON cc.course_id = c.id AND cc.coach_id = %d
             WHERE c.deleted_at IS NULL
             AND c.is_active = 1
             ORDER BY c.title ASC, cc.chapter_name ASC",
            $coach_id
        ));
    } else {
        // اگر مربی در جدول coaches وجود نداشت، لیست خالی
        $courses = [];
    }
} else {
    // کاربر مدیر است - همه دوره‌های فعال را نمایش بده
    $courses = $wpdb->get_results(
        "SELECT * FROM $courses_table 
         WHERE deleted_at IS NULL AND is_active = 1 
         ORDER BY title ASC"
    );
}

    // دریافت دوره و شعبه انتخاب شده
    $raw_course_selection = '';
    if (isset($_GET['course_id']) && $_GET['course_id'] !== '') {
        $raw_course_selection = wp_unslash($_GET['course_id']);
    } elseif (isset($_POST['course_id']) && $_POST['course_id'] !== '') {
        $raw_course_selection = wp_unslash($_POST['course_id']);
    }
    $selected_parts = function_exists('sc_attendance_course_selection_parts')
        ? sc_attendance_course_selection_parts($raw_course_selection)
        : ['course_id' => absint($raw_course_selection), 'chapter_name' => '', 'group_name' => ''];
    $selected_course_id = (int) $selected_parts['course_id'];
    $selected_chapter_name = (string) $selected_parts['chapter_name'];
    $selected_group_name = (string) ($selected_parts['group_name'] ?? '');

    $selected_course_type_filter = 'group';
    if (isset($_GET['filter_course_type']) && in_array($_GET['filter_course_type'], ['group', 'private'], true)) {
        $selected_course_type_filter = sanitize_text_field(wp_unslash($_GET['filter_course_type']));
    } elseif ($selected_course_id) {
        $selected_type_row = $wpdb->get_var($wpdb->prepare(
            "SELECT course_type FROM $courses_table WHERE id = %d LIMIT 1",
            $selected_course_id
        ));
        $selected_course_type_filter = function_exists('sc_normalize_attendance_course_type')
            ? sc_normalize_attendance_course_type($selected_type_row)
            : 'group';
    }
    
    // پردازش تاریخ (شمسی به میلادی)
    $selected_date = '';
    $selected_date_shamsi = '';
    
    if (isset($_GET['date_shamsi']) && !empty($_GET['date_shamsi'])) {
        $selected_date = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['date_shamsi']));
        $selected_date_shamsi = sanitize_text_field($_GET['date_shamsi']);
    } elseif (isset($_GET['date']) && !empty($_GET['date'])) {
        $selected_date = sanitize_text_field($_GET['date']);
        $selected_date_shamsi = sc_date_shamsi_date_only($selected_date);
    } elseif (isset($_POST['attendance_date']) && !empty($_POST['attendance_date'])) {
        $selected_date = sanitize_text_field($_POST['attendance_date']);
        $selected_date_shamsi = sc_date_shamsi_date_only($selected_date);
    } else {
        // تاریخ پیش‌فرض: امروز
        $today = new DateTime();
        $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
        $selected_date_shamsi = $today_jalali[0] . '/' . 
                               str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                               str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
        $selected_date = sc_shamsi_to_gregorian_date($selected_date_shamsi);
    }

    // دریافت کاربران فعال دوره انتخاب شده
$active_members = [];
$existing_attendances = [];

if ($selected_course_id) {
    $group_filter = function_exists('sc_attendance_member_group_filter_sql')
        ? sc_attendance_member_group_filter_sql($selected_group_name)
        : ['sql' => '', 'args' => []];

    // دریافت کاربران فعال دوره (برای مربی: خودش + بازیکنان بدون انتساب؛ نه بازیکنان مربی دیگر)
    if (current_user_can('coach') && !current_user_can('administrator') && !current_user_can('club_coach')) {
        $member_scope = function_exists('sc_attendance_member_scope_sql')
            ? sc_attendance_member_scope_sql($selected_course_id, $current_coach_id, $selected_chapter_name)
            : [
                'coach_scope_where' => '(mc.coach_id = %d OR mc.coach_id IS NULL OR mc.coach_id = 0)',
                'chapter_where' => '',
                'prepare_args' => [$current_coach_id],
            ];
        $members_sql = "SELECT m.id, m.first_name, m.last_name, m.national_id
             FROM $member_courses_table mc
             INNER JOIN $members_table m ON mc.member_id = m.id
             WHERE mc.course_id = %d
             AND {$member_scope['coach_scope_where']}
             {$member_scope['chapter_where']}
             {$group_filter['sql']}
             AND mc.status = 'active'
             AND (
                 mc.course_status_flags IS NULL
                 OR mc.course_status_flags = ''
                 OR (
                     mc.course_status_flags NOT LIKE '%%paused%%'
                     AND mc.course_status_flags NOT LIKE '%%completed%%'
                     AND mc.course_status_flags NOT LIKE '%%canceled%%'
                 )
             )
             ORDER BY m.last_name ASC, m.first_name ASC";
        $active_members = $wpdb->get_results($wpdb->prepare(
            $members_sql,
            array_merge([$selected_course_id], $member_scope['prepare_args'], $group_filter['args'])
        ));
    } else {
        $members_sql = "SELECT m.id, m.first_name, m.last_name, m.national_id
             FROM $member_courses_table mc
             INNER JOIN $members_table m ON mc.member_id = m.id
             WHERE mc.course_id = %d
             {$group_filter['sql']}
             AND mc.status = 'active'
             AND (
                 mc.course_status_flags IS NULL
                 OR mc.course_status_flags = ''
                 OR (
                     mc.course_status_flags NOT LIKE '%%paused%%'
                     AND mc.course_status_flags NOT LIKE '%%completed%%'
                     AND mc.course_status_flags NOT LIKE '%%canceled%%'
                 )
             )
             ORDER BY m.last_name ASC, m.first_name ASC";
        $active_members = $wpdb->get_results($wpdb->prepare(
            $members_sql,
            array_merge([$selected_course_id], $group_filter['args'])
        ));
    }
    
    // دریافت حضور و غیاب‌های ثبت شده برای این دوره و تاریخ
    if (!empty($active_members) && $selected_date) {
        $member_ids = array_map(function($m) { return $m->id; }, $active_members);
        $placeholders = implode(',', array_fill(0, count($member_ids), '%d'));
        
        $existing_attendances_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT member_id, status 
             FROM $attendances_table 
             WHERE course_id = %d 
             AND attendance_date = %s 
             AND member_id IN ($placeholders)",
            array_merge([$selected_course_id, $selected_date], $member_ids)
        ));
        
        foreach ($existing_attendances_raw as $att) {
            $existing_attendances[$att->member_id] = $att->status;
           
        }
    }
}



$is_update_mode = !empty($existing_attendances);

$selected_course_value = '';
$selected_course_label = '';
if ($selected_course_id) {
    $selected_course_value = function_exists('sc_attendance_course_option_value')
        ? sc_attendance_course_option_value($selected_course_id, $selected_chapter_name, $selected_group_name)
        : (string) $selected_course_id;

    if (function_exists('sc_attendance_build_course_dropdown_options')) {
        foreach (sc_attendance_build_course_dropdown_options($courses) as $opt) {
            if ((string) $opt['value'] === (string) $selected_course_value) {
                $selected_course_label = (string) $opt['label'];
                break;
            }
        }
    }

    if ($selected_course_label === '') {
        $course_row_for_label = $wpdb->get_row($wpdb->prepare(
            "SELECT title FROM $courses_table WHERE id = %d LIMIT 1",
            $selected_course_id
        ));
        if ($course_row_for_label) {
            $selected_course_label = function_exists('sc_attendance_course_option_label')
                ? sc_attendance_course_option_label($course_row_for_label->title, $selected_chapter_name, $selected_group_name)
                : $course_row_for_label->title;
        }
    }
}

$attendance_course_dropdown_options = function_exists('sc_attendance_build_course_dropdown_options')
    ? sc_attendance_build_course_dropdown_options($courses)
    : [];
?>

<div class="wrap sc-attendance-page-header">
    <h1 class="wp-heading-inline">ثبت حضور و غیاب</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-attendance-list'); ?>" class="page-title-action">لیست حضور و غیاب</a>
    <hr class="wp-header-end">
</div>
<div class="wrap sc-attendance-page-body">
    <form method="GET" action="" class="form_attendance_add sc-attendance-filter-panel" id="sc-attendance-filter-form">
        <input type="hidden" name="page" value="sc-attendance-add">

        <div class="sc-attendance-filter-grid">
            <div class="sc-attendance-filter-field sc-attendance-filter-field--type">
                <span class="sc-attendance-filter-label">نوع دوره</span>
                <div class="sc-attendance-course-type-radios">
                    <label class="sc-attendance-course-type-option">
                        <input type="radio" name="filter_course_type" value="group" <?php checked($selected_course_type_filter, 'group'); ?>>
                        <span>گروهی</span>
                    </label>
                    <label class="sc-attendance-course-type-option">
                        <input type="radio" name="filter_course_type" value="private" <?php checked($selected_course_type_filter, 'private'); ?>>
                        <span>خصوصی / نیمه‌خصوصی</span>
                    </label>
                </div>
            </div>

            <div class="sc-attendance-filter-field">
                <label class="sc-attendance-filter-label" for="course_id">انتخاب دوره</label>
                <div class="sc-searchable-dropdown sc-attendance-course-dropdown">
                    <input type="hidden" name="course_id" id="course_id" value="<?php echo esc_attr($selected_course_value); ?>">

                    <div class="sc-dropdown-toggle" tabindex="0" role="button" aria-haspopup="listbox">
                        <span class="sc-dropdown-placeholder" <?php echo $selected_course_value !== '' ? 'style="display:none"' : ''; ?>>جستجو و انتخاب دوره...</span>
                        <span class="sc-dropdown-selected" <?php echo $selected_course_value === '' ? 'style="display:none"' : ''; ?>>
                            <?php echo esc_html($selected_course_label); ?>
                        </span>
                        <span class="sc-dropdown-arrow">▼</span>
                    </div>

                    <div class="sc-dropdown-menu" role="listbox">
                        <div class="sc-dropdown-search">
                            <input type="text" class="sc-attendance-course-search" placeholder="جستجوی نام دوره یا شعبه..." autocomplete="off">
                        </div>
                        <div class="sc-dropdown-options">
                            <?php
                            $course_display_count = 0;
                            $course_max_display = 10;
                            foreach ($attendance_course_dropdown_options as $opt) :
                                $course_type_normalized = (string) ($opt['course_type'] ?? 'group');
                                $option_value = (string) $opt['value'];
                                $option_label = (string) $opt['label'];
                                $search_blob = (string) ($opt['search'] ?? strtolower($option_label));
                                $matches_type = ($course_type_normalized === $selected_course_type_filter);
                                $display_class = ($matches_type && $course_display_count < $course_max_display) ? 'sc-visible' : 'sc-hidden';
                                if ($matches_type) {
                                    $course_display_count++;
                                }
                                ?>
                                <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?><?php echo $matches_type ? '' : ' sc-course-type-hidden'; ?>"
                                     data-value="<?php echo esc_attr($option_value); ?>"
                                     data-search="<?php echo esc_attr($search_blob); ?>"
                                     data-label="<?php echo esc_attr($option_label); ?>"
                                     data-course-type="<?php echo esc_attr($course_type_normalized); ?>"
                                     <?php echo $matches_type ? '' : 'style="display:none;"'; ?>>
                                    <?php echo esc_html($option_label); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sc-attendance-filter-field">
                <label class="sc-attendance-filter-label" for="attendance_date">تاریخ</label>
                <input type="text"
                       name="date_shamsi"
                       id="attendance_date"
                       value="<?php echo esc_attr($selected_date_shamsi); ?>"
                       class="regular-text persian-date-input sc-attendance-date-input"
                       placeholder="تاریخ (شمسی)"
                       required
                       readonly>
                <input type="hidden" name="date" id="attendance_date_hidden" value="<?php echo esc_attr($selected_date); ?>">
                <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
            </div>
        </div>

        <p class="submit sc-attendance-filter-actions">
            <input type="submit" name="filter" class="button button-primary" value="نمایش فرم">
        </p>
    </form>
    
    <?php if ($selected_course_id && !empty($active_members)) : 
        $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM $courses_table WHERE id = %d", $selected_course_id));
        
    ?>
        <form method="POST" action="" class="sc-attendance-save-form">
            <?php wp_nonce_field('sc_attendance_nonce', 'sc_attendance_nonce'); ?>
            <input type="hidden" name="course_id" value="<?php echo esc_attr($selected_course_value); ?>">
            <input type="hidden" name="attendance_date" id="attendance_date_hidden_form" value="<?php echo esc_attr($selected_date); ?>">
            <input type="hidden" name="attendance_date_shamsi" id="attendance_date_shamsi_form" value="<?php echo esc_attr($selected_date_shamsi); ?>">

            <div class="sc-attendance-save-panel">
                <div class="sc-attendance-save-panel__head">
                    <h2>
                        لیست حضور و غیاب —
                        <?php
                        echo esc_html($course->title);
                        if ($selected_chapter_name !== '') {
                            echo ' — ' . esc_html($selected_chapter_name);
                        }
                        if ($selected_group_name !== '') {
                            echo ' — گروه: ' . esc_html($selected_group_name);
                        }
                        ?>
                        <span class="name_course_attendance">(<?php echo sc_date_shamsi($selected_date, 'l j F Y'); ?>)</span>
                    </h2>
                    <?php if ($is_update_mode) : ?>
                        <span class="sc-attendance-mode-badge sc-attendance-mode-badge--update">در حال بروزرسانی رکورد موجود</span>
                    <?php else : ?>
                        <span class="sc-attendance-mode-badge sc-attendance-mode-badge--new">ثبت حضور و غیاب جدید</span>
                    <?php endif; ?>
                </div>

                <div class="back_attendance_list sc-attendance-members-wrap">
                    <table class="wp-list-table widefat fixed striped sc-attendance-members-table">
                        <thead>
                            <tr>
                                <th class="column-row">ردیف</th>
                                <th>نام و نام خانوادگی</th>
                                <th>مبلغ بدهی</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $max_debt_for_attendance = floatval(sc_get_setting('max_debt_for_attendance', '0'));
                        foreach ($active_members as $index => $member) :
                            $debt_user = debt_user($member->id)[0];
                            $existing_status = isset($existing_attendances[$member->id]) ? $existing_attendances[$member->id] : '';
                            $row_class = 'sc-attendance-member-row';
                            if ($debt_user >= $max_debt_for_attendance && $max_debt_for_attendance > 0) {
                                $row_class .= ' sc-attendance-member-row--blocked';
                            } elseif ($debt_user > 0) {
                                $row_class .= ' sc-attendance-member-row--debt';
                            }
                        ?>
                            <tr class="<?php echo esc_attr($row_class); ?>">
                                <td><?php echo $index + 1; ?></td>
                                <td class="sc-attendance-member-name"><?php echo esc_html($member->first_name . ' ' . $member->last_name); ?></td>
                                <td class="sc-attendance-member-debt">
                                    <?php echo number_format($debt_user); ?> تومان
                                    <?php if ($debt_user >= $max_debt_for_attendance && $max_debt_for_attendance > 0) : ?>
                                        <span class="sc-attendance-debt-alert">سقف موجودی — عدم ثبت رکورد</span>
                                    <?php endif; ?>
                                </td>
                                <td class="status_attendace_td sc-attendance-status-cell">
                                    <?php if (empty($existing_status)) : ?>
                                    <button type="button"
                                            class="button button-small sc-attendance-clear-btn"
                                            data-attendance-name="attendance[<?php echo esc_attr($member->id); ?>]"
                                            title="حذف انتخاب"
                                            aria-label="حذف انتخاب">
                                        <span class="dashicons dashicons-no-alt"></span>
                                    </button>
                                    <?php endif; ?>
                                    <label class="tooltip-container sc-attendance-status-pill sc-attendance-status-pill--present">
                                        <input type="radio"
                                               name="attendance[<?php echo esc_attr($member->id); ?>]"
                                               value="present"
                                               <?php checked($existing_status, 'present'); echo ($existing_status === 'excused') ? 'disabled' : ''; ?>>
                                        <?php if ($existing_status === 'excused') : ?>
                                            <span class="tooltip_text_abset_acc">امکان ثبت تغییر وجود ندارد<br>علت: حالت غیبت مجاز</span>
                                        <?php endif; ?>
                                        <span>حاضر</span>
                                    </label>
                                    <label class="tooltip-container sc-attendance-status-pill sc-attendance-status-pill--absent">
                                        <input type="radio"
                                               name="attendance[<?php echo esc_attr($member->id); ?>]"
                                               value="absent"
                                               <?php checked($existing_status, 'absent'); echo ($existing_status === 'excused') ? 'disabled' : ''; ?>>
                                        <?php if ($existing_status === 'excused') : ?>
                                            <span class="tooltip_text_abset_acc">امکان ثبت تغییر وجود ندارد<br>علت: حالت غیبت مجاز</span>
                                        <?php endif; ?>
                                        <span>غایب</span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="submit sc-attendance-save-actions">
                    <button type="submit" name="sc_save_attendance" class="button button-primary button-large">
                        ذخیره حضور و غیاب
                    </button>
                </p>
            </div>
        </form>
    <?php elseif ($selected_course_id && empty($active_members)) : ?>
        <div class="notice notice-info sc-attendance-empty-notice">
            <p><?php echo $selected_chapter_name !== '' ? 'در این دوره و شعبه هیچ کاربر فعالی ثبت‌نام نشده است.' : 'در این دوره هیچ کاربر فعالی ثبت‌نام نشده است.'; ?></p>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('click', function (event) {
    const clearBtn = event.target.closest('.sc-attendance-clear-btn');
    if (!clearBtn) {
        return;
    }

    const attendanceName = clearBtn.getAttribute('data-attendance-name');
    if (!attendanceName) {
        return;
    }

    const radios = document.getElementsByName(attendanceName);
    for (let i = 0; i < radios.length; i++) {
        radios[i].checked = false;
    }
});

jQuery(document).ready(function($) {
    function scAttendanceGetSelectedCourseType() {
        var $checked = $('input[name="filter_course_type"]:checked');
        return $checked.length ? $checked.val() : 'group';
    }

    function scAttendanceResetCourseDropdown() {
        var $dropdown = $('.sc-attendance-course-dropdown');
        $dropdown.find('#course_id').val('');
        $dropdown.find('.sc-dropdown-placeholder').show();
        $dropdown.find('.sc-dropdown-selected').hide().text('');
        $dropdown.find('.sc-option-check').remove();
        $dropdown.find('.sc-dropdown-option').removeClass('sc-selected').css('background', '');
    }

    function scAttendanceSyncCourseDropdown(searchTerm) {
        var type = scAttendanceGetSelectedCourseType();
        var term = (searchTerm || '').toLowerCase().trim();
        var $dropdown = $('.sc-attendance-course-dropdown');
        var $options = $dropdown.find('.sc-dropdown-option');
        var visibleCount = 0;
        var maxVisible = 10;
        var currentVal = $dropdown.find('#course_id').val();
        var currentStillValid = false;

        $options.closest('.sc-dropdown-options').find('div:not(.sc-dropdown-option)').remove();

        $options.each(function() {
            var $opt = $(this);
            var optType = $opt.attr('data-course-type') || 'group';
            var searchText = $opt.attr('data-search') || '';
            var typeMatch = (optType === type);
            var searchMatch = (term === '' || searchText.indexOf(term) !== -1);

            if (!typeMatch) {
                $opt.hide().addClass('sc-hidden sc-course-type-hidden').removeClass('sc-visible');
                return;
            }

            $opt.removeClass('sc-course-type-hidden');

            if (searchMatch && visibleCount < maxVisible) {
                $opt.show().removeClass('sc-hidden').addClass('sc-visible');
                visibleCount++;
            } else {
                $opt.hide().addClass('sc-hidden').removeClass('sc-visible');
            }

            if (currentVal && String($opt.attr('data-value')) === String(currentVal) && typeMatch && searchMatch) {
                currentStillValid = true;
            }
        });

        if (currentVal && !currentStillValid) {
            scAttendanceResetCourseDropdown();
        }

        if (visibleCount === 0) {
            $dropdown.find('.sc-dropdown-options').append(
                '<div class="sc-attendance-course-empty" style="padding:15px;text-align:center;color:#757575;">دوره‌ای برای این نوع یافت نشد</div>'
            );
        }
    }

    scAttendanceSyncCourseDropdown($('.sc-attendance-course-search').val() || '');

    $('input[name="filter_course_type"]').on('change', function() {
        $('.sc-attendance-course-search').val('');
        scAttendanceResetCourseDropdown();
        scAttendanceSyncCourseDropdown('');
    });

    $('.sc-attendance-course-search').on('input', function() {
        scAttendanceSyncCourseDropdown($(this).val() || '');
    });

    $('#sc-attendance-filter-form').on('submit', function(e) {
        var courseVal = $('#course_id').val();
        if (!courseVal) {
            e.preventDefault();
            if (typeof window.scConfirm === 'function') {
                window.scConfirm({
                    type: 'warning',
                    title: 'انتخاب دوره',
                    message: 'لطفاً یک دوره را انتخاب کنید.',
                    confirmText: 'باشه',
                    cancelText: ''
                });
            } else {
                alert('لطفاً یک دوره را انتخاب کنید.');
            }
        }
    });
});
</script>

