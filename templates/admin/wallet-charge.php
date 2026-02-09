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

if (isset($_POST['sc_charge_wallet']) && check_admin_referer('sc_charge_wallet_nonce', 'sc_charge_wallet_nonce')) {
    $member_id = isset($_POST['member_id']) ? absint($_POST['member_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';

    if ($member_id <= 0) {
        $message = 'لطفاً بازیکن را انتخاب کنید.';
        $message_type = 'error';
    } elseif ($amount <= 0) {
        $message = 'مبلغ باید بیشتر از صفر باشد.';
        $message_type = 'error';
    } else {
        // بررسی حداقل مبلغ شارژ
        $min_charge = sc_get_wallet_min_charge();
        if ($amount < $min_charge) {
            $message = 'حداقل مبلغ شارژ ' . number_format($min_charge, 0, '.', ',') . ' تومان است.';
            $message_type = 'error';
        } else {
            // بررسی حداکثر مبلغ شارژ
            $max_charge = sc_get_wallet_max_charge();
            if ($max_charge > 0 && $amount > $max_charge) {
                $message = 'حداکثر مبلغ شارژ ' . number_format($max_charge, 0, '.', ',') . ' تومان است.';
                $message_type = 'error';
            } else {
                $result = sc_charge_wallet($member_id, $amount, $description);
                if ($result['success']) {
                    $message = 'کیف پول با موفقیت شارژ شد. موجودی جدید: ' . number_format($result['balance_after'], 0, '.', ',') . ' تومان';
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
    <h1>شارژ کیف پول</h1>
    
    <?php if ($message) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" action="" style="max-width: 600px; margin-top: 20px;">
        <?php wp_nonce_field('sc_charge_wallet_nonce', 'sc_charge_wallet_nonce'); ?>

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
                        ?>
                            <option value="<?php echo esc_attr($member->id); ?>" <?php echo $selected; ?>>
                                <?php echo esc_html($member->first_name . ' ' . $member->last_name . ' - ' . $member->national_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">بازیکنی که می‌خواهید کیف پولش را شارژ کنید را انتخاب کنید.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="amount">مبلغ شارژ (تومان) <span style="color: red;">*</span></label>
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
                    <p class="description">
                        حداقل مبلغ: <?php echo number_format(sc_get_wallet_min_charge(), 0, '.', ','); ?> تومان
                        <?php if (sc_get_wallet_max_charge() > 0) : ?>
                            - حداکثر مبلغ: <?php echo number_format(sc_get_wallet_max_charge(), 0, '.', ','); ?> تومان
                        <?php endif; ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="description">توضیحات</label>
                </th>
                <td>
                    <textarea name="description" 
                              id="description" 
                              class="large-text" 
                              rows="3" 
                              placeholder="توضیحات اختیاری برای این تراکنش"><?php echo isset($_POST['description']) ? esc_textarea($_POST['description']) : ''; ?></textarea>
                    <p class="description">توضیحات اختیاری برای این تراکنش شارژ (مثلاً: شارژ دستی توسط مدیر)</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="sc_charge_wallet" class="button button-primary" value="شارژ کیف پول">
            <a href="<?php echo admin_url('admin.php?page=sc-wallet'); ?>" class="button">بازگشت به لیست</a>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // فرمت کردن مبلغ با کاما
    $('#amount').on('input', function() {
        var value = $(this).val().replace(/,/g, '');
        if (value && !isNaN(value)) {
            $(this).val(parseFloat(value));
        }
    });
});
</script>

