<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی دسترسی
if (!current_user_can('sc_view_coach_salary')) {
    wp_die('شما دسترسی به این صفحه ندارید.');
}

global $wpdb;
$coaches_table = $wpdb->prefix . 'sc_coaches';
$wallet_table = $wpdb->prefix . 'sc_coach_wallet_transactions';
$withdrawal_table = $wpdb->prefix . 'sc_coach_withdrawal_requests';

// دریافت شناسه مربی لاگین شده
$current_user_id = get_current_user_id();
$coach = $wpdb->get_row($wpdb->prepare(
    "SELECT id FROM $coaches_table WHERE user_id = %d LIMIT 1",
    $current_user_id
));

if (!$coach) {
    echo '<div class="wrap"><div class="notice notice-error"><p>شما به عنوان مربی ثبت نشده‌اید.</p></div></div>';
    return;
}

$coach_id = $coach->id;

// دریافت موجودی کیف پول
$wallet_balance = sc_get_coach_wallet_balance($coach_id);

// پردازش درخواست برداشت
$withdrawal_message = '';
$withdrawal_message_type = '';

if (isset($_POST['submit_withdrawal']) && check_admin_referer('coach_withdrawal_nonce')) {
    $amount = isset($_POST['withdrawal_amount']) ? floatval(str_replace(',', '', $_POST['withdrawal_amount'])) : 0;
    $notes = isset($_POST['withdrawal_notes']) ? sanitize_text_field($_POST['withdrawal_notes']) : '';
    
    if ($amount <= 0) {
        $withdrawal_message = 'مبلغ نامعتبر است.';
        $withdrawal_message_type = 'error';
    } else {
        $result = sc_create_coach_withdrawal_request($coach_id, $amount, $notes);
        if ($result['success']) {
            $withdrawal_message = $result['message'];
            $withdrawal_message_type = 'success';
            $wallet_balance = sc_get_coach_wallet_balance($coach_id); // بروزرسانی موجودی
        } else {
            $withdrawal_message = $result['message'];
            $withdrawal_message_type = 'error';
        }
    }
}

// دریافت درخواست‌های برداشت
$withdrawal_requests = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $withdrawal_table 
     WHERE coach_id = %d 
     ORDER BY created_at DESC 
     LIMIT 50",
    $coach_id
));

// دریافت تراکنش‌های کیف پول
$transactions = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $wallet_table 
     WHERE coach_id = %d 
     ORDER BY created_at DESC 
     LIMIT 50",
    $coach_id
));

// حداقل مبلغ برداشت
$min_withdrawal = floatval(sc_get_setting('coach_min_withdrawal_amount', '0'));
?>

