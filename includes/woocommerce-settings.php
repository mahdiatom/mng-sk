<?php
/**
 * در این فایل منو های ووکامرس و فیلد های ووکامرس که در
 * 
 *  بخش حساب کاربری پنل ادمین وافزودن کاربر وردپرس هستن حدف شده اند کد های195 تا 368
 * مواردی هم در admin.css حذف شده اند
 * 
 * WooCommerce Settings
 * تنظیمات ووکامرس - غیرفعال کردن منوهای پیش‌فرض حساب کاربری
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * غیرفعال کردن منوهای پیش‌فرض حساب کاربری ووکامرس
 * فقط "خروج" باقی می‌ماند
 * 
 * اولویت 5 برای اجرا قبل از sc_add_my_account_menu_item (اولویت 10)
 */
add_filter('woocommerce_account_menu_items', 'sc_remove_default_account_menu_items', 5, 1);
function sc_remove_default_account_menu_items($items) {
    // اگر کاربر مدیر است، منوهای پیش‌فرض را نگه دار
    if (current_user_can('manage_options')) {
        return $items;
    }
    

    // حذف تمام منوهای پیش‌فرض
    $items = [];
      // فقط "خروج" را نگه دار و بقیه را حذف کن
    $logout = isset($items['customer-logout']) ? $items['customer-logout'] : 'خروج از حساب کاربری';
    $edit_account = isset($items['edit-account']) ? $items['edit-account'] : 'تغییر اطلاعات حساب';
    
    $items['customer-logout'] = $logout;
    $items['edit-account'] = $edit_account;
    
    return $items;
}
add_action('woocommerce_edit_account_form_fields' , 'add_text_before_password');
function add_text_before_password(){
 echo '<p>کاربر عزیز در صورتی که قصد تغییر رمز خود را دارید و رمز پیشین خود را نمی دانید از مدیر مجموعه بخواهید تا رمز شما را به صورت دستی تغییر دهد و در اختیار تان قرار دهد سپس رمزی که مدیر داده است را به عنوان رمز پیشین وارد کنید و سپس رمز جدید خود را وارد کنید .</p>';
}
/**
 * حذف فیلدهای اضافی از صفحه ویرایش کاربر WordPress
 * فقط نام کاربری، رمز عبور و ایمیل نمایش داده می‌شود
 */
add_action('admin_init', 'sc_remove_user_profile_fields');
function sc_remove_user_profile_fields() {
    // حذف بخش‌های اضافی از صفحه ویرایش کاربر
    remove_action('show_user_profile', 'wp_user_contactmethods');
    remove_action('edit_user_profile', 'wp_user_contactmethods');
    
    // حذف فیلدهای WooCommerce از صفحه ویرایش کاربر
    if (class_exists('WooCommerce')) {
        // حذف فیلدهای billing و shipping
        add_filter('woocommerce_customer_meta_fields', '__return_empty_array', 999);
        
        // حذف فیلدهای اضافی WooCommerce
        remove_action('show_user_profile', array('WC_Admin_Profile', 'add_customer_meta_fields'));
        remove_action('edit_user_profile', array('WC_Admin_Profile', 'add_customer_meta_fields'));
        remove_action('personal_options_update', array('WC_Admin_Profile', 'save_customer_meta_fields'));
        remove_action('edit_user_profile_update', array('WC_Admin_Profile', 'save_customer_meta_fields'));
    }
}

/**
 * حذف فیلدهای اضافی از صفحه ویرایش کاربر با استفاده از CSS و JavaScript
 */
