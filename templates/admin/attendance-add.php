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

// فیلتر «نمایش فرم» با POST + redirect (بدون course_id با کاراکتر | در URL)
if (
    isset($_POST['sc_attendance_filter'])
    && check_admin_referer('sc_attendance_filter', 'sc_attendance_filter_nonce')
) {
    $filter_parts = function_exists('sc_attendance_get_selection_from_request')
        ? sc_attendance_get_selection_from_request($_POST)
        : ['course_id' => 0, 'chapter_name' => '', 'group_name' => ''];

    $redirect_args = ['page' => 'sc-attendance-add'];

    if ($filter_parts['course_id'] > 0) {
        $redirect_args['attendance_course_id'] = $filter_parts['course_id'];
        if ($filter_parts['chapter_name'] !== '') {
            $redirect_args['attendance_chapter'] = $filter_parts['chapter_name'];
        }
        if ($filter_parts['group_name'] !== '') {
            $redirect_args['attendance_group'] = $filter_parts['group_name'];
        }
    }

    if (isset($_POST['filter_course_type']) && in_array($_POST['filter_course_type'], ['group', 'private'], true)) {
        $redirect_args['filter_course_type'] = sanitize_text_field(wp_unslash($_POST['filter_course_type']));
    }

    if (isset($_POST['date_shamsi']) && $_POST['date_shamsi'] !== '') {
        $redirect_args['date_shamsi'] = sanitize_text_field(wp_unslash($_POST['date_shamsi']));
    }
    if (isset($_POST['date']) && $_POST['date'] !== '') {
        $redirect_args['date'] = sanitize_text_field(wp_unslash($_POST['date']));
    }

    wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
    exit;
}

    // پردازش فرم ثبت حضور و غیاب
