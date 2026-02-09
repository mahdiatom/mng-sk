<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';

// پردازش فرم
$message = '';
$message_type = '';

if (isset($_POST['sc_deduct_wallet']) && check_admin_referer('sc_deduct_wallet_nonce', 'sc_deduct_wallet_nonce')) {
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';

    if ($member_id <= 0) {
        $message = 'لطفاً بازیکن را انتخاب کنید.';
        $message_type = 'error';
    } elseif ($amount <= 0) {
        $message = 'مبلغ باید بیشتر از صفر باشد.';
        $message_type = 'error';
    } elseif (empty($description)) {
        $message = 'لطفاً توضیحات هزینه را وارد کنید.';
        $message_type = 'error';
    } else {
        // بررسی موجودی کافی
        $current_balance = sc_get_wallet_balance($member_id);
        $max_negative = sc_get_wallet_max_negative_balance();
        
        if ($current_balance - $amount < -$max_negative) {
            $message = 'موجودی کیف پول نمی‌تواند کمتر از ' . number_format($max_negative, 0, '.', ',') . ' تومان باشد. موجودی فعلی: ' . number_format($current_balance, 0, '.', ',') . ' تومان';
            $message_type = 'error';
        } else {
            $result = sc_deduct_wallet($member_id, $amount, $description);
            if ($result['success']) {
                $message = 'مبلغ از کیف پول کسر شد. موجودی جدید: ' . number_format($result['balance_after'], 0, '.', ',') . ' تومان';
                $message_type = 'success';
                // پاک کردن فرم
                $_POST = [];
            } else {
                $message = $result['message'];
                $message_type = 'error';
            }
        }
    }
}

// دریافت لیست اعضا
$members = $wpdb->get_results(
    "SELECT id, first_name, last_name, national_id, player_phone
     FROM $members_table 
     WHERE is_active = 1 
     ORDER BY last_name, first_name"
);
?>

<div class="wrap">
    <h1>کاهش کیف پول</h1>
    
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" action="" style="max-width: 600px; margin-top: 20px;">
        <?php wp_nonce_field('sc_deduct_wallet_nonce', 'sc_deduct_wallet_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="member_id">بازیکن <span style="color: red;">*</span></label>
                </th>
                <td>
                    <select name="member_id" id="member_id" class="regular-text" required style="width: 100%;">
                        <option value="">-- انتخاب بازیکن --</option>
                        <?php foreach ($members as $member) : 
                            $selected = isset($_POST['member_id']) && $_POST['member_id'] == $member->id ? 'selected' : '';
                            $balance = sc_get_wallet_balance($member->id);
                        ?>
                            <option value="<?php echo esc_attr($member->id); ?>" <?php echo $selected; ?> data-balance="<?php echo esc_attr($balance); ?>">
                                <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id . ' (موجودی: ' . number_format($balance, 0, '.', ',') . ' تومان)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">بازیکنی که می‌خواهید از کیف پولش کسر کنید را انتخاب کنید.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="amount">مبلغ کسر (تومان) <span style="color: red;">*</span></label>
                </th>
                <td>
                    <input type="number" 
                           name="amount" 
                           id="amount" 
                           class="regular-text" 
                           min="0" 
                           step="1000" 
                           value="<?php echo isset($_POST['amount']) ? esc_attr($_POST['amount']) : ''; ?>" 
                           required>
                    <p class="description" id="balance_info">
                        موجودی فعلی: <span id="current_balance">-</span> تومان
                        <?php if (sc_get_wallet_max_negative_balance() > 0) : ?>
                            - حداقل موجودی مجاز: <?php echo number_format(-sc_get_wallet_max_negative_balance(), 0, '.', ','); ?> تومان
                        <?php endif; ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="description">توضیحات هزینه <span style="color: red;">*</span></label>
                </th>
                <td>
                    <textarea name="description" 
                              id="description" 
                              class="large-text" 
                              rows="3" 
                              placeholder="توضیحات هزینه (مثلاً: جریمه تأخیر، هزینه اضافی و...)" 
                              required><?php echo isset($_POST['description']) ? esc_textarea($_POST['description']) : ''; ?></textarea>
                    <p class="description">لطفاً دلیل کسر این مبلغ را وارد کنید (مثلاً: جریمه تأخیر، هزینه اضافی و...)</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="sc_deduct_wallet" class="button button-primary" value="کسر از کیف پول">
            <a href="<?php echo admin_url('admin.php?page=sc-wallet'); ?>" class="button">بازگشت به لیست</a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // نمایش موجودی هنگام انتخاب بازیکن
    $('#member_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var balance = selectedOption.data('balance');
        if (balance !== undefined) {
            $('#current_balance').text(parseFloat(balance).toLocaleString('fa-IR'));
        } else {
            $('#current_balance').text('-');
        }
    });

    // فرمت کردن مبلغ با کاما
    $('#amount').on('input', function() {
        var value = $(this).val().replace(/,/g, '');
        if (value && !isNaN(value)) {
            $(this).val(parseFloat(value));
        }
    });
});
</script>