add_action('admin_head-user-edit.php', 'sc_hide_user_profile_fields');
add_action('admin_head-profile.php', 'sc_hide_user_profile_fields');
function sc_hide_user_profile_fields() {
    ?>
    <style>
        
        /* حذف بخش Contact Info */
        #your-profile h2:contains('Contact Info'),
        #your-profile .user-description-wrap,
        #your-profile .user-url-wrap,
        #your-profile .user-first-name-wrap,
        #your-profile .user-last-name-wrap,
        #your-profile .user-nickname-wrap,
        #your-profile .user-display-name-wrap {
            display: none !important;
        }
        
        /* حذف بخش About Yourself */
        #your-profile h2:contains('About Yourself'),
        #your-profile .user-rich-editing-wrap,
        #your-profile .user-syntax-highlighting-wrap,
        #your-profile .user-comment-shortcuts-wrap,
        #your-profile .user-admin-color-wrap,
        #your-profile .user-admin-bar-front-wrap,
        #your-profile .user-language-wrap {
            display: none !important;
        }
        
        /* حذف بخش Account Management */
        #your-profile h2:contains('Account Management'),
        #your-profile .user-sessions-wrap {
            display: none !important;
        }
        
        /* حذف فیلدهای WooCommerce */
        #your-profile .woocommerce-customer-data,
        #your-profile h2:contains('Billing'),
        #your-profile h2:contains('Shipping'),
        #your-profile .form-table:has(th:contains('Billing')),
        #your-profile .form-table:has(th:contains('Shipping')) {
            display: none !important;
        }
       
        /* حذف تمام فیلدها به جز نام کاربری، رمز و ایمیل */
        #your-profile .form-table tr:not(:has(#user_login)):not(:has(#user_pass)):not(:has(#user_email)) {
            display: none !important;
        }
    </style>
    <script>
        jQuery(document).ready(function($) {
            // حذف بخش‌های اضافی با JavaScript
            $('#your-profile h2').each(function() {
                var $h2 = $(this);
                var text = $h2.text().toLowerCase();
                if (text.indexOf('contact') !== -1 || 
                    text.indexOf('about') !== -1 || 
                    text.indexOf('account management') !== -1 ||
                    text.indexOf('billing') !== -1 ||
                    text.indexOf('shipping') !== -1 ||
                    text.indexOf('اطلاعات تماس') !== -1 ||
                    text.indexOf('درباره') !== -1 ||
                    text.indexOf('مدیریت') !== -1 ||
                    text.indexOf('آدرس') !== -1) {
                    // حذف h2 و تمام محتوای بعد از آن تا h2 بعدی
                    var $next = $h2.nextUntil('h2');
                    $h2.hide();
                    $next.hide();
                }
            });
            
            // حذف فیلدهای اضافی
            $('#your-profile .form-table tr').each(function() {
                var $row = $(this);
                var hasLogin = $row.find('#user_login').length > 0;
                var hasPass = $row.find('#user_pass, #pass1').length > 0;
                var hasEmail = $row.find('#user_email').length > 0;
                
                if (!hasLogin && !hasPass && !hasEmail) {
                    $row.hide();
                }
            });
            
            // حذف فیلدهای WooCommerce
            $('#your-profile').find('tr').each(function() {
                var $row = $(this);
                var thText = $row.find('th').text().toLowerCase();
                if (thText.indexOf('billing') !== -1 || 
                    thText.indexOf('shipping') !== -1 ||
                    thText.indexOf('آدرس') !== -1) {
                    $row.hide();
                }
            });
        });
    </script>
    <?php
}

/**
 * حذف فیلدهای اضافی با استفاده از filter
 */
add_filter('user_contactmethods', '__return_empty_array', 999);
add_filter('show_password_fields', '__return_true', 999);



/**
 * 1- حذف هزینه‌ها فقط برای کاربر (My Account)
 */
add_filter( 'woocommerce_get_order_item_totals', 'my_plugin_hide_order_totals_for_customer', 10, 2 );

function my_plugin_hide_order_totals_for_customer( $totals, $order ) {

    // فقط فرانت‌اند
    if ( is_admin() ) {
        return $totals;
    }

    // فقط صفحه حساب کاربری
    if ( ! is_account_page() ) {
        return $totals;
    }

    // حذف هزینه‌ها
    unset( $totals['cart_subtotal'] );
    unset( $totals['shipping'] );
    unset( $totals['tax'] );
    unset( $totals['order_total'] );
    unset( $totals['payment_method'] );

    return $totals;
}
/**
 * Admin User Profile Cleanup + Keep WooCommerce Phone
 * Prefix: myadmin_
 */

/*--------------------------------------------------------------
1. حذف فیلدهای تماس پیش‌فرض وردپرس
--------------------------------------------------------------*/
add_filter('user_contactmethods', '__return_empty_array', 999);

/*--------------------------------------------------------------
2. نگه داشتن فقط شماره تلفن ووکامرس
--------------------------------------------------------------*/
add_filter('woocommerce_customer_meta_fields', 'myadmin_keep_only_wc_phone', 999);
function myadmin_keep_only_wc_phone($fields) {

    if (isset($fields['billing']['fields'])) {
        foreach ($fields['billing']['fields'] as $key => $field) {
            if ($key !== 'billing_phone') {
                unset($fields['billing']['fields'][$key]);
            }
        }
    }

    // حذف کامل shipping
    unset($fields['shipping']);

    return $fields;
}

