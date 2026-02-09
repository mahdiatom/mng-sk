<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$transactions_table = $wpdb->prefix . 'sc_wallet_transactions';
$invoices_table = $wpdb->prefix . 'sc_invoices';

// دریافت موجودی کیف پول
$wallet_balance = sc_get_wallet_balance($player->id);
$min_balance_alert = sc_get_wallet_min_balance_alert();

// Pagination برای تراکنش‌ها
$per_page = 20;
$current_page = isset($_GET['wallet_page']) ? max(1, absint($_GET['wallet_page'])) : 1;
$offset = ($current_page - 1) * $per_page;

// دریافت تراکنش‌ها
$transactions = sc_get_wallet_transactions($player->id, $per_page, $offset);
$total_transactions = sc_get_wallet_transactions_count($player->id);
$total_pages = ceil($total_transactions / $per_page);

// پردازش فرم شارژ کیف پول
$charge_message = '';
$charge_message_type = '';

if (isset($_POST['sc_charge_wallet_user']) && check_admin_referer('sc_charge_wallet_user_nonce', 'sc_charge_wallet_user_nonce')) {
    $charge_amount = isset($_POST['charge_amount']) ? floatval($_POST['charge_amount']) : 0;
    
    if ($charge_amount <= 0) {
        $charge_message = 'مبلغ باید بیشتر از صفر باشد.';
        $charge_message_type = 'error';
    } else {
        // بررسی حداقل مبلغ شارژ
        $min_charge = sc_get_wallet_min_charge();
        if ($charge_amount < $min_charge) {
            $charge_message = 'حداقل مبلغ شارژ ' . number_format($min_charge, 0, '.', ',') . ' تومان است.';
            $charge_message_type = 'error';
        } else {
            // بررسی حداکثر مبلغ شارژ
            $max_charge = sc_get_wallet_max_charge();
            if ($max_charge > 0 && $charge_amount > $max_charge) {
                $charge_message = 'حداکثر مبلغ شارژ ' . number_format($max_charge, 0, '.', ',') . ' تومان است.';
                $charge_message_type = 'error';
            } else {
                // ایجاد صورت حساب برای شارژ
                if (!class_exists('WooCommerce')) {
                    $charge_message = 'WooCommerce فعال نیست.';
                    $charge_message_type = 'error';
                } else {
                    // ایجاد صورت حساب pending برای شارژ
                    $invoice_data = [
                        'member_id' => $player->id,
                        'course_id' => 0,
                        'event_id' => null,
                        'member_course_id' => null,
                        'woocommerce_order_id' => null,
                        'amount' => $charge_amount,
                        'expense_name' => 'شارژ کیف پول',
                        'penalty_amount' => 0.00,
                        'penalty_applied' => 0,
                        'status' => 'pending',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ];
                    
                    $invoice_inserted = $wpdb->insert(
                        $invoices_table,
                        $invoice_data,
                        ['%d', '%d', '%d', '%d', '%d', '%f', '%s', '%f', '%d', '%s', '%s', '%s']
                    );
                    
                    if ($invoice_inserted) {
                        $invoice_id = $wpdb->insert_id;
                        
                        // ایجاد سفارش WooCommerce
                        $order_result = sc_create_woocommerce_order_for_invoice($invoice_id, $player->id, 0, $charge_amount, 'شارژ کیف پول');
                        
                        if ($order_result && isset($order_result['success']) && $order_result['success'] && !empty($order_result['order_id'])) {
                            $order_id = $order_result['order_id'];
                            
                            // بروزرسانی invoice با order_id
                            $wpdb->update(
                                $invoices_table,
                                ['woocommerce_order_id' => $order_id, 'updated_at' => current_time('mysql')],
                                ['id' => $invoice_id],
                                ['%d', '%s'],
                                ['%d']
                            );
                            
                            // دریافت لینک پرداخت
                            $order = wc_get_order($order_id);
                            $payment_url = $order ? $order->get_checkout_payment_url() : '';
                            
                            if ($payment_url) {
                                wp_safe_redirect($payment_url);
                                exit;
                            } else {
                                $charge_message = 'صورت حساب ایجاد شد. لطفاً از بخش صورت حساب‌ها پرداخت را انجام دهید.';
                                $charge_message_type = 'success';
                            }
                        } else {
                            $charge_message = 'خطا در ایجاد سفارش. لطفاً دوباره تلاش کنید.';
                            $charge_message_type = 'error';
                        }
                    } else {
                        $charge_message = 'خطا در ایجاد صورت حساب. لطفاً دوباره تلاش کنید.';
                        $charge_message_type = 'error';
                    }
                }
            }
        }
    }
}
?>

