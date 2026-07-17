<?php
/**
 * محدودیت ثبت‌نام کاربران سایت
 *
 * وقتی فعال باشد و تعداد کاربران وردپرس به سقف برسد،
 * ایجاد کاربر جدید مسدود می‌شود. فقط نقش وردپرس administrator
 * (نه مدیر باشگاه / مدیر سامانه / منشی) در بک‌اند معاف است.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * آیا محدودیت ثبت‌نام فعال است؟
 */
function sc_registration_limit_is_enabled() {
    return (int) sc_get_setting('registration_limit_enabled', '0') === 1;
}

/**
 * سقف مجاز تعداد کاربران (۰ = نامعتبر / اعمال نشود).
 */
function sc_registration_limit_get_max() {
    return max(0, (int) sc_get_setting('registration_limit_max_users', '0'));
}

/**
 * تعداد کل کاربران وردپرس.
 */
function sc_registration_limit_get_current_count() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $counts = count_users();
    $cached = isset($counts['total_users']) ? (int) $counts['total_users'] : 0;
    return $cached;
}

/**
 * پاک‌سازی کش تعداد (بعد از ایجاد/حذف کاربر).
 */
function sc_registration_limit_bust_count_cache() {
    // static داخل get_current_count فقط در همان درخواست است؛ این برای وضوح API است.
}

/**
 * آیا سقف پر شده؟ (count >= max → نفر بعدی مسدود)
 */
function sc_registration_limit_is_reached() {
    if (!sc_registration_limit_is_enabled()) {
        return false;
    }
    $max = sc_registration_limit_get_max();
    if ($max < 1) {
        return false;
    }
    return sc_registration_limit_get_current_count() >= $max;
}

/**
 * فقط نقش واقعی WordPress «administrator» معاف است.
 * مدیر باشگاه / مدیر سامانه / منشی — حتی با قابلیت‌های مشابه — معاف نیستند.
 * توجه: is_super_admin() روی سایت تک‌سایته به خاطر delete_users برای club_coach هم true می‌شود؛ استفاده نشود.
 *
 * @param int $user_id
 */
function sc_registration_limit_actor_is_exempt($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }
    $user = get_userdata($user_id);
    if (!$user || empty($user->roles)) {
        return false;
    }
    return in_array('administrator', (array) $user->roles, true);
}

/**
 * آیا می‌توان کاربر جدید ساخت؟
 *
 * @param int $actor_user_id ایجادکننده (۰ = مهمان / فرانت)
 */
function sc_registration_limit_can_create_user($actor_user_id = 0) {
    if (!sc_registration_limit_is_reached()) {
        return true;
    }
    return sc_registration_limit_actor_is_exempt($actor_user_id);
}

/**
 * پیام عمومی (فرم ورود و عضویت).
 */
function sc_registration_limit_public_message() {
    return 'در حال حاضر امکان ثبت‌نام کاربر جدید وجود ندارد. لطفاً با مدیریت مجموعه تماس بگیرید.';
}

/**
 * پیام مدیریتی (پنل ادمین / اقدامات سریع).
 */
function sc_registration_limit_admin_message() {
    $current = sc_registration_limit_get_current_count();
    $max     = sc_registration_limit_get_max();
    return sprintf(
        'امکان ثبت کاربر جدید وجود ندارد. ظرفیت کاربران سامانه تکمیل شده است (%s از %s). برای ادامه، نسبت به ارتقای ظرفیت سیستم اقدام کنید.',
        number_format_i18n($current),
        number_format_i18n($max)
    );
}

/**
 * پیام کوتاه بنر سراسری پنل.
 */
function sc_registration_limit_banner_message() {
    $current = sc_registration_limit_get_current_count();
    $max     = sc_registration_limit_get_max();
    return sprintf(
        'تعداد کاربران به سقف مجاز رسیده است (%s از %s). ثبت کاربر جدید فعلاً امکان‌پذیر نیست؛ برای ادامه، ظرفیت سامانه را ارتقا دهید.',
        number_format_i18n($current),
        number_format_i18n($max)
    );
}

