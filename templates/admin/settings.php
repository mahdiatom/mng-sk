<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// بررسی و ایجاد جداول
sc_check_and_create_tables();

// پردازش فرم
if (isset($_POST['sc_save_settings']) && check_admin_referer('sc_settings_nonce', 'sc_settings_nonce')) {
    if ($current_tab === 'penalty') {
        $penalty_enabled = isset($_POST['penalty_enabled']) ? 1 : 0;
        $penalty_days = isset($_POST['penalty_days']) ? absint($_POST['penalty_days']) : 7;
        $penalty_amount = isset($_POST['penalty_amount']) ? floatval($_POST['penalty_amount']) : 500;
        
        sc_update_setting('penalty_enabled', $penalty_enabled, 'penalty');
        sc_update_setting('penalty_days', $penalty_days, 'penalty');
        sc_update_setting('penalty_amount', $penalty_amount, 'penalty');
        
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات جریمه با موفقیت ذخیره شد.</p></div>';
    } elseif ($current_tab === 'invoice') {
        $invoice_interval_days = isset($_POST['invoice_interval_days']) ? absint($_POST['invoice_interval_days']) : 30;
        
        sc_update_setting('invoice_interval_days', $invoice_interval_days, 'invoice');
        
        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات صورتحساب با موفقیت ذخیره شد.</p></div>';
    }
}

// دریافت تنظیمات فعلی
$penalty_enabled = sc_is_penalty_enabled();
$penalty_days = sc_get_penalty_days();
$penalty_amount = sc_get_penalty_amount();
$invoice_interval_days = sc_get_invoice_interval_days();

// دریافت تب فعلی
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'penalty';
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
                            <label for="penalty_days">تعداد روز قبل از اعمال جریمه</label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="penalty_days" 
                                   id="penalty_days" 
                                   value="<?php echo esc_attr($penalty_days); ?>" 
                                   min="1" 
                                   class="regular-text" 
                                   required>
                            <p class="description">تعداد روزی که بعد از ایجاد صورت حساب، در صورت عدم پرداخت، جریمه اعمال می‌شود.</p>
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
        <?php endif; ?>
    </div>
</div>

<style>
.nav-tab-wrapper {
    margin-bottom: 20px;
}

.tab-content {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
</style>