/*--------------------------------------------------------------
3. جلوگیری از ذخیره متاهای اضافه ووکامرس
--------------------------------------------------------------*/
add_action('admin_init', 'myadmin_disable_wc_profile_save');
function myadmin_disable_wc_profile_save() {
    if (class_exists('WooCommerce')) {
        remove_action('personal_options_update', ['WC_Admin_Profile', 'save_customer_meta_fields']);
        remove_action('edit_user_profile_update', ['WC_Admin_Profile', 'save_customer_meta_fields']);
    }
}

/*--------------------------------------------------------------
4. مخفی‌سازی فیلدهای اضافی در پنل ادمین
--------------------------------------------------------------*/
add_action('admin_head-user-edit.php', 'myadmin_hide_user_profile_fields');
add_action('admin_head-user-new.php', 'myadmin_hide_user_profile_fields');
add_action('admin_head-profile.php', 'myadmin_hide_user_profile_fields');

function myadmin_hide_user_profile_fields() {
?>
<style>
</style>
<?php
}

/*--------------------------------------------------------------
5. افزودن فیلد شماره تلفن در صفحه افزودن کاربر
--------------------------------------------------------------*/
add_action('user_new_form', 'myadmin_add_phone_to_user_new');
function myadmin_add_phone_to_user_new() {
?>
<h3>اطلاعات تماس</h3>
<table class="form-table">
    <tr>
        <th><label for="billing_phone">شماره تلفن</label></th>
        <td>
            <input type="text" name="billing_phone" id="billing_phone" class="regular-text">
        </td>
    </tr>
</table>
<?php
}

/*--------------------------------------------------------------
6. ذخیره شماره تلفن هنگام ایجاد کاربر
--------------------------------------------------------------*/
add_action('user_register', 'myadmin_save_phone_on_register');
function myadmin_save_phone_on_register($user_id) {
    if (!empty($_POST['billing_phone'])) {
        update_user_meta(
            $user_id,
            'billing_phone',
            sanitize_text_field($_POST['billing_phone'])
        );
    }
}

/*--------------------------------------------------------------
7. نمایش فیلد رمز عبور
--------------------------------------------------------------*/
add_filter('show_password_fields', '__return_true', 999);

/*--------------------------------------------------------------
8. مخفی کردن جمع کل سفارش برای کاربر در My Account
--------------------------------------------------------------*/
add_filter('woocommerce_get_order_item_totals', 'myadmin_hide_order_totals', 10, 2);
function myadmin_hide_order_totals($totals, $order) {

    if (is_admin() || !is_account_page()) {
        return $totals;
    }

    unset($totals['cart_subtotal']);
    unset($totals['shipping']);
    unset($totals['tax']);
    unset($totals['order_total']);
    unset($totals['payment_method']);

    return $totals;
}
add_action('user_register', function ($user_id) {
    delete_user_meta($user_id, 'user_url');
});

add_action('admin_footer-user-new.php', 'myadmin_remove_website_field_js');
function myadmin_remove_website_field_js() {
?>
<script>
jQuery(document).ready(function($) {

    // حذف ردیف وب‌سایت
    $('#url').closest('tr').remove();

    // حذف زبان
    $('.user-language-wrap').remove();

    // حذف ارسال آگاه‌ساز
    $('#send_user_notification').closest('tr').remove();

});
</script>
<?php
}
add_action('user_new_form', 'myadmin_add_username_note');
function myadmin_add_username_note() {
    ?>
    <script>
    jQuery(document).ready(function($){
        // اضافه کردن متن زیر فیلد نام کاربری
        $('#user_login').after('<p class="description">برای عدم اختلال در فرایند ها لطفا شماره کاربر را به عنوان نام کاربری تعریف کنید نام کاربری فقط برای ورود به سامانه مورد استفاده قرار میگیرد و پیامک های ارسال به آن انجام می شود.همچنین با وارد کردن نام کاربری ایمیل به صورت خودکار ساخته خواهد شد.</p>');
    });
    </script>
    <?php
}

add_action('admin_footer-user-new.php', 'myadmin_autofill_email_js');
function myadmin_autofill_email_js() {
    ?>
    <script>
    jQuery(document).ready(function($){
        // دامنه سایت خودت را مشخص کن
        var domain = 'gmail.com'; 

        // وقتی نام کاربری تغییر کرد
        $('#user_login').on('input', function() {
            var username = $(this).val().trim();

            // اگر خالی بود، ایمیل هم خالی باشد
            if(username === '') {
                $('#email').val('');
                return;
            }

            // ایمیل ساخته شود
            var email = username + '@' + domain;
            $('#email').val(email);
        });
    });
    </script>
    <?php
}




