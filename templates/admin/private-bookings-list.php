<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options') && !current_user_can('club_coach') && !current_user_can('sc_view_coach_salary') && !current_user_can('coach')) {
    wp_die('دسترسی غیرمجاز.');
}

global $wpdb;
$current_admin_page = isset($sc_private_bookings_page_slug) ? (string) $sc_private_bookings_page_slug : 'sc-private-bookings-list';
$force_coach_scope = !empty($sc_private_bookings_force_coach_scope);
$sessions_table = $wpdb->prefix . 'sc_private_booking_sessions';
$bookings_table = $wpdb->prefix . 'sc_private_course_bookings';
$courses_table = $wpdb->prefix . 'sc_courses';
$coaches_table = $wpdb->prefix . 'sc_coaches';
$members_table = $wpdb->prefix . 'sc_members';

$can_manage_all = !$force_coach_scope && (current_user_can('manage_options') || current_user_can('club_coach'));
$is_coach_only = !$can_manage_all && (current_user_can('sc_view_coach_salary') || current_user_can('coach'));
$current_coach_id = $is_coach_only && function_exists('sc_current_user_coach_id') ? (int) sc_current_user_coach_id() : 0;
if ($is_coach_only && $current_coach_id <= 0) {
    wp_die('مربی جاری قابل تشخیص نیست.');
}

$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_coach = ($can_manage_all && isset($_GET['filter_coach'])) ? absint($_GET['filter_coach']) : 0;
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all';

$cancellable_statuses = ['scheduled'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_private_admin_action'])) {
    check_admin_referer('sc_private_admin_sessions_actions');
    $action = sanitize_text_field(wp_unslash($_POST['sc_private_admin_action']));
    $selected_ids = isset($_POST['session_ids']) ? array_map('absint', (array) $_POST['session_ids']) : [];
    $selected_ids = array_values(array_filter($selected_ids));

    if ($action === 'cancel_single' && isset($_POST['session_id'])) {
        $selected_ids = [absint($_POST['session_id'])];
    }
    if (($action === 'cancel_selected' || $action === 'cancel_single') && !empty($selected_ids)) {
        $holders = implode(',', array_fill(0, count($selected_ids), '%d'));
        $allowed_status_holders = implode(',', array_fill(0, count($cancellable_statuses), '%s'));
        $params = $selected_ids;
        $sql = "SELECT id, coach_id, status FROM {$sessions_table} WHERE id IN ({$holders})";
        if ($is_coach_only) {
            $sql .= " AND coach_id = %d";
            $params[] = $current_coach_id;
        }
        $rows_for_cancel = $wpdb->get_results($wpdb->prepare($sql, $params));
        $done = 0;
        foreach ($rows_for_cancel as $row_for_cancel) {
            if (!in_array((string) $row_for_cancel->status, $cancellable_statuses, true)) {
                continue;
            }
            $updated = sc_private_update_session_status((int) $row_for_cancel->id, 'cancelled', true);
            if ($updated) {
                if (function_exists('sc_private_send_cancel_sms')) {
                    sc_private_send_cancel_sms((int) $row_for_cancel->id, 'coach_admin');
                }
                $done++;
                if (function_exists('sc_log_activity')) {
                    sc_log_activity(
                        'updated',
                        'private_session',
                        (int) $row_for_cancel->id,
                        sprintf('جلسه خصوصی #%d توسط مدیر/مربی لغو شد.', (int) $row_for_cancel->id),
                        ['status' => $row_for_cancel->status],
                        ['status' => 'cancelled']
                    );
                }
            }
        }
        if ($done > 0) {
            add_settings_error('sc_private_sessions', 'sc_private_sessions_success', sprintf('%d جلسه با موفقیت لغو شد.', $done), 'updated');
        } else {
            add_settings_error('sc_private_sessions', 'sc_private_sessions_empty', 'جلسه قابل لغوی انتخاب نشد.', 'error');
        }
    } else {
        add_settings_error('sc_private_sessions', 'sc_private_sessions_invalid', 'عملیات نامعتبر است.', 'error');
    }
}

$filter_date_from = '';
$filter_date_to = '';
$filter_date_from_shamsi = '';
$filter_date_to_shamsi = '';
if (!empty($_GET['filter_date_from_shamsi'])) {
    $filter_date_from_shamsi = sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi']));
    $filter_date_from = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($filter_date_from_shamsi) : '';
}
if (!empty($_GET['filter_date_to_shamsi'])) {
    $filter_date_to_shamsi = sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi']));
    $filter_date_to = function_exists('sc_shamsi_to_gregorian_date') ? sc_shamsi_to_gregorian_date($filter_date_to_shamsi) : '';
}

$today_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
$display_date_from_shamsi = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $today_shamsi;
$display_date_to_shamsi = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $today_shamsi;

