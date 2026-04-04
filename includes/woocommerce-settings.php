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
    $downloads = isset($items['downloads']) ? $items['downloads'] : 'فایل های دانلودی';
    
    $items['customer-logout'] = $logout;
    $items['edit-account'] = $edit_account;   
    $items['downloads'] = $downloads;
    
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




// //پایان اعمال تغییرات روی افزودن حساب کاربری در وردپرس
// add_action('template_redirect', function () {
//     // اگر کاربر وارد نشده، کاری نکن
//     if (!is_user_logged_in()) {
//         return;
//     }

//     // اطلاعات کاربر
//     $user = wp_get_current_user();

//     // اگر کاربر ادمین یا مربی است، به داشبورد وردپرس برود
//     if (in_array('administrator', $user->roles) || in_array('club_coach', $user->roles)) {
//         // فقط اگر در صفحه my-account خالی باشد
//         if (is_account_page() && !is_wc_endpoint_url()) {
//             wp_redirect(admin_url());
//             exit;
//         }
//         return;
//     }

//     // فقط برای کاربران subscriber
//     if (!in_array('subscriber', $user->roles)) {
//         return;
//     }

//     // بررسی اینکه آیا در صفحه my-account هستیم یا نه
//     if (!is_account_page()) {
//         return;
//     }

//     // بررسی مستقیم URL برای endpoint ها - این مهمترین بررسی است
//     $request_uri = isset($_SERVER['REQUEST_URI']) ? strtolower($_SERVER['REQUEST_URI']) : '';
//     $has_endpoint = false;
    
//     $endpoints_to_check = ['sc-notifications', 'sc-submit-documents', 'sc-enroll-course', 'sc-my-courses', 
//                           'sc-my-attendances', 'sc-events', 'sc-my-events', 'sc-invoices', 
//                           'sc-event-detail', 'sc-event-success', 'sc-my-honors', 'sc-wallet'];
    
//     // بررسی مستقیم در URL - این باید قبل از هر چیز دیگری بررسی شود
//     foreach ($endpoints_to_check as $endpoint) {
//         $endpoint_lower = strtolower($endpoint);
//         // بررسی با الگوهای مختلف
//         if (strpos($request_uri, '/my-account/' . $endpoint_lower . '/') !== false || 
//             strpos($request_uri, '/my-account/' . $endpoint_lower . '?') !== false ||
//             preg_match('/\/my-account\/' . preg_quote($endpoint_lower, '/') . '(\/|\?|&|$)/i', $request_uri)) {
//             $has_endpoint = true;
//             break;
//         }
//     }
    
//     // اگر endpoint پیدا نشد، بررسی query vars در $wp
//     if (!$has_endpoint) {
//         global $wp;
//         if (isset($wp->query_vars) && is_array($wp->query_vars)) {
//             foreach ($endpoints_to_check as $endpoint) {
//                 if (isset($wp->query_vars[$endpoint])) {
//                     $has_endpoint = true;
//                     break;
//                 }
//             }
//         }
//     }
    
//     // اگر endpoint وجود دارد، هیچ redirect انجام نمی‌دهیم
//     if ($has_endpoint) {
//         return;

//     }



//                    // wp_redirect(home_url('/my-account/sc-submit-documents/'), 301);

// //     $request_uri = isset($_SERVER['REQUEST_URI']) ? strtolower($_SERVER['REQUEST_URI']) : '';

// //     $query_string = isset($_SERVER['QUERY_STRING']) ? trim($_SERVER['QUERY_STRING']) : '';
// //    $path_only = trim(parse_url($request_uri, PHP_URL_PATH), '/');
// //    $q = $_GET['s'];
// //     //print_r($url1,$url2);
// //     //echo $path_only;
// //     if($path_only == site_url('my-account')  && ($query_string ||  $q)){
// //                             wp_redirect(site_url('sc-notifications'), 301);

// //     }

//     // $url1 = PHP_URL_QUERY;
//     // $url2 = PHP_URL_PATH;
//     // print_r($url1,$url2);
//     // // فقط اگر endpoint وجود نداشت و صفحه my-account خالی است، redirect می‌کنیم
//     // $query_string = isset($_SERVER['QUERY_STRING']) ? trim($_SERVER['QUERY_STRING']) : '';
//     // $path_only = trim(parse_url($request_uri, PHP_URL_PATH), '/?');
    
//     // // فقط اگر مسیر دقیقاً my-account است و query string خالی است
//     // if ($path_only === 'my-account' || $query_string) {

//     //     exit;
//     // }
// }, 5); // priority 5 تا زودتر از سایر redirect ها اجرا شود


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




