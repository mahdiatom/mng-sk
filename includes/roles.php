<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * حذف امن منوی ادمین — در admin-post.php آرایه $menu ساخته نمی‌شود.
 *
 * @param string $menu_slug
 * @return array|false
 */
function sc_safe_remove_menu_page($menu_slug) {
    global $menu;
    if (!is_array($menu)) {
        return false;
    }
    return remove_menu_page($menu_slug);
}

/**
 * ===============================
 * ایجاد نقش مدیر باشگاه (DEV MODE)
 * ===============================
*/

function club_create_club_coach_role() {

          // remove_role('club_coach');

    // اگر نقش وجود ندارد، ایجاد شود
    if ( ! get_role('club_coach') ) {
        $admin_role = get_role('administrator');
        if ( $admin_role ) {
            add_role(
                'club_coach',
                'مدیر باشگاه',
                $admin_role->capabilities
            );
        }
    }
}
add_action('admin_init', 'club_create_club_coach_role');

/**
 * نقش‌های هم‌سطح «مدیر باشگاه» (دسترسی و محدودیت یکسان).
 *
 * @return string[]
 */
function sc_get_club_manager_role_slugs() {
    return ['club_coach', 'system_manager'];
}

/**
 * آیا کاربر یکی از نقش‌های مدیر باشگاه / مدیر سامانه را دارد؟
 *
 * @param int $user_id
 * @return bool
 */
function sc_user_has_club_manager_role($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return false;
    }
    $user = get_userdata($user_id);
    if (!$user || empty($user->roles)) {
        return false;
    }
    return (bool) array_intersect(sc_get_club_manager_role_slugs(), (array) $user->roles);
}

/**
 * ===============================
 * ایجاد نقش مدیر سامانه (کپی مدیر باشگاه)
 * ===============================
 */
function club_create_system_manager_role() {
    if (!get_role('club_coach')) {
        club_create_club_coach_role();
    }
    $source_role = get_role('club_coach');
    if (!$source_role) {
        return;
    }

    $caps = (array) $source_role->capabilities;
    $caps['system_manager'] = true;

    $target_role = get_role('system_manager');
    if (!$target_role) {
        add_role('system_manager', 'مدیر سامانه', $caps);
        return;
    }

    foreach ($caps as $cap => $grant) {
        if ($grant && !$target_role->has_cap($cap)) {
            $target_role->add_cap($cap);
        }
    }
}
add_action('admin_init', 'club_create_system_manager_role', 20);

/**
 * مدیر سامانه در تمام بررسی‌های club_coach هم معتبر است.
 */
add_filter('user_has_cap', 'sc_map_system_manager_to_club_coach_cap', 9, 4);
function sc_map_system_manager_to_club_coach_cap($allcaps, $caps, $args, $user) {
    if (!empty($allcaps['system_manager']) && $allcaps['system_manager']) {
        $allcaps['club_coach'] = true;
    }
    return $allcaps;
}

/**
 * ===============================
 * ایجاد نقش مربی
 * ===============================
 */
function sc_create_coach_role() {
       //  remove_role('coach');

    // اگر نقش وجود ندارد، ایجاد شود
    if ( ! get_role('coach') ) {
        // استفاده از capabilities مشابه subscriber اما با دسترسی محدود
        $coach_caps = get_role('club_coach')->capabilities;
        // اضافه کردن دسترسی به حضور و غیاب
        $coach_caps['read'] = true;
        $coach_caps['coach'] = true; // برای تشخیص نقش مربی در current_user_can('coach')
        $coach_caps['sc_manage_attendance'] = true; // capability سفارشی برای حضور و غیاب
        $coach_caps['sc_view_coach_salary'] = true; // دسترسی به دستمزد و کیف پول مربی
        
        add_role(
            'coach',
            'مربی',
            $coach_caps
        );
    } else {
        // اگر نقش وجود دارد، capability را اضافه کن
        $coach_role = get_role('coach');
        if ($coach_role) {
            if (!$coach_role->has_cap('coach')) {
                $coach_role->add_cap('coach');
            }
            if (!$coach_role->has_cap('sc_manage_attendance')) {
                $coach_role->add_cap('sc_manage_attendance');
            }
            if (!$coach_role->has_cap('sc_view_coach_salary')) {
                $coach_role->add_cap('sc_view_coach_salary');
            }
        }
    }
}

// ثبت hook برای ایجاد نقش مربی
add_action('admin_init', 'sc_create_coach_role');

/**
 * ===============================
 * ایجاد نقش حسابدار
 * ===============================
 */
function sc_create_accountant_role(){
   // remove_role('accountantt');

    if( get_role('accountantt')){
        return;
    }
    else{
        $admin_role = get_role('club_coach');
        add_role(
            'accountantt',
            'حسابدار باشگاه',
            $admin_role->capabilities
        );
      
$accountant_caps = get_role('accountantt')->capabilities;
  foreach($accountant_caps as $key => $accountant_cap){
        $accountant_caps[$key] = false;


  }

  $accountant_caps = get_role('accountantt')->capabilities;
  $accountant_caps['accountantt'] = true;
     }
}
add_action('admin_init', 'sc_create_accountant_role');

