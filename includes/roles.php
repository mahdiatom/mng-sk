<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * ===============================
 * ایجاد نقش مدیر باشگاه (DEV MODE)
 * ===============================
 */
add_action('init', 'club_setup_club_coach_role_for_dev');
function club_setup_club_coach_role_for_dev() {

    // DEV: حذف و ایجاد مجدد نقش
    if ( get_role('club_coach') ) {
        remove_role('club_coach');
    }

    $admin_role = get_role('administrator');
    if ( ! $admin_role ) return;

    add_role(
        'club_coach',
        'مدیر باشگاه',
        $admin_role->capabilities
    );
}

/**
 * ===============================
 * مخفی کردن منوها (UI)
 * ===============================
 */
add_action('admin_menu', 'club_hide_menus_for_coach', 999);
function club_hide_menus_for_coach() {

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
        'options-permalink.php',
        'options-writing.php',
        'options-reading.php',
        'options-media.php',
        'options-permalink.php',
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

    $keep_roles = array('administrator','subscriber','club_coach');

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

    if ( ! current_user_can('club_coach') || current_user_can('administrator') ) {
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

    if ( ! current_user_can('club_coach') || current_user_can('administrator') ) {
        return;
    }

    global $wp_meta_boxes;

    // حذف همه ابزارک‌ها
    $wp_meta_boxes['dashboard'] = array();
}



//حذف دسترسی های اضافی برای ووکامرس و کاربر عادی در افزودن کاربر وردپرس 




