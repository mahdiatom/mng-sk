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
            $saved_count = 0;
            $updated_count = 0;
            
            foreach ($attendances as $member_id => $status) {
                $member_id = absint($member_id);
                $status = ($status === 'present') ? 'present' : 'absent';
                
                if (!$member_id) {
                    continue;
                }
                
                // بررسی وجود رکورد قبلی
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $attendances_table 
                     WHERE member_id = %d AND course_id = %d AND attendance_date = %s",
                    $member_id,
                    $course_id,
                    $attendance_date
                ));
                
                $data = [
                    'member_id' => $member_id,
                    'course_id' => $course_id,
                    'attendance_date' => $attendance_date,
                    'status' => $status,
                    'updated_at' => current_time('mysql')
                ];
                
                if ($existing) {
                    // Get current status before update
                    $current_record = $wpdb->get_row($wpdb->prepare(
                        "SELECT status, absence_sms_sent FROM $attendances_table WHERE id = %d",
                        $existing
                    ));

                    // بروزرسانی رکورد موجود
                    $update_data = [
                        'status' => $status,
                        'updated_at' => current_time('mysql')
                    ];

                    // If changing from absent to present, reset SMS flag
                    if ($current_record && $current_record->status == 'absent' && $status == 'present') {
                        $update_data['absence_sms_sent'] = 0;
                    }

                    $wpdb->update(
                        $attendances_table,
                        $update_data,
                        [
                            'id' => $existing
                        ],
                        array_fill(0, count($update_data), '%s'),
                        ['%d']
                    );
                    $updated_count++;

                    // اگر غیبت باشد، هوک ارسال شود
                    if ($status === 'absent') {
                        do_action('sc_attendance_absent', $existing);
                    }
                } else {
                    // ایجاد رکورد جدید
                    $data['created_at'] = current_time('mysql');
                    $inserted_id = $wpdb->insert(
                        $attendances_table,
                        $data,
                        ['%d', '%d', '%s', '%s', '%s', '%s']
                    );

                    if ($inserted_id) {
                        $attendance_id = $wpdb->insert_id;
                    $saved_count++;

                        // اگر غیبت باشد، هوک ارسال شود
                        if ($status === 'absent') {
                            do_action('sc_attendance_absent', $attendance_id);
                        }
                    }
                }
            }
            
            if ($saved_count > 0 || $updated_count > 0) {
                $message = sprintf(
                    'حضور و غیاب با موفقیت ثبت شد. (%d مورد جدید، %d مورد بروزرسانی)',
                    $saved_count,
                    $updated_count
                );
                $message_type = 'success';
            } else {
                $message = 'خطا در ثبت حضور و غیاب.';
                $message_type = 'error';
            }
        }
    }
    
    if (isset($message)) {
        echo '<div class="notice notice-' . esc_attr($message_type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}

// دریافت تمام دوره‌های فعال
$courses = $wpdb->get_results(
    "SELECT * FROM $courses_table 
     WHERE deleted_at IS NULL AND is_active = 1 
     ORDER BY title ASC"
);

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
    // دریافت کاربران فعال دوره
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
    
    <form method="GET" action="" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
        <input type="hidden" name="page" value="sc-attendance-add">
        
        <table class="form-table sc_form-table">
            <tr>
                <th scope="row">
                    <label for="course_id">انتخاب دوره</label>
                </th>
                <td>
                    <select name="course_id" id="course_id" required style="width: 300px; padding: 5px;">
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
                           style="width: 300px; padding: 5px;">
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
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <h2 style="margin-top: 0;">
                    ثبت حضور و غیاب - <?php echo esc_html($course->title); ?>
                    <span style="font-size: 16px; font-weight: normal; color: #666;">(<?php echo sc_date_shamsi($selected_date, 'l j F Y'); ?>)</span>
                    <?php if ($is_update_mode): ?>
                        <span style="margin-right:10px;color:#d63638;font-weight:bold;">شما در حال بروزرسانی یک حضور و غیاب هستید.</span>
                    <?php else: ?>
                        <span style="margin-right:10px;color:#00a32a;font-weight:bold;">
شما در حال ثبت یک حضور غیاب جدید هستید.                        </span>
                    <?php endif; ?>
                </h2>
                
                <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th class="column-row">ردیف</th>
                            <th>نام</th>
                            <th>نام خانوادگی</th>
                            <th>شناسه بازیکن</th>
                            <th style="width: 200px;">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_members as $index => $member) : 
                            $existing_status = isset($existing_attendances[$member->id]) ? $existing_attendances[$member->id] : 'present';
                        ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo esc_html($member->first_name); ?></td>
                                <td><?php echo esc_html($member->last_name); ?></td>
                                <td><?php echo esc_html($member->id); ?></td>
                                <td>
                                    <label style="display: inline-block; margin-left: 20px;">
                                        <input type="radio" 
                                               name="attendance[<?php echo esc_attr($member->id); ?>]" 
                                               value="present" 
                                               <?php checked($existing_status, 'present'); ?> 
                                               required>
                                        <span style="color: #00a32a; font-weight: bold;">حاضر</span>
                                    </label>
                                    <label style="display: inline-block; margin-left: 20px;">
                                        <input type="radio" 
                                               name="attendance[<?php echo esc_attr($member->id); ?>]" 
                                               value="absent"
                                               <?php checked($existing_status, 'absent'); ?> 
                                               required>
                                        <span style="color: #d63638; font-weight: bold;">غایب</span>
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

