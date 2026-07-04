<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
global $orders_list_table;

if (empty($orders_list_table) || !is_a($orders_list_table, 'orders_List_Table')) {
    if (!class_exists('WP_List_Table')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }
    require_once SC_TEMPLATES_ADMIN_DIR . 'list_order.php';
    $orders_list_table = new orders_List_Table();
    $orders_list_table->prepare_items();
    $GLOBALS['orders_list_table'] = $orders_list_table;
}

$members_table = $wpdb->prefix . 'sc_members';

$product_cat_terms = [];
if (taxonomy_exists('product_cat')) {
    $product_cat_terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
    if (is_wp_error($product_cat_terms)) {
        $product_cat_terms = [];
    }
}

$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id
     FROM $members_table
     WHERE is_active = 1
     ORDER BY last_name ASC, first_name ASC"
);

$filter_product_cat = function_exists('sc_admin_sc_orders_get_request_scalar_int')
    ? sc_admin_sc_orders_get_request_scalar_int('filter_product_cat', 0)
    : (isset($_GET['filter_product_cat']) ? absint($_GET['filter_product_cat']) : 0);

$filter_member = function_exists('sc_admin_sc_orders_get_request_scalar_int')
    ? sc_admin_sc_orders_get_request_scalar_int('filter_member', 0)
    : (isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0);

$filter_status = function_exists('sc_admin_sc_orders_get_request_scalar_string')
    ? sanitize_text_field(sc_admin_sc_orders_get_request_scalar_string('filter_status', 'all'))
    : (isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all');

if (function_exists('sc_admin_sc_orders_get_request_scalar_string')) {
    $filter_date_from = sanitize_text_field(sc_admin_sc_orders_get_request_scalar_string('filter_date_from', ''));
    $filter_date_to = sanitize_text_field(sc_admin_sc_orders_get_request_scalar_string('filter_date_to', ''));
    $filter_date_from_shamsi = sanitize_text_field(sc_admin_sc_orders_get_request_scalar_string('filter_date_from_shamsi', ''));
    $filter_date_to_shamsi = sanitize_text_field(sc_admin_sc_orders_get_request_scalar_string('filter_date_to_shamsi', ''));
} else {
    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from'])) : '';
    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to'])) : '';
    $filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])) : '';
    $filter_date_to_shamsi = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])) : '';
}

if ($filter_date_from_shamsi === '' && $filter_date_to_shamsi === '') {
    $today = new DateTime(current_time('Y-m-d'));
    if (function_exists('gregorian_to_jalali')) {
        $jalali = gregorian_to_jalali(
            (int) $today->format('Y'),
            (int) $today->format('m'),
            (int) $today->format('d')
        );
        $today_shamsi = $jalali[0] . '/' .
            str_pad((string) $jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
            str_pad((string) $jalali[2], 2, '0', STR_PAD_LEFT);
        $filter_date_from_shamsi = $today_shamsi;
        $filter_date_to_shamsi = $today_shamsi;
    }
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
if ($filter_product_cat > 0) {
    $active_filters_count++;
}
if ($filter_member > 0) {
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
?>

<div class="wrap sc-reports-list-wrap sc-orders-list-wrap">
    <div class="sc-reports-list-header">
        <div class="sc-reports-list-header-text">
            <h1 class="sc-reports-list-title">لیست سفارشات</h1>
            <p class="sc-reports-list-desc">سفارش‌های اعضای ثبت‌شده در باشگاه که دارای آیتم خطی در سفارش هستند. برای ویرایش روی نام کاربر یا شماره سفارش کلیک کنید.</p>
        </div>
    </div>

    <div class="sc-reports-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-reports-list-filters-toolbar">
            <button type="button"
                    class="sc-reports-list-filters-toggle"
                    id="sc-orders-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-orders-filters-panel">
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
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc_orders')); ?>" class="sc-reports-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="GET" class="sc-reports-list-filters-panel" id="sc-orders-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc_orders">
            <div class="sc-filter-grid sc-orders-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_product_cat">دسته محصول</label>
                    <select name="filter_product_cat" id="filter_product_cat" class="sc-filter-control">
                        <option value="0">همه دسته‌ها</option>
                        <?php foreach ($product_cat_terms as $term) :
                            if (!is_object($term) || empty($term->term_id)) {
                                continue;
                            }
                            $depth = 0;
                            if (function_exists('get_ancestors')) {
                                $depth = count(get_ancestors((int) $term->term_id, 'product_cat'));
                            }
                            $pad = $depth > 0 ? str_repeat('— ', $depth) : '';
                        ?>
                            <option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected($filter_product_cat, (int) $term->term_id); ?>>
                                <?php echo esc_html($pad . $term->name); ?>
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
                    <label class="sc-filter-label" for="filter_status">وضعیت پرداخت</label>
                    <select name="filter_status" id="filter_status" class="sc-filter-control">
                        <?php
                        $status_options = [
                            'all' => 'همه وضعیت‌ها',
                            'pending' => 'در انتظار پرداخت',
                            'on-hold' => 'پرداخت شده',
                            'processing' => 'پرداخت شده منتظر ارسال',
                            'completed' => 'ارسال و تایید',
                            'cancelled' => 'لغو شده',
                            'failed' => 'ناموفق',
                            'refunded' => 'بازگشت شده',
                            'penalty' => 'جریمه‌دار (صورت‌حساب)',
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
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc_orders')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-reports-list-table-card">
        <form method="get">
            <input type="hidden" name="page" value="sc_orders">
            <?php
            foreach (['filter_product_cat', 'filter_member', 'filter_date_from', 'filter_date_to', 'filter_date_from_shamsi', 'filter_date_to_shamsi', 'filter_status', 's'] as $f) {
                if (!isset($_GET[$f]) || $_GET[$f] === '') {
                    continue;
                }
                $v = wp_unslash($_GET[$f]);
                if (is_array($v)) {
                    $v = reset($v);
                }
                if ($v === '' || $v === null || !is_scalar($v)) {
                    continue;
                }
                if ($f === 'filter_status' && $v === 'all') {
                    continue;
                }
                if (in_array($f, ['filter_product_cat', 'filter_member'], true) && (int) $v === 0) {
                    continue;
                }
                echo '<input type="hidden" name="' . esc_attr($f) . '" value="' . esc_attr((string) $v) . '">';
            }

            $orders_list_table->search_box('جستجو', 'search_order');
            $orders_list_table->views();
            $orders_list_table->display();
            ?>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(function ($) {
    var $toggle = $('#sc-orders-filters-toggle');
    var $panel = $('#sc-orders-filters-panel');
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
