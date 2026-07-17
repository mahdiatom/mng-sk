<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$attendances_table = $wpdb->prefix . 'sc_attendances';
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
if (!function_exists('sc_attendance_where_coach_member_scope_list')) {
    /**
     * شرط SQL برای محدود کردن ردیف‌های حضور به بازیکنان همین مربی یا بدون انتساب.
     *
     * @param int $coach_id
     * @return string
     */
    function sc_attendance_where_coach_member_scope_list($coach_id) {
        global $wpdb;
        $mc = $wpdb->prefix . 'sc_member_courses';
        $coach_id = absint($coach_id);
        if (!$coach_id) {
            return '1=1';
        }

        $scope_coach_ids = [$coach_id];
        if (function_exists('sc_assistant_coach_attendance_enabled') && sc_assistant_coach_attendance_enabled()
            && function_exists('sc_course_assistant_coaches_table_ready') && sc_course_assistant_coaches_table_ready()) {
            $assistant_table = sc_course_assistant_coaches_table();
            $primary_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT primary_coach_id FROM `$assistant_table` WHERE assistant_coach_id = %d",
                $coach_id
            ));
            $scope_coach_ids = array_merge($scope_coach_ids, array_map('absint', (array) $primary_ids));
        }
        $scope_coach_ids = array_values(array_unique(array_filter($scope_coach_ids)));

        if (count($scope_coach_ids) === 1) {
            return $wpdb->prepare(
                "EXISTS (SELECT 1 FROM `$mc` mc WHERE mc.member_id = a.member_id AND mc.course_id = a.course_id AND mc.status = %s AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '' OR (mc.course_status_flags NOT LIKE %s AND mc.course_status_flags NOT LIKE %s AND mc.course_status_flags NOT LIKE %s)) AND (mc.coach_id = %d OR mc.coach_id IS NULL OR mc.coach_id = 0))",
                'active',
                '%paused%',
                '%completed%',
                '%canceled%',
                $scope_coach_ids[0]
            );
        }

        $placeholders = implode(',', array_fill(0, count($scope_coach_ids), '%d'));
        return $wpdb->prepare(
            "EXISTS (SELECT 1 FROM `$mc` mc WHERE mc.member_id = a.member_id AND mc.course_id = a.course_id AND mc.status = %s AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '' OR (mc.course_status_flags NOT LIKE %s AND mc.course_status_flags NOT LIKE %s AND mc.course_status_flags NOT LIKE %s)) AND (mc.coach_id IN ($placeholders) OR mc.coach_id IS NULL OR mc.coach_id = 0))",
            array_merge(
                ['active', '%paused%', '%completed%', '%canceled%'],
                $scope_coach_ids
            )
        );
    }
}

$sc_attendance_recorded_by_name_expr = function_exists('sc_attendance_recorded_by_name_sql')
    ? sc_attendance_recorded_by_name_sql()
    : "COALESCE(CONCAT(rec_coach.first_name, ' ', rec_coach.last_name), rec_user.display_name, '-')";

// دریافت تب فعال
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'individual';

$attendance_list_page = (!empty($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'sc-attendance-list_report')
    ? 'sc-attendance-list_report'
    : 'sc-attendance-list';
$can_manage_attendance = function_exists('sc_user_can_manage_attendance') && sc_user_can_manage_attendance();
$can_delete_or_justify_attendance = function_exists('sc_user_can_delete_or_justify_attendance') && sc_user_can_delete_or_justify_attendance();

// پردازش حذف (فقط برای تب اول)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['attendance_id']) && $active_tab === 'individual') {
    if (!$can_delete_or_justify_attendance) {
        echo '<div class="notice notice-error is-dismissible"><p>شما دسترسی لازم برای حذف حضور و غیاب را ندارید.</p></div>';
    } else {
    check_admin_referer('delete_attendance_' . $_GET['attendance_id']);

    $attendance_id = absint($_GET['attendance_id']);
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT member_id, course_id, attendance_date, status FROM $attendances_table WHERE id = %d LIMIT 1",
        $attendance_id
    ));

    if ($row && function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance()) {
        $coach_row = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sc_coaches WHERE user_id = %d LIMIT 1",
            get_current_user_id()
        ));
        $coach_id_for_edit = $coach_row ? (int) $coach_row->id : 0;
        if ($coach_id_for_edit > 0 && function_exists('sc_validate_coach_attendance_date_access')) {
            $access = sc_validate_coach_attendance_date_access($coach_id_for_edit, (int) $row->course_id, (string) $row->attendance_date);
            if (empty($access['allowed'])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($access['message']) . '</p></div>';
                $row = null;
                $deleted = false;
            }
        }
    }

    if (isset($deleted) && $deleted === false) {
        // دسترسی مربی برای ویرایش این تاریخ بسته شده است
    } else {





        if (
            $row &&
            ($row->status === 'present' || $row->status === 'absent') &&
            sc_is_member_team($row->member_id) &&
            (function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet())
        ) {
            $course_row = $wpdb->get_row($wpdb->prepare(
                "SELECT title, price_per_session FROM $courses_table WHERE id = %d LIMIT 1",
                $row->course_id
            ));
            if ($course_row && floatval($course_row->price_per_session) > 0) {
                sc_refund_wallet_session_fee(
                    $row->member_id,
                    floatval($course_row->price_per_session),
                    $course_row->title,
                    sc_date_shamsi_date_only($row->attendance_date)
                );
            }
        }








    // بازگردانی یک جلسه بعد از حذف رکورد حضور یا غیاب
if ($row && ($row->status === 'present' || $row->status === 'absent')) {
    sc_increase_member_session($row->member_id, $row->course_id);
}
    $deleted = $wpdb->delete(
        $attendances_table,
        ['id' => $attendance_id],
        ['%d']
    );

    if ($deleted && $row && $row->status === 'present' && function_exists('sc_refresh_coach_percentage_salary_for_course_date')) {
        sc_refresh_coach_percentage_salary_for_course_date((int) $row->course_id, (string) $row->attendance_date);
    }

    if ($deleted) {
        echo '<div class="notice notice-success is-dismissible"><p>حضور و غیاب با موفقیت حذف شد.' . ( ($row && $row->status === 'present' && sc_is_member_team($row->member_id) && (function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet())) ? ' مبلغ جلسه به کیف پول برگشت داده شد.' : '' ) . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>خطا در حذف حضور و غیاب.</p></div>';
    }
    }
    }
}

// --- بخش جدید: پردازش مجاز کردن غیبت ---
if (isset($_GET['action']) && $_GET['action'] === 'justify' && isset($_GET['attendance_id']) && $active_tab === 'individual') {
    if (!$can_delete_or_justify_attendance) {
        echo '<div class="notice notice-error is-dismissible"><p>شما دسترسی لازم برای مجاز کردن غیبت را ندارید.</p></div>';
    } else {
    check_admin_referer('justify_attendance_' . $_GET['attendance_id']);

    $attendance_id = absint($_GET['attendance_id']);
    
    // دریافت اطلاعات رکورد
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, member_id, course_id, attendance_date  ,status FROM $attendances_table WHERE id = %d LIMIT 1",
        $attendance_id
    ));

    if ($row && function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance()) {
        $coach_row = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sc_coaches WHERE user_id = %d LIMIT 1",
            get_current_user_id()
        ));
        $coach_id_for_justify = $coach_row ? (int) $coach_row->id : 0;
        if ($coach_id_for_justify > 0 && function_exists('sc_validate_coach_attendance_date_access')) {
            $access = sc_validate_coach_attendance_date_access($coach_id_for_justify, (int) $row->course_id, (string) $row->attendance_date);
            if (empty($access['allowed'])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($access['message']) . '</p></div>';
                $row = null;
            }
        }
    }

    // فقط اگر وضعیت "غایب" باشد عملیات انجام شود
    if ($row && $row->status === 'absent') {

                // refund برای تبدیل غیبت به غیبت مجاز
        $course_row = $wpdb->get_row($wpdb->prepare(
            "SELECT title, price_per_session FROM $courses_table WHERE id = %d LIMIT 1",
            $row->course_id
        ));

        if (
            $course_row &&
            floatval($course_row->price_per_session) > 0 &&
            sc_is_member_team($row->member_id) &&
            (function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet())
        ) {
            sc_refund_wallet_session_fee(
                $row->member_id,
                floatval($course_row->price_per_session),
                $course_row->title,
                sc_date_shamsi_date_only($row->attendance_date)
            );
        }

        
        // ۱. بازگرداندن یک جلسه به موجودی کاربر
        $increased = sc_increase_member_session($row->member_id, $row->course_id);

        if ($increased) {
            // ۲. تغییر وضعیت رکورد به "excused" (غیبت مجاز) برای اینکه دکمه غیب شود و دوباره نشود زد
            $wpdb->update(
                $attendances_table,
                ['status' => 'excused'], // وضعیت جدید
                ['id' => $attendance_id],
                ['%s'],
                ['%d']
            );

            echo '<div class="notice notice-success is-dismissible"><p>غیبت با موفقیت مجاز شد و یک جلسه به کاربر بازگردانده شد.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>خطا در بازگرداندن جلسه. ممکن است دوره کاربر فعال نباشد.</p></div>';
        }
    } else {
        echo '<div class="notice notice-warning is-dismissible"><p>این رکورد غایب نیست یا قبلاً مجاز شده است.</p></div>';
    }
    }
}



// دریافت لیست دوره‌ها و اعضا برای فیلترها
// منشی: فقط دوره‌های شعبه؛ مربی: فقط دوره‌های خودش
$current_user_id = get_current_user_id();
$current_user = wp_get_current_user();

