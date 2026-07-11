<?php
/**
 * استعلام موجودی محصول در شعبه‌ها — مدیر و منشی
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('sc_user_can_product_stock_inquiry') || !sc_user_can_product_stock_inquiry()) {
    wp_die(esc_html__('شما به این صفحه دسترسی ندارید.', 'sportclub-manager'), 403);
}

if (function_exists('sc_product_branch_stock_ensure_table')) {
    sc_product_branch_stock_ensure_table();
}

$is_secretary = function_exists('sc_user_is_secretary_only') && sc_user_is_secretary_only();
$scope_label = '';
if ($is_secretary && function_exists('sc_secretary_get_effective_chapters')) {
    $chs = sc_secretary_get_effective_chapters();
    $scope_label = !empty($chs) ? implode('، ', $chs) : '';
}

wp_enqueue_style(
    'sc-product-stock-inquiry',
    SC_ASSETS_URL . 'css/product-stock-inquiry.css',
    ['sc-admin-css'],
    defined('SC_PLUGIN_VERSION') ? SC_PLUGIN_VERSION : '1.0'
);
wp_enqueue_script(
    'sc-product-stock-inquiry',
    SC_ASSETS_URL . 'js/product-stock-inquiry.js',
    ['jquery', 'persian-datepicker-js'],
    defined('SC_PLUGIN_VERSION') ? SC_PLUGIN_VERSION : '1.0',
    true
);
wp_localize_script('sc-product-stock-inquiry', 'scProductStockInquiry', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('sc_product_stock_inquiry'),
    'ordersUrl' => admin_url('admin.php?page=sc_orders'),
    'labels' => [
        'searching' => 'در حال جستجو…',
        'noResults' => 'محصولی یافت نشد. نام یا SKU را بررسی کنید.',
        'minChars' => 'حداقل ۲ کاراکتر تایپ کنید.',
        'loading' => 'در حال دریافت موجودی شعبه‌ها…',
        'noBranches' => 'برای این محصول موجودی شعبه‌ای تعریف نشده است.',
        'outOfStock' => 'ناموجود',
        'inStock' => 'موجود',
        'error' => 'خطا در دریافت اطلاعات. دوباره تلاش کنید.',
        'searchError' => 'خطا در جستجو. دوباره تلاش کنید.',
        'transferOk' => 'انتقال با موفقیت انجام شد.',
        'transferFail' => 'انتقال ناموفق بود.',
        'orderOk' => 'سفارش ثبت شد.',
        'orderFail' => 'ثبت سفارش ناموفق بود.',
        'selectProduct' => 'ابتدا یک محصول انتخاب کنید.',
        'working' => 'در حال انجام…',
    ],
]);
?>
<div class="wrap sc-members-list-wrap sc-pbs-inquiry-wrap">
    <div class="sc-members-list-header">
        <div class="sc-members-list-header-text">
            <h1 class="sc-members-list-title">استعلام موجودی</h1>
            <p class="sc-members-list-desc">
                جستجو، انتقال موجودی بین شعبه‌ها و ثبت سفارش آنی.
                <?php if ($is_secretary && $scope_label !== '') : ?>
                    <span class="sc-pbs-scope-pill">محدوده شما: <?php echo esc_html($scope_label); ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="sc-members-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-warehouse')); ?>" class="sc-pbs-link-btn">انبار</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc_orders')); ?>" class="sc-pbs-link-btn">لیست سفارشات</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-products')); ?>" class="sc-pbs-link-btn">لیست محصولات</a>
        </div>
    </div>

    <div class="sc-pbs-card sc-pbs-search-card">
        <div class="sc-pbs-card-head">
            <h2>جستجوی محصول</h2>
            <p>حداقل ۲ کاراکتر تایپ کنید؛ سپس از لیست روی محصول (یا متغیر) کلیک کنید.</p>
        </div>

        <div class="sc-pbs-search-field">
            <label class="sc-pbs-label" for="sc_pbs_inquiry_q">محصول</label>
            <div class="sc-pbs-search-box">
                <span class="sc-pbs-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
                        <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <input type="search"
                       id="sc_pbs_inquiry_q"
                       class="sc-pbs-input"
                       placeholder="نام محصول یا SKU…"
                       autocomplete="off"
                       spellcheck="false" />
                <span class="sc-pbs-search-spinner" id="sc_pbs_search_spinner" hidden aria-hidden="true"></span>
            </div>
            <div id="sc_pbs_inquiry_selected" class="sc-pbs-selected" hidden></div>
            <div id="sc_pbs_inquiry_results" class="sc-pbs-results" hidden></div>
        </div>
    </div>

    <div id="sc_pbs_inquiry_panel" class="sc-pbs-card sc-pbs-result-card" hidden>
        <div class="sc-pbs-product-head">
            <div class="sc-pbs-thumb" id="sc_pbs_inquiry_thumb"></div>
            <div class="sc-pbs-product-meta">
                <h2 id="sc_pbs_inquiry_name" class="sc-pbs-product-name"></h2>
                <div class="sc-pbs-product-sub" id="sc_pbs_inquiry_sub"></div>
            </div>
            <div class="sc-pbs-product-price" id="sc_pbs_inquiry_price"></div>
        </div>
        <div id="sc_pbs_inquiry_loading" class="sc-pbs-state sc-pbs-state--loading" hidden>
            <span class="sc-pbs-inline-spinner"></span>
            <span>در حال دریافت موجودی شعبه‌ها…</span>
        </div>
        <div id="sc_pbs_inquiry_empty" class="sc-pbs-state" hidden></div>
        <div id="sc_pbs_branches" class="sc-pbs-branches" hidden></div>

        <div id="sc_pbs_actions" class="sc-pbs-actions" hidden>
            <div class="sc-pbs-action-grid">
                <div class="sc-pbs-action-card" id="sc_pbs_transfer_card">
                    <div class="sc-pbs-action-head">
                        <h3>انتقال موجودی</h3>
                        <p>انتقال تعداد مشخص از یک شعبه به شعبه دیگر برای همین محصول/متغیر.</p>
                    </div>
                    <div class="sc-pbs-action-fields">
                        <div class="sc-pbs-field">
                            <label for="sc_pbs_tr_from">از شعبه</label>
                            <select id="sc_pbs_tr_from" class="sc-pbs-select"></select>
                        </div>
                        <div class="sc-pbs-field">
                            <label for="sc_pbs_tr_to">به شعبه</label>
                            <select id="sc_pbs_tr_to" class="sc-pbs-select"></select>
                        </div>
                        <div class="sc-pbs-field sc-pbs-field--sm">
                            <label for="sc_pbs_tr_qty">تعداد</label>
                            <input type="number" id="sc_pbs_tr_qty" class="sc-pbs-input sc-pbs-input--sm" min="1" step="1" value="1" />
                        </div>
                    </div>
                    <div class="sc-pbs-action-footer">
                        <button type="button" class="button button-primary sc-pbs-btn" id="sc_pbs_tr_submit">انتقال موجودی</button>
                        <span class="sc-pbs-action-msg" id="sc_pbs_tr_msg" hidden></span>
                    </div>
                </div>

                <div class="sc-pbs-action-card" id="sc_pbs_order_card">
                    <div class="sc-pbs-action-head">
                        <h3>ثبت سفارش آنی</h3>
                        <p>ثبت سفارش فروشگاه برای مشتری؛ تاریخ سفارش را شمسی انتخاب کنید. در صورت پرداخت‌شده، موجودی کم و فاکتور صادر می‌شود.</p>
                    </div>
                    <div class="sc-pbs-action-fields sc-pbs-action-fields--order">
                        <div class="sc-pbs-field">
                            <label for="sc_pbs_ord_first">نام</label>
                            <input type="text" id="sc_pbs_ord_first" class="sc-pbs-input" autocomplete="off" />
                        </div>
                        <div class="sc-pbs-field">
                            <label for="sc_pbs_ord_last">نام خانوادگی</label>
                            <input type="text" id="sc_pbs_ord_last" class="sc-pbs-input" autocomplete="off" />
                        </div>
                        <div class="sc-pbs-field">
                            <label for="sc_pbs_ord_mobile">موبایل</label>
                            <input type="text" id="sc_pbs_ord_mobile" class="sc-pbs-input" placeholder="09xxxxxxxxx" maxlength="11" dir="ltr" autocomplete="off" />
                        </div>
                        <div class="sc-pbs-field">
                            <label for="sc_pbs_ord_chapter">شعبه فروش</label>
                            <select id="sc_pbs_ord_chapter" class="sc-pbs-select"></select>
                        </div>
                        <div class="sc-pbs-field sc-pbs-field--sm">
                            <label for="sc_pbs_ord_qty">تعداد</label>
                            <input type="number" id="sc_pbs_ord_qty" class="sc-pbs-input sc-pbs-input--sm" min="1" step="1" value="1" />
                        </div>
                        <div class="sc-pbs-field">
                            <label for="sc_pbs_ord_date">تاریخ سفارش</label>
                            <input type="text"
                                   id="sc_pbs_ord_date"
                                   class="sc-pbs-input persian-date-input sc-no-default-date"
                                   value="<?php echo esc_attr(function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only(current_time('mysql')) : ''); ?>"
                                   placeholder="1403/01/01"
                                   autocomplete="off"
                                   readonly />
                        </div>
                        <div class="sc-pbs-field">
                            <label for="sc_pbs_ord_pay">وضعیت پرداخت</label>
                            <select id="sc_pbs_ord_pay" class="sc-pbs-select">
                                <option value="pending">در انتظار پرداخت</option>
                                <option value="processing">پرداخت شده</option>
                                <option value="completed">تایید پرداخت</option>
                            </select>
                        </div>
                    </div>
                    <div class="sc-pbs-action-footer">
                        <button type="button" class="button button-primary sc-pbs-btn" id="sc_pbs_ord_submit">ثبت سفارش</button>
                        <span class="sc-pbs-action-msg" id="sc_pbs_ord_msg" hidden></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="sc_pbs_inquiry_placeholder" class="sc-pbs-card sc-pbs-placeholder-card">
        <div class="sc-pbs-placeholder-inner">
            <div class="sc-pbs-placeholder-icon" aria-hidden="true">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 7h16v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" stroke-width="1.6"/>
                </svg>
            </div>
            <p>محصول را جستجو و انتخاب کنید تا موجودی، انتقال و ثبت سفارش آنی فعال شود.</p>
        </div>
    </div>
</div>
