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

$coach_id = isset($_GET['coach_id']) ? absint($_GET['coach_id']) : 0;
$coach = null;
if ($coach_id) {
    $coach = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $coaches_table WHERE id = %d",
        $coach_id
    ));
}

$action_message = '';
$action_message_type = '';

if (isset($_POST['submit_action']) && check_admin_referer('coach_wallet_action_nonce')) {
    $action_coach_id = absint($_POST['coach_id']);
    $action_type = sanitize_text_field($_POST['action_type']);
    $amount_input = isset($_POST['amount_raw']) && $_POST['amount_raw'] !== '' ? $_POST['amount_raw'] : (isset($_POST['amount']) ? $_POST['amount'] : '');
    $amount = floatval(str_replace(',', '', $amount_input));
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

$coaches = $wpdb->get_results(
    "SELECT id, first_name, last_name FROM $coaches_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC"
);

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

<div class="wrap sc-coach-wallet-manage-page-header sc-finance-page-header">
    <h1 class="wp-heading-inline">مدیریت کیف پول مربیان</h1>
    <hr class="wp-header-end">
    <p class="sc-coach-wallet-manage-subtitle">مربی را انتخاب کنید و عملیات شارژ یا کسر را انجام دهید.</p>
</div>
<div class="wrap sc-coach-wallet-manage-page-body sc-finance-page-body">
    <?php if ($action_message) : ?>
        <div class="notice notice-<?php echo esc_attr($action_message_type); ?> is-dismissible">
            <p><?php echo esc_html($action_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="sc-coach-wallet-manage-layout">
        <div class="sc-coach-wallet-manage-sidebar">
            <div class="sc-wallet-panel sc-finance-panel postbox choose_coach">
                <div class="postbox-header"><h2>انتخاب مربی</h2></div>
                <div class="inside">
                    <form method="GET" action="" class="sc-coach-wallet-select-form">
                        <input type="hidden" name="page" value="sc-coach-management-wallet">
                        <div class="sc-wallet-field-row">
                            <label for="coach_id">مربی</label>
                            <select name="coach_id" id="coach_id" onchange="this.form.submit();">
                                <option value="0">-- انتخاب مربی --</option>
                                <?php foreach ($coaches as $c) : ?>
                                    <option value="<?php echo esc_attr($c->id); ?>" <?php selected($coach_id, $c->id); ?>>
                                        <?php echo esc_html($c->first_name . ' ' . $c->last_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="sc-coach-wallet-manage-main">
            <?php if ($coach) : ?>
                <div class="sc-wallet-balance-card sc-finance-panel postbox info_balance_wallet">
                    <div class="inside sc-wallet-balance-inner">
                        <div class="sc-wallet-balance-meta">
                            <span class="sc-wallet-balance-coach"><?php echo esc_html($coach->first_name . ' ' . $coach->last_name); ?></span>
                            <span class="sc-wallet-balance-label">موجودی کیف پول</span>
                        </div>
                        <div class="sc-wallet-balance-amount">
                            <?php echo esc_html(sc_format_amount_display($wallet_balance)); ?> <span>تومان</span>
                        </div>
                    </div>
                </div>

                <div class="sc-wallet-panel sc-finance-panel postbox charge_wallet">
                    <div class="postbox-header"><h2>شارژ / کسر دستی</h2></div>
                    <div class="inside sc-wallet-panel-fields">
                        <form method="POST" action="" class="sc-coach-wallet-action-form">
                            <?php wp_nonce_field('coach_wallet_action_nonce'); ?>
                            <input type="hidden" name="coach_id" value="<?php echo esc_attr($coach_id); ?>">

                            <div class="sc-wallet-field-row">
                                <label>نوع عملیات</label>
                                <div class="sc-wallet-action-types">
                                    <label class="sc-wallet-radio-label">
                                        <input type="radio" name="action_type" value="charge" checked>
                                        شارژ (افزودن به کیف پول)
                                    </label>
                                    <label class="sc-wallet-radio-label">
                                        <input type="radio" name="action_type" value="deduct">
                                        کسر (برداشت از کیف پول)
                                    </label>
                                </div>
                            </div>

                            <div class="sc-wallet-field-row">
                                <label for="amount">مبلغ (تومان) <span class="required">*</span></label>
                                <input type="text"
                                       id="amount"
                                       name="amount"
                                       class="regular-text"
                                       placeholder="0"
                                       dir="ltr"
                                       inputmode="numeric"
                                       value="<?php echo isset($_POST['amount']) ? esc_attr($_POST['amount']) : ''; ?>"
                                       required>
                                <input type="hidden"
                                       id="amount_raw"
                                       name="amount_raw"
                                       value="<?php echo isset($_POST['amount_raw']) ? esc_attr($_POST['amount_raw']) : (isset($_POST['amount']) ? esc_attr($_POST['amount']) : ''); ?>">
                            </div>

                            <div class="sc-wallet-field-row sc-wallet-field-row--full">
                                <label for="description">توضیحات</label>
                                <textarea id="description" name="description" rows="3" class="large-text" placeholder="توضیحات تراکنش"></textarea>
                            </div>

                            <p class="submit sc-coach-wallet-action-submit">
                                <input type="submit" name="submit_action" class="button button-primary" value="اجرای عملیات">
                            </p>
                        </form>
                    </div>
                </div>

                <div class="sc-wallet-panel sc-finance-panel postbox list_records_wallet">
                    <div class="postbox-header"><h2>تراکنش‌های کیف پول</h2></div>
                    <div class="inside">
                        <?php if (empty($transactions)) : ?>
                            <p class="sc-wallet-empty-text">هیچ تراکنشی ثبت نشده است.</p>
                        <?php else : ?>
                            <div class="back_table_list">
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
                                    foreach ($transactions as $transaction) :
                                    ?>
                                        <tr>
                                            <td><?php echo $row++; ?></td>
                                            <td><?php echo sc_date_shamsi($transaction->created_at, 'Y/m/d H:i'); ?></td>
                                            <td><?php echo esc_html($type_labels[$transaction->transaction_type] ?? $transaction->transaction_type); ?></td>
                                            <td>
                                                <?php if (in_array($transaction->transaction_type, ['charge', 'salary_percentage', 'salary_fixed'], true)) : ?>
                                                    <span class="sc-wallet-amount-plus">+<?php echo esc_html(sc_format_amount_display($transaction->amount)); ?></span>
                                                <?php else : ?>
                                                    <span class="sc-wallet-amount-minus"><?php echo esc_html(sc_format_amount_display(-$transaction->amount)); ?></span>
                                                <?php endif; ?>
                                                <small>تومان</small>
                                            </td>
                                            <td><?php echo esc_html(sc_format_amount_display($transaction->balance_before)); ?> تومان</td>
                                            <td><strong><?php echo esc_html(sc_format_amount_display($transaction->balance_after)); ?> تومان</strong></td>
                                            <td><?php echo esc_html($transaction->description ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="sc-wallet-panel sc-finance-panel postbox sc-wallet-empty-state">
                    <div class="inside">
                        <div class="sc-wallet-empty-icon">🏃</div>
                        <h2>مربی انتخاب نشده است</h2>
                        <p>از پنل کنار، یک مربی انتخاب کنید تا موجودی و تراکنش‌ها نمایش داده شود.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
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
