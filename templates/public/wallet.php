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
    <div class="sc-wallet-balance-card" style="background: linear-gradient(135deg, <?php echo $wallet_balance < 0 ? '#dc3545' : ($wallet_balance < $min_balance_alert ? '#f0b849' : '#667eea'); ?> 0%, <?php echo $wallet_balance < 0 ? '#c82333' : ($wallet_balance < $min_balance_alert ? '#e0a800' : '#764ba2'); ?> 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s ease;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h3 style="margin: 0 0 10px 0; font-size: 18px; font-weight: 600; opacity: 0.9;">موجودی کیف پول</h3>
                <div style="font-size: 36px; font-weight: 700; margin: 10px 0;">
                    <?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> تومان
                </div>
            </div>
            <div style="font-size: 48px; opacity: 0.3;">
                💳
            </div>
        </div>
    </div>
    
    <!-- هشدارهای موجودی -->
    <?php if ($wallet_balance < 0) : ?>
        <div class="woocommerce-message woocommerce-message--error woocommerce-error" style="background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; padding: 15px 20px; margin-bottom: 20px; border-radius: 4px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease;">
            <span style="font-size: 24px;">⚠️</span>
            <div>
                <strong style="display: block; margin-bottom: 5px;">هشدار: موجودی منفی</strong>
                <p style="margin: 0; font-size: 14px;">موجودی کیف پول شما منفی است (<?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> تومان). لطفاً فوراً کیف پول خود را شارژ کنید.</p>
            </div>
        </div>
    <?php elseif ($min_balance_alert > 0 && $wallet_balance <= $min_balance_alert && $wallet_balance >= 0) : ?>
        <div class="woocommerce-message woocommerce-message--warning woocommerce-warning" style="background: #fff3cd; border-left: 4px solid #f0b849; color: #856404; padding: 15px 20px; margin-bottom: 20px; border-radius: 4px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease;">
            <span style="font-size: 24px;">⚠️</span>
            <div>
                <strong style="display: block; margin-bottom: 5px;">هشدار: موجودی کم</strong>
                <p style="margin: 0; font-size: 14px;">موجودی کیف پول شما (<?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> تومان) کمتر از حد مجاز (<?php echo esc_html(sc_format_amount_display($min_balance_alert)); ?> تومان) است. لطفاً کیف پول خود را شارژ کنید.</p>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- فرم شارژ کیف پول -->
    <div class="sc-wallet-charge-form" style="background: #f9f9f9; padding: 25px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ddd;">
        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 20px; color: #333;">شارژ کیف پول</h3>
        
        <form method="POST" action="" style="max-width: 500px;">
            <?php wp_nonce_field('sc_charge_wallet_user_nonce', 'sc_charge_wallet_user_nonce'); ?>
            
            <div style="margin-bottom: 20px;">
                <label for="charge_amount_display" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                    مبلغ شارژ (تومان) <span style="color: red;">*</span>
                </label>
                <input type="text" 
                       id="charge_amount_display" 
                       class="input-text sc-wallet-charge-amount" 
                       placeholder="0"
                       dir="ltr"
                       inputmode="numeric"
                       autocomplete="off"
                       required
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                <input type="hidden" name="charge_amount" id="charge_amount" value="">
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
    
    <!-- گزارش مالی -->
    <?php 
    $financial_report = sc_get_wallet_financial_report($player->id);
    ?>
    <div class="sc-wallet-financial-report" style="background: #f9f9f9; padding: 25px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ddd;">
        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
            <span>📊</span>
            گزارش مالی
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #28a745;">
                <div style="font-size: 13px; color: #666; margin-bottom: 5px;">مجموع شارژ</div>
                <div style="font-size: 24px; font-weight: 700; color: #28a745;">
                    <?php echo number_format($financial_report['total_charge'], 0, '.', ','); ?> تومان
                </div>
            </div>
            
            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #dc3545;">
                <div style="font-size: 13px; color: #666; margin-bottom: 5px;">مجموع پرداخت</div>
                <div style="font-size: 24px; font-weight: 700; color: #dc3545;">
                    <?php echo number_format($financial_report['total_payment'], 0, '.', ','); ?> تومان
                </div>
            </div>
            
            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107;">
                <div style="font-size: 13px; color: #666; margin-bottom: 5px;">موجودی فعلی</div>
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $financial_report['current_balance'] < 0 ? '#dc3545' : '#28a745'; ?>;">
                    <?php echo esc_html(sc_format_amount_display($financial_report['current_balance'])); ?> تومان
                </div>
            </div>
            
            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #17a2b8;">
                <div style="font-size: 13px; color: #666; margin-bottom: 5px;">تعداد تراکنش‌ها</div>
                <div style="font-size: 24px; font-weight: 700; color: #17a2b8;">
                    <?php echo number_format($financial_report['total_transactions'], 0, '.', ','); ?>
                </div>
            </div>
        </div>
    </div>
  
    <!-- گزارش ماهانه/سالانه -->
    <div class="sc-wallet-period-report" style="background: #f9f9f9; padding: 25px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ddd;">
        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 20px; color: #333; display: flex; align-items: center; gap: 10px;">
            <span>📅</span>
            گزارش دوره‌ای
        </h3>
        
        <div class="select_report_field">
            <select id="wallet_period_type" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="monthly">ماهانه</option>
                <option value="yearly">سالانه</option>
            </select>
            
            <select id="wallet_period_year" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <?php 
                // نمایش سال‌های شمسی در فیلتر کاربر
                $today_jalali = gregorian_to_jalali((int)date('Y'), (int)date('m'), (int)date('d'));
                $current_jyear = (int)$today_jalali[0];
                for ($i = $current_jyear; $i >= $current_jyear - 5; $i--) :
                ?>
                    <option value="<?php echo $i; ?>" <?php selected($i, $current_jyear); ?>><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
            
            <select id="wallet_period_month" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <?php 
                $current_month = date('m');
                $months = ['01' => 'فروردین', '02' => 'اردیبهشت', '03' => 'خرداد', '04' => 'تیر', '05' => 'مرداد', '06' => 'شهریور', '07' => 'مهر', '08' => 'آبان', '09' => 'آذر', '10' => 'دی', '11' => 'بهمن', '12' => 'اسفند'];
                foreach ($months as $num => $name) :
                ?>
                    <option value="<?php echo $num; ?>" <?php selected($num, $current_month); ?>><?php echo $name; ?></option>
                <?php endforeach; ?>
            </select>
            
            <button type="button" id="load_period_report" class="button button-primary" style="padding: 8px 20px;">مشاهده گزارش</button>
        </div>
        
        <div id="period_report_content" style="display: none;">
            <!-- محتوای گزارش دوره‌ای اینجا نمایش داده می‌شود -->
        </div>
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
                            'refund' => 'بازگشت وجه',
                            'session_fee' => 'کسر جلسه'
                        ];
                        // $type_icons = [
                        //     'charge' => '➕',
                        //     'deduct' => '➖',
                        //     'payment' => '💳',
                        //     'refund' => '↩',
                        //     'session_fee' => '📋'
                        // ];
                        $type_label = isset($type_labels[$transaction->transaction_type]) ? $type_labels[$transaction->transaction_type] : $transaction->transaction_type;
                        $type_color = [
                            'charge' => '#28a745',
                            'deduct' => '#dc3545',
                            'payment' => '#007bff',
                            'refund' => '#ffc107',
                            'session_fee' => '#856404'
                        ];
                    ?>
                        <tr style="margin-bottom: 15px;">
                            <td data-title="نوع تراکنش ">
                                <span style="
                                    display: inline-block;
                                    padding: 4px 10px;
                                    border-radius: 4px;
                                    
                                    color: <?php echo esc_attr($type_color[$transaction->transaction_type] ?? '#333'); ?>;
                                    font-size: 13px;
                                    font-weight: 600;
                                ">
                                    <span style="margin-left: 4px;">
                                        <?php echo esc_html($type_icons[$transaction->transaction_type] ?? ''); ?>
                                    </span>
                                    <?php echo esc_html($type_label); ?>
                                </span>
                            </td>
                            <td data-title="مبلغ ">
                                <strong style="color: <?php echo esc_attr($type_color[$transaction->transaction_type] ?? '#333'); ?>;">
                                    <?php 
                                    $amt = floatval($transaction->amount);
                                    $is_debit = in_array($transaction->transaction_type ?? '', ['deduct', 'payment', 'session_fee']);
                                    echo esc_html(sc_format_amount_display($is_debit ? -$amt : $amt)); ?> تومان
                                </strong>
                            </td>
                            <td data-title="موجودی بعدی">
                                <strong style="color: <?php echo floatval($transaction->balance_after) < 0 ? '#dc3545' : '#28a745'; ?>;">
                                    <?php echo esc_html(sc_format_amount_display(floatval($transaction->balance_after))); ?> تومان
                                </strong>
                            </td>
                            <td data-title="توضیحات" ><?php echo esc_html($transaction->description ?: '-'); ?></td>
                            <td data-title="تاریخ "><?php echo esc_html(sc_date_shamsi($transaction->created_at, 'Y/m/d H:i')); ?></td>
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

