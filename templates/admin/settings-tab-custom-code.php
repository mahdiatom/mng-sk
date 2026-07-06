<?php
if (!defined('ABSPATH')) {
    exit;
}

$custom_css_admin  = sc_get_custom_code('custom_css_admin');
$custom_css_public = sc_get_custom_code('custom_css_public');
$custom_js_admin   = sc_get_custom_code('custom_js_admin');
$custom_js_public  = sc_get_custom_code('custom_js_public');
?>

<form method="POST" action="" class="sc-settings-custom-code-form">
    <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>

    <p class="description" style="margin-bottom:18px;">
        کدهای CSS و JavaScript سفارشی در صفحات افزونه SportClub اعمال می‌شوند. فقط توسط مدیر سیستم قابل ویرایش است.
    </p>

    <div class="sc-settings-custom-code-grid">
        <div class="sc-settings-custom-code-panel">
            <h3 class="sc-settings-custom-code-panel__title">CSS — پنل مدیریت</h3>
            <p class="description">در تمام صفحات ادمین افزونه SportClub بارگذاری می‌شود.</p>
            <textarea name="custom_css_admin"
                      id="custom_css_admin"
                      class="sc-settings-code-field"
                      rows="14"
                      spellcheck="false"
                      dir="ltr"><?php echo esc_textarea($custom_css_admin); ?></textarea>
        </div>

        <div class="sc-settings-custom-code-panel">
            <h3 class="sc-settings-custom-code-panel__title">CSS — بخش عمومی (کاربران)</h3>
            <p class="description">در صفحات عمومی سایت (حساب کاربری، ثبت‌نام و...) اعمال می‌شود.</p>
            <textarea name="custom_css_public"
                      id="custom_css_public"
                      class="sc-settings-code-field"
                      rows="14"
                      spellcheck="false"
                      dir="ltr"><?php echo esc_textarea($custom_css_public); ?></textarea>
        </div>

        <div class="sc-settings-custom-code-panel">
            <h3 class="sc-settings-custom-code-panel__title">JavaScript — پنل مدیریت</h3>
            <p class="description">پس از اسکریپت‌های ادمین افزونه اجرا می‌شود.</p>
            <textarea name="custom_js_admin"
                      id="custom_js_admin"
                      class="sc-settings-code-field"
                      rows="14"
                      spellcheck="false"
                      dir="ltr"><?php echo esc_textarea($custom_js_admin); ?></textarea>
        </div>

        <div class="sc-settings-custom-code-panel">
            <h3 class="sc-settings-custom-code-panel__title">JavaScript — بخش عمومی (کاربران)</h3>
            <p class="description">پس از اسکریپت‌های عمومی افزونه اجرا می‌شود.</p>
            <textarea name="custom_js_public"
                      id="custom_js_public"
                      class="sc-settings-code-field"
                      rows="14"
                      spellcheck="false"
                      dir="ltr"><?php echo esc_textarea($custom_js_public); ?></textarea>
        </div>
    </div>

    <p class="submit">
        <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره کد سفارشی">
    </p>
</form>