if (
    (isset($_POST['sc_save_attendance_pending']) || isset($_POST['sc_save_attendance_recorded']))
    && check_admin_referer('sc_attendance_nonce', 'sc_attendance_nonce')
) {
    $salary_notices = [];
    $is_recorded_batch = isset($_POST['sc_save_attendance_recorded']);
    $selection_parts = function_exists('sc_attendance_get_selection_from_request')
        ? sc_attendance_get_selection_from_request($_POST)
        : (function_exists('sc_attendance_course_selection_parts')
            ? sc_attendance_course_selection_parts(isset($_POST['course_id']) ? wp_unslash($_POST['course_id']) : '')
            : ['course_id' => absint($_POST['course_id'] ?? 0), 'chapter_name' => '', 'group_name' => '']);
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

    $current_user_id = get_current_user_id();
    $current_is_coach_user = function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance();
    $current_coach_id_for_assignment = 0;
    if ($current_is_coach_user) {
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        $current_coach_id_for_assignment = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
            $current_user_id
        ));
    }
  
    if (!$course_id) {
        $message = 'لطفاً یک دوره را انتخاب کنید.';
        $message_type = 'error';
    } elseif (
        function_exists('sc_secretary_can_access_attendance_course')
        && !sc_secretary_can_access_attendance_course($course_id, $chapter_name)
    ) {
        $message = 'دوره انتخاب‌شده برای شعبه شما مجاز نیست.';
        $message_type = 'error';
    } elseif (
        function_exists('sc_course_has_grouping_enabled')
        && sc_course_has_grouping_enabled($course_id)
        && $group_name === ''
    ) {
        $message = 'برای دوره دارای گروه‌بندی، انتخاب گروه الزامی است.';
        $message_type = 'error';
    } elseif (!$attendance_date) {
        $message = 'لطفاً تاریخ را وارد کنید.';
        $message_type = 'error';
    } elseif (
        $current_is_coach_user
        && $current_coach_id_for_assignment > 0
        && function_exists('sc_coach_is_assistant_only_for_course_chapter')
        && sc_coach_is_assistant_only_for_course_chapter($course_id, $current_coach_id_for_assignment, $chapter_name)
    ) {
        $message = 'کمک‌مربی امکان ثبت حضور و غیاب ندارد. فقط مربی اصلی می‌تواند حضور و غیاب را ثبت کند.';
        $message_type = 'error';
    } else {
        // اعتبارسنجی تاریخ مربی باید فقط در صورت خطا متوقف کند؛ در حالت مجاز باید به ذخیره برسد.
        $coach_date_blocked = false;
        if (
            $current_is_coach_user
            && $current_coach_id_for_assignment > 0
            && function_exists('sc_validate_coach_attendance_date_access')
        ) {
            $coach_date_access = sc_validate_coach_attendance_date_access(
                $current_coach_id_for_assignment,
                $course_id,
                $attendance_date,
                $chapter_name,
                $group_name
            );
            if (empty($coach_date_access['allowed'])) {
                $message = isset($coach_date_access['message']) ? (string) $coach_date_access['message'] : 'در این تاریخ امکان ثبت حضور و غیاب برای مربی وجود ندارد.';
                $message_type = 'error';
                $coach_date_blocked = true;
            }
        }

        if (!$coach_date_blocked) {
            // دریافت لیست حضور/غیاب ارسالی
            if ($is_recorded_batch) {
                $attendances = isset($_POST['attendance_recorded']) ? (array) $_POST['attendance_recorded'] : [];
            } else {
                $attendances = isset($_POST['attendance_pending']) ? (array) $_POST['attendance_pending'] : [];
            }

            if (empty($attendances)) {
                $message = $is_recorded_batch
                    ? 'هیچ موردی برای بروزرسانی انتخاب نشده است.'
                    : 'هیچ موردی برای ثبت جدید انتخاب نشده است.';
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
            $debt_blocked = array(); // لیست کاربرانی که به دلیل بدهی بیش از حد ثبت نشدند
            foreach ($attendances as $member_id => $status) {
                $member_id = absint($member_id);
                if($status === 'present'){
                    $status ='present';
                }elseif($status === 'absent'){
                    $status = 'absent';
                }elseif ($status === 'excused') {
                    // مربی نمی‌تواند غیبت را مجاز کند
                    if ($current_is_coach_user) {
                        continue;
                    }
                    $status = 'excused';
                } else {
                    continue;
                }
                if (!$member_id) {
                    continue;
                }
                if (function_exists('sc_attendance_member_debt_blocked') && sc_attendance_member_debt_blocked($member_id)) {
                    $member_name = $wpdb->get_var($wpdb->prepare(
                        "SELECT CONCAT(first_name, ' ', last_name) FROM $members_table WHERE id = %d LIMIT 1",
                        $member_id
                    ));
                    $debt_blocked[] = $member_name ? $member_name : 'شناسه ' . $member_id;
                    continue;
                }

                $existing_db_status = $wpdb->get_var($wpdb->prepare(
                    "SELECT status FROM $attendances_table
                     WHERE member_id = %d AND course_id = %d AND attendance_date = %s AND schedule_slot_id = 0
                     LIMIT 1",
                    $member_id,
                    $course_id,
                    $attendance_date
                ));
                $has_db_record = ($existing_db_status === 'present' || $existing_db_status === 'absent' || $existing_db_status === 'excused');

                if ($is_recorded_batch && !$has_db_record) {
                    continue;
                }
                if (!$is_recorded_batch && $has_db_record) {
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
                        ? sc_attendance_member_group_filter_sql($group_name, $course_id)
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
                        "SELECT status, absence_sms_sent , user_id, record_method FROM $attendances_table WHERE id = %d",
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
                    'record_method' => 'manual',
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

                    if (!$current_record || $current_record->record_method !== 'qr') {
                        $update_data['record_method'] = 'manual';
                    }

                        // فقط اگر ثبت‌کننده قبلاً مشخص نشده باشد مقدار بده
                        if (empty($current_record->user_id)) {
                            $update_data['user_id'] = $current_user_id;
                        }

                    if ($current_record && $current_record->status == 'absent' && $status == 'present') {
                        $update_data['absence_sms_sent'] = 0;
                    }

                    $update_formats = [];
                    foreach (array_keys($update_data) as $update_key) {
                        $update_formats[] = in_array($update_key, ['user_id', 'absence_sms_sent'], true) ? '%d' : '%s';
                    }

                    $wpdb->update(
                        $attendances_table,
                        $update_data,
                        array('id' => $existing),
                        $update_formats,
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
                        array('%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s')
                    );

                    if ($inserted_id) {
                        $saved_count++;
                        if ($status === 'present') {
                            // حضور: حتی اگر جلسه صفر باشد، منفی می‌شود تا بعد از شارژ کم شود
                            sc_decrease_member_session($member_id, $course_id, true);
                        } elseif ($status === 'absent') {
                            // غیبت: فقط از جلسات مثبت کم می‌شود؛ منفی نمی‌شود
                            sc_decrease_member_session($member_id, $course_id, false);
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
                $message = $is_recorded_batch
                    ? sprintf('بروزرسانی با موفقیت انجام شد. (%d مورد به‌روزرسانی)', $updated_count)
                    : sprintf('ثبت جدید با موفقیت انجام شد. (%d مورد ثبت شد)', $saved_count);
                if ($is_recorded_batch && $saved_count > 0) {
                    $message = sprintf('بروزرسانی انجام شد. (%d مورد جدید، %d مورد به‌روزرسانی)', $saved_count, $updated_count);
                } elseif (!$is_recorded_batch && $updated_count > 0) {
                    $message = sprintf('ثبت انجام شد. (%d مورد جدید، %d مورد به‌روزرسانی)', $saved_count, $updated_count);
                }
                if (!empty($wallet_failed)) {
                    $message .= ' ثبت نشد (موجودی کیف پول ناکافی یا بیش از حد مجاز منفی): ' . implode('؛ ', array_map('esc_html', $wallet_failed));
                }
                if (!empty($debt_blocked)) {
                    $message .= ' ثبت نشد (بدهی بیش از حد مجاز): ' . implode('؛ ', array_map('esc_html', $debt_blocked));
                }
                $message_type = (!empty($wallet_failed) || !empty($debt_blocked)) ? 'warning' : 'success';
            } else {
                $message = 'خطا در ثبت حضور و غیاب.';
                if (!empty($wallet_failed)) {
                    $message .= ' ' . implode('؛ ', array_map('esc_html', $wallet_failed));
                }
                if (!empty($debt_blocked)) {
                    $message .= ' ثبت نشد (بدهی بیش از حد مجاز): ' . implode('؛ ', array_map('esc_html', $debt_blocked));
                }
                $message_type = 'error';
            }
        }
        }
    }
    
    if (isset($message)) {
        echo '<div class="notice notice-' . esc_attr($message_type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
    if (!empty($salary_notices) && is_array($salary_notices)) {
        foreach ($salary_notices as $salary_notice) {
            $notice_type = isset($salary_notice['type']) ? $salary_notice['type'] : 'info';
            if (!in_array($notice_type, ['success', 'warning', 'error', 'info'], true)) {
                $notice_type = 'info';
            }
            $notice_message = isset($salary_notice['message']) ? trim((string) $salary_notice['message']) : '';
            if ($notice_message === '') {
                continue;
            }
            echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible"><p><strong>حقوق و دستمزد مربی:</strong> ' . esc_html($notice_message) . '</p></div>';
        }
    }
}

// دریافت دوره‌های فعال
// منشی: فقط دوره‌های شعبه(های) مجاز؛ مربی: فقط دوره‌های خودش
$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();
$current_coach_id = 0;

if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
    && function_exists('sc_secretary_get_branch_courses_for_attendance')) {
    $courses = sc_secretary_get_branch_courses_for_attendance();
} elseif (function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance()) {
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
        // دریافت دوره‌های مربی اصلی (کمک‌مربی در لیست حضور و غیاب نمی‌آید)
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
    $selection_parts = function_exists('sc_attendance_get_selection_from_request')
        ? sc_attendance_get_selection_from_request()
        : (function_exists('sc_attendance_course_selection_parts')
            ? sc_attendance_course_selection_parts(
                isset($_GET['course_id']) && $_GET['course_id'] !== ''
                    ? wp_unslash($_GET['course_id'])
                    : (isset($_POST['course_id']) ? wp_unslash($_POST['course_id']) : '')
            )
            : ['course_id' => 0, 'chapter_name' => '', 'group_name' => '']);
    $selected_course_id = (int) $selection_parts['course_id'];
    $selected_chapter_name = (string) $selection_parts['chapter_name'];
    $selected_group_name = (string) ($selection_parts['group_name'] ?? '');

    if (
        $selected_course_id > 0
        && function_exists('sc_secretary_can_access_attendance_course')
        && !sc_secretary_can_access_attendance_course($selected_course_id, $selected_chapter_name)
    ) {
        $selected_course_id = 0;
        $selected_chapter_name = '';
        $selected_group_name = '';
        echo '<div class="notice notice-error is-dismissible"><p>دوره انتخاب‌شده برای شعبه شما مجاز نیست.</p></div>';
    }

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

$is_coach_attendance_user = function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance() && $current_coach_id > 0;
$coach_attendance_access = [
    'allowed' => true,
    'code' => 'ok',
    'message' => '',
    'config' => [
        'allowed_weekdays' => [],
        'allowed_weekdays_text' => '',
        'min_date' => '',
        'max_date' => '',
        'deadline_days' => 0,
    ],
];

if ($is_coach_attendance_user && $selected_course_id > 0
    && function_exists('sc_coach_is_assistant_only_for_course_chapter')
    && sc_coach_is_assistant_only_for_course_chapter($selected_course_id, $current_coach_id, $selected_chapter_name)
) {
    $coach_attendance_access = [
        'allowed' => false,
        'code' => 'assistant_no_attendance',
        'message' => 'کمک‌مربی امکان ثبت حضور و غیاب ندارد. فقط مربی اصلی می‌تواند حضور و غیاب را ثبت کند.',
        'config' => $coach_attendance_access['config'],
    ];
} elseif ($is_coach_attendance_user && $selected_course_id > 0 && $selected_date !== '' && function_exists('sc_validate_coach_attendance_date_access')) {
    $coach_attendance_access = sc_validate_coach_attendance_date_access(
        $current_coach_id,
        $selected_course_id,
        $selected_date,
        $selected_chapter_name,
        $selected_group_name
    );
}

    // دریافت کاربران فعال دوره انتخاب شده
$active_members = [];
$existing_attendances = [];
$existing_record_methods = [];

if ($selected_course_id && !empty($coach_attendance_access['allowed'])) {
    $group_filter = function_exists('sc_attendance_member_group_filter_sql')
        ? sc_attendance_member_group_filter_sql($selected_group_name, $selected_course_id)
        : ['sql' => '', 'args' => []];

    // دریافت کاربران فعال دوره (برای مربی: خودش + بازیکنان بدون انتساب؛ نه بازیکنان مربی دیگر)
    if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
        && function_exists('sc_secretary_member_enrollment_match_sql')) {
        $match = sc_secretary_member_enrollment_match_sql('mc');
        if ($match['sql'] === '1=0') {
            $active_members = [];
        } else {
            $chapter_sql = '';
            $chapter_args = [];
            if ($selected_chapter_name !== '') {
                $chapter_sql = " AND (TRIM(IFNULL(mc.chapter, '')) = '' OR TRIM(mc.chapter) = %s)";
                $chapter_args[] = $selected_chapter_name;
            }
            $members_sql = "SELECT m.id, m.first_name, m.last_name, m.national_id
                 FROM $member_courses_table mc
                 INNER JOIN $members_table m ON mc.member_id = m.id
                 WHERE mc.course_id = %d
                 AND {$match['sql']}
                 {$chapter_sql}
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
                array_merge([$selected_course_id], $match['args'], $chapter_args, $group_filter['args'])
            ));
        }
    } elseif (function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance()) {
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
            "SELECT member_id, status, record_method 
             FROM $attendances_table 
             WHERE course_id = %d 
             AND attendance_date = %s 
             AND member_id IN ($placeholders)",
            array_merge([$selected_course_id, $selected_date], $member_ids)
        ));
        
        $existing_record_methods = [];
        foreach ($existing_attendances_raw as $att) {
            $existing_attendances[$att->member_id] = $att->status;
            $existing_record_methods[$att->member_id] = $att->record_method;
        }
    }
}



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
$coach_attendance_rules_map = [];
if ($is_coach_attendance_user && function_exists('sc_attendance_course_selection_parts') && function_exists('sc_get_coach_attendance_date_picker_config')) {
    foreach ($attendance_course_dropdown_options as $attendance_course_option) {
        $option_value = isset($attendance_course_option['value']) ? (string) $attendance_course_option['value'] : '';
        if ($option_value === '') {
            continue;
        }
        $option_parts = sc_attendance_course_selection_parts($option_value);
        $coach_attendance_rules_map[$option_value] = sc_get_coach_attendance_date_picker_config(
            $current_coach_id,
            (int) ($option_parts['course_id'] ?? 0),
            (string) ($option_parts['chapter_name'] ?? ''),
            (string) ($option_parts['group_name'] ?? '')
        );
    }
}

