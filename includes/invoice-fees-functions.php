<?php
/**
 * مالیات / ارزش افزوده و هزینه ثبت‌نام (عضویت)
 */
if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * Settings helpers
 * ------------------------------------------------------------------------- */

function sc_is_tax_fee_enabled() {
    return (int) sc_get_setting('tax_fee_enabled', '0') === 1;
}

function sc_get_tax_fee_mode() {
    $mode = (string) sc_get_setting('tax_fee_mode', 'percent');
    return in_array($mode, ['percent', 'fixed'], true) ? $mode : 'percent';
}

function sc_get_tax_fee_value() {
    return max(0, floatval(sc_get_setting('tax_fee_value', '0')));
}

function sc_get_tax_fee_title() {
    $title = trim((string) sc_get_setting('tax_fee_title', ''));
    return $title !== '' ? $title : 'مالیات و ارزش افزوده';
}

function sc_get_tax_fee_description() {
    return trim((string) sc_get_setting('tax_fee_description', ''));
}

function sc_tax_fee_show_payment_breakdown() {
    return (int) sc_get_setting('tax_fee_show_pay_breakdown', '0') === 1;
}

/**
 * @param WC_Order $order
 * @return array|null
 */
function sc_get_order_pay_price_breakdown($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return null;
    }

    $tax_title = sc_get_tax_fee_title();
    $subtotal = 0.0;
    $discount = 0.0;
    $tax = 0.0;
    $penalty = 0.0;

    foreach ($order->get_items('line_item') as $item) {
        $total = (float) $item->get_total();
        if ($total != 0.0) {
            $subtotal += $total;
        }
    }

    foreach ($order->get_items('fee') as $fee) {
        $name = (string) $fee->get_name();
        $total = (float) $fee->get_total();

        if ($name === $tax_title) {
            $tax += $total;
            continue;
        }
        if (stripos($name, 'جریمه') !== false) {
            $penalty += $total;
            continue;
        }
        if ($total < 0 || stripos($name, 'تخفیف') !== false) {
            $discount += abs($total);
            continue;
        }
        $subtotal += $total;
    }

    $shipping = (float) $order->get_shipping_total();
    $subtotal += $shipping;

    return [
        'subtotal'         => round($subtotal, 2),
        'discount'         => round($discount, 2),
        'tax_amount'       => round($tax, 2),
        'tax_label'        => $tax_title,
        'tax_description'  => sc_get_tax_fee_description(),
        'penalty'          => round($penalty, 2),
        'total'            => round((float) $order->get_total(), 2),
    ];
}

function sc_tax_fee_applies_to($context) {
    if (!sc_is_tax_fee_enabled()) {
        return false;
    }
    $map = [
        'course' => 'tax_fee_apply_course',
        'event'  => 'tax_fee_apply_event',
        'wallet' => 'tax_fee_apply_wallet',
        'shop'   => 'tax_fee_apply_shop',
    ];
    $key = $map[$context] ?? '';
    return $key !== '' && (int) sc_get_setting($key, '0') === 1;
}

function sc_is_registration_fee_enabled() {
    return (int) sc_get_setting('registration_fee_enabled', '0') === 1;
}

function sc_get_registration_fee_amount() {
    return max(0, floatval(sc_get_setting('registration_fee_amount', '0')));
}

function sc_get_registration_fee_title() {
    $title = trim((string) sc_get_setting('registration_fee_title', ''));
    return $title !== '' ? $title : 'هزینه ثبت‌نام (عضویت)';
}

function sc_get_registration_fee_description() {
    return trim((string) sc_get_setting('registration_fee_description', ''));
}

function sc_invoices_support_tax_column() {
    return get_option('sc_invoices_tax_column_added', '0') === '1';
}

function sc_members_support_registration_fee_columns() {
    return get_option('sc_members_registration_fee_columns_added', '0') === '1';
}

/* -------------------------------------------------------------------------
 * Totals
 * ------------------------------------------------------------------------- */

function sc_invoice_get_tax_amount($invoice) {
    if (!$invoice) {
        return 0.0;
    }
    if (sc_invoices_support_tax_column() && isset($invoice->tax_amount)) {
        return max(0, floatval($invoice->tax_amount));
    }
    return 0.0;
}

