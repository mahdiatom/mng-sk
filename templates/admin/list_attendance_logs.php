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
    // یک هفته قبل
    $filter_date_from = jalali_days_before(7);
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
    $per_page = $screen_per_page ? max(1, (int)$screen_per_page) :$count_item_page;

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
    $log_extra_select = ', l.employee_code, m.id AS member_wp_user_id';
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

    $table_colspan = 5 + ($log_has_matched_cols ? 1 : 0);
    if ($show_col_employee) {
        $table_colspan++;
    }
    if ($show_col_user_id) {
        $table_colspan++;
    }
    if ($show_col_course && $log_has_matched_cols) {
        $table_colspan++;
    }

    ?>

    <div class="wrap">

        <h1 class="wp-heading-inline">لاگ حضور و غیاب</h1>
        <hr class="wp-header-end">
</div>

<div class="filter_search_honors">
 <div class="wrap">
    <!-- فیلترها -->
    <form method="get" action="" class="filter_attendance_log_list">
        <input type="hidden" name="page" value="sc-attendance-logs">
        <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
<div class="col-1-attendance">
        <!-- فیلتر کاربر -->
        <label for="filter_user" style="margin-left: 5px; width: 100px;">نام کاربر:</label>
        <div class="sc-searchable-dropdown">
            <input type="hidden" name="filter_user" id="filter_user" value="<?php echo esc_attr($filter_user); ?>">

            <div class="sc-dropdown-toggle" style="width: 100%;">
                <span class="sc-dropdown-placeholder" <?php if (!empty($filter_user) && $filter_user !== '0') echo 'style="display:none"'; ?>>همه کاربران</span>
                <span class="sc-dropdown-selected" <?php if (empty($filter_user) || $filter_user === '0') echo 'style="display:none"'; ?>>
                    نام کاربر....
                </span>
                <span class="sc-dropdown-arrow">▼</span>
            </div>

            <div class="sc-dropdown-menu">
                <div class="sc-dropdown-search">
                    <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد بازیکن...">
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
                    $max_display = 15;
                    foreach ($members_for_filter as $mem) :
                        $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                        $display_count++;
                        $val = 'm_' . $mem->id;
                        $label = $mem->first_name . ' ' . $mem->last_name ;
                        $search_txt = strtolower($mem->first_name . ' ' . $mem->last_name );
                    ?>
                        <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                             data-value="<?php echo esc_attr($val); ?>"
                             data-search="<?php echo esc_attr($search_txt); ?>"
                             onclick="scSelectMemberFilter(this,'<?php echo esc_js($val); ?>','<?php echo esc_js($label); ?>')">
                            <?php echo esc_html($label); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php if ($show_course_filter && $log_has_matched_cols) : ?>
        <br>
        <label for="filter_course" style="margin-left: 5px; width: 100px;">دوره:</label>
        <select name="filter_course" id="filter_course" style="min-width:220px;max-width:100%;">
            <option value="0" <?php selected($filter_course, 0); ?>>همه دوره‌ها</option>
            <?php foreach ($courses_for_filter as $crs) : ?>
                <option value="<?php echo esc_attr((string) $crs->id); ?>" <?php selected($filter_course, (int) $crs->id); ?>>
                    <?php echo esc_html($crs->title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php elseif ($show_course_filter && !$log_has_matched_cols) : ?>
        <p class="description" style="margin-top:8px;">فیلتر دوره پس از به‌روزرسانی دیتابیس (ستون‌های تطبیق لاگ) فعال می‌شود.</p>
        <?php endif; ?>
        <br>
            <!-- فیلتر تاریخ: از -->
            <label style="margin-left:10px;width:60px;">از تاریخ:</label>
            <input type="text"
                name="filter_date_from"
                value="<?php echo esc_attr($filter_date_from); ?>"
                class="persian-date-input"
                placeholder="1403/01/01"
                readonly
                style="width:140px;">

            <!-- فیلتر تاریخ: تا -->
            <label style="margin-left:10px;width:60px;">تا تاریخ:</label>
            <input type="text"
                name="filter_date_to"
                value="<?php echo esc_attr($filter_date_to); ?>"
                class="persian-date-input"
                placeholder="1403/12/29"
                readonly
                style="width:140px;">
</div>

<div class="col-2-attendance">

        <!-- فیلتر زمان -->
            <label style="margin-left:10px;">از زمان:</label>
            <input type="time" class="filter_time" name="filter_time_from" value="<?php echo esc_attr($filter_time_from); ?>">

            <label style="margin-left:10px;">تا زمان:</label>
            <input type="time" class="filter_time" name="filter_time_to" value="<?php echo esc_attr($filter_time_to); ?>">
             <label >تعداد نمایش رکورد ها در صفحه </label>
            <input type="text" style="width:60px" name="count_item_page" value="<?php echo $count_item_page; ?> " >
        <input type="submit" class="button" value="اعمال فیلتر" style="margin-left: 10px;">
    </form>
</div>
    <!-- جستجو بالای جدول -->
    <div class="tablenav top" style="margin-bottom: 0;">
        <div class="alignleft actions">
            <form method="get" action="">
                <input type="hidden" name="page" value="sc-attendance-logs">
                <input type="hidden" name="filter_user" value="<?php echo esc_attr($filter_user); ?>">
                <input type="hidden" name="filter_course" value="<?php echo esc_attr((string) $filter_course); ?>">
                <input type="hidden" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                <input type="hidden" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                <input type="hidden" name="filter_time_from" value="<?php echo esc_attr($filter_time_from); ?>">
                <input type="hidden" name="filter_time_to" value="<?php echo esc_attr($filter_time_to); ?>">
                <input type="hidden" name="count_item_page" value="<?php echo esc_attr((string) $count_item_page); ?>">
                <label class="screen-reader-text" for="search_id">جستجو:</label>
                <input type="search" id="search_id" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجوی نام بازیکن..." style="width: 220px;">
                <input type="submit" id="search-submit" class="button" value="جستجو">
            </form>
        </div>
    </div>

</div>

</div>
 <div class="wrap">
        <form method="post">
            <?php wp_nonce_field('delete_attendance_logs_nonce'); ?>
            <input type="hidden" name="action" value="delete">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <td id="cb" class="manage-column check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th> رکوردشناسه </th>
                    <th>نام کاربر</th>
                    <th>تاریخ</th>
                    <th>زمان</th>
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
                            $date =sc_date_shamsi_date_only( date('Y/m/d', strtotime($log->log_datetime)));
                            $time = date('H:i',     strtotime($log->log_datetime));
                        ?>

                        <tr>

                            <th class="check-column">
                                <input type="checkbox" name="log_ids[]" value="<?php echo esc_attr($log->id); ?>">
                            </th>
                            <td><?php echo esc_html($log->id); ?></td>
                            <td><?php echo esc_html($log->first_name . ' ' . $log->last_name); ?></td>
                            <td><?php echo esc_html($date); ?></td>
                            <td><?php echo esc_html($time); ?></td>
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
                            <td><?php echo !empty($log->matched_to_attendance) ? 'بله' . (!empty($log->matched_attendance_id) ? ' (#' . (int) $log->matched_attendance_id . ')' : '') : '—'; ?></td>
                            <?php endif; ?>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo (int) $table_colspan; ?>" style="text-align:center;padding:20px;">
                            هیچ لاگی یافت نشد.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>

            <br>

            <input type="submit" class="button button-danger" value="حذف انتخاب‌شده‌ها"
                   onclick="return confirm('آیا مطمئن هستید؟');">

        </form>

        <div class="tablenav bottom sc_paginate">
            <div class="tablenav-pages">
                <?php
                   
                    $page_links = paginate_links([
                        'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                        'format' => '',
                        'prev_text' => '< قبلی ',
                        'next_text' => ' بعدی >',
                        'total' => $total_pages,
                        'current' => $current_page
                        
                    ]);
                    echo $page_links;
                    ?>
            </div>
        </div>

    </div> <!-- wrap -->

    <!-- JS -->
    <script>
    // انتخاب همه
    document.getElementById('cb-select-all').addEventListener('click', function () {
        const items = document.querySelectorAll('input[name="log_ids[]"]');
        items.forEach(ch => ch.checked = this.checked);
    });

   

    document.addEventListener("click", function(e) {
        const dd = e.target.closest('.sc-searchable-dropdown');
        document.querySelectorAll('.sc-dropdown-menu').forEach(menu => {
            if (!dd) menu.style.display = 'none';
        });
        if (dd) {
            const menu = dd.querySelector('.sc-dropdown-menu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
    });

    // جستجو داخل dropdown
    document.querySelectorAll('.sc-search-input').forEach(inp => {
        inp.addEventListener('keyup', function(){
            let val = this.value.toLowerCase();
            let opts = this.closest('.sc-dropdown-menu').querySelectorAll('.sc-dropdown-option');
            opts.forEach(o => {

            let search = o.getAttribute('data-search') || '';

            if (search.includes(val)) {
                o.style.display = 'block';
            } else {
                o.style.display = 'none';
            }

        });

        });
    });

    </script>

    <!-- CSS -->
    <style>
    .sc-searchable-dropdown {
        position: relative;
        width: 250px;
        display: inline-block;
        cursor: pointer;
    }
    .sc-dropdown-toggle {
        padding: 7px 10px;
        background: #fff;
        border: 1px solid #ccc;
    }
    .sc-dropdown-arrow {
        float: right;
    }
    .sc-dropdown-menu {
        display: none;
        position: absolute;
        width: 100%;
        background: #fff;
        border: 1px solid #ddd;
        z-index: 999;
        max-height: 260px;
        overflow-y: auto;
    }
    .sc-dropdown-search {
        padding: 8px;
        background: #f5f5f5;
        border-bottom: 1px solid #ddd;
    }
    .sc-search-input {
        width: 100%;
        padding: 6px;
    }
    .sc-dropdown-option {
        padding: 8px 10px;
        border-bottom: 1px solid #eee;
    }
    .sc-dropdown-option:hover {
        background: #f1f1f1;
    }
    </style>

<?php

