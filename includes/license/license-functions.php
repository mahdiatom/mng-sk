<?php
/**
 * یکپارچه‌سازی لایسنس AtomClub برای SportClub Manager
 *
 * @package SportClub
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SC_LICENSE_DIR', SC_INCLUDES_DIR . 'license/');
define('SC_LICENSE_MAIN_KEY', 'sportclub_lic_Key');
define('SC_LICENSE_EMAIL_OPTION', 'sportclub_lic_email');

require_once SC_LICENSE_DIR . 'class-atomclub-base.php';

/**
 * توابع حداقلی وقتی ماژول پیامک (sms-functions) بارگذاری نشده — جلوگیری از Fatal در تنظیمات/Admin Bar
 */
function sc_license_register_module_stubs() {
    if (sc_is_license_active()) {
        return;
    }
    if (!function_exists('sc_get_sms_setting')) {
        function sc_get_sms_setting($key) {
            if (function_exists('sc_get_setting')) {
                $value = sc_get_setting($key, null);
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
            return '';
        }
    }
    if (!function_exists('sc_get_sms_credit')) {
        function sc_get_sms_credit() {
            return [
                'success' => false,
                'credit'  => 0,
                'message' => 'ماژول پیامک بارگذاری نشده است.',
            ];
        }
    }
}

/**
 * @return bool
 */
function sc_is_license_active() {
    return !empty($GLOBALS['sc_license_state']['active']);
}

/**
 * فقط مدیر کل سایت (نقش administrator) — نه مدیر باشگاه
 *
 * @return bool
 */
function sc_can_manage_license() {
    if (!is_user_logged_in()) {
        return false;
    }
    $user = wp_get_current_user();
    return $user->exists() && in_array('administrator', (array) $user->roles, true);
}

/**
 * ثبت capability مدیریت لایسنس فقط برای administrator
 */
function sc_license_register_admin_capability() {
    $admin_role = get_role('administrator');
    if ($admin_role && !$admin_role->has_cap('sc_manage_license')) {
        $admin_role->add_cap('sc_manage_license');
    }
}
add_action('admin_init', 'sc_license_register_admin_capability', 1);

/**
 * دلیل غیرفعال بودن: missing | expired | invalid | error
 *
 * @return string
 */
function sc_license_get_status_reason() {
    return isset($GLOBALS['sc_license_state']['reason']) ? (string) $GLOBALS['sc_license_state']['reason'] : 'missing';
}

/**
 * آیا تاریخ انقضا گذشته است؟
 *
 * @param string $expire_date
 * @return bool
 */
function sc_license_is_expire_date_passed($expire_date) {
    $expire_date = strtolower(trim((string) $expire_date));
    if ($expire_date === '' || in_array($expire_date, ['unlimited', 'no expiry', 'no_expiry', 'lifetime'], true)) {
        return false;
    }
    $ts = strtotime($expire_date);
    return $ts !== false && $ts < time();
}

/**
 * اعتبار پاسخ ذخیره‌شده لایسنس (شامل انقضا)
 *
 * @param stdClass|null $response_obj
 * @return bool
 */
function sc_license_validate_response($response_obj) {
    if (!is_object($response_obj)) {
        return false;
    }
    if (isset($response_obj->is_valid) && !$response_obj->is_valid) {
        return false;
    }
    if (!empty($response_obj->expire_date) && sc_license_is_expire_date_passed($response_obj->expire_date)) {
        return false;
    }
    return true;
}

/**
 * متن هشدار لایسنس برای نمایش در پیشخوان
 *
 * @return string
 */
function sc_license_get_notice_text() {
    $reason  = sc_license_get_status_reason();
    $message = sc_license_get_message();
    $response = sc_license_get_response();

    switch ($reason) {
        case 'expired':
            $expire = is_object($response) && !empty($response->expire_date) ? $response->expire_date : '';
            if ($expire !== '') {
                return sprintf(
                    /* translators: %s: expire date */
                    __('لایسنس SportClub Manager منقضی شده است (تاریخ انقضا: %s). افزونه غیرفعال است.', 'sportclub-manager'),
                    $expire
                );
            }
            return __('لایسنس SportClub Manager منقضی شده است. افزونه غیرفعال است.', 'sportclub-manager');

        case 'invalid':
            if ($message !== '') {
                return sprintf(
                    __('لایسنس SportClub Manager نامعتبر است: %s', 'sportclub-manager'),
                    $message
                );
            }
            return __('لایسنس SportClub Manager نامعتبر است. افزونه غیرفعال است.', 'sportclub-manager');

        case 'error':
            if ($message !== '') {
                return sprintf(
                    __('خطا در بررسی لایسنس SportClub Manager: %s', 'sportclub-manager'),
                    $message
                );
            }
            return __('خطا در بررسی لایسنس SportClub Manager. افزونه غیرفعال است.', 'sportclub-manager');

        case 'missing':
        default:
            return __('افزونه SportClub Manager فعال نیست. لطفاً در تنظیمات، تب لایسنس، کد لایسنس را وارد و فعال کنید.', 'sportclub-manager');
    }
}

/**
 * @return stdClass|null
 */
function sc_license_get_response() {
    return isset($GLOBALS['sc_license_state']['response']) ? $GLOBALS['sc_license_state']['response'] : null;
}

/**
 * @return string
 */
function sc_license_get_message() {
    return isset($GLOBALS['sc_license_state']['message']) ? (string) $GLOBALS['sc_license_state']['message'] : '';
}

/**
 * @return bool
 */
function sc_license_should_show_error() {
    return !empty($GLOBALS['sc_license_state']['show_error']);
}

/**
 * @return string
 */
function sc_license_get_key_option_name() {
    return atomclub_Base::get_lic_key_param(SC_LICENSE_MAIN_KEY);
}

/**
 * @return string
 */
function sc_license_get_stored_key() {
    $lic_key_name = sc_license_get_key_option_name();
    $license_key  = get_option($lic_key_name, '');
    if ($license_key === '') {
        $license_key = get_option(SC_LICENSE_MAIN_KEY, '');
        if ($license_key !== '') {
            update_option($lic_key_name, $license_key);
        }
    }
    return (string) $license_key;
}

/**
 * @return string
 */
function sc_license_get_stored_email() {
    return (string) get_option(SC_LICENSE_EMAIL_OPTION, get_bloginfo('admin_email'));
}

/**
 * بررسی لایسنس و ذخیره وضعیت در حافظهٔ درخواست
 */
function sc_license_bootstrap() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $plugin_file = defined('SC_PLUGIN_MAIN_FILE') ? SC_PLUGIN_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/sportclub_manager.php';

    $license_key   = sc_license_get_stored_key();
    $license_email = sc_license_get_stored_email();
    $message       = '';
    $response_obj  = null;
    $active        = false;
    $show_error    = false;
    $reason        = 'missing';

    atomclub_Base::add_on_delete(static function () {
        update_option(SC_LICENSE_MAIN_KEY, '');
    });

    if ($license_key === '') {
        $reason = 'missing';
    } elseif (atomclub_Base::check_wp_plugin($license_key, $license_email, $message, $response_obj, $plugin_file)) {
        if (sc_license_validate_response($response_obj)) {
            $active = true;
            $reason = 'ok';
        } else {
            $reason     = 'expired';
            $show_error = true;
            if ($message === '' && is_object($response_obj) && !empty($response_obj->expire_date)) {
                $message = 'لایسنس منقضی شده است.';
            }
        }
    } else {
        $show_error = true;
        if (is_object($response_obj) && !empty($response_obj->expire_date) && sc_license_is_expire_date_passed($response_obj->expire_date)) {
            $reason = 'expired';
        } elseif ($message !== '') {
            $reason = 'invalid';
        } else {
            $reason = 'error';
        }
    }

    $GLOBALS['sc_license_state'] = [
        'active'     => $active,
        'response'   => $response_obj,
        'message'    => $message,
        'show_error' => $show_error,
        'reason'     => $reason,
    ];
}