<style>
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.sc-wallet-balance-card {
    transition: all 0.3s ease;
}

.sc-wallet-balance-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15) !important;
}

.sc-wallet-transactions table tbody tr {
    transition: background-color 0.15s ease, transform 0.1s ease;
}

.sc-wallet-transactions table tbody tr:hover {
    background-color: #f5f5f5;
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .sc-wallet-balance-card {
        padding: 20px !important;
    }
    
    .sc-wallet-balance-card h3 {
        font-size: 16px !important;
    }
    
    .sc-wallet-balance-card > div > div:first-child > div {
        font-size: 28px !important;
    }

    .sc-wallet-transactions table {
        font-size: 13px;
    }

    .sc-wallet-transactions {
        overflow-x: auto;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
jQuery(document).ready(function($) {
    // فرمت مبلغ شارژ: سه‌تا سه‌تا با کاما (مثل بقیه بخش‌ها)
    var $chargeDisplay = $('#charge_amount_display');
    var $chargeHidden = $('#charge_amount');
    function formatChargeInput() {
        var v = $chargeDisplay.val().replace(/[^\d]/g, '');
        $chargeHidden.val(v === '' ? '' : v);
        if (v.length > 0) {
            var formatted = v.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            $chargeDisplay.val(formatted);
        }
    }
    $chargeDisplay.on('input', formatChargeInput);
    $chargeDisplay.closest('form').on('submit', function() {
        var v = $chargeDisplay.val().replace(/[^\d]/g, '');
        $chargeHidden.val(v === '' ? '' : v);
    });
    
    // نمودار موجودی
    <?php if (!empty($balance_history)) : ?>
    var balanceData = <?php echo json_encode($balance_history); ?>;
    var ctx = document.getElementById('walletBalanceChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: balanceData.map(function(item) {
                    return item.date;
                }),
                datasets: [{
                    label: 'موجودی (تومان)',
                    data: balanceData.map(function(item) {
                        return item.balance;
                    }),
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'موجودی: ' + context.parsed.y.toLocaleString('fa-IR') + ' تومان';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('fa-IR');
                            }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    // بارگذاری گزارش دوره‌ای
    $('#load_period_report').on('click', function() {
        var period = $('#wallet_period_type').val();
        var year = $('#wallet_period_year').val();
        var month = $('#wallet_period_month').val();
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_get_wallet_period_report',
                member_id: <?php echo $player->id; ?>,
                period: period,
                year: year,
                month: month,
                nonce: '<?php echo wp_create_nonce('sc_wallet_period_report'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#period_report_content').html(response.data.html).show();
                } else {
                    alert('خطا در بارگذاری گزارش');
                }
            }
        });
    });
});
document.addEventListener('DOMContentLoaded', function() {
    const periodType = document.getElementById('wallet_period_type');
    const monthSelect = document.getElementById('wallet_period_month');

    // تابع برای بررسی و مخفی/نمایش ماه
    function toggleMonthSelect() {
        if (periodType.value === 'yearly') {
            monthSelect.style.display = 'none';
        } else {
            monthSelect.style.display = 'inline-block';
        }
    }

    // اجرا در ابتدا
    toggleMonthSelect();

    // اجرا هنگام تغییر نوع دوره
    periodType.addEventListener('change', toggleMonthSelect);
});

document.addEventListener('DOMContentLoaded', function() {
    // بررسی هر 100ms تا المان حاضر شود
    const interval = setInterval(function() {
        const el = document.querySelector('.sc-wallet-page h2'); // المان هدف
        if (el) {
            // اسکرول نرم و مرکز صفحه
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clearInterval(interval); // توقف بررسی بعد از اسکرول
        }
    }, 100);
});

</script>