/**
 * دسترسی‌های ووکامرس (محصولات، سفارشات، کوپن، گزارش) — مشترک بین مدیر فروشگاه و حسابدار.
 *
 * @return array<string, bool>
 */
function sc_get_shop_woocommerce_capabilities() {
    return array(
        'read' => true,
        'edit_products' => true,
        'delete_products' => true,
        'publish_products' => true,
        'edit_published_products' => true,
        'delete_published_products' => true,
        'edit_others_products' => true,
        'delete_others_products' => true,
        'read_private_products' => true,
        'edit_private_products' => true,
        'delete_private_products' => true,
        'edit_shop_orders' => true,
        'read_shop_orders' => true,
        'delete_shop_orders' => true,
        'edit_published_shop_orders' => true,
        'delete_published_shop_orders' => true,
        'edit_others_shop_orders' => true,
        'delete_others_shop_orders' => true,
        'read_private_shop_orders' => true,
        'edit_private_shop_orders' => true,
        'delete_private_shop_orders' => true,
        'edit_shop_coupons' => true,
        'read_shop_coupons' => true,
        'delete_shop_coupons' => true,
        'edit_published_shop_coupons' => true,
        'delete_published_shop_coupons' => true,
        'edit_others_shop_coupons' => true,
        'delete_others_shop_coupons' => true,
        'manage_woocommerce' => true,
        'view_woocommerce_reports' => true,
    );
}

/**
 * اعطای دسترسی فروشگاه/محصولات به نقش حسابدار (برای نقش‌های از قبل ساخته‌شده).
 */
function sc_grant_shop_capabilities_to_accountant() {
    $role = get_role('accountantt');
    if (!$role) {
        return;
    }
    foreach (sc_get_shop_woocommerce_capabilities() as $cap => $grant) {
        if ($grant && !$role->has_cap($cap)) {
            $role->add_cap($cap);
        }
    }
}
add_action('admin_init', 'sc_grant_shop_capabilities_to_accountant', 10);

/**
 * اعطای دسترسی اطلاعیه و پیامک به نقش حسابدار.
 */
function sc_grant_notifications_capabilities_to_accountant() {
    $role = get_role('accountantt');
    if (!$role) {
        return;
    }
    if (!$role->has_cap('sc_manage_notifications')) {
        $role->add_cap('sc_manage_notifications');
    }
}
add_action('admin_init', 'sc_grant_notifications_capabilities_to_accountant', 10);

add_filter('user_has_cap', 'sc_manage_notifications_cap', 10, 4);
function sc_manage_notifications_cap($allcaps, $caps, $args, $user) {
    foreach ($caps as $cap) {
        if ($cap === 'sc_manage_notifications') {
            if (
                (!empty($allcaps['manage_options']) && $allcaps['manage_options']) ||
                (!empty($allcaps['accountantt']) && $allcaps['accountantt'])
            ) {
                $allcaps['sc_manage_notifications'] = true;
            }
        }
    }
    return $allcaps;
}

/**
 * ===============================
 * ایجاد نقش - بررسی مدیر فروشگاه
 * ===============================
 */
// بررسی نقش مدیر فروشگاه و دادن دسترسی های لازم