sc_license_bootstrap();
sc_license_register_module_stubs();

add_action('admin_post_sc_license_activate', 'sc_license_handle_activate');
add_action('admin_post_sc_license_deactivate', 'sc_license_handle_deactivate');

/**
 * فعال‌سازی لایسنس
 */
function sc_license_handle_activate() {
    if (!sc_can_manage_license()) {
        wp_die(esc_html__('فقط مدیر کل سایت می‌تواند لایسنس را فعال کند.', 'sportclub-manager'), '', ['response' => 403]);
    }
    check_admin_referer('sc-license');

    $license_key   = !empty($_POST['sc_license_key']) ? sanitize_text_field(wp_unslash($_POST['sc_license_key'])) : '';
    $license_email = !empty($_POST['sc_license_email']) ? sanitize_email(wp_unslash($_POST['sc_license_email'])) : '';

    update_option(SC_LICENSE_MAIN_KEY, $license_key);
    update_option(SC_LICENSE_EMAIL_OPTION, $license_email);
    update_option('_site_transient_update_plugins', '');

    $lic_key_name = sc_license_get_key_option_name();
    update_option($lic_key_name, $license_key);

    $plugin_file  = defined('SC_PLUGIN_MAIN_FILE') ? SC_PLUGIN_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/sportclub_manager.php';
    $message      = '';
    $response_obj = null;
    $valid        = atomclub_Base::check_wp_plugin($license_key, $license_email, $message, $response_obj, $plugin_file)
        && sc_license_validate_response($response_obj);

    if ($valid) {
        $GLOBALS['sc_license_state'] = [
            'active'     => true,
            'response'   => $response_obj,
            'message'    => $message,
            'show_error' => false,
            'reason'     => 'ok',
        ];
        wp_safe_redirect(admin_url('admin.php?page=sc_setting&tab=license&sc_lic=activated'));
    } else {
        $fail_message = $message !== '' ? $message : __('کد لایسنس نامعتبر است.', 'sportclub-manager');
        set_transient('sc_license_activation_error_' . get_current_user_id(), $fail_message, 60);
        $GLOBALS['sc_license_state'] = [
            'active'     => false,
            'response'   => $response_obj,
            'message'    => $fail_message,
            'show_error' => true,
            'reason'     => 'invalid',
        ];
        wp_safe_redirect(admin_url('admin.php?page=sc_setting&tab=license&sc_lic=failed'));
    }
    exit;
}

