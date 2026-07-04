<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// این فایل برای نمایش لیست تراکنش‌های کیف پول است
global $wallet_transactions_list_table;

// دریافت لیست اعضا برای فیلتر
global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id 
     FROM $members_table 
     WHERE is_active = 1 
     ORDER BY last_name, first_name"
);

// تنظیم مقادیر تاریخ و مقدار پیش‌فرض شمسی (امروز) برای فیلتر بازه تاریخ
$filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
$filter_date_to   = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
$filter_member    = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$filter_type      = isset($_GET['filter_type']) ? sanitize_text_field(wp_unslash($_GET['filter_type'])) : 'all';
$filter_status    = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all';
$search_value     = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

$today        = new DateTime();
$today_jalali = gregorian_to_jalali((int) $today->format('Y'), (int) $today->format('m'), (int) $today->format('d'));

$filter_date_from_shamsi_default = '';
$filter_date_to_shamsi_default   = '';

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

$active_filters_count = 0;
if ($filter_member > 0) {
    $active_filters_count++;
}
if ($filter_type !== 'all' && $filter_type !== '') {
    $active_filters_count++;
}
if ($filter_status !== 'all' && $filter_status !== '') {
    $active_filters_count++;
}
if ($filter_date_from !== '') {
    $active_filters_count++;
}
if ($filter_date_to !== '') {
    $active_filters_count++;
}
if ($search_value !== '') {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;

$selected_member_text = 'همه کاربران';
if ($filter_member > 0) {
    foreach ($members as $m) {
        if ((int) $m->id === $filter_member) {
            $selected_member_text = $m->first_name . ' ' . $m->last_name . ' - ' . $m->national_id;
            break;
        }
    }
}

// ساخت URL خروجی اکسل با درنظرگرفتن فیلترهای فعلی
$export_url = admin_url('admin.php?page=sc-wallet&sc_export=excel&export_type=wallet_transactions');
if ($filter_member > 0) {
    $export_url = add_query_arg('filter_member', $filter_member, $export_url);
}
if ($filter_type !== 'all' && $filter_type !== '') {
    $export_url = add_query_arg('filter_type', $filter_type, $export_url);
}
if ($filter_status !== 'all' && $filter_status !== '') {
    $export_url = add_query_arg('filter_status', $filter_status, $export_url);
}
if ($filter_date_from !== '') {
    $export_url = add_query_arg('filter_date_from', $filter_date_from, $export_url);
}
if ($filter_date_to !== '') {
    $export_url = add_query_arg('filter_date_to', $filter_date_to, $export_url);
}
if ($search_value !== '') {
    $export_url = add_query_arg('s', $search_value, $export_url);
}
$export_url = wp_nonce_url($export_url, 'sc_export_excel');
?>

<div class="wrap sc-wallet-list-wrap">
    <div class="sc-wallet-list-header">
        <div class="sc-wallet-list-header-text">
            <h1 class="sc-wallet-list-title">لیست تراکنش‌های کیف پول</h1>
            <p class="sc-wallet-list-desc">مشاهده و فیلتر همه تراکنش‌های شارژ، کاهش و پرداخت کیف پول بازیکنان.</p>
        </div>
        <div class="sc-wallet-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-charge')); ?>" class="sc-wallet-list-add-btn">شارژ کیف پول</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet-deduct')); ?>" class="sc-wallet-list-export-btn">کاهش کیف پول</a>
            <a href="<?php echo esc_url($export_url); ?>" class="sc-wallet-list-export-btn">خروجی Excel</a>
        </div>
    </div>

    <?php if (function_exists('sc_get_wallet_admin_statistics')) :
        $sc_wallet_stats = sc_get_wallet_admin_statistics();
    ?>
        <div class="sc-wallet-list-stats">
            <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--blue">
                <div class="sc-wallet-list-stat-label">کاربران دارای کیف پول</div>
                <div class="sc-wallet-list-stat-value"><?php echo number_format(intval($sc_wallet_stats['users_with_wallet'] ?? 0)); ?></div>
            </div>
            <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--green">
                <div class="sc-wallet-list-stat-label">مجموع موجودی‌ها</div>
                <div class="sc-wallet-list-stat-value"><?php echo esc_html(sc_format_amount_display(floatval($sc_wallet_stats['total_balance'] ?? 0))); ?> <small>تومان</small></div>
            </div>
            <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--purple">
                <div class="sc-wallet-list-stat-label">کل تراکنش‌ها</div>
                <div class="sc-wallet-list-stat-value"><?php echo number_format(intval($sc_wallet_stats['total_transactions'] ?? 0)); ?></div>
            </div>
            <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--red">
                <div class="sc-wallet-list-stat-label">موجودی منفی</div>
                <div class="sc-wallet-list-stat-value"><?php echo number_format(intval($sc_wallet_stats['users_with_negative'] ?? 0)); ?></div>
            </div>
            <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--amber">
                <div class="sc-wallet-list-stat-label">شارژ / پرداخت</div>
                <div class="sc-wallet-list-stat-value sc-wallet-list-stat-value--split">
                    <span class="is-credit">شارژ: <?php echo esc_html(sc_format_amount_display(floatval($sc_wallet_stats['total_charges'] ?? 0))); ?></span>
                    <span class="is-debit">پرداخت: <?php echo esc_html(sc_format_amount_display(floatval($sc_wallet_stats['total_payments'] ?? 0))); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="sc-wallet-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-wallet-list-filters-toolbar">
            <button type="button"
                    class="sc-wallet-list-filters-toggle"
                    id="sc-wallet-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-wallet-filters-panel">
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
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet')); ?>" class="sc-wallet-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="get" action="" class="sc-wallet-list-filters-panel" id="sc-wallet-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-wallet">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label">بازیکن</label>
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
                                <div class="sc-dropdown-option sc-visible"
                                     data-value="0"
                                     data-search="همه کاربران"
                                     onclick="scSelectMemberFilter(this,'0','همه کاربران')">
                                    همه کاربران
                                </div>
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
                    <label class="sc-filter-label" for="filter_type">نوع تراکنش</label>
                    <select name="filter_type" id="filter_type" class="sc-filter-control">
                        <option value="all" <?php selected($filter_type, 'all'); ?>>همه</option>
                        <option value="charge" <?php selected($filter_type, 'charge'); ?>>شارژ</option>
                        <option value="deduct" <?php selected($filter_type, 'deduct'); ?>>کاهش</option>
                        <option value="payment" <?php selected($filter_type, 'payment'); ?>>پرداخت</option>
                        <option value="refund" <?php selected($filter_type, 'refund'); ?>>بازگشت وجه</option>
                        <option value="session_fee" <?php selected($filter_type, 'session_fee'); ?>>کسر جلسه</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_status">وضعیت</label>
                    <select name="filter_status" id="filter_status" class="sc-filter-control">
                        <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                        <option value="completed" <?php selected($filter_status, 'completed'); ?>>تکمیل شده</option>
                        <option value="pending" <?php selected($filter_status, 'pending'); ?>>در انتظار</option>
                        <option value="failed" <?php selected($filter_status, 'failed'); ?>>ناموفق</option>
                        <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>لغو شده</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_date_from_shamsi">از تاریخ</label>
                    <input type="text"
                           name="filter_date_from_shamsi"
                           id="filter_date_from_shamsi"
                           value="<?php echo esc_attr($filter_date_from_shamsi_default); ?>"
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
                           value="<?php echo esc_attr($filter_date_to_shamsi_default); ?>"
                           class="sc-filter-control persian-date-input"
                           placeholder="تا تاریخ"
                           readonly>
                    <input type="hidden"
                           name="filter_date_to"
                           id="filter_date_to"
                           value="<?php echo esc_attr($filter_date_to); ?>">
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="s">جستجو</label>
                    <input type="text" name="s" id="s" class="sc-filter-control" value="<?php echo esc_attr($search_value); ?>" placeholder="جستجو در نام یا توضیحات">
                </div>
            </div>

            <div class="sc-wallet-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-wallet')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-wallet-list-table-card">
        <form method="get">
            <input type="hidden" name="page" value="sc-wallet">
            <?php if ($filter_member > 0) : ?>
                <input type="hidden" name="filter_member" value="<?php echo esc_attr($filter_member); ?>">
            <?php endif; ?>
            <?php if ($filter_type !== 'all' && $filter_type !== '') : ?>
                <input type="hidden" name="filter_type" value="<?php echo esc_attr($filter_type); ?>">
            <?php endif; ?>
            <?php if ($filter_status !== 'all' && $filter_status !== '') : ?>
                <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">
            <?php endif; ?>
            <?php if ($filter_date_from !== '') : ?>
                <input type="hidden" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
            <?php endif; ?>
            <?php if ($filter_date_to !== '') : ?>
                <input type="hidden" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
            <?php endif; ?>
            <?php if ($search_value !== '') : ?>
                <input type="hidden" name="s" value="<?php echo esc_attr($search_value); ?>">
            <?php endif; ?>

            <?php
            if (isset($wallet_transactions_list_table) && $wallet_transactions_list_table instanceof Wallet_Transactions_List_Table) {
                $wallet_transactions_list_table->display();
            }
            ?>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function ($) {
    var $toggle = $('#sc-wallet-filters-toggle');
    var $panel = $('#sc-wallet-filters-panel');
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
});
</script>
