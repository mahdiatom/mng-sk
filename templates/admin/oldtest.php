<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$event_registrations_table = $wpdb->prefix . 'sc_event_registrations';
$events_table = $wpdb->prefix . 'sc_events';
$members_table = $wpdb->prefix . 'sc_members';
$invoices_table = $wpdb->prefix . 'sc_invoices';

// دریافت فیلترها
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$filter_event = isset($_GET['filter_event']) ? absint($_GET['filter_event']) : 0;
$filter_event_type = isset($_GET['filter_event_type']) ? sanitize_text_field($_GET['filter_event_type']) : 'all';
$filter_order = isset($_GET['filter_order']) ? sanitize_text_field($_GET['filter_order']) : '';
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$offset = ($current_page - 1) * $per_page;

// دریافت لیست رویدادها و اعضا برای فیلترها
$all_events = $wpdb->get_results("SELECT id, name, event_type, holding_date_shamsi, holding_date_gregorian FROM $events_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name ASC");
$all_members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");

// ساخت WHERE clause
$where_conditions = [];
$where_values = [];

if ($filter_member > 0) {
    $where_conditions[] = "r.member_id = %d";
    $where_values[] = $filter_member;
}

if ($filter_event > 0) {
    $where_conditions[] = "r.event_id = %d";
    $where_values[] = $filter_event;
}

if ($filter_event_type !== 'all') {
    $where_conditions[] = "e.event_type = %s";
    $where_values[] = $filter_event_type;
}

if (!empty($filter_order)) {
    $filter_order = str_replace('#', '', $filter_order);
    $filter_order = absint($filter_order);
    if ($filter_order > 0) {
        $where_conditions[] = "(i.woocommerce_order_id = %d OR i.id = %d)";
        $where_values[] = $filter_order;
        $where_values[] = $filter_order;
    }
}

if ($filter_status !== 'all') {
    $where_conditions[] = "i.status = %s";
    $where_values[] = $filter_status;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Query برای دریافت داده‌ها
$query = "SELECT r.*, 
                 e.name as event_name,
                 e.event_type as event_type,
                 e.holding_date_shamsi as event_holding_date,
                 m.first_name, 
                 m.last_name, 
                 m.player_phone,
                 i.status,
                 i.woocommerce_order_id,
                 i.id as invoice_id
          FROM $event_registrations_table r
          LEFT JOIN $events_table e ON r.event_id = e.id AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
          LEFT JOIN $members_table m ON r.member_id = m.id
          LEFT JOIN $invoices_table i ON r.invoice_id = i.id
          $where_clause
          ORDER BY r.created_at DESC
          LIMIT %d OFFSET %d";

if (!empty($where_values)) {
    $query_values = array_merge($where_values, [$per_page, $offset]);
    $registrations = $wpdb->get_results($wpdb->prepare($query, $query_values), ARRAY_A);
} else {
    $registrations = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset), ARRAY_A);
}

// Query برای تعداد کل
$count_query = "SELECT COUNT(*)
                FROM $event_registrations_table r
                LEFT JOIN $events_table e ON r.event_id = e.id AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
                LEFT JOIN $members_table m ON r.member_id = m.id
                LEFT JOIN $invoices_table i ON r.invoice_id = i.id
                $where_clause";

if (!empty($where_values)) {
    $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
} else {
    $total_items = $wpdb->get_var($count_query);
}

$total_pages = ceil($total_items / $per_page);

// Debug mode
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo '<div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc; direction: rtl;">';
    echo '<h3>اطلاعات دیباگ</h3>';
    echo '<p><strong>Query:</strong> ' . esc_html($wpdb->last_query) . '</p>';
    echo '<p><strong>تعداد آیتم‌های یافت شده:</strong> ' . count($registrations) . '</p>';
    echo '<p><strong>خطای آخر:</strong> ' . esc_html($wpdb->last_error ?: 'هیچ خطایی نیست') . '</p>';
    echo '<p><strong>مقادیر Where:</strong> ' . print_r($where_values, true) . '</p>';
    echo '<p><strong>Where Clause:</strong> ' . esc_html($where_clause ?: 'خالی') . '</p>';
    echo '<p><strong>تعداد کل:</strong> ' . $total_items . '</p>';
    if (!empty($registrations)) {
        echo '<p><strong>نمونه اولین آیتم:</strong></p>';
        echo '<pre>' . print_r($registrations[0], true) . '</pre>';
    }
    echo '</div>';
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">ثبت‌نامی‌های رویداد</h1>
</div>