if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
    && function_exists('sc_secretary_get_branch_courses_for_attendance_filter')) {
    $courses = sc_secretary_get_branch_courses_for_attendance_filter();
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
        $accessible_course_ids = function_exists('sc_coach_attendance_accessible_course_ids')
            ? sc_coach_attendance_accessible_course_ids((int) $coach_id)
            : [];
        if (!empty($accessible_course_ids)) {
            $placeholders_courses = implode(',', array_fill(0, count($accessible_course_ids), '%d'));
            $courses = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT c.id, c.title
                 FROM $courses_table c
                 WHERE c.id IN ($placeholders_courses)
                 AND c.deleted_at IS NULL
                 AND c.is_active = 1
                 ORDER BY c.title ASC",
                $accessible_course_ids
            ));
        } else {
            $courses = $wpdb->get_results($wpdb->prepare(
                "SELECT c.id, c.title 
                 FROM $courses_table c
                 INNER JOIN $course_coaches_table cc ON c.id = cc.course_id
                 WHERE cc.coach_id = %d
                 AND c.deleted_at IS NULL 
                 AND c.is_active = 1 
                 ORDER BY c.title ASC",
                $coach_id
            ));
        }
    } else {
        // اگر مربی در جدول coaches وجود نداشت، لیست خالی
        $courses = [];
    }
} else {
    // کاربر مدیر است - همه دوره‌های فعال را نمایش بده
    $courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");
}

$coach_scope_members_list_id = 0;
if (function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance()) {
    $_cm_coach_row = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}sc_coaches WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    if ($_cm_coach_row) {
        $coach_scope_members_list_id = (int) $_cm_coach_row->id;
    }
}

if ($coach_scope_members_list_id > 0) {
    $_ccb_ml = $wpdb->prefix . 'sc_course_coaches';
    $_mcb_ml = $wpdb->prefix . 'sc_member_courses';
    $_coach_course_ids_ml = function_exists('sc_coach_attendance_accessible_course_ids')
        ? sc_coach_attendance_accessible_course_ids($coach_scope_members_list_id)
        : $wpdb->get_col($wpdb->prepare(
            "SELECT course_id FROM $_ccb_ml WHERE coach_id = %d",
            $coach_scope_members_list_id
        ));
    $_scope_coach_ids_ml = [$coach_scope_members_list_id];
    if (function_exists('sc_assistant_coach_attendance_enabled') && sc_assistant_coach_attendance_enabled()
        && function_exists('sc_course_assistant_coaches_table_ready') && sc_course_assistant_coaches_table_ready()) {
        $assistant_table_ml = sc_course_assistant_coaches_table();
        $primary_ids_ml = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT primary_coach_id FROM `$assistant_table_ml` WHERE assistant_coach_id = %d",
            $coach_scope_members_list_id
        ));
        $_scope_coach_ids_ml = array_values(array_unique(array_merge($_scope_coach_ids_ml, array_map('absint', (array) $primary_ids_ml))));
    }
    if (!empty($_coach_course_ids_ml)) {
        $_ph_ml = implode(',', array_fill(0, count($_coach_course_ids_ml), '%d'));
        $_ph_scope_ml = implode(',', array_fill(0, count($_scope_coach_ids_ml), '%d'));
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT m.id, m.first_name, m.last_name, m.national_id
             FROM $members_table m
             INNER JOIN $_mcb_ml mc ON mc.member_id = m.id
             WHERE m.is_active = 1
               AND mc.course_id IN ($_ph_ml)
               AND mc.status = 'active'
               AND (mc.coach_id IN ($_ph_scope_ml) OR mc.coach_id IS NULL OR mc.coach_id = 0)
               AND (
                 mc.course_status_flags IS NULL OR mc.course_status_flags = ''
                 OR (
                   mc.course_status_flags NOT LIKE %s
                   AND mc.course_status_flags NOT LIKE %s
                   AND mc.course_status_flags NOT LIKE %s
                 )
               )
             ORDER BY m.last_name ASC, m.first_name ASC",
            array_merge(
                $_coach_course_ids_ml,
                $_scope_coach_ids_ml,
                [
                    '%paused%',
                    '%completed%',
                    '%canceled%',
                ]
            )
        ));
    } else {
        $members = [];
    }
} elseif (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
    && function_exists('sc_secretary_append_member_where')) {
    $pw = sc_secretary_append_member_where('m.is_active = 1', 'm');
    $members = $wpdb->get_results(
        "SELECT DISTINCT m.id, m.first_name, m.last_name, m.national_id
         FROM $members_table m
         WHERE {$pw}
         ORDER BY m.last_name ASC, m.first_name ASC"
    );
} else {
    $members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
}

$coaches_table = $wpdb->prefix . 'sc_coaches';
// لیست مربیان برای فیلتر (فقط برای مدیر باشگاه)
$coaches_list = [];
if (current_user_can('club_coach') || current_user_can('administrator')) {
    $coaches_list = $wpdb->get_results("SELECT id, first_name, last_name, user_id FROM $coaches_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
}

// ==================== تب 1: لیست حضور و غیاب کاربران ====================
if ($active_tab === 'individual') {
    // دریافت فیلترها
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    $filter_coach = (current_user_can('club_coach') || current_user_can('administrator')) && isset($_GET['filter_coach']) ? absint($_GET['filter_coach']) : 0;
    $filter_record_method = isset($_GET['filter_record_method']) ? sanitize_key(wp_unslash($_GET['filter_record_method'])) : '';
    
    // پردازش فیلترهای تاریخ (شمسی به میلادی)
    $filter_date_from = '';
    $filter_date_to = '';
    $filter_date_from_shamsi = '';
    $filter_date_to_shamsi = '';
    
    if (isset($_GET['filter_date_from_shamsi']) && !empty($_GET['filter_date_from_shamsi'])) {
        $filter_date_from_shamsi = sanitize_text_field($_GET['filter_date_from_shamsi']);
        $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
    } elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
        $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
        $filter_date_from_shamsi = sc_date_shamsi_date_only($filter_date_from);
    }
    
    if (isset($_GET['filter_date_to_shamsi']) && !empty($_GET['filter_date_to_shamsi'])) {
        $filter_date_to_shamsi = sanitize_text_field($_GET['filter_date_to_shamsi']);
        $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
    } elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
        $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
        $filter_date_to_shamsi = sc_date_shamsi_date_only($filter_date_to);
    }
    
    // فقط برای نمایش: وقتی کاربر تاریخی نفرستاده، امروز در فیلدها نشان بده (در فیلتر اعمال نمی‌شود)
    $today_shamsi_tab1 = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
    if (!$today_shamsi_tab1 && function_exists('gregorian_to_jalali')) {
        $today = new DateTime(current_time('Y-m-d'));
        $jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
        $today_shamsi_tab1 = $jalali[0] . '/' . str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($jalali[2], 2, '0', STR_PAD_LEFT);
    }
    $display_date_from_shamsi_tab1 = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $today_shamsi_tab1;
    $display_date_to_shamsi_tab1   = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $today_shamsi_tab1;
    
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';

    // ساخت WHERE clause
    $where_conditions = ['1=1'];
    $where_values = [];
    
    if (function_exists('sc_secretary_append_attendance_course_scope') && function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()) {
        sc_secretary_append_attendance_course_scope($where_conditions, $where_values);
    } elseif (function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance()) {
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
        
        // دریافت coach_id از user_id
        $coach = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
            $current_user_id
        ));
        
        if ($coach) {
            $coach_id = $coach->id;
            // دریافت لیست course_id های مربی
            $coach_course_ids = function_exists('sc_coach_attendance_accessible_course_ids')
                ? sc_coach_attendance_accessible_course_ids((int) $coach_id)
                : $wpdb->get_col($wpdb->prepare(
                "SELECT course_id FROM $course_coaches_table WHERE coach_id = %d",
                $coach_id
            ));
            
            if (!empty($coach_course_ids)) {
                $placeholders = implode(',', array_fill(0, count($coach_course_ids), '%d'));
                $where_conditions[] = "a.course_id IN ($placeholders)";
                $where_values = array_merge($where_values, $coach_course_ids);
                $where_conditions[] = sc_attendance_where_coach_member_scope_list((int) $coach_id);
            } else {
                // اگر مربی هیچ دوره‌ای نداشت، هیچ رکوردی نمایش داده نشود
                $where_conditions[] = "1=0";
            }
        } else {
            // اگر مربی در جدول coaches وجود نداشت، هیچ رکوردی نمایش داده نشود
            $where_conditions[] = "1=0";
        }
    }

    if ($filter_course > 0) {
        $where_conditions[] = "a.course_id = %d";
        $where_values[] = $filter_course;
    }

    if ($filter_member > 0) {
        $where_conditions[] = "a.member_id = %d";
        $where_values[] = $filter_member;
    }

    if ($filter_date_from) {
        $where_conditions[] = "a.attendance_date >= %s";
        $where_values[] = $filter_date_from;
    }

    if ($filter_date_to) {
        $where_conditions[] = "a.attendance_date <= %s";
        $where_values[] = $filter_date_to;
    }

    if ($filter_status !== 'all') {
        $where_conditions[] = "a.status = %s";
        $where_values[] = $filter_status;
    }

    // فیلتر مربی (فقط برای مدیر باشگاه)
    if ($filter_coach > 0) {
        $coach_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $coaches_table WHERE id = %d LIMIT 1",
            $filter_coach
        ));
        if ($coach_user_id) {
            $where_conditions[] = "a.user_id = %d";
            $where_values[] = $coach_user_id;
        }
    }

    if (function_exists('sc_attendance_apply_record_method_filter')) {
        sc_attendance_apply_record_method_filter($where_conditions, $where_values, $filter_record_method);
    }

    $where_clause = implode(' AND ', $where_conditions);

    // دریافت تعداد کل رکوردها برای pagination (با همان JOIN‌های کوئری اصلی)
    $users_table = $wpdb->users;
    $total_query = "SELECT COUNT(*) 
                    FROM $attendances_table a
                    INNER JOIN $members_table m ON a.member_id = m.id
                    INNER JOIN $courses_table c ON a.course_id = c.id
                    LEFT JOIN $coaches_table rec_coach ON rec_coach.user_id = a.user_id
                    LEFT JOIN $users_table rec_user ON rec_user.ID = a.user_id
                    WHERE $where_clause";
    if (!empty($where_values)) {
        $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
    } else {
        $total_items = $wpdb->get_var($total_query);
    }

    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // دریافت لیست حضور و غیاب‌ها (با ستون ثبت‌کننده)
    $users_table = $wpdb->users;
    $query_values = $where_values;
    $query = "SELECT a.*, 
                     m.first_name, m.last_name, m.national_id,
                     c.title as course_title,
                     mc.chapter as member_chapter,
                     mc.group_name as member_group_name,
                     {$sc_attendance_recorded_by_name_expr} as recorded_by_name
              FROM $attendances_table a
              INNER JOIN $members_table m ON a.member_id = m.id
              INNER JOIN $courses_table c ON a.course_id = c.id
              LEFT JOIN $member_courses_table mc ON mc.member_id = a.member_id AND mc.course_id = a.course_id AND mc.status = 'active'
              LEFT JOIN $coaches_table rec_coach ON rec_coach.user_id = a.user_id
              LEFT JOIN $users_table rec_user ON rec_user.ID = a.user_id
              WHERE $where_clause
              ORDER BY a.attendance_date DESC, a.created_at DESC
              LIMIT %d OFFSET %d";

    $query_values[] = $per_page;
    $query_values[] = $offset;

    if (!empty($query_values)) {
        $attendances = $wpdb->get_results($wpdb->prepare($query, $query_values));
    } else {
        $attendances = $wpdb->get_results($query);
    }

    // محاسبه تعداد صفحات
    $total_pages = ceil($total_items / $per_page);
}
//if absent

