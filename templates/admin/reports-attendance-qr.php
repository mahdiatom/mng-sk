<?php
if (!defined('ABSPATH')) {
    exit;
}

sc_check_and_create_tables();

global $wpdb;
$attendances_table = $wpdb->prefix . 'sc_attendances';
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$coaches_table = $wpdb->prefix . 'sc_coaches';
$users_table = $wpdb->users;

// حذف تکی
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['attendance_id'])) {
    $delete_id = absint($_GET['attendance_id']);
    check_admin_referer('sc_delete_qr_attendance_' . $delete_id);
    if (function_exists('sc_attendance_qr_delete_report_records')) {
        $del = sc_attendance_qr_delete_report_records([$delete_id]);
        if ($del['deleted'] > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>رکورد ثبت QR حذف شد.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>حذف رکورد ناموفق بود.</p></div>';
        }
    }
}

// حذف دسته‌جمعی
if (
    isset($_POST['bulk_action'], $_POST['attendance_ids'])
    && is_array($_POST['attendance_ids'])
    && sanitize_key(wp_unslash($_POST['bulk_action'])) === 'delete'
    && check_admin_referer('sc_bulk_qr_attendance_nonce')
) {
    $bulk_ids = array_map('absint', wp_unslash($_POST['attendance_ids']));
    if (function_exists('sc_attendance_qr_delete_report_records')) {
        $del = sc_attendance_qr_delete_report_records($bulk_ids);
        if ($del['deleted'] > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html(sprintf('%d رکورد ثبت QR حذف شد.', $del['deleted']))
                . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>هیچ رکوردی حذف نشد.</p></div>';
        }
    }
}

if (!function_exists('sc_attendance_where_coach_member_scope_list')) {
    function sc_attendance_where_coach_member_scope_list($coach_id) {
        global $wpdb;
        $mc = $wpdb->prefix . 'sc_member_courses';
        $coach_id = absint($coach_id);
        if (!$coach_id) {
            return '1=1';
        }

        return $wpdb->prepare(
            "EXISTS (SELECT 1 FROM `$mc` mc WHERE mc.member_id = a.member_id AND mc.course_id = a.course_id AND mc.status = %s AND (mc.course_status_flags IS NULL OR mc.course_status_flags = '' OR (mc.course_status_flags NOT LIKE %s AND mc.course_status_flags NOT LIKE %s AND mc.course_status_flags NOT LIKE %s)) AND (mc.coach_id = %d OR mc.coach_id IS NULL OR mc.coach_id = 0))",
            'active',
            '%paused%',
            '%completed%',
            '%canceled%',
            $coach_id
        );
    }
}

$current_user_id = get_current_user_id();

if (function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()
    && function_exists('sc_secretary_get_branch_courses_for_attendance_filter')) {
    $courses = sc_secretary_get_branch_courses_for_attendance_filter();
    $pw = function_exists('sc_secretary_append_member_where')
        ? sc_secretary_append_member_where('m.is_active = 1', 'm')
        : 'm.is_active = 1';
    $members = $wpdb->get_results(
        "SELECT DISTINCT m.id, m.first_name, m.last_name, m.national_id
         FROM $members_table m
         WHERE {$pw}
         ORDER BY m.last_name ASC, m.first_name ASC"
    );
} elseif (current_user_can('coach') && !current_user_can('administrator') && !current_user_can('club_coach')) {
    $coach = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    if ($coach) {
        $coach_id_scope = (int) $coach->id;
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.title
             FROM $courses_table c
             INNER JOIN $course_coaches_table cc ON c.id = cc.course_id
             WHERE cc.coach_id = %d
               AND c.deleted_at IS NULL
               AND c.is_active = 1
             ORDER BY c.title ASC",
            $coach_id_scope
        ));
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT m.id, m.first_name, m.last_name, m.national_id
             FROM $members_table m
             INNER JOIN $member_courses_table mc ON mc.member_id = m.id
             INNER JOIN $course_coaches_table cc ON cc.course_id = mc.course_id
             WHERE cc.coach_id = %d
               AND m.is_active = 1
               AND mc.status = 'active'
               AND (mc.coach_id = %d OR mc.coach_id IS NULL OR mc.coach_id = 0)
             ORDER BY m.last_name ASC, m.first_name ASC",
            $coach_id_scope,
            $coach_id_scope
        ));
    } else {
        $courses = [];
        $members = [];
    }
} else {
    $courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");
    $members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
}

