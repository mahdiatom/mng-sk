<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

$is_secretary_event_regs = function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only();

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
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
$filter_date_to_shamsi   = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';
$filter_date_from = '';
$filter_date_to   = '';
if (!empty($filter_date_from_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_from = sc_shamsi_to_gregorian_date($filter_date_from_shamsi);
} elseif (isset($_GET['filter_date_from']) && $_GET['filter_date_from'] !== '') {
    $filter_date_from = sanitize_text_field($_GET['filter_date_from']);
    $filter_date_from_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($filter_date_from) : $filter_date_from_shamsi;
}
if (!empty($filter_date_to_shamsi) && function_exists('sc_shamsi_to_gregorian_date')) {
    $filter_date_to = sc_shamsi_to_gregorian_date($filter_date_to_shamsi);
} elseif (isset($_GET['filter_date_to']) && $_GET['filter_date_to'] !== '') {
    $filter_date_to = sanitize_text_field($_GET['filter_date_to']);
    $filter_date_to_shamsi = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($filter_date_to) : $filter_date_to_shamsi;
}
$filter_free = isset($_GET['filter_free']) ? absint($_GET['filter_free']) : 0;
$filter_user_type = isset($_GET['filter_user_type']) ? sanitize_text_field($_GET['filter_user_type']) : 'all';

// حذف ثبت‌نامی رویداد
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['registration_id'])) {
    $registration_id = absint($_GET['registration_id']);
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

    if (!wp_verify_nonce($nonce, 'sc_delete_registration_' . $registration_id)) {
        wp_die('خطای امنیتی!');
    }

    if (!current_user_can('manage_options')) {
        wp_die('دسترسی ندارید!');
    }

    global $wpdb;

    // دریافت اطلاعات ثبت‌نام
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_event_registrations WHERE id = %d",
        $registration_id
    ), ARRAY_A);

    if (!$registration) {
        wp_die('ثبت‌نام یافت نشد!');
    }

    // دریافت اطلاعات رویداد
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_events WHERE id = %d",
        $registration['event_id']
    ), ARRAY_A);

    $is_free_event = $event && floatval($event['price']) === 0;

    // حذف از جدول ثبت‌نام
    $deleted = $wpdb->delete("{$wpdb->prefix}sc_event_registrations", ['id' => $registration_id], ['%d']);

    if ($deleted !== false) {

        // اگر رویداد غیر رایگان است
        if (!$is_free_event && !empty($registration['invoice_id'])) {

            // حذف از جدول sc_invoices و گرفتن ووکامرس آیدی
            $invoice = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sc_invoices WHERE id = %d",
                $registration['invoice_id']
            ), ARRAY_A);

            if ($invoice) {
                // حذف invoice
                $wpdb->delete("{$wpdb->prefix}sc_invoices", ['id' => $invoice['id']], ['%d']);

                // حذف سفارش ووکامرس
                if (!empty($invoice['woocommerce_order_id']) && function_exists('wc_get_order')) {
                    $order_id = intval($invoice['woocommerce_order_id']);
                    $order = wc_get_order($order_id);
                    if ($order) {
                        // لغو سفارش
                        $order->update_status('cancelled', 'ثبت‌نام مربوط به این رویداد حذف شد.');
                        // حذف کامل سفارش
                        wp_delete_post($order_id, true);
                    }
                }
            }
        }

        // بازگشت به صفحه اصلی با پیام موفقیت
        wp_redirect(add_query_arg('deleted', '1', admin_url('admin.php?page=sc-event-registrations&sc_status=bulk_deleted_register')));
        exit;
    } else {
        wp_die('خطا در حذف ثبت‌نام');
    }
}



// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$offset = ($current_page - 1) * $per_page;