if ($active_tab === 'absents') {

    // ====== فیلترها دقیقاً مثل تب individual ======
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    $filter_coach = (current_user_can('club_coach') || current_user_can('administrator')) && isset($_GET['filter_coach']) ? absint($_GET['filter_coach']) : 0;
    $filter_record_method = isset($_GET['filter_record_method']) ? sanitize_key(wp_unslash($_GET['filter_record_method'])) : '';

    // تاریخ
    $filter_date_from = '';
    $filter_date_to = '';
    $filter_date_from_shamsi = '';
    $filter_date_to_shamsi = '';

    if (!empty($_GET['filter_date_from_shamsi'])) {
        $filter_date_from_shamsi = sanitize_text_field($_GET['filter_date_from_shamsi']);
        $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
    }
    if (!empty($_GET['filter_date_to_shamsi'])) {
        $filter_date_to_shamsi = sanitize_text_field($_GET['filter_date_to_shamsi']);
        $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
    }

    $today_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
    $display_date_from_shamsi = $filter_date_from_shamsi ?: $today_shamsi;
    $display_date_to_shamsi   = $filter_date_to_shamsi   ?: $today_shamsi;

    // ====== ساخت WHERE ======
    $where_conditions = ['1=1'];
    $where_values = [];

    // *** نکته مهم: فقط غایبین ***
    $where_conditions[] = "a.status = 'absent'";

    if (function_exists('sc_secretary_append_attendance_course_scope') && function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()) {
        sc_secretary_append_attendance_course_scope($where_conditions, $where_values);
    } elseif (function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance()) {
        $course_coaches_table_abs = $wpdb->prefix . 'sc_course_coaches';
        $coach_abs = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
            $current_user_id
        ));
        if ($coach_abs) {
            $coach_id_abs = (int) $coach_abs->id;
            $coach_course_ids_abs = function_exists('sc_coach_attendance_accessible_course_ids')
                ? sc_coach_attendance_accessible_course_ids($coach_id_abs)
                : $wpdb->get_col($wpdb->prepare(
                "SELECT course_id FROM $course_coaches_table_abs WHERE coach_id = %d",
                $coach_id_abs
            ));
            if (!empty($coach_course_ids_abs)) {
                $placeholders_abs = implode(',', array_fill(0, count($coach_course_ids_abs), '%d'));
                $where_conditions[] = "a.course_id IN ($placeholders_abs)";
                $where_values = array_merge($where_values, $coach_course_ids_abs);
                $where_conditions[] = sc_attendance_where_coach_member_scope_list($coach_id_abs);
            } else {
                $where_conditions[] = '1=0';
            }
        } else {
            $where_conditions[] = '1=0';
        }
    }

    // فیلتر دوره
    if ($filter_course > 0) {
        $where_conditions[] = "a.course_id = %d";
        $where_values[] = $filter_course;
    }

    // فیلتر کاربر
    if ($filter_member > 0) {
        $where_conditions[] = "a.member_id = %d";
        $where_values[] = $filter_member;
    }

    // فیلتر مربی ثبت‌کننده
    if ($filter_coach > 0) {
        $coach_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $coaches_table WHERE id = %d LIMIT 1", 
            $filter_coach
        ));
        if ($coach_user_id) {
            $where_conditions[] = "a.user_id = %d";
            $where_values[] = $coach_user_id;
        }
    }

    if (function_exists('sc_attendance_apply_record_method_filter')) {
        sc_attendance_apply_record_method_filter($where_conditions, $where_values, $filter_record_method);
    }

    // فیلتر تاریخ
    if ($filter_date_from) {
        $where_conditions[] = "a.attendance_date >= %s";
        $where_values[] = $filter_date_from;
    }
    if ($filter_date_to) {
        $where_conditions[] = "a.attendance_date <= %s";
        $where_values[] = $filter_date_to;
    }

    $where_clause = implode(" AND ", $where_conditions);

    // ====== کوئری ======
    $users_table = $wpdb->users;

    $query = "SELECT a.*,
                     m.first_name, m.last_name, m.national_id,
                     c.title as course_title,
                     {$sc_attendance_recorded_by_name_expr} as recorded_by_name
              FROM $attendances_table a
              INNER JOIN $members_table m ON a.member_id = m.id
              INNER  JOIN $courses_table c ON a.course_id = c.id
              LEFT JOIN $coaches_table rec_coach ON rec_coach.user_id = a.user_id
              LEFT JOIN $users_table rec_user ON rec_user.ID = a.user_id
              WHERE $where_clause
              ORDER BY a.attendance_date DESC, a.created_at DESC";

    if (!empty($where_values)) {
        $absents = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $absents = $wpdb->get_results($query);
    }

}
// ==================== تب 2: لیست گروه‌بندی شده بر اساس دوره و تاریخ ====================
if ($active_tab === 'grouped') {
    // دریافت فیلترها
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_coach = (current_user_can('club_coach') || current_user_can('administrator')) && isset($_GET['filter_coach']) ? absint($_GET['filter_coach']) : 0;
    $filter_record_method = isset($_GET['filter_record_method']) ? sanitize_key(wp_unslash($_GET['filter_record_method'])) : '';
    // پردازش فیلترهای تاریخ (شمسی به میلادی)
    $filter_date_from = '';
    $filter_date_to = '';
    if (isset($_GET['filter_date_from_shamsi']) && !empty($_GET['filter_date_from_shamsi'])) {
        $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi']));
    } elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
        $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
    }
    
    if (isset($_GET['filter_date_to_shamsi']) && !empty($_GET['filter_date_to_shamsi'])) {
        $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi']));
    } elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
        $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
    }
    
    // اگر filter_date_from_shamsi_2 یا filter_date_to_shamsi_2 موجود بود
    if (isset($_GET['filter_date_from_shamsi_2']) && !empty($_GET['filter_date_from_shamsi_2'])) {
        $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi_2']));
    }
    if (isset($_GET['filter_date_to_shamsi_2']) && !empty($_GET['filter_date_to_shamsi_2'])) {
        $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi_2']));
    }

    // ساخت WHERE clause
    $where_conditions = ['1=1'];
    $where_values = [];
    
    if (function_exists('sc_secretary_append_attendance_course_scope') && function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()) {
        sc_secretary_append_attendance_course_scope($where_conditions, $where_values);
    } elseif (function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance()) {
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
        
        // دریافت coach_id از user_id
        $coach = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
            $current_user_id
        ));
        
        if ($coach) {
            $coach_id = $coach->id;
            // دریافت لیست course_id های مربی
            $coach_course_ids = function_exists('sc_coach_attendance_accessible_course_ids')
                ? sc_coach_attendance_accessible_course_ids((int) $coach_id)
                : $wpdb->get_col($wpdb->prepare(
                "SELECT course_id FROM $course_coaches_table WHERE coach_id = %d",
                $coach_id
            ));
            
            if (!empty($coach_course_ids)) {
                $placeholders = implode(',', array_fill(0, count($coach_course_ids), '%d'));
                $where_conditions[] = "a.course_id IN ($placeholders)";
                $where_values = array_merge($where_values, $coach_course_ids);
                $where_conditions[] = sc_attendance_where_coach_member_scope_list((int) $coach_id);
            } else {
                // اگر مربی هیچ دوره‌ای نداشت، هیچ رکوردی نمایش داده نشود
                $where_conditions[] = "1=0";
            }
        } else {
            // اگر مربی در جدول coaches وجود نداشت، هیچ رکوردی نمایش داده نشود
            $where_conditions[] = "1=0";
        }
    }

    if ($filter_course > 0) {
        $where_conditions[] = "a.course_id = %d";
        $where_values[] = $filter_course;
    }

    if ($filter_date_from) {
        $where_conditions[] = "a.attendance_date >= %s";
        $where_values[] = $filter_date_from;
    }

    if ($filter_date_to) {
        $where_conditions[] = "a.attendance_date <= %s";
        $where_values[] = $filter_date_to;
    }

    // فیلتر مربی (فقط برای مدیر باشگاه)
    if ($filter_coach > 0) {
        $coach_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $coaches_table WHERE id = %d LIMIT 1",
            $filter_coach
        ));
        if ($coach_user_id) {
            $where_conditions[] = "a.user_id = %d";
            $where_values[] = $coach_user_id;
        }
    }

    if (function_exists('sc_attendance_apply_record_method_filter')) {
        sc_attendance_apply_record_method_filter($where_conditions, $where_values, $filter_record_method);
    }

    $where_clause = implode(' AND ', $where_conditions);
    $users_table = $wpdb->users;

    // دریافت تعداد کل گروه‌ها برای pagination (تفکیک بر اساس دوره + تاریخ + گروه داخلی)
    $total_query = "SELECT COUNT(DISTINCT CONCAT(a.course_id, '-', a.attendance_date, '-', COALESCE(mc.group_name, ''))) 
                    FROM $attendances_table a
                    INNER JOIN $courses_table c ON a.course_id = c.id
                    LEFT JOIN $member_courses_table mc ON mc.member_id = a.member_id AND mc.course_id = a.course_id AND mc.status = 'active'
                    WHERE $where_clause";
    if (!empty($where_values)) {
        $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
    } else {
        $total_items = $wpdb->get_var($total_query);
    }

    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // دریافت لیست گروه‌بندی شده (با ستون ثبت‌کنندگان)
    $query_values = $where_values;
    $query = "SELECT 
                a.course_id,
                a.attendance_date,
                COALESCE(mc.group_name, '') AS member_group_name,
                c.title as course_title,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                COUNT(*) as total_count,
                GROUP_CONCAT(DISTINCT {$sc_attendance_recorded_by_name_expr} SEPARATOR '، ') as recorded_by_names
              FROM $attendances_table a
              INNER JOIN $courses_table c ON a.course_id = c.id
              LEFT JOIN $member_courses_table mc ON mc.member_id = a.member_id AND mc.course_id = a.course_id AND mc.status = 'active'
              LEFT JOIN $coaches_table rec_coach ON rec_coach.user_id = a.user_id
              LEFT JOIN $users_table rec_user ON rec_user.ID = a.user_id
              WHERE $where_clause
              GROUP BY a.course_id, a.attendance_date, COALESCE(mc.group_name, '')
              ORDER BY a.attendance_date DESC, c.title ASC, member_group_name ASC
              LIMIT %d OFFSET %d";

    $query_values[] = $per_page;
    $query_values[] = $offset;

    if (!empty($query_values)) {
        $grouped_attendances = $wpdb->get_results($wpdb->prepare($query, $query_values));
    } else {
        $grouped_attendances = $wpdb->get_results($query);
    }

    // محاسبه تعداد صفحات
    $total_pages = ceil($total_items / $per_page);
}

