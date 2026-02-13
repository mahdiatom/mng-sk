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

// حداقل مبلغ برداشت
$min_withdrawal = floatval(sc_get_setting('coach_min_withdrawal_amount', '0'));

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

// فیلترهای لیست درخواست‌های برداشت
$withdraw_filter_status = isset($_GET['withdraw_filter_status']) ? sanitize_text_field($_GET['withdraw_filter_status']) : 'all';

// پردازش فیلترهای تاریخ (شمسی به میلادی)
// اگر تاریخ‌ها خالی هستند، تاریخ پیش‌فرض امروز را تنظیم کن
$today_gregorian = current_time('Y-m-d');
$today = new DateTime(current_time('Y-m-d'));
$jalali = gregorian_to_jalali(
    (int)$today->format('Y'),
    (int)$today->format('m'),
    (int)$today->format('d')
);
$today_shamsi = $jalali[0] . '/' .
    str_pad($jalali[1], 2, '0', STR_PAD_LEFT) . '/' .
    str_pad($jalali[2], 2, '0', STR_PAD_LEFT);

$withdraw_filter_date_from = '';
$withdraw_filter_date_to = '';
$withdraw_filter_date_from_shamsi = isset($_GET['withdraw_filter_date_from_shamsi']) ? sanitize_text_field($_GET['withdraw_filter_date_from_shamsi']) : '';
$withdraw_filter_date_to_shamsi   = isset($_GET['withdraw_filter_date_to_shamsi']) ? sanitize_text_field($_GET['withdraw_filter_date_to_shamsi']) : '';

// اگر تاریخ‌ها خالی هستند، تاریخ پیش‌فرض امروز را تنظیم کن
if (empty($withdraw_filter_date_from_shamsi) && empty($withdraw_filter_date_to_shamsi)) {
    $withdraw_filter_date_from_shamsi = $today_shamsi;
    $withdraw_filter_date_to_shamsi = $today_shamsi;
    $withdraw_filter_date_from = $today_gregorian;
    $withdraw_filter_date_to = $today_gregorian;
} else {
    if (!empty($withdraw_filter_date_from_shamsi)) {
        $withdraw_filter_date_from = sc_shamsi_to_gregorian_date($withdraw_filter_date_from_shamsi);
    } elseif (isset($_GET['withdraw_filter_date_from']) && !empty($_GET['withdraw_filter_date_from'])) {
        $withdraw_filter_date_from = sanitize_text_field($_GET['withdraw_filter_date_from']);
        $withdraw_filter_date_from_shamsi = sc_date_shamsi_date_only($withdraw_filter_date_from);
    }

    if (!empty($withdraw_filter_date_to_shamsi)) {
        $withdraw_filter_date_to = sc_shamsi_to_gregorian_date($withdraw_filter_date_to_shamsi);
    } elseif (isset($_GET['withdraw_filter_date_to']) && !empty($_GET['withdraw_filter_date_to'])) {
        $withdraw_filter_date_to = sanitize_text_field($_GET['withdraw_filter_date_to']);
        $withdraw_filter_date_to_shamsi = sc_date_shamsi_date_only($withdraw_filter_date_to);
    }
}

$withdraw_where   = ["coach_id = %d"];
$withdraw_values  = [$coach_id];

if ($withdraw_filter_status !== 'all') {
    $withdraw_where[]  = "status = %s";
    $withdraw_values[] = $withdraw_filter_status;
}

if (!empty($withdraw_filter_date_from)) {
    $withdraw_where[]  = "DATE(created_at) >= %s";
    $withdraw_values[] = $withdraw_filter_date_from;
}

if (!empty($withdraw_filter_date_to)) {
    $withdraw_where[]  = "DATE(created_at) <= %s";
    $withdraw_values[] = $withdraw_filter_date_to;
}

$withdraw_where_clause = implode(' AND ', $withdraw_where);

// دریافت درخواست‌های برداشت
$withdrawal_requests = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $withdrawal_table 
     WHERE $withdraw_where_clause
     ORDER BY created_at DESC 
     LIMIT 50",
    $withdraw_values
));
?>