/**
 * خلاصه وضعیت برای UI تنظیمات.
 *
 * @return array{enabled:bool,max:int,current:int,remaining:int,reached:bool}
 */
function sc_registration_limit_get_status() {
    $enabled = sc_registration_limit_is_enabled();
    $max     = sc_registration_limit_get_max();
    $current = sc_registration_limit_get_current_count();
    $remaining = ($enabled && $max > 0) ? max(0, $max - $current) : -1;
    return [
        'enabled'   => $enabled,
        'max'       => $max,
        'current'   => $current,
        'remaining' => $remaining,
        'reached'   => sc_registration_limit_is_reached(),
    ];
}

/**
 * بنر اخطار در کل پنل مدیریت وقتی سقف پر است.
 */
function sc_registration_limit_admin_banner_notice() {
    if (!is_admin() || !sc_registration_limit_is_reached()) {
        return;
    }
    if (!is_user_logged_in()) {
        return;
    }
    // فقط برای کاربرانی که به پنل دسترسی دارند
    if (!current_user_can('read')) {
        return;
    }

    $settings_url = admin_url('admin.php?page=sc_setting&tab=pro_features');

    echo '<div class="notice notice-warning sc-registration-limit-notice is-dismissible"><p><strong>محدودیت ظرفیت کاربران:</strong> ';
    echo esc_html(sc_registration_limit_banner_message());
    echo ' ثبت کاربر جدید در بخش‌های مربوطه غیرفعال شده است.';
    if (sc_registration_limit_actor_is_exempt()) {
        echo ' <a href="' . esc_url($settings_url) . '">تنظیمات محدودیت ثبت‌نام</a>';
    }
    echo '</p></div>';
}
add_action('admin_notices', 'sc_registration_limit_admin_banner_notice');

/**
 * جلوگیری از ایجاد کاربر در صفحه user-new.php وردپرس.
 */
function sc_registration_limit_block_wp_user_create($errors, $update, $user) {
    if ($update) {
        return;
    }
    if (sc_registration_limit_can_create_user()) {
        return;
    }
    $errors->add('sc_registration_limit', sc_registration_limit_admin_message());
}
add_action('user_profile_update_errors', 'sc_registration_limit_block_wp_user_create', 5, 3);

/**
 * اخطار در صفحه افزودن کاربر وردپرس.
 */
function sc_registration_limit_user_new_notice() {
    global $pagenow;
    if ($pagenow !== 'user-new.php' || sc_registration_limit_can_create_user()) {
        return;
    }
    echo '<div class="notice notice-error sc-registration-limit-page-notice"><p><strong>ثبت کاربر جدید غیرفعال است.</strong> '
        . esc_html(sc_registration_limit_admin_message())
        . '</p></div>';
}
add_action('admin_notices', 'sc_registration_limit_user_new_notice');

/**
 * UI: غیرفعال کردن دکمه ثبت کاربر در user-new.php.
 */
function sc_registration_limit_user_new_footer() {
    if (sc_registration_limit_can_create_user()) {
        return;
    }
    ?>
    <script>
    jQuery(function ($) {
        var $form = $('#createuser');
        if (!$form.length) return;
        var msg = <?php echo wp_json_encode(sc_registration_limit_admin_message()); ?>;
        $form.find('input[type="submit"], button[type="submit"]').prop('disabled', true).addClass('disabled').attr('title', msg);
        $form.on('submit', function (e) {
            e.preventDefault();
            window.alert(msg);
            return false;
        });
    });
    </script>
    <?php
}
add_action('admin_footer-user-new.php', 'sc_registration_limit_user_new_footer');

/**
 * جلوگیری از ثبت‌نام پیش‌فرض وردپرس / ووکامرس در فرانت.
 */