<div class="sc-wallet-page">
    <h2 style="margin-bottom: 25px; color: #1a1a1a; font-size: 28px; font-weight: 700; display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 32px;">💰</span>
        کیف پول من
    </h2>
    
    <?php if ($charge_message) : ?>
        <div class="woocommerce-message woocommerce-message--<?php echo esc_attr($charge_message_type); ?> woocommerce-<?php echo esc_attr($charge_message_type); ?>" style="margin-bottom: 20px;">
            <?php echo esc_html($charge_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- نمایش موجودی -->
    <div class="sc-wallet-balance-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h3 style="margin: 0 0 10px 0; font-size: 18px; font-weight: 600; opacity: 0.9;">موجودی کیف پول</h3>
                <div style="font-size: 36px; font-weight: 700; margin: 10px 0;">
                    <?php echo number_format($wallet_balance, 0, '.', ','); ?> تومان
                </div>
                <?php if ($wallet_balance < $min_balance_alert && $wallet_balance >= 0) : ?>
                    <p style="margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;">
                        ⚠️ موجودی شما کمتر از حد مجاز است
                    </p>
                <?php elseif ($wallet_balance < 0) : ?>
                    <p style="margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;">
                        ⚠️ موجودی شما منفی است
                    </p>
                <?php endif; ?>
            </div>
            <div style="font-size: 48px; opacity: 0.3;">
                💳
            </div>
        </div>
    </div>
    
    <!-- فرم شارژ کیف پول -->
    <div class="sc-wallet-charge-form" style="background: #f9f9f9; padding: 25px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ddd;">
        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 20px; color: #333;">شارژ کیف پول</h3>
        
        <form method="POST" action="" style="max-width: 500px;">
            <?php wp_nonce_field('sc_charge_wallet_user_nonce', 'sc_charge_wallet_user_nonce'); ?>
            
            <div style="margin-bottom: 20px;">
                <label for="charge_amount" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    مبلغ شارژ (تومان) <span style="color: red;">*</span>
                </label>
                <input type="number" 
                       name="charge_amount" 
                       id="charge_amount" 
                       class="input-text" 
                       min="0" 
                       step="1000" 
                       required 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                <p style="margin: 8px 0 0 0; font-size: 13px; color: #666;">
                    حداقل مبلغ: <?php echo number_format(sc_get_wallet_min_charge(), 0, '.', ','); ?> تومان
                    <?php if (sc_get_wallet_max_charge() > 0) : ?>
                        - حداکثر مبلغ: <?php echo number_format(sc_get_wallet_max_charge(), 0, '.', ','); ?> تومان
                    <?php endif; ?>
                </p>
            </div>
            
            <button type="submit" name="sc_charge_wallet_user" class="button button-primary" style="padding: 12px 30px; font-size: 16px; font-weight: 600;">
                شارژ کیف پول
            </button>
        </form>
        
        <p style="margin: 15px 0 0 0; font-size: 13px; color: #666;">
            پس از وارد کردن مبلغ و کلیک روی دکمه "شارژ کیف پول"، به صفحه پرداخت هدایت می‌شوید.
        </p>
    </div>
    
    <!-- تاریخچه تراکنش‌ها -->
    <div class="sc-wallet-transactions">
        <h3 style="margin-bottom: 20px; font-size: 20px; color: #333;">تاریخچه تراکنش‌ها</h3>
        
        <?php if (empty($transactions)) : ?>
            <div class="woocommerce-message woocommerce-message--info woocommerce-info">
                شما هنوز تراکنشی ندارید.
            </div>
        <?php else : ?>
            <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>نوع تراکنش</th>
                        <th>مبلغ</th>
                        <th>موجودی بعد</th>
                        <th>توضیحات</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction) : 
                        $type_labels = [
                            'charge' => 'شارژ',
                            'deduct' => 'کاهش',
                            'payment' => 'پرداخت',
                            'refund' => 'بازگشت وجه'
                        ];
                        $type_label = isset($type_labels[$transaction->transaction_type]) ? $type_labels[$transaction->transaction_type] : $transaction->transaction_type;
                        $type_color = [
                            'charge' => '#28a745',
                            'deduct' => '#dc3545',
                            'payment' => '#007bff',
                            'refund' => '#ffc107'
                        ];
                    ?>
                        <tr>
                            <td>
                                <span style="
                                    display: inline-block;
                                    padding: 4px 10px;
                                    border-radius: 4px;
                                    background: #f0f0f0;
                                    color: <?php echo esc_attr($type_color[$transaction->transaction_type] ?? '#333'); ?>;
                                    font-size: 13px;
                                    font-weight: 600;
                                ">
                                    <?php echo esc_html($type_label); ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: <?php echo esc_attr($type_color[$transaction->transaction_type] ?? '#333'); ?>;">
                                    <?php echo number_format(floatval($transaction->amount), 0, '.', ','); ?> تومان
                                </strong>
                            </td>
                            <td>
                                <strong style="color: <?php echo floatval($transaction->balance_after) < 0 ? '#dc3545' : '#28a745'; ?>;">
                                    <?php echo number_format(floatval($transaction->balance_after), 0, '.', ','); ?> تومان
                                </strong>
                            </td>
                            <td><?php echo esc_html($transaction->description ?: '-'); ?></td>
                            <td><?php echo esc_html(sc_date_shamsi_date_time($transaction->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom" style="margin-top: 20px;">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg(['wallet_page' => '%#%']),
                            'format' => '',
                            'prev_text' => '< قبلی',
                            'next_text' => 'بعدی >',
                            'total' => $total_pages,
                            'current' => $current_page
                        ]);
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // فرمت کردن مبلغ با کاما
    $('#charge_amount').on('input', function() {
        var value = $(this).val().replace(/,/g, '');
        if (value && !isNaN(value)) {
            $(this).val(parseFloat(value));
        }
    });
});
</script>

