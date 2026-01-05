<?php
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
?>

<!-- ================= Page Header ================= -->
<div class="wrap">
    <h1 class="wp-heading-inline">صورت حساب‌ها</h1>
    <a href="<?php echo admin_url('admin.php?page=sc-add-invoice'); ?>" class="page-title-action">
        ایجاد صورت حساب
    </a>
</div>

<!-- ================= Filters ================= -->
<div class="wrap sc-filter-wrapper">
<form method="GET" class="sc-filter-form invoice_form">
<input type="hidden" name="page" value="sc-invoices">

<div class="sc-filter-grid">

<!-- دوره -->
<div class="sc-filter-field">
<label class="sc-filter-label" for="filter_course">دوره</label>
<select name="filter_course" id="filter_course" class="sc-filter-control">
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
</div>

<!-- کاربر -->
<div class="sc-filter-field">
<label class="sc-filter-label">کاربر</label>

<div class="sc-searchable-dropdown">
<?php
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$selected_member_text = 'همه کاربران';

if ($filter_member) {
    foreach ($members as $m) {
        if ($m->id == $filter_member) {
            $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
            break;
        }
    }
}
?>

<input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">

<div class="sc-dropdown-toggle">
<span class="sc-dropdown-placeholder" <?php if ($filter_member) echo 'style="display:none"'; ?>>همه کاربران</span>
<span class="sc-dropdown-selected" <?php if (!$filter_member) echo 'style="display:none"'; ?>>
<?php echo esc_html($selected_member_text); ?>
</span>
<span class="sc-dropdown-arrow">▼</span>
</div>

<div class="sc-dropdown-menu">
<div class="sc-dropdown-search">
<input type="text" class="sc-search-input" placeholder="جستجوی نام، نام خانوادگی یا کد ملی...">
</div>

<div class="sc-dropdown-options">
<?php
$display_count = 0;
$max_display = 10;
?>

<div class="sc-dropdown-option sc-visible"
     data-value="0"
     data-search="همه کاربران"
     onclick="scSelectMemberFilter(this,'0','همه کاربران')">
همه کاربران
</div>

<?php foreach ($members as $member) :
    $display_class = ($display_count < $max_display) ? 'sc-visible' : 'sc-hidden';
    $display_count++;
?>
<div class="sc-dropdown-option <?php echo $display_class; ?>"
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

<!-- وضعیت -->
<div class="sc-filter-field">
<label class="sc-filter-label" for="filter_status">وضعیت پرداخت</label>
<select name="filter_status" id="filter_status" class="sc-filter-control">
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
</div>

<!-- تاریخ -->
<div class="sc-filter-field sc-filter-date">
    <label class="sc-filter-label">بازه تاریخ</label>

    <?php
    $filter_date_from        = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
$filter_date_to          = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field($_GET['filter_date_from_shamsi']) : '';
$filter_date_to_shamsi   = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field($_GET['filter_date_to_shamsi']) : '';

if (empty($filter_date_from) && empty($filter_date_to)) {

    $today_gregorian = current_time('Y-m-d');

    $today = new DateTime(current_time('Y-m-d'));
    $jalali = gregorian_to_jalali(
        (int)$today->format('Y'),
        (int)$today->format('m'),
        (int)$today->format('d')
    );

    $today_shamsi = $jalali[0] . '/' .
        str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
        str_pad($jalali[2], 2, '0', STR_PAD_LEFT);

    $filter_date_from        = $today_gregorian;
    $filter_date_to          = $today_gregorian;
    $filter_date_from_shamsi = $today_shamsi;
    $filter_date_to_shamsi   = $today_shamsi;
}

    ?>

    <div class="sc-date-range">
        <input type="text"
               id="filter_date_from_shamsi"
               name="filter_date_from_shamsi"
               class="sc-filter-control persian-date-input"
               value="<?php echo esc_attr($filter_date_from_shamsi); ?>"
               readonly>

        <span class="sc-date-separator">تا</span>

        <input type="text"
               id="filter_date_to_shamsi"
               name="filter_date_to_shamsi"
               class="sc-filter-control persian-date-input"
               value="<?php echo esc_attr($filter_date_to_shamsi); ?>"
               readonly>

        <input type="hidden"
               name="filter_date_from"
               id="filter_date_from"
               value="<?php echo esc_attr($filter_date_from); ?>">

        <input type="hidden"
               name="filter_date_to"
               id="filter_date_to"
               value="<?php echo esc_attr($filter_date_to); ?>">
    </div>

    <p class="sc-filter-help">
        برای انتخاب تاریخ، روی فیلد کلیک کنید
    </p>
</div>

</div>



<div class="sc-filter-actions">
<input type="submit" class="button button-primary" value="اعمال فیلتر">
<a href="<?php echo admin_url('admin.php?page=sc-invoices'); ?>" class="button">پاک کردن فیلترها</a>
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
</div>

</form>
</div>

<!-- ================= Table ================= -->
<?php
echo '<div class="wrap">';
echo '<form method="get">';
echo '<input type="hidden" name="page" value="sc-invoices">';

foreach (['filter_course','filter_member','filter_date_from','filter_date_to','filter_status'] as $f) {
    if (isset($_GET[$f])) {
        echo '<input type="hidden" name="'.$f.'" value="'.esc_attr($_GET[$f]).'">';
    }
}

$invoices_list_table->search_box('جستجو', 'search_invoice');
$invoices_list_table->views();
$invoices_list_table->display();

echo '</form>';
echo '</div>';
