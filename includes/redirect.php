<?php 
if (!defined('ABSPATH')) {
    exit;
}
add_action('template_redirect', 'redirect_my_account_shop_to_shop');
function redirect_my_account_shop_to_shop() {
    // در پنل ادمین و درخواست‌های AJAX/REST کاری انجام نده
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    // آدرس فعلی را دریافت می‌کنیم
    $request_path = isset($GLOBALS['wp']->request) ? trim((string) $GLOBALS['wp']->request, '/') : '';
    $current_url  = home_url($request_path);

    // بررسی اینکه آیا آدرس مطابق الگوی مورد نظر است یا خیر
    $pattern_shop      = home_url('/my-account/shop');
    $pattern_myaccount = home_url('/my-account');

    // اگر کاربر لاگین نکرده باشد و وارد یکی از صفحات /my-account/ شود،
    // به جای نمایش فرم پیش‌فرض ووکامرس، او را به صفحه ورود سفارشی هدایت کن
    if (!is_user_logged_in()) {
        $is_my_account_url = ($request_path === 'my-account' || strpos($request_path, 'my-account/') === 0);
        $is_portal_url     = ($request_path === 'portal' || strpos($request_path, 'portal/') === 0);
        if (!$is_my_account_url && function_exists('is_account_page')) {
            // در مواردی که permalink ووکامرس متفاوت است، با تابع رسمی هم بررسی کن
            $is_my_account_url = is_account_page();
        }

        if ($is_my_account_url || $is_portal_url) {
            $login_page_id = function_exists('sc_get_setting') ? (int) sc_get_setting('sc_login_page_id', 0) : 0;
            if ($login_page_id > 0) {
                $login_page_url = get_permalink($login_page_id);
                // اطمینان از اینکه روی همان صفحه ورود نیستیم تا حلقه ریدایرکت پیش نیاید
                $current_page_id = is_page() ? (int) get_queried_object_id() : 0;
                if ($login_page_url && $current_page_id !== $login_page_id) {
                    wp_safe_redirect($login_page_url);
                    exit;
                }
            }
        }
    }

    if ($current_url === $pattern_shop) {
        $redirect_url = home_url('/shop/');
        wp_redirect($redirect_url, 301);
        exit;
    }

    // ریدایرکت /my-account/ به صفحه ارسال مدارک فقط برای کاربرانی که لاگین هستند
    if ($current_url === $pattern_myaccount && is_user_logged_in()) {
        $redirect_url2 = home_url('/my-account/sc-submit-documents/');
        wp_redirect($redirect_url2, 301);
        exit;
    }

    $pattern_main_page = home_url('');
    $not_subscriber = (current_user_can('club_coach') || current_user_can('system_manager') || current_user_can('coach') || current_user_can('shop_manager') || current_user_can('accountantt') || current_user_can('administrator')) ? true : false;

    if ($current_url === $pattern_main_page && is_user_logged_in() && !$not_subscriber) {
        $redirect_url3 = home_url('/my-account/sc-submit-documents/');
        wp_redirect($redirect_url3, 301);
        exit;
    } elseif ($current_url === $pattern_main_page && is_user_logged_in() && $not_subscriber) {
        $redirect_url4 = home_url('/wp-admin');
        wp_redirect($redirect_url4, 301);
        exit;
    }
}
