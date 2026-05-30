<?php
/**
 * Wallet payment for WooCommerce shop cart and checkout.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Apply member billing details to a WooCommerce order.
 */
function sc_apply_member_billing_to_order($order, $member, $user_id = 0) {
    if (!$order || !$member) {
        return false;
    }

    if (!$user_id) {
        $user_id = isset($member->user_id) ? (int) $member->user_id : get_current_user_id();
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $billing_first_name = $member->first_name ? $member->first_name : '';
    $billing_last_name = $member->last_name ? $member->last_name : '';
    $billing_email = $user->user_email ? $user->user_email : '';
    $billing_phone = !empty($member->player_phone) ? $member->player_phone : '';

    if (empty($billing_first_name) || empty($billing_last_name) || empty($billing_email)) {
        return false;
    }

    $order->set_customer_id($user_id);
    $order->set_billing_first_name($billing_first_name);
    $order->set_billing_last_name($billing_last_name);
    $order->set_billing_email($billing_email);

    if ($billing_phone !== '') {
        $order->set_billing_phone($billing_phone);
    }

    $order->set_billing_country('IR');
    $order->set_shipping_first_name($billing_first_name);
    $order->set_shipping_last_name($billing_last_name);
    $order->set_shipping_country('IR');

    return true;
}

/**
 * Build expense name for shop orders.
 */
function sc_get_shop_order_expense_name($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return 'خرید فروشگاه';
    }

    $names = [];
    foreach ($order->get_items('line_item') as $item) {
        $name = trim((string) $item->get_name());
        if ($name !== '') {
            $names[] = $name;
        }
    }

    if (empty($names)) {
        return 'خرید فروشگاه';
    }

    $label = implode('، ', array_slice($names, 0, 3));
    if (count($names) > 3) {
        $label .= ' ...';
    }

    return $label;
}

/**
 * Ensure a shop order has a linked sc_invoices row.
 *
 * @return int Invoice ID or 0.
 */
function sc_ensure_shop_invoice_for_order($order_id, $member_id = null) {
    global $wpdb;

    $order_id = absint($order_id);
    if (!$order_id || !function_exists('wc_get_order')) {
        return 0;
    }

    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $existing_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $invoices_table WHERE woocommerce_order_id = %d LIMIT 1",
        $order_id
    ));
    if ($existing_id > 0) {
        return $existing_id;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return 0;
    }

    $customer_id = (int) $order->get_customer_id();
    if (!$customer_id) {
        return 0;
    }

    if ($member_id === null) {
        $members_table = $wpdb->prefix . 'sc_members';
        $member_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $members_table WHERE user_id = %d LIMIT 1",
            $customer_id
        ));
    } else {
        $member_id = absint($member_id);
    }

    if (!$member_id) {
        return 0;
    }

    $amount = round((float) $order->get_total(), 2);
    if ($amount <= 0) {
        return 0;
    }

    $status = in_array($order->get_status(), ['pending', 'on-hold', 'failed'], true) ? 'pending' : 'pending';
    if ($order->is_paid()) {
        $status = 'paid';
    }

    $invoice_row = [
        'member_id' => $member_id,
        'course_id' => 0,
        'member_course_id' => null,
        'woocommerce_order_id' => $order_id,
        'amount' => $amount,
        'expense_name' => sc_get_shop_order_expense_name($order),
        'type' => 'shop',
        'penalty_amount' => 0.00,
        'penalty_applied' => 0,
        'status' => $status,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ];
    $invoice_fmt = ['%d', '%d', '%d', '%d', '%f', '%s', '%s', '%f', '%d', '%s', '%s', '%s'];

    if (function_exists('sc_invoices_support_discount_columns') && sc_invoices_support_discount_columns()) {
        $invoice_row['subtotal_amount'] = $amount;
        $invoice_row['discount_amount'] = 0;
        $invoice_row['discount_code_id'] = null;
        $invoice_row['discount_code'] = null;
        $invoice_fmt[] = '%f';
        $invoice_fmt[] = '%f';
        $invoice_fmt[] = '%s';
        $invoice_fmt[] = '%s';
    }

    $inserted = $wpdb->insert($invoices_table, $invoice_row, $invoice_fmt);
    if ($inserted === false) {
        return 0;
    }

    $invoice_id = (int) $wpdb->insert_id;
    if ($invoice_id) {
        do_action('sc_invoice_created', $invoice_id);
    }

    return $invoice_id;
}

