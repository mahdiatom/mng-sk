<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
//این فایل اجرا نمی شود در قسمت add menu کامنت شده در آینده اگر نیاز بود اضافه خواهد شد .

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$invoices_table = $wpdb->prefix . 'sc_invoices';
$members_table = $wpdb->prefix . 'sc_members';
$courses_table = $wpdb->prefix . 'sc_courses';
$member_courses_table = $wpdb->prefix . 'sc_member_courses';

// دریافت فیلترها
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;

// پردازش فیلترهای تاریخ (شمسی به میلادی)
$filter_date_from = '';
$filter_date_to = '';
if (isset($_GET['filter_date_from_shamsi']) && !empty($_GET['filter_date_from_shamsi'])) {
    $filter_date_from = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_from_shamsi']));
} elseif (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
    $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
}

if (isset($_GET['filter_date_to_shamsi']) && !empty($_GET['filter_date_to_shamsi'])) {
    $filter_date_to = sc_shamsi_to_gregorian_date(sanitize_text_field($_GET['filter_date_to_shamsi']));
} elseif (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
    $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
}

// تاریخ پیش‌فرض: امروز
$today = new DateTime();
$today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
$filter_date_from_shamsi_default = '';
$filter_date_to_shamsi_default = '';

if (empty($filter_date_from)) {
    $filter_date_from_shamsi_default = $today_jalali[0] . '/' . 
                                       str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                       str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
} else {
    $filter_date_from_shamsi_default = sc_date_shamsi_date_only($filter_date_from);
}

if (empty($filter_date_to)) {
    $filter_date_to_shamsi_default = $today_jalali[0] . '/' . 
                                     str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                     str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
} else {
    $filter_date_to_shamsi_default = sc_date_shamsi_date_only($filter_date_to);
}

// دریافت لیست دوره‌ها و اعضا برای فیلترها
$courses = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC");
$all_members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

// ساخت WHERE clause برای صورت حساب‌های پرداخت شده
$where_conditions = ["i.status IN ('completed', 'paid')"];
$where_values = [];

// فیلتر کاربر
if ($filter_member > 0) {
    $where_conditions[] = "i.member_id = %d";
    $where_values[] = $filter_member;
}

// فیلتر دوره
if ($filter_course > 0) {
    $where_conditions[] = "i.course_id = %d";
    $where_values[] = $filter_course;
}

// فیلتر تاریخ
if ($filter_date_from) {
    $where_conditions[] = "DATE(i.created_at) >= %s";
    $where_values[] = $filter_date_from;
}