$coach_attendance_config = isset($coach_attendance_access['config']) && is_array($coach_attendance_access['config'])
    ? $coach_attendance_access['config']
    : ['allowed_weekdays' => [], 'allowed_weekdays_text' => '', 'min_date' => '', 'max_date' => '', 'deadline_days' => 0];
$coach_attendance_weekdays_csv = !empty($coach_attendance_config['allowed_weekdays']) && is_array($coach_attendance_config['allowed_weekdays'])
    ? implode(',', array_map('absint', $coach_attendance_config['allowed_weekdays']))
    : '';
$coach_attendance_deadline_days = isset($coach_attendance_config['deadline_days']) ? (int) $coach_attendance_config['deadline_days'] : 0;
$coach_attendance_help_text = '';
if ($is_coach_attendance_user) {
    $coach_attendance_help_text = 'مربی فقط در روزهای کلاس خود می‌تواند حضور و غیاب ثبت کند.';
    if ($coach_attendance_deadline_days > 0) {
        $coach_attendance_help_text .= ' مهلت ثبت برای هر کلاس ' . $coach_attendance_deadline_days . ' روز بعد از تاریخ کلاس است.';
    } else {
        $coach_attendance_help_text .= ' مهلت ثبت فقط در همان روز کلاس است.';
    }
    if (!empty($coach_attendance_config['allowed_weekdays_text'])) {
        $coach_attendance_help_text .= ' روزهای کلاس این انتخاب: ' . $coach_attendance_config['allowed_weekdays_text'];
    }
}