/**
 * Create a pending WooCommerce order from the current cart.
 *
 * @return WC_Order|WP_Error
 */
function sc_create_shop_order_from_cart() {
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
        return new WP_Error('empty_cart', 'سبد خرید خالی است.');
    }

    if (!is_user_logged_in()) {
        return new WP_Error('not_logged_in', 'لطفاً ابتدا وارد حساب کاربری خود شوید.');
    }

    $player = function_exists('sc_check_user_active_status') ? sc_check_user_active_status() : null;
    if (!$player) {
        return new WP_Error('inactive_member', 'حساب کاربری شما فعال نیست.');
    }

    $user_id = get_current_user_id();
    $order = wc_create_order(['customer_id' => $user_id, 'status' => 'pending']);
    if (is_wp_error($order)) {
        return $order;
    }

    if (!sc_apply_member_billing_to_order($order, $player, $user_id)) {
        wp_delete_post($order->get_id(), true);
        return new WP_Error('incomplete_profile', 'اطلاعات کاربر ناقص است. لطفاً ابتدا اطلاعات خود را تکمیل کنید.');
    }

    $checkout = WC()->checkout();
    $cart = WC()->cart;

    try {
        $order->set_created_via('wallet');
        $order->set_currency(get_woocommerce_currency());
        $order->set_prices_include_tax('yes' === get_option('woocommerce_prices_include_tax'));
        $order->set_cart_hash($cart->get_cart_hash());
        $checkout->set_data_from_cart($order);
    } catch (Exception $e) {
        wp_delete_post($order->get_id(), true);
        return new WP_Error('order_create_failed', 'خطا در ایجاد سفارش از سبد خرید.');
    }

    $order->set_status('pending', 'سفارش فروشگاه');
    $order->save();

    return $order;
}

/**
 * Get cart/checkout total for wallet eligibility.
 */
function sc_get_shop_wallet_pay_amount() {
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
        return 0.0;
    }

    return round((float) WC()->cart->get_total('edit'), 2);
}

/**
 * Resolve wallet pay context and IDs from args / current request.
 */
function sc_resolve_wallet_pay_context($args = []) {
    $args = wp_parse_args($args, [
        'invoice_id' => 0,
        'order_id' => 0,
        'context' => 'auto',
    ]);

    $invoice_id = absint($args['invoice_id']);
    $order_id = absint($args['order_id']);
    $context = sanitize_key($args['context']);

    if (!$invoice_id && isset($_GET['invoice_id'])) {
        $invoice_id = absint($_GET['invoice_id']);
    }

    if (!$order_id) {
        if (isset($_GET['order-pay'])) {
            $order_id = absint($_GET['order-pay']);
        } elseif (get_query_var('order-pay')) {
            $order_id = absint(get_query_var('order-pay'));
        }
    }

    if ($context === 'auto') {
        if ($invoice_id && !$order_id) {
            $context = 'invoice';
        } elseif ($order_id) {
            $context = 'order-pay';
        } elseif (function_exists('is_checkout') && is_checkout() && !is_wc_endpoint_url('order-received')) {
            $context = 'checkout';
        } elseif (function_exists('is_cart') && is_cart()) {
            $context = 'cart';
        } elseif ($invoice_id) {
            $context = 'invoice';
        } else {
            $context = '';
        }
    }

    return [
        'invoice_id' => $invoice_id,
        'order_id' => $order_id,
        'context' => $context,
    ];
}

/**
 * Get invoice row if it belongs to the current member.
 */
function sc_get_wallet_payable_invoice_for_member($invoice_id, $member_id) {
    global $wpdb;

    $invoice_id = absint($invoice_id);
    $member_id = absint($member_id);
    if (!$invoice_id || !$member_id) {
        return null;
    }

    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_invoices WHERE id = %d AND member_id = %d LIMIT 1",
        $invoice_id,
        $member_id
    ));

    if (!$invoice) {
        return null;
    }

    if (!in_array($invoice->status, ['pending', 'under_review'], true)) {
        return null;
    }

    if (!empty($invoice->expense_name) && $invoice->expense_name === 'شارژ کیف پول') {
        return null;
    }

    return $invoice;
}