$coaches_list = [];
if (current_user_can('club_coach') || current_user_can('administrator')) {
    $coaches_list = $wpdb->get_results("SELECT id, first_name, last_name, user_id FROM $coaches_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
}

$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$filter_coach = (current_user_can('club_coach') || current_user_can('administrator')) && isset($_GET['filter_coach'])
    ? absint($_GET['filter_coach'])
    : 0;
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all';

$filter_date_from = '';
$filter_date_to = '';
$filter_date_from_shamsi = '';
$filter_date_to_shamsi = '';

if (!empty($_GET['filter_date_from_shamsi'])) {
    $filter_date_from_shamsi = sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi']));
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
} elseif (!empty($_GET['filter_date_from'])) {
    $filter_date_from = sanitize_text_field(wp_unslash($_GET['filter_date_from']));
    $filter_date_from_shamsi = sc_date_shamsi_date_only($filter_date_from);
}

if (!empty($_GET['filter_date_to_shamsi'])) {
    $filter_date_to_shamsi = sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi']));
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
} elseif (!empty($_GET['filter_date_to'])) {
    $filter_date_to = sanitize_text_field(wp_unslash($_GET['filter_date_to']));
    $filter_date_to_shamsi = sc_date_shamsi_date_only($filter_date_to);
}

$today_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
$display_date_from_shamsi = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $today_shamsi;
$display_date_to_shamsi = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $today_shamsi;

$where_conditions = ["a.record_method = 'qr'"];
$where_values = [];

if (current_user_can('coach') && !current_user_can('administrator') && !current_user_can('club_coach')) {
    $coach = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
        $current_user_id
    ));
    if ($coach) {
        $coach_id = (int) $coach->id;
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
        $coach_course_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT course_id FROM $course_coaches_table WHERE coach_id = %d",
            $coach_id
        ));
        if (!empty($coach_course_ids)) {
            $placeholders = implode(',', array_fill(0, count($coach_course_ids), '%d'));
            $where_conditions[] = "a.course_id IN ($placeholders)";
            $where_values = array_merge($where_values, $coach_course_ids);
            if (function_exists('sc_attendance_where_coach_member_scope_list')) {
                $where_conditions[] = sc_attendance_where_coach_member_scope_list($coach_id);
            }
        } else {
            $where_conditions[] = '1=0';
        }
    } else {
        $where_conditions[] = '1=0';
    }
}

if ($filter_course > 0) {
    $where_conditions[] = 'a.course_id = %d';
    $where_values[] = $filter_course;
}

if ($filter_member > 0) {
    $where_conditions[] = 'a.member_id = %d';
    $where_values[] = $filter_member;
}

if ($filter_coach > 0) {
    $coach_user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $coaches_table WHERE id = %d LIMIT 1",
        $filter_coach
    ));
    if ($coach_user_id) {
        $where_conditions[] = 'a.user_id = %d';
        $where_values[] = $coach_user_id;
    }
}

if ($filter_status !== 'all') {
    $where_conditions[] = 'a.status = %s';
    $where_values[] = $filter_status;
}

if ($filter_date_from) {
    $where_conditions[] = 'a.attendance_date >= %s';
    $where_values[] = $filter_date_from;
}

if ($filter_date_to) {
    $where_conditions[] = 'a.attendance_date <= %s';
    $where_values[] = $filter_date_to;
}

$where_clause = implode(' AND ', $where_conditions);

$total_query = "SELECT COUNT(*)
                FROM $attendances_table a
                INNER JOIN $members_table m ON a.member_id = m.id
                INNER JOIN $courses_table c ON a.course_id = c.id
                WHERE $where_clause";
$total_items = !empty($where_values)
    ? (int) $wpdb->get_var($wpdb->prepare($total_query, $where_values))
    : (int) $wpdb->get_var($total_query);

$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$total_pages = max(1, (int) ceil($total_items / $per_page));
$offset = ($current_page - 1) * $per_page;

$query_values = $where_values;
$query = "SELECT a.*,
                 m.first_name, m.last_name, m.national_id, m.personal_photo,
                 c.title AS course_title,
                 COALESCE(CONCAT(rec_coach.first_name, ' ', rec_coach.last_name), rec_user.display_name, '—') AS recorded_by_name
          FROM $attendances_table a
          INNER JOIN $members_table m ON a.member_id = m.id
          INNER JOIN $courses_table c ON a.course_id = c.id
          LEFT JOIN $coaches_table rec_coach ON rec_coach.user_id = a.user_id
          LEFT JOIN $users_table rec_user ON rec_user.ID = a.user_id
          WHERE $where_clause
          ORDER BY a.created_at DESC, a.id DESC
          LIMIT %d OFFSET %d";