$attendance_group_required = $selected_course_id > 0
    && function_exists('sc_course_has_grouping_enabled')
    && sc_course_has_grouping_enabled($selected_course_id);
$attendance_ungrouped_member_count = ($selected_course_id > 0 && function_exists('sc_attendance_count_members_without_group'))
    ? sc_attendance_count_members_without_group($selected_course_id)
    : 0;

$pending_members = [];
$recorded_members = [];
foreach ($active_members as $member_item) {
    $member_status = isset($existing_attendances[$member_item->id]) ? $existing_attendances[$member_item->id] : '';
    if ($member_status === 'present' || $member_status === 'absent' || $member_status === 'excused') {
        $recorded_members[] = $member_item;
    } else {
        $pending_members[] = $member_item;
    }
}

if (!function_exists('sc_attendance_render_member_table_row')) {
    /**
     * @param object $member
     * @param int    $row_num
     * @param string $list_type pending|recorded
     */
    function sc_attendance_render_member_table_row($member, $row_num, $list_type, $existing_attendances, $existing_record_methods, $max_debt_for_attendance) {
        $existing_status = isset($existing_attendances[$member->id]) ? $existing_attendances[$member->id] : '';
        $existing_method = isset($existing_record_methods[$member->id]) ? $existing_record_methods[$member->id] : '';
        $field_name = ($list_type === 'recorded') ? 'attendance_recorded' : 'attendance_pending';
        $debt_user = debt_user($member->id)[0];
        $debt_blocked = function_exists('sc_attendance_member_debt_blocked') && sc_attendance_member_debt_blocked($member->id);
        $row_class = 'sc-attendance-member-row';
        if ($debt_blocked) {
            $row_class .= ' sc-attendance-member-row--blocked';
        } elseif ($debt_user > 0) {
            $row_class .= ' sc-attendance-member-row--debt';
        }
        ?>
        <tr class="<?php echo esc_attr($row_class); ?>" data-member-id="<?php echo esc_attr((string) $member->id); ?>" data-list-type="<?php echo esc_attr($list_type); ?>">
            <td><?php echo (int) $row_num; ?></td>
            <td class="sc-attendance-member-name"><?php echo esc_html($member->first_name . ' ' . $member->last_name); ?></td>
            <td class="sc-attendance-member-debt">
                <?php echo number_format($debt_user); ?> تومان
                <?php if ($debt_blocked) : ?>
                    <span class="sc-attendance-debt-alert">سقف موجودی — عدم ثبت رکورد</span>
                <?php endif; ?>
            </td>
            <td class="sc-attendance-record-method-cell">
                <?php if ($existing_status !== '') : ?>
                    <span class="sc-attendance-record-method sc-attendance-record-method--<?php echo esc_attr($existing_method ?: 'manual'); ?>">
                        <?php echo esc_html(function_exists('sc_attendance_record_method_label') ? sc_attendance_record_method_label($existing_method) : $existing_method); ?>
                    </span>
                <?php else : ?>
                    <span class="sc-attendance-record-method sc-attendance-record-method--empty">—</span>
                <?php endif; ?>
            </td>
            <td class="status_attendace_td sc-attendance-status-cell">
                <?php if ($list_type === 'pending' && $existing_status === '') : ?>
                <button type="button"
                        class="button button-small sc-attendance-clear-btn"
                        data-attendance-name="<?php echo esc_attr($field_name . '[' . $member->id . ']'); ?>"
                        title="حذف انتخاب"
                        aria-label="حذف انتخاب">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <?php endif; ?>
                <label class="tooltip-container sc-attendance-status-pill sc-attendance-status-pill--present">
                    <input type="radio"
                           name="<?php echo esc_attr($field_name . '[' . $member->id . ']'); ?>"
                           value="present"
                           <?php checked($existing_status, 'present'); echo ($existing_status === 'excused' || $debt_blocked) ? 'disabled' : ''; ?>>
                    <?php if ($existing_status === 'excused') : ?>
                        <span class="tooltip_text_abset_acc">امکان ثبت تغییر وجود ندارد<br>علت: حالت غیبت مجاز</span>
                    <?php elseif ($debt_blocked) : ?>
                        <span class="tooltip_text_abset_acc">امکان ثبت وجود ندارد<br>علت: بدهی بیش از حد مجاز</span>
                    <?php endif; ?>
                    <span>حاضر</span>
                </label>
                <label class="tooltip-container sc-attendance-status-pill sc-attendance-status-pill--absent">
                    <input type="radio"
                           name="<?php echo esc_attr($field_name . '[' . $member->id . ']'); ?>"
                           value="absent"
                           <?php checked($existing_status, 'absent'); echo ($existing_status === 'excused' || $debt_blocked) ? 'disabled' : ''; ?>>
                    <?php if ($existing_status === 'excused') : ?>
                        <span class="tooltip_text_abset_acc">امکان ثبت تغییر وجود ندارد<br>علت: حالت غیبت مجاز</span>
                    <?php elseif ($debt_blocked) : ?>
                        <span class="tooltip_text_abset_acc">امکان ثبت وجود ندارد<br>علت: بدهی بیش از حد مجاز</span>
                    <?php endif; ?>
                    <span>غایب</span>
                </label>
            </td>
        </tr>
        <?php
    }
}
?>

