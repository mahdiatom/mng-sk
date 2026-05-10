<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی به این صفحه ندارید.');
}

global $wpdb;
$coaches_table = $wpdb->prefix . 'sc_coaches';
$salary_records_table = $wpdb->prefix . 'sc_coach_salary_records';
$courses_table = $wpdb->prefix . 'sc_courses';

// دریافت فیلترها — تاریخ فقط از GET، بدون اعمال پیش‌فرض در فیلتر
$filter_coach = isset($_GET['filter_coach']) ? absint($_GET['filter_coach']) : 0;
$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
$filter_date_to_shamsi   = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';
$filter_date_from = $filter_date_from_shamsi; // برای WHERE به شمسی تبدیل می‌شود
$filter_date_to   = $filter_date_to_shamsi;
$today_shamsi_sal = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
if (!$today_shamsi_sal && function_exists('gregorian_to_jalali')) {
    $g = explode('-', current_time('Y-m-d'));
    $j = gregorian_to_jalali((int)$g[0], (int)$g[1], (int)$g[2]);
    $today_shamsi_sal = $j[0] . '/' . str_pad($j[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($j[2], 2, '0', STR_PAD_LEFT);
}
$display_date_from_sal = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $today_shamsi_sal;
$display_date_to_sal   = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $today_shamsi_sal;

// ساخت WHERE clause
$where_conditions = ['1=1'];
$where_values = [];

if ($filter_coach > 0) {
    $where_conditions[] = "sr.coach_id = %d";
    $where_values[] = $filter_coach;
}

if ($filter_course > 0) {
    $where_conditions[] = "sr.course_id = %d";
    $where_values[] = $filter_course;
}

if ($filter_type !== 'all') {
    $where_conditions[] = "sr.salary_type = %s";
    $where_values[] = $filter_type;
}

if ($filter_date_from) {
    $date_from_gregorian = sc_shamsi_to_gregorian_date($filter_date_from);
    $where_conditions[] = "sr.attendance_date >= %s";
    $where_values[] = $date_from_gregorian;
}

if ($filter_date_to) {
    $date_to_gregorian = sc_shamsi_to_gregorian_date($filter_date_to);
    $where_conditions[] = "sr.attendance_date <= %s";
    $where_values[] = $date_to_gregorian;
}

$where_clause = implode(' AND ', $where_conditions);

$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

$count_sql = "SELECT COUNT(*) FROM $salary_records_table sr INNER JOIN $coaches_table c ON sr.coach_id = c.id LEFT JOIN $courses_table co ON sr.course_id = co.id WHERE $where_clause";
$total_items = !empty($where_values) ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $where_values)) : (int) $wpdb->get_var($count_sql);
$total_pages = $total_items > 0 ? ceil($total_items / $per_page) : 1;
$current_page = min($current_page, max(1, $total_pages));
$offset = ($current_page - 1) * $per_page;

$sum_sql = "SELECT COALESCE(SUM(sr.salary_amount), 0) FROM $salary_records_table sr INNER JOIN $coaches_table c ON sr.coach_id = c.id LEFT JOIN $courses_table co ON sr.course_id = co.id WHERE $where_clause";
$total_salary = !empty($where_values) ? (float) $wpdb->get_var($wpdb->prepare($sum_sql, $where_values)) : (float) $wpdb->get_var($sum_sql);

$query = "SELECT sr.*, c.first_name, c.last_name, c.settlement_type, co.title as course_title 
          FROM $salary_records_table sr
          INNER JOIN $coaches_table c ON sr.coach_id = c.id
          LEFT JOIN $courses_table co ON sr.course_id = co.id
          WHERE $where_clause
          ORDER BY sr.attendance_date DESC, sr.created_at DESC
          LIMIT %d OFFSET %d";

$query_values = array_merge($where_values, [$per_page, $offset]);
$salary_records = $wpdb->get_results($wpdb->prepare($query, $query_values));