// ==================== تب 3: لیست کلی حضور و غیاب ====================
if ($active_tab === 'overall') {
    // دریافت فیلترها
    $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
    $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
    $filter_coach = (current_user_can('club_coach') || current_user_can('administrator')) && isset($_GET['filter_coach']) ? absint($_GET['filter_coach']) : 0;
    $filter_record_method = isset($_GET['filter_record_method']) ? sanitize_key(wp_unslash($_GET['filter_record_method'])) : '';
    
    // پردازش فیلترهای تاریخ (شمسی به میلادی)
    $filter_date_from = '';
    $filter_date_to = '';
    if (isset($_GET['filter_date_from_shamsi']) && !empty($_GET['filter_date_from_shamsi'])) {
        $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi']));
    } elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
        $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
    }
    
    if (isset($_GET['filter_date_to_shamsi']) && !empty($_GET['filter_date_to_shamsi'])) {
        $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi']));
    } elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
        $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
    }
    
    // اگر filter_date_from_shamsi_3 یا filter_date_to_shamsi_3 موجود بود
    if (isset($_GET['filter_date_from_shamsi_3']) && !empty($_GET['filter_date_from_shamsi_3'])) {
        $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi_3']));
    }
    if (isset($_GET['filter_date_to_shamsi_3']) && !empty($_GET['filter_date_to_shamsi_3'])) {
        $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi_3']));
    }
    
    // ساخت WHERE clause
    $where_conditions = ['1=1'];
    $where_values = [];
    
    if (function_exists('sc_secretary_append_attendance_course_scope') && function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()) {
        sc_secretary_append_attendance_course_scope($where_conditions, $where_values);
    } elseif (function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance()) {
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
        
        // دریافت coach_id از user_id
        $coach = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
            $current_user_id
        ));
        
        if ($coach) {
            $coach_id = $coach->id;
            // دریافت لیست course_id های مربی
            $coach_course_ids = function_exists('sc_coach_attendance_accessible_course_ids')
                ? sc_coach_attendance_accessible_course_ids((int) $coach_id)
                : $wpdb->get_col($wpdb->prepare(
                "SELECT course_id FROM $course_coaches_table WHERE coach_id = %d",
                $coach_id
            ));
            
            if (!empty($coach_course_ids)) {
                $placeholders = implode(',', array_fill(0, count($coach_course_ids), '%d'));
                $where_conditions[] = "a.course_id IN ($placeholders)";
                $where_values = array_merge($where_values, $coach_course_ids);
                $where_conditions[] = sc_attendance_where_coach_member_scope_list((int) $coach_id);
            } else {
                // اگر مربی هیچ دوره‌ای نداشت، هیچ رکوردی نمایش داده نشود
                $where_conditions[] = "1=0";
            }
        } else {
            // اگر مربی در جدول coaches وجود نداشت، هیچ رکوردی نمایش داده نشود
            $where_conditions[] = "1=0";
        }
    }

    if ($filter_course > 0) {
        $where_conditions[] = "a.course_id = %d";
        $where_values[] = $filter_course;
    }

    if ($filter_member > 0) {
        $where_conditions[] = "a.member_id = %d";
        $where_values[] = $filter_member;
    }

    // فیلتر مربی (فقط برای مدیر باشگاه)
    if ($filter_coach > 0) {
        $coach_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $coaches_table WHERE id = %d LIMIT 1",
            $filter_coach
        ));
        if ($coach_user_id) {
            $where_conditions[] = "a.user_id = %d";
            $where_values[] = $coach_user_id;
        }
    }

    if (function_exists('sc_attendance_apply_record_method_filter')) {
        sc_attendance_apply_record_method_filter($where_conditions, $where_values, $filter_record_method);
    }
    
    if ($filter_date_from) {
        $where_conditions[] = "a.attendance_date >= %s";
        $where_values[] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where_conditions[] = "a.attendance_date <= %s";
        $where_values[] = $filter_date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // دریافت لیست حضور و غیاب‌ها
    $query = "SELECT 
                a.member_id,
                a.attendance_date,
                a.status,
                m.first_name,
                m.last_name
              FROM $attendances_table a
              INNER JOIN $members_table m ON a.member_id = m.id
              WHERE $where_clause
              ORDER BY m.last_name ASC, m.first_name ASC, a.attendance_date ASC";
    
    if (!empty($where_values)) {
        $all_attendances = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $all_attendances = $wpdb->get_results($query);
    }
    
    // ساخت ساختار داده برای نمایش
    $overall_data = [];
    $dates_list = [];
    
    // گروه‌بندی بر اساس member_id و تاریخ
    foreach ($all_attendances as $attendance) {
        $member_id = $attendance->member_id;
        $date_key = $attendance->attendance_date;
        
        if (!isset($overall_data[$member_id])) {
            $overall_data[$member_id] = [
                'name' => $attendance->first_name . ' ' . $attendance->last_name,
                'attendances' => []
            ];
        }
        
        $overall_data[$member_id]['attendances'][$date_key] = $attendance->status;
        
        // اضافه کردن تاریخ به لیست تاریخ‌ها (اگر قبلاً اضافه نشده)
        if (!in_array($date_key, $dates_list)) {
            $dates_list[] = $date_key;
        }
    }
    
    // مرتب‌سازی تاریخ‌ها
    sort($dates_list);
}



?>

<div class="wrap sc-attendance-page-header sc-att-list-header">
    <div class="sc-att-list-header-inner">
        <div class="sc-att-list-header-text">
            <h1 class="sc-att-list-title">لیست حضور و غیاب</h1>
            <p class="sc-att-list-desc">مشاهده و فیلتر حضور و غیاب در تب‌های جزئی، دوره‌ها، اشخاص و غایبین</p>
        </div>
        <div class="sc-att-list-header-actions">
            <?php if ($can_manage_attendance) : ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-attendance-add')); ?>" class="sc-att-btn-primary">ثبت حضور و غیاب</a>
            <?php endif; ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-attendance-report')); ?>" class="sc-att-btn-secondary">گزارش حضور بازیکن</a>
        </div>
    </div>
</div>
<div class="wrap sc-attendance-page-body sc-attendance-list-body sc-att-list-body">
    <nav class="sc-att-tabs nav-tab-wrapper" aria-label="تب‌های حضور و غیاب">
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $attendance_list_page . '&tab=individual')); ?>" class="nav-tab <?php echo $active_tab === 'individual' ? 'nav-tab-active' : ''; ?>">حضور و غیاب جزئی</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $attendance_list_page . '&tab=grouped')); ?>" class="nav-tab <?php echo $active_tab === 'grouped' ? 'nav-tab-active' : ''; ?>">گزارش دوره‌ها</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $attendance_list_page . '&tab=overall')); ?>" class="nav-tab <?php echo $active_tab === 'overall' ? 'nav-tab-active' : ''; ?>">گزارش اشخاص</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $attendance_list_page . '&tab=absents')); ?>" class="nav-tab <?php echo $active_tab === 'absents' ? 'nav-tab-active' : ''; ?>">غایبین</a>
    </nav>
    
    <?php if ($active_tab === 'individual') : ?>
    <!-- تب 1: لیست حضور و غیاب کاربران -->
        <!-- فیلترها -->
        <form method="GET" action="" class="form_fillter_attendance form_fillter_attendance_tab1">
            <input type="hidden" name="page" value="<?php echo esc_attr($attendance_list_page); ?>">
            <input type="hidden" name="tab" value="individual">
        


<div class="sc-filter-grid">