/**
 * غیرفعال‌سازی لایسنس
 */
function sc_license_handle_deactivate() {
    if (!sc_can_manage_license()) {
        wp_die(esc_html__('فقط مدیر کل سایت می‌تواند لایسنس را غیرفعال کند.', 'sportclub-manager'), '', ['response' => 403]);
    }
    check_admin_referer('sc-license');

    $plugin_file = defined('SC_PLUGIN_MAIN_FILE') ? SC_PLUGIN_MAIN_FILE : dirname(dirname(dirname(__FILE__))) . '/sportclub_manager.php';
    $message     = '';
    $lic_key_name = sc_license_get_key_option_name();

    if (atomclub_Base::remove_license_key($plugin_file, $message)) {
        update_option($lic_key_name, '');
        update_option(SC_LICENSE_MAIN_KEY, '');
        update_option('_site_transient_update_plugins', '');
    }

    wp_safe_redirect(admin_url('admin.php?page=sc_setting&tab=license&sc_lic=deactivated'));
    exit;
}

/**
 * استایل صفحه لایسنس در پیشخوان
 */
function sc_license_admin_styles() {
    if (!is_admin()) {
        return;
    }
    $load = !sc_is_license_active();
    if (!$load) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $load   = $screen && strpos((string) $screen->id, 'sc_setting') !== false;
    }
    if (!$load) {
        return;
    }
    wp_enqueue_style(
        'sc-license',
        SC_ASSETS_URL . 'css/sc-license.css',
        [],
        defined('SC_PLUGIN_VERSION') ? SC_PLUGIN_VERSION : '1.0'
    );
}
add_action('admin_enqueue_scripts', 'sc_license_admin_styles');

/**
 * هشدار در صفحه افزونه‌ها (لیست Plugins)
 */
