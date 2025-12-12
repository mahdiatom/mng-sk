<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

// دریافت تب فعلی (باید قبل از پردازش فرم باشد)
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'penalty';

// پردازش فرم
if (isset($_POST['sc_save_settings']) && check_admin_referer('sc_settings_nonce', 'sc_settings_nonce')) {
    if ($current_tab === 'penalty') {
        $penalty_enabled = isset($_POST['penalty_enabled']) ? 1 : 0;
        $penalty_minutes = isset($_POST['penalty_minutes']) ? absint($_POST['penalty_minutes']) : 7;
        $penalty_amount = isset($_POST['penalty_amount']) ? floatval($_POST['penalty_amount']) : 500;

        sc_update_setting('penalty_enabled', $penalty_enabled, 'penalty');
        sc_update_setting('penalty_minutes', $penalty_minutes, 'penalty');
        sc_update_setting('penalty_amount', $penalty_amount, 'penalty');
        
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات جریمه با موفقیت ذخیره شد.</p></div>';
    } elseif ($current_tab === 'invoice') {
        $invoice_interval_minutes = isset($_POST['invoice_interval_minutes']) ? absint($_POST['invoice_interval_minutes']) : 60;

        sc_update_setting('invoice_interval_minutes', $invoice_interval_minutes, 'invoice');

        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات صورتحساب با موفقیت ذخیره شد.</p></div>';
    }
}

// پردازش فرم بازگشت به کارخانه
if (isset($_POST['sc_reset_factory']) && check_admin_referer('sc_reset_factory', 'sc_reset_factory_nonce')) {
    if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] == '1') {
        $reset_result = sc_reset_factory_data();
        if ($reset_result['success']) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($reset_result['message']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($reset_result['message']) . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>لطفاً تأیید را علامت بزنید.</p></div>';
    }
}

// دریافت تنظیمات فعلی
$penalty_enabled = sc_is_penalty_enabled();
$penalty_minutes = sc_get_penalty_minutes();
$penalty_amount = sc_get_penalty_amount();
$invoice_interval_minutes = sc_get_invoice_interval_minutes();

?>

<div class="wrap">
    <h1>تنظیمات SportClub Manager</h1>
    
    <nav class="nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=penalty'); ?>" 
           class="nav-tab <?php echo $current_tab === 'penalty' ? 'nav-tab-active' : ''; ?>">
            جریمه
        </a>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=invoice'); ?>" 
           class="nav-tab <?php echo $current_tab === 'invoice' ? 'nav-tab-active' : ''; ?>">
            صورتحساب
        </a>
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=reset'); ?>" 
           class="nav-tab <?php echo $current_tab === 'reset' ? 'nav-tab-active' : ''; ?>">
            بازگشت به کارخانه
        </a>
    </nav>
    
    <div class="tab-content" style="margin-top: 20px;">
        <?php if ($current_tab === 'penalty') : ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="penalty_enabled">فعال کردن جریمه</label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   name="penalty_enabled" 
                                   id="penalty_enabled" 
                                   value="1" 
                                   <?php checked($penalty_enabled, 1); ?>>
                            <label for="penalty_enabled">فعال کردن سیستم جریمه</label>
                            <p class="description">در صورت فعال بودن، برای صورت حساب‌های پرداخت نشده بعد از مدت مشخص شده جریمه اعمال می‌شود.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="penalty_minutes">تعداد دقیقه قبل از اعمال جریمه</label>
                        </th>
                        <td>
                            <input type="number"
                                   name="penalty_minutes"
                                   id="penalty_minutes"
                                   value="<?php echo esc_attr($penalty_minutes); ?>"
                                   min="1"
                                   class="regular-text"
                                   required>
                            <p class="description">تعداد دقیقه‌ای که بعد از ایجاد صورت حساب، در صورت عدم پرداخت، جریمه اعمال می‌شود.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="penalty_amount">مبلغ جریمه (تومان)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="penalty_amount" 
                                   id="penalty_amount" 
                                   value="<?php echo esc_attr($penalty_amount); ?>" 
                                   min="0" 
                                   step="0.01" 
                                   class="regular-text" 
                                   required>
                            <p class="description">مبلغ جریمه که به صورت حساب اضافه می‌شود.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات">
                </p>
            </form>
        <?php elseif ($current_tab === 'invoice') : ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="invoice_interval_minutes">تعداد دقیقه فاصله بین صورت حساب‌های تکراری</label>
                        </th>
                        <td>
                            <input type="number"
                                   name="invoice_interval_minutes"
                                   id="invoice_interval_minutes"
                                   value="<?php echo esc_attr($invoice_interval_minutes); ?>"
                                   min="1"
                                   class="regular-text"
                                   required>
                            <p class="description">تعداد دقیقه‌ای که بین ایجاد صورت حساب‌های تکراری فاصله وجود دارد.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات">
                </p>
            </form>
        <?php elseif ($current_tab === 'reset') : ?>
            <div class="sc-reset-factory-section">
                <div class="notice notice-warning" style="margin-bottom: 20px;">
                    <p><strong>هشدار:</strong> این عملیات غیر قابل بازگشت است!</p>
                    <p>با کلیک بر روی دکمه زیر، تمام اطلاعات موجود در جداول افزونه حذف خواهد شد:</p>
                    <ul style="margin-right: 20px; margin-top: 10px;">
                        <li>تمام اعضا (کاربران)</li>
                        <li>تمام دوره‌ها</li>
                        <li>تمام ثبت‌نام‌ها در دوره‌ها</li>
                        <li>تمام صورت حساب‌ها</li>
                        <li>تمام حضور و غیاب‌ها</li>
                    </ul>
                    <p><strong>توجه:</strong> ساختار جداول (ستون‌ها) حفظ می‌شود و فقط داده‌ها حذف می‌شوند.</p>
                </div>
                
                <form method="POST" action="" id="sc-reset-factory-form" onsubmit="return confirm('آیا مطمئن هستید؟ این عملیات غیر قابل بازگشت است و تمام اطلاعات حذف خواهد شد!');">
                    <?php wp_nonce_field('sc_reset_factory', 'sc_reset_factory_nonce'); ?>
                    
                    <p>
                        <label>
                            <input type="checkbox" name="confirm_reset" value="1" required>
                            من این عملیات را درک کرده‌ام و می‌خواهم تمام اطلاعات را حذف کنم
                        </label>
                    </p>
                    
                    <p class="submit">
                        <input type="submit" 
                               name="sc_reset_factory" 
                               class="button button-secondary" 
                               value="بازگشت به کارخانه (حذف تمام اطلاعات)"
                               style="background-color: #dc3232; border-color: #dc3232; color: #fff;"
                               onclick="return confirm('آیا واقعاً مطمئن هستید؟ این عملیات غیر قابل بازگشت است!');">
                    </p>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>

</style>

