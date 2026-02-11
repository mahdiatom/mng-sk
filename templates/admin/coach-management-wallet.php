<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی به این صفحه ندارید.');
}

global $wpdb;
$coaches_table = $wpdb->prefix . 'sc_coaches';
$wallet_table = $wpdb->prefix . 'sc_coach_wallet_transactions';

// دریافت مربی انتخاب شده
$coach_id = isset($_GET['coach_id']) ? absint($_GET['coach_id']) : 0;
$coach = null;
if ($coach_id) {
    $coach = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $coaches_table WHERE id = %d",
        $coach_id
    ));
}

// پردازش شارژ/برداشت
$action_message = '';
$action_message_type = '';

if (isset($_POST['submit_action']) && check_admin_referer('coach_wallet_action_nonce')) {
    $action_coach_id = absint($_POST['coach_id']);
    $action_type = sanitize_text_field($_POST['action_type']);
    $amount = floatval(str_replace(',', '', $_POST['amount']));
    $description = sanitize_text_field($_POST['description']);
    
    if ($amount <= 0) {
        $action_message = 'مبلغ نامعتبر است.';
        $action_message_type = 'error';
    } else {
        if ($action_type === 'charge') {
            $result = sc_add_coach_wallet_transaction(
                $action_coach_id,
                'charge',
                $amount,
                $description ?: 'شارژ دستی توسط مدیر'
            );
            if ($result['success']) {
                $action_message = sprintf('مبلغ %s تومان با موفقیت به کیف پول مربی اضافه شد.', number_format($amount, 0, '.', ','));
                $action_message_type = 'success';
            } else {
                $action_message = $result['message'];
                $action_message_type = 'error';
            }
        } elseif ($action_type === 'deduct') {
            $result = sc_deduct_coach_wallet(
                $action_coach_id,
                $amount,
                $description ?: 'کسر دستی توسط مدیر'
            );
            if ($result['success']) {
                $action_message = sprintf('مبلغ %s تومان با موفقیت از کیف پول مربی کسر شد.', number_format($amount, 0, '.', ','));
                $action_message_type = 'success';
            } else {
                $action_message = $result['message'];
                $action_message_type = 'error';
            }
        }
    }
}

// دریافت لیست مربیان
$coaches = $wpdb->get_results(
    "SELECT id, first_name, last_name FROM $coaches_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC"
);

// اگر مربی انتخاب شده، دریافت موجودی و تراکنش‌ها
$wallet_balance = 0;
$transactions = [];
if ($coach) {
    $wallet_balance = sc_get_coach_wallet_balance($coach_id);
    $transactions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $wallet_table 
         WHERE coach_id = %d 
         ORDER BY created_at DESC 
         LIMIT 100",
        $coach_id
    ));
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">مدیریت کیف پول مربیان</h1>
    <hr class="wp-header-end">
    
    <?php if ($action_message): ?>
        <div class="notice notice-<?php echo $action_message_type; ?> is-dismissible">
            <p><?php echo esc_html($action_message); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- انتخاب مربی -->
    <div class="card" style="margin: 20px 0;">
        <h2>انتخاب مربی</h2>
        <form method="GET" action="">
            <input type="hidden" name="page" value="sc-coach-management-wallet">
            <table class="form-table">
                <tr>
                    <th><label for="coach_id">مربی</label></th>
                    <td>
                        <select name="coach_id" id="coach_id" style="width: 300px;" onchange="this.form.submit();">
                            <option value="0">-- انتخاب مربی --</option>
                            <?php foreach ($coaches as $c): ?>
                                <option value="<?php echo $c->id; ?>" <?php selected($coach_id, $c->id); ?>>
                                    <?php echo esc_html($c->first_name . ' ' . $c->last_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </form>
    </div>
    
    <?php if ($coach): ?>
        <!-- نمایش موجودی -->
        <div class="notice notice-info" style="padding: 20px; margin: 20px 0;">
            <h2 style="margin-top: 0;">
                مربی: <strong><?php echo esc_html($coach->first_name . ' ' . $coach->last_name); ?></strong><br>
                موجودی کیف پول: <strong style="font-size: 24px; color: #2271b1;"><?php echo number_format($wallet_balance, 0, '.', ','); ?> تومان</strong>
            </h2>
        </div>
        
        <!-- فرم شارژ/برداشت -->
        <div class="card" style="max-width: 600px; margin: 20px 0;">
            <h2>شارژ / برداشت دستی</h2>
            <form method="POST" action="">
                <?php wp_nonce_field('coach_wallet_action_nonce'); ?>
                <input type="hidden" name="coach_id" value="<?php echo $coach_id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th><label>نوع عملیات</label></th>
                        <td>
                            <label>
                                <input type="radio" name="action_type" value="charge" checked>
                                شارژ (افزودن به کیف پول)
                            </label>
                            <label style="margin-right: 20px;">
                                <input type="radio" name="action_type" value="deduct">
                                برداشت (کسر از کیف پول)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amount">مبلغ (تومان)</label></th>
                        <td>
                            <input type="text" id="amount" name="amount" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description">توضیحات</label></th>
                        <td>
                            <textarea id="description" name="description" rows="3" class="large-text"></textarea>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit_action" class="button button-primary" value="اجرا">
                </p>
            </form>
        </div>
        
        <!-- تراکنش‌ها -->
        <div class="card" style="margin: 20px 0;">
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
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // فرمت کردن مبلغ
    $('#amount').on('input', function() {
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
