<?php
if (!defined('ABSPATH')) {
    exit;
}

$sc_token_club          = sc_get_setting('sc_token_club', '');
$sc_botname_club        = sc_get_setting('sc_botname_club', '');
$sc_bale_safir_api_key  = sc_get_setting('sc_bale_safir_api_key', '');
$sc_bale_safir_bot_id   = sc_get_setting('sc_bale_safir_bot_id', '');
$sc_bale_bot_enabled    = (int) sc_get_setting('sc_bale_bot_enabled', 1);
$sc_bale_use_miniapp    = (int) sc_get_setting('sc_bale_use_miniapp', 1);
$sc_bale_webhook_secret = sc_get_setting('sc_bale_webhook_secret', '');
$webhook_url            = function_exists('bale_get_webhook_url') ? bale_get_webhook_url() : '';
$bot_username           = function_exists('bale_get_bot_username') ? bale_get_bot_username() : '';
$miniapp_url            = $bot_username ? 'https://ble.ir/' . rawurlencode($bot_username) . '?startapp' : '';
?>
<form method="POST" action="" class="sc-bale-admin-wrap">
    <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>

    <div class="sc-bale-hint">
        پیام‌های ربات فقط به اعضایی ارسال می‌شود که <strong>Chat ID</strong> دارند (از طریق صفحه «اتصال به ربات» متصل شده‌اند).
        برای کاربران بدون Chat ID می‌توانید از سرویس <a href="https://docs.bale.ai/safir" target="_blank" rel="noopener">سفیر بله</a> استفاده کنید.
    </div>

    <div class="sc-bale-card">
        <h3>تنظیمات اصلی ربات</h3>
        <table class="form-table">
            <tr>
                <th scope="row">فعال‌سازی ربات</th>
                <td>
                    <label>
                        <input type="checkbox" name="sc_bale_bot_enabled" value="1" <?php checked($sc_bale_bot_enabled, 1); ?>>
                        ارسال پیام از طریق ربات بله فعال باشد
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_token_club">توکن ربات بله</label></th>
                <td>
                    <input type="password" name="sc_token_club" id="sc_token_club"
                           value="<?php echo esc_attr($sc_token_club); ?>" class="regular-text" autocomplete="off">
                    <p class="description">از <code>@botfather</code> در بله دریافت می‌شود.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_botname_club">نام کاربری ربات</label></th>
                <td>
                    <input type="text" name="sc_botname_club" id="sc_botname_club"
                           value="<?php echo esc_attr($sc_botname_club); ?>" class="regular-text" placeholder="mahdi_bot">
                    <p class="description">بدون @ — مثال: <code>mahdi_bot</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row">مینی‌اپ</th>
                <td>
                    <label>
                        <input type="checkbox" name="sc_bale_use_miniapp" value="1" <?php checked($sc_bale_use_miniapp, 1); ?>>
                        لینک‌های ربات در قالب مینی‌اپ باز شوند (<code>web_app</code>)
                    </label>
                    <?php if ($miniapp_url) : ?>
                        <p class="description">پیوند مینی‌اپ اصلی: <a href="<?php echo esc_url($miniapp_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($miniapp_url); ?></a></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="sc-bale-card">
        <h3>سرویس سفیر (ارسال بدون Chat ID)</h3>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="sc_bale_safir_api_key">API Access Key سفیر</label></th>
                <td>
                    <input type="password" name="sc_bale_safir_api_key" id="sc_bale_safir_api_key"
                           value="<?php echo esc_attr($sc_bale_safir_api_key); ?>" class="regular-text" autocomplete="off">
                    <p class="description">از <a href="https://business.bale.ai/" target="_blank" rel="noopener">پنل کسب‌وکار بله</a></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_bale_safir_bot_id">شناسه عددی بازو (Safir bot_id)</label></th>
                <td>
                    <input type="number" name="sc_bale_safir_bot_id" id="sc_bale_safir_bot_id"
                           value="<?php echo esc_attr($sc_bale_safir_bot_id); ?>" class="regular-text">
                </td>
            </tr>
        </table>
    </div>

    <div class="sc-bale-card">
        <h3>وب‌هوک</h3>
        <table class="form-table">
            <tr>
                <th scope="row">آدرس وب‌هوک</th>
                <td>
                    <div class="sc-bale-webhook-url" id="sc-bale-webhook-url"><?php echo esc_html($webhook_url); ?></div>
                    <p class="description">این آدرس را در API بله با <code>setWebhook</code> ثبت کنید یا از دکمه زیر استفاده کنید.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="sc_bale_webhook_secret">رمز امنیتی وب‌هوک</label></th>
                <td>
                    <input type="text" name="sc_bale_webhook_secret" id="sc_bale_webhook_secret"
                           value="<?php echo esc_attr($sc_bale_webhook_secret); ?>" class="regular-text">
                    <p class="description">اختیاری — برای محدود کردن درخواست‌های وب‌هوک</p>
                </td>
            </tr>
            <tr>
                <th scope="row">عملیات</th>
                <td>
                    <div class="sc-bale-webhook-box">
                        <button type="button" class="button button-primary" id="sc-bale-set-webhook">ثبت وب‌هوک</button>
                        <button type="button" class="button" id="sc-bale-refresh-webhook">بررسی وضعیت</button>
                        <button type="button" class="button" id="sc-bale-delete-webhook">حذف وب‌هوک</button>
                    </div>
                    <div class="sc-bale-webhook-status" id="sc-bale-webhook-status" style="display:none;"></div>
                </td>
            </tr>
        </table>
    </div>

    <p class="submit">
        <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات ربات بله">
    </p>
</form>
