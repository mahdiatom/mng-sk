<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$decimal_places = 0;
$decimal_separator = '.';
$thousand_separator = ',';

if (function_exists('wc_get_price_decimals')) {
    $decimal_places = wc_get_price_decimals();
}
if (function_exists('wc_get_price_decimal_separator')) {
    $decimal_separator = wc_get_price_decimal_separator();
}
if (function_exists('wc_get_price_thousand_separator')) {
    $thousand_separator = wc_get_price_thousand_separator();
}

$filter_status = isset($filter_status) ? $filter_status : (isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : 'all');
$invoice_search = isset($invoice_search) ? $invoice_search : (isset($_GET['invoice_search']) ? sanitize_text_field(wp_unslash($_GET['invoice_search'])) : '');
$filter_date_from_shamsi = isset($filter_date_from_shamsi) ? $filter_date_from_shamsi : (isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])) : '');
$filter_date_to_shamsi = isset($filter_date_to_shamsi) ? $filter_date_to_shamsi : (isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])) : '');
$sc_inv_today_shamsi = '';
if (function_exists('sc_date_shamsi_date_only')) {
    $sc_inv_today_shamsi = sc_date_shamsi_date_only(current_time('Y-m-d'));
} elseif (function_exists('sc_get_today_shamsi')) {
    $sc_inv_today_shamsi = sc_get_today_shamsi();
}
$display_date_from_shamsi = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $sc_inv_today_shamsi;
$display_date_to_shamsi = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $sc_inv_today_shamsi;
$invoices = isset($invoices) ? $invoices : [];
$current_page = isset($current_page) ? max(1, absint($current_page)) : 1;
$total_pages = isset($total_pages) ? max(1, absint($total_pages)) : 1;
$get_certificate = isset($_GET['sc_phys_cert']) ? $_GET['sc_phys_cert'] : '';
?>

