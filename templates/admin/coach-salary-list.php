<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی
if (!current_user_can('sc_view_coach_salary')) {
    wp_die('شما دسترسی به این صفحه ندارید.');
}

global $wpdb;
$coaches_table = $wpdb->prefix . 'sc_coaches';
$salary_records_table = $wpdb->prefix . 'sc_coach_salary_records';
$courses_table = $wpdb->prefix . 'sc_courses';

// دریافت شناسه مربی لاگین شده
$current_user_id = get_current_user_id();
$coach = $wpdb->get_row($wpdb->prepare(
    "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
    $current_user_id
));

if (!$coach) {
    echo '<div class="wrap"><div class="notice notice-error"><p>شما به عنوان مربی ثبت نشده‌اید.</p></div></div>';
    return;
}

$coach_id = $coach->id;

// دریافت موجودی کیف پول
$wallet_balance = sc_get_coach_wallet_balance($coach_id);

// دریافت فیلترها
$filter_date_from = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
$filter_date_to = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';
$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';

// ساخت WHERE clause
$where_conditions = ["coach_id = %d"];
$where_values = [$coach_id];

if ($filter_course > 0) {
    $where_conditions[] = "course_id = %d";
    $where_values[] = $filter_course;
}

if ($filter_type !== 'all') {
    $where_conditions[] = "salary_type = %s";
    $where_values[] = $filter_type;
}

if ($filter_date_from) {
    $date_from_gregorian = sc_shamsi_to_gregorian_date($filter_date_from);
    $where_conditions[] = "attendance_date >= %s";
    $where_values[] = $date_from_gregorian;
}

if ($filter_date_to) {
    $date_to_gregorian = sc_shamsi_to_gregorian_date($filter_date_to);
    $where_conditions[] = "attendance_date <= %s";
    $where_values[] = $date_to_gregorian;
}

$where_clause = implode(' AND ', $where_conditions);

// دریافت رکوردهای دستمزد
$salary_records = $wpdb->get_results($wpdb->prepare(
    "SELECT sr.*, c.title as course_title 
     FROM $salary_records_table sr
     LEFT JOIN $courses_table c ON sr.course_id = c.id
     WHERE $where_clause
     ORDER BY sr.attendance_date DESC, sr.created_at DESC",
    $where_values
));

// محاسبه مجموع
$total_salary = 0;
foreach ($salary_records as $record) {
    $total_salary += floatval($record->salary_amount);
}

// دریافت لیست دوره‌ها برای فیلتر
$courses = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT c.id, c.title 
     FROM $courses_table c
     INNER JOIN $salary_records_table sr ON c.id = sr.course_id
     WHERE sr.coach_id = %d
     ORDER BY c.title ASC",
    $coach_id
));
?>

<div class="wrap">
    <h1 class="wp-heading-inline">لیست دستمزد</h1>
    <hr class="wp-header-end">
    
    <!-- نمایش موجودی کیف پول -->
    <div class="notice notice-info" style="padding: 15px; margin: 20px 0;">
        <h3 style="margin-top: 0;">💰 موجودی کیف پول: <strong><?php echo number_format($wallet_balance, 0, '.', ','); ?> تومان</strong></h3>
        <p><a href="<?php echo admin_url('admin.php?page=sc-coach-wallet'); ?>" class="button button-primary">مدیریت کیف پول</a></p>
    </div>
    
    <!-- فیلترها -->
    <div class="sc-filter-wrapper" style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <form method="GET" action="">
            <input type="hidden" name="page" value="sc-coach-salary">
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div>
                    <label>از تاریخ (شمسی):</label><br>
                    <input type="text" name="filter_date_from_shamsi" id="filter_date_from_shamsi" 
                           value="<?php echo esc_attr($filter_date_from); ?>" 
                           class="persian-datepicker" style="width: 150px;">
                </div>
                
                <div>
                    <label>تا تاریخ (شمسی):</label><br>
                    <input type="text" name="filter_date_to_shamsi" id="filter_date_to_shamsi" 
                           value="<?php echo esc_attr($filter_date_to); ?>" 
                           class="persian-datepicker" style="width: 150px;">
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
                    <input type="submit" class="button button-primary" value="فیلتر">
                    <a href="<?php echo admin_url('admin.php?page=sc-coach-salary'); ?>" class="button">پاک کردن فیلترها</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- خلاصه -->
    <div style="background: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid #2271b1;">
        <strong>مجموع دستمزد:</strong> <?php echo number_format($total_salary, 0, '.', ','); ?> تومان
        <span style="margin-right: 30px;"></span>
        <strong>تعداد رکورد:</strong> <?php echo count($salary_records); ?>
    </div>
    
    <!-- جدول دستمزد -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ردیف</th>
                <th>تاریخ</th>
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
                    <td colspan="9" style="text-align: center; padding: 30px;">
                        <p>هیچ رکورد دستمزدی یافت نشد.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php $row_number = 1; ?>
                <?php foreach ($salary_records as $record): ?>
                    <tr>
                        <td><?php echo $row_number++; ?></td>
                        <td><?php echo sc_date_shamsi_date_only($record->attendance_date); ?></td>
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
                <th colspan="8" style="text-align: left;">مجموع:</th>
                <th><strong><?php echo number_format($total_salary, 0, '.', ','); ?> تومان</strong></th>
            </tr>
        </tfoot>
    </table>
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