function sc_invoice_get_total_payable($invoice) {
    if (!$invoice) {
        return 0.0;
    }
    $base = floatval(is_array($invoice) ? ($invoice['amount'] ?? 0) : ($invoice->amount ?? 0));
    $tax = 0.0;
    if (is_array($invoice)) {
        $tax = isset($invoice['tax_amount']) ? max(0, floatval($invoice['tax_amount'])) : 0.0;
        $penalty = floatval($invoice['penalty_amount'] ?? 0);
    } else {
        $tax = sc_invoice_get_tax_amount($invoice);
        $penalty = floatval($invoice->penalty_amount ?? 0);
    }
    return round($base + $tax + $penalty, 2);
}

function sc_invoice_fees_detect_context($invoice = null, $args = []) {
    if (is_object($invoice)) {
        if (!empty($invoice->type) && $invoice->type === 'shop') {
            return 'shop';
        }
        if (!empty($invoice->type) && $invoice->type === 'registration_fee') {
            return 'registration_fee';
        }
        if (!empty($invoice->event_id)) {
            return 'event';
        }
        if (!empty($invoice->course_id) && (int) $invoice->course_id > 0) {
            return 'course';
        }
        $expense = trim((string) ($invoice->expense_name ?? ''));
        if ($expense === 'شارژ کیف پول') {
            return 'wallet';
        }
    }
    if (is_array($args)) {
        if (!empty($args['context'])) {
            return sanitize_key($args['context']);
        }
        if (!empty($args['course_id']) && (int) $args['course_id'] > 0) {
            return 'course';
        }
        if (!empty($args['event_id'])) {
            return 'event';
        }
        if (!empty($args['expense_name']) && trim((string) $args['expense_name']) === 'شارژ کیف پول') {
            return 'wallet';
        }
        if (!empty($args['type']) && $args['type'] === 'shop') {
            return 'shop';
        }
    }
    return 'other';
}

function sc_invoice_fees_calculate_tax($base_amount, $context) {
    $base_amount = max(0, round(floatval($base_amount), 2));
    if ($base_amount <= 0 || !sc_tax_fee_applies_to($context)) {
        return 0.0;
    }
    $mode = sc_get_tax_fee_mode();
    $value = sc_get_tax_fee_value();
    if ($value <= 0) {
        return 0.0;
    }
    if ($mode === 'fixed') {
        return round($value, 2);
    }
    return round($base_amount * ($value / 100), 2);
}

/**
 * @param WC_Order $order
 * @param float    $base_amount
 * @param string   $context
 * @param int      $invoice_id
 * @return float Tax amount added
 */
function sc_invoice_fees_apply_tax_to_order($order, $base_amount, $context, $invoice_id = 0) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return 0.0;
    }
    if ($context === 'registration_fee' || $context === 'other') {
        return 0.0;
    }

    $tax_amount = sc_invoice_fees_calculate_tax($base_amount, $context);
    if ($tax_amount <= 0) {
        return 0.0;
    }

    $fee = new WC_Order_Item_Fee();
    $fee->set_name(sc_get_tax_fee_title());
    $fee->set_amount($tax_amount);
    $fee->set_tax_class('');
    $fee->set_tax_status('none');
    $fee->set_total($tax_amount);
    $order->add_item($fee);

    if ($invoice_id > 0 && sc_invoices_support_tax_column()) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'sc_invoices',
            [
                'tax_amount' => $tax_amount,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => absint($invoice_id)],
            ['%f', '%s'],
            ['%d']
        );
    }

    return $tax_amount;
}

function sc_invoice_fees_update_tax_from_order($invoice_id, $order) {
    $invoice_id = absint($invoice_id);
    if (!$invoice_id || !$order || !is_a($order, 'WC_Order') || !sc_invoices_support_tax_column()) {
        return;
    }
    $tax_title = sc_get_tax_fee_title();
    $tax_amount = 0.0;
    foreach ($order->get_fees() as $fee) {
        if ((string) $fee->get_name() === $tax_title) {
            $tax_amount = round(floatval($fee->get_total()), 2);
            break;
        }
    }
    if ($tax_amount <= 0) {
        return;
    }
    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'sc_invoices',
        [
            'tax_amount' => $tax_amount,
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $invoice_id],
        ['%f', '%s'],
        ['%d']
    );
}

/* -------------------------------------------------------------------------
 * Registration fee
 * ------------------------------------------------------------------------- */