<!-- فیلترها -->
<div class="wrap" style="margin-top: 20px;">
    <form method="GET" action="" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
        <input type="hidden" name="page" value="sc-event-registrations">
        
        <table class="form-table">
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
                                    $selected_member_text = trim($m->first_name . ' ' . $m->last_name) . ' - ' . $m->national_id;
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
                                         onclick="scSelectMemberFilter(this, '<?php echo esc_js($member_option->id); ?>', '<?php echo esc_js(trim($member_option->first_name . ' ' . $member_option->last_name) . ' - ' . $member_option->national_id); ?>')">
                                        <?php echo esc_html(trim($member_option->first_name . ' ' . $member_option->last_name) . ' - ' . $member_option->national_id); ?>
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
                    <label for="filter_event_type">نوع</label>
                </th>
                <td>
                    <select name="filter_event_type" id="filter_event_type" style="width: 200px; padding: 5px;">
                        <option value="all" <?php selected($filter_event_type, 'all'); ?>>همه</option>
                        <option value="event" <?php selected($filter_event_type, 'event'); ?>>رویداد</option>
                        <option value="competition" <?php selected($filter_event_type, 'competition'); ?>>مسابقه</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="filter_event">رویداد</label>
                </th>
                <td>
                    <div class="sc-searchable-dropdown" style="position: relative; width: 100%; max-width: 400px;">
                        <?php 
                        $selected_event_text = 'همه رویدادها';
                        if ($filter_event > 0) {
                            foreach ($all_events as $e) {
                                if ($e->id == $filter_event) {
                                    $holding_date_display = '';
                                    if (!empty($e->holding_date_shamsi)) {
                                        $holding_date_display = ' - ' . $e->holding_date_shamsi;
                                    }
                                    $selected_event_text = $e->name . $holding_date_display;
                                    break;
                                }
                            }
                        }
                        ?>
                        <input type="hidden" name="filter_event" id="filter_event" value="<?php echo esc_attr($filter_event); ?>">
                        <div class="sc-dropdown-toggle" style="position: relative; cursor: pointer; border: 1px solid #8c8f94; border-radius: 4px; padding: 8px 35px 8px 12px; background: #fff; min-height: 30px; display: flex; align-items: center;">
                            <span class="sc-dropdown-placeholder" style="color: #757575; display: <?php echo $filter_event > 0 ? 'none' : 'inline'; ?>;">همه رویدادها</span>
                            <span class="sc-dropdown-selected" style="color: #2c3338; display: <?php echo $filter_event > 0 ? 'inline' : 'none'; ?>;"><?php echo esc_html($selected_event_text); ?></span>
                            <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #757575;">▼</span>
                        </div>
                        <div class="sc-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #8c8f94; border-top: none; border-radius: 0 0 4px 4px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.2); margin-top: -1px;">
                            <div class="sc-dropdown-search" style="padding: 10px; border-bottom: 1px solid #ddd; position: sticky; top: 0; background: #fff;">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام رویداد..." style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;">
                            </div>
                            <div class="sc-dropdown-options" style="max-height: 250px; overflow-y: auto;">
                                <div class="sc-dropdown-option sc-visible" 
                                     data-value="0"
                                     data-search="همه رویدادها"
                                     style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; <?php echo $filter_event == 0 ? 'background: #f0f6fc;' : ''; ?>"
                                     onclick="scSelectEventFilter(this, '0', 'همه رویدادها')">
                                    همه رویدادها
                                    <?php if ($filter_event == 0) : ?>
                                        <span style="float: left; color: #2271b1; font-weight: bold;">✓</span>
                                    <?php endif; ?>
                                </div>
                                <?php 
                                $display_count = 0;
                                $max_display = 10;
                                foreach ($all_events as $event_option) : 
                                    $is_selected = ($filter_event == $event_option->id);
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $holding_date_display = '';
                                    if (!empty($event_option->holding_date_shamsi)) {
                                        $holding_date_display = ' - ' . $event_option->holding_date_shamsi;
                                    }
                                    $event_display_text = $event_option->name . $holding_date_display;
                                ?>
                                    <div class="sc-dropdown-option <?php echo $display_class; ?>" 
                                         data-value="<?php echo esc_attr($event_option->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($event_option->name . ' ' . ($event_option->holding_date_shamsi ?: ''))); ?>"
                                         style="padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f1; <?php echo $is_selected ? 'background: #f0f6fc;' : ''; ?>"
                                         onclick="scSelectEventFilter(this, '<?php echo esc_js($event_option->id); ?>', '<?php echo esc_js($event_display_text); ?>')">
                                        <?php echo esc_html($event_display_text); ?>
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
                    <label for="filter_order">شماره سفارش</label>
                </th>
                <td>
                    <input type="text" name="filter_order" id="filter_order" value="<?php echo esc_attr($filter_order); ?>" placeholder="#123" style="width: 300px; padding: 5px;">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="filter_status">وضعیت</label>
                </th>
                <td>
                    <select name="filter_status" id="filter_status" style="width: 300px; padding: 5px;">
                        <option value="all" <?php selected($filter_status, 'all'); ?>>همه وضعیت‌ها</option>
                        <option value="pending" <?php selected($filter_status, 'pending'); ?>>در انتظار پرداخت</option>
                        <option value="processing" <?php selected($filter_status, 'processing'); ?>>پرداخت شده</option>
                        <option value="completed" <?php selected($filter_status, 'completed'); ?>>تایید پرداخت</option>
                        <option value="on-hold" <?php selected($filter_status, 'on-hold'); ?>>در حال بررسی</option>
                        <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>لغو شده</option>
                    </select>
                </td>
            </tr>
        </table>
        
        <p class="submit" style="margin-top: 20px;">
            <input type="submit" name="filter_action" id="post-query-submit" class="button button-primary" value="اعمال فیلتر">
            <a href="<?php echo admin_url('admin.php?page=sc-event-registrations'); ?>" class="button" style="margin-right: 10px;">حذف فیلتر</a>
            <?php
            $export_url = add_query_arg([
                'sc_export' => 'excel',
                'export_type' => 'event_registrations',
                'filter_member' => $filter_member,
                'filter_event' => $filter_event,
                'filter_event_type' => $filter_event_type,
                'filter_order' => $filter_order,
                'filter_status' => $filter_status,
                '_wpnonce' => wp_create_nonce('sc_export_excel')
            ], admin_url('admin.php'));
            ?>
            <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary" style="margin-right: 10px;">
                <span class="dashicons dashicons-media-spreadsheet" style="vertical-align: middle; margin-left: 5px;"></span>
                خروجی اکسل
            </a>
        </p>
    </form>