$query_values[] = $per_page;
$query_values[] = $offset;

$records = !empty($where_values)
    ? $wpdb->get_results($wpdb->prepare($query, $query_values))
    : $wpdb->get_results($wpdb->prepare($query, $per_page, $offset));

$selected_member_text = 'همه بازیکنان';
if ($filter_member > 0) {
    foreach ($members as $m) {
        if ((int) $m->id === $filter_member) {
            $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
            break;
        }
    }
}

$active_filters_count = 0;
if ($filter_member > 0) {
    $active_filters_count++;
}
if ($filter_course > 0) {
    $active_filters_count++;
}
if ($filter_coach > 0) {
    $active_filters_count++;
}
if ($filter_status !== 'all') {
    $active_filters_count++;
}
if ($filter_date_from_shamsi !== '') {
    $active_filters_count++;
}
if ($filter_date_to_shamsi !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$export_url = admin_url('admin.php?page=sc-reports-attendance-qr&sc_export=excel&export_type=attendance&filter_record_method=qr');
if ($filter_course > 0) {
    $export_url = add_query_arg('filter_course', $filter_course, $export_url);
}
if ($filter_member > 0) {
    $export_url = add_query_arg('filter_member', $filter_member, $export_url);
}
if ($filter_coach > 0) {
    $export_url = add_query_arg('filter_coach', $filter_coach, $export_url);
}
if ($filter_date_from) {
    $export_url = add_query_arg('filter_date_from', $filter_date_from, $export_url);
}
if ($filter_date_to) {
    $export_url = add_query_arg('filter_date_to', $filter_date_to, $export_url);
}
if ($filter_status !== 'all') {
    $export_url = add_query_arg('filter_status', $filter_status, $export_url);
}
$export_url = wp_nonce_url($export_url, 'sc_export_excel');

$clear_url = admin_url('admin.php?page=sc-reports-attendance-qr');
?>

<div class="wrap sc-members-list-wrap sc-attendance-qr-report-wrap">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">گزارش ثبت QR</h1>
            <p class="sc-members-list-desc">رکوردهای حضور و غیابی که از طریق اسکن QR در دستگاه ثبت شده‌اند.</p>
        </div>
        <div class="sc-members-list-header-actions">
            <a href="<?php echo esc_url($export_url); ?>" class="sc-members-list-export-btn">خروجی Excel</a>
        </div>
    </div>

    <div class="sc-members-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-members-list-filters-toolbar">
            <button type="button"
                    class="sc-members-list-filters-toggle"
                    id="sc-attendance-qr-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-attendance-qr-filters-panel">
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
                <a href="<?php echo esc_url($clear_url); ?>" class="sc-members-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" action="" class="sc-members-list-filters-panel" id="sc-attendance-qr-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-reports-attendance-qr">

            <div class="sc-filter-grid sc-attendance-qr-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label">بازیکن</label>
                    <div class="sc-searchable-dropdown">
                        <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if ($filter_member) echo 'style="display:none"'; ?>>همه بازیکنان</span>
                            <span class="sc-dropdown-selected" <?php if (!$filter_member) echo 'style="display:none"'; ?>><?php echo esc_html($selected_member_text); ?></span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option sc-visible" data-value="0" data-search="همه بازیکنان" onclick="scSelectMemberFilter(this,'0','همه بازیکنان')">همه بازیکنان</div>
                                <?php
                                $display_count = 0;
                                $max_display = 15;
                                foreach ($members as $member_option) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                ?>
                                    <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                         data-value="<?php echo esc_attr($member_option->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($member_option->first_name . ' ' . $member_option->last_name . ' ' . $member_option->national_id)); ?>"
                                         onclick="scSelectMemberFilter(this,'<?php echo esc_js($member_option->id); ?>','<?php echo esc_js($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>')">
                                        <?php echo esc_html($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_course">دوره</label>
                    <?php
                    echo function_exists('sc_render_searchable_course_filter_dropdown')
                        ? sc_render_searchable_course_filter_dropdown($courses, $filter_course)
                        : '';
                    if (!function_exists('sc_render_searchable_course_filter_dropdown')) :
                    ?>
                    <select name="filter_course" id="filter_course" class="sc-filter-control">
                        <option value="0">همه دوره‌ها</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>>
                                <?php echo esc_html($course->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>

                <?php if (!empty($coaches_list)) : ?>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_coach">ثبت‌کننده (اسکنر)</label>
                    <select name="filter_coach" id="filter_coach" class="sc-filter-control">
                        <option value="0">همه</option>
                        <?php foreach ($coaches_list as $coach_item) : ?>
                            <option value="<?php echo esc_attr($coach_item->id); ?>" <?php selected($filter_coach, $coach_item->id); ?>>
                                <?php echo esc_html($coach_item->first_name . ' ' . $coach_item->last_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_status">وضعیت</label>
                    <select name="filter_status" id="filter_status" class="sc-filter-control">
                        <option value="all" <?php selected($filter_status, 'all'); ?>>همه وضعیت‌ها</option>
                        <option value="present" <?php selected($filter_status, 'present'); ?>>حاضر</option>
                        <option value="absent" <?php selected($filter_status, 'absent'); ?>>غایب</option>
                    </select>
                </div>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ جلسه</label>
                    <div class="sc-date-range">
                        <input type="text"
                               id="filter_date_from_shamsi"
                               name="filter_date_from_shamsi"
                               class="persian-date-input sc-no-default-date sc-filter-control"
                               value="<?php echo esc_attr($display_date_from_shamsi); ?>"
                               readonly>
                        <input type="text"
                               id="filter_date_to_shamsi"
                               name="filter_date_to_shamsi"
                               class="persian-date-input sc-no-default-date sc-filter-control"
                               value="<?php echo esc_attr($display_date_to_shamsi); ?>"
                               readonly>
                        <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                        <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                    </div>
                </div>
            </div>

            <div class="sc-members-list-filters-actions">
                <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
            </div>
        </form>
    </div>

    <div class="sc-members-list-table-card">
        <?php if (empty($records)) : ?>
            <div class="sc-reports-empty">هیچ رکورد QR یافت نشد.</div>
        <?php else : ?>
            <form method="post" id="sc-qr-attendance-report-form">
                <?php wp_nonce_field('sc_bulk_qr_attendance_nonce'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text">عملیات دسته‌جمعی</label>
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value="">عملیات دسته‌جمعی...</option>
                            <option value="delete">حذف رکورد</option>
                        </select>
                        <input type="submit" class="button action" id="doaction" value="اجرا">
                    </div>
                </div>
            <table class="wp-list-table widefat fixed striped sc-attendance-qr-report-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="sc-qr-cb-select-all">
                        </td>
                        <th class="column-row">ردیف</th>
                        <th>تاریخ جلسه</th>
                        <th>زمان ثبت</th>
                        <th>بازیکن</th>
                        <th>دوره</th>
                        <th>ثبت‌کننده</th>
                        <th>وضعیت</th>
                        <th>عکس اسکن</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $start_number = ($current_page - 1) * $per_page;
                    foreach ($records as $index => $row) :
                        $row_number = $start_number + $index + 1;
                        $full_name = trim($row->first_name . ' ' . $row->last_name);
                        $photo = !empty($row->personal_photo) ? $row->personal_photo : '';
                        $initials = '';
                        if (!empty($row->first_name)) {
                            $initials .= mb_substr((string) $row->first_name, 0, 1);
                        }
                        if (!empty($row->last_name)) {
                            $initials .= mb_substr((string) $row->last_name, 0, 1);
                        }
                        if ($initials === '') {
                            $initials = '؟';
                        }

                        if ($row->status === 'present') {
                            $status_label = 'حاضر';
                            $status_class = 'sc-badge--success';
                        } elseif ($row->status === 'absent') {
                            $status_label = 'غایب';
                            $status_class = 'sc-badge--danger';
                        } else {
                            $status_label = 'غیبت مجاز';
                            $status_class = 'sc-badge--warning';
                        }

                        $delete_url = wp_nonce_url(
                            add_query_arg([
                                'page' => 'sc-reports-attendance-qr',
                                'action' => 'delete',
                                'attendance_id' => (int) $row->id,
                            ], admin_url('admin.php')),
                            'sc_delete_qr_attendance_' . (int) $row->id
                        );
                        // Preserve filters after delete
                        if ($filter_course > 0) {
                            $delete_url = add_query_arg('filter_course', $filter_course, $delete_url);
                        }
                        if ($filter_member > 0) {
                            $delete_url = add_query_arg('filter_member', $filter_member, $delete_url);
                        }
                        if ($filter_coach > 0) {
                            $delete_url = add_query_arg('filter_coach', $filter_coach, $delete_url);
                        }
                        if ($filter_status !== 'all') {
                            $delete_url = add_query_arg('filter_status', $filter_status, $delete_url);
                        }
                        if ($filter_date_from_shamsi !== '') {
                            $delete_url = add_query_arg('filter_date_from_shamsi', $filter_date_from_shamsi, $delete_url);
                        }
                        if ($filter_date_to_shamsi !== '') {
                            $delete_url = add_query_arg('filter_date_to_shamsi', $filter_date_to_shamsi, $delete_url);
                        }
                    ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="attendance_ids[]" value="<?php echo (int) $row->id; ?>" class="sc-qr-cb-item">
                            </th>
                            <td class="column-row"><?php echo (int) $row_number; ?></td>
                            <td>
                                <strong><?php echo esc_html(sc_date_shamsi_date_only($row->attendance_date)); ?></strong>
                                <br><small style="color:#6b7280;"><?php echo esc_html(sc_date_shamsi($row->attendance_date, 'l')); ?></small>
                            </td>
                            <td><?php echo esc_html(sc_date_shamsi($row->created_at, 'Y/m/d H:i')); ?></td>
                            <td>
                                <span class="sc-member-identity">
                                    <?php if ($photo) : ?>
                                        <span class="sc-member-avatar"><img src="<?php echo esc_url($photo); ?>" alt="" loading="lazy"></span>
                                    <?php else : ?>
                                        <span class="sc-member-avatar sc-member-avatar--initials" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                                    <?php endif; ?>
                                    <span class="sc-member-identity-text">
                                        <span class="sc-member-name"><?php echo esc_html($full_name); ?></span>
                                        <?php if (!empty($row->national_id)) : ?>
                                            <span class="sc-member-meta"><span class="sc-member-meta-item"><?php echo esc_html($row->national_id); ?></span></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </td>
                            <td><?php echo esc_html($row->course_title); ?></td>
                            <td><?php echo esc_html($row->recorded_by_name ?? '—'); ?></td>
                            <td>
                                <span class="sc-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                                <span class="sc-attendance-record-method sc-attendance-record-method--qr" style="margin-inline-start:6px;">QR</span>
                            </td>
                            <td>
                                <?php
                                if (function_exists('sc_qr_scan_photo_render_cell')) {
                                    sc_qr_scan_photo_render_cell(
                                        $row->scan_photo ?? '',
                                        $row->scan_photo_front ?? ''
                                    );
                                } else {
                                    echo '<span style="color:#9ca3af;">—</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($delete_url); ?>"
                                   class="button button-small button_delete_attendance sc-qr-delete-one"
                                   data-confirm="این رکورد ثبت QR حذف شود؟">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </form>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = ['page' => 'sc-reports-attendance-qr'];
                        if ($filter_course > 0) {
                            $pagination_args['filter_course'] = $filter_course;
                        }
                        if ($filter_member > 0) {
                            $pagination_args['filter_member'] = $filter_member;
                        }
                        if ($filter_coach > 0) {
                            $pagination_args['filter_coach'] = $filter_coach;
                        }
                        if ($filter_status !== 'all') {
                            $pagination_args['filter_status'] = $filter_status;
                        }
                        if ($filter_date_from_shamsi !== '') {
                            $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
                        }
                        if ($filter_date_to_shamsi !== '') {
                            $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
                        }
                        if ($filter_date_from) {
                            $pagination_args['filter_date_from'] = $filter_date_from;
                        }
                        if ($filter_date_to) {
                            $pagination_args['filter_date_to'] = $filter_date_to;
                        }
                        echo paginate_links([
                            'base' => add_query_arg(array_merge($pagination_args, ['paged' => '%#%'])),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(function ($) {
    var $toggle = $('#sc-attendance-qr-filters-toggle');
    var $panel = $('#sc-attendance-qr-filters-panel');
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

    $('#sc-qr-cb-select-all').on('change', function () {
        $('.sc-qr-cb-item').prop('checked', this.checked);
    });

    $('#sc-qr-attendance-report-form').on('submit', function (e) {
        var action = $('#bulk-action-selector-top').val();
        if (action === 'delete') {
            var checked = $('.sc-qr-cb-item:checked').length;
            if (!checked) {
                e.preventDefault();
                alert('حداقل یک رکورد را انتخاب کنید.');
                return;
            }
            if (!window.confirm('رکوردهای انتخاب‌شده حذف شوند؟ فقط ثبت حضور QR حذف می‌شود.')) {
                e.preventDefault();
            }
        } else if (!action) {
            e.preventDefault();
            alert('یک عملیات را انتخاب کنید.');
        }
    });

    $(document).on('click', '.sc-qr-delete-one', function (e) {
        var msg = $(this).data('confirm') || 'این رکورد حذف شود؟';
        if (!window.confirm(msg)) {
            e.preventDefault();
        }
    });
});
</script>
