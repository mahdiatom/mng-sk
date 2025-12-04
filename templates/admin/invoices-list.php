<?php
global $invoices_list_table;

// همگام‌سازی وضعیت‌های WooCommerce با صورت حساب‌ها
if (function_exists('wc_get_order')) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    
    // دریافت صورت حساب‌هایی که woocommerce_order_id دارند
    $invoices_to_sync = $wpdb->get_results(
        "SELECT id, woocommerce_order_id, status 
         FROM $invoices_table 
         WHERE woocommerce_order_id IS NOT NULL AND woocommerce_order_id > 0 
         LIMIT 50"
    );
    
    foreach ($invoices_to_sync as $invoice) {
        $order = wc_get_order($invoice->woocommerce_order_id);
        if ($order) {
            $wc_status = $order->get_status();
            $current_status = $invoice->status;
            $sync_needed = false;
            $new_status = $current_status;
            
            // تبدیل وضعیت‌های قدیمی به WooCommerce
            if ($current_status === 'under_review') {
                $current_status = 'on-hold';
            } elseif ($current_status === 'paid') {
                $current_status = 'completed';
            }
            
            // sync وضعیت WooCommerce
            if ($wc_status !== $current_status) {
                $new_status = $wc_status;
                $sync_needed = true;
            }
            
            if ($sync_needed) {
                $update_data = ['status' => $new_status, 'updated_at' => current_time('mysql')];
                $update_format = ['%s', '%s'];
                
                if (in_array($new_status, ['completed', 'processing'])) {
                    $update_data['payment_date'] = current_time('mysql');
                    $update_format[] = '%s';
                }
                
                $wpdb->update(
                    $invoices_table,
                    $update_data,
                    ['id' => $invoice->id],
                    $update_format,
                    ['%d']
                );
            }
        }
    }
}

// دریافت لیست دوره‌ها و اعضا برای فیلتر
global $wpdb;
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';
$courses = $wpdb->get_results(
    "SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"
);
$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC"
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">صورت حساب‌ها</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-add-invoice'); ?>" class="page-title-action">ایجاد صورت حساب</a>
</div>