/**
 * Build wallet payment button HTML.
 *
 * @return string
 */
function sc_get_wallet_pay_button_html($args = []) {
    $args = wp_parse_args($args, [
        'invoice_id' => 0,
        'order_id' => 0,
        'context' => 'auto',
        'redirect_to' => '',
        'show_balance' => true,
        'redirect_url' => '',
    ]);

    if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) {
        return '';
    }

    if (!is_user_logged_in()) {
        return '';
    }

    $player = function_exists('sc_check_user_active_status') ? sc_check_user_active_status() : null;
    if (!$player) {
        return '';
    }

    $resolved = sc_resolve_wallet_pay_context($args);
    $invoice_id = $resolved['invoice_id'];
    $order_id = $resolved['order_id'];
    $context = $resolved['context'];

    if ($context === '') {
        return '';
    }

    $redirect_to = sanitize_key($args['redirect_to']);
    $show_balance = filter_var($args['show_balance'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($show_balance === null) {
        $show_balance = !in_array(strtolower((string) $args['show_balance']), ['0', 'false', 'no'], true);
    }

    $wallet_pay_url = '';
    $amount = 0.0;
    $wrap_class = 'sc-shop-wallet-pay-wrap sc-shop-wallet-pay-wrap--' . esc_attr($context);

    if ($context === 'invoice') {
        $invoice = sc_get_wallet_payable_invoice_for_member($invoice_id, $player->id);
        if (!$invoice) {
            return '';
        }

        $amount = (float) $invoice->amount + (float) ($invoice->penalty_amount ?? 0);
        if ($amount <= 0 || !sc_can_pay_amount_from_wallet($player->id, $amount)) {
            return '';
        }

        $redirect_base = $args['redirect_url'] !== ''
            ? $args['redirect_url']
            : (function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('sc-invoices') : home_url('/'));

        $pay_args = [
            'pay_from_wallet' => '1',
            'invoice_id' => $invoice->id,
        ];
        if ($redirect_to === 'my-orders') {
            $pay_args['redirect_to'] = 'my-orders';
            $redirect_base = wc_get_account_endpoint_url('my-orders');
        }

        $wallet_pay_url = wp_nonce_url(
            add_query_arg($pay_args, $redirect_base),
            'pay_from_wallet_' . $invoice->id
        );
    } elseif ($context === 'order-pay') {
        if (!$order_id || !function_exists('wc_get_order')) {
            return '';
        }

        $order = wc_get_order($order_id);
        if (!$order || (int) $order->get_customer_id() !== get_current_user_id() || $order->is_paid()) {
            return '';
        }

        $amount = round((float) $order->get_total(), 2);
        if ($amount <= 0 || !sc_can_pay_amount_from_wallet($player->id, $amount)) {
            return '';
        }

        $redirect_base = $args['redirect_url'] !== '' ? $args['redirect_url'] : $order->get_checkout_payment_url();
        $wallet_pay_url = wp_nonce_url(
            add_query_arg([
                'pay_shop_from_wallet' => '1',
                'context' => 'order-pay',
                'order_id' => $order_id,
            ], $redirect_base),
            'pay_shop_from_wallet'
        );
    } else {
        $amount = sc_get_shop_wallet_pay_amount();
        if ($amount <= 0 || !sc_can_pay_amount_from_wallet($player->id, $amount)) {
            return '';
        }

        $redirect_base = $args['redirect_url'] !== ''
            ? $args['redirect_url']
            : (($context === 'checkout') ? wc_get_checkout_url() : wc_get_cart_url());

        $wallet_pay_url = wp_nonce_url(
            add_query_arg([
                'pay_shop_from_wallet' => '1',
                'context' => $context,
            ], $redirect_base),
            'pay_shop_from_wallet'
        );
    }

    if ($wallet_pay_url === '') {
        return '';
    }

    $wallet_text = sc_get_wallet_payment_button_label($player->id, $amount);
    $balance = sc_get_wallet_balance($player->id);

    ob_start();
    ?>
    <div class="<?php echo esc_attr($wrap_class); ?>">
        <a href="<?php echo esc_url($wallet_pay_url); ?>"
           class="button sc-shop-wallet-pay-btn alt wp-block-button__link">
            💰 <?php echo esc_html($wallet_text); ?>
        </a>
        <?php if ($show_balance) : ?>
            <p class="sc-shop-wallet-pay-note">
                موجودی کیف پول: <?php echo esc_html(number_format($balance, 0, '.', ',')); ?> تومان
            </p>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Shortcode: [sc_wallet_pay]
 *
 * Attributes:
 * - invoice_id  : ID صورت‌حساب
 * - order_id    : ID سفارش ووکامرس (صفحه پرداخت)
 * - context     : auto | cart | checkout | order-pay | invoice
 * - redirect_to : sc-invoices | my-orders
 * - show_balance: yes | no
 */
function sc_wallet_pay_shortcode($atts) {
    $atts = shortcode_atts([
        'invoice_id' => 0,
        'order_id' => 0,
        'context' => 'auto',
        'redirect_to' => '',
        'show_balance' => 'yes',
    ], $atts, 'sc_wallet_pay');

    return sc_get_wallet_pay_button_html([
        'invoice_id' => absint($atts['invoice_id']),
        'order_id' => absint($atts['order_id']),
        'context' => sanitize_key($atts['context']),
        'redirect_to' => sanitize_key($atts['redirect_to']),
        'show_balance' => $atts['show_balance'],
    ]);
}
add_shortcode('sc_wallet_pay', 'sc_wallet_pay_shortcode');

/**
 * Inject wallet button into WooCommerce block cart/checkout.
 */
function sc_wallet_pay_inject_into_wc_blocks($block_content, $block) {
    if (!is_array($block) || empty($block['blockName'])) {
        return $block_content;
    }

    static $cart_injected = false;
    static $checkout_injected = false;

    if ($block['blockName'] === 'woocommerce/proceed-to-checkout-block' && !$cart_injected) {
        $html = sc_get_wallet_pay_button_html(['context' => 'cart']);
        if ($html !== '') {
            $cart_injected = true;
            $block_content .= $html;
        }
    }

    if ($block['blockName'] === 'woocommerce/checkout-payment-block' && !$checkout_injected) {
        $html = sc_get_wallet_pay_button_html(['context' => 'checkout']);
        if ($html !== '') {
            $checkout_injected = true;
            $block_content = $html . $block_content;
        }
    }

    if ($block['blockName'] === 'woocommerce/checkout-actions-block' && !$checkout_injected) {
        $html = sc_get_wallet_pay_button_html(['context' => 'checkout']);
        if ($html !== '') {
            $checkout_injected = true;
            $block_content = $html . $block_content;
        }
    }

    return $block_content;
}
add_filter('render_block', 'sc_wallet_pay_inject_into_wc_blocks', 20, 2);

/**
 * Render wallet payment button on cart/checkout (classic templates).
 */
function sc_render_shop_wallet_payment_button($context = 'cart') {
    echo sc_get_wallet_pay_button_html(['context' => $context]);
}

add_action('woocommerce_proceed_to_checkout', 'sc_render_cart_wallet_payment_button', 25);
function sc_render_cart_wallet_payment_button() {
    sc_render_shop_wallet_payment_button('cart');
}

add_action('woocommerce_review_order_before_payment', 'sc_render_checkout_wallet_payment_button', 15);
function sc_render_checkout_wallet_payment_button() {
    sc_render_shop_wallet_payment_button('checkout');
}

add_action('woocommerce_pay_order_before_submit', 'sc_render_order_pay_wallet_button', 15);
function sc_render_order_pay_wallet_button($order_id) {
    echo sc_get_wallet_pay_button_html([
        'order_id' => absint($order_id),
        'context' => 'order-pay',
    ]);
}

add_action('woocommerce_checkout_order_processed', 'sc_maybe_create_shop_invoice_for_order', 20, 1);
function sc_maybe_create_shop_invoice_for_order($order_id) {
    sc_ensure_shop_invoice_for_order($order_id);
}

/**
 * Redirect helper after wallet payment.
 */
function sc_wallet_payment_redirect_url($endpoint = 'sc-invoices') {
    if ($endpoint === 'my-orders' && function_exists('wc_get_account_endpoint_url')) {
        return wc_get_account_endpoint_url('my-orders');
    }

    return function_exists('wc_get_account_endpoint_url')
        ? wc_get_account_endpoint_url('sc-invoices')
        : home_url('/');
}

/**
 * Handle wallet payment result (shared by invoice and shop flows).
 */
function sc_process_wallet_payment_result($result, $invoice_id, $redirect_endpoint = 'sc-invoices') {
    $redirect_url = sc_wallet_payment_redirect_url($redirect_endpoint);

    if (!$result['success']) {
        wc_add_notice($result['message'], 'error');
        wp_safe_redirect($redirect_url);
        exit;
    }

    if (isset($result['remaining_amount']) && $result['remaining_amount'] > 0) {
        global $wpdb;
        $invoices_table = $wpdb->prefix . 'sc_invoices';
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $invoices_table WHERE id = %d",
            $invoice_id
        ));

        if ($invoice && !empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($invoice->woocommerce_order_id);
            if ($order) {
                $order->calculate_totals();
                $order->save();
                wc_add_notice(
                    'مبلغ ' . number_format($result['paid_amount'], 0, '.', ',') . ' تومان از کیف پول پرداخت شد. مابقی مبلغ: ' . number_format($result['remaining_amount'], 0, '.', ',') . ' تومان',
                    'success'
                );
                wp_safe_redirect($order->get_checkout_payment_url());
                exit;
            }
        }
    }

    wc_add_notice('پرداخت با موفقیت از کیف پول انجام شد.', 'success');

    if ($redirect_endpoint === 'shop' && !empty($invoice_id)) {
        global $wpdb;
        $order_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT woocommerce_order_id FROM {$wpdb->prefix}sc_invoices WHERE id = %d LIMIT 1",
            $invoice_id
        ));
        if ($order_id && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if ($order) {
                wp_safe_redirect($order->get_checkout_order_received_url());
                exit;
            }
        }
    }

    wp_safe_redirect($redirect_url);
    exit;
}