// دریافت لیست مربیان و دوره‌ها برای فیلتر
$coaches = $wpdb->get_results(
    "SELECT id, first_name, last_name FROM $coaches_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC"
);

$courses = $wpdb->get_results(
    "SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">گزارش دستمزد مربیان</h1>
    <hr class="wp-header-end">
</div>
    <div class="wrap">
    <!-- فیلترها -->
    <div class="sc-filter-wrapper" style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <form method="GET" action="">
            <input type="hidden" name="page" value="sc-coach-management-salary">
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div>
                    <label>مربی:</label><br>
                    <select name="filter_coach" style="width: 200px;">
                        <option value="0">همه مربیان</option>
                        <?php foreach ($coaches as $coach): ?>
                            <option value="<?php echo $coach->id; ?>" <?php selected($filter_coach, $coach->id); ?>>
                                <?php echo esc_html($coach->first_name . ' ' . $coach->last_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>دوره:</label><br>
                    <select name="filter_course" style="width: 200px;">
                        <option value="0">همه دوره‌ها</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course->id; ?>" <?php selected($filter_course, $course->id); ?>>
                                <?php echo esc_html($course->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>نوع دستمزد:</label><br>
                    <select name="filter_type" style="width: 150px;">
                        <option value="all" <?php selected($filter_type, 'all'); ?>>همه</option>
                        <option value="percentage" <?php selected($filter_type, 'percentage'); ?>>درصدی</option>
                        <option value="fixed" <?php selected($filter_type, 'fixed'); ?>>ثابت</option>
                    </select>
                </div>
                
                <div>
                    <label>از تاریخ (شمسی):</label><br>
                    <input type="text" name="filter_date_from_shamsi" id="filter_date_from_shamsi_salary" 
                           value="<?php echo esc_attr($display_date_from_sal); ?>" 
                           class="persian-date-input sc-filter-control sc-no-default-date" style="width: 150px;" readonly>
                </div>
                
                <div>
                    <label>تا تاریخ (شمسی):</label><br>
                    <input type="text" name="filter_date_to_shamsi" id="filter_date_to_shamsi_salary" 
                           value="<?php echo esc_attr($display_date_to_sal); ?>" 
                           class="persian-date-input sc-filter-control sc-no-default-date" style="width: 150px;" readonly>
                </div>
                
                <div>
                    <input type="submit" class="button button-primary" value="فیلتر">
                    <a href="<?php echo admin_url('admin.php?page=sc-coach-management-salary'); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
                     <?php
            // ساخت URL برای export Excel با حفظ فیلترها
            $export_url = admin_url('admin.php?page=sc-coach-management-salary&sc_export=excel&export_type=coach_management_salary');
            $export_url = add_query_arg('filter_status', isset($_GET['filter_status']) ? $_GET['filter_status'] : 'all', $export_url);
            $export_url = add_query_arg('filter_course', isset($_GET['filter_course']) ? $_GET['filter_course'] : 0, $export_url);
            $export_url = add_query_arg('filter_member', isset($_GET['filter_member']) ? $_GET['filter_member'] : 0, $export_url);
            if (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
                $export_url = add_query_arg('filter_date_from', $_GET['filter_date_from'], $export_url);
            }
            if (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
                $export_url = add_query_arg('filter_date_to', $_GET['filter_date_to'], $export_url);
            }
            if (isset($_GET['s']) && !empty($_GET['s'])) {
                $export_url = add_query_arg('s', $_GET['s'], $export_url);
            }
            $export_url = wp_nonce_url($export_url, 'sc_export_excel');
            ?>
            <a href="<?php echo esc_url($export_url); ?>" class="button button_export" >
                📊 خروجی Excel
            </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- خلاصه -->
    <div style="background: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid #2271b1;">
        <strong>مجموع دستمزد:</strong> <?php echo number_format($total_salary, 0, '.', ','); ?> تومان
        <span style="margin-right: 30px;"></span>
        <strong>تعداد رکورد:</strong> <?php echo $total_items > 0 ? sprintf('%d تا %d از %d', $offset + 1, min($offset + count($salary_records), $total_items), $total_items) : '۰'; ?>
    </div>
    
    <!-- جدول دستمزد -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ردیف</th>
                <th>ID</th>
                <th>تاریخ</th>
                <th>مربی</th>
                <th>دوره</th>
                <th>نوع</th>
                <th>تعداد شرکت‌کنندگان</th>
                <th>قیمت هر جلسه</th>
                <th>کل درآمد</th>
                <th>درصد دستمزد</th>
                <th>مبلغ دستمزد</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($salary_records)): ?>
                <tr>
                    <td colspan="11" style="text-align: center; padding: 30px;">
                        <p>هیچ رکورد دستمزدی یافت نشد.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php $row_number = $offset + 1; ?>
                <?php foreach ($salary_records as $record): ?>
                    <tr>
                        <td><?php echo $row_number++; ?></td>
                        <td><code><?php echo esc_html($record->id ?? '-'); ?></code></td>
                        <td><?php echo sc_date_shamsi_date_only($record->attendance_date); ?></td>
                        <td>
                            <strong><?php echo esc_html($record->first_name . ' ' . $record->last_name); ?></strong><br>
                            <small style="color: #666;">
                                <?php
                                echo $record->settlement_type === 'fixed'
                                    ? 'ثابت'
                                    : ($record->settlement_type === 'both' ? 'ثابت + درصدی' : 'درصدی');
                                ?>
                            </small>
                        </td>
                        <td>
                            <?php 
                            if ($record->course_id > 0) {
                                echo esc_html($record->course_title ?: 'دوره حذف شده');
                            } else {
                                echo '<em>دستمزد ثابت</em>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($record->salary_type === 'percentage'): ?>
                                <span style="color: #2271b1;">درصدی</span>
                            <?php else: ?>
                                <span style="color: #00a32a;">ثابت</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $record->attendance_count > 0 ? number_format($record->attendance_count) : '-'; ?></td>
                        <td><?php echo $record->price_per_session > 0 ? number_format($record->price_per_session, 0, '.', ',') . ' تومان' : '-'; ?></td>
                        <td><?php echo $record->total_revenue > 0 ? number_format($record->total_revenue, 0, '.', ',') . ' تومان' : '-'; ?></td>
                        <td><?php echo $record->salary_percentage > 0 ? number_format($record->salary_percentage, 2) . '%' : '-'; ?></td>
                        <td><strong style="color: #00a32a;"><?php echo number_format($record->salary_amount, 0, '.', ','); ?> تومان</strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="10" style="text-align: left;">مجموع:</th>
                <th><strong><?php echo number_format($total_salary, 0, '.', ','); ?> تومان</strong></th>
            </tr>
        </tfoot>
    </table>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom sc_paginate" style="margin-top: 15px;">
            <div class="tablenav-pages">
                <p class="pagination-links">
                    <?php
                    $pagination_args = ['page' => 'sc-coach-management-salary'];
                    if ($filter_coach > 0) $pagination_args['filter_coach'] = $filter_coach;
                    if ($filter_course > 0) $pagination_args['filter_course'] = $filter_course;
                    if ($filter_type !== 'all') $pagination_args['filter_type'] = $filter_type;
                    if (!empty($filter_date_from_shamsi)) $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
                    if (!empty($filter_date_to_shamsi)) $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                        'format' => '',
                        'prev_text' => '&laquo; قبلی',
                        'next_text' => 'بعدی &raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'add_args' => $pagination_args,
                    ]);
                    ?>
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // فعال‌سازی datepicker شمسی
    if (typeof initPersianDatepicker === 'function') {
        initPersianDatepicker();
    } else if ($.fn.persianDatepicker) {
        $('.persian-datepicker').persianDatepicker({
            format: 'YYYY/MM/DD',
            observer: true,
            altField: '.observer-example-alt',
            altFormat: 'YYYY/MM/DD'
        });
    }
});
</script>