// افزودن فیلتر به صفحه دسته‌بندی ووکامرس
add_action( 'woocommerce_before_shop_loop', 'add_custom_category_tag_filter', 15 );
function add_custom_category_tag_filter() {
    global $wp_query;
// فقط در صفحه دسته‌بندی (category) اجرا شود
    // if ( ! is_product_category() ) {
    //     return;
    // }
// دریافت دسته‌بندی فعلی
    $current_category = get_queried_object();
// فرم فیلتر
    echo '<div class="custom-product-filter" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px;">';
    echo '<h4 style="margin-top: 0; margin-bottom: 10px; font-size: 16px;">فیلتر محصولات</h4>';
// 1. فیلتر جستجو (Search)
    echo '<div class="input_filters">';
    echo '<div style="margin-bottom: 10px; ">';
    echo '<label for="filter-search" style="display: block; margin-bottom: 5px; font-weight: 500;">جستجو:</label>';
    echo '<input type="text" id="filter-search" name="s" placeholder="نام محصول را وارد کنید..." value="' . esc_attr( get_search_query() ) . '" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">';
    echo '</div>';
// 2. فیلتر دسته‌بندی (Categories)
    echo '<div style="margin-bottom: 10px;">';
    echo '<label for="filter-category" style="display: block; margin-bottom: 5px; font-weight: 500;">دسته‌بندی:</label>';
    echo '<select id="filter-category" name="product_cat" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">';
    echo '<option value="">همه دسته‌بندی‌ها</option>';
// دریافت تمام دسته‌بندی‌های محصولات (فقط فرزندان دسته‌بندی فعلی یا زیرمجموعه‌های آن)
    $args = array(
        'taxonomy'     => 'product_cat',
        'hide_empty'   => true,
       // 'parent'       => $current_category->term_id,
        'orderby'      => 'name',
        'order'        => 'ASC'
    );
    $categories = get_categories( $args );
foreach ( $categories as $cat ) {
        $selected = ( get_query_var( 'product_cat' ) == $cat->slug ) ? 'selected' : '';
        echo '<option value="' . esc_attr( $cat->slug ) . '" ' . $selected . '>' . esc_html( $cat->name ) . '</option>';
    }
echo '</select>';
    echo '</div>';
// 3. فیلتر برچسب‌ها (Tags)
    echo '<div style="margin-bottom: 10px;">';
    echo '<label for="filter-tag" style="display: block; margin-bottom: 5px; font-weight: 500;">برچسب:</label>';
    echo '<select id="filter-tag" name="product_tag" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">';
    echo '<option value="">همه برچسب‌ها</option>';
// دریافت تمام برچسب‌های محصولات (فقط برچسب‌های مرتبط با دسته‌بندی فعلی)
    $tags = get_terms( array(
        'taxonomy'   => 'product_tag',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC'
    ) );
foreach ( $tags as $tag ) {
        $selected = ( get_query_var( 'product_tag' ) == $tag->slug ) ? 'selected' : '';
        echo '<option value="' . esc_attr( $tag->slug ) . '" ' . $selected . '>' . esc_html( $tag->name ) . '</option>';
    }
echo '</select>';
    echo '</div>';
    echo '</div>';
// دکمه اعمال فیلتر (می‌توانید از فرم ارسال کنید)
    echo '<button type="submit" id="button_filter_custom" class="button button-primary" >اعمال فیلتر</button>';
    echo '<input type="hidden" name="filter" value="1" />'; // نشانه فیلتر فعال شده
    echo '</div>';
// اضافه کردن فرم جستجو به صفحه
    echo '<form method="get" style="display: none;">';
    echo '<input type="hidden" name="post_type" value="product" />';
    echo '<input type="hidden" name="product_cat" value="' . esc_attr( get_query_var( 'product_cat' ) ) . '" />';
    echo '<input type="hidden" name="product_tag" value="' . esc_attr( get_query_var( 'product_tag' ) ) . '" />';
    echo '</form>';
}

add_action( 'wp_enqueue_scripts', 'enqueue_custom_filter_script' );
function enqueue_custom_filter_script() {
  
wp_enqueue_script( 'custom-filter-js', get_stylesheet_directory_uri() . '/js/custom-filter.js', array( 'jquery' ), '1.0', true );
// ✅ اضافه کردن نونس به JavaScript
    wp_localize_script( 'custom-filter-js', 'ajax_object', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'filter_products_nonce' ) // ✅ این خط مهم است!
    ) );
}


// افزودن عملکرد AJAX برای فیلتر محصولات
add_action( 'wp_ajax_filter_products_ajax', 'filter_products_ajax_callback' );
add_action( 'wp_ajax_nopriv_filter_products_ajax', 'filter_products_ajax_callback' );

