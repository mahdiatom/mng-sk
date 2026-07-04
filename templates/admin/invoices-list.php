<?php
if ( ! defined('ABSPATH') ) exit;
// this is a file for filter invoices in list_invoices.php
global $invoices_list_table;

/* ================= Woo Sync ================= */
if (function_exists('wc_get_order')) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';

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

            if ($current_status === 'under_review') {
                $current_status = 'on-hold';
            } elseif ($current_status === 'paid') {
                $current_status = 'completed';
            }

            if ($wc_status !== $current_status) {
                $update_data = [
                    'status'     => $wc_status,
                    'updated_at' => current_time('mysql')
                ];
                $update_format = ['%s', '%s'];

                if (in_array($wc_status, ['completed', 'processing'])) {
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

/* ================= Filters Data ================= */
global $wpdb;
$courses_table = $wpdb->prefix . 'sc_courses';
$members_table = $wpdb->prefix . 'sc_members';

$courses = $wpdb->get_results(
    "SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title ASC"
);

$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id
     FROM $members_table
     WHERE is_active = 1
     ORDER BY last_name ASC, first_name ASC"
);

$filter_course = isset($_GET['filter_course']) ? absint($_GET['filter_course']) : 0;
$filter_user_type = isset($_GET['filter_user_type']) ? sanitize_text_field($_GET['filter_user_type']) : 'all';
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
$filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
$filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
$filter_date_to_shamsi = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';

if ($filter_date_from_shamsi === '' && $filter_date_to_shamsi === '') {
    $today = new DateTime(current_time('Y-m-d'));
    $jalali = gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('m'), (int) $today->format('d'));
    $today_shamsi = $jalali[0] . '/' . str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($jalali[2], 2, '0', STR_PAD_LEFT);
    $filter_date_from_shamsi = $today_shamsi;
    $filter_date_to_shamsi = $today_shamsi;
}

$selected_member_text = 'همه کاربران';
if ($filter_member > 0) {
    foreach ($members as $m) {
        if ((int) $m->id === $filter_member) {
            $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
            break;
        }
    }
}