// دریافت لیست رویدادها و اعضا برای فیلترها
$event_where_parts = ['deleted_at IS NULL', 'is_active = 1'];
$event_where_values = [];
if (function_exists('sc_secretary_merge_event_chapter_where')) {
    sc_secretary_merge_event_chapter_where($event_where_parts, $event_where_values, 'e');
}
$event_where_sql = implode(' AND ', $event_where_parts);
if (!empty($event_where_values)) {
    $all_events = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, event_type, holding_date_shamsi, holding_date_gregorian FROM $events_table e WHERE {$event_where_sql} ORDER BY name ASC",
        $event_where_values
    ));
} else {
    $all_events = $wpdb->get_results("SELECT id, name, event_type, holding_date_shamsi, holding_date_gregorian FROM $events_table e WHERE {$event_where_sql} ORDER BY name ASC");
}
if (function_exists('sc_secretary_get_branch_members_for_picker') && function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only()) {
    $all_members = sc_secretary_get_branch_members_for_picker();
} else {
    $all_members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
}

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

if ($filter_free === 1) {
    $where_conditions[] = "e.price = %d";
    $where_values[] = 0;
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
if ($filter_user_type === 'member') {
    $where_conditions[] = "(r.registration_source IS NULL OR r.registration_source <> %s)";
    $where_values[] = 'guest';
} elseif ($filter_user_type === 'guest') {
    $where_conditions[] = "r.registration_source = %s";
    $where_values[] = 'guest';
}
// فیلتر تاریخ ثبت‌نام
if (!empty($filter_date_from) && !empty($filter_date_to)) {
    $where_conditions[] = "r.created_at BETWEEN %s AND %s";
    $where_values[] = $filter_date_from . ' 00:00:00';
    $where_values[] = $filter_date_to . ' 23:59:59';
}

if (function_exists('sc_secretary_merge_event_registration_where')) {
    sc_secretary_merge_event_registration_where($where_conditions, $where_values, 'r', 'e');
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
                 r.registration_source,
                 r.guest_first_name,
                 r.guest_last_name,
                 r.guest_phone,
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
}

$selected_event_text = 'همه رویدادها';
if ($filter_event > 0) {
    foreach ($all_events as $e) {
        if ((int) $e->id === $filter_event) {
            $selected_event_text = $e->name . ' - ' . $e->holding_date_shamsi;
            break;
        }
    }
}
$selected_member_text = 'همه کاربران';
if ($filter_member > 0) {
    foreach ($all_members as $m) {
        if ((int) $m->id === $filter_member) {
            $selected_member_text = trim($m->first_name . ' ' . $m->last_name) . ' - ' . $m->national_id;
            break;
        }
    }
}

$today_shamsi_reg = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('Y-m-d')) : '';
if (!$today_shamsi_reg && function_exists('gregorian_to_jalali')) {
    $today = new DateTime(current_time('Y-m-d'));
    $jalali = gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('m'), (int) $today->format('d'));
    $today_shamsi_reg = $jalali[0] . '/' . str_pad((string) $jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $jalali[2], 2, '0', STR_PAD_LEFT);
}
$display_date_from_shamsi_reg = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $today_shamsi_reg;
$display_date_to_shamsi_reg   = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $today_shamsi_reg;

$active_filters_count = 0;
if ($filter_member > 0) {
    $active_filters_count++;
}
if ($filter_event > 0) {
    $active_filters_count++;
}
if ($filter_event_type !== 'all') {
    $active_filters_count++;
}
if ($filter_order !== '' && $filter_order !== '0') {
    $active_filters_count++;
}
if ($filter_status !== 'all') {
    $active_filters_count++;
}
if ($filter_date_from !== '' || $filter_date_to !== '') {
    $active_filters_count++;
}
if ($filter_free === 1) {
    $active_filters_count++;
}
if ($filter_user_type !== 'all') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$export_url = admin_url('admin.php?page=sc-event-registrations&sc_export=excel&export_type=event_registrations');
$export_url = add_query_arg('filter_status', $filter_status, $export_url);
$export_url = add_query_arg('filter_event', $filter_event, $export_url);
$export_url = add_query_arg('filter_member', $filter_member, $export_url);
$export_url = add_query_arg('filter_free', $filter_free, $export_url);
$export_url = add_query_arg('filter_user_type', $filter_user_type, $export_url);
if ($filter_event_type !== 'all') {
    $export_url = add_query_arg('filter_event_type', $filter_event_type, $export_url);
}
if ($filter_date_from !== '') {
    $export_url = add_query_arg('filter_date_from', $filter_date_from, $export_url);
}
if ($filter_date_to !== '') {
    $export_url = add_query_arg('filter_date_to', $filter_date_to, $export_url);
}
$export_url = wp_nonce_url($export_url, 'sc_export_excel');
?>