<!-- فیلترها -->
<div class="wrap" style="margin-top: 20px;">
    <form method="GET" action="" style="padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;">
        <input type="hidden" name="page" value="sc-invoices">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="filter_course">دوره</label>
                </th>
                <td>
                    <select name="filter_course" id="filter_course" style="width: 300px; padding: 5px;">
                        <option value="0">همه دوره‌ها</option>
                        <?php 
                        $filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
                        foreach ($courses as $course) : 
                        ?>
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
                    <div class="sc-searchable-dropdown" style="position: relative; width: 100%; max-width: 400px;">
                        <?php 
                        $filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
                        $selected_member_text = 'همه کاربران';
                        if ($filter_member > 0) {
                            foreach ($members as $m) {
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
                                foreach ($members as $member) : 
                                    $is_selected = ($filter_member == $member->id);
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                ?>
                                    <div class="sc-dropdown-option <?php echo $display_class; ?>" 
                                         data-value="<?php echo esc_attr($member->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                         style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; <?php echo $is_selected ? 'background: #f0f6fc;' : ''; ?>"
                                         onclick="scSelectMemberFilter(this, '<?php echo esc_js($member->id); ?>', '<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
                                        <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
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
                    <label for="filter_status">وضعیت پرداخت</label>
                </th>
                <td>
                    <select name="filter_status" id="filter_status" style="width: 300px; padding: 5px;">
                        <?php 
                        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
                        $status_options = [
                            'all' => 'همه وضعیت‌ها',
                            'pending' => 'در انتظار پرداخت',
                            'processing' => 'پرداخت شده',
                            'on-hold' => 'در حال بررسی',
                            'completed' => 'تایید پرداخت',
                            'cancelled' => 'لغو شده',
                            'failed' => 'ناموفق'
                        ];
                        foreach ($status_options as $value => $label) :
                        ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($filter_status, $value); ?>>
                                <?php echo esc_html($label); ?>
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
                    <?php 
                    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
                    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
                    
                    // تبدیل تاریخ‌های میلادی به شمسی برای نمایش
                    $filter_date_from_shamsi = '';
                    $filter_date_to_shamsi = '';
                    if (!empty($filter_date_from)) {
                        $filter_date_from_shamsi = sc_date_shamsi_date_only($filter_date_from);
                    } else {
                        // تاریخ پیش‌فرض: امروز
                        $today = new DateTime();
                        $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                        $filter_date_from_shamsi = $today_jalali[0] . '/' . 
                                                   str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                                   str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                    }
                    if (!empty($filter_date_to)) {
                        $filter_date_to_shamsi = sc_date_shamsi_date_only($filter_date_to);
                    } else {
                        // تاریخ پیش‌فرض: امروز
                        $today = new DateTime();
                        $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
                        $filter_date_to_shamsi = $today_jalali[0] . '/' . 
                                                 str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                                 str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
                    }
                    ?>
                    <input type="text" name="filter_date_from_shamsi" id="filter_date_from_shamsi" 
                           value="<?php echo esc_attr($filter_date_from_shamsi); ?>" 
                           class="regular-text persian-date-input" 
                           placeholder="از تاریخ (شمسی)" 
                           style="padding: 5px; margin-left: 10px; width: 150px;" readonly>
                    <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                    <span>تا</span>
                    <input type="text" name="filter_date_to_shamsi" id="filter_date_to_shamsi" 
                           value="<?php echo esc_attr($filter_date_to_shamsi); ?>" 
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
            // ساخت URL برای export Excel با حفظ فیلترها
            $export_url = admin_url('admin.php?page=sc-invoices&sc_export=excel&export_type=invoices');
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
            <a href="<?php echo esc_url($export_url); ?>" class="button" style="background-color: #00a32a; border-color: #00a32a; color: #fff;">
                📊 خروجی Excel
            </a>
            <a href="<?php echo admin_url('admin.php?page=sc-invoices'); ?>" class="button">پاک کردن فیلترها</a>
        </p>
    </form>
</div>

<?php
echo '<div class="wrap">';
    echo '<form Method="get">';
        echo '<input type="hidden" name="page" value="sc-invoices">';
        
        // حفظ فیلترها در فرم جستجو
        if (isset($_GET['filter_course'])) {
            echo '<input type="hidden" name="filter_course" value="' . esc_attr($_GET['filter_course']) . '">';
        }
        if (isset($_GET['filter_member'])) {
            echo '<input type="hidden" name="filter_member" value="' . esc_attr($_GET['filter_member']) . '">';
        }
        if (isset($_GET['filter_date_from_shamsi'])) {
            echo '<input type="hidden" name="filter_date_from_shamsi" value="' . esc_attr($_GET['filter_date_from_shamsi']) . '">';
        }
        if (isset($_GET['filter_date_from'])) {
            echo '<input type="hidden" name="filter_date_from" value="' . esc_attr($_GET['filter_date_from']) . '">';
        }
        if (isset($_GET['filter_date_to_shamsi'])) {
            echo '<input type="hidden" name="filter_date_to_shamsi" value="' . esc_attr($_GET['filter_date_to_shamsi']) . '">';
        }
        if (isset($_GET['filter_date_to'])) {
            echo '<input type="hidden" name="filter_date_to" value="' . esc_attr($_GET['filter_date_to']) . '">';
        }
        if (isset($_GET['filter_status'])) {
            echo '<input type="hidden" name="filter_status" value="' . esc_attr($_GET['filter_status']) . '">';
        }
        
        $invoices_list_table->search_box('جستجو صورت حساب (نام، نام خانوادگی، کد ملی، شماره سفارش)', 'search_invoice');
        $invoices_list_table->views();
        $invoices_list_table->display();
    echo '</form>';
echo '</div>';
?>



