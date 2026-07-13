<?php
/**
 * Sales invoice (فاکتور فروش) — print/PDF export for WooCommerce orders.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Issuer settings for sales invoices (with fallbacks to club about settings).
 *
 * @return array{
 *   name:string,logo:string,phone:string,address:string,
 *   economic_code:string,national_id:string,postal_code:string,footer_note:string
 * }
 */
function sc_sales_invoice_get_issuer_settings() {
    $name = trim((string) sc_get_setting('sc_sales_invoice_issuer_name', ''));
    if ($name === '') {
        $name = trim((string) sc_get_setting('sc_name_club', ''));
    }

    $logo = trim((string) sc_get_setting('sc_sales_invoice_logo_url', ''));
    if ($logo === '') {
        $logo = trim((string) sc_get_setting('sc_club_logo_url', ''));
    }

    $phone = trim((string) sc_get_setting('sc_sales_invoice_phone', ''));
    if ($phone === '') {
        $phone = trim((string) sc_get_setting('sc_phone_club', ''));
    }

    return [
        'name'           => $name !== '' ? $name : 'باشگاه',
        'logo'           => $logo,
        'phone'          => $phone,
        'address'        => trim((string) sc_get_setting('sc_sales_invoice_address', '')),
        'economic_code'  => trim((string) sc_get_setting('sc_sales_invoice_economic_code', '')),
        'national_id'    => trim((string) sc_get_setting('sc_sales_invoice_national_id', '')),
        'postal_code'    => trim((string) sc_get_setting('sc_sales_invoice_postal_code', '')),
        'footer_note'    => trim((string) sc_get_setting('sc_sales_invoice_footer_note', '')),
    ];
}

/**
 * Format money for invoice display (plain number, no HTML).
 *
 * @param float|int|string $amount
 * @return string
 */
function sc_sales_invoice_format_money($amount) {
    $amount = floatval($amount);
    if (function_exists('sc_format_amount_display')) {
        return sc_format_amount_display($amount);
    }
    return number_format(abs($amount), 0, '.', ',');
}

/**
 * Format order date as Shamsi when possible.
 *
 * @param string $mysql_date
 * @return string
 */
function sc_sales_invoice_format_date($mysql_date) {
    $mysql_date = trim((string) $mysql_date);
    if ($mysql_date === '' || $mysql_date === '0000-00-00 00:00:00') {
        return '—';
    }
    if (function_exists('sc_date_shamsi')) {
        return sc_date_shamsi($mysql_date, 'Y/m/d H:i');
    }
    return $mysql_date;
}

/**
 * Resolve club member row for a WooCommerce order.
 *
 * @param WC_Order $order
 * @return object|null
 */
function sc_sales_invoice_get_member_for_order($order) {
    if (!$order || !is_object($order)) {
        return null;
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $user_id = (int) $order->get_user_id();

    if ($user_id > 0) {
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$members_table} WHERE user_id = %d LIMIT 1",
            $user_id
        ));
        if ($member) {
            return $member;
        }
    }

    $phone = '';
    if (method_exists($order, 'get_billing_phone')) {
        $phone = trim((string) $order->get_billing_phone());
    }
    if ($phone !== '') {
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$members_table} WHERE player_phone = %s OR father_phone = %s OR mother_phone = %s LIMIT 1",
            $phone,
            $phone,
            $phone
        ));
        if ($member) {
            return $member;
        }
    }

    return null;
}

/**
 * Build buyer info (no address).
 *
 * @param WC_Order   $order
 * @param object|null $member
 * @return array{name:string,phone:string,national_id:string}
 */