function sc_member_has_paid_registration_fee($member_id) {
    $member_id = absint($member_id);
    if (!$member_id || !sc_is_registration_fee_enabled()) {
        return true;
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT id, registration_fee_paid FROM `$members_table` WHERE id = %d LIMIT 1",
        $member_id
    ));
    if (!$member) {
        return true;
    }
    if (sc_members_support_registration_fee_columns() && (int) ($member->registration_fee_paid ?? 0) === 1) {
        return true;
    }

    $invoices_table = $wpdb->prefix . 'sc_invoices';
    $paid = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `$invoices_table`
         WHERE member_id = %d AND type = 'registration_fee'
         AND status IN ('paid','processing','completed')",
        $member_id
    ));
    if ($paid > 0) {
        sc_mark_registration_fee_paid($member_id);
        return true;
    }
    return false;
}

function sc_mark_registration_fee_paid($member_id, $invoice_id = 0) {
    $member_id = absint($member_id);
    if (!$member_id || !sc_members_support_registration_fee_columns()) {
        return;
    }
    global $wpdb;
    $data = [
        'registration_fee_paid' => 1,
        'updated_at'            => current_time('mysql'),
    ];
    $fmt = ['%d', '%s'];
    if ($invoice_id > 0) {
        $data['registration_fee_invoice_id'] = absint($invoice_id);
        $fmt[] = '%d';
    }
    $wpdb->update(
        $wpdb->prefix . 'sc_members',
        $data,
        ['id' => $member_id],
        $fmt,
        ['%d']
    );
}

function sc_is_registration_fee_gate_active_for_user($player = null) {
    if (!is_user_logged_in() || current_user_can('manage_options')) {
        return false;
    }
    if (!sc_is_registration_fee_enabled() || sc_get_registration_fee_amount() <= 0) {
        return false;
    }
    if (!$player) {
        $player = function_exists('sc_get_current_member_for_account_user')
            ? sc_get_current_member_for_account_user()
            : null;
    }
    if (!$player || empty($player->id)) {
        return false;
    }
    return !sc_member_has_paid_registration_fee((int) $player->id);
}

function sc_is_registration_fee_locked_endpoint($endpoint = '') {
    $allowed = ['customer-logout'];
    return !in_array($endpoint, $allowed, true);
}

/**
 * @param int $member_id
 * @return int Invoice ID or 0
 */
function sc_ensure_registration_fee_invoice($member_id) {
    $member_id = absint($member_id);
    if (!$member_id || !sc_is_registration_fee_enabled()) {
        return 0;
    }
    $amount = sc_get_registration_fee_amount();
    if ($amount <= 0) {
        return 0;
    }
    if (sc_member_has_paid_registration_fee($member_id)) {
        return 0;
    }

    if (!class_exists('WooCommerce')) {
        return 0;
    }

    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';

    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `$invoices_table` WHERE member_id = %d AND type = 'registration_fee' ORDER BY id DESC LIMIT 1",
        $member_id
    ));

    if ($existing) {
        if (in_array($existing->status, ['paid', 'processing', 'completed'], true)) {
            sc_mark_registration_fee_paid($member_id, (int) $existing->id);
            return (int) $existing->id;
        }
        if ($existing->status === 'pending' && !empty($existing->woocommerce_order_id)) {
            return (int) $existing->id;
        }
        if ($existing->status === 'pending' && empty($existing->woocommerce_order_id)) {
            $invoice_id = (int) $existing->id;
        } else {
            $invoice_id = 0;
        }
    } else {
        $invoice_id = 0;
    }

    $title = sc_get_registration_fee_title();
    $desc = sc_get_registration_fee_description();

    if (!$invoice_id) {
        $row = [
            'member_id'            => $member_id,
            'course_id'            => 0,
            'member_course_id'     => null,
            'woocommerce_order_id' => null,
            'amount'               => $amount,
            'expense_name'         => $title,
            'type'                 => 'registration_fee',
            'penalty_amount'       => 0.00,
            'penalty_applied'      => 0,
            'status'               => 'pending',
            'created_at'           => current_time('mysql'),
            'updated_at'           => current_time('mysql'),
        ];
        $fmt = ['%d', '%d', '%d', '%d', '%f', '%s', '%s', '%f', '%d', '%s', '%s', '%s'];
        if ($desc !== '') {
            $row['invoice_description'] = $desc;
            $fmt[] = '%s';
        }
        if (sc_invoices_support_tax_column()) {
            $row['tax_amount'] = 0;
            $fmt[] = '%f';
        }
        $inserted = $wpdb->insert($invoices_table, $row, $fmt);
        if (!$inserted) {
            return 0;
        }
        $invoice_id = (int) $wpdb->insert_id;
    }

    if (!function_exists('sc_create_woocommerce_order_for_invoice')) {
        return $invoice_id;
    }

    $order_result = sc_create_woocommerce_order_for_invoice(
        $invoice_id,
        $member_id,
        0,
        $amount,
        $title
    );

    if (empty($order_result['success']) || empty($order_result['order_id'])) {
        return $invoice_id;
    }

    $wpdb->update(
        $invoices_table,
        [
            'woocommerce_order_id' => (int) $order_result['order_id'],
            'updated_at'           => current_time('mysql'),
        ],
        ['id' => $invoice_id],
        ['%d', '%s'],
        ['%d']
    );

    if (sc_members_support_registration_fee_columns()) {
        $wpdb->update(
            $wpdb->prefix . 'sc_members',
            ['registration_fee_invoice_id' => $invoice_id, 'updated_at' => current_time('mysql')],
            ['id' => $member_id],
            ['%d', '%s'],
            ['%d']
        );
    }

    do_action('sc_invoice_created', $invoice_id);
    return $invoice_id;
}

