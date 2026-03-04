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
$filter_debt_status = isset($_GET['filter_debt_status']) ? sanitize_text_field($_GET['filter_debt_status']) : 'all';
$filter_insurance_status = isset($_GET['filter_insurance_status']) ? sanitize_text_field($_GET['filter_insurance_status']) : 'all';
$filter_profile_status = isset($_GET['filter_profile_status']) ? sanitize_text_field($_GET['filter_profile_status']) : 'all';

// دریافت لیست دوره‌ها و اعضا برای فیلترها
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");
$all_members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

// ساخت WHERE clause
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

// دریافت اعضا (بدون LIMIT برای امکان فیلتر کردن)
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
foreach ($members as $member) {
    // محاسبه کل مبلغ صورت حساب‌های پرداخت نشده
    
   
    $debt_result = debt_user( $member->id)[0];
    $debt_count =  debt_user( $member->id)[1] ?? 0;
    $member->debt_amount = $debt_result ? floatval($debt_result) : 0;
    $member->has_debt = $member->debt_amount > 0;
    
    // بررسی وضعیت بیمه
    if (!empty($member->insurance_expiry_date_shamsi)) {
        $today = new DateTime();
        $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
        $today_shamsi = $today_jalali[0] . '/' . 
                       str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                       str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
        
        $expiry_parts = explode('/', $member->insurance_expiry_date_shamsi);
        $today_parts = explode('/', $today_shamsi);
        
        if (count($expiry_parts) === 3 && count($today_parts) === 3) {
            $expiry_year = (int)$expiry_parts[0];
            $expiry_month = (int)$expiry_parts[1];
            $expiry_day = (int)$expiry_parts[2];
            
            $today_year = (int)$today_parts[0];
            $today_month = (int)$today_parts[1];
            $today_day = (int)$today_parts[2];
            
            $is_expired = false;
            if ($expiry_year < $today_year) {
                $is_expired = true;
            } elseif ($expiry_year == $today_year) {
                if ($expiry_month < $today_month) {
                    $is_expired = true;
                } elseif ($expiry_month == $today_month) {
                    if ($expiry_day < $today_day) {
                        $is_expired = true;
                    }
                }
            }
            
            $member->insurance_active = !$is_expired;
        } else {
            $member->insurance_active = false;
        }
    } else {
        $member->insurance_active = false;
    }
    
    // بررسی تکمیل پروفایل
    $member->profile_completed = sc_check_profile_completed($member->id);
    
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
}