$where = ["c.course_type = 'private'"];
$values = [];
if ($filter_course > 0) {
    $where[] = 'ps.course_id = %d';
    $values[] = $filter_course;
}
if ($is_coach_only) {
    $where[] = 'ps.coach_id = %d';
    $values[] = $current_coach_id;
} elseif ($filter_coach > 0) {
    $where[] = 'ps.coach_id = %d';
    $values[] = $filter_coach;
}
if ($filter_member > 0) {
    $where[] = 'ps.member_id = %d';
    $values[] = $filter_member;
}
if ($filter_status !== 'all') {
    $where[] = 'ps.status = %s';
    $values[] = $filter_status;
}
if ($filter_date_from !== '') {
    $where[] = 'ps.session_date >= %s';
    $values[] = $filter_date_from;
}
if ($filter_date_to !== '') {
    $where[] = 'ps.session_date <= %s';
    $values[] = $filter_date_to;
}
$where_clause = implode(' AND ', $where);

$count_sql = "SELECT COUNT(*)
              FROM {$sessions_table} ps
              INNER JOIN {$courses_table} c ON c.id = ps.course_id
              WHERE {$where_clause}";
$total_items = !empty($values) ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $values)) : (int) $wpdb->get_var($count_sql);

$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;
$total_pages = max(1, (int) ceil($total_items / $per_page));

$list_sql = "SELECT ps.*, b.invoice_id, c.title AS course_title, m.first_name, m.last_name, co.first_name AS coach_first_name, co.last_name AS coach_last_name
             FROM {$sessions_table} ps
             INNER JOIN {$bookings_table} b ON b.id = ps.booking_id
             INNER JOIN {$courses_table} c ON c.id = ps.course_id
             INNER JOIN {$members_table} m ON m.id = ps.member_id
             INNER JOIN {$coaches_table} co ON co.id = ps.coach_id
             WHERE {$where_clause}
             ORDER BY ps.session_date DESC, ps.time_start DESC
             LIMIT %d OFFSET %d";
$list_values = $values;
$list_values[] = $per_page;
$list_values[] = $offset;
$rows = $wpdb->get_results($wpdb->prepare($list_sql, $list_values));

$courses = $wpdb->get_results("SELECT id, title FROM {$courses_table} WHERE deleted_at IS NULL AND course_type = 'private' ORDER BY title ASC");
$coaches = $can_manage_all
    ? $wpdb->get_results("SELECT id, first_name, last_name FROM {$coaches_table} WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC")
    : [];
$members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM {$members_table} WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC LIMIT 500");

$status_options = [
    'all' => 'همه وضعیت‌ها',
    'scheduled' => 'برنامه‌ریزی‌شده',
    'done' => 'برگزار شده',
    'cancelled' => 'لغو شده',
    'absent' => 'غایب',
    'excused' => 'غیبت مجاز',
    'rescheduled' => 'جابجا شده',
];
?>
<div class="wrap">
    <style>

    </style>
    <h1 class="wp-heading-inline"><?php echo $is_coach_only ? 'کلاس‌های خصوصی من' : 'مدیریت کلاس‌های خصوصی'; ?></h1>
    <hr class="wp-header-end">
    <?php settings_errors('sc_private_sessions'); ?>
