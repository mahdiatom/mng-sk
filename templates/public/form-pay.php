<?php
/**
 * Pay for order form — SportClub custom layout.
 *
 * @see woocommerce/templates/checkout/form-pay.php
 * @package SportClub Manager
 * @version 8.2.0
 */

defined('ABSPATH') || exit;

$order_id = $order->get_id();
$sc_ctx = function_exists('sc_get_order_sportclub_context')
    ? sc_get_order_sportclub_context($order_id)
    : [];
$display_total = function_exists('sc_get_order_formatted_total')
    ? sc_get_order_formatted_total($order, $sc_ctx)
    : $order->get_formatted_order_total();
$totals = $order->get_order_item_totals();
$order_number = $order->get_order_number();
$order_date_shamsi = '';
$created = $order->get_date_created();
if ($created && function_exists('sc_date_shamsi_date_only')) {
    $order_date_shamsi = sc_date_shamsi_date_only($created->date('Y-m-d H:i:s'));
}
$item_type = $sc_ctx['item_type'] ?? 'other';
$section_icon = $item_type === 'event' ? '🎯' : ($item_type === 'course' ? '📚' : '🧾');
$section_title = $item_type === 'event' ? 'اطلاعات رویداد' : ($item_type === 'course' ? 'اطلاعات دوره' : 'خلاصه سفارش');
?>