function sc_sales_invoice_build_buyer($order, $member = null) {
    $name = '';
    $phone = '';
    $national_id = '';

    if ($member) {
        $name = trim(
            (isset($member->first_name) ? (string) $member->first_name : '') . ' ' .
            (isset($member->last_name) ? (string) $member->last_name : '')
        );
        $phone = isset($member->player_phone) ? trim((string) $member->player_phone) : '';
        $national_id = isset($member->national_id) ? trim((string) $member->national_id) : '';
    }

    if ($name === '' && $order) {
        $name = trim(
            (string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name()
        );
        if ($name === '') {
            $name = trim((string) $order->get_formatted_billing_full_name());
        }
    }

    if ($phone === '' && $order) {
        $phone = trim((string) $order->get_billing_phone());
    }

    return [
        'name'        => $name !== '' ? $name : '—',
        'phone'       => $phone !== '' ? $phone : '—',
        'national_id' => $national_id !== '' ? $national_id : '—',
    ];
}

/**
 * Build line items + totals from a WC order.
 *
 * @param WC_Order $order
 * @return array{lines:array<int,array>,subtotal:float,discount:float,fees:float,shipping:float,tax:float,total:float,currency:string}
 */
function sc_sales_invoice_build_lines_from_order($order) {
    $lines = [];
    $subtotal = 0.0;

    if ($order && method_exists($order, 'get_items')) {
        foreach ($order->get_items('line_item') as $item) {
            $qty = (float) $item->get_quantity();
            $line_total = (float) $item->get_total();
            $line_subtotal = (float) $item->get_subtotal();
            $unit = $qty > 0 ? ($line_subtotal / $qty) : $line_subtotal;
            $subtotal += $line_subtotal;

            $lines[] = [
                'name'       => (string) $item->get_name(),
                'qty'        => $qty,
                'unit_price' => $unit,
                'total'      => $line_total > 0 ? $line_total : $line_subtotal,
            ];
        }

        foreach ($order->get_items('fee') as $fee) {
            $fee_total = (float) $fee->get_total();
            $lines[] = [
                'name'       => (string) $fee->get_name(),
                'qty'        => 1,
                'unit_price' => $fee_total,
                'total'      => $fee_total,
            ];
        }
    }

    $discount = 0.0;
    $shipping = 0.0;
    $tax = 0.0;
    $fees = 0.0;
    $total = 0.0;
    $currency = '';

    if ($order) {
        $discount = (float) $order->get_discount_total();
        $shipping = (float) $order->get_shipping_total();
        $tax = (float) $order->get_total_tax();
        $total = (float) $order->get_total();
        $currency = (string) $order->get_currency();
        foreach ($order->get_items('fee') as $fee) {
            $fees += (float) $fee->get_total();
        }
    }

    return [
        'lines'     => $lines,
        'subtotal'  => $subtotal,
        'discount'  => $discount,
        'fees'      => $fees,
        'shipping'  => $shipping,
        'tax'       => $tax,
        'total'     => $total,
        'currency'  => $currency,
    ];
}

/**
 * Human-readable order status label.
 *
 * @param WC_Order $order
 * @return string
 */
function sc_sales_invoice_order_status_label($order) {
    if (!$order) {
        return '—';
    }
    $status = $order->get_status();
    if (function_exists('wc_get_order_status_name')) {
        return (string) wc_get_order_status_name($status);
    }
    return $status;
}

/**
 * Build one sales-invoice payload from a WooCommerce order ID.
 *
 * @param int $order_id
 * @return array|null
 */
function sc_sales_invoice_build_from_wc_order($order_id) {
    $order_id = absint($order_id);
    if ($order_id <= 0 || !function_exists('wc_get_order')) {
        return null;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return null;
    }

    $member = sc_sales_invoice_get_member_for_order($order);
    $buyer = sc_sales_invoice_build_buyer($order, $member);
    $money = sc_sales_invoice_build_lines_from_order($order);

    $payment_method = '';
    if (method_exists($order, 'get_payment_method_title')) {
        $payment_method = trim((string) $order->get_payment_method_title());
    }

    $created = method_exists($order, 'get_date_created') && $order->get_date_created()
        ? $order->get_date_created()->date('Y-m-d H:i:s')
        : '';

    return [
        'order_id'       => $order_id,
        'invoice_number' => (string) $order_id,
        'date'           => sc_sales_invoice_format_date($created),
        'status'         => sc_sales_invoice_order_status_label($order),
        'payment_method' => $payment_method !== '' ? $payment_method : '—',
        'buyer'          => $buyer,
        'lines'          => $money['lines'],
        'subtotal'       => $money['subtotal'],
        'discount'       => $money['discount'],
        'shipping'       => $money['shipping'],
        'tax'            => $money['tax'],
        'total'          => $money['total'],
        'sc_invoice_id'  => 0,
        'description'    => '',
    ];
}

/**
 * Build sales-invoice payload from sc_invoices row (uses linked Woo order when available).
 *
 * @param int $invoice_id
 * @return array|null
 */
function sc_sales_invoice_build_from_sc_invoice($invoice_id) {
    $invoice_id = absint($invoice_id);
    if ($invoice_id <= 0) {
        return null;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'sc_invoices';
    $members_table = $wpdb->prefix . 'sc_members';

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT i.*, m.first_name, m.last_name, m.player_phone, m.national_id
         FROM {$table} i
         LEFT JOIN {$members_table} m ON m.id = i.member_id
         WHERE i.id = %d
         LIMIT 1",
        $invoice_id
    ));

    if (!$row) {
        return null;
    }

    $wc_order_id = !empty($row->woocommerce_order_id) ? absint($row->woocommerce_order_id) : 0;
    if ($wc_order_id > 0) {
        $payload = sc_sales_invoice_build_from_wc_order($wc_order_id);
        if ($payload) {
            $payload['sc_invoice_id'] = $invoice_id;
            $payload['invoice_number'] = (string) $invoice_id;
            if (!empty($row->invoice_description)) {
                $payload['description'] = (string) $row->invoice_description;
            } elseif (!empty($row->expense_name)) {
                $payload['description'] = (string) $row->expense_name;
            }
            return $payload;
        }
    }

    $name = trim(
        (isset($row->first_name) ? (string) $row->first_name : '') . ' ' .
        (isset($row->last_name) ? (string) $row->last_name : '')
    );
    $phone = isset($row->player_phone) ? trim((string) $row->player_phone) : '';
    $national_id = isset($row->national_id) ? trim((string) $row->national_id) : '';

    $line_name = '';
    if (!empty($row->expense_name)) {
        $line_name = (string) $row->expense_name;
    } elseif (!empty($row->invoice_description)) {
        $line_name = (string) $row->invoice_description;
    } else {
        $line_name = 'صورت‌حساب #' . $invoice_id;
    }

    $amount = isset($row->amount) ? (float) $row->amount : 0.0;
    $subtotal = isset($row->subtotal_amount) && $row->subtotal_amount !== null && $row->subtotal_amount !== ''
        ? (float) $row->subtotal_amount
        : $amount;
    $discount = isset($row->discount_amount) ? (float) $row->discount_amount : 0.0;
    $tax = isset($row->tax_amount) ? (float) $row->tax_amount : 0.0;
    $penalty = isset($row->penalty_amount) ? (float) $row->penalty_amount : 0.0;

    $lines = [
        [
            'name'       => $line_name,
            'qty'        => 1,
            'unit_price' => $subtotal,
            'total'      => $subtotal,
        ],
    ];
    if ($penalty > 0) {
        $lines[] = [
            'name'       => 'جریمه تأخیر',
            'qty'        => 1,
            'unit_price' => $penalty,
            'total'      => $penalty,
        ];
    }

    return [
        'order_id'       => $wc_order_id,
        'invoice_number' => (string) $invoice_id,
        'date'           => sc_sales_invoice_format_date(isset($row->created_at) ? $row->created_at : ''),
        'status'         => isset($row->status) ? (string) $row->status : '—',
        'payment_method' => '—',
        'buyer'          => [
            'name'        => $name !== '' ? $name : '—',
            'phone'       => $phone !== '' ? $phone : '—',
            'national_id' => $national_id !== '' ? $national_id : '—',
        ],
        'lines'          => $lines,
        'subtotal'       => $subtotal,
        'discount'       => $discount,
        'shipping'       => 0.0,
        'tax'            => $tax,
        'total'          => $amount,
        'sc_invoice_id'  => $invoice_id,
        'description'    => isset($row->invoice_description) ? (string) $row->invoice_description : '',
    ];
}