function sc_get_registration_fee_direct_pay_url($member_id = 0) {
    if (!$member_id) {
        $player = function_exists('sc_get_current_member_for_account_user')
            ? sc_get_current_member_for_account_user()
            : null;
        $member_id = $player ? (int) $player->id : 0;
    }
    if (!$member_id) {
        return '';
    }
    $base = function_exists('wc_get_account_endpoint_url')
        ? wc_get_account_endpoint_url('sc-dashboard')
        : wc_get_page_permalink('myaccount');
    return wp_nonce_url(
        add_query_arg('sc_pay_registration_fee', '1', $base),
        'sc_pay_registration_fee',
        '_wpnonce'
    );
}

/**
 * @deprecated Use sc_get_registration_fee_direct_pay_url()
 */
function sc_get_registration_fee_payment_url($member_id = 0) {
    return sc_get_registration_fee_direct_pay_url($member_id);
}

/**
 * @param WC_Order $order
 * @return string|WP_Error Redirect URL or error
 */
function sc_redirect_order_to_payment_gateway($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return new WP_Error('invalid_order', 'سفارش یافت نشد.');
    }
    if (!$order->needs_payment()) {
        return new WP_Error('already_paid', 'این سفارش قبلاً پرداخت شده است.');
    }
    if (!function_exists('WC') || !WC()->payment_gateways()) {
        return new WP_Error('no_wc', 'WooCommerce فعال نیست.');
    }

    $gateways = WC()->payment_gateways()->get_available_payment_gateways();
    if (empty($gateways)) {
        return new WP_Error('no_gateway', 'درگاه پرداخت فعالی یافت نشد.');
    }

    $skip = ['cod', 'bacs', 'cheque'];
    $candidates = [];
    foreach ($gateways as $id => $gateway) {
        if (in_array($id, $skip, true)) {
            continue;
        }
        if ($id === 'wallet' || stripos($id, 'wallet') !== false) {
            continue;
        }
        $candidates[$id] = $gateway;
    }
    if (empty($candidates)) {
        $candidates = $gateways;
    }

    $default_id = (string) get_option('woocommerce_default_gateway', '');
    $gateway = ($default_id && isset($candidates[$default_id]))
        ? $candidates[$default_id]
        : reset($candidates);

    if (!$gateway) {
        return new WP_Error('no_gateway', 'درگاه پرداخت فعالی یافت نشد.');
    }

    $order->set_payment_method($gateway);
    $order->save();

    // برخی درگاه‌ها payment_method را از POST می‌خوانند
    $_POST['payment_method'] = $gateway->id;

    $result = $gateway->process_payment($order->get_id());
    if (is_array($result) && ($result['result'] ?? '') === 'success' && !empty($result['redirect'])) {
        return (string) $result['redirect'];
    }

    $message = is_array($result) && !empty($result['messages'])
        ? wp_strip_all_tags((string) $result['messages'])
        : 'اتصال به درگاه پرداخت ناموفق بود.';
    return new WP_Error('gateway_failed', $message);
}

