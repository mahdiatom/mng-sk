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

// دریافت فیلترها — تاریخ فقط از GET، بدون اعمال پیش‌فرض در فیلتر
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
$filter_date_to_shamsi   = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';
$filter_date_from = (!empty($filter_date_from_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) ? sc_shamsi_to_gregorian_date($filter_date_from_shamsi) : '';
$filter_date_to   = (!empty($filter_date_to_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) ? sc_shamsi_to_gregorian_date($filter_date_to_shamsi) : '';
$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';

// فقط برای نمایش در فیلدها: وقتی کاربر تاریخی نفرستاده امروز نشان بده
$today_shamsi_salary = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
if (!$today_shamsi_salary && function_exists('gregorian_to_jalali')) {
    $today = new DateTime(current_time('Y-m-d'));
    $jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
    $today_shamsi_salary = $jalali[0] . '/' . str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($jalali[2], 2, '0', STR_PAD_LEFT);
}
$display_date_from_shamsi_salary = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $today_shamsi_salary;
$display_date_to_shamsi_salary   = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $today_shamsi_salary;

// دریافت دوره‌هایی که مربی به آن‌ها دسترسی دارد (برای فیلتر کردن)
$course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
$coach_accessible_courses = $wpdb->get_col($wpdb->prepare(
    "SELECT course_id FROM $course_coaches_table WHERE coach_id = %d",
    $coach_id
));

// ساخت WHERE clause
$where_conditions = ["coach_id = %d"];
$where_values = [$coach_id];

if ($filter_course > 0) {
    // بررسی اینکه آیا مربی به این دوره دسترسی دارد
    if (in_array($filter_course, $coach_accessible_courses)) {
        $where_conditions[] = "course_id = %d";
        $where_values[] = $filter_course;
    } else {
        // اگر مربی به دوره انتخاب شده دسترسی ندارد، هیچ نتیجه‌ای نمایش نده
        $where_conditions[] = "1 = 0";
    }
} else {
    // اگر دوره انتخاب نشده، فقط دوره‌هایی که مربی به آن‌ها دسترسی دارد را نمایش بده
    if (!empty($coach_accessible_courses)) {
        $placeholders = implode(',', array_fill(0, count($coach_accessible_courses), '%d'));
        $where_conditions[] = "(course_id IN ($placeholders) OR course_id = 0)";
        $where_values = array_merge($where_values, $coach_accessible_courses);
    } else {
        // اگر مربی به هیچ دوره‌ای دسترسی ندارد، فقط دستمزدهای ثابت را نمایش بده
        $where_conditions[] = "course_id = 0";
    }
}

if ($filter_type !== 'all') {
    $where_conditions[] = "salary_type = %s";
    $where_values[] = $filter_type;
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "attendance_date >= %s";
    $where_values[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "attendance_date <= %s";
    $where_values[] = $filter_date_to;
}

$where_clause = implode(' AND ', $where_conditions);

$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

// تعداد کل رکوردها
$count_sql = "SELECT COUNT(*) FROM $salary_records_table sr LEFT JOIN $courses_table c ON sr.course_id = c.id WHERE $where_clause";
$total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $where_values));
$total_pages = $total_items > 0 ? ceil($total_items / $per_page) : 1;
$current_page = min($current_page, max(1, $total_pages));
$offset = ($current_page - 1) * $per_page;

// مجموع دستمزد روی همهٔ رکوردهای فیلترشده (نه فقط صفحه فعلی)
$sum_sql = "SELECT COALESCE(SUM(sr.salary_amount), 0) FROM $salary_records_table sr LEFT JOIN $courses_table c ON sr.course_id = c.id WHERE $where_clause";
$total_salary = (float) $wpdb->get_var($wpdb->prepare($sum_sql, $where_values));

// دریافت رکوردهای دستمزد (صفحه فعلی)
$list_values = array_merge($where_values, [$per_page, $offset]);
$salary_records = $wpdb->get_results($wpdb->prepare(
    "SELECT sr.*, c.title as course_title 
     FROM $salary_records_table sr
     LEFT JOIN $courses_table c ON sr.course_id = c.id
     WHERE $where_clause
     ORDER BY sr.attendance_date DESC, sr.created_at DESC
     LIMIT %d OFFSET %d",
    $list_values
));

// دریافت تمام دوره‌های فعال برای فیلتر (همه دوره‌ها)
$all_courses_for_filter = $wpdb->get_results(
    "SELECT id, title 
     FROM $courses_table 
     WHERE deleted_at IS NULL AND is_active = 1 
     ORDER BY title ASC"
);

$active_filters_count = 0;
if ($filter_course > 0) {
    $active_filters_count++;
}
if ($filter_type !== 'all') {
    $active_filters_count++;
}
if (isset($_GET['filter_date_from_shamsi']) || isset($_GET['filter_date_to_shamsi'])
    || isset($_GET['filter_date_from']) || isset($_GET['filter_date_to'])) {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;
?>

<div class="wrap sc-wallet-list-wrap sc-coach-wallet-list-page">
    <div class="sc-wallet-list-header">
        <div class="sc-wallet-list-header-text">
            <h1 class="sc-wallet-list-title">لیست دستمزد</h1>
            <p class="sc-wallet-list-desc">مشاهده و فیلتر رکوردهای دستمزد ثبت‌شده برای شما.</p>
        </div>
        <div class="sc-wallet-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-wallet')); ?>" class="sc-wallet-list-add-btn">کیف پول</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-withdrawals')); ?>" class="sc-wallet-list-export-btn">درخواست برداشت</a>
        </div>
    </div>

    <div class="sc-wallet-list-stats">
        <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--green">
            <div class="sc-wallet-list-stat-label">موجودی کیف پول</div>
            <div class="sc-wallet-list-stat-value"><?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> <small>تومان</small></div>
        </div>
        <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--purple">
            <div class="sc-wallet-list-stat-label">مجموع دستمزد (فیلتر فعلی)</div>
            <div class="sc-wallet-list-stat-value"><?php echo number_format($total_salary, 0, '.', ','); ?> <small>تومان</small></div>
        </div>
        <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--blue">
            <div class="sc-wallet-list-stat-label">تعداد رکورد</div>
            <div class="sc-wallet-list-stat-value"><?php echo $total_items > 0 ? sprintf('%d تا %d از %d', $offset + 1, min($offset + count($salary_records), $total_items), $total_items) : '۰'; ?></div>
        </div>
    </div>

    <div class="sc-wallet-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-wallet-list-filters-toolbar">
            <button type="button"
                    class="sc-wallet-list-filters-toggle"
                    id="sc-coach-salary-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-coach-salary-filters-panel">
                <span class="sc-wallet-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-wallet-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-wallet-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-wallet-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-salary')); ?>" class="sc-wallet-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="GET" action="" class="sc-wallet-list-filters-panel" id="sc-coach-salary-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-coach-salary">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_course">دوره</label>
                    <select name="filter_course" id="filter_course" class="sc-filter-control">
                        <option value="0">همه دوره‌ها</option>
                        <?php foreach ($all_courses_for_filter as $course) : ?>
                            <option value="<?php echo (int) $course->id; ?>" <?php selected($filter_course, (int) $course->id); ?>>
                                <?php echo esc_html($course->title); ?>
                            </option>
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

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_date_from_shamsi">از تاریخ</label>
                    <input type="text"
                           name="filter_date_from_shamsi"
                           id="filter_date_from_shamsi"
                           value="<?php echo esc_attr($display_date_from_shamsi_salary); ?>"
                           class="sc-filter-control persian-date-input"
                           placeholder="از تاریخ"
                           readonly>
                    <input type="hidden"
                           name="filter_date_from"
                           id="filter_date_from"
                           value="<?php echo esc_attr($filter_date_from); ?>">
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_date_to_shamsi">تا تاریخ</label>
                    <input type="text"
                           name="filter_date_to_shamsi"
                           id="filter_date_to_shamsi"
                           value="<?php echo esc_attr($display_date_to_shamsi_salary); ?>"
                           class="sc-filter-control persian-date-input"
                           placeholder="تا تاریخ"
                           readonly>
                    <input type="hidden"
                           name="filter_date_to"
                           id="filter_date_to"
                           value="<?php echo esc_attr($filter_date_to); ?>">
                </div>
            </div>

            <div class="sc-wallet-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-salary')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-wallet-list-table-card">
    <div class="sc-coach-salary-table-wrapper">
    <table class="wp-list-table widefat fixed striped sc-coach-salary-table">
        <thead>
            <tr>
                
                <th class="column-index">ردیف</th>
                <th class="column-id">ID</th>
                <th class="column-date">تاریخ</th>
                <th class="column-course">دوره</th>
                <th class="column-chapter">شعبه</th>
                <th class="column-type">نوع</th>
                <th class="column-attendance">تعداد افراد</th>
                <th class="column-price">قیمت هر جلسه</th>
                <th class="column-revenue">کل درآمد</th>
                <th class="column-percent">درصد دستمزد</th>
                <th class="column-amount">مبلغ دستمزد</th>
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
                        <td class="column-index"><?php echo $row_number++; ?></td>
                        <td class="column-id"><code><?php echo esc_html($record->id ?? '-'); ?></code></td>
                        
                        <td class="column-date"><span class="sc-wallet-date"><?php echo esc_html(sc_date_shamsi_date_only($record->attendance_date)); ?></span></td>
                        <td class="column-course">
                            <?php 
                            if ($record->course_id > 0) {
                                echo esc_html($record->course_title ?: 'دوره حذف شده');
                            } else {
                                echo '<em>دستمزد ثابت</em>';
                            }
                            ?>
                        </td>
                        <td class="column-chapter"><?php echo !empty($record->chapter_name) ? esc_html($record->chapter_name) : '-'; ?></td>
                        <td class="column-type">
                            <?php if ($record->salary_type === 'percentage'): ?>
                                <span class="sc-badge sc-badge--purple sc-wallet-type-badge">
                                    <span class="sc-wallet-type-icon" aria-hidden="true">%</span>
                                    درصدی
                                </span>
                            <?php else: ?>
                                <span class="sc-badge sc-badge--success sc-wallet-type-badge">
                                    <span class="sc-wallet-type-icon" aria-hidden="true">↑</span>
                                    ثابت
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="column-attendance"><?php echo $record->attendance_count > 0 ? number_format($record->attendance_count) : '-'; ?></td>
                        <td class="column-price"><?php echo $record->price_per_session > 0 ? number_format($record->price_per_session, 0, '.', ',') . ' تومان' : '-'; ?></td>
                        <td class="column-revenue"><?php echo $record->total_revenue > 0 ? number_format($record->total_revenue, 0, '.', ',') . ' تومان' : '-'; ?></td>
                        <td class="column-percent"><?php echo $record->salary_percentage > 0 ? number_format($record->salary_percentage, 2) . '%' : '-'; ?></td>
                        <td class="column-amount"><span class="sc-wallet-amount is-credit">+ <?php echo esc_html(number_format($record->salary_amount, 0, '.', ',')); ?></span></td>
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
    </div>
    </div>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom sc_paginate" style="margin-top: 15px;">
            <div class="tablenav-pages">
                <p class="pagination-links">
                    <?php
                    $pagination_args = ['page' => 'sc-coach-salary'];
                    if (!empty($filter_date_from_shamsi)) $pagination_args['filter_date_from_shamsi'] = $filter_date_from_shamsi;
                    if (!empty($filter_date_to_shamsi)) $pagination_args['filter_date_to_shamsi'] = $filter_date_to_shamsi;
                    if ($filter_course > 0) $pagination_args['filter_course'] = $filter_course;
                    if ($filter_type !== 'all') $pagination_args['filter_type'] = $filter_type;
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

<style>
    /* جدول دستمزد مربی (بخش مربی) */
    .sc-coach-salary-table-wrapper {
        overflow-x: auto;
        margin-top: 10px;
    }

    .sc-coach-salary-table th,
    .sc-coach-salary-table td {
        vertical-align: middle;
        white-space: nowrap;
    }

    .sc-coach-salary-table .column-index {
        width: 50px;
        text-align: center;
    }

    .sc-coach-salary-table .column-id {
        width: 70px;
        text-align: center;
    }

    .sc-coach-salary-table .column-date {
        width: 120px;
    }

    .sc-coach-salary-table .column-course {
        min-width: 160px;
    }

    .sc-coach-salary-table .column-type {
        width: 90px;
    }

    .sc-coach-salary-table .column-attendance {
        width: 110px;
        text-align: center;
    }

    .sc-coach-salary-table .column-price,
    .sc-coach-salary-table .column-revenue,
    .sc-coach-salary-table .column-amount {
        width: 130px;
        text-align: right;
    }

    .sc-coach-salary-table .column-percent {
        width: 110px;
        text-align: center;
    }

    @media (max-width: 960px) {
        .sc-coach-salary-table th,
        .sc-coach-salary-table td {
            padding: 6px 8px;
            font-size: 12px;
        }
    }

    @media (max-width: 782px) {
        .sc-coach-salary-table-wrapper {
            margin: 0 -10px;
        }

        .sc-coach-salary-table th,
        .sc-coach-salary-table td {
            padding: 6px 6px;
            font-size: 11px;
        }

        .sc-coach-salary-table .column-course {
            min-width: 180px;
        }

        .sc-coach-salary-table .column-price,
        .sc-coach-salary-table .column-revenue,
        .sc-coach-salary-table .column-amount {
            width: 120px;
        }
    }
</style>

<script>
jQuery(document).ready(function($) {
    var $toggle = $('#sc-coach-salary-filters-toggle');
    var $panel = $('#sc-coach-salary-filters-panel');
    var $card = $toggle.closest('.sc-wallet-list-filters-card');
    var $label = $toggle.find('.sc-wallet-list-filters-toggle-label');
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

    function updateGregorianDate($shamsiInput) {
        var shamsiValue = $shamsiInput.val();
        if (!shamsiValue) return;
        
        var gregorianValue = convertShamsiToGregorian(shamsiValue);
        if (gregorianValue) {
            var inputId = $shamsiInput.attr('id');
            if (inputId === 'filter_date_from_shamsi') {
                $('#filter_date_from').val(gregorianValue);
            } else if (inputId === 'filter_date_to_shamsi') {
                $('#filter_date_to').val(gregorianValue);
            }
        }
    }
    
    // تبدیل اولیه تاریخ‌ها
    if ($('#filter_date_from_shamsi').val()) {
        updateGregorianDate($('#filter_date_from_shamsi'));
    }
    if ($('#filter_date_to_shamsi').val()) {
        updateGregorianDate($('#filter_date_to_shamsi'));
    }
    
    // تبدیل هنگام تغییر تاریخ
    $(document).on('change', '#filter_date_from_shamsi, #filter_date_to_shamsi', function() {
        updateGregorianDate($(this));
    });
});
</script>