<div class="wrap">
    <h1 class="wp-heading-inline">کیف پول</h1>
    <hr class="wp-header-end">
    
    <?php if ($withdrawal_message): ?>
        <div class="notice notice-<?php echo $withdrawal_message_type; ?> is-dismissible">
            <p><?php echo esc_html($withdrawal_message); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- نمایش موجودی -->
    <div class="notice notice-info" style="padding: 20px; margin: 20px 0;">
        <h2 style="margin-top: 0;">💰 موجودی کیف پول: <strong style="font-size: 24px; color: #2271b1;"><?php echo number_format($wallet_balance, 0, '.', ','); ?> تومان</strong></h2>
        <?php if ($min_withdrawal > 0): ?>
            <p>حداقل مبلغ برداشت: <strong><?php echo number_format($min_withdrawal, 0, '.', ','); ?> تومان</strong></p>
        <?php endif; ?>
    </div>
    
    <!-- فرم درخواست برداشت -->
    <div class="card" style="margin: 20px 0; max-width: 100%;">
        <h2>درخواست برداشت</h2>
        <form method="POST" action="" style="max-width: 800px;">
            <?php wp_nonce_field('coach_withdrawal_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="withdrawal_amount">مبلغ درخواستی (تومان)</label></th>
                    <td>
                        <input type="text" 
                               id="withdrawal_amount" 
                               name="withdrawal_amount" 
                               value="<?php echo number_format($wallet_balance, 0, '.', ','); ?>" 
                               class="regular-text"
                               style="width: 300px;"
                               required>
                        <p class="description">پیش‌فرض: کل موجودی کیف پول. می‌توانید تغییر دهید.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="withdrawal_notes">یادداشت (اختیاری)</label></th>
                    <td>
                        <textarea id="withdrawal_notes" 
                                  name="withdrawal_notes" 
                                  rows="3" 
                                  class="large-text"
                                  style="width: 100%; max-width: 600px;"></textarea>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit_withdrawal" class="button button-primary" value="ثبت درخواست برداشت">
            </p>
        </form>
    </div>
    
    <!-- درخواست‌های برداشت -->
    <div class="card" style="margin: 20px 0; max-width: 100%;">
        <h2>درخواست‌های برداشت</h2>
        <?php if (empty($withdrawal_requests)): ?>
            <p>هیچ درخواست برداشتی ثبت نشده است.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>تاریخ درخواست</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th>یادداشت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $row = 1; ?>
                    <?php foreach ($withdrawal_requests as $request): ?>
                        <tr>
                            <td><?php echo $row++; ?></td>
                            <td><?php echo sc_date_shamsi($request->created_at, 'Y/m/d H:i'); ?></td>
                            <td><strong><?php echo number_format($request->amount, 0, '.', ','); ?> تومان</strong></td>
                            <td>
                                <?php
                                $status_labels = [
                                    'pending' => ['label' => 'در انتظار تایید', 'color' => '#f0a000'],
                                    'approved' => ['label' => 'تایید شده', 'color' => '#2271b1'],
                                    'rejected' => ['label' => 'رد شده', 'color' => '#d63638'],
                                    'paid' => ['label' => 'پرداخت شده', 'color' => '#00a32a']
                                ];
                                $status_info = $status_labels[$request->status] ?? ['label' => $request->status, 'color' => '#666'];
                                ?>
                                <span style="color: <?php echo $status_info['color']; ?>; font-weight: bold;">
                                    <?php echo $status_info['label']; ?>
                                </span>
                                <?php if ($request->status === 'rejected' && $request->rejection_reason): ?>
                                    <br><small style="color: #d63638;">دلیل: <?php echo esc_html($request->rejection_reason); ?></small>
                                <?php endif; ?>
                                <?php if ($request->status === 'paid' && $request->paid_at): ?>
                                    <br><small>پرداخت شده در: <?php echo sc_date_shamsi($request->paid_at, 'Y/m/d H:i'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($request->notes ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- تراکنش‌های کیف پول -->
    <div class="card" style="margin: 20px 0; max-width: 100%;">
        <h2>تراکنش‌های کیف پول</h2>
        <?php if (empty($transactions)): ?>
            <p>هیچ تراکنشی ثبت نشده است.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>تاریخ</th>
                        <th>نوع</th>
                        <th>مبلغ</th>
                        <th>موجودی قبل</th>
                        <th>موجودی بعد</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $row = 1;
                    $type_labels = [
                        'salary_percentage' => 'دستمزد درصدی',
                        'salary_fixed' => 'دستمزد ثابت',
                        'charge' => 'شارژ',
                        'deduct' => 'کسر',
                        'withdrawal' => 'برداشت'
                    ];
                    ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo $row++; ?></td>
                            <td><?php echo sc_date_shamsi($transaction->created_at, 'Y/m/d H:i'); ?></td>
                            <td><?php echo $type_labels[$transaction->transaction_type] ?? $transaction->transaction_type; ?></td>
                            <td>
                                <?php if (in_array($transaction->transaction_type, ['charge', 'salary_percentage', 'salary_fixed'])): ?>
                                    <span style="color: #00a32a;">+<?php echo number_format($transaction->amount, 0, '.', ','); ?></span>
                                <?php else: ?>
                                    <span style="color: #d63638;">-<?php echo number_format($transaction->amount, 0, '.', ','); ?></span>
                                <?php endif; ?>
                                <small>تومان</small>
                            </td>
                            <td><?php echo number_format($transaction->balance_before, 0, '.', ','); ?> تومان</td>
                            <td><strong><?php echo number_format($transaction->balance_after, 0, '.', ','); ?> تومان</strong></td>
                            <td><?php echo esc_html($transaction->description ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // فرمت کردن مبلغ هنگام تایپ
    $('#withdrawal_amount').on('input', function() {
        var value = $(this).val().replace(/,/g, '');
        if (!isNaN(value) && value !== '') {
            $(this).val(number_format(value, 0, '.', ','));
        }
    });
    
    function number_format(number, decimals, dec_point, thousands_sep) {
        number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+number) ? 0 : +number,
            prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
            sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
            dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
            s = '',
            toFixedFix = function(n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
    }
});
</script>