</div>
<div class="wrap">
    <form method="get" action="" class="form_fillter_attendance form_fillter_attendance_tab1" style="margin-top:12px;">
        <input type="hidden" name="page" value="<?php echo esc_attr($current_admin_page); ?>">
        <div class="sc-filter-grid">
            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_course">دوره</label>
                <select class="sc-filter-control" id="filter_course" name="filter_course">
                    <option value="0">همه دوره‌ها</option>
                    <?php foreach ($courses as $course) : ?>
                        <option value="<?php echo esc_attr((int) $course->id); ?>" <?php selected($filter_course, (int) $course->id); ?>><?php echo esc_html($course->title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($can_manage_all) : ?>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_coach">مربی</label>
                    <select class="sc-filter-control" id="filter_coach" name="filter_coach">
                        <option value="0">همه مربیان</option>
                        <?php foreach ($coaches as $coach) : ?>
                            <option value="<?php echo esc_attr((int) $coach->id); ?>" <?php selected($filter_coach, (int) $coach->id); ?>><?php echo esc_html(trim($coach->first_name . ' ' . $coach->last_name)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_member">بازیکن</label>
                <select class="sc-filter-control" id="filter_member" name="filter_member">
                    <option value="0">همه بازیکنان</option>
                    <?php foreach ($members as $member) : ?>
                        <option value="<?php echo esc_attr((int) $member->id); ?>" <?php selected($filter_member, (int) $member->id); ?>><?php echo esc_html(trim($member->first_name . ' ' . $member->last_name) . ' - ' . $member->national_id); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sc-filter-field">
                <label class="sc-filter-label" for="filter_status">وضعیت</label>
                <select class="sc-filter-control" id="filter_status" name="filter_status">
                    <?php foreach ($status_options as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($filter_status, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sc-filter-field sc-filter-date">
                <label class="sc-filter-label">بازه تاریخ</label>
                <div class="sc-date-range">
                    <input type="text" name="filter_date_from_shamsi" class="sc-filter-control persian-date-input sc-no-default-date" value="<?php echo esc_attr($display_date_from_shamsi); ?>" readonly>
                    <span class="sc-date-separator">تا</span>
                    <input type="text" name="filter_date_to_shamsi" class="sc-filter-control persian-date-input sc-no-default-date" value="<?php echo esc_attr($display_date_to_shamsi); ?>" readonly>
                </div>
            </div>
        </div>
        <p class="submit">
            <button type="submit" class="button button-primary">اعمال فیلتر</button>
            <a class="sc_button delete_fillter" href="<?php echo esc_url(admin_url('admin.php?page=' . rawurlencode($current_admin_page))); ?>">پاک کردن فیلترها</a>
        </p>
    </form>
</div>
<div class="wrap">
    <?php if (empty($rows)) : ?>
        <div class="notice notice-info"><p>رکوردی یافت نشد.</p></div>
    <?php else : ?>
        <form method="post" action="">
            <?php wp_nonce_field('sc_private_admin_sessions_actions'); ?>
            <input type="hidden" name="sc_private_admin_action" value="cancel_selected">
            <div style="margin: 12px 0;">
                <button type="submit" class="button button-secondary" onclick="return scConfirmInline(event, { type: 'warning', message: 'جلسات انتخابی لغو شوند؟' });">لغو دسته‌جمعی جلسات انتخاب‌شده</button>
            </div>
        <div class="back_table_list" >
            <table class="wp-list-table widefat striped " style="margin-top:12px;">
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="sc-private-check-all"></th>
                        <th>تاریخ</th>
                        <th>ساعت</th>
                        <th>دوره</th>
                        <th>مربی</th>
                        <th>بازیکن</th>
                        <th>وضعیت</th>
                        <?php if (!$is_coach_only) : ?>
                            <th>رزرو</th>
                            <th>صورت‌حساب</th>
                        <?php endif; ?>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $status_label = function_exists('sc_private_session_status_label') ? sc_private_session_status_label($row->status) : $row->status;
                        $status_key = strtolower((string) $row->status);
                        $status_class = 'sc-private-status sc-private-status-' . preg_replace('/[^a-z_]/', '', $status_key);
                        $can_cancel_row = in_array((string) $row->status, $cancellable_statuses, true);
                        ?>
                        <tr>
                            <td>
                                <?php if ($can_cancel_row) : ?>
                                    <input type="checkbox" class="sc-private-session-checkbox" name="session_ids[]" value="<?php echo esc_attr((int) $row->id); ?>">
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($row->session_date) : $row->session_date); ?></td>
                            <td><?php echo esc_html(substr((string) $row->time_start, 0, 5) . ' تا ' . substr((string) $row->time_end, 0, 5)); ?></td>
                            <td><?php echo esc_html($row->course_title); ?></td>
                            <td><?php echo esc_html(trim($row->coach_first_name . ' ' . $row->coach_last_name)); ?></td>
                            <td><?php echo esc_html(trim($row->first_name . ' ' . $row->last_name)); ?></td>
                            <td><span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                            <?php if (!$is_coach_only) : ?>
                                <td>#<?php echo esc_html((string) $row->booking_id); ?></td>
                                <td><?php echo !empty($row->invoice_id) ? '#' . esc_html((string) $row->invoice_id) : '-'; ?></td>
                            <?php endif; ?>
                            <td>
                                <?php if ($can_cancel_row) : ?>
                                    <button type="submit" class="button-link-delete sc_button" name="session_id" value="<?php echo esc_attr((int) $row->id); ?>" onclick="this.form.sc_private_admin_action.value='cancel_single'; return scConfirmInline(event, { type: 'warning', message: 'این جلسه لغو شود؟' });">لغو جلسه</button>
                                <?php else : ?>
                                    <span style="color:#888;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </form>
        <script>
            (function () {
                var checkAll = document.getElementById('sc-private-check-all');
                if (!checkAll) return;
                checkAll.addEventListener('change', function () {
                    var boxes = document.querySelectorAll('.sc-private-session-checkbox');
                    for (var i = 0; i < boxes.length; i++) {
                        boxes[i].checked = !!checkAll.checked;
                    }
                });
            })();
        </script>
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom sc_paginate" style="margin-top:12px;">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                        'format' => '',
                        'prev_text' => '< قبلی',
                        'next_text' => 'بعدی >',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'add_args' => [
                            'page' => $current_admin_page,
                            'filter_course' => $filter_course,
                            'filter_coach' => $filter_coach,
                            'filter_member' => $filter_member,
                            'filter_status' => $filter_status,
                            'filter_date_from_shamsi' => $filter_date_from_shamsi,
                            'filter_date_to_shamsi' => $filter_date_to_shamsi,
                        ],
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