function sc_registration_limit_registration_errors($errors, $sanitized_user_login, $user_email) {
    if (!sc_registration_limit_can_create_user()) {
        $errors->add('sc_registration_limit', sc_registration_limit_public_message());
    }
    return $errors;
}
add_filter('registration_errors', 'sc_registration_limit_registration_errors', 10, 3);

/**
 * ووکامرس — جلوگیری از ثبت‌نام مشتری جدید.
 */
function sc_registration_limit_woocommerce_process_registration_errors($validation_error, $username, $password, $email) {
    if (!sc_registration_limit_can_create_user()) {
        return new WP_Error('sc_registration_limit', sc_registration_limit_public_message());
    }
    return $validation_error;
}
add_filter('woocommerce_process_registration_errors', 'sc_registration_limit_woocommerce_process_registration_errors', 10, 4);

/**
 * استایل بنر محدودیت در ادمین (inline روی sc-admin-css).
 */
function sc_registration_limit_admin_assets() {
    if (!is_admin() || !wp_style_is('sc-admin-css', 'enqueued')) {
        return;
    }
    $css = '
    .sc-registration-limit-notice{border-right:4px solid #dba617!important;background:linear-gradient(135deg,#fffbeb 0%,#fff 55%)!important;box-shadow:0 2px 12px rgba(180,83,9,.08)}
    .sc-registration-limit-notice p{font-size:13px;line-height:1.7}
    .sc-registration-limit-page-notice{border-right-color:#b32d2e!important;margin:12px 0 16px!important}
    .sc-reg-limit-card{margin:28px 0 8px;padding:22px 24px;border:1px solid rgba(109,52,255,.14);border-radius:14px;background:linear-gradient(145deg,#faf8ff 0%,#fff 48%,#f7f4ff 100%);box-shadow:0 4px 18px rgba(74,31,184,.06)}
    .sc-reg-limit-card__head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px}
    .sc-reg-limit-card__title{margin:0 0 6px;font-size:16px;font-weight:700;color:#4a1fb8}
    .sc-reg-limit-card__desc{margin:0;color:#646970;font-size:13px;line-height:1.7;max-width:640px}
    .sc-reg-limit-stats{display:flex;flex-wrap:wrap;gap:10px}
    .sc-reg-limit-stat{min-width:110px;padding:10px 14px;border-radius:10px;background:#fff;border:1px solid rgba(109,52,255,.12);text-align:center}
    .sc-reg-limit-stat__val{display:block;font-size:18px;font-weight:800;color:#4a1fb8;line-height:1.2}
    .sc-reg-limit-stat__lbl{display:block;margin-top:4px;font-size:11px;color:#787c82}
    .sc-reg-limit-stat.is-danger .sc-reg-limit-stat__val{color:#b32d2e}
    .sc-reg-limit-stat.is-ok .sc-reg-limit-stat__val{color:#00a32a}
    .sc-reg-limit-fields{display:grid;grid-template-columns:minmax(200px,280px) 1fr;gap:20px;align-items:center;padding-top:8px;border-top:1px solid rgba(109,52,255,.1)}
    @media (max-width:782px){.sc-reg-limit-fields{grid-template-columns:1fr}}
    .sc-reg-limit-field label{display:block;font-weight:600;margin-bottom:8px;color:#1d2327}
    .sc-reg-limit-field .description{margin-top:8px}
    .sc-reg-limit-blocked-banner{margin:0 0 16px;padding:14px 16px;border-radius:10px;border:1px solid #f0c36d;background:#fff8e5;color:#5c3b00;line-height:1.7}
    .sc-reg-limit-blocked-banner strong{color:#8a5b00}
    ';
    wp_add_inline_style('sc-admin-css', $css);
}
add_action('admin_enqueue_scripts', 'sc_registration_limit_admin_assets', 40);