<div class="wrap sc-event-regs-list-wrap event_registrs">
    <div class="sc-event-regs-list-header">
        <div class="sc-event-regs-list-header-text">
            <h1 class="sc-event-regs-list-title">ثبت‌نامی‌های رویداد</h1>
            <p class="sc-event-regs-list-desc"><?php echo $is_secretary_event_regs ? 'ثبت‌نامی بازیکنان شعبه شما و رویدادهای شعبه(های) مجاز.' : 'برای مشاهده درست حتماً بازه تاریخی را در ابتدا وارد کنید.'; ?></p>
        </div>
        <?php if (!$is_secretary_event_regs) : ?>
        <div class="sc-event-regs-list-header-actions">
            <a href="<?php echo esc_url($export_url); ?>" class="sc-event-regs-list-export-btn">خروجی Excel</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="sc-event-regs-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-event-regs-list-filters-toolbar">
            <button type="button"
                    class="sc-event-regs-list-filters-toggle"
                    id="sc-event-regs-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-event-regs-filters-panel">
                <span class="sc-event-regs-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-event-regs-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-event-regs-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-event-regs-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-event-registrations')); ?>" class="sc-event-regs-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="GET" action="" class="sc-event-regs-list-filters-panel" id="sc-event-regs-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-event-registrations">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_member">کاربر</label>
                    <div class="sc-searchable-dropdown">
                        <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php echo $filter_member > 0 ? 'style="display:none"' : ''; ?>>همه کاربران</span>
                            <span class="sc-dropdown-selected" <?php echo $filter_member == 0 ? 'style="display:none"' : ''; ?>>
                                <?php echo esc_html($selected_member_text); ?>
                            </span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option sc-visible <?php echo $filter_member == 0 ? 'sc-selected' : ''; ?>"
                                     data-value="0"
                                     data-search="همه کاربران"
                                     onclick="scSelectMemberFilter(this,'0','همه کاربران')">
                                    همه کاربران
                                </div>
                                <?php
                                $display_count = 0;
                                $max_display = 10;
                                foreach ($all_members as $member_option) :
                                    $is_selected = ((int) $filter_member === (int) $member_option->id);
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    ?>
                                    <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?> <?php echo $is_selected ? 'sc-selected' : ''; ?>"
                                         data-value="<?php echo esc_attr($member_option->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($member_option->first_name . ' ' . $member_option->last_name . ' ' . $member_option->national_id)); ?>"
                                         onclick="scSelectMemberFilter(this,'<?php echo esc_js($member_option->id); ?>','<?php echo esc_js(trim($member_option->first_name . ' ' . $member_option->last_name) . ' - ' . $member_option->national_id); ?>')">
                                        <?php echo esc_html(trim($member_option->first_name . ' ' . $member_option->last_name) . ' - ' . $member_option->national_id); ?>
                                    </div>
                                    <?php
                                    if ($is_selected || $display_count < $max_display) {
                                        $display_count++;
                                    }
                                endforeach;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sc-filter-field">
                    <label for="filter_event_type" class="sc-filter-label">نوع</label>
                    <select name="filter_event_type" id="filter_event_type" class="sc-filter-control">
                        <option value="all" <?php selected($filter_event_type, 'all'); ?>>همه</option>
                        <option value="event" <?php selected($filter_event_type, 'event'); ?>>رویداد</option>
                        <option value="competition" <?php selected($filter_event_type, 'competition'); ?>>مسابقه</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label for="filter_event" class="sc-filter-label">رویداد</label>
                    <div class="sc-searchable-dropdown">
                        <input type="hidden" name="filter_event" id="filter_event" value="<?php echo esc_attr($filter_event); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php echo $filter_event > 0 ? 'style="display:none"' : ''; ?>>همه رویدادها</span>
                            <span class="sc-dropdown-selected" <?php echo $filter_event == 0 ? 'style="display:none"' : ''; ?>>
                                <?php echo esc_html($selected_event_text); ?>
                            </span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام رویداد...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option" data-value="0" onclick="scSelectEventFilter(this,'0','همه رویدادها')">
                                    همه رویدادها
                                </div>
                                <?php foreach ($all_events as $event_option) : ?>
                                    <div class="sc-dropdown-option"
                                         data-value="<?php echo esc_attr($event_option->id); ?>"
                                         onclick="scSelectEventFilter(this,'<?php echo esc_js($event_option->id); ?>','<?php echo esc_js($event_option->name . ' - ' . $event_option->holding_date_shamsi); ?>')">
                                        <?php echo esc_html($event_option->name . ' - ' . $event_option->holding_date_shamsi); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sc-filter-field">
                    <label for="filter_order" class="sc-filter-label">شماره سفارش</label>
                    <input type="text" name="filter_order" id="filter_order" value="<?php echo esc_attr($filter_order); ?>" class="sc-filter-control" placeholder="#123">
                </div>

                <div class="sc-filter-field">
                    <label for="filter_status" class="sc-filter-label">وضعیت</label>
                    <select name="filter_status" id="filter_status" class="sc-filter-control">
                        <option value="all" <?php selected($filter_status, 'all'); ?>>همه وضعیت‌ها</option>
                        <option value="pending" <?php selected($filter_status, 'pending'); ?>>در انتظار پرداخت</option>
                        <option value="processing" <?php selected($filter_status, 'processing'); ?>>پرداخت شده</option>
                        <option value="completed" <?php selected($filter_status, 'completed'); ?>>تایید پرداخت</option>
                        <option value="on-hold" <?php selected($filter_status, 'on-hold'); ?>>در حال بررسی</option>
                        <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>لغو شده</option>
                    </select>
                </div>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ ثبت‌نام</label>
                    <div class="sc-event-regs-date-range">
                        <input type="text"
                               id="filter_date_from_shamsi"
                               name="filter_date_from_shamsi"
                               class="sc-filter-control persian-date-input sc-no-default-date"
                               value="<?php echo esc_attr($display_date_from_shamsi_reg); ?>"
                               readonly>
                        <input type="text"
                               id="filter_date_to_shamsi"
                               name="filter_date_to_shamsi"
                               class="sc-filter-control persian-date-input sc-no-default-date"
                               value="<?php echo esc_attr($display_date_to_shamsi_reg); ?>"
                               readonly>
                        <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                        <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                    </div>
                    <p class="sc-filter-help">برای انتخاب بازه تاریخ، روی فیلد کلیک کنید</p>
                </div>

                <div class="sc-filter-field">
                    <label for="filter_free" class="sc-filter-label">ثبت‌نام رایگان</label>
                    <select name="filter_free" id="filter_free" class="sc-filter-control">
                        <option value="0" <?php selected($filter_free, 0); ?>>همه</option>
                        <option value="1" <?php selected($filter_free, 1); ?>>فقط رایگان</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label for="filter_user_type" class="sc-filter-label">نوع کاربر</label>
                    <select name="filter_user_type" id="filter_user_type" class="sc-filter-control">
                        <option value="all" <?php selected($filter_user_type, 'all'); ?>>همه کاربران</option>
                        <option value="member" <?php selected($filter_user_type, 'member'); ?>>کاربر سایت</option>
                        <option value="guest" <?php selected($filter_user_type, 'guest'); ?>>کاربر مهمان</option>
                    </select>
                </div>
            </div>

            <div class="sc-event-regs-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-event-registrations')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-event-regs-list-table-card">
        <div class="sc-event-regs-list-summary">
            <span><?php echo (int) $total_items; ?> ثبت‌نام</span>
        </div>
    <?php if (!empty($registrations)) : ?>
        <div class="sc-event-regs-table-scroll">
        <table class="wp-list-table widefat striped sc-event-regs-table">
            <thead>
                <tr>
                    <th>ردیف</th>
                    <th>شماره سفارش</th>
                    <th>نام رویداد</th>
                    <th>نام کاربر</th>
                    <th>شماره تماس</th>
                    <th>وضعیت</th>
                    <th>تاریخ ثبت‌نام</th>
                    <th>عملیات</th>
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
                    else{
                        $order_number = "@free" . $registration['id'];
                    }
                    
                  if (isset($registration['event_price']) && floatval($registration['event_price']) === 0) {
    $status = 'free_event';
} else {
    // اگر شماره سفارش ووکامرس موجود نیست، رایگان
    if (empty($registration['woocommerce_order_id'])) {
        $status = 'free_event';
    } else {
        // وضعیت عادی از فیلد status یا ووکامرس
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
            } else {
                // اگر سفارش ووکامرس پیدا نشد، رایگان
                $status = 'free_event';
            }
        }
    }
}
                    
                    $status_labels = [
                        'pending' => ['label' => 'در انتظار پرداخت', 'badge' => 'sc-badge--warning'],
                        'on-hold' => ['label' => 'در حال بررسی', 'badge' => 'sc-badge--soft'],
                        'processing' => ['label' => 'پرداخت شده', 'badge' => 'sc-badge--success'],
                        'completed' => ['label' => 'تایید پرداخت', 'badge' => 'sc-badge--success'],
                        'cancelled' => ['label' => 'لغو شده', 'badge' => 'sc-badge--danger'],
                        'refunded' => ['label' => 'بازگشت شده', 'badge' => 'sc-badge--danger'],
                        'failed' => ['label' => 'ناموفق', 'badge' => 'sc-badge--danger'],
                        'free_event' => ['label' => 'رویداد رایگان', 'badge' => 'sc-badge--success'],
                    ];
                    $status_info = isset($status_labels[$status])
                        ? $status_labels[$status]
                        : ['label' => $status, 'badge' => 'sc-badge--muted'];

                    // تاریخ
                    $created_date = '-';
                    if (!empty($registration['created_at'])) {
                        $date = new DateTime($registration['created_at']);
                        $shamsi_date = gregorian_to_jalali(
                            (int) $date->format('Y'),
                            (int) $date->format('m'),
                            (int) $date->format('d')
                        );
                        $created_date = $shamsi_date[0] . '/' .
                            str_pad((string) $shamsi_date[1], 2, '0', STR_PAD_LEFT) . '/' .
                            str_pad((string) $shamsi_date[2], 2, '0', STR_PAD_LEFT);
                    }

                    // نام کاربر
                    $is_guest_registration = isset($registration['registration_source']) && $registration['registration_source'] === 'guest';
                    $member_name = trim(($registration['first_name'] ?: '') . ' ' . ($registration['last_name'] ?: ''));
                    if ($is_guest_registration) {
                        $guest_name = trim(($registration['guest_first_name'] ?: '') . ' ' . ($registration['guest_last_name'] ?: ''));
                        if (!empty($guest_name)) {
                            $member_name = $guest_name;
                        }
                    }
                    $member_name = $member_name ?: 'کاربر حذف شده';
                    $display_phone = $is_guest_registration ? ($registration['guest_phone'] ?: $registration['player_phone']) : ($registration['player_phone'] ?: '-');

                    $name_parts = preg_split('/\s+/u', $member_name);
                    $initials = '';
                    if (!empty($name_parts[0])) {
                        $initials .= mb_substr($name_parts[0], 0, 1);
                    }
                    if (!empty($name_parts[1])) {
                        $initials .= mb_substr($name_parts[1], 0, 1);
                    }
                    if ($initials === '') {
                        $initials = '؟';
                    }

                    // نام رویداد
                    $event_name = $registration['event_name'] ?: 'رویداد حذف شده';
                    $event_type = isset($registration['event_type']) ? $registration['event_type'] : 'event';
                    $event_type_label = ($event_type === 'competition') ? 'مسابقه' : 'رویداد';

                    $registration_id = (int) $registration['id'];
                    $delete_url = admin_url('admin.php?page=sc-event-registrations&action=delete&registration_id=' . $registration_id . '&_wpnonce=' . wp_create_nonce('sc_delete_registration_' . $registration_id));
                ?>
                <tr>
                    <td data-label="ردیف"><?php echo (int) $row_number; ?></td>
                    <td data-label="شماره سفارش"><strong class="sc-event-regs-order"><?php echo esc_html($order_number); ?></strong></td>
                    <td data-label="نام رویداد">
                        <span class="sc-event-regs-event-cell">
                            <strong class="sc-event-regs-event-name"><?php echo esc_html($event_name); ?></strong>
                            <span class="sc-badge <?php echo $event_type === 'competition' ? 'sc-badge--purple' : 'sc-badge--soft'; ?>"><?php echo esc_html($event_type_label); ?></span>
                        </span>
                    </td>
                    <td data-label="نام کاربر">
                        <span class="sc-member-identity">
                            <span class="sc-event-regs-user-avatar" aria-hidden="true"><?php echo esc_html($initials); ?></span>
                            <span class="sc-member-identity-text">
                                <span class="sc-member-name"><?php echo esc_html($member_name); ?></span>
                                <?php if ($is_guest_registration) : ?>
                                    <span class="sc-member-meta"><span class="sc-badge sc-badge--danger">مهمان</span></span>
                                <?php endif; ?>
                            </span>
                        </span>
                    </td>
                    <td data-label="شماره تماس"><?php echo esc_html($display_phone ?: '-'); ?></td>
                    <td data-label="وضعیت">
                        <span class="sc-badge <?php echo esc_attr($status_info['badge']); ?>">
                            <?php echo esc_html($status_info['label']); ?>
                        </span>
                    </td>
                    <td data-label="تاریخ ثبت‌نام"><?php echo esc_html($created_date); ?></td>
                    <td data-label="عملیات" class="sc-event-regs-actions">
                        <a href="#" class="sc-view-registration-details" data-registration-id="<?php echo esc_attr($registration_id); ?>">مشاهده جزئیات</a>
                        <?php if (!$is_secretary_event_regs) : ?>
                        <a href="<?php echo esc_url($delete_url); ?>"
                           class="sc-event-regs-delete"
                           onclick="return scConfirmInline(event, { type: 'warning', message: 'آیا مطمئن هستید که می‌خواهید این ثبت‌نام را حذف کنید؟' });">حذف</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom sc-event-regs-pagination">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg(['paged' => '%#%']),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <div class="notif_register_event sc-event-regs-empty">
            <p>هیچ ثبت‌نامی یافت نشد.</p>
        </div>
    <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function ($) {
    var $toggle = $('#sc-event-regs-filters-toggle');
    var $panel = $('#sc-event-regs-filters-panel');
    var $card = $toggle.closest('.sc-event-regs-list-filters-card');
    var $label = $toggle.find('.sc-event-regs-list-filters-toggle-label');

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
});
</script>

<!-- Modal برای مشاهده جزئیات -->
<div id="scRegistrationModal" class="sc-modal sc-reg-details-modal" style="display: none !important; visibility: hidden !important;">
    <div class="sc-modal-content sc-reg-details-modal-content">
        <div class="sc-modal-header sc-reg-details-modal-header">
            <h2 class="sc-modal-title">جزئیات ثبت‌نام</h2>
            <span class="sc-modal-close" aria-label="بستن">&times;</span>
        </div>
        <div class="sc-modal-body sc-reg-details-modal-body">
            <div class="sc-modal-loading sc-reg-details-loading">
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


