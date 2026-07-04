<?php
// -----------------------------
//  صفحه مدیریت لاگ دستگاه حضور و غیاب
//  
// -----------------------------

if (!defined('ABSPATH')) exit;

    sc_check_and_create_tables();

    global $wpdb;

    $logs_table    = $wpdb->prefix . 'sc_api_attendance_logs';
    $members_table = $wpdb->prefix . 'sc_members';
    $att_table     = $wpdb->prefix . 'sc_attendances';
    $courses_table = $wpdb->prefix . 'sc_courses';

    $log_has_matched_cols = !empty($wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$logs_table}` LIKE %s", 'matched_to_attendance')));

    $show_col_employee = (int) sc_get_setting('attendance_logs_col_employee_code', '1') === 1;
    $show_col_user_id = (int) sc_get_setting('attendance_logs_col_user_id', '1') === 1;
    $show_col_course = (int) sc_get_setting('attendance_logs_col_course', '1') === 1;
    $show_course_filter = (int) sc_get_setting('attendance_logs_show_course_filter', '1') === 1;
    $bulk_delete_on = (int) sc_get_setting('attendance_logs_bulk_delete', '1') === 1;
    $bulk_clear_match_setting = (int) sc_get_setting('attendance_logs_bulk_clear_match', '1') === 1;
    $bulk_clear_on = $bulk_clear_match_setting && $log_has_matched_cols;
    $show_bulk_bar = $bulk_delete_on || $bulk_clear_on;
    $default_days_back = max(1, min(366, (int) sc_get_setting('attendance_logs_default_days_back', '7')));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sc_attendance_logs_bulk_submit'])) {
        check_admin_referer('sc_attendance_logs_bulk', 'sc_attendance_logs_bulk_nonce');
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash($_POST['bulk_action'])) : '';
        $log_ids_in = isset($_POST['log_ids']) ? array_map('absint', (array) $_POST['log_ids']) : [];
        $log_ids_in = array_values(array_filter($log_ids_in));
        $notice = '';
        if ($bulk_action === '' || $bulk_action === '-1') {
            $notice = 'pick_action';
        } elseif (empty($log_ids_in)) {
            $notice = 'no_ids';
        } elseif ($bulk_action === 'delete' && $bulk_delete_on) {
            $in = implode(',', $log_ids_in);
            $wpdb->query("DELETE FROM `{$logs_table}` WHERE id IN ({$in})");
            $notice = 'deleted';
        } elseif ($bulk_action === 'clear_match' && $bulk_clear_on) {
            $in = implode(',', $log_ids_in);
            $wpdb->query("UPDATE `{$logs_table}` SET matched_to_attendance = 0, matched_attendance_id = NULL WHERE id IN ({$in})");
            $notice = 'cleared';
        } else {
            $notice = 'forbidden';
        }
        $ref = wp_get_referer();
        if (!$ref) {
            $ref = admin_url('admin.php?page=sc-attendance-logs');
        }
        wp_safe_redirect(add_query_arg('sc_logs_notice', $notice, remove_query_arg('sc_logs_notice', $ref)));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sc_attendance_logs_save_page_settings'])) {
        check_admin_referer('sc_attendance_logs_page_settings', 'sc_attendance_logs_page_settings_nonce');
        $attendance_logs_col_employee_code = isset($_POST['attendance_logs_col_employee_code']) ? 1 : 0;
        $attendance_logs_col_user_id = isset($_POST['attendance_logs_col_user_id']) ? 1 : 0;
        $attendance_logs_col_course = isset($_POST['attendance_logs_col_course']) ? 1 : 0;
        $attendance_logs_show_course_filter = isset($_POST['attendance_logs_show_course_filter']) ? 1 : 0;
        $attendance_logs_bulk_delete = isset($_POST['attendance_logs_bulk_delete']) ? 1 : 0;
        $attendance_logs_bulk_clear_match = isset($_POST['attendance_logs_bulk_clear_match']) ? 1 : 0;
        $attendance_logs_default_days_back = isset($_POST['attendance_logs_default_days_back']) ? max(1, min(366, absint($_POST['attendance_logs_default_days_back']))) : 7;
        sc_update_setting('attendance_logs_col_employee_code', $attendance_logs_col_employee_code, 'attendance');
        sc_update_setting('attendance_logs_col_user_id', $attendance_logs_col_user_id, 'attendance');
        sc_update_setting('attendance_logs_col_course', $attendance_logs_col_course, 'attendance');
        sc_update_setting('attendance_logs_show_course_filter', $attendance_logs_show_course_filter, 'attendance');
        sc_update_setting('attendance_logs_bulk_delete', $attendance_logs_bulk_delete, 'attendance');
        sc_update_setting('attendance_logs_bulk_clear_match', $attendance_logs_bulk_clear_match, 'attendance');
        sc_update_setting('attendance_logs_default_days_back', (string) $attendance_logs_default_days_back, 'attendance');
        if (function_exists('sc_log_activity')) {
            sc_log_activity('updated', 'settings', 0, 'تنظیمات صفحهٔ لاگ دستگاه حضور و غیاب از همان صفحه ذخیره شد', null, ['context' => 'attendance_logs_page']);
        }
        wp_safe_redirect(add_query_arg('sc_logs_settings_saved', '1', admin_url('admin.php?page=sc-attendance-logs')));
        exit;
    }

// -----------------------------
//  فیلترها
// -----------------------------
$filter_user       = isset($_GET['filter_user']) ? sanitize_text_field($_GET['filter_user']) : '';
$filter_course     = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;

$filter_date_from  = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
$filter_date_to    = isset($_GET['filter_date_to'])   ? sanitize_text_field($_GET['filter_date_to'])   : '';

$filter_time_from  = isset($_GET['filter_time_from']) ? sanitize_text_field($_GET['filter_time_from']) : '06:00';
$filter_time_to    = isset($_GET['filter_time_to'])   ? sanitize_text_field($_GET['filter_time_to'])   : '23:59';

$count_item_page            = isset($_GET['count_item_page']) ? sanitize_text_field($_GET['count_item_page']) : 20;
$search            = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';


// -----------------------------
// پیش‌فرض‌ها (تاریخ شمسی)
// -----------------------------

function today_jalali() {
    $today = new DateTime();
    list($jy, $jm, $jd) = gregorian_to_jalali(
        (int)$today->format("Y"),
        (int)$today->format("m"),
        (int)$today->format("d")
    );
    return sprintf("%04d/%02d/%02d", $jy, $jm, $jd);
}

function jalali_days_before($days) {
    $today = new DateTime();
    $today->modify("-" . intval($days) . " days");
    list($jy, $jm, $jd) = gregorian_to_jalali(
        (int)$today->format("Y"),
        (int)$today->format("m"),
        (int)$today->format("d")
    );
    return sprintf("%04d/%02d/%02d", $jy, $jm, $jd);
}

// اگر کاربر مقدار وارد نکرده باشد → مقدار پیش‌فرض فعال شود
if (empty($filter_date_from)) {
    $filter_date_from = jalali_days_before($default_days_back);
}

if (empty($filter_date_to)) {
    // تاریخ امروز
    $filter_date_to = today_jalali();
}

function jalali_to_greg($jalali_date) {
    if (empty($jalali_date)) return '';

    list($jy, $jm, $jd) = explode('/', $jalali_date);
    list($gy, $gm, $gd) = jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);

    return sprintf("%04d-%02d-%02d", $gy, $gm, $gd);
}

$g_date_from = jalali_to_greg($filter_date_from);
$g_date_to   = jalali_to_greg($filter_date_to);


    // -----------------------------
    //  pagination
    // -----------------------------
    $screen_per_page = get_user_meta(get_current_user_id(), 'attendance_logs_per_page', true);
    $per_page = $screen_per_page ? max(1, (int) $screen_per_page) : max(1, absint(trim((string) $count_item_page)));

    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // -----------------------------
    //  ساخت WHERE
    // -----------------------------
$where = "1=1";
$where_values = [];

// فیلتر کاربر
if (!empty($filter_user) && preg_match('/^m_(\d+)$/', $filter_user, $m)) {
    $uid = absint($m[1]);
    $where .= " AND l.employee_code = %d";
    $where_values[] = $uid;
}

// فیلتر تاریخ از
if (!empty($g_date_from)) {
    $where .= " AND DATE(l.log_datetime) >= %s";
    $where_values[] = $g_date_from;
}

// فیلتر تاریخ تا
if (!empty($g_date_to)) {
    $where .= " AND DATE(l.log_datetime) <= %s";
    $where_values[] = $g_date_to;
}

// فیلتر زمان از
if (!empty($filter_time_from)) {
    $where .= " AND TIME(l.log_datetime) >= %s";
    $where_values[] = $filter_time_from;
}

// فیلتر زمان تا
if (!empty($filter_time_to)) {
    $where .= " AND TIME(l.log_datetime) <= %s";
    $where_values[] = $filter_time_to;
}

// جستجو
if (!empty($search)) {
    $where .= " AND (m.first_name LIKE %s OR m.last_name LIKE %s)";
    $like = '%' . $wpdb->esc_like($search) . '%';
    $where_values[] = $like;
    $where_values[] = $like;
}

if ($filter_course > 0 && $log_has_matched_cols) {
    $where .= ' AND att.course_id = %d';
    $where_values[] = $filter_course;
}


    // -----------------------------
    //  count
    // -----------------------------
    $join_att_course_count = '';
    if ($log_has_matched_cols) {
        $join_att_course_count = " LEFT JOIN `{$att_table}` att ON att.id = l.matched_attendance_id
            LEFT JOIN `{$courses_table}` co ON co.id = att.course_id ";
    }
    $count_query = "
        SELECT COUNT(*)
        FROM $logs_table l
        INNER JOIN $members_table m
        ON m.id = CAST(l.employee_code AS UNSIGNED)
        {$join_att_course_count}
        WHERE $where
    ";

    if (!empty($where_values)) {
        $count_query = $wpdb->prepare($count_query, $where_values);
    }

    $total_items = $wpdb->get_var($count_query);
    $total_pages = ceil($total_items / $per_page);

    $join_att_course = '';
    if ($log_has_matched_cols) {
        $join_att_course = " LEFT JOIN `{$att_table}` att ON att.id = l.matched_attendance_id
            LEFT JOIN `{$courses_table}` co ON co.id = att.course_id ";
    }

    // -----------------------------
    //  دریافت رکوردها
    // -----------------------------
    $log_extra_select = ', l.employee_code, m.id AS member_wp_user_id, l.created_at';
    if ($log_has_matched_cols) {
        $log_extra_select .= ', l.matched_to_attendance, l.matched_attendance_id, att.course_id AS matched_course_id, co.title AS matched_course_title';
    }
    $main_query = "
        SELECT 
            l.id,
            l.log_datetime,
            m.first_name,
            m.last_name
            {$log_extra_select}
        FROM $logs_table l
        INNER JOIN $members_table m
            ON m.id = CAST(l.employee_code AS UNSIGNED)
        {$join_att_course}
        WHERE $where
        ORDER BY l.log_datetime DESC
        LIMIT %d OFFSET %d
    ";

    $where_values[] = $per_page;
    $where_values[] = $offset;

    $main_query = $wpdb->prepare($main_query, $where_values);
   
    $logs = $wpdb->get_results($main_query);
    
    // -----------------------------
    //  اعضای dropdown فیلتر
    // -----------------------------
    $members_for_filter = $wpdb->get_results("
    SELECT id, first_name, last_name
    FROM $members_table
    
    ");

    $courses_for_filter = [];
    if ($show_course_filter && $log_has_matched_cols) {
        $courses_for_filter = $wpdb->get_results(
            "SELECT id, title FROM `{$courses_table}` WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' ORDER BY title ASC"
        );
    }

    $table_colspan = ($show_bulk_bar ? 5 : 4) + ($log_has_matched_cols ? 1 : 0);
    if ($show_col_employee) {
        $table_colspan++;
    }
    if ($show_col_user_id) {
        $table_colspan++;
    }
    if ($show_col_course && $log_has_matched_cols) {
        $table_colspan++;
    }

    $logs_notice = isset($_GET['sc_logs_notice']) ? sanitize_text_field(wp_unslash($_GET['sc_logs_notice'])) : '';
    $logs_settings_saved = isset($_GET['sc_logs_settings_saved']) && (string) $_GET['sc_logs_settings_saved'] === '1';

    $selected_member_text = 'همه کاربران';
    if (!empty($filter_user) && preg_match('/^m_(\d+)$/', $filter_user, $member_match)) {
        $selected_member_id = absint($member_match[1]);
        foreach ($members_for_filter as $mem) {
            if ((int) $mem->id === $selected_member_id) {
                $selected_member_text = trim($mem->first_name . ' ' . $mem->last_name);
                break;
            }
        }
    }

    $active_filters_count = 0;
    if (!empty($filter_user) && $filter_user !== '0') {
        $active_filters_count++;
    }
    if ($filter_course > 0) {
        $active_filters_count++;
    }
    if (isset($_GET['filter_date_from']) && sanitize_text_field(wp_unslash((string) $_GET['filter_date_from'])) !== '') {
        $active_filters_count++;
    }
    if (isset($_GET['filter_date_to']) && sanitize_text_field(wp_unslash((string) $_GET['filter_date_to'])) !== '') {
        $active_filters_count++;
    }
    if (isset($_GET['filter_time_from'])) {
        $time_from_get = sanitize_text_field(wp_unslash((string) $_GET['filter_time_from']));
        if ($time_from_get !== '' && $time_from_get !== '06:00') {
            $active_filters_count++;
        }
    }
    if (isset($_GET['filter_time_to'])) {
        $time_to_get = sanitize_text_field(wp_unslash((string) $_GET['filter_time_to']));
        if ($time_to_get !== '' && $time_to_get !== '23:59') {
            $active_filters_count++;
        }
    }
    if ($search !== '') {
        $active_filters_count++;
    }
    if (isset($_GET['count_item_page']) && trim((string) $count_item_page) !== '' && (string) $count_item_page !== '20') {
        $active_filters_count++;
    }
    $filters_open = $active_filters_count > 0;
    $clear_filters_url = admin_url('admin.php?page=sc-attendance-logs');

    ?>

    <div class="wrap sc-members-list-wrap sc-attendance-logs-wrap">

        <div class="sc-members-list-header">
            <div class="sc-members-list-header-text">
                <h1 class="sc-members-list-title">لاگ‌های تردد دستگاه حضور و غیاب</h1>
                <p class="sc-members-list-desc">ثبت ترددهای دستگاه، تطبیق با حضور و فیلتر بر اساس بازیکن، دوره و بازه زمانی.</p>
            </div>
        </div>

        <?php if ($logs_settings_saved) : ?>
            <div class="notice notice-success is-dismissible"><p>تنظیمات صفحهٔ لاگ ذخیره شد.</p></div>
        <?php endif; ?>

        <?php
        // مثل metabox وردپرس: پیش‌فرض بسته؛ بعد از ذخیرهٔ موفق باز می‌ماند تا پیام را ببینند.
        $sc_logs_settings_box_open = $logs_settings_saved;
        ?>
        <div id="sc-attendance-logs-page-settings-box" class="sc-reports-list-panel postbox sc-attendance-logs-settings-postbox<?php echo $sc_logs_settings_box_open ? '' : ' closed'; ?>" >
            <div class="postbox-header sc-attendance-logs-settings-header" role="button" tabindex="0" aria-expanded="<?php echo $sc_logs_settings_box_open ? 'true' : 'false'; ?>" aria-controls="sc-attendance-logs-page-settings-inside">
                <h2>تنظیمات صفحه</h2>
                <div class="handle-actions hide-if-no-js">
                    <button type="button" class="handlediv sc-attendance-logs-settings-toggle" aria-expanded="<?php echo $sc_logs_settings_box_open ? 'true' : 'false'; ?>">
                        <span class="screen-reader-text">باز و بسته کردن تنظیمات صفحهٔ لاگ</span>
                        <span class="toggle-indicator" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
            <div id="sc-attendance-logs-page-settings-inside" class="inside">
                <p class="sc-reports-note">این موارد همان مقادیر تب «حضور و غیاب» در تنظیمات افزونه است؛ از اینجا هم می‌توانید بدون ترک صفحهٔ لاگ ویرایش کنید. تعداد رکورد در هر صفحه فقط از فیلتر پایین (فیلد «تعداد نمایش») قابل تغییر است.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=sc-attendance-logs')); ?>">
                    <?php wp_nonce_field('sc_attendance_logs_page_settings', 'sc_attendance_logs_page_settings_nonce'); ?>
                    <input type="hidden" name="sc_attendance_logs_save_page_settings" value="1">
                    <table class="form-table" role="presentation" style="margin-top:0;">
                        <tbody>
                        <tr>
                            <th scope="row">ستون‌ها</th>
                            <td>
                                <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="attendance_logs_col_employee_code" value="1" <?php checked($show_col_employee, true); ?>> <code>employee_code</code></label>
                                <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="attendance_logs_col_user_id" value="1" <?php checked($show_col_user_id, true); ?>> شناسهٔ کاربر وردپرس عضو</label>
                                <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="attendance_logs_col_course" value="1" <?php checked($show_col_course, true); ?>> دورهٔ مرتبط با حضور (بعد از تطبیق)</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">فیلتر دوره</th>
                            <td>
                                <label><input type="checkbox" name="attendance_logs_show_course_filter" value="1" <?php checked($show_course_filter, true); ?>> نمایش فیلتر دوره در بالای لیست</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">عملیات دسته‌جمعی</th>
                            <td>
                                <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="attendance_logs_bulk_delete" value="1" <?php checked($bulk_delete_on, true); ?>> حذف دسته‌جمعی</label>
                                <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="attendance_logs_bulk_clear_match" value="1" <?php checked($bulk_clear_match_setting, true); ?>> لغو تطبیق دسته‌جمعی <?php if (!$log_has_matched_cols) : ?><span class="description">(پس از به‌روزرسانی دیتابیس)</span><?php endif; ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">پیش‌فرض «از تاریخ»</th>
                            <td>
                                <input name="attendance_logs_default_days_back" type="number" min="1" max="366" class="small-text" value="<?php echo esc_attr((string) $default_days_back); ?>" dir="ltr">
                                <span class="description">روز به عقب وقتی کاربر تاریخ «از» را خالی بگذارد.</span>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <div class="sc-members-list-filters-actions">
                        <button type="submit" class="button button-primary">ذخیرهٔ تنظیمات صفحه</button>
                    </div>
                </form>
            </div>
        </div>
   
        <script>
        (function () {
            var box = document.getElementById('sc-attendance-logs-page-settings-box');
            if (!box) return;
            function setOpen(open) {
                box.classList.toggle('closed', !open);
                var hdr = box.querySelector('.sc-attendance-logs-settings-header');
                var btn = box.querySelector('.sc-attendance-logs-settings-toggle');
                if (hdr) hdr.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            function toggle() {
                setOpen(box.classList.contains('closed'));
            }
            var hdr = box.querySelector('.sc-attendance-logs-settings-header');
            var btn = box.querySelector('.sc-attendance-logs-settings-toggle');
            if (hdr) {
                hdr.addEventListener('click', function (e) {
                    if (e.target.closest('button.handlediv')) return;
                    toggle();
                });
                hdr.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggle();
                    }
                });
            }
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    toggle();
                });
            }
        })();
        </script>

    <div class="sc-members-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-members-list-filters-toolbar">
            <button type="button"
                    class="sc-members-list-filters-toggle"
                    id="sc-attendance-logs-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-attendance-logs-filters-panel">
                <span class="sc-members-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-members-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-members-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-members-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url($clear_filters_url); ?>" class="sc-members-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" action="" class="sc-members-list-filters-panel" id="sc-attendance-logs-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-attendance-logs">

            <div class="sc-filter-grid sc-attendance-logs-filter-grid">

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_user">نام کاربر</label>
                    <div class="sc-searchable-dropdown">
                        <input type="hidden" name="filter_user" id="filter_user" value="<?php echo esc_attr($filter_user); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if (!empty($filter_user) && $filter_user !== '0') echo 'style="display:none"'; ?>>همه کاربران</span>
                            <span class="sc-dropdown-selected" <?php if (empty($filter_user) || $filter_user === '0') echo 'style="display:none"'; ?>>
                                <?php echo esc_html($selected_member_text); ?>
                            </span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد بازیکن...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option sc-visible" data-value="0" data-search="همه کاربران" onclick="scSelectMemberFilter(this,'0','همه کاربران')">همه کاربران</div>
                                <?php
                                $display_count = 0;
                                $max_display = 15;
                                foreach ($members_for_filter as $mem) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                    $val = 'm_' . $mem->id;
                                    $label = $mem->first_name . ' ' . $mem->last_name;
                                    $search_txt = strtolower($mem->first_name . ' ' . $mem->last_name);
                                ?>
                                    <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>" data-value="<?php echo esc_attr($val); ?>" data-search="<?php echo esc_attr($search_txt); ?>" onclick="scSelectMemberFilter(this,'<?php echo esc_js($val); ?>','<?php echo esc_js($label); ?>')">
                                        <?php echo esc_html($label); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($show_course_filter && $log_has_matched_cols) : ?>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_course">دوره</label>
                    <select name="filter_course" id="filter_course" class="sc-filter-control">
                        <option value="0" <?php selected($filter_course, 0); ?>>همه دوره‌ها</option>
                        <?php foreach ($courses_for_filter as $crs) : ?>
                            <option value="<?php echo esc_attr((string) $crs->id); ?>" <?php selected($filter_course, (int) $crs->id); ?>>
                                <?php echo esc_html($crs->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ</label>
                    <div class="sc-date-range">
                        <input type="text" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>" class="persian-date-input sc-filter-control sc-no-default-date" placeholder="از تاریخ" readonly>
                        <input type="text" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>" class="persian-date-input sc-filter-control sc-no-default-date" placeholder="تا تاریخ" readonly>
                    </div>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label">بازه زمان</label>
                    <div class="sc-date-range">
                        <input type="time" name="filter_time_from" value="<?php echo esc_attr($filter_time_from); ?>" class="sc-filter-control">
                        <input type="time" name="filter_time_to" value="<?php echo esc_attr($filter_time_to); ?>" class="sc-filter-control">
                    </div>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="count_item_page">تعداد در صفحه</label>
                    <input type="text" name="count_item_page" id="count_item_page" value="<?php echo esc_attr(trim((string) $count_item_page)); ?>" class="sc-filter-control">
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="search_id">جستجو</label>
                    <input type="search" id="search_id" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجوی نام بازیکن..." class="sc-filter-control">
                </div>

            </div>

            <div class="sc-members-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url($clear_filters_url); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

        <?php
        if ($logs_notice === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>رکوردهای انتخاب‌شده از لاگ حذف شدند.</p></div>';
        } elseif ($logs_notice === 'cleared') {
            echo '<div class="notice notice-success is-dismissible"><p>تطبیق انتخاب‌شده‌ها با حضور لغو شد (رکورد حضور دست‌نخورده است).</p></div>';
        } elseif ($logs_notice === 'no_ids') {
            echo '<div class="notice notice-warning is-dismissible"><p>حداقل یک ردیف را انتخاب کنید.</p></div>';
        } elseif ($logs_notice === 'pick_action') {
            echo '<div class="notice notice-warning is-dismissible"><p>یک عملیات دسته‌جمعی از فهرست انتخاب کنید.</p></div>';
        } elseif ($logs_notice === 'forbidden') {
            echo '<div class="notice notice-error is-dismissible"><p>این عملیات مجاز نیست یا از تنظیمات غیرفعال شده است.</p></div>';
        }
        ?>
        <div class="sc-attendance-logs-meta">
            <span class="sc-attendance-logs-count">تعداد: <strong><?php echo number_format_i18n((int) $total_items); ?></strong> مورد</span>
        </div>

        <div class="sc-members-list-table-card">
        <form method="post" class="sc-attendance-logs-table-form">
            <?php wp_nonce_field('sc_attendance_logs_bulk', 'sc_attendance_logs_bulk_nonce'); ?>

            <?php if ($show_bulk_bar) : ?>
            <div class="tablenav top sc-attendance-logs-bulk-bar">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector" class="screen-reader-text">عملیات دسته‌جمعی</label>
                    <select name="bulk_action" id="bulk-action-selector" class="sc-filter-control">
                        <option value="-1">عملیات دسته‌جمعی…</option>
                        <?php if ($bulk_delete_on) : ?>
                        <option value="delete">حذف رکوردهای لاگ</option>
                        <?php endif; ?>
                        <?php if ($bulk_clear_on) : ?>
                        <option value="clear_match">لغو تطبیق با حضور</option>
                        <?php endif; ?>
                    </select>
                    <input type="submit" name="sc_attendance_logs_bulk_submit" id="doaction" class="button action" value="اعمال">
                </div>
            </div>
            <?php endif; ?>

            <div class="sc-attendance-logs-table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <?php if ($show_bulk_bar) : ?>
                    <td id="cb" class="manage-column check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <?php endif; ?>
                    <th>شناسه</th>
                    <th>نام کاربر</th>
                    <th>تاریخ</th>
                    <th>زمان</th>
                    <th>تاریخ ثبت</th>
                    <?php if ($show_col_employee) : ?>
                    <th><code>employee_code</code></th>
                    <?php endif; ?>
                    <?php if ($show_col_user_id) : ?>
                    <th>شناسه کاربر </th>
                    <?php endif; ?>
                    <?php if ($show_col_course && $log_has_matched_cols) : ?>
                    <th>دورهٔ حضور ثبت‌شده</th>
                    <?php endif; ?>
                    <?php if ($log_has_matched_cols) : ?>
                    <th>حضور خودکار</th>
                    <?php endif; ?>
                </tr>
                </thead>

                <tbody>

                <?php if ($logs):
                    ?>
                       
                    <?php foreach ($logs as $log): ?>
                        <?php
                            $date = sc_date_shamsi_date_only(date('Y/m/d', strtotime($log->log_datetime)));
                            $time = date('H:i', strtotime($log->log_datetime));
                            $full_name = trim($log->first_name . ' ' . $log->last_name);
                            $initials = '';
                            if (!empty($log->first_name)) {
                                $initials .= mb_substr((string) $log->first_name, 0, 1);
                            }
                            if (!empty($log->last_name)) {
                                $initials .= mb_substr((string) $log->last_name, 0, 1);
                            }
                            if ($initials === '') {
                                $initials = '؟';
                            }
                        ?>

                        <tr>
                            <?php if ($show_bulk_bar) : ?>
                            <th class="check-column">
                                <input type="checkbox" name="log_ids[]" value="<?php echo esc_attr($log->id); ?>">
                            </th>
                            <?php endif; ?>
                            <td><span class="sc-member-meta-item" style="font-weight:700;color:#6b7280;">#<?php echo esc_html($log->id); ?></span></td>
                            <td>
                                <span class="sc-member-identity">
                                    <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                                    <span class="sc-member-identity-text"><span class="sc-member-name"><?php echo esc_html($full_name); ?></span></span>
                                </span>
                            </td>
                            <td><?php echo esc_html($date); ?></td>
                            <td><?php echo esc_html($time); ?></td>
                            <td><?php echo !empty($log->created_at) ? esc_html(sc_date_shamsi_date_only($log->created_at) . ' ' . date('H:i', strtotime($log->created_at))) : '—'; ?></td>
                            <?php if ($show_col_employee) : ?>
                            <td><?php echo esc_html((string) ($log->employee_code ?? '')); ?></td>
                            <?php endif; ?>
                            <?php if ($show_col_user_id) : ?>
                            <td><?php
                                $uid = isset($log->member_wp_user_id) ? $log->member_wp_user_id : null;
                                echo ($uid !== null && $uid !== '' && (int) $uid > 0) ? esc_html((string) (int) $uid) : '—';
                            ?></td>
                            <?php endif; ?>
                            <?php if ($show_col_course && $log_has_matched_cols) : ?>
                            <td><?php
                            if (!empty($log->matched_to_attendance) && !empty($log->matched_course_title)) {
                                echo esc_html($log->matched_course_title);
                                if (!empty($log->matched_attendance_id)) {
                                    echo ' <span class="description">(حضور #' . (int) $log->matched_attendance_id . ')</span>';
                                }
                            } else {
                                echo '—';
                            }
                            ?></td>
                            <?php endif; ?>
                            <?php if ($log_has_matched_cols) : ?>
                            <td><?php echo !empty($log->matched_to_attendance) ? '<span class="sc-badge sc-badge--success">بله</span>' : '<span class="sc-badge sc-badge--muted">—</span>'; ?></td>
                            <?php endif; ?>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo (int) $table_colspan; ?>">
                            <div class="sc-attendance-logs-empty">هیچ لاگی یافت نشد.</div>
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>
            </div>

        </form>

        <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom sc_paginate">
            <div class="tablenav-pages">
                <?php
                    $pagination_base = add_query_arg(
                        [
                            'page' => 'sc-attendance-logs',
                            'paged' => '%#%',
                            'filter_user' => $filter_user,
                            'filter_course' => $filter_course,
                            'filter_date_from' => $filter_date_from,
                            'filter_date_to' => $filter_date_to,
                            'filter_time_from' => $filter_time_from,
                            'filter_time_to' => $filter_time_to,
                            'count_item_page' => trim((string) $count_item_page),
                            's' => $search,
                        ],
                        admin_url('admin.php')
                    );
                    echo paginate_links([
                        'base' => esc_url($pagination_base),
                        'format' => '',
                        'prev_text' => '< قبلی ',
                        'next_text' => ' بعدی >',
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]);
                    ?>
            </div>
        </div>
        <?php endif; ?>
        </div>

    </div>

    <script type="text/javascript">
    jQuery(function ($) {
        var $toggle = $('#sc-attendance-logs-filters-toggle');
        var $panel = $('#sc-attendance-logs-filters-panel');
        var $card = $toggle.closest('.sc-members-list-filters-card');
        var $label = $toggle.find('.sc-members-list-filters-toggle-label');

        $toggle.on('click', function () {
            var isOpen = $card.hasClass('is-open');
            if (isOpen) {
                $card.removeClass('is-open');
                $panel.attr('hidden', true);
                $toggle.attr('aria-expanded', 'false');
                $label.text($label.data('label-closed'));
            } else {
                $card.addClass('is-open');
                $panel.removeAttr('hidden');
                $toggle.attr('aria-expanded', 'true');
                $label.text($label.data('label-open'));
            }
        });
    });

    (function () {
        var cbAll = document.getElementById('cb-select-all');
        if (cbAll) {
            cbAll.addEventListener('click', function () {
                document.querySelectorAll('input[name="log_ids[]"]').forEach(function (ch) {
                    ch.checked = cbAll.checked;
                });
            });
        }
    })();
    </script>

<?php