<!-- دوره -->
<div class="sc-filter-field">
<label class="sc-filter-label" for="filter_course">دوره</label>
<?php
$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
echo function_exists('sc_render_searchable_course_filter_dropdown')
    ? sc_render_searchable_course_filter_dropdown($courses, $filter_course)
    : '';
?>
</div>

<!-- کاربر -->
<div class="sc-filter-field">
<label class="sc-filter-label">کاربر</label>

<div class="sc-searchable-dropdown">
<?php
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$selected_member_text = 'همه کاربران';

if ($filter_member) {
    foreach ($members as $m) {
        if ($m->id == $filter_member) {
            $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
            break;
        }
    }
}
?>

<input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">

<div class="sc-dropdown-toggle">
<span class="sc-dropdown-placeholder" <?php if ($filter_member) echo 'style="display:none"'; ?>>همه کاربران</span>
<span class="sc-dropdown-selected" <?php if (!$filter_member) echo 'style="display:none"'; ?>>
<?php echo esc_html($selected_member_text); ?>
</span>
<span class="sc-dropdown-arrow">▼</span>
</div>

<div class="sc-dropdown-menu">
<div class="sc-dropdown-search">
<input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
</div>

<div class="sc-dropdown-options">
<?php
$display_count = 0;
$max_display = 10;
?>

<div class="sc-dropdown-option sc-visible"
     data-value="0"
     data-search="همه کاربران"
     onclick="scSelectMemberFilter(this,'0','همه کاربران')">
همه کاربران
</div>

<?php foreach ($members as $member) :
    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
    $display_count++;
?>
<div class="sc-dropdown-option <?php echo $display_class; ?>"
     data-value="<?php echo esc_attr($member->id); ?>"
     data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
     onclick="scSelectMemberFilter(this,'<?php echo esc_js($member->id); ?>','<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
<?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
</div>
<?php endforeach; ?>
</div>
</div>
</div>
</div>

<?php if (!empty($coaches_list)) : ?>
<!-- مربی ثبت‌کننده -->
<div class="sc-filter-field">
<label class="sc-filter-label" for="filter_coach">مربی ثبت‌کننده</label>
<select name="filter_coach" id="filter_coach" class="sc-filter-control">
<option value="0">همه</option>
<?php foreach ($coaches_list as $coach) : ?>
<option value="<?php echo esc_attr($coach->id); ?>" <?php selected($filter_coach ?? 0, $coach->id); ?>><?php echo esc_html($coach->first_name . ' ' . $coach->last_name); ?></option>
<?php endforeach; ?>
</select>
</div>
<?php endif; ?>

<?php if (function_exists('sc_attendance_record_method_filter_options')) : ?>
<div class="sc-filter-field">
<label class="sc-filter-label" for="filter_record_method">روش ثبت</label>
<select name="filter_record_method" id="filter_record_method" class="sc-filter-control">
<?php foreach (sc_attendance_record_method_filter_options() as $method_value => $method_label) : ?>
<option value="<?php echo esc_attr($method_value); ?>" <?php selected($filter_record_method ?? '', $method_value); ?>><?php echo esc_html($method_label); ?></option>
<?php endforeach; ?>
</select>
</div>
<?php endif; ?>

<!-- وضعیت -->
<div class="sc-filter-field">
<label class="sc-filter-label" for="filter_status">وضعیت</label>
      <select name="filter_status" id="filter_status" class="sc-filter-control">
                                <option value="all" <?php selected($filter_status, 'all'); ?>>همه وضعیت‌ها</option>
                                <option value="present" <?php selected($filter_status, 'present'); ?>>حاضر</option>
                                <option value="absent" <?php selected($filter_status, 'absent'); ?>>غایب</option>

</select>
</div>

<!-- تاریخ -->
<div class="sc-filter-field sc-filter-date">
    <label class="sc-filter-label">بازه تاریخ</label>

    <div class="sc-date-range">
        <input type="text"
               id="filter_date_from_shamsi"
               name="filter_date_from_shamsi"
               class="persian-date-input sc-no-default-date sc-filter-control"
               value="<?php echo esc_attr($display_date_from_shamsi_tab1); ?>"
               readonly>

        <input type="text"
               id="filter_date_to_shamsi"
               name="filter_date_to_shamsi"
               class="persian-date-input sc-no-default-date sc-filter-control"
               value="<?php echo esc_attr($display_date_to_shamsi_tab1); ?>"
               readonly>

        <input type="hidden"
               name="filter_date_from"
               id="filter_date_from"
               value="<?php echo esc_attr($filter_date_from); ?>">

        <input type="hidden"
               name="filter_date_to"
               id="filter_date_to"
               value="<?php echo esc_attr($filter_date_to); ?>">
    </div>

</div>

</div>


            <p class="submit">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <?php
                // ساخت URL برای export Excel با حفظ فیلترها
                $export_url = admin_url('admin.php?page=' . $attendance_list_page . '&sc_export=excel&export_type=attendance');
                $export_url = add_query_arg('filter_course', isset($_GET['filter_course']) ? $_GET['filter_course'] : 0, $export_url);
                $export_url = add_query_arg('filter_member', isset($_GET['filter_member']) ? $_GET['filter_member'] : 0, $export_url);
                if (!empty($coaches_list) && isset($_GET['filter_coach']) && $_GET['filter_coach'] > 0) {
                    $export_url = add_query_arg('filter_coach', $_GET['filter_coach'], $export_url);
                }
                if (!empty($_GET['filter_record_method'])) {
                    $export_url = add_query_arg('filter_record_method', sanitize_key(wp_unslash($_GET['filter_record_method'])), $export_url);
                }
                if (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
                    $export_url = add_query_arg('filter_date_from', $_GET['filter_date_from'], $export_url);
                }
                if (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
                    $export_url = add_query_arg('filter_date_to', $_GET['filter_date_to'], $export_url);
                }
                if (isset($_GET['filter_status']) && $_GET['filter_status'] !== 'all') {
                    $export_url = add_query_arg('filter_status', $_GET['filter_status'], $export_url);
                }
                $export_url = wp_nonce_url($export_url, 'sc_export_excel');
                ?>
                <a href="<?php echo admin_url('admin.php?page=' . $attendance_list_page . '&tab=individual'); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
                <a href="<?php echo esc_url($export_url); ?>" class="button export_excel_btn button_export">
                    📊 خروجی Excel
                </a>
            </p>
        </form>
        
        <!-- لیست حضور و غیاب‌ها -->
        <?php if (empty($attendances)) : ?>
            <div class="notice notice-info">
                <p>هیچ حضور و غیابی یافت نشد.</p>
            </div>
        <?php else : ?>
            <div class="back_attendance_list">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-row">ردیف</th>
                            <th>تاریخ</th>
                            <th>دوره</th>
                            <th>نام</th>
                            <th>نام خانوادگی</th>
                            <th>شناسه بازیکن</th>
                            <th>ثبت‌کننده</th>
                            <th>روش ثبت</th>
                            <th>وضعیت</th>
                            <th style="width: 150px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $start_number = ($current_page - 1) * $per_page;
                        foreach ($attendances as $index => $attendance) : 
                        
                            $row_number = $start_number + $index + 1;
                            //$status_label = $attendance->status === 'present' ? 'حاضر' : 'غایب';
                            if($attendance->status === 'present'){
                                $status_label = 'حاضر' ;
                            }elseif($attendance->status === 'absent'){
                                 $status_label = 'غیبت' ;
                            }
                            else{
                                 $status_label = 'غیبت -  مجاز' ;
                            }
                            $status_color = $attendance->status === 'present' ? '#00a32a' : '#d63638';
                            $status_bg = $attendance->status === 'present' ? '#d4edda' : '#ffeaea';
                        ?>
                            <tr>
                                <td class="column-row"><?php echo $row_number; ?></td>
                                <td>
                                    <strong><?php echo sc_date_shamsi_date_only($attendance->attendance_date); ?></strong>
                                     - 
                                    <small style="color: #666;"><?php echo sc_date_shamsi($attendance->attendance_date, 'l'); ?></small>
                                </td>
                                <td><?php echo esc_html($attendance->course_title); ?></td>
                                <td><?php echo esc_html($attendance->first_name); ?></td>
                                <td><?php echo esc_html($attendance->last_name); ?></td>
                                <td><?php echo esc_html($attendance->member_id); ?></td>
                                <td><?php echo esc_html($attendance->recorded_by_name ?? '-'); ?></td>
                                <td><?php echo esc_html(function_exists('sc_attendance_record_method_label') ? sc_attendance_record_method_label($attendance->record_method ?? '') : ($attendance->record_method ?? '—')); ?></td>
                                <td>
                                    <span style="
                                        padding: 5px 10px;
                                        border-radius: 4px;
                                        font-weight: bold;
                                        background-color: <?php echo $status_bg; ?>;
                                        color: <?php echo $status_color; ?>;
                                    ">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($can_manage_attendance) : ?>
                                    <a href="<?php echo esc_url(function_exists('sc_attendance_add_page_url')
                                        ? sc_attendance_add_page_url(
                                            $attendance->course_id,
                                            $attendance->attendance_date,
                                            isset($attendance->member_chapter) ? (string) $attendance->member_chapter : '',
                                            isset($attendance->member_group_name) ? (string) $attendance->member_group_name : ''
                                        )
                                        : admin_url('admin.php?page=sc-attendance-add&attendance_course_id=' . (int) $attendance->course_id . '&date=' . rawurlencode($attendance->attendance_date))); ?>"
                                       class="button button-small">ویرایش</a>
                                    <?php endif; ?>
                                    <?php if ($can_delete_or_justify_attendance) : ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=' . $attendance_list_page . '&tab=individual&action=delete&attendance_id=' . $attendance->id), 'delete_attendance_' . $attendance->id); ?>" 
                                       class="button button-small button_delete_attendance" 
                                       onclick="return scConfirmInline(event, { type: 'warning', message: 'آیا مطمئن هستید که می‌خواهید این حضور و غیاب را حذف کنید؟' });"
                                       >حذف</a>

                                <?php
                                    // لینک مجاز کردن غیبت
                                    $justify_url = wp_nonce_url(
                                        admin_url('admin.php?page=' . $attendance_list_page . '&tab=individual&action=justify&attendance_id=' . $attendance->id),
                                        'justify_attendance_' . $attendance->id
                                    );

                                   // فقط برای وضعیت absent دکمه نمایش داده شود
                                        if ($attendance->status === 'absent') {
                                            echo '<a href="' . esc_url($justify_url) . '" 
                                                    class="button button-small" 
                                                    style="color:#2271b1; border-color:#2271b1;" 
                                                    onclick="return scConfirmInline(event, { type: \'warning\', message: \'آیا از مجاز کردن این غیبت و بازگرداندن جلسه مطمئن هستید؟\' });">
                                                    مجاز کردن
                                                </a>';
                                        }
                                    ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                 </div>
                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom sc_paginate" style="margin-top: 20px;">
                        <div class="tablenav-pages">
                            <?php
                            $pagination_args = ['page' => $attendance_list_page, 'tab' => 'individual', 'filter_course' => $filter_course, 'filter_member' => $filter_member, 'filter_status' => $filter_status];
                            if ($filter_coach > 0) $pagination_args['filter_coach'] = $filter_coach;
                            if (!empty($filter_record_method)) $pagination_args['filter_record_method'] = $filter_record_method;
                            if (!empty($filter_date_from)) $pagination_args['filter_date_from'] = $filter_date_from;
                            if (!empty($filter_date_to)) $pagination_args['filter_date_to'] = $filter_date_to;
                            if (!empty($filter_date_from_shamsi)) $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
                            if (!empty($filter_date_to_shamsi)) $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
                            $page_links = paginate_links([
                                'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                                'format' => '',
                                'prev_text' => '< قبلی ',
                                'next_text' => ' بعدی >',
                                'total' => $total_pages,
                                'current' => $current_page,
                                'add_args' => $pagination_args
                            ]);
                            echo $page_links;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
           
        <?php endif; ?>



        <!-- //tab 4 -->