function sc_license_plugin_list_notice($plugin_file = '', $plugin_data = null) {
    unset($plugin_file, $plugin_data);
    if (sc_is_license_active() || !sc_can_manage_license()) {
        return;
    }
    echo '<tr class="plugin-update-tr active sc-license-plugin-row-notice"><td colspan="4" class="plugin-update colspanchange">';
    echo '<div class="notice notice-error inline"><p>';
    echo esc_html(sc_license_get_notice_text());
    echo ' <a href="' . esc_url(admin_url('admin.php?page=sc_setting&tab=license')) . '">' . esc_html__('فعال‌سازی لایسنس', 'sportclub-manager') . '</a>';
    echo '</p></div></td></tr>';
}
if (defined('SC_PLUGIN_MAIN_FILE')) {
    add_action('after_plugin_row_' . plugin_basename(SC_PLUGIN_MAIN_FILE), 'sc_license_plugin_list_notice', 10, 2);
}

/**
 * فقط منوی تنظیمات (تب لایسنس) وقتی لایسنس فعال نیست
 */
function sc_register_license_only_admin_menu() {
    if (!sc_can_manage_license()) {
        return;
    }
    add_menu_page(
        'فعال‌سازی لایسنس',
        'SportClub — لایسنس',
        'sc_manage_license',
        'sc_setting',
        'sc_setting_callback',
        'dashicons-lock',
        3
    );
}

/**
 * صفحات پیشخوان مجاز بدون لایسنس فعال
 *
 * @return string[]
 */
function sc_license_allowed_admin_pages() {
    return ['sc_setting'];
}

/**
 * مسدود کردن دسترسی به صفحات مدیریت افزونه
 */
