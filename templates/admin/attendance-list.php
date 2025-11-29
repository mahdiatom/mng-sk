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

// پردازش حذف
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['attendance_id'])) {
    check_admin_referer('delete_attendance_' . $_GET['attendance_id']);
    
    $attendance_id = absint($_GET['attendance_id']);
    $deleted = $wpdb->delete(
        $attendances_table,
        ['id' => $attendance_id],
        ['%d']
    );
    
    if ($deleted) {
        echo '<div class="notice notice-success is-dismissible"><p>حضور و غیاب با موفقیت حذف شد.</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>خطا در حذف حضور و غیاب.</p></div>';
    }
}

// دریافت فیلترها
$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
$filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';

// ساخت WHERE clause
$where_conditions = ['1=1'];
$where_values = [];

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

$where_clause = implode(' AND ', $where_conditions);

// دریافت تعداد کل رکوردها برای pagination
$total_query = "SELECT COUNT(*) FROM $attendances_table a WHERE $where_clause";
if (!empty($where_values)) {
    $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
} else {
    $total_items = $wpdb->get_var($total_query);
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// دریافت لیست حضور و غیاب‌ها
$query_values = $where_values;
$query = "SELECT a.*, 
                 m.first_name, m.last_name, m.national_id,
                 c.title as course_title
          FROM $attendances_table a
          INNER JOIN $members_table m ON a.member_id = m.id
          INNER JOIN $courses_table c ON a.course_id = c.id
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

// دریافت لیست دوره‌ها و اعضا برای فیلترها
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");
$members = $wpdb->get_results("SELECT id, first_name, last_name FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

// محاسبه تعداد صفحات
$total_pages = ceil($total_items / $per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">لیست حضور و غیاب</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-attendance-add'); ?>" class="page-title-action">ثبت حضور و غیاب</a>
    <hr class="wp-header-end">
    
    <!-- فیلترها -->
    <form method="GET" action="" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
        <input type="hidden" name="page" value="sc-attendance-list">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="filter_course">دوره</label>
                </th>
                <td>
                    <select name="filter_course" id="filter_course" style="width: 300px; padding: 5px;">
                        <option value="0">همه دوره‌ها</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>>
                                <?php echo esc_html($course->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="filter_member">کاربر</label>
                </th>
                <td>
                    <select name="filter_member" id="filter_member" style="width: 300px; padding: 5px;">
                        <option value="0">همه کاربران</option>
                        <?php foreach ($members as $member) : ?>
                            <option value="<?php echo esc_attr($member->id); ?>" <?php selected($filter_member, $member->id); ?>>
                                <?php echo esc_html($member->first_name . ' ' . $member->last_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label>بازه تاریخ</label>
                </th>
                <td>
                    <input type="date" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>" style="padding: 5px; margin-left: 10px;">
                    <span>تا</span>
                    <input type="date" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>" style="padding: 5px; margin-left: 10px;">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="filter_status">وضعیت</label>
                </th>
                <td>
                    <select name="filter_status" id="filter_status" style="width: 300px; padding: 5px;">
                        <option value="all" <?php selected($filter_status, 'all'); ?>>همه وضعیت‌ها</option>
                        <option value="present" <?php selected($filter_status, 'present'); ?>>حاضر</option>
                        <option value="absent" <?php selected($filter_status, 'absent'); ?>>غایب</option>
                    </select>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
            <a href="<?php echo admin_url('admin.php?page=sc-attendance-list'); ?>" class="button">پاک کردن فیلترها</a>
        </p>
    </form>
    
    <!-- لیست حضور و غیاب‌ها -->
    <?php if (empty($attendances)) : ?>
        <div class="notice notice-info">
            <p>هیچ حضور و غیابی یافت نشد.</p>
        </div>
    <?php else : ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ردیف</th>
                        <th>تاریخ</th>
                        <th>دوره</th>
                        <th>نام</th>
                        <th>نام خانوادگی</th>
                        <th>کد ملی</th>
                        <th>وضعیت</th>
                        <th style="width: 150px;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $start_number = ($current_page - 1) * $per_page;
                    foreach ($attendances as $index => $attendance) : 
                        $row_number = $start_number + $index + 1;
                        $status_label = $attendance->status === 'present' ? 'حاضر' : 'غایب';
                        $status_color = $attendance->status === 'present' ? '#00a32a' : '#d63638';
                        $status_bg = $attendance->status === 'present' ? '#d4edda' : '#ffeaea';
                    ?>
                        <tr>
                            <td><?php echo $row_number; ?></td>
                            <td>
                                <strong><?php echo date_i18n('Y/m/d', strtotime($attendance->attendance_date)); ?></strong>
                                <br>
                                <small style="color: #666;"><?php echo date_i18n('l', strtotime($attendance->attendance_date)); ?></small>
                            </td>
                            <td><?php echo esc_html($attendance->course_title); ?></td>
                            <td><?php echo esc_html($attendance->first_name); ?></td>
                            <td><?php echo esc_html($attendance->last_name); ?></td>
                            <td><?php echo esc_html($attendance->national_id); ?></td>
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
                                <a href="<?php echo admin_url('admin.php?page=sc-attendance-add&course_id=' . $attendance->course_id . '&date=' . $attendance->attendance_date); ?>" 
                                   class="button button-small">ویرایش</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sc-attendance-list&action=delete&attendance_id=' . $attendance->id), 'delete_attendance_' . $attendance->id); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('آیا مطمئن هستید که می‌خواهید این حضور و غیاب را حذف کنید؟');"
                                   style="background-color: #d63638; color: #fff; border-color: #d63638;">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom" style="margin-top: 20px;">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ]);
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

