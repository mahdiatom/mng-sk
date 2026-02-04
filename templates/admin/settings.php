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
    }elseif ($current_tab === 'invoice') {

    $invoice_mode = isset($_POST['invoice_mode']) ? sanitize_text_field($_POST['invoice_mode']) : 'interval';

    sc_update_setting('invoice_mode', $invoice_mode, 'invoice');

    if ($invoice_mode === 'interval') {
        $invoice_interval_minutes = absint($_POST['invoice_interval_minutes']);
        sc_update_setting('invoice_interval_minutes', $invoice_interval_minutes, 'invoice');
    } else {
        sc_update_setting('invoice_day_of_month', absint($_POST['invoice_day_of_month']), 'invoice');
        sc_update_setting('invoice_hour', absint($_POST['invoice_hour']), 'invoice');
    }

    echo '<div class="notice notice-success is-dismissible"><p>تنظیمات صورتحساب ذخیره شد.</p></div>';
}
 elseif ($current_tab === 'sms') {
        // API Settings
        $sms_api_key = isset($_POST['sms_api_key']) ? sanitize_text_field($_POST['sms_api_key']) : '';
        $sms_sender = isset($_POST['sms_sender']) ? sanitize_text_field($_POST['sms_sender']) : '';
        $sms_admin_phone = isset($_POST['sms_admin_phone']) ? sanitize_text_field($_POST['sms_admin_phone']) : '';
        $sms_reminder_delay_minutes = isset($_POST['sms_reminder_delay_minutes']) ? absint($_POST['sms_reminder_delay_minutes']) : 4320;

        sc_update_setting('sms_api_key', $sms_api_key, 'sms');
        sc_update_setting('sms_sender', $sms_sender, 'sms');
        sc_update_setting('sms_admin_phone', $sms_admin_phone, 'sms');
        sc_update_setting('sms_reminder_delay_minutes', $sms_reminder_delay_minutes, 'sms');

        // Invoice SMS Settings
        $sms_invoice_user_enabled = isset($_POST['sms_invoice_user_enabled']) ? 1 : 0;
        $sms_invoice_user_template = isset($_POST['sms_invoice_user_template']) ? wp_kses($_POST['sms_invoice_user_template'], array()) : '';
        $sms_invoice_user_pattern = isset($_POST['sms_invoice_user_pattern']) ? absint($_POST['sms_invoice_user_pattern']) : '';
        $sms_invoice_admin_enabled = isset($_POST['sms_invoice_admin_enabled']) ? 1 : 0;
        $sms_invoice_admin_template = isset($_POST['sms_invoice_admin_template']) ? wp_kses($_POST['sms_invoice_admin_template'], array()) : '';
        $sms_invoice_admin_pattern = isset($_POST['sms_invoice_admin_pattern']) ? absint($_POST['sms_invoice_admin_pattern']) : '';

        sc_update_setting('sms_invoice_user_enabled', $sms_invoice_user_enabled, 'sms');
        sc_update_setting('sms_invoice_user_template', $sms_invoice_user_template, 'sms');
        sc_update_setting('sms_invoice_user_pattern', $sms_invoice_user_pattern, 'sms');
        sc_update_setting('sms_invoice_admin_enabled', $sms_invoice_admin_enabled, 'sms');
        sc_update_setting('sms_invoice_admin_template', $sms_invoice_admin_template, 'sms');
        sc_update_setting('sms_invoice_admin_pattern', $sms_invoice_admin_pattern, 'sms');

        // Enrollment SMS Settings
        $sms_enrollment_user_enabled = isset($_POST['sms_enrollment_user_enabled']) ? 1 : 0;
        $sms_enrollment_user_template = isset($_POST['sms_enrollment_user_template']) ? wp_kses($_POST['sms_enrollment_user_template'], array()) : '';
        $sms_enrollment_user_pattern = isset($_POST['sms_enrollment_user_pattern']) ? absint($_POST['sms_enrollment_user_pattern']) : '';
        $sms_enrollment_admin_enabled = isset($_POST['sms_enrollment_admin_enabled']) ? 1 : 0;
        $sms_enrollment_admin_template = isset($_POST['sms_enrollment_admin_template']) ? wp_kses($_POST['sms_enrollment_admin_template'], array()) : '';
        $sms_enrollment_admin_pattern = isset($_POST['sms_enrollment_admin_pattern']) ? absint($_POST['sms_enrollment_admin_pattern']) : '';

        sc_update_setting('sms_enrollment_user_enabled', $sms_enrollment_user_enabled, 'sms');
        sc_update_setting('sms_enrollment_user_template', $sms_enrollment_user_template, 'sms');
        sc_update_setting('sms_enrollment_user_pattern', $sms_enrollment_user_pattern, 'sms');
        sc_update_setting('sms_enrollment_admin_enabled', $sms_enrollment_admin_enabled, 'sms');
        sc_update_setting('sms_enrollment_admin_template', $sms_enrollment_admin_template, 'sms');
        sc_update_setting('sms_enrollment_admin_pattern', $sms_enrollment_admin_pattern, 'sms');

        // Reminder SMS Settings
        $sms_reminder_user_enabled = isset($_POST['sms_reminder_user_enabled']) ? 1 : 0;
        $sms_reminder_user_template = isset($_POST['sms_reminder_user_template']) ? wp_kses($_POST['sms_reminder_user_template'], array()) : '';
        $sms_reminder_user_pattern = isset($_POST['sms_reminder_user_pattern']) ? absint($_POST['sms_reminder_user_pattern']) : '';
        $sms_reminder_admin_enabled = isset($_POST['sms_reminder_admin_enabled']) ? 1 : 0;
        $sms_reminder_admin_template = isset($_POST['sms_reminder_admin_template']) ? wp_kses($_POST['sms_reminder_admin_template'], array()) : '';
        $sms_reminder_admin_pattern = isset($_POST['sms_reminder_admin_pattern']) ? absint($_POST['sms_reminder_admin_pattern']) : '';

        sc_update_setting('sms_reminder_user_enabled', $sms_reminder_user_enabled, 'sms');
        sc_update_setting('sms_reminder_user_template', $sms_reminder_user_template, 'sms');
        sc_update_setting('sms_reminder_user_pattern', $sms_reminder_user_pattern, 'sms');
        sc_update_setting('sms_reminder_admin_enabled', $sms_reminder_admin_enabled, 'sms');
        sc_update_setting('sms_reminder_admin_template', $sms_reminder_admin_template, 'sms');
        sc_update_setting('sms_reminder_admin_pattern', $sms_reminder_admin_pattern, 'sms');

        // Absence SMS Settings
        $sms_absence_user_enabled = isset($_POST['sms_absence_user_enabled']) ? 1 : 0;
        $sms_absence_user_template = isset($_POST['sms_absence_user_template']) ? wp_kses($_POST['sms_absence_user_template'], array()) : '';
        $sms_absence_user_pattern = isset($_POST['sms_absence_user_pattern']) ? absint($_POST['sms_absence_user_pattern']) : '';
        $sms_absence_admin_enabled = isset($_POST['sms_absence_admin_enabled']) ? 1 : 0;
        $sms_absence_admin_template = isset($_POST['sms_absence_admin_template']) ? wp_kses($_POST['sms_absence_admin_template'], array()) : '';
        $sms_absence_admin_pattern = isset($_POST['sms_absence_admin_pattern']) ? absint($_POST['sms_absence_admin_pattern']) : '';

        sc_update_setting('sms_absence_user_enabled', $sms_absence_user_enabled, 'sms');
        sc_update_setting('sms_absence_user_template', $sms_absence_user_template, 'sms');
        sc_update_setting('sms_absence_user_pattern', $sms_absence_user_pattern, 'sms');
        sc_update_setting('sms_absence_admin_enabled', $sms_absence_admin_enabled, 'sms');
        sc_update_setting('sms_absence_admin_template', $sms_absence_admin_template, 'sms');
        sc_update_setting('sms_absence_admin_pattern', $sms_absence_admin_pattern, 'sms');

        echo '<div class="notice notice-success is-dismissible"><p>تنظیمات پیامک با موفقیت ذخیره شد.</p></div>';
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

// تنظیمات SMS
$sms_api_key = sc_get_setting('sms_api_key', '');
$sms_sender = sc_get_setting('sms_sender', '');
$sms_admin_phone = sc_get_setting('sms_admin_phone', '');
$sms_reminder_delay_minutes = sc_get_setting('sms_reminder_delay_minutes', '4320');

// Invoice SMS Settings
$sms_invoice_user_enabled = (int)sc_get_setting('sms_invoice_user_enabled', '1');
$sms_invoice_user_template = sc_get_setting('sms_invoice_user_template', '');
$sms_invoice_user_pattern = sc_get_setting('sms_invoice_user_pattern', '');
$sms_invoice_admin_enabled = (int)sc_get_setting('sms_invoice_admin_enabled', '1');
$sms_invoice_admin_template = sc_get_setting('sms_invoice_admin_template', '');
$sms_invoice_admin_pattern = sc_get_setting('sms_invoice_admin_pattern', '');

// Enrollment SMS Settings
$sms_enrollment_user_enabled = (int)sc_get_setting('sms_enrollment_user_enabled', '1');
$sms_enrollment_user_template = sc_get_setting('sms_enrollment_user_template', '');
$sms_enrollment_user_pattern = sc_get_setting('sms_enrollment_user_pattern', '');
$sms_enrollment_admin_enabled = (int)sc_get_setting('sms_enrollment_admin_enabled', '1');
$sms_enrollment_admin_template = sc_get_setting('sms_enrollment_admin_template', '');
$sms_enrollment_admin_pattern = sc_get_setting('sms_enrollment_admin_pattern', '');

// Reminder SMS Settings
$sms_reminder_user_enabled = (int)sc_get_setting('sms_reminder_user_enabled', '1');
$sms_reminder_user_template = sc_get_setting('sms_reminder_user_template', '');
$sms_reminder_user_pattern = sc_get_setting('sms_reminder_user_pattern', '');
$sms_reminder_admin_enabled = (int)sc_get_setting('sms_reminder_admin_enabled', '1');
$sms_reminder_admin_template = sc_get_setting('sms_reminder_admin_template', '');
$sms_reminder_admin_pattern = sc_get_setting('sms_reminder_admin_pattern', '');

// Absence SMS Settings
$sms_absence_user_enabled = (int)sc_get_setting('sms_absence_user_enabled', '1');
$sms_absence_user_template = sc_get_setting('sms_absence_user_template', 'کاربر گرامی %user_name%، غیبت شما در جلسه دوره %course_name% مورخ %date% ثبت شد.');
$sms_absence_user_pattern = sc_get_setting('sms_absence_user_pattern', '');
$sms_absence_admin_enabled = (int)sc_get_setting('sms_absence_admin_enabled', '1');
$sms_absence_admin_template = sc_get_setting('sms_absence_admin_template', 'غیبت: %user_name% - دوره %course_name% - تاریخ %date%');
$sms_absence_admin_pattern = sc_get_setting('sms_absence_admin_pattern', '');

?>

<div class="wrap sc_setting_section" >
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
        <a href="<?php echo admin_url('admin.php?page=sc_setting&tab=sms'); ?>"
           class="nav-tab <?php echo $current_tab === 'sms' ? 'nav-tab-active' : ''; ?>">
            پیامک
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

                <table class="form-table  ">
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
                            <label for="penalty_minutes">مهلت زمانی برای جریمه </label>
                        </th>
                        <td>
                            <input type="number"
                                   name="penalty_minutes"
                                   id="penalty_minutes"
                                   value="<?php echo esc_attr($penalty_minutes); ?>"
                                   min="1"
                                   class="regular-text"
                                   required>
                            <p class="description">بعد از گذشت این مهلت زمانی جریمه برای کاربر اعمال خواهد شد.</p>
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
    <th>نوع ایجاد صورتحساب</th>
    <td>
        <label>
            <input type="radio" name="invoice_mode" value="interval"
                <?php checked(sc_get_invoice_mode(), 'interval'); ?>>
            بر اساس فاصله زمانی
        </label>
        <br>
        <label>
            <input type="radio" name="invoice_mode" value="fixed_date"
                <?php checked(sc_get_invoice_mode(), 'fixed_date'); ?>>
            در تاریخ مشخص ماهانه
        </label>
    </td>
</tr>

<tr>
    <th>فاصله زمانی (دقیقه)</th>
    <td>
        <input type="number"
               name="invoice_interval_minutes"
               value="<?php echo esc_attr(sc_get_invoice_interval_minutes()); ?>">
        <p class="description">فقط در حالت فاصله زمانی استفاده می‌شود</p>
    </td>
</tr>

<tr>
    <th>روز ماه</th>
    <td>
        <input type="number"
               name="invoice_day_of_month"
               min="1"
               max="28"
               value="<?php echo esc_attr(sc_get_invoice_day_of_month()); ?>">
        <p class="description">۱ تا ۲۸</p>
    </td>
</tr>

<tr>
    <th>ساعت اجرا</th>
    <td>
        <input type="number"
               name="invoice_hour"
               min="0"
               max="23"
               value="<?php echo esc_attr(sc_get_invoice_hour()); ?>">
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
        <?php elseif ($current_tab === 'sms') : ?>
            <form method="POST" action="">
                <?php wp_nonce_field('sc_settings_nonce', 'sc_settings_nonce'); ?>

                <!-- API Settings -->
                <h3>تنظیمات API پیامک</h3>
                <table class="form-table  ">
                    <tr>
                        <th scope="row">
                            <label for="sms_api_key">API Key</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="sms_api_key"
                                   id="sms_api_key"
                                   value="<?php echo esc_attr($sms_api_key); ?>"
                                   class="regular-text"
                                   placeholder="API Key پنل sms.ir">
                            <p class="description">API Key که از پنل sms.ir دریافت کرده‌اید.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sms_sender">شماره ارسال کننده</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="sms_sender"
                                   id="sms_sender"
                                   value="<?php echo esc_attr($sms_sender); ?>"
                                   class="regular-text"
                                   placeholder="مثال: 1000123456">
                            <p class="description">شماره اختصاصی شما در پنل sms.ir.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sms_admin_phone">شماره مدیر</label>
                        </th>
                        <td>
                            <input type="text"
                                   name="sms_admin_phone"
                                   id="sms_admin_phone"
                                   value="<?php echo esc_attr($sms_admin_phone); ?>"
                                   class="regular-text"
                                   placeholder="مثال: 09123456789">
                            <p class="description">شماره موبایل مدیر برای دریافت پیامک‌های اطلاع‌رسانی.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">اعتبار پنل</th>
                        <td>
                            <?php
                            $credit_result = sc_get_sms_credit();
                            if ($credit_result['success']) {
                                $sms_count = floor($credit_result['credit']); // گرد کردن به پایین
                                $monetary_value = $sms_count * 219; // محاسبه ارزش ریالی (۲۱۹ تومان هر پیامک)
                                echo '<span style="color: green; font-weight: bold;">' . esc_html($sms_count) . ' پیامک</span>';
                                echo '<br><small style="color: #666;">معادل ' . number_format($monetary_value, 0) . ' تومان</small>';
                            } else {
                                echo '<span style="color: red;">' . esc_html($credit_result['message']) . '</span>';
                            }
                            ?>
                            <p class="description">میزان اعتبار باقی‌مانده در پنل sms.ir</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sms_reminder_delay_minutes">مدت زمان یادآوری پرداخت</label>
                        </th>
                        <td>
                            <input type="number"
                                   name="sms_reminder_delay_minutes"
                                   id="sms_reminder_delay_minutes"
                                   value="<?php echo esc_attr($sms_reminder_delay_minutes); ?>"
                                   min="1"
                                   class="regular-text"
                                   required>
                            <p class="description">مدت زمان به دقیقه که بعد از ایجاد صورت حساب، پیامک یادآوری ارسال شود. (پیش‌فرض: 4320 دقیقه = 3 روز)</p>
                        </td>
                    </tr>
                </table>

                <!-- Invoice SMS Settings -->
                <h3>پیامک صورت حساب</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">پیامک به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_invoice_user_enabled"
                                       value="1"
                                       <?php checked($sms_invoice_user_enabled, 1); ?>>
                                فعال کردن پیامک صورت حساب به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_invoice_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به کاربر"><?php echo esc_textarea($sms_invoice_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% -
                                نام رویداد = %event_name% -
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount% - 
                                تاریخ سررسید = %due_date%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_invoice_user_pattern"
                                   value="<?php echo esc_attr($sms_invoice_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">پیامک به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_invoice_admin_enabled"
                                       value="1"
                                       <?php checked($sms_invoice_admin_enabled, 1); ?>>
                                فعال کردن پیامک صورت حساب به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_invoice_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به مدیر"><?php echo esc_textarea($sms_invoice_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount% - 
                                تاریخ سررسید = %due_date%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_invoice_admin_pattern"
                                   value="<?php echo esc_attr($sms_invoice_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                <!-- Enrollment SMS Settings -->
                <h3>پیامک ثبت نام</h3>
                <table class="form-table  ">
                    <tr>
                        <th scope="row">پیامک به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_enrollment_user_enabled"
                                       value="1"
                                       <?php checked($sms_enrollment_user_enabled, 1); ?>>
                                فعال کردن پیامک ثبت نام به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_enrollment_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به کاربر"><?php echo esc_textarea($sms_enrollment_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده: - 
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_enrollment_user_pattern"
                                   value="<?php echo esc_attr($sms_enrollment_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">پیامک به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_enrollment_admin_enabled"
                                       value="1"
                                       <?php checked($sms_enrollment_admin_enabled, 1); ?>>
                                فعال کردن پیامک ثبت نام به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_enrollment_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به مدیر"><?php echo esc_textarea($sms_enrollment_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_enrollment_admin_pattern"
                                   value="<?php echo esc_attr($sms_enrollment_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                <!-- Reminder SMS Settings -->
                <h3>پیامک یادآوری پرداخت</h3>
                <table class="form-table  ">
                    <tr>
                        <th scope="row">پیامک به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_reminder_user_enabled"
                                       value="1"
                                       <?php checked($sms_reminder_user_enabled, 1); ?>>
                                فعال کردن پیامک یادآوری به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_reminder_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به کاربر"><?php echo esc_textarea($sms_reminder_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount% - 
                                مبلغ جریمه = %penalty_amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_reminder_user_pattern"
                                   value="<?php echo esc_attr($sms_reminder_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">پیامک به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_reminder_admin_enabled"
                                       value="1"
                                       <?php checked($sms_reminder_admin_enabled, 1); ?>>
                                فعال کردن پیامک یادآوری به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_reminder_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به مدیر"><?php echo esc_textarea($sms_reminder_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                نام هزینه = %expense_name% - 
                                مبلغ = %amount% - 
                                مبلغ جریمه = %penalty_amount%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_reminder_user_pattern"
                                   value="<?php echo esc_attr($sms_reminder_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                <!-- Absence SMS Settings -->
                <h3>پیامک غیبت</h3>
                <table class="form-table  ">
                    <tr>
                        <th scope="row">پیامک به کاربر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_absence_user_enabled"
                                       value="1"
                                       <?php checked($sms_absence_user_enabled, 1); ?>>
                                فعال کردن پیامک غیبت به کاربر
                            </label>
                            <br><br>
                            <textarea name="sms_absence_user_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به کاربر"><?php echo esc_textarea($sms_absence_user_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                تاریخ = %date%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_absence_user_pattern"
                                   value="<?php echo esc_attr($sms_absence_user_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">پیامک به مدیر</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="sms_absence_admin_enabled"
                                       value="1"
                                       <?php checked($sms_absence_admin_enabled, 1); ?>>
                                فعال کردن پیامک غیبت به مدیر
                            </label>
                            <br><br>
                            <textarea name="sms_absence_admin_template"
                                      rows="3"
                                      class="large-text"
                                      placeholder="متن پیامک به مدیر"><?php echo esc_textarea($sms_absence_admin_template); ?></textarea>
                            <p class="description">
                                متغیرهای قابل استفاده:<br>
                                نام کاربر = %user_name% - 
                                نام دوره = %course_name% - 
                                نام رویداد = %event_name% - 
                                نام آیتم = %item_name% - 
                                تاریخ = %date%
                            </p>
                            <br>
                            <input type="number"
                                   name="sms_absence_admin_pattern"
                                   value="<?php echo esc_attr($sms_absence_admin_pattern); ?>"
                                   class="small-text"
                                   placeholder="کد پترن (اختیاری)">
                            <p class="description">کد پترن از پنل sms.ir (در صورت خالی بودن از پیامک عادی استفاده می‌شود)</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="sc_save_settings" class="button button-primary" value="ذخیره تنظیمات پیامک">
                </p>
            </form>

        <?php endif; ?>
    </div>
</div>

<style>

</style>