function sc_license_gate_admin_pages() {
    if (sc_is_license_active() || !is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    $is_sc_page = ($page !== '' && (strpos($page, 'sc-') === 0 || $page === 'sc_setting'));

    if ($is_sc_page) {
        if (!sc_can_manage_license()) {
            wp_die(
                esc_html__('افزونه SportClub به‌دلیل غیرفعال بودن لایسنس در دسترس نیست. لطفاً با مدیر کل سایت تماس بگیرید.', 'sportclub-manager'),
                esc_html__('لایسنس غیرفعال', 'sportclub-manager'),
                ['response' => 403]
            );
        }
        if (in_array($page, sc_license_allowed_admin_pages(), true)) {
            $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
            if ($page === 'sc_setting' && $tab !== 'license') {
                wp_safe_redirect(admin_url('admin.php?page=sc_setting&tab=license'));
                exit;
            }
            return;
        }
        wp_safe_redirect(admin_url('admin.php?page=sc_setting&tab=license'));
        exit;
    }

    // بدون لایسنس، مدیر باشگاه و سایر نقش‌های افزونه به صفحات حساس وردپرس دسترسی نداشته باشند (پشتیبان roles.php)
    if (current_user_can('club_coach') && !sc_can_manage_license()) {
        $blocked_fragments = ['plugins.php', 'plugin-install.php', 'plugin-editor.php', 'themes.php', 'options-general.php', 'tools.php'];
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        foreach ($blocked_fragments as $fragment) {
            if ($uri !== '' && stripos($uri, $fragment) !== false) {
                wp_die(
                    esc_html__('شما به این بخش دسترسی ندارید.', 'sportclub-manager'),
                    esc_html__('خطای دسترسی', 'sportclub-manager'),
                    ['response' => 403]
                );
            }
        }
    }
}
add_action('admin_init', 'sc_license_gate_admin_pages', 1);

/**
 * HTML هشدار لایسنس
 */
function sc_license_render_notice_markup() {
    $url = admin_url('admin.php?page=sc_setting&tab=license');
    echo '<div class="notice notice-error sc-license-global-notice is-dismissible"><p><strong>SportClub Manager:</strong> ';
    echo esc_html(sc_license_get_notice_text());
    echo ' <a href="' . esc_url($url) . '"><strong>' . esc_html__('فعال‌سازی / تمدید لایسنس', 'sportclub-manager') . '</strong></a>';
    echo '</p></div>';
}

/**
 * اعلان در همه صفحات پیشخوان
 */
function sc_license_admin_notice() {
    if (sc_is_license_active() || !sc_can_manage_license()) {
        return;
    }
    sc_license_render_notice_markup();
}
add_action('admin_notices', 'sc_license_admin_notice', 1);
add_action('network_admin_notices', 'sc_license_admin_notice', 1);

/**
 * هشدار در نوار ابزار مدیریت (همه صفحات پیشخوان و فرانت برای مدیر)
 */
function sc_license_admin_bar_notice($wp_admin_bar) {
    if (sc_is_license_active() || !sc_can_manage_license()) {
        return;
    }
    $wp_admin_bar->add_node([
        'id'     => 'sc-license-warning',
        'parent' => 'top-secondary',
        'title'  => '<span style="color:#fcf0f1;background:#d63638;padding:2px 8px;border-radius:3px;">' . esc_html__('لایسنس SportClub غیرفعال', 'sportclub-manager') . '</span>',
        'href'   => admin_url('admin.php?page=sc_setting&tab=license'),
        'meta'   => ['class' => 'sc-license-admin-bar-notice'],
    ]);
}
add_action('admin_bar_menu', 'sc_license_admin_bar_notice', 999);

/**
 * هشدار در صفحات سایت برای مدیر (وقتی نوار ابزار نیست)
 */
function sc_license_frontend_admin_banner() {
    if (sc_is_license_active() || is_admin() || !sc_can_manage_license()) {
        return;
    }
    if (is_admin_bar_showing()) {
        return;
    }
    $url = admin_url('admin.php?page=sc_setting&tab=license');
    echo '<div class="sc-license-front-banner" style="position:fixed;top:0;left:0;right:0;z-index:999999;background:#d63638;color:#fff;padding:10px 16px;text-align:center;font-size:14px;direction:rtl;">';
    echo esc_html(sc_license_get_notice_text());
    echo ' <a href="' . esc_url($url) . '" style="color:#fff;text-decoration:underline;font-weight:bold;">' . esc_html__('رفتن به لایسنس', 'sportclub-manager') . '</a>';
    echo '</div><style>body{padding-top:48px !important;}</style>';
}
add_action('wp_body_open', 'sc_license_frontend_admin_banner', 1);
add_action('wp_footer', 'sc_license_frontend_admin_banner', 1);

/**
 * مسدود کردن درخواست‌های AJAX افزونه
 */
function sc_license_gate_ajax() {
    if (sc_is_license_active() || !wp_doing_ajax()) {
        return;
    }
    $action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
    if ($action !== '' && strpos($action, 'sc_') === 0) {
        wp_send_json_error(['message' => __('لایسنس افزونه فعال نیست.', 'sportclub-manager')], 403);
    }
}
add_action('admin_init', 'sc_license_gate_ajax', 0);

/**
 * مسدود کردن endpointهای عمومی افزونه در فرانت
 */
function sc_license_gate_public() {
    if (sc_is_license_active() || is_admin()) {
        return;
    }
    if (wp_doing_ajax()) {
        sc_license_gate_ajax();
        return;
    }
    $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    if ($uri === '') {
        return;
    }
    if (function_exists('sc_is_portal_page') && sc_is_portal_page()) {
        status_header(503);
        wp_die(
            esc_html__('سرویس باشگاه موقتاً در دسترس نیست. لطفاً با مدیر سایت تماس بگیرید.', 'sportclub-manager'),
            esc_html__('لایسنس نامعتبر', 'sportclub-manager'),
            ['response' => 503]
        );
    }
    $blocked_fragments = [
        '/my-account/',
        'sc_survey',
        'sportclub',
    ];
    foreach ($blocked_fragments as $fragment) {
        if (stripos($uri, $fragment) !== false && function_exists('is_account_page') && is_account_page()) {
            status_header(503);
            wp_die(
                esc_html__('سرویس باشگاه موقتاً در دسترس نیست. لطفاً با مدیر سایت تماس بگیرید.', 'sportclub-manager'),
                esc_html__('لایسنس نامعتبر', 'sportclub-manager'),
                ['response' => 503]
            );
        }
    }
}
add_action('template_redirect', 'sc_license_gate_public', 1);