function filter_products_ajax_callback() {
    // بررسی نونس (Nonce) برای امنیت
   if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'filter_products_nonce' ) ) {
    wp_die( 'Unauthorized access.' );
}
// دریافت داده‌های فیلتر
    $search = sanitize_text_field( $_GET['search'] );
    $category = sanitize_text_field( $_GET['category'] );
    $tag = sanitize_text_field( $_GET['tag'] );
    
// تنظیمات کوئری محصولات
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 12, // تعداد محصولات نمایش داده شده
        'orderby' => 'price',
        'order' => 'DESC',
        'tax_query' => array(),
    );
// اگر جستجو وجود داشت
    if ( ! empty( $search ) ) {
        $args['s'] = $search;
    }
// اگر دسته‌بندی انتخاب شده بود
    if ( ! empty( $category ) ) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $category,
        );
    }
// اگر برچسب انتخاب شده بود
    if ( ! empty( $tag ) ) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_tag',
            'field'    => 'slug',
            'terms'    => $tag,
        );
    }
// اگر دو تا یا بیشتر تکسونومی داشتیم، باید AND باشه
    if ( count( $args['tax_query'] ) > 1 ) {
        $args['tax_query']['relation'] = 'AND';
    }
// اجرای کوئری
    $query = new WP_Query( $args );
// اگر محصولی وجود نداشت
    if (  $query->have_posts() ) {
        
   
// جمع‌آوری محتوای محصولات
  ob_start();
?>
<div class="woocommerce products">
    <?php
    $count = 0;
    while ( $query->have_posts() ) {
        $count++;
        $query->the_post();
        wc_get_template_part( 'content', 'product' );
    }

    ?>
</div>
<?php
$html = ob_get_clean();
$count = 'تعداد نتایج فیلتر شده : ' . $count;


    }
    else{
          ob_start();
?>
<div class="products-no-product">
    <?php
    $count = 0;
    ?>
        <div> محصولی در این فیلتر انتخابی وجود ندارد برای مشاهده تمامی محصولات به فروشگاه بروید.</div>
        <a class="button button_filter_custom" href="<?php echo site_url('shop'); ?> " >  رفتن به فروشگاه </a>

</div>
<?php
$html = ob_get_clean();
$count = 'تعداد نتایج فیلتر شده : ' . $count;

    }

// بازگشت محتوای HTML به AJAX
   wp_send_json_success(['html' => $html , 'count' => $count]);
  

// خاتمه
    wp_die();
}


//شروع محدود کردن فیلد های صورت حساب کلاسیک


/* Remove Woocommerce User Fields
این کد برای نگه داشتن اسم و نام خانوادگی و شماره و ایمیل هست اگر موارد دیگه ای میخوای باقی بمونه از اینجا پاک کن 
 */
add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );
add_filter( 'woocommerce_billing_fields' , 'custom_override_billing_fields' );
add_filter( 'woocommerce_shipping_fields' , 'custom_override_shipping_fields' );

 
function custom_override_checkout_fields( $fields ) {  
  unset($fields['billing']['billing_country']);
  unset($fields['billing']['billing_company']); 
  unset($fields['billing']['billing_address_1']);  
  unset($fields['billing']['billing_address_2']);  
  unset($fields['billing']['billing_state']);  
  unset($fields['billing']['billing_city']);  
  unset($fields['billing']['billing_email']);  
  unset($fields['billing']['billing_postcode']);  
   unset($fields['shipping']['shipping_country']);
  unset($fields['shipping']['shipping_company']);
  unset($fields['shipping']['shipping_address_2']);  
  unset( $fields['shipping']['billing_email'] );
  return $fields;
}
function custom_override_billing_fields( $fields ) {  
  unset($fields['billing_country']);
  unset($fields['billing_company']);  
  unset($fields['billing_address_2']);
  unset( $fields['billing']['billing_email'] );
  return $fields;
}
function custom_override_shipping_fields( $fields ) { 
  unset($fields['shipping_country']);
  unset($fields['shipping_company']);
  unset($fields['shipping_address_2']);
	

  return $fields;
}


add_filter( 'woocommerce_checkout_fields', 'remove_email_checkout_field' );

function remove_email_checkout_field( $fields ) {
 
	
// 	$fields['billing']['billing_state']['priority'] = 20; // استان
//     $fields['billing']['billing_city']['priority'] = 30; // شهر
    return $fields;
}
/* End - Remove Woocommerce User Fields */


// شمسی سازی تاریخ کامنت 

add_filter('get_comment_date', 'my_convert_comment_date_to_jalali', 10, 3);

function my_convert_comment_date_to_jalali($date, $format, $comment){

    // timestamp کامنت
    $timestamp =$comment->comment_date;

    // تبدیل با تابع شمسی خودت
    $jalali_date = sc_date_shamsi_date_only($timestamp); 

    return "تاریخ ثبت نظر : " . $jalali_date;
}
