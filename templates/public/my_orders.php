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

<?php
// دریافت متغیر فیلتر (اگر از my-account.php فراخوانی شده باشد)
$filter_status = isset($filter_status) ? $filter_status : (isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all');
?>

<div class="sc-orders-page">
    <h2 style="margin-bottom: 25px; color: #1a1a1a; font-size: 28px; font-weight: 700; display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 32px;">💳</span>
        صورت حساب‌ها
    </h2>
    
    <!-- فیلتر وضعیت -->
    <div class="sc-orders-filters" style="margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <form method="GET" action="<?php echo esc_url(wc_get_account_endpoint_url('my-orders')); ?>" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            
            <div style="flex: 1; min-width: 200px;">
                <label for="filter_status" style="display: block; margin-bottom: 5px; font-weight: 600;">وضعیت:</label>
                <select name="filter_status" id="filter_status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                    <option value="wc-checkout-draft" <?php selected($filter_status, 'wc-checkout-draft'); ?>>در انتظار پرداخت</option>
                    <option value="wc-under_review" <?php selected($filter_status, 'wc-under_review'); ?>>در حال بررسی</option>
                    <option value="wc-processing" <?php selected($filter_status, 'wc-processing'); ?>>پرداخت شده</option>
                    <option value="wc-completed" <?php selected($filter_status, 'wc-completed'); ?>>تایید پرداخت</option>
                    <option value="wc-paid" <?php selected($filter_status, 'wc-paid'); ?>>تایید پرداخت</option>
                    <option value="wc-cancelled" <?php selected($filter_status, 'wc-cancelled'); ?>>لغو شده</option>
                    <option value="wc-refunded" <?php selected($filter_status, 'wc-refunded'); ?>>بازگشت شده</option>
                    <option value="wc-failed" <?php selected($filter_status, 'wc-failed'); ?>>ناموفق</option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="button button-primary" style="padding: 8px 20px; height: auto;">اعمال فیلتر</button>
            </div>
        </form>
        
    </div>
    
    <?php if (empty($orders)) : ?>
        <div class="sc-message sc-message-info" style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px; color: #856404;">
            <?php if ($filter_status !== 'all') : ?>
                صورت حسابی با این وضعیت یافت نشد.
            <?php else : ?>
                شما هنوز صورت حسابی ندارید.
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="sc-orders-table-wrap">
        <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table my_account_orders account-orders-table sc-orders-table sc-orders-table-desktop">
            <thead>
                <tr>
                    <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-number">
                        <span class="nobr">شماره و تاریخ سفارش</span>
                    </th>
                    <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date">
                        <span class="nobr">جزئیات سفارش</span>
                    </th>
                    
                    <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-total">
                        <span class="nobr">جمع کل</span>
                    </th>
                    <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-actions">
                        <span class="nobr">وضعیت سفارش</span>
                    </th>
                    <th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status">
                        <span class="nobr">عملیات</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count_orders =  0;
                
                foreach ($orders as $order) : 
                    $count_orders++;
                      $total_amount = $order->total_amount;
                    
                    $formatted_price = '';
                    if (function_exists('wc_price')) {
                        $formatted_price = wc_price($order->total_amount);
                    } else {
                        $formatted_price = number_format((float)$order->total_amount, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                    }
                    
                    $formatted_total = '';
                    if (function_exists('wc_price')) {
                        $formatted_total = wc_price($total_amount);
                    } else {
                        $formatted_total = number_format($total_amount, $decimal_places, $decimal_separator, $thousand_separator) . ' تومان';
                    }
                    
                   
                  
                    
                   
                    
                    // تعیین وضعیت و رنگ
                    $status_label = '';
                    $status_class = '';
                    $status_bg = '';
                    $status_color = '';
                    $status_icon = '';
                    
                    switch ($order->status) {
                        case 'wc-paid':
                        case 'wc-completed':
                            $status_label = 'تایید پرداخت';
                            $status_class = 'paid';
                            $status_bg = '#d4edda';
                            $status_color = '#155724';
                            $status_icon = '✅';
                            break;
                        case 'wc-processing':
                            $status_label = 'پرداخت شده';
                            $status_class = 'processing';
                            $status_bg = '#d4edda';
                            $status_color = '#155724';
                            $status_icon = '✅';
                            break;
                        case 'wc-pending':
                            $status_label = 'در انتظار پرداخت';
                            $status_class = 'pending';
                            $status_bg = '#fff3cd';
                            $status_color = '#856404';
                            $status_icon = '⏳';
                            break;
                        case 'wc-under_review':
                        case 'wc-on-hold':
                            $status_label = 'در حال بررسی';
                            $status_class = 'under_review';
                            $status_bg = '#e5f5fa';
                            $status_color = '#2271b1';
                            $status_icon = '🔍';
                            break;
                        case 'wc-cancelled':
                            $status_label = 'لغو شده';
                            $status_class = 'cancelled';
                            $status_bg = '#ffeaea';
                            $status_color = '#d63638';
                            $status_icon = '❌';
                            break;
                        case 'wc-refunded':
                            $status_label = 'بازگشت شده';
                            $status_class = 'refunded';
                            $status_bg = '#ffeaea';
                            $status_color = '#d63638';
                            $status_icon = '↩️';
                            break;
                        case 'wc-failed':
                            $status_label = 'ناموفق';
                            $status_class = 'failed';
                            $status_bg = '#ffeaea';
                            $status_color = '#d63638';
                            $status_icon = '⚠️';
                            break;
                        default:
                            $status_label = 'در انتظار پرداخت - سبد خرید';
                            $status_class = 'pending';
                            $status_bg = '#fff3cd';
                            $status_color = '#856404';
                            $status_icon = '❓';
                    }
                    
                    // دریافت لینک پرداخت اگر سفارش WooCommerce وجود دارد
                    $payment_url = '';
                    $order_object = null;
                    $is_order_paid = false;
                    $has_valid_order = false;
                    
                    // بررسی وجود id_order و وضعیت pending یا under_review
                    if (!empty($order->id_order) && in_array($order->status, ['wc-pending','wc-checkout-draft', 'wc-under_review'])) {
                        if (function_exists('wc_get_order')) {
                            $order_object = wc_get_order($order->id_order);
                            if ($order_object) {
                                $has_valid_order = true;
                                $is_order_paid = $order_object->is_paid();
                                $order_status = $order_object->get_status();
                                
                                // اگر سفارش پرداخت نشده است و وضعیت pending است، لینک پرداخت را ایجاد کن
                                // برای under_review فقط لینک مشاهده سفارش نمایش داده می‌شود
                                if (!$is_order_paid && ($order->status === 'wc-pending' ||$order->status === 'wc-checkout-draft' )) {
                                    // استفاده از متد اصلی WooCommerce برای لینک پرداخت
                                    $payment_url = $order_object->get_checkout_payment_url();
                                    
                                    // اگر لینک خالی بود یا متد وجود نداشت، از endpoint استفاده کن
                                    if (empty($payment_url)) {
                                        $checkout_page_id = wc_get_page_id('checkout');
                                        if ($checkout_page_id) {
                                            $payment_url = add_query_arg('order-pay', $order->id_order, get_permalink($checkout_page_id));
                                            $payment_url = add_query_arg('key', $order_object->get_order_key(), $payment_url);
                                        } else {
                                            // در صورت عدم وجود صفحه checkout، از order-pay endpoint استفاده کن
                                            $payment_url = wc_get_endpoint_url('order-pay', $order->id_order, wc_get_page_permalink('checkout'));
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // اگر لینک پرداخت وجود ندارد اما id_order و وضعیت pending یا under_review دارد، لینک را ایجاد کن
                    if (empty($payment_url) && !empty($order->id_order) && in_array($order->status, ['wc-pending', 'wc-under_review','wc-checkout-draft'])) {
                        // اگر order پیدا نشد، دوباره تلاش کن
                        if (!$order_object && function_exists('wc_get_order')) {
                            $order_object = wc_get_order($order->id_order);
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
                                    'order-pay' => $order->id_order,
                                    'key' => $order_key
                                ], get_permalink($checkout_page_id));
                            }
                        } elseif (!empty($order->id_order)) {
                            // اگر order پیدا نشد اما order_id وجود دارد، یک لینک ساده ایجاد کن
                            $checkout_page_id = wc_get_page_id('checkout');
                            if ($checkout_page_id) {
                                $payment_url = add_query_arg('order-pay', $order->id_order , get_permalink($checkout_page_id));
                            }
                        }
                    }
                ?>
                    <tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr($status_class); ?> order">
                        <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-label="شماره سفارش">
                            <?php
                            // استفاده از شماره سفارش WooCommerce اگر وجود داشته باشد
                            $order_number = '#' . $order->id_order;
                            if (!empty($order->id_order) && function_exists('wc_get_order')) {
                               
                               
                                    $order_number = $order->id_order;
                                
                            }
                            ?>
                            <strong style="color: #2271b1; font-size: 15px;"><?php echo esc_html($order_number); ?></strong>
                            <br>
                            <small style="color: #666; font-size: 12px;">
                                📅 <?php echo sc_date_shamsi_date_only($order->date_created_gmt); ?>
                            </small>
                        </td>
                        <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-label="سفارش">
                            <?php if (!empty($order->products_with_quantity)) : ?>
                                <div style="margin-bottom: 5px;">
                                    
                                    <span style="color: #333;"><?php echo $order->products_with_quantity; ?></span>
                                </div>
                            <?php elseif (!empty($order->payment_method_title)) : ?>
                                <div style="margin-bottom: 5px;">
                                    
                                    <span style="color: #333;"><?php echo esc_html($order->payment_method_title); ?></span>
                                </div>
                            <?php elseif (!empty($order->total_amount)) : ?>
                                <div style="margin-bottom: 5px;">
                                    
                                    <span style="color: #333;"><?php echo esc_html($order->total_amount); ?></span>
                                </div>
                            <?php else : ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                            <?php if (!empty($order->expense_name) && !empty($order->course_title)) : ?>
                                <div style="margin-top: 5px; padding-top: 5px; border-top: 1px solid #eee;">
                                    <small><strong style="color: #2271b1;">💰 هزینه اضافی:</strong> <?php echo esc_html($order->expense_name); ?></small>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($order->order_description)) : ?>
                                <div class="sc-order-description" style="margin-top: 8px; padding: 8px 10px; background: #f5f5f5; border-radius: 6px; font-size: 13px; color: #555; line-height: 1.5; min-height: 2.5em; white-space: pre-wrap; word-wrap: break-word;"><?php echo wp_kses_post(nl2br(esc_html(trim($order->order_description)))); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-label="مبلغ">
                            <div style="margin-bottom: 5px;">
                                <strong style="font-size: 16px; color: #2271b1;"><?php echo number_format( $order->total_amount) . '  تومان' ;  ?></strong>
                            </div>
                        </td>
                        <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-label="وضعیت">
                            <span class="woocommerce-orders-table__status status-<?php echo esc_attr($status_class); ?>" style="
                                display: inline-flex;
                                align-items: center;
                                gap: 6px;
                                padding: 8px 14px;
                                border-radius: 6px;
                                font-weight: 600;
                                font-size: 13px;
                                background-color: <?php echo esc_attr($status_bg); ?>;
                                color: <?php echo esc_attr($status_color); ?>;
                            ">
                                <span style="font-size: 16px;"><?php echo esc_html($status_icon); ?></span>
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </td>
                        <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions" data-label="عملیات">
                            <div style="display: flex;gap: 8px;flex-wrap: wrap;justify-content: center;flex-direction: column;text-align: center;">
                                <?php 
                                // دکمه‌های عملیات
                                $action_buttons = [];
                                
                                // دکمه پرداخت برای pending
                                if ($payment_url && ($order->status === 'wc-pending' ||$order->status === 'wc-checkout-draft' )) {
                                    // بررسی فعال بودن کیف پول (امکانات پرو + تنظیم کیف پول)
                                    $wallet_enabled = function_exists('sc_can_show_players_wallet') && sc_can_show_players_wallet();
                                    $wallet_balance = 0;
                                    $can_pay_from_wallet = false;
                                    
                                    if ($wallet_enabled) {
                                        $wallet_balance = sc_get_wallet_balance($order->user_id);
                                        
                                        
                                        // بررسی امکان پرداخت از کیف پول
                                        if ($wallet_balance >= $total_amount) {
                                            $can_pay_from_wallet = true;
                                        } elseif (sc_is_wallet_partial_payment_allowed() && $wallet_balance > 0) {
                                            $can_pay_from_wallet = true; // پرداخت جزئی
                                        }
                                    }
                                    
                                    // دکمه پرداخت از کیف پول
                                    if ($can_pay_from_wallet) {
                                        $wallet_pay_url = wp_nonce_url(
                                            add_query_arg([
                                                'pay_from_wallet' => '1',
                                                'order_id' => $order->id
                                            ], wc_get_account_endpoint_url('my-orders')),
                                            'pay_from_wallet_' . $order->id
                                        );
                                        
                                        $wallet_text = $wallet_balance >= $total_amount ? 'پرداخت از کیف پول' : 'پرداخت از کیف پول + بقیه اش از درگاه';
                                        $action_buttons[] = '<a href="' . esc_url($wallet_pay_url) . '" class="woocommerce-button button view sc-order-btn sc-order-btn-wallet" style="background: #28a745; color: white;"
                                        >💰 ' . esc_html($wallet_text) . '</a>';
                                    }
                                    
                                    // دکمه پرداخت از درگاه
                                    $action_buttons[] = '<a href="' . esc_url($payment_url) . '" class="woocommerce-button button view sc-order-btn sc-order-btn-pay"
                                    >💳 پرداخت از درگاه</a>';
                                }
                                
                                // دکمه مشاهده سفارش برای under_review یا سایر حالات
                                if ($order->status === 'under_review' && !empty($order->id_order) && function_exists('wc_get_endpoint_url')) {
                                    $action_buttons[] = '<a href="' . esc_url(wc_get_endpoint_url('view-order', $order->id_order)) . '" class="woocommerce-button button view sc-order-btn sc-order-btn-view button-primary"
                                   >👁️ مشاهده</a>';
                                } elseif (!empty($order->id_order) && function_exists('wc_get_endpoint_url') && !in_array($order->status, ['wc-pending', 'wc-under_review','wc-checkout-draft'])) {
                                    $action_buttons[] = '<a href="' . esc_url(wc_get_endpoint_url('view-order', $order->id_order)) . '" class="woocommerce-button button view sc-order-btn sc-order-btn-view button-primary" 
                                    >👁️ مشاهده</a>';
                                }
                                
                                // دکمه لغو برای pending و under_review
                                if (in_array($order->status, ['wc-pending', 'wc-under_review','wc-checkout-draft'])) {
                                    $cancel_base_url = wc_get_account_endpoint_url('my-orders');
                                    $cancel_args = [
                                        'cancel_order' => '1',
                                        'order_id' => $order->id_order
                                    ];
                                    // حفظ فیلتر در URL لغو
                                    if ($filter_status !== 'all') {
                                        $cancel_args['filter_status'] = $filter_status;
                                    }
                                    $cancel_url = wp_nonce_url(
                                        add_query_arg($cancel_args, $cancel_base_url),
                                        'cancel_order_' . $order->id_order
                                    );
                                    $action_buttons[] = '<a href="' . esc_url($cancel_url) . '" 
                                        class="woocommerce-button button sc-order-btn sc-order-btn-cancel"
                                        onclick="return confirm(\'آیا مطمئن هستید می‌خواهید این صورت‌حساب را حذف کنید؟ ✖ این عملیات غیرقابل برگشت است🗑\')"
                                        style="background:#dc3545;color:#fff;">
                                         حذف صورتحساب
                                    </a>';
                                    
                                }
                                
                                // نمایش دکمه‌ها یا پیام
                                if (!empty($action_buttons)) {
                                    echo implode('', $action_buttons);
                                } elseif (in_array($order->status, ['wc-pending', 'wc-under_review','wc-checkout-draft']) && empty($order->id_order)) {
                                    echo '<span style="color: #d63638; font-size: 12px; padding: 8px; background: #ffeaea; border-radius: 6px; display: inline-block;">⏳ در انتظار ایجاد سفارش</span>';
                                } else {
                                    echo '<span style="color: #999;">-</span>';
                                }
                                ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <!-- Pagination -->
            <!-- <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom sc_paginate" style="margin: 20px 10px 50px 0px;">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg(['pag' => '%#%']),
                            'format' => '',
                            'prev_text' => '< قبلی ',
                            'next_text' => ' بعدی >' ,
                            'total' => $total_pages,
                            'current' => $current_page
                        ]);
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?> -->
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // بررسی هر 100ms تا المان حاضر شود
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-orders-page h2'); // المان هدف
        if (el) {
            // اسکرول نرم و مرکز صفحه
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval); // توقف بررسی بعد از اسکرول
        }
    }, 100);
});
</script>

