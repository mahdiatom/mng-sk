<?php
if (!defined('ABSPATH')) {
    exit;
}

$sc_tax_pro = !function_exists('sc_is_pro_feature_tax_enabled') || sc_is_pro_feature_tax_enabled();
$sc_membership_pro = !function_exists('sc_is_pro_feature_membership_enabled') || sc_is_pro_feature_membership_enabled();

if (!$sc_tax_pro && !$sc_membership_pro) {
    echo '<div class="notice notice-warning"><p>امکانات مالیات و عضویت در تنظیمات امکانات پرو غیرفعال هستند.</p></div>';
    return;
}

$tax_fee_enabled = (int) sc_get_setting('tax_fee_enabled', '0');
$tax_fee_mode = sc_get_tax_fee_mode();
$tax_fee_value = sc_get_tax_fee_value();
$tax_fee_title = sc_get_tax_fee_title();
$tax_fee_description = sc_get_tax_fee_description();
$tax_fee_apply_course = (int) sc_get_setting('tax_fee_apply_course', '0');
$tax_fee_apply_event = (int) sc_get_setting('tax_fee_apply_event', '0');
$tax_fee_apply_wallet = (int) sc_get_setting('tax_fee_apply_wallet', '0');
$tax_fee_apply_shop = (int) sc_get_setting('tax_fee_apply_shop', '0');
$tax_fee_show_pay_breakdown = (int) sc_get_setting('tax_fee_show_pay_breakdown', '0');

$registration_fee_enabled = (int) sc_get_setting('registration_fee_enabled', '0');
$registration_fee_amount = sc_get_registration_fee_amount();
$registration_fee_title = sc_get_registration_fee_title();
$registration_fee_description = sc_get_registration_fee_description();
?>
<form method="POST" action="">
    <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>

    <?php if ($sc_tax_pro) : ?>
    <h2 class="title">مالیات و ارزش افزوده</h2>
    <p class="description">این هزینه به مبلغ فاکتورها (قبل از جریمه تأخیر) اضافه می‌شود.</p>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="tax_fee_enabled">فعال‌سازی</label></th>
            <td>
                <label>
                    <input type="checkbox" name="tax_fee_enabled" id="tax_fee_enabled" value="1" <?php checked($tax_fee_enabled, 1); ?>>
                    افزودن مالیات / ارزش افزوده به فاکتورها
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="tax_fee_title">عنوان در فاکتور</label></th>
            <td>
                <input type="text" name="tax_fee_title" id="tax_fee_title" class="regular-text" value="<?php echo esc_attr($tax_fee_title); ?>" placeholder="مالیات و ارزش افزوده">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="tax_fee_mode">نحوه محاسبه</label></th>
            <td>
                <select name="tax_fee_mode" id="tax_fee_mode">
                    <option value="percent" <?php selected($tax_fee_mode, 'percent'); ?>>درصد از کل فاکتور</option>
                    <option value="fixed" <?php selected($tax_fee_mode, 'fixed'); ?>>مبلغ ثابت (تومان)</option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="tax_fee_value">مقدار</label></th>
            <td>
                <input type="number" name="tax_fee_value" id="tax_fee_value" class="regular-text" min="0" step="0.01" value="<?php echo esc_attr($tax_fee_value); ?>">
                <p class="description">برای درصد: مثلاً 9 یعنی ۹٪ — برای ثابت: مبلغ تومان.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">اعمال برای</th>
            <td>
                <label><input type="checkbox" name="tax_fee_apply_course" value="1" <?php checked($tax_fee_apply_course, 1); ?>> دوره</label><br>
                <label><input type="checkbox" name="tax_fee_apply_event" value="1" <?php checked($tax_fee_apply_event, 1); ?>> رویداد / مسابقه</label><br>
                <label><input type="checkbox" name="tax_fee_apply_wallet" value="1" <?php checked($tax_fee_apply_wallet, 1); ?>> شارژ کیف پول</label><br>
                <label><input type="checkbox" name="tax_fee_apply_shop" value="1" <?php checked($tax_fee_apply_shop, 1); ?>> فروشگاه</label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="tax_fee_description">توضیحات</label></th>
            <td>
                <textarea name="tax_fee_description" id="tax_fee_description" class="large-text" rows="3"><?php echo esc_textarea($tax_fee_description); ?></textarea>
                <p class="description">نمایش اختیاری برای کاربر (در صورت نیاز در فاکتور).</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="tax_fee_show_pay_breakdown">جزئیات در صفحه پرداخت</label></th>
            <td>
                <label>
                    <input type="checkbox" name="tax_fee_show_pay_breakdown" id="tax_fee_show_pay_breakdown" value="1" <?php checked($tax_fee_show_pay_breakdown, 1); ?>>
                    نمایش جزئیات قیمت (مبلغ پایه، مالیات و …) قبل از جمع کل در صفحه «در انتظار پرداخت»
                </label>
                <p class="description">پیش‌فرض غیرفعال — فقط جمع کل نمایش داده می‌شود.</p>
            </td>
        </tr>
    </table>
    <?php endif; ?>

    <?php if ($sc_tax_pro && $sc_membership_pro) : ?>
    <hr>
    <?php endif; ?>

    <?php if ($sc_membership_pro) : ?>
    <h2 class="title">هزینه ثبت‌نام (عضویت)</h2>
    <p class="description">با فعال بودن، کاربر جدید باید یک‌بار هزینه ثبت‌نام را پرداخت کند تا پنل برایش باز شود. پرداخت مستقیم از درگاه انجام می‌شود.</p>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="registration_fee_enabled">فعال‌سازی</label></th>
            <td>
                <label>
                    <input type="checkbox" name="registration_fee_enabled" id="registration_fee_enabled" value="1" <?php checked($registration_fee_enabled, 1); ?>>
                    الزام پرداخت هزینه ثبت‌نام قبل از دسترسی به پنل
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="registration_fee_title">عنوان فاکتور</label></th>
            <td>
                <input type="text" name="registration_fee_title" id="registration_fee_title" class="regular-text" value="<?php echo esc_attr($registration_fee_title); ?>" placeholder="هزینه ثبت‌نام (عضویت)">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="registration_fee_amount">مبلغ (تومان)</label></th>
            <td>
                <input type="text" name="registration_fee_amount" id="registration_fee_amount" class="regular-text" dir="ltr"
                       value="<?php echo $registration_fee_amount > 0 ? esc_attr(number_format($registration_fee_amount, 0, '.', ',')) : ''; ?>">
                <input type="hidden" name="registration_fee_amount_raw" id="registration_fee_amount_raw" value="<?php echo esc_attr($registration_fee_amount); ?>">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="registration_fee_description">توضیحات</label></th>
            <td>
                <textarea name="registration_fee_description" id="registration_fee_description" class="large-text" rows="4"><?php echo esc_textarea($registration_fee_description); ?></textarea>
                <p class="description">در پاپ‌آپ پنل کاربر نمایش داده می‌شود.</p>
            </td>
        </tr>
    </table>
    <?php endif; ?>

    <p class="submit">
        <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات">
    </p>
</form>
<?php if ($sc_membership_pro) : ?>
<script>
(function () {
    var amountInput = document.getElementById('registration_fee_amount');
    var amountRaw = document.getElementById('registration_fee_amount_raw');
    if (!amountInput || !amountRaw) return;
    amountInput.addEventListener('input', function () {
        var v = (amountInput.value || '').replace(/,/g, '');
        amountRaw.value = v;
    });
})();
</script>
<?php endif; ?>