add_action('template_redirect', 'sc_handle_registration_fee_direct_pay', 5);
function sc_handle_registration_fee_direct_pay() {
    if (!isset($_GET['sc_pay_registration_fee']) || (string) $_GET['sc_pay_registration_fee'] !== '1') {
        return;
    }

    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(home_url(add_query_arg([]))));
        exit;
    }

    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'sc_pay_registration_fee')) {
        if (function_exists('wc_add_notice')) {
            wc_add_notice('درخواست پرداخت نامعتبر است.', 'error');
        }
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }

    $player = function_exists('sc_get_current_member_for_account_user')
        ? sc_get_current_member_for_account_user()
        : null;
    if (!$player || !sc_is_registration_fee_gate_active_for_user($player)) {
        wp_safe_redirect(function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('sc-dashboard')
            : wc_get_page_permalink('myaccount'));
        exit;
    }

    $invoice_id = sc_ensure_registration_fee_invoice((int) $player->id);
    if (!$invoice_id) {
        if (function_exists('wc_add_notice')) {
            wc_add_notice('خطا در ایجاد فاکتر ثبت‌نام.', 'error');
        }
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }

    global $wpdb;
    $order_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT woocommerce_order_id FROM {$wpdb->prefix}sc_invoices WHERE id = %d LIMIT 1",
        $invoice_id
    ));
    if (!$order_id || !function_exists('wc_get_order')) {
        if (function_exists('wc_add_notice')) {
            wc_add_notice('سفارش پرداخت یافت نشد.', 'error');
        }
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }

    $order = wc_get_order($order_id);
    $redirect = sc_redirect_order_to_payment_gateway($order);
    if (is_wp_error($redirect)) {
        if (function_exists('wc_add_notice')) {
            wc_add_notice($redirect->get_error_message(), 'error');
        }
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }

    wp_redirect($redirect);
    exit;
}

function sc_render_registration_fee_required_message() {
    $amount = sc_get_registration_fee_amount();
    $title = sc_get_registration_fee_title();
    $desc = sc_get_registration_fee_description();
    $pay_url = sc_get_registration_fee_direct_pay_url();
    ?>
    <div class="sc-registration-fee-blocked">
        <div class="sc-registration-fee-blocked__icon" aria-hidden="true">🔒</div>
        <h2><?php echo esc_html($title); ?></h2>
        <?php if ($desc !== '') : ?>
            <p class="sc-registration-fee-blocked__desc"><?php echo esc_html($desc); ?></p>
        <?php else : ?>
            <p class="sc-registration-fee-blocked__desc">برای دسترسی به پنل کاربری، ابتدا هزینه ثبت‌نام را پرداخت کنید.</p>
        <?php endif; ?>
        <p class="sc-registration-fee-blocked__amount">
            مبلغ: <strong><?php echo esc_html(number_format($amount, 0, '.', ',') . ' تومان'); ?></strong>
        </p>
        <?php if ($pay_url) : ?>
            <a href="<?php echo esc_url($pay_url); ?>" class="sc_button button-primary button-large sc-registration-fee-blocked__pay">
                پرداخت مستقیم در درگاه
            </a>
        <?php else : ?>
            <p class="sc-registration-fee-blocked__error">خطا در ایجاد لینک پرداخت. لطفاً با پشتیبانی تماس بگیرید.</p>
        <?php endif; ?>
    </div>
    <?php
}