<div class="sc-order-pay-page">
    <div class="sc-order-pay-hero">
        <div class="sc-order-pay-hero-inner">
            <div class="sc-order-pay-hero-badge">پرداخت امن</div>
            <h1 class="sc-order-pay-hero-title">تکمیل پرداخت سفارش</h1>
            <p class="sc-order-pay-hero-sub">
                سفارش <strong>#<?php echo esc_html($order_number); ?></strong>
                <?php if ($order_date_shamsi !== '') : ?>
                    · <?php echo esc_html($order_date_shamsi); ?>
                <?php endif; ?>
            </p>
            <div class="sc-order-pay-hero-amount">
                <span class="sc-order-pay-hero-amount-label">مبلغ قابل پرداخت</span>
                <span class="sc-order-pay-hero-amount-value"><?php echo wp_kses_post($display_total); ?></span>
            </div>
        </div>
    </div>

    <div class="sc-order-pay-layout">
        <aside class="sc-order-pay-summary">
            <div class="sc-order-pay-card sc-order-pay-card-details">
                <h2 class="sc-order-pay-card-title">
                    <span class="sc-order-pay-card-icon"><?php echo esc_html($section_icon); ?></span>
                    <?php echo esc_html($section_title); ?>
                </h2>

                <?php if ($item_type !== 'other' && !empty($sc_ctx['item_name'])) : ?>
                    <div class="sc-order-pay-item-name">
                        <span class="sc-order-pay-label"><?php echo $item_type === 'event' ? 'نام رویداد' : 'نام دوره'; ?></span>
                        <span class="sc-order-pay-value"><?php echo esc_html($sc_ctx['item_name']); ?></span>
                    </div>
                    <div class="sc-order-pay-detail-rows">
                        <?php
                        if (function_exists('sc_render_order_item_detail_rows')) {
                            sc_render_order_item_detail_rows($sc_ctx, 'sc-order-pay-detail-row');
                        }
                        ?>
                    </div>
                <?php else : ?>
                    <p class="sc-order-pay-empty-note">جزئیات سفارش در بخش پرداخت نمایش داده می‌شود.</p>
                <?php endif; ?>
            </div>

            <div class="sc-order-pay-card sc-order-pay-card-trust">
                <div class="sc-order-pay-trust-item">
                    <span class="sc-order-pay-trust-icon">🔒</span>
                    <span>پرداخت از درگاه‌های معتبر</span>
                </div>
                <div class="sc-order-pay-trust-item">
                    <span class="sc-order-pay-trust-icon">⚡</span>
                    <span>ثبت فوری پس از پرداخت موفق</span>
                </div>
            </div>
        </aside>

        <div class="sc-order-pay-main">
            <div class="sc-order-pay-card sc-order-pay-card-payment">
                <h2 class="sc-order-pay-card-title">
                    <span class="sc-order-pay-card-icon">💳</span>
                    روش پرداخت
                </h2>

                <form id="order_review" method="post" class="sc-order-pay-form">
                    <div class="sc-order-pay-items">
                        <div class="sc-order-pay-items-head">
                            <span>شرح</span>
                            <span>مبلغ</span>
                        </div>
                        <?php if (count($order->get_items()) > 0) : ?>
                            <?php foreach ($order->get_items() as $item_id => $item) : ?>
                                <?php
                                if (!apply_filters('woocommerce_order_item_visible', true, $item)) {
                                    continue;
                                }
                                $line_amount = function_exists('sc_get_order_item_display_amount')
                                    ? sc_get_order_item_display_amount($item, $sc_ctx)
                                    : (float) $item->get_total();
                                ?>
                                <div class="<?php echo esc_attr(apply_filters('woocommerce_order_item_class', 'sc-order-pay-item', $item, $order)); ?>">
                                    <div class="sc-order-pay-item-info">
                                        <div class="sc-order-pay-item-title">
                                            <?php echo wp_kses_post(apply_filters('woocommerce_order_item_name', $item->get_name(), $item, false)); ?>
                                        </div>
                                        <?php
                                        do_action('woocommerce_order_item_meta_start', $item_id, $item, $order, false);
                                        wc_display_item_meta($item);
                                        do_action('woocommerce_order_item_meta_end', $item_id, $item, $order, false);
                                        ?>
                                    </div>
                                    <div class="sc-order-pay-item-price">
                                        <?php echo wp_kses_post(wc_price($line_amount)); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($totals && isset($totals['order_total'])) : ?>
                            <?php $grand_total = $totals['order_total']; ?>
                            <div class="sc-order-pay-totals">
                                <div class="sc-order-pay-total-row sc-order-pay-total-row--grand">
                                    <span><?php echo wp_kses_post($grand_total['label']); ?></span>
                                    <span><?php echo wp_kses_post((float) $order->get_total() <= 0 ? $display_total : $grand_total['value']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php do_action('woocommerce_pay_order_before_payment'); ?>

                    <div id="payment" class="sc-order-pay-gateways">
                        <?php if ($order->needs_payment()) : ?>
                            <ul class="wc_payment_methods payment_methods methods">
                                <?php
                                if (!empty($available_gateways)) {
                                    foreach ($available_gateways as $gateway) {
                                        wc_get_template('checkout/payment-method.php', ['gateway' => $gateway]);
                                    }
                                } else {
                                    echo '<li>';
                                    wc_print_notice(
                                        apply_filters(
                                            'woocommerce_no_available_payment_methods_message',
                                            esc_html__('متأسفانه در حال حاضر روش پرداختی برای شما فعال نیست. لطفاً با پشتیبانی تماس بگیرید.', 'sportclub-manager')
                                        ),
                                        'notice'
                                    );
                                    echo '</li>';
                                }
                                ?>
                            </ul>
                        <?php endif; ?>

                        <div class="form-row sc-order-pay-submit-row">
                            <input type="hidden" name="woocommerce_pay" value="1" />

                            <?php wc_get_template('checkout/terms.php'); ?>

                            <?php do_action('woocommerce_pay_order_before_submit'); ?>

                            <?php
                            echo apply_filters(
                                'woocommerce_pay_order_button_html',
                                '<button type="submit" class="button alt sc-order-pay-submit' . esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : '') . '" id="place_order" value="' . esc_attr($order_button_text) . '" data-value="' . esc_attr($order_button_text) . '">' . esc_html($order_button_text) . '</button>'
                            );
                            ?>

                            <?php do_action('woocommerce_pay_order_after_submit'); ?>

                            <?php wp_nonce_field('woocommerce-pay', 'woocommerce-pay-nonce'); ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
