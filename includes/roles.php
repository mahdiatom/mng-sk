<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * ===============================
 * ایجاد نقش مدیر باشگاه (DEV MODE)
 * ===============================
 */
function club_create_club_coach_role() {
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

/**
 * ===============================
 * ایجاد نقش مربی
 * ===============================
 */
function sc_create_coach_role() {
    // اگر نقش وجود ندارد، ایجاد شود
    if ( ! get_role('coach') ) {
        // استفاده از capabilities مشابه subscriber اما با دسترسی محدود
        $coach_caps = get_role('subscriber')->capabilities;
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
 * مخفی کردن منوها (UI)
 * ===============================
 */
add_action('admin_menu', 'club_hide_menus_for_coach', 999);
function club_hide_menus_for_coach() {
    
    // اگر کاربر مربی است، فقط منوهای حضور و غیاب، دستمزد، افتخارات، اطلاعیه‌ها، دوره‌های من، بازیکن‌های من، اطلاعات من را نگه دار (مدیر باشگاه و ادمین دسترسی کامل دارند)
    if ( current_user_can('coach') && ! current_user_can('administrator') && ! current_user_can('club_coach') ) {
        // حذف تمام منوها به جز حضور و غیاب، دستمزد/کیف پول و افتخارات
        global $menu;
        
        // حذف تمام منوهای اصلی به جز حضور و غیاب، دستمزد/کیف پول (در صورت فعال بودن امکانات پرو) و افتخارات
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
                    $item[2] !== 'sc-coach-support-tickets'
                ) {
                    remove_menu_page($item[2]);
                }
            }
        }
        
        // حذف منوهای وردپرس
        remove_menu_page('plugins.php');
        remove_menu_page('themes.php');
        remove_menu_page('edit.php');
        remove_menu_page('edit.php?post_type=page');
        remove_menu_page('edit-comments.php');
        remove_menu_page('options-general.php');
        remove_menu_page('tools.php');
        
        // حذف منوهای المنتور
        remove_menu_page('elementor');
        remove_menu_page('edit.php?post_type=elementor_library');
        remove_menu_page('hello-elementor');
        
        // حذف منوهای ووکامرس
        remove_menu_page('woocommerce');
        remove_menu_page('wc-admin');
        remove_menu_page('edit.php?post_type=product');
        remove_menu_page('edit.php?post_type=shop_coupon');
        remove_menu_page('wc-settings');
        
        // حذف منوهای افزونه
        remove_menu_page('sc-dashboard');
        remove_menu_page('sc-members');
        remove_menu_page('sc-courses');
        remove_menu_page('sc-coaches');
        remove_menu_page('sc-events');
        remove_menu_page('sc-invoices');
        remove_menu_page('sc-reports');
        remove_menu_page('sc_setting');
        
        return;
    }

    if ( ! current_user_can('club_coach') || current_user_can('administrator') ) return;

    // وردپرس
    remove_menu_page('plugins.php');
    remove_menu_page('themes.php');
    remove_menu_page('edit.php');
    remove_menu_page('edit.php?post_type=page');
    remove_menu_page('edit-comments.php');
    remove_menu_page('options-general.php');
    remove_menu_page('tools.php');

    // المنتور
    remove_menu_page('elementor');
    remove_menu_page('edit.php?post_type=elementor_library');
    remove_menu_page('hello-elementor');

    // ووکامرس (کامل)
    remove_menu_page('woocommerce');
    remove_menu_page('wc-admin');
    remove_menu_page('edit.php?post_type=product');
    remove_menu_page('edit.php?post_type=shop_coupon');
    remove_menu_page('wc-settings');
}

/**
 * ===============================
 * جلوگیری از دسترسی مستقیم (SECURITY)
 * ===============================
 */
add_action('admin_init', 'club_block_restricted_pages_for_coach');
function club_block_restricted_pages_for_coach() {

    // اگر کاربر مربی است، فقط دسترسی به صفحات مجاز (مدیر باشگاه و ادمین دسترسی کامل دارند)
    if ( current_user_can('coach') && ! current_user_can('administrator') && ! current_user_can('club_coach') ) {
        $allowed_pages = [
            'sc-attendance-add',
            'sc-view-member',
            'sc-attendance-list',
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
        ];

        // صفحات دستمزد و کیف پول فقط در صورت فعال بودن امکانات پرو
        if ( function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') && sc_is_pro_feature_coaches_wallet_salary_enabled() ) {
            $allowed_pages = array_merge($allowed_pages, ['sc-coach-salary', 'sc-coach-wallet', 'sc-coach-withdrawals']);
        }
        
        $page = $_GET['page'] ?? '';
        $path = $_GET['path'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // اگر در صفحه ادمین هستیم و صفحه مجاز نیست
        if (is_admin() && !empty($page) && !in_array($page, $allowed_pages)) {
            // بررسی اینکه آیا صفحه اصلی dashboard است یا نه
            if ($page !== 'index.php' && strpos($uri, 'sc-attendance') === false && strpos($uri, 'sc-coach') === false) {
                wp_die(
                    '<div><h2 style="text-align: left;">Access Denied</h2><p style="text-align: left;">شما فقط به بخش حضور و غیاب، دستمزد و افتخارات دسترسی دارید.</p></div>',
                    'خطای دسترسی',
                    array('response' => 403)
                );
            }
        }
        return;
    }
    
    if ( ! current_user_can('club_coach') || current_user_can('administrator') ) return;

    $blocked_pages = array(
        // وردپرس
        'plugins.php',
        'plugin-editor.php',
        'themes.php',
        'edit.php',
        'edit-comments.php',
        'options-general.php',
        'tools.php',
        'options-writing.php',
        'options-reading.php',
        'options-media.php',
        'options-privacy.php',

        // المنتور
        'elementor',
        'hello-elementor',

        // ووکامرس اصلی
        'wc-admin',
       // 'wc-orders',
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
        'product',
        'shop_coupon',
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

    $page      = $_GET['page']      ?? '';
    $path      = $_GET['path']      ?? '';
    $post_type = $_GET['post_type'] ?? '';
    $uri       = $_SERVER['REQUEST_URI'];

    foreach ( $blocked_pages as $blocked ) {
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
                <p style="text-align: left;">You have access to this section.</p>',
                'خطای دسترسی',
                array('response' => 403)
            );
        }
    }
}

/**
 * ===============================
 * حذف نقش‌های غیرضروری + تغییر نام subscriber
 * ===============================
 */
add_action('init', 'club_cleanup_roles');
function club_cleanup_roles() {

    $keep_roles = array('administrator','subscriber','club_coach','coach');

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

    if ( current_user_can('club_coach') && ! current_user_can('administrator') ) {
        return true; // wc-admin کامل خاموش
    }

    return $disabled;
}
add_action('admin_head', 'club_hide_wc_payment_menu_with_css');
function club_hide_wc_payment_menu_with_css() {

    if ( ! current_user_can('club_coach') && ! current_user_can('coach') ) {
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
// حذف ابزارک های پیشخوان
add_action('wp_dashboard_setup', 'club_remove_all_dashboard_widgets', 999);

function club_remove_all_dashboard_widgets() {

    if ( ! (current_user_can('club_coach') || current_user_can('coach') )|| current_user_can('administrator') ) {
        return;
    }

    global $wp_meta_boxes;

    // حذف همه ابزارک‌ها
    $wp_meta_boxes['dashboard'] = array();
}



//حذف دسترسی های اضافی برای ووکامرس و کاربر عادی در افزودن کاربر وردپرس 