add_action('template_redirect', 'sc_handle_shop_wallet_payment', 5);
function sc_handle_shop_wallet_payment() {
    if (!isset($_GET['pay_shop_from_wallet']) || $_GET['pay_shop_from_wallet'] !== '1') {
        return;
    }

    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'pay_shop_from_wallet')) {
        wc_add_notice('خطا در تأیید درخواست. لطفاً دوباره تلاش کنید.', 'error');
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }

    if (!function_exists('sc_can_show_players_wallet') || !sc_can_show_players_wallet()) {
        wc_add_notice('سیستم کیف پول فعال نیست.', 'error');
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }

    if (!is_user_logged_in()) {
        wc_add_notice('لطفاً ابتدا وارد حساب کاربری خود شوید.', 'error');
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }

    $player = function_exists('sc_check_user_active_status') ? sc_check_user_active_status() : null;
    if (!$player) {
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }

    $context = isset($_GET['context']) ? sanitize_text_field(wp_unslash($_GET['context'])) : 'cart';
    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
    $order = null;

    if ($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || (int) $order->get_customer_id() !== get_current_user_id()) {
            wc_add_notice('سفارش یافت نشد.', 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('my-orders'));
            exit;
        }

        if ($order->is_paid()) {
            wc_add_notice('این سفارش قبلاً پرداخت شده است.', 'error');
            wp_safe_redirect($order->get_view_order_url());
            exit;
        }
    } else {
        $order = sc_create_shop_order_from_cart();
        if (is_wp_error($order)) {
            wc_add_notice($order->get_error_message(), 'error');
            wp_safe_redirect($context === 'checkout' ? wc_get_checkout_url() : wc_get_cart_url());
            exit;
        }

        if (WC()->cart) {
            WC()->cart->empty_cart();
        }
    }

    $invoice_id = sc_ensure_shop_invoice_for_order($order->get_id(), $player->id);
    if (!$invoice_id) {
        wc_add_notice('خطا در ایجاد صورت‌حساب سفارش.', 'error');
        wp_safe_redirect($context === 'checkout' ? wc_get_checkout_url() : wc_get_cart_url());
        exit;
    }

    $result = sc_pay_invoice_from_wallet($invoice_id);
    sc_process_wallet_payment_result($result, $invoice_id, 'shop');
}