<div class="wrap sc-attendance-page-header">
    <h1 class="wp-heading-inline">ثبت حضور و غیاب</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-attendance-list'); ?>" class="page-title-action">لیست حضور و غیاب</a>
    <hr class="wp-header-end">
</div>
<div class="wrap sc-attendance-page-body">
    <form method="POST" action="<?php echo esc_url(admin_url('admin.php?page=sc-attendance-add')); ?>" class="form_attendance_add sc-attendance-filter-panel" id="sc-attendance-filter-form">
        <?php wp_nonce_field('sc_attendance_filter', 'sc_attendance_filter_nonce'); ?>
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
                    <input type="hidden" name="attendance_course_id" id="attendance_course_id" value="<?php echo esc_attr($selected_course_id > 0 ? (string) $selected_course_id : ''); ?>">
                    <input type="hidden" name="attendance_chapter" id="attendance_chapter" value="<?php echo esc_attr($selected_chapter_name); ?>">
                    <input type="hidden" name="attendance_group" id="attendance_group" value="<?php echo esc_attr($selected_group_name); ?>">
                    <input type="hidden" id="attendance_course_option_value" value="<?php echo esc_attr($selected_course_value); ?>">

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
                       data-coach-date-restricted="<?php echo esc_attr($is_coach_attendance_user ? '1' : '0'); ?>"
                       data-allowed-ir-weekdays="<?php echo esc_attr($coach_attendance_weekdays_csv); ?>"
                       data-min-gregorian="<?php echo esc_attr($is_coach_attendance_user ? (string) ($coach_attendance_config['min_date'] ?? '') : ''); ?>"
                       data-max-gregorian="<?php echo esc_attr($is_coach_attendance_user ? (string) ($coach_attendance_config['max_date'] ?? '') : ''); ?>"
                       data-restrict-message="<?php echo esc_attr($is_coach_attendance_user ? 'این تاریخ برای ثبت حضور و غیاب مربی مجاز نیست.' : ''); ?>"
                       readonly>
                <input type="hidden" name="date" id="attendance_date_hidden" value="<?php echo esc_attr($selected_date); ?>">
                <p class="description" id="sc-attendance-date-help"><?php echo esc_html($is_coach_attendance_user ? $coach_attendance_help_text : 'برای انتخاب تاریخ، روی فیلد کلیک کنید'); ?></p>
            </div>
        </div>

        <p class="submit sc-attendance-filter-actions">
            <input type="submit" name="sc_attendance_filter" class="button button-primary" value="نمایش فرم">
        </p>
    </form>

    <?php if ($selected_course_id && !empty($coach_attendance_access['message']) && empty($coach_attendance_access['allowed'])) : ?>
        <div class="notice notice-warning"><p><?php echo esc_html($coach_attendance_access['message']); ?></p></div>
    <?php endif; ?>
    
    <?php if ($selected_course_id && !empty($active_members)) : 
        $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM $courses_table WHERE id = %d", $selected_course_id));
        
    ?>
        <form method="POST" action="<?php echo esc_url(function_exists('sc_attendance_add_page_url')
            ? sc_attendance_add_page_url($selected_course_id, $selected_date, $selected_chapter_name, $selected_group_name, isset($_GET['filter_course_type']) ? ['filter_course_type' => sanitize_text_field(wp_unslash($_GET['filter_course_type']))] : [])
            : admin_url('admin.php?page=sc-attendance-add')); ?>" class="sc-attendance-save-form">
            <?php wp_nonce_field('sc_attendance_nonce', 'sc_attendance_nonce'); ?>
            <input type="hidden" name="attendance_course_id" value="<?php echo esc_attr((string) $selected_course_id); ?>">
            <input type="hidden" name="attendance_chapter" value="<?php echo esc_attr($selected_chapter_name); ?>">
            <input type="hidden" name="attendance_group" value="<?php echo esc_attr($selected_group_name); ?>">
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
                    <?php if (!empty($recorded_members)) : ?>
                        <span class="sc-attendance-mode-badge sc-attendance-mode-badge--update"><?php echo count($recorded_members); ?> مورد ثبت‌شده</span>
                    <?php endif; ?>
                    <?php if (!empty($pending_members)) : ?>
                        <span class="sc-attendance-mode-badge sc-attendance-mode-badge--new"><?php echo count($pending_members); ?> مورد در انتظار ثبت</span>
                    <?php endif; ?>
                </div>

                <?php
                $sc_qr_enabled = function_exists('sc_attendance_qr_is_enabled') && sc_attendance_qr_is_enabled();
                $sc_qr_member_map = ($sc_qr_enabled && !empty($active_members) && function_exists('sc_attendance_qr_build_member_lookup_map'))
                    ? sc_attendance_qr_build_member_lookup_map($active_members)
                    : [];
                if ($sc_qr_enabled && !empty($sc_qr_member_map)) {
                    wp_add_inline_script(
                        'sc-attendance-qr-scanner-js',
                        'window.scAttendanceQr = Object.assign(window.scAttendanceQr || {}, ' . wp_json_encode(['memberMap' => $sc_qr_member_map], JSON_UNESCAPED_UNICODE) . ');',
                        'before'
                    );
                }
                if ($sc_qr_enabled) :
                ?>
                <div class="sc-attendance-mode-switch" role="tablist" aria-label="روش ثبت حضور">
                    <label class="sc-attendance-mode-switch__option">
                        <input type="radio" name="sc_attendance_mode" value="list" checked>
                        <span>لیست دستی</span>
                    </label>
                    <label class="sc-attendance-mode-switch__option">
                        <input type="radio" name="sc_attendance_mode" value="qr">
                        <span>اسکن QR</span>
                    </label>
                </div>

                <div id="sc-attendance-mode-qr" class="sc-attendance-mode-panel" style="display:none;">
                    <div id="sc-attendance-qr-panel" class="sc-attendance-qr-panel">
                        <div class="sc-attendance-qr-panel__main">
                            <div class="sc-attendance-qr-camera-wrap">
                                <div id="sc-attendance-qr-reader" class="sc-attendance-qr-reader" aria-label="دوربین اسکن QR"></div>
                                <div id="sc-attendance-qr-toast" class="sc-attendance-qr-toast" aria-live="polite">
                                    <span class="sc-attendance-qr-toast__text">برای شروع، دکمه «شروع اسکن» را بزنید</span>
                                </div>
                            </div>
                            <div class="sc-attendance-qr-actions">
                                <button type="button" class="button button-primary button-large" id="sc-attendance-qr-start">شروع اسکن</button>
                                <button type="button" class="button button-large" id="sc-attendance-qr-switch" disabled title="تغییر بین دوربین جلو و عقب">تغییر دوربین</button>
                                <button type="button" class="button button-large" id="sc-attendance-qr-stop" disabled>توقف</button>
                            </div>
                            <p class="sc-attendance-qr-hint">پیش‌فرض دوربین عقب است. اگر تصویر اشتباه بود از دکمه «تغییر دوربین» استفاده کنید.</p>
                        </div>
                        <aside class="sc-attendance-qr-sidebar">
                            <div class="sc-attendance-qr-stat">
                                <span class="sc-attendance-qr-stat__label">ثبت‌شده در این جلسه</span>
                                <strong class="sc-attendance-qr-stat__value" id="sc-attendance-qr-count">0</strong>
                            </div>
                            <h3 class="sc-attendance-qr-log-title">آخرین اسکن‌ها</h3>
                            <ul id="sc-attendance-qr-log" class="sc-attendance-qr-log"></ul>
                        </aside>
                    </div>
                </div>

                <div id="sc-attendance-mode-list" class="sc-attendance-mode-panel">
                <?php endif; ?>

                <?php $max_debt_for_attendance = floatval(sc_get_setting('max_debt_for_attendance', '0')); ?>

                <div class="sc-attendance-list-section sc-attendance-list-section--pending">
                    <div class="sc-attendance-list-section__head">
                        <h3 class="sc-attendance-list-section__title">در انتظار ثبت</h3>
                        <span class="sc-attendance-list-section__count" id="sc-attendance-pending-count"><?php echo count($pending_members); ?> نفر</span>
                    </div>
                    <p class="sc-attendance-list-section__desc">بازیکنانی که هنوز وضعیت حاضر یا غایب برایشان ثبت نشده است.</p>
                    <div class="back_attendance_list sc-attendance-members-wrap">
                        <table class="wp-list-table widefat fixed striped sc-attendance-members-table sc-attendance-members-table--pending"<?php echo empty($pending_members) ? ' style="display:none"' : ''; ?> id="sc-attendance-pending-table">
                            <colgroup>
                                <col class="sc-att-col-row">
                                <col class="sc-att-col-name">
                                <col class="sc-att-col-debt">
                                <col class="sc-att-col-method">
                                <col class="sc-att-col-status">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="column-row sc-att-col-row">ردیف</th>
                                    <th class="sc-att-col-name">نام و نام خانوادگی</th>
                                    <th class="sc-att-col-debt">مبلغ بدهی</th>
                                    <th class="sc-att-col-method">روش ثبت</th>
                                    <th class="sc-att-col-status">وضعیت</th>
                                </tr>
                            </thead>
                            <tbody id="sc-attendance-pending-tbody">
                            <?php foreach ($pending_members as $index => $member) :
                                sc_attendance_render_member_table_row($member, $index + 1, 'pending', $existing_attendances, $existing_record_methods, $max_debt_for_attendance);
                            endforeach; ?>
                            </tbody>
                        </table>
                        <div class="sc-attendance-list-empty" id="sc-attendance-pending-empty"<?php echo empty($pending_members) ? '' : ' style="display:none"'; ?>>همه بازیکنان این جلسه ثبت شده‌اند.</div>
                    </div>
                    <p class="submit sc-attendance-save-actions sc-attendance-save-actions--pending">
                        <button type="submit" name="sc_save_attendance_pending" class="button button-primary button-large sc-attendance-save-btn sc-attendance-save-btn--new" id="sc-attendance-save-pending-btn"<?php echo empty($pending_members) ? ' disabled' : ''; ?>>
                            ثبت حضور و غیاب جدید
                        </button>
                    </p>
                </div>

                <div class="sc-attendance-list-section sc-attendance-list-section--recorded">
                    <div class="sc-attendance-list-section__head">
                        <h3 class="sc-attendance-list-section__title">ثبت‌شده — بروزرسانی</h3>
                        <span class="sc-attendance-list-section__count" id="sc-attendance-recorded-count"><?php echo count($recorded_members); ?> نفر</span>
                    </div>
                    <p class="sc-attendance-list-section__desc">بازیکنانی که وضعیت حاضر یا غایب دارند؛ از جمله موارد ثبت‌شده با QR.</p>
                    <div class="back_attendance_list sc-attendance-members-wrap">
                        <table class="wp-list-table widefat fixed striped sc-attendance-members-table sc-attendance-members-table--recorded"<?php echo empty($recorded_members) ? ' style="display:none"' : ''; ?> id="sc-attendance-recorded-table">
                            <colgroup>
                                <col class="sc-att-col-row">
                                <col class="sc-att-col-name">
                                <col class="sc-att-col-debt">
                                <col class="sc-att-col-method">
                                <col class="sc-att-col-status">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="column-row sc-att-col-row">ردیف</th>
                                    <th class="sc-att-col-name">نام و نام خانوادگی</th>
                                    <th class="sc-att-col-debt">مبلغ بدهی</th>
                                    <th class="sc-att-col-method">روش ثبت</th>
                                    <th class="sc-att-col-status">وضعیت</th>
                                </tr>
                            </thead>
                            <tbody id="sc-attendance-recorded-tbody">
                            <?php foreach ($recorded_members as $index => $member) :
                                sc_attendance_render_member_table_row($member, $index + 1, 'recorded', $existing_attendances, $existing_record_methods, $max_debt_for_attendance);
                            endforeach; ?>
                            </tbody>
                        </table>
                        <div class="sc-attendance-list-empty" id="sc-attendance-recorded-empty"<?php echo empty($recorded_members) ? '' : ' style="display:none"'; ?>>هنوز موردی ثبت نشده است.</div>
                    </div>
                    <p class="submit sc-attendance-save-actions sc-attendance-save-actions--recorded">
                        <button type="submit" name="sc_save_attendance_recorded" class="button button-large sc-attendance-save-btn sc-attendance-save-btn--update" id="sc-attendance-save-recorded-btn"<?php echo empty($recorded_members) ? ' disabled' : ''; ?>>
                            بروزرسانی حضور و غیاب
                        </button>
                    </p>
                </div>

                <?php if ($sc_qr_enabled) : ?>
                </div><!-- #sc-attendance-mode-list -->
                <?php endif; ?>
            </div>
        </form>
    <?php elseif ($selected_course_id && empty($active_members) && !empty($coach_attendance_access['allowed'])) : ?>
        <div class="notice notice-info sc-attendance-empty-notice">
            <p>
                <?php if ($attendance_group_required && $selected_group_name === '') : ?>
                    این دوره دارای گروه‌بندی است. لطفاً یک گروه مشخص را از لیست دوره‌ها انتخاب کنید.
                <?php elseif ($attendance_group_required && $selected_group_name !== '') : ?>
                    در گروه «<?php echo esc_html($selected_group_name); ?>» هیچ بازیکن فعالی ثبت‌نام نشده است.
                <?php elseif ($selected_chapter_name !== '') : ?>
                    در این دوره و شعبه هیچ کاربر فعالی ثبت‌نام نشده است.
                <?php else : ?>
                    در این دوره هیچ کاربر فعالی ثبت‌نام نشده است.
                <?php endif; ?>
            </p>
            <?php if ($attendance_ungrouped_member_count > 0) : ?>
                <p>
                    <?php echo esc_html(sprintf(
                        'توجه: %d بازیکن فعال این دوره گروه مشخصی ندارند و در هیچ گروهی نمایش داده نمی‌شوند. از بخش ویرایش بازیکن، گروه آن‌ها را تعیین کنید.',
                        $attendance_ungrouped_member_count
                    )); ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
