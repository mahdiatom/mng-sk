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

if (empty($withdraw_filter_date_from_shamsi) && !empty($withdraw_filter_date_from)) {
    $withdraw_filter_date_from_shamsi = sc_date_shamsi_date_only($withdraw_filter_date_from);
}
if (empty($withdraw_filter_date_to_shamsi) && !empty($withdraw_filter_date_to)) {
    $withdraw_filter_date_to_shamsi = sc_date_shamsi_date_only($withdraw_filter_date_to);
}

$active_filters_count = 0;
if ($withdraw_filter_status !== 'all') {
    $active_filters_count++;
}
if (isset($_GET['withdraw_filter_date_from_shamsi']) || isset($_GET['withdraw_filter_date_to_shamsi'])
    || isset($_GET['withdraw_filter_date_from']) || isset($_GET['withdraw_filter_date_to'])) {
    $active_filters_count++;
}
$filters_open = $active_filters_count > 0;
?>

<div class="wrap sc-wallet-list-wrap sc-coach-wallet-list-page">
    <div class="sc-wallet-list-header">
        <div class="sc-wallet-list-header-text">
            <h1 class="sc-wallet-list-title">درخواست‌های برداشت</h1>
            <p class="sc-wallet-list-desc">ثبت درخواست برداشت و پیگیری وضعیت درخواست‌های شما.</p>
        </div>
        <div class="sc-wallet-list-header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-wallet')); ?>" class="sc-wallet-list-add-btn">کیف پول</a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-salary')); ?>" class="sc-wallet-list-export-btn">لیست دستمزد</a>
        </div>
    </div>

    <?php if ($withdrawal_message): ?>
        <div class="notice notice-<?php echo $withdrawal_message_type; ?> is-dismissible">
            <p><?php echo esc_html($withdrawal_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="sc-wallet-list-stats">
        <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--green">
            <div class="sc-wallet-list-stat-label">موجودی کیف پول</div>
            <div class="sc-wallet-list-stat-value"><?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> <small>تومان</small></div>
        </div>
        <?php if ($wallet_balance < 0): ?>
        <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--red">
            <div class="sc-wallet-list-stat-label">بدهی کیف پول</div>
            <div class="sc-wallet-list-stat-value"><?php echo esc_html(sc_format_amount_display(abs($wallet_balance))); ?> <small>تومان</small></div>
        </div>
        <?php endif; ?>
        <?php if ($min_withdrawal > 0): ?>
        <div class="sc-wallet-list-stat-card sc-wallet-list-stat-card--amber">
            <div class="sc-wallet-list-stat-label">حداقل مبلغ برداشت</div>
            <div class="sc-wallet-list-stat-value"><?php echo esc_html(sc_format_amount_display($min_withdrawal)); ?> <small>تومان</small></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="sc-wallet-list-filters-card sc-coach-withdrawal-form-card">
        <div class="sc-wallet-list-panel-header" style="margin-bottom:12px;">
            <h2 style="margin:0;font-size:1rem;font-weight:800;color:#1e1b2e;">ثبت درخواست برداشت جدید</h2>
        </div>
        <form method="POST" action="">
            <?php wp_nonce_field('coach_withdrawal_nonce'); ?>
            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="withdrawal_amount">مبلغ درخواستی (تومان)</label>
                    <input type="text"
                           id="withdrawal_amount"
                           name="withdrawal_amount"
                           value="<?php echo esc_attr(number_format($wallet_balance < 0 ? 0 : $wallet_balance, 0, '.', ',')); ?>"
                           class="sc-filter-control"
                           required>
                    <p class="description" style="margin-top:6px;">پیش‌فرض: کل موجودی کیف پول. می‌توانید تغییر دهید.</p>
                </div>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="withdrawal_notes">یادداشت (اختیاری)</label>
                    <textarea id="withdrawal_notes"
                              name="withdrawal_notes"
                              rows="3"
                              class="sc-filter-control"
                              style="height:auto;min-height:88px;"></textarea>
                </div>
            </div>
            <div class="sc-wallet-list-filters-actions">
                <input type="submit" name="submit_withdrawal" class="button button-primary" value="ثبت درخواست برداشت">
            </div>
        </form>
    </div>

    <div class="sc-wallet-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-wallet-list-filters-toolbar">
            <button type="button"
                    class="sc-wallet-list-filters-toggle"
                    id="sc-coach-withdrawals-filters-toggle"
                    aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>"
                    aria-controls="sc-coach-withdrawals-filters-panel">
                <span class="sc-wallet-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sc-wallet-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها">
                    <?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?>
                </span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-wallet-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-wallet-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-withdrawals')); ?>" class="sc-wallet-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>

        <form method="GET" action="" class="sc-wallet-list-filters-panel" id="sc-coach-withdrawals-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>
            <input type="hidden" name="page" value="sc-coach-withdrawals">

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="withdraw_filter_date_from_shamsi">از تاریخ</label>
                    <input type="text"
                           name="withdraw_filter_date_from_shamsi"
                           id="withdraw_filter_date_from_shamsi"
                           value="<?php echo esc_attr($withdraw_filter_date_from_shamsi); ?>"
                           class="sc-filter-control persian-date-input"
                           placeholder="از تاریخ"
                           readonly>
                    <input type="hidden"
                           name="withdraw_filter_date_from"
                           id="withdraw_filter_date_from"
                           value="<?php echo esc_attr($withdraw_filter_date_from); ?>">
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="withdraw_filter_date_to_shamsi">تا تاریخ</label>
                    <input type="text"
                           name="withdraw_filter_date_to_shamsi"
                           id="withdraw_filter_date_to_shamsi"
                           value="<?php echo esc_attr($withdraw_filter_date_to_shamsi); ?>"
                           class="sc-filter-control persian-date-input"
                           placeholder="تا تاریخ"
                           readonly>
                    <input type="hidden"
                           name="withdraw_filter_date_to"
                           id="withdraw_filter_date_to"
                           value="<?php echo esc_attr($withdraw_filter_date_to); ?>">
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="withdraw_filter_status">وضعیت</label>
                    <select name="withdraw_filter_status" id="withdraw_filter_status" class="sc-filter-control">
                        <option value="all" <?php selected($withdraw_filter_status, 'all'); ?>>همه</option>
                        <option value="pending" <?php selected($withdraw_filter_status, 'pending'); ?>>در انتظار تایید</option>
                        <option value="approved" <?php selected($withdraw_filter_status, 'approved'); ?>>تایید شده (منتظر پرداخت)</option>
                        <option value="rejected" <?php selected($withdraw_filter_status, 'rejected'); ?>>رد شده</option>
                        <option value="paid" <?php selected($withdraw_filter_status, 'paid'); ?>>تایید و پرداخت شده</option>
                    </select>
                </div>
            </div>

            <div class="sc-wallet-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-coach-withdrawals')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </form>
    </div>

    <div class="sc-wallet-list-table-card">
        <?php if (empty($withdrawal_requests)): ?>
            <p style="text-align:center;padding:32px 16px;color:#6b7280;margin:0;">هیچ درخواست برداشتی ثبت نشده است.</p>
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
                    <?php
                    $row = 1;
                    $status_labels = [
                        'pending' => 'در انتظار تایید',
                        'approved' => 'تایید شده (منتظر پرداخت)',
                        'rejected' => 'رد شده',
                        'paid' => 'تایید و پرداخت شده',
                    ];
                    $status_badge_map = [
                        'pending' => 'sc-badge--warning',
                        'approved' => 'sc-badge--purple',
                        'rejected' => 'sc-badge--danger',
                        'paid' => 'sc-badge--success',
                    ];
                    ?>
                    <?php foreach ($withdrawal_requests as $request): ?>
                        <?php
                        $status = $request->status ?? '';
                        $status_label = $status_labels[$status] ?? $status;
                        $status_badge = $status_badge_map[$status] ?? 'sc-badge--muted';
                        $notes = $request->notes ?? '';
                        $notes_display = $notes === '' ? '—' : (mb_strlen($notes) > 50 ? mb_substr($notes, 0, 50) . '...' : $notes);
                        ?>
                        <tr>
                            <td><?php echo (int) $row++; ?></td>
                            <td><span class="sc-wallet-date"><?php echo esc_html(sc_date_shamsi($request->created_at, 'Y/m/d H:i')); ?></span></td>
                            <td><span class="sc-wallet-amount is-debit"><?php echo esc_html(sc_format_amount_display($request->amount)); ?></span></td>
                            <td>
                                <span class="sc-badge <?php echo esc_attr($status_badge); ?>"><?php echo esc_html($status_label); ?></span>
                                <?php if ($request->status === 'rejected' && $request->rejection_reason): ?>
                                    <br><small style="color: #dc2626;">دلیل: <?php echo esc_html($request->rejection_reason); ?></small>
                                <?php endif; ?>
                                <?php if ($request->status === 'paid' && $request->paid_at): ?>
                                    <br><small class="sc-wallet-date">پرداخت شده در: <?php echo esc_html(sc_date_shamsi($request->paid_at, 'Y/m/d H:i')); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($notes === ''): ?>
                                    <span class="sc-badge sc-badge--muted">—</span>
                                <?php else: ?>
                                    <span class="sc-wallet-desc" title="<?php echo esc_attr($notes); ?>"><?php echo esc_html($notes_display); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var $toggle = $('#sc-coach-withdrawals-filters-toggle');
    var $panel = $('#sc-coach-withdrawals-filters-panel');
    var $card = $toggle.closest('.sc-wallet-list-filters-card').not('.sc-coach-withdrawal-form-card');
    var $label = $toggle.find('.sc-wallet-list-filters-toggle-label');
    $toggle.on('click', function () {
        var isOpen = $card.hasClass('is-open');
        if (isOpen) {
            $card.removeClass('is-open');
            $panel.attr('hidden', true);
            $toggle.attr('aria-expanded', 'false');
            $label.text($label.data('label-closed'));
        } else {
            $card.addClass('is-open');
            $panel.removeAttr('hidden');
            $toggle.attr('aria-expanded', 'true');
            $label.text($label.data('label-open'));
        }
    });

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
