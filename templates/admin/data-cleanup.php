<?php
if (!defined('ABSPATH')) {
    exit;
}

$modules = sc_data_cleanup_modules();
$otp_phone = sc_data_cleanup_get_otp_phone();
$super_admin_phone = sc_data_cleanup_get_super_admin_phone();
?>
<div class="wrap sc-data-cleanup" dir="rtl">
    <header class="sc-data-cleanup__header">
        <div>
            <span class="sc-data-cleanup__eyebrow">عملیات حساس</span>
            <h1>پاکسازی اطلاعات کاربری</h1>
            <p>داده‌های حجیم هر بخش را به‌صورت مستقل و غیرقابل‌بازگشت پاک کنید.</p>
        </div>
        <div class="sc-data-cleanup__security">
            <span class="dashicons dashicons-shield-alt"></span>
            <div>
                <strong>حفاظت سه‌مرحله‌ای</strong>
                <span>دو تأیید هشدار + OTP مدیر + تأیید مدیر کل</span>
            </div>
        </div>
    </header>

    <?php if ($otp_phone === '') : ?>
        <div class="notice notice-error inline">
            <p>شماره OTP تولید QR تنظیم نشده است. ابتدا آن را از تنظیمات حضور و غیاب ثبت کنید.</p>
        </div>
    <?php endif; ?>

    <div class="sc-data-cleanup__phone-row">
        <span>شماره احراز اولیه: <strong dir="ltr"><?php echo esc_html(sc_data_cleanup_mask_phone($otp_phone)); ?></strong></span>
        <span>شماره تأیید مدیر کل: <strong dir="ltr"><?php echo esc_html(sc_data_cleanup_mask_phone($super_admin_phone)); ?></strong></span>
    </div>

    <section class="sc-data-cleanup__list" aria-label="بخش‌های قابل پاکسازی">
        <?php foreach ($modules as $key => $module) :
            $count = sc_data_cleanup_module_count($key);
            ?>
            <article class="sc-data-cleanup__item" data-module="<?php echo esc_attr($key); ?>">
                <div class="sc-data-cleanup__item-main">
                    <h2><?php echo esc_html($module['label']); ?></h2>
                    <p><?php echo esc_html($module['description']); ?></p>
                </div>
                <div class="sc-data-cleanup__item-meta">
                    <span class="sc-data-cleanup__count">
                        <b><?php echo esc_html(number_format_i18n($count)); ?></b>
                        رکورد اصلی
                    </span>
                    <button
                        type="button"
                        class="button sc-data-cleanup__button"
                        data-module="<?php echo esc_attr($key); ?>"
                        data-label="<?php echo esc_attr($module['label']); ?>"
                        data-danger="<?php echo esc_attr($module['danger']); ?>"
                        <?php disabled($otp_phone === ''); ?>
                    >
                        پاکسازی <?php echo esc_html($module['label']); ?>
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <div id="sc-data-cleanup-otp-modal" class="sc-data-cleanup-modal" hidden aria-hidden="true">
        <div class="sc-data-cleanup-modal__backdrop"></div>
        <div class="sc-data-cleanup-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sc-data-cleanup-modal-title">
            <button type="button" class="sc-data-cleanup-modal__close" aria-label="بستن">×</button>
            <span class="dashicons dashicons-lock sc-data-cleanup-modal__icon"></span>
            <h2 id="sc-data-cleanup-modal-title">احراز هویت پیامکی</h2>
            <p class="sc-data-cleanup-modal__hint"></p>
            <p class="sc-data-cleanup-modal__phone"></p>
            <label for="sc-data-cleanup-otp-input">کد ۶ رقمی</label>
            <input type="text" id="sc-data-cleanup-otp-input" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="ــــــ">
            <p class="sc-data-cleanup-modal__error" hidden></p>
            <div class="sc-data-cleanup-modal__actions">
                <button type="button" class="button sc-data-cleanup-modal__cancel">انصراف</button>
                <button type="button" class="button button-primary sc-data-cleanup-modal__confirm">تأیید</button>
            </div>
        </div>
    </div>

    <div id="sc-data-cleanup-progress" class="sc-data-cleanup-progress" hidden>
        <div class="sc-data-cleanup-progress__panel" role="status" aria-live="polite">
            <span class="dashicons dashicons-update sc-data-cleanup-progress__spinner"></span>
            <h2>پاکسازی در حال انجام است</h2>
            <p class="sc-data-cleanup-progress__message">لطفاً این صفحه را نبندید.</p>
            <div class="sc-data-cleanup-progress__track"><span></span></div>
            <strong class="sc-data-cleanup-progress__count"></strong>
        </div>
    </div>
</div>