</div>

<!-- جدول -->
<div class="wrap" style="margin-top: 20px;">
    <?php if (!empty($registrations)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;">ردیف</th>
                    <th style="width: 120px;">شماره سفارش</th>
                    <th>نام رویداد</th>
                    <th>نام کاربر</th>
                    <th style="width: 120px;">شماره تماس</th>
                    <th style="width: 120px;">وضعیت</th>
                    <th style="width: 120px;">تاریخ ثبت‌نام</th>
                    <th style="width: 200px;">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row_number = ($current_page - 1) * $per_page;
                foreach ($registrations as $registration) : 
                    $row_number++;
                    
                    // شماره سفارش
                    $order_number = '#' . $registration['invoice_id'];
                    if (!empty($registration['woocommerce_order_id'])) {
                        if (function_exists('wc_get_order')) {
                            $order = wc_get_order($registration['woocommerce_order_id']);
                            if ($order) {
                                $wc_order_number = $order->get_order_number();
                                if (strpos($wc_order_number, '#') === false) {
                                    $order_number = '#' . $wc_order_number;
                                } else {
                                    $order_number = $wc_order_number;
                                }
                            }
                        }
                    }
                    
                    // وضعیت
                    $status = $registration['status'] ?: 'pending';
                    if ($status === 'under_review') {
                        $status = 'on-hold';
                    } elseif ($status === 'paid') {
                        $status = 'completed';
                    }
                    
                    if (!empty($registration['woocommerce_order_id']) && function_exists('wc_get_order')) {
                        $order = wc_get_order($registration['woocommerce_order_id']);
                        if ($order) {
                            $status = $order->get_status();
                        }
                    }
                    
                    $status_labels = [
                        'pending' => ['label' => 'در انتظار پرداخت', 'color' => '#f0a000', 'bg' => '#fff8e1'],
                        'on-hold' => ['label' => 'در حال بررسی', 'color' => '#2271b1', 'bg' => '#e5f5fa'],
                        'processing' => ['label' => 'پرداخت شده', 'color' => '#00a32a', 'bg' => '#d4edda'],
                        'completed' => ['label' => 'تایید پرداخت', 'color' => '#00a32a', 'bg' => '#d4edda'],
                        'cancelled' => ['label' => 'لغو شده', 'color' => '#d63638', 'bg' => '#ffeaea'],
                        'refunded' => ['label' => 'بازگشت شده', 'color' => '#d63638', 'bg' => '#ffeaea'],
                        'failed' => ['label' => 'ناموفق', 'color' => '#d63638', 'bg' => '#ffeaea']
                    ];
                    
                    $status_info = isset($status_labels[$status]) ? $status_labels[$status] : ['label' => $status, 'color' => '#666', 'bg' => '#f5f5f5'];
                    
                    // تاریخ
                    $created_date = '-';
                    if (!empty($registration['created_at'])) {
                        $date = new DateTime($registration['created_at']);
                        $shamsi_date = gregorian_to_jalali(
                            (int)$date->format('Y'),
                            (int)$date->format('m'),
                            (int)$date->format('d')
                        );
                        $created_date = $shamsi_date[0] . '/' . 
                                       str_pad($shamsi_date[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                       str_pad($shamsi_date[2], 2, '0', STR_PAD_LEFT);
                    }
                    
                    // نام کاربر
                    $member_name = trim(($registration['first_name'] ?: '') . ' ' . ($registration['last_name'] ?: ''));
                    $member_name = $member_name ?: 'کاربر حذف شده';
                    
                    // نام رویداد
                    $event_name = $registration['event_name'] ?: 'رویداد حذف شده';
                    $event_type = isset($registration['event_type']) ? $registration['event_type'] : 'event';
                    $event_type_label = ($event_type === 'competition') ? 'مسابقه' : 'رویداد';
                    $event_name_display = $event_name . ' (' . $event_type_label . ')';
                    
                    $registration_id = intval($registration['id']);
                    $invoice_id = intval($registration['invoice_id'] ?: 0);
                ?>
                <tr>
                    <td><?php echo $row_number; ?></td>
                    <td><strong><?php echo esc_html($order_number); ?></strong></td>
                    <td><?php echo esc_html($event_name_display); ?></td>
                    <td><?php echo esc_html($member_name); ?></td>
                    <td><?php echo esc_html($registration['player_phone'] ?: '-'); ?></td>
                    <td>
                        <span style="display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; color: <?php echo esc_attr($status_info['color']); ?>; background: <?php echo esc_attr($status_info['bg']); ?>;">
                            <?php echo esc_html($status_info['label']); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($created_date); ?></td>
                    <td>
                        <a href="#" class="sc-view-registration-details" data-registration-id="<?php echo esc_attr($registration_id); ?>" style="cursor: pointer; color: #2271b1; text-decoration: none;">مشاهده جزئیات</a>
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
    <?php else : ?>
        <div class="notice notice-info">
            <p>هیچ ثبت‌نامی یافت نشد.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modal برای مشاهده جزئیات -->
<div id="scRegistrationModal" class="sc-modal" style="display: none !important; visibility: hidden !important;">
    <div class="sc-modal-content">
        <div class="sc-modal-header">
            <h2 class="sc-modal-title">جزئیات ثبت‌نام</h2>
            <span class="sc-modal-close">&times;</span>
        </div>
        <div class="sc-modal-body">
            <div class="sc-modal-loading" style="text-align: center; padding: 40px;">
                <div class="sc-spinner"></div>
                <p>در حال بارگذاری...</p>
            </div>
            <div class="sc-modal-content-body" style="display: none;"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var scRegistrationNonce = '<?php echo wp_create_nonce("sc_registration_nonce"); ?>';
    
    // مشاهده جزئیات ثبت‌نام
    $(document).on('click', '.sc-view-registration-details', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var registrationId = $(this).data('registration-id');
        
        if (!registrationId) {
            alert('شناسه ثبت‌نام معتبر نیست');
            return;
        }
        
        var $modal = $('#scRegistrationModal');
        var $loading = $modal.find('.sc-modal-loading');
        var $contentBody = $modal.find('.sc-modal-content-body');
        
        $loading.show();
        $contentBody.hide().empty();
        
        $modal.css({
            'display': 'flex',
            'visibility': 'visible'
        }).addClass('show-modal');
        
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sc_get_registration_details',
                registration_id: registrationId,
                nonce: scRegistrationNonce
            },
            success: function(response) {
                $loading.hide();
                
                if (response && response.success && response.data && response.data.html) {
                    $contentBody.html(response.data.html).fadeIn(300);
                } else {
                    var errorMsg = (response && response.data && response.data.message) ? response.data.message : 'خطای نامشخص';
                    $contentBody.html('<p style="text-align: center; padding: 40px; color: #d63638;">خطا: ' + errorMsg + '</p>').fadeIn(300);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                $loading.hide();
                
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse && jsonResponse.data && jsonResponse.data.message) {
                        $contentBody.html('<p style="text-align: center; padding: 40px; color: #d63638;">خطا: ' + jsonResponse.data.message + '</p>').fadeIn(300);
                    } else {
                        $contentBody.html('<p style="text-align: center; padding: 40px; color: #d63638;">خطا در دریافت اطلاعات. لطفاً دوباره تلاش کنید.</p>').fadeIn(300);
                    }
                } catch(e) {
                    $contentBody.html('<p style="text-align: center; padding: 40px; color: #d63638;">خطا در دریافت اطلاعات. لطفاً دوباره تلاش کنید.</p><p style="text-align: center; color: #666; font-size: 12px;">خطا: ' + error + ' (Status: ' + xhr.status + ')</p>').fadeIn(300);
                }
            }
        });
    });
    
    // بستن modal
    $(document).on('click', '.sc-modal-close', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $modal = $('#scRegistrationModal');
        $modal.removeClass('show-modal');
        $modal.css({
            'display': 'none',
            'visibility': 'hidden'
        });
    });
    
    $(document).on('click', '#scRegistrationModal', function(e) {
        if ($(e.target).is('#scRegistrationModal')) {
            var $modal = $(this);
            $modal.removeClass('show-modal');
            $modal.css({
                'display': 'none',
                'visibility': 'hidden'
            });
        }
    });
    
    $(document).on('click', '.sc-modal-content', function(e) {
        e.stopPropagation();
    });
});
</script>

<style>
.sc-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.sc-modal.show-modal {
    display: flex !important;
    visibility: visible !important;
}

.sc-modal-content {
    background: #fff;
    border-radius: 4px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.sc-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f9f9f9;
}

.sc-modal-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.sc-modal-close {
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    line-height: 1;
    border: none;
    background: none;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sc-modal-close:hover {
    color: #000;
}

.sc-modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

.sc-modal-loading {
    text-align: center;
    padding: 40px;
}

.sc-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #2271b1;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>