window.scAttendanceCoachDateRules = <?php echo wp_json_encode($coach_attendance_rules_map, JSON_UNESCAPED_UNICODE); ?>;
window.scAttendanceCoachDateRestrictEnabled = <?php echo $is_coach_attendance_user ? 'true' : 'false'; ?>;

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
    function scAttendanceApplyCoachDateRules(optionValue) {
        if (!window.scAttendanceCoachDateRestrictEnabled) {
            return;
        }

        var rulesMap = window.scAttendanceCoachDateRules || {};
        var rules = (optionValue && rulesMap[optionValue]) ? rulesMap[optionValue] : null;
        var $dateInput = $('#attendance_date');
        var $help = $('#sc-attendance-date-help');

        if (!$dateInput.length) {
            return;
        }

        if (!rules) {
            $dateInput.attr('data-coach-date-restricted', '1');
            $dateInput.attr('data-allowed-ir-weekdays', '');
            $dateInput.attr('data-min-gregorian', '');
            $dateInput.attr('data-max-gregorian', '');
            $dateInput.attr('data-restrict-message', 'این تاریخ برای ثبت حضور و غیاب مربی مجاز نیست.');
            if ($help.length) {
                $help.text('ابتدا دوره را انتخاب کنید تا فقط روزهای مجاز برای مربی نمایش داده شود.');
            }
            return;
        }

        var weekdays = Array.isArray(rules.allowed_weekdays) ? rules.allowed_weekdays.join(',') : '';
        $dateInput.attr('data-coach-date-restricted', '1');
        $dateInput.attr('data-allowed-ir-weekdays', weekdays);
        $dateInput.attr('data-min-gregorian', rules.min_date || '');
        $dateInput.attr('data-max-gregorian', rules.max_date || '');
        $dateInput.attr('data-restrict-message', 'این تاریخ برای ثبت حضور و غیاب مربی مجاز نیست.');
        $dateInput.data('coachMode', 1);

        if ($help.length) {
            var helpText = 'مربی فقط در روزهای کلاس خود می‌تواند حضور و غیاب ثبت کند.';
            if (parseInt(rules.deadline_days || 0, 10) > 0) {
                helpText += ' مهلت ثبت: ' + rules.deadline_days + ' روز بعد از کلاس.';
            } else {
                helpText += ' مهلت ثبت فقط همان روز کلاس است.';
            }
            if (rules.allowed_weekdays_text) {
                helpText += ' روزهای کلاس: ' + rules.allowed_weekdays_text;
            }
            $help.text(helpText);
        }
    }

    function scAttendanceGetSelectedCourseType() {
        var $checked = $('input[name="filter_course_type"]:checked');
        return $checked.length ? $checked.val() : 'group';
    }

    function scAttendanceResetCourseDropdown() {
        var $dropdown = $('.sc-attendance-course-dropdown');
        scAttendanceApplyCourseFields($dropdown, '', '');
        $dropdown.find('.sc-option-check').remove();
        $dropdown.find('.sc-dropdown-option').removeClass('sc-selected').css('background', '');
        scAttendanceApplyCoachDateRules('');
    }

    function scAttendanceSyncCourseDropdown(searchTerm) {
        var type = scAttendanceGetSelectedCourseType();
        var term = (searchTerm || '').toLowerCase().trim();
        var $dropdown = $('.sc-attendance-course-dropdown');
        var $options = $dropdown.find('.sc-dropdown-option');
        var visibleCount = 0;
        var maxVisible = 10;
        var currentVal = $dropdown.find('#attendance_course_option_value').val();
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
    scAttendanceApplyCoachDateRules($('#attendance_course_option_value').val() || '');

    $('input[name="filter_course_type"]').on('change', function() {
        $('.sc-attendance-course-search').val('');
        scAttendanceResetCourseDropdown();
        scAttendanceSyncCourseDropdown('');
    });

    $('.sc-attendance-course-search').on('input', function() {
        scAttendanceSyncCourseDropdown($(this).val() || '');
    });

    $(document).on('click', '.sc-attendance-course-dropdown .sc-dropdown-option', function() {
        var optionValue = $(this).attr('data-value') || '';
        setTimeout(function() {
            scAttendanceApplyCoachDateRules(optionValue);
        }, 0);
    });

    $('#sc-attendance-filter-form').on('submit', function(e) {
        var courseVal = $('#attendance_course_id').val();
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

