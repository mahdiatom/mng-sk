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
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    
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

                // اگر ثبت‌کننده مربی باشد، بازیکن‌های بدون انتساب در همین دوره را به خودش منتسب کن
                // تا محاسبه حقوق درصدی برای همان مربی صفر نشود.
                if ($current_coach_id_for_assignment > 0) {
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

            // محاسبه دستمزد مربی‌ها (درصدی) بر اساس بازیکن‌های اختصاص‌یافته به هر مربی
            $calc_couch_salary = sc_get_setting('calc_couch_salary');
            $present_only_for_salary = !empty($calc_couch_salary);

            if ($price_per_session > 0) {
                $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
                $coaches_table = $wpdb->prefix . 'sc_coaches';
                
                // دریافت مربی‌های این دوره که نوع دستمزدشان درصدی است
                $coaches = $wpdb->get_results($wpdb->prepare(
                    "SELECT cc.coach_id, cc.salary_percentage, c.settlement_type 
                     FROM $course_coaches_table cc
                     INNER JOIN $coaches_table c ON cc.coach_id = c.id
                     WHERE cc.course_id = %d AND c.settlement_type = 'percentage' AND c.is_active = 1",
                    $course_id
                ));
                
                foreach ($coaches as $coach) {
                    if (floatval($coach->salary_percentage) > 0 && function_exists('sc_get_coach_attendance_count_for_salary')) {
                        $coach_attendance_count = sc_get_coach_attendance_count_for_salary(
                            $coach->coach_id,
                            $course_id,
                            $attendance_date,
                            $present_only_for_salary
                        );

                        sc_calculate_coach_percentage_salary(
                            $coach->coach_id,
                            $course_id,
                            $attendance_date,
                            $coach_attendance_count,
                            $price_per_session
                        );
                    }
                }
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
        // دریافت دوره‌های مربی که فعال هستند
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.* 
             FROM $courses_table c
             INNER JOIN $course_coaches_table cc ON c.id = cc.course_id
             WHERE cc.coach_id = %d
             AND c.deleted_at IS NULL 
             AND c.is_active = 1 
             ORDER BY c.title ASC",
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

    // دریافت دوره انتخاب شده
    $selected_course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : (isset($_POST['course_id']) ? absint($_POST['course_id']) : 0);
    
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
    // دریافت کاربران فعال دوره (برای مربی: فقط شاگردهای خودش)
    if (current_user_can('coach') && !current_user_can('administrator') && !current_user_can('club_coach')) {
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
        $coaches_table = $wpdb->prefix . 'sc_coaches';

        // اگر دوره فقط یک مربی فعال داشته باشد، رکوردهای بدون coach_id هم برای همان مربی نمایش داده می‌شود
        $single_active_coach_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT CASE WHEN COUNT(DISTINCT cc.coach_id) = 1 THEN MIN(cc.coach_id) ELSE 0 END
             FROM $course_coaches_table cc
             INNER JOIN $coaches_table c ON c.id = cc.coach_id
             WHERE cc.course_id = %d AND c.is_active = 1",
            $selected_course_id
        ));

        $coach_scope_where = "mc.coach_id = %d";
        if ($single_active_coach_id > 0 && $single_active_coach_id === (int) $current_coach_id) {
            $coach_scope_where = "(mc.coach_id = %d OR mc.coach_id IS NULL OR mc.coach_id = 0)";
        }

        $active_members = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.first_name, m.last_name, m.national_id
             FROM $member_courses_table mc
             INNER JOIN $members_table m ON mc.member_id = m.id
             WHERE mc.course_id = %d
             AND $coach_scope_where
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
             ORDER BY m.last_name ASC, m.first_name ASC",
            $selected_course_id,
            $current_coach_id
        ));

        // Fallback: اگر به‌دلیل ناقص بودن انتساب coach_id لیست خالی شد،
        // برای جلوگیری از توقف ثبت حضور، کاربران فعال دوره نمایش داده شوند.
        if (empty($active_members)) {
            $active_members = $wpdb->get_results($wpdb->prepare(
                "SELECT m.id, m.first_name, m.last_name, m.national_id
                 FROM $member_courses_table mc
                 INNER JOIN $members_table m ON mc.member_id = m.id
                 WHERE mc.course_id = %d
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
                 ORDER BY m.last_name ASC, m.first_name ASC",
                $selected_course_id
            ));
        }
    } else {
        $active_members = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.first_name, m.last_name, m.national_id
             FROM $member_courses_table mc
             INNER JOIN $members_table m ON mc.member_id = m.id
             WHERE mc.course_id = %d
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
             ORDER BY m.last_name ASC, m.first_name ASC",
            $selected_course_id
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
?>

<div class="wrap">
    <h1 class="wp-heading-inline">ثبت حضور و غیاب</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-attendance-list'); ?>" class="page-title-action">لیست حضور و غیاب</a>
    <hr class="wp-header-end">
    
</div> 
   <div class="wrap">
    <form method="GET" action="" class="form_attendance_add">
        <input type="hidden" name="page" value="sc-attendance-add">
        
        <table class="form-table sc_form-table">
            <tr>
                <th scope="row">
                    <label for="course_id">انتخاب دوره</label>
                </th>
                <td>
                    <select name="course_id" id="course_id" required >
                        <option value="">-- انتخاب دوره --</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo esc_attr($course->id); ?>" <?php selected($selected_course_id, $course->id); ?>>
                                <?php echo esc_html($course->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="attendance_date">تاریخ</label>
                </th>
                <td>
                    <input type="text" 
                           name="date_shamsi" 
                           id="attendance_date" 
                           value="<?php echo esc_attr($selected_date_shamsi); ?>" 
                           class="regular-text persian-date-input"
                           placeholder="تاریخ (شمسی)" 
                           required 
                           readonly
                           >
                    <input type="hidden" name="date" id="attendance_date_hidden" value="<?php echo esc_attr($selected_date); ?>">
                    <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="filter" class="button button-primary" value="نمایش فرم">
        </p>
    </form>
    
    <?php if ($selected_course_id && !empty($active_members)) : 
        $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM $courses_table WHERE id = %d", $selected_course_id));
        
    ?>
        <form method="POST" action="" style="margin-top: 30px;">
            <?php wp_nonce_field('sc_attendance_nonce', 'sc_attendance_nonce'); ?>
            <input type="hidden" name="course_id" value="<?php echo esc_attr($selected_course_id); ?>">
            <input type="hidden" name="attendance_date" id="attendance_date_hidden_form" value="<?php echo esc_attr($selected_date); ?>">
            <input type="hidden" name="attendance_date_shamsi" id="attendance_date_shamsi_form" value="<?php echo esc_attr($selected_date_shamsi); ?>">
            
            <div class="back_attendance_list">
                <h2 style="margin-top: 0;">
                    لیست حضور و غیاب - 
                    
                    
                    <?php echo esc_html($course->title); ?>
                    <span class="name_course_attendance">(<?php echo sc_date_shamsi($selected_date, 'l j F Y'); ?>)</span>
                    
                </h2>
                <?php if ($is_update_mode): ?>
                        <span>شما در حال بروزرسانی یک حضور و غیاب هستید.</span>
                    <?php else: ?>
                        <span>
شما در حال ثبت یک حضور غیاب جدید هستید.                        </span>
                    <?php endif; ?>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th class="column-row">ردیف</th>
                            <th> نام و نام خانوادگی </th>
                            <th>مبلغ بدهی</th>
                            <th>وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_members as $index => $member) :
                            $debt_user = debt_user($member->id)[0];
                            $existing_status = isset($existing_attendances[$member->id]) ? $existing_attendances[$member->id] : '';
                        ?>
                            <tr style="width = 800px; background-color: <?php echo ($debt_user > 0) ? '#c3191957' : '' ?> !important; background-color: <?php echo ( $debt_user >= floatval(sc_get_setting('max_debt_for_attendance', '0'))) ? '#f20e0e9a' : '2222' ?> !important;" >
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo esc_html($member->first_name . ' '. $member->last_name); ?></td>
                                <td ><?php echo number_format($debt_user) ; ?>  تومان    <?php echo ($debt_user >= floatval(sc_get_setting('max_debt_for_attendance', '0'))) ? 'سقف موجودی - عدم ثبت رکورد کاربر' : ' '; ?></td>
                                <td style="display: flex; margin-top: 7px; ">
                                    <label class="tooltip-container" style="display: inline-block; margin-left: 20px;">
                                        <input type="radio" 
                                               name="attendance[<?php echo esc_attr($member->id); ?>]" 
                                               value="present" 
                                               <?php checked($existing_status, 'present'); echo ($existing_status === 'excused') ? 'disabled' : ''; ?> 
                                              
                                               >
                                                <?php  if($existing_status === 'excused'){ ?>
                                               <span class="tooltip_text_abset_acc">امکان ثبت تغییر وجود ندارد<br> علت :  حالت غیبت مجاز</span>
                                               <?php } ?>
                                        <span style="color: #00a32a; font-weight: bold;">حاضر</span>
                                    </label>
                                    <label class="tooltip-container" style="display: inline-block; margin-left: 20px;">
                                        <input type="radio" 
                                               name="attendance[<?php echo esc_attr($member->id); ?>]" 
                                               value="absent"
                                               <?php checked($existing_status, 'absent'); echo ($existing_status === 'excused') ? 'disabled' : ''; ?> 
                                               >
                                                <?php  if($existing_status === 'excused'){ ?>
                                               <span class="tooltip_text_abset_acc">امکان ثبت تغییر وجود ندارد<br> علت :  حالت غیبت مجاز</span>
                                               <?php } ?>
                                               <span style="color: #d63638; font-weight: bold;">غایب</span>
                                    </label>
                                    <label class="tooltip-container" style="display: inline-block; margin-left: 20px;">
                                        <input type="radio" 
                                               name="attendance[<?php echo esc_attr($member->id); ?>]" 
                                               value="excused"
                                               <?php checked($existing_status, 'excused');  ?> 
                                               disabled
                                               >
                                        <span style="color: #d63638; font-weight: bold;">غایب مجاز</span>
                                        <?php  if($existing_status === 'excused'){ ?>
                                                  <span class="tooltip_text_abset_acc">غیبت مجاز شده است<br>برای ویرایش یا حذف به بخش حضور و غیاب جزئی بروید.</span>
                                               <?php }else{
                                                ?>
                                                    <span class="tooltip_text_abset_acc">برای مجاز کردن غیبت <br>به لیست غایبین مراجعه کنید.</span>

                                                <?php
                                               } ?>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="submit" style="margin-top: 20px;">
                    <button type="submit" name="sc_save_attendance" class="button button-primary button-large">
                        ذخیره حضور و غیاب
                    </button>
                </p>
            </div>
        </form>
    <?php elseif ($selected_course_id && empty($active_members)) : ?>
        <div class="notice notice-info" style="margin-top: 20px;">
            <p>در این دوره هیچ کاربر فعالی ثبت‌نام نشده است.</p>
        </div>
    <?php endif; ?>
</div>