// اعمال فیلترهای بعد از دریافت داده‌ها
$filtered_members = [];
foreach ($members as $member) {
    // فیلتر وضعیت بدهی
    if ($filter_debt_status !== 'all') {
        if ($filter_debt_status === 'has_debt' && !$member->has_debt) {
            continue;
        }
        if ($filter_debt_status === 'no_debt' && $member->has_debt) {
            continue;
        }
    }
    
    // فیلتر وضعیت بیمه
    if ($filter_insurance_status !== 'all') {
        if ($filter_insurance_status === 'active' && !$member->insurance_active) {
            continue;
        }
        if ($filter_insurance_status === 'expired' && $member->insurance_active) {
            continue;
        }
    }
    
    // فیلتر وضعیت پروفایل
    if ($filter_profile_status !== 'all') {
        if ($filter_profile_status === 'completed' && !$member->profile_completed) {
            continue;
        }
        if ($filter_profile_status === 'incomplete' && $member->profile_completed) {
            continue;
        }
    }
    
    $filtered_members[] = $member;
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$total_items = count($filtered_members);
$total_pages = ceil($total_items / $per_page);
$offset = ($current_page - 1) * $per_page;
$members = array_slice($filtered_members, $offset, $per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">گزارشات باشگاه - کاربران فعال</h1>
    <hr class="wp-header-end">
    
    <!-- فیلترها -->
    <form method="GET" action="" class="form_filter_general">
        <input type="hidden" name="page" value="sc-reports-active-users">
        
        <table class="form-table sc_form-table">
            <tr>
                <th scope="row">
                    <label for="filter_member">کاربر</label>
                </th>
                <td>
                    <div class="sc-searchable-dropdown">
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
                        <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                        <div class="sc-dropdown-toggle" >
                            <span class="sc-dropdown-placeholder" style="color: #757575; display: <?php echo $filter_member > 0 ? 'none' : 'inline'; ?>;">همه کاربران</span>
                            <span class="sc-dropdown-selected" style="color: #2c3338; display: <?php echo $filter_member > 0 ? 'inline' : 'none'; ?>;"><?php echo esc_html($selected_member_text); ?></span>
                            <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #757575;">▼</span>
                        </div>
                        <div class="sc-dropdown-menu" >
                            <div class="sc-dropdown-search" >
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options" >
                                <div class="sc-dropdown-option sc-visible" 
                                     data-value="0"
                                     data-search="همه کاربران"
                                      <?php echo $filter_member == 0 ? 'background: #f0f6fc;' : ''; ?>"
                                     onclick="scSelectMemberFilter(this, '0', 'همه کاربران')">
                                    همه کاربران
                                    <?php if ($filter_member == 0) : ?>
                                        <span style="float: left; color: #2271b1; font-weight: bold;">✓</span>
                                    <?php endif; ?>
                                </div>
                                <?php 
                                $display_count = 0;
                                $max_display = 10;
                                foreach ($all_members as $member_option) : 
                                    $is_selected = ($filter_member == $member_option->id);
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                ?>
                                    <div class="sc-dropdown-option <?php echo $display_class; ?>" 
                                         data-value="<?php echo esc_attr($member_option->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($member_option->first_name . ' ' . $member_option->last_name . ' ' . $member_option->national_id)); ?>"
                                         style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; <?php echo $is_selected ? 'background: #f0f6fc;' : ''; ?>"
                                         onclick="scSelectMemberFilter(this, '<?php echo esc_js($member_option->id); ?>', '<?php echo esc_js($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>')">
                                        <?php echo esc_html($member_option->first_name . ' ' . $member_option->last_name . ' - ' . $member_option->national_id); ?>
                                        <?php if ($is_selected) : ?>
                                            <span style="float: left; color: #2271b1; font-weight: bold;">✓</span>
                                        <?php endif; ?>
                                    </div>
                                <?php 
                                    if ($is_selected) {
                                        $display_count++;
                                    } elseif ($display_count < $max_display) {
                                        $display_count++;
                                    }
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="filter_course">دوره</label>
                </th>
                <td>
                    <select name="filter_course" id="filter_course">
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
                    <label for="filter_debt_status">وضعیت بدهی</label>
                </th>
                <td>
                    <select name="filter_debt_status" id="filter_debt_status">
                        <option value="all" <?php selected($filter_debt_status, 'all'); ?>>همه</option>
                        <option value="has_debt" <?php selected($filter_debt_status, 'has_debt'); ?>>دارد</option>
                        <option value="no_debt" <?php selected($filter_debt_status, 'no_debt'); ?>>ندارد</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="filter_insurance_status">بیمه</label>
                </th>
                <td>
                    <select name="filter_insurance_status" id="filter_insurance_status" >
                        <option value="all" <?php selected($filter_insurance_status, 'all'); ?>>همه</option>
                        <option value="active" <?php selected($filter_insurance_status, 'active'); ?>>فعال</option>
                        <option value="expired" <?php selected($filter_insurance_status, 'expired'); ?>>منقضی</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="filter_profile_status">وضعیت پروفایل</label>
                </th>
                <td>
                    <select name="filter_profile_status" id="filter_profile_status" >
                        <option value="all" <?php selected($filter_profile_status, 'all'); ?>>همه</option>
                        <option value="completed" <?php selected($filter_profile_status, 'completed'); ?>>تکمیل</option>
                        <option value="incomplete" <?php selected($filter_profile_status, 'incomplete'); ?>>ناقص</option>
                    </select>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
            <?php
            // ساخت URL برای export Excel
            $export_url = admin_url('admin.php?page=sc-reports-active-users&sc_export=excel&export_type=active_users');
            if ($filter_member > 0) {
                $export_url = add_query_arg('filter_member', $filter_member, $export_url);
            }
            if ($filter_course > 0) {
                $export_url = add_query_arg('filter_course', $filter_course, $export_url);
            }
            if (isset($_GET['filter_debt_status']) && $_GET['filter_debt_status'] !== 'all') {
                $export_url = add_query_arg('filter_debt_status', $_GET['filter_debt_status'], $export_url);
            }
            if (isset($_GET['filter_insurance_status']) && $_GET['filter_insurance_status'] !== 'all') {
                $export_url = add_query_arg('filter_insurance_status', $_GET['filter_insurance_status'], $export_url);
            }
            if (isset($_GET['filter_profile_status']) && $_GET['filter_profile_status'] !== 'all') {
                $export_url = add_query_arg('filter_profile_status', $_GET['filter_profile_status'], $export_url);
            }
            $export_url = wp_nonce_url($export_url, 'sc_export_excel');
            ?>
            <a href="<?php echo esc_url($export_url); ?>" class="button" style="background-color: #00a32a; border-color: #00a32a; color: #fff;">
                📊 خروجی Excel
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc-reports-active-users'); ?>" class="button">پاک کردن فیلترها</a>
        </p>
    </form>
    
    <!-- لیست کاربران -->
    <?php if (empty($members)) : ?>
        <div class="notice notice-info">
            <p>هیچ کاربر فعالی یافت نشد.</p>
        </div>
    <?php else : ?>
        
        <div class="back_attendance_list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ردیف</th>
                        <th>نام و نام خانوادگی</th>
                        <th>دوره‌های فعال</th>
                        <th>شماره تماس</th>
                        <th>مقدار بدهی</th>
                        <th>وضعیت پروفایل</th>
                        <th>بیمه</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $start_number = ($current_page - 1) * $per_page;
                    foreach ($members as $index => $member) : 
                        $row_number = $start_number + $index + 1;
                        
                        // دوره‌های فعال
                        $course_names = [];
                        if (!empty($member->active_courses)) {
                            foreach ($member->active_courses as $course) {
                                $course_names[] = $course->title;
                            }
                        }
                        $courses_text = !empty($course_names) ? implode('، ', $course_names) : '-';
                    ?>
                        <tr style="background-color: <?php echo (debt_user( $member->id)[0]) ? '#c3191957' : '' ?> !important;">
                            <td><?php echo $row_number; ?></td>
                            <td>
                                <strong><?php echo esc_html($member->first_name . ' ' . $member->last_name); ?></strong>
                            </td>
                            <td><?php echo esc_html($courses_text); ?></td>
                            <td><?php echo esc_html($member->player_phone ?: '-'); ?></td>
                            <td>
                                <?php if ($member->has_debt) : ?>
                                    <span style="color: #d63638; font-weight: bold;">
                                        <?php echo number_format($member->debt_amount, 0, '.', ','); ?> تومان
                                    </span>
                                <?php else : ?>
                                    <span style="color: #00a32a;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($member->profile_completed) : ?>
                                    <span style="color: #00a32a; font-weight: bold;">✓ تکمیل</span>
                                <?php else : ?>
                                    <span style="color: #d63638; font-weight: bold;">✗ ناقص</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($member->insurance_expiry_date_shamsi)) : ?>
                                    <?php if ($member->insurance_active) : ?>
                                        <span style="color: #00a32a; font-weight: bold;">✓ فعال</span>
                                    <?php else : ?>
                                        <span style="color: #d63638; font-weight: bold;">✗ منقضی</span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>    
            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate" style="margin-top: 20px;">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg(['paged' => '%#%']),
                            'format' => '',
                            'prev_text' => '< قبلی ',
                            'next_text' => ' بعدی >' ,
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