//پایان اعمال تغییرات روی افزودن حساب کاربری در وردپرس
add_action('template_redirect', function () {
    // اگر کاربر وارد نشده، کاری نکن
    if (!is_user_logged_in()) {
        return;
    }

    // اطلاعات کاربر
    $user = wp_get_current_user();

    // اگر کاربر ادمین یا مربی است، به داشبورد وردپرس برود
    if (in_array('administrator', $user->roles) || in_array('club_coach', $user->roles)) {
        // فقط اگر در صفحه my-account خالی باشد
        if (is_account_page() && !is_wc_endpoint_url()) {
            wp_redirect(admin_url());
            exit;
        }
        return;
    }

    // فقط برای کاربران subscriber
    if (!in_array('subscriber', $user->roles)) {
        return;
    }

    // بررسی اینکه آیا در صفحه my-account هستیم یا نه
    if (!is_account_page()) {
        return;
    }

    // بررسی مستقیم URL برای endpoint ها - این مهمترین بررسی است
    $request_uri = isset($_SERVER['REQUEST_URI']) ? strtolower($_SERVER['REQUEST_URI']) : '';
    $has_endpoint = false;
    
    $endpoints_to_check = ['sc-notifications', 'sc-submit-documents', 'sc-enroll-course', 'sc-my-courses', 
                          'sc-my-attendances', 'sc-events', 'sc-my-events', 'sc-invoices', 
                          'sc-event-detail', 'sc-event-success', 'sc-my-honors', 'sc-wallet'];
    
    // بررسی مستقیم در URL - این باید قبل از هر چیز دیگری بررسی شود
    foreach ($endpoints_to_check as $endpoint) {
        $endpoint_lower = strtolower($endpoint);
        // بررسی با الگوهای مختلف
        if (strpos($request_uri, '/my-account/' . $endpoint_lower . '/') !== false || 
            strpos($request_uri, '/my-account/' . $endpoint_lower . '?') !== false ||
            preg_match('/\/my-account\/' . preg_quote($endpoint_lower, '/') . '(\/|\?|&|$)/i', $request_uri)) {
            $has_endpoint = true;
            break;
        }
    }
    
    // اگر endpoint پیدا نشد، بررسی query vars در $wp
    if (!$has_endpoint) {
        global $wp;
        if (isset($wp->query_vars) && is_array($wp->query_vars)) {
            foreach ($endpoints_to_check as $endpoint) {
                if (isset($wp->query_vars[$endpoint])) {
                    $has_endpoint = true;
                    break;
                }
            }
        }
    }
    
    // اگر endpoint وجود دارد، هیچ redirect انجام نمی‌دهیم
    if ($has_endpoint) {
        return;

    }



                    wp_redirect(home_url('/my-account/sc-submit-documents/'), 301);

//     $request_uri = isset($_SERVER['REQUEST_URI']) ? strtolower($_SERVER['REQUEST_URI']) : '';

//     $query_string = isset($_SERVER['QUERY_STRING']) ? trim($_SERVER['QUERY_STRING']) : '';
//    $path_only = trim(parse_url($request_uri, PHP_URL_PATH), '/');
//    $q = $_GET['s'];
//     //print_r($url1,$url2);
//     //echo $path_only;
//     if($path_only == site_url('my-account')  && ($query_string ||  $q)){
//                             wp_redirect(site_url('sc-notifications'), 301);

//     }

    // $url1 = PHP_URL_QUERY;
    // $url2 = PHP_URL_PATH;
    // print_r($url1,$url2);
    // // فقط اگر endpoint وجود نداشت و صفحه my-account خالی است، redirect می‌کنیم
    // $query_string = isset($_SERVER['QUERY_STRING']) ? trim($_SERVER['QUERY_STRING']) : '';
    // $path_only = trim(parse_url($request_uri, PHP_URL_PATH), '/?');
    
    // // فقط اگر مسیر دقیقاً my-account است و query string خالی است
    // if ($path_only === 'my-account' || $query_string) {

    //     exit;
    // }
}, 5); // priority 5 تا زودتر از سایر redirect ها اجرا شود


// ارتباط حذف بین رویداد و صورت حساب افزونه و صورت حساب ووکامرس
add_action('woocommerce_before_delete_order', function($order_id) {
    global $wpdb;
    $registrations_table = $wpdb->prefix . 'sc_event_registrations';

    // حذف ثبت‌نام‌هایی که با این سفارش مرتبط هستند
    $wpdb->delete(
        $registrations_table,
        ['woocommerce_order_id' => $order_id],
        ['%d']
    );
});







