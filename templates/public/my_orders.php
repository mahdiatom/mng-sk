<?php
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

$filter_status = isset($filter_status) ? $filter_status : (isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all');
$orders = isset($orders) ? $orders : [];
$current_page = isset($current_page) ? max(1, absint($current_page)) : 1;
$total_pages = isset($total_pages) ? max(1, absint($total_pages)) : 1;
?>

<div class="sc-orders-page sc-account-list-page">
    <div class="sc-invoices-page-header sc-orders-page-header">
        <div class="sc-invoices-page-icon">🛒</div>
        <div>
            <h2 class="sc-invoices-page-title">سفارشات فروشگاه</h2>
            <p class="sc-invoices-page-subtitle">لیست سفارش‌های ثبت‌شده در فروشگاه باشگاه</p>
        </div>
    </div>

    <div class="sc-invoices-filters sc-orders-filters">
        <form method="GET" action="<?php echo esc_url(wc_get_account_endpoint_url('my-orders')); ?>" class="sc-invoices-filter-form">
            <input type="hidden" name="pag" value="1" />
            <div class="sc-invoices-filter-field">
                <label for="filter_status">وضعیت</label>
                <select name="filter_status" id="filter_status" class="sc-invoices-filter-control">
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                    <option value="pending" <?php selected($filter_status, 'pending'); ?>>در انتظار پرداخت</option>
                    <option value="on-hold" <?php selected($filter_status, 'on-hold'); ?>>در حال بررسی</option>
                    <option value="processing" <?php selected($filter_status, 'processing'); ?>>پرداخت شده</option>
                    <option value="completed" <?php selected($filter_status, 'completed'); ?>>تایید پرداخت</option>
                    <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>لغو شده</option>
                    <option value="failed" <?php selected($filter_status, 'failed'); ?>>ناموفق</option>
                    <option value="refunded" <?php selected($filter_status, 'refunded'); ?>>بازگشت شده</option>
                </select>
            </div>
            <div class="sc-invoices-filter-actions">
                <button type="submit" class="button button-primary sc-invoices-filter-submit">اعمال فیلتر</button>
                <?php if ($filter_status !== 'all') : ?>
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('my-orders')); ?>" class="button sc-invoices-filter-reset">پاک کردن</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (empty($orders)) : ?>
        <div class="sc-invoices-empty">
            <?php if ($filter_status !== 'all') : ?>
                سفارشی با این وضعیت یافت نشد.
            <?php else : ?>
                شما هنوز سفارشی ثبت نکرده‌اید.
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="sc-invoices-list sc-orders-list">
            <?php foreach ($orders as $order) :
                $total_amount = (float) $order->total_amount;
                $formatted_price = function_exists('wc_price')
                    ? wc_price($order->total_amount)
                    : number_format((float) $order->total_amount, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';

                $status = function_exists('sc_get_wc_order_status_display')
                    ? sc_get_wc_order_status_display((string) $order->status)
                    : ['label' => 'در انتظار پرداخت', 'class' => 'pending', 'bg' => '#fff3cd', 'color' => '#856404', 'icon' => '⏳'];

                $order_ctx = function_exists('sc_get_order_sportclub_context') && !empty($order->id_order)
                    ? sc_get_order_sportclub_context((int) $order->id_order)
                    : ['item_type' => 'other', 'item_name' => ''];

                $order_number = !empty($order->id_order) ? (string) $order->id_order : '—';
                if (!empty($order->id_order) && function_exists('wc_get_order')) {
                    $wc_order = wc_get_order($order->id_order);
                    if ($wc_order) {
                        $order_number = $wc_order->get_order_number();
                    }
                }

                $item_type = $order_ctx['item_type'] ?? 'other';
                $item_icon = '🛍️';
                $item_section_label = 'محصول';
                if ($item_type === 'course') {
                    $item_icon = !empty($order_ctx['is_private']) ? '🏋️' : '📚';
                    $item_section_label = !empty($order_ctx['is_private']) ? 'کلاس خصوصی' : 'دوره';
                } elseif ($item_type === 'event') {
                    $item_icon = '🎯';
                    $item_section_label = 'رویداد';
                } elseif ($item_type === 'expense') {
                    $item_icon = '💰';
                    $item_section_label = 'هزینه';
                }

                $item_title = '';
                if (!empty($order_ctx['item_name'])) {
                    $item_title = wp_strip_all_tags((string) $order_ctx['item_name']);
                } elseif (!empty($order->products_with_quantity)) {
                    $products_plain = wp_strip_all_tags(str_replace('<br>', '، ', (string) $order->products_with_quantity));
                    $item_title = $products_plain;
                } elseif (!empty($order->payment_method_title)) {
                    $item_title = (string) $order->payment_method_title;
                }

                if (function_exists('sc_shop_order_has_expandable_details')) {
                    $has_expandable = sc_shop_order_has_expandable_details($order, $order_ctx);
                } else {
                    $has_expandable = !empty($order->order_description) || !empty($order_ctx['chapter']) || !empty($order_ctx['coach_name']);
                }

                $payment_url = '';
                $order_object = null;
                $is_order_paid = false;

                if (!empty($order->id_order) && in_array($order->status, ['wc-pending', 'wc-checkout-draft', 'wc-under_review', 'wc-on-hold'], true)) {
                    if (function_exists('wc_get_order')) {
                        $order_object = wc_get_order($order->id_order);
                        if ($order_object) {
                            $is_order_paid = $order_object->is_paid();
                            if (!$is_order_paid && ($order->status === 'wc-pending' || $order->status === 'wc-checkout-draft')) {
                                $payment_url = $order_object->get_checkout_payment_url();
                                if (empty($payment_url)) {
                                    $checkout_page_id = wc_get_page_id('checkout');
                                    if ($checkout_page_id) {
                                        $payment_url = add_query_arg('order-pay', $order->id_order, get_permalink($checkout_page_id));
                                        $payment_url = add_query_arg('key', $order_object->get_order_key(), $payment_url);
                                    } else {
                                        $payment_url = wc_get_endpoint_url('order-pay', $order->id_order, wc_get_page_permalink('checkout'));
                                    }
                                }
                            }
                        }
                    }
                }

                if (empty($payment_url) && !empty($order->id_order) && in_array($order->status, ['wc-pending', 'wc-under_review', 'wc-checkout-draft', 'wc-on-hold'], true)) {
                    if (!$order_object && function_exists('wc_get_order')) {
                        $order_object = wc_get_order($order->id_order);
                        if ($order_object) {
                            $is_order_paid = $order_object->is_paid();
                        }
                    }
                    if ($order_object && !$is_order_paid) {
                        $order_key = $order_object->get_order_key();
                        $checkout_page_id = wc_get_page_id('checkout');
                        if ($checkout_page_id && $order_key) {
                            $payment_url = add_query_arg([
                                'order-pay' => $order->id_order,
                                'key' => $order_key,
                            ], get_permalink($checkout_page_id));
                        }
                    } elseif (!empty($order->id_order)) {
                        $checkout_page_id = wc_get_page_id('checkout');
                        if ($checkout_page_id) {
                            $payment_url = add_query_arg('order-pay', $order->id_order, get_permalink($checkout_page_id));
                        }
                    }
                }
            ?>
                <article class="sc-account-card sc-order-card sc-invoice-card--status-<?php echo esc_attr($status['class']); ?>">
                    <div class="sc-invoice-card-head">
                        <div class="sc-invoice-card-head-main">
                            <div class="sc-invoice-card-number">
                                <span class="sc-invoice-card-number-label">شماره سفارش</span>
                                <strong><?php echo esc_html($order_number); ?></strong>
                            </div>
                            <div class="sc-invoice-card-date">
                                <span class="sc-invoice-card-date-icon">📅</span>
                                <?php echo esc_html(sc_date_shamsi_date_only($order->date_created_gmt)); ?>
                            </div>
                        </div>
                        <span class="sc-invoice-status-badge" style="background-color: <?php echo esc_attr($status['bg']); ?>; color: <?php echo esc_attr($status['color']); ?>;">
                            <span class="sc-invoice-status-icon"><?php echo esc_html($status['icon']); ?></span>
                            <?php echo esc_html($status['label']); ?>
                        </span>
                    </div>

                    <div class="sc-account-card-summary sc-invoice-card-body">
                        <div class="sc-invoice-card-item">
                            <div class="sc-invoice-card-item-head">
                                <span class="sc-invoice-card-item-icon"><?php echo esc_html($item_icon); ?></span>
                                <span class="sc-invoice-card-item-type"><?php echo esc_html($item_section_label); ?></span>
                            </div>
                            <?php if ($item_title !== '') : ?>
                                <h3 class="sc-invoice-card-item-title"><?php echo esc_html(wp_trim_words($item_title, 12, '…')); ?></h3>
                            <?php else : ?>
                                <h3 class="sc-invoice-card-item-title sc-invoice-card-item-title--muted">بدون عنوان</h3>
                            <?php endif; ?>

                            <?php if (!$has_expandable && (!empty($order_ctx['chapter']) || !empty($order_ctx['coach_name']))) : ?>
                                <div class="sc-account-card-chips">
                                    <?php if (!empty($order_ctx['chapter'])) : ?>
                                        <span class="sc-account-card-chip">شعبه: <?php echo esc_html($order_ctx['chapter']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($order_ctx['coach_name'])) : ?>
                                        <span class="sc-account-card-chip">مربی: <?php echo esc_html($order_ctx['coach_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="sc-invoice-card-amount">
                            <div class="sc-invoice-card-amount-label">جمع کل</div>
                            <div class="sc-invoice-card-amount-value"><?php echo wp_kses_post($formatted_price); ?></div>
                        </div>
                    </div>

                    <?php if ($has_expandable) : ?>
                        <div class="sc-account-card-details" id="sc-ord-details-<?php echo esc_attr((string) $order->id_order); ?>" hidden>
                            <?php if (!empty($order->products_with_quantity)) : ?>
                                <div class="sc-order-products-list">
                                    <div class="sc-order-products-label">محصولات:</div>
                                    <div class="sc-order-products-value"><?php echo wp_kses_post($order->products_with_quantity); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($item_type === 'course' || $item_type === 'event') : ?>
                                <div class="sc-invoice-card-details">
                                    <?php
                                    if (function_exists('sc_render_order_item_detail_rows')) {
                                        sc_render_order_item_detail_rows($order_ctx, 'sc-invoice-detail-row', ['show_dates' => false, 'show_price' => false]);
                                    }
                                    ?>
                                </div>
                            <?php elseif (!empty($order_ctx['chapter']) || !empty($order_ctx['coach_name'])) : ?>
                                <div class="sc-invoice-card-meta">
                                    <?php if (!empty($order_ctx['chapter'])) : ?>
                                        <span class="sc-invoice-card-meta-item"><strong>شعبه:</strong> <?php echo esc_html($order_ctx['chapter']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($order_ctx['coach_name'])) : ?>
                                        <span class="sc-invoice-card-meta-item"><strong>مربی:</strong> <?php echo esc_html($order_ctx['coach_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($order->payment_method_title)) : ?>
                                <div class="sc-invoice-card-meta">
                                    <span class="sc-invoice-card-meta-item"><strong>روش پرداخت:</strong> <?php echo esc_html($order->payment_method_title); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($order->order_description)) : ?>
                                <div class="sc-invoice-card-description"><?php echo wp_kses_post(nl2br(esc_html(trim($order->order_description)))); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="sc-account-card-footer">
                        <?php if ($has_expandable) : ?>
                            <button type="button"
                                class="sc_button sc-account-card-toggle"
                                aria-expanded="false"
                                aria-controls="sc-ord-details-<?php echo esc_attr((string) $order->id_order); ?>">
                                مشاهده جزئیات
                            </button>
                        <?php endif; ?>

                        <div class="sc-account-card-actions sc-invoice-card-actions">
                            <?php
                            $action_buttons = [];
                            $pending_like = ['wc-pending', 'wc-under_review', 'wc-checkout-draft', 'wc-on-hold'];

                            if ($payment_url && ($order->status === 'wc-pending' || $order->status === 'wc-checkout-draft')) {
                                $member_id_wallet = isset($player) && !empty($player->id) ? (int) $player->id : 0;
                                $wallet_enabled = $member_id_wallet > 0
                                    && function_exists('sc_can_show_players_wallet')
                                    && sc_can_show_players_wallet();
                                $can_pay_from_wallet = false;

                                if ($wallet_enabled) {
                                    $can_pay_from_wallet = sc_can_pay_amount_from_wallet($member_id_wallet, $total_amount);
                                }

                                if ($can_pay_from_wallet && function_exists('sc_ensure_shop_invoice_for_order')) {
                                    $invoice_id_wallet = sc_ensure_shop_invoice_for_order($order->id_order, $member_id_wallet);
                                    if ($invoice_id_wallet > 0) {
                                        $wallet_pay_url = wp_nonce_url(
                                            add_query_arg([
                                                'pay_from_wallet' => '1',
                                                'invoice_id' => $invoice_id_wallet,
                                                'redirect_to' => 'my-orders',
                                            ], wc_get_account_endpoint_url('my-orders')),
                                            'pay_from_wallet_' . $invoice_id_wallet
                                        );
                                        $wallet_text = sc_get_wallet_payment_button_label($member_id_wallet, $total_amount);
                                        $action_buttons[] = '<a href="' . esc_url($wallet_pay_url) . '" class="woocommerce-button button view sc-order-btn sc-order-btn-wallet sc-account-btn-compact">💰 ' . esc_html($wallet_text) . '</a>';
                                    }
                                }

                                $action_buttons[] = '<a href="' . esc_url($payment_url) . '" class="woocommerce-button button view sc-order-btn sc-order-btn-pay sc-account-btn-compact button-primary">💳 پرداخت</a>';
                            }

                            if (in_array($order->status, ['wc-on-hold', 'wc-under_review'], true) && !empty($order->id_order) && function_exists('wc_get_endpoint_url')) {
                                $action_buttons[] = '<a href="' . esc_url(wc_get_endpoint_url('view-order', $order->id_order)) . '" class="woocommerce-button button view sc-order-btn sc-order-btn-view sc-account-btn-compact button-primary">👁️ مشاهده</a>';
                            } elseif (!empty($order->id_order) && function_exists('wc_get_endpoint_url') && !in_array($order->status, $pending_like, true)) {
                                $action_buttons[] = '<a href="' . esc_url(wc_get_endpoint_url('view-order', $order->id_order)) . '" class="woocommerce-button button view sc-order-btn sc-order-btn-view sc-account-btn-compact button-primary">👁️ مشاهده</a>';
                            }

                            if (in_array($order->status, $pending_like, true)) {
                                $cancel_base_url = wc_get_account_endpoint_url('my-orders');
                                $cancel_args = [
                                    'cancel_order' => '1',
                                    'order_id' => $order->id_order,
                                ];
                                if ($filter_status !== 'all') {
                                    $cancel_args['filter_status'] = $filter_status;
                                }
                                $cancel_url = wp_nonce_url(
                                    add_query_arg($cancel_args, $cancel_base_url),
                                    'cancel_order_' . $order->id_order
                                );
                                $action_buttons[] = '<a href="' . esc_url($cancel_url) . '"
                                    class="woocommerce-button button sc-order-btn sc-order-btn-cancel sc-account-btn-compact"
                                    onclick="return scConfirmInline(event, { type: \'warning\', message: \'آیا مطمئن هستید می‌خواهید این سفارش را حذف کنید؟ ✖ این عملیات غیرقابل برگشت است🗑\' })">
                                     حذف
                                </a>';
                            }

                            if (!empty($action_buttons)) {
                                echo implode('', $action_buttons);
                            } elseif (in_array($order->status, $pending_like, true) && empty($order->id_order)) {
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
                    $sc_orders_pag_base = wc_get_account_endpoint_url('my-orders');
                    if ($filter_status !== '' && $filter_status !== 'all') {
                        $sc_orders_pag_base = add_query_arg('filter_status', $filter_status, $sc_orders_pag_base);
                    }
                    $sc_orders_pag_base = remove_query_arg('pag', $sc_orders_pag_base);
                    $sc_orders_pag_join = (strpos($sc_orders_pag_base, '?') !== false) ? '&' : '?';
                    $sc_orders_pagination_base = esc_url($sc_orders_pag_base) . $sc_orders_pag_join . 'pag=%#%';
                    $page_links = paginate_links([
                        'base' => $sc_orders_pagination_base,
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
        const el = document.querySelector('.sc-orders-page-header');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval);
        }
    }, 100);

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
