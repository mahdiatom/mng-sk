<?php 
add_action('template_redirect', 'redirect_my_account_shop_to_shop');
function redirect_my_account_shop_to_shop() {
    // بررسی اینکه آیا کاربر در بخش فرانت‌اند هست (نه در پنل ادمین)
   
// آدرس فعلی را دریافت می‌کنیم
    $current_url = home_url(add_query_arg(array(), $GLOBALS['wp']->request));
// بررسی اینکه آیا آدرس مطابق الگوی مورد نظر است یا خیر
    $pattern_shop = home_url('/my-account/shop');
    $pattern_myaccount = home_url('/my-account');

if ($current_url === $pattern_shop) {
        // آدرس مقصد
        $redirect_url = home_url('/shop/');
// ریدایرکت با کد 301 (توصیه می‌شود برای SEO)
        wp_redirect($redirect_url, 301);
        
    }
if ($current_url === $pattern_myaccount) {
        // آدرس مقصد
        $redirect_url2 = home_url('/my-account/sc-submit-documents/');
// ریدایرکت با کد 301 (توصیه می‌شود برای SEO)
        wp_redirect($redirect_url2, 301);
        
    }

    $pattern_main_page = home_url('');
    $not_subscriber = ((current_user_can('club_coach') || current_user_can('coach') || current_user_can('shop_manager') || current_user_can('accountantt') || current_user_can('administrator')  ) ) ? true : false ; 
    
if ($current_url === $pattern_main_page && is_user_logged_in() && !$not_subscriber) {
        // آدرس مقصد
        $redirect_url3 = home_url('/my-account/sc-submit-documents/');
// ریدایرکت با کد 301 (توصیه می‌شود برای SEO)
        wp_redirect($redirect_url3, 301);
        
    }elseif($current_url === $pattern_main_page && is_user_logged_in() && $not_subscriber ){

                // آدرس مقصد
        $redirect_url4 = home_url('/wp-admin');
// ریدایرکت با کد 301 (توصیه می‌شود برای SEO)
        wp_redirect($redirect_url4, 301);
    }
  
    
}
