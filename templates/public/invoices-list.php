<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// استفاده از تنظیمات WooCommerce برای فرمت قیمت
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
?>

<div class="sc-invoices-page">
    <h2>صورت حساب‌ها</h2>
    
    <?php if (empty($invoices)) : ?>
        <div class="woocommerce-message woocommerce-message--info woocommerce-info">
            شما هنوز صورت حسابی ندارید.
        </div>
    <?php else : ?>
        <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
            <thead>
                <tr>
                    <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-number">
                        <span class="nobr">شماره سفارش</span>
                    </th>
                    <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date">
                        <span class="nobr">دوره</span>
                    </th>
                    <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status">
                        <span class="nobr">مبلغ (با جریمه)</span>
                    </th>
                    <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-total">
                        <span class="nobr">وضعیت</span>
                    </th>
                    <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-actions">
                        <span class="nobr">عملیات</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice) : 
                    // بررسی و اعمال جریمه در صورت نیاز
                    if ($invoice->status === 'pending' && !$invoice->penalty_applied) {
                        sc_apply_penalty_to_invoice($invoice->id);
                        // دریافت مجدد اطلاعات صورت حساب
                        $events_table = $wpdb->prefix . 'sc_events';
                        $invoice = $wpdb->get_row($wpdb->prepare(
                            "SELECT i.*, c.title as course_title, c.price as course_price, e.name as event_name
                             FROM {$wpdb->prefix}sc_invoices i
                             LEFT JOIN {$wpdb->prefix}sc_courses c ON i.course_id = c.id AND (c.deleted_at IS NULL OR c.deleted_at = '0000-00-00 00:00:00')
                             LEFT JOIN $events_table e ON i.event_id = e.id AND (e.deleted_at IS NULL OR e.deleted_at = '0000-00-00 00:00:00')
                             WHERE i.id = %d",
                            $invoice->id
                        ));
                    }
                    
                    $total_amount = (float)$invoice->amount + (float)($invoice->penalty_amount ?? 0);
                    
                    $formatted_price = '';
                    if (function_exists('wc_price')) {
                        $formatted_price = wc_price($invoice->amount);
                    } else {
                        $formatted_price = number_format((float)$invoice->amount, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                    }
                    
                    $formatted_total = '';
                    if (function_exists('wc_price')) {
                        $formatted_total = wc_price($total_amount);
                    } else {
                        $formatted_total = number_format($total_amount, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                    }
                    
                    $penalty_amount = (float)($invoice->penalty_amount ?? 0);
                    $formatted_penalty = '';
                    if ($penalty_amount > 0) {
                        if (function_exists('wc_price')) {
                            $formatted_penalty = wc_price($penalty_amount);
                        } else {
                            $formatted_penalty = number_format($penalty_amount, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                        }
                    }
                    
                    // تعیین وضعیت و رنگ
                    $status_label = '';
                    $status_class = '';
                    switch ($invoice->status) {
                        case 'paid':
                        case 'completed':
                            $status_label = 'تایید پرداخت';
                            $status_class = 'paid';
                            break;
                        case 'processing':
                            $status_label = 'پرداخت شده';
                            $status_class = 'processing';
                            break;
                        case 'pending':
                            $status_label = 'در انتظار پرداخت';
                            $status_class = 'pending';
                            break;
                        case 'under_review':
                        case 'on-hold':
                            $status_label = 'در حال بررسی';
                            $status_class = 'under_review';
                            break;
                        case 'cancelled':
                            $status_label = 'لغو شده';
                            $status_class = 'cancelled';
                            break;
                        case 'refunded':
                            $status_label = 'بازگشت شده';
                            $status_class = 'refunded';
                            break;
                        case 'failed':
                            $status_label = 'ناموفق';
                            $status_class = 'failed';
                            break;
                        default:
                            $status_label = 'در انتظار پرداخت';
                            $status_class = 'pending';
                    }
                    
                    // دریافت لینک پرداخت اگر سفارش WooCommerce وجود دارد
                    $payment_url = '';
                    $order_object = null;
                    $is_order_paid = false;
                    $has_valid_order = false;
                    
                    // بررسی وجود woocommerce_order_id و وضعیت pending یا under_review
                    if (!empty($invoice->woocommerce_order_id) && in_array($invoice->status, ['pending', 'under_review'])) {
                        if (function_exists('wc_get_order')) {
                            $order_object = wc_get_order($invoice->woocommerce_order_id);
                            if ($order_object) {
                                $has_valid_order = true;
                                $is_order_paid = $order_object->is_paid();
                                $order_status = $order_object->get_status();
                                
                                // اگر سفارش پرداخت نشده است و وضعیت pending است، لینک پرداخت را ایجاد کن
                                // برای under_review فقط لینک مشاهده سفارش نمایش داده می‌شود
                                if (!$is_order_paid && $invoice->status === 'pending') {
                                    // استفاده از متد اصلی WooCommerce برای لینک پرداخت
                                    $payment_url = $order_object->get_checkout_payment_url();
                                    
                                    // اگر لینک خالی بود یا متد وجود نداشت، از endpoint استفاده کن
                                    if (empty($payment_url)) {
                                        $checkout_page_id = wc_get_page_id('checkout');
                                        if ($checkout_page_id) {
                                            $payment_url = add_query_arg('order-pay', $invoice->woocommerce_order_id, get_permalink($checkout_page_id));
                                            $payment_url = add_query_arg('key', $order_object->get_order_key(), $payment_url);
                                        } else {
                                            // در صورت عدم وجود صفحه checkout، از order-pay endpoint استفاده کن
                                            $payment_url = wc_get_endpoint_url('order-pay', $invoice->woocommerce_order_id, wc_get_page_permalink('checkout'));
                                        }
                                    }
                                }
                            }
                        }
                    }
                ?>
                    <tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr($status_class); ?> order">
                        <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-title="شماره سفارش">
                            <?php
                            // استفاده از شماره سفارش WooCommerce اگر وجود داشته باشد
                            $order_number = '#' . $invoice->id;
                            if (!empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
                                $order = wc_get_order($invoice->woocommerce_order_id);
                                if ($order) {
                                    $order_number = $order->get_order_number();
                                }
                            }
                            echo esc_html($order_number);
                            ?>
                        </td>
                        <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="دوره">
                            <?php if (!empty($invoice->course_title)) : ?>
                                <strong>دوره:</strong> <?php echo esc_html($invoice->course_title); ?>
                            <?php elseif (!empty($invoice->event_name)) : ?>
                                <strong>رویداد / مسابقه:</strong> <?php echo esc_html($invoice->event_name); ?>
                            <?php elseif (!empty($invoice->expense_name)) : ?>
                                <strong>هزینه اضافی:</strong> <?php echo esc_html($invoice->expense_name); ?>
                            <?php else : ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                            <?php if (!empty($invoice->expense_name) && !empty($invoice->course_title)) : ?>
                                <br>
                                <small><strong>هزینه اضافی:</strong> <?php echo esc_html($invoice->expense_name); ?></small>
                            <?php endif; ?>
                            <br>
                            <small style="color: #666;">
                                <?php echo sc_date_shamsi_date_only($invoice->created_at); ?>
                            </small>
                        </td>
                        <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="مبلغ">
                            <strong><?php echo $formatted_price; ?></strong>
                            <?php if ($penalty_amount > 0) : ?>
                                <br>
                                <small style="color: #d63638;">
                                    <strong>جریمه:</strong> <?php echo $formatted_penalty; ?>
                                </small>
                                <br>
                                <strong style="color: #2271b1;">مجموع: <?php echo $formatted_total; ?></strong>
                            <?php endif; ?>
                        </td>
                        <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-title="وضعیت">
                            <span class="woocommerce-orders-table__status status-<?php echo esc_attr($status_class); ?>" style="
                                padding: 5px 10px;
                                border-radius: 4px;
                                font-weight: bold;
                                <?php if ($status_class === 'paid') : ?>
                                    background-color: #d4edda;
                                    color: #155724;
                                <?php elseif ($status_class === 'processing') : ?>
                                    background-color: #d4edda;
                                    color: #155724;
                                <?php elseif ($status_class === 'pending') : ?>
                                    background-color: #fff3cd;
                                    color: #856404;
                                <?php elseif ($status_class === 'under_review') : ?>
                                    background-color: #e5f5fa;
                                    color: #2271b1;
                                <?php elseif ($status_class === 'cancelled') : ?>
                                    background-color: #f8d7da;
                                    color: #721c24;
                                <?php elseif ($status_class === 'refunded') : ?>
                                    background-color: #f8d7da;
                                    color: #721c24;
                                <?php elseif ($status_class === 'failed') : ?>
                                    background-color: #f8d7da;
                                    color: #721c24;
                                <?php else : ?>
                                    background-color: #f8d7da;
                                    color: #721c24;
                                <?php endif; ?>
                            ">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </td>
                        <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions" data-title="عملیات">
                            <?php 
                            // اگر لینک پرداخت وجود ندارد اما woocommerce_order_id و وضعیت pending یا under_review دارد، لینک را ایجاد کن
                            if (empty($payment_url) && !empty($invoice->woocommerce_order_id) && in_array($invoice->status, ['pending', 'under_review'])) {
                                // اگر order پیدا نشد، دوباره تلاش کن
                                if (!$order_object && function_exists('wc_get_order')) {
                                    $order_object = wc_get_order($invoice->woocommerce_order_id);
                                    if ($order_object) {
                                        $is_order_paid = $order_object->is_paid();
                                    }
                                }
                                
                                if ($order_object && !$is_order_paid) {
                                    // تلاش برای ایجاد لینک پرداخت با استفاده از order key
                                    $order_key = $order_object->get_order_key();
                                    $checkout_page_id = wc_get_page_id('checkout');
                                    if ($checkout_page_id && $order_key) {
                                        $payment_url = add_query_arg([
                                            'order-pay' => $invoice->woocommerce_order_id,
                                            'key' => $order_key
                                        ], get_permalink($checkout_page_id));
                                    }
                                } elseif (!empty($invoice->woocommerce_order_id)) {
                                    // اگر order پیدا نشد اما order_id وجود دارد، یک لینک ساده ایجاد کن
                                    $checkout_page_id = wc_get_page_id('checkout');
                                    if ($checkout_page_id) {
                                        $payment_url = add_query_arg('order-pay', $invoice->woocommerce_order_id, get_permalink($checkout_page_id));
                                    }
                                }
                            }
                            
                            if ($invoice->status === 'under_review' && !empty($invoice->woocommerce_order_id) && function_exists('wc_get_endpoint_url')) : ?>
                                <a href="<?php echo esc_url(wc_get_endpoint_url('view-order', $invoice->woocommerce_order_id)); ?>" class="woocommerce-button button view">
                                    مشاهده سفارش
                                </a>
                            <?php elseif ($payment_url && $invoice->status === 'pending') : ?>
                                <a href="<?php echo esc_url($payment_url); ?>" class="woocommerce-button button view" style="
                                    display: inline-block;
                                    padding: 8px 15px;
                                    background-color: #2271b1;
                                    color: #fff;
                                    text-decoration: none;
                                    border-radius: 4px;
                                    font-weight: bold;
                                ">
                                    پرداخت
                                </a>
                            <?php elseif (!empty($invoice->woocommerce_order_id) && function_exists('wc_get_endpoint_url')) : ?>
                                <a href="<?php echo esc_url(wc_get_endpoint_url('view-order', $invoice->woocommerce_order_id)); ?>" class="woocommerce-button button view">
                                    مشاهده سفارش
                                </a>
                            <?php elseif (in_array($invoice->status, ['pending', 'under_review']) && empty($invoice->woocommerce_order_id)) : ?>
                                <span style="color: #d63638; font-size: 12px;">در انتظار ایجاد سفارش</span>
                            <?php else : ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.sc-invoices-page {
    margin-top: 20px;
}

.woocommerce-orders-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.woocommerce-orders-table th,
.woocommerce-orders-table td {
    padding: 12px;
    text-align: right;
    border-bottom: 1px solid #ddd;
}

.woocommerce-orders-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}

.woocommerce-orders-table tr:hover {
    background-color: #f9f9f9;
}

@media (max-width: 768px) {
    .woocommerce-orders-table {
        display: block;
    }
    
    .woocommerce-orders-table thead {
        display: none;
    }
    
    .woocommerce-orders-table tbody,
    .woocommerce-orders-table tr,
    .woocommerce-orders-table td {
        display: block;
        width: 100%;
    }
    
    .woocommerce-orders-table td {
        text-align: right;
        padding: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .woocommerce-orders-table td:before {
        content: attr(data-title) ": ";
        font-weight: bold;
        float: right;
        margin-left: 10px;
    }
}
</style>