$active_filters_count = 0;
if ($filter_course > 0) {
    $active_filters_count++;
}
if ($filter_member > 0) {
    $active_filters_count++;
}
if ($filter_user_type !== 'all') {
    $active_filters_count++;
}
if ($filter_status !== 'all') {
    $active_filters_count++;
}
if ($filter_date_from !== '') {
    $active_filters_count++;
}
if ($filter_date_to !== '') {
    $active_filters_count++;
}
if (!empty($_GET['s'])) {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$export_url = admin_url('admin.php?page=sc-invoices&sc_export=excel&export_type=invoices');
$export_url = add_query_arg('filter_status', $filter_status, $export_url);
$export_url = add_query_arg('filter_course', $filter_course, $export_url);
$export_url = add_query_arg('filter_member', $filter_member, $export_url);
$export_url = add_query_arg('filter_user_type', $filter_user_type, $export_url);
if ($filter_date_from !== '') {
    $export_url = add_query_arg('filter_date_from', $filter_date_from, $export_url);
}
if ($filter_date_to !== '') {
    $export_url = add_query_arg('filter_date_to', $filter_date_to, $export_url);
}
if (!empty($_GET['s'])) {
    $export_url = add_query_arg('s', sanitize_text_field(wp_unslash($_GET['s'])), $export_url);
}
$export_url = wp_nonce_url($export_url, 'sc_export_excel');
?>

<div class="wrap sc-reports-list-wrap">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">لیست صورت حساب‌ها</h1>
            <p class="sc-reports-list-desc">برای ویرایش هر سفارش روی نام کاربر یا شماره سفارش کلیک کنید.</p>
        </div>
        <div class="sc-reports-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-add-invoice')); ?>" class="sc-reports-list-add-btn">ایجاد صورت حساب</a>
            <a href="<?php echo esc_url($export_url); ?>" class="sc-reports-list-export-btn">خروجی Excel</a>
        </div>
    </div>

    <div class="sc-reports-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-reports-list-filters-toolbar">
            <button type="button"
                    class="sc-reports-list-filters-toggle"
                    id="sc-invoices-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-invoices-filters-panel">
                <span class="sc-reports-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-reports-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-reports-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-reports-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-invoices')); ?>" class="sc-reports-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="GET" class="sc-reports-list-filters-panel" id="sc-invoices-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-invoices">
            <div class="sc-filter-grid sc-invoices-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_course">دوره</label>
                    <select name="filter_course" id="filter_course" class="sc-filter-control">
                        <option value="0">همه دوره‌ها</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo esc_attr($course->id); ?>" <?php selected($filter_course, $course->id); ?>>
                                <?php echo esc_html($course->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label">کاربر</label>
                    <div class="sc-searchable-dropdown">
                        <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if ($filter_member) echo 'style="display:none"'; ?>>همه کاربران</span>
                            <span class="sc-dropdown-selected" <?php if (!$filter_member) echo 'style="display:none"'; ?>><?php echo esc_html($selected_member_text); ?></span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option sc-visible" data-value="0" data-search="همه کاربران" onclick="scSelectMemberFilter(this,'0','همه کاربران')">همه کاربران</div>
                                <?php
                                $display_count = 0;
                                $max_display = 10;
                                foreach ($members as $member) :
                                    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
                                    $display_count++;
                                ?>
                                    <div class="sc-dropdown-option <?php echo esc_attr($display_class); ?>"
                                         data-value="<?php echo esc_attr($member->id); ?>"
                                         data-search="<?php echo esc_attr(strtolower($member->first_name . ' ' . $member->last_name . ' ' . $member->national_id)); ?>"
                                         onclick="scSelectMemberFilter(this,'<?php echo esc_js($member->id); ?>','<?php echo esc_js($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>')">
                                        <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_user_type">نوع کاربر</label>
                    <select name="filter_user_type" id="filter_user_type" class="sc-filter-control">
                        <option value="all" <?php selected($filter_user_type, 'all'); ?>>همه کاربران</option>
                        <option value="member" <?php selected($filter_user_type, 'member'); ?>>کاربر سایت</option>
                        <option value="guest" <?php selected($filter_user_type, 'guest'); ?>>کاربر مهمان</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_status">وضعیت پرداخت</label>
                    <select name="filter_status" id="filter_status" class="sc-filter-control">
                        <?php
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
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($filter_status, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label">از تاریخ</label>
                    <input type="text" id="filter_date_from_shamsi" name="filter_date_from_shamsi" class="sc-filter-control persian-date-input" value="<?php echo esc_attr($filter_date_from_shamsi); ?>" readonly>
                    <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label">تا تاریخ</label>
                    <input type="text" id="filter_date_to_shamsi" name="filter_date_to_shamsi" class="sc-filter-control persian-date-input" value="<?php echo esc_attr($filter_date_to_shamsi); ?>" readonly>
                    <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                </div>
            </div>

            <div class="sc-reports-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-invoices')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
                <a href="<?php echo esc_url($export_url); ?>" class="button button_export">خروجی Excel</a>
            </div>
        </form>
    </div>

    <div class="sc-reports-list-table-card">
        <form method="get">
            <input type="hidden" name="page" value="sc-invoices">
            <?php
            foreach (['filter_course', 'filter_member', 'filter_user_type', 'filter_date_from', 'filter_date_to', 'filter_status'] as $f) {
                if (isset($_GET[$f]) && $_GET[$f] !== '' && $_GET[$f] !== 'all' && $_GET[$f] !== '0') {
                    echo '<input type="hidden" name="' . esc_attr($f) . '" value="' . esc_attr(wp_unslash($_GET[$f])) . '">';
                }
            }
            if (isset($invoices_list_table) && $invoices_list_table) {
                $invoices_list_table->search_box('جستجو', 'search_invoice');
                $invoices_list_table->views();
                $invoices_list_table->display();
            }
            ?>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(function ($) {
    var $toggle = $('#sc-invoices-filters-toggle');
    var $panel = $('#sc-invoices-filters-panel');
    var $card = $toggle.closest('.sc-reports-list-filters-card');
    var $label = $toggle.find('.sc-reports-list-filters-toggle-label');
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
