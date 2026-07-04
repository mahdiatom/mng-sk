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

$active_filters_count = 0;
if ($filter_coach > 0) {
    $active_filters_count++;
}
if ($filter_course > 0) {
    $active_filters_count++;
}
if ($filter_type !== 'all') {
    $active_filters_count++;
}
if ($filter_date_from_shamsi !== '' || $filter_date_to_shamsi !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$export_url = admin_url('admin.php?page=sc-coach-management-salary&sc_export=excel&export_type=coach_management_salary');
if ($filter_coach > 0) {
    $export_url = add_query_arg('filter_coach', $filter_coach, $export_url);
}
if ($filter_course > 0) {
    $export_url = add_query_arg('filter_course', $filter_course, $export_url);
}
if ($filter_type !== 'all') {
    $export_url = add_query_arg('filter_type', $filter_type, $export_url);
}
if (!empty($filter_date_from_shamsi)) {
    $export_url = add_query_arg('filter_date_from_shamsi', $filter_date_from_shamsi, $export_url);
}
if (!empty($filter_date_to_shamsi)) {
    $export_url = add_query_arg('filter_date_to_shamsi', $filter_date_to_shamsi, $export_url);
}
$export_url = wp_nonce_url($export_url, 'sc_export_excel');
?>

<div class="wrap sc-cm-wrap">
    <div class="sc-cm-header">
        <div class="sc-cm-header-text">
            <h1 class="sc-cm-title">گزارش دستمزد مربیان</h1>
            <p class="sc-cm-desc">گزارش دستمزد ثابت و درصدی مربیان بر اساس فیلترهای انتخابی</p>
        </div>
        <div class="sc-cm-header-actions">
            <a href="<?php echo esc_url($export_url); ?>" class="sc-cm-btn-secondary">خروجی Excel</a>
        </div>
    </div>

    <div class="sc-cm-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-cm-filters-toolbar">
            <button type="button" class="sc-cm-filters-toggle" id="sc-cm-salary-filters-toggle" aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>" aria-controls="sc-cm-salary-filters-panel">
                <span class="sc-cm-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </span>
                <span class="sc-cm-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها"><?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?></span>
                <?php if ($active_filters_count > 0) : ?><span class="sc-cm-filters-badge"><?php echo (int) $active_filters_count; ?></span><?php endif; ?>
                <span class="sc-cm-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-management-salary')); ?>" class="sc-cm-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="GET" action="" class="sc-cm-filters-panel" id="sc-cm-salary-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-coach-management-salary">
            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_coach">مربی</label>
                    <select name="filter_coach" id="filter_coach" class="sc-filter-control">
                        <option value="0">همه مربیان</option>
                        <?php foreach ($coaches as $coach) : ?>
                            <option value="<?php echo esc_attr($coach->id); ?>" <?php selected($filter_coach, $coach->id); ?>><?php echo esc_html($coach->first_name . ' ' . $coach->last_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_course">دوره</label>
                    <select name="filter_course" id="filter_course" class="sc-filter-control">
                        <option value="0">همه دوره‌ها</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>><?php echo esc_html($course->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_type">نوع دستمزد</label>
                    <select name="filter_type" id="filter_type" class="sc-filter-control">
                        <option value="all" <?php selected($filter_type, 'all'); ?>>همه</option>
                        <option value="percentage" <?php selected($filter_type, 'percentage'); ?>>درصدی</option>
                        <option value="fixed" <?php selected($filter_type, 'fixed'); ?>>ثابت</option>
                    </select>
                </div>
                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ (شمسی)</label>
                    <div class="sc-cm-date-range">
                        <input type="text" name="filter_date_from_shamsi" id="filter_date_from_shamsi_salary" value="<?php echo esc_attr($display_date_from_sal); ?>" class="persian-date-input sc-filter-control sc-no-default-date" placeholder="از تاریخ" readonly>
                        <input type="text" name="filter_date_to_shamsi" id="filter_date_to_shamsi_salary" value="<?php echo esc_attr($display_date_to_sal); ?>" class="persian-date-input sc-filter-control sc-no-default-date" placeholder="تا تاریخ" readonly>
                    </div>
                </div>
            </div>
            <div class="sc-cm-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-management-salary')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-cm-stats">
        <div class="sc-cm-stat-card">
            <span class="sc-cm-stat-label">مجموع دستمزد</span>
            <span class="sc-cm-stat-value"><?php echo number_format($total_salary, 0, '.', ','); ?> تومان</span>
        </div>
        <div class="sc-cm-stat-card">
            <span class="sc-cm-stat-label">تعداد رکورد</span>
            <span class="sc-cm-stat-value"><?php echo $total_items > 0 ? sprintf('%d تا %d از %d', $offset + 1, min($offset + count($salary_records), $total_items), $total_items) : '۰'; ?></span>
        </div>
    </div>

    <div class="sc-cm-table-card">
    <div class="sc-cm-table-scroll">
    <table class="wp-list-table widefat striped sc-cm-table">
        <thead>
            <tr>
                <th>ردیف</th>
                <th>ID</th>
                <th>تاریخ</th>
                <th>مربی</th>
                <th>دوره</th>
                <th>شعبه</th>
                <th>نوع</th>
                <th>تعداد شرکت‌کنندگان</th>
                <th>قیمت هر جلسه</th>
                <th>کل درآمد</th>
                <th>درصد دستمزد</th>
                <th>مبلغ دستمزد</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($salary_records)) : ?>
                <tr>
                    <td colspan="12" class="sc-cm-empty">هیچ رکورد دستمزدی یافت نشد.</td>
                </tr>
            <?php else : ?>
                <?php $row_number = $offset + 1; ?>
                <?php foreach ($salary_records as $record) : ?>
                    <tr>
                        <td data-label="ردیف"><?php echo (int) $row_number++; ?></td>
                        <td data-label="ID"><code><?php echo esc_html($record->id ?? '-'); ?></code></td>
                        <td data-label="تاریخ"><?php echo esc_html(sc_date_shamsi_date_only($record->attendance_date)); ?></td>
                        <td data-label="مربی">
                            <strong><?php echo esc_html($record->first_name . ' ' . $record->last_name); ?></strong>
                            <span class="sc-cm-meta">
                                <?php
                                echo $record->settlement_type === 'fixed'
                                    ? 'ثابت'
                                    : ($record->settlement_type === 'both' ? 'ثابت + درصدی' : 'درصدی');
                                ?>
                            </span>
                        </td>
                        <td data-label="دوره">
                            <?php
                            if ($record->course_id > 0) {
                                echo esc_html($record->course_title ?: 'دوره حذف شده');
                            } else {
                                echo '<em>دستمزد ثابت</em>';
                            }
                            ?>
                        </td>
                        <td data-label="شعبه"><?php echo !empty($record->chapter_name) ? esc_html($record->chapter_name) : '—'; ?></td>
                        <td data-label="نوع">
                            <?php if ($record->salary_type === 'percentage') : ?>
                                <span class="sc-badge sc-badge--purple">درصدی</span>
                            <?php else : ?>
                                <span class="sc-badge sc-badge--success">ثابت</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="تعداد شرکت‌کنندگان"><?php echo $record->attendance_count > 0 ? number_format($record->attendance_count) : '—'; ?></td>
                        <td data-label="قیمت هر جلسه"><?php echo $record->price_per_session > 0 ? number_format($record->price_per_session, 0, '.', ',') . ' تومان' : '—'; ?></td>
                        <td data-label="کل درآمد"><?php echo $record->total_revenue > 0 ? number_format($record->total_revenue, 0, '.', ',') . ' تومان' : '—'; ?></td>
                        <td data-label="درصد دستمزد"><?php echo $record->salary_percentage > 0 ? number_format($record->salary_percentage, 2) . '%' : '—'; ?></td>
                        <td data-label="مبلغ دستمزد"><strong class="sc-cm-amount"><?php echo number_format($record->salary_amount, 0, '.', ','); ?></strong> تومان</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="11">مجموع:</th>
                <th><strong class="sc-cm-amount"><?php echo number_format($total_salary, 0, '.', ','); ?></strong> تومان</th>
            </tr>
        </tfoot>
    </table>
    </div>
    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom sc-cm-pagination">
            <div class="tablenav-pages">
                <?php
                $pagination_args = ['page' => 'sc-coach-management-salary'];
                if ($filter_coach > 0) {
                    $pagination_args['filter_coach'] = $filter_coach;
                }
                if ($filter_course > 0) {
                    $pagination_args['filter_course'] = $filter_course;
                }
                if ($filter_type !== 'all') {
                    $pagination_args['filter_type'] = $filter_type;
                }
                if (!empty($filter_date_from_shamsi)) {
                    $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
                }
                if (!empty($filter_date_to_shamsi)) {
                    $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
                }
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                    'format' => '',
                    'prev_text' => '‹',
                    'next_text' => '›',
                    'total' => $total_pages,
                    'current' => $current_page,
                    'add_args' => $pagination_args,
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var $toggle = $('#sc-cm-salary-filters-toggle');
    var $panel = $('#sc-cm-salary-filters-panel');
    var $card = $toggle.closest('.sc-cm-filters-card');
    var $label = $toggle.find('.sc-cm-filters-toggle-label');
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
    if (typeof initPersianDatepicker === 'function') {
        initPersianDatepicker();
    }
});
</script>