if ($filter_date_to) {
    $where_conditions[] = "DATE(i.created_at) <= %s";
    $where_values[] = $filter_date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// دریافت تعداد کل رکوردها برای pagination
$total_query = "SELECT COUNT(*) 
                FROM $invoices_table i
                WHERE $where_clause";
if (!empty($where_values)) {
    $total_items = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
} else {
    $total_items = $wpdb->get_var($total_query);
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// دریافت لیست صورت حساب‌های پرداخت شده
$query = "SELECT SQL_CALC_FOUND_ROWS 
                i.*,
                m.first_name,
                m.last_name,
                m.player_phone,
                c.title as course_title,
                c.price as course_price
          FROM $invoices_table i
          INNER JOIN $members_table m ON i.member_id = m.id
          LEFT JOIN $courses_table c ON i.course_id = c.id
          WHERE $where_clause
          ORDER BY i.created_at DESC
          LIMIT %d OFFSET %d";

$query_values = $where_values;
$query_values[] = $per_page;
$query_values[] = $offset;

if (!empty($query_values)) {
    $payments = $wpdb->get_results($wpdb->prepare($query, $query_values));
} else {
    $payments = $wpdb->get_results($query);
}

$total_items = $wpdb->get_var("SELECT FOUND_ROWS()");
$total_pages = ceil($total_items / $per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">گزارشات باشگاه - پرداختی‌ها</h1>
    <hr class="wp-header-end">
    
    <!-- فیلترها -->
    <form method="GET" action="" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
        <input type="hidden" name="page" value="sc-reports-payments">
        
        <table class="form-table sc_form-table">
            <tr>
                <th scope="row">
                    <label for="filter_member">کاربر</label>
                </th>
                <td>
                    <div class="sc-searchable-dropdown" style="position: relative; width: 100%; max-width: 400px;">
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
                        <div class="sc-dropdown-toggle" style="position: relative; cursor: pointer; border: 1px solid #8c8f94; border-radius: 4px; padding: 8px 35px 8px 12px; background: #fff; min-height: 30px; display: flex; align-items: center;">
                            <span class="sc-dropdown-placeholder" style="color: #757575; display: <?php echo $filter_member > 0 ? 'none' : 'inline'; ?>;">همه کاربران</span>
                            <span class="sc-dropdown-selected" style="color: #2c3338; display: <?php echo $filter_member > 0 ? 'inline' : 'none'; ?>;"><?php echo esc_html($selected_member_text); ?></span>
                            <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #757575;">▼</span>
                        </div>
                        <div class="sc-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #8c8f94; border-top: none; border-radius: 0 0 4px 4px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.2); margin-top: -1px;">
                            <div class="sc-dropdown-search" style="padding: 10px; border-bottom: 1px solid #ddd; position: sticky; top: 0; background: #fff;">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی..." style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                            </div>
                            <div class="sc-dropdown-options" style="max-height: 250px; overflow-y: auto;">
                                <div class="sc-dropdown-option sc-visible" 
                                     data-value="0"
                                     data-search="همه کاربران"
                                     style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; <?php echo $filter_member == 0 ? 'background: #f0f6fc;' : ''; ?>"
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
                    <label>بازه تاریخ (شمسی)</label>
                </th>
                <td>
                    <input type="text" name="filter_date_from_shamsi" id="filter_date_from_shamsi" 
                           value="<?php echo esc_attr($filter_date_from_shamsi_default); ?>" 
                           class="regular-text persian-date-input" 
                           placeholder="از تاریخ (شمسی)" 
                           style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                    <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                    <span>تا</span>
                    <input type="text" name="filter_date_to_shamsi" id="filter_date_to_shamsi" 
                           value="<?php echo esc_attr($filter_date_to_shamsi_default); ?>" 
                           class="regular-text persian-date-input" 
                           placeholder="تا تاریخ (شمسی)" 
                           style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                    <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                    <p class="description">برای انتخاب تاریخ، روی فیلد کلیک کنید</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="filter" class="button button-primary" value="اعمال فیلتر">
            <?php
            // ساخت URL برای export Excel
            $export_url = admin_url('admin.php?page=sc-reports-payments&sc_export=excel&export_type=payments');
            if ($filter_member > 0) {
                $export_url = add_query_arg('filter_member', $filter_member, $export_url);
            }
            if ($filter_course > 0) {
                $export_url = add_query_arg('filter_course', $filter_course, $export_url);
            }
            if (!empty($filter_date_from)) {
                $export_url = add_query_arg('filter_date_from', $filter_date_from, $export_url);
            }
            if (!empty($filter_date_to)) {
                $export_url = add_query_arg('filter_date_to', $filter_date_to, $export_url);
            }
            $export_url = wp_nonce_url($export_url, 'sc_export_excel');
            ?>
            <a href="<?php echo esc_url($export_url); ?>" class="button button_export" >
                📊 خروجی Excel
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc-reports-payments'); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
        </p>
    </form>
    
    <!-- لیست پرداختی‌ها -->
    <?php if (empty($payments)) : ?>
        <div class="notice notice-info">
            <p>هیچ پرداختی یافت نشد.</p>
        </div>
    <?php else : ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">ردیف</th>
                        <th>سفارش</th>
                        <th>نام و نام خانوادگی کاربر</th>
                        <th>تاریخ ثبت سفارش</th>
                        <th>جزئیات سفارش</th>
                        <th>مجموع قیمت</th>
                        <th>شماره تماس</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $start_number = ($current_page - 1) * $per_page;
                    foreach ($payments as $index => $payment) : 
                        $row_number = $start_number + $index + 1;
                        
                        // شماره سفارش
                        $order_number = '#' . $payment->id;
                        if (!empty($payment->woocommerce_order_id)) {
                            if (function_exists('wc_get_order')) {
                                $order = wc_get_order($payment->woocommerce_order_id);
                                if ($order) {
                                    $order_number = $order->get_order_number();
                                } else {
                                    $order_number = '#' . $payment->woocommerce_order_id;
                                }
                            } else {
                                $order_number = '#' . $payment->woocommerce_order_id;
                            }
                        }
                        
                        // جزئیات سفارش
                        $course_title = $payment->course_title ?? '';
                        $course_price = isset($payment->course_price) ? floatval($payment->course_price) : 0;
                        $expense_name = $payment->expense_name ?? '';
                        $total_amount = isset($payment->amount) ? floatval($payment->amount) : 0;
                        
                        $parts = [];
                        if (!empty($course_title) && trim($course_title) !== '') {
                            $course_display = esc_html($course_title);
                            if ($course_price > 0) {
                                $course_display .= ' (' . number_format($course_price, 0, '.', ',') . ' تومان)';
                            }
                            $parts[] = '<strong>دوره:</strong> ' . $course_display;
                        }
                        
                        if (!empty($expense_name) && trim($expense_name) !== '') {
                            $expense_display = esc_html($expense_name);
                            $expense_amount = $total_amount - $course_price;
                            if ($expense_amount > 0) {
                                $expense_display .= ' (' . number_format($expense_amount, 0, '.', ',') . ' تومان)';
                            }
                            $parts[] = '<strong>هزینه اضافی:</strong> ' . $expense_display;
                        }
                        
                        $details_html = !empty($parts) ? implode('<br>', $parts) : '<span style="color: #999; font-style: italic;">بدون دوره</span>';
                        
                        // مبلغ کل
                        $total_with_penalty = $total_amount + (float)($payment->penalty_amount ?? 0);
                    ?>
                        <tr>
                            <td><?php echo $row_number; ?></td>
                            <td><strong><?php echo esc_html($order_number); ?></strong></td>
                            <td><?php echo esc_html($payment->first_name . ' ' . $payment->last_name); ?></td>
                            <td><?php echo sc_date_shamsi($payment->created_at, 'Y/m/d H:i'); ?></td>
                            <td>
                                <div style="line-height: 1.8;">
                                    <?php echo $details_html; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (function_exists('wc_price')) : ?>
                                    <?php echo wc_price($total_with_penalty); ?>
                                <?php else : ?>
                                    <strong><?php echo number_format($total_with_penalty, 0, '.', ','); ?> تومان</strong>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($payment->player_phone ?: '-'); ?></td>
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