<div class="sc-invoices-page sc-account-list-page">
    <div class="sc-invoices-page-header">
        <div class="sc-invoices-page-icon">💳</div>
        <div>
            <h2 class="sc-invoices-page-title">صورت حساب‌ها</h2>
            <p class="sc-invoices-page-subtitle">لیست پرداخت‌های دوره، رویداد و سایر هزینه‌های شما</p>
        </div>
    </div>

    <?php if ($get_certificate) : ?>
        <div class="woocommerce-message sc-invoices-notice" role="alert" tabindex="-1">
            صورت حساب درخواست فیزیکی گواهینامه شما صادر شد. لطفاً نسبت به پرداخت آن اقدام فرمایید.
        </div>
    <?php endif; ?>

    <div class="sc-invoices-filters">
        <form method="GET" action="<?php echo esc_url(wc_get_account_endpoint_url('sc-invoices')); ?>" class="sc-invoices-filter-form">
            <input type="hidden" name="pag" value="1" />

            <div class="sc-invoices-filter-field">
                <label for="filter_status">وضعیت</label>
                <select name="filter_status" id="filter_status" class="sc-invoices-filter-control">
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                    <option value="pending" <?php selected($filter_status, 'pending'); ?>>در انتظار پرداخت</option>
                    <option value="under_review" <?php selected($filter_status, 'under_review'); ?>>در حال بررسی</option>
                    <option value="processing" <?php selected($filter_status, 'processing'); ?>>پرداخت شده</option>
                    <option value="completed" <?php selected($filter_status, 'completed'); ?>>تایید پرداخت</option>
                    <option value="paid" <?php selected($filter_status, 'paid'); ?>>تایید پرداخت</option>
                    <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>لغو شده</option>
                    <option value="refunded" <?php selected($filter_status, 'refunded'); ?>>بازگشت شده</option>
                    <option value="failed" <?php selected($filter_status, 'failed'); ?>>ناموفق</option>
                </select>
            </div>

            <div class="sc-invoices-filter-field">
                <label for="invoice_search">جستجو</label>
                <input type="search" name="invoice_search" id="invoice_search" class="sc-invoices-filter-control" value="<?php echo esc_attr($invoice_search); ?>" placeholder="نام، شماره سفارش یا توضیحات...">
            </div>

            <div class="sc-invoices-filter-field sc-invoices-filter-field-dates">
                <label>بازه تاریخ</label>
                <div class="sc-invoices-date-range">
                    <input type="text" name="filter_date_from_shamsi" class="persian-date-input sc-no-default-date sc-inv-date-input sc-invoices-filter-control" data-today-shamsi="<?php echo esc_attr($sc_inv_today_shamsi); ?>" value="<?php echo esc_attr($filter_date_from_shamsi); ?>" placeholder="<?php echo esc_attr($display_date_from_shamsi); ?>" readonly autocomplete="off">
                    <span class="sc-invoices-date-sep">تا</span>
                    <input type="text" name="filter_date_to_shamsi" class="persian-date-input sc-no-default-date sc-inv-date-input sc-invoices-filter-control" data-today-shamsi="<?php echo esc_attr($sc_inv_today_shamsi); ?>" value="<?php echo esc_attr($filter_date_to_shamsi); ?>" placeholder="<?php echo esc_attr($display_date_to_shamsi); ?>" readonly autocomplete="off">
                </div>
            </div>

            <div class="sc-invoices-filter-actions">
                <button type="submit" class="button button-primary sc-invoices-filter-submit">اعمال فیلتر</button>
                <?php if ($filter_status !== 'all' || $invoice_search !== '' || $filter_date_from_shamsi !== '' || $filter_date_to_shamsi !== '') : ?>
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('sc-invoices')); ?>" class="button sc-invoices-filter-reset">پاک کردن</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (empty($invoices)) : ?>
        <div class="sc-invoices-empty">
            <?php if ($invoice_search !== '' || $filter_date_from_shamsi !== '' || $filter_date_to_shamsi !== '') : ?>
                موردی با این جستجو/بازه تاریخ یافت نشد.
            <?php elseif ($filter_status !== 'all') : ?>
                صورت حسابی با این وضعیت یافت نشد.
            <?php else : ?>
                شما هنوز صورت حسابی ندارید.
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="sc-invoices-list">
            <?php foreach ($invoices as $invoice) :
                $total_amount = function_exists('sc_invoice_get_total_payable')
                    ? sc_invoice_get_total_payable($invoice)
                    : ((float) $invoice->amount + (float) ($invoice->penalty_amount ?? 0));

                if (function_exists('wc_price')) {
                    $formatted_price = wc_price($invoice->amount);
                    $formatted_total = wc_price($total_amount);
                } else {
                    $formatted_price = number_format((float) $invoice->amount, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                    $formatted_total = number_format($total_amount, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                }

                $penalty_amount = (float) ($invoice->penalty_amount ?? 0);
                $formatted_penalty = '';
                if ($penalty_amount > 0) {
                    $formatted_penalty = function_exists('wc_price')
                        ? wc_price($penalty_amount)
                        : number_format($penalty_amount, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                }

                $status = function_exists('sc_get_invoice_status_display')
                    ? sc_get_invoice_status_display((string) $invoice->status)
                    : ['label' => 'در انتظار پرداخت', 'class' => 'pending', 'bg' => '#fff3cd', 'color' => '#856404', 'icon' => '⏳'];
                $status_label = $status['label'];
                $status_class = $status['class'];
                $status_bg = $status['bg'];
                $status_color = $status['color'];
                $status_icon = $status['icon'];

                $inv_ctx = function_exists('sc_get_invoice_sportclub_context')
                    ? sc_get_invoice_sportclub_context($invoice)
                    : ['item_type' => 'other', 'item_name' => ''];

                $order_number = '#' . $invoice->id;
                if (!empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
                    $order = wc_get_order($invoice->woocommerce_order_id);
                    if ($order) {
                        $order_number = $order->get_order_number();
                    }
                }

                $item_type = $inv_ctx['item_type'] ?? 'other';
                $item_icon = '📄';
                $item_section_label = 'جزئیات';
                if ($item_type === 'course') {
                    $item_icon = !empty($inv_ctx['is_private']) ? '🏋️' : '📚';
                    $item_section_label = !empty($inv_ctx['is_private']) ? 'کلاس خصوصی' : 'دوره';
                } elseif ($item_type === 'event') {
                    $item_icon = '🎯';
                    $item_section_label = 'رویداد / مسابقه';
                } elseif ($item_type === 'expense') {
                    $item_icon = '💰';
                    $item_section_label = 'هزینه';
                } elseif (!empty($invoice->course_title)) {
                    $item_icon = '📚';
                    $item_section_label = 'دوره';
                } elseif (!empty($invoice->event_name)) {
                    $item_icon = '🎯';
                    $item_section_label = 'رویداد / مسابقه';
                } elseif (!empty($invoice->expense_name)) {
                    $item_icon = '💰';
                    $item_section_label = 'هزینه اضافی';
                }

                $item_title = '';
                if (!empty($inv_ctx['item_name'])) {
                    $item_title = (string) $inv_ctx['item_name'];
                } elseif (!empty($invoice->course_title)) {
                    $item_title = (string) $invoice->course_title;
                } elseif (!empty($invoice->event_name)) {
                    $item_title = (string) $invoice->event_name;
                } elseif (!empty($invoice->expense_name)) {
                    $item_title = (string) $invoice->expense_name;
                }

                $payment_url = '';
                $order_object = null;
                $is_order_paid = false;

                if (!empty($invoice->woocommerce_order_id) && in_array($invoice->status, ['pending', 'under_review'], true)) {
                    if (function_exists('wc_get_order')) {
                        $order_object = wc_get_order($invoice->woocommerce_order_id);
                        if ($order_object) {
                            $is_order_paid = $order_object->is_paid();
                            if (!$is_order_paid && $invoice->status === 'pending') {
                                $payment_url = $order_object->get_checkout_payment_url();
                                if (empty($payment_url)) {
                                    $checkout_page_id = wc_get_page_id('checkout');
                                    if ($checkout_page_id) {
                                        $payment_url = add_query_arg('order-pay', $invoice->woocommerce_order_id, get_permalink($checkout_page_id));
                                        $payment_url = add_query_arg('key', $order_object->get_order_key(), $payment_url);
                                    } else {
                                        $payment_url = wc_get_endpoint_url('order-pay', $invoice->woocommerce_order_id, wc_get_page_permalink('checkout'));
                                    }
                                }
                            }
                        }
                    }
                }

                if (empty($payment_url) && !empty($invoice->woocommerce_order_id) && in_array($invoice->status, ['pending', 'under_review'], true)) {
                    if (!$order_object && function_exists('wc_get_order')) {
                        $order_object = wc_get_order($invoice->woocommerce_order_id);
                        if ($order_object) {
                            $is_order_paid = $order_object->is_paid();
                        }
                    }

                    if ($order_object && !$is_order_paid) {
                        $order_key = $order_object->get_order_key();
                        $checkout_page_id = wc_get_page_id('checkout');
                        if ($checkout_page_id && $order_key) {
                            $payment_url = add_query_arg([
                                'order-pay' => $invoice->woocommerce_order_id,
                                'key' => $order_key,
                            ], get_permalink($checkout_page_id));
                        }
                    } elseif (!empty($invoice->woocommerce_order_id)) {
                        $checkout_page_id = wc_get_page_id('checkout');
                        if ($checkout_page_id) {
                            $payment_url = add_query_arg('order-pay', $invoice->woocommerce_order_id, get_permalink($checkout_page_id));
                        }
                    }
                }
                $has_expandable = function_exists('sc_invoice_has_expandable_details')
                    && sc_invoice_has_expandable_details($invoice, $inv_ctx);
            ?>
                <article class="sc-account-card sc-invoice-card sc-invoice-card--status-<?php echo esc_attr($status_class); ?>">
                    <div class="sc-invoice-card-head">
                        <div class="sc-invoice-card-head-main">
                            <div class="sc-invoice-card-number">
                                <span class="sc-invoice-card-number-label">شماره سفارش</span>
                                <strong><?php echo esc_html($order_number); ?></strong>
                            </div>
                            <div class="sc-invoice-card-date">
                                <span class="sc-invoice-card-date-icon">📅</span>
                                <?php echo esc_html(sc_date_shamsi_date_only($invoice->created_at)); ?>
                            </div>
                        </div>
                        <span class="sc-invoice-status-badge" style="background-color: <?php echo esc_attr($status_bg); ?>; color: <?php echo esc_attr($status_color); ?>;">
                            <span class="sc-invoice-status-icon"><?php echo esc_html($status_icon); ?></span>
                            <?php echo esc_html($status_label); ?>
                        </span>
                    </div>

                    <div class="sc-account-card-summary sc-invoice-card-body">
                        <div class="sc-invoice-card-item">
                            <div class="sc-invoice-card-item-head">
                                <span class="sc-invoice-card-item-icon"><?php echo esc_html($item_icon); ?></span>
                                <span class="sc-invoice-card-item-type"><?php echo esc_html($item_section_label); ?></span>
                            </div>

                            <?php if ($item_title !== '') : ?>
                                <h3 class="sc-invoice-card-item-title"><?php echo esc_html($item_title); ?></h3>
                            <?php else : ?>
                                <h3 class="sc-invoice-card-item-title sc-invoice-card-item-title--muted">بدون عنوان</h3>
                            <?php endif; ?>

                            <?php if (!$has_expandable && (!empty($inv_ctx['chapter']) || !empty($inv_ctx['coach_name']))) : ?>
                                <div class="sc-account-card-chips">
                                    <?php if (!empty($inv_ctx['chapter'])) : ?>
                                        <span class="sc-account-card-chip">شعبه: <?php echo esc_html($inv_ctx['chapter']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($inv_ctx['coach_name'])) : ?>
                                        <span class="sc-account-card-chip">مربی: <?php echo esc_html($inv_ctx['coach_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="sc-invoice-card-amount">
                            <div class="sc-invoice-card-amount-label">مبلغ</div>
                            <div class="sc-invoice-card-amount-value"><?php echo wp_kses_post($formatted_price); ?></div>
                            <?php if ($penalty_amount > 0) : ?>
                                <div class="sc-invoice-card-penalty">
                                    <span class="sc-invoice-card-penalty-label">جریمه:</span>
                                    <?php echo wp_kses_post($formatted_penalty); ?>
                                </div>
                                <div class="sc-invoice-card-total">
                                    <span class="sc-invoice-card-total-label">مجموع:</span>
                                    <?php echo wp_kses_post($formatted_total); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($has_expandable) : ?>
                        <div class="sc-account-card-details" id="sc-inv-details-<?php echo esc_attr((string) $invoice->id); ?>" hidden>
                            <?php if ($item_type === 'course' || $item_type === 'event') : ?>
                                <div class="sc-invoice-card-details">
                                    <?php
                                    if (function_exists('sc_render_order_item_detail_rows')) {
                                        sc_render_order_item_detail_rows($inv_ctx, 'sc-invoice-detail-row', ['show_dates' => false, 'show_price' => false]);
                                    }
                                    ?>
                                </div>
                            <?php else : ?>
                                <div class="sc-invoice-card-meta">
                                    <?php if (!empty($inv_ctx['chapter'])) : ?>
                                        <span class="sc-invoice-card-meta-item"><strong>شعبه:</strong> <?php echo esc_html($inv_ctx['chapter']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($inv_ctx['coach_name'])) : ?>
                                        <span class="sc-invoice-card-meta-item"><strong>مربی:</strong> <?php echo esc_html($inv_ctx['coach_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($invoice->expense_name) && !empty($invoice->course_title) && $item_type !== 'expense') : ?>
                                        <span class="sc-invoice-card-meta-item"><strong>هزینه اضافی:</strong> <?php echo esc_html($invoice->expense_name); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($invoice->invoice_description)) : ?>
                                <div class="sc-invoice-card-description"><?php echo wp_kses_post(nl2br(esc_html(trim($invoice->invoice_description)))); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="sc-account-card-footer">
                        <?php if ($has_expandable) : ?>
                            <button type="button"
                                class="sc_button sc-account-card-toggle"
                                aria-expanded="false"
                                aria-controls="sc-inv-details-<?php echo esc_attr((string) $invoice->id); ?>">
                                مشاهده جزئیات
                            </button>
                        <?php endif; ?>

                        <div class="sc-account-card-actions sc-invoice-card-actions">
                        <?php
                        $action_buttons = [];

                        if ($payment_url && $invoice->status === 'pending') {
                            $action_buttons[] = '<a href="' . esc_url($payment_url) . '" class="woocommerce-button button view sc-invoice-btn sc-invoice-btn-pay sc-account-btn-compact">💳 پرداخت</a>';
                        }

                        if (in_array($invoice->status, ['pending', 'under_review'], true) && $invoice->expense_name === 'شارژ کیف پول') {
                            $cancel_base_url = wc_get_account_endpoint_url('sc-invoices');
                            $cancel_args = [
                                'cancel_invoice' => '1',
                                'invoice_id' => $invoice->id,
                            ];
                            if ($filter_status !== 'all') {
                                $cancel_args['filter_status'] = $filter_status;
                            }
                            $cancel_url = wp_nonce_url(
                                add_query_arg($cancel_args, $cancel_base_url),
                                'cancel_invoice_' . $invoice->id
                            );
                            $action_buttons[] = '<a href="' . esc_url($cancel_url) . '"
                                class="woocommerce-button button sc-invoice-btn sc-invoice-btn-cancel sc-account-btn-compact"
                                onclick="return scConfirmInline(event, { type: \'warning\', message: \'آیا مطمئن هستید می‌خواهید این صورت‌حساب را لغو کنید؟\' })">
                                 لغو
                            </a>';
                        }

                        if (!empty($action_buttons)) {
                            echo implode('', $action_buttons);
                        } elseif (in_array($invoice->status, ['pending', 'under_review'], true) && empty($invoice->woocommerce_order_id)) {
                            echo '<span class="sc-invoice-card-waiting">⏳ در انتظار ایجاد سفارش</span>';
                        } else {
                            echo '<span class="sc-invoice-card-no-action">—</span>';
                        }
                        ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom sc_paginate sc-invoices-pagination">
                <div class="tablenav-pages">
                    <?php
                    $sc_inv_pag_base = wc_get_account_endpoint_url('sc-invoices');
                    if ($filter_status !== '' && $filter_status !== 'all') {
                        $sc_inv_pag_base = add_query_arg('filter_status', $filter_status, $sc_inv_pag_base);
                    }
                    if ($invoice_search !== '') {
                        $sc_inv_pag_base = add_query_arg('invoice_search', $invoice_search, $sc_inv_pag_base);
                    }
                    if ($filter_date_from_shamsi !== '') {
                        $sc_inv_pag_base = add_query_arg('filter_date_from_shamsi', $filter_date_from_shamsi, $sc_inv_pag_base);
                    }
                    if ($filter_date_to_shamsi !== '') {
                        $sc_inv_pag_base = add_query_arg('filter_date_to_shamsi', $filter_date_to_shamsi, $sc_inv_pag_base);
                    }
                    $sc_inv_pag_base = remove_query_arg('pag', $sc_inv_pag_base);
                    $sc_inv_pag_join = (strpos($sc_inv_pag_base, '?') !== false) ? '&' : '?';
                    $sc_inv_pagination_base = esc_url($sc_inv_pag_base) . $sc_inv_pag_join . 'pag=%#%';
                    $page_links = paginate_links([
                        'base' => $sc_inv_pagination_base,
                        'format' => '',
                        'type' => 'list',
                        'prev_text' => '< قبلی ',
                        'next_text' => ' بعدی >',
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]);
                    echo $page_links ? $page_links : '';
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-invoices-page-header');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval);
        }
    }, 100);

    document.querySelectorAll('.sc-inv-date-input').forEach(function (input) {
        input.addEventListener('mousedown', function () {
            if (!input.value) {
                var today = input.getAttribute('data-today-shamsi') || '';
                if (today) {
                    input.value = today;
                }
            }
        }, true);
    });

    document.querySelectorAll('.sc-account-card-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var panelId = btn.getAttribute('aria-controls');
            var panel = panelId ? document.getElementById(panelId) : null;
            if (!panel) {
                return;
            }
            var isOpen = !panel.hidden;
            panel.hidden = isOpen;
            btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            btn.textContent = isOpen ? 'مشاهده جزئیات' : 'بستن جزئیات';
            btn.classList.toggle('is-open', !isOpen);
        });
    });
});
</script>
