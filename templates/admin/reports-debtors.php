<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';
$invoices_table = $wpdb->prefix . 'sc_invoices';

// دریافت فیلترها
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;

// دریافت لیست دوره‌ها و اعضا برای فیلترها
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");
$all_members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

// ساخت WHERE clause برای دریافت اعضای بدهکار
$where_conditions = ['m.is_active = 1'];
$where_values = [];

// فیلتر کاربر
if ($filter_member > 0) {
    $where_conditions[] = "m.id = %d";
    $where_values[] = $filter_member;
}

// فیلتر دوره
if ($filter_course > 0) {
    $where_conditions[] = "m.id IN (SELECT member_id FROM $member_courses_table WHERE course_id = %d AND status = 'active')";
    $where_values[] = $filter_course;
}

$where_clause = implode(' AND ', $where_conditions);

// دریافت اعضا
$query = "SELECT m.* 
          FROM $members_table m 
          WHERE $where_clause 
          ORDER BY m.last_name ASC, m.first_name ASC";

if (!empty($where_values)) {
    $members = $wpdb->get_results($wpdb->prepare($query, $where_values));
} else {
    $members = $wpdb->get_results($query);
}

// محاسبه بدهی برای هر کاربر
$debtors = [];
foreach ($members as $member) {
    // محاسبه کل مبلغ و تعداد صورت حساب‌های پرداخت نشده
    $sum_count_debt =debt_user($member->id);
    $debt_amount = $sum_count_debt[0];
    $debt_count = $sum_count_debt[1];
    
    // فقط اگر بدهی داشته باشد، به لیست اضافه می‌کنیم
    if ($debt_amount > 0) {
        $member->debt_amount = $debt_amount;
        $member->debt_count = $debt_count;
        
        // دریافت دوره‌های فعال
        $member_courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.title 
             FROM $courses_table c 
             INNER JOIN $member_courses_table mc ON c.id = mc.course_id 
             WHERE mc.member_id = %d AND mc.status = 'active' AND c.deleted_at IS NULL 
             ORDER BY c.title ASC",
            $member->id
        ));
        $member->active_courses = $member_courses;
        
        $debtors[] = $member;
    }
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$total_items = count($debtors);
$total_pages = ceil($total_items / $per_page);
$offset = ($current_page - 1) * $per_page;
$debtors = array_slice($debtors, $offset, $per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">گزارشات باشگاه - بدهکاران</h1>
    <hr class="wp-header-end">
    
    <!-- فیلترها -->
  <form method="GET" action="" class="sc-filter-form">
    <input type="hidden" name="page" value="sc-reports-debtors">

    <div>

        <!-- کاربر -->
        <div class="sc-filter-field">
            <label class="sc-filter-label">کاربر</label>

            <?php
            $selected_member_text = 'همه کاربران';
            if ($filter_member > 0) {
                foreach ($all_members as $m) {
                    if ($m->id == $filter_member) {
                        $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
                        break;
                    }
                }
            }
            ?>

            <div class="sc-searchable-dropdown sc_debtors_dropdown-toggle">
                <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">

                <div class="sc-dropdown-toggle ">
                    <span class="sc-dropdown-placeholder <?php echo $filter_member ? 'sc-hidden' : ''; ?>">
                        همه کاربران
                    </span>

                    <span class="sc-dropdown-selected <?php echo !$filter_member ? 'sc-hidden' : ''; ?>">
                        <?php echo esc_html($selected_member_text); ?>
                    </span>

                    <span class="sc-dropdown-arrow">▼</span>
                </div>

                <div class="sc-dropdown-menu">
                    <div class="sc-dropdown-search">
                        <input type="text" class="sc-search-input"
                               placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                    </div>

                    <div class="sc-dropdown-options">
                        <div class="sc-dropdown-option sc-visible <?php echo $filter_member == 0 ? 'sc-selected' : ''; ?>"
                             data-value="0"
                             data-search="همه کاربران"
                             onclick="scSelectMemberFilter(this,'0','همه کاربران')">
                            همه کاربران
                            <?php if ($filter_member == 0): ?>
                                <span class="sc-checkmark">✓</span>
                            <?php endif; ?>
                        </div>

                        <?php
                        $display_count = 0;
                        $max_display = 10;
                        foreach ($all_members as $member_option):
                            $is_selected = ($filter_member == $member_option->id);
                            $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                            $display_count++;
                        ?>
                            <div class="sc-dropdown-option <?php echo $display_class; ?> <?php echo $is_selected ? 'sc-selected' : ''; ?>"
                                 data-value="<?php echo esc_attr($member_option->id); ?>"
                                 data-search="<?php echo esc_attr(strtolower($member_option->first_name . ' ' . $member_option->last_name . ' ' . $member_option->national_id)); ?>"
                                 onclick="scSelectMemberFilter(this,'<?php echo esc_js($member_option->id); ?>','<?php echo esc_js($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>')">
                                <?php echo esc_html($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>
                                <?php if ($is_selected): ?>
                                    <span class="sc-checkmark">✓</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- دوره -->
        <div class="sc-filter-field">
            <label class="sc-filter-label" for="filter_course">دوره</label>
            <select name="filter_course" id="filter_course" class="sc-filter-control">
                <option value="0">همه دوره‌ها</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>>
                        <?php echo esc_html($course->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

    </div>
    
      
        <p class="submit">
            <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
            <?php
            // ساخت URL برای export Excel
            $export_url = admin_url('admin.php?page=sc-reports-debtors&sc_export=excel&export_type=debtors');
            if ($filter_member > 0) {
                $export_url = add_query_arg('filter_member', $filter_member, $export_url);
            }
            if ($filter_course > 0) {
                $export_url = add_query_arg('filter_course', $filter_course, $export_url);
            }
            $export_url = wp_nonce_url($export_url, 'sc_export_excel');
            ?>
            <a href="<?php echo esc_url($export_url); ?>" class="button" style="background-color: #00a32a; border-color: #00a32a; color: #fff;">
                📊 خروجی Excel
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc-reports-debtors'); ?>" class="button">پاک کردن فیلترها</a>
        </p>
    </form>
    
    <!-- لیست بدهکاران -->
    <?php if (empty($debtors)) : ?>
        <div class="notice notice-info">
            <p>هیچ بدهکاری یافت نشد.</p>
        </div>
    <?php else : ?>
        <div class="back_attendance_list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ردیف</th>
                        <th>نام و نام خانوادگی</th>
                        <th>دوره‌های فعال</th>
                        <th>مبلغ</th>
                        <th>تعداد بدهی‌ها</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $start_number = ($current_page - 1) * $per_page;
                    foreach ($debtors as $index => $debtor) : 
                        $row_number = $start_number + $index + 1;
                        
                        // دوره‌های فعال
                        $course_names = [];
                        if (!empty($debtor->active_courses)) {
                            foreach ($debtor->active_courses as $course) {
                                $course_names[] = $course->title;
                            }
                        }
                        $courses_text = !empty($course_names) ? implode('، ', $course_names) : '-';
                    ?>
                        <tr >
                            <td><?php echo $row_number; ?></td>
                            <td>
                                <strong><?php echo esc_html($debtor->first_name . ' ' . $debtor->last_name); ?></strong>
                            </td>
                            <td><?php echo esc_html($courses_text); ?></td>
                            <td>
                                <span style="color: #d63638; font-weight: bold;">
                                    <?php echo number_format($debtor->debt_amount, 0, '.', ','); ?> تومان
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: bold;">
                                    <?php echo $debtor->debt_count; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
         </div>   
            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom" style="margin-top: 20px;">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg(['paged' => '%#%']),
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

<style>

</style>