<?php elseif ($active_tab === 'absents') : ?>

<?php
// -------------------- پردازش فیلترها مانند تب individual --------------------
$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$filter_coach  = (current_user_can('club_coach') || current_user_can('administrator')) && isset($_GET['filter_coach']) 
                 ? absint($_GET['filter_coach']) 
                 : 0;
$filter_record_method = isset($_GET['filter_record_method']) ? sanitize_key(wp_unslash($_GET['filter_record_method'])) : '';

$filter_date_from = '';
$filter_date_to   = '';
$filter_date_from_shamsi = '';
$filter_date_to_shamsi   = '';

if (!empty($_GET['filter_date_from_shamsi'])) {
    $filter_date_from_shamsi = sanitize_text_field($_GET['filter_date_from_shamsi']);
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
}
if (!empty($_GET['filter_date_to_shamsi'])) {
    $filter_date_to_shamsi = sanitize_text_field($_GET['filter_date_to_shamsi']);
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
}

$today_shamsi = function_exists('sc_date_shamsi_date_only') 
                ? sc_date_shamsi_date_only(current_time('Y-m-d')) 
                : '';
$display_date_from_shamsi = $filter_date_from_shamsi ?: $today_shamsi;
$display_date_to_shamsi   = $filter_date_to_shamsi ?: $today_shamsi;



$where_conditions = ["1=1"];
$where_values = [];

if (function_exists('sc_user_is_coach_only_for_attendance') && sc_user_is_coach_only_for_attendance()) {
    $course_coaches_table_abs_tab4 = $wpdb->prefix . 'sc_course_coaches';
    $coach_abs_tab4 = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    if ($coach_abs_tab4) {
        $coach_id_abs_tab4 = (int) $coach_abs_tab4->id;
        $coach_course_ids_abs_tab4 = function_exists('sc_coach_attendance_accessible_course_ids')
            ? sc_coach_attendance_accessible_course_ids($coach_id_abs_tab4)
            : $wpdb->get_col($wpdb->prepare(
            "SELECT course_id FROM $course_coaches_table_abs_tab4 WHERE coach_id = %d",
            $coach_id_abs_tab4
        ));
        if (!empty($coach_course_ids_abs_tab4)) {
            $placeholders_abs_tab4 = implode(',', array_fill(0, count($coach_course_ids_abs_tab4), '%d'));
            $where_conditions[] = "a.course_id IN ($placeholders_abs_tab4)";
            $where_values = array_merge($where_values, $coach_course_ids_abs_tab4);
            $where_conditions[] = sc_attendance_where_coach_member_scope_list($coach_id_abs_tab4);
        } else {
            $where_conditions[] = '1=0';
        }
    } else {
        $where_conditions[] = '1=0';
    }
}

if ($filter_course > 0) {
    $where_conditions[] = "a.course_id = %d";
    $where_values[] = $filter_course;
}

if ($filter_member > 0) {
    $where_conditions[] = "a.member_id = %d";
    $where_values[] = $filter_member;
}

if ($filter_coach > 0) {
    $coach_user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->prefix}sc_coaches WHERE id=%d LIMIT 1",
        $filter_coach
    ));

    if ($coach_user_id) {
        $where_conditions[] = "a.user_id = %d";
        $where_values[] = $coach_user_id;
    }
}

if (function_exists('sc_attendance_apply_record_method_filter')) {
    sc_attendance_apply_record_method_filter($where_conditions, $where_values, $filter_record_method);
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "a.attendance_date >= %s";
    $where_values[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "a.attendance_date <= %s";
    $where_values[] = $filter_date_to;
}

$where_clause = implode(" AND ", $where_conditions);


$query = "
SELECT 
    m.id AS member_id,
    m.first_name,
    m.last_name,
    m.national_id,

    COUNT(CASE WHEN a.status = 'absent' THEN 1 END)   AS absent_count,
    COUNT(CASE WHEN a.status = 'excused' THEN 1 END)  AS excused_count,

    MAX(CASE WHEN a.status = 'absent' THEN a.attendance_date END) AS last_absent_date

FROM $attendances_table a
INNER JOIN $members_table m ON a.member_id = m.id
INNER JOIN $courses_table c ON a.course_id = c.id
LEFT JOIN {$wpdb->prefix}sc_coaches rec_coach ON rec_coach.user_id = a.user_id
LEFT JOIN {$wpdb->users} rec_user ON rec_user.ID = a.user_id

WHERE $where_clause

GROUP BY a.member_id
HAVING absent_count > 0
ORDER BY absent_count DESC
";





$absents = !empty($where_values)
           ? $wpdb->get_results($wpdb->prepare($query, $where_values))
           : $wpdb->get_results($query);


          
?>


<form method="GET" action="" class="form_fillter_attendance form_fillter_attendance_tab1">
    <input type="hidden" name="page" value="<?php echo esc_attr($attendance_list_page); ?>">
    <input type="hidden" name="tab" value="absents">

<div class="sc-filter-grid">

<!-- دوره -->
<div class="sc-filter-field">
<label class="sc-filter-label" for="filter_course">دوره</label>
<?php echo function_exists('sc_render_searchable_course_filter_dropdown')
    ? sc_render_searchable_course_filter_dropdown($courses, $filter_course)
    : ''; ?>
</div>

<!-- کاربر (Dropdown جستجو) -->
<div class="sc-filter-field">
<label class="sc-filter-label">کاربر</label>

<?php
$selected_member_text = 'همه کاربران';
if ($filter_member > 0) {
    foreach ($members as $m) {
        if ($m->id == $filter_member) {
            $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
            break;
        }
    }
}
?>

<div class="sc-searchable-dropdown">
    <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">

    <div class="sc-dropdown-toggle">
        <span class="sc-dropdown-placeholder" <?php if ($filter_member) echo 'style="display:none"'; ?>>همه کاربران</span>
        <span class="sc-dropdown-selected" <?php if (!$filter_member) echo 'style="display:none"'; ?>>
            <?php echo esc_html($selected_member_text); ?>
        </span>
        <span class="sc-dropdown-arrow">▼</span>
    </div>

    <div class="sc-dropdown-menu">
        <div class="sc-dropdown-search">
            <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
        </div>

        <div class="sc-dropdown-options">
            <div class="sc-dropdown-option sc-visible"
                 data-value="0"
                 onclick="scSelectMemberFilter(this,'0','همه کاربران')">
                همه کاربران
            </div>

            <?php foreach ($members as $member) : ?>
                <div class="sc-dropdown-option sc-visible"
                     data-value="<?php echo esc_attr($member->id); ?>"
                     data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                     onclick="scSelectMemberFilter(this,'<?php echo esc_js($member->id); ?>',
                     '<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
                    <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</div>

<?php if (!empty($coaches_list)) : ?>
<!-- مربی -->
<div class="sc-filter-field">
<label class="sc-filter-label" for="filter_coach">مربی ثبت‌کننده</label>
<select name="filter_coach" id="filter_coach" class="sc-filter-control">
<option value="0">همه</option>
<?php foreach ($coaches_list as $coach) : ?>
<option value="<?php echo esc_attr($coach->id); ?>" <?php selected($filter_coach, $coach->id); ?>>
<?php echo esc_html($coach->first_name . ' ' . $coach->last_name); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<?php endif; ?>

<?php if (function_exists('sc_attendance_record_method_filter_options')) : ?>
<div class="sc-filter-field">
<label class="sc-filter-label" for="filter_record_method">روش ثبت</label>
<select name="filter_record_method" id="filter_record_method" class="sc-filter-control">
<?php foreach (sc_attendance_record_method_filter_options() as $method_value => $method_label) : ?>
<option value="<?php echo esc_attr($method_value); ?>" <?php selected($filter_record_method ?? '', $method_value); ?>><?php echo esc_html($method_label); ?></option>
<?php endforeach; ?>
</select>
</div>
<?php endif; ?>

<!-- تاریخ -->
<div class="sc-filter-field sc-filter-date">
<label class="sc-filter-label">بازه تاریخ</label>

<div class="sc-date-range">
    <input type="text"
           name="filter_date_from_shamsi"
           class="sc-filter-control persian-date-input sc-no-default-date"
           value="<?php echo esc_attr($display_date_from_shamsi); ?>"
           readonly>


    <input type="text"
           name="filter_date_to_shamsi"
           class="sc-filter-control persian-date-input sc-no-default-date"
           value="<?php echo esc_attr($display_date_to_shamsi); ?>"
           readonly>

    <input type="hidden" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
    <input type="hidden" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
</div>
</div>

</div>

<p class="submit">
    <input type="submit" class="button button-primary" value="اعمال فیلتر">
    <a href="<?php echo admin_url('admin.php?page=' . $attendance_list_page . '&tab=absents'); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
</p>

</form>


<!-- =================== لیست غایبین =================== -->
<?php if (empty($absents)) : ?>
    <div class="notice notice-info"><p>هیچ غایبی یافت نشد.</p></div>
<?php else : ?>

<div class="back_attendance_list">
    <table class="wp-list-table widefat fixed striped">
<thead>
    <tr>
        <th>ردیف</th>
        <th>نام</th>
        <th>نام خانوادگی</th>
        <th>کل غیبت ها</th>
        <th>غیبت غیر مجاز</th>
        <th>غیبت مجاز</th>
        <th>آخرین غیبت</th>
        <th>عملیات</th>
    </tr>
</thead>

<tbody>
<?php foreach ($absents as $i => $a): ?>
<tr>
    <td><?php echo $i + 1; ?></td>

    <td><?php echo esc_html($a->first_name); ?></td>
    <td><?php echo esc_html($a->last_name); ?></td>
    

    <td>
        <strong style="color:#000 text-alingh">
            <?php echo intval($a->absent_count + $a->excused_count); ?> 
        </strong>
    </td>
    <td>
        <strong style="color:#d63638">
            <?php echo intval($a->absent_count); ?> 
        </strong>
    </td>

    <td>
        <strong style="color:#0073aa">
            <?php echo intval($a->excused_count); ?> 
        </strong>
    </td>

    <td>
        <?php if ($a->last_absent_date): ?>
            <strong>
                <?php echo sc_date_shamsi_date_only($a->last_absent_date); ?>
            </strong>
        <?php else: ?>
            -
        <?php endif; ?>
    </td>

    <td>
     <a class="button button-small"
   href="<?php echo admin_url(
       'admin.php?page=' . $attendance_list_page
       .'&tab=individual'
       .'&filter_member=' . $a->member_id
       .'&filter_status=absent'
       .($filter_course > 0 ? '&filter_course=' . $filter_course : '')
       .($filter_coach > 0 ? '&filter_coach=' . $filter_coach : '')
       .(!empty($filter_date_from) ? '&filter_date_from=' . $filter_date_from : '')
       .(!empty($filter_date_to) ? '&filter_date_to=' . $filter_date_to : '')
   ); ?>">
    مشاهده جزئیات