function sc_render_registration_fee_gate_modal() {
    static $rendered = false;
    if ($rendered) {
        return;
    }

    if (!is_user_logged_in() || current_user_can('manage_options')) {
        return;
    }
    if (isset($_GET['sc_pay_registration_fee']) && (string) $_GET['sc_pay_registration_fee'] === '1') {
        return;
    }
    if (function_exists('is_checkout') && is_checkout()) {
        return;
    }
    if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
        return;
    }

    $on_panel = (function_exists('is_account_page') && is_account_page())
        || (function_exists('sc_is_portal_page') && sc_is_portal_page());
    if (!$on_panel) {
        return;
    }
    if (!sc_is_registration_fee_gate_active_for_user()) {
        return;
    }

    $rendered = true;

    $amount = sc_get_registration_fee_amount();
    $title = sc_get_registration_fee_title();
    $desc = sc_get_registration_fee_description();
    $pay_url = sc_get_registration_fee_direct_pay_url();
    ?>
    <div id="sc-registration-fee-overlay" class="sc-registration-fee-overlay" role="dialog" aria-modal="true" aria-labelledby="sc-registration-fee-title">
        <div class="sc-registration-fee-modal">
            <div class="sc-registration-fee-modal__icon" aria-hidden="true">🔒</div>
            <h2 id="sc-registration-fee-title"><?php echo esc_html($title); ?></h2>
            <?php if ($desc !== '') : ?>
                <p><?php echo esc_html($desc); ?></p>
            <?php else : ?>
                <p>برای استفاده از پنل باشگاه، ابتدا هزینه ثبت‌نام (عضویت) را پرداخت کنید.</p>
            <?php endif; ?>
            <p class="sc-registration-fee-modal__amount">مبلغ قابل پرداخت: <strong><?php echo esc_html(number_format($amount, 0, '.', ',') . ' تومان'); ?></strong></p>
            <?php if ($pay_url) : ?>
                <a href="<?php echo esc_url($pay_url); ?>" class="sc_button button-primary button-large sc-registration-fee-modal__pay">پرداخت مستقیم در درگاه</a>
            <?php endif; ?>
            <p class="sc-registration-fee-modal__hint">پس از پرداخت موفق، دسترسی کامل به پنل برای شما فعال می‌شود.</p>
        </div>
    </div>
    <style>
        .sc-registration-fee-overlay{position:fixed;inset:0;background:rgba(15,23,42,.72);z-index:999999;display:flex;align-items:center;justify-content:center;padding:20px}
        .sc-registration-fee-modal{background:#fff;border-radius:16px;max-width:480px;width:100%;padding:32px 28px;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.25)}
        .sc-registration-fee-modal__icon{font-size:48px;margin-bottom:8px}
        .sc-registration-fee-modal h2{margin:0 0 12px;font-size:22px;color:#111827}
        .sc-registration-fee-modal p{margin:0 0 12px;color:#4b5563;line-height:1.7}
        .sc-registration-fee-modal__amount{font-size:16px;color:#111827}
        .sc-registration-fee-modal__pay{margin-top:8px!important;min-width:220px}
        .sc-registration-fee-modal__hint{font-size:13px;color:#6b7280;margin-top:16px!important}
        body.sc-registration-fee-locked .woocommerce-MyAccount-navigation,
        body.sc-registration-fee-locked .woocommerce-MyAccount-content > *:not(.sc-registration-fee-blocked){opacity:.35;pointer-events:none;user-select:none}
        body.sc-registration-fee-locked .sc-registration-fee-blocked{opacity:1!important;pointer-events:auto!important}
        .sc-registration-fee-blocked{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:32px 24px;text-align:center;margin:20px 0}
        .sc-registration-fee-blocked__icon{font-size:42px;margin-bottom:8px}
        .sc-registration-fee-blocked h2{margin:0 0 10px}
        .sc-registration-fee-blocked__desc{color:#4b5563;line-height:1.7}
        .sc-registration-fee-blocked__amount{margin:16px 0;font-size:16px}
        .sc-registration-fee-blocked__pay{min-width:220px}
        .sc-registration-fee-blocked__error{color:#b91c1c}
    </style>
    <script>
        document.body.classList.add('sc-registration-fee-locked');
    </script>
    <?php
}

add_action('sc_member_created', 'sc_invoice_fees_on_member_created', 20, 1);
function sc_invoice_fees_on_member_created($member_id) {
    // فاکتور و سفارش هزینه ثبت‌نام فقط هنگام کلیک «پرداخت مستقیم در درگاه» ساخته می‌شود.
}

add_action('wp_footer', 'sc_render_registration_fee_gate_modal', 50);

add_action('woocommerce_cart_calculate_fees', 'sc_invoice_fees_add_tax_to_cart', 20, 1);
function sc_invoice_fees_add_tax_to_cart($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    if (!sc_tax_fee_applies_to('shop') || !function_exists('sc_is_sportclub_shop_checkout') || !sc_is_sportclub_shop_checkout()) {
        return;
    }
    if (!$cart || !is_a($cart, 'WC_Cart')) {
        return;
    }
    $base = max(0, round(floatval($cart->get_subtotal()), 2));
    $tax = sc_invoice_fees_calculate_tax($base, 'shop');
    if ($tax <= 0) {
        return;
    }
    $cart->add_fee(sc_get_tax_fee_title(), $tax, false);
}

function sc_is_sportclub_shop_checkout() {
    if (function_exists('is_cart') && is_cart()) {
        return true;
    }
    if (function_exists('is_checkout') && is_checkout()) {
        return true;
    }
    return false;
}

add_action('sc_invoice_paid', 'sc_invoice_fees_on_invoice_paid', 10, 1);
function sc_invoice_fees_on_invoice_paid($invoice_id) {
    global $wpdb;
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sc_invoices WHERE id = %d LIMIT 1",
        absint($invoice_id)
    ));
    if (!$invoice || $invoice->type !== 'registration_fee' || empty($invoice->member_id)) {
        return;
    }
    sc_mark_registration_fee_paid((int) $invoice->member_id, (int) $invoice->id);
}