/**
 * Discard output buffers before sending print HTML.
 */
function sc_sales_invoice_discard_output_buffers() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

/**
 * Capability check for sales invoice export.
 *
 * @return bool
 */
function sc_sales_invoice_user_can_export() {
    return current_user_can('manage_woocommerce')
        || current_user_can('manage_options')
        || current_user_can('edit_shop_orders');
}

/**
 * Render print/PDF page for a list of invoice payloads.
 *
 * @param array<int,array> $invoices
 * @param string           $page_title
 */
function sc_sales_invoice_render_pdf_page(array $invoices, $page_title = 'فاکتور فروش') {
    if (!sc_sales_invoice_user_can_export()) {
        wp_die(esc_html__('شما مجاز به دریافت فاکتور فروش نیستید.', 'sportclub-manager'));
    }

    $issuer = sc_sales_invoice_get_issuer_settings();
    $count = count($invoices);
    $css_url = defined('SC_ASSETS_URL')
        ? SC_ASSETS_URL . 'css/sales-invoice-print.css'
        : '';

    sc_sales_invoice_discard_output_buffers();
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!doctype html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($page_title); ?></title>
        <?php if ($css_url !== '') : ?>
            <link rel="stylesheet" href="<?php echo esc_url($css_url); ?>">
        <?php endif; ?>
    </head>
    <body class="sc-sales-invoice-print-page">
        <div class="sc-si-toolbar">
            <div class="sc-si-toolbar-text">
                <strong><?php echo esc_html($page_title); ?></strong>
                <span><?php echo esc_html($count . ' فاکتور'); ?></span>
            </div>
            <div class="sc-si-toolbar-actions">
                <button type="button" onclick="window.print()">چاپ / PDF</button>
            </div>
        </div>

        <?php if (empty($invoices)) : ?>
            <div class="sc-si-empty">موردی برای صدور فاکتور یافت نشد.</div>
        <?php else : ?>
            <?php foreach ($invoices as $index => $inv) : ?>
                <?php
                $buyer = isset($inv['buyer']) && is_array($inv['buyer']) ? $inv['buyer'] : [];
                $lines = isset($inv['lines']) && is_array($inv['lines']) ? $inv['lines'] : [];
                ?>
                <article class="sc-si-sheet<?php echo $index > 0 ? ' sc-si-sheet-break' : ''; ?>">
                    <header class="sc-si-header">
                        <div class="sc-si-issuer">
                            <?php if (!empty($issuer['logo'])) : ?>
                                <div class="sc-si-logo">
                                    <img src="<?php echo esc_url($issuer['logo']); ?>" alt="">
                                </div>
                            <?php endif; ?>
                            <div class="sc-si-issuer-meta">
                                <h1 class="sc-si-issuer-name"><?php echo esc_html($issuer['name']); ?></h1>
                                <?php if ($issuer['address'] !== '') : ?>
                                    <div class="sc-si-meta-line"><span>آدرس:</span> <?php echo esc_html($issuer['address']); ?></div>
                                <?php endif; ?>
                                <?php if ($issuer['phone'] !== '') : ?>
                                    <div class="sc-si-meta-line"><span>تلفن:</span> <?php echo esc_html($issuer['phone']); ?></div>
                                <?php endif; ?>
                                <?php if ($issuer['economic_code'] !== '') : ?>
                                    <div class="sc-si-meta-line"><span>کد اقتصادی:</span> <?php echo esc_html($issuer['economic_code']); ?></div>
                                <?php endif; ?>
                                <?php if ($issuer['national_id'] !== '') : ?>
                                    <div class="sc-si-meta-line"><span>شناسه ملی:</span> <?php echo esc_html($issuer['national_id']); ?></div>
                                <?php endif; ?>
                                <?php if ($issuer['postal_code'] !== '') : ?>
                                    <div class="sc-si-meta-line"><span>کد پستی:</span> <?php echo esc_html($issuer['postal_code']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="sc-si-doc-title">
                            <div class="sc-si-doc-label">فاکتور فروش</div>
                            <div class="sc-si-doc-number">شماره: <?php echo esc_html((string) ($inv['invoice_number'] ?? '')); ?></div>
                            <div class="sc-si-doc-date">تاریخ: <?php echo esc_html((string) ($inv['date'] ?? '—')); ?></div>
                            <?php if (!empty($inv['order_id'])) : ?>
                                <div class="sc-si-doc-date">سفارش: #<?php echo esc_html((string) $inv['order_id']); ?></div>
                            <?php endif; ?>
                        </div>
                    </header>

                    <section class="sc-si-parties">
                        <div class="sc-si-party sc-si-party-buyer">
                            <h2>خریدار</h2>
                            <div class="sc-si-party-grid">
                                <div><span>نام:</span> <?php echo esc_html((string) ($buyer['name'] ?? '—')); ?></div>
                                <div><span>موبایل:</span> <?php echo esc_html((string) ($buyer['phone'] ?? '—')); ?></div>
                                <div><span>کد ملی:</span> <?php echo esc_html((string) ($buyer['national_id'] ?? '—')); ?></div>
                            </div>
                        </div>
                        <div class="sc-si-party sc-si-party-extra">
                            <h2>اطلاعات سند</h2>
                            <div class="sc-si-party-grid">
                                <div><span>وضعیت:</span> <?php echo esc_html((string) ($inv['status'] ?? '—')); ?></div>
                                <div><span>روش پرداخت:</span> <?php echo esc_html((string) ($inv['payment_method'] ?? '—')); ?></div>
                                <?php if (!empty($inv['description'])) : ?>
                                    <div><span>توضیح:</span> <?php echo esc_html((string) $inv['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <table class="sc-si-items">
                        <thead>
                            <tr>
                                <th class="sc-si-col-row">ردیف</th>
                                <th class="sc-si-col-name">شرح کالا / خدمت</th>
                                <th class="sc-si-col-qty">تعداد</th>
                                <th class="sc-si-col-unit">مبلغ واحد (تومان)</th>
                                <th class="sc-si-col-total">مبلغ کل (تومان)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($lines)) : ?>
                            <tr>
                                <td colspan="5" class="sc-si-empty-row">آیتمی ثبت نشده است.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($lines as $li => $line) : ?>
                                <tr>
                                    <td><?php echo (int) ($li + 1); ?></td>
                                    <td class="sc-si-item-name"><?php echo esc_html((string) ($line['name'] ?? '')); ?></td>
                                    <td><?php echo esc_html(sc_sales_invoice_format_money($line['qty'] ?? 0)); ?></td>
                                    <td><?php echo esc_html(sc_sales_invoice_format_money($line['unit_price'] ?? 0)); ?></td>
                                    <td><?php echo esc_html(sc_sales_invoice_format_money($line['total'] ?? 0)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>

                    <section class="sc-si-totals">
                        <table>
                            <tr>
                                <th>جمع جزء</th>
                                <td><?php echo esc_html(sc_sales_invoice_format_money($inv['subtotal'] ?? 0)); ?> تومان</td>
                            </tr>
                            <?php if (!empty($inv['discount']) && (float) $inv['discount'] > 0) : ?>
                                <tr>
                                    <th>تخفیف</th>
                                    <td><?php echo esc_html(sc_sales_invoice_format_money($inv['discount'])); ?> تومان</td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($inv['shipping']) && (float) $inv['shipping'] > 0) : ?>
                                <tr>
                                    <th>هزینه ارسال</th>
                                    <td><?php echo esc_html(sc_sales_invoice_format_money($inv['shipping'])); ?> تومان</td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($inv['tax']) && (float) $inv['tax'] > 0) : ?>
                                <tr>
                                    <th>مالیات</th>
                                    <td><?php echo esc_html(sc_sales_invoice_format_money($inv['tax'])); ?> تومان</td>
                                </tr>
                            <?php endif; ?>
                            <tr class="sc-si-grand">
                                <th>مبلغ قابل پرداخت</th>
                                <td><?php echo esc_html(sc_sales_invoice_format_money($inv['total'] ?? 0)); ?> تومان</td>
                            </tr>
                        </table>
                    </section>

                    <?php if ($issuer['footer_note'] !== '') : ?>
                        <footer class="sc-si-footer-note">
                            <?php echo esc_html($issuer['footer_note']); ?>
                        </footer>
                    <?php endif; ?>

                    <div class="sc-si-signatures">
                        <div class="sc-si-sign">مهر و امضای فروشنده</div>
                        <div class="sc-si-sign">امضای خریدار</div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Export sales invoices for WooCommerce order IDs.
 *
 * @param array<int,int|string> $order_ids
 */
function sc_sales_invoice_render_pdf_for_wc_orders(array $order_ids) {
    $order_ids = array_values(array_unique(array_filter(array_map('absint', $order_ids))));
    $payloads = [];
    foreach ($order_ids as $oid) {
        $payload = sc_sales_invoice_build_from_wc_order($oid);
        if ($payload) {
            $payloads[] = $payload;
        }
    }
    sc_sales_invoice_render_pdf_page($payloads, 'فاکتور فروش سفارش‌ها');
}

/**
 * Export sales invoices for sc_invoices IDs.
 *
 * @param array<int,int|string> $invoice_ids
 */
function sc_sales_invoice_render_pdf_for_sc_invoices(array $invoice_ids) {
    $invoice_ids = array_values(array_unique(array_filter(array_map('absint', $invoice_ids))));
    $payloads = [];
    foreach ($invoice_ids as $iid) {
        $payload = sc_sales_invoice_build_from_sc_invoice($iid);
        if ($payload) {
            $payloads[] = $payload;
        }
    }
    sc_sales_invoice_render_pdf_page($payloads, 'فاکتور فروش صورت‌حساب‌ها');
}