</a>

    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>





    <?php elseif ($active_tab === 'grouped') : ?>
        <!-- تب 2: لیست بر اساس دوره و تاریخ -->
        <!-- فیلترها -->
        <form method="GET" action="" class="form_fillter_attendance form_fillter_attendance_tab1">
            <input type="hidden" name="page" value="<?php echo esc_attr($attendance_list_page); ?>">
            <input type="hidden" name="tab" value="grouped">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_course">دوره</label>
                    <?php echo function_exists('sc_render_searchable_course_filter_dropdown')
                        ? sc_render_searchable_course_filter_dropdown($courses, $filter_course)
                        : ''; ?>
                </div>

                <?php if (!empty($coaches_list)) : ?>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_coach">مربی ثبت‌کننده</label>
                    <select name="filter_coach" id="filter_coach" class="sc-filter-control">
                        <option value="0">همه</option>
                        <?php foreach ($coaches_list as $coach) : ?>
                            <option value="<?php echo esc_attr($coach->id); ?>" <?php selected($filter_coach ?? 0, $coach->id); ?>><?php echo esc_html($coach->first_name . ' ' . $coach->last_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if (function_exists('sc_attendance_record_method_filter_options')) : ?>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_record_method">روش ثبت</label>
                    <select name="filter_record_method" id="filter_record_method" class="sc-filter-control">
                        <?php foreach (sc_attendance_record_method_filter_options() as $method_value => $method_label) : ?>
                            <option value="<?php echo esc_attr($method_value); ?>" <?php selected($filter_record_method ?? '', $method_value); ?>><?php echo esc_html($method_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ</label>
                    <?php
                    // فقط برای نمایش: وقتی کاربر تاریخی نفرستاده امروز نشان بده (در فیلتر اعمال نمی‌شود)
                    $filter_date_from_shamsi_2 = '';
                    $filter_date_to_shamsi_2 = '';
                    if (!empty($filter_date_from)) {
                        $filter_date_from_shamsi_2 = sc_date_shamsi_date_only($filter_date_from);
                    } else {
                        $filter_date_from_shamsi_2 = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
                        if (!$filter_date_from_shamsi_2 && function_exists('gregorian_to_jalali')) {
                            $today = new DateTime(current_time('Y-m-d'));
                            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                            $filter_date_from_shamsi_2 = $today_jalali[0] . '/' . str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                        }
                    }
                    if (!empty($filter_date_to)) {
                        $filter_date_to_shamsi_2 = sc_date_shamsi_date_only($filter_date_to);
                    } else {
                        $filter_date_to_shamsi_2 = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
                        if (!$filter_date_to_shamsi_2 && function_exists('gregorian_to_jalali')) {
                            $today = new DateTime(current_time('Y-m-d'));
                            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                            $filter_date_to_shamsi_2 = $today_jalali[0] . '/' . str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                        }
                    }
                    ?>
                    <div class="sc-date-range">
                        <input type="text" name="filter_date_from_shamsi_2" id="filter_date_from_shamsi_2"
                               value="<?php echo esc_attr($filter_date_from_shamsi_2); ?>"
                               class="persian-date-input sc-no-default-date sc-filter-control"
                               placeholder="از تاریخ (شمسی)"
                               readonly>
                        <input type="hidden" name="filter_date_from" id="filter_date_from_2" value="<?php echo esc_attr($filter_date_from); ?>">

                        <input type="text" name="filter_date_to_shamsi_2" id="filter_date_to_shamsi_2"
                               value="<?php echo esc_attr($filter_date_to_shamsi_2); ?>"
                               class="persian-date-input sc-no-default-date sc-filter-control"
                               placeholder="تا تاریخ (شمسی)"
                               readonly>
                        <input type="hidden" name="filter_date_to" id="filter_date_to_2" value="<?php echo esc_attr($filter_date_to); ?>">
                    </div>
                </div>
            </div>
            
            <p class="submit">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo admin_url('admin.php?page=' . $attendance_list_page . '&tab=grouped'); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </p>
        </form>
        
        <!-- لیست گروه‌بندی شده -->
        <?php if (empty($grouped_attendances)) : ?>
            <div class="notice notice-info">
                <p>هیچ حضور و غیابی یافت نشد.</p>
            </div>
        <?php else : ?>
            <div class="back_attendance_list">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-row">ردیف</th>
                            <th>دوره</th>
                            <th>گروه</th>
                            <th>تاریخ</th>
                            <th>ثبت‌کننده</th>
                            <th> حاضر</th>
                            <th> غایب</th>
                            <th>کل</th>
                            <th style="width: 150px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $start_number = ($current_page - 1) * $per_page;
                        foreach ($grouped_attendances as $index => $group) : 
                            $row_number = $start_number + $index + 1;
                        ?>
                            <tr>
                                <td><?php echo $row_number; ?></td>
                                <td><strong><?php echo esc_html($group->course_title); ?></strong></td>
                                <td><?php echo !empty($group->member_group_name) ? esc_html($group->member_group_name) : '—'; ?></td>
                                <td>
                                    <strong><?php echo sc_date_shamsi_date_only($group->attendance_date); ?></strong>
                                    <br>
                                    <small style="color: #666;"><?php echo sc_date_shamsi($group->attendance_date, 'l'); ?></small>
                                </td>
                                <td><?php echo esc_html($group->recorded_by_names ?? '-'); ?></td>
                                <td>
                                        <?php echo esc_html($group->present_count); ?> نفر
                                    </span>
                                </td>
                                <td>
                                    <span>
                                        <?php echo esc_html($group->absent_count); ?> نفر
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($group->total_count); ?> نفر
                                </td>
                                <td>
                                    <?php if ($can_manage_attendance) : ?>
                                    <a href="<?php echo esc_url(function_exists('sc_attendance_add_page_url')
                                        ? sc_attendance_add_page_url(
                                            $group->course_id,
                                            $group->attendance_date,
                                            '',
                                            isset($group->member_group_name) ? (string) $group->member_group_name : ''
                                        )
                                        : admin_url('admin.php?page=sc-attendance-add&attendance_course_id=' . (int) $group->course_id . '&date=' . rawurlencode($group->attendance_date))); ?>"
                                       class="button button-small">ویرایش</a>
                                    <?php endif; ?>
                                    <?php
                                    // ساخت URL برای export Excel این روز
                                    $export_url = admin_url('admin.php?page=' . $attendance_list_page . '&sc_export=excel&export_type=attendance_overall');
                                    $export_url = add_query_arg('filter_course', $group->course_id, $export_url);
                                    $export_url = add_query_arg('filter_date_from', $group->attendance_date, $export_url);
                                    $export_url = add_query_arg('filter_date_to', $group->attendance_date, $export_url);
                                    if (!empty($coaches_list) && $filter_coach > 0) {
                                        $export_url = add_query_arg('filter_coach', $filter_coach, $export_url);
                                    }
                                    if (!empty($filter_record_method)) {
                                        $export_url = add_query_arg('filter_record_method', $filter_record_method, $export_url);
                                    }
                                    $export_url = wp_nonce_url($export_url, 'sc_export_excel');
                                    ?>
                                    <a href="<?php echo esc_url($export_url); ?>" 
                                       class="button button-small export_excel_btn" 
                                       >
                                        📊 Excel
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                  </div> 
                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom sc_paginate" style="margin-top: 20px;">
                        <div class="tablenav-pages">
                            <?php
                            $pagination_args = ['page' => $attendance_list_page, 'tab' => 'grouped', 'filter_course' => $filter_course];
                            if ($filter_coach > 0) $pagination_args['filter_coach'] = $filter_coach;
                            if (!empty($filter_record_method)) $pagination_args['filter_record_method'] = $filter_record_method;
                            if (!empty($filter_date_from)) $pagination_args['filter_date_from'] = $filter_date_from;
                            if (!empty($filter_date_to)) $pagination_args['filter_date_to'] = $filter_date_to;
                            if (!empty($_GET['filter_date_from_shamsi_2'])) $pagination_args['filter_date_from_shamsi_2'] = $_GET['filter_date_from_shamsi_2'];
                            if (!empty($_GET['filter_date_to_shamsi_2'])) $pagination_args['filter_date_to_shamsi_2'] = $_GET['filter_date_to_shamsi_2'];
                            $page_links = paginate_links([
                                'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                                'format' => '',
                                'prev_text' => '< قبلی ',
                                'next_text' => ' بعدی >',
                                'total' => $total_pages,
                                'current' => $current_page,
                                'add_args' => $pagination_args
                            ]);
                            echo $page_links;






                            ?>
                        </div>
                    </div>
                <?php endif; ?>
         
        <?php endif; ?>
    <?php elseif ($active_tab === 'overall') : ?>
        <!-- تب 3: لیست کلی حضور و غیاب -->
        <!-- فیلترها -->
        <form method="GET" action="" class="form_fillter_attendance form_fillter_attendance_tab1">
            <input type="hidden" name="page" value="<?php echo esc_attr($attendance_list_page); ?>">
            <input type="hidden" name="tab" value="overall">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_course">دوره</label>
                    <?php echo function_exists('sc_render_searchable_course_filter_dropdown')
                        ? sc_render_searchable_course_filter_dropdown($courses, $filter_course)
                        : ''; ?>
                </div>

                <?php if (!empty($coaches_list)) : ?>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_coach">مربی ثبت‌کننده</label>
                    <select name="filter_coach" id="filter_coach" class="sc-filter-control">
                        <option value="0">همه</option>
                        <?php foreach ($coaches_list as $coach) : ?>
                            <option value="<?php echo esc_attr($coach->id); ?>" <?php selected($filter_coach ?? 0, $coach->id); ?>><?php echo esc_html($coach->first_name . ' ' . $coach->last_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if (function_exists('sc_attendance_record_method_filter_options')) : ?>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_record_method">روش ثبت</label>
                    <select name="filter_record_method" id="filter_record_method" class="sc-filter-control">
                        <?php foreach (sc_attendance_record_method_filter_options() as $method_value => $method_label) : ?>
                            <option value="<?php echo esc_attr($method_value); ?>" <?php selected($filter_record_method ?? '', $method_value); ?>><?php echo esc_html($method_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="sc-filter-field">
                    <label class="sc-filter-label">کاربر</label>
                    <?php
                    $selected_member_text = 'همه کاربران';
                    if ($filter_member > 0) {
                        foreach ($members as $m) {
                            if ($m->id == $filter_member) {
                                $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
                                break;
                            }
                        }
                    }
                    ?>
                    <div class="sc-searchable-dropdown">
                        <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if ($filter_member) echo 'style="display:none"'; ?>>همه کاربران</span>
                            <span class="sc-dropdown-selected" <?php if (!$filter_member) echo 'style="display:none"'; ?>>
                                <?php echo esc_html($selected_member_text); ?>
                            </span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option sc-visible"
                                     data-value="0"
                                     data-search="همه کاربران"
                                     onclick="scSelectMemberFilter(this,'0','همه کاربران')">
                                    همه کاربران
                                </div>
                                <?php
                                $display_count = 0;
                                $max_display = 10;
                                foreach ($members as $member) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                ?>
                                <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                     data-value="<?php echo esc_attr($member->id); ?>"
                                     data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                     onclick="scSelectMemberFilter(this,'<?php echo esc_js($member->id); ?>','<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
                                    <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ</label>
                    <?php
                    // فقط برای نمایش: وقتی کاربر تاریخی نفرستاده امروز نشان بده (در فیلتر اعمال نمی‌شود)
                    $filter_date_from_shamsi_3 = '';
                    $filter_date_to_shamsi_3 = '';
                    if (!empty($filter_date_from)) {
                        $filter_date_from_shamsi_3 = sc_date_shamsi_date_only($filter_date_from);
                    } else {
                        $filter_date_from_shamsi_3 = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
                        if (!$filter_date_from_shamsi_3 && function_exists('gregorian_to_jalali')) {
                            $today = new DateTime(current_time('Y-m-d'));
                            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                            $filter_date_from_shamsi_3 = $today_jalali[0] . '/' . str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                        }
                    }
                    if (!empty($filter_date_to)) {
                        $filter_date_to_shamsi_3 = sc_date_shamsi_date_only($filter_date_to);
                    } else {
                        $filter_date_to_shamsi_3 = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
                        if (!$filter_date_to_shamsi_3 && function_exists('gregorian_to_jalali')) {
                            $today = new DateTime(current_time('Y-m-d'));
                            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                            $filter_date_to_shamsi_3 = $today_jalali[0] . '/' . str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                        }
                    }
                    ?>
                    <div class="sc-date-range">
                        <input type="text" name="filter_date_from_shamsi_3" id="filter_date_from_shamsi_3"
                               value="<?php echo esc_attr($filter_date_from_shamsi_3); ?>"
                               class="persian-date-input sc-no-default-date sc-filter-control"
                               placeholder="از تاریخ (شمسی)"
                               readonly>
                        <input type="hidden" name="filter_date_from" id="filter_date_from_3" value="<?php echo esc_attr($filter_date_from); ?>">

                        <input type="text" name="filter_date_to_shamsi_3" id="filter_date_to_shamsi_3"
                               value="<?php echo esc_attr($filter_date_to_shamsi_3); ?>"
                               class="persian-date-input sc-no-default-date sc-filter-control"
                               placeholder="تا تاریخ (شمسی)"
                               readonly>
                        <input type="hidden" name="filter_date_to" id="filter_date_to_3" value="<?php echo esc_attr($filter_date_to); ?>">
                    </div>
                </div>
            </div>
            
            <p class="submit">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
                <?php
                // ساخت URL برای export Excel
                $export_url = admin_url('admin.php?page=' . $attendance_list_page . '&sc_export=excel&export_type=attendance_overall');
                $export_url = add_query_arg('filter_course', isset($_GET['filter_course']) ? $_GET['filter_course'] : 0, $export_url);
                $export_url = add_query_arg('filter_member', isset($_GET['filter_member']) ? $_GET['filter_member'] : 0, $export_url);
                if (!empty($coaches_list) && isset($_GET['filter_coach']) && $_GET['filter_coach'] > 0) {
                    $export_url = add_query_arg('filter_coach', $_GET['filter_coach'], $export_url);
                }
                if (!empty($_GET['filter_record_method'])) {
                    $export_url = add_query_arg('filter_record_method', sanitize_key(wp_unslash($_GET['filter_record_method'])), $export_url);
                }
                if (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
                    $export_url = add_query_arg('filter_date_from', $_GET['filter_date_from'], $export_url);
                }
                if (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
                    $export_url = add_query_arg('filter_date_to', $_GET['filter_date_to'], $export_url);
                }
                $export_url = wp_nonce_url($export_url, 'sc_export_excel');
                ?>
                <a href="<?php echo admin_url('admin.php?page=' . $attendance_list_page . '&tab=overall'); ?>" class="button delete_fillter">پاک کردن فیلترها</a>

                <a href="<?php echo esc_url($export_url); ?>" class="button export_excel_btn button_export" >
                    📊 خروجی Excel
                </a>
            </p>
        </form>
        
        <!-- جدول کلی حضور و غیاب -->
        <?php if (empty($overall_data) || empty($dates_list)) : ?>
            <div class="notice notice-info">
                <p>هیچ حضور و غیابی یافت نشد.</p>
            </div>
        <?php else : ?>
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped list_all_attendance" style="min-width: 100%;">
                    <thead>
                        <tr>
                            <th style=" position: sticky; right: 0; background: #fff; z-index: 10; border-right: 2px solid #ddd;">نام و نام خانوادگی</th>
                            <?php foreach ($dates_list as $date) : ?>
                                <th style="min-width: 100px; text-align: center;"><?php echo esc_html(sc_date_shamsi_date_only($date)); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overall_data as $member_id => $member_data) : ?>
                            <tr>
                                <td style="position: sticky; right: 0; background: #fff; z-index: 10; border-right: 2px solid #ddd; font-weight: bold;">
                                    <?php echo esc_html($member_data['name']); ?>
                                </td>
                                <?php foreach ($dates_list as $date) : ?>
                                    <td style="text-align: center;">
                                        <?php 
                                        if (isset($member_data['attendances'][$date])) {
                                            $status = $member_data['attendances'][$date];
                                            if ($status === 'present') {
                                                echo '<span style="color: #00a32a; font-weight: bold; font-size: 18px;">✓</span>';
                                            } else {
                                                echo '<span style="color: #d63638; font-weight: bold; font-size: 18px;">✗</span>';
                                            }
                                        } else {
                                            echo '<span style="color: #999;">-</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>