<div class="wrap sc-coach-panel-wrap">
    <div class="sc-coach-panel-header">
        <h1 class="sc-coach-panel-title">درخواست‌های برداشت</h1>
        <p class="sc-coach-panel-desc">ثبت و پیگیری درخواست‌های برداشت از کیف پول.</p>
        <p style="margin-top: 8px;"><a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-wallet')); ?>" class="button">کیف پول</a></p>
    </div>
    
    <?php if ($withdrawal_message): ?>
        <div class="notice notice-<?php echo $withdrawal_message_type; ?> is-dismissible">
            <p><?php echo esc_html($withdrawal_message); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- نمایش موجودی -->
    <div class="notice notice-info" style="padding: 20px; margin: 20px 0;">
        <h2 style="margin-top: 0;">💰 موجودی کیف پول: 
            <strong style="font-size: 24px; color: <?php echo $wallet_balance < 0 ? '#d63638' : '#2271b1'; ?>;">
                <?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> تومان
            </strong>
        </h2>
        <?php if ($wallet_balance < 0): ?>
            <p style="margin-top: 8px; color: #d63638;">
                بدهی کیف پول: <strong><?php echo esc_html(sc_format_amount_display(abs($wallet_balance))); ?> تومان</strong>
            </p>
        <?php endif; ?>
        <?php if ($min_withdrawal > 0): ?>
            <p>حداقل مبلغ برداشت: <strong><?php echo esc_html(sc_format_amount_display($min_withdrawal)); ?> تومان</strong></p>
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
                               value="<?php echo esc_attr(number_format($wallet_balance < 0 ? 0 : $wallet_balance, 0, '.', ',')); ?>" 
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

        <!-- فیلترهای لیست درخواست‌های برداشت -->
        <div class="sc-filter-wrapper" style="background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 8px;">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                <input type="hidden" name="page" value="sc-coach-withdrawals">

                <div style="min-width: 180px;">
                    <label for="withdraw_filter_status">وضعیت:</label><br>
                    <select name="withdraw_filter_status" id="withdraw_filter_status" style="width: 100%;">
                        <option value="all" <?php selected($withdraw_filter_status, 'all'); ?>>همه</option>
                        <option value="pending" <?php selected($withdraw_filter_status, 'pending'); ?>>در انتظار تایید</option>
                        <option value="approved" <?php selected($withdraw_filter_status, 'approved'); ?>>تایید شده (منتظر پرداخت)</option>
                        <option value="rejected" <?php selected($withdraw_filter_status, 'rejected'); ?>>رد شده</option>
                        <option value="paid" <?php selected($withdraw_filter_status, 'paid'); ?>>تایید و پرداخت شده</option>
                    </select>
                </div>

                <div style="min-width: 180px;">
                    <label for="withdraw_filter_date_from_shamsi">از تاریخ:</label><br>
                    <?php
                    if (empty($withdraw_filter_date_from_shamsi) && !empty($withdraw_filter_date_from)) {
                        $withdraw_filter_date_from_shamsi = sc_date_shamsi_date_only($withdraw_filter_date_from);
                    }
                    ?>
                    <input type="text"
                           name="withdraw_filter_date_from_shamsi"
                           id="withdraw_filter_date_from_shamsi"
                           value="<?php echo esc_attr($withdraw_filter_date_from_shamsi); ?>"
                           class="regular-text persian-date-input"
                           placeholder="از تاریخ (شمسی)"
                           readonly
                           style="width: 100%;">
                    <input type="hidden" name="withdraw_filter_date_from" id="withdraw_filter_date_from" value="<?php echo esc_attr($withdraw_filter_date_from); ?>">
                </div>

                <div style="min-width: 180px;">
                    <label for="withdraw_filter_date_to_shamsi">تا تاریخ:</label><br>
                    <?php
                    if (empty($withdraw_filter_date_to_shamsi) && !empty($withdraw_filter_date_to)) {
                        $withdraw_filter_date_to_shamsi = sc_date_shamsi_date_only($withdraw_filter_date_to);
                    }
                    ?>
                    <input type="text"
                           name="withdraw_filter_date_to_shamsi"
                           id="withdraw_filter_date_to_shamsi"
                           value="<?php echo esc_attr($withdraw_filter_date_to_shamsi); ?>"
                           class="regular-text persian-date-input"
                           placeholder="تا تاریخ (شمسی)"
                           readonly
                           style="width: 100%;">
                    <input type="hidden" name="withdraw_filter_date_to" id="withdraw_filter_date_to" value="<?php echo esc_attr($withdraw_filter_date_to); ?>">
                </div>

                <div style="min-width: 140px;">
                    <button type="submit" class="button button-primary">اعمال فیلتر</button>
                    <a href="<?php echo admin_url('admin.php?page=sc-coach-withdrawals'); ?>" class="button">پاک کردن</a>
                </div>
            </form>
        </div>
        
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
                            <td><strong><?php echo esc_html(sc_format_amount_display($request->amount)); ?> تومان</strong></td>
                            <td>
                                <?php
                                $status_labels = [
                                    'pending' => ['label' => 'در انتظار تایید', 'color' => '#f0a000'],
                                    'approved' => ['label' => 'تایید شده (منتظر پرداخت)', 'color' => '#2271b1'],
                                    'rejected' => ['label' => 'رد شده', 'color' => '#d63638'],
                                    'paid' => ['label' => 'تایید و پرداخت شده', 'color' => '#00a32a']
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