add_action('admin_init', 'club_recreate_shop_manager_role');
function club_recreate_shop_manager_role() {
//remove_role('shop_manager');
    // بررسی اینکه آیا نقش shop_manager وجود دارد یا خیر
    if ( ! get_role('shop_manager') ) {
        
        // ایجاد دوباره نقش مدیر فروشگاه
        add_role(
            'shop_manager',
            'مدیر فروشگاه',
            array_merge(
                sc_get_shop_woocommerce_capabilities(),
                array(
                    'read_private_pages' => true,
                    'edit_private_pages' => true,
                    'delete_private_pages' => true,
                    'edit_published_pages' => true,
                    'delete_published_pages' => true,
                )
            )
        );

   
}


}


    /**
     * ===============================
     * مدیر فروشگاه -  جلوگیری از دسترسی مستقیم (SECURITY)
     * ===============================
     */
    add_action('admin_menu', 'club_block_restricted_pages_for_shop_manager');
    function club_block_restricted_pages_for_shop_manager() {
         if ( current_user_can('shop_manager')) {
            global $menu;
        
      //  print_r($menu);
            if (!is_array($menu)) {
                return;
            }
            foreach ($menu as $key => $item) {
                
                if (isset($item[2])) {
            
                    // منوی دستمزد و کیف پول فقط در صورت فعال بودن امکانات پرو نمایش داده می‌شود
                    if (
                         
                         $item[2] !== 'edit.php?post_type=product' &&
                         $item[2] !== 'sc-products' &&
                         $item[2] !== 'page=wc-admin' && 
                         $item[2] !== 'index.php'
                
                    ) {
                        sc_safe_remove_menu_page($item[2]);
                    }
                }
            }
        
       
             sc_safe_remove_menu_page('woocommerce');
             sc_safe_remove_menu_page('woocommerce-marketing');
             sc_safe_remove_menu_page('profile.php');
            $allowed_pages = [
                
                'wc-orders',
                'coupons-moved',
                'wc-reports',
                'sc_orders',
                'sc-products',
                'wc-admin',
                'post-new.php',
                'shop_coupon'

                
                
            ];

            $blocked_pages = array(
            // وردپرس
            'plugins.php',
            'plugin-editor.php',
            'themes.php',
            'upload.php',
            //'edit.php',
            //'edit-tags.php',
            //'post-new.php',
            'edit-comments.php',
            'options-general.php',
            'tools.php',
            'options-writing.php',
            'options-reading.php',
            'options-media.php',
            'options-privacy.php',
            'media-new.php',
            'edit.php?post_type=page',
            'post-new.php?post_type=page',
            'users.php',
            'options-permalink.php',

            // المنتور
            'elementor',
            'hello-elementor',

            // ووکامرس اصلی
         //   'wc-admin',
        // 'wc-orders',
            'wc-settings',
            'wc-status',
        //  'wc-reports',
        //    'coupons-moved',

            // ووکامرس admin + analytics + marketing
            '/analytics',
            '/analytics/overview',
            '/analytics/products',
            '/analytics/orders',
            '/analytics/variations',
            '/analytics/categories',
            '/analytics/taxes',
            '/analytics/coupons',
            '/analytics/stock',
            '/analytics/settings',
            '/analytics/downloads',
            '/analytics/revenue',
            '/marketing',

            // محصولات و کوپن‌ها
        // 'product',
        // 'shop_coupon'
            );
                 
            // وقتی امکانات «کیف پول مربیان و دستمزد» غیرفعال است، مدیریت مربیان و دستمزد مسدود می‌شوند
            if ( ! ( function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') && sc_is_pro_feature_coaches_wallet_salary_enabled() ) ) {
                $blocked_pages = array_merge($blocked_pages, [
                    'sc-coach-management',
                    'sc-coach-salary',
                    'sc-coach-wallet',
                    'sc-coach-withdrawals',
                ]);
            }

        
            $page = $_GET['page'] ?? '';
            $path = $_GET['path'] ?? '';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $post_type = $_GET['post_type'] ?? '';
         

        foreach ( $blocked_pages as $blocked ) {
            if (
                 (
                strpos($page, $blocked) !== false ||
                strpos($path, $blocked) !== false ||
                strpos($post_type, $blocked) !== false ||
                strpos($uri, $blocked) !== false
             ) ||
             (
               is_admin() && !empty($page) && !in_array($page, $allowed_pages) 
             )
              ) {
                wp_die(
                    '
                    <div >
                    <h2 style="text-align: left;">Access Denied</h2>
                    <p style="text-align: left;">نقش شما مدیر فروشگاه است - شما به این بخش دسترسی ندارید </p>',
                    'خطای دسترسی',
                    array('response' => 403)
                );
            }
        }
    }
    }


















//مدیر باشگاه

/**
 * ===============================
 * سطح دسترسی مدیر باشگاه(club_coach)
 * مدیر به تمامی امکانانات سامانه دسترسی دارد به جز موارد امنینتی و مهم 
 * ===============================
 */
add_action('admin_init', 'club_add_woocommerce_capabilities_to_club_coach',99);
function club_add_woocommerce_capabilities_to_club_coach() {
    $club_coach_role = get_role('club_coach');
    if ( ! $club_coach_role ) {
        club_create_club_coach_role();
        return;
    }
    if(current_user_can('club_coach')){
// حذف دسترسی های اضافی مدیر باشگاه  
   
            // حذف منوهای وردپرس
            sc_safe_remove_menu_page('plugins.php');
            sc_safe_remove_menu_page('themes.php');
            //sc_safe_remove_menu_page('edit.php');
            //sc_safe_remove_menu_page('edit.php?post_type=page');
            sc_safe_remove_menu_page('edit-comments.php');
            sc_safe_remove_menu_page('options-general.php');
            sc_safe_remove_menu_page('tools.php');
            
            // حذف منوهای المنتور
            sc_safe_remove_menu_page('elementor');
            sc_safe_remove_menu_page('edit.php?post_type=elementor_library');
            sc_safe_remove_menu_page('hello-elementor');
    
            // حذف منوهای ووکامرس
           sc_safe_remove_menu_page('woocommerce');
           sc_safe_remove_menu_page('updraftplus');
           sc_safe_remove_menu_page('duplicator');

        //   sc_safe_remove_menu_page('wc-admin');
        //   sc_safe_remove_menu_page('edit.php?post_type=product');
        //   sc_safe_remove_menu_page('edit.php?post_type=shop_coupon');
        //   sc_safe_remove_menu_page('wc-settings');
}
}

    /**
     * ===============================
     * مدیریت باشگاه -  جلوگیری از دسترسی مستقیم (SECURITY)
     * ===============================
     */
    add_action('admin_menu', 'club_block_restricted_pages_for_club_coach');
    function club_block_restricted_pages_for_club_coach() {
        if ( current_user_can('club_coach')) {
           

            $blocked_pages = array(
            // وردپرس
            'plugins.php',
            'plugin-editor.php',
            'themes.php',
           // 'upload.php',
            //'edit.php',
           // 'edit-tags.php',
            //'post-new.php',
           // 'edit-comments.php',
            'options-general.php',
            'tools.php',
            'options-writing.php',
            'options-reading.php',
            'options-media.php',
            'options-privacy.php',
          //  'media-new.php',
          //  'edit.php?post_type=page',
          //  'post-new.php?post_type=page',
          //  'users.php',
          //  'options-permalink.php',

            // المنتور
            'elementor',
            'hello-elementor',
            'duplicator-getting-started',
            'duplicator',

            // ووکامرس اصلی
         //   'wc-admin',
        // 'wc-orders',
            'wc-settings',
            'wc-status',
        //  'wc-reports',
        //    'coupons-moved',

            // ووکامرس admin + analytics + marketing
        //     '/analytics',
        //     '/analytics/overview',
        //     '/analytics/products',
        //     '/analytics/orders',
        //     '/analytics/variations',
        //     '/analytics/categories',
        //     '/analytics/taxes',
        //     '/analytics/coupons',
        //     '/analytics/stock',
        //     '/analytics/settings',
        //     '/analytics/downloads',
        //     '/analytics/revenue',
        //     '/marketing',

            // محصولات و کوپن‌ها
        // 'product',
        // 'shop_coupon'
            );
                 
          
        
            $page = $_GET['page'] ?? '';
            $path = $_GET['path'] ?? '';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $post_type = $_GET['post_type'] ?? '';
         

        foreach ( $blocked_pages as $blocked ) {
            if (
                 (
                strpos($page, $blocked) !== false ||
                strpos($path, $blocked) !== false ||
                strpos($post_type, $blocked) !== false ||
                strpos($uri, $blocked) !== false
             )
            
              ) {
                wp_die(
                    '
                    <div >
                    <h2 style="text-align: left;">Access Denied</h2>
                    <p style="text-align: left;">نقش شما مدیر باشگاه یا مدیر سامانه است - شما به این بخش دسترسی ندارید </p>',
                    'خطای دسترسی',
                    array('response' => 403)
                );
            }
        }
    }
    }












//حسابدار
// /**
//  * ===============================
//  * مخفی کردن منوها (UI) برای حسابدار
//  * ===============================
//  */
 add_action('admin_menu', 'club_hide_menus_for_accountant', 999);
    function club_hide_menus_for_accountant() {
        
        // اگر کاربر مربی است، فقط منوهای حضور و غیاب، دستمزد، افتخارات، اطلاعیه‌ها، دوره‌های من، بازیکن‌های من، اطلاعات من را نگه دار (مدیر باشگاه و ادمین دسترسی کامل دارند)
        if ( current_user_can('accountantt')  && ! current_user_can('administrator') ) {
            // حذف تمام منوها به جز حضور و غیاب، دستمزد/کیف پول و افتخارات
            global $menu;
        
        //print_r($menu);
            if (!is_array($menu)) {
                return;
            }
            foreach ($menu as $key => $item) {
                
                if (isset($item[2])) {
            
                    // منوی دستمزد و کیف پول فقط در صورت فعال بودن امکانات پرو نمایش داده می‌شود
                    if (
                        $item[2] !== 'sc-invoices' && 
                        $item[2] !== 'sc-wallet' && 
                        $item[2] !== 'sc-reports' && 
                        $item[2] !== 'sc-notifications' &&
                        $item[2] !== 'sc-coach-management' &&
                        $item[2] !== 'sc-ticket'&&
                        $item[2] !== 'sc-support-tickets' &&
                        $item[2] !== 'woocommerce'&&
                        $item[2] !== 'woocommerce-marketing' &&
                        $item[2] !== 'sc_orders' &&
                        $item[2] !== 'edit.php?post_type=product' &&
                        $item[2] !== 'page=wc-admin' &&
                        $item[2] !== 'wc-admin&path=/analytics/overview' 

                
                    ) {
                        sc_safe_remove_menu_page($item[2]);
                    }
                }
            }

            
            // حذف منوهای وردپرس
            sc_safe_remove_menu_page('plugins.php');
            sc_safe_remove_menu_page('themes.php');
            sc_safe_remove_menu_page('edit.php');
            sc_safe_remove_menu_page('edit.php?post_type=page');
            sc_safe_remove_menu_page('edit-comments.php');
            sc_safe_remove_menu_page('options-general.php');
            sc_safe_remove_menu_page('tools.php');
            
            // حذف منوهای المنتور
            sc_safe_remove_menu_page('elementor');
            sc_safe_remove_menu_page('edit.php?post_type=elementor_library');
            sc_safe_remove_menu_page('hello-elementor');
    
            // حذف منوهای ووکامرس
          sc_safe_remove_menu_page('woocommerce');
        //  sc_safe_remove_menu_page('wc-settings');
            
            // حذف منوهای افزونه
            sc_safe_remove_menu_page('sc-dashboard');
        // sc_safe_remove_menu_page('sc-members');
            sc_safe_remove_menu_page('sc-courses');
            sc_safe_remove_menu_page('sc-coaches');
            sc_safe_remove_menu_page('sc-events');
        // sc_safe_remove_menu_page('sc-invoices');
        // sc_safe_remove_menu_page('sc-reports');
            sc_safe_remove_menu_page('sc_setting');
            
            return;
    
        }
    }

    /**
     * ===============================
     * حسابدار -  جلوگیری از دسترسی مستقیم (SECURITY)
     * ===============================
     */
    add_action('admin_menu', 'club_block_restricted_pages_for_accountant');
    function club_block_restricted_pages_for_accountant() {
        if ( current_user_can('accountantt')) {
            $allowed_pages = [
                'sc-attendance-list_report',
                'sc-reports',
                'sc-reports-income-expenses',
                'sc-reports-bi-analytics',
                'sc-reports-coach-performance',
                'sc-reports-weekly-schedule',
                'sc-reports-sms-log',
                'sc-notifications',
                'sc-add-notification',
                'sc-add-expense',
                'sc-expenses',
                'sc-add-invoice',
                'sc-attendance-list_report',
                'sc-invoices',
                'sc-wallet',
                'sc-wallet-charge',
                'sc-wallet-deduct',
                'sc-wallet-manage',
                'sc-reports-debtors',
                'sc-coach-management',
                'sc-coach-management-wallet',
                'sc-coach-management-salary',
                'sc-coach-management-withdrawals',
                'wc-orders',
                'coupons-moved',
                'wc-reports',
                'sc_orders',
                'sc-products',
                'wc-admin',
                'post-new.php',
                'shop_coupon',
                'product',
                'sc-support-tickets',
                'sc-support-ticket-view',
                'sc-support-ticket-new',
                'sc-discount-codes',
                'sc-add-discount-code',
            ];

            $blocked_pages = array(
            // وردپرس
            'plugins.php',
            'plugin-editor.php',
            'themes.php',
            'upload.php',
            //'edit.php',
            'edit-tags.php',
            //'post-new.php',
            'edit-comments.php',
            'options-general.php',
            'tools.php',
            'options-writing.php',
            'options-reading.php',
            'options-media.php',
            'options-privacy.php',
            'media-new.php',
            'edit.php?post_type=page',
            'post-new.php?post_type=page',
            'users.php',
            'options-permalink.php',

            // المنتور
            'elementor',
            'hello-elementor',

            // ووکامرس اصلی
         //   'wc-admin',
        // 'wc-orders',
            'wc-settings',
            'wc-status',
        //  'wc-reports',
        //    'coupons-moved',

            // ووکامرس admin + analytics + marketing
        //     '/analytics',
        //     '/analytics/overview',
        //     '/analytics/products',
        //     '/analytics/orders',
        //     '/analytics/variations',
        //     '/analytics/categories',
        //     '/analytics/taxes',
        //     '/analytics/coupons',
        //     '/analytics/stock',
        //     '/analytics/settings',
        //     '/analytics/downloads',
        //     '/analytics/revenue',
        //     '/marketing',

            // محصولات و کوپن‌ها
        // 'product',
        // 'shop_coupon'
            );
                 
            // وقتی امکانات «کیف پول مربیان و دستمزد» غیرفعال است، مدیریت مربیان و دستمزد مسدود می‌شوند
            if ( ! ( function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') && sc_is_pro_feature_coaches_wallet_salary_enabled() ) ) {
                $blocked_pages = array_merge($blocked_pages, [
                    'sc-coach-management',
                    'sc-coach-salary',
                    'sc-coach-wallet',
                    'sc-coach-withdrawals',
                ]);
            }

        
            $page = $_GET['page'] ?? '';
            $path = $_GET['path'] ?? '';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $post_type = $_GET['post_type'] ?? '';
         

        foreach ( $blocked_pages as $blocked ) {
            if (
                 (
                strpos($page, $blocked) !== false ||
                strpos($path, $blocked) !== false ||
                strpos($post_type, $blocked) !== false ||
                strpos($uri, $blocked) !== false
             ) ||
             (
               is_admin() && !empty($page) && !in_array($page, $allowed_pages) 
             )
              ) {
                wp_die(
                    '
                    <div >
                    <h2 style="text-align: left;">Access Denied</h2>
                    <p style="text-align: left;">نقش شما حسابدار است - شما به این بخش دسترسی ندارید </p>',
                    'خطای دسترسی',
                    array('response' => 403)
                );
            }
        }
    }
    }










//مربی
/**
 * ===============================
 *  مخفی کردن منوها  برای مربی
 * (UI)
 * ===============================
 */
add_action('admin_menu', 'club_hide_menus_for_coach', 999);
function club_hide_menus_for_coach() {
     if ( current_user_can('coach') ) {
    // اگر کاربر مربی است، فقط منوهای حضور و غیاب، دستمزد، افتخارات، اطلاعیه‌ها، دوره‌های من، بازیکن‌های من، اطلاعات من را نگه دار (مدیر باشگاه و ادمین دسترسی کامل دارند)
    if ( current_user_can('coach') && ! current_user_can('administrator') && ! current_user_can('club_coach') ) {
        // حذف تمام منوها به جز حضور و غیاب، دستمزد/کیف پول و افتخارات
        global $menu;
        
        // حذف تمام منوهای اصلی به جز حضور و غیاب، دستمزد/کیف پول (در صورت فعال بودن امکانات پرو) و افتخارات
        if (!is_array($menu)) {
            return;
        }
        $keep_salary = function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') && sc_is_pro_feature_coaches_wallet_salary_enabled();
        foreach ($menu as $key => $item) {
            if (isset($item[2])) {
                // منوی دستمزد و کیف پول فقط در صورت فعال بودن امکانات پرو نمایش داده می‌شود
                if (
                    $item[2] !== 'sc-attendance-add' &&
                    $item[2] !== 'index.php' &&
                    ($item[2] !== 'sc-coach-salary' || !$keep_salary) &&
                    $item[2] !== 'sc-coach-honors' &&
                    $item[2] !== 'sc-coach-notifications' &&
                    $item[2] !== 'sc-coach-my-courses' &&
                    $item[2] !== 'sc-coach-my-players' &&
                    $item[2] !== 'sc-coach-my-profile' &&
                    $item[2] !== 'sc-coach-support-tickets' &&
                    $item[2] !== 'sc-coach-private-notes' &&
                    $item[2] !== 'sc-coach-private-classes' &&
                    $item[2] !== 'sc-coach-surveys'
                ) {
                    sc_safe_remove_menu_page($item[2]);
                }
            }
        }
        
        // حذف منوهای وردپرس
        sc_safe_remove_menu_page('plugins.php');
        sc_safe_remove_menu_page('themes.php');
        sc_safe_remove_menu_page('edit.php');
        sc_safe_remove_menu_page('edit.php?post_type=page');
        sc_safe_remove_menu_page('edit-comments.php');
        sc_safe_remove_menu_page('options-general.php');
        sc_safe_remove_menu_page('tools.php');
        
        // حذف منوهای المنتور
        sc_safe_remove_menu_page('elementor');
        sc_safe_remove_menu_page('edit.php?post_type=elementor_library');
        sc_safe_remove_menu_page('hello-elementor');
        
        // حذف منوهای ووکامرس
        sc_safe_remove_menu_page('woocommerce');
        sc_safe_remove_menu_page('wc-admin');
        sc_safe_remove_menu_page('edit.php?post_type=product');
        sc_safe_remove_menu_page('edit.php?post_type=shop_coupon');
        sc_safe_remove_menu_page('wc-settings');
        
        // حذف منوهای افزونه
        sc_safe_remove_menu_page('sc-dashboard');
        sc_safe_remove_menu_page('sc-members');
        sc_safe_remove_menu_page('sc-courses');
        sc_safe_remove_menu_page('sc-coaches');
        sc_safe_remove_menu_page('sc-events');
        sc_safe_remove_menu_page('sc-invoices');
        sc_safe_remove_menu_page('sc-reports');
        sc_safe_remove_menu_page('sc_setting');
        
        return;
    }


}
}

/**
 * ===============================
 * مربی -  جلوگیری از دسترسی مستقیم (SECURITY)
 * ===============================
 */
add_action('admin_init', 'club_block_restricted_pages_for_coach');
function club_block_restricted_pages_for_coach() {
if ( current_user_can('coach') ) {

 

        $allowed_pages = [
            'sc-attendance-add',
            'sc-view-member',
            'sc-attendance-list',
            'sc-attendance-session-cancellations',
            'sc-coach-honors',
            'sc-coach-notifications',
            'sc-coach-notifications-list',
            'sc-coach-add-notification',
            'sc-coach-my-courses',
            'sc-coach-my-players',
            'sc-coach-my-profile',
            'sc-coach-support-tickets',
            'sc-coach-support-ticket-view',
            'sc-coach-support-ticket-new',
            'sc-coach-private-notes',
            'sc-coach-add-private-note',
            'sc-private-notes-view',
            'sc-coach-salary',
            'sc-coach-wallet',
            'sc-coach-withdrawals',
            'sc-attendance-report',
            'sc-coach-weekly-schedule',
            'sc-coach-list-privet-class',
            'sc-private-bookings-list',
            'sc-coach-private-classes',
            'sc-coach-surveys'
           
        ];

           $blocked_pages = array(
            // وردپرس
            'plugins.php',
            'plugin-editor.php',
            'themes.php',
            'upload.php',
            'edit.php',
            'edit-tags.php',
            'post-new.php',
            'edit-comments.php',
            'options-general.php',
            'tools.php',
            'options-writing.php',
            'options-reading.php',
            'options-media.php',
            'options-privacy.php',
            'media-new.php',
            'edit.php?post_type=page',
            'post-new.php?post_type=page',
            'users.php',
            'options-permalink.php',

            // المنتور
            'elementor',
            'hello-elementor',

            // ووکامرس اصلی
            'wc-admin',
            'wc-orders',
            'wc-settings',
            'wc-status',
            'wc-reports',
            'coupons-moved',

            // ووکامرس admin + analytics + marketing
            '/analytics',
            '/analytics/overview',
            '/analytics/products',
            '/analytics/orders',
            '/analytics/variations',
            '/analytics/categories',
            '/analytics/taxes',
            '/analytics/coupons',
            '/analytics/stock',
            '/analytics/settings',
            '/analytics/downloads',
            '/analytics/revenue',
            '/marketing',

            // محصولات و کوپن‌ها
        // 'product',
        // 'shop_coupon'
            );
                 
            // وقتی امکانات «کیف پول مربیان و دستمزد» غیرفعال است، مدیریت مربیان و دستمزد مسدود می‌شوند
            if ( ! ( function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') && sc_is_pro_feature_coaches_wallet_salary_enabled() ) ) {
                $blocked_pages = array_merge($blocked_pages, [
                    'sc-coach-management',
                    'sc-coach-salary',
                    'sc-coach-wallet',
                    'sc-coach-withdrawals',
                ]);
            }

        
            $page = $_GET['page'] ?? '';
            $path = $_GET['path'] ?? '';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $post_type = $_GET['post_type'] ?? '';

            if (is_admin() && $page !== '' && !in_array($page, $allowed_pages, true)) {
                wp_die(
                    '
                    <div >
                    <h2 style="text-align: left;">Access Denied</h2>
                    <p style="text-align: left;">نقش شما مربی است - شما به این بخش دسترسی ندارید </p>',
                    'خطای دسترسی',
                    array('response' => 403)
                );
            }

            foreach ($blocked_pages as $blocked) {
                if (
                    strpos($page, $blocked) !== false ||
                    strpos($path, $blocked) !== false ||
                    strpos($post_type, $blocked) !== false ||
                    strpos($uri, $blocked) !== false
                ) {
                    wp_die(
                        '
                    <div >
                    <h2 style="text-align: left;">Access Denied</h2>
                    <p style="text-align: left;">نقش شما مربی است - شما به این بخش دسترسی ندارید </p>',
                        'خطای دسترسی',
                        array('response' => 403)
                    );
                }
            }
}
}


























// حذف ابزارک های پیشخوان
add_action('wp_dashboard_setup', 'club_remove_all_dashboard_widgets', 999);

function club_remove_all_dashboard_widgets() {

    if ( ! (current_user_can('club_coach') || current_user_can('coach') || current_user_can('shop_manager') || current_user_can('accountantt') || current_user_can('secretary') ) || current_user_can('administrator')  ) {
        return;
    }

    global $wp_meta_boxes;

    // حذف همه ابزارک‌ها
    $wp_meta_boxes['dashboard'] = array();
}
/**
 * ===============================
 * حذف نقش‌های غیرضروری + تغییر نام subscriber
 * ===============================
 */
add_action('init', 'club_cleanup_roles');
function club_cleanup_roles() {

    $keep_roles = array_merge(
        ['administrator', 'subscriber', 'coach', 'accountantt', 'shop_manager', 'secretary'],
        sc_get_club_manager_role_slugs()
    );

    global $wp_roles;
    if ( ! isset($wp_roles) ) $wp_roles = new WP_Roles();

    foreach ( $wp_roles->roles as $role_key => $role_data ) {
        if ( ! in_array($role_key, $keep_roles) ) {
            remove_role($role_key);
        }
    }

    // تغییر نام subscriber → بازیکن
    if ( isset($wp_roles->roles['subscriber']) ) {
        $wp_roles->roles['subscriber']['name'] = 'بازیکن';
        $wp_roles->role_names['subscriber'] = 'بازیکن';
    }
}

add_filter('woocommerce_admin_disabled', 'club_disable_wc_admin_for_coach');

function club_disable_wc_admin_for_coach( $disabled ) {
    if ( current_user_can('coach') ) {

    if ( !current_user_can('club_coach') && ! current_user_can('administrator') ) {
        return true; // wc-admin کامل خاموش
    }

    return $disabled;
}
}
add_action('admin_head', 'club_hide_wc_payment_menu_with_css');
function club_hide_wc_payment_menu_with_css() {

    if ( ! current_user_can('club_coach') && ! current_user_can('coach') && ! current_user_can('accountantt') ) {
        return;
    }
    ?>
    <style>
        /* حذف منوی پرداخت ووکامرس */
        #toplevel_page_admin-page-wc-settings-tab-checkout-from-PAYMENTS_MENU_ITEM ,
        #toplevel_page_woocommerce-marketing ,
        #wp-admin-bar-elementor_inspector,
        #wp-admin-bar-customize,
        #wp-admin-bar-updates,
        #wp-admin-bar-comments,
        #wp-admin-bar-new-content,
        li[id*="wc-settings-tab-checkout"],
        li[id*="PAYMENTS_MENU_ITEM"] {
            display: none !important;
        }
       
    </style>
    <?php
}




/**
 * ===============================
 * منشی — مخفی کردن منوهای غیرمجاز
 * ===============================
 */
add_action('admin_menu', 'club_hide_menus_for_secretary', 999);
function club_hide_menus_for_secretary() {
    if (!function_exists('sc_user_is_secretary_only') || !sc_user_is_secretary_only()) {
        return;
    }
    global $menu;
    if (!is_array($menu)) {
        return;
    }
    $allowed = function_exists('sc_secretary_get_allowed_admin_pages')
        ? sc_secretary_get_allowed_admin_pages()
        : [];
    foreach ($menu as $key => $item) {
        if (!isset($item[2])) {
            continue;
        }
        $slug = (string) $item[2];
        if (!in_array($slug, $allowed, true)) {
            sc_safe_remove_menu_page($slug);
        }
    }
    sc_safe_remove_menu_page('plugins.php');
    sc_safe_remove_menu_page('themes.php');
    sc_safe_remove_menu_page('edit.php');
    sc_safe_remove_menu_page('edit.php?post_type=page');
    sc_safe_remove_menu_page('edit-comments.php');
    sc_safe_remove_menu_page('options-general.php');
    sc_safe_remove_menu_page('tools.php');
    sc_safe_remove_menu_page('users.php');
    sc_safe_remove_menu_page('elementor');
    sc_safe_remove_menu_page('woocommerce');
    sc_safe_remove_menu_page('wc-admin');
    sc_safe_remove_menu_page('edit.php?post_type=product');
    sc_safe_remove_menu_page('wc-settings');
    sc_safe_remove_menu_page('sc-coaches');
    sc_safe_remove_menu_page('sc_setting');
    sc_safe_remove_menu_page('sc-surveys');
    sc_safe_remove_menu_page('sc-public-announcement');
    sc_safe_remove_menu_page('sc-certificates-issue');
    sc_safe_remove_menu_page('sc-wallet');
    sc_safe_remove_menu_page('sc-coach-management');
}

/**
 * ===============================
 * منشی — جلوگیری از دسترسی مستقیم URL
 * ===============================
 */
add_action('admin_init', 'club_block_restricted_pages_for_secretary');
function club_block_restricted_pages_for_secretary() {
    if (!function_exists('sc_user_is_secretary_only') || !sc_user_is_secretary_only()) {
        return;
    }
    if (!isset($_GET['page'])) {
        return;
    }
    $page = sanitize_text_field(wp_unslash((string) $_GET['page']));
    $allowed = function_exists('sc_secretary_get_allowed_admin_pages')
        ? sc_secretary_get_allowed_admin_pages()
        : [];
    if (in_array($page, $allowed, true)) {
        return;
    }
    if (function_exists('sc_secretary_die_access_denied')) {
        sc_secretary_die_access_denied(
            'ورود به صفحه «' . $page . '»',
            'نقش شما منشی شعبه است و این بخش خارج از دسترسی منشی قرار دارد. فقط منوها و صفحات مرتبط با شعبه خودتان برای شما باز هستند.'
        );
    }
    wp_die(
        '<div style="max-width:640px;margin:40px auto;padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;direction:rtl;text-align:right;">'
        . '<h2 style="margin:0 0 8px;color:#b91c1c;">دسترسی مجاز نیست</h2>'
        . '<p style="margin:0;line-height:1.9;">به‌خاطر نقش <strong>منشی</strong>، به این بخش دسترسی ندارید.</p>'
        . '<p style="margin:12px 0 0;"><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=sc-members')) . '">بازگشت به لیست بازیکنان</a></p>'
        . '</div>',
        'محدودیت دسترسی منشی',
        ['response' => 403]
    );
}

//بستن دسترسی کلی بوفه و فروشگاه
add_action('admin_init', 'close_acsses_shop');
function close_acsses_shop(){

if ((!function_exists('sc_is_pro_feature_shop_enabled') || !sc_is_pro_feature_shop_enabled()) && !current_user_can('administrator')) {
    sc_safe_remove_menu_page('wc-admin');
    sc_safe_remove_menu_page('edit.php?post_type=product');
    sc_safe_remove_menu_page('edit.php?post_type=shop_coupon');
    sc_safe_remove_menu_page('wc-settings');


}

}